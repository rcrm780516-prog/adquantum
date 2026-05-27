"""
main.py — AdQuantum Backend API v2
FastAPI completo con autenticación Google, multi-cliente y soporte Meta + Google Ads.
Ejecutar: uvicorn main:app --reload --host 0.0.0.0 --port 8000
"""

import logging
import os
import uuid
from contextlib import asynccontextmanager
from datetime import datetime
from typing import Optional

from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException, Depends, Request, UploadFile, File
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import RedirectResponse
from pydantic import BaseModel
from sqlalchemy.orm import Session
import base64

load_dotenv()

logging.basicConfig(level=logging.INFO, format="%(asctime)s | %(levelname)s | %(name)s | %(message)s")
logger = logging.getLogger("adquantum.api")

FRONTEND_URL = os.getenv("FRONTEND_URL", "http://localhost:5173")


@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("🚀 AdQuantum API v2 iniciando...")
    from modules.database import create_tables
    create_tables()
    try:
        if os.getenv("ENVIRONMENT") == "development":
            from modules.vector_db import seed_database
            seed_database()
    except Exception as exc:
        logger.warning("Vector DB no disponible: %s", exc)
    logger.info("✅ API lista.")
    yield
    logger.info("👋 API cerrándose.")


app = FastAPI(title="AdQuantum API", version="2.0.0", lifespan=lifespan)

app.add_middleware(
    CORSMiddleware,
    allow_origins=[FRONTEND_URL, "http://localhost:3000", "http://localhost:5173"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


def get_db():
    from modules.database import SessionLocal
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()


def get_current_client(request: Request, db: Session = Depends(get_db)):
    from modules.auth.google_auth import verify_session_token
    from modules.database import Client
    auth = request.headers.get("Authorization", "")
    if not auth.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Token de sesión requerido.")
    payload = verify_session_token(auth.replace("Bearer ", ""))
    if not payload:
        raise HTTPException(status_code=401, detail="Token inválido o expirado.")
    client = db.query(Client).filter(Client.id == payload["sub"]).first()
    if not client or not client.is_active:
        raise HTTPException(status_code=401, detail="Cliente no encontrado.")
    return client


# HEALTH
@app.get("/", tags=["health"])
def root():
    return {"service": "AdQuantum API", "version": "2.0.0", "status": "online"}


# AUTH
@app.get("/auth/google", tags=["auth"])
def google_login():
    from modules.auth.google_auth import get_google_auth_url
    import secrets
    return RedirectResponse(get_google_auth_url(state=secrets.token_urlsafe(16)))


@app.get("/auth/google/callback", tags=["auth"])
async def google_callback(code: str, db: Session = Depends(get_db)):
    from modules.auth.google_auth import exchange_code_for_token, get_google_user_info, create_session_token, upsert_client_from_google
    try:
        tokens = await exchange_code_for_token(code)
        user_info = await get_google_user_info(tokens["access_token"])
        client = upsert_client_from_google(db, user_info)
        session_token = create_session_token(client.id, client.email)
        return RedirectResponse(f"{FRONTEND_URL}/dashboard?token={session_token}&name={client.name}")
    except Exception as exc:
        logger.exception("Error en Google callback: %s", exc)
        raise HTTPException(status_code=400, detail=str(exc))


@app.get("/auth/me", tags=["auth"])
def get_me(client=Depends(get_current_client)):
    return {
        "id": client.id, "name": client.name, "email": client.email,
        "picture": client.picture_url, "niche": client.niche,
        "business_name": client.business_name, "city": client.city,
        "created_at": client.created_at.isoformat() if client.created_at else None,
    }


# ONBOARDING
class OnboardingRequest(BaseModel):
    niche: str
    business_name: str
    city: str
    country: str = "MX"
    meta_ad_account_id: Optional[str] = None
    google_ads_customer_id: Optional[str] = None


@app.post("/api/clients/onboarding", tags=["clients"])
def complete_onboarding(data: OnboardingRequest, client=Depends(get_current_client), db: Session = Depends(get_db)):
    from modules.database import AdAccount
    client.niche = data.niche
    client.business_name = data.business_name
    client.city = data.city
    client.country = data.country
    accounts = []

    if data.meta_ad_account_id:
        account_id = data.meta_ad_account_id.strip()
        if not account_id.startswith("act_"):
            account_id = f"act_{account_id}"
        existing = db.query(AdAccount).filter(AdAccount.client_id == client.id, AdAccount.platform == "META").first()
        if existing:
            existing.account_id = account_id
        else:
            db.add(AdAccount(id=str(uuid.uuid4()), client_id=client.id, platform="META", account_id=account_id, account_name=f"Meta Ads — {data.business_name}", is_verified=False))
        accounts.append({"platform": "META", "account_id": account_id})

    if data.google_ads_customer_id:
        customer_id = data.google_ads_customer_id.replace("-", "").strip()
        existing = db.query(AdAccount).filter(AdAccount.client_id == client.id, AdAccount.platform == "GOOGLE_ADS").first()
        if existing:
            existing.account_id = customer_id
        else:
            db.add(AdAccount(id=str(uuid.uuid4()), client_id=client.id, platform="GOOGLE_ADS", account_id=customer_id, account_name=f"Google Ads — {data.business_name}", is_verified=False))
        accounts.append({"platform": "GOOGLE_ADS", "account_id": customer_id})

    db.commit()
    return {"message": "Perfil configurado.", "client_id": client.id, "accounts": accounts}


# BRIEF
class BriefRequest(BaseModel):
    product_name: str
    description: str
    target_audience: str
    budget_daily_usd: float
    geo: str
    brand_colors: Optional[str] = None
    brand_style: Optional[str] = None
    platform: str = "META"


@app.post("/api/brief/process", tags=["brief"])
def process_brief_endpoint(brief: BriefRequest, client=Depends(get_current_client)):
    from modules.brief_processor import process_brief, BriefInput
    from modules.vector_db import search_winning_pattern
    try:
        patterns = search_winning_pattern(f"{brief.product_name} {brief.description}", niche=client.niche, top_k=3)
        strategy = process_brief(BriefInput(**brief.model_dump(exclude={"platform"})))
        return {
            "client_id": client.id,
            "strategy": strategy.model_dump(),
            "vector_db_matches": [{"pattern_id": r.pattern.id, "format": r.pattern.format, "visual_style": r.pattern.visual_style, "ctr_score": r.pattern.ctr_score, "similarity": round(r.similarity_score, 3)} for r in patterns],
        }
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc))


# CREATIVES
class GenerateImagesRequest(BaseModel):
    image_prompts: list[str]
    reference_image_b64: Optional[str] = None


@app.post("/api/creatives/generate", tags=["creatives"])
def generate_creatives_endpoint(req: GenerateImagesRequest, client=Depends(get_current_client)):
    from modules.creative_gen import generate_campaign_creatives, enhance_prompt_for_niche
    try:
        enriched = [enhance_prompt_for_niche(p, client.niche or "general") for p in req.image_prompts]
        results = generate_campaign_creatives(enriched, req.reference_image_b64)
        return {"generated": sum(1 for r in results if r["status"] == "success"), "total": len(req.image_prompts), "creatives": results}
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc))


@app.post("/api/creatives/upload-reference", tags=["creatives"])
async def upload_reference(file: UploadFile = File(...), client=Depends(get_current_client)):
    contents = await file.read()
    return {"filename": file.filename, "image_b64": base64.b64encode(contents).decode(), "size_kb": round(len(contents) / 1024, 1)}


# CAMPAIGNS
class LaunchMetaRequest(BaseModel):
    name: str
    budget_daily_usd: float
    headline: str
    body_text: str
    destination_url: str
    page_id: str
    image_b64: Optional[str] = None
    image_prompt_used: Optional[str] = None
    ad_copy_used: Optional[str] = None
    db_pattern_reference: Optional[str] = None
    ai_confidence_score: Optional[float] = None


class LaunchGoogleRequest(BaseModel):
    name: str
    budget_daily_usd: float
    keywords: list[str]
    headlines: list[str]
    descriptions: list[str]
    final_url: str
    ai_confidence_score: Optional[float] = None


@app.post("/api/campaigns/meta/launch", tags=["campaigns"])
def launch_meta(req: LaunchMetaRequest, client=Depends(get_current_client), db: Session = Depends(get_db)):
    from modules.database import AdAccount, Campaign, get_client_ad_account
    from modules.meta_trader import CampaignConfig, AdSetConfig, AdCreativeConfig, create_campaign, create_ad_set, create_ad_creative
    account = get_client_ad_account(db, client.id, "META")
    if not account:
        raise HTTPException(status_code=400, detail="Cuenta Meta no configurada. Completa el onboarding.")
    os.environ["META_AD_ACCOUNT_ID"] = account.account_id
    try:
        campaign_id = create_campaign(CampaignConfig(name=req.name, budget_daily_cents=int(req.budget_daily_usd * 100), target_geo_country=client.country or "MX"))
        ad_set_id = create_ad_set(AdSetConfig(campaign_id=campaign_id, name=f"{req.name} · Set 1"))
        creative_id = create_ad_creative(AdCreativeConfig(name=f"{req.name} · Creativo 1", image_b64=req.image_b64, headline=req.headline, body_text=req.body_text, destination_url=req.destination_url, page_id=req.page_id))
        c = Campaign(id=str(uuid.uuid4()), client_id=client.id, platform="META", platform_campaign_id=campaign_id, name=req.name, niche=client.niche, budget_daily_usd=req.budget_daily_usd, status="PAUSED", image_prompt_used=req.image_prompt_used, ad_copy_used=req.ad_copy_used, db_pattern_reference=req.db_pattern_reference, ai_confidence_score=req.ai_confidence_score, created_at=datetime.utcnow())
        db.add(c)
        db.commit()
        return {"status": "CREATED_PAUSED", "campaign_id": campaign_id, "ad_set_id": ad_set_id, "creative_id": creative_id, "adquantum_id": c.id}
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc))


@app.post("/api/campaigns/google/launch", tags=["campaigns"])
def launch_google(req: LaunchGoogleRequest, client=Depends(get_current_client), db: Session = Depends(get_db)):
    from modules.database import Campaign, get_client_ad_account
    from modules.google_ads_trader import create_search_campaign
    account = get_client_ad_account(db, client.id, "GOOGLE_ADS")
    if not account:
        raise HTTPException(status_code=400, detail="Cuenta Google Ads no configurada.")
    try:
        result = create_search_campaign(customer_id=account.account_id, campaign_name=req.name, budget_daily_micros=int(req.budget_daily_usd * 1_000_000), keywords=req.keywords, headlines=req.headlines, descriptions=req.descriptions, final_url=req.final_url)
        c = Campaign(id=str(uuid.uuid4()), client_id=client.id, platform="GOOGLE_ADS", platform_campaign_id=result["campaign_resource"], name=req.name, niche=client.niche, budget_daily_usd=req.budget_daily_usd, status="PAUSED", ai_confidence_score=req.ai_confidence_score, created_at=datetime.utcnow())
        db.add(c)
        db.commit()
        return {**result, "adquantum_id": c.id}
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc))


@app.get("/api/campaigns", tags=["campaigns"])
def list_campaigns(client=Depends(get_current_client), db: Session = Depends(get_db)):
    from modules.database import Campaign
    from modules.rl_monitor import evaluate_campaign_health
    campaigns = db.query(Campaign).filter(Campaign.client_id == client.id).order_by(Campaign.created_at.desc()).all()
    result = []
    for c in campaigns:
        health = None
        if c.roas is not None and c.ctr is not None:
            h = evaluate_campaign_health(c.platform_campaign_id or c.id, c.name, c.ctr, c.roas, c.spend_total_usd or 0)
            health = h.status
        result.append({"id": c.id, "platform": c.platform, "name": c.name, "status": c.status, "budget_daily_usd": c.budget_daily_usd, "roas": c.roas, "ctr": c.ctr, "spend_total_usd": c.spend_total_usd, "leads": c.leads, "health": health, "swap_count": c.swap_count, "created_at": c.created_at.isoformat() if c.created_at else None})
    return {"campaigns": result, "total": len(result)}


# ADMIN
class AdminVerifyRequest(BaseModel):
    client_id: str
    platform: str
    admin_key: str


@app.post("/api/admin/verify-account", tags=["admin"])
def admin_verify(req: AdminVerifyRequest, db: Session = Depends(get_db)):
    if req.admin_key != os.getenv("VIRTUOSO_ADMIN_KEY", ""):
        raise HTTPException(status_code=403, detail="Acceso denegado.")
    from modules.database import AdAccount
    account = db.query(AdAccount).filter(AdAccount.client_id == req.client_id, AdAccount.platform == req.platform).first()
    if not account:
        raise HTTPException(status_code=404, detail="Cuenta no encontrada.")
    account.is_verified = True
    account.verified_at = datetime.utcnow()
    db.commit()
    return {"message": "Cuenta verificada.", "account_id": account.account_id}

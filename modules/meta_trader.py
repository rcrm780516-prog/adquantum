"""
modules/meta_trader.py
AdQuantum — Módulo de Trading Autónomo en Meta Ads
Gestiona la creación y publicación de campañas via Meta Graph API.
"""

import logging
import os
from typing import Optional

import requests
from dotenv import load_dotenv
from pydantic import BaseModel

load_dotenv()
logger = logging.getLogger(__name__)

META_GRAPH_URL = "https://graph.facebook.com/v20.0"


# ---------------------------------------------------------------------------
# Modelos de datos
# ---------------------------------------------------------------------------

class CampaignConfig(BaseModel):
    """Configuración de campaña para Meta Ads."""
    name: str
    budget_daily_cents: int        # Meta maneja presupuesto en centavos
    target_geo_country: str        # e.g. "MX", "US"
    target_geo_cities: Optional[list[str]] = None
    optimization_goal: str = "OFFSITE_CONVERSIONS"
    bid_strategy: str = "LOWEST_COST_WITH_BID_CAP"
    bid_cap_cents: Optional[int] = None
    objective: str = "OUTCOME_LEADS"


class AdSetConfig(BaseModel):
    """Configuración del Ad Set."""
    campaign_id: str
    name: str
    age_min: int = 25
    age_max: int = 65
    genders: list[int] = [1, 2]    # 1=Hombre, 2=Mujer
    interests: Optional[list[dict]] = None


class AdCreativeConfig(BaseModel):
    """Configuración del creativo de anuncio."""
    name: str
    image_b64: Optional[str] = None  # Base64 de la imagen generada
    image_url: Optional[str] = None
    headline: str
    body_text: str
    call_to_action: str = "LEARN_MORE"
    destination_url: str
    page_id: str


# ---------------------------------------------------------------------------
# Helpers de API
# ---------------------------------------------------------------------------

def _get_headers() -> dict:
    """Headers base para Meta Graph API."""
    return {"Content-Type": "application/json"}


def _get_token() -> str:
    """Obtiene el access token desde variables de entorno."""
    token = os.getenv("META_ACCESS_TOKEN")
    if not token:
        raise EnvironmentError("META_ACCESS_TOKEN no está configurado en .env")
    return token


def _get_ad_account_id() -> str:
    """Obtiene el ID de cuenta publicitaria desde variables de entorno."""
    account_id = os.getenv("META_AD_ACCOUNT_ID")
    if not account_id:
        raise EnvironmentError("META_AD_ACCOUNT_ID no está configurado en .env")
    return account_id


def _api_post(endpoint: str, payload: dict) -> dict:
    """
    Realiza un POST a Meta Graph API con manejo de errores.

    Args:
        endpoint: Endpoint relativo, e.g. "/campaigns".
        payload: Datos a enviar.

    Returns:
        Respuesta JSON de Meta.

    Raises:
        requests.HTTPError: Si Meta devuelve un error.
    """
    url = f"{META_GRAPH_URL}/{_get_ad_account_id()}{endpoint}"
    payload["access_token"] = _get_token()

    response = requests.post(url, json=payload, headers=_get_headers(), timeout=30)

    if response.status_code != 200:
        logger.error(
            "Error Meta API %d: %s", response.status_code, response.text[:300]
        )
        response.raise_for_status()

    return response.json()


# ---------------------------------------------------------------------------
# Operaciones de campaña
# ---------------------------------------------------------------------------

def create_campaign(config: CampaignConfig) -> str:
    """
    Crea una campaña en Meta Ads Manager en estado PAUSED.
    (El usuario activa manualmente — protección de presupuesto)

    Args:
        config: Configuración de la campaña.

    Returns:
        ID de la campaña creada.
    """
    payload = {
        "name": config.name,
        "objective": config.objective,
        "status": "PAUSED",    # SIEMPRE paused al crear — activación manual
        "special_ad_categories": [],
        "daily_budget": config.budget_daily_cents,
        "bid_strategy": config.bid_strategy,
    }

    if config.bid_cap_cents:
        payload["bid_amount"] = config.bid_cap_cents

    response = _api_post("/campaigns", payload)
    campaign_id = response["id"]
    logger.info("Campaña creada: %s (ID: %s)", config.name, campaign_id)
    return campaign_id


def create_ad_set(config: AdSetConfig) -> str:
    """
    Crea un Ad Set asociado a la campaña.

    Args:
        config: Configuración del ad set.

    Returns:
        ID del ad set creado.
    """
    targeting = {
        "age_min": config.age_min,
        "age_max": config.age_max,
        "genders": config.genders,
        "geo_locations": {"countries": ["MX"]},  # Ajustar dinámicamente si es necesario
    }

    if config.interests:
        targeting["flexible_spec"] = [{"interests": config.interests}]

    payload = {
        "name": config.name,
        "campaign_id": config.campaign_id,
        "billing_event": "IMPRESSIONS",
        "optimization_goal": "LEAD_GENERATION",
        "targeting": targeting,
        "status": "PAUSED",
    }

    response = _api_post("/adsets", payload)
    ad_set_id = response["id"]
    logger.info("Ad Set creado: %s (ID: %s)", config.name, ad_set_id)
    return ad_set_id


def upload_image_from_b64(image_b64: str) -> str:
    """
    Sube una imagen en base64 a la biblioteca de Meta Ads.

    Args:
        image_b64: Imagen en formato base64.

    Returns:
        Hash de la imagen en Meta (necesario para crear creativos).
    """
    url = f"{META_GRAPH_URL}/{_get_ad_account_id()}/adimages"
    payload = {
        "bytes": image_b64,
        "access_token": _get_token(),
    }
    response = requests.post(url, data=payload, timeout=30)
    response.raise_for_status()

    images_data = response.json().get("images", {})
    image_hash = list(images_data.values())[0]["hash"]
    logger.info("Imagen subida a Meta. Hash: %s", image_hash)
    return image_hash


def create_ad_creative(config: AdCreativeConfig) -> str:
    """
    Crea el creativo del anuncio (imagen + texto + CTA).

    Args:
        config: Configuración del creativo.

    Returns:
        ID del creativo creado.
    """
    image_hash = None

    if config.image_b64:
        image_hash = upload_image_from_b64(config.image_b64)

    story = {
        "page_id": config.page_id,
        "link_data": {
            "message": config.body_text,
            "name": config.headline,
            "link": config.destination_url,
            "call_to_action": {"type": config.call_to_action},
        },
    }

    if image_hash:
        story["link_data"]["image_hash"] = image_hash
    elif config.image_url:
        story["link_data"]["picture"] = config.image_url

    payload = {
        "name": config.name,
        "object_story_spec": story,
    }

    response = _api_post("/adcreatives", payload)
    creative_id = response["id"]
    logger.info("Creativo creado: %s (ID: %s)", config.name, creative_id)
    return creative_id


def get_campaign_insights(campaign_id: str) -> dict:
    """
    Obtiene métricas de rendimiento de una campaña activa.

    Args:
        campaign_id: ID de la campaña en Meta.

    Returns:
        Dict con CTR, spend, ROAS y otras métricas.
    """
    url = (
        f"{META_GRAPH_URL}/{campaign_id}/insights"
        f"?fields=spend,ctr,roas,impressions,clicks,cost_per_result"
        f"&access_token={_get_token()}"
    )
    response = requests.get(url, timeout=30)
    response.raise_for_status()
    data = response.json()

    if data.get("data"):
        metrics = data["data"][0]
        logger.info(
            "Insights campaña %s | CTR: %s | ROAS: %s",
            campaign_id,
            metrics.get("ctr", "N/A"),
            metrics.get("roas", "N/A"),
        )
        return metrics

    return {}

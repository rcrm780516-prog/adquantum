"""
modules/brief_processor.py
AdQuantum — Módulo de Procesamiento de Brief con Claude
Analiza el brief del usuario y genera la estrategia estructurada de campaña.
"""

import json
import logging
import os
from typing import Optional

import anthropic
from dotenv import load_dotenv
from pydantic import BaseModel

load_dotenv()
logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Modelos de datos
# ---------------------------------------------------------------------------

class BriefInput(BaseModel):
    """Input del usuario desde el dashboard."""
    product_name: str
    description: str
    target_audience: str
    budget_daily_usd: float
    geo: str  # "NATIONAL" | "LOCAL: Ciudad, País"
    brand_colors: Optional[str] = None   # e.g. "HSL(200,80%,50%)"
    brand_style: Optional[str] = None    # e.g. "minimalista, lujo, médico"
    reference_image_b64: Optional[str] = None  # Imagen de referencia en base64


class CampaignStrategy(BaseModel):
    """Estrategia estructurada devuelta por Claude."""
    automated_campaign_setup: dict
    generative_creatives: dict
    db_reference: str
    ai_confidence_score: float
    image_prompts: list[str]
    ad_copies: list[str]


# ---------------------------------------------------------------------------
# Lógica principal
# ---------------------------------------------------------------------------

SYSTEM_PROMPT = """
Eres el Motor Autónomo de Trading y Creativos de AdQuantum para Virtuoso Marketing.
Tu objetivo: procesar un brief de marca, simular una consulta a la Vector DB de inteligencia
competitiva y estructurar una campaña optimizada.

REGLAS OPERATIVAS:
1. Simula consulta de cosine-similarity a colección "market_intelligence".
2. Si budget < $100/día → prioriza META_ADS_MANAGER con conversión directa.
   Si budget >= $100/día → sugiere embudo completo AWARENESS → CONSIDERATION → CONVERSION.
3. Genera 3 prompts de imagen ultra-profesionales para Gemini Imagen:
   Formato: "High quality commercial photography, advertisement style, [producto], 
   [estilo/paleta], 8k resolution, crisp setup, no logo, no text"
4. Genera 3 copias de anuncio bajo fórmula AIDA.
5. Responde ÚNICAMENTE con JSON válido, sin markdown, sin explicaciones extra.

ESQUEMA DE RESPUESTA REQUERIDO:
{
  "automated_campaign_setup": {
    "platform": "META_ADS_MANAGER",
    "budget_daily": <number>,
    "target_geo": "<geo>",
    "bid_strategy": "LOWEST_COST_WITH_BID_CAP",
    "optimization_goal": "CONVERSIONS",
    "funnel_stages": ["<stage>"]
  },
  "generative_creatives": {
    "format": "HYBRID_REEL_STATIC",
    "image_prompts": ["<prompt1>", "<prompt2>", "<prompt3>"],
    "ad_copies": ["<copy1>", "<copy2>", "<copy3>"]
  },
  "db_reference": "pattern_idx_<id_simulado>",
  "ai_confidence_score": <número entre 85.0 y 99.9>
}
"""


def process_brief(brief: BriefInput) -> CampaignStrategy:
    """
    Envía el brief a Claude y devuelve la estrategia estructurada de campaña.

    Args:
        brief: Objeto BriefInput con los datos del usuario.

    Returns:
        CampaignStrategy con la estrategia completa.

    Raises:
        ValueError: Si la respuesta de Claude no es JSON válido.
        anthropic.APIError: Si falla la llamada a la API.
    """
    client = anthropic.Anthropic(api_key=os.getenv("ANTHROPIC_API_KEY"))

    user_message = f"""
BRIEF DE CAMPAÑA:
- Producto/Servicio: {brief.product_name}
- Descripción: {brief.description}
- Audiencia objetivo: {brief.target_audience}
- Presupuesto diario: ${brief.budget_daily_usd} USD
- Geografía: {brief.geo}
- Colores de marca: {brief.brand_colors or 'No especificado'}
- Estilo de marca: {brief.brand_style or 'No especificado'}

Procesa este brief y devuelve el JSON estructurado.
"""

    logger.info("Procesando brief para: %s", brief.product_name)

    response = client.messages.create(
        model="claude-sonnet-4-20250514",
        max_tokens=2048,
        system=SYSTEM_PROMPT,
        messages=[{"role": "user", "content": user_message}],
    )

    raw_text = response.content[0].text.strip()

    # Limpieza defensiva por si Claude añade backticks
    if raw_text.startswith("```"):
        raw_text = raw_text.split("```")[1]
        if raw_text.startswith("json"):
            raw_text = raw_text[4:]

    try:
        data = json.loads(raw_text)
    except json.JSONDecodeError as exc:
        logger.error("Respuesta no es JSON válido: %s", raw_text[:200])
        raise ValueError(f"Claude no devolvió JSON válido: {exc}") from exc

    # Extraer campos del JSON anidado
    creatives = data.get("generative_creatives", {})
    setup = data.get("automated_campaign_setup", {})

    strategy = CampaignStrategy(
        automated_campaign_setup=setup,
        generative_creatives=creatives,
        db_reference=data.get("db_reference", "pattern_idx_unknown"),
        ai_confidence_score=data.get("ai_confidence_score", 0.0),
        image_prompts=creatives.get("image_prompts", []),
        ad_copies=creatives.get("ad_copies", []),
    )

    logger.info(
        "Estrategia generada | confianza: %.1f%% | patrón: %s",
        strategy.ai_confidence_score,
        strategy.db_reference,
    )
    return strategy

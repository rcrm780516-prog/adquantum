"""
modules/scraper.py
AdQuantum — Scraper de Inteligencia Competitiva
Extrae anuncios activos de competidores desde Meta Ads Library API
y los indexa automáticamente en la Vector DB como patrones ganadores.

Documentación oficial Meta Ads Library API:
https://www.facebook.com/ads/library/api/
"""

import logging
import os
import time
from datetime import datetime, timedelta
from typing import Optional

import requests
from dotenv import load_dotenv

from modules.vector_db import AdPattern, index_ad_pattern, init_collection

load_dotenv()
logger = logging.getLogger(__name__)

META_GRAPH_URL = "https://graph.facebook.com/v20.0"

# Nichos mapeados a palabras clave de búsqueda en Meta Ads Library
NICHE_KEYWORDS = {
    "dermatología":         ["dermatólogo", "dermatología", "tratamiento piel", "manchas faciales", "acné"],
    "pediatría":            ["pediatra", "pediatría", "médico niños", "salud infantil"],
    "otorrinolaringología": ["otorrino", "oído nariz garganta", "problemas auditivos", "sinusitis"],
    "urología":             ["urólogo", "urología", "próstata", "vías urinarias"],
    "spa":                  ["spa", "masaje relajante", "bienestar", "tratamiento corporal"],
    "real_estate":          ["casa en venta", "departamento", "bienes raíces", "propiedad"],
}


# ---------------------------------------------------------------------------
# Meta Ads Library API
# ---------------------------------------------------------------------------

def fetch_competitor_ads(
    keywords: list[str],
    country: str = "MX",
    ad_type: str = "ALL",
    limit: int = 20,
    min_days_active: int = 30,
) -> list[dict]:
    """
    Consulta Meta Ads Library API para obtener anuncios activos de competidores.

    Args:
        keywords: Palabras clave para buscar anuncios.
        country: Código de país (default: MX).
        ad_type: Tipo de anuncio (ALL | POLITICAL_AND_ISSUE_ADS).
        limit: Máximo de anuncios a retornar por keyword.
        min_days_active: Filtro mínimo de días activos.

    Returns:
        Lista de anuncios con metadatos relevantes.

    Note:
        Requiere META_ACCESS_TOKEN con permiso ads_read.
        La API es pública pero rate-limited a ~200 req/hora.
    """
    token = os.getenv("META_ACCESS_TOKEN")
    if not token:
        raise EnvironmentError("META_ACCESS_TOKEN requerido para Ads Library API.")

    all_ads = []

    for keyword in keywords:
        params = {
            "access_token": token,
            "ad_type": ad_type,
            "ad_reached_countries": country,
            "search_terms": keyword,
            "fields": (
                "id,ad_creation_time,ad_delivery_start_time,"
                "ad_snapshot_url,page_name,page_id,"
                "impressions,spend,demographic_distribution,"
                "ad_creative_bodies,ad_creative_link_captions,"
                "ad_creative_link_descriptions,ad_creative_link_titles"
            ),
            "limit": limit,
        }

        url = f"{META_GRAPH_URL}/ads_archive"
        response = requests.get(url, params=params, timeout=30)

        if response.status_code != 200:
            logger.warning(
                "Error Ads Library para '%s': %d — %s",
                keyword, response.status_code, response.text[:200]
            )
            continue

        data = response.json()
        ads = data.get("data", [])

        # Filtrar por días activos
        cutoff_date = datetime.utcnow() - timedelta(days=min_days_active)

        for ad in ads:
            start_str = ad.get("ad_delivery_start_time", "")
            if start_str:
                try:
                    start_date = datetime.fromisoformat(start_str.replace("Z", "+00:00"))
                    if start_date.replace(tzinfo=None) > cutoff_date:
                        continue   # Anuncio muy reciente, omitir
                    days_running = (datetime.utcnow() - start_date.replace(tzinfo=None)).days
                    ad["_days_running"] = days_running
                except ValueError:
                    ad["_days_running"] = min_days_active  # Asumir válido si no parseable

            all_ads.append(ad)

        logger.info("Keyword '%s': %d anuncios encontrados.", keyword, len(ads))

        # Rate limiting conservador: 1 req/segundo
        time.sleep(1)

    logger.info("Total anuncios competidores obtenidos: %d", len(all_ads))
    return all_ads


# ---------------------------------------------------------------------------
# Transformación y carga a Vector DB
# ---------------------------------------------------------------------------

def _extract_text_from_ad(ad: dict) -> str:
    """Extrae y concatena el texto disponible de un anuncio de Meta."""
    texts = []

    bodies = ad.get("ad_creative_bodies", [])
    if isinstance(bodies, list):
        texts.extend(bodies)

    titles = ad.get("ad_creative_link_titles", [])
    if isinstance(titles, list):
        texts.extend(titles)

    descriptions = ad.get("ad_creative_link_descriptions", [])
    if isinstance(descriptions, list):
        texts.extend(descriptions)

    return " | ".join([t for t in texts if t])


def _infer_format(ad: dict) -> str:
    """Infiere el formato del anuncio a partir de sus metadatos."""
    snapshot_url = ad.get("ad_snapshot_url", "")
    if "video" in snapshot_url.lower():
        return "VIDEO"
    if "carousel" in str(ad).lower():
        return "CAROUSEL"
    return "IMAGE"


def _estimate_ctr_score(ad: dict) -> float:
    """
    Estima un CTR score normalizado (0-10) basado en
    las métricas de impresiones y gasto disponibles.
    """
    impressions = ad.get("impressions", {})
    spend = ad.get("spend", {})

    # Meta devuelve rangos, tomamos el mínimo
    imp_min = impressions.get("lower_bound", 0) if isinstance(impressions, dict) else 0
    spend_min = spend.get("lower_bound", 1) if isinstance(spend, dict) else 1

    if imp_min > 0 and spend_min > 0:
        # CTR score proporcional al ratio impresiones/gasto
        ratio = imp_min / max(spend_min, 1)
        return min(round(ratio / 100, 1), 10.0)

    # Default: score medio si no hay datos suficientes
    return 5.0


def ingest_competitor_ads_to_vectordb(
    niche: str,
    country: str = "MX",
    min_days_active: int = 30,
) -> int:
    """
    Pipeline completo: scraping → transformación → indexación en Vector DB.

    Args:
        niche: Nicho a procesar (debe existir en NICHE_KEYWORDS).
        country: País objetivo.
        min_days_active: Mínimo de días activos para considerar ganador.

    Returns:
        Número de patrones indexados exitosamente.

    Raises:
        ValueError: Si el nicho no está en el mapa de keywords.
    """
    if niche not in NICHE_KEYWORDS:
        raise ValueError(
            f"Nicho '{niche}' no reconocido. "
            f"Disponibles: {list(NICHE_KEYWORDS.keys())}"
        )

    init_collection()
    keywords = NICHE_KEYWORDS[niche]
    ads = fetch_competitor_ads(
        keywords=keywords,
        country=country,
        min_days_active=min_days_active,
    )

    indexed_count = 0

    for i, ad in enumerate(ads):
        ad_text = _extract_text_from_ad(ad)
        if not ad_text:
            logger.debug("Anuncio %s sin texto, omitiendo.", ad.get("id", i))
            continue

        pattern = AdPattern(
            id=f"meta_{niche}_{ad.get('id', i)}",
            niche=niche,
            platform="META",
            format=_infer_format(ad),
            days_running=ad.get("_days_running", min_days_active),
            description=ad_text[:500],  # Truncar a 500 chars
            visual_style=f"Anuncio activo de {ad.get('page_name', 'competidor')} en {country}",
            ctr_score=_estimate_ctr_score(ad),
            payload={
                "page_name": ad.get("page_name"),
                "page_id": ad.get("page_id"),
                "snapshot_url": ad.get("ad_snapshot_url"),
                "source": "meta_ads_library",
                "ingested_at": datetime.utcnow().isoformat(),
            },
        )

        try:
            index_ad_pattern(pattern)
            indexed_count += 1
        except Exception as exc:
            logger.error("Error indexando patrón %s: %s", pattern.id, str(exc))

    logger.info(
        "Ingesta completada para nicho '%s': %d/%d patrones indexados.",
        niche, indexed_count, len(ads)
    )
    return indexed_count


# ---------------------------------------------------------------------------
# Script de ingesta completa (todos los nichos)
# ---------------------------------------------------------------------------

def run_full_ingestion(country: str = "MX") -> dict:
    """
    Ejecuta la ingesta de inteligencia competitiva para todos los nichos.
    Diseñado para correr como cron job semanal en GCE.

    Args:
        country: País a procesar.

    Returns:
        Resumen de patrones indexados por nicho.
    """
    summary = {}
    start = datetime.utcnow()
    logger.info("=== Iniciando ingesta completa de inteligencia competitiva ===")

    for niche in NICHE_KEYWORDS:
        logger.info("Procesando nicho: %s", niche)
        try:
            count = ingest_competitor_ads_to_vectordb(niche=niche, country=country)
            summary[niche] = {"status": "success", "patterns_indexed": count}
        except Exception as exc:
            logger.error("Error en nicho %s: %s", niche, str(exc))
            summary[niche] = {"status": "error", "error": str(exc)}

        # Pausa entre nichos para respetar rate limits
        time.sleep(2)

    duration = (datetime.utcnow() - start).seconds
    logger.info(
        "=== Ingesta completa. Duración: %ds | Resumen: %s ===",
        duration, summary
    )
    return summary


if __name__ == "__main__":
    # Ejecución directa para testing
    logging.basicConfig(level=logging.INFO)
    result = run_full_ingestion(country="MX")
    print(result)

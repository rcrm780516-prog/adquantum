"""
modules/vector_db.py
AdQuantum — Módulo de Vector DB (Qdrant)
Gestiona la colección market_intelligence: indexación y búsqueda de patrones ganadores.
"""

import logging
import os
from typing import Optional

from dotenv import load_dotenv
from pydantic import BaseModel
from qdrant_client import QdrantClient
from qdrant_client.models import (
    Distance,
    PointStruct,
    VectorParams,
    Filter,
    FieldCondition,
    MatchValue,
)
from sentence_transformers import SentenceTransformer

load_dotenv()
logger = logging.getLogger(__name__)

COLLECTION_NAME = os.getenv("QDRANT_COLLECTION", "market_intelligence")
VECTOR_SIZE = 384  # all-MiniLM-L6-v2 output size

# Modelo de embeddings ligero (se descarga automáticamente)
_encoder = None


def _get_encoder() -> SentenceTransformer:
    """Lazy-load del modelo de embeddings para evitar carga en import."""
    global _encoder
    if _encoder is None:
        logger.info("Cargando modelo de embeddings...")
        _encoder = SentenceTransformer("all-MiniLM-L6-v2")
    return _encoder


def _get_client() -> QdrantClient:
    """Retorna cliente Qdrant configurado desde variables de entorno."""
    return QdrantClient(
        host=os.getenv("QDRANT_HOST", "localhost"),
        port=int(os.getenv("QDRANT_PORT", 6333)),
    )


# ---------------------------------------------------------------------------
# Modelos de datos
# ---------------------------------------------------------------------------

class AdPattern(BaseModel):
    """Representa un anuncio ganador indexado en la Vector DB."""
    id: str
    niche: str                # e.g. "dermatología", "real_estate"
    platform: str             # "META" | "GOOGLE" | "TIKTOK"
    format: str               # "IMAGE" | "VIDEO" | "CAROUSEL"
    days_running: int         # Días activo (>30 = ganador confirmado)
    description: str          # Descripción textual del anuncio
    visual_style: str         # e.g. "minimalista, fondo blanco, producto centrado"
    ctr_score: float          # Score estimado 0.0 - 10.0
    payload: Optional[dict] = None  # Metadatos adicionales


class SearchResult(BaseModel):
    """Resultado de búsqueda de similitud."""
    pattern: AdPattern
    similarity_score: float


# ---------------------------------------------------------------------------
# Inicialización de colección
# ---------------------------------------------------------------------------

def init_collection() -> None:
    """
    Crea la colección market_intelligence si no existe.
    Idempotente: seguro llamar múltiples veces.
    """
    client = _get_client()
    existing = [c.name for c in client.get_collections().collections]

    if COLLECTION_NAME not in existing:
        client.create_collection(
            collection_name=COLLECTION_NAME,
            vectors_config=VectorParams(size=VECTOR_SIZE, distance=Distance.COSINE),
        )
        logger.info("Colección '%s' creada.", COLLECTION_NAME)
    else:
        logger.info("Colección '%s' ya existe.", COLLECTION_NAME)


# ---------------------------------------------------------------------------
# Indexación
# ---------------------------------------------------------------------------

def index_ad_pattern(pattern: AdPattern) -> None:
    """
    Indexa un patrón de anuncio ganador en la Vector DB.

    Args:
        pattern: Objeto AdPattern con los datos del anuncio.
    """
    client = _get_client()
    encoder = _get_encoder()

    # Texto compuesto para el embedding
    text_for_embedding = (
        f"{pattern.niche} {pattern.platform} {pattern.format} "
        f"{pattern.description} {pattern.visual_style}"
    )
    vector = encoder.encode(text_for_embedding).tolist()

    point = PointStruct(
        id=abs(hash(pattern.id)) % (10**9),  # ID numérico requerido por Qdrant
        vector=vector,
        payload={
            "id": pattern.id,
            "niche": pattern.niche,
            "platform": pattern.platform,
            "format": pattern.format,
            "days_running": pattern.days_running,
            "description": pattern.description,
            "visual_style": pattern.visual_style,
            "ctr_score": pattern.ctr_score,
            **(pattern.payload or {}),
        },
    )

    client.upsert(collection_name=COLLECTION_NAME, points=[point])
    logger.info("Patrón indexado: %s (niche: %s)", pattern.id, pattern.niche)


# ---------------------------------------------------------------------------
# Búsqueda de similitud
# ---------------------------------------------------------------------------

def search_winning_pattern(
    brief_text: str,
    niche: Optional[str] = None,
    top_k: int = 3,
    min_days_running: int = 30,
) -> list[SearchResult]:
    """
    Busca los patrones ganadores más similares al brief del usuario.

    Args:
        brief_text: Texto del brief para vectorizar.
        niche: Filtro opcional por nicho (e.g. "dermatología").
        top_k: Número de resultados a retornar.
        min_days_running: Filtro mínimo de días activos.

    Returns:
        Lista de SearchResult ordenados por similitud descendente.
    """
    client = _get_client()
    encoder = _get_encoder()

    query_vector = encoder.encode(brief_text).tolist()

    # Filtro por días activos (valida rentabilidad)
    query_filter = Filter(
        must=[
            FieldCondition(
                key="days_running",
                range={"gte": min_days_running},
            )
        ]
    )

    # Filtro adicional por nicho si se especifica
    if niche:
        query_filter.must.append(
            FieldCondition(key="niche", match=MatchValue(value=niche))
        )

    results = client.search(
        collection_name=COLLECTION_NAME,
        query_vector=query_vector,
        query_filter=query_filter,
        limit=top_k,
    )

    search_results = []
    for hit in results:
        p = hit.payload
        pattern = AdPattern(
            id=p.get("id", str(hit.id)),
            niche=p.get("niche", ""),
            platform=p.get("platform", ""),
            format=p.get("format", "IMAGE"),
            days_running=p.get("days_running", 0),
            description=p.get("description", ""),
            visual_style=p.get("visual_style", ""),
            ctr_score=p.get("ctr_score", 0.0),
        )
        search_results.append(
            SearchResult(pattern=pattern, similarity_score=hit.score)
        )

    logger.info(
        "Búsqueda vectorial: %d resultados para brief '%s...'",
        len(search_results),
        brief_text[:50],
    )
    return search_results


# ---------------------------------------------------------------------------
# Datos semilla (seed) para pruebas locales
# ---------------------------------------------------------------------------

SEED_PATTERNS = [
    AdPattern(
        id="meta_derm_001",
        niche="dermatología",
        platform="META",
        format="IMAGE",
        days_running=45,
        description="Antes y después tratamiento facial, luz suave, fondo clínico blanco",
        visual_style="minimalista médico, blanco y verde menta, producto centrado",
        ctr_score=8.7,
    ),
    AdPattern(
        id="meta_pediatric_001",
        niche="pediatría",
        platform="META",
        format="VIDEO",
        days_running=62,
        description="Médico pediatra sonriendo, ambiente cálido, consulta amigable",
        visual_style="cálido, tonos pastel, fotografía documental",
        ctr_score=9.1,
    ),
    AdPattern(
        id="google_realestate_001",
        niche="real_estate",
        platform="GOOGLE",
        format="IMAGE",
        days_running=38,
        description="Casa moderna exterior, luz dorada, jardín impecable",
        visual_style="lujo moderno, blanco y dorado, fotografía arquitectónica",
        ctr_score=7.8,
    ),
    AdPattern(
        id="meta_spa_001",
        niche="spa",
        platform="META",
        format="CAROUSEL",
        days_running=55,
        description="Tratamientos de bienestar, velas, ambiente zen",
        visual_style="orgánico, tierra y beige, fotografía editorial de lujo",
        ctr_score=8.3,
    ),
]


def seed_database() -> None:
    """Carga patrones de prueba en la Vector DB. Usar solo en desarrollo."""
    init_collection()
    for pattern in SEED_PATTERNS:
        index_ad_pattern(pattern)
    logger.info("Seed completado: %d patrones cargados.", len(SEED_PATTERNS))

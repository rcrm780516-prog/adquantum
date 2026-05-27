"""
modules/rl_monitor.py
AdQuantum — Bucle de Optimización por Refuerzo (ROAS Monitor)
Monitorea campañas activas y activa Creative Swap si el ROAS cae bajo el umbral.
"""

import logging
import time
from datetime import datetime
from typing import Optional

from pydantic import BaseModel

logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Modelos de datos
# ---------------------------------------------------------------------------

class CampaignHealth(BaseModel):
    """Estado de salud de una campaña en tiempo real."""
    campaign_id: str
    campaign_name: str
    ctr: float
    roas: float
    spend_today_usd: float
    status: str  # "HEALTHY" | "WARNING" | "SWAP_TRIGGERED"
    last_checked: str
    action_taken: Optional[str] = None


class ROASThresholds(BaseModel):
    """Umbrales de ROAS para el bucle de optimización."""
    healthy_min: float = 2.0       # ROAS mínimo aceptable
    warning_threshold: float = 1.5  # ROAS que dispara advertencia
    swap_threshold: float = 1.0    # ROAS que dispara Creative Swap
    ctr_min: float = 0.5           # CTR mínimo en porcentaje


# ---------------------------------------------------------------------------
# Lógica de monitoreo
# ---------------------------------------------------------------------------

def evaluate_campaign_health(
    campaign_id: str,
    campaign_name: str,
    ctr: float,
    roas: float,
    spend_today_usd: float,
    thresholds: Optional[ROASThresholds] = None,
) -> CampaignHealth:
    """
    Evalúa la salud de una campaña y determina si requiere acción.

    Args:
        campaign_id: ID de la campaña en Meta.
        campaign_name: Nombre descriptivo de la campaña.
        ctr: Click-Through Rate actual.
        roas: Return on Ad Spend actual.
        spend_today_usd: Gasto del día en USD.
        thresholds: Umbrales personalizados (usa defaults si es None).

    Returns:
        CampaignHealth con status y acción recomendada.
    """
    t = thresholds or ROASThresholds()
    now = datetime.utcnow().isoformat()
    action = None

    if roas >= t.healthy_min and ctr >= t.ctr_min:
        status = "HEALTHY"
        logger.info(
            "✅ Campaña %s SALUDABLE | ROAS: %.2f | CTR: %.2f%%",
            campaign_name, roas, ctr
        )

    elif roas >= t.warning_threshold:
        status = "WARNING"
        action = "Revisar segmentación y copys. Considerar test A/B."
        logger.warning(
            "⚠️ Campaña %s EN ADVERTENCIA | ROAS: %.2f (mín: %.2f)",
            campaign_name, roas, t.warning_threshold
        )

    else:
        # ROAS crítico — activar Creative Swap
        status = "SWAP_TRIGGERED"
        action = "CREATIVE_SWAP_INITIATED"
        logger.error(
            "🔴 SWAP ACTIVADO para %s | ROAS: %.2f < umbral: %.2f",
            campaign_name, roas, t.swap_threshold
        )

    return CampaignHealth(
        campaign_id=campaign_id,
        campaign_name=campaign_name,
        ctr=ctr,
        roas=roas,
        spend_today_usd=spend_today_usd,
        status=status,
        last_checked=now,
        action_taken=action,
    )


def run_monitoring_loop(
    campaigns: list[dict],
    interval_seconds: int = 3600,
    max_iterations: Optional[int] = None,
) -> None:
    """
    Bucle de monitoreo continuo. Diseñado para correr como proceso en GCE.

    Args:
        campaigns: Lista de dicts con {campaign_id, campaign_name}.
        interval_seconds: Intervalo entre checks (default: 1 hora).
        max_iterations: Límite de iteraciones (None = infinito, útil para tests).

    Nota:
        Este bucle llama a meta_trader.get_campaign_insights() en producción.
        En desarrollo usa datos simulados para no consumir API calls.
    """
    from modules.meta_trader import get_campaign_insights  # Import local para evitar circular

    iteration = 0

    logger.info(
        "🚀 Iniciando bucle RL | %d campañas | intervalo: %ds",
        len(campaigns), interval_seconds
    )

    while True:
        iteration += 1
        logger.info("--- Iteración #%d ---", iteration)

        for camp in campaigns:
            try:
                metrics = get_campaign_insights(camp["campaign_id"])

                if not metrics:
                    logger.warning("Sin datos para campaña %s", camp["campaign_id"])
                    continue

                health = evaluate_campaign_health(
                    campaign_id=camp["campaign_id"],
                    campaign_name=camp.get("campaign_name", camp["campaign_id"]),
                    ctr=float(metrics.get("ctr", 0)),
                    roas=float(metrics.get("roas", [{"value": 0}])[0].get("value", 0)),
                    spend_today_usd=float(metrics.get("spend", 0)),
                )

                if health.status == "SWAP_TRIGGERED":
                    # TODO: Integrar con brief_processor + creative_gen para swap automático
                    # Por ahora registra la alerta para revisión manual (human-in-the-loop v1)
                    logger.error(
                        "ALERTA SWAP: campaña %s requiere nuevos creativos. "
                        "Acción pendiente de aprobación.",
                        health.campaign_name
                    )

            except Exception as exc:
                logger.exception(
                    "Error monitoreando campaña %s: %s",
                    camp.get("campaign_id", "unknown"), str(exc)
                )

        if max_iterations and iteration >= max_iterations:
            logger.info("Límite de iteraciones alcanzado (%d). Deteniendo bucle.", iteration)
            break

        logger.info("Próximo check en %d segundos.", interval_seconds)
        time.sleep(interval_seconds)

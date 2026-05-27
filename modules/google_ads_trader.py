"""
modules/google_ads_trader.py
AdQuantum — Módulo Google Ads (cuenta agencia MCC)
Virtuoso opera desde su cuenta MCC sobre las cuentas de los clientes.
Token: GOOGLE_ADS_REFRESH_TOKEN de la cuenta de agencia Virtuoso.
"""

import logging
import os
from typing import Optional

from dotenv import load_dotenv
from google.ads.googleads.client import GoogleAdsClient
from google.ads.googleads.errors import GoogleAdsException

load_dotenv()
logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Cliente de Google Ads (singleton por proceso)
# ---------------------------------------------------------------------------

_gads_client: Optional[GoogleAdsClient] = None


def _get_client() -> GoogleAdsClient:
    """
    Retorna el cliente de Google Ads autenticado con la cuenta MCC de Virtuoso.
    Lazy-load para no inicializar en import.
    """
    global _gads_client
    if _gads_client is None:
        _gads_client = GoogleAdsClient.load_from_dict({
            "developer_token": os.getenv("GOOGLE_ADS_DEVELOPER_TOKEN"),
            "client_id": os.getenv("GOOGLE_ADS_CLIENT_ID"),
            "client_secret": os.getenv("GOOGLE_ADS_CLIENT_SECRET"),
            "refresh_token": os.getenv("GOOGLE_ADS_REFRESH_TOKEN"),
            "login_customer_id": os.getenv("GOOGLE_ADS_MCC_CUSTOMER_ID"),
            "use_proto_plus": True,
        })
        logger.info("Cliente Google Ads MCC inicializado.")
    return _gads_client


def verify_client_account(customer_id: str) -> dict:
    """
    Verifica que el MCC de Virtuoso tenga acceso a la cuenta del cliente.

    Args:
        customer_id: ID de cuenta Google Ads del cliente (10 dígitos, sin guiones).

    Returns:
        Dict con nombre, moneda y estado de la cuenta.

    Raises:
        GoogleAdsException: Si el MCC no tiene acceso.
    """
    client = _get_client()
    ga_service = client.get_service("GoogleAdsService")

    query = """
        SELECT
            customer.id,
            customer.descriptive_name,
            customer.currency_code,
            customer.status
        FROM customer
        LIMIT 1
    """

    try:
        response = ga_service.search(customer_id=customer_id, query=query)
        for row in response:
            return {
                "customer_id": customer_id,
                "name": row.customer.descriptive_name,
                "currency": row.customer.currency_code,
                "status": row.customer.status.name,
                "has_access": True,
            }
    except GoogleAdsException as exc:
        logger.error(
            "Sin acceso a cuenta Google Ads %s: %s",
            customer_id, exc.error.code().name
        )
        return {"customer_id": customer_id, "has_access": False, "error": str(exc)}


def create_search_campaign(
    customer_id: str,
    campaign_name: str,
    budget_daily_micros: int,
    keywords: list[str],
    headlines: list[str],
    descriptions: list[str],
    final_url: str,
    geo_target_id: str = "2484",   # 2484 = México
) -> dict:
    """
    Crea una campaña de búsqueda en Google Ads en estado PAUSED.

    Args:
        customer_id: ID de cuenta del cliente (sin guiones).
        campaign_name: Nombre descriptivo de la campaña.
        budget_daily_micros: Presupuesto diario en micros (1 USD = 1_000_000 micros).
        keywords: Lista de palabras clave (broad match por default).
        headlines: 3-15 títulos para el anuncio responsivo.
        descriptions: 2-4 descripciones para el anuncio responsivo.
        final_url: URL de destino del anuncio.
        geo_target_id: ID de ubicación de Google Ads (default: México).

    Returns:
        Dict con IDs de campaña, ad group y anuncio creados.
    """
    client = _get_client()

    # --- 1. Crear presupuesto ---
    budget_service = client.get_service("CampaignBudgetService")
    budget_op = client.get_type("CampaignBudgetOperation")
    budget = budget_op.create
    budget.name = f"Budget_{campaign_name}"
    budget.amount_micros = budget_daily_micros
    budget.delivery_method = client.enums.BudgetDeliveryMethodEnum.STANDARD

    budget_response = budget_service.mutate_campaign_budgets(
        customer_id=customer_id, operations=[budget_op]
    )
    budget_resource = budget_response.results[0].resource_name
    logger.info("Presupuesto creado: %s", budget_resource)

    # --- 2. Crear campaña ---
    campaign_service = client.get_service("CampaignService")
    campaign_op = client.get_type("CampaignOperation")
    campaign = campaign_op.create
    campaign.name = campaign_name
    campaign.status = client.enums.CampaignStatusEnum.PAUSED   # Siempre PAUSED al crear
    campaign.advertising_channel_type = client.enums.AdvertisingChannelTypeEnum.SEARCH
    campaign.campaign_budget = budget_resource
    campaign.network_settings.target_google_search = True
    campaign.network_settings.target_search_network = True

    # Segmentación geográfica
    location_criterion = client.get_type("CampaignCriterionOperation")
    location = location_criterion.create
    location.campaign = campaign_op.create.name
    location.location.geo_target_constant = (
        f"geoTargetConstants/{geo_target_id}"
    )

    campaign_response = campaign_service.mutate_campaigns(
        customer_id=customer_id, operations=[campaign_op]
    )
    campaign_resource = campaign_response.results[0].resource_name
    logger.info("Campaña creada: %s", campaign_resource)

    # --- 3. Crear Ad Group ---
    ad_group_service = client.get_service("AdGroupService")
    ag_op = client.get_type("AdGroupOperation")
    ag = ag_op.create
    ag.name = f"{campaign_name} - Grupo 1"
    ag.campaign = campaign_resource
    ag.status = client.enums.AdGroupStatusEnum.ENABLED
    ag.type_ = client.enums.AdGroupTypeEnum.SEARCH_STANDARD
    ag.cpc_bid_micros = 1_000_000  # $1 USD CPC inicial

    ag_response = ad_group_service.mutate_ad_groups(
        customer_id=customer_id, operations=[ag_op]
    )
    ag_resource = ag_response.results[0].resource_name
    logger.info("Ad Group creado: %s", ag_resource)

    # --- 4. Agregar keywords ---
    kw_service = client.get_service("AdGroupCriterionService")
    kw_operations = []
    for kw in keywords[:20]:  # Google Ads permite hasta 20 por Ad Group recomendado
        kw_op = client.get_type("AdGroupCriterionOperation")
        criterion = kw_op.create
        criterion.ad_group = ag_resource
        criterion.status = client.enums.AdGroupCriterionStatusEnum.ENABLED
        criterion.keyword.text = kw
        criterion.keyword.match_type = client.enums.KeywordMatchTypeEnum.BROAD
        kw_operations.append(kw_op)

    kw_service.mutate_ad_group_criteria(
        customer_id=customer_id, operations=kw_operations
    )
    logger.info("%d keywords agregadas.", len(kw_operations))

    # --- 5. Crear anuncio responsivo de búsqueda ---
    ad_service = client.get_service("AdGroupAdService")
    ad_op = client.get_type("AdGroupAdOperation")
    ad = ad_op.create
    ad.ad_group = ag_resource
    ad.status = client.enums.AdGroupAdStatusEnum.ENABLED

    rsa = ad.ad.responsive_search_ad
    rsa.final_urls.append(final_url)

    for i, headline in enumerate(headlines[:15]):
        asset = client.get_type("AdTextAsset")
        asset.text = headline[:30]  # Límite de 30 chars en headlines de Google
        rsa.headlines.append(asset)

    for desc in descriptions[:4]:
        asset = client.get_type("AdTextAsset")
        asset.text = desc[:90]  # Límite de 90 chars en descriptions
        rsa.descriptions.append(asset)

    ad_response = ad_service.mutate_ad_group_ads(
        customer_id=customer_id, operations=[ad_op]
    )
    ad_resource = ad_response.results[0].resource_name
    logger.info("Anuncio responsivo creado: %s", ad_resource)

    return {
        "platform": "GOOGLE_ADS",
        "status": "CREATED_PAUSED",
        "campaign_resource": campaign_resource,
        "ad_group_resource": ag_resource,
        "ad_resource": ad_resource,
        "customer_id": customer_id,
        "keywords_added": len(kw_operations),
    }


def get_campaign_performance(customer_id: str, days: int = 7) -> list[dict]:
    """
    Obtiene métricas de rendimiento de campañas activas del cliente.

    Args:
        customer_id: ID de cuenta del cliente.
        days: Rango de días a consultar.

    Returns:
        Lista de campañas con CTR, conversiones, CPC y gasto.
    """
    client = _get_client()
    ga_service = client.get_service("GoogleAdsService")

    query = f"""
        SELECT
            campaign.id,
            campaign.name,
            campaign.status,
            metrics.clicks,
            metrics.impressions,
            metrics.ctr,
            metrics.average_cpc,
            metrics.cost_micros,
            metrics.conversions,
            metrics.cost_per_conversion
        FROM campaign
        WHERE segments.date DURING LAST_{days}_DAYS
          AND campaign.status != 'REMOVED'
        ORDER BY metrics.cost_micros DESC
        LIMIT 20
    """

    results = []
    try:
        response = ga_service.search(customer_id=customer_id, query=query)
        for row in response:
            results.append({
                "campaign_id": str(row.campaign.id),
                "campaign_name": row.campaign.name,
                "status": row.campaign.status.name,
                "clicks": row.metrics.clicks,
                "impressions": row.metrics.impressions,
                "ctr": round(row.metrics.ctr * 100, 2),
                "avg_cpc_usd": round(row.metrics.average_cpc / 1_000_000, 2),
                "spend_usd": round(row.metrics.cost_micros / 1_000_000, 2),
                "conversions": round(row.metrics.conversions, 1),
                "cost_per_conversion": round(row.metrics.cost_per_conversion / 1_000_000, 2),
            })
    except GoogleAdsException as exc:
        logger.error("Error obteniendo métricas Google Ads: %s", exc.error.code().name)

    return results

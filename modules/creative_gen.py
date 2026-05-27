"""
modules/creative_gen.py
AdQuantum — Módulo de Generación Creativa con Gemini
Genera imágenes publicitarias de alta calidad usando Google Gemini Imagen.
"""

import base64
import logging
import os
from io import BytesIO
from typing import Optional

import google.generativeai as genai
from dotenv import load_dotenv
from PIL import Image

load_dotenv()
logger = logging.getLogger(__name__)

# Configurar SDK de Gemini con API key
genai.configure(api_key=os.getenv("GEMINI_API_KEY"))


# ---------------------------------------------------------------------------
# Lógica de generación
# ---------------------------------------------------------------------------

def generate_ad_image(
    prompt: str,
    reference_image_b64: Optional[str] = None,
    width: int = 1080,
    height: int = 1080,
) -> str:
    """
    Genera una imagen publicitaria usando Gemini Imagen.

    Args:
        prompt: Prompt técnico generado por el motor de AdQuantum.
        reference_image_b64: Imagen de referencia en base64 (para Image-to-Image).
        width: Ancho de la imagen en píxeles.
        height: Alto de la imagen en píxeles.

    Returns:
        Imagen generada en formato base64 (JPEG).

    Raises:
        RuntimeError: Si Gemini no devuelve imagen válida.
    """
    # Modelo de generación de imágenes de Gemini
    model = genai.ImageGenerationModel("imagen-3.0-generate-002")

    logger.info("Generando imagen | prompt: %s...", prompt[:80])

    generation_config = {
        "number_of_images": 1,
        "aspect_ratio": "1:1" if width == height else "16:9",
        "safety_filter_level": "block_some",
        "person_generation": "allow_adult",
    }

    if reference_image_b64:
        # Image-to-Image: preserva coherencia de marca con imagen de referencia
        ref_bytes = base64.b64decode(reference_image_b64)
        ref_image = Image.open(BytesIO(ref_bytes))

        response = model.generate_images(
            prompt=prompt,
            base_image=ref_image,
            **generation_config,
        )
    else:
        response = model.generate_images(
            prompt=prompt,
            **generation_config,
        )

    if not response.images:
        raise RuntimeError("Gemini no devolvió imágenes. Verifica el prompt o la API key.")

    # Convertir imagen generada a base64
    generated_image = response.images[0]
    buffer = BytesIO()
    generated_image._pil_image.save(buffer, format="JPEG", quality=95)
    image_b64 = base64.b64encode(buffer.getvalue()).decode("utf-8")

    logger.info("Imagen generada correctamente (%.1f KB)", len(image_b64) * 0.75 / 1024)
    return image_b64


def generate_campaign_creatives(
    image_prompts: list[str],
    reference_image_b64: Optional[str] = None,
) -> list[dict]:
    """
    Genera múltiples creativos para una campaña.

    Args:
        image_prompts: Lista de prompts generados por el brief processor.
        reference_image_b64: Imagen de referencia opcional.

    Returns:
        Lista de dicts con {prompt, image_b64, index}.
    """
    results = []

    for i, prompt in enumerate(image_prompts):
        try:
            image_b64 = generate_ad_image(
                prompt=prompt,
                reference_image_b64=reference_image_b64,
            )
            results.append({
                "index": i,
                "prompt": prompt,
                "image_b64": image_b64,
                "status": "success",
            })
        except Exception as exc:
            logger.error("Error generando imagen %d: %s", i, str(exc))
            results.append({
                "index": i,
                "prompt": prompt,
                "image_b64": None,
                "status": "error",
                "error": str(exc),
            })

    successful = sum(1 for r in results if r["status"] == "success")
    logger.info(
        "Generación completada: %d/%d imágenes exitosas.", successful, len(image_prompts)
    )
    return results


def enhance_prompt_for_niche(base_prompt: str, niche: str) -> str:
    """
    Enriquece el prompt con directrices específicas del nicho médico/spa/realestate.

    Args:
        base_prompt: Prompt base generado por Claude.
        niche: Nicho del cliente.

    Returns:
        Prompt enriquecido.
    """
    niche_modifiers = {
        "dermatología": "professional medical aesthetics, clinical clean environment, "
                        "soft diffused lighting, trustworthy medical branding",
        "pediatría": "warm friendly medical setting, pastel colors, "
                    "safe and welcoming atmosphere, soft natural light",
        "otorrinolaringología": "professional clinical setting, medical equipment, "
                               "clean white and blue tones, authoritative medical brand",
        "urología": "professional medical photography, clinical precision, "
                   "trustworthy specialist branding, clean minimal environment",
        "spa": "luxury wellness photography, zen atmosphere, soft candlelight, "
               "organic textures, premium brand aesthetic",
        "real_estate": "luxury architectural photography, golden hour lighting, "
                       "premium property showcase, aspirational lifestyle",
    }

    modifier = niche_modifiers.get(niche.lower(), "professional commercial photography")
    return f"{base_prompt}, {modifier}"

"""
modules/auth/google_auth.py
AdQuantum — Autenticación con Google OAuth
El cliente entra a AdQuantum usando su cuenta de Google.
No se almacena ningún token de Meta aquí — solo identidad del usuario.
"""

import logging
import os
import uuid
from datetime import datetime, timedelta
from typing import Optional

import httpx
import jwt
from dotenv import load_dotenv

load_dotenv()
logger = logging.getLogger(__name__)

GOOGLE_AUTH_URL = "https://accounts.google.com/o/oauth2/v2/auth"
GOOGLE_TOKEN_URL = "https://oauth2.googleapis.com/token"
GOOGLE_USERINFO_URL = "https://www.googleapis.com/oauth2/v3/userinfo"


# ---------------------------------------------------------------------------
# OAuth flow
# ---------------------------------------------------------------------------

def get_google_auth_url(state: Optional[str] = None) -> str:
    """
    Genera la URL de autorización de Google OAuth.
    El cliente es redirigido aquí para iniciar sesión.

    Args:
        state: Token CSRF opcional para validar el callback.

    Returns:
        URL de autorización de Google.
    """
    params = {
        "client_id": os.getenv("GOOGLE_CLIENT_ID"),
        "redirect_uri": os.getenv("GOOGLE_REDIRECT_URI"),
        "response_type": "code",
        "scope": "openid email profile",
        "access_type": "online",
        "prompt": "select_account",
    }
    if state:
        params["state"] = state

    query = "&".join(f"{k}={v}" for k, v in params.items())
    return f"{GOOGLE_AUTH_URL}?{query}"


async def exchange_code_for_token(code: str) -> dict:
    """
    Intercambia el código de autorización por tokens de Google.

    Args:
        code: Código recibido en el callback de Google.

    Returns:
        Dict con access_token, id_token, etc.

    Raises:
        httpx.HTTPError: Si Google rechaza el código.
    """
    async with httpx.AsyncClient() as client:
        response = await client.post(
            GOOGLE_TOKEN_URL,
            data={
                "client_id": os.getenv("GOOGLE_CLIENT_ID"),
                "client_secret": os.getenv("GOOGLE_CLIENT_SECRET"),
                "redirect_uri": os.getenv("GOOGLE_REDIRECT_URI"),
                "grant_type": "authorization_code",
                "code": code,
            },
        )
        response.raise_for_status()
        return response.json()


async def get_google_user_info(access_token: str) -> dict:
    """
    Obtiene el perfil del usuario desde Google.

    Args:
        access_token: Token de acceso de Google.

    Returns:
        Dict con sub (Google ID), email, name, picture.
    """
    async with httpx.AsyncClient() as client:
        response = await client.get(
            GOOGLE_USERINFO_URL,
            headers={"Authorization": f"Bearer {access_token}"},
        )
        response.raise_for_status()
        return response.json()


# ---------------------------------------------------------------------------
# JWT para sesiones de AdQuantum
# ---------------------------------------------------------------------------

def create_session_token(client_id: str, email: str) -> str:
    """
    Genera un JWT de sesión para el cliente autenticado.

    Args:
        client_id: UUID del cliente en la DB de AdQuantum.
        email: Email del cliente.

    Returns:
        JWT firmado con expiración configurada en .env.
    """
    expire_hours = int(os.getenv("JWT_EXPIRE_HOURS", 24))
    payload = {
        "sub": client_id,
        "email": email,
        "exp": datetime.utcnow() + timedelta(hours=expire_hours),
        "iat": datetime.utcnow(),
    }
    return jwt.encode(
        payload,
        os.getenv("JWT_SECRET_KEY", "dev-secret"),
        algorithm=os.getenv("JWT_ALGORITHM", "HS256"),
    )


def verify_session_token(token: str) -> Optional[dict]:
    """
    Verifica y decodifica un JWT de sesión.

    Args:
        token: JWT a verificar.

    Returns:
        Payload del token o None si es inválido/expirado.
    """
    try:
        return jwt.decode(
            token,
            os.getenv("JWT_SECRET_KEY", "dev-secret"),
            algorithms=[os.getenv("JWT_ALGORITHM", "HS256")],
        )
    except jwt.ExpiredSignatureError:
        logger.warning("Token expirado.")
        return None
    except jwt.InvalidTokenError as exc:
        logger.warning("Token inválido: %s", str(exc))
        return None


# ---------------------------------------------------------------------------
# Registro / login de cliente en DB
# ---------------------------------------------------------------------------

def upsert_client_from_google(db, user_info: dict):
    """
    Crea o actualiza el perfil del cliente a partir de los datos de Google.
    Si el cliente ya existe (mismo google_id), actualiza last_login.
    Si es nuevo, crea el registro.

    Args:
        db: Sesión de SQLAlchemy.
        user_info: Dict con datos de Google (sub, email, name, picture).

    Returns:
        Objeto Client de la DB.
    """
    from modules.database import Client, get_client_by_google_id

    google_id = user_info.get("sub")
    email = user_info.get("email")
    name = user_info.get("name", email)
    picture = user_info.get("picture")

    client = get_client_by_google_id(db, google_id)

    if client:
        # Cliente existente: actualizar último login
        client.last_login = datetime.utcnow()
        client.picture_url = picture
        logger.info("Login existente: %s", email)
    else:
        # Nuevo cliente
        client = Client(
            id=str(uuid.uuid4()),
            google_id=google_id,
            email=email,
            name=name,
            picture_url=picture,
            created_at=datetime.utcnow(),
            last_login=datetime.utcnow(),
        )
        db.add(client)
        logger.info("Nuevo cliente registrado: %s", email)

    db.commit()
    db.refresh(client)
    return client

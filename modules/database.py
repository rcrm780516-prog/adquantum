"""
modules/database.py
AdQuantum — Modelos de Base de Datos (PostgreSQL)
Gestiona clientes, sus cuentas publicitarias y el historial de campañas.

Setup inicial:
    python -c "from modules.database import create_tables; create_tables()"
"""

import os
import logging
from datetime import datetime
from typing import Optional

from dotenv import load_dotenv
from sqlalchemy import (
    create_engine, Column, String, Float, Integer,
    Boolean, DateTime, Text, ForeignKey, Enum
)
from sqlalchemy.orm import declarative_base, sessionmaker, relationship, Session
from cryptography.fernet import Fernet

load_dotenv()
logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# Engine y sesión
# ---------------------------------------------------------------------------

DATABASE_URL = os.getenv("DATABASE_URL", "sqlite:///./adquantum_dev.db")

# SQLite para dev local, PostgreSQL en GCE — mismo código
engine = create_engine(
    DATABASE_URL,
    connect_args={"check_same_thread": False} if "sqlite" in DATABASE_URL else {},
    echo=False,
)

SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()


# ---------------------------------------------------------------------------
# Cifrado de tokens (Fernet AES-128)
# ---------------------------------------------------------------------------

def _get_cipher() -> Fernet:
    """Retorna el cipher Fernet con la clave del entorno."""
    key = os.getenv("TOKEN_ENCRYPTION_KEY")
    if not key:
        raise EnvironmentError(
            "TOKEN_ENCRYPTION_KEY no configurada. "
            "Generar con: python -c \"from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())\""
        )
    return Fernet(key.encode())


def encrypt_token(token: str) -> str:
    """Cifra un token antes de guardarlo en la DB."""
    return _get_cipher().encrypt(token.encode()).decode()


def decrypt_token(encrypted: str) -> str:
    """Descifra un token de la DB para usarlo en API calls."""
    return _get_cipher().decrypt(encrypted.encode()).decode()


# ---------------------------------------------------------------------------
# Modelos
# ---------------------------------------------------------------------------

class Client(Base):
    """
    Representa un cliente de Virtuoso Marketing.
    Guarda su perfil, cuentas publicitarias y estado de acceso.
    """
    __tablename__ = "clients"

    id = Column(String, primary_key=True)              # UUID generado al registro
    email = Column(String, unique=True, nullable=False)
    name = Column(String, nullable=False)
    google_id = Column(String, unique=True)            # ID de Google OAuth
    picture_url = Column(String)                       # Avatar de Google
    niche = Column(String)                             # dermatología, pediatría, spa...
    business_name = Column(String)                     # Nombre del consultorio/negocio
    city = Column(String)
    country = Column(String, default="MX")
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    last_login = Column(DateTime)

    # Relaciones
    ad_accounts = relationship("AdAccount", back_populates="client", cascade="all, delete-orphan")
    campaigns = relationship("Campaign", back_populates="client", cascade="all, delete-orphan")

    def __repr__(self):
        return f"<Client {self.email} | {self.niche}>"


class AdAccount(Base):
    """
    Cuenta publicitaria de un cliente (Meta o Google Ads).
    Virtuoso tiene acceso admin a estas cuentas — el token
    de agencia (VIRTUOSO_META_SYSTEM_TOKEN) es el que opera,
    aquí solo guardamos el ID de la cuenta del cliente.
    """
    __tablename__ = "ad_accounts"

    id = Column(String, primary_key=True)
    client_id = Column(String, ForeignKey("clients.id"), nullable=False)
    platform = Column(
        Enum("META", "GOOGLE_ADS", name="platform_enum"),
        nullable=False
    )

    # IDs de la cuenta del cliente en cada plataforma
    # Meta: act_XXXXXXXXXX (Virtuoso debe ser admin en Meta Business)
    # Google Ads: customer_id de 10 dígitos (Virtuoso MCC debe tenerla vinculada)
    account_id = Column(String, nullable=False)
    account_name = Column(String)                      # Nombre descriptivo
    currency = Column(String, default="MXN")
    is_verified = Column(Boolean, default=False)       # Virtuoso confirmó acceso admin
    added_at = Column(DateTime, default=datetime.utcnow)
    verified_at = Column(DateTime)

    # Relación
    client = relationship("Client", back_populates="ad_accounts")

    def __repr__(self):
        return f"<AdAccount {self.platform}:{self.account_id} | client:{self.client_id}>"


class Campaign(Base):
    """
    Registro de cada campaña creada por AdQuantum.
    Historial completo para el bucle de aprendizaje RL.
    """
    __tablename__ = "campaigns"

    id = Column(String, primary_key=True)
    client_id = Column(String, ForeignKey("clients.id"), nullable=False)
    platform = Column(String, nullable=False)          # META | GOOGLE_ADS
    platform_campaign_id = Column(String)              # ID real en Meta/Google
    name = Column(String, nullable=False)
    niche = Column(String)
    budget_daily_usd = Column(Float)
    geo = Column(String)
    status = Column(String, default="PAUSED")          # PAUSED | ACTIVE | ENDED
    objective = Column(String, default="CONVERSIONS")

    # Métricas (actualizadas por el bucle RL)
    roas = Column(Float)
    ctr = Column(Float)
    spend_total_usd = Column(Float, default=0.0)
    impressions = Column(Integer, default=0)
    clicks = Column(Integer, default=0)
    leads = Column(Integer, default=0)

    # Creativos generados
    image_prompt_used = Column(Text)                   # Prompt de Gemini usado
    ad_copy_used = Column(Text)                        # Copy de Claude usado
    db_pattern_reference = Column(String)              # Patrón de Vector DB usado
    ai_confidence_score = Column(Float)

    # Timestamps
    created_at = Column(DateTime, default=datetime.utcnow)
    activated_at = Column(DateTime)
    last_monitored_at = Column(DateTime)
    swap_count = Column(Integer, default=0)            # Veces que se hizo Creative Swap

    # Relación
    client = relationship("Client", back_populates="campaigns")

    def __repr__(self):
        return f"<Campaign {self.name} | {self.status} | ROAS:{self.roas}>"


# ---------------------------------------------------------------------------
# Helpers de sesión
# ---------------------------------------------------------------------------

def get_db() -> Session:
    """
    Generador de sesión DB para inyección de dependencias en FastAPI.

    Uso en endpoints:
        from modules.database import get_db
        def my_endpoint(db: Session = Depends(get_db)):
            ...
    """
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()


def create_tables() -> None:
    """Crea todas las tablas en la DB. Idempotente."""
    Base.metadata.create_all(bind=engine)
    logger.info("Tablas creadas/verificadas en: %s", DATABASE_URL)


# ---------------------------------------------------------------------------
# CRUD helpers
# ---------------------------------------------------------------------------

def get_client_by_google_id(db: Session, google_id: str) -> Optional[Client]:
    return db.query(Client).filter(Client.google_id == google_id).first()


def get_client_by_email(db: Session, email: str) -> Optional[Client]:
    return db.query(Client).filter(Client.email == email).first()


def get_client_ad_account(
    db: Session,
    client_id: str,
    platform: str,
) -> Optional[AdAccount]:
    """Retorna la cuenta publicitaria del cliente para una plataforma específica."""
    return (
        db.query(AdAccount)
        .filter(
            AdAccount.client_id == client_id,
            AdAccount.platform == platform,
            AdAccount.is_verified == True,
        )
        .first()
    )


def get_active_campaigns(db: Session, client_id: str) -> list[Campaign]:
    """Retorna campañas activas de un cliente para el bucle RL."""
    return (
        db.query(Campaign)
        .filter(
            Campaign.client_id == client_id,
            Campaign.status == "ACTIVE",
        )
        .all()
    )

"""ORM: users."""

from __future__ import annotations

import uuid
from datetime import datetime
from typing import TYPE_CHECKING

from sqlalchemy import DateTime, Text, func
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.infrastructure.persistence.base import Base

if TYPE_CHECKING:
    from app.infrastructure.persistence.models.fantasy import FantasyLeague, LeagueMembership


class User(Base):
    __tablename__ = "users"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    email: Mapped[str | None] = mapped_column(Text, unique=True)
    display_name: Mapped[str] = mapped_column(Text, nullable=False)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    leagues_commissioned: Mapped[list[FantasyLeague]] = relationship(
        "FantasyLeague",
        back_populates="commissioner",
        foreign_keys="FantasyLeague.commissioner_user_id",
    )
    league_memberships: Mapped[list[LeagueMembership]] = relationship(
        "LeagueMembership",
        back_populates="user",
    )

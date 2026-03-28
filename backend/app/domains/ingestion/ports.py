"""Outbound port: pull stats from external providers."""

from typing import Protocol
from uuid import UUID

from app.domains.ingestion.models import NormalizedStatEvent


class IStatProvider(Protocol):
    """Registered by stats_adapter_key (see infrastructure.ingestion.registry)."""

    async def fetch_events_since(
        self,
        *,
        tournament_id: UUID,
        cursor: str | None,
    ) -> tuple[list[NormalizedStatEvent], str | None]:
        """Return (events, next_cursor)."""
        ...

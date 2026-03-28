from uuid import UUID

from app.domains.ingestion.models import NormalizedStatEvent


class NoopStatProvider:
    """Safe default when no adapter is configured."""

    async def fetch_events_since(
        self,
        *,
        tournament_id: UUID,
        cursor: str | None,
    ) -> tuple[list[NormalizedStatEvent], str | None]:
        del tournament_id, cursor
        return [], None

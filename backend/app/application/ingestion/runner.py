"""Pull normalized stats from IStatProvider and persist stat_events."""

from __future__ import annotations

from typing import Any
from uuid import UUID

from sqlalchemy.ext.asyncio import AsyncSession

from app.domains.tournament.ports import ITournamentRepository
from app.infrastructure.ingestion.registry import get_stat_provider
from app.infrastructure.persistence.models.stat_event import StatEvent as StatEventRow


class StatIngestionRunner:
    def __init__(
        self,
        session: AsyncSession,
        tournaments: ITournamentRepository,
    ) -> None:
        self._session = session
        self._tournaments = tournaments

    async def sync_tournament(
        self,
        tournament_id: UUID,
        *,
        cursor_override: str | None = None,
    ) -> tuple[int, str | None]:
        """
        Fetch events from the tournament's stats_adapter_key provider and insert rows.

        When the provider returns next_cursor, it is merged into tournaments.metadata
        under the key ``ingestion_cursor``.
        """
        tournament = await self._tournaments.get_tournament(tournament_id)
        if tournament is None:
            msg = f"Tournament not found: {tournament_id}"
            raise ValueError(msg)

        cursor: str | None = cursor_override
        if cursor is None:
            raw = (tournament.meta or {}).get("ingestion_cursor")
            cursor = raw if isinstance(raw, str) else None

        provider = get_stat_provider(tournament.stats_adapter_key)
        events, next_cursor = await provider.fetch_events_since(
            tournament_id=tournament_id,
            cursor=cursor,
        )

        inserted = 0
        for e in events:
            self._session.add(
                StatEventRow(
                    tournament_id=e.tournament_id,
                    participant_id=e.participant_id,
                    match_id=e.match_id,
                    tournament_round_id=e.tournament_round_id,
                    metric_key=e.metric_key,
                    value_numeric=e.value_numeric,
                    occurred_at=e.occurred_at,
                    details=dict(e.details),
                    source=e.source,
                )
            )
            inserted += 1

        patch: dict[str, Any] = {}
        if next_cursor is not None:
            patch["ingestion_cursor"] = next_cursor
        if patch:
            await self._tournaments.patch_tournament_metadata(tournament_id, patch)

        await self._session.flush()
        return inserted, next_cursor

"""SQLAlchemy implementation of ITournamentRepository."""

from uuid import UUID

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.domains.tournament import entities as te
from app.domains.tournament.ports import ITournamentRepository
from app.infrastructure.persistence.models.tournament import (
    Participant as ParticipantRow,
)
from app.infrastructure.persistence.models.tournament import (
    Tournament as TournamentRow,
)
from app.infrastructure.persistence.models.tournament import (
    TournamentRound as TournamentRoundRow,
)


class TournamentRepository(ITournamentRepository):
    def __init__(self, session: AsyncSession) -> None:
        self._session = session

    async def get_tournament(self, tournament_id: UUID) -> te.Tournament | None:
        row = await self._session.get(TournamentRow, tournament_id)
        return _tournament_from_row(row) if row else None

    async def list_tournaments(self) -> list[te.Tournament]:
        result = await self._session.scalars(
            select(TournamentRow).order_by(TournamentRow.created_at.desc())
        )
        return [_tournament_from_row(r) for r in result.all()]

    async def list_rounds(self, tournament_id: UUID) -> list[te.TournamentRound]:
        stmt = (
            select(TournamentRoundRow)
            .where(TournamentRoundRow.tournament_id == tournament_id)
            .order_by(TournamentRoundRow.order_index)
        )
        result = await self._session.scalars(stmt)
        return [_round_from_row(r) for r in result.all()]

    async def list_participants(self, tournament_id: UUID) -> list[te.Participant]:
        stmt = select(ParticipantRow).where(ParticipantRow.tournament_id == tournament_id)
        result = await self._session.scalars(stmt)
        return [_participant_from_row(r) for r in result.all()]


def _tournament_from_row(row: TournamentRow) -> te.Tournament:
    return te.Tournament(
        id=row.id,
        name=row.name,
        slug=row.slug,
        sport_key=row.sport_key,
        stats_adapter_key=row.stats_adapter_key,
        default_scoring_engine_key=row.default_scoring_engine_key,
        timezone=row.timezone,
        starts_at=row.starts_at,
        ends_at=row.ends_at,
        status=row.status,
        meta=dict(row.metadata_ or {}),
    )


def _round_from_row(row: TournamentRoundRow) -> te.TournamentRound:
    return te.TournamentRound(
        id=row.id,
        tournament_id=row.tournament_id,
        order_index=row.order_index,
        name=row.name,
        canonical_key=row.canonical_key,
        meta=dict(row.metadata_ or {}),
    )


def _participant_from_row(row: ParticipantRow) -> te.Participant:
    return te.Participant(
        id=row.id,
        tournament_id=row.tournament_id,
        external_ref=row.external_ref,
        display_name=row.display_name,
        kind=row.kind,
        meta=dict(row.metadata_ or {}),
    )

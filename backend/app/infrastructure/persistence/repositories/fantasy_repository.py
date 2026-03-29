"""SQLAlchemy implementation of IFantasyRepository."""

from uuid import UUID

from sqlalchemy import func, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.domains.fantasy import entities as fe
from app.domains.fantasy.ports import IFantasyRepository
from app.infrastructure.persistence.models.fantasy import DraftPick as DraftPickRow
from app.infrastructure.persistence.models.fantasy import DraftSession as DraftSessionRow
from app.infrastructure.persistence.models.fantasy import FantasyLeague as FantasyLeagueRow
from app.infrastructure.persistence.models.fantasy import LeagueMembership as LeagueMembershipRow


class FantasyRepository(IFantasyRepository):
    def __init__(self, session: AsyncSession) -> None:
        self._session = session

    async def get_league(self, league_id: UUID) -> fe.FantasyLeague | None:
        row = await self._session.get(FantasyLeagueRow, league_id)
        return _league_from_row(row) if row else None

    async def list_memberships(self, league_id: UUID) -> list[fe.LeagueMembership]:
        stmt = select(LeagueMembershipRow).where(LeagueMembershipRow.league_id == league_id)
        result = await self._session.scalars(stmt)
        return [_membership_from_row(r) for r in result.all()]

    async def list_league_summaries(
        self,
        tournament_id: UUID | None = None,
    ) -> list[fe.LeagueSummary]:
        stmt = (
            select(FantasyLeagueRow, func.count(LeagueMembershipRow.id))
            .outerjoin(
                LeagueMembershipRow,
                LeagueMembershipRow.league_id == FantasyLeagueRow.id,
            )
            .group_by(FantasyLeagueRow)
            .order_by(FantasyLeagueRow.created_at.desc())
        )
        if tournament_id is not None:
            stmt = stmt.where(FantasyLeagueRow.tournament_id == tournament_id)
        result = await self._session.execute(stmt)
        return [
            fe.LeagueSummary(
                id=row.id,
                tournament_id=row.tournament_id,
                name=row.name,
                status=row.status,
                member_count=int(cnt),
            )
            for row, cnt in result.all()
        ]

    async def list_draft_roster_participant_ids(
        self,
        league_id: UUID,
        league_membership_id: UUID,
    ) -> frozenset[UUID]:
        stmt = (
            select(DraftPickRow.participant_id)
            .join(DraftSessionRow, DraftPickRow.draft_session_id == DraftSessionRow.id)
            .where(
                DraftSessionRow.league_id == league_id,
                DraftPickRow.league_membership_id == league_membership_id,
            )
        )
        result = await self._session.scalars(stmt)
        return frozenset(result.all())


def _league_from_row(row: FantasyLeagueRow) -> fe.FantasyLeague:
    template = row.lineup_template
    if isinstance(template, dict):
        lt: list | dict = template
    elif isinstance(template, list):
        lt = template
    else:
        lt = []
    return fe.FantasyLeague(
        id=row.id,
        tournament_id=row.tournament_id,
        name=row.name,
        commissioner_user_id=row.commissioner_user_id,
        status=row.status,
        settings=dict(row.settings or {}),
        lineup_template=lt,
    )


def _membership_from_row(row: LeagueMembershipRow) -> fe.LeagueMembership:
    return fe.LeagueMembership(
        id=row.id,
        league_id=row.league_id,
        user_id=row.user_id,
        nickname=row.nickname,
        role=row.role,
    )

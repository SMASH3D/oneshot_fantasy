"""SQLAlchemy implementation of IFantasyRepository."""

from uuid import UUID

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.domains.fantasy import entities as fe
from app.domains.fantasy.ports import IFantasyRepository
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

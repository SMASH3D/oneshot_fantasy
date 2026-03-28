"""Fantasy-side use cases."""

from uuid import UUID

from app.domains.fantasy import FantasyLeague, LeagueMembership
from app.domains.fantasy.exceptions import FantasyLeagueNotFoundError
from app.domains.fantasy.ports import IFantasyRepository


class FantasyService:
    def __init__(self, fantasy: IFantasyRepository) -> None:
        self._fantasy = fantasy

    async def get_league(self, league_id: UUID) -> FantasyLeague | None:
        return await self._fantasy.get_league(league_id)

    async def get_league_or_raise(self, league_id: UUID) -> FantasyLeague:
        league = await self._fantasy.get_league(league_id)
        if league is None:
            raise FantasyLeagueNotFoundError(league_id)
        return league

    async def list_memberships(self, league_id: UUID) -> list[LeagueMembership]:
        return await self._fantasy.list_memberships(league_id)

"""Persistence ports for the fantasy context."""

from typing import Protocol
from uuid import UUID

from app.domains.fantasy.entities import FantasyLeague, LeagueMembership


class IFantasyRepository(Protocol):
    async def get_league(self, league_id: UUID) -> FantasyLeague | None: ...

    async def list_memberships(self, league_id: UUID) -> list[LeagueMembership]: ...

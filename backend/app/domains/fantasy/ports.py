"""Persistence ports for the fantasy context."""

from typing import Protocol
from uuid import UUID

from app.domains.fantasy.entities import FantasyLeague, LeagueMembership, LeagueSummary


class IFantasyRepository(Protocol):
    async def get_league(self, league_id: UUID) -> FantasyLeague | None: ...

    async def list_memberships(self, league_id: UUID) -> list[LeagueMembership]: ...

    async def list_league_summaries(
        self,
        tournament_id: UUID | None = None,
    ) -> list[LeagueSummary]: ...

    async def list_draft_roster_participant_ids(
        self,
        league_id: UUID,
        league_membership_id: UUID,
    ) -> frozenset[UUID]:
        """Participant IDs drafted by this membership in the league (empty if no draft picks)."""
        ...

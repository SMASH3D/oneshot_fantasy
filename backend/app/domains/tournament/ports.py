"""Persistence ports for the tournament context."""

from typing import Any, Protocol
from uuid import UUID

from app.domains.tournament.entities import Match, Participant, Tournament, TournamentRound


class ITournamentRepository(Protocol):
    async def get_tournament(self, tournament_id: UUID) -> Tournament | None: ...

    async def list_tournaments(self) -> list[Tournament]: ...

    async def list_rounds(self, tournament_id: UUID) -> list[TournamentRound]: ...

    async def list_participants(self, tournament_id: UUID) -> list[Participant]: ...

    async def list_matches(
        self,
        tournament_id: UUID,
        tournament_round_id: UUID | None = None,
    ) -> list[Match]: ...

    async def patch_tournament_metadata(self, tournament_id: UUID, patch: dict[str, Any]) -> None:
        """Merge JSON keys into tournaments.metadata (worker cursors, etc.)."""
        ...

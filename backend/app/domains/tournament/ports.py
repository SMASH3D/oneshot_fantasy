"""Persistence ports for the tournament context."""

from typing import Protocol
from uuid import UUID

from app.domains.tournament.entities import Participant, Tournament, TournamentRound


class ITournamentRepository(Protocol):
    async def get_tournament(self, tournament_id: UUID) -> Tournament | None: ...

    async def list_tournaments(self) -> list[Tournament]: ...

    async def list_rounds(self, tournament_id: UUID) -> list[TournamentRound]: ...

    async def list_participants(self, tournament_id: UUID) -> list[Participant]: ...

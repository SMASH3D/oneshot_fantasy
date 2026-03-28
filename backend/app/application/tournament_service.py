"""Tournament-side use cases."""

from uuid import UUID

from app.domains.tournament import Participant, Tournament, TournamentRound
from app.domains.tournament.exceptions import TournamentNotFoundError
from app.domains.tournament.ports import ITournamentRepository


class TournamentService:
    def __init__(self, tournaments: ITournamentRepository) -> None:
        self._tournaments = tournaments

    async def get_tournament(self, tournament_id: UUID) -> Tournament | None:
        return await self._tournaments.get_tournament(tournament_id)

    async def get_tournament_or_raise(self, tournament_id: UUID) -> Tournament:
        t = await self._tournaments.get_tournament(tournament_id)
        if t is None:
            raise TournamentNotFoundError(tournament_id)
        return t

    async def list_tournaments(self) -> list[Tournament]:
        return await self._tournaments.list_tournaments()

    async def list_rounds(self, tournament_id: UUID) -> list[TournamentRound]:
        return await self._tournaments.list_rounds(tournament_id)

    async def list_participants(self, tournament_id: UUID) -> list[Participant]:
        return await self._tournaments.list_participants(tournament_id)

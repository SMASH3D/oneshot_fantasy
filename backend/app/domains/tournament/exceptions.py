from uuid import UUID


class TournamentNotFoundError(LookupError):
    def __init__(self, tournament_id: UUID) -> None:
        super().__init__(f"Tournament not found: {tournament_id}")
        self.tournament_id = tournament_id

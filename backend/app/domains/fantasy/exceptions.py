from uuid import UUID


class FantasyLeagueNotFoundError(LookupError):
    def __init__(self, league_id: UUID) -> None:
        super().__init__(f"Fantasy league not found: {league_id}")
        self.league_id = league_id

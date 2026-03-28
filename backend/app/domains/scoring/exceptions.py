from uuid import UUID


class ScoringRuleNotFoundError(LookupError):
    def __init__(self, league_id: UUID) -> None:
        super().__init__(f"No active scoring rule set for league: {league_id}")
        self.league_id = league_id

"""Scoring orchestration (calculator registry injected from infrastructure)."""

from typing import Any
from uuid import UUID

from app.domains.fantasy.exceptions import FantasyLeagueNotFoundError
from app.domains.fantasy.ports import IFantasyRepository
from app.domains.scoring.calculator import ScoringCalculator
from app.domains.scoring.exceptions import ScoringRuleNotFoundError
from app.domains.scoring.ports import IScoringRepository, IStatQuery


class CalculatorNotRegisteredError(LookupError):
    def __init__(self, engine_key: str) -> None:
        super().__init__(f"No ScoringCalculator registered for engine_key={engine_key!r}")
        self.engine_key = engine_key


class ScoringService:
    def __init__(
        self,
        scoring: IScoringRepository,
        stats: IStatQuery,
        fantasy: IFantasyRepository,
        calculators: dict[str, ScoringCalculator] | None = None,
    ) -> None:
        self._scoring = scoring
        self._stats = stats
        self._fantasy = fantasy
        self._calculators = calculators or {}

    def register_calculator(self, engine_key: str, calculator: ScoringCalculator) -> None:
        """Called during app startup wiring (per engine_key implementation)."""
        self._calculators[engine_key] = calculator

    async def recompute_membership_round(
        self,
        *,
        league_id: UUID,
        fantasy_round_id: UUID,
        league_membership_id: UUID,
    ) -> tuple[float, dict[str, Any]]:
        rule = await self._scoring.get_active_rule_set(league_id)
        if rule is None:
            raise ScoringRuleNotFoundError(league_id)

        league = await self._fantasy.get_league(league_id)
        if league is None:
            raise FantasyLeagueNotFoundError(league_id)

        calculator = self._calculators.get(rule.engine_key)
        if calculator is None:
            raise CalculatorNotRegisteredError(rule.engine_key)

        stat_rows = await self._stats.stat_events_for_round(
            tournament_id=league.tournament_id,
            fantasy_round_id=fantasy_round_id,
        )
        roster = await self._fantasy.list_draft_roster_participant_ids(
            league_id,
            league_membership_id,
        )
        if roster:
            stat_rows = [r for r in stat_rows if r.get("participant_id") in roster]
        else:
            stat_rows = []

        total, breakdown = calculator.compute(
            league_id=league_id,
            fantasy_round_id=fantasy_round_id,
            membership_id=league_membership_id,
            stat_rows=stat_rows,
            parameters=rule.parameters,
        )
        await self._scoring.save_round_score(
            fantasy_round_id=fantasy_round_id,
            league_membership_id=league_membership_id,
            points=total,
            breakdown=breakdown,
        )
        return total, breakdown

"""Ports for scoring persistence and stat reads."""

from typing import Any, Protocol
from uuid import UUID

from app.domains.scoring.rules import ScoringRuleSet


class IStatQuery(Protocol):
    async def stat_events_for_round(
        self,
        *,
        tournament_id: UUID,
        fantasy_round_id: UUID,
    ) -> list[dict[str, Any]]:
        """Normalized rows consumed by ScoringCalculator (shape is engine-specific)."""
        ...


class IScoringRepository(Protocol):
    async def get_active_rule_set(self, league_id: UUID) -> ScoringRuleSet | None: ...

    async def save_round_score(
        self,
        *,
        fantasy_round_id: UUID,
        league_membership_id: UUID,
        points: float,
        breakdown: dict[str, Any],
    ) -> None: ...

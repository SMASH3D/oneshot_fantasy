"""Scoring strategy interface (implemented per engine_key in infrastructure)."""

from __future__ import annotations

from typing import Any, Protocol
from uuid import UUID


class ScoringCalculator(Protocol):
    """Computes fantasy points from normalized inputs for one membership / round."""

    def compute(
        self,
        *,
        league_id: UUID,
        fantasy_round_id: UUID,
        membership_id: UUID,
        stat_rows: list[dict[str, Any]],
        parameters: dict[str, Any],
    ) -> tuple[float, dict[str, Any]]:
        """Return (total_points, breakdown_json_serializable)."""
        ...

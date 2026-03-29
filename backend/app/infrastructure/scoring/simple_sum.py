"""Reference scoring engine: weighted sum of stat lines (per metric_key)."""

from __future__ import annotations

from typing import Any
from uuid import UUID


class SimpleSumCalculator:
    """Weighted sum of stat rows; roster filtering is done in ScoringService."""

    def compute(
        self,
        *,
        league_id: UUID,
        fantasy_round_id: UUID,
        membership_id: UUID,
        stat_rows: list[dict[str, Any]],
        parameters: dict[str, Any],
    ) -> tuple[float, dict[str, Any]]:
        del league_id, fantasy_round_id, membership_id
        raw_weights = parameters.get("weights")
        weights: dict[str, Any] = raw_weights if isinstance(raw_weights, dict) else {}
        default_w = float(parameters.get("default_weight", 1.0))

        total = 0.0
        breakdown: dict[str, float] = {}
        for row in stat_rows:
            mk = str(row.get("metric_key", ""))
            raw_w = weights.get(mk, default_w)
            try:
                w = float(raw_w)
            except (TypeError, ValueError):
                w = default_w
            v = float(row.get("value_numeric", 0))
            pts = v * w
            total += pts
            breakdown[mk] = breakdown.get(mk, 0.0) + pts

        return total, breakdown

"""Tests for scoring_engine: performance bonus detection and score computation."""

import pytest
from ingestion.scoring.scoring_engine import compute_score, detect_performance_bonuses

# ---------------------------------------------------------------------------
# Minimal config used across tests — mirrors the League.php default structure
# ---------------------------------------------------------------------------
BASE_CONFIG = {
    "points": 1,
    "assists": 1.5,
    "rebounds": 1.2,
    "blocks": 3,
    "steals": 3,
    "turnovers": -1.5,
    "performance_bonuses": {
        "double_double": 3,
        "triple_double": 5,
        "quadruple_double": 8,
        "quintuple_double": 12,
        "five_by_five": 6,
    },
}


# ---------------------------------------------------------------------------
# detect_performance_bonuses unit tests
# ---------------------------------------------------------------------------

class TestDetectPerformanceBonuses:
    def test_no_bonus(self):
        stats = {"points": 8, "assists": 5, "rebounds": 4, "steals": 1, "blocks": 0}
        result = detect_performance_bonuses(stats)
        assert result["multi_category"] is None
        assert result["five_by_five"] is False

    def test_double_double(self):
        stats = {"points": 10, "assists": 0, "rebounds": 10, "steals": 0, "blocks": 0}
        result = detect_performance_bonuses(stats)
        assert result["multi_category"] == "double_double"
        assert result["five_by_five"] is False

    def test_triple_double(self):
        stats = {"points": 10, "assists": 10, "rebounds": 10, "steals": 0, "blocks": 0}
        result = detect_performance_bonuses(stats)
        assert result["multi_category"] == "triple_double"

    def test_quadruple_double(self):
        stats = {"points": 10, "assists": 10, "rebounds": 10, "steals": 10, "blocks": 0}
        result = detect_performance_bonuses(stats)
        assert result["multi_category"] == "quadruple_double"

    def test_quintuple_double(self):
        stats = {"points": 10, "assists": 10, "rebounds": 10, "steals": 10, "blocks": 10}
        result = detect_performance_bonuses(stats)
        assert result["multi_category"] == "quintuple_double"

    def test_five_by_five(self):
        stats = {"points": 5, "assists": 5, "rebounds": 5, "steals": 5, "blocks": 5}
        result = detect_performance_bonuses(stats)
        assert result["multi_category"] is None
        assert result["five_by_five"] is True

    def test_five_by_five_with_triple_double(self):
        stats = {"points": 10, "assists": 10, "rebounds": 10, "steals": 5, "blocks": 5}
        result = detect_performance_bonuses(stats)
        assert result["multi_category"] == "triple_double"
        assert result["five_by_five"] is True

    def test_missing_stats_treated_as_zero(self):
        result = detect_performance_bonuses({})
        assert result["multi_category"] is None
        assert result["five_by_five"] is False

    def test_none_values_treated_as_zero(self):
        stats = {"points": None, "assists": None, "rebounds": None, "steals": None, "blocks": None}
        result = detect_performance_bonuses(stats)
        assert result["multi_category"] is None
        assert result["five_by_five"] is False


# ---------------------------------------------------------------------------
# compute_score integration tests
# ---------------------------------------------------------------------------

class TestComputeScore:
    def test_double_double_adds_bonus(self):
        stats = {"points": 10, "assists": 0, "rebounds": 10, "steals": 0, "blocks": 0, "turnovers": 0}
        result = compute_score(stats, BASE_CONFIG)
        assert "bonuses" in result["breakdown"]
        assert "double_double" in result["breakdown"]["bonuses"]
        assert result["breakdown"]["bonuses"]["double_double"] == 3.0
        # Only double_double, no triple_double
        assert "triple_double" not in result["breakdown"]["bonuses"]

    def test_triple_double_replaces_double_double(self):
        stats = {"points": 10, "assists": 10, "rebounds": 10, "steals": 0, "blocks": 0, "turnovers": 0}
        result = compute_score(stats, BASE_CONFIG)
        bonuses = result["breakdown"]["bonuses"]
        assert "triple_double" in bonuses
        assert bonuses["triple_double"] == 5.0
        # double_double must NOT also be applied
        assert "double_double" not in bonuses

    def test_quintuple_double_is_highest(self):
        stats = {"points": 10, "assists": 10, "rebounds": 10, "steals": 10, "blocks": 10, "turnovers": 0}
        result = compute_score(stats, BASE_CONFIG)
        bonuses = result["breakdown"]["bonuses"]
        assert "quintuple_double" in bonuses
        for lower in ("double_double", "triple_double", "quadruple_double"):
            assert lower not in bonuses

    def test_five_by_five_stacks_with_multi(self):
        stats = {"points": 10, "assists": 10, "rebounds": 10, "steals": 5, "blocks": 5, "turnovers": 0}
        result = compute_score(stats, BASE_CONFIG)
        bonuses = result["breakdown"]["bonuses"]
        assert "triple_double" in bonuses
        assert "five_by_five" in bonuses
        # Total should include both
        base = result["breakdown"]["base_score"]
        assert result["total"] == round(base + 5 + 6, 4)

    def test_five_by_five_alone_no_multi(self):
        stats = {"points": 5, "assists": 5, "rebounds": 5, "steals": 5, "blocks": 5, "turnovers": 0}
        result = compute_score(stats, BASE_CONFIG)
        bonuses = result["breakdown"]["bonuses"]
        assert "five_by_five" in bonuses
        assert "double_double" not in bonuses

    def test_no_bonus_when_config_missing(self):
        """If performance_bonuses key is absent from config, no bonus is applied."""
        cfg = {k: v for k, v in BASE_CONFIG.items() if k != "performance_bonuses"}
        stats = {"points": 10, "assists": 10, "rebounds": 10, "steals": 10, "blocks": 10, "turnovers": 0}
        result = compute_score(stats, cfg)
        assert "bonuses" not in result["breakdown"]

    def test_breakdown_contains_base_score_and_total(self):
        stats = {"points": 20, "assists": 5, "rebounds": 8, "steals": 0, "blocks": 0, "turnovers": 2}
        result = compute_score(stats, BASE_CONFIG)
        assert "base_score" in result["breakdown"]
        assert "total" in result["breakdown"]
        assert result["breakdown"]["total"] == result["total"]

    def test_steals_and_blocks_always_in_breakdown(self):
        """Bonus-eligible stats must appear even when their weighted score is zero."""
        stats = {"points": 10, "assists": 10, "rebounds": 10, "steals": 0, "blocks": 0, "turnovers": 0}
        breakdown = compute_score(stats, BASE_CONFIG)["breakdown"]
        assert "steals" in breakdown
        assert "blocks" in breakdown
        assert breakdown["steals"] == 0.0
        assert breakdown["blocks"] == 0.0

    def test_total_matches_manual_calculation(self):
        # 10 pts * 1 + 10 ast * 1.5 + 10 reb * 1.2 = 10 + 15 + 12 = 37 base
        # triple_double = 5 → total = 42
        stats = {"points": 10, "assists": 10, "rebounds": 10, "steals": 0, "blocks": 0, "turnovers": 0}
        result = compute_score(stats, BASE_CONFIG)
        assert result["breakdown"]["base_score"] == 37.0
        assert result["total"] == 42.0

    def test_idempotent_recomputation(self):
        """Calling compute_score twice on the same stats yields the same result."""
        stats = {"points": 25, "assists": 12, "rebounds": 10, "steals": 0, "blocks": 0, "turnovers": 3}
        r1 = compute_score(stats, BASE_CONFIG)
        r2 = compute_score(stats, BASE_CONFIG)
        assert r1 == r2

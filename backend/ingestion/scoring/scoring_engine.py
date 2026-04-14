"""Scoring engine to compute fantasy logic dynamically from config."""

# Stats eligible for performance bonus detection (double-double, triple-double, etc.)
BONUS_ELIGIBLE_STATS = ["points", "assists", "rebounds", "steals", "blocks"]

# Ordered from highest to lowest so we can pick the best applicable bonus
_MULTI_CATEGORY_BONUSES = [
    ("quintuple_double", 5),
    ("quadruple_double", 4),
    ("triple_double", 3),
    ("double_double", 2),
]


def detect_performance_bonuses(player_stats: dict[str, float]) -> dict:
    """
    Detect which performance bonuses a player has earned.

    Returns:
        {
            "multi_category": str | None,  # highest applicable bonus key, or None
            "five_by_five": bool,          # True if >= 5 in all eligible stats
        }
    """
    double_digit_count = sum(
        1 for stat in BONUS_ELIGIBLE_STATS
        if float(player_stats.get(stat) or 0.0) >= 10.0
    )

    five_by_five = all(
        float(player_stats.get(stat) or 0.0) >= 5.0
        for stat in BONUS_ELIGIBLE_STATS
    )

    multi_category = None
    for bonus_key, threshold in _MULTI_CATEGORY_BONUSES:
        if double_digit_count >= threshold:
            multi_category = bonus_key
            break

    return {
        "multi_category": multi_category,
        "five_by_five": five_by_five,
    }


def compute_score(player_stats: dict[str, float], scoring_config: dict) -> dict:
    """
    Computes fantasy score from player box score stats based on the league's scoring rules.

    Returns:
        {
            "total": float,
            "breakdown": {
                "<stat>": float,          # per-stat weighted scores
                "base_score": float,      # sum before bonuses
                "bonuses": {              # present only if bonuses were applied
                    "<bonus_key>": float,
                },
                "total": float,
            }
        }
    """
    total = 0.0
    breakdown: dict = {}

    # Base scoring
    # BONUS_ELIGIBLE_STATS are always written to the breakdown (even at 0) so that
    # readers can audit double/triple/quad/quint/five_by_five qualification.
    base_stats = ["points", "assists", "rebounds", "blocks", "steals", "turnovers"]
    for stat in base_stats:
        val = player_stats.get(stat, 0.0)
        weight = scoring_config.get(stat, 0.0)
        if val is None:
            val = 0.0
        score = float(val) * float(weight)
        if score != 0.0 or stat in BONUS_ELIGIBLE_STATS:
            breakdown[stat] = score
            total += score

    # Efficiency bonuses
    eff = scoring_config.get("efficiency", {})

    def check_efficiency(stat_key: str, cfg: dict) -> float:
        val = player_stats.get(stat_key)
        if val is not None and float(val) >= float(cfg.get("threshold", 1.0)):
            b_val = float(cfg.get("value", 0.0))
            breakdown[f"{stat_key}_bonus"] = b_val
            return b_val
        return 0.0

    total += check_efficiency("fg_pct", eff.get("fg_pct_bonus", {}))
    total += check_efficiency("three_pt_pct", eff.get("three_pt_pct_bonus", {}))
    total += check_efficiency("ft_pct", eff.get("ft_pct_bonus", {}))

    breakdown["base_score"] = round(total, 4)

    # Performance bonuses (config-driven, no hardcoded values)
    perf_bonuses_cfg = scoring_config.get("performance_bonuses", {})
    detected = detect_performance_bonuses(player_stats)
    applied_bonuses: dict[str, float] = {}

    # Apply only the highest multi-category bonus (no stacking among these)
    if detected["multi_category"] and detected["multi_category"] in perf_bonuses_cfg:
        bonus_val = float(perf_bonuses_cfg[detected["multi_category"]])
        applied_bonuses[detected["multi_category"]] = bonus_val
        total += bonus_val

    # five_by_five stacks independently with multi-category bonuses
    if detected["five_by_five"] and "five_by_five" in perf_bonuses_cfg:
        bonus_val = float(perf_bonuses_cfg["five_by_five"])
        applied_bonuses["five_by_five"] = bonus_val
        total += bonus_val

    if applied_bonuses:
        breakdown["bonuses"] = applied_bonuses

    total = round(total, 4)
    breakdown["total"] = total

    return {
        "total": total,
        "breakdown": breakdown,
    }

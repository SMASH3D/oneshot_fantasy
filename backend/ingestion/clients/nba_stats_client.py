"""NBA Stats fetching and normalization."""

from datetime import datetime, timezone
from typing import Any
import logging

_LOG = logging.getLogger(__name__)

def _current_nba_season() -> str:
    now = datetime.now(timezone.utc)
    y = now.year
    if now.month >= 10:
        return f"{y}-{str(y + 1)[-2:]}"
    return f"{y - 1}-{str(y)[-2:]}"


def _float(val: Any) -> float | None:
    if val is None:
        return None
    try:
        f = float(val)
        import math
        if math.isnan(f) or math.isinf(f):
            return None
        return f
    except (TypeError, ValueError):
        return None

def _int(val: Any) -> int | None:
    if val is None:
        return None
    try:
        return int(val)
    except (TypeError, ValueError):
        return None

class NBAStatsClient:
    """Fetches player season statistics via nba_api."""

    def get_player_stats(self, season: str | None = None) -> list[dict[str, Any]]:
        from nba_api.stats.endpoints.leaguedashplayerstats import LeagueDashPlayerStats

        target_season = season or _current_nba_season()
        _LOG.info("Fetching NBA player stats for season %s", target_season)

        endpoint = LeagueDashPlayerStats(
            season=target_season,
            per_mode_detailed="Totals",
            # We strictly fetch current season for stable metrics mapping
        )

        df = endpoint.get_data_frames()[0]
        records = df.to_dict("records")
        out = []

        for raw in records:
            pid = raw.get("PLAYER_ID")
            if pid is None:
                continue

            out.append({
                "player_id": str(_int(pid)),
                "games_played": _int(raw.get("GP")),
                "games_started": _int(raw.get("GS")),
                "minutes_played": _float(raw.get("MIN")),
                "plus_minus": _float(raw.get("PLUS_MINUS")),
                "points": _float(raw.get("PTS")),
                "assists": _float(raw.get("AST")),
                "rebounds": _float(raw.get("REB")),
                "blocks": _float(raw.get("BLK")),
                "steals": _float(raw.get("STL")),
                "turnovers": _float(raw.get("TOV")),
                "three_point_pct": _float(raw.get("FG3_PCT")),
                "field_goal_pct": _float(raw.get("FG_PCT")),
                "free_throw_pct": _float(raw.get("FT_PCT")),
            })

        return out

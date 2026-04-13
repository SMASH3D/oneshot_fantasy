"""Fetch NBA Player Boxscores safely grouped via game logs."""
from typing import Any
import logging
from datetime import datetime, timezone

_LOG = logging.getLogger(__name__)

def _current_nba_season() -> str:
    now = datetime.now(timezone.utc)
    y = now.year
    if now.month >= 10:
        return f"{y}-{str(y + 1)[-2:]}"
    return f"{y - 1}-{str(y)[-2:]}"

def _float(val: Any) -> float | None:
    if val is None: return None
    try: return float(val)
    except (TypeError, ValueError): return None

class NBAGameStatsClient:
    def get_game_stats(self, season: str | None = None) -> list[dict[str, Any]]:
        from nba_api.stats.endpoints.leaguegamelog import LeagueGameLog
        target_season = season or _current_nba_season()
        _LOG.info("Fetching NBA playoff player game logs for season %s", target_season)
        
        endpoint = LeagueGameLog(
            season=target_season,
            season_type_all_star="Playoffs",
            player_or_team_abbreviation="P"  # Individual players
        )
        
        records = endpoint.get_data_frames()[0].to_dict("records")
        out = []
        for r in records:
            pid = r.get("PLAYER_ID")
            gid = r.get("GAME_ID")
            if not pid or not gid: continue
            
            m = r.get("MIN")
            mins = 0.0
            if isinstance(m, str) and ":" in m:
                parts = m.split(":")
                mins = float(parts[0]) + float(parts[1])/60.0
            else:
                mins = _float(m)
                
            out.append({
                "player_external_id": str(int(pid)),
                "game_external_id": str(gid),
                "points": _float(r.get("PTS")),
                "assists": _float(r.get("AST")),
                "rebounds": _float(r.get("REB")),
                "blocks": _float(r.get("BLK")),
                "steals": _float(r.get("STL")),
                "turnovers": _float(r.get("TOV")),
                "minutes_played": mins,
                "plus_minus": _float(r.get("PLUS_MINUS"))
            })
            
        return out

"""Fetch NBA Games representing schedules correctly mapping home/away identities."""
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

class NBAGameClient:
    def get_playoff_games(self, season: str | None = None) -> list[dict[str, Any]]:
        from nba_api.stats.endpoints.leaguegamefinder import LeagueGameFinder
        
        target_season = season or _current_nba_season()
        _LOG.info("Fetching NBA playoff games for season %s", target_season)
        
        finder = LeagueGameFinder(
            season_nullable=target_season,
            season_type_nullable="Playoffs",
            league_id_nullable="00"
        )
        
        df = finder.get_data_frames()[0]
        records = df.to_dict("records")
        
        games = {}
        for row in records:
            gid = str(row.get("GAME_ID"))
            matchup = str(row.get("MATCHUP", ""))
            
            # e.g., "MIA @ BOS" or "BOS vs. MIA"
            is_home = "vs." in matchup
            team_id = str(row.get("TEAM_ID"))
            pts = row.get("PTS")
            
            if gid not in games:
                games[gid] = {
                    "external_id": gid,
                    "date": row.get("GAME_DATE"), # YYYY-MM-DD
                    "home_team_external_id": None,
                    "away_team_external_id": None,
                    "home_score": None,
                    "away_score": None
                }
                
            if is_home:
                games[gid]["home_team_external_id"] = team_id
                games[gid]["home_score"] = pts
            else:
                games[gid]["away_team_external_id"] = team_id
                games[gid]["away_score"] = pts

        # Clean mapping ensuring valid matches
        out = []
        for gid, g in games.items():
            if not g["home_team_external_id"] or not g["away_team_external_id"]:
                continue
                
            status = 'live'
            if g["home_score"] is not None and g["away_score"] is not None:
                status = 'complete'
            else:
                status = 'scheduled'
                
            g["status"] = status
            out.append(g)
            
        return out

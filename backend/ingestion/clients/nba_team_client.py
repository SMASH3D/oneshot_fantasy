"""Fetch NBA Teams natively matching external identities securely."""
from typing import Any
import logging

_LOG = logging.getLogger(__name__)

class NBATeamClient:
    def get_teams(self) -> list[dict[str, Any]]:
        from nba_api.stats.static import teams
        raw_teams = teams.get_teams()
        
        out = []
        for t in raw_teams:
            out.append({
                "external_id": str(t["id"]),
                "name": t.get("full_name") or t.get("nickname"),
                "abbreviation": t.get("abbreviation"),
                "city": t.get("city"),
                "metadata": {
                    "state": t.get("state"),
                    "year_founded": t.get("year_founded")
                }
            })
        return out

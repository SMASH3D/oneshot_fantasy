"""Fetch NBA playoff series data from nba_api (PlayoffSeries endpoint)."""
from __future__ import annotations

import logging
from typing import Any

_LOG = logging.getLogger(__name__)

# NBA canonical round names keyed by round number
NBA_ROUND_NAMES: dict[int, str] = {
    1: "First Round",
    2: "Conference Semifinals",
    3: "Conference Finals",
    4: "NBA Finals",
}

NBA_CANONICAL_KEYS: dict[int, str] = {
    1: "first_round",
    2: "conf_semi",
    3: "conf_finals",
    4: "finals",
}


class NBAPlayoffsClient:
    """Wraps the nba_api PlayoffSeries endpoint to return structured series data."""

    def get_playoff_series(self, season: str) -> list[dict[str, Any]]:
        """
        Return a list of series dicts for the given season (e.g. '2024-25').

        Each dict has:
          - series_id: str  (unique, e.g. "0042400111")
          - round_num: int  (1-4)
          - round_name: str
          - canonical_key: str
          - home_team_external_id: str  (NBA team_id)
          - away_team_external_id: str
          - wins_home: int
          - wins_away: int
          - status: str  (pending | active | completed)
          - conference: str | None  (East / West / None for Finals)
          - game_ids: list[str]
        """
        from nba_api.stats.endpoints.commonplayoffseries import CommonPlayoffSeries

        _LOG.info("Fetching playoff series for season %s", season)

        ps = CommonPlayoffSeries(season=season, league_id="00")
        df = ps.get_data_frames()[0]
        if df.empty:
            _LOG.warning("No playoff series data returned for season %s", season)
            return []

        results: list[dict[str, Any]] = []
        for _, row in df.iterrows():
            round_num = int(row.get("ROUND_NUM") or 0)
            if round_num == 0:
                continue

            home_wins = int(row.get("HOME_TEAM_WINS") or 0)
            visitor_wins = int(row.get("VISITOR_TEAM_WINS") or 0)
            is_complete = (home_wins >= 4 or visitor_wins >= 4)

            results.append({
                "series_id": str(row.get("SERIES_ID") or ""),
                "round_num": round_num,
                "round_name": NBA_ROUND_NAMES.get(round_num, f"Round {round_num}"),
                "canonical_key": NBA_CANONICAL_KEYS.get(round_num, f"round_{round_num}"),
                "home_team_external_id": str(row.get("HOME_TEAM_ID") or ""),
                "away_team_external_id": str(row.get("VISITOR_TEAM_ID") or ""),
                "wins_home": home_wins,
                "wins_away": visitor_wins,
                "status": "completed" if is_complete else "active" if (home_wins + visitor_wins) > 0 else "pending",
                "conference": str(row.get("CONFERENCE") or "") or None,
                "game_ids": [],  # will be populated by the importer from the games table
            })

        _LOG.info("Fetched %d playoff series entries", len(results))
        return results

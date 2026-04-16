"""Fetch NBA playoff series data from nba_api.

Uses two endpoints:
  - CommonPlayoffSeries  → series structure (teams, round, game IDs)
  - LeagueGameFinder     → game outcomes (W/L) to compute series wins

CommonPlayoffSeries actual columns: GAME_ID, HOME_TEAM_ID, VISITOR_TEAM_ID, SERIES_ID, GAME_NUM
(Note: ROUND_NUM / HOME_TEAM_WINS / CONFERENCE do NOT exist in this endpoint.)

SERIES_ID format (9 chars, e.g. "004240010"):
  chars 0-1 = league ("00")
  char  2   = season type ("4" = playoffs)
  chars 3-4 = season year ("24" = 2024-25)
  chars 5-6 = always "00"
  char  7   = round number (1-4)
  char  8   = series index within round (0-based; 0-3 = East, 4-7 = West for round 1)
"""
from __future__ import annotations

import logging
from typing import Any

_LOG = logging.getLogger(__name__)

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

# For rounds 1-3, series indices below this threshold are East; above are West.
# Round 1: 8 series (0-7) → East 0-3, West 4-7 → threshold 4
# Round 2: 4 series (0-3) → East 0-1, West 2-3 → threshold 2
# Round 3: 2 series (0-1) → East 0,   West 1   → threshold 1
_EAST_THRESHOLD: dict[int, int] = {1: 4, 2: 2, 3: 1}


def _round_num(series_id: str) -> int:
    try:
        return int(series_id[7])
    except (IndexError, ValueError):
        return 0


def _conference(series_id: str, round_num: int) -> str | None:
    if round_num == 4:
        return None
    try:
        series_idx = int(series_id[8])
    except (IndexError, ValueError):
        return None
    threshold = _EAST_THRESHOLD.get(round_num, 0)
    return "East" if series_idx < threshold else "West"


class NBAPlayoffsClient:
    """Wraps nba_api endpoints to return structured playoff series data."""

    def get_playoff_series(self, season: str) -> list[dict[str, Any]]:
        """
        Return one dict per playoff series for the given season (e.g. '2024-25').

        Each dict has:
          series_id             str   e.g. "004240010"
          round_num             int   1-4
          round_name            str   e.g. "First Round"
          canonical_key         str   e.g. "first_round"
          home_team_external_id str   NBA team_id
          away_team_external_id str   NBA team_id
          wins_home             int
          wins_away             int
          status                str   pending | active | completed
          conference            str | None   East / West / None (Finals)
          game_ids              list[str]
        """
        from nba_api.stats.endpoints.commonplayoffseries import CommonPlayoffSeries
        from nba_api.stats.endpoints.leaguegamefinder import LeagueGameFinder

        _LOG.info("Fetching playoff series for season %s", season)

        # --- Step 1: series structure ---
        ps_df = CommonPlayoffSeries(season=season, league_id="00").get_data_frames()[0]
        if ps_df.empty:
            _LOG.warning("CommonPlayoffSeries returned no data for season %s", season)
            return []

        # Deduplicate by SERIES_ID; home/away are consistent across rows for the same series.
        series_map: dict[str, dict[str, Any]] = {}
        for _, row in ps_df.iterrows():
            sid = str(row["SERIES_ID"])
            gid = str(row["GAME_ID"])
            if sid not in series_map:
                series_map[sid] = {
                    "series_id": sid,
                    "home_team_external_id": str(int(row["HOME_TEAM_ID"])),
                    "away_team_external_id": str(int(row["VISITOR_TEAM_ID"])),
                    "game_ids": [],
                    "wins_home": 0,
                    "wins_away": 0,
                }
            series_map[sid]["game_ids"].append(gid)

        # --- Step 2: wins from game outcomes ---
        gf_df = LeagueGameFinder(
            season_nullable=season,
            season_type_nullable="Playoffs",
            league_id_nullable="00",
        ).get_data_frames()[0]

        # game_results[game_id][team_id] = "W" | "L"
        game_results: dict[str, dict[str, str]] = {}
        for _, row in gf_df.iterrows():
            gid = str(row["GAME_ID"])
            tid = str(int(row["TEAM_ID"]))
            game_results.setdefault(gid, {})[tid] = str(row.get("WL") or "")

        for sid, s in series_map.items():
            home_id = s["home_team_external_id"]
            away_id = s["away_team_external_id"]
            for gid in s["game_ids"]:
                outcomes = game_results.get(gid, {})
                if outcomes.get(home_id) == "W":
                    s["wins_home"] += 1
                elif outcomes.get(away_id) == "W":
                    s["wins_away"] += 1

        # --- Step 3: enrich and return ---
        results: list[dict[str, Any]] = []
        for sid, s in series_map.items():
            rn = _round_num(sid)
            if rn == 0:
                _LOG.warning("Could not parse round number from series_id=%s — skipping", sid)
                continue
            wh, wa = s["wins_home"], s["wins_away"]
            results.append({
                **s,
                "round_num": rn,
                "round_name": NBA_ROUND_NAMES.get(rn, f"Round {rn}"),
                "canonical_key": NBA_CANONICAL_KEYS.get(rn, f"round_{rn}"),
                "status": "completed" if (wh >= 4 or wa >= 4) else "active" if (wh + wa) > 0 else "pending",
                "conference": _conference(sid, rn),
            })

        _LOG.info("Fetched %d playoff series entries", len(results))
        return results

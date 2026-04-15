"""
Idempotent importer for NBA playoff series → tournament_rounds.

For each series from the API:
  1. Upserts a Round row (canonical_key = 'first_round' etc, home/away team, order_index, status).
  2. Links existing Game rows to that Round via external game ID prefix matching.
  3. Updates Round.metadata with current series scores (wins_home / wins_away).
"""
from __future__ import annotations

import json
import logging
from dataclasses import dataclass
from typing import Any, Protocol

from ingestion.db.connection import get_connection

_LOG = logging.getLogger(__name__)

ROUND_ORDER_INDEX: dict[int, int] = {1: 1, 2: 2, 3: 3, 4: 4}


class SupportsSeriesFetch(Protocol):
    def get_playoff_series(self, season: str) -> list[dict[str, Any]]: ...


@dataclass
class PlayoffsImportSummary:
    rounds_upserted: int
    games_linked: int
    total_series: int


class PlayoffsImporter:
    """Upserts Round rows from playoff series data and links Game records."""

    def __init__(self, client: SupportsSeriesFetch, *, sport: str = "basketball") -> None:
        self._client = client
        self._sport = sport

    def run(self, season: str, tournament_slug: str) -> PlayoffsImportSummary:
        series_list = self._client.get_playoff_series(season=season)
        if not series_list:
            return PlayoffsImportSummary(0, 0, 0)

        with get_connection() as conn:
            with conn.cursor() as cur:
                # --- resolve tournament ID ---
                cur.execute(
                    "SELECT id FROM tournaments WHERE slug = %s",
                    (tournament_slug,)
                )
                row = cur.fetchone()
                if not row:
                    _LOG.error("Tournament not found for slug=%s", tournament_slug)
                    return PlayoffsImportSummary(0, 0, 0)
                tournament_id = row[0]

                # --- resolve team external_id → internal UUID map ---
                cur.execute(
                    "SELECT id, external_id FROM teams WHERE sport = %s", (self._sport,)
                )
                teams: dict[str, str] = {str(r[1]): str(r[0]) for r in cur.fetchall()}

                rounds_upserted = 0
                games_linked = 0

                for series in series_list:
                    home_fk = teams.get(series["home_team_external_id"])
                    away_fk = teams.get(series["away_team_external_id"])

                    if not home_fk or not away_fk:
                        _LOG.warning(
                            "Skipping series %s — teams not found (home=%s away=%s)",
                            series["series_id"],
                            series["home_team_external_id"],
                            series["away_team_external_id"],
                        )
                        continue

                    metadata = json.dumps({
                        "series_id": series["series_id"],
                        "wins_home": series["wins_home"],
                        "wins_away": series["wins_away"],
                        "conference": series["conference"],
                    })

                    # Upsert Round — unique by (tournament_id, order_index) combined with series_id in metadata
                    # We use series_id stored in metadata as the real dedup key, but the unique constraint
                    # covers (tournament_id, order_index). For multi-series per round we encode conference
                    # into a sub-index offset: East=0, West=4 per round (e.g., round 1 East series get 1..4, West 5..8).
                    # For simplicity, we rely on canonical_key + series_id to generate a stable order_index.
                    order_index = self._series_order_index(series)

                    cur.execute("""
                        INSERT INTO tournament_rounds
                            (id, tournament_id, order_index, name, canonical_key,
                             home_team_id, away_team_id, status, metadata, created_at, updated_at)
                        VALUES
                            (gen_random_uuid(), %s, %s, %s, %s, %s, %s, %s, %s::jsonb, NOW(), NOW())
                        ON CONFLICT (tournament_id, order_index) DO UPDATE SET
                            name         = EXCLUDED.name,
                            canonical_key = EXCLUDED.canonical_key,
                            home_team_id = EXCLUDED.home_team_id,
                            away_team_id = EXCLUDED.away_team_id,
                            status       = EXCLUDED.status,
                            metadata     = tournament_rounds.metadata || EXCLUDED.metadata::jsonb,
                            updated_at   = NOW()
                        RETURNING id
                    """, (
                        tournament_id,
                        order_index,
                        series["round_name"],
                        series["canonical_key"],
                        home_fk,
                        away_fk,
                        series["status"],
                        metadata,
                    ))
                    round_row = cur.fetchone()
                    if round_row:
                        rounds_upserted += 1
                        round_id = round_row[0]

                        # Link games: NBA game IDs start with the series prefix (first 8 chars of series_id)
                        series_prefix = series["series_id"][:8]
                        cur.execute("""
                            UPDATE games
                            SET    tournament_round_id = %s, updated_at = NOW()
                            WHERE  tournament_id = %s
                              AND  tournament_round_id IS NULL
                              AND  external_id LIKE %s
                        """, (round_id, tournament_id, f"{series_prefix}%"))
                        games_linked += cur.rowcount

        _LOG.info(
            "Playoffs import done — %d rounds upserted, %d games linked",
            rounds_upserted, games_linked,
        )
        return PlayoffsImportSummary(
            rounds_upserted=rounds_upserted,
            games_linked=games_linked,
            total_series=len(series_list),
        )

    @staticmethod
    def _series_order_index(series: dict[str, Any]) -> int:
        """
        Generate a stable, unique order_index per series.
        Encodes round_num (1-4) and conference (East=0, West=10) and a series counter.
        Finals (round 4) always gets order_index 40.
        """
        round_num = series["round_num"]
        if round_num == 4:
            return 40
        conference = (series.get("conference") or "").lower()
        conf_offset = 0 if "east" in conference else 10
        # Use series_id last digit as tiebreaker within same round/conference
        series_tiebreak = int(series["series_id"][-1]) if series["series_id"] else 0
        return round_num * 10 + conf_offset + series_tiebreak

"""
Idempotent importer for team participation status within a tournament.

Derives participation records directly from the tournament_rounds table:
  - For each completed series (wins_home >= 4 or wins_away >= 4):
      winner team → 'active' (still in) or 'champion' (Finals winner)
      loser team  → 'eliminated'
  - For incomplete series:
      both teams  → 'active'

Designed to be re-run after every round update — safe via
ON CONFLICT (tournament_id, team_id) DO UPDATE.

Play-in rounds (type = "playin" in metadata) are skipped; those teams are
managed separately since their bracket position isn't yet determined.
"""
from __future__ import annotations

import logging
from dataclasses import dataclass
from typing import Any

from ingestion.db.connection import get_connection

_LOG = logging.getLogger(__name__)

# canonical_key of the Finals round — winner gets 'champion'
FINALS_CANONICAL_KEY = "finals"


@dataclass
class ParticipationImportSummary:
    upserted: int
    tournament: str


class TournamentParticipationImporter:
    """Reads tournament_rounds for a tournament and upserts tournament_participations."""

    def run(self, tournament_slug: str) -> ParticipationImportSummary:
        with get_connection() as conn:
            with conn.cursor() as cur:
                # --- Resolve tournament ---
                cur.execute("SELECT id, name FROM tournaments WHERE slug = %s", (tournament_slug,))
                row = cur.fetchone()
                if not row:
                    _LOG.error("Tournament not found: slug=%s", tournament_slug)
                    return ParticipationImportSummary(0, tournament_slug)
                tournament_id, tournament_name = row[0], row[1]

                # --- Load rounds with team data and metadata ---
                cur.execute("""
                    SELECT id, canonical_key, home_team_id, away_team_id, metadata
                    FROM   tournament_rounds
                    WHERE  tournament_id = %s
                """, (tournament_id,))
                rounds: list[dict[str, Any]] = [
                    {
                        "id": r[0],
                        "canonical_key": r[1] or "",
                        "home_team_id": r[2],
                        "away_team_id": r[3],
                        "metadata": r[4] or {},
                    }
                    for r in cur.fetchall()
                ]

                upserted = 0

                for rnd in rounds:
                    meta = rnd["metadata"]

                    # Skip play-in rounds — these teams don't have bracket positions yet
                    if meta.get("type") == "playin":
                        _LOG.debug("Skipping play-in round %s", rnd["canonical_key"])
                        continue

                    home_id = rnd["home_team_id"]
                    away_id = rnd["away_team_id"]

                    if not home_id or not away_id:
                        _LOG.debug("Skipping round %s — one or both teams missing", rnd["canonical_key"])
                        continue

                    wins_home = int(meta.get("wins_home") or 0)
                    wins_away = int(meta.get("wins_away") or 0)
                    is_finals = rnd["canonical_key"] == FINALS_CANONICAL_KEY

                    if wins_home >= 4:
                        home_status = "champion" if is_finals else "active"
                        away_status = "eliminated"
                    elif wins_away >= 4:
                        home_status = "eliminated"
                        away_status = "champion" if is_finals else "active"
                    else:
                        home_status = "active"
                        away_status = "active"

                    for team_id, status in [(home_id, home_status), (away_id, away_status)]:
                        cur.execute("""
                            INSERT INTO tournament_participations
                                (id, tournament_id, team_id, status, metadata, created_at, updated_at)
                            VALUES
                                (gen_random_uuid(), %s, %s, %s, '{}'::json, NOW(), NOW())
                            ON CONFLICT (tournament_id, team_id) DO UPDATE SET
                                status     = EXCLUDED.status,
                                updated_at = NOW()
                        """, (tournament_id, team_id, status))
                        upserted += 1

                _LOG.info(
                    "Participation import done — %d upserted for tournament '%s'",
                    upserted, tournament_name,
                )
                return ParticipationImportSummary(upserted=upserted, tournament=tournament_name)

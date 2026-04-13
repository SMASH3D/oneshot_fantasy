"""Importer service strictly mapped for dynamic participant stats."""

import logging
from dataclasses import dataclass
from typing import Protocol, Any
from psycopg2.extras import execute_values

from ingestion.db.connection import get_connection

_LOG = logging.getLogger(__name__)

class SupportsPlayerStatsFetch(Protocol):
    def get_player_stats(self, season: str | None = None) -> list[dict[str, Any]]: ...

@dataclass
class StatsImportSummary:
    inserted: int
    updated: int
    total: int

class PlayerStatsImporter:
    def __init__(self, client: SupportsPlayerStatsFetch, *, sport: str = "basketball") -> None:
        self._client = client
        self._sport = sport

    def run(self, season: str) -> StatsImportSummary:
        stats_list = self._client.get_player_stats(season=season)
        if not stats_list:
            _LOG.warning("No stats retrieved from client.")
            return StatsImportSummary(inserted=0, updated=0, total=0)
            
        with get_connection() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT id, code, unit FROM stat_definitions WHERE sport = %s", (self._sport,))
                defs = {row[1]: {"id": row[0], "unit": row[2]} for row in cur.fetchall()}

                if not defs:
                    _LOG.error("No stat definitions found for sport '%s'. Run Symfony fixtures first.", self._sport)
                    return StatsImportSummary(inserted=0, updated=0, total=0)

                cur.execute("SELECT id, external_id FROM participants WHERE sport = %s", (self._sport,))
                participants = {str(row[1]): row[0] for row in cur.fetchall()}

                upsert_rows = []
                # Map codes we expect the client output to emit
                supported_codes = [
                    "points", "assists", "rebounds", "blocks", "steals", 
                    "turnovers", "three_point_pct", "field_goal_pct", "free_throw_pct",
                    "games_played", "games_started", "minutes_played", "plus_minus"
                ]

                for sp in stats_list:
                    p_external_id = sp.get("player_id")
                    if p_external_id not in participants:
                        continue
                        
                    participant_id = participants[p_external_id]
                    gp = sp.get("games_played", 1) or 1
                    
                    for mapping_code in supported_codes:
                        if mapping_code not in defs:
                            continue
                            
                        val = sp.get(mapping_code)
                        if val is None:
                            continue
                            
                        definition = defs[mapping_code]
                        total_val = float(val)
                        
                        if definition["unit"] == "percentage":
                            avg_val = total_val
                        else:
                            avg_val = round(total_val / gp, 4) if gp > 0 else 0.0
                            
                        upsert_rows.append((
                            participant_id, 
                            definition["id"], 
                            season, 
                            total_val, 
                            avg_val
                        ))

                if not upsert_rows:
                    _LOG.warning("No valid stats to insert. Could mean missing participants.")
                    return StatsImportSummary(inserted=0, updated=0, total=0)

                query = """
                    INSERT INTO participant_stats 
                    (id, participant_id, stat_definition_id, season, total_value, average_value, created_at, updated_at)
                    VALUES %s
                    ON CONFLICT (participant_id, stat_definition_id, season) DO UPDATE SET
                        total_value = EXCLUDED.total_value,
                        average_value = EXCLUDED.average_value,
                        updated_at = NOW()
                    RETURNING (xmax = 0) AS inserted;
                """
                template = "(gen_random_uuid(), %s, %s, %s, %s, %s, NOW(), NOW())"
                
                results = execute_values(
                    cur, 
                    query, 
                    upsert_rows, 
                    template=template,
                    fetch=True
                )

                if results is None:
                     return StatsImportSummary(inserted=0, updated=0, total=len(stats_list))

                inserted_count = sum(1 for row in results if row[0] is True)
                updated_count = len(upsert_rows) - inserted_count

                _LOG.info("Import finished: %d individual statistic metrics pushed (%d new, %d updated)", len(upsert_rows), inserted_count, updated_count)
                return StatsImportSummary(inserted=inserted_count, updated=updated_count, total=len(stats_list))

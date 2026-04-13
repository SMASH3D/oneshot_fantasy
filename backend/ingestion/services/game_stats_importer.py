"""Importer connecting absolute box score stats to participants per active matchups."""
import logging
from dataclasses import dataclass
from typing import Protocol, Any
from psycopg2.extras import execute_values
from ingestion.db.connection import get_connection

_LOG = logging.getLogger(__name__)

class SupportsGameStatsFetch(Protocol):
    def get_game_stats(self, season: str | None = None) -> list[dict[str, Any]]: ...

@dataclass
class GameStatImportSummary:
    inserted: int
    updated: int
    total: int

class GameStatsImporter:
    def __init__(self, client: SupportsGameStatsFetch, *, sport: str = "basketball") -> None:
        self._client = client
        self._sport = sport

    def run(self, season: str) -> GameStatImportSummary:
        raw_stats = self._client.get_game_stats(season=season)
        if not raw_stats:
            return GameStatImportSummary(0, 0, 0)
            
        with get_connection() as conn:
            with conn.cursor() as cur:
                # 1. Stat definitions
                cur.execute("SELECT id, code FROM stat_definitions WHERE sport = %s", (self._sport,))
                defs = {row[1]: row[0] for row in cur.fetchall()}
                
                # 2. Participants map
                cur.execute("SELECT id, external_id FROM participants WHERE sport = %s", (self._sport,))
                parts = {str(row[1]): row[0] for row in cur.fetchall()}
                
                # 3. Games map (active imported games)
                cur.execute("SELECT id, external_id FROM games")
                games = {str(row[1]): row[0] for row in cur.fetchall()}
                
                upsert_rows = []
                codes = ["points", "assists", "rebounds", "blocks", "steals", "turnovers", "minutes_played", "plus_minus"]
                
                for s in raw_stats:
                    p_fk = parts.get(s["player_external_id"])
                    g_fk = games.get(s["game_external_id"])
                    if not p_fk or not g_fk:
                        continue
                        
                    for c in codes:
                        if c not in defs: continue
                        if s.get(c) is None: continue
                        
                        upsert_rows.append((
                            p_fk,
                            g_fk,
                            defs[c],
                            s.get(c)
                        ))
                
                if not upsert_rows:
                    _LOG.warning("No valid stats to push. Ensure definitions and players/games are synced.")
                    return GameStatImportSummary(0, 0, len(raw_stats))
                    
                query = """
                    INSERT INTO participant_game_stats (id, participant_id, game_id, stat_definition_id, value, created_at, updated_at)
                    VALUES %s
                    ON CONFLICT (participant_id, game_id, stat_definition_id) DO UPDATE SET
                        value = EXCLUDED.value,
                        updated_at = NOW()
                    RETURNING (xmax = 0) AS inserted;
                """
                
                template = "(gen_random_uuid(), %s, %s, %s, %s, NOW(), NOW())"
                
                results = execute_values(
                    cur, 
                    query, 
                    upsert_rows, 
                    template=template,
                    fetch=True
                )
                
                if results is None: return GameStatImportSummary(0, 0, len(raw_stats))
                
                inserted = sum(1 for r in results if r[0] is True)
                updated = len(upsert_rows) - inserted
                
                _LOG.info("Imported %d participant box score statistics (%d new, %d updated)", len(upsert_rows), inserted, updated)
                return GameStatImportSummary(inserted=inserted, updated=updated, total=len(raw_stats))

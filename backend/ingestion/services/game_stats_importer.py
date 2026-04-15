"""Importer connecting absolute box score stats to participants per active matchups."""
import logging
from dataclasses import dataclass
from typing import Protocol, Any
import json
from psycopg2.extras import execute_values
from ingestion.db.connection import get_connection
from ingestion.scoring.scoring_config_loader import ScoringConfigLoader
from ingestion.scoring.scoring_engine import compute_score

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
                
                # 4. Draft mappings (participant -> list of league_ids)
                cur.execute("SELECT dp.participant_id, ds.league_id FROM draft_picks dp JOIN draft_sessions ds ON dp.draft_session_id = ds.id")
                player_leagues = {}
                for pid, lid in cur.fetchall():
                    pid_str = str(pid)
                    if pid_str not in player_leagues:
                        player_leagues[pid_str] = []
                    player_leagues[pid_str].append(str(lid))
                    
                config_loader = ScoringConfigLoader()
                
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
                
                # --- SCORING PART ---
                score_upsert_rows = []
                for s in raw_stats:
                    p_fk = parts.get(s["player_external_id"])
                    g_fk = games.get(s["game_external_id"])
                    if not p_fk or not g_fk:
                        continue
                        
                    leagues = player_leagues.get(str(p_fk), [])
                    if not leagues:
                        continue
                        
                    for lid in leagues:
                        cfg = config_loader.get_config(lid)
                        score_res = compute_score(s, cfg)
                        
                        score_upsert_rows.append((
                            p_fk,
                            g_fk,
                            lid,
                            score_res["total"],
                            json.dumps(score_res["breakdown"])
                        ))
                
                if score_upsert_rows:
                    score_query = """
                        INSERT INTO participant_game_score (id, participant_id, game_id, fantasy_league_id, score, breakdown, created_at, updated_at)
                        VALUES %s
                        ON CONFLICT (participant_id, game_id, fantasy_league_id) DO UPDATE SET
                            score = EXCLUDED.score,
                            breakdown = EXCLUDED.breakdown,
                            updated_at = NOW()
                    """
                    score_template = "(gen_random_uuid(), %s, %s, %s, %s, %s::json, NOW(), NOW())"
                    execute_values(cur, score_query, score_upsert_rows, template=score_template)
                    _LOG.info("Upserted %d participant game scores for drafted leagues.", len(score_upsert_rows))
                else:
                    _LOG.info("No player instances found in any drafted league for scoring.")

                # Append Clifford Benchman baseline logic for game stats
                cur.execute("SELECT id FROM participants WHERE external_id = 'clifford_benchman'")
                row = cur.fetchone()
                if not row:
                    cur.execute(
                        """
                        INSERT INTO participants (id, name, sport, external_id, type, injury_status, metadata, created_at, updated_at)
                        VALUES (gen_random_uuid(), 'Clifford Benchman', %s, 'clifford_benchman', 'player', 'ACTIVE', '{}'::jsonb, NOW(), NOW())
                        RETURNING id
                        """,
                        (self._sport,)
                    )
                    clifford_id = cur.fetchone()[0]
                else:
                    clifford_id = row[0]

                cur.execute(
                    """
                    INSERT INTO participant_game_stats (id, participant_id, game_id, stat_definition_id, value, created_at, updated_at)
                    SELECT 
                        gen_random_uuid(),
                        %s,
                        game_id,
                        stat_definition_id,
                        AVG(value),
                        NOW(),
                        NOW()
                    FROM participant_game_stats
                    WHERE participant_id != %s
                    GROUP BY game_id, stat_definition_id
                    ON CONFLICT (participant_id, game_id, stat_definition_id) DO UPDATE SET
                        value = EXCLUDED.value,
                        updated_at = NOW()
                    """,
                    (str(clifford_id), str(clifford_id))
                )

                _LOG.info("Clifford Benchman baseline averaged effectively across participant game stats.")
                
                return GameStatImportSummary(inserted=inserted, updated=updated, total=len(raw_stats))

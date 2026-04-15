"""Scoring logic for aggregating participant stats over a season based on a scoring config hash."""
import logging
import json
from psycopg2.extras import execute_values
from ingestion.db.connection import get_connection
from ingestion.scoring.scoring_engine import compute_score

_LOG = logging.getLogger(__name__)


def compute_and_store_aggregated_scores(config_hash: str, scoring_config: dict, season: str, stat_scope: str = "season") -> int:
    """
    Computes and stores the the aggregated score for all participants for the given season.
    
    1. Fetches all participants and their summed stats for the given season.
    2. Applies the scoring_config using compute_score.
    3. Bulk inserts the results, skipping if it already exists.
    """
    with get_connection() as conn:
        with conn.cursor() as cur:
            # First, check if this run has already been done for this hash + season + scope
            cur.execute(
                """
                SELECT 1 FROM participant_aggregated_scores 
                WHERE score_config_hash = %s AND season = %s AND stat_scope = %s
                LIMIT 1
                """,
                (config_hash, season, stat_scope)
            )
            if cur.fetchone() is not None:
                _LOG.info(f"Aggregated scores already exist for hash {config_hash}, season {season}. Skipping computation.")
                return 0

            _LOG.info(f"Computing aggregated scores for hash {config_hash}, season {season}...")

            # The true mapping lives in participant_stats which tracks `total_value` and `season` natively.
            cur.execute(
                """
                SELECT ps.participant_id, sd.code, sd.unit, ps.total_value as total_val
                FROM participant_stats ps
                JOIN stat_definitions sd ON ps.stat_definition_id = sd.id
                WHERE ps.season = %s
                """,
                (season,)
            )
            
            participant_stats_agg: dict[str, dict[str, tuple[float, str]]] = {}
            for row in cur.fetchall():
                p_id, code, unit, total_val = str(row[0]), str(row[1]), str(row[2]), float(row[3])
                if p_id not in participant_stats_agg:
                    participant_stats_agg[p_id] = {}
                participant_stats_agg[p_id][code] = (total_val, unit)

            if not participant_stats_agg:
                _LOG.info(f"No participant stats found for season {season}.")
                return 0

            upsert_rows = []
            for p_id, stats_info in participant_stats_agg.items():
                
                games_played = 1.0
                if "games_played" in stats_info:
                    gp_val = stats_info["games_played"][0]
                    if gp_val > 0:
                        games_played = gp_val
                
                compute_stats = {}
                for code, (val, unit) in stats_info.items():
                    if unit == "count" and code != "games_played":
                        compute_stats[code] = val / games_played
                    else:
                        compute_stats[code] = val

                score_res = compute_score(compute_stats, scoring_config)
                upsert_rows.append((
                    p_id,
                    config_hash,
                    season,
                    stat_scope,
                    score_res["total"],
                    json.dumps(score_res["breakdown"])
                ))

            if not upsert_rows:
                return 0

            query = """
                INSERT INTO participant_aggregated_scores 
                (id, participant_id, score_config_hash, season, stat_scope, total_score, breakdown, created_at, updated_at)
                VALUES %s
                ON CONFLICT (participant_id, score_config_hash, season, stat_scope) DO UPDATE SET
                    total_score = EXCLUDED.total_score,
                    breakdown = EXCLUDED.breakdown,
                    updated_at = NOW()
            """
            template = "(gen_random_uuid(), %s, %s, %s, %s, %s, %s::json, NOW(), NOW())"
            
            execute_values(cur, query, upsert_rows, template=template)
            conn.commit()
            
            _LOG.info(f"Stored {len(upsert_rows)} aggregated scores for hash {config_hash}.")
            return len(upsert_rows)

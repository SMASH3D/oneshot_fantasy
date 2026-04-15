"""Script to compute aggregated scores for distinct scoring configuration hashes."""
import logging
import argparse
from ingestion.db.connection import get_connection
from ingestion.scoring.aggregated_scoring import compute_and_store_aggregated_scores

logging.basicConfig(level=logging.INFO)
_LOG = logging.getLogger(__name__)

def main() -> None:
    parser = argparse.ArgumentParser(description="Import/Compute aggregated participant scores.")
    parser.add_argument("--season", required=True, help="The artificial string defining the season dimension for aggregation.")
    args = parser.parse_args()
    
    unique_configs = {}
    with get_connection() as conn:
        with conn.cursor() as cur:
            # 1. Fetch distinct scoring_config_hashes and their configs
            # We take the first jsonb scoring_config we see for each distinct hash.
            cur.execute(
                """
                SELECT DISTINCT ON (scoring_config_hash) scoring_config_hash, scoring_config
                FROM fantasy_leagues
                WHERE scoring_config_hash != ''
                """
            )
            for row in cur.fetchall():
                chash, config = row[0], row[1]
                unique_configs[chash] = config

    if not unique_configs:
        _LOG.info("No fantasy leagues with scoring_config_hash found.")
        return

    _LOG.info(f"Discovered {len(unique_configs)} unique scoring configurations. Beginning aggregation logic.")

    for chash, config in unique_configs.items():
        try:
            compute_and_store_aggregated_scores(
                config_hash=chash,
                scoring_config=config,
                season=args.season,
            )
        except Exception as e:
            _LOG.error(f"Failed to compute aggregated scores for hash {chash}: {e}")

if __name__ == "__main__":
    main()

"""Script providing direct terminal execution for fetching NBA Player Stats."""

import logging
import sys
from datetime import datetime, timezone
import os
from pathlib import Path

# Provide package path awareness
sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from ingestion.clients.nba_stats_client import NBAStatsClient
from ingestion.services.player_stats_importer import PlayerStatsImporter

def _current_nba_season() -> str:
    now = datetime.now(timezone.utc)
    y = now.year
    if now.month >= 10:
        return f"{y}-{str(y + 1)[-2:]}"
    return f"{y - 1}-{str(y)[-2:]}"

def main() -> None:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
    client = NBAStatsClient()
    importer = PlayerStatsImporter(client=client, sport="basketball")
    season = _current_nba_season()
    
    logging.info("Starting NBA Stat Aggregation sequence for season %s", season)
    try:
        summary = importer.run(season=season)
        logging.info("Sequence Summary -> Total Participant Responses Scanned: %d | Granular DB Stat Assertions -> Inserted: %d, Updated: %d", summary.total, summary.inserted, summary.updated)
    except Exception as err:
        logging.error("Aggregation Failed: %s", err)
        sys.exit(1)

if __name__ == "__main__":
    main()

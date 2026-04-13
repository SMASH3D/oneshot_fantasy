"""Script executing atomic game boxed stats."""
import logging
import sys
from pathlib import Path
from datetime import datetime, timezone

sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from ingestion.clients.nba_game_stats_client import NBAGameStatsClient
from ingestion.services.game_stats_importer import GameStatsImporter

def _current_nba_season() -> str:
    now = datetime.now(timezone.utc)
    y = now.year
    if now.month >= 10:
        return f"{y}-{str(y + 1)[-2:]}"
    return f"{y - 1}-{str(y)[-2:]}"

def main() -> None:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
    client = NBAGameStatsClient()
    importer = GameStatsImporter(client=client, sport="basketball")
    season = _current_nba_season()
    
    logging.info("Starting NBA Box Score Stat Aggregation for season %s...", season)
    try:
        summary = importer.run(season=season)
        logging.info("Sequence Summary -> Inserted: %d, Updated: %d, Game Logs parsed: %d", summary.inserted, summary.updated, summary.total)
    except Exception as err:
        logging.error("Game Stats Aggregation Failed: %s", err)
        sys.exit(1)

if __name__ == "__main__":
    main()

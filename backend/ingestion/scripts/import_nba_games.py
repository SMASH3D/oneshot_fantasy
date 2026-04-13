"""Execution script driving standard NBA Matchups directly via explicit constraints."""
import logging
import sys
from pathlib import Path
from datetime import datetime, timezone

sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from ingestion.clients.nba_game_client import NBAGameClient
from ingestion.services.game_importer import GameImporter

def _current_nba_season() -> str:
    now = datetime.now(timezone.utc)
    y = now.year
    if now.month >= 10:
        return f"{y}-{str(y + 1)[-2:]}"
    return f"{y - 1}-{str(y)[-2:]}"

def main() -> None:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
    client = NBAGameClient()
    importer = GameImporter(client=client, sport="basketball")
    season = _current_nba_season()
    
    logging.info("Starting NBA Games Aggregation sequence...")
    try:
        summary = importer.run(season=season)
        logging.info("Sequence Summary -> Inserted: %d, Updated: %d, Total: %d", summary.inserted, summary.updated, summary.total)
    except Exception as err:
        logging.error("Game Aggregation Failed: %s", err)
        sys.exit(1)

if __name__ == "__main__":
    main()

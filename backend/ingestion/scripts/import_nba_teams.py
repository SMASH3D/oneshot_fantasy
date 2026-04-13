"""Execution script driving standard NBA Teams directly via API limits."""
import logging
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from ingestion.clients.nba_team_client import NBATeamClient
from ingestion.services.team_importer import TeamImporter

def main() -> None:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
    client = NBATeamClient()
    importer = TeamImporter(client=client, sport="basketball")
    
    logging.info("Starting NBA Teams Aggregation sequence...")
    try:
        summary = importer.run()
        logging.info("Sequence Summary -> Inserted: %d, Updated: %d, Total: %d", summary.inserted, summary.updated, summary.total)
    except Exception as err:
        logging.error("Team Aggregation Failed: %s", err)
        sys.exit(1)

if __name__ == "__main__":
    main()

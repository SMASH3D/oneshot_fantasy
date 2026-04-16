"""
Entry point: import NBA playoff games into the games table.

Usage (preferred):
    make ingest-nba-games SLUG=nba-playoffs-2025 SEASON=2024-25

Direct invocation (must use the venv Python explicitly):
    cd backend/
    .venv/bin/python ingestion/scripts/import_nba_games.py --slug nba-playoffs-2025
    .venv/bin/python ingestion/scripts/import_nba_games.py --slug nba-playoffs-2025 --season 2024-25

The script is idempotent — safe to re-run; it upserts games by external_id.
"""
from __future__ import annotations

import argparse
import logging
import sys
from datetime import datetime, timezone
from pathlib import Path

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
    parser = argparse.ArgumentParser(description="Import NBA playoff games into the games table.")
    parser.add_argument(
        "--season", default=None,
        help="NBA season string (e.g. '2024-25'). Defaults to current season.",
    )
    parser.add_argument(
        "--slug", required=True,
        help="Tournament slug (e.g. 'nba-playoffs-2025') used to associate games with a tournament.",
    )
    args = parser.parse_args()

    logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")

    season = args.season or _current_nba_season()
    logging.info("Starting NBA Games ingestion — season=%s, tournament=%s", season, args.slug)

    client = NBAGameClient()
    importer = GameImporter(client=client, sport="basketball")

    try:
        summary = importer.run(season=season, tournament_slug=args.slug)
        logging.info(
            "Done — %d inserted, %d updated (of %d total games)",
            summary.inserted, summary.updated, summary.total,
        )
    except Exception as err:
        logging.error("Game ingestion failed: %s", err, exc_info=True)
        sys.exit(1)


if __name__ == "__main__":
    main()

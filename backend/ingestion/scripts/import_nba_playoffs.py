"""
Entry point: import NBA playoff series into tournament_rounds.

Usage (preferred — uses the venv Python and loads backend/.env automatically):
    make ingest-nba-playoffs SLUG=nba-playoffs-2026

Direct invocation (must use the venv Python explicitly):
    cd backend/
    .venv/bin/python ingestion/scripts/import_nba_playoffs.py --slug nba-playoffs-2026
    .venv/bin/python ingestion/scripts/import_nba_playoffs.py --slug nba-playoffs-2026 --season 2025-26

The script is idempotent — safe to re-run; it upserts rounds and re-links games.
"""
from __future__ import annotations

import argparse
import logging
import sys
from datetime import datetime, timezone
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from ingestion.clients.nba_playoffs_client import NBAPlayoffsClient
from ingestion.services.playoffs_importer import PlayoffsImporter


def _current_nba_season() -> str:
    now = datetime.now(timezone.utc)
    y = now.year
    if now.month >= 10:
        return f"{y}-{str(y + 1)[-2:]}"
    return f"{y - 1}-{str(y)[-2:]}"


def main() -> None:
    parser = argparse.ArgumentParser(description="Import NBA playoff series into tournament_rounds.")
    parser.add_argument(
        "--season", default=None,
        help="NBA season string (e.g. '2024-25'). Defaults to current season."
    )
    parser.add_argument(
        "--slug", required=True,
        help="Tournament slug (e.g. 'nba-playoffs-2025') used to resolve the tournament row."
    )
    args = parser.parse_args()

    logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")

    season = args.season or _current_nba_season()
    logging.info("Starting NBA Playoffs ingestion — season=%s, tournament=%s", season, args.slug)

    client = NBAPlayoffsClient()
    importer = PlayoffsImporter(client=client, sport="basketball")

    try:
        summary = importer.run(season=season, tournament_slug=args.slug)
        logging.info(
            "Done — %d rounds upserted, %d games linked (of %d total series)",
            summary.rounds_upserted, summary.games_linked, summary.total_series,
        )
    except Exception as err:
        logging.error("Playoffs import failed: %s", err, exc_info=True)
        sys.exit(1)


if __name__ == "__main__":
    main()

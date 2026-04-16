"""
Entry point: sync team participation status for a tournament.

Usage (preferred):
    make ingest-nba-participations SLUG=nba-playoffs-2025

Direct invocation (must use the venv Python explicitly):
    cd backend/
    .venv/bin/python ingestion/scripts/import_nba_participations.py --slug nba-playoffs-2025

Reads directly from tournament_rounds (already populated by import_nba_playoffs).
Safe to re-run after every round update — all upserts are idempotent.

Status values written to tournament_participations.status:
    active      — team is still competing in the bracket
    eliminated  — team was knocked out in a completed series
    champion    — Finals winner
"""
from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from ingestion.services.tournament_participation_importer import TournamentParticipationImporter


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Sync team participation status for a tournament.",
    )
    parser.add_argument(
        "--slug", required=True,
        help="Tournament slug (e.g. 'nba-playoffs-2025').",
    )
    args = parser.parse_args()

    logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
    logging.info("Starting participation ingestion for tournament=%s", args.slug)

    importer = TournamentParticipationImporter()
    try:
        summary = importer.run(tournament_slug=args.slug)
        logging.info(
            "Done — %d participations upserted for '%s'",
            summary.upserted, summary.tournament,
        )
    except Exception as err:
        logging.error("Participation ingestion failed: %s", err, exc_info=True)
        sys.exit(1)


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""
Ingest stat events for a tournament via the configured stats_adapter_key.

Examples:
  cd backend && .venv/bin/python -m scripts.sync_data --tournament-id <uuid>
  cd backend && .venv/bin/python -m scripts.sync_data --tournament-id <uuid> --cursor null
"""
from __future__ import annotations

import argparse
import asyncio
import logging
import sys
from uuid import UUID

from app.application.ingestion import StatIngestionRunner
from app.infrastructure.database import dispose_engine, get_session_factory
from app.infrastructure.persistence.repositories.tournament_repository import TournamentRepository
from app.worker.logging_setup import configure_worker_logging

log = logging.getLogger(__name__)


def _parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Sync external stats into stat_events (PostgreSQL).")
    p.add_argument("--tournament-id", type=UUID, required=True)
    p.add_argument(
        "--cursor",
        default=None,
        help="Override ingestion cursor (omit to use tournaments.metadata.ingestion_cursor).",
    )
    p.add_argument("-v", "--verbose", action="store_true")
    return p.parse_args()


async def _main() -> int:
    args = _parse_args()
    configure_worker_logging(verbose=args.verbose)
    log.info("sync_data start tournament_id=%s", args.tournament_id)
    factory = get_session_factory()
    async with factory() as session:
        async with session.begin():
            runner = StatIngestionRunner(session, TournamentRepository(session))
            n, next_cursor = await runner.sync_tournament(
                args.tournament_id,
                cursor_override=args.cursor,
            )
    log.info("sync_data done inserted=%d next_cursor=%r", n, next_cursor)
    await dispose_engine()
    return 0


def main() -> None:
    try:
        raise SystemExit(asyncio.run(_main()))
    except ValueError as e:
        print(e, file=sys.stderr)
        raise SystemExit(1) from e


if __name__ == "__main__":
    main()

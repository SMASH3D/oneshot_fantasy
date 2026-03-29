#!/usr/bin/env python3
"""
Recompute scores for many (league_id, fantasy_round_id) pairs — cron-friendly.

Jobs file format (one job per line):
  <league-uuid> <fantasy-round-uuid>
  Lines starting with # and blank lines are ignored.

Example:
  cd backend && .venv/bin/python -m scripts.score_batch --jobs-file /etc/oneshot/score-jobs.txt
"""
from __future__ import annotations

import argparse
import asyncio
import logging
import sys
from pathlib import Path
from uuid import UUID

from app.infrastructure.database import dispose_engine, get_session_factory
from app.infrastructure.persistence.repositories.fantasy_repository import FantasyRepository
from app.worker.bootstrap import build_scoring_service
from app.worker.logging_setup import configure_worker_logging

log = logging.getLogger(__name__)


def _parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(
        description="Batch round_scores recomputation from a jobs file (idempotent).",
    )
    p.add_argument(
        "--jobs-file",
        type=Path,
        required=True,
        help="Whitespace-separated league_id fantasy_round_id per line.",
    )
    p.add_argument(
        "--continue-on-error",
        action="store_true",
        help="Process remaining jobs after a failure (exit 1 if any failed).",
    )
    p.add_argument("-v", "--verbose", action="store_true")
    return p.parse_args()


def _parse_jobs(path: Path) -> list[tuple[UUID, UUID]]:
    out: list[tuple[UUID, UUID]] = []
    for i, raw in enumerate(path.read_text(encoding="utf-8").splitlines(), start=1):
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        parts = line.split()
        if len(parts) != 2:
            msg = f"{path}:{i}: expected '<league-uuid> <fantasy-round-uuid>'"
            raise ValueError(msg)
        out.append((UUID(parts[0]), UUID(parts[1])))
    return out


async def _main() -> int:
    args = _parse_args()
    configure_worker_logging(verbose=args.verbose)
    jobs = _parse_jobs(args.jobs_file)
    log.info("Loaded %d scoring job(s) from %s", len(jobs), args.jobs_file)
    if not jobs:
        return 0

    factory = get_session_factory()
    failed = 0
    for league_id, fantasy_round_id in jobs:
        try:
            async with factory() as session:
                async with session.begin():
                    fantasy = FantasyRepository(session)
                    service = build_scoring_service(session)
                    league = await fantasy.get_league(league_id)
                    if league is None:
                        raise ValueError(f"League not found: {league_id}")
                    memberships = await fantasy.list_memberships(league_id)
                    for m in memberships:
                        await service.recompute_membership_round(
                            league_id=league_id,
                            fantasy_round_id=fantasy_round_id,
                            league_membership_id=m.id,
                        )
                    log.info(
                        "Scored league=%s round=%s memberships=%d",
                        league_id,
                        fantasy_round_id,
                        len(memberships),
                    )
        except Exception:
            failed += 1
            log.exception(
                "Job failed league=%s fantasy_round=%s",
                league_id,
                fantasy_round_id,
            )
            if not args.continue_on_error:
                await dispose_engine()
                return 1

    await dispose_engine()
    return 1 if failed else 0


def main() -> None:
    try:
        raise SystemExit(asyncio.run(_main()))
    except ValueError as e:
        print(e, file=sys.stderr)
        raise SystemExit(1) from e


if __name__ == "__main__":
    main()

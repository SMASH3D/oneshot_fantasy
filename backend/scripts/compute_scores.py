#!/usr/bin/env python3
"""
Recompute round_scores for a fantasy round using the league's active scoring_rule_sets.

Examples:
  cd backend && .venv/bin/python -m scripts.compute_scores \\
    --league-id <uuid> --fantasy-round-id <uuid>

  # Single membership:
  cd backend && .venv/bin/python -m scripts.compute_scores \\
    --league-id <uuid> --fantasy-round-id <uuid> --membership-id <uuid>
"""
from __future__ import annotations

import argparse
import asyncio
import logging
import sys
from uuid import UUID

from app.application.scoring_service import CalculatorNotRegisteredError
from app.domains.fantasy.exceptions import FantasyLeagueNotFoundError
from app.domains.scoring.exceptions import ScoringRuleNotFoundError
from app.infrastructure.database import dispose_engine, get_session_factory
from app.infrastructure.persistence.repositories.fantasy_repository import FantasyRepository
from app.worker.bootstrap import build_scoring_service
from app.worker.logging_setup import configure_worker_logging

log = logging.getLogger(__name__)


def _parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(
        description="Write round_scores from stat_events and draft roster (PostgreSQL).",
    )
    p.add_argument("--league-id", type=UUID, required=True)
    p.add_argument("--fantasy-round-id", type=UUID, required=True)
    p.add_argument(
        "--membership-id",
        type=UUID,
        default=None,
        help="Limit to one league_membership.",
    )
    p.add_argument("-v", "--verbose", action="store_true")
    return p.parse_args()


async def _main() -> int:
    args = _parse_args()
    configure_worker_logging(verbose=args.verbose)
    log.info(
        "compute_scores start league_id=%s fantasy_round_id=%s",
        args.league_id,
        args.fantasy_round_id,
    )
    factory = get_session_factory()
    async with factory() as session:
        async with session.begin():
            fantasy = FantasyRepository(session)
            service = build_scoring_service(session)

            league = await fantasy.get_league(args.league_id)
            if league is None:
                log.error("League not found: %s", args.league_id)
                print("League not found", file=sys.stderr)
                return 1

            if args.membership_id is not None:
                all_m = await fantasy.list_memberships(args.league_id)
                memberships = [m for m in all_m if m.id == args.membership_id]
                if not memberships:
                    log.error("membership-id not in league: %s", args.membership_id)
                    print("membership-id not in league", file=sys.stderr)
                    return 1
            else:
                memberships = await fantasy.list_memberships(args.league_id)

            for m in memberships:
                try:
                    total, breakdown = await service.recompute_membership_round(
                        league_id=args.league_id,
                        fantasy_round_id=args.fantasy_round_id,
                        league_membership_id=m.id,
                    )
                except ScoringRuleNotFoundError:
                    msg = "No active scoring_rule_sets for this league; add one in PostgreSQL."
                    print(msg, file=sys.stderr)
                    return 1
                except CalculatorNotRegisteredError as e:
                    print(
                        f"No ScoringCalculator registered for engine_key={e.engine_key!r}",
                        file=sys.stderr,
                    )
                    return 1
                except FantasyLeagueNotFoundError:
                    print("League disappeared mid-run", file=sys.stderr)
                    return 1
                log.info("membership=%s points=%.4f breakdown=%s", m.id, total, breakdown)
                print(f"{m.id}: points={total:.4f} breakdown={breakdown!r}")

    log.info("compute_scores done")
    await dispose_engine()
    return 0


def main() -> None:
    raise SystemExit(asyncio.run(_main()))


if __name__ == "__main__":
    main()

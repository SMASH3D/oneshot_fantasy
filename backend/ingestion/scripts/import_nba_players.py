#!/usr/bin/env python3
"""CLI: load current NBA rosters into ``participants`` (sport=basketball, type=player)."""

from __future__ import annotations

import logging
import sys
from pathlib import Path

# Allow `python path/to/import_nba_players.py` without installing the package.
_ROOT = Path(__file__).resolve().parents[2]
if str(_ROOT) not in sys.path:
    sys.path.insert(0, str(_ROOT))

from ingestion.clients.nba_client import NBAClient  # noqa: E402
from ingestion.services.player_importer import PlayerImporter  # noqa: E402


def main() -> int:
    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")
    log = logging.getLogger("import_nba_players")

    log.info("Fetching players from NBA API…")
    client = NBAClient()
    importer = PlayerImporter(client)
    summary = importer.run()

    print(f"Done. Total rows processed: {summary.total}")
    print(f"  Inserted (new): {summary.inserted}")
    print(f"  Updated (existing): {summary.updated}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

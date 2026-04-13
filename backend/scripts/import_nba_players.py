#!/usr/bin/env python3
"""Run from backend/: ``.venv/bin/python scripts/import_nba_players.py``"""

from __future__ import annotations

import sys
from pathlib import Path

_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(_ROOT))

from ingestion.scripts.import_nba_players import main  # noqa: E402

if __name__ == "__main__":
    raise SystemExit(main())

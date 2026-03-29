"""Shared logging for CLI / cron workers (stderr, UTC timestamps)."""

from __future__ import annotations

import logging
import sys
import time


def configure_worker_logging(*, verbose: bool = False) -> None:
    """
    Idempotent-friendly: safe to call once per process.

    - INFO: milestones (counts, cursors)
    - DEBUG: verbose (--verbose): SQL-ish context avoided; use for IDs and branches
    - All output to stderr so stdout stays clean for piping (e.g. captured cursors)
    """
    level = logging.DEBUG if verbose else logging.INFO
    root = logging.getLogger()
    if root.handlers:
        root.setLevel(level)
        for h in root.handlers:
            h.setLevel(level)
        return

    handler = logging.StreamHandler(sys.stderr)
    handler.setLevel(level)
    fmt = logging.Formatter(
        fmt="%(asctime)s %(levelname)s [%(name)s] %(message)s",
        datefmt="%Y-%m-%dT%H:%M:%SZ",
    )
    fmt.converter = time.gmtime
    handler.setFormatter(fmt)
    root.addHandler(handler)
    root.setLevel(level)

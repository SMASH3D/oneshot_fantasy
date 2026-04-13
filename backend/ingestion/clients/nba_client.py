"""Thin wrapper around nba_api — returns normalized dicts only (no raw endpoint shape in top-level fields)."""

from __future__ import annotations

import math
from datetime import datetime, timezone
from typing import Any


def _current_nba_season() -> str:
    """Return season string e.g. '2025-26' for the active NBA league year."""
    now = datetime.now(timezone.utc)
    y = now.year
    if now.month >= 10:
        return f"{y}-{str(y + 1)[-2:]}"
    return f"{y - 1}-{str(y)[-2:]}"


def _cell(value: Any) -> Any:
    """Coerce pandas / numpy scalars to JSON-friendly Python or None."""
    if value is None:
        return None
    if isinstance(value, float) and (math.isnan(value) or math.isinf(value)):
        return None
    try:
        import pandas as pd

        if pd.isna(value):
            return None
    except ImportError:
        pass
    if isinstance(value, (str, int, bool)):
        return value
    if isinstance(value, float):
        return value
    try:
        return int(value)
    except (TypeError, ValueError):
        pass
    try:
        return float(value)
    except (TypeError, ValueError):
        pass
    return str(value)


def _team_label(raw: dict[str, Any]) -> str | None:
    city = str(raw.get("TEAM_CITY") or "").strip()
    name = str(raw.get("TEAM_NAME") or "").strip()
    abbr = str(raw.get("TEAM_ABBREVIATION") or "").strip()
    label = f"{city} {name}".strip()
    return label or (abbr or None)


def _player_metadata(season: str, raw: dict[str, Any]) -> dict[str, Any]:
    """Compact, JSON-serializable context for Symfony `metadata` column."""
    keys = (
        "PERSON_ID",
        "PLAYERCODE",
        "PLAYER_SLUG",
        "TEAM_ID",
        "TEAM_ABBREVIATION",
        "TEAM_SLUG",
        "ROSTERSTATUS",
        "FROM_YEAR",
        "TO_YEAR",
    )
    meta: dict[str, Any] = {"source": "nba_api.commonallplayers", "season": season}
    for k in keys:
        if k in raw:
            meta[k.lower()] = _cell(raw.get(k))
    return meta


class NBAClient:
    """Fetches league roster data via nba_api (stats.nba.com)."""

    def get_all_players(self, *, include_metadata: bool = False) -> list[dict[str, Any]]:
        """
        Return normalized player dicts: id, name, team, position.
        When include_metadata=True (used by ingestion), each dict also has a ``metadata`` object
        suitable for the DB JSON column.
        """
        from nba_api.stats.endpoints.commonallplayers import CommonAllPlayers

        season = _current_nba_season()
        endpoint = CommonAllPlayers(
            league_id="00",
            season=season,
            is_only_current_season=1,
        )
        frame = endpoint.get_data_frames()[0]
        records = frame.to_dict("records")
        out: list[dict[str, Any]] = []
        for raw in records:
            status = _cell(raw.get("ROSTERSTATUS"))
            if status is not None and int(status) != 1:
                continue
            pid = _cell(raw.get("PERSON_ID"))
            if pid is None:
                continue
            display = raw.get("DISPLAY_FIRST_LAST")
            name_val = _cell(display)
            name = str(name_val).strip() if name_val is not None else ""
            if not name:
                continue
            tid = _cell(raw.get("TEAM_ID"))
            row: dict[str, Any] = {
                "id": str(int(pid)),
                "name": name,
                "team": _team_label(raw),
                "position": None,
                "team_external_id": str(int(tid)) if tid is not None else None
            }
            if include_metadata:
                row["metadata"] = _player_metadata(season, raw)
            out.append(row)
        return out

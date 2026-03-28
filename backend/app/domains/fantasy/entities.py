"""Fantasy-layer domain objects."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any
from uuid import UUID


@dataclass(frozen=True, slots=True)
class FantasyLeague:
    id: UUID
    tournament_id: UUID
    name: str
    commissioner_user_id: UUID
    status: str
    settings: dict[str, Any]
    lineup_template: list[Any] | dict[str, Any]


@dataclass(frozen=True, slots=True)
class LeagueMembership:
    id: UUID
    league_id: UUID
    user_id: UUID
    nickname: str | None
    role: str

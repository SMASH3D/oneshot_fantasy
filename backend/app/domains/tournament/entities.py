"""Tournament-side domain objects (sport-agnostic)."""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from typing import Any
from uuid import UUID


@dataclass(frozen=True, slots=True)
class Tournament:
    id: UUID
    name: str
    slug: str | None
    sport_key: str
    stats_adapter_key: str | None
    default_scoring_engine_key: str | None
    timezone: str
    starts_at: datetime | None
    ends_at: datetime | None
    status: str
    meta: dict[str, Any]


@dataclass(frozen=True, slots=True)
class TournamentRound:
    id: UUID
    tournament_id: UUID
    order_index: int
    name: str
    canonical_key: str | None
    meta: dict[str, Any]


@dataclass(frozen=True, slots=True)
class Participant:
    id: UUID
    tournament_id: UUID
    external_ref: str | None
    display_name: str
    kind: str | None
    meta: dict[str, Any]


@dataclass(frozen=True, slots=True)
class MatchSlotRef:
    slot_index: int
    participant_id: UUID | None


@dataclass(frozen=True, slots=True)
class Match:
    id: UUID
    tournament_round_id: UUID
    scheduled_at: datetime | None
    status: str
    slots: tuple[MatchSlotRef, ...]

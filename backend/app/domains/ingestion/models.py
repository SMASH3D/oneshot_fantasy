"""DTOs crossing the ingestion boundary (after adapter normalization)."""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from typing import Any
from uuid import UUID


@dataclass(frozen=True, slots=True)
class NormalizedStatEvent:
    tournament_id: UUID
    participant_id: UUID
    match_id: UUID | None
    tournament_round_id: UUID | None
    metric_key: str
    value_numeric: float
    occurred_at: datetime
    details: dict[str, Any]
    source: str | None

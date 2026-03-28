"""In-memory representation of scoring configuration."""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from typing import Any
from uuid import UUID


@dataclass(frozen=True, slots=True)
class ScoringRuleSet:
    id: UUID
    league_id: UUID
    version: int
    engine_key: str
    parameters: dict[str, Any]
    effective_from: datetime | None
    is_active: bool

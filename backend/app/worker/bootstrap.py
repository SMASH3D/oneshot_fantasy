"""Wire repositories and ScoringService for worker scripts."""

from __future__ import annotations

from sqlalchemy.ext.asyncio import AsyncSession

from app.application.scoring_service import ScoringService
from app.infrastructure.persistence.repositories.fantasy_repository import FantasyRepository
from app.infrastructure.persistence.repositories.scoring_repository import ScoringRepository
from app.infrastructure.scoring.simple_sum import SimpleSumCalculator


def build_scoring_service(session: AsyncSession) -> ScoringService:
    """Wire ScoringService for one AsyncSession (repo is scoring + stat query)."""
    scoring = ScoringRepository(session)
    fantasy = FantasyRepository(session)
    calc = SimpleSumCalculator()
    return ScoringService(
        scoring=scoring,
        stats=scoring,
        fantasy=fantasy,
        calculators={
            "simple_sum_v1": calc,
            "simple_sum": calc,
        },
    )

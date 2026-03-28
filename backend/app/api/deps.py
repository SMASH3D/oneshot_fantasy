"""FastAPI dependencies (DB session, application services)."""

from typing import Annotated

from fastapi import Depends
from sqlalchemy.ext.asyncio import AsyncSession

from app.application.fantasy_service import FantasyService
from app.application.scoring_service import ScoringService
from app.application.tournament_service import TournamentService
from app.infrastructure.database import get_db
from app.infrastructure.persistence.repositories.fantasy_repository import FantasyRepository
from app.infrastructure.persistence.repositories.scoring_repository import ScoringRepository
from app.infrastructure.persistence.repositories.tournament_repository import TournamentRepository


def get_tournament_service(
    session: Annotated[AsyncSession, Depends(get_db)],
) -> TournamentService:
    return TournamentService(TournamentRepository(session))


def get_fantasy_service(
    session: Annotated[AsyncSession, Depends(get_db)],
) -> FantasyService:
    return FantasyService(FantasyRepository(session))


def get_scoring_service(
    session: Annotated[AsyncSession, Depends(get_db)],
) -> ScoringService:
    scoring_repo = ScoringRepository(session)
    return ScoringService(
        scoring=scoring_repo,
        stats=scoring_repo,
        fantasy=FantasyRepository(session),
    )

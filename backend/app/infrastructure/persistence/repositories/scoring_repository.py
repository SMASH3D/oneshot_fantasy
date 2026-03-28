"""SQLAlchemy implementation of IScoringRepository (+ IStatQuery stub)."""

from decimal import Decimal
from typing import Any
from uuid import UUID

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.domains.scoring import rules as sr
from app.domains.scoring.ports import IScoringRepository, IStatQuery
from app.infrastructure.persistence.models.fantasy import RoundScore as RoundScoreRow
from app.infrastructure.persistence.models.fantasy import ScoringRuleSet as ScoringRuleSetRow


class ScoringRepository(IScoringRepository, IStatQuery):
    def __init__(self, session: AsyncSession) -> None:
        self._session = session

    async def get_active_rule_set(self, league_id: UUID) -> sr.ScoringRuleSet | None:
        stmt = (
            select(ScoringRuleSetRow)
            .where(
                ScoringRuleSetRow.league_id == league_id,
                ScoringRuleSetRow.is_active.is_(True),
            )
            .limit(1)
        )
        row = (await self._session.scalars(stmt)).one_or_none()
        return _rule_set_from_row(row) if row else None

    async def save_round_score(
        self,
        *,
        fantasy_round_id: UUID,
        league_membership_id: UUID,
        points: float,
        breakdown: dict[str, Any],
    ) -> None:
        stmt = select(RoundScoreRow).where(
            RoundScoreRow.fantasy_round_id == fantasy_round_id,
            RoundScoreRow.league_membership_id == league_membership_id,
        )
        existing = (await self._session.scalars(stmt)).one_or_none()
        dec = Decimal(str(points))
        if existing:
            existing.points = dec
            existing.breakdown = breakdown
        else:
            self._session.add(
                RoundScoreRow(
                    fantasy_round_id=fantasy_round_id,
                    league_membership_id=league_membership_id,
                    points=dec,
                    breakdown=breakdown,
                )
            )
        await self._session.flush()

    async def stat_events_for_round(
        self,
        *,
        tournament_id: UUID,
        fantasy_round_id: UUID,
    ) -> list[dict[str, Any]]:
        """Placeholder until lineup joins and metric selection are implemented."""
        del tournament_id, fantasy_round_id
        return []


def _rule_set_from_row(row: ScoringRuleSetRow) -> sr.ScoringRuleSet:
    return sr.ScoringRuleSet(
        id=row.id,
        league_id=row.league_id,
        version=row.version,
        engine_key=row.engine_key,
        parameters=dict(row.parameters or {}),
        effective_from=row.effective_from,
        is_active=row.is_active,
    )

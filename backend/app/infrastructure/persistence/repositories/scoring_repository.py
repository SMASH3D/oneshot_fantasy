"""SQLAlchemy implementation of IScoringRepository and IStatQuery."""

from datetime import UTC, datetime
from decimal import Decimal
from typing import Any
from uuid import UUID

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.domains.scoring import rules as sr
from app.domains.scoring.ports import IScoringRepository, IStatQuery
from app.infrastructure.persistence.models.fantasy import FantasyRound as FantasyRoundRow
from app.infrastructure.persistence.models.fantasy import RoundScore as RoundScoreRow
from app.infrastructure.persistence.models.fantasy import ScoringRuleSet as ScoringRuleSetRow
from app.infrastructure.persistence.models.stat_event import StatEvent as StatEventRow


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
        now = datetime.now(UTC)
        if existing:
            existing.points = dec
            existing.breakdown = breakdown
            existing.computed_at = now
        else:
            self._session.add(
                RoundScoreRow(
                    fantasy_round_id=fantasy_round_id,
                    league_membership_id=league_membership_id,
                    points=dec,
                    breakdown=breakdown,
                    computed_at=now,
                )
            )
        await self._session.flush()

    async def stat_events_for_round(
        self,
        *,
        tournament_id: UUID,
        fantasy_round_id: UUID,
    ) -> list[dict[str, Any]]:
        fr = await self._session.get(FantasyRoundRow, fantasy_round_id)
        if fr is None:
            return []

        stmt = select(StatEventRow).where(StatEventRow.tournament_id == tournament_id)
        if fr.tournament_round_id is not None:
            stmt = stmt.where(StatEventRow.tournament_round_id == fr.tournament_round_id)
        stmt = stmt.order_by(StatEventRow.occurred_at, StatEventRow.id)
        rows = (await self._session.scalars(stmt)).all()
        return [_stat_event_row_to_dict(r) for r in rows]


def _stat_event_row_to_dict(row: StatEventRow) -> dict[str, Any]:
    return {
        "id": row.id,
        "tournament_id": row.tournament_id,
        "participant_id": row.participant_id,
        "match_id": row.match_id,
        "tournament_round_id": row.tournament_round_id,
        "metric_key": row.metric_key,
        "value_numeric": float(row.value_numeric),
        "occurred_at": row.occurred_at,
        "details": dict(row.details or {}),
        "source": row.source,
    }


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

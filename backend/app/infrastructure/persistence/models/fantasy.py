"""ORM: fantasy leagues, draft, lineups, scoring, usage."""

from __future__ import annotations

import uuid
from datetime import datetime
from typing import Any

from sqlalchemy import (
    Boolean,
    DateTime,
    ForeignKey,
    Integer,
    Numeric,
    Text,
    false,
    func,
    text,
    true,
)
from sqlalchemy.dialects.postgresql import JSONB, UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.infrastructure.persistence.base import Base
from app.infrastructure.persistence.models.tournament import (
    Participant,
    Tournament,
    TournamentRound,
)
from app.infrastructure.persistence.models.user import User


class FantasyLeague(Base):
    __tablename__ = "fantasy_leagues"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    tournament_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("tournaments.id", ondelete="RESTRICT"), nullable=False
    )
    name: Mapped[str] = mapped_column(Text, nullable=False)
    commissioner_user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="RESTRICT"), nullable=False
    )
    status: Mapped[str] = mapped_column(Text, nullable=False, server_default="forming")
    settings: Mapped[dict[str, Any]] = mapped_column(JSONB, server_default="{}")
    lineup_template: Mapped[list[Any] | dict[str, Any]] = mapped_column(
        JSONB, server_default=text("'[]'::jsonb")
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    tournament: Mapped[Tournament] = relationship()
    commissioner: Mapped[User] = relationship(
        back_populates="leagues_commissioned",
        foreign_keys=[commissioner_user_id],
    )
    memberships: Mapped[list[LeagueMembership]] = relationship(
        back_populates="league",
        cascade="save-update, merge, refresh-expire, expunge",
    )
    draft_session: Mapped[DraftSession | None] = relationship(
        back_populates="league",
        uselist=False,
        cascade="save-update, merge, refresh-expire, expunge",
    )
    fantasy_rounds: Mapped[list[FantasyRound]] = relationship(
        back_populates="league",
        cascade="save-update, merge, refresh-expire, expunge",
    )
    scoring_rule_sets: Mapped[list[ScoringRuleSet]] = relationship(
        back_populates="league",
        cascade="save-update, merge, refresh-expire, expunge",
    )
    usage_policies: Mapped[list[UsageConstraintPolicy]] = relationship(
        back_populates="league",
        cascade="save-update, merge, refresh-expire, expunge",
    )
    usage_ledger_entries: Mapped[list[UsageLedgerEntry]] = relationship(
        back_populates="league",
        cascade="save-update, merge, refresh-expire, expunge",
    )


class LeagueMembership(Base):
    __tablename__ = "league_memberships"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    league_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("fantasy_leagues.id", ondelete="CASCADE"), nullable=False
    )
    user_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("users.id", ondelete="RESTRICT"), nullable=False
    )
    nickname: Mapped[str | None] = mapped_column(Text)
    role: Mapped[str] = mapped_column(Text, nullable=False, server_default="member")
    joined_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    league: Mapped[FantasyLeague] = relationship(back_populates="memberships")
    user: Mapped[User] = relationship(back_populates="league_memberships")


class DraftSession(Base):
    __tablename__ = "draft_sessions"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    league_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("fantasy_leagues.id", ondelete="CASCADE"), nullable=False
    )
    status: Mapped[str] = mapped_column(Text, nullable=False, server_default="pending")
    config: Mapped[dict[str, Any]] = mapped_column(JSONB, server_default="{}")
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    league: Mapped[FantasyLeague] = relationship(back_populates="draft_session")
    picks: Mapped[list[DraftPick]] = relationship(
        back_populates="draft_session",
        cascade="save-update, merge, refresh-expire, expunge",
    )


class DraftPick(Base):
    __tablename__ = "draft_picks"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    draft_session_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("draft_sessions.id", ondelete="CASCADE"), nullable=False
    )
    pick_index: Mapped[int] = mapped_column(Integer, nullable=False)
    league_membership_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("league_memberships.id", ondelete="RESTRICT"), nullable=False
    )
    participant_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("participants.id", ondelete="RESTRICT"), nullable=False
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    draft_session: Mapped[DraftSession] = relationship(back_populates="picks")
    membership: Mapped[LeagueMembership] = relationship()
    participant: Mapped[Participant] = relationship()


class FantasyRound(Base):
    __tablename__ = "fantasy_rounds"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    league_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("fantasy_leagues.id", ondelete="CASCADE"), nullable=False
    )
    tournament_round_id: Mapped[uuid.UUID | None] = mapped_column(
        UUID(as_uuid=True), ForeignKey("tournament_rounds.id", ondelete="SET NULL")
    )
    order_index: Mapped[int] = mapped_column(Integer, nullable=False)
    name: Mapped[str] = mapped_column(Text, nullable=False)
    opens_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    locks_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    metadata_: Mapped[dict[str, Any]] = mapped_column("metadata", JSONB, server_default="{}")
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    league: Mapped[FantasyLeague] = relationship(back_populates="fantasy_rounds")
    tournament_round: Mapped[TournamentRound | None] = relationship()
    lineup_submissions: Mapped[list[LineupSubmission]] = relationship(
        back_populates="fantasy_round",
        cascade="save-update, merge, refresh-expire, expunge",
    )
    round_scores: Mapped[list[RoundScore]] = relationship(
        back_populates="fantasy_round",
        cascade="save-update, merge, refresh-expire, expunge",
    )
    usage_ledger_entries: Mapped[list[UsageLedgerEntry]] = relationship(
        back_populates="fantasy_round",
        cascade="save-update, merge, refresh-expire, expunge",
    )


class LineupSubmission(Base):
    __tablename__ = "lineup_submissions"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    fantasy_round_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("fantasy_rounds.id", ondelete="CASCADE"), nullable=False
    )
    league_membership_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("league_memberships.id", ondelete="CASCADE"), nullable=False
    )
    status: Mapped[str] = mapped_column(Text, nullable=False, server_default="draft")
    submitted_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    metadata_: Mapped[dict[str, Any]] = mapped_column("metadata", JSONB, server_default="{}")
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    fantasy_round: Mapped[FantasyRound] = relationship(back_populates="lineup_submissions")
    membership: Mapped[LeagueMembership] = relationship()
    slots: Mapped[list[LineupSlot]] = relationship(
        back_populates="lineup_submission",
        cascade="save-update, merge, refresh-expire, expunge",
    )


class LineupSlot(Base):
    __tablename__ = "lineup_slots"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    lineup_submission_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("lineup_submissions.id", ondelete="CASCADE"), nullable=False
    )
    slot_role: Mapped[str] = mapped_column(Text, nullable=False)
    order_index: Mapped[int] = mapped_column(Integer, nullable=False)
    participant_id: Mapped[uuid.UUID | None] = mapped_column(
        UUID(as_uuid=True), ForeignKey("participants.id", ondelete="SET NULL")
    )
    metadata_: Mapped[dict[str, Any]] = mapped_column("metadata", JSONB, server_default="{}")
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    lineup_submission: Mapped[LineupSubmission] = relationship(back_populates="slots")
    participant: Mapped[Participant | None] = relationship()


class ScoringRuleSet(Base):
    __tablename__ = "scoring_rule_sets"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    league_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("fantasy_leagues.id", ondelete="CASCADE"), nullable=False
    )
    version: Mapped[int] = mapped_column(Integer, nullable=False)
    engine_key: Mapped[str] = mapped_column(Text, nullable=False)
    parameters: Mapped[dict[str, Any]] = mapped_column(JSONB, server_default="{}")
    effective_from: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    is_active: Mapped[bool] = mapped_column(Boolean, nullable=False, server_default=false())
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    league: Mapped[FantasyLeague] = relationship(back_populates="scoring_rule_sets")


class RoundScore(Base):
    __tablename__ = "round_scores"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    fantasy_round_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("fantasy_rounds.id", ondelete="CASCADE"), nullable=False
    )
    league_membership_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("league_memberships.id", ondelete="CASCADE"), nullable=False
    )
    points: Mapped[float] = mapped_column(Numeric, nullable=False, server_default="0")
    breakdown: Mapped[dict[str, Any]] = mapped_column(JSONB, server_default="{}")
    computed_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    fantasy_round: Mapped[FantasyRound] = relationship(back_populates="round_scores")
    membership: Mapped[LeagueMembership] = relationship()


class UsageConstraintPolicy(Base):
    __tablename__ = "usage_constraint_policies"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    league_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("fantasy_leagues.id", ondelete="CASCADE"), nullable=False
    )
    constraint_type: Mapped[str] = mapped_column(Text, nullable=False)
    parameters: Mapped[dict[str, Any]] = mapped_column(JSONB, server_default="{}")
    is_active: Mapped[bool] = mapped_column(Boolean, nullable=False, server_default=true())
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )

    league: Mapped[FantasyLeague] = relationship(back_populates="usage_policies")


class UsageLedgerEntry(Base):
    __tablename__ = "usage_ledger_entries"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    league_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("fantasy_leagues.id", ondelete="CASCADE"), nullable=False
    )
    league_membership_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("league_memberships.id", ondelete="CASCADE"), nullable=False
    )
    participant_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("participants.id", ondelete="CASCADE"), nullable=False
    )
    fantasy_round_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("fantasy_rounds.id", ondelete="CASCADE"), nullable=False
    )
    context: Mapped[dict[str, Any]] = mapped_column(JSONB, server_default="{}")
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )

    league: Mapped[FantasyLeague] = relationship(back_populates="usage_ledger_entries")
    membership: Mapped[LeagueMembership] = relationship()
    participant: Mapped[Participant] = relationship()
    fantasy_round: Mapped[FantasyRound] = relationship(back_populates="usage_ledger_entries")

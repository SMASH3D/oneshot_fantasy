-- Oneshot Fantasy — PostgreSQL schema (domain Step 2)
-- PostgreSQL 14+ recommended; uses gen_random_uuid() (built-in from PG 13+).
-- Sport-agnostic: tournament structure + fantasy layer + stats + scoring + usage.

BEGIN;

-- ---------------------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------------------

CREATE TABLE users (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    email           text UNIQUE,
    display_name    text NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now (),
    updated_at      timestamptz NOT NULL DEFAULT now ()
);

CREATE INDEX idx_users_created_at ON users (created_at);

COMMENT ON TABLE users IS 'Account identity; authentication details live in infrastructure later.';

-- ---------------------------------------------------------------------------
-- Tournament structure (real-world competition)
-- ---------------------------------------------------------------------------

CREATE TABLE tournaments (
    id                      uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    name                    text NOT NULL,
    slug                    text UNIQUE,
    sport_key               text NOT NULL,
    stats_adapter_key       text,
    default_scoring_engine_key text,
    timezone                text NOT NULL DEFAULT 'UTC',
    starts_at               timestamptz,
    ends_at                 timestamptz,
    status                  text NOT NULL DEFAULT 'draft',
    metadata                jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at              timestamptz NOT NULL DEFAULT now (),
    updated_at              timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT ck_tournaments_status CHECK (
        status IN ('draft', 'scheduled', 'live', 'completed', 'archived')
    )
);

CREATE INDEX idx_tournaments_sport_key ON tournaments (sport_key);
CREATE INDEX idx_tournaments_status ON tournaments (status);
CREATE INDEX idx_tournaments_starts_at ON tournaments (starts_at);

COMMENT ON TABLE tournaments IS 'One elimination-style event instance (e.g. a World Cup or playoff year).';
COMMENT ON COLUMN tournaments.sport_key IS 'Opaque key for adapters (e.g. soccer_fifa, basketball_nba); not an enum in DB.';
COMMENT ON COLUMN tournaments.metadata IS 'Venue, season label, provider ids, UI hints, etc.';

CREATE TABLE tournament_rounds (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    tournament_id   uuid NOT NULL REFERENCES tournaments (id) ON DELETE CASCADE,
    order_index     int NOT NULL,
    name            text NOT NULL,
    canonical_key   text,
    metadata        jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at      timestamptz NOT NULL DEFAULT now (),
    updated_at      timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT uq_tournament_rounds_order UNIQUE (tournament_id, order_index),
    CONSTRAINT ck_tournament_rounds_order_non_negative CHECK (order_index >= 0)
);

CREATE INDEX idx_tournament_rounds_tournament ON tournament_rounds (tournament_id);

COMMENT ON TABLE tournament_rounds IS 'Bracket phases (Ro16, QF, …); ordered within a tournament.';

CREATE TABLE matches (
    id                  uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    tournament_round_id uuid NOT NULL REFERENCES tournament_rounds (id) ON DELETE CASCADE,
    scheduled_at        timestamptz,
    status              text NOT NULL DEFAULT 'scheduled',
    bracket_path        jsonb,
    metadata            jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at          timestamptz NOT NULL DEFAULT now (),
    updated_at          timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT ck_matches_status CHECK (
        status IN ('scheduled', 'live', 'final', 'void', 'postponed')
    )
);

CREATE INDEX idx_matches_round ON matches (tournament_round_id);
CREATE INDEX idx_matches_scheduled_at ON matches (scheduled_at);
CREATE INDEX idx_matches_status ON matches (status);

COMMENT ON TABLE matches IS 'Single contest within a tournament round.';
COMMENT ON COLUMN matches.bracket_path IS 'Optional graph hints (e.g. slot codes) for complex brackets.';

CREATE TABLE participants (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    tournament_id   uuid NOT NULL REFERENCES tournaments (id) ON DELETE CASCADE,
    external_ref    text,
    display_name    text NOT NULL,
    kind            text,
    metadata        jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at      timestamptz NOT NULL DEFAULT now (),
    updated_at      timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT uq_participants_tournament_external_ref UNIQUE (tournament_id, external_ref)
);

CREATE INDEX idx_participants_tournament ON participants (tournament_id);
CREATE INDEX idx_participants_kind ON participants (kind);

COMMENT ON TABLE participants IS 'Generic competitor: team, athlete, nation, pair, etc.; same rows are drafted and lined up.';
COMMENT ON COLUMN participants.external_ref IS 'Stable id from stats provider within this tournament.';

CREATE TABLE match_slots (
    id                  uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    match_id            uuid NOT NULL REFERENCES matches (id) ON DELETE CASCADE,
    slot_index          int NOT NULL,
    participant_id      uuid REFERENCES participants (id) ON DELETE SET NULL,
    seed                int,
    advances_from_match_id uuid REFERENCES matches (id) ON DELETE SET NULL,
    metadata            jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at          timestamptz NOT NULL DEFAULT now (),
    updated_at          timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT uq_match_slots UNIQUE (match_id, slot_index),
    CONSTRAINT ck_match_slots_slot_index_non_negative CHECK (slot_index >= 0)
);

CREATE INDEX idx_match_slots_match ON match_slots (match_id);
CREATE INDEX idx_match_slots_participant ON match_slots (participant_id);

COMMENT ON TABLE match_slots IS 'One side of a match; participant may be null until bracket resolves.';

-- ---------------------------------------------------------------------------
-- Stats / performance facts (ingestion output)
-- ---------------------------------------------------------------------------

CREATE TABLE stat_events (
    id                  uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    tournament_id       uuid NOT NULL REFERENCES tournaments (id) ON DELETE CASCADE,
    participant_id      uuid NOT NULL REFERENCES participants (id) ON DELETE CASCADE,
    match_id            uuid REFERENCES matches (id) ON DELETE SET NULL,
    tournament_round_id uuid REFERENCES tournament_rounds (id) ON DELETE SET NULL,
    metric_key          text NOT NULL,
    value_numeric       numeric NOT NULL,
    occurred_at         timestamptz NOT NULL DEFAULT now (),
    details             jsonb NOT NULL DEFAULT '{}'::jsonb,
    source              text,
    created_at          timestamptz NOT NULL DEFAULT now ()
);

CREATE INDEX idx_stat_events_tournament ON stat_events (tournament_id);
CREATE INDEX idx_stat_events_participant ON stat_events (participant_id);
CREATE INDEX idx_stat_events_match ON stat_events (match_id);
CREATE INDEX idx_stat_events_round ON stat_events (tournament_round_id);
CREATE INDEX idx_stat_events_metric ON stat_events (metric_key);
CREATE INDEX idx_stat_events_occurred_at ON stat_events (occurred_at);
CREATE INDEX idx_stat_events_tournament_participant_metric
    ON stat_events (tournament_id, participant_id, metric_key);

COMMENT ON TABLE stat_events IS 'Normalized stat lines for scoring; metric_key is adapter-defined (sport-specific).';
COMMENT ON COLUMN stat_events.details IS 'Raw payload, units, period breakdowns, provenance.';

-- ---------------------------------------------------------------------------
-- Fantasy layer
-- ---------------------------------------------------------------------------

CREATE TABLE fantasy_leagues (
    id                      uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    tournament_id           uuid NOT NULL REFERENCES tournaments (id) ON DELETE RESTRICT,
    name                    text NOT NULL,
    commissioner_user_id    uuid NOT NULL REFERENCES users (id) ON DELETE RESTRICT,
    status                  text NOT NULL DEFAULT 'forming',
    settings                jsonb NOT NULL DEFAULT '{}'::jsonb,
    lineup_template         jsonb NOT NULL DEFAULT '[]'::jsonb,
    scoring_config          jsonb NOT NULL DEFAULT '{}'::jsonb,
    scoring_config_hash     text NOT NULL DEFAULT '',
    created_at              timestamptz NOT NULL DEFAULT now (),
    updated_at              timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT ck_fantasy_leagues_status CHECK (
        status IN ('forming', 'draft', 'active', 'completed', 'archived')
    )
);

CREATE INDEX idx_fantasy_leagues_tournament ON fantasy_leagues (tournament_id);
CREATE INDEX idx_fantasy_leagues_commissioner ON fantasy_leagues (commissioner_user_id);

COMMENT ON TABLE fantasy_leagues IS 'Fantasy game bound to exactly one tournament.';
COMMENT ON COLUMN fantasy_leagues.settings IS 'Roster size, draft type, lock rules, tie-breakers, etc.';
COMMENT ON COLUMN fantasy_leagues.lineup_template IS 'Ordered slot roles, e.g. [{""role"": ""starter_1""}, ...].';

CREATE TABLE league_memberships (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    league_id       uuid NOT NULL REFERENCES fantasy_leagues (id) ON DELETE CASCADE,
    user_id         uuid NOT NULL REFERENCES users (id) ON DELETE RESTRICT,
    nickname        text,
    role            text NOT NULL DEFAULT 'member',
    joined_at       timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT uq_league_memberships UNIQUE (league_id, user_id),
    CONSTRAINT ck_league_memberships_role CHECK (role IN ('commissioner', 'member'))
);

CREATE INDEX idx_league_memberships_league ON league_memberships (league_id);
CREATE INDEX idx_league_memberships_user ON league_memberships (user_id);

CREATE TABLE draft_sessions (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    league_id   uuid NOT NULL UNIQUE REFERENCES fantasy_leagues (id) ON DELETE CASCADE,
    status      text NOT NULL DEFAULT 'pending',
    config      jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at  timestamptz NOT NULL DEFAULT now (),
    updated_at  timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT ck_draft_sessions_status CHECK (
        status IN ('pending', 'in_progress', 'completed', 'cancelled')
    )
);

CREATE INDEX idx_draft_sessions_league ON draft_sessions (league_id);

COMMENT ON COLUMN draft_sessions.config IS 'Snake flag, pick clock seconds, order seeds, etc.';

CREATE TABLE draft_picks (
    id                      uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    draft_session_id        uuid NOT NULL REFERENCES draft_sessions (id) ON DELETE CASCADE,
    pick_index              int NOT NULL,
    league_membership_id    uuid NOT NULL REFERENCES league_memberships (id) ON DELETE RESTRICT,
    participant_id          uuid NOT NULL REFERENCES participants (id) ON DELETE RESTRICT,
    created_at              timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT uq_draft_picks_session_index UNIQUE (draft_session_id, pick_index),
    CONSTRAINT ck_draft_picks_pick_index_non_negative CHECK (pick_index >= 0)
);

CREATE INDEX idx_draft_picks_session ON draft_picks (draft_session_id);
CREATE INDEX idx_draft_picks_membership ON draft_picks (league_membership_id);
CREATE INDEX idx_draft_picks_participant ON draft_picks (participant_id);

CREATE TABLE fantasy_rounds (
    id                  uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    league_id           uuid NOT NULL REFERENCES fantasy_leagues (id) ON DELETE CASCADE,
    tournament_round_id uuid REFERENCES tournament_rounds (id) ON DELETE SET NULL,
    order_index         int NOT NULL,
    name                text NOT NULL,
    opens_at            timestamptz,
    locks_at            timestamptz,
    metadata            jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at          timestamptz NOT NULL DEFAULT now (),
    updated_at          timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT uq_fantasy_rounds_league_order UNIQUE (league_id, order_index),
    CONSTRAINT ck_fantasy_rounds_order_non_negative CHECK (order_index >= 0)
);

CREATE INDEX idx_fantasy_rounds_league ON fantasy_rounds (league_id);
CREATE INDEX idx_fantasy_rounds_tournament_round ON fantasy_rounds (tournament_round_id);
CREATE INDEX idx_fantasy_rounds_locks_at ON fantasy_rounds (locks_at);

COMMENT ON TABLE fantasy_rounds IS 'Scoring period for a league; optionally aligned to a tournament round.';

CREATE TABLE lineup_submissions (
    id                      uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    fantasy_round_id        uuid NOT NULL REFERENCES fantasy_rounds (id) ON DELETE CASCADE,
    league_membership_id    uuid NOT NULL REFERENCES league_memberships (id) ON DELETE CASCADE,
    status                  text NOT NULL DEFAULT 'draft',
    submitted_at            timestamptz,
    metadata                jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at              timestamptz NOT NULL DEFAULT now (),
    updated_at              timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT uq_lineup_submissions UNIQUE (fantasy_round_id, league_membership_id),
    CONSTRAINT ck_lineup_submissions_status CHECK (
        status IN ('draft', 'submitted', 'locked', 'void')
    )
);

CREATE INDEX idx_lineup_submissions_round ON lineup_submissions (fantasy_round_id);
CREATE INDEX idx_lineup_submissions_membership ON lineup_submissions (league_membership_id);

CREATE TABLE lineup_slots (
    id                      uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    lineup_submission_id    uuid NOT NULL REFERENCES lineup_submissions (id) ON DELETE CASCADE,
    slot_role               text NOT NULL,
    order_index             int NOT NULL,
    participant_id          uuid REFERENCES participants (id) ON DELETE SET NULL,
    metadata                jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at              timestamptz NOT NULL DEFAULT now (),
    updated_at              timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT uq_lineup_slots_submission_order UNIQUE (lineup_submission_id, order_index)
);

CREATE INDEX idx_lineup_slots_submission ON lineup_slots (lineup_submission_id);
CREATE INDEX idx_lineup_slots_participant ON lineup_slots (participant_id);

COMMENT ON COLUMN lineup_slots.slot_role IS 'Role key from league lineup_template (starter, flex, captain, …).';

-- ---------------------------------------------------------------------------
-- Scoring rules (flexible, versioned)
-- ---------------------------------------------------------------------------

CREATE TABLE scoring_rule_sets (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    league_id       uuid NOT NULL REFERENCES fantasy_leagues (id) ON DELETE CASCADE,
    version         int NOT NULL,
    engine_key      text NOT NULL,
    parameters      jsonb NOT NULL DEFAULT '{}'::jsonb,
    effective_from  timestamptz,
    is_active       boolean NOT NULL DEFAULT false,
    created_at      timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT uq_scoring_rule_sets_league_version UNIQUE (league_id, version)
);

CREATE INDEX idx_scoring_rule_sets_league ON scoring_rule_sets (league_id);
CREATE UNIQUE INDEX uq_scoring_rule_sets_one_active_per_league
    ON scoring_rule_sets (league_id)
    WHERE is_active;

COMMENT ON TABLE scoring_rule_sets IS 'Per-league scoring definition; engine_key selects strategy implementation.';
COMMENT ON COLUMN scoring_rule_sets.parameters IS 'Weights, caps, bonuses — interpreted by engine_key.';

-- ---------------------------------------------------------------------------
-- Scores (aggregated fantasy points)
-- ---------------------------------------------------------------------------

CREATE TABLE round_scores (
    id                      uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    fantasy_round_id        uuid NOT NULL REFERENCES fantasy_rounds (id) ON DELETE CASCADE,
    league_membership_id    uuid NOT NULL REFERENCES league_memberships (id) ON DELETE CASCADE,
    points                  numeric(14, 4) NOT NULL DEFAULT 0,
    breakdown               jsonb NOT NULL DEFAULT '{}'::jsonb,
    computed_at             timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT uq_round_scores UNIQUE (fantasy_round_id, league_membership_id)
);

CREATE INDEX idx_round_scores_round ON round_scores (fantasy_round_id);
CREATE INDEX idx_round_scores_membership ON round_scores (league_membership_id);
CREATE INDEX idx_round_scores_points ON round_scores (fantasy_round_id, points DESC);

COMMENT ON COLUMN round_scores.breakdown IS 'Per-slot or per-metric detail for transparency and disputes.';

-- ---------------------------------------------------------------------------
-- Usage constraints + ledger (e.g. use-once across competition)
-- ---------------------------------------------------------------------------

CREATE TABLE usage_constraint_policies (
    id                  uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    league_id           uuid NOT NULL REFERENCES fantasy_leagues (id) ON DELETE CASCADE,
    constraint_type     text NOT NULL,
    parameters          jsonb NOT NULL DEFAULT '{}'::jsonb,
    is_active           boolean NOT NULL DEFAULT true,
    created_at          timestamptz NOT NULL DEFAULT now (),
    updated_at          timestamptz NOT NULL DEFAULT now ()
);

CREATE INDEX idx_usage_constraint_policies_league ON usage_constraint_policies (league_id);
CREATE INDEX idx_usage_constraint_policies_active ON usage_constraint_policies (league_id) WHERE is_active;

COMMENT ON TABLE usage_constraint_policies IS 'Declarative rules (USE_ONCE_GLOBAL, max per stage, …).';
COMMENT ON COLUMN usage_constraint_policies.constraint_type IS 'Application-enumerated string; not a DB enum for extensibility.';

CREATE TABLE usage_ledger_entries (
    id                      uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    league_id               uuid NOT NULL REFERENCES fantasy_leagues (id) ON DELETE CASCADE,
    league_membership_id    uuid NOT NULL REFERENCES league_memberships (id) ON DELETE CASCADE,
    participant_id          uuid NOT NULL REFERENCES participants (id) ON DELETE CASCADE,
    fantasy_round_id        uuid NOT NULL REFERENCES fantasy_rounds (id) ON DELETE CASCADE,
    context                 jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at              timestamptz NOT NULL DEFAULT now ()
);

CREATE INDEX idx_usage_ledger_league_member_participant
    ON usage_ledger_entries (league_id, league_membership_id, participant_id);
CREATE INDEX idx_usage_ledger_round ON usage_ledger_entries (fantasy_round_id);
CREATE INDEX idx_usage_ledger_league_round
    ON usage_ledger_entries (league_id, fantasy_round_id);

COMMENT ON TABLE usage_ledger_entries IS 'Append-only facts: participant was used by membership in a fantasy round.';
COMMENT ON COLUMN usage_ledger_entries.context IS 'e.g. {""slot_role"": ""starter_1""}; policy decides what consumes a use.';

-- ---------------------------------------------------------------------------
-- Participant Aggregated Scores (Hash-driven)
-- ---------------------------------------------------------------------------

CREATE TABLE participant_aggregated_scores (
    id                  uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    participant_id      uuid NOT NULL REFERENCES participants (id) ON DELETE CASCADE,
    score_config_hash   text NOT NULL,
    season              text NOT NULL,
    stat_scope          text NOT NULL DEFAULT 'season',
    total_score         numeric(14, 4) NOT NULL DEFAULT 0,
    breakdown           jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at          timestamptz NOT NULL DEFAULT now (),
    updated_at          timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT uq_participant_score_config_season_scope UNIQUE (participant_id, score_config_hash, season, stat_scope)
);

CREATE INDEX idx_participant_scores_hash ON participant_aggregated_scores (score_config_hash);
CREATE INDEX idx_participant_scores_season ON participant_aggregated_scores (season);

-- ---------------------------------------------------------------------------
-- Scoring Config Presets
-- ---------------------------------------------------------------------------

CREATE TABLE scoring_config_presets (
    id                  uuid PRIMARY KEY DEFAULT gen_random_uuid (),
    name                text NOT NULL,
    scoring_config      jsonb NOT NULL DEFAULT '{}'::jsonb,
    scoring_config_hash text NOT NULL,
    created_at          timestamptz NOT NULL DEFAULT now (),
    updated_at          timestamptz NOT NULL DEFAULT now (),
    CONSTRAINT uq_scoring_config_presets_hash UNIQUE (scoring_config_hash)
);

COMMIT;

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260413192458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE draft_picks (
              pick_index INT NOT NULL,
              created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                draft_session_id UUID NOT NULL,
                league_membership_id UUID NOT NULL,
                participant_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_draft_picks_session_index ON draft_picks (draft_session_id, pick_index)');
        $this->addSql('CREATE INDEX idx_draft_picks_participant ON draft_picks (participant_id)');
        $this->addSql('CREATE INDEX idx_draft_picks_membership ON draft_picks (league_membership_id)');
        $this->addSql('CREATE INDEX idx_draft_picks_session ON draft_picks (draft_session_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              draft_picks
            ADD
              CONSTRAINT fk_7b3dcce75e6a9d74 FOREIGN KEY (draft_session_id) REFERENCES draft_sessions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              draft_picks
            ADD
              CONSTRAINT fk_7b3dcce748d1ec4a FOREIGN KEY (league_membership_id) REFERENCES league_memberships (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              draft_picks
            ADD
              CONSTRAINT fk_7b3dcce79d1c3019 FOREIGN KEY (participant_id) REFERENCES participants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE draft_sessions (
              status VARCHAR(255) DEFAULT 'pending' NOT NULL,
              config JSON DEFAULT '{}' NOT NULL,
              created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                league_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_draft_sessions_league ON draft_sessions (league_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_38ff251958afc4de ON draft_sessions (league_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              draft_sessions
            ADD
              CONSTRAINT fk_38ff251958afc4de FOREIGN KEY (league_id) REFERENCES fantasy_leagues (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE fantasy_leagues (
              name VARCHAR(255) NOT NULL,
              status VARCHAR(255) DEFAULT 'forming' NOT NULL,
              settings JSON DEFAULT '{}' NOT NULL,
              lineup_template JSON DEFAULT '[]' NOT NULL,
              created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                tournament_id UUID NOT NULL,
                commissioner_user_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_fantasy_leagues_commissioner ON fantasy_leagues (commissioner_user_id)');
        $this->addSql('CREATE INDEX idx_fantasy_leagues_tournament ON fantasy_leagues (tournament_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              fantasy_leagues
            ADD
              CONSTRAINT fk_bc8e4fab39d82b0a FOREIGN KEY (commissioner_user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              fantasy_leagues
            ADD
              CONSTRAINT fk_bc8e4fab33d1a3e7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE fantasy_rounds (
              order_index INT NOT NULL,
              name VARCHAR(255) NOT NULL,
              opens_at TIMESTAMP(0)
              WITH
                TIME ZONE DEFAULT NULL,
                locks_at TIMESTAMP(0)
              WITH
                TIME ZONE DEFAULT NULL,
                metadata JSON DEFAULT '{}' NOT NULL,
                created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                league_id UUID NOT NULL,
                tournament_round_id UUID DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_fantasy_rounds_tournament_round ON fantasy_rounds (tournament_round_id)');
        $this->addSql('CREATE INDEX idx_fantasy_rounds_league ON fantasy_rounds (league_id)');
        $this->addSql('CREATE INDEX idx_fantasy_rounds_locks_at ON fantasy_rounds (locks_at)');
        $this->addSql('CREATE UNIQUE INDEX uq_fantasy_rounds_league_order ON fantasy_rounds (league_id, order_index)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              fantasy_rounds
            ADD
              CONSTRAINT fk_e36dd7a846beba45 FOREIGN KEY (tournament_round_id) REFERENCES tournament_rounds (id) ON DELETE
            SET
              NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              fantasy_rounds
            ADD
              CONSTRAINT fk_e36dd7a858afc4de FOREIGN KEY (league_id) REFERENCES fantasy_leagues (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE league_memberships (
              nickname VARCHAR(255) DEFAULT NULL,
              role VARCHAR(255) DEFAULT 'member' NOT NULL,
              joined_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                league_id UUID NOT NULL,
                user_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_league_memberships_league ON league_memberships (league_id)');
        $this->addSql('CREATE INDEX idx_league_memberships_user ON league_memberships (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_league_memberships ON league_memberships (league_id, user_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              league_memberships
            ADD
              CONSTRAINT fk_8b5614f858afc4de FOREIGN KEY (league_id) REFERENCES fantasy_leagues (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              league_memberships
            ADD
              CONSTRAINT fk_8b5614f8a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE lineup_submissions (
              status VARCHAR(255) DEFAULT 'draft' NOT NULL,
              submitted_at TIMESTAMP(0)
              WITH
                TIME ZONE DEFAULT NULL,
                metadata JSON DEFAULT '{}' NOT NULL,
                created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                fantasy_round_id UUID NOT NULL,
                league_membership_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uq_lineup_submissions ON lineup_submissions (
              fantasy_round_id, league_membership_id
            )
        SQL);
        $this->addSql('CREATE INDEX idx_lineup_submissions_membership ON lineup_submissions (league_membership_id)');
        $this->addSql('CREATE INDEX idx_lineup_submissions_round ON lineup_submissions (fantasy_round_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              lineup_submissions
            ADD
              CONSTRAINT fk_2831914af0074a2f FOREIGN KEY (fantasy_round_id) REFERENCES fantasy_rounds (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              lineup_submissions
            ADD
              CONSTRAINT fk_2831914a48d1ec4a FOREIGN KEY (league_membership_id) REFERENCES league_memberships (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE participants (
              external_id VARCHAR(255) NOT NULL,
              name VARCHAR(512) NOT NULL,
              sport VARCHAR(64) NOT NULL,
              type VARCHAR(32) NOT NULL,
              team_name VARCHAR(512) DEFAULT NULL,
              "position" VARCHAR(64) DEFAULT NULL,
              metadata JSON DEFAULT NULL,
              created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_participants_external_id ON participants (external_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_participants_external_id_sport ON participants (external_id, sport)');
        $this->addSql('CREATE INDEX idx_participants_sport ON participants (sport)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE round_scores (
              points NUMERIC(14, 4) NOT NULL,
              breakdown JSON DEFAULT '{}' NOT NULL,
              computed_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                fantasy_round_id UUID NOT NULL,
                league_membership_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_round_scores_points ON round_scores (fantasy_round_id, points)');
        $this->addSql('CREATE UNIQUE INDEX uq_round_scores ON round_scores (fantasy_round_id, league_membership_id)');
        $this->addSql('CREATE INDEX idx_round_scores_membership ON round_scores (league_membership_id)');
        $this->addSql('CREATE INDEX idx_round_scores_round ON round_scores (fantasy_round_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              round_scores
            ADD
              CONSTRAINT fk_c3e70ef248d1ec4a FOREIGN KEY (league_membership_id) REFERENCES league_memberships (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              round_scores
            ADD
              CONSTRAINT fk_c3e70ef2f0074a2f FOREIGN KEY (fantasy_round_id) REFERENCES fantasy_rounds (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE tournament_rounds (
              order_index INT NOT NULL,
              name VARCHAR(255) NOT NULL,
              canonical_key VARCHAR(255) DEFAULT NULL,
              metadata JSON DEFAULT '{}' NOT NULL,
              created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                tournament_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uq_tournament_rounds_order ON tournament_rounds (tournament_id, order_index)
        SQL);
        $this->addSql('CREATE INDEX idx_tournament_rounds_tournament ON tournament_rounds (tournament_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tournament_rounds
            ADD
              CONSTRAINT fk_5ed52b0433d1a3e7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE tournaments (
              name VARCHAR(255) NOT NULL,
              slug VARCHAR(255) DEFAULT NULL,
              sport_key VARCHAR(255) NOT NULL,
              stats_adapter_key VARCHAR(255) DEFAULT NULL,
              default_scoring_engine_key VARCHAR(255) DEFAULT NULL,
              timezone VARCHAR(255) DEFAULT 'UTC' NOT NULL,
              starts_at TIMESTAMP(0)
              WITH
                TIME ZONE DEFAULT NULL,
                ends_at TIMESTAMP(0)
              WITH
                TIME ZONE DEFAULT NULL,
                status VARCHAR(255) DEFAULT 'draft' NOT NULL,
                metadata JSON DEFAULT '{}' NOT NULL,
                created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_e4bcfac3989d9b62 ON tournaments (slug)');
        $this->addSql('CREATE INDEX idx_tournaments_sport_key ON tournaments (sport_key)');
        $this->addSql('CREATE INDEX idx_tournaments_starts_at ON tournaments (starts_at)');
        $this->addSql('CREATE INDEX idx_tournaments_status ON tournaments (status)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE usage_constraint_policies (
              constraint_type VARCHAR(255) NOT NULL,
              parameters JSON DEFAULT '{}' NOT NULL,
              is_active BOOLEAN DEFAULT true NOT NULL,
              created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                league_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_usage_constraint_policies_league ON usage_constraint_policies (league_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              usage_constraint_policies
            ADD
              CONSTRAINT fk_ce9123aa58afc4de FOREIGN KEY (league_id) REFERENCES fantasy_leagues (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE usage_ledger_entries (
              context JSON DEFAULT '{}' NOT NULL,
              created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                league_id UUID NOT NULL,
                league_membership_id UUID NOT NULL,
                participant_id UUID NOT NULL,
                fantasy_round_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_c0a41fcc58afc4de ON usage_ledger_entries (league_id)');
        $this->addSql('CREATE INDEX idx_c0a41fcc48d1ec4a ON usage_ledger_entries (league_membership_id)');
        $this->addSql('CREATE INDEX idx_c0a41fcc9d1c3019 ON usage_ledger_entries (participant_id)');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_usage_ledger_league_member_participant ON usage_ledger_entries (
              league_id, league_membership_id,
              participant_id
            )
        SQL);
        $this->addSql('CREATE INDEX idx_usage_ledger_round ON usage_ledger_entries (fantasy_round_id)');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_usage_ledger_league_round ON usage_ledger_entries (league_id, fantasy_round_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              usage_ledger_entries
            ADD
              CONSTRAINT fk_c0a41fcc9d1c3019 FOREIGN KEY (participant_id) REFERENCES participants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              usage_ledger_entries
            ADD
              CONSTRAINT fk_c0a41fccf0074a2f FOREIGN KEY (fantasy_round_id) REFERENCES fantasy_rounds (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              usage_ledger_entries
            ADD
              CONSTRAINT fk_c0a41fcc48d1ec4a FOREIGN KEY (league_membership_id) REFERENCES league_memberships (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              usage_ledger_entries
            ADD
              CONSTRAINT fk_c0a41fcc58afc4de FOREIGN KEY (league_id) REFERENCES fantasy_leagues (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE users (
              email VARCHAR(255) DEFAULT NULL,
              display_name VARCHAR(255) NOT NULL,
              created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e9e7927c74 ON users (email)');
        $this->addSql('CREATE INDEX idx_users_created_at ON users (created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "draft_picks"');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "draft_sessions"');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "fantasy_leagues"');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "fantasy_rounds"');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "league_memberships"');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "lineup_submissions"');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "participants"');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "round_scores"');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "tournament_rounds"');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "tournaments"');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "usage_constraint_policies"');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "usage_ledger_entries"');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQL120Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQL120Platform'."
        );

        $this->addSql('DROP TABLE "users"');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create tournament_participations table.
 *
 * Tracks each team's participation and status within a tournament:
 *   - active    : still competing
 *   - eliminated: knocked out
 *   - champion  : tournament winner
 *   - playin    : in the play-in phase (not yet in the main bracket)
 *
 * Kept idempotent via a unique constraint on (tournament_id, team_id).
 */
final class Version20260416120001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tournament_participations table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE tournament_participations (
                id           UUID        NOT NULL,
                tournament_id UUID       NOT NULL,
                team_id      UUID        NOT NULL,
                status       VARCHAR(64) NOT NULL DEFAULT 'active',
                seed         INTEGER     DEFAULT NULL,
                metadata     JSON        NOT NULL DEFAULT '{}',
                created_at   TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at   TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_tp_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
                CONSTRAINT fk_tp_team       FOREIGN KEY (team_id)       REFERENCES teams(id)       ON DELETE CASCADE,
                CONSTRAINT uq_tp_tournament_team UNIQUE (tournament_id, team_id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_tp_tournament ON tournament_participations (tournament_id)');
        $this->addSql('CREATE INDEX idx_tp_team       ON tournament_participations (team_id)');
        $this->addSql('CREATE INDEX idx_tp_status     ON tournament_participations (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tournament_participations');
    }
}

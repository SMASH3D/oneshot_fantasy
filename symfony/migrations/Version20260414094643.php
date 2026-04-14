<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414094643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE participant_game_score (score DOUBLE PRECISION NOT NULL, breakdown JSON NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, participant_id UUID NOT NULL, game_id UUID NOT NULL, fantasy_league_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_pgs_participant ON participant_game_score (participant_id)');
        $this->addSql('CREATE INDEX idx_pgs_game ON participant_game_score (game_id)');
        $this->addSql('CREATE INDEX idx_pgs_fantasy_league ON participant_game_score (fantasy_league_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_participant_game_score ON participant_game_score (participant_id, game_id, fantasy_league_id)');
        $this->addSql('ALTER TABLE participant_game_score ADD CONSTRAINT FK_3C6AA8C09D1C3019 FOREIGN KEY (participant_id) REFERENCES participants (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE participant_game_score ADD CONSTRAINT FK_3C6AA8C0E48FD905 FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE participant_game_score ADD CONSTRAINT FK_3C6AA8C025FE5D79 FOREIGN KEY (fantasy_league_id) REFERENCES fantasy_leagues (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE fantasy_leagues ADD scoring_config JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE participant_game_score DROP CONSTRAINT FK_3C6AA8C09D1C3019');
        $this->addSql('ALTER TABLE participant_game_score DROP CONSTRAINT FK_3C6AA8C0E48FD905');
        $this->addSql('ALTER TABLE participant_game_score DROP CONSTRAINT FK_3C6AA8C025FE5D79');
        $this->addSql('DROP TABLE participant_game_score');
        $this->addSql('ALTER TABLE fantasy_leagues DROP scoring_config');
    }
}

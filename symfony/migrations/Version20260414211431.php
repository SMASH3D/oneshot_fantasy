<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414211431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE participant_aggregated_scores (score_config_hash VARCHAR(64) NOT NULL, season VARCHAR(64) NOT NULL, stat_scope VARCHAR(32) DEFAULT \'season\' NOT NULL, total_score DOUBLE PRECISION NOT NULL, breakdown JSON NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, participant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8F8A70049D1C3019 ON participant_aggregated_scores (participant_id)');
        $this->addSql('CREATE INDEX idx_participant_scores_hash ON participant_aggregated_scores (score_config_hash)');
        $this->addSql('CREATE INDEX idx_participant_scores_season ON participant_aggregated_scores (season)');
        $this->addSql('CREATE UNIQUE INDEX uq_participant_score_config_season_scope ON participant_aggregated_scores (participant_id, score_config_hash, season, stat_scope)');
        $this->addSql('ALTER TABLE participant_aggregated_scores ADD CONSTRAINT FK_8F8A70049D1C3019 FOREIGN KEY (participant_id) REFERENCES participants (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE fantasy_leagues ADD scoring_config_hash VARCHAR(64) DEFAULT \'\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE participant_aggregated_scores DROP CONSTRAINT FK_8F8A70049D1C3019');
        $this->addSql('DROP TABLE participant_aggregated_scores');
        $this->addSql('ALTER TABLE fantasy_leagues DROP scoring_config_hash');
    }
}

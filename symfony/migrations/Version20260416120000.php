<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data migration:
 *  - Set bracketType = 'nba_postseason' on both NBA playoff tournaments
 *  - Set bracket_url in tournament metadata (ESPN links)
 *  - Backfill tournament_rounds.metadata with "type": "playoff" for 2025 rounds
 *  - Backfill tournament_rounds.metadata with "type": "playin" for 2026 play-in rounds
 */
final class Version20260416120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set bracketType + bracket_url on NBA tournaments; backfill round type in metadata';
    }

    public function up(Schema $schema): void
    {
        // --- bracketType + bracket_url on both tournaments ---
        // Note: metadata may be an empty JSON array '[]' (Doctrine default); replace entirely.
        $this->addSql(<<<'SQL'
            UPDATE tournaments
            SET bracket_type = 'nba_postseason',
                metadata     = '{"bracket_url": "https://www.espn.com/nba/playoff-bracket/_/season/2025"}'::json
            WHERE slug = 'nba-playoffs-2025'
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE tournaments
            SET bracket_type = 'nba_postseason',
                metadata     = '{"bracket_url": "https://www.espn.com/nba/playoff-bracket/_/season/2026"}'::json
            WHERE slug = 'nba-playoffs-2026'
        SQL);

        // --- Backfill round type on rounds that already carry series metadata (object, not array) ---
        $this->addSql(<<<'SQL'
            UPDATE tournament_rounds
            SET metadata = (metadata::jsonb || '{"type": "playoff"}'::jsonb)::json
            WHERE tournament_id = (SELECT id FROM tournaments WHERE slug = 'nba-playoffs-2025')
              AND metadata::text NOT IN ('[]', '{}', '')
        SQL);

        // --- Rounds with empty metadata: just set the type directly ---
        $this->addSql(<<<'SQL'
            UPDATE tournament_rounds
            SET metadata = '{"type": "playoff"}'::json
            WHERE tournament_id = (SELECT id FROM tournaments WHERE slug = 'nba-playoffs-2025')
              AND metadata::text IN ('[]', '{}')
        SQL);

        // --- Backfill round type: "playin" for 2026 play-in rounds ---
        $this->addSql(<<<'SQL'
            UPDATE tournament_rounds
            SET metadata = '{"type": "playin"}'::json
            WHERE tournament_id = (SELECT id FROM tournaments WHERE slug = 'nba-playoffs-2026')
              AND canonical_key LIKE 'playin_%'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE tournaments
            SET bracket_type = NULL,
                metadata     = (metadata::jsonb - 'bracket_url')::json
            WHERE slug IN ('nba-playoffs-2025', 'nba-playoffs-2026')
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE tournament_rounds
            SET metadata = (metadata::jsonb - 'type')::json
            WHERE tournament_id IN (SELECT id FROM tournaments WHERE slug IN ('nba-playoffs-2025', 'nba-playoffs-2026'))
        SQL);
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415211516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tournaments ADD cms_content TEXT DEFAULT NULL');
        $this->addSql('ALTER INDEX uniq_1483a5e9_nickname RENAME TO UNIQ_1483A5E9A188FE64');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tournaments DROP cms_content');
        $this->addSql('ALTER INDEX uniq_1483a5e9a188fe64 RENAME TO uniq_1483a5e9_nickname');
    }
}

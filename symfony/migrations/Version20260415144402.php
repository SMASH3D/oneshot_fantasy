<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add Symfony security fields to users table.
 *
 * Changes:
 *  - Rename display_name → nickname (VARCHAR 50, unique)
 *  - Add password (VARCHAR 255, hashed)
 *  - Add roles (JSON)
 *  - Make email non-nullable
 */
final class Version20260415144402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Symfony security fields (password, roles) and rename display_name to nickname';
    }

    public function up(Schema $schema): void
    {
        // Rename display_name to nickname and tighten the length
        $this->addSql('ALTER TABLE users RENAME COLUMN display_name TO nickname');
        $this->addSql('ALTER TABLE users ALTER COLUMN nickname TYPE VARCHAR(50)');

        // Add hashed password column
        $this->addSql('ALTER TABLE users ADD password VARCHAR(255) NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE users ALTER COLUMN password DROP DEFAULT');

        // Add roles as a JSON array
        $this->addSql('ALTER TABLE users ADD roles JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE users ALTER COLUMN roles DROP DEFAULT');

        // Email was nullable in the MVP; authentication requires a value
        $this->addSql('UPDATE users SET email = CONCAT(\'unknown+\', id::text, \'@placeholder.invalid\') WHERE email IS NULL');
        $this->addSql('ALTER TABLE users ALTER email SET NOT NULL');

        // Unique index on nickname
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9_nickname ON users (nickname)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_1483A5E9_nickname');
        $this->addSql('ALTER TABLE users ALTER email DROP NOT NULL');
        $this->addSql('ALTER TABLE users DROP roles');
        $this->addSql('ALTER TABLE users DROP password');
        $this->addSql('ALTER TABLE users ALTER COLUMN nickname TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE users RENAME COLUMN nickname TO display_name');
    }
}

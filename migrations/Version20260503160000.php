<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair refresh_token FK: idempotent drop/re-add so existing databases that executed Version20260503100000 before the FK fix receive the correction (#163).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This migration can only run on PostgreSQL.'
        );

        // DROP IF EXISTS is case-insensitive and safe to run on any database state.
        $this->addSql('ALTER TABLE refresh_token DROP CONSTRAINT IF EXISTS fk_c74f2195a76ed395');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT fk_c74f2195a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This migration can only run on PostgreSQL.'
        );

        // Re-declare without ON DELETE CASCADE to match the pre-fix state.
        $this->addSql('ALTER TABLE refresh_token DROP CONSTRAINT IF EXISTS fk_c74f2195a76ed395');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT fk_c74f2195a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}

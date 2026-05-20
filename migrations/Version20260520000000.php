<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand plant_event.ip_address column to 64 chars (HMAC-SHA256 pseudonymized IP)';
    }

    public function up(Schema $schema): void
    {
        // HMAC-SHA256 produces 64 hex chars — old column was 45 (IPv6 raw string)
        $this->addSql('ALTER TABLE plant_event ALTER COLUMN ip_address TYPE VARCHAR(64)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plant_event ALTER COLUMN ip_address TYPE VARCHAR(45) USING SUBSTRING(ip_address, 1, 45)');
    }
}

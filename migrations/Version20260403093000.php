<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure legacy plot and crop_activity tables are absent on older environments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS crop_activity');
        $this->addSql('DROP TABLE IF EXISTS plot');
    }

    public function down(Schema $schema): void
    {
        // No-op on purpose: legacy tables stay removed and are not recreated.
    }
}

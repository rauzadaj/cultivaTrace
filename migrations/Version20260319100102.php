<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319100102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sensor ADD tenant_id UUID NOT NULL');
        $this->addSql('ALTER TABLE sensor ADD type VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE sensor ADD device_id VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE sensor ADD protocol VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE sensor ADD status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE sensor ADD last_seen TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE sensor ADD thresholds JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sensor DROP tenant_id');
        $this->addSql('ALTER TABLE sensor DROP type');
        $this->addSql('ALTER TABLE sensor DROP device_id');
        $this->addSql('ALTER TABLE sensor DROP protocol');
        $this->addSql('ALTER TABLE sensor DROP status');
        $this->addSql('ALTER TABLE sensor DROP last_seen');
        $this->addSql('ALTER TABLE sensor DROP thresholds');
    }
}

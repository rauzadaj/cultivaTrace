<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename subscription plan values: starter→growth, business→scale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE organization SET plan = 'growth' WHERE plan = 'starter'");
        $this->addSql("UPDATE organization SET plan = 'scale' WHERE plan = 'business'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE organization SET plan = 'starter' WHERE plan = 'growth'");
        $this->addSql("UPDATE organization SET plan = 'business' WHERE plan = 'scale'");
    }
}

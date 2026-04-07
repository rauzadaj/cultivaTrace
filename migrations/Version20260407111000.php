<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407111000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce non-null organization membership for users.';
    }

    public function up(Schema $schema): void
    {
        $orphanedUsers = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM "user" WHERE organization_id IS NULL');
        $this->abortIf($orphanedUsers > 0, sprintf(
            'Cannot enforce NOT NULL on user.organization_id: %d users are missing an organization.',
            $orphanedUsers,
        ));

        $this->addSql('ALTER TABLE "user" ALTER organization_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ALTER organization_id DROP NOT NULL');
    }
}

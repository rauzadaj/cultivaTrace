<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Map room.type to PHP enum RoomType (no SQL schema change required)';
    }

    public function up(Schema $schema): void
    {
    }

    public function down(Schema $schema): void
    {
    }
}

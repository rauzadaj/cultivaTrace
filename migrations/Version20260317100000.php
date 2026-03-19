<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add organization relation and primary role column to user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = 'user' AND column_name = 'organization_id'
                ) THEN
                    ALTER TABLE "user" ADD organization_id UUID DEFAULT NULL;
                END IF;
            END
            $$;
        SQL);

        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = 'user' AND column_name = 'role'
                ) THEN
                    ALTER TABLE "user" ADD role VARCHAR(50) NOT NULL DEFAULT 'ROLE_OPERATOR';
                END IF;
            END
            $$;
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_8D93D64932C8A3DE ON "user" (organization_id)');

        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE lower(conname) = lower('FK_8D93D64932C8A3DE')
                ) THEN
                    ALTER TABLE "user"
                        ADD CONSTRAINT FK_8D93D64932C8A3DE
                        FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE INITIALLY IMMEDIATE;
                END IF;
            END
            $$;
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D64932C8A3DE');
        $this->addSql('DROP INDEX IDX_8D93D64932C8A3DE');
        $this->addSql('ALTER TABLE "user" DROP organization_id');
        $this->addSql('ALTER TABLE "user" DROP role');
    }
}

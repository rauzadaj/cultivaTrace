<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remap legacy user roles to the new organization role hierarchy before deploying RBAC code.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE "user"
            SET role = CASE role
                WHEN 'ROLE_ADMIN' THEN 'ROLE_ORG_ADMIN'
                WHEN 'ROLE_MANAGER' THEN 'ROLE_ORG_ADMIN'
                WHEN 'ROLE_OPERATOR' THEN 'ROLE_ORG_USER'
                ELSE role
            END
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE "user"
            SET roles = REPLACE(
                REPLACE(
                    REPLACE(roles::text, '"ROLE_ADMIN"', '"ROLE_ORG_ADMIN"'),
                    '"ROLE_MANAGER"',
                    '"ROLE_ORG_ADMIN"'
                ),
                '"ROLE_OPERATOR"',
                '"ROLE_ORG_USER"'
            )::json
        SQL);

        $this->addSql('ALTER TABLE "user" ALTER role SET DEFAULT \'ROLE_ORG_USER\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE "user"
            SET role = CASE role
                WHEN 'ROLE_ORG_ADMIN' THEN 'ROLE_ADMIN'
                WHEN 'ROLE_ORG_USER' THEN 'ROLE_OPERATOR'
                ELSE role
            END
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE "user"
            SET roles = REPLACE(
                REPLACE(
                    roles::text,
                    '"ROLE_ORG_ADMIN"',
                    '"ROLE_ADMIN"'
                ),
                '"ROLE_ORG_USER"',
                '"ROLE_OPERATOR"'
            )::json
        SQL);

        $this->addSql('ALTER TABLE "user" ALTER role SET DEFAULT \'ROLE_OPERATOR\'');
    }
}

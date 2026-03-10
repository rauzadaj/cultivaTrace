<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260310190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Protect journal_entry from update and delete operations at the PostgreSQL layer.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration can only be executed safely on PostgreSQL.');

        $this->addSql(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_journal_entry_mutation()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'journal_entry is append-only; % operations are forbidden', TG_OP;
END;
$$ LANGUAGE plpgsql;
SQL);
        $this->addSql('CREATE TRIGGER trg_journal_entry_append_only BEFORE UPDATE OR DELETE ON journal_entry FOR EACH ROW EXECUTE FUNCTION prevent_journal_entry_mutation()');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('postgresql' !== $this->connection->getDatabasePlatform()->getName(), 'This migration can only be executed safely on PostgreSQL.');

        $this->addSql('DROP TRIGGER IF EXISTS trg_journal_entry_append_only ON journal_entry');
        $this->addSql('DROP FUNCTION IF EXISTS prevent_journal_entry_mutation()');
    }
}

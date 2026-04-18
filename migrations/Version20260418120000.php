<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Protect plant_event from update and delete operations at the PostgreSQL layer.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_plant_event_append_only ON plant_event');
        $this->addSql('DROP FUNCTION IF EXISTS prevent_plant_event_mutation()');
        $this->addSql(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_plant_event_mutation()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'plant_event is append-only; % operations are forbidden', TG_OP;
END;
$$ LANGUAGE plpgsql;
SQL
);
        $this->addSql('CREATE TRIGGER trg_plant_event_append_only BEFORE UPDATE OR DELETE ON plant_event FOR EACH ROW EXECUTE FUNCTION prevent_plant_event_mutation()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_plant_event_append_only ON plant_event');
        $this->addSql('DROP FUNCTION IF EXISTS prevent_plant_event_mutation()');
    }
}

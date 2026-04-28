<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove duplicated user role column and normalize roles array to organization roles only.';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, roles, role FROM "user"');

        foreach ($rows as $row) {
            $roles = $this->normalizeRoles($row['roles'] ?? null, $row['role'] ?? null);

            $this->addSql(
                'UPDATE "user" SET roles = :roles WHERE id = :id',
                [
                    'roles' => json_encode($roles, JSON_THROW_ON_ERROR),
                    'id' => (int) $row['id'],
                ],
                [
                    'roles' => ParameterType::STRING,
                    'id' => ParameterType::INTEGER,
                ],
            );
        }

        $this->addSql('ALTER TABLE "user" DROP role');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD role VARCHAR(50) DEFAULT \'ROLE_ORG_USER\' NOT NULL');

        $rows = $this->connection->fetchAllAssociative('SELECT id, roles FROM "user"');

        foreach ($rows as $row) {
            $primaryRole = $this->resolvePrimaryRole($row['roles'] ?? null);

            $this->addSql(
                'UPDATE "user" SET role = :role WHERE id = :id',
                [
                    'role' => $primaryRole,
                    'id' => (int) $row['id'],
                ],
                [
                    'role' => ParameterType::STRING,
                    'id' => ParameterType::INTEGER,
                ],
            );
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeRoles(mixed $rolesValue, mixed $legacyRoleValue): array
    {
        $roles = $this->decodeRoles($rolesValue);

        if (is_string($legacyRoleValue) && trim($legacyRoleValue) !== '') {
            $roles[] = trim($legacyRoleValue);
        }

        $normalized = [];
        foreach ($roles as $role) {
            $mappedRole = match ($role) {
                'ROLE_ADMIN', 'ROLE_MANAGER' => 'ROLE_ORG_ADMIN',
                'ROLE_OPERATOR', 'ROLE_USER' => 'ROLE_ORG_USER',
                default => $role,
            };

            if (!in_array($mappedRole, ['ROLE_SUPER_ADMIN', 'ROLE_ORG_ADMIN', 'ROLE_ORG_USER', 'ROLE_API'], true)) {
                continue;
            }

            $normalized[] = $mappedRole;
        }

        $normalized = array_values(array_unique($normalized));

        return $normalized !== [] ? $normalized : ['ROLE_ORG_USER'];
    }

    private function resolvePrimaryRole(mixed $rolesValue): string
    {
        $roles = $this->decodeRoles($rolesValue);

        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ORG_ADMIN', 'ROLE_ORG_USER', 'ROLE_API'] as $candidate) {
            if (in_array($candidate, $roles, true)) {
                return $candidate;
            }
        }

        return 'ROLE_ORG_USER';
    }

    /**
     * @return list<string>
     */
    private function decodeRoles(mixed $rolesValue): array
    {
        if (is_array($rolesValue)) {
            return array_values(array_filter(array_map('strval', $rolesValue), static fn (string $role): bool => $role !== ''));
        }

        if (!is_string($rolesValue) || trim($rolesValue) === '') {
            return [];
        }

        try {
            $decoded = json_decode($rolesValue, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $decoded), static fn (string $role): bool => $role !== ''));
    }
}

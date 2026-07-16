<?php
namespace Core\Sys;

final class MigrationService
{
    private const LEGACY_BASELINE_MAX = '20260311_0001_mscontroller_bl_metadata';
    private string $upgradeDir;

    public function __construct(
        private array $config,
        private Database $db,
        private Logger $logger,
    ) {
        $this->upgradeDir = rtrim((string)$config['dir']['rad'], '/') . '/upgrades';
        $created = $this->ensureLedger();
        if ($created) {
            $this->adoptVerifiedLegacyBaseline();
        }
    }

    public function status(): array
    {
        $definitions = $this->definitions();
        $rows = $this->db->query('SELECT * FROM s_migration');
        $ledger = [];
        foreach ($rows as $row) {
            $ledger[(string)$row['migration_id']] = $row;
        }
        $result = [];
        foreach ($definitions as $definition) {
            $row = $ledger[$definition['id']] ?? [];
            $storedChecksum = (string)($row['checksum'] ?? '');
            $result[] = [
                'id' => $definition['id'],
                'description' => $definition['description'],
                'file' => $definition['file'],
                'checksum' => $definition['checksum'],
                'applied' => ($row['status'] ?? '') === 'applied',
                'status' => $row['status'] ?? 'pending',
                'executed_at' => $row['finished_at'] ?? null,
                'locked' => false,
                'has_rollback' => is_callable($definition['rollback']),
                'checksum_valid' => $storedChecksum === '' || hash_equals($storedChecksum, $definition['checksum']),
            ];
        }
        usort($result, static fn (array $a, array $b): int => strcmp($b['id'], $a['id']));
        return $result;
    }

    public function applyPending(?callable $output = null): array
    {
        return $this->withLock(function () use ($output): array {
            $applied = [];
            foreach ($this->definitions() as $definition) {
                $row = $this->ledgerRow($definition['id']);
                if (($row['status'] ?? '') === 'applied') {
                    $this->assertChecksum($definition, $row);
                    continue;
                }
                $this->emit($output, sprintf('Running %s (%s)', $definition['id'], $definition['description']));
                $this->apply($definition);
                $applied[] = $definition['id'];
                $this->emit($output, sprintf('Completed %s', $definition['id']));
            }
            if ($applied === []) {
                $this->emit($output, 'No pending upgrades. Database is up to date.');
            }
            return $applied;
        });
    }

    public function rollback(string $migrationId): array
    {
        return $this->withLock(function () use ($migrationId): array {
            $definitions = $this->definitions();
            $target = $definitions[$migrationId] ?? null;
            if ($target === null) {
                throw new \RuntimeException('Migration not found: ' . $migrationId);
            }
            if (!is_callable($target['rollback'])) {
                throw new \RuntimeException('Migration does not define a rollback handler: ' . $migrationId);
            }
            $latest = $this->db->query(
                "SELECT migration_id FROM s_migration WHERE status = 'applied' ORDER BY finished_at DESC, migration_id DESC LIMIT 1"
            );
            if (($latest[0]['migration_id'] ?? null) !== $migrationId) {
                throw new \RuntimeException('Only the latest applied migration may be rolled back.');
            }
            $row = $this->ledgerRow($migrationId);
            $this->assertChecksum($target, $row);
            call_user_func($target['rollback'], $this->db, $this->logger, $this->config);
            $this->db->query(
                "UPDATE s_migration SET status = 'rolled_back', finished_at = NOW(), error_message = NULL WHERE migration_id = :id",
                [':id' => $migrationId]
            );
            return ['message' => 'Rollback completed for ' . $migrationId . '.', 'output' => []];
        });
    }

    private function apply(array $definition): void
    {
        $this->db->query(
            "INSERT INTO s_migration
                (migration_id, checksum, release_version, status, started_at, finished_at, applied_by, error_message)
             VALUES (:id, :checksum, :release, 'running', NOW(), NULL, :actor, NULL)
             ON DUPLICATE KEY UPDATE checksum = :checksum_update, status = 'running', started_at = NOW(),
                finished_at = NULL, applied_by = :actor_update, error_message = NULL",
            [
                ':id' => $definition['id'],
                ':checksum' => $definition['checksum'],
                ':release' => defined('BATOI_RAD_VERSION') ? BATOI_RAD_VERSION : '1.0.0-dev',
                ':actor' => $this->actor(),
                ':checksum_update' => $definition['checksum'],
                ':actor_update' => $this->actor(),
            ]
        );
        try {
            call_user_func($definition['run'], $this->db, $this->logger, $this->config);
            $this->db->query(
                "UPDATE s_migration SET status = 'applied', finished_at = NOW(), error_message = NULL WHERE migration_id = :id",
                [':id' => $definition['id']]
            );
        } catch (\Throwable $exception) {
            $this->db->query(
                "UPDATE s_migration SET status = 'failed', finished_at = NOW(), error_message = :error WHERE migration_id = :id",
                [':error' => mb_substr($exception->getMessage(), 0, 2000), ':id' => $definition['id']]
            );
            throw $exception;
        }
    }

    private function definitions(): array
    {
        $files = glob($this->upgradeDir . '/*.php') ?: [];
        sort($files);
        $definitions = [];
        foreach ($files as $file) {
            $definition = include $file;
            if (!is_array($definition) || empty($definition['id']) || !is_callable($definition['run'] ?? null)) {
                throw new \RuntimeException('Invalid migration definition: ' . basename($file));
            }
            $id = (string)$definition['id'];
            if (isset($definitions[$id])) {
                throw new \RuntimeException('Duplicate migration ID: ' . $id);
            }
            $definitions[$id] = [
                'id' => $id,
                'description' => (string)($definition['description'] ?? 'No description'),
                'run' => $definition['run'],
                'rollback' => $definition['rollback'] ?? null,
                'file' => $file,
                'checksum' => hash_file('sha256', $file),
            ];
        }
        return $definitions;
    }

    private function ensureLedger(): bool
    {
        $exists = $this->db->query(
            "SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 's_migration'"
        );
        if ((int)($exists[0]['total'] ?? 0) === 1) {
            return false;
        }
        $this->db->query("CREATE TABLE `s_migration` (
            `migration_id` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
            `checksum` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
            `release_version` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
            `status` enum('baseline','running','applied','failed','rolled_back') COLLATE utf8mb4_unicode_ci NOT NULL,
            `started_at` datetime DEFAULT NULL,
            `finished_at` datetime DEFAULT NULL,
            `applied_by` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `error_message` text COLLATE utf8mb4_unicode_ci,
            PRIMARY KEY (`migration_id`),
            KEY `idx_migration_status` (`status`),
            KEY `idx_migration_finished` (`finished_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    }

    private function adoptVerifiedLegacyBaseline(): void
    {
        $checks = [
            ['s_branch', 'id'],
            ['s_permission_binding', 's_object_type'],
            ['s_mscontroller', 's_source_file'],
        ];
        foreach ($checks as [$table, $column]) {
            $rows = $this->db->query(
                'SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
                [':table' => $table, ':column' => $column]
            );
            if ((int)($rows[0]['total'] ?? 0) !== 1) {
                return;
            }
        }
        foreach ($this->definitions() as $definition) {
            if (strcmp($definition['id'], self::LEGACY_BASELINE_MAX) > 0) {
                continue;
            }
            $this->db->query(
                "INSERT INTO s_migration
                    (migration_id, checksum, release_version, status, started_at, finished_at, applied_by, error_message)
                 VALUES (:id, :checksum, 'legacy', 'applied', NOW(), NOW(), 'legacy-baseline', NULL)",
                [':id' => $definition['id'], ':checksum' => $definition['checksum']]
            );
        }
    }

    private function ledgerRow(string $id): array
    {
        $rows = $this->db->query('SELECT * FROM s_migration WHERE migration_id = :id LIMIT 1', [':id' => $id]);
        return $rows[0] ?? [];
    }

    private function assertChecksum(array $definition, array $row): void
    {
        $stored = (string)($row['checksum'] ?? '');
        if ($stored !== '' && !hash_equals($stored, $definition['checksum'])) {
            throw new \RuntimeException('Applied migration checksum changed: ' . $definition['id']);
        }
    }

    private function withLock(callable $callback): mixed
    {
        $rows = $this->db->query("SELECT GET_LOCK('batoi_rad_migrations', 10) AS acquired");
        if ((int)($rows[0]['acquired'] ?? 0) !== 1) {
            throw new \RuntimeException('Unable to acquire the RAD migration lock.');
        }
        try {
            return $callback();
        } finally {
            try {
                $this->db->query("SELECT RELEASE_LOCK('batoi_rad_migrations')");
            } catch (\Throwable) {
            }
        }
    }

    private function actor(): string
    {
        $entityId = (int)($_SESSION['entity_id'] ?? 0);
        return $entityId > 0 ? 'entity:' . $entityId : 'cli';
    }

    private function emit(?callable $output, string $message): void
    {
        if ($output !== null) {
            $output($message);
        }
    }
}

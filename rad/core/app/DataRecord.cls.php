<?php
namespace Core\App;

use InvalidArgumentException;

/**
 * DataRecord service to read application (a_*) tables and version history.
 * Intended for app developers to build data viewers outside RAD Admin.
 */
class DataRecord {
    private $db;
    private $cacheService;

    public function __construct($db, array $config = []) {
        $this->db = $db;
        $this->cacheService = new \Core\Sys\CacheService($config);
    }

    /**
     * List a_* tables available.
     *
     * @return array Table names (strings)
     */
    public function listTables(): array {
        $rows = $this->db->query("SHOW TABLES LIKE 'a\\_%'");
        $tables = [];
        foreach ($rows as $row) {
            $tables[] = array_values($row)[0] ?? null;
        }
        return array_values(array_filter($tables));
    }

    /**
     * Get records from a table with optional filters, order, limit, offset.
     * $filters is an associative array of column => value (equality).
     *
     * @param string $table a_* table name
     * @param array $filters Column => value equality filters
     * @param int $limit Max rows
     * @param int $offset Offset rows
     * @param array $order Column => direction map (ASC|DESC)
     * @return array Result rows
     *
     * @throws InvalidArgumentException when table is not prefixed with a_
     */
    public function list(string $table, array $filters = [], int $limit = 100, int $offset = 0, array $order = [], array $options = []): array {
        $table = $this->assertAppTable($table);
        $cache = $options['cache'] ?? [];
        $cacheKey = $this->resolveDmCacheKey($cache);
        $cacheVariant = $this->buildDmCacheVariant($filters, $order, $limit, $offset, $cache);
        $cacheTtl = isset($cache['ttl']) ? max(0, (int)$cache['ttl']) : $this->cacheService->defaultTtl('dm');
        if ($cacheKey && $this->cacheService->isEnabled() && $cacheTtl > 0) {
            $hit = $this->cacheService->get($cacheKey['ms_name'], 'dm', $cacheKey['dm_id'], $cacheVariant);
            if (!empty($hit['hit']) && is_array($hit['payload'])) {
                return $hit['payload'];
            }
        }
        $sql = "SELECT * FROM {$table}";
        $params = [];
        if (!empty($filters)) {
            $clauses = [];
            foreach ($filters as $col => $val) {
                $paramKey = ':p_' . preg_replace('/[^a-zA-Z0-9_]/', '', $col);
                $clauses[] = "{$col} = {$paramKey}";
                $params[$paramKey] = $val;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        if (!empty($order)) {
            $parts = [];
            foreach ($order as $col => $dir) {
                $d = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
                $parts[] = "{$col} {$d}";
            }
            if ($parts) {
                $sql .= ' ORDER BY ' . implode(', ', $parts);
            }
        }
        $sql .= ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);
        $rows = $this->db->query($sql, $params);
        if ($cacheKey && $this->cacheService->isEnabled() && $cacheTtl > 0) {
            $this->cacheService->set(
                $cacheKey['ms_name'],
                'dm',
                $cacheKey['dm_id'],
                $cacheVariant,
                $rows,
                $cacheTtl,
                [
                    'table' => $table,
                    'space_id' => $cache['space_id'] ?? null,
                ]
            );
        }
        return $rows;
    }

    /**
     * Get a single record by primary id.
     *
     * @param string $table a_* table name
     * @param int $id Primary id
     * @return array|null Record or null
     *
     * @throws InvalidArgumentException when table is not prefixed with a_
     */
    public function get(string $table, int $id, array $options = []): ?array {
        $table = $this->assertAppTable($table);
        $cache = $options['cache'] ?? [];
        $cacheKey = $this->resolveDmCacheKey($cache);
        $cacheVariant = $this->buildDmCacheVariant(['id' => $id], [], 1, 0, $cache);
        $cacheTtl = isset($cache['ttl']) ? max(0, (int)$cache['ttl']) : $this->cacheService->defaultTtl('dm');
        if ($cacheKey && $this->cacheService->isEnabled() && $cacheTtl > 0) {
            $hit = $this->cacheService->get($cacheKey['ms_name'], 'dm', $cacheKey['dm_id'], $cacheVariant);
            if (!empty($hit['hit']) && is_array($hit['payload'])) {
                return $hit['payload'][0] ?? null;
            }
        }
        $rows = $this->db->select($table, ['id' => $id], true);
        if ($cacheKey && $this->cacheService->isEnabled() && $cacheTtl > 0) {
            $this->cacheService->set(
                $cacheKey['ms_name'],
                'dm',
                $cacheKey['dm_id'],
                $cacheVariant,
                $rows,
                $cacheTtl,
                [
                    'table' => $table,
                    'space_id' => $cache['space_id'] ?? null,
                ]
            );
        }
        return $rows[0] ?? null;
    }

    /**
     * Get version history for a record from s_version_history.
     *
     * @param string $table a_* table name
     * @param int $recordId Record id
     * @param int $limit Max versions to return
     * @return array Version rows
     *
     * @throws InvalidArgumentException when table is not prefixed with a_
     */
    public function versionHistory(string $table, int $recordId, int $limit = 20): array {
        $table = $this->assertAppTable($table);
        return $this->db->query(
            "SELECT * FROM s_version_history
             WHERE s_db_table = :tbl AND s_data_record_id = :rid
             ORDER BY s_version_number DESC, id DESC
             LIMIT {$limit}",
            [':tbl' => $table, ':rid' => $recordId]
        );
    }

    /**
     * Get the latest version snapshot for a record.
     */
    public function latestVersion(string $table, int $recordId): ?array {
        $history = $this->versionHistory($table, $recordId, 1);
        return $history[0] ?? null;
    }

    private function assertAppTable(string $table): string {
        $table = trim($table);
        if (!preg_match('/^a_[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Only a_* tables are permitted');
        }
        return $table;
    }

    private function resolveDmCacheKey(array $cache): ?array {
        $msName = trim((string)($cache['ms_name'] ?? ''));
        $dmId = trim((string)($cache['dm_id'] ?? ''));
        if ($msName === '' || $dmId === '') {
            return null;
        }
        return ['ms_name' => $msName, 'dm_id' => $dmId];
    }

    private function buildDmCacheVariant(array $filters, array $order, int $limit, int $offset, array $cache): string {
        $variant = [
            'filters' => $filters,
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset,
        ];
        if (isset($cache['space_id'])) {
            $variant['space_id'] = $cache['space_id'];
        }
        if (isset($cache['user_id'])) {
            $variant['user_id'] = $cache['user_id'];
        }
        return json_encode($variant, JSON_UNESCAPED_SLASHES);
    }
}

<?php
namespace Core\Sys;

use DateTime;

class HomeDashboardService {
    private $config;
    private $workspaceService;
    private $upgradeDir;
    private $db;

    public function __construct(array $config, WorkspaceService $workspaceService, $db = null) {
        $this->config = $config;
        $this->workspaceService = $workspaceService;
        $this->upgradeDir = rtrim($config['dir']['rad'] ?? '', '/') . '/upgrades';
        $this->db = $db;
    }

    public function getDashboardData(bool $forceRefresh = false, string $scope = 'all'): array {
        $cache = $this->getCachedDashboard();
        if ($scope === 'logs' && $cache !== null) {
            $latestDay = $this->findLatestLogDay();
            $logs = $latestDay ? $this->loadDailyLogs($latestDay) : ['access' => [], 'error' => [], 'sql' => []];
            $cache['metrics'] = $this->buildMetrics($latestDay);
            $cache['recent_activity'] = $this->buildRecentActivity($logs);
            $cache['daily_series'] = $this->buildDailySeries();
            $cache['log_health'] = $this->buildLogHealth($latestDay);
            $cache['generated_at'] = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $cache['cached'] = false;
            $cache['cache_ttl'] = $this->getCacheTtl();
            $this->storeCachedDashboard($cache);
            return $cache;
        }
        if (!$forceRefresh && $cache !== null) {
            return $cache;
        }

        $latestDay = $this->findLatestLogDay();
        $logs = $latestDay ? $this->loadDailyLogs($latestDay) : ['access' => [], 'error' => [], 'sql' => []];
        $metrics = $this->buildMetrics($latestDay);
        $recentActivity = $this->buildRecentActivity($logs);
        $dailySeries = $this->buildDailySeries();
        $pendingUpgrades = $this->loadPendingUpgrades();
        $acSummary = $this->buildAccessControlSummary();
        $bindingIntegrity = $this->buildBindingIntegrity();
        $logHealth = $this->buildLogHealth($latestDay);

        $workspaceSummaries = $this->workspaceService->listSummaries();
        $topWorkspaces = array_slice($workspaceSummaries, 0, 5);
        $topWorkspaces = $this->attachOwnerInfo($topWorkspaces);

        $data = [
            'metrics' => $metrics,
            'recent_activity' => $recentActivity,
            'top_workspaces' => $topWorkspaces,
            'log_date' => $latestDay,
            'daily_series' => $dailySeries,
            'pending_upgrades' => $pendingUpgrades,
            'ac_summary' => $acSummary,
            'binding_integrity' => $bindingIntegrity,
            'log_health' => $logHealth,
            'generated_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            'cached' => false,
            'cache_ttl' => $this->getCacheTtl(),
        ];
        $this->storeCachedDashboard($data);
        return $data;
    }

    private function findLatestLogDay(): ?array {
        $base = rtrim($this->config['dir']['log'] ?? '', '/');
        if ($base === '' || !is_dir($base)) {
            return null;
        }
        $years = glob($base . '/*', GLOB_ONLYDIR);
        rsort($years);
        foreach ($years as $yearPath) {
            $months = glob($yearPath . '/*', GLOB_ONLYDIR);
            rsort($months);
            foreach ($months as $monthPath) {
                $days = glob($monthPath . '/*', GLOB_ONLYDIR);
                rsort($days);
                foreach ($days as $dayPath) {
                    if (!$this->dayHasLogs($dayPath)) {
                        continue;
                    }
                    $day = basename($dayPath);
                    $month = basename($monthPath);
                    $year = basename($yearPath);
                    return [
                        'year' => $year,
                        'month' => $month,
                        'day' => $day,
                        'path' => $dayPath,
                    ];
                }
            }
        }
        return null;
    }

    private function getCachePath(): ?string {
        $dataDir = rtrim($this->config['dir']['data'] ?? '', '/');
        if ($dataDir === '') {
            return null;
        }
        $dir = $dataDir . '/temp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/home-dashboard.json';
    }

    private function getCachedDashboard(): ?array {
        $cachePath = $this->getCachePath();
        if ($cachePath === null || !is_file($cachePath)) {
            return null;
        }
        $ttl = $this->getCacheTtl();
        $ttl = $ttl < 30 ? 30 : $ttl;
        $age = time() - filemtime($cachePath);
        if ($age > $ttl) {
            return null;
        }
        $raw = file_get_contents($cachePath);
        $decoded = json_decode($raw ?: '', true);
        if (!is_array($decoded)) {
            return null;
        }
        $decoded['cached'] = true;
        $decoded['cache_ttl'] = $this->getCacheTtl();
        return $decoded;
    }

    private function storeCachedDashboard(array $data): void {
        $cachePath = $this->getCachePath();
        if ($cachePath === null) {
            return;
        }
        file_put_contents($cachePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function getCacheTtl(): int {
        $ttl = (int)($this->config['sys']['home_dashboard_cache_ttl'] ?? 0);
        if ($ttl > 0) {
            return $ttl;
        }
        if (!$this->db) {
            return 120;
        }
        try {
            $rows = $this->db->select('s_config', ['s_config_handle' => 'home_dashboard_cache_ttl'], true);
            if (!empty($rows[0]['s_config_value'])) {
                $ttl = (int)$rows[0]['s_config_value'];
                return $ttl > 0 ? $ttl : 120;
            }
        } catch (\Throwable $e) {
            return 120;
        }
        return 120;
    }

    private function buildLogHealth(?array $latestDay): array {
        $base = rtrim($this->config['dir']['log'] ?? '', '/');
        $health = [
            'log_dir' => $base,
            'log_dir_exists' => $base !== '' && is_dir($base),
            'latest_day_found' => $latestDay !== null,
            'access_log_exists' => false,
            'error_log_exists' => false,
            'sql_log_exists' => false,
        ];
        if ($latestDay) {
            $health['access_log_exists'] = $this->dayHasLogKind($latestDay['path'], 'access');
            $health['error_log_exists'] = $this->dayHasLogKind($latestDay['path'], 'error');
            $health['sql_log_exists'] = $this->dayHasLogKind($latestDay['path'], 'sql');
        }
        return $health;
    }

    private function loadDailyLogs(array $day, int $maxLines = 500): array {
        $kinds = ['access', 'error', 'sql'];
        $result = [];
        foreach ($kinds as $kind) {
            $files = $this->getLogFilesForDay($day['path'], $kind);
            $maxPerFile = max(50, (int)ceil($maxLines / max(1, count($files))));
            $lines = [];
            foreach ($files as $file) {
                $lines = array_merge($lines, $this->readLogTail($file, $maxPerFile));
            }
            $result[$kind] = $lines;
        }
        return $result;
    }

    private function buildMetrics(?array $day): array {
        if (!$day) {
            return [
                'counts' => ['access' => 0, 'error' => 0, 'sql' => 0],
                'date' => null,
            ];
        }

        $counts = [
            'access' => $this->countLogLinesForDay($day['path'], 'access'),
            'error' => $this->countLogLinesForDay($day['path'], 'error'),
            'sql' => $this->countLogLinesForDay($day['path'], 'sql'),
        ];

        $readableDate = sprintf('%s-%s-%s', $day['year'], $day['month'], $day['day']);

        return [
            'counts' => $counts,
            'date' => $readableDate,
        ];
    }

    private function buildRecentActivity(array $logs): array {
        $recentErrors = array_slice(array_reverse($logs['error']), 0, 5);
        $recentErrorEntries = array_map([$this, 'parseLogLine'], $recentErrors);
        $recentRequests = array_slice(array_reverse($logs['access']), 0, 5);
        $recentRequestEntries = array_map([$this, 'parseLogLine'], $recentRequests);
        return [
            'errors' => array_filter($recentErrorEntries),
            'access' => array_filter($recentRequestEntries),
        ];
    }

    private function parseLogLine(string $line): ?array {
        if (!preg_match('/^\[(.*?)\]:\s*(.*)$/', trim($line), $matches)) {
            return null;
        }
        return [
            'timestamp' => $matches[1],
            'payload' => $matches[2],
        ];
    }

    private function dayHasLogs(string $dayPath): bool {
        foreach (['access', 'error', 'sql'] as $kind) {
            if ($this->dayHasLogKind($dayPath, $kind)) {
                return true;
            }
        }
        return false;
    }

    private function dayHasLogKind(string $dayPath, string $kind): bool {
        if (is_file($dayPath . '/' . $kind . '.log')) {
            return true;
        }
        $hourFiles = glob($dayPath . '/*/' . $kind . '.log');
        return !empty($hourFiles);
    }

    private function getLogFilesForDay(string $dayPath, string $kind): array {
        $files = glob($dayPath . '/*/' . $kind . '.log') ?: [];
        $legacy = $dayPath . '/' . $kind . '.log';
        if (is_file($legacy)) {
            $files[] = $legacy;
        }
        return $files;
    }

    private function countLogLinesForDay(string $dayPath, string $kind): int {
        $files = $this->getLogFilesForDay($dayPath, $kind);
        $count = 0;
        foreach ($files as $file) {
            $count += $this->countLogLines($file);
        }
        return $count;
    }
    private function buildDailySeries(int $limit = 14): array {
        $base = rtrim($this->config['dir']['log'] ?? '', '/');
        if ($base === '' || !is_dir($base)) {
            return [];
        }
        $series = [];
        $years = glob($base . '/*', GLOB_ONLYDIR);
        rsort($years);
        foreach ($years as $yearPath) {
            $months = glob($yearPath . '/*', GLOB_ONLYDIR);
            rsort($months);
            foreach ($months as $monthPath) {
                $days = glob($monthPath . '/*', GLOB_ONLYDIR);
                rsort($days);
                foreach ($days as $dayPath) {
                    $dateLabel = sprintf('%s-%s-%s',
                        basename($yearPath),
                        basename($monthPath),
                        basename($dayPath)
                    );
                    $counts = ['access' => 0, 'error' => 0, 'sql' => 0];
                    foreach (array_keys($counts) as $kind) {
                        $counts[$kind] = $this->countLogLinesForDay($dayPath, $kind);
                    }
                    $series[] = [
                        'date' => $dateLabel,
                        'access' => $counts['access'],
                        'error' => $counts['error'],
                        'sql' => $counts['sql'],
                    ];
                    if (count($series) >= $limit) {
                        break 3;
                    }
                }
            }
        }
        return $series;
    }

    private function loadPendingUpgrades(): array {
        if (!is_dir($this->upgradeDir)) {
            return [];
        }
        $files = glob($this->upgradeDir . '/*.php');
        sort($files);
        $pending = [];
        foreach ($files as $filePath) {
            $meta = include $filePath;
            if (!is_array($meta) || empty($meta['id'])) {
                continue;
            }
            $pending[] = [
                'id' => $meta['id'],
                'description' => $meta['description'] ?? '',
            ];
            if (count($pending) >= 3) {
                break;
            }
        }
        return $pending;
    }

    private function buildAccessControlSummary(): array {
        if (!$this->db) {
            return [
                'users_missing_primary' => 0,
                'private_ms_unbound' => 0,
                'private_route_unbound' => 0,
                'memberships_missing_role' => 0,
                'memberships_ms_missing_ms' => 0,
                'roles_missing_default_route' => 0,
            ];
        }

        $missingPrimaryRow = $this->db->query(
            "SELECT COUNT(*) AS c
             FROM s_entity
             WHERE s_type = 'U'
               AND livestatus = '1'
               AND (s_nonsaas_role_id IS NULL OR s_nonsaas_role_id = 0)"
        );
        $missingPrimary = (int)($missingPrimaryRow[0]['c'] ?? 0);

        $privateMsRow = $this->db->query(
            "SELECT COUNT(*) AS c
             FROM s_ms m
             LEFT JOIN s_permission_binding b
               ON b.s_object_type = 'ms'
              AND b.s_object_id = m.id
              AND b.livestatus != '0'
             WHERE m.livestatus = '1'
               AND (m.s_scope IS NULL OR LOWER(m.s_scope) <> 'global')
               AND b.id IS NULL"
        );
        $privateMsUnbound = (int)($privateMsRow[0]['c'] ?? 0);

        $privateRouteRow = $this->db->query(
            "SELECT COUNT(*) AS c
             FROM s_msroute r
             INNER JOIN s_ms m ON m.id = r.s_ms_id
             LEFT JOIN s_permission_binding b
               ON b.s_object_type = 'route'
              AND b.s_object_id = r.id
              AND b.livestatus != '0'
             WHERE r.livestatus = '1'
               AND m.livestatus = '1'
               AND (m.s_scope IS NULL OR LOWER(m.s_scope) <> 'global')
               AND b.id IS NULL"
        );
        $privateRouteUnbound = (int)($privateRouteRow[0]['c'] ?? 0);

        $missingRoleRow = $this->db->query(
            "SELECT COUNT(*) AS c
             FROM s_space_membership
             WHERE livestatus = '1'
               AND (s_role_id IS NULL OR s_role_id = 0)"
        );
        $membershipsMissingRole = (int)($missingRoleRow[0]['c'] ?? 0);

        $missingMsRow = $this->db->query(
            "SELECT COUNT(*) AS c
             FROM s_space_membership
             WHERE livestatus = '1'
               AND s_scope_level = 'ms'
               AND (s_ms_id IS NULL OR s_ms_id = 0)"
        );
        $membershipsMsMissingMs = (int)($missingMsRow[0]['c'] ?? 0);

        $missingDefaultRouteRow = $this->db->query(
            "SELECT COUNT(*) AS c
             FROM s_role
             WHERE livestatus = '1'
               AND (s_default_route_id IS NULL OR s_default_route_id = 0)"
        );
        $rolesMissingDefaultRoute = (int)($missingDefaultRouteRow[0]['c'] ?? 0);

        return [
            'users_missing_primary' => $missingPrimary,
            'private_ms_unbound' => $privateMsUnbound,
            'private_route_unbound' => $privateRouteUnbound,
            'memberships_missing_role' => $membershipsMissingRole,
            'memberships_ms_missing_ms' => $membershipsMsMissingMs,
            'roles_missing_default_route' => $rolesMissingDefaultRoute,
        ];
    }

    /**
     * Detect unbound routes/controllers (missing microservice association).
     * Returns counts and offending IDs for surfacing in RAD Admin home.
     */
    private function buildBindingIntegrity(): array {
        if (!$this->db) {
            return [
                'routes_unbound' => 0,
                'controllers_unbound' => 0,
                'route_ids' => [],
                'controller_ids' => [],
            ];
        }

        $routes = $this->db->query(
            "SELECT id FROM s_msroute
             WHERE (s_ms_id IS NULL OR s_ms_id = 0)
               AND livestatus = '1'"
        );
        $controllers = $this->db->query(
            "SELECT id FROM s_mscontroller
             WHERE (s_ms_id IS NULL OR s_ms_id = 0)
               AND livestatus = '1'"
        );

        return [
            'routes_unbound' => count($routes),
            'controllers_unbound' => count($controllers),
            'route_ids' => array_map(fn($r) => (int)$r['id'], $routes),
            'controller_ids' => array_map(fn($r) => (int)$r['id'], $controllers),
        ];
    }

    private function readLogTail(string $file, int $maxLines): array {
        if (!is_file($file) || $maxLines <= 0) {
            return [];
        }

        try {
            $log = new \SplFileObject($file, 'r');
        } catch (\RuntimeException $e) {
            return [];
        }

        $log->seek(PHP_INT_MAX);
        $lastLine = $log->key();
        $startLine = max($lastLine - $maxLines + 1, 0);
        $log->seek($startLine);

        $lines = [];
        while (!$log->eof()) {
            $line = trim((string)$log->fgets());
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, -1 * $maxLines);
        }

        return $lines;
    }

    private function countLogLines(string $file): int {
        if (!is_file($file)) {
            return 0;
        }
        try {
            $log = new \SplFileObject($file, 'r');
        } catch (\RuntimeException $e) {
            return 0;
        }

        $count = 0;
        while (!$log->eof()) {
            $log->current();
            $log->next();
            $count++;
        }

        return max($count - 1, 0);
    }

    private function attachOwnerInfo(array $workspaces): array {
        if (!$this->db || empty($workspaces)) {
            return $workspaces;
        }
        $ownerIds = [];
        foreach ($workspaces as $workspace) {
            if (!empty($workspace['s_owner_entity_id'])) {
                $ownerIds[] = (int)$workspace['s_owner_entity_id'];
            }
        }
        $ownerIds = array_values(array_unique(array_filter($ownerIds)));
        if (empty($ownerIds)) {
            return $workspaces;
        }
        $placeholders = [];
        $params = [];
        foreach ($ownerIds as $index => $id) {
            $placeholder = ':oid' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }
        $sql = sprintf(
            "SELECT id, s_name, s_identity FROM s_entity WHERE id IN (%s)",
            implode(',', $placeholders)
        );
        $rows = $this->db->query($sql, $params);
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = [
                'name' => $row['s_name'] ?? '',
                'identity' => $row['s_identity'] ?? '',
            ];
        }
        foreach ($workspaces as &$workspace) {
            $ownerId = (int)($workspace['s_owner_entity_id'] ?? 0);
            $owner = $map[$ownerId] ?? null;
            if ($owner) {
                $workspace['owner_name'] = $owner['name'];
                $workspace['owner_identity'] = $owner['identity'];
            }
        }
        unset($workspace);
        return $workspaces;
    }
}

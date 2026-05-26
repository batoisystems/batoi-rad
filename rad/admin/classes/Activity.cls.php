<?php
namespace RadAdmin;

use Core\Sys\TimeHelper;

class Activity {
    private $runData = [];
    private $db;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
    }

    public function view() {
        $this->runData['route']['h1'] = 'Activity Feed';
        $this->runData['route']['meta_title'] = 'Activity Feed';
        $this->runData['route']['breadcrumb'] = ['Engagement' => $this->runData['route']['rad_admin_url'] . '/home/view'];

        $this->handleIngestAction();

        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        $superAdmin = $this->isSuperAdmin();
        $filters = $this->resolveFilters($superAdmin);
        $pagination = $this->resolvePagination();

        $spaceOptions = $this->loadSpaces($superAdmin, $entityId);
        $activities = $this->fetchActivity($entityId, $filters, $spaceOptions, $superAdmin, $pagination);
        $metrics = $this->buildMetrics($activities, $spaceOptions, $filters);

        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['scope_options'] = $this->getScopeOptions($superAdmin);
        $this->runData['data']['space_options'] = $spaceOptions;
        $this->runData['data']['activities'] = $activities['rows'];
        $this->runData['data']['metrics'] = $metrics;
        $this->runData['data']['pagination'] = $activities['pagination'];
        $this->runData['data']['chart'] = $activities['chart'];
        $this->runData['data']['is_super_admin'] = $superAdmin;
        $this->runData['data']['activity_ingest_last_run'] = $this->getConfigValue('activity_ingest_last_run');
        $this->runData['data']['activity_ingest_last_range'] = $this->getConfigJson('activity_ingest_last_range');

        return $this->runData;
    }

    private function resolveFilters(bool $superAdmin): array {
        $scopeOptions = array_keys($this->getScopeOptions($superAdmin));
        $scope = $this->runData['request']->get['scope'] ?? 'my';
        $scope = in_array($scope, $scopeOptions, true) ? $scope : 'my';

        $spaceId = (int)($this->runData['request']->get['space_id'] ?? 0);
        $eventType = trim((string)($this->runData['request']->get['event_type'] ?? ''));
        $actorId = (int)($this->runData['request']->get['actor_id'] ?? 0);
        $fromDate = $this->sanitizeDate($this->runData['request']->get['from'] ?? '');
        $toDate = $this->sanitizeDate($this->runData['request']->get['to'] ?? '');
        $search = trim((string)($this->runData['request']->get['q'] ?? ''));

        if (!$superAdmin) {
            $actorId = 0;
        }

        return [
            'scope' => $scope,
            'space_id' => $spaceId,
            'event_type' => $eventType,
            'actor_id' => $actorId,
            'from' => $fromDate,
            'to' => $toDate,
            'q' => $search,
        ];
    }

    private function resolvePagination(): array {
        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $perPage = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($perPage === 0) {
            $perPage = $this->getProfilePerPage(25);
        } elseif ($this->isAllowedPerPage($perPage)) {
            $this->saveProfilePerPage($perPage);
        } else {
            $perPage = 25;
        }
        return ['page' => $page, 'per_page' => $perPage];
    }

    private function fetchActivity(int $entityId, array $filters, array $spaceOptions, bool $superAdmin, array $pagination): array {
        $where = "livestatus != '0'";
        $params = [];

        switch ($filters['scope']) {
            case 'workspace':
                $spaceId = $this->resolveWorkspaceScope($filters['space_id'], $spaceOptions);
                if ($spaceId > 0) {
                    $where .= " AND space_id = :activity_space";
                    $params[':activity_space'] = $spaceId;
                }
                break;
            case 'global':
                $where .= " AND space_id = 0";
                break;
            case 'system':
                if (!$superAdmin) {
                    $where .= " AND s_actor_id = :activity_actor";
                    $params[':activity_actor'] = $entityId;
                }
                break;
            default:
                $actorId = $superAdmin && $filters['actor_id'] > 0 ? $filters['actor_id'] : $entityId;
                $where .= " AND s_actor_id = :activity_actor";
                $params[':activity_actor'] = $actorId;
                break;
        }

        if ($superAdmin && $filters['actor_id'] > 0) {
            $where .= " AND s_actor_id = :activity_actor_override";
            $params[':activity_actor_override'] = $filters['actor_id'];
        }

        if ($filters['event_type'] !== '') {
            $where .= " AND (s_action = :activity_event OR s_object_type = :activity_event OR JSON_EXTRACT(COALESCE(s_payload, '{}'), '$.activity_event') = :activity_event OR JSON_EXTRACT(COALESCE(s_payload, '{}'), '$.event_type') = :activity_event)";
            $params[':activity_event'] = $filters['event_type'];
        }

        if (!empty($filters['from'])) {
            $where .= " AND createstamp >= :activity_from";
            $params[':activity_from'] = $filters['from'] . ' 00:00:00';
        }

        if (!empty($filters['to'])) {
            $where .= " AND createstamp <= :activity_to";
            $params[':activity_to'] = $filters['to'] . ' 23:59:59';
        }

        if ($filters['q'] !== '') {
            $where .= " AND (s_message LIKE :activity_q OR s_action LIKE :activity_q OR s_object_type LIKE :activity_q OR s_object_id LIKE :activity_q OR JSON_SEARCH(COALESCE(s_payload, '{}'), 'one', :activity_q_raw) IS NOT NULL)";
            $params[':activity_q'] = '%' . $filters['q'] . '%';
            $params[':activity_q_raw'] = $filters['q'];
        }

        $totalSql = "SELECT COUNT(*) AS total FROM s_activity WHERE {$where}";
        $totalRows = $this->db->query($totalSql, $params);
        $total = (int)($totalRows[0]['total'] ?? 0);

        $page = (int)$pagination['page'];
        $perPage = (int)$pagination['per_page'];
        $offset = max(0, ($page - 1) * $perPage);

        $sql = "SELECT * FROM s_activity WHERE {$where} ORDER BY createstamp DESC LIMIT {$perPage} OFFSET {$offset}";
        $rows = $this->db->query($sql, $params);

        foreach ($rows as &$row) {
            $payload = [];
            if (!empty($row['s_payload'])) {
                $payload = json_decode((string)$row['s_payload'], true) ?: [];
            }
            $row['metadata'] = $payload;
            $row['event_label'] = $payload['activity_event'] ?? $payload['event_type'] ?? ($row['s_action'] ?? '');
            $row['relative_time'] = $this->formatRelativeTime($row['createstamp'] ?? null);
            $row['scope'] = $this->resolveScope($row);
            $row['scope_label'] = $this->formatScopeLabel($row);
            $row['metadata_pairs'] = $this->formatMetadataPairs($payload);
            $row['link'] = $this->resolveLink($payload);
            $row['actor_name'] = $payload['user_name'] ?? $payload['username'] ?? null;
        }
        unset($row);

        $chart = $this->buildChartData($entityId, $filters, $spaceOptions, $superAdmin);
        $totalPages = max(1, (int)ceil($total / max(1, $perPage)));

        return [
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'chart' => $chart,
        ];
    }

    private function buildMetrics(array $activities, array $spaceOptions, array $filters): array {
        $rows = $activities['rows'] ?? [];
        $total = (int)($activities['pagination']['total'] ?? count($rows));
        $actors = [];
        $spaces = [];
        foreach ($rows as $row) {
            $actorId = (int)($row['s_actor_id'] ?? 0);
            if ($actorId > 0) {
                $actors[$actorId] = true;
            }
            $spaceId = (int)($row['space_id'] ?? 0);
            if ($spaceId > 0) {
                $spaces[$spaceId] = true;
            }
        }
        return [
            'total' => $total,
            'unique_actors' => count($actors),
            'unique_workspaces' => count($spaces),
            'filters_active' => !empty(array_filter($filters, static fn($value) => $value !== '' && $value !== 0 && $value !== null)),
        ];
    }

    private function buildChartData(int $entityId, array $filters, array $spaceOptions, bool $superAdmin): array {
        $where = "livestatus != '0'";
        $params = [];

        switch ($filters['scope']) {
            case 'workspace':
                $spaceId = $this->resolveWorkspaceScope($filters['space_id'], $spaceOptions);
                if ($spaceId > 0) {
                    $where .= " AND space_id = :activity_space";
                    $params[':activity_space'] = $spaceId;
                }
                break;
            case 'global':
                $where .= " AND space_id = 0";
                break;
            case 'system':
                if (!$superAdmin) {
                    $where .= " AND s_actor_id = :activity_actor";
                    $params[':activity_actor'] = $entityId;
                }
                break;
            default:
                $actorId = $superAdmin && $filters['actor_id'] > 0 ? $filters['actor_id'] : $entityId;
                $where .= " AND s_actor_id = :activity_actor";
                $params[':activity_actor'] = $actorId;
                break;
        }

        if ($superAdmin && $filters['actor_id'] > 0) {
            $where .= " AND s_actor_id = :activity_actor_override";
            $params[':activity_actor_override'] = $filters['actor_id'];
        }

        if ($filters['event_type'] !== '') {
            $where .= " AND (s_action = :activity_event OR s_object_type = :activity_event OR JSON_EXTRACT(COALESCE(s_payload, '{}'), '$.activity_event') = :activity_event OR JSON_EXTRACT(COALESCE(s_payload, '{}'), '$.event_type') = :activity_event)";
            $params[':activity_event'] = $filters['event_type'];
        }

        if (!empty($filters['from'])) {
            $where .= " AND createstamp >= :activity_from";
            $params[':activity_from'] = $filters['from'] . ' 00:00:00';
        }

        if (!empty($filters['to'])) {
            $where .= " AND createstamp <= :activity_to";
            $params[':activity_to'] = $filters['to'] . ' 23:59:59';
        }

        $dailySql = "SELECT DATE(createstamp) AS day, COUNT(*) AS total FROM s_activity WHERE {$where} GROUP BY DATE(createstamp) ORDER BY day DESC LIMIT 14";
        $dailyRows = $this->db->query($dailySql, $params);
        $dailyRows = array_reverse($dailyRows);

        $eventSql = "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(s_payload, '{}'), '$.activity_event')), s_action) AS event_key, COUNT(*) AS total
                     FROM s_activity WHERE {$where}
                     GROUP BY event_key
                     ORDER BY total DESC
                     LIMIT 8";
        $eventRows = $this->db->query($eventSql, $params);

        return [
            'daily' => $dailyRows,
            'events' => $eventRows,
        ];
    }

    private function resolveWorkspaceScope(int $requestedId, array $spaceOptions): int {
        if ($requestedId > 0 && isset($spaceOptions[$requestedId])) {
            return $requestedId;
        }
        if (!empty($spaceOptions)) {
            return (int)array_key_first($spaceOptions);
        }
        return 0;
    }

    private function loadSpaces(bool $superAdmin, int $entityId): array {
        if ($superAdmin) {
            $rows = $this->db->select('s_space', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        } else {
            $sql = "SELECT DISTINCT s.id, s.s_name
                    FROM s_space_membership m
                    INNER JOIN s_space s ON s.id = m.space_id
                    WHERE m.livestatus != '0' AND m.s_entity_id = :entity AND s.livestatus != '0'
                    ORDER BY s.s_name";
            $rows = $this->db->query($sql, [':entity' => $entityId]);
        }

        $spaces = [];
        foreach ($rows as $row) {
            $spaces[(int)$row['id']] = $row['s_name'] ?? ('Workspace #' . $row['id']);
        }
        return $spaces;
    }

    private function getScopeOptions(bool $superAdmin): array {
        $options = [
            'my' => 'My Actions',
            'workspace' => 'Workspace',
            'global' => 'Global',
        ];
        if ($superAdmin) {
            $options['system'] = 'All';
        }
        return $options;
    }

    private function sanitizeDate(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return $dt ? $dt->format('Y-m-d') : null;
    }

    private function formatMetadataPairs(array $metadata): array {
        $reserved = ['event_type', 'activity_event', 'audience'];
        $pairs = [];
        foreach ($metadata as $key => $value) {
            if (in_array($key, $reserved, true)) {
                continue;
            }
            if (is_scalar($value)) {
                $pairs[] = ['label' => ucfirst(str_replace('_', ' ', $key)), 'value' => (string)$value];
            }
        }
        return $pairs;
    }

    private function formatScopeLabel(array $row): string {
        switch ($row['scope'] ?? '') {
            case 'user':
                return 'Direct';
            case 'workspace':
                return 'Workspace';
            default:
                return 'Global';
        }
    }

    private function resolveScope(array $row): string {
        $spaceId = (int)($row['space_id'] ?? 0);
        $actorId = (int)($row['s_actor_id'] ?? 0);
        if ($spaceId > 0) {
            return 'workspace';
        }
        if ($actorId > 0) {
            return 'user';
        }
        return 'global';
    }

    private function resolveLink(array $payload): ?string {
        $uri = trim((string)($payload['uri'] ?? ''));
        if ($uri === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $uri)) {
            return $uri;
        }
        $host = trim((string)($payload['host'] ?? ''));
        if ($host !== '') {
            return 'https://' . $host . $uri;
        }
        return $uri;
    }

    private function handleIngestAction(): void {
        if (strtoupper((string)$this->runData['request']->method) !== 'POST') {
            return;
        }

        $action = (string)($this->runData['request']->post['action'] ?? '');
        if ($action !== 'ingest_activity_auto') {
            return;
        }

        if (!$this->isSuperAdmin()) {
            throw new \Exception('Access denied.', 403);
        }

        $logDir = rtrim($this->runData['config']['dir']['log'] ?? '', '/');
        $lastRun = $this->getConfigValue('activity_ingest_last_run');
        $start = $this->normalizeDate($lastRun) ?? date('Y-m-d');
        $latestDay = $this->findLatestLogDay($logDir);
        $end = $latestDay ? sprintf('%s-%s-%s', $latestDay['year'], $latestDay['month'], $latestDay['day']) : $start;

        $activity = new \Core\App\Activity($this->db, $this->runData['config']);
        $result = $activity->ingestAccessLogs([
            'start' => $start,
            'end' => $end,
            'log_dir' => $logDir,
        ]);

        $this->saveConfigValue('activity_ingest_last_run', date('Y-m-d H:i:s'));
        $this->saveConfigValue('activity_ingest_last_range', json_encode([
            'start' => $start,
            'end' => $end,
            'processed' => (int)$result['processed'],
            'inserted' => (int)$result['inserted'],
            'skipped' => (int)$result['skipped'],
        ]));

        $message = sprintf(
            'Processed %d entries across %d day(s). Inserted %d, skipped %d.',
            (int)$result['processed'],
            (int)$result['days'],
            (int)$result['inserted'],
            (int)$result['skipped']
        );
        $this->runData['request']->setAlert($message, 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/activity/view');
        exit;
    }

    private function formatRelativeTime(?string $date): string {
        if (!$date) {
            return '';
        }
        try {
            $dt = new \DateTime($date, new \DateTimeZone('UTC'));
            $timestamp = $dt->getTimestamp();
        } catch (\Throwable $e) {
            return $date;
        }
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return $diff . 's ago';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        }
        $timezone = TimeHelper::resolveTimezone(
            $this->runData['entity']['timezone'] ?? null,
            $this->runData['config']['sys']['timezone_default'] ?? null
        );
        return TimeHelper::formatUtc($timestamp, $timezone, 'M j, Y H:i') ?? $date;
    }

    private function findLatestLogDay(string $logDir): ?array {
        if ($logDir === '' || !is_dir($logDir)) {
            return null;
        }
        $years = glob($logDir . '/*', GLOB_ONLYDIR);
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
                    return [
                        'year' => basename($yearPath),
                        'month' => basename($monthPath),
                        'day' => basename($dayPath),
                        'path' => $dayPath,
                    ];
                }
            }
        }
        return null;
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

    private function getConfigValue(string $handle): ?string {
        try {
            $rows = $this->db->select('s_config', ['s_config_handle' => $handle], true);
            if (!empty($rows[0]['s_config_value'])) {
                return (string)$rows[0]['s_config_value'];
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    private function normalizeDate(?string $value): ?string {
        if (!$value) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        $datePart = substr($trimmed, 0, 10);
        $dateObj = \DateTime::createFromFormat('Y-m-d', $datePart);
        if (!$dateObj) {
            return null;
        }
        return $dateObj->format('Y-m-d');
    }

    private function getConfigJson(string $handle): ?array {
        $value = $this->getConfigValue($handle);
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function saveConfigValue(string $handle, string $value): void {
        $existing = $this->db->select('s_config', ['s_config_handle' => $handle], true);
        if (!empty($existing)) {
            $this->db->update('s_config', ['s_config_value' => $value], ['s_config_handle' => $handle]);
        } else {
            $this->db->insert('s_config', [
                's_config_handle' => $handle,
                's_config_value' => $value,
            ]);
        }
    }

    private function getProfilePerPage(int $fallback): int {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return $fallback;
        }
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return $fallback;
        }
        $definition = json_decode((string)($rows[0]['s_definition'] ?? '{}'), true);
        $perPage = (int)($definition['profile_prefs']['per_page'] ?? $fallback);
        return $this->isAllowedPerPage($perPage) ? $perPage : $fallback;
    }

    private function saveProfilePerPage(int $perPage): void {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return;
        }
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return;
        }
        $definition = json_decode((string)($rows[0]['s_definition'] ?? '{}'), true);
        if (!is_array($definition)) {
            $definition = [];
        }
        $definition['profile_prefs']['per_page'] = $perPage;
        $this->db->update('s_entity', [
            's_definition' => json_encode($definition, JSON_UNESCAPED_SLASHES),
        ], ['id' => $entityId]);
    }

    private function isAllowedPerPage(int $perPage): bool {
        return in_array($perPage, [10, 25, 50, 100, 200], true);
    }

    private function isSuperAdmin(): bool {
        $entity = $this->runData['entity'] ?? [];
        if (!empty($entity['id']) && (int)$entity['id'] === 1) {
            return true;
        }
        $roles = $entity['role_id'] ?? [];
        if (is_array($roles)) {
            return in_array(1, $roles, true);
        }
        return (int)$roles === 1;
    }
}

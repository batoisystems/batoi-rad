<?php
namespace RadAdmin;
use DateTime;

class Accesslog{
    private const MAX_RENDER_ENTRIES = 1500;
    private $runData = [];
    private $db;
    private $errorHandler;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
    }

    public function view() {
        $perPageParam = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($this->isAllowedPerPage($perPageParam)) {
            $this->saveProfilePerPage($perPageParam);
        }
        $perPage = $this->isAllowedPerPage($perPageParam) ? $perPageParam : $this->getProfilePerPage(25);

        $selectedDate = $this->sanitizeDate($this->runData['request']->get['date'] ?? date('Y-m-d'));
        $rangeDays = max(1, (int)($this->runData['request']->get['range_days'] ?? 1));
        $hourFilter = $this->normalizeHour($this->runData['request']->get['hour'] ?? '');
        $search = trim((string)($this->runData['request']->get['q'] ?? ''));
        $entryLimit = $this->resolveEntryLimit(1000);
        $accessEntries = $this->getAccessLogDataRange($selectedDate, $rangeDays, $entryLimit);
        $accessEntries = $this->applyFilters($accessEntries, $hourFilter, $search);
        $groupedRequests = $this->groupAccessEntries($accessEntries);
        $metrics = $this->buildMetrics($accessEntries);
        $hourlyChart = $this->buildHourlyChart($accessEntries);
        $topEndpoints = $this->buildTopEndpoints($accessEntries);

        if (!isset($this->runData['route']['alert'])) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Interactive access log insights for ' . DateTime::createFromFormat('Y-m-d', $selectedDate)->format('F j, Y');
        }
        $this->runData['route']['h1'] = 'Access Log & Analytics';

        $this->runData['data']['selected_date'] = $selectedDate;
        $this->runData['data']['accesslog'] = $accessEntries;
        $this->runData['data']['request_groups'] = $groupedRequests;
        $this->runData['data']['metrics'] = $metrics;
        $this->runData['data']['hourly_chart'] = $hourlyChart;
        $this->runData['data']['top_endpoints'] = $topEndpoints;
        $this->runData['data']['date_options'] = $this->getAvailableLogDates();
        $this->runData['data']['entry_limit'] = $entryLimit;
        $this->runData['data']['per_page_pref'] = $perPage;
        $this->runData['data']['filters'] = [
            'date' => $selectedDate,
            'range_days' => $rangeDays,
            'hour' => $hourFilter,
            'q' => $search,
        ];

        return $this->runData;
    }

    public function exportcsv() {
        $selectedDate = $this->sanitizeDate($this->runData['request']->get['date'] ?? date('Y-m-d'));
        $rangeDays = max(1, (int)($this->runData['request']->get['range_days'] ?? 1));
        $hourFilter = $this->normalizeHour($this->runData['request']->get['hour'] ?? '');
        $search = trim((string)($this->runData['request']->get['q'] ?? ''));
        $limit = $this->resolveEntryLimit(2000);
        $entries = $this->getAccessLogDataRange($selectedDate, $rangeDays, $limit);
        $entries = $this->applyFilters($entries, $hourFilter, $search);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="access-log.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Timestamp',
            'IP',
            'Method',
            'URI',
            'Execution Time',
            'User ID',
            'Username',
            'Full Name',
            'Session',
            'User Agent',
            'Space ID',
            'MS',
            'Route',
            'Activity Label',
            'Activity Severity',
        ]);
        foreach ($entries as $entry) {
            fputcsv($out, [
                $entry['timestamp'] ?? '',
                $entry['ip'] ?? '',
                $entry['method'] ?? '',
                $entry['uri'] ?? '',
                $entry['execution_time'] ?? '',
                $entry['user_id'] ?? '',
                $entry['username'] ?? '',
                $entry['user_fullname'] ?? '',
                $entry['session_key'] ?? '',
                $entry['user_agent'] ?? '',
                $entry['space_id'] ?? '',
                $entry['ms_name'] ?? '',
                $entry['route_id'] ?? '',
                $entry['activity_label'] ?? '',
                $entry['activity_severity'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    public function purge() {
        if (strtoupper($this->runData['request']->method) !== 'POST') {
            header('Location: '.$this->runData['route']['rad_admin_url'].'/accesslog/view');
            exit();
        }

        $window = $this->runData['request']->post['purge_window'] ?? '';
        $windows = [
            '30' => ['label' => 'more than last 30 days', 'days' => 30],
            '60' => ['label' => 'more than last 60 days', 'days' => 60],
            '90' => ['label' => 'more than last 90 days', 'days' => 90],
            '180' => ['label' => 'more than last 6 months', 'days' => 180],
            '365' => ['label' => 'more than last 1 year', 'days' => 365],
            '730' => ['label' => 'more than last 2 years', 'days' => 730],
            '1095' => ['label' => 'more than last 3 years', 'days' => 1095],
            '1460' => ['label' => 'more than last 4 years', 'days' => 1460],
            '1825' => ['label' => 'more than last 5 years', 'days' => 1825],
            '2190' => ['label' => 'more than last 6 years', 'days' => 2190],
        ];

        if (!isset($windows[$window])) {
            $this->runData['request']->setAlert('Invalid purge window selected.', 'danger');
            header('Location: '.$this->runData['route']['rad_admin_url'].'/accesslog/view');
            exit();
        }

        $deleted = $this->purgeAccessLogsOlderThan($windows[$window]['days']);
        if ($deleted > 0) {
            $message = sprintf('%d access log(s) older than %s removed.', $deleted, $windows[$window]['label']);
            $this->runData['request']->setAlert($message, 'success');
        } else {
            $this->runData['request']->setAlert('No access logs matched the selected purge window.', 'info');
        }

        header('Location: '.$this->runData['route']['rad_admin_url'].'/accesslog/view');
        exit();
    }

    private function sanitizeDate(string $date): string {
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if ($dateObj === false) {
            return date('Y-m-d');
        }
        return $dateObj->format('Y-m-d');
    }

    private function getProfilePerPage(int $fallback): int {
        $definition = $this->loadEntityDefinition();
        $prefs = $definition['profile_prefs'] ?? [];
        $perPage = (int)($prefs['per_page'] ?? 0);
        return $this->isAllowedPerPage($perPage) ? $perPage : $fallback;
    }

    private function saveProfilePerPage(int $perPage): void {
        if (!$this->isAllowedPerPage($perPage)) {
            return;
        }
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return;
        }
        $definition = $this->loadEntityDefinition();
        $prefs = $definition['profile_prefs'] ?? [];
        if (!is_array($prefs)) {
            $prefs = [];
        }
        $prefs['per_page'] = $perPage;
        $definition['profile_prefs'] = $prefs;
        $this->db->update('s_entity', [
            's_definition' => json_encode($definition, JSON_UNESCAPED_SLASHES),
        ], ['id' => $entityId]);
    }

    private function loadEntityDefinition(): array {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return [];
        }
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return [];
        }
        $raw = $rows[0]['s_definition'] ?? '';
        if (empty($raw)) {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function isAllowedPerPage(int $perPage): bool {
        return in_array($perPage, [10, 25, 50, 100, 200], true);
    }

    private function getAccessLogDataRange(string $date, int $rangeDays, int $limit = 1000): array {
        $rangeDays = max(1, $rangeDays);
        $dateObj = DateTime::createFromFormat('Y-m-d', $date) ?: new DateTime();
        $dates = [];
        for ($i = 0; $i < $rangeDays; $i++) {
            $dates[] = (clone $dateObj)->modify('-' . $i . ' day')->format('Y-m-d');
        }

        $baseUrl = rtrim($this->runData['config']['sys']['base_url'] ?? '', '/');
        $entries = [];
        $maxLines = $this->resolveEntryLimit($limit);
        foreach ($dates as $day) {
            $files = $this->buildLogFilesForDate($day, 'access');
            if (empty($files)) {
                continue;
            }
            $maxPerFile = max(50, (int)ceil($maxLines / max(1, count($files))));
            $lines = [];
            foreach ($files as $file) {
                $lines = array_merge($lines, $this->readLogTail($file, $maxPerFile));
            }
            foreach ($lines as $line) {
                $parsed = $this->parseAccessLine($line);
                if ($parsed) {
                    $entries[] = $this->decorateEntry($parsed, $baseUrl);
                }
            }
        }

        usort($entries, function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });

        if ($limit > 0 && count($entries) > $limit) {
            $entries = array_slice($entries, 0, $limit);
        }

        return $this->enrichUserMetadata($entries);
    }

    private function applyFilters(array $entries, ?int $hour, string $search): array {
        $search = strtolower($search);
        if ($hour === null && $search === '') {
            return $entries;
        }
        $filtered = [];
        foreach ($entries as $entry) {
            if ($hour !== null && (int)($entry['hour'] ?? -1) !== $hour) {
                continue;
            }
            if ($search !== '') {
                $blob = strtolower(
                    ($entry['uri'] ?? '') . ' ' .
                    ($entry['username'] ?? '') . ' ' .
                    ($entry['user_fullname'] ?? '') . ' ' .
                    ($entry['ip'] ?? '') . ' ' .
                    ($entry['session_key'] ?? '')
                );
                if (strpos($blob, $search) === false) {
                    continue;
                }
            }
            $filtered[] = $entry;
        }
        return $filtered;
    }

    private function normalizeHour($hour): ?int {
        if ($hour === '' || $hour === null) {
            return null;
        }
        if (!is_numeric($hour)) {
            return null;
        }
        $hour = (int)$hour;
        if ($hour < 0 || $hour > 23) {
            return null;
        }
        return $hour;
    }

    private function parseAccessLine(string $line): ?array {
        if (!preg_match('/^\[(.*?)\]:\s*(\{.*\})$/', trim($line), $matches)) {
            return null;
        }
        $timestamp = $matches[1];
        $payload = json_decode($matches[2], true);
        if (!is_array($payload)) {
            return null;
        }

        $payload['timestamp'] = $timestamp;
        $payload['hour'] = substr($timestamp, 11, 2);
        $payload['execution_time'] = isset($payload['execution_time']) ? (float)$payload['execution_time'] : 0.0;
        return $payload;
    }

    private function decorateEntry(array $entry, string $baseUrl): array {
        $uriValue = $entry['uri'] ?? '/';
        $relative = $uriValue === '' ? '/' : $uriValue;
        $parsed = parse_url($relative);
        $path = $parsed['path'] ?? $relative;
        $queryString = $parsed['query'] ?? '';
        $fragment = $parsed['fragment'] ?? '';
        $queryParams = [];
        if ($queryString !== '') {
            parse_str($queryString, $queryParams);
        }

        $entry['path'] = $path === '' ? '/' : $path;
        $entry['query_string'] = $queryString;
        $entry['query_params'] = $queryParams;
        $entry['fragment'] = $fragment;
        $entry['full_url'] = $this->buildAbsoluteUrl($relative, $baseUrl);
        $entry['timestamp_unix'] = isset($entry['timestamp']) ? strtotime($entry['timestamp']) ?: 0 : 0;
        $entry['session_key'] = $entry['session_key'] ?? '';
        $entry['user_id'] = $entry['user_id'] ?? null;
        $entry['username'] = $entry['username'] ?? '';
        $entry['user_fullname'] = $entry['user_fullname'] ?? '';
        $entry['user_label'] = $entry['user_fullname'] ?: ($entry['username'] ?: 'Guest');
        $entry['is_asset'] = $this->isStaticAsset($path);
        $entry['asset_type'] = $this->detectAssetType($path);
        $entry['context_key'] = $this->buildContextKey($entry);
        $entry['request_id'] = $this->makeRequestId($entry);
        return $entry;
    }

    private function buildAbsoluteUrl(string $uri, string $baseUrl): string {
        $trimmedBase = rtrim($baseUrl, '/');
        if (str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')) {
            return $uri;
        }
        if ($trimmedBase === '') {
            return $uri;
        }
        if ($uri === '' || $uri === '/') {
            return $trimmedBase;
        }
        return $trimmedBase . '/' . ltrim($uri, '/');
    }

    private function enrichUserMetadata(array $entries): array {
        $lookupNeeded = [];
        foreach ($entries as $entry) {
            $userId = $entry['user_id'] ?? null;
            if ($userId && (empty($entry['username']) && empty($entry['user_fullname']))) {
                $lookupNeeded[$userId] = true;
            }
        }

        if (empty($lookupNeeded)) {
            return $entries;
        }

        $resolved = $this->fetchUserMetadata(array_keys($lookupNeeded));
        foreach ($entries as &$entry) {
            $userId = $entry['user_id'] ?? null;
            if ($userId && isset($resolved[$userId])) {
                $meta = $resolved[$userId];
                if (empty($entry['username'])) {
                    $entry['username'] = $meta['username'] ?? '';
                }
                if (empty($entry['user_fullname'])) {
                    $entry['user_fullname'] = $meta['fullname'] ?? '';
                }
                $entry['user_label'] = $entry['user_fullname'] ?: ($entry['username'] ?: 'User #' . $userId);
            }
        }
        unset($entry);

        return $entries;
    }

    private function fetchUserMetadata(array $userIds): array {
        $result = [];
        foreach ($userIds as $userId) {
            $rows = $this->db->select('s_entity', [
                'id' => $userId,
                'livestatus' => '1',
            ], true, [], 1);
            if (!empty($rows[0])) {
                $user = $rows[0];
                $result[$userId] = [
                    'username' => $user['s_identity'] ?? '',
                    'fullname' => $user['s_name'] ?? '',
                ];
            }
        }
        return $result;
    }

    private function buildContextKey(array $entry): string {
        $parts = [
            $entry['ip'] ?? '',
            $entry['session_key'] ?? '',
            substr($entry['user_agent'] ?? '', 0, 120),
        ];
        return sha1(implode('|', $parts));
    }

    private function makeRequestId(array $entry): string {
        return sha1(
            ($entry['timestamp'] ?? '') . '|' .
            ($entry['ip'] ?? '') . '|' .
            ($entry['session_key'] ?? '') . '|' .
            ($entry['uri'] ?? '')
        );
    }

    private function groupAccessEntries(array $entries): array {
        if (empty($entries)) {
            return [];
        }

        $chronological = $entries;
        usort($chronological, static function ($a, $b) {
            return ($a['timestamp_unix'] ?? 0) <=> ($b['timestamp_unix'] ?? 0);
        });

        $groups = [];
        $activeParents = [];
        foreach ($chronological as $entry) {
            $contextKey = $entry['context_key'] ?? '';
            $isAsset = $entry['is_asset'] ?? false;

            if (!$isAsset) {
                $groupId = $entry['request_id'];
                $entry['group_id'] = $groupId;
                $groups[$groupId] = [
                    'group_id' => $groupId,
                    'parent' => $entry,
                    'children' => [],
                ];
                $activeParents[$contextKey] = [
                    'group_id' => $groupId,
                    'timestamp_unix' => $entry['timestamp_unix'] ?? 0,
                ];
                continue;
            }

            $assigned = false;
            if ($contextKey && isset($activeParents[$contextKey])) {
                $parentMeta = $activeParents[$contextKey];
                $delta = ($entry['timestamp_unix'] ?? 0) - ($parentMeta['timestamp_unix'] ?? 0);
                if ($delta >= 0 && $delta <= 20) {
                    $groups[$parentMeta['group_id']]['children'][] = $entry;
                    $assigned = true;
                } elseif ($delta > 30) {
                    unset($activeParents[$contextKey]);
                }
            }

            if (!$assigned) {
                $groupId = $entry['request_id'];
                $entry['group_id'] = $groupId;
                $groups[$groupId] = [
                    'group_id' => $groupId,
                    'parent' => null,
                    'children' => [$entry],
                ];
            }
        }

        $list = array_values($groups);
        foreach ($list as &$group) {
            $group['child_count'] = count($group['children']);
            $group['asset_types'] = $this->summarizeAssetTypes($group['children']);
            $group['primary_timestamp'] = $group['parent']['timestamp_unix']
                ?? ($group['children'] ? end($group['children'])['timestamp_unix'] : 0);
        }
        unset($group);

        usort($list, static function ($a, $b) {
            return ($b['primary_timestamp'] ?? 0) <=> ($a['primary_timestamp'] ?? 0);
        });

        return $list;
    }

    private function summarizeAssetTypes(array $children): array {
        if (empty($children)) {
            return [];
        }
        $counts = [];
        foreach ($children as $child) {
            $label = $this->detectAssetType($child['path'] ?? $child['uri'] ?? '');
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }
        ksort($counts);
        return $counts;
    }

    private function detectAssetType(string $path): string {
        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? $path, PATHINFO_EXTENSION));
        if (in_array($extension, ['css'], true)) {
            return 'CSS';
        }
        if (in_array($extension, ['js'], true)) {
            return 'JS';
        }
        if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico'], true)) {
            return 'Image';
        }
        if (in_array($extension, ['woff', 'woff2', 'ttf', 'otf'], true)) {
            return 'Font';
        }
        if (in_array($extension, ['json', 'xml'], true)) {
            return strtoupper($extension);
        }
        return $extension ? strtoupper($extension) : 'Other';
    }

    private function buildMetrics(array $entries): array {
        if (empty($entries)) {
            return [
                'requests' => 0,
                'avg_time' => 0,
                'unique_ips' => 0,
            ];
        }

        $totalRequests = count($entries);
        $totalTime = array_sum(array_column($entries, 'execution_time'));
        $uniqueIps = count(array_unique(array_column($entries, 'ip')));

        return [
            'requests' => $totalRequests,
            'avg_time' => $totalRequests ? round($totalTime / $totalRequests, 4) : 0,
            'unique_ips' => $uniqueIps,
        ];
    }

    private function buildHourlyChart(array $entries): array {
        $hours = array_fill(0, 24, ['count' => 0, 'time' => 0.0]);

        foreach ($entries as $entry) {
            $hour = isset($entry['hour']) ? (int)$entry['hour'] : (int)substr($entry['timestamp'], 11, 2);
            $hours[$hour]['count']++;
            $hours[$hour]['time'] += $entry['execution_time'];
        }

        $labels = [];
        $counts = [];
        $avgTimes = [];
        foreach ($hours as $hour => $data) {
            $labels[] = sprintf('%02d:00', $hour);
            $counts[] = $data['count'];
            $avgTimes[] = $data['count'] ? round($data['time'] / $data['count'], 4) : 0;
        }

        return [
            'labels' => $labels,
            'requests' => $counts,
            'avg_time' => $avgTimes,
        ];
    }

    private function buildTopEndpoints(array $entries, int $limit = 5): array {
        $totals = [];
        foreach ($entries as $entry) {
            $uri = $entry['uri'] ?? '/';
            if ($this->isStaticAsset($uri)) {
                continue;
            }
            if (!isset($totals[$uri])) {
                $totals[$uri] = ['count' => 0, 'time' => 0.0];
            }
            $totals[$uri]['count']++;
            $totals[$uri]['time'] += $entry['execution_time'];
        }

        $ranked = [];
        foreach ($totals as $uri => $data) {
            $ranked[] = [
                'uri' => $uri,
                'count' => $data['count'],
                'avg_time' => $data['count'] ? round($data['time'] / $data['count'], 4) : 0,
            ];
        }

        usort($ranked, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return array_slice($ranked, 0, $limit);
    }

    private function isStaticAsset(string $uri): bool {
        $uri = strtolower($uri);
        return str_starts_with($uri, '/assets')
            || str_starts_with($uri, '/rad-assets')
            || preg_match('#\.(css|js|png|jpg|jpeg|gif|svg)$#', $uri);
    }

    private function buildLogFilesForDate(string $date, string $type): array {
        [$year, $month, $day] = explode('-', $date);
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $day = str_pad($day, 2, '0', STR_PAD_LEFT);
        $base = rtrim($this->runData['config']['dir']['log'], '/');
        $dayDir = $base . '/' . $year . '/' . $month . '/' . $day;
        if (!is_dir($dayDir)) {
            return [];
        }
        $files = glob($dayDir . '/*/' . $type . '.log') ?: [];
        $legacy = $dayDir . '/' . $type . '.log';
        if (is_file($legacy)) {
            $files[] = $legacy;
        }
        return $files;
    }

    private function resolveEntryLimit(int $requested): int {
        $limit = $requested > 0 ? $requested : self::MAX_RENDER_ENTRIES;
        return max(100, min($limit, self::MAX_RENDER_ENTRIES));
    }

    private function readLogTail(string $file, int $maxLines): array {
        if ($maxLines <= 0) {
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
        while (!$log->eof() && count($lines) < $maxLines) {
            $line = trim((string)$log->fgets());
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function purgeAccessLogsOlderThan(int $days): int {
        $logRoot = rtrim($this->runData['config']['dir']['log'] ?? '', '/');
        if ($logRoot === '' || !is_dir($logRoot)) {
            return 0;
        }
        $cutoff = new DateTime('-' . $days . ' days');
        $deleted = 0;

        $years = glob($logRoot . '/*', GLOB_ONLYDIR);
        foreach ($years as $yearDir) {
            $year = basename($yearDir);
            if (!ctype_digit($year)) {
                continue;
            }
            $months = glob($yearDir . '/*', GLOB_ONLYDIR);
            foreach ($months as $monthDir) {
                $month = basename($monthDir);
                if (!ctype_digit($month)) {
                    continue;
                }
                $dayDirs = glob($monthDir . '/*', GLOB_ONLYDIR);
                foreach ($dayDirs as $dayDir) {
                    $day = basename($dayDir);
                    if (!ctype_digit($day)) {
                        continue;
                    }
                    $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $dateObj = DateTime::createFromFormat('Y-m-d', $dateString);
                    if (!$dateObj || $dateObj >= $cutoff) {
                        continue;
                    }
                    $hourDirs = glob($dayDir . '/*', GLOB_ONLYDIR) ?: [];
                    foreach ($hourDirs as $hourDir) {
                        $accessFile = $hourDir . '/access.log';
                        if (is_file($accessFile) && @unlink($accessFile)) {
                            $deleted++;
                        }
                        if (is_dir($hourDir) && count(glob($hourDir . '/*')) === 0) {
                            @rmdir($hourDir);
                        }
                    }
                    $legacyFile = $dayDir . '/access.log';
                    if (is_file($legacyFile) && @unlink($legacyFile)) {
                        $deleted++;
                    }
                }
            }
        }

        return $deleted;
    }

    private function getAvailableLogDates(): array {
        $logDir = rtrim($this->runData['config']['dir']['log'] ?? '', '/');
        if ($logDir === '' || !is_dir($logDir)) {
            return [date('Y-m-d')];
        }

        $dates = [];
        foreach (glob($logDir . '/*', GLOB_ONLYDIR) as $yearPath) {
            $year = basename($yearPath);
            foreach (glob($yearPath . '/*', GLOB_ONLYDIR) as $monthPath) {
                $month = basename($monthPath);
                foreach (glob($monthPath . '/*', GLOB_ONLYDIR) as $dayPath) {
                    $hasLog = is_file($dayPath . '/access.log') || !empty(glob($dayPath . '/*/access.log'));
                    if ($hasLog) {
                        $day = basename($dayPath);
                        $dates[] = sprintf('%s-%s-%s',
                            str_pad($year, 4, '0', STR_PAD_LEFT),
                            str_pad($month, 2, '0', STR_PAD_LEFT),
                            str_pad($day, 2, '0', STR_PAD_LEFT)
                        );
                    }
                }
            }
        }

        $dates = array_unique($dates);
        rsort($dates);
        return $dates ?: [date('Y-m-d')];
    }
}

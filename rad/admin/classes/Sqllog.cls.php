<?php
namespace RadAdmin;
use DateTime;
class Sqllog{
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
        $typeFilter = strtoupper(trim((string)($this->runData['request']->get['type'] ?? '')));
        $entries = $this->getSQLLogDataRange($selectedDate, $rangeDays);
        $entries = $this->applyFilters($entries, $hourFilter, $search, $typeFilter);
        $metrics = $this->buildMetrics($entries);
        $hourlyChart = $this->buildHourlyChart($entries);
        $typeChart = $this->buildTypeChart($metrics['type_counts']);
        $topTables = $this->buildTopTables($entries);

        if (!isset($this->runData['route']['alert'])) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'SQL activity for ' . DateTime::createFromFormat('Y-m-d', $selectedDate)->format('F j, Y') . '. Enable SQL logging in config to populate this page.';
        }
        $this->runData['route']['h1'] = 'SQL Analytics';

        $this->runData['data']['selected_date'] = $selectedDate;
        $this->runData['data']['sqllog'] = $entries;
        $this->runData['data']['metrics'] = $metrics;
        $this->runData['data']['hourly_chart'] = $hourlyChart;
        $this->runData['data']['type_chart'] = $typeChart;
        $this->runData['data']['top_tables'] = $topTables;
        $this->runData['data']['date_options'] = $this->getAvailableLogDates();
        $this->runData['data']['per_page_pref'] = $perPage;
        $this->runData['data']['filters'] = [
            'date' => $selectedDate,
            'range_days' => $rangeDays,
            'hour' => $hourFilter,
            'q' => $search,
            'type' => $typeFilter,
        ];
        return $this->runData;
    }

    public function exportcsv() {
        $selectedDate = $this->sanitizeDate($this->runData['request']->get['date'] ?? date('Y-m-d'));
        $rangeDays = max(1, (int)($this->runData['request']->get['range_days'] ?? 1));
        $hourFilter = $this->normalizeHour($this->runData['request']->get['hour'] ?? '');
        $search = trim((string)($this->runData['request']->get['q'] ?? ''));
        $typeFilter = strtoupper(trim((string)($this->runData['request']->get['type'] ?? '')));
        $entries = $this->getSQLLogDataRange($selectedDate, $rangeDays);
        $entries = $this->applyFilters($entries, $hourFilter, $search, $typeFilter);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sql-log.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Timestamp',
            'Type',
            'Primary Table',
            'Tables',
            'Param Count',
            'Query',
            'Params',
        ]);
        foreach ($entries as $entry) {
            fputcsv($out, [
                $entry['timestamp'] ?? '',
                $entry['query_type'] ?? '',
                $entry['primary_table'] ?? '',
                implode(', ', $entry['tables'] ?? []),
                $entry['param_count'] ?? '',
                $entry['query'] ?? '',
                json_encode($entry['params'] ?? []),
            ]);
        }
        fclose($out);
        exit;
    }

    public function purge() {
        if (strtoupper($this->runData['request']->method) !== 'POST') {
            header('Location: '.$this->runData['route']['rad_admin_url'].'/sqllog/view');
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
            header('Location: '.$this->runData['route']['rad_admin_url'].'/sqllog/view');
            exit();
        }

        $deleted = $this->purgeSqlLogsOlderThan($windows[$window]['days']);
        if ($deleted > 0) {
            $message = sprintf('%d SQL log(s) older than %s removed.', $deleted, $windows[$window]['label']);
            $this->runData['request']->setAlert($message, 'success');
        } else {
            $this->runData['request']->setAlert('No SQL logs matched the selected purge window.', 'info');
        }

        header('Location: '.$this->runData['route']['rad_admin_url'].'/sqllog/view');
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

    private function getSQLLogDataRange(string $date, int $rangeDays): array {
        $rangeDays = max(1, $rangeDays);
        $dateObj = DateTime::createFromFormat('Y-m-d', $date) ?: new DateTime();
        $dates = [];
        for ($i = 0; $i < $rangeDays; $i++) {
            $dates[] = (clone $dateObj)->modify('-' . $i . ' day')->format('Y-m-d');
        }

        $logEntries = [];
        foreach ($dates as $day) {
            $files = $this->buildLogFilesForDate($day);
            if (empty($files)) {
                continue;
            }
            foreach ($files as $file) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines === false) {
                    continue;
                }
                foreach ($lines as $line) {
                    if (!preg_match('/\[(.*?)\]:\s*(.*)/', $line, $matches)) {
                        continue;
                    }
                    $timestamp = $matches[1];
                    $jsonStr = $matches[2];
                    $logData = json_decode($jsonStr, true);
                    if (!is_array($logData) || !isset($logData['query'])) {
                        continue;
                    }
                    $details = $this->parseQueryDetails($logData['query']);
                    $params = $logData['params'] ?? [];
                    if (!is_array($params)) {
                        $params = [];
                    }

                    $logEntries[] = [
                        'timestamp' => $timestamp,
                        'query' => trim($logData['query']),
                        'params' => $params,
                        'param_count' => count($params),
                        'query_type' => $details['type'],
                        'tables' => $details['tables'],
                        'primary_table' => $details['primary_table'],
                        'hour' => (int)substr($timestamp, 11, 2),
                        'search_blob' => strtolower($logData['query'] . ' ' . implode(' ', $params)),
                    ];
                }
            }
        }

        usort($logEntries, static function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });

        return $logEntries;
    }

    private function applyFilters(array $entries, ?int $hour, string $search, string $type): array {
        $search = strtolower($search);
        $type = strtoupper($type);
        if ($hour === null && $search === '' && $type === '') {
            return $entries;
        }
        $filtered = [];
        foreach ($entries as $entry) {
            if ($hour !== null && (int)($entry['hour'] ?? -1) !== $hour) {
                continue;
            }
            if ($type !== '' && strtoupper((string)($entry['query_type'] ?? '')) !== $type) {
                continue;
            }
            if ($search !== '') {
                $blob = $entry['search_blob'] ?? '';
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

    private function parseQueryDetails(string $query): array {
        $normalized = strtoupper($query);
        $type = 'OTHER';
        if (preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)/i', $query, $match)) {
            $type = strtoupper($match[1]);
        }

        $tables = [];
        $primary = null;
        if (in_array($type, ['SELECT', 'DELETE'], true) && preg_match('/\bFROM\s+`?([a-zA-Z0-9_\.]+)`?/i', $query, $match)) {
            $primary = $match[1];
        } elseif (in_array($type, ['UPDATE'], true) && preg_match('/^\s*UPDATE\s+`?([a-zA-Z0-9_\.]+)`?/i', $query, $match)) {
            $primary = $match[1];
        } elseif (in_array($type, ['INSERT', 'REPLACE'], true) && preg_match('/\bINTO\s+`?([a-zA-Z0-9_\.]+)`?/i', $query, $match)) {
            $primary = $match[1];
        }

        if ($primary) {
            $tables[] = $primary;
        }
        if (preg_match_all('/\bJOIN\s+`?([a-zA-Z0-9_\.]+)`?/i', $query, $joinMatches)) {
            foreach ($joinMatches[1] as $joinTable) {
                $tables[] = $joinTable;
            }
        }

        return [
            'type' => $type,
            'primary_table' => $primary,
            'tables' => array_values(array_unique($tables)),
        ];
    }

    private function buildMetrics(array $entries): array {
        $typeCounts = [];
        $totalParams = 0;
        foreach ($entries as $entry) {
            $type = $entry['query_type'] ?? 'OTHER';
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            $totalParams += $entry['param_count'] ?? 0;
        }

        $total = count($entries);
        $reads = $typeCounts['SELECT'] ?? 0;
        $writes = $total - $reads;
        return [
            'total' => $total,
            'reads' => $reads,
            'writes' => $writes,
            'avg_params' => $total ? round($totalParams / $total, 2) : 0,
            'type_counts' => $typeCounts,
        ];
    }

    private function buildHourlyChart(array $entries): array {
        $hours = array_fill(0, 24, 0);
        foreach ($entries as $entry) {
            $hour = $entry['hour'] ?? 0;
            $hours[$hour]++;
        }

        $labels = [];
        $counts = [];
        foreach ($hours as $hour => $count) {
            $labels[] = sprintf('%02d:00', $hour);
            $counts[] = $count;
        }
        return [
            'labels' => $labels,
            'counts' => $counts,
        ];
    }

    private function buildTypeChart(array $typeCounts): array {
        if (empty($typeCounts)) {
            return [
                'labels' => [],
                'counts' => [],
            ];
        }
        ksort($typeCounts);
        return [
            'labels' => array_keys($typeCounts),
            'counts' => array_values($typeCounts),
        ];
    }

    private function buildTopTables(array $entries, int $limit = 5): array {
        $counts = [];
        foreach ($entries as $entry) {
            if (empty($entry['primary_table'])) {
                continue;
            }
            $table = $entry['primary_table'];
            $counts[$table] = ($counts[$table] ?? 0) + 1;
        }
        arsort($counts);
        $top = array_slice($counts, 0, $limit, true);
        $result = [];
        foreach ($top as $table => $count) {
            $result[] = ['table' => $table, 'count' => $count];
        }
        return $result;
    }

    private function purgeSqlLogsOlderThan(int $days): int {
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
                        $sqlFile = $hourDir . '/sql.log';
                        if (is_file($sqlFile) && @unlink($sqlFile)) {
                            $deleted++;
                        }
                        if (is_dir($hourDir) && count(glob($hourDir . '/*')) === 0) {
                            @rmdir($hourDir);
                        }
                    }
                    $legacyFile = $dayDir . '/sql.log';
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
                    $hasLog = is_file($dayPath . '/sql.log') || !empty(glob($dayPath . '/*/sql.log'));
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

    private function buildLogFilesForDate(string $date): array {
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if ($dateObj === false) {
            $dateObj = new DateTime();
        }
        $year = $dateObj->format('Y');
        $month = $dateObj->format('m');
        $day = $dateObj->format('d');
        $base = rtrim($this->runData['config']['dir']['log'] ?? '', '/');
        $dayDir = $base . '/' . $year . '/' . $month . '/' . $day;
        if (!is_dir($dayDir)) {
            return [];
        }
        $files = glob($dayDir . '/*/sql.log') ?: [];
        $legacy = $dayDir . '/sql.log';
        if (is_file($legacy)) {
            $files[] = $legacy;
        }
        return $files;
    }
}

<?php
namespace RadAdmin;
use DateTime;
use Core\Sys\PrivilegeService;
class Errorlog{
    private const MAX_RENDER_ENTRIES = 2000;
    private $runData = [];
    private $db;
    private $errorHandler;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
    }

    public function view() {
        if (!isset($this->runData['route']['alert'])) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Review structured insights for PHP errors.';
        }

        $perPageParam = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($this->isAllowedPerPage($perPageParam)) {
            $this->saveProfilePerPage($perPageParam);
        }
        $perPage = $this->isAllowedPerPage($perPageParam) ? $perPageParam : $this->getProfilePerPage(25);

        $selectedDate = $this->sanitizeDate($this->runData['request']->get['date'] ?? date('Y-m-d'));
        $rangeDays = max(1, (int)($this->runData['request']->get['range_days'] ?? 1));
        $hourFilter = $this->normalizeHour($this->runData['request']->get['hour'] ?? '');
        $search = trim((string)($this->runData['request']->get['q'] ?? ''));
        $entries = $this->getErrorEntriesRange($selectedDate, $rangeDays);
        $entries = $this->applyFilters($entries, $hourFilter, $search);

        $this->runData['route']['h1'] = 'Error Analytics';
        $this->runData['data']['selected_date'] = $selectedDate;
        $this->runData['data']['available_dates'] = $this->getAvailableLogDates();
        $this->runData['data']['errors'] = $entries;
        $this->runData['data']['metrics'] = $this->buildMetrics($entries);
        $this->runData['data']['hourly_chart'] = $this->buildHourlyStats($entries);
        $this->runData['data']['severity_chart'] = $this->buildSeverityStats($entries);
        $this->runData['data']['top_files'] = $this->buildTopFiles($entries);
        $this->runData['data']['top_messages'] = $this->buildTopMessages($entries);
        $this->runData['data']['entry_limit'] = $this->getEntryLimit();
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
        $entries = $this->getErrorEntriesRange($selectedDate, $rangeDays);
        $entries = $this->applyFilters($entries, $hourFilter, $search);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="error-log.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Timestamp',
            'Type',
            'Code',
            'File',
            'Line',
            'Message',
            'Request',
            'Method',
            'Host',
            'Referer',
            'Status',
            'User ID',
            'User Name',
            'Reference',
            'User Agent',
        ]);
        foreach ($entries as $entry) {
            fputcsv($out, [
                $entry['timestamp'] ?? '',
                $entry['error_type'] ?? '',
                $entry['error_code'] ?? '',
                $entry['file_path'] ?? '',
                $entry['line_number'] ?? '',
                $entry['message'] ?? '',
                $entry['request_uri'] ?? '',
                $entry['request_method'] ?? '',
                $entry['request_host'] ?? '',
                $entry['referer'] ?? '',
                $entry['status_code'] ?? '',
                $entry['user_id'] ?? '',
                $entry['user_name'] ?? '',
                $entry['reference'] ?? '',
                $entry['user_agent'] ?? '',
            ]);
        }
        fclose($out);
        exit;
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

    private function getErrorEntriesRange(string $date, int $rangeDays): array {
        $rangeDays = max(1, $rangeDays);
        $dateObj = DateTime::createFromFormat('Y-m-d', $date) ?: new DateTime();
        $dates = [];
        for ($i = 0; $i < $rangeDays; $i++) {
            $dates[] = (clone $dateObj)->modify('-' . $i . ' day')->format('Y-m-d');
        }

        $maxLines = $this->getEntryLimit();
        $baseDir = rtrim($this->runData['config']['dir']['site'] ?? '', '/') . '/';
        $entries = [];
        foreach ($dates as $day) {
            $files = $this->buildLogFilesForDate($day);
            if (empty($files)) {
                continue;
            }
            $maxPerFile = max(50, (int)ceil($maxLines / max(1, count($files))));
            $lines = [];
            foreach ($files as $file) {
                $lines = array_merge($lines, $this->readLogTail($file, $maxPerFile));
            }
            foreach ($lines as $line) {
                $parsed = $this->parseErrorLine($line, $baseDir);
                if ($parsed) {
                    $entries[] = $parsed;
                }
            }
        }

        usort($entries, function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });

        return $entries;
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
                    ($entry['message'] ?? '') . ' ' .
                    ($entry['file_path'] ?? '') . ' ' .
                    ($entry['request_uri'] ?? '') . ' ' .
                    ($entry['user_name'] ?? '')
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

    private function parseErrorLine(string $line, string $baseDir): ?array {
        $context = [];
        if (strpos($line, '||') !== false) {
            [$linePart, $contextPart] = explode('||', $line, 2);
            $line = trim($linePart);
            $contextJson = trim($contextPart);
            $decoded = json_decode($contextJson, true);
            if (is_array($decoded)) {
                $context = $decoded;
            }
        }

        $line = trim($line);
        $matches = [];
        $parsed = null;

        if (preg_match('/^\[(.*?)\]:\s+(ERROR\s+\d+|ERROR|WARNING|NOTICE)(?:\s+(\d+))?\s+in\s+(.*?)(?:\s+on line (\d+))?:\s+(.*)$/i', $line, $matches)) {
            $parsed = [
                'timestamp' => $matches[1],
                'error_type' => strtoupper(trim($matches[2])),
                'error_code' => $matches[3] ?? null,
                'file_path' => $matches[4] ?? '',
                'line_number' => $matches[5] ?? null,
                'message' => $matches[6] ?? '',
            ];
        } elseif (preg_match('/^\[(.*?)\]:\s+EXCEPTION\s+with\s+code\s+(\d+)\s+in\s+(.*?)\s+\((.*?)\):\s+(.*)$/i', $line, $matches)) {
            $parsed = [
                'timestamp' => $matches[1],
                'error_type' => 'EXCEPTION',
                'error_code' => $matches[2],
                'file_path' => $matches[3] ?? '',
                'line_number' => $matches[4] ?? null,
                'message' => $matches[5] ?? '',
            ];
        } else {
            return null;
        }

        $filePath = isset($parsed['file_path']) ? str_replace($baseDir, '', $parsed['file_path']) : '';
        $filePath = ltrim($filePath, '/');

        return [
            'timestamp' => $parsed['timestamp'],
            'error_type' => $parsed['error_type'],
            'error_code' => $parsed['error_code'],
            'file_path' => $filePath,
            'line_number' => $parsed['line_number'],
            'message' => $parsed['message'],
            'hour' => (int)substr($parsed['timestamp'], 11, 2),
            'request_uri' => $context['uri'] ?? '',
            'request_method' => $context['method'] ?? '',
            'request_host' => $context['host'] ?? '',
            'referer' => $context['referer'] ?? '',
            'user_agent' => $context['user_agent'] ?? '',
            'user_id' => $context['user_id'] ?? '',
            'user_name' => $context['user_name'] ?? '',
            'reference' => $context['reference'] ?? '',
            'status_code' => $context['status_code'] ?? $context['response_code'] ?? null,
        ];
    }

    private function buildMetrics(array $entries): array {
        if (empty($entries)) {
            return [
                'total' => 0,
                'unique_files' => 0,
                'unique_messages' => 0,
            ];
        }

        $files = array_unique(array_map(static function ($entry) {
            return $entry['file_path'] ?? '';
        }, $entries));
        $messages = array_unique(array_map(static function ($entry) {
            return $entry['message'] ?? '';
        }, $entries));

        return [
            'total' => count($entries),
            'unique_files' => count(array_filter($files)),
            'unique_messages' => count(array_filter($messages)),
        ];
    }

    private function buildHourlyStats(array $entries): array {
        $hours = array_fill(0, 24, 0);
        foreach ($entries as $entry) {
            $hourRaw = $entry['hour'] ?? null;
            if ($hourRaw === null && !empty($entry['timestamp'])) {
                $parsedHour = DateTime::createFromFormat('Y-m-d H:i:s', $entry['timestamp']);
                if ($parsedHour !== false) {
                    $hourRaw = (int)$parsedHour->format('H');
                }
            }
            $hour = is_numeric($hourRaw) ? (int)$hourRaw : 0;
            if ($hour < 0 || $hour > 23) {
                $hour = 0;
            }
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

    private function buildSeverityStats(array $entries): array {
        $totals = [];
        foreach ($entries as $entry) {
            $type = strtoupper($entry['error_type'] ?? 'ERROR');
            $totals[$type] = ($totals[$type] ?? 0) + 1;
        }

        $labels = array_keys($totals);
        $counts = array_values($totals);
        return [
            'labels' => $labels,
            'counts' => $counts,
        ];
    }

    private function buildTopFiles(array $entries, int $limit = 5): array {
        $counts = [];
        foreach ($entries as $entry) {
            $file = $entry['file_path'] ?? '(unknown)';
            $counts[$file] = ($counts[$file] ?? 0) + 1;
        }

        arsort($counts);
        $top = array_slice($counts, 0, $limit, true);
        $result = [];
        foreach ($top as $file => $count) {
            $result[] = ['file' => $file, 'count' => $count];
        }
        return $result;
    }

    private function buildTopMessages(array $entries, int $limit = 5): array {
        $counts = [];
        foreach ($entries as $entry) {
            $message = $entry['message'] ?? '';
            if ($message === '') {
                continue;
            }
            $counts[$message] = ($counts[$message] ?? 0) + 1;
        }

        arsort($counts);
        $top = array_slice($counts, 0, $limit, true);
        $result = [];
        foreach ($top as $message => $count) {
            $result[] = ['message' => $message, 'count' => $count];
        }
        return $result;
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
                    $hasLog = is_file($dayPath . '/error.log') || !empty(glob($dayPath . '/*/error.log'));
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

    /**
     * Delete the error log file
     */
    public function deletelog() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('delete')) {
            $this->runData['request']->setAlert('Access denied.', 'danger');
            header('Location: '.$this->runData['route']['rad_admin_url'].'/errorlog/view');
            exit();
        }
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->errorHandler->handleException('Invalid date');
        }
        $date = $this->sanitizeDate($this->runData['route']['pathparts'][3]);
        $files = $this->buildLogFilesForDate($date);
        $deleted = 0;
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $dateDisplay = DateTime::createFromFormat('Y-m-d', $date)->format('F j, Y');
            $message = 'Error log for ' . $dateDisplay . ' has been deleted.';
            $this->runData['request']->setAlert($message, 'success');
        } else {
            $this->runData['request']->setAlert('Error log not found for ' . $date, 'danger');
        }

        header('Location: '.$this->runData['route']['rad_admin_url'].'/errorlog/view');
        exit();
    }

    public function purge() {
        if (strtoupper($this->runData['request']->method) !== 'POST') {
            header('Location: '.$this->runData['route']['rad_admin_url'].'/errorlog/view');
            exit();
        }
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('delete')) {
            $this->runData['request']->setAlert('Access denied.', 'danger');
            header('Location: '.$this->runData['route']['rad_admin_url'].'/errorlog/view');
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
            header('Location: '.$this->runData['route']['rad_admin_url'].'/errorlog/view');
            exit();
        }

        $deleted = $this->purgeLogsOlderThan($windows[$window]['days']);
        if ($deleted > 0) {
            $message = sprintf('%d error log(s) older than %s removed.', $deleted, $windows[$window]['label']);
            $this->runData['request']->setAlert($message, 'success');
        } else {
            $this->runData['request']->setAlert('No error logs matched the selected purge window.', 'info');
        }

        header('Location: '.$this->runData['route']['rad_admin_url'].'/errorlog/view');
        exit();
    }

    public function analyze() {
        if (strtoupper($this->runData['request']->method) !== 'POST') {
            header('HTTP/1.1 405 Method Not Allowed');
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Invalid JSON payload']);
            exit;
        }

        $aiConfig = $this->resolveAiConfig();
        if (empty($aiConfig['endpoint']) || empty($aiConfig['api_key'])) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'AI is not configured. Please set ai.api_key and ai.endpoint in rad/admin/rad.config.php or rad/config/ai-config.php.']);
            exit;
        }

        $summary = $this->buildErrorSummary($payload);
        $prompt = "You are an experienced PHP architect. Analyze the following error log entry and explain likely root causes plus concise fix steps. Highlight any configuration or permission issues. Entry:\n\n" . $summary;

        try {
            $response = $this->requestOpenAi($aiConfig, $prompt);
        } catch (\RuntimeException $e) {
            $this->errorHandler->reportError('AI advice failed: ' . $e->getMessage());
            header('HTTP/1.1 502 Bad Gateway');
            echo json_encode([
                'error' => 'Failed to retrieve AI advice: ' . $e->getMessage(),
            ]);
            exit;
        }

        echo json_encode([
            'advice' => trim($response),
        ]);
        exit;
    }

    private function buildErrorSummary(array $payload): string {
        $parts = [];
        if (!empty($payload['timestamp'])) {
            $parts[] = 'Timestamp: ' . $payload['timestamp'];
        }
        if (!empty($payload['error_type'])) {
            $parts[] = 'Type: ' . $payload['error_type'];
        }
        if (!empty($payload['error_code'])) {
            $parts[] = 'Code: ' . $payload['error_code'];
        }
        if (!empty($payload['file_path'])) {
            $parts[] = 'File: ' . $payload['file_path'];
        }
        if (!empty($payload['line_number'])) {
            $parts[] = 'Line: ' . $payload['line_number'];
        }
        if (!empty($payload['message'])) {
            $parts[] = 'Message: ' . $payload['message'];
        }
        return implode("\n", $parts);
    }

    private function requestOpenAi(array $config, string $prompt): string {
        $models = [];
        if (!empty($config['model'])) {
            $models[] = $config['model'];
        }
        if (!empty($config['fallback_model']) && (!isset($config['model']) || $config['fallback_model'] !== $config['model'])) {
            $models[] = $config['fallback_model'];
        }
        if (empty($models)) {
            $models[] = 'gpt-5.1';
        }

        $lastException = null;
        foreach ($models as $model) {
            try {
                return $this->callOpenAi($config, $prompt, $model);
            } catch (\RuntimeException $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new \RuntimeException('AI service unavailable.');
    }

    private function callOpenAi(array $config, string $prompt, string $model): string {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant for debugging PHP/LAMP applications. Provide concise root-cause analysis and fix steps.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_completion_tokens' => 400,
        ];

        $ch = curl_init($config['endpoint']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('cURL error: ' . $err);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            $snippet = $response ? substr($response, 0, 300) : 'No response body';
            throw new \RuntimeException('HTTP ' . $httpCode . ' ' . $snippet);
        }

        $parsed = json_decode($response, true);
        if (!isset($parsed['choices'][0]['message'])) {
            throw new \RuntimeException('Missing assistant content in AI response.');
        }

        $content = $this->normalizeMessageContent($parsed['choices'][0]['message']['content'] ?? null);
        if ($content === '') {
            throw new \RuntimeException('AI returned an empty response.');
        }

        return $content;
    }

    private function normalizeMessageContent($content): string {
        if (is_string($content)) {
            return trim($content);
        }

        if (!is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $segment) {
            if (is_string($segment)) {
                $parts[] = $segment;
                continue;
            }
            if (!is_array($segment)) {
                continue;
            }
            if (isset($segment['text']) && is_string($segment['text'])) {
                $parts[] = $segment['text'];
                continue;
            }
            if (isset($segment['content']) && is_string($segment['content'])) {
                $parts[] = $segment['content'];
                continue;
            }
        }

        return trim(implode("\n", array_filter($parts, static function ($part) {
            return trim($part) !== '';
        })));
    }

    private function resolveAiConfig(): array {
        try {
            if (class_exists('\\Core\\Sys\\AiProviderFactory')) {
                $config = \Core\Sys\AiProviderFactory::loadConfig($this->runData['config'] ?? []);
                $profileKey = strtolower((string)($config['default_profile'] ?? 'general'));
                $profile = $config['profiles'][$profileKey] ?? ($config['profiles']['general'] ?? []);
                $providerKey = strtolower((string)($profile['provider'] ?? ($config['default_provider'] ?? 'openai')));
                $providers = $config['providers'] ?? [];
                if (!empty($providers[$providerKey])) {
                    $provider = $providers[$providerKey];
                    $quality = strtolower((string)($profile['default_quality'] ?? ($config['default_quality'] ?? 'mini')));
                    $qualityModels = $profile['quality_models'][$quality] ?? [];
                    return [
                        'endpoint' => $profile['endpoint'] ?? ($provider['endpoint'] ?? ''),
                        'api_key' => $provider['api_key'] ?? '',
                        'model' => $qualityModels['model'] ?? ($profile['model'] ?? ($provider['model'] ?? ($provider['ai_model'] ?? ''))),
                        'fallback_model' => $qualityModels['fallback_model'] ?? ($profile['fallback_model'] ?? ($provider['fallback_model'] ?? '')),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Fall back to legacy config.
        }

        return $this->runData['config']['ai'] ?? ($this->runData['config']['rad']['ai'] ?? []);
    }

    private function purgeLogsOlderThan(int $days): int {
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
                $daysDirs = glob($monthDir . '/*', GLOB_ONLYDIR);
                foreach ($daysDirs as $dayDir) {
                    $day = basename($dayDir);
                    if (!ctype_digit($day)) {
                        continue;
                    }
                    $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $dateObj = DateTime::createFromFormat('Y-m-d', $dateString);
                    if (!$dateObj) {
                        continue;
                    }
                    if ($dateObj >= $cutoff) {
                        continue;
                    }
                    $hourDirs = glob($dayDir . '/*', GLOB_ONLYDIR) ?: [];
                    foreach ($hourDirs as $hourDir) {
                        $errorFile = $hourDir . '/error.log';
                        if (is_file($errorFile) && @unlink($errorFile)) {
                            $deleted++;
                        }
                        if (is_dir($hourDir) && count(glob($hourDir . '/*')) === 0) {
                            @rmdir($hourDir);
                        }
                    }
                    $legacyFile = $dayDir . '/error.log';
                    if (is_file($legacyFile) && @unlink($legacyFile)) {
                        $deleted++;
                    }
                }
            }
        }

        return $deleted;
    }

    private function buildLogFilesForDate(string $date): array {
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if ($dateObj === false) {
            // Fall back to today's date if we can't parse the provided string
            $dateObj = new DateTime();
        }

        $year = $dateObj->format('Y');
        $month = $dateObj->format('m');
        $day = $dateObj->format('d');

        $baseDir = rtrim($this->runData['config']['dir']['log'] ?? '', '/');
        $dayDir = $baseDir . '/' . $year . '/' . $month . '/' . $day;
        if (!is_dir($dayDir)) {
            return [];
        }
        $files = glob($dayDir . '/*/error.log') ?: [];
        $legacy = $dayDir . '/error.log';
        if (is_file($legacy)) {
            $files[] = $legacy;
        }
        return $files;
    }

    private function getEntryLimit(): int {
        $configured = (int)($this->runData['config']['rad']['analytics']['errorlog_limit'] ?? 0);
        $limit = $configured > 0 ? $configured : self::MAX_RENDER_ENTRIES;
        return max(100, $limit);
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
}

<?php
namespace Core\Sys;

class QueueService {
    private const BUILTIN_JOBS = ['activity_ingest', 'changelog_fs_ingest'];
    private Database $db;
    private Logger $logger;
    private array $config;

    public function __construct(Database $db, Logger $logger, array $config) {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $config;
    }

    public function ensureBuiltinJobs(): void {
        $this->ensureActivityIngestJob();
        $this->ensureChangelogFsIngestJob();
    }

    private function ensureActivityIngestJob(): void {
        $existing = $this->db->select('s_queue', ['s_queue_script_name' => 'activity_ingest'], true);
        if (!empty($existing)) {
            if (($existing[0]['livestatus'] ?? '0') !== '1') {
                $this->db->update('s_queue', ['livestatus' => '1'], ['id' => (int)$existing[0]['id']]);
            }
            return;
        }
        $next = $this->nextExecutionDate('5 min', null);
        $this->db->insert('s_queue', [
            's_queue_title' => 'Activity Ingest',
            's_queue_script_name' => 'activity_ingest',
            's_execution_frequency' => '5 min',
            's_next_execution' => $next,
            's_queue_status' => null,
        ], ['livestatus' => '1']);
    }

    private function ensureChangelogFsIngestJob(): void {
        $existing = $this->db->select('s_queue', ['s_queue_script_name' => 'changelog_fs_ingest'], true);
        if (!empty($existing)) {
            if (($existing[0]['livestatus'] ?? '0') !== '1') {
                $this->db->update('s_queue', ['livestatus' => '1'], ['id' => (int)$existing[0]['id']]);
            }
            return;
        }
        $next = $this->nextExecutionDate('1 h', null);
        $this->db->insert('s_queue', [
            's_queue_title' => 'Changelog Filesystem Ingest',
            's_queue_script_name' => 'changelog_fs_ingest',
            's_execution_frequency' => '1 h',
            's_next_execution' => $next,
            's_queue_status' => null,
        ], ['livestatus' => '1']);
    }

    public function builtinJobs(): array {
        return self::BUILTIN_JOBS;
    }

    public function runDueJobs(?string $jobName = null): array {
        $this->ensureBuiltinJobs();
        $now = date('Y-m-d H:i:s');
        $params = [];
        $sql = "SELECT * FROM s_queue WHERE livestatus = '1'";
        if ($jobName) {
            $sql .= " AND s_queue_script_name = :job";
            $params[':job'] = $jobName;
        } else {
            $sql .= " AND (s_next_execution IS NULL OR s_next_execution <= :now)";
            $params[':now'] = $now;
        }
        $sql .= " ORDER BY s_next_execution ASC";
        $jobs = $this->db->query($sql, $params);
        $results = [];
        foreach ($jobs as $job) {
            $results[] = $this->runJob($job);
        }
        return $results;
    }

    private function runJob(array $job): array {
        $jobName = $job['s_queue_script_name'] ?? '';
        $start = microtime(true);
        $status = 'Success';
        $error = null;
        $payload = [];

        try {
            if ($jobName === 'activity_ingest') {
                $payload = $this->runActivityIngest();
            } elseif ($jobName === 'changelog_fs_ingest') {
                $payload = $this->runChangelogFsIngest();
            } else {
                $payload = $this->runCustomJob($jobName);
            }
        } catch (\Throwable $e) {
            $status = 'Failure';
            $error = $e->getMessage();
        }

        $duration = round(microtime(true) - $start, 3);
        $next = $this->nextExecutionDate($job['s_execution_frequency'] ?? null, $job['s_last_executed'] ?? null);
        $this->db->update('s_queue', [
            's_last_executed' => date('Y-m-d H:i:s'),
            's_next_execution' => $next,
            's_queue_status' => $status,
            's_error_message' => $error,
        ], ['id' => (int)$job['id']]);

        $this->logger->logQueue('queue_run', [
            'job' => $jobName,
            'status' => $status,
            'duration' => $duration,
            'error' => $error,
            'payload' => $payload,
        ]);

        return [
            'job' => $jobName,
            'status' => $status,
            'duration' => $duration,
            'error' => $error,
            'payload' => $payload,
        ];
    }

    private function runCustomJob(string $jobName): array {
        $path = $this->resolveJobScriptPath($jobName);
        if ($path === null || !is_file($path)) {
            throw new \RuntimeException('Queue job script not found.');
        }

        $context = [
            'db' => $this->db,
            'logger' => $this->logger,
            'config' => $this->config,
            'job' => $jobName,
            'started_at' => date('Y-m-d H:i:s'),
        ];

        $result = include $path;
        if (is_callable($result)) {
            $payload = $result($context);
        } elseif (is_array($result) && isset($result['run']) && is_callable($result['run'])) {
            $payload = $result['run']($context);
        } else {
            throw new \RuntimeException('Queue job script must return a callable.');
        }

        return is_array($payload) ? $payload : ['result' => $payload];
    }

    private function resolveJobScriptPath(string $jobName): ?string {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $jobName)) {
            return null;
        }
        $radDir = rtrim($this->config['dir']['rad'] ?? '', '/');
        if ($radDir === '') {
            return null;
        }
        return $radDir . '/data/queue/jobs/' . $jobName . '.php';
    }

    private function runActivityIngest(): array {
        $logDir = rtrim($this->config['dir']['log'] ?? '', '/');
        $lastRun = $this->getConfigValue('activity_ingest_last_run');
        $start = $this->normalizeDate($lastRun) ?? date('Y-m-d');
        $latest = $this->findLatestLogDay($logDir);
        $end = $latest ? sprintf('%s-%s-%s', $latest['year'], $latest['month'], $latest['day']) : $start;
        $activity = new \Core\App\Activity($this->db, $this->config);
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
        return $result;
    }

    private function runChangelogFsIngest(): array {
        $radDir = rtrim($this->config['dir']['rad'] ?? '', '/');
        if ($radDir === '') {
            return ['processed' => 0, 'candidates' => 0, 'inserted' => 0, 'skipped' => 0, 'errors' => 1, 'message' => 'rad dir not configured'];
        }

        $roots = $this->getChangelogRoots($radDir);
        $extensions = $this->getChangelogExtensions();
        $lastRun = $this->getConfigValue('changelog_fs_last_run');
        $lastRunTs = $lastRun ? strtotime($lastRun) : 0;
        $cursor = $this->getChangelogCursor();
        $cursorRoot = (int)($cursor['root_index'] ?? 0);
        $cursorPath = (string)($cursor['last_path'] ?? '');
        $cursorMtime = (int)($cursor['last_mtime'] ?? 0);
        $minMtime = max($lastRunTs, $cursorMtime);
        $maxFiles = $this->getChangelogMaxFiles();
        $maxSeconds = $this->getChangelogMaxSeconds();
        $startTime = microtime(true);

        $processed = 0;
        $candidates = 0;
        $inserted = 0;
        $skipped = 0;
        $errors = 0;
        $latestMtime = $minMtime;
        $lastProcessedPath = $cursorPath;
        $lastRootIndex = $cursorRoot;
        $partial = false;

        foreach ($roots as $rootIndex => $root) {
            if ($rootIndex < $cursorRoot) {
                continue;
            }
            if (!is_dir($root)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }
                if ($processed >= $maxFiles || (microtime(true) - $startTime) >= $maxSeconds) {
                    $partial = true;
                    break 2;
                }
                $processed++;
                $path = $fileInfo->getPathname();
                if ($rootIndex === $cursorRoot && $cursorPath !== '' && strcmp($path, $cursorPath) <= 0) {
                    continue;
                }
                if (!$this->matchesChangelogExtension($path, $extensions)) {
                    continue;
                }
                $mtime = (int)$fileInfo->getMTime();
                if ($minMtime > 0 && $mtime <= $minMtime) {
                    continue;
                }
                $candidates++;
                $relative = ltrim(str_replace($radDir, '', $path), '/');
                $source = $this->resolveChangelogSource($relative);
                if ($source === '') {
                    continue;
                }
                $table = 'fs_' . $source;
                $recordId = $this->hashPathToId($source, $relative);
                $content = @file_get_contents($path);
                if ($content === false) {
                    $errors++;
                    continue;
                }
                $hash = sha1($content);
                $prev = $this->db->query(
                    "SELECT s_version_number, s_data_record_dump
                     FROM s_version_history
                     WHERE s_db_table = :tbl AND s_data_record_id = :rid
                     ORDER BY id DESC LIMIT 1",
                    [':tbl' => $table, ':rid' => $recordId]
                );
                if (!empty($prev)) {
                    $prevMeta = $this->decodeFsDump($prev[0]['s_data_record_dump'] ?? '');
                    if (($prevMeta['hash'] ?? '') === $hash) {
                        $skipped++;
                        continue;
                    }
                }
                $versionNumber = !empty($prev) ? ((int)($prev[0]['s_version_number'] ?? 0) + 1) : 1;
                $payload = [
                    'type' => 'file',
                    'path' => $relative,
                    'source' => $source,
                    'hash' => $hash,
                    'size' => (int)$fileInfo->getSize(),
                    'mtime' => date('Y-m-d H:i:s', $mtime),
                    'content' => $content,
                ];
                $dump = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($dump === false) {
                    $errors++;
                    continue;
                }
                $this->db->query(
                    "INSERT INTO s_version_history
                        (s_db_table, s_data_record_id, s_data_record_dump, s_version_number, s_modified_by)
                     VALUES (:tbl, :rid, :dump, :ver, :mod)",
                    [
                        ':tbl' => $table,
                        ':rid' => $recordId,
                        ':dump' => $dump,
                        ':ver' => $versionNumber,
                        ':mod' => 0,
                    ]
                );
                $inserted++;
                $lastProcessedPath = $path;
                $lastRootIndex = $rootIndex;
                if ($mtime > $latestMtime) {
                    $latestMtime = $mtime;
                }
            }
        }

        $this->saveConfigValue('changelog_fs_last_run', date('Y-m-d H:i:s'));
        if ($partial) {
            $this->saveChangelogCursor([
                'root_index' => $lastRootIndex,
                'last_path' => $lastProcessedPath,
                'last_mtime' => $latestMtime,
            ]);
        } else {
            $this->saveChangelogCursor([]);
        }
        $this->saveConfigValue('changelog_fs_last_scan', json_encode([
            'processed' => $processed,
            'candidates' => $candidates,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
            'roots' => $roots,
            'extensions' => $extensions,
            'last_mtime' => $latestMtime ? date('Y-m-d H:i:s', $latestMtime) : null,
            'partial' => $partial,
            'cursor' => $partial ? ['root_index' => $lastRootIndex, 'last_path' => $lastProcessedPath] : null,
        ], JSON_UNESCAPED_SLASHES));

        return [
            'processed' => $processed,
            'candidates' => $candidates,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
            'roots' => $roots,
            'extensions' => $extensions,
            'partial' => $partial,
        ];
    }

    private function nextExecutionDate(?string $frequency, ?string $last): ?string {
        $freq = trim((string)$frequency);
        if ($freq === '') {
            return null;
        }
        $base = $last ? new \DateTime($last) : new \DateTime();
        $minutesMap = [
            '1 min' => 1,
            '5 min' => 5,
            '15 min' => 15,
            '30 min' => 30,
            '1 h' => 60,
            '2h' => 120,
            '4h' => 240,
            '6h' => 360,
            '8h' => 480,
            '12h' => 720,
            '1d' => 1440,
            '1w' => 10080,
            '2w' => 20160,
            '1m' => 43200,
            '2m' => 86400,
            '3m' => 129600,
            '4m' => 172800,
            '6m' => 259200,
            '1y' => 525600,
        ];
        $minutes = $minutesMap[$freq] ?? null;
        if ($minutes === null) {
            return null;
        }
        $base->modify('+' . $minutes . ' minutes');
        return $base->format('Y-m-d H:i:s');
    }

    private function getConfigValue(string $handle): ?string {
        $rows = $this->db->select('s_config', ['s_config_handle' => $handle], true);
        return !empty($rows[0]['s_config_value']) ? (string)$rows[0]['s_config_value'] : null;
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

    private function getChangelogRoots(string $radDir): array {
        $roots = $this->config['rad']['changelog_fs']['roots'] ?? $this->config['rad']['changelog']['fs_roots'] ?? [];
        if (!is_array($roots) || empty($roots)) {
            $roots = [
                $radDir . '/ms',
                $radDir . '/theme',
                $radDir . '/vendor',
            ];
        }
        return array_values(array_filter($roots, 'is_string'));
    }

    private function getChangelogExtensions(): array {
        $exts = $this->config['rad']['changelog_fs']['extensions'] ?? $this->config['rad']['changelog']['fs_extensions'] ?? ['php', 'tpl.php', 'js', 'css', 'json'];
        if (!is_array($exts) || empty($exts)) {
            $exts = ['php', 'tpl.php', 'js', 'css', 'json'];
        }
        return array_values(array_filter($exts, 'is_string'));
    }

    private function matchesChangelogExtension(string $path, array $extensions): bool {
        $lower = strtolower($path);
        foreach ($extensions as $ext) {
            $ext = strtolower($ext);
            if ($ext === '') {
                continue;
            }
            $suffix = '.' . ltrim($ext, '.');
            if (substr($lower, -strlen($suffix)) === $suffix) {
                return true;
            }
        }
        return false;
    }

    private function resolveChangelogSource(string $relativePath): string {
        $trimmed = ltrim($relativePath, '/');
        if (strpos($trimmed, 'ms/') === 0) {
            return 'ms';
        }
        if (strpos($trimmed, 'theme/') === 0) {
            return 'theme';
        }
        if (strpos($trimmed, 'vendor/') === 0) {
            return 'vendor';
        }
        return '';
    }

    private function hashPathToId(string $source, string $relativePath): int {
        $hash = sha1($source . ':' . $relativePath);
        return (int)hexdec(substr($hash, 0, 15));
    }

    private function decodeFsDump($dump): array {
        if (!is_string($dump) || $dump === '') {
            return [];
        }
        $decoded = json_decode($dump, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getChangelogMaxFiles(): int {
        $value = (int)($this->config['rad']['changelog_fs']['max_files'] ?? 500);
        return $value > 0 ? $value : 500;
    }

    private function getChangelogMaxSeconds(): int {
        $value = (int)($this->config['rad']['changelog_fs']['max_seconds'] ?? 8);
        return $value > 0 ? $value : 8;
    }

    private function getChangelogCursor(): array {
        $raw = $this->getConfigValue('changelog_fs_cursor');
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function saveChangelogCursor(array $cursor): void {
        $value = empty($cursor) ? '' : json_encode($cursor, JSON_UNESCAPED_SLASHES);
        $this->saveConfigValue('changelog_fs_cursor', $value ?: '');
    }

    private function normalizeDate(?string $value): ?string {
        if (!$value) {
            return null;
        }
        $datePart = substr(trim($value), 0, 10);
        $dateObj = \DateTime::createFromFormat('Y-m-d', $datePart);
        if (!$dateObj) {
            return null;
        }
        return $dateObj->format('Y-m-d');
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

}

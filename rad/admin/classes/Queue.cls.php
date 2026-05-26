<?php
namespace RadAdmin;
use DateTime;

class Queue {
    private $runData = [];
    private $db;
    private $errorHandler;
    private $versionService;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        $this->versionService = new \Core\Sys\FileVersionService($runData['config'] ?? [], function () {
            $entity = $this->runData['entity'] ?? [];
            return $entity['s_name'] ?? $entity['s_identity'] ?? $entity['s_email'] ?? 'RAD Admin';
        });
    }

    public function view() {
        return $this->overview();
    }

    public function overview() {
        if (!$this->canViewQueue()) {
            throw new \Exception('Access denied.', 403);
        }

        $svc = new \Core\Sys\QueueService($this->db, $this->runData['logger'], $this->runData['config']);
        $svc->ensureBuiltinJobs();
        $queues = $this->loadQueues();
        $date = $this->sanitizeDate($this->runData['request']->get['date'] ?? date('Y-m-d'));
        $history = $this->readQueueLog($date);
        $stats = $this->buildStats($history);
        $cronInfo = $this->buildCronInfo();

        $this->runData['route']['h1'] = 'Queue Overview';
        $this->runData['route']['meta_title'] = 'Queue Overview';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $this->runData['route']['rad_admin_url'] . '/governance/changelog',
            'Queue Overview' => '',
        ];

        $this->runData['data']['queues'] = $queues;
        $this->runData['data']['history'] = $history;
        $this->runData['data']['date'] = $date;
        $this->runData['data']['date_options'] = $this->getAvailableLogDates();
        $this->runData['data']['stats'] = $stats;
        $this->runData['data']['cron'] = $cronInfo;
        return $this->runData;
    }

    public function jobs() {
        if (!$this->canViewQueue()) {
            throw new \Exception('Access denied.', 403);
        }
        $svc = new \Core\Sys\QueueService($this->db, $this->runData['logger'], $this->runData['config']);
        $svc->ensureBuiltinJobs();

        $this->runData['route']['h1'] = 'Queue Jobs';
        $this->runData['route']['meta_title'] = 'Queue Jobs';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $this->runData['route']['rad_admin_url'] . '/governance/changelog',
            'Queue Overview' => $this->runData['route']['rad_admin_url'] . '/queue/overview',
            'Queue Jobs' => '',
        ];
        $queues = $this->loadQueues();
        $builtins = $svc->builtinJobs();
        $this->runData['data']['queues'] = $this->decorateQueues($queues, $builtins);
        $this->runData['data']['job_root'] = $this->resolveJobRoot();
        $this->runData['data']['builtin_jobs'] = $builtins;
        return $this->runData;
    }

    public function history() {
        if (!$this->canViewQueue()) {
            throw new \Exception('Access denied.', 403);
        }
        $date = $this->sanitizeDate($this->runData['request']->get['date'] ?? date('Y-m-d'));
        $rangeDays = max(1, (int)($this->runData['request']->get['range_days'] ?? 1));
        $jobFilter = trim((string)($this->runData['request']->get['job'] ?? ''));
        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $perPage = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($perPage === 0) {
            $perPage = $this->getProfilePerPage(25);
        } elseif ($this->isAllowedPerPage($perPage)) {
            $this->saveProfilePerPage($perPage);
        } else {
            $perPage = 25;
        }
        $perPage = max(10, min(200, $perPage));

        $history = $this->readQueueLogRange($date, $rangeDays);
        if ($jobFilter !== '') {
            $history = array_values(array_filter($history, function ($entry) use ($jobFilter) {
                $ctx = $entry['context'] ?? [];
                return isset($ctx['job']) && $ctx['job'] === $jobFilter;
            }));
        }
        $svc = new \Core\Sys\QueueService($this->db, $this->runData['logger'], $this->runData['config']);
        $builtins = $svc->builtinJobs();
        $total = count($history);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;
        $pagedHistory = array_slice($history, $offset, $perPage);
        $summary = $this->buildHistorySummary($history);

        $this->runData['route']['h1'] = 'Queue History';
        $this->runData['route']['meta_title'] = 'Queue History';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $this->runData['route']['rad_admin_url'] . '/governance/changelog',
            'Queue Overview' => $this->runData['route']['rad_admin_url'] . '/queue/overview',
            'Queue History' => '',
        ];
        $this->runData['data']['history'] = $pagedHistory;
        $this->runData['data']['date'] = $date;
        $this->runData['data']['range_days'] = $rangeDays;
        $this->runData['data']['job_filter'] = $jobFilter;
        $this->runData['data']['date_options'] = $this->getAvailableLogDates();
        $this->runData['data']['job_options'] = $this->loadJobOptions();
        $this->runData['data']['builtin_jobs'] = $builtins;
        $this->runData['data']['history_total'] = $total;
        $this->runData['data']['page'] = $page;
        $this->runData['data']['per_page'] = $perPage;
        $this->runData['data']['pages'] = $pages;
        $this->runData['data']['summary'] = $summary;
        return $this->runData;
    }

    public function add() {
        if (!$this->canManageQueue()) {
            throw new \Exception('Access denied.', 403);
        }
        $this->runData['route']['h1'] = 'Create Queue Job';
        $this->runData['route']['meta_title'] = 'Create Queue Job';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $this->runData['route']['rad_admin_url'] . '/governance/changelog',
            'Queue Jobs' => $this->runData['route']['rad_admin_url'] . '/queue/jobs',
            'Create Job' => '',
        ];
        $this->runData['data']['form'] = [
            'mode' => 'create',
            's_queue_title' => '',
            's_queue_script_name' => '',
            's_execution_frequency' => '5 min',
            'livestatus' => '1',
            'code' => $this->defaultJobTemplate(),
        ];
        $this->runData['data']['frequencies'] = $this->getFrequencyOptions();
        $this->runData['data']['versions'] = [];
        $this->runData['data']['job_root'] = $this->resolveJobRoot();
        return $this->runData;
    }

    public function edit() {
        if (!$this->canManageQueue()) {
            throw new \Exception('Access denied.', 403);
        }
        $scriptName = $this->sanitizeScriptName($this->runData['route']['pathparts'][3] ?? '');
        if ($scriptName === '') {
            throw new \Exception('Job not found', 404);
        }
        $rows = $this->db->select('s_queue', ['s_queue_script_name' => $scriptName], true);
        if (empty($rows)) {
            throw new \Exception('Job not found', 404);
        }
        $job = $rows[0];
        $svc = new \Core\Sys\QueueService($this->db, $this->runData['logger'], $this->runData['config']);
        $builtins = $svc->builtinJobs();
        $isBuiltin = in_array($scriptName, $builtins, true);
        if ($isBuiltin) {
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/queue/viewone/' . $scriptName);
            exit;
        }
        $code = $isBuiltin ? $this->loadBuiltinPreview($scriptName) : $this->readJobCode($scriptName);
        $versions = $isBuiltin ? [] : $this->versionService->listVersions('queuejob', $scriptName);

        $this->runData['route']['h1'] = 'Edit Queue Job';
        $this->runData['route']['meta_title'] = 'Edit Queue Job';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $this->runData['route']['rad_admin_url'] . '/governance/changelog',
            'Queue Jobs' => $this->runData['route']['rad_admin_url'] . '/queue/jobs',
            $job['s_queue_title'] ?? $scriptName => '',
        ];
        $this->runData['data']['form'] = array_merge($job, [
            'mode' => 'edit',
            'code' => $code,
            'is_builtin' => $isBuiltin,
        ]);
        $this->runData['data']['frequencies'] = $this->getFrequencyOptions();
        $this->runData['data']['versions'] = $versions;
        $this->runData['data']['job_root'] = $this->resolveJobRoot();
        $this->runData['data']['builtin_jobs'] = $builtins;
        return $this->runData;
    }

    public function viewone() {
        if (!$this->canViewQueue()) {
            throw new \Exception('Access denied.', 403);
        }
        $scriptName = $this->sanitizeScriptName($this->runData['route']['pathparts'][3] ?? '');
        if ($scriptName === '') {
            throw new \Exception('Job not found', 404);
        }
        $rows = $this->db->select('s_queue', ['s_queue_script_name' => $scriptName], true);
        if (empty($rows)) {
            throw new \Exception('Job not found', 404);
        }
        $job = $rows[0];
        $svc = new \Core\Sys\QueueService($this->db, $this->runData['logger'], $this->runData['config']);
        $builtins = $svc->builtinJobs();
        $isBuiltin = in_array($scriptName, $builtins, true);
        $code = $isBuiltin ? $this->loadBuiltinPreview($scriptName) : $this->readJobCode($scriptName);

        $this->runData['route']['h1'] = 'Queue Job';
        $this->runData['route']['meta_title'] = 'Queue Job';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $this->runData['route']['rad_admin_url'] . '/governance/changelog',
            'Queue Jobs' => $this->runData['route']['rad_admin_url'] . '/queue/jobs',
            $job['s_queue_title'] ?? $scriptName => '',
        ];
        $this->runData['data']['job'] = $job;
        $this->runData['data']['code'] = $code;
        $this->runData['data']['is_builtin'] = $isBuiltin;
        $this->runData['data']['job_root'] = $this->resolveJobRoot();
        return $this->runData;
    }

    public function save() {
        if (!$this->canManageQueue()) {
            throw new \Exception('Access denied.', 403);
        }
        if (strtoupper($this->runData['request']->method) !== 'POST') {
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/queue/jobs');
            exit;
        }

        $scriptName = $this->sanitizeScriptName($this->runData['request']->post['s_queue_script_name'] ?? '');
        $title = trim((string)($this->runData['request']->post['s_queue_title'] ?? ''));
        $frequency = trim((string)($this->runData['request']->post['s_execution_frequency'] ?? ''));
        $livestatus = ($this->runData['request']->post['livestatus'] ?? '1') === '1' ? '1' : '0';
        $code = (string)($this->runData['request']->post['code'] ?? '');
        $note = trim((string)($this->runData['request']->post['version_note'] ?? ''));

        if ($scriptName === '') {
            $this->runData['request']->setAlert('Script name is required (letters, numbers, dashes, underscores).', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/queue/add');
            exit;
        }
        $svc = new \Core\Sys\QueueService($this->db, $this->runData['logger'], $this->runData['config']);
        if (in_array($scriptName, $svc->builtinJobs(), true)) {
            $this->runData['request']->setAlert('Built-in jobs cannot be edited.', 'warning');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/queue/edit/' . $scriptName);
            exit;
        }

        if (!in_array($frequency, $this->getFrequencyOptions(), true)) {
            $this->runData['request']->setAlert('Invalid execution frequency.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/queue/edit/' . $scriptName);
            exit;
        }

        $existing = $this->db->select('s_queue', ['s_queue_script_name' => $scriptName], true);
        $now = date('Y-m-d H:i:s');
        $entityId = (int)($this->runData['entity']['id'] ?? 1);

        $this->ensureJobDirectory();
        if ($this->writeJobCode($scriptName, $code) === false) {
            $this->runData['request']->setAlert('Unable to save job code to filesystem.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/queue/edit/' . $scriptName);
            exit;
        }

        $snapshotMeta = ['note' => $note];
        if ($note !== '') {
            $snapshotMeta['force'] = true;
        }
        $this->versionService->snapshot('queuejob', $scriptName, $code, $snapshotMeta);

        if (empty($existing)) {
            $next = $this->computeNextExecution($frequency, null);
            $this->db->insert('s_queue', [
                's_queue_title' => $title !== '' ? $title : $scriptName,
                's_queue_script_name' => $scriptName,
                's_execution_frequency' => $frequency,
                's_next_execution' => $next,
                's_queue_status' => null,
                's_error_message' => null,
            ], [
                'livestatus' => $livestatus,
                'createdby' => $entityId,
            ]);
            $this->runData['request']->setAlert('Queue job created.', 'success');
        } else {
            $row = $existing[0];
            $data = [
                's_queue_title' => $title !== '' ? $title : ($row['s_queue_title'] ?? $scriptName),
                's_execution_frequency' => $frequency,
                's_error_message' => $row['s_error_message'] ?? null,
                'livestatus' => $livestatus,
            ];
            if (($row['s_execution_frequency'] ?? '') !== $frequency) {
                $data['s_next_execution'] = $this->computeNextExecution($frequency, $now);
            }
            $this->db->update('s_queue', $data, ['id' => (int)$row['id']], ['updatedby' => $entityId]);
            $this->runData['request']->setAlert('Queue job updated.', 'success');
        }

        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/queue/edit/' . $scriptName);
        exit;
    }

    public function downloadversion() {
        if (!$this->canManageQueue()) {
            throw new \Exception('Access denied.', 403);
        }
        $scriptName = $this->sanitizeScriptName($this->runData['route']['pathparts'][3] ?? '');
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][4] ?? '');
        if ($scriptName === '' || $versionId === '') {
            throw new \Exception('Invalid version request', 404);
        }
        $version = $this->versionService->fetchVersion('queuejob', $scriptName, $versionId);
        if (!$version) {
            throw new \Exception('Version not found', 404);
        }
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="queue-' . $scriptName . '-' . $versionId . '.php"');
        echo $version['content'] ?? '';
        exit;
    }

    public function diffversion() {
        if (!$this->canManageQueue()) {
            throw new \Exception('Access denied.', 403);
        }
        $scriptName = $this->sanitizeScriptName($this->runData['route']['pathparts'][3] ?? '');
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][4] ?? '');
        if ($scriptName === '' || $versionId === '') {
            throw new \Exception('Invalid version request', 404);
        }
        $current = $this->readJobCode($scriptName);
        $diff = $this->versionService->diff('queuejob', $scriptName, $versionId, $current);
        $version = $this->versionService->fetchVersion('queuejob', $scriptName, $versionId);

        $this->runData['route']['h1'] = 'Queue Job Diff';
        $this->runData['route']['meta_title'] = 'Queue Job Diff';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $this->runData['route']['rad_admin_url'] . '/governance/changelog',
            'Queue Jobs' => $this->runData['route']['rad_admin_url'] . '/queue/jobs',
            'Diff' => '',
        ];
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/queue/edit/' . $scriptName;
        $this->runData['data']['diff'] = [
            'job' => $scriptName,
            'version' => $version,
            'diff' => $diff,
        ];
        return $this->runData;
    }

    public function restoreversion() {
        if (!$this->canManageQueue()) {
            throw new \Exception('Access denied.', 403);
        }
        $scriptName = $this->sanitizeScriptName($this->runData['route']['pathparts'][3] ?? '');
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][4] ?? '');
        $redirect = $this->runData['route']['rad_admin_url'] . '/queue/edit/' . $scriptName;
        if ($scriptName === '' || $versionId === '' || strtoupper($this->runData['request']->method) !== 'POST') {
            header('Location: ' . $redirect);
            exit;
        }
        $version = $this->versionService->fetchVersion('queuejob', $scriptName, $versionId);
        if (!$version) {
            $this->runData['request']->setAlert('Version not found.', 'danger');
            header('Location: ' . $redirect);
            exit;
        }
        if ($this->writeJobCode($scriptName, $version['content'] ?? '') === false) {
            $this->runData['request']->setAlert('Failed to restore version.', 'danger');
            header('Location: ' . $redirect);
            exit;
        }
        $this->versionService->snapshot('queuejob', $scriptName, $version['content'] ?? '', ['note' => 'Restored version ' . $versionId]);
        $this->runData['request']->setAlert('Version restored successfully.', 'success');
        header('Location: ' . $redirect);
        exit;
    }

    public function cron() {
        if (!$this->canViewQueue()) {
            throw new \Exception('Access denied.', 403);
        }
        $queues = $this->loadQueues();
        $svc = new \Core\Sys\QueueService($this->db, $this->runData['logger'], $this->runData['config']);
        $builtins = $svc->builtinJobs();
        $cronInfo = $this->buildCronInfo();
        $this->runData['route']['h1'] = 'Cron Setup';
        $this->runData['route']['meta_title'] = 'Cron Setup';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $this->runData['route']['rad_admin_url'] . '/governance/changelog',
            'Queue Overview' => $this->runData['route']['rad_admin_url'] . '/queue/overview',
            'Cron Setup' => '',
        ];
        $this->runData['data']['cron'] = $cronInfo;
        $this->runData['data']['cron_jobs'] = $this->buildCronJobs($queues, $builtins, $cronInfo);
        return $this->runData;
    }

    public function run() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $job = $this->runData['request']->post['job'] ?? '';
        if ($job === '') {
            $this->runData['request']->setAlert('Missing job name.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/queue/view');
            exit;
        }
        $svc = new \Core\Sys\QueueService($this->db, $this->runData['logger'], $this->runData['config']);
        $svc->runDueJobs($job);
        $this->runData['request']->setAlert('Queue job executed.', 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/queue/view');
        exit;
    }

    private function loadQueues(): array {
        $svc = new \Core\Sys\QueueService($this->db, $this->runData['logger'], $this->runData['config']);
        $svc->ensureBuiltinJobs();
        return $this->db->select('s_queue', [], true, ['s_next_execution' => 'ASC']);
    }

    private function loadJobOptions(): array {
        $rows = $this->db->select('s_queue', [], true, ['s_queue_script_name' => 'ASC']);
        $jobs = [];
        foreach ($rows as $row) {
            if (!empty($row['s_queue_script_name'])) {
                $jobs[] = $row['s_queue_script_name'];
            }
        }
        return array_values(array_unique($jobs));
    }

    private function buildStats(array $history): array {
        $stats = [
            'total' => 0,
            'success' => 0,
            'failure' => 0,
            'avg_duration' => null,
            'last_run' => null,
            'last_success' => null,
            'last_failure' => null,
            'trend' => [],
        ];
        if (empty($history)) {
            return $stats;
        }
        $durations = [];
        foreach ($history as $entry) {
            $stats['total']++;
            $ctx = $entry['context'] ?? [];
            $status = strtolower((string)($ctx['status'] ?? ''));
            $timestamp = $entry['timestamp'] ?? null;
            if ($stats['last_run'] === null && $timestamp) {
                $stats['last_run'] = $timestamp;
            }
            if ($status === 'success') {
                $stats['success']++;
                if ($stats['last_success'] === null && $timestamp) {
                    $stats['last_success'] = $timestamp;
                }
            } elseif ($status === 'failure') {
                $stats['failure']++;
                if ($stats['last_failure'] === null && $timestamp) {
                    $stats['last_failure'] = $timestamp;
                }
            }
            if (!empty($ctx['duration'])) {
                $durations[] = (float)$ctx['duration'];
            }
            if (count($stats['trend']) < 12) {
                $stats['trend'][] = [
                    'status' => $status,
                    'duration' => (float)($ctx['duration'] ?? 0),
                ];
            }
        }
        if (!empty($durations)) {
            $stats['avg_duration'] = round(array_sum($durations) / count($durations), 3);
        }
        return $stats;
    }

    private function buildHistorySummary(array $history): array {
        $summary = [
            'total' => 0,
            'success' => 0,
            'failure' => 0,
            'other' => 0,
            'avg_duration' => null,
            'last_run' => null,
            'by_job' => [],
            'chart' => [
                'status' => ['labels' => [], 'values' => []],
                'jobs' => ['labels' => [], 'values' => []],
            ],
        ];
        if (empty($history)) {
            return $summary;
        }
        $durations = [];
        foreach ($history as $entry) {
            $summary['total']++;
            $ctx = $entry['context'] ?? [];
            $status = strtolower((string)($ctx['status'] ?? 'other'));
            if ($summary['last_run'] === null && !empty($entry['timestamp'])) {
                $summary['last_run'] = $entry['timestamp'];
            }
            if ($status === 'success') {
                $summary['success']++;
            } elseif ($status === 'failure') {
                $summary['failure']++;
            } else {
                $summary['other']++;
            }
            if (!empty($ctx['duration'])) {
                $durations[] = (float)$ctx['duration'];
            }
            $job = $ctx['job'] ?? 'unknown';
            $summary['by_job'][$job] = ($summary['by_job'][$job] ?? 0) + 1;
        }
        if (!empty($durations)) {
            $summary['avg_duration'] = round(array_sum($durations) / count($durations), 3);
        }
        arsort($summary['by_job']);
        $topJobs = array_slice($summary['by_job'], 0, 6, true);
        $summary['chart']['status'] = [
            'labels' => ['Success', 'Failure', 'Other'],
            'values' => [$summary['success'], $summary['failure'], $summary['other']],
        ];
        $summary['chart']['jobs'] = [
            'labels' => array_keys($topJobs),
            'values' => array_values($topJobs),
        ];
        return $summary;
    }

    private function buildCronInfo(): array {
        $baseUrl = rtrim($this->runData['config']['sys']['base_url'] ?? '', '/');
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
            $baseUrl = $scheme . '://' . $host;
        }
        $queueUrl = $baseUrl . '/queue/run';
        $token = $this->resolveQueueToken();
        $tokenSuffix = $token !== '' ? '&token=' . $token : '';
        return [
            'base_url' => $baseUrl,
            'queue_url' => $queueUrl,
            'token' => $token,
            'has_token' => $token !== '',
            'run_all' => $queueUrl . ($token !== '' ? '?token=' . $token : ''),
            'run_activity' => $queueUrl . '?job=activity_ingest' . $tokenSuffix,
        ];
    }

    private function buildCronJobs(array $queues, array $builtins, array $cronInfo): array {
        $queueUrl = $cronInfo['queue_url'] ?? '';
        $token = $cronInfo['token'] ?? '';
        $tokenSuffix = $token !== '' ? '&token=' . $token : '';
        $jobs = [];
        foreach ($queues as $queue) {
            $script = $queue['s_queue_script_name'] ?? '';
            if ($script === '') {
                continue;
            }
            $jobs[] = [
                'title' => $queue['s_queue_title'] ?? $script,
                'script' => $script,
                'frequency' => $queue['s_execution_frequency'] ?? '',
                'cron' => $this->frequencyToCron($queue['s_execution_frequency'] ?? ''),
                'status' => $queue['s_queue_status'] ?? '',
                'livestatus' => $queue['livestatus'] ?? '',
                'is_builtin' => in_array($script, $builtins, true),
                'run' => $queueUrl . '?job=' . rawurlencode($script) . $tokenSuffix,
                'wget' => '/usr/bin/wget -qO- "' . $queueUrl . '?job=' . rawurlencode($script) . $tokenSuffix . '" >/dev/null 2>&1',
            ];
        }
        return $jobs;
    }

    private function frequencyToCron(string $frequency): string {
        $freq = strtolower(trim($frequency));
        $map = [
            '1 min' => '* * * * *',
            '2 min' => '*/2 * * * *',
            '5 min' => '*/5 * * * *',
            '10 min' => '*/10 * * * *',
            '15 min' => '*/15 * * * *',
            '30 min' => '*/30 * * * *',
            '1 hour' => '0 * * * *',
            '6 hour' => '0 */6 * * *',
            '12 hour' => '0 */12 * * *',
            '1 day' => '0 0 * * *',
            'daily' => '0 0 * * *',
        ];
        return $map[$freq] ?? '';
    }

    private function resolveQueueToken(): string {
        $token = $this->runData['config']['sys']['queue_token'] ?? '';
        if ($token !== '') {
            return (string)$token;
        }
        $rows = $this->db->select('s_config', ['s_config_handle' => 'sys.queue_token'], true);
        if (!empty($rows[0]['s_config_value'])) {
            return (string)$rows[0]['s_config_value'];
        }
        return '';
    }

    private function canManageQueue(): bool {
        $entityId = (int)($this->runData['entity']['id'] ?? $this->runData['entity']['entity_id'] ?? 0);
        if ($entityId === 1) {
            return true;
        }
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->can('idm_manage')) {
            return true;
        }
        $role = $priv->role();
        return in_array($role, ['system_admin', 'access_admin', 'developer'], true);
    }

    private function canViewQueue(): bool {
        $entityId = (int)($this->runData['entity']['id'] ?? $this->runData['entity']['entity_id'] ?? 0);
        if ($entityId === 1) {
            return true;
        }
        if (!empty($this->runData['entity']['is_logged_in'])) {
            return true;
        }
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        $role = $priv->role();
        if (in_array($role, ['system_admin', 'access_admin', 'developer', 'analyst'], true)) {
            return true;
        }
        if ($priv->can('idm_view')) {
            return true;
        }
        return $priv->can('view');
    }

    private function sanitizeDate(string $date): string {
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if ($dateObj === false) {
            return date('Y-m-d');
        }
        return $dateObj->format('Y-m-d');
    }

    private function readQueueLog(string $date): array {
        $date = $this->sanitizeDate($date);
        [$year, $month, $day] = explode('-', $date);
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $day = str_pad($day, 2, '0', STR_PAD_LEFT);
        $base = rtrim($this->runData['config']['dir']['log'] ?? '', '/');
        $dayDir = $base . '/' . $year . '/' . $month . '/' . $day;
        if (!is_dir($dayDir)) {
            return [];
        }
        $files = glob($dayDir . '/*/queue.log') ?: [];
        $legacy = $dayDir . '/queue.log';
        if (is_file($legacy)) {
            $files[] = $legacy;
        }
        $entries = [];
        foreach ($files as $file) {
            $lines = $this->readLogTail($file, 200);
            foreach ($lines as $line) {
                if (!preg_match('/^\[(.*?)\]:\s*(\{.*\})$/', trim($line), $matches)) {
                    continue;
                }
                $payload = json_decode($matches[2], true);
                if (!is_array($payload)) {
                    continue;
                }
                $entries[] = [
                    'timestamp' => $matches[1],
                    'message' => $payload['message'] ?? '',
                    'context' => $payload['context'] ?? [],
                ];
            }
        }
        usort($entries, function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });
        return $entries;
    }

    private function readQueueLogRange(string $endDate, int $rangeDays): array {
        $rangeDays = max(1, $rangeDays);
        if ($rangeDays === 1) {
            return $this->readQueueLog($endDate);
        }
        $end = \DateTime::createFromFormat('Y-m-d', $this->sanitizeDate($endDate)) ?: new \DateTime();
        $entries = [];
        for ($i = 0; $i < $rangeDays; $i++) {
            $day = (clone $end)->modify('-' . $i . ' day')->format('Y-m-d');
            $entries = array_merge($entries, $this->readQueueLog($day));
        }
        usort($entries, function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });
        return $entries;
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
                    $hasLog = !empty(glob($dayPath . '/*/queue.log')) || is_file($dayPath . '/queue.log');
                    if ($hasLog) {
                        $day = basename($dayPath);
                        $dates[] = sprintf('%s-%s-%s', $year, $month, $day);
                    }
                }
            }
        }
        $dates = array_unique($dates);
        rsort($dates);
        return $dates ?: [date('Y-m-d')];
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

    private function getFrequencyOptions(): array {
        return ['1 min', '5 min', '15 min', '30 min', '1 h', '2h', '4h', '6h', '8h', '12h', '1d', '1w', '2w', '1m', '2m', '3m', '4m', '6m', '1y'];
    }

    private function computeNextExecution(?string $frequency, ?string $last): ?string {
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

    private function sanitizeScriptName(string $name): string {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            return '';
        }
        return $name;
    }

    private function sanitizeVersionId(string $versionId): string {
        $versionId = trim($versionId);
        if ($versionId === '') {
            return '';
        }
        return preg_replace('/[^A-Za-z0-9_]/', '', $versionId);
    }

    private function resolveJobRoot(): string {
        $radDir = rtrim($this->runData['config']['dir']['rad'] ?? '', '/');
        return $radDir !== '' ? $radDir . '/data/queue/jobs' : '';
    }

    private function resolveJobPath(string $scriptName): string {
        return rtrim($this->resolveJobRoot(), '/') . '/' . $scriptName . '.php';
    }

    private function ensureJobDirectory(): void {
        $dir = $this->resolveJobRoot();
        if ($dir !== '' && !is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function readJobCode(string $scriptName): string {
        $path = $this->resolveJobPath($scriptName);
        if (!is_file($path)) {
            return '';
        }
        $raw = (string)file_get_contents($path);
        $normalized = $this->normalizeJobCode($raw);
        if ($normalized !== $raw) {
            // Normalize HTML-escaped content back to PHP source.
            file_put_contents($path, $normalized);
        }
        return $normalized;
    }

    private function writeJobCode(string $scriptName, string $code): bool {
        $path = $this->resolveJobPath($scriptName);
        $normalized = $this->normalizeJobCode($code);
        return file_put_contents($path, $normalized) !== false;
    }

    private function decorateQueues(array $queues, array $builtins = []): array {
        foreach ($queues as &$queue) {
            $script = $queue['s_queue_script_name'] ?? '';
            $queue['script_exists'] = false;
            $queue['version_count'] = 0;
            $queue['is_builtin'] = in_array($script, $builtins, true);
            if ($script !== '') {
                if ($queue['is_builtin']) {
                    $queue['script_exists'] = true;
                    $queue['version_count'] = 0;
                } else {
                    $queue['script_exists'] = is_file($this->resolveJobPath($script));
                    $queue['version_count'] = count($this->versionService->listVersions('queuejob', $script));
                }
            }
        }
        return $queues;
    }

    private function getProfilePerPage(int $fallback): int {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return $fallback;
        }
        $rows = $this->runData['db']->select('s_entity', ['id' => $entityId], true);
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
        $rows = $this->runData['db']->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return;
        }
        $definition = json_decode((string)($rows[0]['s_definition'] ?? '{}'), true);
        if (!is_array($definition)) {
            $definition = [];
        }
        $definition['profile_prefs']['per_page'] = $perPage;
        $this->runData['db']->update('s_entity', [
            's_definition' => json_encode($definition, JSON_UNESCAPED_SLASHES),
        ], ['id' => $entityId]);
    }

    private function isAllowedPerPage(int $perPage): bool {
        return in_array($perPage, [10, 25, 50, 100, 200], true);
    }

    private function loadBuiltinPreview(string $scriptName): string {
        if ($scriptName === 'activity_ingest') {
            return "// Built-in job\n// This job runs Core\\Sys\\QueueService::runActivityIngest()\n// Code is managed in rad/core/sys/QueueService.cls.php\n";
        }
        if ($scriptName === 'changelog_fs_ingest') {
            return "// Built-in job\n// This job runs Core\\Sys\\QueueService::runChangelogFsIngest()\n// Code is managed in rad/core/sys/QueueService.cls.php\n";
        }
        return "// Built-in job\n// Code is managed in core system classes.\n";
    }

    private function defaultJobTemplate(): string {
        return "<?php\n// Queue job: return a callable that receives a context array.\nreturn function (array \$context): array {\n    // \$context['db'], \$context['logger'], \$context['config'] available.\n    return ['status' => 'ok'];\n};\n";
    }

    private function normalizeJobCode(string $code): string {
        if (strpos($code, '&lt;') !== false || strpos($code, '&#039;') !== false || strpos($code, '&gt;') !== false) {
            return html_entity_decode($code, ENT_QUOTES | ENT_HTML5);
        }
        return $code;
    }
}

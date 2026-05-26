<?php
namespace RadAdmin;

class Governance {
    private $runData = [];
    private $db;
    private $errorHandler;

    private $codeTables = ['s_ms', 's_msroute', 's_mscontroller', 's_theme', 's_content', 's_ms_tpl', 's_upgrade', 's_wf_action', 's_wf_state', 's_nav', 's_navset'];
    private $fsTables = ['fs_ms', 'fs_theme', 'fs_vendor'];

    private $codePaths = [];
    private $codeExtensions = [];
    private $versionDirs = [];

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];

        // Configure filesystem scan roots and extensions for code insights
        $adminDir = rtrim($runData['config']['dir']['admin'] ?? '', '/');
        $baseRoot = rtrim($runData['config']['dir']['rad'] ?? dirname($adminDir), '/');
        $this->codePaths = $runData['config']['rad']['code_insights']['paths'] ?? [
            $baseRoot . '/ms',
            $baseRoot . '/theme',
            $baseRoot . '/public_html/assets',
        ];
        $this->codeExtensions = $runData['config']['rad']['code_insights']['extensions'] ?? ['php','tpl.php','js','css','json'];
        $this->versionDirs = $runData['config']['rad']['code_insights']['version_dirs'] ?? [
            $baseRoot . '/data/versions/theme',
            $baseRoot . '/data/versions/route',
        ];
    }

    public function changelog() {
        $this->runData['route']['h1'] = 'Changelog';
        $this->runData['route']['meta_title'] = 'Changelog';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $this->runData['route']['rad_admin_url'] . '/governance/changelog',
            'Changelog' => '',
        ];

        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $perPage = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($perPage === 0) {
            $perPage = $this->getProfilePerPage(25);
        } elseif ($this->isAllowedPerPage($perPage)) {
            $this->saveProfilePerPage($perPage);
        } else {
            $perPage = 25;
        }

        $filters = [
            'table' => $this->runData['request']->get['table'] ?? '',
            'actor' => $this->runData['request']->get['actor'] ?? '',
            'date_from' => $this->runData['request']->get['date_from'] ?? '',
            'date_to' => $this->runData['request']->get['date_to'] ?? '',
            'search' => $this->runData['request']->get['search'] ?? '',
            'code_only' => ($this->runData['request']->get['code_only'] ?? '') === '1',
            'change_type' => $this->runData['request']->get['change_type'] ?? '',
            'code_source' => $this->runData['request']->get['code_source'] ?? '',
        ];

        $totalRows = $this->countChangelog($filters);
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $entries = $this->fetchChangelog($filters, $perPage, $offset);
        $tables = $this->db->query("SELECT DISTINCT s_db_table FROM s_version_history ORDER BY s_db_table");
        $actors = $this->db->query("SELECT DISTINCT s_modified_by FROM s_version_history WHERE s_modified_by IS NOT NULL ORDER BY s_modified_by");

        $this->runData['data']['changelog'] = $entries;
        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['tables'] = array_map(function ($r) { return $r['s_db_table']; }, $tables);
        $this->runData['data']['actors'] = array_map(function ($r) { return $r['s_modified_by']; }, $actors);
        $this->runData['data']['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalRows,
            'total_pages' => $totalPages,
        ];
        return $this->runData;
    }

    public function snapshot() {
        $id = (int)($this->runData['route']['pathparts'][3] ?? 0);
        if ($id <= 0) {
            $this->jsonError('Snapshot not found.');
        }
        $rows = $this->db->select('s_version_history', ['id' => $id], true);
        if (!$rows) {
            $this->jsonError('Snapshot not found.');
        }
        $row = $rows[0];
        $decoded = $this->decodeDumpWithMeta($row['s_data_record_dump'] ?? '');
        $content = $this->prepareDumpForJson($this->sanitizeDumpValue($decoded['data']));
        header('Content-Type: application/json');
        echo json_encode([
            'snapshot' => $content,
            'format' => $decoded['format'],
            'raw_base64' => $decoded['raw_base64'],
        ], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public function diff() {
        $id = (int)($this->runData['route']['pathparts'][3] ?? 0);
        if ($id <= 0) {
            $this->jsonError('Diff not found.');
        }
        $rows = $this->db->select('s_version_history', ['id' => $id], true);
        if (!$rows) {
            $this->jsonError('Diff not found.');
        }
        $row = $rows[0];
        $prev = $this->db->query(
            "SELECT * FROM s_version_history
             WHERE s_db_table = :tbl AND s_data_record_id = :rid AND id < :id
             ORDER BY id DESC LIMIT 1",
            [':tbl' => $row['s_db_table'], ':rid' => $row['s_data_record_id'], ':id' => $row['id']]
        );
        $currentDecoded = $this->decodeDumpWithMeta($row['s_data_record_dump'] ?? '');
        $prevDecoded = $this->decodeDumpWithMeta($prev[0]['s_data_record_dump'] ?? '');
        $currentDump = $currentDecoded['data'];
        $prevDump = $prevDecoded['data'];
        $diff = $this->buildDiff($prevDump, $currentDump);
        header('Content-Type: application/json');
        echo json_encode([
            'diff' => $this->prepareDumpForJson($this->sanitizeDumpValue($diff)),
            'format' => [
                'current' => $currentDecoded['format'],
                'previous' => $prevDecoded['format'],
            ],
            'raw_base64' => [
                'current' => $currentDecoded['raw_base64'],
                'previous' => $prevDecoded['raw_base64'],
            ],
        ], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public function insights() {
        $this->runData['route']['h1'] = 'Code Insights';
        $this->runData['route']['meta_title'] = 'Code Insights';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $this->runData['route']['rad_admin_url'] . '/governance/changelog',
            'Code Insights' => '',
        ];
        $defaultDays = (int)($this->runData['request']->get['days'] ?? 30);
        if ($defaultDays < 1 || $defaultDays > 365) {
            $defaultDays = 30;
        }
        $this->runData['data']['default_days'] = $defaultDays;
        // Preload insights so the page has data even if JS fetch is blocked
        $this->runData['data']['insights_bootstrap'] = $this->collectInsights($defaultDays);
        return $this->runData;
    }

    public function insightsData() {
        $days = (int)($this->runData['request']->get['days'] ?? 30);
        if ($days < 1 || $days > 365) {
            $days = 30;
        }
        $insights = $this->collectInsights($days);
        header('Content-Type: application/json');
        echo json_encode(['insights' => $insights], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public function health() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }

        $logDir = rtrim($this->runData['config']['dir']['log'] ?? '', '/');
        $action = $this->runData['request']->post['action'] ?? '';
        if (strtoupper((string)$this->runData['request']->method) === 'POST' && $action !== '') {
            if ($action === 'purge_logs') {
                $days = (int)($this->runData['request']->post['purge_days'] ?? 0);
                if ($days <= 0) {
                    $this->runData['request']->setAlert('Select a valid purge window.', 'danger');
                } else {
                    $deleted = $this->purgeLogsOlderThan($logDir, $days);
                    $message = $deleted > 0 ? sprintf('%d log files removed.', $deleted) : 'No logs matched the selected purge window.';
                    $this->runData['request']->setAlert($message, $deleted > 0 ? 'success' : 'info');
                }
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/governance/health');
                exit;
            }

            if ($action === 'ingest_activity') {
                $start = $this->runData['request']->post['date_from'] ?? date('Y-m-d');
                $end = $this->runData['request']->post['date_to'] ?? $start;
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
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/governance/health');
                exit;
            }

            if ($action === 'ingest_activity_auto') {
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
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/governance/health');
                exit;
            }

            if ($action === 'workspace_audit_scan') {
                $report = $this->runWorkspaceSqlAudit();
                $this->saveConfigValue('workspace_audit_last_run', $report['generated_at']);
                $this->saveConfigValue('workspace_audit_last_count', (string)$report['summary']['issue_total']);
                $this->saveConfigValue('workspace_audit_last_report', $report['report_file']);
                $redirect = $this->runData['route']['rad_admin_url'] . '/governance/workspace-audit';
                header('Location: ' . $redirect);
                exit;
            }
        }

        $latestDay = $this->findLatestLogDay($logDir);
        $counts = $latestDay ? $this->countLogsForDay($latestDay['path']) : ['access' => 0, 'error' => 0, 'sql' => 0];

        $this->runData['route']['h1'] = 'System Health';
        $this->runData['route']['meta_title'] = 'System Health';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $this->runData['route']['rad_admin_url'] . '/governance/changelog',
            'System Health' => '',
        ];

        $this->runData['data']['log_dir'] = $logDir;
        $this->runData['data']['latest_day'] = $latestDay;
        $this->runData['data']['log_counts'] = $counts;
        $this->runData['data']['log_dates'] = $this->getLogDates($logDir);
        $this->runData['data']['activity_ingest_last_run'] = $this->getConfigValue('activity_ingest_last_run');
        $this->runData['data']['activity_ingest_last_range'] = $this->getConfigJson('activity_ingest_last_range');
        $this->runData['data']['queue_last_run'] = $this->getLatestQueueRun($logDir);
        $this->runData['data']['workspace_audit_last_run'] = $this->getConfigValue('workspace_audit_last_run');
        $this->runData['data']['workspace_audit_last_count'] = $this->getConfigValue('workspace_audit_last_count');
        $cacheService = new \Core\Sys\CacheService($this->runData['config']);
        $cacheSummary = $cacheService->summarize();
        $cacheSummary['total_size_label'] = $cacheService->formatBytes((int)$cacheSummary['total_size']);
        $this->runData['data']['cache_summary'] = $cacheSummary;
        return $this->runData;
    }

    public function cache() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }

        $radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
        $this->runData['route']['h1'] = 'Cache Management';
        $this->runData['route']['meta_title'] = 'Cache Management';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $radAdminUrl . '/governance/health',
            'Cache Management' => '',
        ];

        $cache = new \Core\Sys\CacheService($this->runData['config']);
        if (strtoupper((string)$this->runData['request']->method) === 'POST') {
            $action = $this->runData['request']->post['action'] ?? '';
            $ms = trim((string)($this->runData['request']->post['ms_name'] ?? ''));
            $type = trim((string)($this->runData['request']->post['cache_type'] ?? ''));
            $id = trim((string)($this->runData['request']->post['cache_id'] ?? ''));

            $deleted = 0;
            if ($action === 'purge_all') {
                $deleted = $cache->purgeAll();
                $this->runData['request']->setAlert('Purged all cache entries (' . $deleted . ' files).', 'success');
            } elseif ($action === 'purge_ms' && $ms !== '') {
                $deleted = $cache->purgeMs($ms);
                $this->runData['request']->setAlert('Purged cache for ' . $ms . ' (' . $deleted . ' files).', 'success');
            } elseif ($action === 'purge_type' && $ms !== '' && $type !== '') {
                $deleted = $cache->purgeType($ms, $type);
                $this->runData['request']->setAlert('Purged ' . $type . ' cache for ' . $ms . ' (' . $deleted . ' files).', 'success');
            } elseif ($action === 'purge_item' && $ms !== '' && $type !== '' && $id !== '') {
                $deleted = $cache->purgeItem($ms, $type, $id);
                $this->runData['request']->setAlert('Purged ' . $type . ' cache item ' . $id . ' (' . $deleted . ' files).', 'success');
            } else {
                $this->runData['request']->setAlert('Invalid cache purge request.', 'danger');
            }
            header('Location: ' . $radAdminUrl . '/governance/cache');
            exit;
        }

        $summary = $cache->summarize();
        $summary['total_size_label'] = $cache->formatBytes((int)$summary['total_size']);
        foreach ($summary['services'] as &$service) {
            $service['size_label'] = $cache->formatBytes((int)$service['size']);
            foreach ($service['types'] as &$typeEntry) {
                $typeEntry['size_label'] = $cache->formatBytes((int)$typeEntry['size']);
                foreach ($typeEntry['items'] as &$item) {
                    $item['size_label'] = $cache->formatBytes((int)$item['size']);
                }
                unset($item);
            }
            unset($typeEntry);
        }
        unset($service);

        $this->runData['data']['cache_summary'] = $summary;
        return $this->runData;
    }

    public function forgotpasswordlog() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }

        $radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
        $this->runData['route']['h1'] = 'Forgot Password Log';
        $this->runData['route']['meta_title'] = 'Forgot Password Log';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $radAdminUrl . '/governance/health',
            'Forgot Password Log' => '',
        ];

        $action = $this->runData['request']->post['action'] ?? '';
        if (strtoupper((string)$this->runData['request']->method) === 'POST' && $action === 'purge_password_reset') {
            $days = (int)($this->runData['request']->post['purge_days'] ?? 0);
            $allowed = [30, 90, 180];
            if (!in_array($days, $allowed, true)) {
                $this->runData['request']->setAlert('Select a valid cleanup window.', 'danger');
            } else {
                $cutoff = (new \DateTime('now', new \DateTimeZone('UTC')))
                    ->modify('-' . $days . ' days')
                    ->format('Y-m-d H:i:s');
                $this->db->query(
                    "UPDATE s_password_reset SET livestatus = '2' WHERE createstamp < :cutoff",
                    [':cutoff' => $cutoff]
                );
                $this->runData['request']->setAlert('Cleanup completed for entries older than ' . $days . ' days.', 'success');
            }
            header('Location: ' . $radAdminUrl . '/governance/forgotpasswordlog');
            exit;
        }

        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $perPage = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($perPage === 0) {
            $perPage = $this->getProfilePerPage(25);
        } elseif ($this->isAllowedPerPage($perPage)) {
            $this->saveProfilePerPage($perPage);
        } else {
            $perPage = 25;
        }

        $filters = [
            'status' => trim((string)($this->runData['request']->get['status'] ?? '')),
            'date_from' => $this->normalizeDate($this->runData['request']->get['date_from'] ?? ''),
            'date_to' => $this->normalizeDate($this->runData['request']->get['date_to'] ?? ''),
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
            'ip' => trim((string)($this->runData['request']->get['ip'] ?? '')),
        ];

        $totalRows = $this->countForgotPasswordRows($filters);
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $rows = $this->fetchForgotPasswordRows($filters, $perPage, $offset);
        $rows = $this->augmentForgotPasswordLogs($rows);

        $summary = $this->summarizeForgotPassword($filters);

        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['rows'] = $rows;
        $this->runData['data']['summary'] = $summary;
        $this->runData['data']['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalRows,
            'total_pages' => $totalPages,
        ];
        return $this->runData;
    }

    public function workspace_audit() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }

        $action = $this->runData['request']->post['action'] ?? '';
        if (strtoupper((string)$this->runData['request']->method) === 'POST' && $action !== '') {
            if ($action === 'workspace_audit_scan') {
                $report = $this->runWorkspaceSqlAudit();
                $this->saveConfigValue('workspace_audit_last_run', $report['generated_at']);
                $this->saveConfigValue('workspace_audit_last_count', (string)$report['summary']['issue_total']);
                $this->saveConfigValue('workspace_audit_last_report', $report['report_file']);
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/governance/workspace-audit');
                exit;
            }

            if ($action === 'workspace_audit_ignore') {
                $key = trim((string)($this->runData['request']->post['ignore_key'] ?? ''));
                if ($key !== '') {
                    $allow = $this->getConfigJson('workspace_audit_allowlist') ?? [];
                    if (!in_array($key, $allow, true)) {
                        $allow[] = $key;
                        $this->saveConfigValue('workspace_audit_allowlist', json_encode($allow, JSON_UNESCAPED_SLASHES));
                    }
                }
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/governance/workspace-audit');
                exit;
            }
        }

        $report = $this->loadWorkspaceAuditReport();
        $filters = [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
            'ms' => trim((string)($this->runData['request']->get['ms'] ?? '')),
            'route_id' => trim((string)($this->runData['request']->get['route_id'] ?? '')),
            'route_uid' => trim((string)($this->runData['request']->get['route_uid'] ?? '')),
            'source' => trim((string)($this->runData['request']->get['source'] ?? '')),
        ];
        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $perPage = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($perPage === 0) {
            $perPage = $this->getProfilePerPage(25);
        } elseif ($this->isAllowedPerPage($perPage)) {
            $this->saveProfilePerPage($perPage);
        } else {
            $perPage = 25;
        }

        $issues = $report['issues'] ?? [];
        $issues = array_values(array_filter($issues, function ($row) use ($filters) {
            if ($filters['ms'] !== '' && stripos((string)($row['ms_name'] ?? ''), $filters['ms']) === false) {
                return false;
            }
            if ($filters['route_id'] !== '' && (string)($row['route_id'] ?? '') !== $filters['route_id']) {
                return false;
            }
            if ($filters['route_uid'] !== '' && stripos((string)($row['route_uid'] ?? ''), $filters['route_uid']) === false) {
                return false;
            }
            if ($filters['source'] !== '' && (string)($row['source'] ?? '') !== $filters['source']) {
                return false;
            }
            if ($filters['q'] !== '') {
                $haystack = implode(' ', [
                    (string)($row['ms_name'] ?? ''),
                    (string)($row['route_id'] ?? ''),
                    (string)($row['route_uid'] ?? ''),
                    (string)($row['route_name'] ?? ''),
                    (string)($row['template'] ?? ''),
                    (string)($row['file'] ?? ''),
                    (string)($row['snippet'] ?? ''),
                ]);
                if (stripos($haystack, $filters['q']) === false) {
                    return false;
                }
            }
            return true;
        }));

        $total = count($issues);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $issues = array_slice($issues, $offset, $perPage);
        $this->runData['route']['h1'] = 'Workspace SQL Audit';
        $this->runData['route']['meta_title'] = 'Workspace SQL Audit';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $this->runData['route']['rad_admin_url'] . '/governance/changelog',
            'System Health' => $this->runData['route']['rad_admin_url'] . '/governance/health',
            'Workspace SQL Audit' => '',
        ];
        $report['issues'] = $issues;
        $this->runData['data']['audit_report'] = $report;
        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ];
        return $this->runData;
    }

    public function strayroutes() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }

        $radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
        $entityId = (int)($this->runData['entity']['id'] ?? 0);

        if (strtoupper((string)$this->runData['request']->method) === 'POST') {
            if ($entityId !== 1) {
                throw new \Exception('Access denied.', 403);
            }
            $action = $this->runData['request']->post['action'] ?? '';
            if ($action === 'delete_file') {
                $file = trim((string)($this->runData['request']->post['file'] ?? ''));
                $msName = trim((string)($this->runData['request']->post['ms_name'] ?? ''));
                $modeRaw = $this->runData['request']->post['delete_mode'] ?? 'trash';
                if (is_array($modeRaw)) {
                    $modeRaw = end($modeRaw);
                }
                $mode = trim((string)$modeRaw);
                if ($file === '' || $msName === '') {
                    $this->runData['request']->setAlert('Missing file details.', 'danger');
                } else {
                    $result = $this->deleteStrayFile($file, $msName, $mode !== 'trash');
                    $this->runData['request']->setAlert($result['message'], $result['status']);
                }
                header('Location: ' . $radAdminUrl . '/governance/strayroutes');
                exit;
            }
            if ($action === 'generate_file') {
                $msId = (int)($this->runData['request']->post['ms_id'] ?? 0);
                $msName = trim((string)($this->runData['request']->post['ms_name'] ?? ''));
                $type = trim((string)($this->runData['request']->post['type'] ?? ''));
                $key = trim((string)($this->runData['request']->post['key'] ?? ''));
                if ($msId <= 0 || $msName === '' || $type === '' || $key === '') {
                    $this->runData['request']->setAlert('Missing generation details.', 'danger');
                } else {
                    $result = $this->generateStrayFile($msId, $msName, $type, $key);
                    $this->runData['request']->setAlert($result['message'], $result['status']);
                }
                header('Location: ' . $radAdminUrl . '/governance/strayroutes');
                exit;
            }
            if ($action === 'bulk_delete_files') {
                $files = (array)($this->runData['request']->post['files'] ?? []);
                $msNames = (array)($this->runData['request']->post['ms_names'] ?? []);
                $modeRaw = $this->runData['request']->post['delete_mode'] ?? 'trash';
                if (is_array($modeRaw)) {
                    $modeRaw = end($modeRaw);
                }
                $mode = trim((string)$modeRaw);
                $deleted = 0;
                $failed = 0;
                foreach ($files as $idx => $file) {
                    $msName = $msNames[$idx] ?? '';
                    if ($file === '' || $msName === '') {
                        $failed++;
                        continue;
                    }
                    $result = $this->deleteStrayFile($file, $msName, $mode !== 'trash');
                    if ($result['status'] === 'success') {
                        $deleted++;
                    } else {
                        $failed++;
                    }
                }
                $this->runData['request']->setAlert("Deleted {$deleted} file(s). Failed: {$failed}.", $failed ? 'warning' : 'success');
                header('Location: ' . $radAdminUrl . '/governance/strayroutes');
                exit;
            }
            if ($action === 'bulk_generate_files') {
                $items = (array)($this->runData['request']->post['items'] ?? []);
                $generated = 0;
                $failed = 0;
                foreach ($items as $item) {
                    $msId = (int)($item['ms_id'] ?? 0);
                    $msName = (string)($item['ms_name'] ?? '');
                    $type = (string)($item['type'] ?? '');
                    $key = (string)($item['key'] ?? '');
                    if ($msId <= 0 || $msName === '' || $type === '' || $key === '') {
                        $failed++;
                        continue;
                    }
                    $result = $this->generateStrayFile($msId, $msName, $type, $key);
                    if ($result['status'] === 'success') {
                        $generated++;
                    } else {
                        $failed++;
                    }
                }
                $this->runData['request']->setAlert("Generated {$generated} file(s). Failed: {$failed}.", $failed ? 'warning' : 'success');
                header('Location: ' . $radAdminUrl . '/governance/strayroutes');
                exit;
            }
        }

        $this->runData['route']['h1'] = 'Stray Routes & Controllers';
        $this->runData['route']['meta_title'] = 'Stray Routes & Controllers';
        $this->runData['route']['breadcrumb'] = [
            'Governance' => $radAdminUrl . '/governance/health',
            'Stray Routes' => '',
        ];

        $filters = [
            'ms' => trim((string)($this->runData['request']->get['ms'] ?? '')),
            'type' => trim((string)($this->runData['request']->get['type'] ?? '')),
            'status' => trim((string)($this->runData['request']->get['status'] ?? '')),
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
        ];

        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $perPage = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($perPage === 0) {
            $perPage = $this->getProfilePerPage(25);
        } elseif ($this->isAllowedPerPage($perPage)) {
            $this->saveProfilePerPage($perPage);
        } else {
            $perPage = 25;
        }

        $entries = $this->collectStrayEntries();
        $entries = array_values(array_filter($entries, function ($row) use ($filters) {
            if ($filters['ms'] !== '' && stripos((string)($row['ms_name'] ?? ''), $filters['ms']) === false) {
                return false;
            }
            if ($filters['type'] !== '' && (string)($row['type'] ?? '') !== $filters['type']) {
                return false;
            }
            if ($filters['status'] !== '' && (string)($row['status'] ?? '') !== $filters['status']) {
                return false;
            }
            if ($filters['q'] !== '') {
                $haystack = implode(' ', [
                    (string)($row['ms_name'] ?? ''),
                    (string)($row['ms_id'] ?? ''),
                    (string)($row['ms_uid'] ?? ''),
                    (string)($row['file'] ?? ''),
                    (string)($row['key'] ?? ''),
                ]);
                if (stripos($haystack, $filters['q']) === false) {
                    return false;
                }
            }
            return true;
        }));

        $total = count($entries);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $entries = array_slice($entries, $offset, $perPage);

        $this->runData['data']['entries'] = $entries;
        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ];
        $this->runData['data']['can_remediate'] = ($entityId === 1);
        return $this->runData;
    }

    private function collectStrayEntries(): array {
        $radDir = rtrim((string)($this->runData['config']['dir']['rad'] ?? ''), '/');
        $msDir = $radDir . '/ms';
        $msRows = $this->db->query("SELECT id, uid, s_name, s_type FROM s_ms WHERE livestatus != '0'");
        $msByName = [];
        foreach ($msRows as $row) {
            $name = (string)($row['s_name'] ?? '');
            if ($name !== '') {
                $msByName[$name] = [
                    'id' => (int)$row['id'],
                    'uid' => (string)$row['uid'],
                    'name' => $name,
                    'type' => (string)($row['s_type'] ?? ''),
                ];
            }
        }

        $entries = [];
        foreach ($this->listMsDirectories($msDir) as $msName => $path) {
            $msMeta = $msByName[$msName] ?? ['id' => 0, 'uid' => '', 'name' => $msName];
            $includeIdKeys = strtoupper((string)($msMeta['type'] ?? '')) !== 'DYN';
            $routesInDb = $this->loadMsRoutesByMs((int)$msMeta['id'], $includeIdKeys);
            $routesList = $this->loadMsRoutesListByMs((int)$msMeta['id']);
            $controllersInDb = $this->loadMsControllersByMs((int)$msMeta['id']);

            $routeFiles = $this->scanMsRouteFiles($path);
            foreach ($routeFiles as $key => $files) {
                $inDb = $this->routeKeyExists($key, $routesInDb);
                if ($inDb) {
                    continue;
                }
                foreach ($files as $file) {
                    $entries[] = [
                        'ms_id' => $msMeta['id'],
                        'ms_uid' => $msMeta['uid'],
                        'ms_name' => $msMeta['name'],
                        'type' => 'route',
                        'key' => $key,
                        'file' => $file,
                        'status' => 'file_only',
                    ];
                }
            }

            $controllerFiles = $this->scanMsControllerFiles($path);
            foreach ($controllerFiles as $name => $file) {
                if (isset($controllersInDb[$name])) {
                    continue;
                }
                $entries[] = [
                    'ms_id' => $msMeta['id'],
                    'ms_uid' => $msMeta['uid'],
                    'ms_name' => $msMeta['name'],
                    'type' => 'controller',
                    'key' => $name,
                    'file' => $file,
                    'status' => 'file_only',
                ];
            }

            $msType = strtoupper((string)($msMeta['type'] ?? ''));
            foreach ($routesList as $routeMeta) {
                $routeName = (string)($routeMeta['s_name'] ?? '');
                $routeId = (string)($routeMeta['id'] ?? '');
                $fileKey = ($msType === 'DYN' && $routeName !== '') ? $routeName : $routeId;
                if ($fileKey === '' || $this->routeKeyHasFile($fileKey, $routeFiles)) {
                    continue;
                }
                $entries[] = [
                    'ms_id' => $msMeta['id'],
                    'ms_uid' => $msMeta['uid'],
                    'ms_name' => $msMeta['name'],
                    'type' => 'route',
                    'key' => $fileKey,
                    'file' => '',
                    'status' => 'db_only',
                ];
            }

            foreach ($controllersInDb as $ctrlName => $ctrlMeta) {
                if (isset($controllerFiles[$ctrlName])) {
                    continue;
                }
                $entries[] = [
                    'ms_id' => $msMeta['id'],
                    'ms_uid' => $msMeta['uid'],
                    'ms_name' => $msMeta['name'],
                    'type' => 'controller',
                    'key' => $ctrlName,
                    'file' => '',
                    'status' => 'db_only',
                ];
            }
        }

        return $entries;
    }

    private function listMsDirectories(string $msDir): array {
        $dirs = [];
        if (!is_dir($msDir)) {
            return $dirs;
        }
        $items = @scandir($msDir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $msDir . '/' . $item;
            if (is_dir($path)) {
                $dirs[$item] = $path;
            }
        }
        return $dirs;
    }

    private function scanMsRouteFiles(string $msPath): array {
        $matches = [];
        $files = glob($msPath . '/route.*.php') ?: [];
        foreach ($files as $file) {
            $base = basename($file);
            if (!preg_match('/^route\.([^.]+)\./', $base, $m)) {
                continue;
            }
            $key = $m[1];
            if (!isset($matches[$key])) {
                $matches[$key] = [];
            }
            $matches[$key][] = $file;
        }
        return $matches;
    }

    private function scanMsControllerFiles(string $msPath): array {
        $controllers = [];
        $files = glob($msPath . '/*.cls.php') ?: [];
        foreach ($files as $file) {
            $base = basename($file);
            $name = preg_replace('/\.cls\.php$/', '', $base);
            if ($name !== '') {
                $controllers[$name] = $file;
            }
        }
        return $controllers;
    }

    private function loadMsRoutesByMs(int $msId, bool $includeIdKeys = true): array {
        if ($msId <= 0) {
            return [];
        }
        $rows = $this->db->query(
            "SELECT id, s_name, uid FROM s_msroute WHERE s_ms_id = :ms AND livestatus != '0'",
            [':ms' => $msId]
        );
        $map = [];
        foreach ($rows as $row) {
            if (!empty($row['s_name'])) {
                $map[(string)$row['s_name']] = $row;
            }
            if ($includeIdKeys) {
                $map[(string)$row['id']] = $row;
            }
        }
        return $map;
    }

    private function loadMsRoutesListByMs(int $msId): array {
        if ($msId <= 0) {
            return [];
        }
        return $this->db->query(
            "SELECT id, s_name, uid FROM s_msroute WHERE s_ms_id = :ms AND livestatus != '0'",
            [':ms' => $msId]
        );
    }

    private function loadMsControllersByMs(int $msId): array {
        if ($msId <= 0) {
            return [];
        }
        $rows = $this->db->query(
            "SELECT id, s_name, uid, s_source_file FROM s_mscontroller WHERE s_ms_id = :ms AND livestatus != '0'",
            [':ms' => $msId]
        );
        $map = [];
        foreach ($rows as $row) {
            $keys = [];
            if (!empty($row['s_name'])) {
                $keys[] = (string)$row['s_name'];
            }
            if (!empty($row['s_source_file'])) {
                $fileStem = preg_replace('/\.cls\.php$/', '', basename((string)$row['s_source_file']));
                if ($fileStem !== '') {
                    $keys[] = $fileStem;
                }
            }
            foreach (array_unique($keys) as $key) {
                $map[$key] = $row;
            }
        }
        return $map;
    }

    private function routeKeyExists(string $key, array $routesInDb): bool {
        return isset($routesInDb[$key]);
    }

    private function routeKeyHasFile(string $key, array $routeFiles): bool {
        return isset($routeFiles[$key]);
    }

    private function deleteStrayFile(string $file, string $msName, bool $permanent): array {
        $radDir = rtrim((string)($this->runData['config']['dir']['rad'] ?? ''), '/');
        $msDir = $radDir . '/ms/' . $msName;
        $filePath = realpath($file);
        if ($filePath === false || !$this->isPathInDir($filePath, $msDir)) {
            return ['status' => 'danger', 'message' => 'Invalid file path.'];
        }
        if (!file_exists($filePath)) {
            return ['status' => 'warning', 'message' => 'File not found.'];
        }
        if ($permanent) {
            if (@unlink($filePath)) {
                return ['status' => 'success', 'message' => 'File deleted permanently.'];
            }
            return ['status' => 'danger', 'message' => 'Failed to delete file.'];
        }
        $trashDir = $radDir . '/data/trash/stray/' . $msName;
        if (!is_dir($trashDir)) {
            @mkdir($trashDir, 0777, true);
        }
        $target = $trashDir . '/' . basename($filePath);
        if (@rename($filePath, $target)) {
            return ['status' => 'success', 'message' => 'File moved to trash.'];
        }
        return ['status' => 'danger', 'message' => 'Failed to move file to trash.'];
    }

    private function generateStrayFile(int $msId, string $msName, string $type, string $key): array {
        $radDir = rtrim((string)($this->runData['config']['dir']['rad'] ?? ''), '/');
        $msDir = $radDir . '/ms/' . $msName;
        if (!is_dir($msDir)) {
            return ['status' => 'danger', 'message' => 'Microservicelet folder not found.'];
        }
        if ($type === 'controller') {
            $rows = $this->db->query(
                "SELECT s_name, s_source_file, s_class_name FROM s_mscontroller WHERE s_ms_id = :ms AND s_name = :name AND livestatus != '0'",
                [':ms' => $msId, ':name' => $key]
            );
            if (empty($rows)) {
                return ['status' => 'danger', 'message' => 'Controller record not found.'];
            }
            $sourceFile = trim((string)($rows[0]['s_source_file'] ?? ''));
            $fileName = $sourceFile !== '' ? basename($sourceFile) : ($key . '.cls.php');
            $file = $msDir . '/' . $fileName;
            if (file_exists($file)) {
                return ['status' => 'warning', 'message' => 'Controller file already exists.'];
            }
            $className = trim((string)($rows[0]['s_class_name'] ?? ''));
            if ($className === '') {
                $className = preg_replace('/[^A-Za-z0-9_]/', '', $key);
            }
            $content = "<?php

class {$className} {
    // TODO: Implement controller logic.
}
";
            if (@file_put_contents($file, $content) !== false) {
                return ['status' => 'success', 'message' => 'Controller file generated.'];
            }
            return ['status' => 'danger', 'message' => 'Failed to create controller file.'];
        }

        if ($type === 'route') {
            $routeRows = $this->db->query(
                "SELECT r.id, r.s_name, m.s_type FROM s_msroute r JOIN s_ms m ON m.id = r.s_ms_id
                 WHERE r.s_ms_id = :ms AND (r.s_name = :key OR r.id = :key_id) AND r.livestatus != '0'",
                [':ms' => $msId, ':key' => $key, ':key_id' => ctype_digit($key) ? (int)$key : 0]
            );
            if (empty($routeRows)) {
                return ['status' => 'danger', 'message' => 'Route record not found.'];
            }
            $route = $routeRows[0];
            $msType = strtoupper((string)($route['s_type'] ?? ''));
            $routeName = (string)($route['s_name'] ?? '');
            $routeId = (int)($route['id'] ?? 0);
            $fileKey = ($msType === 'DYN' && $routeName !== '') ? $routeName : (string)$routeId;
            if ($fileKey === '') {
                return ['status' => 'danger', 'message' => 'Route key not resolved.'];
            }
            $files = [
                $msDir . '/route.' . $fileKey . '.php',
                $msDir . '/route.' . $fileKey . '.pagepart.php',
                $msDir . '/route.' . $fileKey . '.prepart.php',
                $msDir . '/route.' . $fileKey . '.postpart.php',
            ];
            $created = 0;
            foreach ($files as $file) {
                if (file_exists($file)) {
                    continue;
                }
                $stub = "<?php
// TODO: Implement route logic.
";
                if (@file_put_contents($file, $stub) !== false) {
                    $created++;
                }
            }
            if ($created > 0) {
                return ['status' => 'success', 'message' => 'Route file(s) generated.'];
            }
            return ['status' => 'warning', 'message' => 'Route files already exist.'];
        }

        return ['status' => 'danger', 'message' => 'Unsupported type.'];
    }

    private function isPathInDir(string $path, string $dir): bool {
        $dirPath = realpath($dir);
        if ($dirPath === false) {
            return false;
        }
        return strncmp($path, $dirPath, strlen($dirPath)) === 0;
    }

    private function fetchChangelog(array $filters, int $limit = 100, int $offset = 0): array {
        $sql = "SELECT vh.*, e.s_name AS actor_name
                FROM s_version_history vh
                LEFT JOIN s_entity e ON e.id = vh.s_modified_by
                WHERE 1=1";
        $params = [];
        if ($filters['table'] !== '') {
            $sql .= " AND vh.s_db_table = :tbl";
            $params[':tbl'] = $filters['table'];
        }
        if ($filters['actor'] !== '') {
            $sql .= " AND vh.s_modified_by = :actor";
            $params[':actor'] = (int)$filters['actor'];
        }
        if ($filters['date_from'] !== '') {
            $sql .= " AND vh.s_modified_timestamp >= :df";
            $params[':df'] = $filters['date_from'] . ' 00:00:00';
        }
        if ($filters['date_to'] !== '') {
            $sql .= " AND vh.s_modified_timestamp <= :dt";
            $params[':dt'] = $filters['date_to'] . ' 23:59:59';
        }
        if ($filters['search'] !== '') {
            $sql .= " AND (vh.s_db_table LIKE :q OR CAST(vh.s_data_record_id AS CHAR) LIKE :q)";
            $params[':q'] = '%' . $filters['search'] . '%';
        }
        $this->applyChangelogTypeFilters($filters, $sql, $params);
        $sql .= " ORDER BY vh.s_modified_timestamp DESC, vh.id DESC LIMIT {$limit} OFFSET {$offset}";

        $rows = $this->db->query($sql, $params);
        foreach ($rows as &$row) {
            $row['is_code'] = in_array($row['s_db_table'], $this->getAllCodeTables(), true);
            $row['is_fs'] = in_array($row['s_db_table'], $this->fsTables, true);
            if (!empty($row['is_fs'])) {
                $decoded = $this->decodeDumpWithMeta($row['s_data_record_dump'] ?? '');
                if (is_array($decoded['data'] ?? null)) {
                    $row['fs_path'] = $decoded['data']['path'] ?? '';
                    $row['fs_source'] = $decoded['data']['source'] ?? '';
                    $row['fs_hash'] = $decoded['data']['hash'] ?? '';
                    $row['fs_size'] = $decoded['data']['size'] ?? '';
                }
            }
        }
        return $rows;
    }

    private function countChangelog(array $filters): int {
        $sql = "SELECT COUNT(*) AS cnt
                FROM s_version_history vh
                WHERE 1=1";
        $params = [];
        if ($filters['table'] !== '') {
            $sql .= " AND vh.s_db_table = :tbl";
            $params[':tbl'] = $filters['table'];
        }
        if ($filters['actor'] !== '') {
            $sql .= " AND vh.s_modified_by = :actor";
            $params[':actor'] = (int)$filters['actor'];
        }
        if ($filters['date_from'] !== '') {
            $sql .= " AND vh.s_modified_timestamp >= :df";
            $params[':df'] = $filters['date_from'] . ' 00:00:00';
        }
        if ($filters['date_to'] !== '') {
            $sql .= " AND vh.s_modified_timestamp <= :dt";
            $params[':dt'] = $filters['date_to'] . ' 23:59:59';
        }
        if ($filters['search'] !== '') {
            $sql .= " AND (vh.s_db_table LIKE :q OR CAST(vh.s_data_record_id AS CHAR) LIKE :q)";
            $params[':q'] = '%' . $filters['search'] . '%';
        }
        $this->applyChangelogTypeFilters($filters, $sql, $params);
        $rows = $this->db->query($sql, $params);
        return isset($rows[0]['cnt']) ? (int)$rows[0]['cnt'] : 0;
    }

    private function buildDiff($prev, $curr) {
        if (is_array($prev) && is_array($curr) && isset($prev['content'], $curr['content'])) {
            return [
                'path' => $curr['path'] ?? $prev['path'] ?? null,
                'source' => $curr['source'] ?? $prev['source'] ?? null,
                'content_diff' => $this->buildLineDiff((string)$prev['content'], (string)$curr['content']),
            ];
        }
        // If both are arrays, compute associative diff; else return raw current.
        if (is_array($prev) && is_array($curr)) {
            $diff = [
                'added' => [],
                'removed' => [],
                'changed' => [],
            ];
            foreach ($curr as $k => $v) {
                if (!array_key_exists($k, $prev)) {
                    $diff['added'][$k] = $v;
                } elseif ($prev[$k] !== $v) {
                    $diff['changed'][$k] = ['from' => $prev[$k], 'to' => $v];
                }
            }
            foreach ($prev as $k => $v) {
                if (!array_key_exists($k, $curr)) {
                    $diff['removed'][$k] = $v;
                }
            }
            return $diff;
        }
        return ['current' => $curr, 'previous' => $prev];
    }

    private function buildLineDiff(string $before, string $after): array {
        $oldLines = explode("\n", $before);
        $newLines = explode("\n", $after);
        $diff = [];
        $i = $j = 0;
        $oldCount = count($oldLines);
        $newCount = count($newLines);

        while ($i < $oldCount || $j < $newCount) {
            $oldLine = $oldLines[$i] ?? null;
            $newLine = $newLines[$j] ?? null;

            if ($oldLine !== null && $newLine !== null && $oldLine === $newLine) {
                $diff[] = ['type' => 'equal', 'old' => $oldLine, 'new' => $newLine, 'old_line' => $i + 1, 'new_line' => $j + 1];
                $i++; $j++;
                continue;
            }

            if ($oldLine !== null && $newLine !== null) {
                $diff[] = ['type' => 'replace', 'old' => $oldLine, 'new' => $newLine, 'old_line' => $i + 1, 'new_line' => $j + 1];
                $i++; $j++;
                continue;
            }

            if ($oldLine !== null) {
                $diff[] = ['type' => 'delete', 'old' => $oldLine, 'new' => '', 'old_line' => $i + 1, 'new_line' => null];
                $i++;
                continue;
            }

            if ($newLine !== null) {
                $diff[] = ['type' => 'insert', 'old' => '', 'new' => $newLine, 'old_line' => null, 'new_line' => $j + 1];
                $j++;
                continue;
            }
        }

        return $diff;
    }

    private function decodeDump($dump) {
        if ($dump === null || $dump === '') {
            return '';
        }

        $tryJson = static function ($value) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        };

        $tryUnserialize = static function ($value) {
            $decoded = @unserialize($value);
            if ($decoded !== false || $value === 'b:0;') {
                return $decoded;
            }
            return null;
        };

        $normalize = function ($value) {
            if (is_array($value) && $this->isListArray($value) && count($value) === 1 && is_array($value[0])) {
                return $value[0];
            }
            return $value;
        };

        $process = function ($raw) use ($tryJson, $tryUnserialize, $normalize) {
            if ($raw === null || $raw === '' || $raw === false) {
                return null;
            }

            $candidates = [$raw];
            if (function_exists('gzdecode')) {
                $candidates[] = @gzdecode($raw);
            }
            if (function_exists('gzuncompress')) {
                $candidates[] = @gzuncompress($raw);
            }
            if (function_exists('gzinflate')) {
                $candidates[] = @gzinflate($raw);
            }

            foreach ($candidates as $candidate) {
                if ($candidate === false || $candidate === null || $candidate === '') {
                    continue;
                }
                $json = $tryJson($candidate);
                if ($json !== null) {
                    return $normalize($json);
                }
                $unserialized = $tryUnserialize($candidate);
                if ($unserialized !== null) {
                    return $normalize($unserialized);
                }
            }

            foreach ($candidates as $candidate) {
                if ($candidate !== false && $candidate !== null && $candidate !== '') {
                    return $candidate;
                }
            }
            return null;
        };

        $direct = $process($dump);
        if ($direct !== null) {
            return $direct;
        }

        $base64 = base64_decode($dump, true);
        if ($base64 !== false) {
            $decoded = $process($base64);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return $dump;
    }

    private function decodeDumpWithMeta($dump): array {
        $format = 'raw';
        if ($dump === null || $dump === '') {
            return ['data' => '', 'format' => 'empty', 'raw_base64' => ''];
        }
        $raw = (string)$dump;

        $tryJson = static function ($value) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        };
        $tryUnserialize = static function ($value) {
            $decoded = @unserialize($value);
            if ($decoded !== false || $value === 'b:0;') {
                return $decoded;
            }
            return null;
        };

        $json = $tryJson($raw);
        if ($json !== null) {
            return ['data' => $json, 'format' => 'json', 'raw_base64' => base64_encode($raw)];
        }
        $unser = $tryUnserialize($raw);
        if ($unser !== null) {
            return ['data' => $unser, 'format' => 'serialized', 'raw_base64' => base64_encode($raw)];
        }
        $compressed = null;
        if (function_exists('gzdecode')) {
            $compressed = @gzdecode($raw);
        }
        if ($compressed === false || $compressed === null) {
            if (function_exists('gzuncompress')) {
                $compressed = @gzuncompress($raw);
            }
        }
        if (($compressed === false || $compressed === null) && function_exists('gzinflate')) {
            $compressed = @gzinflate($raw);
        }
        if ($compressed !== false && $compressed !== null && $compressed !== '') {
            $json = $tryJson($compressed);
            if ($json !== null) {
                return ['data' => $json, 'format' => 'gzip+json', 'raw_base64' => base64_encode($raw)];
            }
            $unser = $tryUnserialize($compressed);
            if ($unser !== null) {
                return ['data' => $unser, 'format' => 'gzip+serialized', 'raw_base64' => base64_encode($raw)];
            }
        }
        $base = base64_decode($raw, true);
        if ($base !== false) {
            $json = $tryJson($base);
            if ($json !== null) {
                return ['data' => $json, 'format' => 'base64+json', 'raw_base64' => base64_encode($raw)];
            }
            $unser = $tryUnserialize($base);
            if ($unser !== null) {
                return ['data' => $unser, 'format' => 'base64+serialized', 'raw_base64' => base64_encode($raw)];
            }
            $baseInflated = $this->inflateCandidate($base);
            if ($baseInflated !== null) {
                $json = $tryJson($baseInflated);
                if ($json !== null) {
                    return ['data' => $json, 'format' => 'base64+gzip+json', 'raw_base64' => base64_encode($raw)];
                }
                $unser = $tryUnserialize($baseInflated);
                if ($unser !== null) {
                    return ['data' => $unser, 'format' => 'base64+gzip+serialized', 'raw_base64' => base64_encode($raw)];
                }
            }
        }

        $decoded = $this->decodeDump($raw);
        $format = is_array($decoded) ? 'structured' : 'raw';
        return [
            'data' => $decoded,
            'format' => $format,
            'raw_base64' => base64_encode($raw),
        ];
    }

    private function inflateCandidate(string $value): ?string {
        $decoded = null;
        if (function_exists('gzdecode')) {
            $decoded = @gzdecode($value);
        }
        if ($decoded === false || $decoded === null) {
            if (function_exists('gzuncompress')) {
                $decoded = @gzuncompress($value);
            }
        }
        if (($decoded === false || $decoded === null) && function_exists('gzinflate')) {
            $decoded = @gzinflate($value);
        }
        if ($decoded === false || $decoded === null || $decoded === '') {
            return null;
        }
        return $decoded;
    }

    private function sanitizeDumpValue($value) {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $val) {
                $clean[$key] = $this->sanitizeDumpValue($val);
            }
            return $clean;
        }
        if (is_string($value)) {
            if ($value === '') {
                return $value;
            }
            if (!preg_match('//u', $value)) {
                $encoded = base64_encode($value);
                $preview = substr($encoded, 0, 120);
                $suffix = strlen($encoded) > 120 ? '…' : '';
                return '[binary base64] ' . $preview . $suffix;
            }
        }
        return $value;
    }

    private function prepareDumpForJson($value) {
        if (is_string($value)) {
            if ($value === '') {
                return $value;
            }
            if (!preg_match('//u', $value)) {
                return [
                    'encoding' => 'base64',
                    'value' => base64_encode($value),
                ];
            }
        }
        return $value;
    }

    private function collectInsights(int $days): array {
        $limit = 500; // guardrail for aggregation
        $days = max(1, (int)$days);

        // Primary query: windowed by days
        $rows = $this->db->query("
            SELECT vh.id, vh.s_db_table, vh.s_data_record_id, vh.s_data_record_dump, vh.s_version_number,
                   vh.s_modified_by, vh.s_modified_timestamp, e.s_name AS actor_name
            FROM s_version_history vh
            LEFT JOIN s_entity e ON e.id = vh.s_modified_by
            WHERE vh.s_modified_timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
            ORDER BY vh.s_modified_timestamp DESC
            LIMIT {$limit}", []);
        $usedFallback = false;

        // Fallback: if nothing in the requested window, pull most recent records without date filter
        if (!is_array($rows) || count($rows) === 0) {
            $rows = $this->db->query("
                SELECT vh.id, vh.s_db_table, vh.s_data_record_id, vh.s_data_record_dump, vh.s_version_number,
                       vh.s_modified_by, vh.s_modified_timestamp, e.s_name AS actor_name
                FROM s_version_history vh
                LEFT JOIN s_entity e ON e.id = vh.s_modified_by
                ORDER BY vh.s_modified_timestamp DESC
                LIMIT {$limit}", []);
            $usedFallback = true;
        }
        if (!is_array($rows)) {
            $rows = [];
        }

        $touchesOverTime = [];
        $byTable = [];
        $byActor = [];
        $fieldsTouched = [];
        $latestChanges = [];

        foreach ($rows as $row) {
            $day = substr($row['s_modified_timestamp'], 0, 10);
            $touchesOverTime[$day] = ($touchesOverTime[$day] ?? 0) + 1;
            $byTable[$row['s_db_table']] = ($byTable[$row['s_db_table']] ?? 0) + 1;
            $actorKey = $row['actor_name'] ?: ($row['s_modified_by'] ?: 'Unknown');
            $byActor[$actorKey] = ($byActor[$actorKey] ?? 0) + 1;

            $payload = $this->decodeDump($row['s_data_record_dump'] ?? '');
            if (is_array($payload)) {
                foreach (array_keys($payload) as $field) {
                    $fieldsTouched[$field] = ($fieldsTouched[$field] ?? 0) + 1;
                }
            }

            if (count($latestChanges) < 8) {
                $latestChanges[] = [
                    'table' => $row['s_db_table'],
                    'record_id' => $row['s_data_record_id'],
                    'version' => $row['s_version_number'],
                    'actor' => $row['actor_name'] ?: $row['s_modified_by'],
                    'timestamp' => $row['s_modified_timestamp'],
                ];
            }
        }

        ksort($touchesOverTime);
        $fs = $this->collectFilesystemInsights();
        $fsVersions = $this->collectVersionedFiles();

        return [
            'period_days' => $days,
            'counts' => [
                'total' => count($rows),
                'tables' => count($byTable),
                'actors' => count($byActor),
                'files_scanned' => $fs['files_scanned'],
                'versioned_files' => $fsVersions['count'],
            ],
            'used_fallback' => $usedFallback,
            'touches_over_time' => $touchesOverTime,
            'by_table' => $this->topN($byTable, 8),
            'by_actor' => $this->topN($byActor, 8),
            'fields_touched' => $this->topN($fieldsTouched, 10),
            'latest_changes' => $latestChanges,
            'fs' => $fs,
            'fs_versions' => $fsVersions,
        ];
    }

    private function collectFilesystemInsights(): array {
        $totalsByExt = [];
        $totalsByDir = [];
        $recent = [];
        $filesScanned = 0;
        $maxRecent = 10;

        $allowedExts = array_map('strtolower', $this->codeExtensions);

        foreach ($this->codePaths as $path) {
            $base = rtrim($path, '/');
            if (!is_dir($base)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                $name = $file->getFilename();
                // allow tpl.php by suffix check
                $matchesTpl = (substr($name, -7) === 'tpl.php');
                if (!$matchesTpl && !in_array($ext, $allowedExts, true)) {
                    continue;
                }
                $filesScanned++;
                $size = $file->getSize();
                $mtime = $file->getMTime();
                $relDir = trim(str_replace($base, '', $file->getPath()), '/');
                $keyDir = $relDir === '' ? basename($base) : basename($base) . '/' . $relDir;
                $totalsByExt[$matchesTpl ? 'tpl.php' : $ext] = ($totalsByExt[$matchesTpl ? 'tpl.php' : $ext] ?? 0) + $size;
                $totalsByDir[$keyDir] = ($totalsByDir[$keyDir] ?? 0) + $size;

                $recent[] = [
                    'path' => $file->getPathname(),
                    'mtime' => $mtime,
                    'size' => $size,
                ];
                if (count($recent) > $maxRecent * 5) {
                    usort($recent, function ($a, $b) {
                        return $b['mtime'] <=> $a['mtime'];
                    });
                    $recent = array_slice($recent, 0, $maxRecent * 2);
                }
            }
        }

        usort($recent, function ($a, $b) {
            return $b['mtime'] <=> $a['mtime'];
        });
        $recent = array_slice($recent, 0, $maxRecent);

        return [
            'files_scanned' => $filesScanned,
            'by_extension_bytes' => $this->topN($totalsByExt, 8),
            'by_directory_bytes' => $this->topN($totalsByDir, 8),
            'recent_files' => $recent,
            'roots' => $this->codePaths,
            'extensions' => $this->codeExtensions,
        ];
    }

    private function collectVersionedFiles(): array {
        $entries = [];
        foreach ($this->versionDirs as $root) {
            $base = rtrim($root, '/');
            if (!is_dir($base)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }
                if ($file->getFilename() === 'manifest.json') {
                    $manifest = json_decode(@file_get_contents($file->getPathname()), true);
                    if (!is_array($manifest)) {
                        continue;
                    }
                    $template = basename($file->getPath());
                    $latest = $manifest[0] ?? null;
                    if ($latest) {
                        $entries[] = [
                            'template' => $template,
                            'path' => $file->getPath(),
                            'latest_id' => $latest['id'] ?? '',
                            'latest_timestamp' => $latest['timestamp'] ?? null,
                            'versions' => count($manifest),
                        ];
                    }
                }
            }
        }
        usort($entries, function ($a, $b) {
            return ($b['latest_timestamp'] ?? 0) <=> ($a['latest_timestamp'] ?? 0);
        });
        return [
            'entries' => array_slice($entries, 0, 10),
            'count' => count($entries),
            'roots' => $this->versionDirs,
        ];
    }

    private function topN(array $map, int $n): array {
        arsort($map);
        return array_slice($map, 0, $n, true);
    }

    private function isListArray(array $value): bool {
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function getLogDates(string $logDir): array {
        if ($logDir === '' || !is_dir($logDir)) {
            return [];
        }
        $dates = [];
        foreach (glob($logDir . '/*', GLOB_ONLYDIR) as $yearPath) {
            $year = basename($yearPath);
            foreach (glob($yearPath . '/*', GLOB_ONLYDIR) as $monthPath) {
                $month = basename($monthPath);
                foreach (glob($monthPath . '/*', GLOB_ONLYDIR) as $dayPath) {
                    if ($this->dayHasLogs($dayPath)) {
                        $day = basename($dayPath);
                        $dates[] = sprintf('%s-%s-%s', $year, $month, $day);
                    }
                }
            }
        }
        rsort($dates);
        return array_values(array_unique($dates));
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

    private function getLogFilesForDay(string $dayPath, string $kind): array {
        $files = glob($dayPath . '/*/' . $kind . '.log') ?: [];
        $legacy = $dayPath . '/' . $kind . '.log';
        if (is_file($legacy)) {
            $files[] = $legacy;
        }
        return $files;
    }

    private function countLogsForDay(string $dayPath): array {
        $counts = ['access' => 0, 'error' => 0, 'sql' => 0];
        foreach ($counts as $kind => $_) {
            $files = $this->getLogFilesForDay($dayPath, $kind);
            $total = 0;
            foreach ($files as $file) {
                $total += $this->countLines($file);
            }
            $counts[$kind] = $total;
        }
        return $counts;
    }

    private function countLines(string $file): int {
        if (!is_file($file)) {
            return 0;
        }
        $lines = 0;
        $handle = fopen($file, 'r');
        if (!$handle) {
            return 0;
        }
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line !== false) {
                $lines++;
            }
        }
        fclose($handle);
        return $lines;
    }

    private function countForgotPasswordRows(array $filters): int {
        $params = [];
        $where = $this->buildForgotPasswordWhere($filters, $params, true);
        $sql = "SELECT COUNT(*) AS total
                FROM s_password_reset pr
                LEFT JOIN s_entity e ON e.id = pr.s_entity_id
                {$where}";
        $rows = $this->db->query($sql, $params);
        return (int)($rows[0]['total'] ?? 0);
    }

    private function fetchForgotPasswordRows(array $filters, int $limit, int $offset): array {
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);
        $params = [];
        $where = $this->buildForgotPasswordWhere($filters, $params, true);
        $sql = "SELECT pr.id, pr.uid, pr.livestatus, pr.createstamp, pr.s_expires_at, pr.s_used_at,
                       pr.s_ip, pr.s_user_agent, pr.s_entity_id,
                       e.s_name, e.s_identity, e.s_email
                FROM s_password_reset pr
                LEFT JOIN s_entity e ON e.id = pr.s_entity_id
                {$where}
                ORDER BY pr.createstamp DESC
                LIMIT {$limit} OFFSET {$offset}";
        $rows = $this->db->query($sql, $params);
        foreach ($rows as &$row) {
            $row['status'] = $this->resolveForgotPasswordStatus($row);
        }
        return $rows;
    }

    private function summarizeForgotPassword(array $filters): array {
        $params = [];
        $where = $this->buildForgotPasswordWhere($filters, $params, false);
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN pr.s_used_at IS NOT NULL THEN 1 ELSE 0 END) AS used_count,
                    SUM(CASE WHEN pr.s_used_at IS NULL AND pr.s_expires_at < NOW() THEN 1 ELSE 0 END) AS expired_count,
                    SUM(CASE WHEN pr.livestatus = '2' THEN 1 ELSE 0 END) AS archived_count,
                    SUM(CASE WHEN pr.livestatus = '1' AND pr.s_used_at IS NULL AND pr.s_expires_at >= NOW() THEN 1 ELSE 0 END) AS active_count
                FROM s_password_reset pr
                LEFT JOIN s_entity e ON e.id = pr.s_entity_id
                {$where}";
        $rows = $this->db->query($sql, $params);
        $row = $rows[0] ?? [];
        return [
            'total' => (int)($row['total'] ?? 0),
            'active' => (int)($row['active_count'] ?? 0),
            'used' => (int)($row['used_count'] ?? 0),
            'expired' => (int)($row['expired_count'] ?? 0),
            'archived' => (int)($row['archived_count'] ?? 0),
        ];
    }

    private function buildForgotPasswordWhere(array $filters, array &$params, bool $includeStatus): string {
        $clauses = [];
        if (!empty($filters['date_from'])) {
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
            $clauses[] = 'pr.createstamp >= :date_from';
        }
        if (!empty($filters['date_to'])) {
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            $clauses[] = 'pr.createstamp <= :date_to';
        }
        if (!empty($filters['ip'])) {
            $params[':ip'] = $filters['ip'];
            $clauses[] = 'pr.s_ip = :ip';
        }
        if (!empty($filters['q'])) {
            $params[':q'] = '%' . $filters['q'] . '%';
            $clauses[] = '(e.s_identity LIKE :q OR e.s_email LIKE :q OR e.s_name LIKE :q OR pr.uid LIKE :q)';
        }
        if ($includeStatus && !empty($filters['status'])) {
            $statusClause = $this->forgotPasswordStatusClause($filters['status']);
            if ($statusClause !== '') {
                $clauses[] = $statusClause;
            }
        }
        return !empty($clauses) ? 'WHERE ' . implode(' AND ', $clauses) : '';
    }

    private function forgotPasswordStatusClause(string $status): string {
        switch ($status) {
            case 'active':
                return "pr.livestatus = '1' AND pr.s_used_at IS NULL AND pr.s_expires_at >= NOW()";
            case 'used':
                return 'pr.s_used_at IS NOT NULL';
            case 'expired':
                return 'pr.s_used_at IS NULL AND pr.s_expires_at < NOW()';
            case 'archived':
                return "pr.livestatus = '2'";
            case 'inactive':
                return "pr.livestatus = '0'";
            case 'suspended':
                return "pr.livestatus = '3'";
            default:
                return '';
        }
    }

    private function resolveForgotPasswordStatus(array $row): string {
        $livestatus = (string)($row['livestatus'] ?? '');
        if (!empty($row['s_used_at'])) {
            return 'used';
        }
        if (!empty($row['s_expires_at']) && strtotime((string)$row['s_expires_at']) < time()) {
            return 'expired';
        }
        if ($livestatus === '2') {
            return 'archived';
        }
        if ($livestatus === '3') {
            return 'suspended';
        }
        if ($livestatus === '0') {
            return 'inactive';
        }
        return 'active';
    }

    private function augmentForgotPasswordLogs(array $rows): array {
        if (empty($rows)) {
            return $rows;
        }
        $dates = [];
        foreach ($rows as $row) {
            if (!empty($row['createstamp'])) {
                $dates[] = substr((string)$row['createstamp'], 0, 10);
            }
        }
        $dates = array_values(array_unique($dates));
        $accessIndex = $this->collectForgotPasswordAccessLogs($dates);
        $errorIndex = $this->collectForgotPasswordErrorLogs($dates);

        foreach ($rows as &$row) {
            $date = !empty($row['createstamp']) ? substr((string)$row['createstamp'], 0, 10) : null;
            $ip = (string)($row['s_ip'] ?? '');
            $row['access_log'] = $date && $ip !== '' ? ($accessIndex[$date][$ip] ?? null) : null;
            $row['error_log'] = $date ? ($errorIndex[$date] ?? null) : null;
        }
        return $rows;
    }

    private function collectForgotPasswordAccessLogs(array $dates): array {
        $logDir = rtrim($this->runData['config']['dir']['log'] ?? '', '/');
        if ($logDir === '' || empty($dates)) {
            return [];
        }
        $index = [];
        foreach ($dates as $date) {
            $dayPath = $this->resolveLogDayPath($logDir, $date);
            if ($dayPath === null) {
                continue;
            }
            $files = $this->getLogFilesForDay($dayPath, 'access');
            if (empty($files)) {
                continue;
            }
            $byIp = [];
            foreach ($files as $file) {
                $handle = fopen($file, 'r');
                if (!$handle) {
                    continue;
                }
                while (!feof($handle)) {
                    $line = fgets($handle);
                    if ($line === false) {
                        continue;
                    }
                    if (stripos($line, '/login/forgotpassword') === false) {
                        continue;
                    }
                    $parsed = $this->parseAccessLogLine($line);
                    if (!$parsed) {
                        continue;
                    }
                    $payload = $parsed['payload'];
                    $uri = (string)($payload['uri'] ?? '');
                    if ($uri === '' || stripos($uri, '/login/forgotpassword') === false) {
                        continue;
                    }
                    $ip = (string)($payload['ip'] ?? '');
                    if ($ip === '') {
                        continue;
                    }
                    if (!isset($byIp[$ip])) {
                        $byIp[$ip] = [
                            'count' => 0,
                            'last_time' => '',
                            'last_uri' => '',
                            'last_method' => '',
                        ];
                    }
                    $byIp[$ip]['count']++;
                    if ($parsed['timestamp'] > $byIp[$ip]['last_time']) {
                        $byIp[$ip]['last_time'] = $parsed['timestamp'];
                        $byIp[$ip]['last_uri'] = $uri;
                        $byIp[$ip]['last_method'] = (string)($payload['method'] ?? '');
                    }
                }
                fclose($handle);
            }
            if (!empty($byIp)) {
                $index[$date] = $byIp;
            }
        }
        return $index;
    }

    private function collectForgotPasswordErrorLogs(array $dates): array {
        $logDir = rtrim($this->runData['config']['dir']['log'] ?? '', '/');
        if ($logDir === '' || empty($dates)) {
            return [];
        }
        $index = [];
        foreach ($dates as $date) {
            $dayPath = $this->resolveLogDayPath($logDir, $date);
            if ($dayPath === null) {
                continue;
            }
            $files = $this->getLogFilesForDay($dayPath, 'error');
            if (empty($files)) {
                continue;
            }
            $summary = [
                'count' => 0,
                'last_time' => '',
                'last_message' => '',
                'last_uri' => '',
            ];
            foreach ($files as $file) {
                $handle = fopen($file, 'r');
                if (!$handle) {
                    continue;
                }
                while (!feof($handle)) {
                    $line = fgets($handle);
                    if ($line === false) {
                        continue;
                    }
                    if (stripos($line, '/login/forgotpassword') === false) {
                        continue;
                    }
                    $parsed = $this->parseErrorLogLine($line);
                    if (!$parsed) {
                        continue;
                    }
                    $payload = $parsed['payload'];
                    $uri = (string)($payload['uri'] ?? '');
                    if ($uri === '' || stripos($uri, '/login/forgotpassword') === false) {
                        continue;
                    }
                    $summary['count']++;
                    if ($parsed['timestamp'] > $summary['last_time']) {
                        $summary['last_time'] = $parsed['timestamp'];
                        $summary['last_message'] = $parsed['message'];
                        $summary['last_uri'] = $uri;
                    }
                }
                fclose($handle);
            }
            if ($summary['count'] > 0) {
                $index[$date] = $summary;
            }
        }
        return $index;
    }

    private function resolveLogDayPath(string $logDir, string $date): ?string {
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            return null;
        }
        $path = rtrim($logDir, '/') . '/' . $parts[0] . '/' . $parts[1] . '/' . $parts[2];
        return is_dir($path) ? $path : null;
    }

    private function parseAccessLogLine(string $line): ?array {
        if (!preg_match('/^\[(.*?)\]:\s*(\{.*\})$/', trim($line), $matches)) {
            return null;
        }
        $payload = json_decode($matches[2], true);
        if (!is_array($payload)) {
            return null;
        }
        return [
            'timestamp' => $matches[1],
            'payload' => $payload,
        ];
    }

    private function parseErrorLogLine(string $line): ?array {
        if (!preg_match('/^\[(.*?)\]:\s*(.*?)\s*\\|\\|\\s*(\{.*\})$/', trim($line), $matches)) {
            return null;
        }
        $payload = json_decode($matches[3], true);
        if (!is_array($payload)) {
            return null;
        }
        return [
            'timestamp' => $matches[1],
            'message' => trim($matches[2]),
            'payload' => $payload,
        ];
    }

    private function purgeLogsOlderThan(string $logDir, int $days): int {
        if ($logDir === '' || !is_dir($logDir)) {
            return 0;
        }
        $cutoff = new \DateTime('-' . $days . ' days');
        $deleted = 0;
        foreach (glob($logDir . '/*', GLOB_ONLYDIR) as $yearPath) {
            $year = basename($yearPath);
            foreach (glob($yearPath . '/*', GLOB_ONLYDIR) as $monthPath) {
                $month = basename($monthPath);
                foreach (glob($monthPath . '/*', GLOB_ONLYDIR) as $dayPath) {
                    $day = basename($dayPath);
                    $dateObj = \DateTime::createFromFormat('Y-m-d', sprintf('%s-%s-%s', $year, $month, $day));
                    if (!$dateObj || $dateObj >= $cutoff) {
                        continue;
                    }
                    $hourDirs = glob($dayPath . '/*', GLOB_ONLYDIR) ?: [];
                    foreach ($hourDirs as $hourDir) {
                        foreach (['access', 'error', 'sql'] as $kind) {
                            $file = $hourDir . '/' . $kind . '.log';
                            if (is_file($file) && @unlink($file)) {
                                $deleted++;
                            }
                        }
                        if (is_dir($hourDir) && count(glob($hourDir . '/*')) === 0) {
                            @rmdir($hourDir);
                        }
                    }
                    foreach (['access', 'error', 'sql'] as $kind) {
                        $legacy = $dayPath . '/' . $kind . '.log';
                        if (is_file($legacy) && @unlink($legacy)) {
                            $deleted++;
                        }
                    }
                }
            }
        }
        return $deleted;
    }

    private function getLatestQueueRun(string $logDir): ?array {
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
                    $files = glob($dayPath . '/*/queue.log') ?: [];
                    $legacy = $dayPath . '/queue.log';
                    if (is_file($legacy)) {
                        $files[] = $legacy;
                    }
                    if (empty($files)) {
                        continue;
                    }
                    $latest = null;
                    foreach ($files as $file) {
                        $line = $this->readLastLogLine($file);
                        if (!$line) {
                            continue;
                        }
                        $entry = $this->parseQueueLogLine($line);
                        if (!$entry) {
                            continue;
                        }
                        if ($latest === null || strcmp($entry['timestamp'], $latest['timestamp']) > 0) {
                            $latest = $entry;
                        }
                    }
                    if ($latest) {
                        return $latest;
                    }
                }
            }
        }
        return null;
    }

    private function readLastLogLine(string $file): ?string {
        try {
            $log = new \SplFileObject($file, 'r');
        } catch (\RuntimeException $e) {
            return null;
        }
        $log->seek(PHP_INT_MAX);
        $line = '';
        while ($log->key() > 0 && trim($line) === '') {
            $line = (string)$log->current();
            if (trim($line) !== '') {
                break;
            }
            $log->seek($log->key() - 1);
        }
        $line = trim($line);
        return $line !== '' ? $line : null;
    }

    private function parseQueueLogLine(string $line): ?array {
        if (!preg_match('/^\[(.*?)\]:\s*(\{.*\})$/', trim($line), $matches)) {
            return null;
        }
        $payload = json_decode($matches[2], true);
        if (!is_array($payload)) {
            return null;
        }
        return [
            'timestamp' => $matches[1],
            'message' => $payload['message'] ?? '',
            'context' => $payload['context'] ?? [],
        ];
    }

    private function runWorkspaceSqlAudit(): array {
        $reportDir = $this->getWorkspaceAuditDir();
        $allow = $this->getConfigJson('workspace_audit_allowlist') ?? [];
        $issues = [];
        $fileCount = 0;

        $msRows = $this->db->select('s_ms', ['livestatus' => '1', 's_scope' => 'workspace'], true);
        foreach ($msRows as $ms) {
            $msId = (int)($ms['id'] ?? 0);
            $msName = $ms['s_name'] ?? '';
            if ($msName === '') {
                continue;
            }
            $msDir = rtrim($this->runData['config']['dir']['ms'] ?? '', '/') . '/' . $msName;
            if (!is_dir($msDir)) {
                continue;
            }
            $files = glob($msDir . '/route.*.php');
            if (!$files) {
                continue;
            }
            foreach ($files as $file) {
                $fileCount++;
                $content = @file_get_contents($file);
                if ($content === false || trim($content) === '') {
                    continue;
                }
                $issues = array_merge($issues, $this->scanWorkspaceSqlInFile($msId, $msName, $file, $content, $allow));
            }
            $tplName = trim((string)($ms['s_tpl_name'] ?? ''));
            if ($tplName !== '') {
                $tplPath = rtrim($this->runData['config']['dir']['theme'] ?? '', '/') . '/' . $tplName . '.tpl.php';
                if (is_file($tplPath)) {
                    $fileCount++;
                    $content = @file_get_contents($tplPath);
                    if ($content !== false && trim($content) !== '') {
                        $issues = array_merge($issues, $this->scanWorkspaceTemplateFile($msId, $msName, $tplName, $tplPath, $content, $allow));
                    }
                }
            }
        }

        $generatedAt = date('Y-m-d H:i:s');
        $report = [
            'generated_at' => $generatedAt,
            'summary' => [
                'ms_total' => count($msRows),
                'file_total' => $fileCount,
                'issue_total' => count($issues),
            ],
            'issues' => $issues,
        ];
        $reportFile = 'workspace_audit_' . date('Ymd_His') . '.json';
        $path = rtrim($reportDir, '/') . '/' . $reportFile;
        file_put_contents($path, json_encode($report, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        $report['report_file'] = $reportFile;
        return $report;
    }

    private function scanWorkspaceSqlInFile(int $msId, string $msName, string $file, string $content, array $allow): array {
        if (strpos($content, '->query(') === false && strpos($content, '->select(') === false && strpos($content, '->update(') === false && strpos($content, '->delete(') === false) {
            return [];
        }
        $routeMeta = $this->resolveRouteMeta($msId, $file);
        $issues = [];
        $calls = $this->findDbCalls($content);
        foreach ($calls as $call) {
            $tables = $this->extractAuditTables($call);
            if (empty($tables)) {
                continue;
            }
            if ($this->hasSpaceScope($call['code'])) {
                continue;
            }
            $key = $this->buildWorkspaceAuditKey($msName, $file, $call['type'], $call['code'], $routeMeta['route_id'] ?? null);
            if (in_array($key, $allow, true)) {
                continue;
            }
            $issues[] = [
                'source' => 'route',
                'ms_name' => $msName,
                'route_id' => $routeMeta['route_id'],
                'route_uid' => $routeMeta['route_uid'],
                'route_name' => $routeMeta['route_name'],
                'file' => $this->shortenPath($file),
                'call_type' => $call['type'],
                'tables' => $tables,
                'snippet' => $call['snippet'],
                'key' => $key,
            ];
        }
        return $issues;
    }

    private function scanWorkspaceTemplateFile(int $msId, string $msName, string $tplName, string $file, string $content, array $allow): array {
        if (strpos($content, '->query(') === false && strpos($content, '->select(') === false && strpos($content, '->update(') === false && strpos($content, '->delete(') === false) {
            return [];
        }
        $issues = [];
        $calls = $this->findDbCalls($content);
        foreach ($calls as $call) {
            $tables = $this->extractAuditTables($call);
            if (empty($tables)) {
                continue;
            }
            if ($this->hasSpaceScope($call['code'])) {
                continue;
            }
            $key = $this->buildWorkspaceAuditKey($msName, $file, $call['type'], $call['code'], null);
            if (in_array($key, $allow, true)) {
                continue;
            }
            $issues[] = [
                'source' => 'template',
                'ms_name' => $msName,
                'route_id' => null,
                'route_uid' => null,
                'route_name' => null,
                'template' => $tplName,
                'file' => $this->shortenPath($file),
                'call_type' => $call['type'],
                'tables' => $tables,
                'snippet' => $call['snippet'],
                'key' => $key,
            ];
        }
        return $issues;
    }

    private function findDbCalls(string $content): array {
        $patterns = [
            'query' => '/->query\\(([^;]+)\\);/s',
            'select' => '/->select\\(([^;]+)\\);/s',
            'update' => '/->update\\(([^;]+)\\);/s',
            'delete' => '/->delete\\(([^;]+)\\);/s',
        ];
        $calls = [];
        foreach ($patterns as $type => $regex) {
            if (preg_match_all($regex, $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $calls[] = [
                        'type' => $type,
                        'code' => $match,
                        'snippet' => $this->clipSnippet($match),
                    ];
                }
            }
        }
        return $calls;
    }

    private function extractAuditTables(array $call): array {
        $type = $call['type'] ?? '';
        $code = $call['code'] ?? '';
        $tables = [];

        if (in_array($type, ['select', 'update', 'delete'], true)) {
            if (preg_match('/^\s*[\'"]([a-zA-Z0-9_]+)[\'"]\s*,/s', $code, $match)) {
                $table = strtolower($match[1]);
                if (str_starts_with($table, 'a_')) {
                    $tables[] = $table;
                }
            }
            return $tables;
        }

        if ($type === 'query') {
            $tables = $this->extractTablesFromSql($code);
            return $tables;
        }

        return $tables;
    }

    private function extractTablesFromSql(string $code): array {
        if (!preg_match('/[\'"]([^\'"]+)[\'"]/', $code, $match)) {
            return [];
        }
        $sql = strtolower($match[1]);
        $tables = [];
        $patterns = [
            '/\bfrom\s+([a-z0-9_]+)/',
            '/\bjoin\s+([a-z0-9_]+)/',
            '/\bupdate\s+([a-z0-9_]+)/',
            '/\binsert\s+into\s+([a-z0-9_]+)/',
            '/\bdelete\s+from\s+([a-z0-9_]+)/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $sql, $matches)) {
                foreach ($matches[1] as $table) {
                    if (str_starts_with($table, 'a_')) {
                        $tables[] = $table;
                    }
                }
            }
        }
        return array_values(array_unique($tables));
    }

    private function hasSpaceScope(string $code): bool {
        return (bool)preg_match('/\bspace_id\b|\bs_space_id\b|\bspaceId\b|\:space_id\b/i', $code);
    }

    private function buildWorkspaceAuditKey(string $msName, string $file, string $type, string $code, ?int $routeId): string {
        $routeTag = $routeId ? ('route' . $routeId) : 'route';
        return $msName . '/' . $routeTag . '/' . basename($file) . ':' . $type . ':' . substr(md5($code), 0, 12);
    }

    private function resolveRouteMeta(int $msId, string $file): array {
        $routeId = null;
        if (preg_match('/route\.(\d+)\./', basename($file), $matches)) {
            $routeId = (int)$matches[1];
        }
        if (!$routeId || $msId <= 0) {
            return ['route_id' => $routeId, 'route_uid' => null, 'route_name' => null];
        }
        static $cache = [];
        $cacheKey = $msId . ':' . $routeId;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }
        $rows = $this->db->select('s_msroute', ['id' => $routeId, 's_ms_id' => $msId], true);
        if (!empty($rows[0])) {
            $cache[$cacheKey] = [
                'route_id' => $routeId,
                'route_uid' => $rows[0]['uid'] ?? null,
                'route_name' => $rows[0]['s_name'] ?? null,
            ];
        } else {
            $cache[$cacheKey] = ['route_id' => $routeId, 'route_uid' => null, 'route_name' => null];
        }
        return $cache[$cacheKey];
    }

    private function clipSnippet(string $code): string {
        $compact = trim(preg_replace('/\s+/', ' ', $code));
        if (strlen($compact) > 200) {
            return substr($compact, 0, 200) . '…';
        }
        return $compact;
    }

    private function getWorkspaceAuditDir(): string {
        $baseRoot = rtrim($this->runData['config']['dir']['rad'] ?? '', '/');
        $dir = $baseRoot . '/data/health/workspace_audit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function loadWorkspaceAuditReport(): array {
        $reportDir = $this->getWorkspaceAuditDir();
        $file = $this->getConfigValue('workspace_audit_last_report') ?? '';
        $file = basename($file);
        $path = $file !== '' ? $reportDir . '/' . $file : '';
        if ($path !== '' && is_file($path)) {
            $content = file_get_contents($path);
            $decoded = json_decode((string)$content, true);
            if (is_array($decoded)) {
                $decoded['report_file'] = $file;
                return $decoded;
            }
        }
        return [
            'generated_at' => null,
            'summary' => [
                'ms_total' => 0,
                'file_total' => 0,
                'issue_total' => 0,
            ],
            'issues' => [],
            'report_file' => null,
        ];
    }

    private function getAllCodeTables(): array {
        return array_values(array_unique(array_merge($this->codeTables, $this->fsTables)));
    }

    private function applyChangelogTypeFilters(array $filters, string &$sql, array &$params): void {
        $codeTables = $this->getAllCodeTables();
        if (!empty($filters['code_only'])) {
            $this->appendTableFilter($sql, $params, $codeTables, 'ct');
            return;
        }

        $changeType = $filters['change_type'] ?? '';
        if ($changeType === 'db') {
            $sql .= " AND vh.s_db_table LIKE 's\\_%'";
        } elseif ($changeType === 'code') {
            $this->appendTableFilter($sql, $params, $codeTables, 'ct');
        } elseif ($changeType === 'fs') {
            $this->appendTableFilter($sql, $params, $this->fsTables, 'fs');
        }

        $source = $filters['code_source'] ?? '';
        if (in_array($source, ['ms', 'theme', 'vendor'], true)) {
            $sql .= " AND vh.s_db_table = :fs_source";
            $params[':fs_source'] = 'fs_' . $source;
        }
    }

    private function appendTableFilter(string &$sql, array &$params, array $tables, string $prefix): void {
        if (empty($tables)) {
            return;
        }
        $placeholders = [];
        foreach (array_values($tables) as $i => $tbl) {
            $k = ':' . $prefix . $i;
            $placeholders[] = $k;
            $params[$k] = $tbl;
        }
        $sql .= " AND vh.s_db_table IN (" . implode(',', $placeholders) . ")";
    }

    private function shortenPath(string $path): string {
        $radDir = rtrim($this->runData['config']['dir']['rad'] ?? '', '/');
        if ($radDir !== '' && strpos($path, $radDir) === 0) {
            return ltrim(substr($path, strlen($radDir)), '/');
        }
        return $path;
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

    private function jsonError(string $msg): void {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => $msg]);
        exit;
    }
}

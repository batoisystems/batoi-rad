<?php
namespace RadAdmin;

use Core\Sys\TelemetryService;
use Core\Sys\PrivilegeService;
use RuntimeException;

class Telemetry {
    private $runData = [];
    private $db;
    private $errorHandler;
    private $service;
    private PrivilegeService $priv;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        $this->service = new TelemetryService($this->db, $this->errorHandler, $runData['config'] ?? []);
        $this->priv = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
    }

    public function view() {
        if (!$this->priv->can('view')) {
            $this->runData['request']->setAlert('Access denied.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/home/view');
            exit;
        }
        $config = $this->service->getConfig();
        $summary = $this->service->summary();
        $events = $this->service->listEvents([], 100);
        $tokens = $this->service->listTokens();

        $this->runData['data']['telemetry_config'] = $config;
        $this->runData['data']['telemetry_summary'] = $summary;
        $this->runData['data']['telemetry_events'] = $events;
        $this->runData['data']['telemetry_tokens'] = $tokens;
        $this->runData['data']['privilege_flags'] = [
            'settings' => $this->priv->can('settings'),
            'manage_tokens' => $this->priv->can('manage_tokens'),
        ];
        $this->runData['data']['activity_ingest_last_run'] = $this->getConfigValue('activity_ingest_last_run');
        $this->runData['data']['activity_ingest_last_range'] = $this->getConfigJson('activity_ingest_last_range');

        $this->runData['route']['h1'] = 'Telemetry';
        $this->runData['route']['meta_title'] = 'Telemetry';
        $this->runData['route']['breadcrumb'] = ['Telemetry' => ''];

        if ($this->runData['request']->method === 'POST') {
            if (isset($this->runData['request']->post['telemetry_config_save'])) {
                if (!$this->priv->can('settings')) {
                    $this->runData['request']->setAlert('Access denied.', 'danger');
                    return $this->runData;
                }
                $this->saveConfig();
            } elseif (isset($this->runData['request']->post['telemetry_token_create'])) {
                if (!$this->priv->can('manage_tokens')) {
                    $this->runData['request']->setAlert('Access denied.', 'danger');
                    return $this->runData;
                }
                $this->createToken();
            } elseif (isset($this->runData['request']->post['telemetry_ingest_logs'])) {
                if (!$this->priv->can('settings')) {
                    $this->runData['request']->setAlert('Access denied.', 'danger');
                    return $this->runData;
                }
                $this->ingestLogs();
            }
        }

        if (isset($this->runData['route']['pathparts'][3]) && $this->runData['route']['pathparts'][2] === 'revoke') {
            if (!$this->priv->can('manage_tokens')) {
                $this->runData['request']->setAlert('Access denied.', 'danger');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/telemetry/view');
                exit;
            }
            $this->revokeToken((int)$this->runData['route']['pathparts'][3]);
        }

        return $this->runData;
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

    private function getConfigJson(string $handle): ?array {
        $value = $this->getConfigValue($handle);
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function list() {
        if (!$this->priv->can('view')) {
            $this->runData['request']->setAlert('Access denied.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/home/view');
            exit;
        }
        $filters = [
            'severity' => $this->runData['request']->get['severity'] ?? '',
            'component_type' => $this->runData['request']->get['component_type'] ?? '',
            'search' => $this->runData['request']->get['q'] ?? '',
        ];
        $events = $this->service->listEvents([], 1000);
        $events = $this->applyFilters($events, $filters);

        $perPage = 50;
        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $total = count($events);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) { $page = $pages; }
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($events, $offset, $perPage);

        $this->runData['data']['telemetry_filters'] = $filters;
        $this->runData['data']['telemetry_events'] = $paged;
        $this->runData['data']['telemetry_pagination'] = [
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
        $this->runData['route']['h1'] = 'Telemetry Events';
        $this->runData['route']['meta_title'] = 'Telemetry Events';
        $this->runData['route']['breadcrumb'] = [
            'Telemetry' => $this->runData['route']['rad_admin_url'] . '/telemetry/view',
            'Events' => '',
        ];
        $this->runData['route']['pagepart'] = 'telemetry-list';
        return $this->runData;
    }

    public function export() {
        if (!$this->priv->can('view')) {
            $this->runData['request']->setAlert('Access denied.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/home/view');
            exit;
        }
        $report = [
            'config' => $this->service->getConfig(),
            'summary' => $this->service->summary(),
            'events' => $this->service->listEvents([], 50),
        ];
        $html = $this->renderExport($report);
        $format = strtolower($this->runData['route']['pathparts'][3] ?? 'html');
        if ($format === 'pdf') {
            $tcpdfPath = $this->runData['config']['dir']['vendor'] . '/tcpdf/tcpdf.php';
            if (!file_exists($tcpdfPath)) {
                $this->runData['request']->setAlert('PDF export requires TCPDF under rad/vendor/tcpdf.', 'danger');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/telemetry/view');
                exit;
            }
            require_once $tcpdfPath;
            $pdf = new \TCPDF();
            $pdf->SetTitle('Telemetry Report');
            $pdf->AddPage();
            $pdf->writeHTML($html);
            $pdf->Output('telemetry-report.pdf', 'I');
            exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    public function metrics() {
        if (!$this->priv->can('view')) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied.']);
            exit;
        }
        $summary = $this->service->summary();
        $events = $this->service->listEvents([], 200);
        $severityCounts = $summary;
        unset($severityCounts['events']);

        $components = [];
        foreach ($events as $event) {
            $type = $event['component_type'] ?? 'other';
            $components[$type] = ($components[$type] ?? 0) + 1;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'severity' => [
                'high' => $severityCounts['high'] ?? 0,
                'medium' => $severityCounts['medium'] ?? 0,
                'low' => $severityCounts['low'] ?? 0,
            ],
            'components' => $components,
            'events' => $summary['events'] ?? 0,
        ]);
        exit;
    }

    public function exportcsv() {
        $filters = [
            'severity' => $this->runData['request']->get['severity'] ?? '',
            'component_type' => $this->runData['request']->get['component_type'] ?? '',
            'search' => $this->runData['request']->get['q'] ?? '',
        ];
        $events = $this->applyFilters($this->service->listEvents([], 2000), $filters);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="telemetry-events.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Time', 'Component', 'Severity', 'Message', 'Duration(ms)']);
        foreach ($events as $event) {
            fputcsv($out, [
                $event['created_at'] ?? '',
                ($event['component_type'] ?? '') . ':' . ($event['component_ref'] ?? ''),
                $event['severity'] ?? '',
                $event['message'] ?? '',
                $event['duration_ms'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    private function renderExport(array $report): string {
        $summary = $report['summary'] ?? [];
        $events = $report['events'] ?? [];

        $html = '<h1>Telemetry Report</h1>';
        $html .= '<ul>';
        $html .= '<li><strong>Events:</strong> ' . (int)($summary['events'] ?? 0) . '</li>';
        $html .= '<li><strong>High:</strong> ' . (int)($summary['high'] ?? 0) . '</li>';
        $html .= '<li><strong>Medium:</strong> ' . (int)($summary['medium'] ?? 0) . '</li>';
        $html .= '<li><strong>Low:</strong> ' . (int)($summary['low'] ?? 0) . '</li>';
        $html .= '</ul>';

        $html .= '<h2>Visual Summaries</h2>';
        $totalSev = max(1, ($summary['high'] ?? 0) + ($summary['medium'] ?? 0) + ($summary['low'] ?? 0));
        $html .= '<h3>Severity</h3>' . $this->renderBarTable([
            ['label' => 'High', 'value' => $summary['high'] ?? 0, 'total' => $totalSev, 'color' => '#e74c3c'],
            ['label' => 'Medium', 'value' => $summary['medium'] ?? 0, 'total' => $totalSev, 'color' => '#f39c12'],
            ['label' => 'Low', 'value' => $summary['low'] ?? 0, 'total' => $totalSev, 'color' => '#95a5a6'],
        ]);

        $html .= '<h2>Recent Events</h2>';
        $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
        $html .= '<tr><th>Time</th><th>Component</th><th>Severity</th><th>Message</th><th>Duration (ms)</th></tr>';
        foreach ($events as $event) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($event['created_at'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars(($event['component_type'] ?? '') . ':' . ($event['component_ref'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars($event['severity'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($event['message'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($event['duration_ms'] ?? '') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    private function renderBarTable(array $rows): string {
        $barWidth = 180;
        $html = '<table border="0" cellpadding="4" cellspacing="0" width="100%">';
        foreach ($rows as $row) {
            $value = (int)$row['value'];
            $total = (int)$row['total'];
            $pct = $total > 0 ? round(($value / $total) * 100) : 0;
            $fillWidth = (int)round(($pct / 100) * $barWidth);
            $emptyWidth = max(0, $barWidth - $fillWidth);
            $color = $row['color'] ?? '#3498db';
            $html .= '<tr><td width="35%">' . htmlspecialchars($row['label']) . '</td><td width="65%">';
            $html .= '<table border="0" cellspacing="0" cellpadding="0" width="' . $barWidth . '" style="border:1px solid #e0e0e0;"><tr>';
            $html .= '<td width="' . $fillWidth . '" bgcolor="' . htmlspecialchars($color) . '">&nbsp;</td>';
            $html .= '<td width="' . $emptyWidth . '" bgcolor="#f6f8fa">&nbsp;</td>';
            $html .= '</tr></table>';
            $html .= '<div style="font-size:10px;color:#555;margin-top:2px;">' . $value . ' / ' . $total . ' (' . $pct . '%)</div>';
            $html .= '</td></tr>';
        }
        $html .= '</table>';
        return $html;
    }

    private function saveConfig(): void {
        $post = $this->runData['request']->post;
        $payload = [
            'enabled' => !empty($post['enabled']) ? 'Y' : 'N',
            'sampling_rate' => (int)($post['sampling_rate'] ?? 100),
            'retention_days' => (int)($post['retention_days'] ?? 30),
            'collect_requests' => !empty($post['collect_requests']) ? 'Y' : 'N',
            'collect_errors' => !empty($post['collect_errors']) ? 'Y' : 'N',
            'collect_jobs' => !empty($post['collect_jobs']) ? 'Y' : 'N',
        ];
        $this->service->saveConfig($payload);
        $this->runData['request']->setAlert('Telemetry settings saved.', 'success');
        if (!headers_sent()) {
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/telemetry/view');
        }
        exit;
    }

    private function createToken(): void {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('manage_tokens')) {
            throw new \Exception('Access denied.', 403);
        }
        $post = $this->runData['request']->post;
        $scopes = $post['scopes'] ?? [];
        if (!is_array($scopes)) {
            $scopes = [$scopes];
        }
        $expires = !empty($post['expires_at']) ? $post['expires_at'] : null;
        $result = $this->service->createToken($scopes, $expires);
        $this->runData['request']->setAlert('New token created: ' . htmlspecialchars($result['token']), 'success');
        if (!headers_sent()) {
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/telemetry/view');
        }
        exit;
    }

    private function revokeToken(int $id): void {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('manage_tokens')) {
            throw new \Exception('Access denied.', 403);
        }
        if ($id > 0) {
            $this->service->revokeToken($id);
            $this->runData['request']->setAlert('Token revoked.', 'success');
        }
        if (!headers_sent()) {
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/telemetry/view');
        }
        exit;
    }

    private function ingestLogs(): void {
        $date = $this->runData['request']->post['ingest_date'] ?? null;
        $result = $this->service->ingestFromLogs($date, 2000);
        $this->runData['request']->setAlert('Ingested ' . (int)($result['ingested'] ?? 0) . ' telemetry events from logs.', 'success');
        if (!headers_sent()) {
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/telemetry/view');
        }
        exit;
    }

    private function applyFilters(array $events, array $filters): array {
        return array_values(array_filter($events, function ($event) use ($filters) {
            if (!empty($filters['severity']) && strtolower($event['severity'] ?? '') !== strtolower($filters['severity'])) {
                return false;
            }
            if (!empty($filters['component_type']) && strtolower($event['component_type'] ?? '') !== strtolower($filters['component_type'])) {
                return false;
            }
            if (!empty($filters['search'])) {
                $haystack = strtolower(($event['message'] ?? '') . ' ' . ($event['component_ref'] ?? ''));
                if (strpos($haystack, strtolower($filters['search'])) === false) {
                    return false;
                }
            }
            return true;
        }));
    }
}

<?php
namespace RadAdmin;

class Devsecops {
    private array $runData = [];
    private $db;
    private $errorHandler;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
    }

    public function view() {
        $data = $this->collectSnapshot();
        $this->runData['data'] = array_merge($this->runData['data'] ?? [], $data);
        $this->runData['route']['h1'] = 'DevSecOps Report';
        $this->runData['route']['meta_title'] = 'DevSecOps Report';
        $this->runData['route']['breadcrumb'] = ['DevSecOps Report' => ''];
        return $this->runData;
    }

    public function export() {
        $format = strtolower($this->runData['route']['pathparts'][3] ?? 'html');
        $data = $this->collectSnapshot();
        if ($format === 'pdf') {
            $tcpdfPath = $this->runData['config']['dir']['vendor'] . '/tcpdf/tcpdf.php';
            if (!file_exists($tcpdfPath)) {
                $this->runData['request']->setAlert('PDF export requires TCPDF under rad/vendor/tcpdf.', 'danger');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/devsecops/view');
                exit;
            }
            require_once $tcpdfPath;
            $pdf = new \TCPDF();
            $pdf->SetTitle('DevSecOps Report');
            $pdf->AddPage();
            $html = $this->renderHtmlSummary($data);
            $pdf->writeHTML($html);
            $pdf->Output('devsecops-report.pdf', 'I');
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderHtmlSummary($data);
        exit;
    }

    public function metrics() {
        $data = $this->collectSnapshot();
        $metrics = [
            'severity' => $this->severityCounts($data['findings'] ?? []),
            'vendor' => [
                'up_to_date' => max(0, ($data['summary']['vendors'] ?? 0) - (($data['summary']['vendors_outdated'] ?? 0) + count($data['vendor_hygiene']['missing'] ?? []))),
                'outdated' => $data['summary']['vendors_outdated'] ?? 0,
                'missing' => count($data['vendor_hygiene']['missing'] ?? []),
            ],
            'access' => [
                'microservices_total' => $data['summary']['microservices'] ?? 0,
                'microservices_bound' => ($data['summary']['microservices'] ?? 0) - count($this->detectUnboundMicroservices($data['bindings_by_type']['ms'] ?? [], $data['microservices'] ?? [])),
                'routes_total' => $data['summary']['routes'] ?? 0,
                'routes_bound' => ($data['summary']['routes'] ?? 0) - count($this->detectUnboundRoutes($data['bindings_by_type']['route'] ?? [], $data['routes'] ?? [])),
            ],
            'jobs' => $this->jobStatusCounts($data['queues'] ?? []),
        ];
        header('Content-Type: application/json');
        echo json_encode($metrics);
        exit;
    }

    private function collectSnapshot(): array {
        $microservices = $this->filterRestrictedMs($this->db->select('s_ms', ['livestatus' => '1'], true, ['s_name' => 'ASC']));
        $routesRaw = $this->db->select('s_msroute', ['livestatus' => '1'], true, ['s_ms_id' => 'ASC', 's_name' => 'ASC']);
        $controllersRaw = $this->db->select('s_mscontroller', ['livestatus' => '1'], true, ['s_ms_id' => 'ASC', 's_name' => 'ASC']);
        $routes = $this->filterRoutesByMs($routesRaw, $microservices);
        $controllers = $this->filterControllersByMs($controllersRaw, $microservices);
        $vendors = $this->db->select('s_vendor', ['s_service_type_id' => '3'], true, ['s_title' => 'ASC']);
        $roles = $this->db->select('s_role', ['livestatus' => '1'], true, ['s_role_name' => 'ASC']);
        $bindings = $this->db->select('s_permission_binding', ['livestatus' => '1'], true);
        $navsets = $this->db->select('s_navset', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        $queues = $this->db->select('s_queue', [], true, ['s_next_execution' => 'ASC']);
        $workspaces = $this->db->select('s_space', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        try {
            $branches = $this->db->select('s_branch', ['livestatus' => '1'], true, ['id' => 'DESC'], 25);
        } catch (\Throwable $e) {
            $branches = [];
        }

        $bindingsByType = $this->groupBy($bindings, 's_object_type');
        $vendorHygiene = $this->evaluateVendors($vendors);
        $findings = $this->compileFindings($vendorHygiene, $bindingsByType, $queues);

        $summary = [
            'microservices' => count($microservices),
            'routes' => count($routes),
            'controllers' => count($controllers),
            'vendors' => count($vendors),
            'vendors_outdated' => count($vendorHygiene['outdated']),
            'roles' => count($roles),
            'bindings' => count($bindings),
            'navsets' => count($navsets),
            'workspaces' => count($workspaces),
            'queues' => count($queues),
            'branches' => count($branches),
        ];

        return [
            'summary' => $summary,
            'microservices' => $microservices,
            'routes' => $routes,
            'controllers' => $controllers,
            'vendors' => $vendors,
            'vendor_hygiene' => $vendorHygiene,
            'roles' => $roles,
            'bindings' => $bindings,
            'bindings_by_type' => $bindingsByType,
            'navsets' => $navsets,
            'queues' => $queues,
            'workspaces' => $workspaces,
            'branches' => $branches,
            'findings' => $findings,
            'recommendations' => $this->recommendations($findings),
        ];
    }

    private function groupBy(array $rows, string $key): array {
        $grouped = [];
        foreach ($rows as $row) {
            if (!isset($row[$key])) {
                continue;
            }
            $grouped[$row[$key]][] = $row;
        }
        return $grouped;
    }

    private function evaluateVendors(array $vendors): array {
        $outdated = [];
        $missing = [];
        foreach ($vendors as $vendor) {
            $installed = trim($vendor['s_version_installed'] ?? '');
            $available = trim($vendor['s_version_available'] ?? '');
            if ($installed === '') {
                $missing[] = $vendor;
                continue;
            }
            if ($available !== '' && $installed !== $available) {
                $outdated[] = $vendor;
            }
        }
        return [
            'outdated' => $outdated,
            'missing' => $missing,
        ];
    }

    private function compileFindings(array $vendorHygiene, array $bindingsByType, array $queues): array {
        $findings = [];

        if (!empty($vendorHygiene['outdated'])) {
            $findings[] = [
                'severity' => 'medium',
                'category' => 'Dependencies',
                'title' => 'Vendor libraries with available updates',
                'count' => count($vendorHygiene['outdated']),
                'items' => array_map(function ($v) {
                    return ($v['s_title'] ?? $v['s_handle'] ?? 'Library') . ' (' . ($v['s_version_installed'] ?? 'n/a') . ' → ' . ($v['s_version_available'] ?? 'n/a') . ')';
                }, $vendorHygiene['outdated']),
            ];
        }

        if (!empty($vendorHygiene['missing'])) {
            $findings[] = [
                'severity' => 'low',
                'category' => 'Dependencies',
                'title' => 'Vendor libraries without installed versions',
                'count' => count($vendorHygiene['missing']),
                'items' => array_map(function ($v) {
                    return ($v['s_title'] ?? $v['s_handle'] ?? 'Library');
                }, $vendorHygiene['missing']),
            ];
        }

        if (empty($bindingsByType['ms']) || empty($bindingsByType['route'])) {
            $findings[] = [
                'severity' => 'medium',
                'category' => 'Access Control',
                'title' => 'Permission bindings incomplete',
                'count' => 1,
                'items' => ['Review microservicelet and route bindings for least-privilege coverage.'],
            ];
        }

        $unboundMs = $this->detectUnboundMicroservices($bindingsByType['ms'] ?? [], $this->runData['data']['microservices'] ?? []);
        if (!empty($unboundMs)) {
            $findings[] = [
                'severity' => 'medium',
                'category' => 'Access Control',
                'title' => 'Microservicelets without bindings',
                'count' => count($unboundMs),
                'items' => $unboundMs,
            ];
        }

        $unboundRoutes = $this->detectUnboundRoutes($bindingsByType['route'] ?? [], $this->runData['data']['routes'] ?? []);
        if (!empty($unboundRoutes)) {
            $findings[] = [
                'severity' => 'high',
                'category' => 'Access Control',
                'title' => 'Routes without bindings',
                'count' => count($unboundRoutes),
                'items' => $unboundRoutes,
            ];
        }

        $failingQueues = array_filter($queues, function ($q) {
            return isset($q['s_queue_status']) && strtolower($q['s_queue_status']) === 'failure';
        });
        if (!empty($failingQueues)) {
            $findings[] = [
                'severity' => 'high',
                'category' => 'Operations',
                'title' => 'Scheduled jobs failing',
                'count' => count($failingQueues),
                'items' => array_map(function ($q) {
                    return ($q['s_queue_title'] ?? $q['s_queue_script_name'] ?? 'Job') . ' — ' . ($q['s_error_message'] ?? 'Error not specified');
                }, $failingQueues),
            ];
        }

        return $findings;
    }

    private function recommendations(array $findings): array {
        $recs = [];
        foreach ($findings as $finding) {
            $category = $finding['category'] ?? '';
            if ($category === 'Dependencies') {
                $recs[] = 'Upgrade or install pending vendor libraries and retest impacted microservicelets.';
            } elseif ($category === 'Access Control') {
                $recs[] = 'Audit permission bindings for microservicelets/routes to ensure least-privilege role coverage.';
            } elseif ($category === 'Operations') {
                $recs[] = 'Investigate failing scheduled jobs and review logs/error messages for remediation.';
            }
        }
        if (empty($recs)) {
            $recs[] = 'No critical findings. Keep monitoring dependencies, permissions, and jobs regularly.';
        }
        return array_unique($recs);
    }

    private function detectUnboundMicroservices(array $bindings, array $microservices): array {
        $boundIds = [];
        foreach ($bindings as $binding) {
            if (($binding['s_object_type'] ?? '') === 'ms' && isset($binding['s_object_id'])) {
                $boundIds[(int)$binding['s_object_id']] = true;
            }
        }
        $missing = [];
        foreach ($microservices as $ms) {
            $id = (int)($ms['id'] ?? 0);
            if ($id > 0 && !isset($boundIds[$id])) {
                $missing[] = $ms['s_name'] ?? ('MS #' . $id);
            }
        }
        return $missing;
    }

    private function detectUnboundRoutes(array $bindings, array $routes): array {
        $boundIds = [];
        foreach ($bindings as $binding) {
            if (($binding['s_object_type'] ?? '') === 'route' && isset($binding['s_object_id'])) {
                $boundIds[(int)$binding['s_object_id']] = true;
            }
        }
        $missing = [];
        foreach ($routes as $route) {
            $id = (int)($route['id'] ?? 0);
            if ($id > 0 && !isset($boundIds[$id])) {
                $missing[] = $route['s_name'] ?? ('Route #' . $id);
            }
        }
        return $missing;
    }

    private function severityCounts(array $findings): array {
        $counts = ['high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($findings as $f) {
            $sev = strtolower($f['severity'] ?? '');
            if (isset($counts[$sev])) {
                $counts[$sev]++;
            }
        }
        return $counts;
    }

    private function jobStatusCounts(array $queues): array {
        $counts = ['success' => 0, 'failure' => 0, 'unknown' => 0];
        foreach ($queues as $q) {
            $status = strtolower($q['s_queue_status'] ?? '');
            if ($status === 'success') {
                $counts['success']++;
            } elseif ($status === 'failure') {
                $counts['failure']++;
            } else {
                $counts['unknown']++;
            }
        }
        return $counts;
    }

    private function renderHtmlSummary(array $data): string {
        $summary = $data['summary'] ?? [];
        $findings = $data['findings'] ?? [];
        $vendors = $data['vendor_hygiene']['outdated'] ?? [];
        $vendorHygiene = $data['vendor_hygiene'] ?? ['outdated' => [], 'missing' => []];
        $severity = $this->severityCounts($findings);
        $jobs = $this->jobStatusCounts($data['queues'] ?? []);
        $access = [
            'microservices_total' => $summary['microservices'] ?? 0,
            'microservices_bound' => ($summary['microservices'] ?? 0) - count($this->detectUnboundMicroservices($data['bindings_by_type']['ms'] ?? [], $data['microservices'] ?? [])),
            'routes_total' => $summary['routes'] ?? 0,
            'routes_bound' => ($summary['routes'] ?? 0) - count($this->detectUnboundRoutes($data['bindings_by_type']['route'] ?? [], $data['routes'] ?? [])),
        ];
        $html = '<h1>DevSecOps Report</h1>';
        $html .= '<p>Snapshot of security, dependencies, access, and operations across the application.</p>';
        $html .= '<ul>';
        foreach ($summary as $label => $count) {
            $html .= '<li><strong>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $label))) . ':</strong> ' . (int)$count . '</li>';
        }
        $html .= '</ul>';

        if (!empty($findings)) {
            $html .= '<h2>Findings</h2><ul>';
            foreach ($findings as $f) {
                $html .= '<li><strong>' . htmlspecialchars($f['title'] ?? '') . '</strong> [' . htmlspecialchars($f['severity'] ?? '') . ']<br>';
                $html .= '<em>' . htmlspecialchars($f['category'] ?? '') . '</em><br>';
                if (!empty($f['items'])) {
                    $html .= '<ul>';
                    foreach ($f['items'] as $item) {
                        $html .= '<li>' . htmlspecialchars($item) . '</li>';
                    }
                    $html .= '</ul>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        if (!empty($vendors)) {
            $html .= '<h2>Outdated Vendor Libraries</h2><ul>';
            foreach ($vendors as $v) {
                $html .= '<li>' . htmlspecialchars($v['s_title'] ?? $v['s_handle'] ?? '') .
                    ' (' . htmlspecialchars($v['s_version_installed'] ?? 'n/a') . ' → ' .
                    htmlspecialchars($v['s_version_available'] ?? 'n/a') . ')</li>';
            }
            $html .= '</ul>';
        }

        $html .= '<h2>Static Charts</h2>';
        $html .= '<h3>Findings by Severity</h3>' . $this->renderBarTable([
            ['label' => 'High', 'value' => $severity['high'] ?? 0, 'total' => max(1, array_sum($severity)), 'color' => '#e74c3c'],
            ['label' => 'Medium', 'value' => $severity['medium'] ?? 0, 'total' => max(1, array_sum($severity)), 'color' => '#f39c12'],
            ['label' => 'Low', 'value' => $severity['low'] ?? 0, 'total' => max(1, array_sum($severity)), 'color' => '#95a5a6'],
        ]);

        $html .= '<h3>Dependency Hygiene</h3>' . $this->renderBarTable([
            ['label' => 'Up-to-date', 'value' => max(0, ($summary['vendors'] ?? 0) - (($summary['vendors_outdated'] ?? 0) + count($vendorHygiene['missing'] ?? []))), 'total' => max(1, $summary['vendors'] ?? 0), 'color' => '#2ecc71'],
            ['label' => 'Outdated', 'value' => $summary['vendors_outdated'] ?? 0, 'total' => max(1, $summary['vendors'] ?? 0), 'color' => '#f39c12'],
            ['label' => 'Missing', 'value' => count($vendorHygiene['missing'] ?? []), 'total' => max(1, $summary['vendors'] ?? 0), 'color' => '#3498db'],
        ]);

        $html .= '<h3>Access Coverage</h3>' . $this->renderBarTable([
            ['label' => 'Microservicelets (bound)', 'value' => $access['microservices_bound'], 'total' => max(1, $access['microservices_total']), 'color' => '#2ecc71'],
            ['label' => 'Routes (bound)', 'value' => $access['routes_bound'], 'total' => max(1, $access['routes_total']), 'color' => '#2ecc71'],
        ]);

        $html .= '<h3>Job Status</h3>' . $this->renderBarTable([
            ['label' => 'Success', 'value' => $jobs['success'] ?? 0, 'total' => max(1, array_sum($jobs)), 'color' => '#2ecc71'],
            ['label' => 'Failure', 'value' => $jobs['failure'] ?? 0, 'total' => max(1, array_sum($jobs)), 'color' => '#e74c3c'],
            ['label' => 'Unknown', 'value' => $jobs['unknown'] ?? 0, 'total' => max(1, array_sum($jobs)), 'color' => '#95a5a6'],
        ]);

        $html .= '<h3>Vendor Library Status</h3>' . $this->renderBarTable([
            ['label' => 'Installed', 'value' => max(0, ($summary['vendors'] ?? 0) - (($summary['vendors_outdated'] ?? 0) + count($vendorHygiene['missing'] ?? []))), 'total' => max(1, $summary['vendors'] ?? 0), 'color' => '#2ecc71'],
            ['label' => 'Outdated', 'value' => $summary['vendors_outdated'] ?? 0, 'total' => max(1, $summary['vendors'] ?? 0), 'color' => '#f39c12'],
            ['label' => 'Missing', 'value' => count($vendorHygiene['missing'] ?? []), 'total' => max(1, $summary['vendors'] ?? 0), 'color' => '#3498db'],
        ]);

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

    private function filterRestrictedMs(array $msList): array {
        $config = $this->runData['config'] ?? [];
        $entity = $this->runData['entity'] ?? [];
        if ((new \Core\Sys\PrivilegeService($config, $entity))->role() === 'system_admin') {
            return $msList;
        }
        $filtered = [];
        foreach ($msList as $ms) {
            $id = (int)($ms['id'] ?? 0);
            if ($id === 0) {
                continue;
            }
            if (\RadAdmin\VisibilityHelper::isRestrictedMs($id, $config, $entity)) {
                continue;
            }
            $filtered[] = $ms;
        }
        return $filtered;
    }

    private function filterRoutesByMs(array $routes, array $msList): array {
        $allowed = array_flip(array_map(function ($ms) { return (int)$ms['id']; }, $msList));
        return array_values(array_filter($routes, function ($route) use ($allowed) {
            return isset($allowed[(int)($route['s_ms_id'] ?? 0)]);
        }));
    }

    private function filterControllersByMs(array $controllers, array $msList): array {
        $allowed = array_flip(array_map(function ($ms) { return (int)$ms['id']; }, $msList));
        return array_values(array_filter($controllers, function ($ctrl) use ($allowed) {
            return isset($allowed[(int)($ctrl['s_ms_id'] ?? 0)]);
        }));
    }
}

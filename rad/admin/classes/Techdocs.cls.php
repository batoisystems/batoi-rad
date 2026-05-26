<?php
namespace RadAdmin;

use RuntimeException;
use Core\Sys\TimeHelper;

class Techdocs {
    private $runData = [];
    private $db;
    private $errorHandler;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
    }

    public function view() {
        $this->populateData();
        $this->runData['route']['h1'] = 'Technical Documentation';
        $this->runData['route']['meta_title'] = 'Technical Documentation';
        $this->runData['route']['breadcrumb'] = ['Technical Docs' => ''];
        return $this->runData;
    }

    public function export() {
        $format = strtolower($this->runData['route']['pathparts'][3] ?? 'html');
        $this->populateData();
        $data = $this->runData['data'];

        if ($format === 'pdf') {
            $tcpdfPath = $this->runData['config']['dir']['vendor'] . '/tcpdf/tcpdf.php';
            if (!file_exists($tcpdfPath)) {
                $this->runData['request']->setAlert('PDF export requires TCPDF library under rad/vendor/tcpdf.', 'danger');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/techdocs/view');
                exit;
            }
            require_once $tcpdfPath;
            $pdf = new \TCPDF();
            $pdf->SetTitle('Technical Documentation');
            $pdf->AddPage();
            $html = $this->renderHtmlSummary($data, true);
            $pdf->writeHTML($html);
            $pdf->Output('technical-docs.pdf', 'I');
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderHtmlSummary($data, false);
        exit;
    }

    public function metrics() {
        $this->populateData();
        $data = $this->runData['data'];

        $msMetrics = [];
        foreach ($data['microservices'] as $ms) {
            $id = $ms['id'] ?? null;
            if (!$id) { continue; }
            $msMetrics[] = [
                'name' => $ms['s_name'] ?? ('MS #' . $id),
                'routes' => count($data['routes_by_ms'][$id] ?? []),
                'controllers' => count($data['controllers_by_ms'][$id] ?? []),
            ];
        }

        $vendorMetrics = [
            'total' => count($data['vendors']),
            'missing' => 0,
            'installed' => 0,
        ];
        foreach ($data['vendors'] as $vendor) {
            $installed = trim($vendor['s_version_installed'] ?? '');
            if ($installed === '') {
                $vendorMetrics['missing']++;
            } else {
                $vendorMetrics['installed']++;
            }
        }

        $routeScopes = [
            'UA' => 0,
            'U' => 0,
            'A' => 0,
        ];
        foreach ($data['routes'] as $route) {
            $scope = $route['s_entity_scope'] ?? '';
            if (isset($routeScopes[$scope])) {
                $routeScopes[$scope]++;
            }
        }

        $roleCount = count($data['roles'] ?? []);
        $workspaceCount = count($data['workspaces'] ?? []);

        header('Content-Type: application/json');
        echo json_encode([
            'microservices' => $msMetrics,
            'vendor' => $vendorMetrics,
            'routes_by_scope' => $routeScopes,
            'roles' => $roleCount,
            'workspaces' => $workspaceCount,
        ]);
        exit;
    }

    public function graph() {
        $this->populateData();
        $data = $this->runData['data'];

        $nodes = [];
        $edges = [];

        foreach ($data['microservices'] as $ms) {
            $nodes[] = ['id' => 'ms-' . $ms['id'], 'label' => $ms['s_name'] ?? 'MS', 'type' => 'microservice'];
        }
        foreach ($data['routes'] as $route) {
            $nodes[] = ['id' => 'route-' . $route['id'], 'label' => $route['s_name'] ?? 'route', 'type' => 'route'];
            if (!empty($route['s_ms_id'])) {
                $edges[] = ['from' => 'ms-' . $route['s_ms_id'], 'to' => 'route-' . $route['id'], 'type' => 'ms-route'];
            }
        }
        foreach ($data['controllers'] as $ctrl) {
            $nodes[] = ['id' => 'ctrl-' . $ctrl['id'], 'label' => $ctrl['s_name'] ?? 'controller', 'type' => 'controller'];
            if (!empty($ctrl['s_ms_id'])) {
                $edges[] = ['from' => 'ms-' . $ctrl['s_ms_id'], 'to' => 'ctrl-' . $ctrl['id'], 'type' => 'ms-controller'];
            }
        }
        foreach ($data['vendors'] as $vendor) {
            $nodes[] = ['id' => 'vendor-' . $vendor['id'], 'label' => $vendor['s_title'] ?? $vendor['s_handle'] ?? 'vendor', 'type' => 'vendor'];
            foreach ($data['microservices'] as $ms) {
                $edges[] = ['from' => 'ms-' . $ms['id'], 'to' => 'vendor-' . $vendor['id'], 'type' => 'dependency'];
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['nodes' => $nodes, 'edges' => $edges]);
        exit;
    }

    public function accesscontrol() {
        $this->runData['route']['h1'] = 'Access Control';
        $this->runData['route']['meta_title'] = 'Access Control';
        $this->runData['route']['breadcrumb'] = [
            'Technical Docs' => $this->runData['route']['rad_admin_url'] . '/techdocs/view',
            'Access Control' => '',
        ];
        return $this->runData;
    }

    public function dotphrases() {
        $this->runData['route']['h1'] = 'Dot Phrases';
        $this->runData['route']['meta_title'] = 'Dot Phrases';
        $this->runData['route']['breadcrumb'] = [
            'Technical Docs' => $this->runData['route']['rad_admin_url'] . '/techdocs/view',
            'Dot Phrases' => '',
        ];
        return $this->runData;
    }

    public function changelog() {
        // Default page will be rendered by governance controller
        return $this->runData;
    }

    public function aclreport() {
        $report = [
            'ms_no_bindings' => [],
            'routes_no_bindings' => [],
        ];

        // Microservicelets (private) with zero bindings
        $msRows = $this->db->query("
            SELECT m.id, m.s_name, m.s_scope, COUNT(pb.id) AS binding_count
            FROM s_ms m
            LEFT JOIN s_permission_binding pb
              ON pb.s_object_type = 'ms' AND pb.s_object_id = m.id AND pb.livestatus != '0'
            WHERE m.livestatus = '1' AND (m.s_scope IS NULL OR LOWER(m.s_scope) <> 'global')
            GROUP BY m.id, m.s_name, m.s_scope
            HAVING binding_count = 0
            ORDER BY m.s_name ASC
        ");
        $report['ms_no_bindings'] = $msRows;

        // Routes (of private ms) with zero bindings
        $routeRows = $this->db->query("
            SELECT r.id, r.s_name, r.s_ms_id, m.s_name AS ms_name, COUNT(pb.id) AS binding_count
            FROM s_msroute r
            INNER JOIN s_ms m ON m.id = r.s_ms_id AND (m.s_scope IS NULL OR LOWER(m.s_scope) <> 'global') AND m.livestatus = '1'
            LEFT JOIN s_permission_binding pb
              ON pb.s_object_type = 'route' AND pb.s_object_id = r.id AND pb.livestatus != '0'
            WHERE r.livestatus = '1'
            GROUP BY r.id, r.s_name, r.s_ms_id, m.s_name
            HAVING binding_count = 0
            ORDER BY m.s_name ASC, r.s_name ASC
        ");
        $report['routes_no_bindings'] = $routeRows;

        $this->runData['data']['ac_report'] = $report;
        $this->runData['route']['h1'] = 'Access Control Report';
        $this->runData['route']['meta_title'] = 'Access Control Report';
        $this->runData['route']['breadcrumb'] = [
            'Technical Docs' => $this->runData['route']['rad_admin_url'] . '/techdocs/view',
            'Access Control Report' => '',
        ];
        return $this->runData;
    }

    private function populateData(): void {
        $microservices = $this->filterRestrictedMs($this->db->select('s_ms', ['livestatus' => '1'], true, ['s_name' => 'ASC']));
        $routesRaw = $this->db->select('s_msroute', ['livestatus' => '1'], true, ['s_ms_id' => 'ASC', 's_name' => 'ASC']);
        $controllersRaw = $this->db->select('s_mscontroller', ['livestatus' => '1'], true, ['s_ms_id' => 'ASC', 's_name' => 'ASC']);
        $routes = $this->filterRoutesByMs($routesRaw, $microservices);
        $controllers = $this->filterControllersByMs($controllersRaw, $microservices);
        $navsets = $this->db->select('s_navset', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        $navitems = $this->db->select('s_nav', ['livestatus' => '1'], true, ['s_navset_id' => 'ASC', 's_name' => 'ASC']);
        $apiEndpoints = $this->db->select('s_api_endpoint', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        $roles = $this->db->select('s_role', ['livestatus' => '1'], true, ['s_role_name' => 'ASC']);
        $workspaces = $this->db->select('s_space', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        $permissionBindings = $this->db->select('s_permission_binding', ['livestatus' => '1'], true);
        $vendors = $this->db->select('s_vendor', ['s_service_type_id' => '3'], true, ['s_title' => 'ASC']);

        $routesByMs = $this->groupBy($routes, 's_ms_id');
        $controllersByMs = $this->groupBy($controllers, 's_ms_id');
        $navitemsBySet = $this->groupBy($navitems, $this->inferNavsetColumn($navitems));
        $bindingsIndex = $this->indexBindings($permissionBindings, $roles);
        $templates = $this->scanTemplates();

        $summary = [
            'microservices' => count($microservices),
            'routes' => count($routes),
            'controllers' => count($controllers),
            'navsets' => count($navsets),
            'templates' => count($templates),
            'apis' => count($apiEndpoints),
            'roles' => count($roles),
            'workspaces' => count($workspaces),
            'vendors' => count($vendors),
        ];

        $this->runData['data']['summary'] = $summary;
        $this->runData['data']['microservices'] = $microservices;
        $this->runData['data']['routes'] = $routes;
        $this->runData['data']['controllers'] = $controllers;
        $this->runData['data']['routes_by_ms'] = $routesByMs;
        $this->runData['data']['controllers_by_ms'] = $controllersByMs;
        $this->runData['data']['navsets'] = $navsets;
        $this->runData['data']['navitems_by_set'] = $navitemsBySet;
        $this->runData['data']['api_endpoints'] = $apiEndpoints;
        $this->runData['data']['roles'] = $roles;
        $this->runData['data']['workspaces'] = $workspaces;
        $this->runData['data']['permission_bindings'] = $permissionBindings;
        $this->runData['data']['bindings_index'] = $bindingsIndex;
        $this->runData['data']['vendors'] = $vendors;
        $this->runData['data']['templates'] = $templates;
        $this->runData['data']['graph'] = $this->buildDependencyGraph($microservices, $routes, $controllers, $vendors);
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

    private function indexBindings(array $bindings, array $roles): array {
        $roleMap = [];
        foreach ($roles as $role) {
            if (isset($role['id'])) {
                $roleMap[$role['id']] = $role;
            }
        }

        $index = [];
        foreach ($bindings as $binding) {
            $type = $binding['s_object_type'] ?? '';
            $objectId = $binding['s_object_id'] ?? null;
            if ($type === '' || $objectId === null) {
                continue;
            }
            $roleId = $binding['s_role_id'] ?? null;
            $binding['role'] = $roleId && isset($roleMap[$roleId]) ? $roleMap[$roleId] : null;
            $index[$type][$objectId][] = $binding;
        }
        return $index;
    }

    private function scanTemplates(): array {
        $themeDir = rtrim($this->runData['config']['dir']['theme'] ?? '', '/');
        if ($themeDir === '' || !is_dir($themeDir)) {
            return [];
        }

        $templates = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($themeDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $name = $file->getFilename();
            if (!preg_match('/\\.(tpl|html)\\.php$/', $name)) {
                continue;
            }
            $templates[] = [
                'name' => $name,
                'path' => str_replace($themeDir . '/', '', $file->getPathname()),
                'size' => $file->getSize(),
                'modified' => $this->formatTimestamp($file->getMTime()),
            ];
            if (count($templates) >= 50) {
                break;
            }
        }
        return $templates;
    }

    private function renderHtmlSummary(array $data, bool $compact): string {
        $summary = $data['summary'] ?? [];
        $routesByScope = ['UA' => 0, 'U' => 0, 'A' => 0];
        foreach ($data['routes'] as $route) {
            $scope = $route['s_entity_scope'] ?? '';
            if (isset($routesByScope[$scope])) {
                $routesByScope[$scope]++;
            }
        }
        $vendorInstalled = 0;
        $vendorMissing = 0;
        foreach ($data['vendors'] as $vendor) {
            $installed = trim($vendor['s_version_installed'] ?? '');
            if ($installed === '') {
                $vendorMissing++;
            } else {
                $vendorInstalled++;
            }
        }
        $roleCount = count($data['roles'] ?? []);
        $workspaceCount = count($data['workspaces'] ?? []);
        $html = '<h1>Technical Documentation</h1>';
        $html .= '<p>Snapshot of microservicelets, routes, controllers, navigation, APIs, roles, workspaces, and vendor dependencies.</p>';
        $html .= '<ul>';
        foreach ($summary as $label => $count) {
            $html .= '<li>' . htmlspecialchars(ucfirst($label)) . ': ' . (int)$count . '</li>';
        }
        $html .= '</ul>';

        $html .= '<h2>Microservicelets</h2><ul>';
        foreach ($data['microservices'] as $ms) {
            $html .= '<li><strong>' . htmlspecialchars($ms['s_name'] ?? '') . '</strong> - ' . htmlspecialchars($ms['s_description'] ?? '') . '</li>';
        }
        $html .= '</ul>';

        if (!$compact) {
            $html .= '<h2>Routes</h2><ul>';
            foreach ($data['routes'] as $route) {
                $html .= '<li>' . htmlspecialchars($route['s_name'] ?? '') . ' (' . htmlspecialchars($route['s_description'] ?? '') . ')</li>';
            }
            $html .= '</ul>';

            $html .= '<h2>Controllers</h2><ul>';
            foreach ($data['controllers'] as $ctrl) {
                $html .= '<li>' . htmlspecialchars($ctrl['s_name'] ?? '') . ' [' . htmlspecialchars($ctrl['s_type'] ?? '') . ']</li>';
            }
            $html .= '</ul>';
        }

        $html .= '<h2>Vendor Libraries</h2><ul>';
        foreach ($data['vendors'] as $vendor) {
            $html .= '<li>' . htmlspecialchars($vendor['s_title'] ?? $vendor['s_handle'] ?? '') . ' (v ' . htmlspecialchars($vendor['s_version_installed'] ?? $vendor['s_version_available'] ?? 'n/a') . ')</li>';
        }
        $html .= '</ul>';

        $html .= '<h2>Visual Summaries</h2>';
        $msRows = [];
        foreach ($data['microservices'] as $ms) {
            $id = $ms['id'] ?? null;
            if (!$id) { continue; }
            $routesCount = count($data['routes_by_ms'][$id] ?? []);
            $controllersCount = count($data['controllers_by_ms'][$id] ?? []);
            $msRows[] = [
                'label' => $ms['s_name'] ?? ('MS #' . $id),
                'segments' => [
                    ['value' => $routesCount, 'color' => '#0d6efd', 'label' => 'Routes'],
                    ['value' => $controllersCount, 'color' => '#3498db', 'label' => 'Controllers'],
                ],
            ];
        }
        $html .= '<h3>Routes & Controllers per Microservicelet</h3>' . $this->renderStackedBars($msRows);

        $html .= '<h3>Vendor Library Status</h3>' . $this->renderSimpleBars([
            ['label' => 'Installed', 'value' => $vendorInstalled, 'total' => max(1, $summary['vendors'] ?? 0), 'color' => '#2ecc71'],
            ['label' => 'Missing', 'value' => $vendorMissing, 'total' => max(1, $summary['vendors'] ?? 0), 'color' => '#f39c12'],
        ]);

        $totalRoutes = max(1, array_sum($routesByScope));
        $html .= '<h3>Routes by Scope</h3>' . $this->renderSimpleBars([
            ['label' => 'UA', 'value' => $routesByScope['UA'], 'total' => $totalRoutes, 'color' => '#3498db'],
            ['label' => 'U', 'value' => $routesByScope['U'], 'total' => $totalRoutes, 'color' => '#0d6efd'],
            ['label' => 'A', 'value' => $routesByScope['A'], 'total' => $totalRoutes, 'color' => '#95a5a6'],
        ]);

        $totalRw = max(1, ($roleCount + $workspaceCount));
        $html .= '<h3>Roles & Workspaces</h3>' . $this->renderSimpleBars([
            ['label' => 'Roles', 'value' => $roleCount, 'total' => $totalRw, 'color' => '#2ecc71'],
            ['label' => 'Workspaces', 'value' => $workspaceCount, 'total' => $totalRw, 'color' => '#0d6efd'],
        ]);

        return $html;
    }

    private function renderSimpleBars(array $rows): string {
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

    private function renderStackedBars(array $rows): string {
        $barWidth = 180;
        $html = '<table border="0" cellpadding="4" cellspacing="0" width="100%">';
        foreach ($rows as $row) {
            $segments = $row['segments'] ?? [];
            $total = array_sum(array_column($segments, 'value'));
            $total = $total > 0 ? $total : 1;
            $html .= '<tr><td width="25%">' . htmlspecialchars($row['label']) . '</td><td width="75%">';
            $html .= '<table border="0" cellspacing="0" cellpadding="0" width="' . $barWidth . '" style="border:1px solid #e0e0e0;"><tr>';
            foreach ($segments as $seg) {
                $segValue = (int)$seg['value'];
                $segWidth = (int)round(($segValue / $total) * $barWidth);
                $html .= '<td width="' . $segWidth . '" bgcolor="' . htmlspecialchars($seg['color'] ?? '#0d6efd') . '">&nbsp;</td>';
            }
            $html .= '</tr></table>';
            $labels = [];
            foreach ($segments as $seg) {
                $labels[] = htmlspecialchars($seg['label'] ?? '') . ': ' . (int)$seg['value'];
            }
            $html .= '<div style="font-size:10px;color:#555;margin-top:2px;">' . implode(' · ', $labels) . '</div>';
            $html .= '</td></tr>';
        }
        $html .= '</table>';
        return $html;
    }

    private function inferNavsetColumn(array $navitems): string {
        if (empty($navitems)) {
            return 's_navset_id';
        }
        $first = $navitems[0];
        if (array_key_exists('s_navset_id', $first)) {
            return 's_navset_id';
        }
        if (array_key_exists('s_navset', $first)) {
            return 's_navset';
        }
        return 's_navset_id';
    }

    private function buildDependencyGraph(array $microservices, array $routes, array $controllers, array $vendors): array {
        $graph = [];
        foreach ($microservices as $ms) {
            $msId = $ms['id'] ?? null;
            if (!$msId) {
                continue;
            }
            $graph[$msId] = [
                'ms' => $ms,
                'routes' => [],
                'controllers' => [],
                'vendors' => $vendors,
            ];
        }
        foreach ($routes as $route) {
            $msId = $route['s_ms_id'] ?? null;
            if ($msId && isset($graph[$msId])) {
                $graph[$msId]['routes'][] = $route;
            }
        }
        foreach ($controllers as $ctrl) {
            $msId = $ctrl['s_ms_id'] ?? null;
            if ($msId && isset($graph[$msId])) {
                $graph[$msId]['controllers'][] = $ctrl;
            }
        }
        return $graph;
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

    private function formatTimestamp(int $timestamp): string {
        if ($timestamp <= 0) {
            return '';
        }
        $timezone = TimeHelper::resolveTimezone(
            $this->runData['entity']['timezone'] ?? null,
            $this->runData['config']['sys']['timezone_default'] ?? null
        );
        return TimeHelper::formatUtc($timestamp, $timezone, 'Y-m-d H:i') ?? '';
    }
}

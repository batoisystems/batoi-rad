<?php
namespace RadAdmin;
use Core\Sys\TimeHelper;
class Role{
    private $runData = [];
    private $errorHandler;
    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->errorHandler = $runData['errorHandler'];
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }

    private function roleSupportsMsScope(): bool {
        try {
            $rows = $this->runData['db']->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 's_role' AND COLUMN_NAME = 's_ms_id'"
            );
            return !empty($rows);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function fetchRouteOptions(): array {
        $rows = $this->runData['db']->query(
            "SELECT r.id, r.s_name AS route_name, r.uid AS route_uid, r.s_ms_id, m.s_name AS ms_name
             FROM s_msroute r
             LEFT JOIN s_ms m ON m.id = r.s_ms_id
             WHERE r.livestatus = '1'
             ORDER BY m.s_name, r.s_name"
        );
        $options = [];
        foreach ($rows as $row) {
            $label = trim(($row['ms_name'] ? $row['ms_name'] . ' · ' : '') . ($row['route_name'] ?? ''));
            $options[] = [
                'id' => (int)$row['id'],
                'label' => $label !== '' ? $label : 'Route ' . $row['id'],
            ];
        }
        return $options;
    }

    private function scopeUsesDefaultRoute(string $scope): bool {
        return strtolower(trim($scope)) === 'platform';
    }

    private function buildRoleViewFilters(): array {
        return [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
            'status' => trim((string)($this->runData['request']->get['status'] ?? '')),
            'scope' => trim((string)($this->runData['request']->get['scope'] ?? '')),
            'has_route' => trim((string)($this->runData['request']->get['has_route'] ?? '')),
            'saas' => trim((string)($this->runData['request']->get['saas'] ?? '')),
            'page' => max(1, (int)($this->runData['request']->get['page'] ?? 1)),
            'per_page' => 0,
        ];
    }

    private function buildEnrichedRoles(): array {
        $roles = $this->runData['db']->select('s_role', [], true);
        $assignmentCounts = $this->buildRoleAssignmentCounts();

        $statusMeta = [
            '0' => ['label' => 'Inactive', 'badge' => 'secondary', 'slug' => 'inactive'],
            '1' => ['label' => 'Active', 'badge' => 'success', 'slug' => 'active'],
            '2' => ['label' => 'Archived', 'badge' => 'danger', 'slug' => 'archived'],
            '3' => ['label' => 'Suspended', 'badge' => 'warning', 'slug' => 'suspended'],
        ];
        $scopeMeta = [
            'platform' => ['label' => 'Platform', 'badge' => 'dark'],
            'workspace' => ['label' => 'Workspace', 'badge' => 'primary'],
            'global' => ['label' => 'Global', 'badge' => 'secondary'],
        ];

        $routeIds = [];
        foreach ($roles as $role) {
            if (!empty($role['s_default_route_id'])) {
                $routeIds[] = (int)$role['s_default_route_id'];
            }
        }
        $routeMap = $this->fetchRoutesByIds($routeIds);
        $msIds = [];
        foreach ($routeMap as $route) {
            if (!empty($route['s_ms_id'])) {
                $msIds[] = (int)$route['s_ms_id'];
            }
        }
        $msMap = $this->fetchMicroservicesByIds($msIds);

        foreach ($roles as &$role) {
            $status = $statusMeta[$role['livestatus']] ?? $statusMeta['0'];
            $scopeSlug = strtolower($role['s_scope'] ?? 'global');
            $scopeMetaRow = $scopeMeta[$scopeSlug] ?? ['label' => ucfirst($scopeSlug), 'badge' => 'secondary'];
            $scope = $role['s_scope'] ?? 'platform';
            $saasEnabled = ($scope === 'workspace');

            $role['status_meta'] = $status;
            $role['status_slug'] = $status['slug'];
            $role['scope_slug'] = $scopeSlug;
            $role['scope_meta'] = $scopeMetaRow;
            $role['saas_slug'] = $saasEnabled ? 'saas' : 'non_saas';
            $role['saas_label'] = $saasEnabled ? 'SaaS' : 'Non-SaaS';
            $role['saas_badge'] = $saasEnabled ? 'info' : 'secondary';
            $role['description_excerpt'] = $this->trimText($role['s_description'] ?? '', 120);
            $role['assignment_count'] = $assignmentCounts[(int)$role['id']]['assignments'] ?? 0;
            $role['space_count'] = $assignmentCounts[(int)$role['id']]['spaces'] ?? 0;
            $role['search_blob'] = strtolower(
                trim(
                    ($role['s_role_name'] ?? '') . ' ' .
                    ($role['s_scope'] ?? '') . ' ' .
                    ($role['s_code'] ?? '') . ' ' .
                    ($role['uid'] ?? '')
                )
            );

            $routeInfo = null;
            if (!empty($role['s_default_route_id']) && isset($routeMap[(int)$role['s_default_route_id']])) {
                $routeRow = $routeMap[(int)$role['s_default_route_id']];
                $msName = '';
                if (!empty($routeRow['s_ms_id']) && isset($msMap[(int)$routeRow['s_ms_id']])) {
                    $msName = $msMap[(int)$routeRow['s_ms_id']]['s_name'] ?? '';
                }
                $routeInfo = [
                    'id' => (int)$role['s_default_route_id'],
                    'name' => $routeRow['s_name'] ?? '',
                    'uid' => $routeRow['uid'] ?? '',
                    'ms_name' => $msName,
                ];
            }
            $role['default_route'] = $routeInfo;
            $role['has_route'] = $routeInfo ? 'yes' : 'no';

            $diagnostics = [];
            if ($this->scopeUsesDefaultRoute($scopeSlug) && empty($role['s_default_route_id'])) {
                $diagnostics[] = 'missing_default_route';
            }
            if (!in_array($scopeSlug, ['platform', 'workspace', 'global'], true)) {
                $diagnostics[] = 'invalid_scope';
            }
            if (($role['assignment_count'] ?? 0) === 0) {
                $diagnostics[] = 'unused';
            }
            $role['diagnostics'] = $diagnostics;
        }
        unset($role);

        return $roles;
    }

    private function filterRoles(array $roles, array $filters): array {
        return array_values(array_filter($roles, function ($row) use ($filters) {
            if ($filters['status'] !== '' && (string)($row['livestatus'] ?? '') !== $filters['status']) {
                return false;
            }
            if ($filters['scope'] !== '' && strtolower($row['s_scope'] ?? '') !== strtolower($filters['scope'])) {
                return false;
            }
            if ($filters['has_route'] !== '' && $row['has_route'] !== $filters['has_route']) {
                return false;
            }
            if ($filters['saas'] !== '' && $row['saas_slug'] !== $filters['saas']) {
                return false;
            }
            if ($filters['q'] !== '' && strpos($row['search_blob'], strtolower($filters['q'])) === false) {
                return false;
            }
            return true;
        }));
    }

    private function buildRoleStats(array $roles): array {
        $stats = [
            'total' => count($roles),
            'saas' => 0,
            'non_saas' => 0,
            'with_route' => 0,
        ];
        foreach ($roles as $role) {
            if (($role['saas_slug'] ?? '') === 'saas') {
                $stats['saas']++;
            } else {
                $stats['non_saas']++;
            }
            if (($role['has_route'] ?? 'no') === 'yes') {
                $stats['with_route']++;
            }
        }
        return $stats;
    }

    private function buildRoleListSniffPayload(array $roles, array $filters): array {
        $items = array_map(function ($role) {
            return [
                'id' => (int)($role['id'] ?? 0),
                'uid' => (string)($role['uid'] ?? ''),
                'role' => (string)($role['s_role_name'] ?? ''),
                'type' => (string)($role['s_scope'] ?? ''),
                'code' => (string)($role['s_code'] ?? ''),
                'status' => (string)($role['status_meta']['label'] ?? ''),
                'default_route' => $role['default_route'] ? [
                    'id' => (int)($role['default_route']['id'] ?? 0),
                    'uid' => (string)($role['default_route']['uid'] ?? ''),
                    'name' => (string)($role['default_route']['name'] ?? ''),
                    'microservice' => (string)($role['default_route']['ms_name'] ?? ''),
                ] : null,
            ];
        }, $roles);
        $platformRoles = array_values(array_filter($items, function ($item) {
            return strtolower((string)($item['type'] ?? '')) === 'platform';
        }));
        $workspaceRoles = array_values(array_filter($items, function ($item) {
            return strtolower((string)($item['type'] ?? '')) === 'workspace';
        }));

        return [
            'object' => [
                'kind' => 'role_collection',
                'name' => 'Role Catalog',
            ],
            'filters' => [
                'q' => (string)($filters['q'] ?? ''),
                'status' => (string)($filters['status'] ?? ''),
                'scope' => (string)($filters['scope'] ?? ''),
                'has_route' => (string)($filters['has_route'] ?? ''),
                'saas' => (string)($filters['saas'] ?? ''),
            ],
            'roles' => $items,
            'roles_by_scope' => [
                'platform' => $platformRoles,
                'workspace' => $workspaceRoles,
            ],
            'stats' => [
                'total' => count($items),
                'platform' => count($platformRoles),
                'workspace' => count($workspaceRoles),
            ],
        ];
    }

    public function view() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('role_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $this->runData['data']['can_idm_manage'] = $priv->can('role_manage');
        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Review role coverage, SaaS eligibility, and default routes.';
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        $this->runData['route']['h1'] = 'Role Catalog';
        $this->runData['route']['meta_title'] = 'Roles';
        $this->runData['route']['backlink'] = $radAdminUrl . '/home/view';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Users & Roles' => $radAdminUrl . '/user/view',
            'Roles' => '',
        ];

        $filters = $this->buildRoleViewFilters();
        $perPage = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($perPage === 0) {
            $perPage = $this->getProfilePerPage(25);
        } elseif ($this->isAllowedPerPage($perPage)) {
            $this->saveProfilePerPage($perPage);
        }
        if ($perPage < 10) {
            $perPage = 10;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }
        $filters['per_page'] = $perPage;

        $allRoles = $this->buildEnrichedRoles();
        $stats = $this->buildRoleStats($allRoles);
        $roles = $this->filterRoles($allRoles, $filters);

        $totalFiltered = count($roles);
        $totalPages = max(1, (int)ceil($totalFiltered / $filters['per_page']));
        if ($filters['page'] > $totalPages) {
            $filters['page'] = $totalPages;
        }
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $pagedRoles = array_slice($roles, $offset, $filters['per_page']);

        $this->runData['data']['roles'] = $pagedRoles;
        $this->runData['data']['role_stats'] = $stats;
        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['pagination'] = [
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'total' => $totalFiltered,
            'total_pages' => $totalPages,
        ];
        return $this->runData;
    }

    public function viewone() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Role not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            header('Location: ' . $radAdminUrl . '/role/view'); exit;
        }

        $uid = $this->runData['route']['pathparts'][3];
        $roleRows = $this->runData['db']->select('s_role', ['uid' => $uid], true);
        if (count($roleRows) !== 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Role not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            header('Location: ' . $radAdminUrl . '/role/view'); exit;
        }

        $role = $roleRows[0];
        $msScopeSupported = $this->roleSupportsMsScope();
        if (!$msScopeSupported) {
            $role['s_ms_id'] = null;
        }
        $statusMeta = [
            '0' => ['label' => 'Inactive', 'badge' => 'secondary', 'slug' => 'inactive'],
            '1' => ['label' => 'Active', 'badge' => 'success', 'slug' => 'active'],
            '2' => ['label' => 'Archived', 'badge' => 'danger', 'slug' => 'archived'],
            '3' => ['label' => 'Suspended', 'badge' => 'warning', 'slug' => 'suspended'],
        ];
        $scopeMeta = [
            'platform' => ['label' => 'Platform', 'badge' => 'dark'],
            'workspace' => ['label' => 'Workspace', 'badge' => 'primary'],
            'global' => ['label' => 'Global', 'badge' => 'secondary'],
        ];

        $status = $statusMeta[$role['livestatus']] ?? $statusMeta['0'];
        $scopeSlug = strtolower($role['s_scope'] ?? 'global');
        $scopeMetaRow = $scopeMeta[$scopeSlug] ?? ['label' => ucfirst($scopeSlug), 'badge' => 'secondary'];
        $scope = $role['s_scope'] ?? 'platform';
        $saasEnabled = ($scope === 'workspace');

        $routeInfo = null;
        if (!empty($role['s_default_route_id'])) {
            $routeMap = $this->fetchRoutesByIds([(int)$role['s_default_route_id']]);
            if (!empty($routeMap)) {
                $routeRow = reset($routeMap);
                $msMap = $this->fetchMicroservicesByIds([(int)($routeRow['s_ms_id'] ?? 0)]);
                $routeInfo = [
                    'id' => (int)$role['s_default_route_id'],
                    'name' => $routeRow['s_name'] ?? '',
                    'uid' => $routeRow['uid'] ?? '',
                    'ms_name' => '',
                ];
                if (!empty($routeRow['s_ms_id']) && isset($msMap[(int)$routeRow['s_ms_id']])) {
                    $routeInfo['ms_name'] = $msMap[(int)$routeRow['s_ms_id']]['s_name'] ?? '';
                }
            }
        }

        $assignmentPage = max(1, (int)($this->runData['request']->get['assignments_page'] ?? 1));
        $assignmentPerPage = (int)($this->runData['request']->get['assignments_per_page'] ?? 0);
        if ($assignmentPerPage === 0) {
            $assignmentPerPage = $this->getProfilePerPage(25);
        } elseif ($this->isAllowedPerPage($assignmentPerPage)) {
            $this->saveProfilePerPage($assignmentPerPage);
        }
        if ($assignmentPerPage < 10) {
            $assignmentPerPage = 10;
        }
        if ($assignmentPerPage > 200) {
            $assignmentPerPage = 200;
        }

        $countRow = $this->runData['db']->query(
            "SELECT COUNT(*) AS total,
                    COUNT(DISTINCT IF(space_id > 0, space_id, NULL)) AS spaces,
                    SUM(CASE WHEN s_scope_level = 'ms' THEN 1 ELSE 0 END) AS ms_assignments,
                    SUM(CASE WHEN s_scope_level = 'ms' THEN 0 ELSE 1 END) AS workspace_assignments
             FROM s_space_membership
             WHERE s_role_id = :role AND livestatus = '1'",
            [':role' => $role['id']]
        );
        $countRow = $countRow[0] ?? [];
        $assignmentTotal = (int)($countRow['total'] ?? 0);
        $spaceCount = (int)($countRow['spaces'] ?? 0);
        $msAssignments = (int)($countRow['ms_assignments'] ?? 0);
        $workspaceAssignments = (int)($countRow['workspace_assignments'] ?? 0);

        $assignmentPages = max(1, (int)ceil($assignmentTotal / $assignmentPerPage));
        if ($assignmentPage > $assignmentPages) {
            $assignmentPage = $assignmentPages;
        }
        $assignmentOffset = ($assignmentPage - 1) * $assignmentPerPage;

        $assignmentRows = $this->runData['db']->query(
            "SELECT m.id,
                    m.space_id,
                    m.s_scope_level,
                    m.s_ms_id,
                    s.s_name AS space_name,
                    s.uid AS space_uid,
                    ms.s_name AS ms_name,
                    e.id AS user_id,
                    e.s_name AS user_name,
                    e.uid AS user_uid,
                    e.s_identity AS username
             FROM s_space_membership m
             INNER JOIN s_entity e ON e.id = m.s_entity_id
             LEFT JOIN s_space s ON s.id = m.space_id
             LEFT JOIN s_ms ms ON ms.id = m.s_ms_id
             WHERE m.s_role_id = :role AND m.livestatus = '1'
             ORDER BY s.s_name, ms.s_name, e.s_name
             LIMIT {$assignmentPerPage} OFFSET {$assignmentOffset}",
            [':role' => $role['id']]
        );

        $assignments = [];
        foreach ($assignmentRows as $row) {
            $assignments[] = [
                'space_name' => $row['space_name'] ?? 'Global',
                'space_uid' => $row['space_uid'] ?? '',
                'scope_level' => $row['s_scope_level'] ?? 'workspace',
                'ms_id' => $row['s_ms_id'] ?? null,
                'ms_name' => $row['ms_name'] ?? '',
                'user_name' => $row['user_name'] ?? '',
                'user_uid' => $row['user_uid'] ?? '',
                'username' => $row['username'] ?? '',
            ];
        }

        $actorIds = [];
        if (!empty($role['createdby'])) {
            $actorIds[] = (int)$role['createdby'];
        }
        if (!empty($role['updatedby'])) {
            $actorIds[] = (int)$role['updatedby'];
        }
        $actorMap = $this->buildLookupMap('s_entity', $actorIds, 's_name');

        $profile = [
            'id' => (int)$role['id'],
            'uid' => $role['uid'],
            'name' => $role['s_role_name'],
            'code' => $role['s_code'] ?? '',
            'description' => $role['s_description'] ?? '',
            'status_meta' => $status,
            'scope_meta' => $scopeMetaRow,
            'scope_slug' => $scopeSlug,
            'saas_slug' => $saasEnabled ? 'saas' : 'non_saas',
            'saas_label' => $saasEnabled ? 'SaaS' : 'Non-SaaS',
            'saas_badge' => $saasEnabled ? 'info' : 'secondary',
            'default_route' => $routeInfo,
            'created_at' => $this->formatTimestamp($role['createstamp'] ?? null),
            'updated_at' => $this->formatTimestamp($role['updatestamp'] ?? null),
        ];

        $activity = [
            'created' => [
                'timestamp' => $profile['created_at'],
                'actor' => $actorMap[(int)($role['createdby'] ?? 0)] ?? 'System',
            ],
            'updated' => [
                'timestamp' => $profile['updated_at'],
                'actor' => $actorMap[(int)($role['updatedby'] ?? 0)] ?? 'System',
            ],
        ];

        $detailStats = [
            'assignments' => $assignmentTotal,
            'spaces' => $spaceCount,
            'workspace_assignments' => $workspaceAssignments,
            'ms_assignments' => $msAssignments,
            'saas_label' => $profile['saas_label'],
            'scope_label' => $scopeMetaRow['label'] ?? ucfirst($scopeSlug),
            'has_route' => $routeInfo ? 'yes' : 'no',
        ];

        $spaceChartRows = $this->runData['db']->query(
            "SELECT s.s_name AS space_name, COUNT(*) AS total
             FROM s_space_membership m
             INNER JOIN s_space s ON s.id = m.space_id
             WHERE m.s_role_id = :role AND m.livestatus = '1' AND m.space_id > 0
             GROUP BY m.space_id
             ORDER BY total DESC, s.s_name ASC
             LIMIT 8",
            [':role' => $role['id']]
        );
        $spaceChart = ['labels' => [], 'values' => []];
        foreach ($spaceChartRows as $row) {
            $spaceChart['labels'][] = $row['space_name'] ?? 'Workspace';
            $spaceChart['values'][] = (int)($row['total'] ?? 0);
        }
        $charts = [
            'scope' => [
                'labels' => ['Workspace', 'MS'],
                'values' => [$workspaceAssignments, $msAssignments],
            ],
            'spaces' => $spaceChart,
        ];

        $diagnostics = [];
        if ($this->scopeUsesDefaultRoute($scopeSlug) && empty($role['s_default_route_id'])) {
            $diagnostics[] = 'missing_default_route';
        }
        if (!in_array($scopeSlug, ['platform', 'workspace', 'global'], true)) {
            $diagnostics[] = 'invalid_scope';
        }
        if (count($assignments) === 0) {
            $diagnostics[] = 'unused';
        }

        $safeName = htmlspecialchars($role['s_role_name'] ?? 'Role', ENT_QUOTES, 'UTF-8');
        $this->runData['route']['h1'] = 'Role · ' . $safeName;
        $this->runData['route']['meta_title'] = 'Role: ' . ($role['s_role_name'] ?? '');
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Users & Roles' => $radAdminUrl . '/user/view',
            'Roles' => $radAdminUrl . '/role/view',
            $safeName => '',
        ];
        $this->runData['route']['backlink'] = $radAdminUrl . '/role/view';
        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Inspect default routes and memberships for this role.';
        }

        $this->runData['data']['role'] = $role;
        $this->runData['data']['role_profile'] = $profile;
        $this->runData['data']['role_assignments'] = $assignments;
        $this->runData['data']['role_detail_stats'] = $detailStats;
        $this->runData['data']['role_activity'] = $activity;
        $this->runData['data']['role_diagnostics'] = $diagnostics;
        $this->runData['data']['role_charts'] = $charts;
        $this->runData['data']['role_assignment_pagination'] = [
            'page' => $assignmentPage,
            'per_page' => $assignmentPerPage,
            'total' => $assignmentTotal,
            'total_pages' => $assignmentPages,
        ];
        $archiveContext = $this->buildArchiveContext($role);
        $this->runData['data']['role_archive'] = $archiveContext;

        return $this->runData;
    }

    public function sniff() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        $uid = $this->runData['route']['pathparts'][3] ?? '';
        if ($uid === '') {
            $filters = $this->buildRoleViewFilters();
            $allRoles = $this->buildEnrichedRoles();
            $roles = $this->filterRoles($allRoles, $filters);
            $payload = $this->buildRoleListSniffPayload($roles, $filters);
            $this->runData['data']['sniff_payload'] = $payload;
            $this->runData['data']['sniff_mode'] = 'collection';
            $this->runData['route']['h1'] = 'Meta Sniff: Roles';
            $this->runData['route']['meta_title'] = 'Meta Sniff - Roles';
            $this->runData['route']['backlink'] = $radAdminUrl . '/role/view';
            return $this->runData;
        }

        $roleRows = $this->runData['db']->select('s_role', ['uid' => $uid], true);
        if (count($roleRows) !== 1) {
            throw new \Exception('Role not found.', 404);
        }

        $role = $roleRows[0];
        $defaultRoute = null;
        if (!empty($role['s_default_route_id'])) {
            $routeRows = $this->runData['db']->select('s_msroute', ['id' => (int)$role['s_default_route_id']], true);
            if (!empty($routeRows)) {
                $defaultRoute = [
                    'id' => (int)($routeRows[0]['id'] ?? 0),
                    'uid' => (string)($routeRows[0]['uid'] ?? ''),
                    'name' => (string)($routeRows[0]['s_name'] ?? ''),
                ];
            }
        }

        $assignmentRows = $this->runData['db']->query(
            "SELECT m.id,
                    m.space_id,
                    m.s_scope_level,
                    m.s_ms_id,
                    s.uid AS space_uid,
                    s.s_name AS space_name,
                    ms.uid AS ms_uid,
                    ms.s_name AS ms_name,
                    e.id AS user_id,
                    e.uid AS user_uid,
                    e.s_name AS user_name
             FROM s_space_membership m
             INNER JOIN s_entity e ON e.id = m.s_entity_id
             LEFT JOIN s_space s ON s.id = m.space_id
             LEFT JOIN s_ms ms ON ms.id = m.s_ms_id
             WHERE m.s_role_id = :role AND m.livestatus = '1'
             ORDER BY s.s_name ASC, ms.s_name ASC, e.s_name ASC",
            [':role' => (int)$role['id']]
        );

        $assignments = [];
        foreach ($assignmentRows as $row) {
            $assignments[] = [
                'assignment_id' => (int)($row['id'] ?? 0),
                'type' => (string)($row['s_scope_level'] ?? 'workspace'),
                'space_id' => (int)($row['space_id'] ?? 0),
                'space_uid' => (string)($row['space_uid'] ?? ''),
                'space' => (string)($row['space_name'] ?? ''),
                'ms_id' => (int)($row['s_ms_id'] ?? 0),
                'ms_uid' => (string)($row['ms_uid'] ?? ''),
                'microservice' => (string)($row['ms_name'] ?? ''),
                'user_id' => (int)($row['user_id'] ?? 0),
                'user_uid' => (string)($row['user_uid'] ?? ''),
                'user' => (string)($row['user_name'] ?? ''),
            ];
        }

        $payload = [
            'object' => [
                'kind' => 'role',
                'id' => (int)($role['id'] ?? 0),
                'uid' => (string)($role['uid'] ?? ''),
                'role' => (string)($role['s_role_name'] ?? ''),
                'type' => (string)($role['s_scope'] ?? ''),
                'code' => (string)($role['s_code'] ?? ''),
            ],
            'default_route' => $defaultRoute,
            'assignments' => $assignments,
            'stats' => [
                'assignment_count' => count($assignments),
            ],
        ];

        $this->runData['data']['role'] = $role;
        $this->runData['data']['sniff_payload'] = $payload;
        $this->runData['data']['sniff_mode'] = 'single';
        $this->runData['route']['h1'] = 'Meta Sniff: ' . ($role['s_role_name'] ?? '');
        $this->runData['route']['meta_title'] = 'Meta Sniff - ' . ($role['s_role_name'] ?? '');
        $this->runData['route']['backlink'] = $radAdminUrl . '/role/viewone/' . $role['uid'];

        return $this->runData;
    }

    public function add() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $supportsMsScope = $this->roleSupportsMsScope();
        // alert
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Here, you can add a new user role.';
        }
        // Check the post data
        if(isset($this->runData['request']->post['s_role_name'])) {
            // print '<pre>';print_r($this->runData['request']->post());print '</pre>';die('here');
            // Insert the role
            $scope = $this->runData['request']->post['s_scope'] ?? 'platform';
            $defaultRouteId = $this->runData['request']->post['s_default_route_id'] ?? '';
            $defaultRouteId = $defaultRouteId !== '' ? (int)$defaultRouteId : null;
            $msId = isset($this->runData['request']->post['s_ms_id']) ? (int)$this->runData['request']->post['s_ms_id'] : null;
            if (!$this->scopeUsesDefaultRoute($scope)) {
                $defaultRouteId = null;
            }
            if (!in_array($scope, ['platform', 'workspace', 'global'], true)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid role scope selected.';
                return $this->runData;
            }
            $msId = null;
            $insertData = [
                's_role_name' => $this->runData['request']->post['s_role_name'],
                's_scope' => $scope,
                's_default_route_id' => $defaultRouteId,
            ];
            if ($supportsMsScope) {
                $insertData['s_ms_id'] = $msId;
            }
            $this->runData['db']->insert('s_role', $insertData);
            // Set the alert
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'Role added successfully';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // redirect to role list
            $redirectUrl = '/rad-admin/role/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        $this->runData['route']['h1'] = 'Add User Role';
        $this->runData['route']['meta_title'] = 'Add User Role';
        $this->runData['data']['role'] = [];
        $this->runData['data']['supports_ms_scope'] = $supportsMsScope;
        $this->runData['data']['route_options'] = $this->fetchRouteOptions();
        $this->runData['data']['microservices'] = $this->runData['db']->select('s_ms', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        // backlink
        $this->runData['route']['backlink'] = '/rad-admin/role/view';
        return $this->runData;
    }

    public function edit() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $supportsMsScope = $this->roleSupportsMsScope();
        // check the pathparts[3] is set
        if(!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Role not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // redirect to role list
            $redirectUrl = '/rad-admin/role/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        // find the role from the UID in pathparts[2]
        // print '<pre>';print_r($this->runData['route']['pathparts']);print '</pre>';die('here');
        $roleRows = $this->runData['db']->select('s_role', ['uid' => $this->runData['route']['pathparts'][3]], true);
        // print '<pre>';print_r($roleRows);print '</pre>';die('here');
        if(!$roleRows) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Role not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // redirect to role list
            $redirectUrl = '/rad-admin/role/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        $this->runData['data']['role'] = $roleRows[0];
        // print '<pre>';print_r($this->runData['data']['role']);print '</pre>';die('here');
        $selectedRouteId = $this->runData['data']['role']['s_default_route_id'] ?? null;
        $this->runData['data']['supports_ms_scope'] = $supportsMsScope;
        $this->runData['data']['route_options'] = $this->fetchRouteOptions();
        $this->runData['data']['microservices'] = $this->runData['db']->select('s_ms', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        // Check the post data
        if(isset($this->runData['request']->post['s_role_name'])) {
            // print '<pre>';print_r($this->runData['request']->post());print '</pre>';die('here');
            // Update the role
            $scope = $this->runData['request']->post['s_scope'] ?? 'platform';
            $defaultRouteId = $this->runData['request']->post['s_default_route_id'] ?? '';
            $defaultRouteId = $defaultRouteId !== '' ? (int)$defaultRouteId : null;
            $msId = isset($this->runData['request']->post['s_ms_id']) ? (int)$this->runData['request']->post['s_ms_id'] : null;
            if (!$this->scopeUsesDefaultRoute($scope)) {
                $defaultRouteId = null;
            }
            if (!in_array($scope, ['platform', 'workspace', 'global'], true)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid role scope selected.';
                return $this->runData;
            }
            $msId = null;
            $updateData = [
                's_role_name' => $this->runData['request']->post['s_role_name'],
                's_scope' => $scope,
                's_default_route_id' => $defaultRouteId,
            ];
            if ($supportsMsScope) {
                $updateData['s_ms_id'] = $msId;
            }
            $roleId = isset($this->runData['request']->post['role_id']) ? (int)$this->runData['request']->post['role_id'] : 0;
            $updateWhere = $roleId > 0 ? ['id' => $roleId] : ['uid' => $this->runData['route']['pathparts'][3]];
            $this->runData['db']->update('s_role', $updateData, $updateWhere);
            // Set the alert
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'Role updated successfully';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // redirect to role list
            $redirectUrl = '/rad-admin/role/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        $this->runData['route']['h1'] = 'Edit Role: ' . $this->runData['data']['role']['s_role_name'];
        $this->runData['route']['meta_title'] = 'Edit Role: ' . $this->runData['data']['role']['s_role_name'];
        // backlink
        $this->runData['route']['backlink'] = '/rad-admin/role/view';
        return $this->runData;
    }

    public function getroutes() {
        // check the post data
        if($this->runData['request']->post['s_ms_id']) {
            // print '<pre>';print_r($this->runData['request']->post());print '</pre>';die('here');
            // Get all the routes from the s_msroute table for s_ms_id = $this->runData['request']->post['s_ms_id']
            $routes = $this->runData['db']->select('s_msroute', ['s_ms_id' => $this->runData['request']->post['s_ms_id']], true);
            // create a dropdown list of routes with their ids as values and names as text. Also, mark the $this-runData['data']['role']['s_default_route_id'] as selected
            $routesDropdown = '';
            foreach($routes as $route) {
                $routesDropdown .= '<option value="' . $route['id'] . '" ' . ( ( isset($this->runData['data']['role']['s_default_route_id']) && ($this->runData['data']['role']['s_default_route_id'] == $route['id']) ) ? 'selected' : '') . '>' . $route['s_name'] . '</option>';
            }
            // print '<pre>';print_r($routesDropdown);print '</pre>';die('here');
            // return the routes dropdown
            print '<div class="form-group" id="route_form_group">
                <label for="s_default_route_id">Default Route <span class="text-danger">*</span></label>
                <select class="form-control" name="s_default_route_id" id="s_default_route_id" required>
                    ' . $routesDropdown . '
                </select>
                <div class="invalid-feedback">
                    Please choose a default route.
                </div>
                <small id="defaultRouteHelp" class="form-text text-muted">Select a default route for the system.</small>
            </div>';
            die();
        }
    }

    /*
     * Archive Role
     */
    public function archive() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('role_manage') || $priv->role() === 'developer') {
            throw new \Exception('Access denied.', 403);
        }
        if ((int)($this->runData['entity']['id'] ?? 0) !== 1) {
            throw new \Exception('Access denied.', 403);
        }
        // check the pathparts[3] is set
        if(!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Role not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // redirect to role list
            $redirectUrl = '/rad-admin/role/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        // find the role from the UID in pathparts[3]
        // print '<pre>';print_r($this->runData['route']['pathparts']);print '</pre>';die('here');
        $roleRows = $this->runData['db']->select('s_role', ['uid' => $this->runData['route']['pathparts'][3]], true);
        // print '<pre>';print_r($roleRows);print '</pre>';die('here');
        if(!$roleRows) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Role not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // redirect to role list
            $redirectUrl = '/rad-admin/role/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        // print '<pre>';print_r($roleRows);print '</pre>';die('here');
        $role = $roleRows[0];
        $archiveContext = $this->buildArchiveContext($role);
        $replacementRoleId = isset($this->runData['request']->post['replacement_role_id'])
            ? (int)$this->runData['request']->post['replacement_role_id']
            : 0;
        $hasAssignments = (int)($archiveContext['assignments_total'] ?? 0) > 0;
        if ($hasAssignments && $replacementRoleId <= 0) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Please choose a replacement role before archiving.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/role/viewone/' . $role['uid']);
            die();
        }
        if ($replacementRoleId > 0) {
            $scope = strtolower((string)($role['s_scope'] ?? 'platform'));
            $params = [':id' => $replacementRoleId, ':scope' => $scope];
            $sql = "SELECT id";
            if ($this->roleSupportsMsScope()) {
                $sql .= ", s_ms_id";
            }
            $sql .= " FROM s_role WHERE id = :id AND s_scope = :scope AND livestatus = '1'";
            if ($scope === 'ms' && $this->roleSupportsMsScope() && !empty($role['s_ms_id'])) {
                $sql .= " AND (s_ms_id = :ms OR s_ms_id IS NULL)";
                $params[':ms'] = (int)$role['s_ms_id'];
            }
            $valid = $this->runData['db']->query($sql, $params);
            if (empty($valid)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid replacement role selected.';
                $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/role/viewone/' . $role['uid']);
                die();
            }
        }

        if ($hasAssignments) {
            $scope = strtolower((string)($role['s_scope'] ?? 'platform'));
            if ($scope === 'platform') {
                $this->runData['db']->update(
                    's_entity',
                    ['s_nonsaas_role_id' => $replacementRoleId],
                    ['s_nonsaas_role_id' => (int)$role['id'], 's_type' => 'U']
                );
            } else {
                $where = ['s_role_id' => (int)$role['id'], 'livestatus' => '1'];
                if ($scope === 'workspace') {
                    $where['s_scope_level'] = 'workspace';
                } elseif ($scope === 'ms') {
                    $where['s_scope_level'] = 'ms';
                    if ($this->roleSupportsMsScope() && !empty($role['s_ms_id'])) {
                        $where['s_ms_id'] = (int)$role['s_ms_id'];
                    }
                }
                $this->runData['db']->update('s_space_membership', ['s_role_id' => $replacementRoleId], $where);
            }
        }

        $updateData = [
            'livestatus' => '2',
            'updatedby' => (int)($this->runData['entity']['id'] ?? 0),
        ];
        $updateWhere = [
            'uid' => $this->runData['route']['pathparts'][3],
        ];
        $this->runData['db']->update('s_role', $updateData, $updateWhere);
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Role <strong>'. $role['s_role_name'] .'</strong> archived successfully';
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/role/view');
        die();
        return $this->runData;
    }

    private function buildArchiveContext(array $role): array {
        $scope = strtolower((string)($role['s_scope'] ?? 'platform'));
        $roleId = (int)($role['id'] ?? 0);
        $assignments = [];
        if ($scope === 'platform') {
            $rows = $this->runData['db']->query(
                "SELECT id, uid, s_name, s_identity, s_email
                 FROM s_entity
                 WHERE s_type = 'U' AND s_nonsaas_role_id = :role AND livestatus = '1'
                 ORDER BY s_name",
                [':role' => $roleId]
            );
            foreach ($rows as $row) {
                $assignments[] = [
                    'user_id' => (int)($row['id'] ?? 0),
                    'user_uid' => (string)($row['uid'] ?? ''),
                    'user_name' => (string)($row['s_name'] ?? ''),
                    'username' => (string)($row['s_identity'] ?? ''),
                    'email' => (string)($row['s_email'] ?? ''),
                    'scope_level' => 'platform',
                    'space_name' => '',
                    'ms_name' => '',
                ];
            }
        } else {
            $where = "m.s_role_id = :role AND m.livestatus = '1'";
            $params = [':role' => $roleId];
            if ($scope === 'workspace') {
                $where .= " AND m.s_scope_level = 'workspace'";
            } elseif ($scope === 'ms') {
                $where .= " AND m.s_scope_level = 'ms'";
                if (!empty($role['s_ms_id'])) {
                    $where .= " AND m.s_ms_id = :ms";
                    $params[':ms'] = (int)$role['s_ms_id'];
                }
            }
            $rows = $this->runData['db']->query(
                "SELECT m.id AS membership_id,
                        m.space_id,
                        m.s_scope_level,
                        m.s_ms_id,
                        s.s_name AS space_name,
                        ms.s_name AS ms_name,
                        e.id AS user_id,
                        e.uid AS user_uid,
                        e.s_name AS user_name,
                        e.s_identity AS username,
                        e.s_email AS email
                 FROM s_space_membership m
                 INNER JOIN s_entity e ON e.id = m.s_entity_id
                 LEFT JOIN s_space s ON s.id = m.space_id
                 LEFT JOIN s_ms ms ON ms.id = m.s_ms_id
                 WHERE {$where}
                 ORDER BY s.s_name, ms.s_name, e.s_name",
                $params
            );
            foreach ($rows as $row) {
                $assignments[] = [
                    'user_id' => (int)($row['user_id'] ?? 0),
                    'user_uid' => (string)($row['user_uid'] ?? ''),
                    'user_name' => (string)($row['user_name'] ?? ''),
                    'username' => (string)($row['username'] ?? ''),
                    'email' => (string)($row['email'] ?? ''),
                    'scope_level' => (string)($row['s_scope_level'] ?? ''),
                    'space_name' => (string)($row['space_name'] ?? ''),
                    'ms_name' => (string)($row['ms_name'] ?? ''),
                ];
            }
        }

        $selectCols = "id, s_role_name, s_scope";
        if ($this->roleSupportsMsScope()) {
            $selectCols .= ", s_ms_id";
        }
        $replacementRows = $this->runData['db']->query(
            "SELECT {$selectCols}
             FROM s_role
             WHERE livestatus = '1' AND s_scope = :scope AND id != :id
             ORDER BY s_role_name",
            [':scope' => $scope, ':id' => $roleId]
        );
        if ($scope === 'ms' && $this->roleSupportsMsScope() && !empty($role['s_ms_id'])) {
            $replacementRows = array_values(array_filter($replacementRows, function ($row) use ($role) {
                return empty($row['s_ms_id']) || (int)$row['s_ms_id'] === (int)$role['s_ms_id'];
            }));
        }

        return [
            'assignments' => $assignments,
            'assignments_total' => count($assignments),
            'replacement_roles' => $replacementRows,
            'can_archive' => ((int)($this->runData['entity']['id'] ?? 0) === 1),
        ];
    }

    private function trimText(string $text, int $limit): string {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $limit - 3)) . '...';
    }

    private function fetchRoutesByIds(array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            return [];
        }
        $params = [];
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $placeholder = ':route' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }
        $sql = "SELECT id, uid, s_name, s_ms_id FROM s_msroute WHERE id IN (" . implode(',', $placeholders) . ")";
        $rows = $this->runData['db']->query($sql, $params);

        $map = [];
        foreach ($rows as $row) {
            if (!isset($row['id'])) {
                continue;
            }
            $map[(int)$row['id']] = $row;
        }
        return $map;
    }

    private function fetchMicroservicesByIds(array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            return [];
        }
        $params = [];
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $placeholder = ':ms' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }
        $sql = "SELECT id, uid, s_name FROM s_ms WHERE id IN (" . implode(',', $placeholders) . ")";
        $rows = $this->runData['db']->query($sql, $params);

        $map = [];
        foreach ($rows as $row) {
            if (!isset($row['id'])) {
                continue;
            }
            $map[(int)$row['id']] = $row;
        }
        return $map;
    }

    private function buildLookupMap(string $table, array $ids, string $labelColumn): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            return [];
        }
        $params = [];
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $placeholder = ':id' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }
        $sql = "SELECT id, {$labelColumn} AS label FROM {$table} WHERE id IN (" . implode(',', $placeholders) . ")";
        $rows = $this->runData['db']->query($sql, $params);

        $map = [];
        foreach ($rows as $row) {
            if (!isset($row['id'])) {
                continue;
            }
            $map[(int)$row['id']] = $row['label'] ?? '';
        }
        return $map;
    }

    private function buildRoleAssignmentCounts(): array {
        $rows = $this->runData['db']->query(
            "SELECT s_role_id,
                    COUNT(*) AS assignments,
                    COUNT(DISTINCT space_id) AS spaces
             FROM s_space_membership
             WHERE livestatus = '1' AND s_role_id IS NOT NULL
             GROUP BY s_role_id"
        );
        $map = [];
        foreach ($rows as $row) {
            $roleId = (int)($row['s_role_id'] ?? 0);
            if ($roleId <= 0) {
                continue;
            }
            $map[$roleId] = [
                'assignments' => (int)($row['assignments'] ?? 0),
                'spaces' => (int)($row['spaces'] ?? 0),
            ];
        }
        return $map;
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

    private function formatTimestamp(?string $value): string {
        if (empty($value)) {
            return '—';
        }
        $timezone = TimeHelper::resolveTimezone(
            $this->runData['entity']['timezone'] ?? null,
            $this->runData['config']['sys']['timezone_default'] ?? null
        );
        return TimeHelper::formatUtc($value, $timezone, 'd M Y, H:i') ?? '—';
    }
}

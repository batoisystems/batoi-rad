<?php
namespace RadAdmin;
use DateTime;
use Core\Sys\FileVersionService;
use Core\Sys\BranchService;
class Route{
    use AiAssistAware;
    private $runData = [];
    // private $db;
    private $errorHandler;
    private $versionService;
    private $branchService;
    private $testHookHelper;
    public function __construct(array $runData) {
        $this->runData = $runData;
        // $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        $config = $runData['config'] ?? [];
        $this->versionService = new FileVersionService($config, function () {
            $entity = $this->runData['entity'] ?? [];
            if (!empty($entity['fullname'])) {
                return $entity['fullname'];
            }
            if (!empty($entity['username'])) {
                return $entity['username'];
            }
            return 'RAD Admin';
        });
        $this->branchService = new BranchService(
            $this->runData['db'],
            $config,
            $this->runData['entity'] ?? [],
            $this->runData['request'] ?? null
        );
        $hookPath = $runData['config']['dir']['admin'].'/classes/Testhookhelper.cls.php';
        if (file_exists($hookPath) && !class_exists('\\RadAdmin\\Testhookhelper', false)) {
            require_once $hookPath;
        }
        if (class_exists('\\RadAdmin\\Testhookhelper', false)) {
            $this->testHookHelper = new \RadAdmin\Testhookhelper($this->runData['db'], $this->errorHandler);
        }
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }

    public function detail() {
        $ref = $this->runData['route']['pathparts'][4] ?? ($this->runData['route']['pathparts'][3] ?? '');
        if ($ref === '') {
            throw new \Exception('Route identifier missing.', 404);
        }

        $routeRows = $this->locateRouteRecord($ref);
        if (count($routeRows) !== 1) {
            throw new \Exception('Route not found.', 404);
        }
        $route = $routeRows[0];

        $msRows = $this->runData['db']->select('s_ms', ['id' => $route['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Parent microservice missing.', 404);
        }
        $microservice = $msRows[0];

        $history = $this->fetchHistoryEntries('s_msroute', (int)$route['id']);
        $permissionService = $this->runData['permissionService'] ?? null;
        $routeBindings = $this->fetchBindingRoles('route', (int)$route['id']);
        $hasRouteBindings = !empty($routeBindings);
        $inheritsBindings = false;
        $isPublicMicroservice = strtolower($microservice['s_scope'] ?? '') === 'global';
        $allowedRoleScopes = $this->allowedBindingRoleScopesForMicroservice($microservice);
        $effectiveBindingSource = 'none';
        $effectiveBindings = [];
        $inheritedBindings = [];

        if ($hasRouteBindings) {
            $effectiveBindingSource = 'direct';
            $effectiveBindings = $routeBindings;
        } elseif (!$isPublicMicroservice) {
            $inheritedBindings = $this->fetchBindingRoles('ms', (int)$route['s_ms_id']);
            if (!empty($inheritedBindings)) {
                $inheritsBindings = true;
                $effectiveBindingSource = 'inherited';
                $effectiveBindings = $inheritedBindings;
            }
        }

        if ($isPublicMicroservice) {
            $hasRouteBindings = false;
            $inheritsBindings = false;
            $effectiveBindingSource = 'public';
            $effectiveBindings = [];
            $routeBindings = [];
            $inheritedBindings = [];
        }

        $routeBindings = $this->filterBindingRolesByAllowedScopes($routeBindings, $allowedRoleScopes);
        $inheritedBindings = $this->filterBindingRolesByAllowedScopes($inheritedBindings, $allowedRoleScopes);
        $effectiveBindings = $this->filterBindingRolesByAllowedScopes($effectiveBindings, $allowedRoleScopes);
        $effectiveBindingGroups = $this->groupBindingRolesByScope($effectiveBindings);

        $this->runData['data']['route'] = $route;
        $this->runData['data']['microservice'] = $microservice;
        $this->runData['data']['history'] = $history;
        $this->runData['data']['route_has_bindings'] = $hasRouteBindings;
        $this->runData['data']['route_inherits_bindings'] = $inheritsBindings;
        $this->runData['data']['route_permission_locked'] = $isPublicMicroservice;
        $this->runData['data']['route_direct_binding_roles'] = $routeBindings;
        $this->runData['data']['route_inherited_binding_roles'] = $inheritedBindings;
        $this->runData['data']['route_effective_binding_roles'] = $effectiveBindings;
        $this->runData['data']['route_effective_binding_role_groups'] = $effectiveBindingGroups;
        $this->runData['data']['route_effective_binding_source'] = $effectiveBindingSource;
        $this->runData['data']['allowed_binding_role_scopes'] = $allowedRoleScopes;
        $this->runData['data']['route_created_by'] = $this->resolveUserName($route['createdby'] ?? 0);
        $this->runData['data']['route_updated_by'] = $this->resolveUserName($route['updatedby'] ?? 0);
        $this->runData['data']['test_hooks'] = $this->testHookHelper ? $this->testHookHelper->fetchForRoute((int)$route['id']) : [];
        $this->runData['data']['test_hook_scope'] = 'route';
        $this->runData['data']['test_hook_ref'] = $route['id'];
        $helpLivePath = $this->getRouteHelpFilePath((string)($microservice['s_name'] ?? ''), (string)($route['s_name'] ?? ''), 'live');
        $helpBetaPath = $this->getRouteHelpFilePath((string)($microservice['s_name'] ?? ''), (string)($route['s_name'] ?? ''), 'beta');
        $helpLiveContent = $this->readFileSafe($helpLivePath);
        $this->runData['data']['route_help'] = [
            'exists' => is_file($helpLivePath),
            'beta_exists' => is_file($helpBetaPath),
            'live_path' => $helpLivePath,
            'beta_path' => $helpBetaPath,
            'excerpt' => $this->buildHelpExcerpt($helpLiveContent),
            'view_url' => $this->runData['route']['rad_admin_url'] . '/route/help/' . ($route['uid'] ?? ''),
            'edit_url' => $this->runData['route']['rad_admin_url'] . '/route/helpedit/' . ($route['uid'] ?? ''),
        ];

        $this->runData['route']['h1'] = 'Route Details';
        $this->runData['route']['meta_title'] = 'Route: ' . ($route['s_name'] ?? '');
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $microservice['uid'];
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            $microservice['s_name'] => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $microservice['uid'],
            'Routes' => $this->runData['route']['rad_admin_url'] . '/route/view/' . $microservice['uid'],
            $route['s_name'] => '',
        ];

        return $this->runData;
    }

    /**
     * View all Routes within a Microservice
     */
    public function view() {
        $perPageParam = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($this->isAllowedPerPage($perPageParam)) {
            $this->saveProfilePerPage($perPageParam);
        }
        $perPage = $this->isAllowedPerPage($perPageParam) ? $perPageParam : $this->getProfilePerPage(25);

        if (empty($this->runData['route']['pathparts'][3])) {
            $this->runData['request']->setAlert('Select a microservicelet to view its routes.', 'warning');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/view');
            exit;
        }
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Routes are the endpoints that are called with a request to the application';
        }
        // Get the Microservice details from s_ms table with uid = 3rd routeparts element
        $msRow = $this->runData['db']->select('s_ms', ['uid' => $this->runData['route']['pathparts'][3]], true);
        // print '<pre>';print_r($msRow);print '</pre>';die('here');
        if (count($msRow) != 1) {
            $this->runData['request']->setAlert('Invalid microservicelet reference.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/view');
            exit;
        }
        if (!empty($msRow[0]['id']) && \RadAdmin\VisibilityHelper::isRestrictedMs((int)$msRow[0]['id'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [])) {
            throw new \Exception('Access denied.', 404);
        }
        $this->runData['data']['ms'] = $msRow[0];
        // create a badge for status of the ms livestatus
        if ($this->runData['data']['ms']['livestatus'] == 0) {
            $livestatus_badge = '<span class="badge bg-info">Inactive</span>';
        }
        elseif ($this->runData['data']['ms']['livestatus'] == 1) {
            $livestatus_badge = '<span class="badge bg-success">Active</span>';
        }
        elseif ($this->runData['data']['ms']['livestatus'] == 2) {
            $livestatus_badge = '<span class="badge bg-danger">Archived</span>';
        }
        else {
            $livestatus_badge = '<span class="badge bg-warning">Suspended</span>';
        }

        // Create badge for access scope
        $msName = $this->runData['data']['ms']['s_name'] ?? '';
        $this->runData['route']['h1'] = 'Routes of the Microservicelet ' . $msName;
        $this->runData['route']['meta_title'] = 'Routes of the Microservicelet '.$msName;
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelet' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            $msName => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . ($this->runData['data']['ms']['uid'] ?? ''),
            'Routes' => ''
        ];
        // Select Route from s_msroute table with Microservice id
        $this->runData['data']['route'] = $this->runData['db']->select('s_msroute', ['s_ms_id' => $this->runData['data']['ms']['id']], true);
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        $this->runData['data']['can_bulk_archive'] = $priv->role() !== 'developer' && $priv->can('route_edit');
        $this->runData['data']['can_bulk_delete'] = ((int)($this->runData['entity']['id'] ?? 0) === 1) && $priv->can('route_edit');
        $this->runData['data']['per_page_pref'] = $perPage;
        // backlink for the route
        $this->runData['route']['backlink'] = $this->runData['config']['sys']['base_url'] . '/rad-admin/microservice/view/';
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
        return $this->runData;
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
        $this->runData['db']->update('s_entity', [
            's_definition' => json_encode($definition, JSON_UNESCAPED_SLASHES),
        ], ['id' => $entityId]);
    }

    private function loadEntityDefinition(): array {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return [];
        }
        $rows = $this->runData['db']->select('s_entity', ['id' => $entityId], true);
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

    /**
     * View all routes across microservicelets.
     */
    public function viewall() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('route_edit') && !$priv->can('view')) {
            throw new \Exception('Access denied.', 403);
        }

        $filters = [
            'search' => trim($this->runData['request']->get['search'] ?? ''),
            'scope' => trim($this->runData['request']->get['scope'] ?? ''),
            'livestatus' => trim($this->runData['request']->get['livestatus'] ?? ''),
            'has_bindings' => trim($this->runData['request']->get['has_bindings'] ?? ''),
        ];

        $routes = $this->runData['db']->query(
            "SELECT r.*, m.s_name AS ms_name, m.s_scope, m.uid AS s_ms_uid
             FROM s_msroute r
             LEFT JOIN s_ms m ON m.id = r.s_ms_id
             WHERE r.livestatus != '0'
             ORDER BY m.s_name, r.s_name",
            []
        );

        // Basic filtering in PHP for liveliness/scope/search
        $routes = array_values(array_filter($routes, function ($row) use ($filters) {
            if ($filters['livestatus'] !== '' && (string)($row['livestatus'] ?? '') !== $filters['livestatus']) {
                return false;
            }
            if ($filters['scope'] !== '' && strtolower($row['s_scope'] ?? '') !== strtolower($filters['scope'])) {
                return false;
            }
            if ($filters['search'] !== '') {
                $blob = strtolower(($row['s_name'] ?? '') . ' ' . ($row['ms_name'] ?? '') . ' ' . ($row['s_description'] ?? ''));
                if (strpos($blob, strtolower($filters['search'])) === false) {
                    return false;
                }
            }
            return true;
        }));

        // Compute binding flags
        foreach ($routes as &$r) {
            $r['has_bindings'] = $this->runData['permissionService']->hasBindings('route', (int)$r['id']);
        }
        unset($r);

        if ($filters['has_bindings'] === 'Y') {
            $routes = array_values(array_filter($routes, fn($r) => !empty($r['has_bindings'])));
        } elseif ($filters['has_bindings'] === 'N') {
            $routes = array_values(array_filter($routes, fn($r) => empty($r['has_bindings'])));
        }

        $this->runData['data']['routes'] = $routes;
        $this->runData['data']['filters'] = $filters;
        $this->runData['route']['h1'] = 'All Routes';
        $this->runData['route']['meta_title'] = 'Routes';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Routes' => '',
        ];
        return $this->runData;
    }
    
    /**
     * Add a Route
     */
    public function add() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('route_add')) {
            throw new \Exception('Access denied.', 403);
        }
        $msRow = $this->requireMicroserviceForRouteAction();
        
        // Set route alert and messages
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'The following form is meant to add a Route.';
        $this->runData['route']['h1'] = 'Add Route for Microservicelet ' . $msRow[0]['s_name'];
        $this->runData['route']['meta_title'] = 'Add Route for Microservicelet ' . $msRow[0]['s_name'];
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            $msRow[0]['s_name'] => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $msRow[0]['uid'],
            'Routes' => $this->runData['route']['rad_admin_url'] . '/route/view/' . $msRow[0]['uid'],
            'Add' => '',
        ];
    
        // Get Microservice details from s_ms table using the 3rd element in routeparts
        $this->runData['data']['ms'] = $msRow[0];
    
        if (isset($this->runData['request']->post['s_name'])) {
            $normalizedName = $this->normalizeRouteName((string)$this->runData['request']->post['s_name']);
            $serviceDefinition = '{}';
            if (!empty($this->runData['request']->post['s_service_definition'])) {
                $validation = $this->validateServiceDefinition((string)$this->runData['request']->post['s_service_definition']);
                if (!$validation['ok']) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = $validation['message'];
                    return $this->runData;
                }
                $serviceDefinition = $validation['json'];
            }
            $entityScope = (string)($this->runData['request']->post['s_entity_scope'] ?? 'U');
            $result = $this->createRouteRecord(
                $this->runData['data']['ms'],
                $normalizedName,
                (string)($this->runData['request']->post['s_description'] ?? ''),
                $entityScope,
                $serviceDefinition
            );

            if (!empty($result['created'])) {
                $this->runData['route']['alert'] = 'success';
                $this->runData['route']['alert_message'] = 'The Route ' . $normalizedName . ' has been added successfully.';
                $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/route/view/' . $this->runData['data']['ms']['uid'];
                header("Location: {$redirectUrl}");
                exit;
            }
            if (($result['reason'] ?? '') === 'duplicate') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Route already exists.';
            } else {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = $result['message'] ?? 'Unable to create route.';
            }
        }
    
        return $this->runData;
    }

    public function addmultiple() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('route_add')) {
            throw new \Exception('Access denied.', 403);
        }
        $msRow = $this->requireMicroserviceForRouteAction();
        $this->runData['data']['ms'] = $msRow[0];
        $this->runData['data']['route_names_input'] = (string)($this->runData['request']->post['route_names'] ?? '');
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'Add one route name per line. New routes will be created as Both User and API.';
        $this->runData['route']['h1'] = 'Add Multiple Routes for Microservicelet ' . $msRow[0]['s_name'];
        $this->runData['route']['meta_title'] = 'Add Multiple Routes for Microservicelet ' . $msRow[0]['s_name'];
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            $msRow[0]['s_name'] => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $msRow[0]['uid'],
            'Routes' => $this->runData['route']['rad_admin_url'] . '/route/view/' . $msRow[0]['uid'],
            'Add Multiple' => '',
        ];

        if (!isset($this->runData['request']->post['route_names'])) {
            return $this->runData;
        }

        $rawInput = (string)$this->runData['request']->post['route_names'];
        $lines = preg_split('/\R/', $rawInput) ?: [];
        $seenNames = [];
        $createdNames = [];
        $duplicateNames = [];
        $invalidNames = [];

        foreach ($lines as $line) {
            $normalizedName = $this->normalizeRouteName($line);
            if ($normalizedName === '') {
                if (trim((string)$line) !== '') {
                    $invalidNames[] = trim((string)$line);
                }
                continue;
            }
            if (isset($seenNames[$normalizedName])) {
                $duplicateNames[] = $normalizedName;
                continue;
            }
            $seenNames[$normalizedName] = true;
            $result = $this->createRouteRecord($this->runData['data']['ms'], $normalizedName, '', 'UA', '{}');
            if (!empty($result['created'])) {
                $createdNames[] = $normalizedName;
                continue;
            }
            if (($result['reason'] ?? '') === 'duplicate') {
                $duplicateNames[] = $normalizedName;
                continue;
            }
            $invalidNames[] = trim((string)$line);
        }

        if (!empty($createdNames)) {
            $summary = count($createdNames) . ' route' . (count($createdNames) === 1 ? '' : 's') . ' created';
            if (!empty($duplicateNames)) {
                $summary .= '; existing routes skipped: ' . implode(', ', array_values(array_unique($duplicateNames)));
            }
            if (!empty($invalidNames)) {
                $summary .= '; invalid lines skipped: ' . implode(', ', array_values(array_unique($invalidNames)));
            }
            $this->runData['request']->setAlert($summary . '.', 'success');
            $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/route/view/' . $this->runData['data']['ms']['uid'];
            header("Location: {$redirectUrl}");
            exit;
        }

        $this->runData['route']['alert'] = 'danger';
        $messages = [];
        if (!empty($duplicateNames)) {
            $messages[] = 'Existing routes skipped: ' . implode(', ', array_values(array_unique($duplicateNames)));
        }
        if (!empty($invalidNames)) {
            $messages[] = 'Invalid lines skipped: ' . implode(', ', array_values(array_unique($invalidNames)));
        }
        if (empty($messages)) {
            $messages[] = 'No valid route names were submitted';
        }
        $this->runData['route']['alert_message'] = implode('. ', $messages) . '.';
        return $this->runData;
    }

    private function requireMicroserviceForRouteAction(): array {
        $msUid = (string)($this->runData['route']['pathparts'][3] ?? '');
        $msRow = $this->runData['db']->select('s_ms', ['uid' => $msUid], true);
        if (count($msRow) !== 1) {
            throw new \Exception('Microservicelet not found.', 404);
        }
        if (!empty($msRow[0]['id']) && \RadAdmin\VisibilityHelper::isRestrictedMs((int)$msRow[0]['id'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [])) {
            throw new \Exception('Access denied.', 404);
        }
        return $msRow;
    }

    private function normalizeRouteName(string $name): string {
        $name = strtolower($name);
        $name = str_replace(' ', '-', $name);
        $name = preg_replace('/[^A-Za-z0-9\-]/', '', $name) ?? '';
        return trim($name, '-');
    }

    private function validateServiceDefinition(string $definition): array {
        $json = html_entity_decode($definition);
        $json = stripslashes($json);
        json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'ok' => false,
                'json' => '{}',
                'message' => 'Invalid JSON for Service Definition.',
            ];
        }
        return [
            'ok' => true,
            'json' => $json,
            'message' => '',
        ];
    }

    private function createRouteRecord(array $microservice, string $routeName, string $description, string $entityScope, string $serviceDefinition): array {
        if ($routeName === '') {
            return [
                'created' => false,
                'reason' => 'invalid',
                'message' => 'Route name is required.',
            ];
        }
        $existing = $this->runData['db']->select('s_msroute', [
            's_name' => $routeName,
            's_ms_id' => $microservice['id'],
        ], true);
        if (!empty($existing)) {
            return [
                'created' => false,
                'reason' => 'duplicate',
                'message' => 'Route already exists.',
            ];
        }

        $routeId = $this->runData['db']->insert('s_msroute', [
            's_ms_id' => $microservice['id'],
            's_name' => $routeName,
            's_description' => $description,
            's_degree' => 0,
            's_entity_scope' => $entityScope,
            's_service_definition' => $serviceDefinition,
        ]);
        if (!$routeId) {
            return [
                'created' => false,
                'reason' => 'error',
                'message' => 'Unable to create route.',
            ];
        }

        $routeKey = $this->getRouteFileKey(
            ['id' => $routeId, 's_name' => $routeName],
            $microservice['s_type'] ?? 'STA'
        );
        $this->ensureRouteFiles($microservice['s_name'], $routeKey);
        $this->ensureRouteHelpFile($microservice['s_name'], $routeName, $routeKey, (string)($microservice['s_type'] ?? 'STA'));
        $this->inheritMsBindingsToRoute((int)$microservice['id'], (int)$routeId);
        $this->logRouteActivity('create', $routeId, (int)$microservice['id'], $routeName, $description);

        return [
            'created' => true,
            'route_id' => $routeId,
            'route_name' => $routeName,
        ];
    }


    /**
     * Edit a Route
     */
    public function edit() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('route_edit')) {
            throw new \Exception('Access denied.', 403);
        }
        // add alert and alert_message to runData - information to be displayed to the user
        if (isset($this->runData['request']->post['route_id'])) {
            // Sanitize 's_name' to remove spaces, special characters and convert to lowercase but keep forward slash (/)
            $this->runData['request']->post['s_name'] = strtolower($this->runData['request']->post['s_name']);
            $this->runData['request']->post['s_name'] = str_replace(' ', '-', $this->runData['request']->post['s_name']);
            $this->runData['request']->post['s_name'] = preg_replace('/[^A-Za-z0-9\-\/]/', '', $this->runData['request']->post['s_name']);
            
            $routeDetails = $this->runData['db']->select('s_msroute', ['uid' => $this->runData['request']->post['route_id']], true);
            if (count($routeDetails) != 1) {
                throw new \Exception('Invalid Route', 404);
            }
            $msRow = $this->runData['db']->select('s_ms', ['id' => $routeDetails[0]['s_ms_id']], true);
            if (count($msRow) != 1) {
                throw new \Exception('Invalid Microservicelet', 404);
            }
            $ms = $this->runData['db']->select('s_ms', ['uid' => $this->runData['request']->post['ms_id']], true);
            
            if (count($ms) == 1 && $ms[0]['uid'] != $this->runData['request']->post['ms_id']) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Microservicelet already exists.';
            } else {
                // Set default value for $json_str
                $json_str = '{}';
    
                // Validate and decode the JSON
                if (!empty($this->runData['request']->post['s_service_definition'])) {
                    $json_str = html_entity_decode($this->runData['request']->post['s_service_definition']);
                    $json_str = stripslashes($json_str);
                    $json = json_decode($json_str);
    
                    if (json_last_error() != JSON_ERROR_NONE) {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Invalid JSON for Service Definition.';
                    }
                }
    
                if ($this->runData['route']['alert'] != 'danger') {
                    $this->runData['db']->update('s_msroute', [
                        's_name' => $this->runData['request']->post['s_name'],
                        's_description' => $this->runData['request']->post['s_description'],
                        's_degree' => 0,
                        's_entity_scope' => $this->runData['request']->post['s_entity_scope'],
                        's_service_definition' => $json_str
                    ], ['uid' => $this->runData['request']->post['route_id']]);

                    $this->logRouteActivity('update', (int)$routeDetails[0]['id'], (int)$msRow[0]['id'], $this->runData['request']->post['s_name'], $this->runData['request']->post['s_description']);
                    $msName = $msRow[0]['s_name'];
                    $msType = $msRow[0]['s_type'] ?? 'STA';
                    $oldKey = $this->getRouteFileKey($routeDetails[0], $msType);
                    $newKey = $this->getRouteFileKey(
                        ['id' => $routeDetails[0]['id'], 's_name' => $this->runData['request']->post['s_name']],
                        $msType
                    );
                    if ($oldKey !== $newKey) {
                        $this->renameRouteFiles($msName, $oldKey, $newKey);
                    }
                    $this->ensureRouteFiles($msName, $newKey);
                    if ((string)($routeDetails[0]['s_name'] ?? '') !== (string)$this->runData['request']->post['s_name']) {
                        $this->renameRouteHelpFile(
                            $msName,
                            (string)($routeDetails[0]['s_name'] ?? ''),
                            (string)$this->runData['request']->post['s_name']
                        );
                    }
                    $this->ensureRouteHelpFile($msName, (string)$this->runData['request']->post['s_name'], $newKey, $msType);

                    $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'The Route '.$this->runData['request']->post['s_name'].' has been updated successfully.';
                    $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                    $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/route/view/'.$ms[0]['uid'];
                    header("Location: {$redirectUrl}");exit;
                }
            }
        }
    
        $msRow = $this->runData['db']->select('s_ms', ['uid' => $this->runData['route']['pathparts'][4]], true);
        if (count($msRow) != 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $this->runData['data']['ms'] = $msRow[0];
        $routeRow = $this->runData['db']->select('s_msroute', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($routeRow) != 1) {
            throw new \Exception('Invalid Route', 404);
        }
        $this->runData['data']['route'] = $routeRow[0];
    
        if (!isset($this->runData['route']['alert']) || ($this->runData['route']['alert'] != 'danger')) {
            $this->runData['route']['alert'] = 'info';
        $this->runData['route']['h1'] = 'Edit Route '.$routeRow[0]['s_name'].' of Microservicelet '.$msRow[0]['s_name'];
            $this->runData['route']['meta_title'] = 'Edit Route '.$routeRow[0]['s_name'].' of Microservicelet '.$msRow[0]['s_name'];
        }
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            $msRow[0]['s_name'] => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $msRow[0]['uid'],
            'Routes' => $this->runData['route']['rad_admin_url'] . '/route/view/' . $msRow[0]['uid'],
            $routeRow[0]['s_name'] => $this->runData['route']['rad_admin_url'] . '/route/detail/' . $routeRow[0]['uid'],
            'Edit' => '',
        ];
        return $this->runData;
    }    

    /**
     * Archive a Route
     */
    public function archive() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() === 'developer' || !$priv->can('route_edit')) {
            throw new \Exception('Access denied', 403);
        }
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Route', 404);
        }
        $routeRow = $this->runData['db']->select('s_msroute', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($routeRow) != 1) {
            throw new \Exception('Invalid Route', 404);
        }
        // Archive the Route
        $this->runData['db']->update('s_msroute', ['livestatus' => '2'], ['id' => $routeRow[0]['id']]);
        // Add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Route archived successfully.';
        $this->logRouteActivity('archive', (int)$routeRow[0]['id'], (int)$routeRow[0]['s_ms_id'], $routeRow[0]['s_name'], $routeRow[0]['s_description'] ?? '');
        // Register alert into cookie
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        // Get Microservice uid fronm Microservice id
        $msRow = $this->runData['db']->select('s_ms', ['id' => $routeRow[0]['s_ms_id']], true);
        // Redirect to the Microservice listing page
        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/route/view/' . $msRow[0]['uid'];
        header("Location: {$redirectUrl}");exit;
    }

    /**
     * Bulk archive selected routes within a microservice.
     */
    public function bulkarchive() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() === 'developer' || !$priv->can('route_edit')) {
            throw new \Exception('Access denied', 403);
        }

        $msUid = (string)($this->runData['route']['pathparts'][3] ?? '');
        if ($msUid === '') {
            throw new \Exception('Invalid Microservicelet', 404);
        }

        $msRows = $this->runData['db']->select('s_ms', ['uid' => $msUid], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $ms = $msRows[0];
        if (\RadAdmin\VisibilityHelper::isRestrictedMs((int)$ms['id'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [])) {
            throw new \Exception('Access denied.', 404);
        }

        $request = $this->runData['request'];
        $post = $request->post ?? [];
        $csrfToken = $post['csrf_token'] ?? '';
        if (!$request->checkCSRFToken($csrfToken)) {
            $request->setAlert('Invalid request token. Please try again.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/route/view/' . $msUid);
            exit;
        }

        $routeIds = $post['route_ids'] ?? [];
        if (!is_array($routeIds)) {
            $routeIds = [];
        }
        $routeIds = array_values(array_unique(array_filter(array_map('intval', $routeIds))));
        if (empty($routeIds)) {
            $request->setAlert('Select at least one route to archive.', 'warning');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/route/view/' . $msUid);
            exit;
        }

        $params = [':msid' => (int)$ms['id']];
        $in = [];
        foreach ($routeIds as $idx => $rid) {
            $key = ':rid' . $idx;
            $in[] = $key;
            $params[$key] = $rid;
        }
        $rows = $this->runData['db']->query(
            "SELECT id, uid, s_name, s_description, livestatus
             FROM s_msroute
             WHERE s_ms_id = :msid
               AND id IN (" . implode(',', $in) . ")",
            $params
        );

        if (empty($rows)) {
            $request->setAlert('No valid routes found for bulk archive.', 'warning');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/route/view/' . $msUid);
            exit;
        }

        $archived = 0;
        $alreadyArchived = 0;
        foreach ($rows as $row) {
            $routeId = (int)($row['id'] ?? 0);
            if ($routeId <= 0) {
                continue;
            }
            if ((string)($row['livestatus'] ?? '') === '2') {
                $alreadyArchived++;
                continue;
            }
            $this->runData['db']->update('s_msroute', ['livestatus' => '2'], ['id' => $routeId]);
            $this->logRouteActivity('archive', $routeId, (int)$ms['id'], (string)($row['s_name'] ?? ''), (string)($row['s_description'] ?? ''));
            $archived++;
        }

        $message = sprintf('Bulk archive complete: %d archived, %d already archived.', $archived, $alreadyArchived);
        $request->setAlert($message, $archived > 0 ? 'success' : 'info');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/route/view/' . $msUid);
        exit;
    }

    /**
     * Bulk delete selected routes within a microservice (entity_id = 1 only).
     * Deletes DB rows from s_msroute and matching route files under rad/ms/{ms_name}/route.{key}.*
     */
    public function bulkdelete() {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($entityId !== 1 || !$priv->can('route_edit')) {
            throw new \Exception('Access denied', 403);
        }

        $msUid = (string)($this->runData['route']['pathparts'][3] ?? '');
        if ($msUid === '') {
            throw new \Exception('Invalid Microservicelet', 404);
        }

        $msRows = $this->runData['db']->select('s_ms', ['uid' => $msUid], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $ms = $msRows[0];
        if (\RadAdmin\VisibilityHelper::isRestrictedMs((int)$ms['id'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [])) {
            throw new \Exception('Access denied.', 404);
        }

        $request = $this->runData['request'];
        $post = $request->post ?? [];
        $csrfToken = $post['csrf_token'] ?? '';
        if (!$request->checkCSRFToken($csrfToken)) {
            $request->setAlert('Invalid request token. Please try again.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/route/view/' . $msUid);
            exit;
        }

        $routeIds = $post['route_ids'] ?? [];
        if (!is_array($routeIds)) {
            $routeIds = [];
        }
        $routeIds = array_values(array_unique(array_filter(array_map('intval', $routeIds))));
        if (empty($routeIds)) {
            $request->setAlert('Select at least one route to delete.', 'warning');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/route/view/' . $msUid);
            exit;
        }

        $params = [':msid' => (int)$ms['id']];
        $in = [];
        foreach ($routeIds as $idx => $rid) {
            $key = ':rid' . $idx;
            $in[] = $key;
            $params[$key] = $rid;
        }
        $rows = $this->runData['db']->query(
            "SELECT id, uid, s_name, s_description, livestatus, s_ms_id
             FROM s_msroute
             WHERE s_ms_id = :msid
               AND id IN (" . implode(',', $in) . ")",
            $params
        );

        if (empty($rows)) {
            $request->setAlert('No valid routes found for bulk delete.', 'warning');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/route/view/' . $msUid);
            exit;
        }

        $deleted = 0;
        $filesDeleted = 0;
        $deletedIds = [];
        $msType = (string)($ms['s_type'] ?? 'STA');
        $msName = (string)($ms['s_name'] ?? '');
        foreach ($rows as $row) {
            $routeId = (int)($row['id'] ?? 0);
            if ($routeId <= 0) {
                continue;
            }

            $this->logRouteActivity('destroy', $routeId, (int)$ms['id'], (string)($row['s_name'] ?? ''), (string)($row['s_description'] ?? ''));
            $filesDeleted += $this->deleteRouteFilesForRow($msName, $row, $msType);
            $filesDeleted += $this->deleteRouteHelpFiles($msName, (string)($row['s_name'] ?? ''));

            $this->runData['db']->delete('s_permission_binding', [
                's_object_type' => 'route',
                's_object_id' => $routeId,
            ]);
            $this->runData['db']->delete('s_msroute', ['id' => $routeId]);
            $deleted++;
            $deletedIds[$routeId] = true;
        }

        $defaultRouteId = (int)($ms['s_default_route_id'] ?? 0);
        if ($defaultRouteId > 0 && isset($deletedIds[$defaultRouteId])) {
            $replacement = $this->runData['db']->query(
                "SELECT id
                 FROM s_msroute
                 WHERE s_ms_id = :msid
                 ORDER BY id ASC
                 LIMIT 1",
                [':msid' => (int)$ms['id']]
            );
            $newDefault = (int)($replacement[0]['id'] ?? 0);
            $this->runData['db']->update('s_ms', ['s_default_route_id' => $newDefault], ['id' => (int)$ms['id']]);
        }

        $message = sprintf('Bulk delete complete: %d route(s) removed, %d file(s) deleted.', $deleted, $filesDeleted);
        $request->setAlert($message, $deleted > 0 ? 'success' : 'info');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/route/view/' . $msUid);
        exit;
    }

    /**
     * Activate a Route
     */
    public function activate() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() === 'developer' || !$priv->can('route_edit')) {
            throw new \Exception('Access denied', 403);
        }
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Route', 404);
        }
        $routeRow = $this->runData['db']->select('s_msroute', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($routeRow) != 1) {
            throw new \Exception('Invalid Route', 404);
        }
        // print '<pre>';print_r($routeRow);print '</pre>';die('here');
        // Archive the Route
        $this->runData['db']->update('s_msroute', ['livestatus' => '1'], ['id' => $routeRow[0]['id']]);
        // Add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Route activated successfully.';
        $this->logRouteActivity('activate', (int)$routeRow[0]['id'], (int)$routeRow[0]['s_ms_id'], $routeRow[0]['s_name'], $routeRow[0]['s_description'] ?? '');
        // Register alert into cookie
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        // Redirect to the Microservice listing page
        // Get Microservice uid fronm Microservice id
        $msRow = $this->runData['db']->select('s_ms', ['id' => $routeRow[0]['s_ms_id']], true);
        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/route/view/' . $msRow[0]['uid'];
        header("Location: {$redirectUrl}");exit;
    }

    /**
     * Code for a Route
     */
    public function code () {
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Edit the code for the Route.';
        }
        $routeRow = $this->runData['db']->select('s_msroute', ['uid' => $this->runData['route']['pathparts'][3]], true);
        // print '<pre>';print_r($routeRow);print '<br/>'.count($routeRow);print '</pre>';die('here');
        // $requestId = uniqid();
        // $this->errorHandler->reportError("Request ID $requestId: " . $_SERVER['REQUEST_URI']);
        if (count($routeRow) != 1) {
            $this->errorHandler->reportError("Invalid Route found");
            exit;
        }
        $this->runData['data']['route'] = $routeRow[0];
        // print '<pre>';print_r($this->runData['data']['route']);print '</pre>';die('here');
        // create a badge for status of the route livestatus
        if ($this->runData['data']['route']['livestatus'] == 0) {
            $livestatus_badge = '<span class="badge bg-info">Inactive</span>';
        }
        elseif ($this->runData['data']['route']['livestatus'] == 1) {
            $livestatus_badge = '<span class="badge bg-success">Active</span>';
        }
        elseif ($this->runData['data']['route']['livestatus'] == 2) {
            $livestatus_badge = '<span class="badge bg-danger">Archived</span>';
        }
        else {
            $livestatus_badge = '<span class="badge bg-warning">Suspended</span>';
        }
        // get the Microservice details from s_ms table
        $msRow = $this->runData['db']->select('s_ms', ['id' => $this->runData['data']['route']['s_ms_id']], true);
        // print '<pre>';print_r($msRow);print '</pre>';die('here');
        if (count($msRow) != 1) {
            $this->errorHandler->reportError("Invalid Microservicelet found");
            exit;
        }
        $this->runData['data']['route']['ms_name'] = $msRow[0]['s_name'];
        $this->runData['data']['route']['ms_type'] = $msRow[0]['s_type'] ?? 'STA';
        $routeKey = $this->getRouteFileKey($this->runData['data']['route'], $this->runData['data']['route']['ms_type']);

        $branch = $this->branchService->resolveEditorBranch();
        $this->runData['data']['branch'] = $branch;
        $this->runData['data']['branch_status'] = $this->branchService->getRouteBranchStatus((int)$this->runData['data']['route']['id']);
        $this->runData['data']['branch_has_beta'] = $this->branchService->hasRouteBetaFiles(
            $this->runData['data']['route']['ms_name'],
            $routeKey
        );
        $this->runData['data']['branch_can_manage'] = $this->branchService->canUseBeta();
        $this->runData['data']['branch_can_merge'] = $this->branchService->canMerge();
        $this->runData['data']['preview_can_manage'] = $this->branchService->canUsePreview();
        $this->runData['data']['preview_context'] = $this->branchService->getPreviewContext();
        $this->runData['data']['preview_active'] = $this->branchService->isPreviewActiveFor([
            'ms_name' => $this->runData['data']['route']['ms_name'],
            'route_key' => $routeKey,
        ]);
        try {
            $this->runData['data']['branch_history'] = $this->runData['db']->query(
                "SELECT * FROM s_branch
                 WHERE s_object_type = 'route' AND s_object_id = :rid
                 ORDER BY id DESC
                 LIMIT 10",
                [':rid' => (int)$this->runData['data']['route']['id']]
            );
        } catch (\Throwable $e) {
            $this->runData['data']['branch_history'] = [];
        }

        // Get $this->runData['data']['code_*'] from live or beta files depending on branch.
        // Create live files if missing; beta files are created explicitly via branch action.
        $msDir = $this->runData['config']['dir']['ms'] . '/' . $this->runData['data']['route']['ms_name'];
        if (!file_exists($msDir)) {
            mkdir($msDir, 0777, true);
        }
        if ($branch !== 'beta') {
            if (!file_exists($msDir . '/route.' . $routeKey . '.php')) {
                file_put_contents($msDir . '/route.' . $routeKey . '.php', '');
            }
            if (!file_exists($msDir . '/route.' . $routeKey . '.pagepart.php')) {
                file_put_contents($msDir . '/route.' . $routeKey . '.pagepart.php', '');
            }
            if (!file_exists($msDir . '/route.' . $routeKey . '.prepart.php')) {
                file_put_contents($msDir . '/route.' . $routeKey . '.prepart.php', '');
            }
            if (!file_exists($msDir . '/route.' . $routeKey . '.postpart.php')) {
                file_put_contents($msDir . '/route.' . $routeKey . '.postpart.php', '');
            }
        } elseif (!$this->runData['data']['branch_has_beta']) {
            $this->runData['data']['branch_missing'] = true;
        }

        $this->runData['data']['code_load'] = $this->readFileSafe(
            $this->branchService->getRouteFilePath(
                $this->runData['data']['route']['ms_name'],
                $routeKey,
                'load',
                $branch,
                false
            )
        );
        $this->runData['data']['code_pagepart'] = $this->readFileSafe(
            $this->branchService->getRouteFilePath(
                $this->runData['data']['route']['ms_name'],
                $routeKey,
                'pagepart',
                $branch,
                false
            )
        );
        $this->runData['data']['code_prepart'] = $this->readFileSafe(
            $this->branchService->getRouteFilePath(
                $this->runData['data']['route']['ms_name'],
                $routeKey,
                'prepart',
                $branch,
                false
            )
        );
        $this->runData['data']['code_postpart'] = $this->readFileSafe(
            $this->branchService->getRouteFilePath(
                $this->runData['data']['route']['ms_name'],
                $routeKey,
                'postpart',
                $branch,
                false
            )
        );

        $versions = [];
        foreach (['load', 'pagepart', 'prepart', 'postpart'] as $partKey) {
            $itemId = $this->getRouteVersionItemId($this->runData['data']['route']['ms_name'], $routeKey, $partKey, $branch);
            $versions[$partKey] = $this->versionService->listVersions('route', $itemId);
        }
        $this->runData['data']['versions'] = $versions;
        $this->runData['route']['h1'] = 'Route: '.$this->runData['data']['route']['s_name'];
        $this->runData['route']['meta_title'] = 'Route: '.$this->runData['data']['route']['s_name'];
        // get ms uid from ms id from s_ms table
        $msRow = $this->runData['db']->select('s_ms', ['id' => $this->runData['data']['route']['s_ms_id']], true);
        $msUid = $msRow[0]['uid'];
        $this->runData['route']['backlink'] = $this->runData['config']['sys']['base_url'] . '/rad-admin/route/view/'.$msUid;
        return $this->runData;
    }

    /**
     * Save code for a Route
     */
    public function codesave() {
        $data = json_decode(file_get_contents("php://input"), true);
    
        $response = [];
        header('Content-Type: application/json');
    
        if (!$data || !isset($data['type']) || !isset($data['content'])) {
            $response = ['message' => 'Invalid data provided'];
            echo json_encode($response);
            exit;
        }
    
        $type = $data['type'];
        $content = $data['content'];
        $createVersion = !empty($data['create_version']);
    
        $branch = $this->branchService->resolveEditorBranch();
        $file_path = '';
        $msName = $this->runData['route']['pathparts'][3] ?? '';
        $routeRef = $this->runData['route']['pathparts'][4] ?? '';
        $routeKey = $this->resolveRouteFileKey($routeRef);
        switch ($type) {
            case 'load':
                $file_path = $this->branchService->getRouteFilePath(
                    $msName,
                    $routeKey,
                    'load',
                    $branch,
                    false
                );
                break;
            case 'pagepart':
                $file_path = $this->branchService->getRouteFilePath(
                    $msName,
                    $routeKey,
                    'pagepart',
                    $branch,
                    false
                );
                break;
            case 'prepart':
                $file_path = $this->branchService->getRouteFilePath(
                    $msName,
                    $routeKey,
                    'prepart',
                    $branch,
                    false
                );
                break;
            case 'postpart':
                $file_path = $this->branchService->getRouteFilePath(
                    $msName,
                    $routeKey,
                    'postpart',
                    $branch,
                    false
                );
                break;
            default:
                $response = ['message' => 'Error: Invalid type'];
                echo json_encode($response);
                exit;
        }
    
        // Save the content to the file
        if ($branch === 'beta' && !$this->branchService->hasRouteBetaFiles($msName, $routeKey)) {
            $response = ['message' => 'Create a beta branch before saving beta code.'];
            echo json_encode($response);
            exit;
        }
        if ($file_path) {
            if (file_put_contents($file_path, $content) === false) {
                $response = ['message' => 'Failed to save the content'];
                echo json_encode($response);
                exit;
            }

            if ($createVersion) {
                $this->snapshotRouteCode($type, $content, $branch);
            }
            // $response = ['message' => 'Saved successfully'];
            $response = ['message' => ''];
            echo json_encode($response);
            exit;
        }
    }

    public function branchcreate() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('route_edit') || !$this->branchService->canUseBeta()) {
            throw new \Exception('Access denied.', 403);
        }
        $ref = $this->runData['route']['pathparts'][3] ?? '';
        if ($ref === '') {
            throw new \Exception('Route identifier missing.', 404);
        }
        $routeRows = $this->locateRouteRecord($ref);
        if (count($routeRows) !== 1) {
            throw new \Exception('Route not found.', 404);
        }
        $route = $routeRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $route['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Microservicelet not found.', 404);
        }
        $msName = $msRows[0]['s_name'];
        $routeKey = $this->getRouteFileKey($route, $msRows[0]['s_type'] ?? 'STA');
        $result = $this->branchService->createRouteBeta($msName, (int)$route['id'], $routeKey);
        $this->runData['request']->setAlert($result['message'], $result['status'] ? 'success' : 'danger');
        $redirect = $this->getRouteWorkspaceRedirect((string)($route['uid'] ?? ''), (string)($msRows[0]['uid'] ?? ''), 'beta');
        header("Location: {$redirect}");
        exit;
    }

    public function branchmerge() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('route_edit') || !$this->branchService->canMerge()) {
            throw new \Exception('Access denied.', 403);
        }
        $ref = $this->runData['route']['pathparts'][3] ?? '';
        if ($ref === '') {
            throw new \Exception('Route identifier missing.', 404);
        }
        $routeRows = $this->locateRouteRecord($ref);
        if (count($routeRows) !== 1) {
            throw new \Exception('Route not found.', 404);
        }
        $route = $routeRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $route['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Microservicelet not found.', 404);
        }
        $msName = $msRows[0]['s_name'];
        $routeKey = $this->getRouteFileKey($route, $msRows[0]['s_type'] ?? 'STA');
        $result = $this->branchService->mergeRouteBeta($msName, (int)$route['id'], $routeKey);
        $this->runData['request']->setAlert($result['message'], $result['status'] ? 'success' : 'danger');
        $redirect = $this->getRouteWorkspaceRedirect((string)($route['uid'] ?? ''), (string)($msRows[0]['uid'] ?? ''), 'live');
        header("Location: {$redirect}");
        exit;
    }

    public function branchdiscard() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('route_edit') || !$this->branchService->canUseBeta()) {
            throw new \Exception('Access denied.', 403);
        }
        $ref = $this->runData['route']['pathparts'][3] ?? '';
        if ($ref === '') {
            throw new \Exception('Route identifier missing.', 404);
        }
        $routeRows = $this->locateRouteRecord($ref);
        if (count($routeRows) !== 1) {
            throw new \Exception('Route not found.', 404);
        }
        $route = $routeRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $route['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Microservicelet not found.', 404);
        }
        $msName = $msRows[0]['s_name'];
        $routeKey = $this->getRouteFileKey($route, $msRows[0]['s_type'] ?? 'STA');
        $result = $this->branchService->discardRouteBeta($msName, (int)$route['id'], $routeKey);
        $this->runData['request']->setAlert($result['message'], $result['status'] ? 'success' : 'danger');
        $redirect = $this->getRouteWorkspaceRedirect((string)($route['uid'] ?? ''), (string)($msRows[0]['uid'] ?? ''), 'live');
        header("Location: {$redirect}");
        exit;
    }

    public function previewstart() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('route_edit') || !$this->branchService->canUsePreview()) {
            throw new \Exception('Access denied.', 403);
        }
        $ref = $this->runData['route']['pathparts'][3] ?? '';
        if ($ref === '') {
            throw new \Exception('Route identifier missing.', 404);
        }
        $routeRows = $this->locateRouteRecord($ref);
        if (count($routeRows) !== 1) {
            throw new \Exception('Route not found.', 404);
        }
        $route = $routeRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $route['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Microservicelet not found.', 404);
        }
        $ms = $msRows[0];
        $routeKey = $this->getRouteFileKey($route, $ms['s_type'] ?? 'STA');
        if (!$this->branchService->hasRouteBetaFiles($ms['s_name'], $routeKey)) {
            $this->runData['request']->setAlert('Create a beta branch before starting preview.', 'warning');
        } else {
            $this->branchService->activatePreviewSession([
                'object_type' => 'route',
                'ms_name' => (string)$ms['s_name'],
                'route_key' => (string)$routeKey,
                'route_uid' => (string)($route['uid'] ?? ''),
            ]);
            $this->runData['request']->setAlert('Beta preview started for this route.', 'success');
        }
        $redirect = $this->getRouteWorkspaceRedirect((string)($route['uid'] ?? ''), (string)($ms['uid'] ?? ''), 'beta');
        header("Location: {$redirect}");
        exit;
    }

    public function previewstop() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('route_edit') || !$this->branchService->canUsePreview()) {
            throw new \Exception('Access denied.', 403);
        }
        $ref = $this->runData['route']['pathparts'][3] ?? '';
        if ($ref === '') {
            throw new \Exception('Route identifier missing.', 404);
        }
        $routeRows = $this->locateRouteRecord($ref);
        if (count($routeRows) !== 1) {
            throw new \Exception('Route not found.', 404);
        }
        $route = $routeRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $route['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Microservicelet not found.', 404);
        }
        $ms = $msRows[0];
        $this->branchService->clearPreviewSession();
        $this->runData['request']->setAlert('Beta preview stopped.', 'success');
        $redirect = $this->getRouteWorkspaceRedirect((string)($route['uid'] ?? ''), (string)($ms['uid'] ?? ''), 'beta');
        header("Location: {$redirect}");
        exit;
    }

    public function help() {
        $context = $this->buildHelpContext();
        $branch = $context['branch'];
        $branchQuery = '?branch=' . ($branch === 'beta' ? 'beta' : 'live');

        $this->runData['data'] = array_merge($this->runData['data'] ?? [], $context);
        $this->runData['route']['h1'] = 'Help: ' . ($context['route']['s_name'] ?? '');
        $this->runData['route']['meta_title'] = 'Help: ' . ($context['route']['s_name'] ?? '');
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/route/detail/' . ($context['route']['uid'] ?? '');
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            $context['ms']['s_name'] => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . ($context['ms']['uid'] ?? ''),
            'Routes' => $this->runData['route']['rad_admin_url'] . '/route/view/' . ($context['ms']['uid'] ?? ''),
            $context['route']['s_name'] => $this->runData['route']['rad_admin_url'] . '/route/detail/' . ($context['route']['uid'] ?? ''),
            'Help' => '',
        ];
        $this->runData['data']['help_branch_query'] = $branchQuery;
        $this->runData['data']['help_rendered_html'] = $this->renderMarkdownToHtml((string)($context['help_content'] ?? ''));
        return $this->runData;
    }

    public function helpedit() {
        $context = $this->buildHelpContext();
        $branch = $context['branch'];
        $branchQuery = '?branch=' . ($branch === 'beta' ? 'beta' : 'live');

        $itemId = $this->getRouteHelpVersionItemId((string)$context['ms']['s_name'], (string)$context['route']['s_name'], $branch);
        $context['help_versions'] = $this->versionService->listVersions('route', $itemId);
        $context['help_branch_query'] = $branchQuery;
        $context['help_rendered_html'] = $this->renderMarkdownToHtml((string)($context['help_content'] ?? ''));
        $context['help_save_url'] = $this->runData['route']['rad_admin_url'] . '/route/helpsave/' . rawurlencode((string)$context['ms']['s_name']) . '/' . (int)$context['route']['id'] . $branchQuery;
        $context['help_preview_url'] = $this->runData['route']['rad_admin_url'] . '/route/helppreview/' . rawurlencode((string)($context['route']['uid'] ?? '')) . $branchQuery;
        $context['help_generate_url'] = $this->runData['route']['rad_admin_url'] . '/route/helpgenerate/' . rawurlencode((string)($context['route']['uid'] ?? '')) . $branchQuery;

        $this->runData['data'] = array_merge($this->runData['data'] ?? [], $context);
        $this->runData['route']['h1'] = 'Edit Help: ' . ($context['route']['s_name'] ?? '');
        $this->runData['route']['meta_title'] = 'Edit Help: ' . ($context['route']['s_name'] ?? '');
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/route/help/' . ($context['route']['uid'] ?? '') . $branchQuery;
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            $context['ms']['s_name'] => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . ($context['ms']['uid'] ?? ''),
            'Routes' => $this->runData['route']['rad_admin_url'] . '/route/view/' . ($context['ms']['uid'] ?? ''),
            $context['route']['s_name'] => $this->runData['route']['rad_admin_url'] . '/route/detail/' . ($context['route']['uid'] ?? ''),
            'Help' => $this->runData['route']['rad_admin_url'] . '/route/help/' . ($context['route']['uid'] ?? '') . $branchQuery,
            'Edit' => '',
        ];
        return $this->runData;
    }

    public function helpsave() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || !isset($data['content'])) {
            echo json_encode(['message' => 'Invalid data provided']);
            exit;
        }

        $msName = (string)($this->runData['route']['pathparts'][3] ?? '');
        $routeId = (int)($this->runData['route']['pathparts'][4] ?? 0);
        if ($msName === '' || $routeId <= 0) {
            echo json_encode(['message' => 'Invalid route reference']);
            exit;
        }

        $routeRows = $this->runData['db']->select('s_msroute', ['id' => $routeId], true);
        if (count($routeRows) !== 1) {
            echo json_encode(['message' => 'Route not found']);
            exit;
        }
        $route = $routeRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $route['s_ms_id']], true);
        if (count($msRows) !== 1 || (string)($msRows[0]['s_name'] ?? '') !== $msName) {
            echo json_encode(['message' => 'Microservicelet mismatch']);
            exit;
        }

        $branch = $this->branchService->resolveEditorBranch();
        $path = $this->branchService->getRouteHelpFilePath($msName, (string)$route['s_name'], $branch, false);
        if ($branch === 'beta' && !$this->branchService->hasRouteBetaFiles($msName, $this->getRouteFileKey($route, (string)($msRows[0]['s_type'] ?? 'STA'))) && !$this->branchService->hasRouteHelpBetaFile($msName, (string)$route['s_name'])) {
            echo json_encode(['message' => 'Create a beta branch before saving beta help.']);
            exit;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $content = (string)$data['content'];
        if (file_put_contents($path, $content) === false) {
            echo json_encode(['message' => 'Failed to save help content']);
            exit;
        }

        $this->runData['data']['route'] = [
            'id' => (int)$route['id'],
            's_name' => (string)$route['s_name'],
            'ms_name' => $msName,
        ];
        $this->snapshotRouteHelpContent((string)$route['s_name'], $msName, $content, $branch, !empty($data['create_version']));
        echo json_encode(['message' => '']);
        exit;
    }

    public function helppreview() {
        header('Content-Type: application/json');
        $ref = (string)($this->runData['route']['pathparts'][3] ?? '');
        $routeRows = $this->locateRouteRecord($ref);
        if (count($routeRows) !== 1) {
            echo json_encode(['message' => 'Route not found', 'html' => '']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $content = is_array($data) ? (string)($data['content'] ?? '') : '';
        echo json_encode([
            'message' => '',
            'html' => $this->renderMarkdownToHtml($content),
        ]);
        exit;
    }

    public function helpgenerate() {
        header('Content-Type: application/json');
        $ref = (string)($this->runData['route']['pathparts'][3] ?? '');
        $routeRows = $this->locateRouteRecord($ref);
        if (count($routeRows) !== 1) {
            echo json_encode(['error' => 'Route not found.']);
            exit;
        }
        $route = $routeRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $route['s_ms_id']], true);
        if (count($msRows) !== 1) {
            echo json_encode(['error' => 'Microservicelet not found.']);
            exit;
        }
        $ms = $msRows[0];
        $branch = $this->branchService->resolveEditorBranch();
        $routeKey = $this->getRouteFileKey($route, (string)($ms['s_type'] ?? 'STA'));

        $snippets = [];
        foreach (['load', 'prepart', 'pagepart', 'postpart'] as $part) {
            $content = $this->readFileSafe(
                $this->branchService->getRouteFilePath((string)$ms['s_name'], $routeKey, $part, $branch, true)
            );
            if (trim($content) !== '') {
                $snippets[$part] = $this->limitAiContext($content, 1800);
            }
        }

        $roleHints = $this->getRouteHelpRoleHints($route, $ms);
        $prompt = $this->buildRouteHelpGenerationPrompt($ms, $route, $branch, $snippets, $roleHints);
        try {
            $client = $this->getAiAssistClient(1800, 45, 'coding', 'full');
            $response = $client->getSuggestion($prompt, 'chat');
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->logError('Route help generation error: ' . $e->getMessage());
            }
            echo json_encode(['error' => 'AI service is currently unavailable.']);
            exit;
        }

        $markdown = $this->cleanupGeneratedHelpMarkdown((string)$response);
        if ($markdown === '') {
            echo json_encode(['error' => 'AI returned an empty response.']);
            exit;
        }

        echo json_encode([
            'suggestion' => $markdown,
            'html' => $this->renderMarkdownToHtml($markdown),
        ]);
        exit;
    }

    public function helpdownloadversion() {
        $branch = $this->branchService->resolveEditorBranch();
        $ms = (string)($this->runData['route']['pathparts'][3] ?? '');
        $routeName = (string)($this->runData['route']['pathparts'][4] ?? '');
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][5] ?? '');
        if ($ms === '' || $routeName === '' || $versionId === '') {
            throw new \Exception('Invalid help version request', 404);
        }
        $itemId = $this->getRouteHelpVersionItemId($ms, $routeName, $branch);
        $version = $this->versionService->fetchVersion('route', $itemId, $versionId);
        if (!$version) {
            throw new \Exception('Version not found', 404);
        }
        header('Content-Type: text/markdown; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $ms . '-' . $routeName . '-help-' . $versionId . '.md"');
        echo $version['content'] ?? '';
        exit;
    }

    public function helpdiffversion() {
        $branch = $this->branchService->resolveEditorBranch();
        $ms = (string)($this->runData['route']['pathparts'][3] ?? '');
        $routeName = (string)($this->runData['route']['pathparts'][4] ?? '');
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][5] ?? '');
        if ($ms === '' || $routeName === '' || $versionId === '') {
            throw new \Exception('Invalid help version request', 404);
        }
        $itemId = $this->getRouteHelpVersionItemId($ms, $routeName, $branch);
        $version = $this->versionService->fetchVersion('route', $itemId, $versionId);
        $filePath = $this->branchService->getRouteHelpFilePath($ms, $routeName, $branch, false);
        if (!$version || !is_file($filePath)) {
            throw new \Exception('Version not found', 404);
        }
        $currentContent = file_get_contents($filePath) ?: '';
        $diff = $this->versionService->diff('route', $itemId, $versionId, $currentContent);
        $this->runData['route']['h1'] = 'Route Help Diff';
        $this->runData['route']['meta_title'] = 'Route Help Diff';
        $branchQuery = '?branch=' . ($branch === 'beta' ? 'beta' : 'live');
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/route/helpedit/' . $this->resolveRouteUidByMsAndName($ms, $routeName) . $branchQuery;
        $this->runData['data']['diff'] = [
            'template' => $ms . '/' . $routeName . ' help',
            'version' => $version,
            'part' => 'help',
            'diff' => $diff,
        ];
        return $this->runData;
    }

    public function helprestoreversion() {
        $branch = $this->branchService->resolveEditorBranch();
        $ms = (string)($this->runData['route']['pathparts'][3] ?? '');
        $routeName = (string)($this->runData['route']['pathparts'][4] ?? '');
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][5] ?? '');
        $routeUid = $this->resolveRouteUidByMsAndName($ms, $routeName);
        $branchQuery = '?branch=' . ($branch === 'beta' ? 'beta' : 'live');
        $redirect = $this->runData['route']['rad_admin_url'] . '/route/helpedit/' . $routeUid . $branchQuery;
        if ($ms === '' || $routeName === '' || $versionId === '') {
            header("Location: {$redirect}");
            exit;
        }
        if (strtoupper($this->runData['request']->method) !== 'POST') {
            header("Location: {$redirect}");
            exit;
        }

        $itemId = $this->getRouteHelpVersionItemId($ms, $routeName, $branch);
        $version = $this->versionService->fetchVersion('route', $itemId, $versionId);
        $filePath = $this->branchService->getRouteHelpFilePath($ms, $routeName, $branch, false);
        if (!$version) {
            $this->runData['request']->setAlert('Version not found.', 'danger');
            header("Location: {$redirect}");
            exit;
        }
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (file_put_contents($filePath, $version['content'] ?? '') === false) {
            $this->runData['request']->setAlert('Failed to restore version.', 'danger');
            header("Location: {$redirect}");
            exit;
        }
        $this->snapshotRouteHelpContent($routeName, $ms, (string)($version['content'] ?? ''), $branch, true);
        $this->runData['request']->setAlert('Help version restored successfully.', 'success');
        header("Location: {$redirect}");
        exit;
    }

    public function downloadversion() {
        $branch = $this->branchService->resolveEditorBranch();
        $ms = $this->runData['route']['pathparts'][3] ?? '';
        $route = $this->runData['route']['pathparts'][4] ?? '';
        $part = $this->normalizeRoutePart($this->runData['route']['pathparts'][5] ?? '');
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][6] ?? '');
        if ($ms === '' || $route === '' || !$part || $versionId === '') {
            throw new \Exception('Invalid version request', 404);
        }
        $routeKey = $this->resolveRouteFileKey($route);
        $itemId = $this->getRouteVersionItemId($ms, $routeKey, $part, $branch);
        $version = $this->versionService->fetchVersion('route', $itemId, $versionId);
        if (!$version) {
            throw new \Exception('Version not found', 404);
        }
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $ms . '-' . $route . '-' . $part . '-' . $versionId . '.txt"');
        echo $version['content'] ?? '';
        exit;
    }

    public function diffversion() {
        $branch = $this->branchService->resolveEditorBranch();
        $ms = $this->runData['route']['pathparts'][3] ?? '';
        $route = $this->runData['route']['pathparts'][4] ?? '';
        $part = $this->normalizeRoutePart($this->runData['route']['pathparts'][5] ?? '');
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][6] ?? '');
        if ($ms === '' || $route === '' || !$part || $versionId === '') {
            throw new \Exception('Invalid version request', 404);
        }
        $routeKey = $this->resolveRouteFileKey($route);
        $itemId = $this->getRouteVersionItemId($ms, $routeKey, $part, $branch);
        $version = $this->versionService->fetchVersion('route', $itemId, $versionId);
        $filePath = $this->branchService->getRouteFilePath($ms, $routeKey, $part, $branch, false);
        if (!$version || !is_file($filePath)) {
            throw new \Exception('Version not found', 404);
        }
        $currentContent = file_get_contents($filePath) ?: '';
        $diff = $this->versionService->diff('route', $itemId, $versionId, $currentContent);

        $this->runData['route']['h1'] = 'Route Diff';
        $this->runData['route']['meta_title'] = 'Route Diff';
        $branchQuery = '?branch=' . ($branch === 'beta' ? 'beta' : 'live');
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/route/code/' . $ms . '/' . $route . $branchQuery;
        $this->runData['data']['diff'] = [
            'template' => $ms . '/' . $route,
            'version' => $version,
            'part' => $part,
            'diff' => $diff,
        ];
        return $this->runData;
    }

    public function restoreversion() {
        $branch = $this->branchService->resolveEditorBranch();
        $ms = $this->runData['route']['pathparts'][3] ?? '';
        $route = $this->runData['route']['pathparts'][4] ?? '';
        $part = $this->normalizeRoutePart($this->runData['route']['pathparts'][5] ?? '');
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][6] ?? '');
        $branchQuery = '?branch=' . ($branch === 'beta' ? 'beta' : 'live');
        $redirect = $this->runData['route']['rad_admin_url'] . '/route/code/' . $ms . '/' . $route . $branchQuery;

        if ($ms === '' || $route === '' || !$part || $versionId === '') {
            header("Location: {$redirect}");
            exit;
        }

        if (strtoupper($this->runData['request']->method) !== 'POST') {
            header("Location: {$redirect}");
            exit;
        }

        $routeKey = $this->resolveRouteFileKey($route);
        $itemId = $this->getRouteVersionItemId($ms, $routeKey, $part, $branch);
        $version = $this->versionService->fetchVersion('route', $itemId, $versionId);
        $filePath = $this->branchService->getRouteFilePath($ms, $routeKey, $part, $branch, false);
        if (!$version || !is_file($filePath)) {
            $this->runData['request']->setAlert('Version not found.', 'danger');
            header("Location: {$redirect}");
            exit;
        }

        if (file_put_contents($filePath, $version['content'] ?? '') === false) {
            $this->runData['request']->setAlert('Failed to restore version.', 'danger');
            header("Location: {$redirect}");
            exit;
        }

        $this->snapshotRouteCode($part, $version['content'] ?? '', $branch);
        $this->runData['request']->setAlert('Version restored successfully.', 'success');
        header("Location: {$redirect}");
        exit;
    }
    
    /**
     * AI Assist code for a Route
     */
    public function aiassist() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data || !isset($data['content'])) {
            echo json_encode(['error' => 'Invalid data provided']);
            return;
        }

        $service = $this->getAiAssistService('coding', 'full');
        $result = $service->suggest($data['content'], 'route', [
            'microservice' => $this->runData['route']['pathparts'][3] ?? '',
            'route' => $this->runData['route']['pathparts'][4] ?? '',
        ]);

        echo json_encode($result);
    }

    private function locateRouteRecord(string $ref): array {
        if (ctype_digit($ref)) {
            return $this->runData['db']->select('s_msroute', ['id' => $ref], true);
        }
        return $this->runData['db']->select('s_msroute', ['uid' => $ref], true);
    }

    private function resolveUserName($userId): string {
        $userId = (int)$userId;
        if ($userId === 0) {
            return 'System';
        }
        try {
            $rows = $this->runData['db']->select('s_entity', ['id' => $userId, 's_type' => 'U'], true);
        } catch (\Throwable $e) {
            $rows = [];
        }
        if (!empty($rows)) {
            $name = trim((string)($rows[0]['s_name'] ?? ''));
            if ($name !== '') {
                return $name . ' (#' . $userId . ')';
            }
            if (!empty($rows[0]['s_identity'])) {
                return trim((string)$rows[0]['s_identity']) . ' (#' . $userId . ')';
            }
        }
        try {
            $rows = $this->runData['db']->select('s_user', ['id' => $userId], true);
        } catch (\Throwable $e) {
            return 'User #' . $userId;
        }
        if (!empty($rows)) {
            $name = trim(($rows[0]['s_first_name'] ?? '') . ' ' . ($rows[0]['s_last_name'] ?? ''));
            if ($name !== '') {
                return $name . ' (#' . $userId . ')';
            }
            if (!empty($rows[0]['s_display_name'])) {
                return trim((string)$rows[0]['s_display_name']) . ' (#' . $userId . ')';
            }
            if (!empty($rows[0]['s_username'])) {
                return trim((string)$rows[0]['s_username']) . ' (#' . $userId . ')';
            }
        }
        return 'User #' . $userId;
    }

    private function fetchHistoryEntries(string $table, int $recordId): array {
        try {
            $entries = $this->runData['db']->select(
                's_version_history',
                ['s_db_table' => $table, 's_data_record_id' => $recordId],
                true,
                ['id' => 'DESC'],
                8
            );
        } catch (\Throwable $e) {
            return [];
        }

        foreach ($entries as &$entry) {
            $entry['modifier_label'] = $this->resolveUserName($entry['s_modified_by'] ?? 0);
            $entry['snapshot'] = $this->decodeSnapshot($entry['s_data_record_dump'] ?? null);
        }
        return $entries;
    }

    private function decodeSnapshot(?string $payload): string {
        if (!$payload) {
            return '';
        }
        $decoded = @base64_decode($payload);
        if ($decoded === false) {
            return '';
        }
        $inflated = @gzuncompress($decoded);
        if ($inflated === false) {
            return '';
        }
        $data = @unserialize($inflated);
        if (is_array($data) && isset($data[0])) {
            $data = $data[0];
        }
        if (!is_array($data)) {
            return '';
        }
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function snapshotRouteCode(string $type, string $content, string $branch = 'live'): void {
        if (!$this->versionService) {
            return;
        }
        $ms = $this->runData['data']['route']['ms_name'] ?? ($this->runData['route']['pathparts'][3] ?? '');
        $route = $this->runData['data']['route']['id'] ?? ($this->runData['route']['pathparts'][4] ?? '');
        if ($ms === '' || $route === '') {
            return;
        }
        $routeKey = $this->runData['data']['route']['file_key']
            ?? $this->resolveRouteFileKey((string)$route);
        $itemId = $this->getRouteVersionItemId($ms, $routeKey, $type, $branch);
        $this->versionService->snapshot('route', $itemId, $content, [
            'note' => strtoupper($type) . ' updated',
        ]);
    }

    private function getRouteVersionItemId(string $ms, string $route, string $part, string $branch = 'live'): string {
        $suffix = $branch === 'beta' ? '@beta' : '';
        return $ms . '/route-' . $route . '/' . $part . $suffix;
    }

    private function getRouteFileKey(array $routeRow, string $msType): string {
        if (strtoupper($msType) === 'DYN') {
            return (string)($routeRow['s_name'] ?? $routeRow['id'] ?? '');
        }
        return (string)($routeRow['id'] ?? '');
    }

    private function resolveRouteFileKey(string $routeRef): string {
        $routeRows = $this->locateRouteRecord($routeRef);
        if (count($routeRows) !== 1) {
            return $routeRef;
        }
        $route = $routeRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $route['s_ms_id']], true);
        if (count($msRows) !== 1) {
            return $routeRef;
        }
        return $this->getRouteFileKey($route, $msRows[0]['s_type'] ?? 'STA');
    }

    private function ensureRouteFiles(string $msName, string $routeKey): void {
        if ($msName === '' || $routeKey === '') {
            return;
        }
        $msDir = $this->runData['config']['dir']['ms'] . '/' . $msName;
        if (!is_dir($msDir)) {
            mkdir($msDir, 0777, true);
        }
        foreach (['php', 'pagepart.php', 'prepart.php', 'postpart.php'] as $suffix) {
            $path = $msDir . '/route.' . $routeKey . '.' . $suffix;
            if (!file_exists($path)) {
                file_put_contents($path, '');
            }
        }
    }

    private function renameRouteFiles(string $msName, string $oldKey, string $newKey): void {
        if ($msName === '' || $oldKey === '' || $newKey === '' || $oldKey === $newKey) {
            return;
        }
        $msDir = $this->runData['config']['dir']['ms'] . '/' . $msName;
        foreach (['php', 'pagepart.php', 'prepart.php', 'postpart.php'] as $suffix) {
            $oldPath = $msDir . '/route.' . $oldKey . '.' . $suffix;
            $newPath = $msDir . '/route.' . $newKey . '.' . $suffix;
            if (file_exists($oldPath) && !file_exists($newPath)) {
                rename($oldPath, $newPath);
            }
        }
    }

    private function deleteRouteFilesForRow(string $msName, array $routeRow, string $msType): int {
        if ($msName === '') {
            return 0;
        }
        $keys = [];
        $keys[] = $this->getRouteFileKey($routeRow, $msType);
        $keys[] = (string)($routeRow['s_name'] ?? '');
        $keys[] = (string)($routeRow['id'] ?? '');
        $keys = array_values(array_unique(array_filter(array_map('strval', $keys), function ($v) {
            return trim($v) !== '';
        })));

        $deleted = 0;
        foreach ($keys as $key) {
            foreach (['live', 'beta'] as $branch) {
                $paths = $this->branchService->getRouteFiles($msName, $key, $branch);
                foreach ($paths as $path) {
                    if (is_file($path) && @unlink($path)) {
                        $deleted++;
                    }
                }
            }
        }
        return $deleted;
    }

    private function buildHelpContext(): array {
        $ref = (string)($this->runData['route']['pathparts'][3] ?? '');
        $routeRows = $this->locateRouteRecord($ref);
        if (count($routeRows) !== 1) {
            throw new \Exception('Route not found.', 404);
        }
        $route = $routeRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $route['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Microservicelet not found.', 404);
        }
        $ms = $msRows[0];
        $branch = $this->branchService->resolveEditorBranch();
        $routeKey = $this->getRouteFileKey($route, (string)($ms['s_type'] ?? 'STA'));
        $helpPath = $this->branchService->getRouteHelpFilePath((string)$ms['s_name'], (string)$route['s_name'], $branch, false);
        $helpReadPath = $this->branchService->getRouteHelpFilePath((string)$ms['s_name'], (string)$route['s_name'], $branch, true);
        $helpExists = is_file($helpPath);
        $helpContent = is_file($helpReadPath)
            ? $this->readFileSafe($helpReadPath)
            : $this->buildRouteHelpStub((string)$ms['s_name'], (string)$route['s_name'], $routeKey);
        $branchStatus = $this->branchService->getRouteBranchStatus((int)$route['id']);
        $branchHistory = [];
        try {
            $branchHistory = $this->runData['db']->query(
                "SELECT * FROM s_branch
                 WHERE s_object_type = 'route' AND s_object_id = :rid
                 ORDER BY id DESC
                 LIMIT 10",
                [':rid' => (int)$route['id']]
            );
        } catch (\Throwable $e) {
            $branchHistory = [];
        }

        return [
            'route' => $route,
            'ms' => $ms,
            'branch' => $branch,
            'branch_status' => $branchStatus,
            'branch_history' => $branchHistory,
            'branch_has_beta' => $this->branchService->hasRouteBetaFiles((string)$ms['s_name'], $routeKey) || $this->branchService->hasRouteHelpBetaFile((string)$ms['s_name'], (string)$route['s_name']),
            'branch_missing' => $branch === 'beta' && !$this->branchService->hasRouteBetaFiles((string)$ms['s_name'], $routeKey) && !$this->branchService->hasRouteHelpBetaFile((string)$ms['s_name'], (string)$route['s_name']),
            'branch_can_manage' => $this->branchService->canUseBeta(),
            'branch_can_merge' => $this->branchService->canMerge(),
            'help_path' => $helpPath,
            'help_exists' => $helpExists,
            'help_content' => $helpContent,
            'help_excerpt' => $this->buildHelpExcerpt($helpContent),
            'help_view_url' => $this->runData['route']['rad_admin_url'] . '/route/help/' . ($route['uid'] ?? ''),
            'help_edit_url' => $this->runData['route']['rad_admin_url'] . '/route/helpedit/' . ($route['uid'] ?? ''),
            'route_key' => $routeKey,
        ];
    }

    private function getRouteWorkspaceRedirect(string $routeUid, string $msUid, string $branch = 'live'): string {
        $branchQuery = '?branch=' . ($branch === 'beta' ? 'beta' : 'live');
        $return = strtolower(trim((string)($this->runData['request']->get['return'] ?? '')));
        if ($return === 'help') {
            return $this->runData['route']['rad_admin_url'] . '/route/help/' . $routeUid . $branchQuery;
        }
        if ($return === 'helpedit') {
            return $this->runData['route']['rad_admin_url'] . '/route/helpedit/' . $routeUid . $branchQuery;
        }
        return $this->runData['route']['rad_admin_url'] . '/route/code/' . $routeUid . '/' . $msUid . $branchQuery;
    }

    private function getRouteHelpVersionItemId(string $msName, string $routeName, string $branch = 'live'): string {
        $suffix = $branch === 'beta' ? '@beta' : '';
        return $msName . '/route-help-' . $routeName . '/help' . $suffix;
    }

    private function getRouteHelpFileName(string $routeName): string {
        return 'route.' . $routeName . '.help.md';
    }

    private function getRouteHelpFilePath(string $msName, string $routeName, string $branch = 'live'): string {
        return $this->branchService->getRouteHelpFilePath($msName, $routeName, $branch, false);
    }

    private function ensureRouteHelpFile(string $msName, string $routeName, string $routeKey, string $msType): void {
        if ($msName === '' || $routeName === '') {
            return;
        }
        $path = $this->getRouteHelpFilePath($msName, $routeName, 'live');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_file($path)) {
            file_put_contents($path, $this->buildRouteHelpStub($msName, $routeName, $routeKey, $msType));
        }
    }

    private function renameRouteHelpFile(string $msName, string $oldRouteName, string $newRouteName): void {
        if ($msName === '' || $oldRouteName === '' || $newRouteName === '' || $oldRouteName === $newRouteName) {
            return;
        }
        foreach (['live', 'beta'] as $branch) {
            $oldPath = $this->getRouteHelpFilePath($msName, $oldRouteName, $branch);
            $newPath = $this->getRouteHelpFilePath($msName, $newRouteName, $branch);
            if (is_file($oldPath) && !is_file($newPath)) {
                @rename($oldPath, $newPath);
            }
        }
    }

    private function deleteRouteHelpFiles(string $msName, string $routeName): int {
        if ($msName === '' || $routeName === '') {
            return 0;
        }
        $deleted = 0;
        foreach (['live', 'beta'] as $branch) {
            $path = $this->getRouteHelpFilePath($msName, $routeName, $branch);
            if (is_file($path) && @unlink($path)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    private function buildRouteHelpStub(string $msName, string $routeName, string $routeKey, string $msType = 'STA'): string {
        $routeKey = trim($routeKey) !== '' ? $routeKey : $routeName;
        $scopeLine = '/' . $msName . '/' . $routeName . '/...';
        $lines = [
            '# ' . $routeName,
            '',
            '## Overview',
            'Explain in simple terms what this page or feature helps the user do.',
            '',
            '## When to Use This Page',
            'Describe when a user should visit this page and what tasks it supports.',
            '',
            '## Before You Start',
            'Mention anything the user should have ready before using this page.',
            '',
            '## Steps',
            '1. Open the page.',
            '2. Complete the required fields or actions.',
            '3. Save, submit, or continue as needed.',
            '',
            '## What You Will See',
            'Describe the important sections, fields, buttons, or results shown on the page.',
            '',
            '## Tips',
            'Share any helpful usage tips, shortcuts, or best practices.',
            '',
            '## Troubleshooting',
            'Describe common issues the user might face and what to check first.',
            '',
            '## Page Address',
            $scopeLine,
        ];
        return implode("\n", $lines) . "\n";
    }

    private function buildRouteHelpGenerationPrompt(array $ms, array $route, string $branch, array $snippets, array $roleHints = []): string {
        $parts = [];
        foreach (['load', 'prepart', 'pagepart', 'postpart'] as $part) {
            if (empty($snippets[$part])) {
                continue;
            }
            $parts[] = strtoupper($part) . ":\n" . $snippets[$part];
        }

        $serviceDefinition = trim((string)($route['s_service_definition'] ?? ''));
        if ($serviceDefinition === '') {
            $serviceDefinition = '{}';
        }

        $prompt = [];
        $prompt[] = 'You are writing a user guide for a RAD Framework route.';
        $prompt[] = 'Write concise, user-facing markdown help content for end users, not developers.';
        $prompt[] = 'Return markdown only. Do not wrap the answer in code fences. Do not add commentary before or after the markdown.';
        $prompt[] = 'Do not mention PHP files, route internals, prepart/pagepart/postpart, source code structure, or implementation details.';
        $prompt[] = 'Infer what the user sees and does from the metadata and code context, but describe it in plain business/user language.';
        if (!empty($roleHints)) {
            $prompt[] = 'If the route experience differs by role, write the shared guidance first and then add only the role-specific differences using markers like:';
            $prompt[] = '<!-- role:admin -->';
            $prompt[] = '## Admin Only';
            $prompt[] = '...';
            $prompt[] = '<!-- /role:admin -->';
            $prompt[] = 'Use only the role slugs listed below. Do not invent extra roles.';
        } else {
            $prompt[] = 'If the route experience does not clearly differ by role, do not add any role markers.';
        }
        $prompt[] = 'Use these sections exactly when relevant:';
        $prompt[] = '# ' . (string)($route['s_name'] ?? '');
        $prompt[] = '## Overview';
        $prompt[] = '## When to Use This Page';
        $prompt[] = '## Before You Start';
        $prompt[] = '## Steps';
        $prompt[] = '## What You Will See';
        $prompt[] = '## Tips';
        $prompt[] = '## Troubleshooting';
        $prompt[] = '';
        $prompt[] = 'ROUTE METADATA';
        $prompt[] = 'Microservicelet: ' . (string)($ms['s_name'] ?? '');
        $prompt[] = 'Microservicelet type: ' . (string)($ms['s_type'] ?? 'STA');
        $prompt[] = 'Route name: ' . (string)($route['s_name'] ?? '');
        $prompt[] = 'Route description: ' . (string)($route['s_description'] ?? '');
        $prompt[] = 'Entity scope: ' . (string)($route['s_entity_scope'] ?? 'U');
        $prompt[] = 'Service definition: ' . $serviceDefinition;
        $prompt[] = 'Editing branch: ' . $branch;
        $prompt[] = 'URL pattern: /' . (string)($ms['s_name'] ?? '') . '/' . (string)($route['s_name'] ?? '') . '/...';
        if (!empty($roleHints)) {
            $prompt[] = '';
            $prompt[] = 'ROLE HINTS';
            foreach ($roleHints as $roleHint) {
                $prompt[] = '- slug: ' . $roleHint['slug'] . ' | name: ' . $roleHint['name'] . ' | source: ' . $roleHint['source'];
            }
        }
        $prompt[] = '';
        if (!empty($parts)) {
            $prompt[] = '';
            $prompt[] = 'ROUTE CODE CONTEXT';
            $prompt[] = implode("\n\n", $parts);
        }
        $prompt[] = '';
        $prompt[] = 'Important: if something is not evident, describe it conservatively rather than inventing details.';
        $prompt[] = 'Focus on actions, expectations, field meanings, and likely outcomes for a user.';

        return implode("\n", $prompt);
    }

    private function getRouteHelpRoleHints(array $route, array $ms): array {
        $roles = $this->fetchBindingRoles('route', (int)($route['id'] ?? 0));
        $source = 'route';
        if (empty($roles)) {
            $roles = $this->fetchBindingRoles('ms', (int)($ms['id'] ?? 0));
            $source = 'microservice';
        }
        $hints = [];
        foreach ($roles as $role) {
            $name = trim((string)($role['role_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $slug = $this->slugifyRoleMarker($name);
            if ($slug === '') {
                continue;
            }
            $hints[$slug] = [
                'slug' => $slug,
                'name' => $name,
                'source' => $source,
            ];
        }
        return array_values($hints);
    }

    private function slugifyRoleMarker(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function cleanupGeneratedHelpMarkdown(string $markdown): string {
        $markdown = trim($markdown);
        $markdown = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $markdown) ?? $markdown;
        $markdown = preg_replace('/```$/', '', $markdown) ?? $markdown;
        return trim($markdown);
    }

    private function limitAiContext(string $content, int $maxLength = 1800): string {
        $content = trim($content);
        if (strlen($content) <= $maxLength) {
            return $content;
        }
        return substr($content, 0, $maxLength) . "\n... [truncated]";
    }

    private function buildHelpExcerpt(string $markdown): string {
        $markdown = trim($markdown);
        if ($markdown === '') {
            return 'No help content created yet.';
        }
        $markdown = preg_replace('/`{3}.*?`{3}/s', '', $markdown) ?? $markdown;
        $markdown = preg_replace('/[#>*_\-\[\]\(\)`]/', ' ', $markdown) ?? $markdown;
        $markdown = preg_replace('/\s+/', ' ', $markdown) ?? $markdown;
        $markdown = trim($markdown);
        if ($markdown === '') {
            return 'Help content is available.';
        }
        if (strlen($markdown) > 180) {
            return substr($markdown, 0, 177) . '...';
        }
        return $markdown;
    }

    private function snapshotRouteHelpContent(string $routeName, string $msName, string $content, string $branch = 'live', bool $force = false): void {
        $itemId = $this->getRouteHelpVersionItemId($msName, $routeName, $branch);
        $this->versionService->snapshot('route', $itemId, $content, [
            'note' => 'HELP updated',
            'force' => $force,
        ]);
    }

    private function resolveRouteUidByMsAndName(string $msName, string $routeName): string {
        if ($msName === '' || $routeName === '') {
            return '';
        }
        $msRows = $this->runData['db']->select('s_ms', ['s_name' => $msName], true);
        if (count($msRows) !== 1) {
            return '';
        }
        $routeRows = $this->runData['db']->select('s_msroute', [
            's_ms_id' => $msRows[0]['id'],
            's_name' => $routeName,
        ], true);
        if (count($routeRows) !== 1) {
            return '';
        }
        return (string)($routeRows[0]['uid'] ?? '');
    }

    private function renderMarkdownToHtml(string $markdown): string {
        $markdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));
        if ($markdown === '') {
            return '<p class="text-muted mb-0">No help content available.</p>';
        }
        try {
            if (class_exists('\\League\\CommonMark\\GithubFlavoredMarkdownConverter')) {
                $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter([
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                    'max_nesting_level' => 20,
                ]);
                $rendered = $converter->convert($markdown);
                if (is_object($rendered) && method_exists($rendered, 'getContent')) {
                    return (string)$rendered->getContent();
                }
                return (string)$rendered;
            }
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->logError('Help markdown render error: ' . $e->getMessage());
            }
        }

        return '<pre class="bg-light border rounded p-3 mb-0">' . htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8') . '</pre>';
    }

    private function logRouteActivity(string $action, int $routeId, int $msId, string $name, string $description): void {
        $db = $this->runData['db'] ?? null;
        if (!$db) {
            return;
        }

        $routeRows = $db->select('s_msroute', ['id' => $routeId], true);
        if (empty($routeRows[0])) {
            return;
        }
        $routeRow = $routeRows[0];

        $msRows = $db->select('s_ms', ['id' => $msId], true);
        $msName = $msRows[0]['s_name'] ?? '';

        $actorId = (int)($this->runData['entity']['id'] ?? 0);
        $actorName = $this->runData['entity']['fullname']
            ?? $this->runData['entity']['username']
            ?? '';

        $context = [
            '{action}' => $action,
            '{route_id}' => (string)$routeId,
            '{route_uid}' => $routeRow['uid'] ?? '',
            '{route_name}' => $name,
            '{route_description}' => $description,
            '{ms_id}' => (string)$msId,
            '{ms_name}' => $msName,
            '{actor}' => $actorName,
            '{timestamp}' => date('Y-m-d H:i:s T'),
        ];

        $message = $this->renderRouteTemplate($routeRow['s_activity_template'] ?? '', $context);
        if ($message === '') {
            $message = sprintf('Route %s: %s', $action, $name);
        }

        try {
            $activitySvc = new \Core\Sys\ActivityService($db);
            $activitySvc->log([
                's_actor_id' => $actorId ?: null,
                's_object_type' => 'route',
                's_object_id' => $routeId,
                's_action' => $action,
                's_message' => $message,
                's_payload' => [
                    'ms_id' => $msId,
                    'ms_name' => $msName,
                    'route_id' => $routeId,
                    'route_name' => $name,
                    'actor' => $actorName,
                    'description' => $description,
                    'timestamp' => $context['{timestamp}'],
                ],
            ]);
        } catch (\Throwable $e) {
            // Swallow logging failures
        }

        $notifTemplate = $routeRow['s_notification_template'] ?? '';
        if ($notifTemplate !== '') {
            try {
                $notifSvc = $this->runData['notificationService'] ?? new \Core\Sys\NotificationService($db);
                $notifMessage = $this->renderRouteTemplate($notifTemplate, $context);
                if ($notifMessage !== '' && $notifSvc instanceof \Core\Sys\NotificationService) {
                    $notifSvc->logGlobalEvent($notifMessage, [
                        'event_type' => 'route_' . $action,
                        'link' => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . ($msRows[0]['uid'] ?? ''),
                        'created_by' => $actorId ?: null,
                        'metadata' => [
                            'route_id' => $routeId,
                            'route_name' => $name,
                            'ms_id' => $msId,
                            'ms_name' => $msName,
                            'actor' => $actorName,
                            'timestamp' => $context['{timestamp}'],
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
                // Ignore notification failures
            }
        }
    }

    private function renderRouteTemplate(string $template, array $context): string {
        $template = trim($template);
        if ($template === '') {
            return '';
        }
        return strtr($template, $context);
    }

    private function getRouteFilePath(string $ms, string $route, string $part): string {
        $base = rtrim($this->runData['config']['dir']['ms'], '/');
        $prefix = $base . '/' . $ms . '/route.' . $route;
        switch ($part) {
            case 'load':
                return $prefix . '.php';
            case 'pagepart':
                return $prefix . '.pagepart.php';
            case 'prepart':
                return $prefix . '.prepart.php';
            case 'postpart':
                return $prefix . '.postpart.php';
            default:
                return $prefix . '.php';
        }
    }

    private function normalizeRoutePart(?string $value): ?string {
        $value = strtolower(trim((string)$value));
        $allowed = ['load', 'pagepart', 'prepart', 'postpart'];
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function sanitizeVersionId(?string $value): string {
        $value = strtolower(trim((string)$value));
        return preg_replace('/[^a-z0-9_]+/', '', $value);
    }

    private function readFileSafe(string $path): string {
        if ($path === '' || !is_file($path)) {
            return '';
        }
        $content = file_get_contents($path);
        return $content === false ? '' : $content;
    }

    private function inheritMsBindingsToRoute(int $msId, int $routeId): int {
        if ($msId <= 0 || $routeId <= 0) {
            return 0;
        }
        $rows = $this->runData['db']->query(
            "SELECT s_role_id FROM s_permission_binding
             WHERE s_object_type = 'ms' AND s_object_id = :msid AND livestatus != '0'",
            [':msid' => $msId]
        );
        if (empty($rows)) {
            return 0;
        }
        $roleIds = [];
        foreach ($rows as $row) {
            $rid = (int)($row['s_role_id'] ?? 0);
            if ($rid > 0) {
                $roleIds[] = $rid;
            }
        }
        $roleIds = array_values(array_unique($roleIds));
        if (empty($roleIds)) {
            return 0;
        }
        $existing = $this->runData['db']->query(
            "SELECT s_role_id FROM s_permission_binding
             WHERE s_object_type = 'route' AND s_object_id = :rid AND livestatus != '0'",
            [':rid' => $routeId]
        );
        $existingMap = [];
        foreach ($existing as $row) {
            $existingMap[(int)($row['s_role_id'] ?? 0)] = true;
        }
        $added = 0;
        foreach ($roleIds as $roleId) {
            if (isset($existingMap[$roleId])) {
                continue;
            }
            $this->runData['db']->insert('s_permission_binding', [
                's_object_type' => 'route',
                's_object_id' => $routeId,
                's_role_id' => $roleId,
            ]);
            $added++;
        }
        return $added;
    }

    private function allowedBindingRoleScopesForMicroservice(array $ms): array {
        $scope = strtolower((string)($ms['s_scope'] ?? 'platform'));
        if ($scope === 'workspace') {
            return ['platform', 'workspace'];
        }
        if ($scope === 'global') {
            return [];
        }
        return ['platform'];
    }

    private function fetchBindingRoles(string $objectType, int $objectId): array {
        if ($objectId <= 0) {
            return [];
        }
        $rows = $this->runData['db']->query(
            "SELECT b.id AS binding_id, b.s_role_id, r.s_role_name, r.s_scope
             FROM s_permission_binding b
             LEFT JOIN s_role r ON r.id = b.s_role_id
             WHERE b.s_object_type = :otype
               AND b.s_object_id = :oid
               AND b.livestatus != '0'
             ORDER BY r.s_role_name ASC, b.s_role_id ASC",
            [
                ':otype' => $objectType,
                ':oid' => $objectId,
            ]
        );
        $roles = [];
        foreach ($rows as $row) {
            $roles[] = [
                'binding_id' => (int)($row['binding_id'] ?? 0),
                'role_id' => (int)($row['s_role_id'] ?? 0),
                'role_name' => (string)($row['s_role_name'] ?? ('Role #' . (int)($row['s_role_id'] ?? 0))),
                'role_scope' => strtolower((string)($row['s_scope'] ?? '')),
            ];
        }
        return $roles;
    }

    private function filterBindingRolesByAllowedScopes(array $roles, array $allowedScopes): array {
        if (empty($allowedScopes)) {
            return [];
        }
        $allowedMap = array_fill_keys($allowedScopes, true);
        return array_values(array_filter($roles, function ($role) use ($allowedMap) {
            $scope = strtolower((string)($role['role_scope'] ?? ''));
            return isset($allowedMap[$scope]);
        }));
    }

    private function groupBindingRolesByScope(array $roles): array {
        $groups = [
            'platform' => [],
            'workspace' => [],
        ];
        foreach ($roles as $role) {
            $scope = strtolower((string)($role['role_scope'] ?? ''));
            if (isset($groups[$scope])) {
                $groups[$scope][] = $role;
            }
        }
        return $groups;
    }
}

<?php
namespace RadAdmin;

class Apiendpoint {
    private $runData = [];
    private $errorHandler;
    private $db;
    private $roleLookup = null;
    private $endpointService;
    private $testHookHelper;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->errorHandler = $runData['errorHandler'];
        $this->db = $runData['db'];
        $this->endpointService = new \Core\Sys\ApiEndpointService(
            $this->db,
            $this->errorHandler,
            $runData['config']['sys']['api_gateway'] ?? [],
            $runData
        );
        $hookPath = $runData['config']['dir']['admin'].'/classes/Testhookhelper.cls.php';
        if (file_exists($hookPath) && !class_exists('\\RadAdmin\\Testhookhelper', false)) {
            require_once $hookPath;
        }
        if (class_exists('\\RadAdmin\\Testhookhelper', false)) {
            $this->testHookHelper = new \RadAdmin\Testhookhelper($this->db, $this->errorHandler);
        }
    }

    public function view() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('api_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'RAD exposes a single /api gateway. Use JSON payloads to route every request.';
        }

        $this->runData['route']['h1'] = 'API Gateway Endpoints';
        $this->runData['route']['meta_title'] = 'API Gateway Endpoints';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/home/view';
        $this->runData['route']['breadcrumb'] = [
            'API' => null,
        ];

        $this->runData['data']['gateway_url'] = rtrim($this->runData['config']['sys']['base_url'], '/') . '/api/';
        $eligibleData = $this->getApiEligibleRoutes();
        $this->runData['data']['ms'] = $eligibleData['microservices'];
        $this->runData['data']['routes'] = $eligibleData['routes'];
        $this->runData['data']['apis'] = $this->db->select('s_entity', ['s_type' => 'A', 'livestatus' => '1'], true);
        $gatewayConfig = $this->runData['config']['sys']['api_gateway'] ?? [];
        $this->runData['data']['api_gateway'] = $gatewayConfig;
        $this->runData['data']['sample_payloads'] = $this->buildSamplePayloads($eligibleData, $gatewayConfig);
        $this->runData['data']['system_catalog'] = $this->buildSystemCatalog($gatewayConfig);
        $filters = [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
            'status' => trim((string)($this->runData['request']->get['status'] ?? '')),
        ];
        $endpoints = $this->endpointService->listActive();
        if ($filters['status'] !== '') {
            $endpoints = array_values(array_filter($endpoints, function ($row) use ($filters) {
                return (string)($row['livestatus'] ?? '') === $filters['status'];
            }));
        }
        if ($filters['q'] !== '') {
            $needle = strtolower($filters['q']);
            $endpoints = array_values(array_filter($endpoints, function ($row) use ($needle) {
                $blob = strtolower(($row['s_slug'] ?? '') . ' ' . ($row['s_description'] ?? ''));
                return strpos($blob, $needle) !== false;
            }));
        }
        $this->runData['data']['named_endpoints'] = $endpoints;
        $this->runData['data']['filters'] = $filters;

        return $this->runData;
    }

    public function verify() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('api_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $this->runData['route']['h1'] = 'API Gateway Verifier';
        $this->runData['route']['meta_title'] = 'API Gateway Verifier';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/apiendpoint/view';
        $this->runData['route']['breadcrumb'] = [
            'API' => $this->runData['route']['rad_admin_url'] . '/apiendpoint/view',
        ];

        $gatewayUrl = rtrim($this->runData['config']['sys']['base_url'], '/') . '/api/';
        $eligibleData = $this->getApiEligibleRoutes();
        $msList = $eligibleData['microservices'];
        $routes = $eligibleData['routes'];
        $apis = $this->db->select('s_entity', ['s_type' => 'A', 'livestatus' => '1'], true);
        $bindings = $this->fetchPermissionBindings($msList, $routes);
        $bindings = $this->fetchPermissionBindings($msList, $routes);
        $namedEndpoints = $this->endpointService->listActive();
        $bindings = $this->fetchPermissionBindings($msList, $routes);
        $gatewayConfig = $this->runData['config']['sys']['api_gateway'] ?? [];

        $this->runData['data']['gateway_url'] = $gatewayUrl;
        $this->runData['data']['ms'] = $msList;
        $this->runData['data']['routes'] = $routes;
        $this->runData['data']['apis'] = $apis;
        $this->runData['data']['endpoints'] = $namedEndpoints;
        $this->runData['data']['api_gateway'] = $gatewayConfig;
        $this->runData['data']['target_lists'] = $this->buildEndpointTargetLists($gatewayConfig);
        $this->runData['data']['system_catalog'] = $this->buildSystemCatalog($gatewayConfig);

        $sessionKey = 'api_verify_state';
        $formDefaults = [
            'ms_id' => $this->runData['request']->post['ms_id'] ?? '',
            'route_id' => $this->runData['request']->post['route_id'] ?? '',
            'api_id' => $this->runData['request']->post['api_id'] ?? '',
            'security_key' => $this->runData['request']->post['security_key'] ?? '',
            'api_type' => $this->runData['request']->post['api_type'] ?? 'application',
            'params_json' => $this->runData['request']->post['params_json'] ?? '',
            'system_target_type' => $this->runData['request']->post['system_target_type'] ?? 'table',
            'system_target' => $this->runData['request']->post['system_target'] ?? '',
            'system_action' => $this->runData['request']->post['system_action'] ?? 'select',
            'system_criteria' => $this->runData['request']->post['system_criteria'] ?? '',
            'system_data' => $this->runData['request']->post['system_data'] ?? '',
            'system_arguments' => $this->runData['request']->post['system_arguments'] ?? '',
            'endpoint_slug' => $this->runData['request']->post['endpoint_slug'] ?? '',
        ];
        if (!empty($_SESSION[$sessionKey])) {
            $cached = $_SESSION[$sessionKey];
            unset($_SESSION[$sessionKey]);
            $formDefaults = $cached['form'] ?? $formDefaults;
            $this->runData['data']['payload_preview'] = $cached['payload'] ?? null;
            $this->runData['data']['verification'] = $cached['verification'] ?? null;
            if (!empty($cached['alert'])) {
                $this->runData['route']['alert'] = $cached['alert']['type'] ?? 'info';
                $this->runData['route']['alert_message'] = $cached['alert']['message'] ?? '';
            }
        } else {
            $this->runData['data']['payload_preview'] = null;
            $this->runData['data']['verification'] = null;
        }
        $this->runData['data']['form'] = $formDefaults;
        $this->runData['data']['dyn_url_hint'] = $this->buildDynUrlHint($msList, $formDefaults['ms_id'] ?? '');

        if (strtoupper($this->runData['request']->method) === 'POST') {
            $validationError = $this->validateVerifyForm($formDefaults);
            if ($validationError !== null) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = $validationError;
                return $this->runData;
            }

            $selectedApi = $this->findById($apis, $formDefaults['api_id']);
            if (!$selectedApi) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid API selection.';
                return $this->runData;
            }

            $paramsPayload = $this->parseParamsJson($formDefaults['params_json']);
            if ($paramsPayload === null && trim($formDefaults['params_json']) !== '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Parameters must be a valid JSON object.';
                return $this->runData;
            }
            if ($paramsPayload === null) {
                $paramsPayload = [];
            }

            if ($formDefaults['api_type'] === 'system') {
                $systemPayload = $this->buildSystemVerificationPayload($formDefaults, $gatewayConfig, $formDefaults['endpoint_slug'] !== '');
                if ($systemPayload['error']) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = $systemPayload['message'];
                    return $this->runData;
                }
                $requestPayload = [
                    'api_type' => 'system',
                    'api_key' => $selectedApi['s_identity'],
                    'security_key' => $formDefaults['security_key'],
                    'system' => $systemPayload['system'],
                    'params' => $paramsPayload,
                ];
                if ($formDefaults['endpoint_slug'] !== '') {
                    $requestPayload['endpoint'] = $formDefaults['endpoint_slug'];
                }
            } else {
                $selectedMs = $this->findById($msList, $formDefaults['ms_id']);
                $selectedRoute = $this->findById($routes, $formDefaults['route_id']);
                if (!$selectedMs || !$selectedRoute) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Invalid microservice/route selection.';
                    return $this->runData;
                }

                $routePath = $this->buildRoutePath($selectedMs, $selectedRoute);
                $requestPayload = [
                    'api_type' => 'application',
                    'ms' => $selectedMs['s_name'],
                    'route' => $routePath,
                    'api_key' => $selectedApi['s_identity'],
                    'security_key' => $formDefaults['security_key'],
                    'params' => $paramsPayload,
                ];
            }
            $result = $this->executeGatewayRequest($gatewayUrl, $requestPayload);

            $_SESSION[$sessionKey] = [
                'form' => $formDefaults,
                'payload' => $requestPayload,
                'verification' => $result,
                'alert' => [
                    'type' => $result['error'] ? 'danger' : 'success',
                    'message' => $result['error']
                        ? ('Request failed: ' . $result['error'])
                        : 'Request executed. Review the response below.',
                ],
            ];
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/apiendpoint/verify';
            header('Location: ' . $redirectUrl);
            exit;
        }

        return $this->runData;
    }

    private function buildDynUrlHint(array $msList, $msId): string {
        $msId = (string)$msId;
        if ($msId === '') {
            return '';
        }
        $ms = $this->findById($msList, $msId);
        if (!$ms || strtoupper($ms['s_type'] ?? '') !== 'DYN') {
            return '';
        }
        $prefix = '';
        if (!empty($this->runData['config']['sys']['workspace_slug_prefix'])) {
            $prefix = trim((string)$this->runData['config']['sys']['workspace_slug_prefix'], "/ \t\n\r\0\x0B");
        }
        $msName = $ms['s_name'] ?? '{ms_name}';
        $scope = strtolower($ms['s_scope'] ?? 'platform');
        if ($scope === 'workspace') {
            if ($prefix !== '') {
                return '/' . $prefix . '/' . '{space_name}' . '/' . $msName . '/' . '{route_name}' . '/...';
            }
            return '/' . '{space_name}' . '/' . $msName . '/' . '{route_name}' . '/...';
        }
        return '/' . $msName . '/' . '{route_name}' . '/...';
    }

    public function docs() {
        $this->runData['route']['h1'] = 'API Gateway Docs';
        $this->runData['route']['meta_title'] = 'API Gateway Docs';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/apiendpoint/view';
        $this->runData['route']['breadcrumb'] = [
            'API' => $this->runData['route']['rad_admin_url'] . '/apiendpoint/view',
        ];

        $gatewayUrl = rtrim($this->runData['config']['sys']['base_url'], '/') . '/api/';
        $eligibleData = $this->getApiEligibleRoutes();
        $msList = $eligibleData['microservices'];
        $routes = $eligibleData['routes'];
        $apis = $this->db->select('s_entity', ['s_type' => 'A', 'livestatus' => '1'], true);

        $routesByMs = [];
        foreach ($routes as $route) {
            $routesByMs[$route['s_ms_id']][] = $route;
        }

        $docs = [];
        foreach ($msList as $ms) {
            $msRoutes = $routesByMs[$ms['id']] ?? [];
            usort($msRoutes, function ($a, $b) {
                return $a['id'] <=> $b['id'];
            });

            $routeDocs = [];
            foreach ($msRoutes as $route) {
                $routePath = $this->buildRoutePath($ms, $route);
                $samplePayload = $this->buildSamplePayloadJson($ms['s_name'], $routePath);
                $permissionBindings = [
                    'route' => $bindings['route'][$route['id']] ?? [],
                    'microservice' => $bindings['ms'][$ms['id']] ?? [],
                ];
                $routeDocs[] = [
                    'id' => $route['id'],
                    'uid' => $route['uid'],
                    'name' => $route['s_name'] ?: ($route['uid'] ?: 'Route #' . $route['id']),
                    'description' => $route['s_description'],
                    'route_path' => $routePath,
                    'entity_scope' => $route['s_entity_scope'],
                    'definition' => $this->decodeServiceDefinition($route['s_service_definition']),
                    'permission_bindings' => $permissionBindings,
                    'sample_payload' => $samplePayload,
                    'curl_example' => $this->buildCurlExample($gatewayUrl, $samplePayload),
                ];
            }

            if (!empty($routeDocs)) {
                $docs[] = [
                    'microservice' => $ms,
                    'routes' => $routeDocs,
                ];
            }
        }

        $gatewayConfig = $this->runData['config']['sys']['api_gateway'] ?? [];
        $this->runData['data']['gateway_url'] = $gatewayUrl;
        $this->runData['data']['apis'] = $apis;
        $this->runData['data']['docs'] = $docs;
        $this->runData['data']['api_gateway'] = $gatewayConfig;
        $this->runData['data']['sample_payloads'] = $this->buildSamplePayloads($eligibleData, $gatewayConfig);
        $this->runData['data']['named_endpoints'] = $this->endpointService->listActive();
        $this->runData['data']['system_catalog'] = $this->buildSystemCatalog($gatewayConfig);
        $this->runData['data']['system_catalog'] = $this->buildSystemCatalog($gatewayConfig);

        return $this->runData;
    }

    public function endpoints() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }
        $this->runData['data']['can_idm_manage'] = $priv->can('idm_manage');
        $this->runData['route']['h1'] = 'Named API Endpoints';
        $this->runData['route']['meta_title'] = 'API Endpoints';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/apiendpoint/view';
        $this->runData['route']['breadcrumb'] = [
            'API' => $this->runData['route']['rad_admin_url'] . '/apiendpoint/view',
            'Endpoints' => null,
        ];

        if (strtoupper($this->runData['request']->method) === 'POST') {
            if (!$priv->can('idm_manage')) {
                throw new \Exception('Access denied.', 403);
            }
            $action = strtolower($this->runData['request']->post['_action'] ?? 'save');
            if ($action === 'delete') {
                $this->deleteEndpoint($this->runData['request']->post['uid'] ?? '');
            } else {
                $this->persistEndpoint($this->runData['request']->post ?? []);
            }
        }

        $editUid = $this->runData['route']['pathparts'][3] ?? '';
        $editing = null;
        $this->runData['data']['test_hooks'] = [];
        $this->runData['data']['test_hook_scope'] = 'api';
        $this->runData['data']['test_hook_ref'] = null;
        if ($editUid !== '') {
            $rows = $this->db->select('s_api_endpoint', ['uid' => $editUid], true);
            if (count($rows) === 1) {
                $editing = $rows[0];
                $editing['s_definition'] = $editing['s_definition'] ? json_encode(json_decode($editing['s_definition'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
                $editing['s_rate_limit'] = $editing['s_rate_limit'] ? json_encode(json_decode($editing['s_rate_limit'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
                if ($this->testHookHelper) {
                    $this->runData['data']['test_hooks'] = $this->testHookHelper->fetchForApi((int)$editing['id']);
                    $this->runData['data']['test_hook_scope'] = 'api';
                    $this->runData['data']['test_hook_ref'] = (int)$editing['id'];
                }
            }
        }

        $endpoints = $this->db->select('s_api_endpoint', [], true, ['s_name' => 'ASC']);
        $this->runData['data']['endpoints'] = $endpoints;
        $this->runData['data']['editing_endpoint'] = $editing;
        $gatewayConfig = $this->runData['config']['sys']['api_gateway'] ?? [];
        $this->runData['data']['api_gateway'] = $gatewayConfig;
        $this->runData['data']['target_lists'] = $this->buildEndpointTargetLists($gatewayConfig);
        $this->runData['data']['system_catalog'] = $this->buildSystemCatalog($gatewayConfig);
        return $this->runData;
    }

    public function services() {
        $this->runData['route']['h1'] = 'System Target Catalog';
        $this->runData['route']['meta_title'] = 'System Target Catalog';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/apiendpoint/view';
        $this->runData['route']['breadcrumb'] = [
            'API' => $this->runData['route']['rad_admin_url'] . '/apiendpoint/view',
            'System Catalog' => null,
        ];

        $gatewayConfig = $this->runData['config']['sys']['api_gateway'] ?? [];
        $this->runData['data']['system_catalog'] = $this->buildSystemCatalog($gatewayConfig);
        $this->runData['data']['named_endpoints'] = $this->endpointService->listActive();
        return $this->runData;
    }

    private function persistEndpoint(array $form): void {
        $slug = $this->slugify($form['s_slug'] ?? '');
        $name = trim($form['s_name'] ?? '');
        $type = trim($form['s_type'] ?? '');
        $target = trim($form['s_target'] ?? '');
        if ($name === '' || $slug === '' || $type === '' || $target === '') {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Name, slug, type, and target are required.';
            return;
        }
        $definition = $this->normalizeJsonField($form['s_definition'] ?? '');
        $rateLimit = $this->normalizeJsonField($form['s_rate_limit'] ?? '');
        if ($definition === null || $rateLimit === null) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Definition and rate limit must be valid JSON.';
            return;
        }
        $data = [
            's_name' => $name,
            's_slug' => $slug,
            's_type' => $type,
            's_target' => $target,
            's_description' => $form['s_description'] ?? '',
            's_definition' => $definition ? json_encode($definition) : null,
            's_rate_limit' => $rateLimit ? json_encode($rateLimit) : null,
        ];
        $uid = trim($form['uid'] ?? '');
        if ($uid === '') {
            $data['uid'] = $this->generateUuid();
            $this->db->insert('s_api_endpoint', $data);
            $message = 'Endpoint created.';
        } else {
            $this->db->update('s_api_endpoint', $data, ['uid' => $uid]);
            $message = 'Endpoint updated.';
        }
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = $message;
        $this->runData['request']->setAlert($message, 'success');
        $redirectUrl = $this->runData['route']['rad_admin_url'] . '/apiendpoint/endpoints';
        header("Location: {$redirectUrl}");
        exit;
    }

    private function deleteEndpoint(string $uid): void {
        $uid = trim($uid);
        if ($uid === '') {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Missing endpoint reference.';
            return;
        }
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() !== 'system_admin') {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Access denied.';
            return;
        }
        $rows = $this->db->select('s_api_endpoint', ['uid' => $uid], true);
        if (count($rows) !== 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Endpoint not found.';
            return;
        }
        $this->db->delete('s_api_endpoint', ['uid' => $uid]);
        $message = 'Endpoint removed.';
        $this->runData['request']->setAlert($message, 'success');
        $redirectUrl = $this->runData['route']['rad_admin_url'] . '/apiendpoint/endpoints';
        header("Location: {$redirectUrl}");
        exit;
    }

    private function buildEndpointTargetLists(array $config): array {
        $systemTables = $config['system_tables'] ?? [];
        $systemServices = array_map(static function ($service) {
            return $service['key'];
        }, $config['system_services'] ?? []);
        $utilityCallables = array_keys($config['utility_callables'] ?? []);
        $vendorProfiles = array_keys($config['vendor_profiles'] ?? []);
        $aiPresets = array_keys($config['ai_presets'] ?? []);

        return [
            'system_table' => $systemTables,
            'system_service' => $systemServices,
            'utility' => $utilityCallables,
            'vendor' => $vendorProfiles,
            'ai' => $aiPresets,
        ];
    }

    private function buildSystemCatalog(array $config): array {
        $tables = array_values($config['system_tables'] ?? []);
        if (empty($tables)) {
            $tables = $this->loadDefaultSystemTables();
        }
        $services = [];
        $serviceList = $config['system_services'] ?? $this->loadServiceManifest();
        foreach ($serviceList as $service) {
            if (empty($service['key'])) {
                continue;
            }
            $services[] = [
                'key' => $service['key'],
                'label' => $service['label'] ?? $service['key'],
                'description' => $service['description'] ?? '',
                'callable' => $this->resolveCallableLabel($service),
                'args_hint' => $service['args_hint'] ?? [],
            ];
        }
        return [
            'tables' => $tables,
            'services' => $services,
        ];
    }

    private function resolveCallableLabel(array $service): string {
        $class = $service['class'] ?? '';
        $method = $service['method'] ?? '';
        if ($class === '' && $method === '') {
            return '';
        }
        if ($class === '') {
            return $method;
        }
        if ($method === '') {
            return $class;
        }
        return rtrim($class, '\\') . '::' . $method;
    }

    private function loadServiceManifest(): array {
        $siteDir = $this->runData['config']['dir']['site'] ?? dirname(dirname(__DIR__));
        $manifestPath = $siteDir . '/rad/config/api-services.php';
        if (file_exists($manifestPath)) {
            $manifest = include $manifestPath;
            if (is_array($manifest)) {
                return $manifest;
            }
        }
        return [];
    }

    private function loadDefaultSystemTables(): array {
        $siteDir = $this->runData['config']['dir']['site'] ?? dirname(dirname(__DIR__));
        $adminConfigPath = $siteDir . '/rad/admin/rad.config.php';
        if (file_exists($adminConfigPath)) {
            $config = include $adminConfigPath;
            if (!empty($config['api_gateway']['system_tables']) && is_array($config['api_gateway']['system_tables'])) {
                return array_values($config['api_gateway']['system_tables']);
            }
        }
        return [];
    }

    private function getApiEligibleRoutes(): array {
        $msList = $this->db->select('s_ms', ['livestatus' => '1'], true);
        $routes = $this->db->select('s_msroute', ['livestatus' => '1'], true);
        $msList = $this->filterRestrictedMs($msList);
        $routes = $this->filterRoutesByMs($routes, $msList);

        $eligibleRoutes = array_values(array_filter($routes, function ($route) {
            $scope = strtoupper($route['s_entity_scope'] ?? 'U');
            return in_array($scope, ['A', 'UA'], true);
        }));

        $routesByMs = [];
        foreach ($eligibleRoutes as $route) {
            $routesByMs[$route['s_ms_id']][] = $route;
        }

        $eligibleMicroservices = array_values(array_filter($msList, function ($ms) use ($routesByMs) {
            return !empty($routesByMs[$ms['id']] ?? []);
        }));

        return [
            'microservices' => $eligibleMicroservices,
            'routes' => $eligibleRoutes,
        ];
    }

    private function filterRestrictedMs(array $msList): array {
        $config = $this->runData['config'] ?? [];
        $entity = $this->runData['entity'] ?? [];
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
        $allowedIds = array_map(function ($ms) {
            return (int)$ms['id'];
        }, $msList);
        $allowedIds = array_flip($allowedIds);
        return array_values(array_filter($routes, function ($route) use ($allowedIds) {
            return isset($allowedIds[(int)($route['s_ms_id'] ?? 0)]);
        }));
    }

    private function validateVerifyForm(array $form): ?string {
        if ($form['api_id'] === '') {
            return 'API selection is required.';
        }
        if (trim($form['security_key']) === '') {
            return 'Security key is required.';
        }
        if ($form['api_type'] === 'system') {
            if ($form['endpoint_slug'] === '' && trim($form['system_target']) === '') {
                return 'System target or endpoint is required.';
            }
            return null;
        }
        if ($form['ms_id'] === '' || $form['route_id'] === '') {
            return 'Microservicelet and Route selections are required.';
        }
        return null;
    }

    private function findById(array $items, $id) {
        foreach ($items as $item) {
            if ((string) $item['id'] === (string) $id) {
                return $item;
            }
        }
        return null;
    }

    private function parseParamsJson(string $json): ?array {
        if (trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }
        return $decoded;
    }

    private function buildRoutePath(array $ms, array $route): string {
        switch ($ms['s_type']) {
            case 'STA':
            case 'DYN':
                return $route['s_name'] ?? '';
            case 'UID':
                return $route['uid'] ?? '';
            default:
                return $route['s_name'] ?? (string) $route['id'];
        }
    }

    private function executeGatewayRequest(string $url, array $payload): array {
        $ch = curl_init($url);
        $jsonPayload = json_encode($payload);

        $requestTimeout = $this->getSysConfigInt('gateway_request_timeout', 45);
        $connectTimeout = $this->getSysConfigInt('gateway_connect_timeout', 10);
        if ($connectTimeout > $requestTimeout) {
            $connectTimeout = $requestTimeout;
        }

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonPayload),
            ],
            CURLOPT_TIMEOUT => $requestTimeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL,
        ];

        if ($this->shouldIgnoreSslVerification($url)) {
            // Local HTTPS endpoints commonly use self-signed certificates.
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        curl_setopt_array($ch, $curlOptions);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = null;
        if ($responseBody !== false) {
            $decodedObj = json_decode($responseBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded = $decodedObj;
            }
        }

        return [
            'http_code' => $httpCode,
            'body' => $responseBody,
            'decoded' => $decoded,
            'error' => $curlError,
        ];
    }

    private function shouldIgnoreSslVerification(string $url): bool {
        if ($this->isSelfSignedOverrideEnabled()) {
            return true;
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        $localHosts = ['localhost', 'localhost.radsandbox', '127.0.0.1', '::1'];
        if (in_array($host, $localHosts, true)) {
            return true;
        }

        return strpos($host, '.localhost') !== false || substr($host, -6) === '.local';
    }

    private function isSelfSignedOverrideEnabled(): bool {
        $flag = $this->runData['config']['sys']['allow_self_signed_gateway'] ?? null;
        if ($flag === null) {
            return false;
        }

        $normalized = strtolower(trim((string) $flag));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function buildSamplePayloadJson(string $msName, string $routePath): string {
        $payload = [
            'ms' => $msName,
            'route' => $routePath,
            'api_key' => '<api_key>',
            'security_key' => '<security_key>',
            'params' => new \stdClass(),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function buildCurlExample(string $gatewayUrl, string $payloadJson): string {
        $escapedPayload = $this->escapeForSingleQuotes($payloadJson);
        return "curl -X POST '" . $gatewayUrl . "' \\\n"
            . "  -H 'Content-Type: application/json' \\\n"
            . "  -d '" . $escapedPayload . "'";
    }

    private function escapeForSingleQuotes(string $value): string {
        return str_replace("'", "'\\''", $value);
    }

    private function decodeServiceDefinition($json): ?array {
        if (!$json) {
            return null;
        }
        $decoded = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function fetchPermissionBindings(array $msList, array $routes): array {
        $msIds = [];
        foreach ($msList as $ms) {
            if (isset($ms['id'])) {
                $msIds[] = (int)$ms['id'];
            }
        }
        $msIds = array_values(array_unique(array_filter($msIds)));

        $routeIds = [];
        foreach ($routes as $route) {
            if (isset($route['id'])) {
                $routeIds[] = (int)$route['id'];
            }
        }
        $routeIds = array_values(array_unique(array_filter($routeIds)));

        if (empty($msIds) && empty($routeIds)) {
            return ['ms' => [], 'route' => []];
        }

        $params = [':livestatus' => '1'];
        $clauses = [];
        if (!empty($msIds)) {
            $clauses[] = "(s_object_type = 'ms' AND s_object_id IN (" . $this->buildInClause('ms', $msIds, $params) . "))";
        }
        if (!empty($routeIds)) {
            $clauses[] = "(s_object_type = 'route' AND s_object_id IN (" . $this->buildInClause('route', $routeIds, $params) . "))";
        }

        if (empty($clauses)) {
            return ['ms' => [], 'route' => []];
        }

        $sql = "SELECT id, s_object_type, s_object_id, s_role_id
                FROM s_permission_binding
                WHERE livestatus = :livestatus AND (" . implode(' OR ', $clauses) . ")
                ORDER BY s_object_type, s_object_id, s_role_id";
        $rows = $this->db->query($sql, $params);

        $roleLookup = $this->getRoleLookup();
        $grouped = ['ms' => [], 'route' => []];
        foreach ($rows as $row) {
            $type = $row['s_object_type'];
            if (!isset($grouped[$type][$row['s_object_id']])) {
                $grouped[$type][$row['s_object_id']] = [];
            }
            $roleMeta = $roleLookup[$row['s_role_id']] ?? null;
            $grouped[$type][$row['s_object_id']][] = [
                'role_id' => (int)$row['s_role_id'],
                'role_name' => $roleMeta['s_role_name'] ?? ('Role #' . $row['s_role_id']),
                'role_code' => $roleMeta['s_code'] ?? null
            ];
        }

        return $grouped;
    }

    private function buildInClause(string $prefix, array $ids, array &$params): string {
        $placeholders = [];
        foreach ($ids as $index => $value) {
            $paramName = ':' . $prefix . $index;
            $placeholders[] = $paramName;
            $params[$paramName] = $value;
        }
        return implode(',', $placeholders);
    }

    private function getRoleLookup(): array {
        if ($this->roleLookup !== null) {
            return $this->roleLookup;
        }

        $roles = $this->db->select('s_role', ['livestatus' => '1'], true, ['s_role_name' => 'ASC']);
        $lookup = [];
        foreach ($roles as $role) {
            $lookup[$role['id']] = $role;
        }
        $this->roleLookup = $lookup;
        return $this->roleLookup;
    }

    private function getSysConfigInt(string $key, int $default): int {
        if (!isset($this->runData['config']['sys'][$key])) {
            return $default;
        }

        $value = (int) $this->runData['config']['sys'][$key];
        return $value > 0 ? $value : $default;
    }

    private function buildSamplePayloads(array $eligibleData, array $gatewayConfig): array {
        $msList = $eligibleData['microservices'] ?? [];
        $routes = $eligibleData['routes'] ?? [];
        $sampleMs = $msList[0]['s_name'] ?? 'ms_name';
        $sampleRoute = $routes[0]['s_name'] ?? 'service/method';

        $applicationPayload = [
            'api_type' => 'application',
            'ms' => $sampleMs,
            'route' => $sampleRoute,
            'api_key' => '<api_key>',
            'security_key' => '<security_key>',
            'params' => new \stdClass(),
        ];

        $systemTable = $gatewayConfig['system_tables'][0] ?? 's_ms';
        $systemPayload = [
            'api_type' => 'system',
            'api_key' => '<api_key>',
            'security_key' => '<security_key>',
            'system' => [
                'target_type' => 'table',
                'target' => $systemTable,
                'action' => 'select',
                'criteria' => new \stdClass(),
            ],
        ];

        return [
            'application' => json_encode($applicationPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'system' => json_encode($systemPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function buildSystemVerificationPayload(array $form, array $gatewayConfig, bool $targetOptional = false): array {
        $target = trim($form['system_target'] ?? '');
        if ($target === '' && !$targetOptional) {
            return ['error' => true, 'message' => 'System target cannot be empty.'];
        }
        $payload = [
            'target_type' => strtolower(trim($form['system_target_type'] ?? 'table')),
            'target' => $target,
            'action' => strtolower(trim($form['system_action'] ?? 'select')),
            'criteria' => $this->decodeJsonObject($form['system_criteria'] ?? ''),
            'data' => $this->decodeJsonObject($form['system_data'] ?? ''),
            'arguments' => $this->decodeJsonArray($form['system_arguments'] ?? ''),
        ];
        if ($payload['criteria'] === null && trim($form['system_criteria'] ?? '') !== '') {
            return ['error' => true, 'message' => 'System criteria must be valid JSON.'];
        }
        if ($payload['data'] === null && trim($form['system_data'] ?? '') !== '') {
            return ['error' => true, 'message' => 'System data must be valid JSON.'];
        }
        if ($payload['arguments'] === null && trim($form['system_arguments'] ?? '') !== '') {
            return ['error' => true, 'message' => 'System arguments must be a JSON array.'];
        }
        if ($payload['criteria'] === null) {
            $payload['criteria'] = new \stdClass();
        }
        if ($payload['data'] === null) {
            $payload['data'] = new \stdClass();
        }
        if ($payload['arguments'] === null) {
            $payload['arguments'] = [];
        }
        if ($payload['target_type'] === 'table' && $payload['target'] !== '') {
            $payload['target'] = $this->ensureTablePrefix($payload['target'], 's_');
        }

        return ['error' => false, 'system' => $payload];
    }

    private function decodeJsonObject(string $json) {
        if (trim($json) === '') {
            return new \stdClass();
        }
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }
        return $decoded;
    }

    private function decodeJsonArray(string $json): ?array {
        if (trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }
        return array_values($decoded);
    }

    private function normalizeJsonField(string $value) {
        if (trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $decoded;
    }

    private function slugify(string $value): string {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-') ?: bin2hex(random_bytes(4));
    }

    private function generateUuid(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function ensureTablePrefix(string $name, string $prefix): string {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        $lower = strtolower($name);
        if (strpos($lower, 's_') === 0 || strpos($lower, 'a_') === 0) {
            return $lower === $name ? $name : $lower;
        }
        $clean = ltrim($lower, '_');
        return $prefix . $clean;
    }
}

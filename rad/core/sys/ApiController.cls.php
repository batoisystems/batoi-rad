<?php
namespace Core\Sys;

/**
 * API Gateway controller that accepts JSON payloads at /api
 * and routes them to the correct microservice + route combination.
 */
class ApiController {
    private $db;
    private $view;
    private $session;
    private $errorHandler;
    private $runData = [];
    private $routeIndex = [];
    private $payload = [];
    private $serviceName = '';
    private $serviceMethod = '';
    private $serviceArgs = [];
    private $permissionService;
    private $endpointService;
    private $apiAccount = [];
    private $apiType = 'application';

    public function __construct(array $runData, \Core\Sys\ErrorHandler $errorHandler) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->view = $runData['view'];
        $this->session = $runData['session'];
        $this->errorHandler = $errorHandler;
        $this->routeIndex = [];
        $this->permissionService = $runData['permissionService'] ?? new PermissionService($this->db);
        $this->endpointService = new ApiEndpointService($this->db, $errorHandler, $runData['config']['sys']['api_gateway'] ?? [], $runData);
    }

    public function handle() {
        date_default_timezone_set('UTC');
        $this->errorHandler->setResponseMode('json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respondError(405, 'Only POST requests are supported at the API gateway.');
        }

        $this->payload = $this->parsePayload();
        $this->apiType = $this->normalizeApiType($this->payload['api_type'] ?? null);
        $this->apiAccount = $this->authenticateCaller($this->apiType);
        $endpointSlug = trim((string)($this->payload['endpoint'] ?? ''));
        if ($endpointSlug !== '') {
            $endpoint = $this->endpointService->getBySlug($endpointSlug);
            $this->enforceEndpointAccess($endpoint);
            $result = $this->executeNamedEndpoint($endpoint);
            $this->respondJson(200, [
                'success' => true,
                'api_type' => $this->apiType,
                'endpoint' => $endpoint['s_slug'],
                'data' => $result,
            ]);
            return;
        }
        if ($this->apiType === 'system') {
            $this->handleSystemApi();
            return;
        }
        $msName = $this->payload['ms'] ?? '';
        if ($msName === '') {
            $this->respondError(400, 'Missing microservice identifier in the payload.');
        }

        $routePath = isset($this->payload['route']) ? trim($this->payload['route'], '/') : '';
        $this->routeIndex = $routePath === '' ? [] : array_values(array_filter(explode('/', $routePath), 'strlen'));

        // Make the posted parameters accessible to downstream services.
        $params = $this->payload['params'] ?? [];
        $this->runData['request']->data = $params;
        $this->runData['request']->post = $params;

        $msDetails = $this->loadMeshDetails($msName);
        $routeDetails = $this->resolveRoute($msDetails);
        $this->enforceAccess($routeDetails);

        $routeDefinition = $this->parseRouteDefinition($routeDetails);
        $dataPayload = $this->executeRoute($routeDetails, $routeDefinition);

        $response = [
            'success' => true,
            'api_type' => $this->apiType,
            'ms' => $this->runData['ms'],
            'route' => $this->summarizeRoute($routeDetails, $routeDefinition),
            'data' => $dataPayload,
        ];
        $this->respondJson(200, $response);
    }

    private function parsePayload(): array {
        $rawBody = $this->runData['request']->body ?? file_get_contents('php://input');
        if ($rawBody === '' || $rawBody === null) {
            return [];
        }

        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $this->respondError(400, 'Invalid JSON payload.', ['error' => json_last_error_msg()]);
        }

        return $decoded;
    }

    private function normalizeApiType(?string $value): string {
        if ($value === null || $value === '') {
            $defaults = $this->runData['config']['sys']['api_gateway']['default_api_types'] ?? ['application'];
            return strtolower($defaults[0] ?? 'application');
        }
        $normalized = strtolower(trim($value));
        return in_array($normalized, ['system', 'application'], true) ? $normalized : 'application';
    }

    private function authenticateCaller(string $apiType): array {
        $apiKey = $this->payload['api_key'] ?? '';
        $securityKey = $this->payload['security_key'] ?? '';
        if ($apiKey === '' || $securityKey === '') {
            $this->respondError(401, 'API key and security key are required.');
        }

        $apiRows = $this->db->select(
            's_entity',
            ['livestatus' => '1', 's_type' => 'A', 's_identity' => $apiKey],
            true
        );
        if (count($apiRows) !== 1) {
            $this->respondError(401, 'Invalid API key.');
        }

        $api = $apiRows[0];
        if (!password_verify($securityKey, $api['s_identity_secret'])) {
            $this->respondError(401, 'Invalid security key.');
        }
        $authInfo = [
            'access_ips' => $api['s_access_ips'] ?? '',
            'api_types' => $this->decodeJsonField($api['s_api_types'] ?? null),
            'allowed_endpoints' => $this->decodeJsonField($api['s_api_allowed_endpoints'] ?? null),
            'system_tables' => $this->decodeJsonField($api['s_api_system_tables'] ?? null),
            'system_services' => $this->decodeJsonField($api['s_api_system_services'] ?? null),
        ];

        $allowedTypes = $authInfo['api_types'] ?? ($this->runData['config']['sys']['api_gateway']['default_api_types'] ?? ['application']);
        if (!in_array($apiType, $allowedTypes, true)) {
            $this->respondError(403, 'API key does not allow this API type.');
        }

        $allowedIps = array_filter(array_map('trim', explode(',', $authInfo['access_ips'] ?? '')));
        if (!empty($allowedIps)) {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            if ($clientIp === '' || !in_array($clientIp, $allowedIps, true)) {
                $this->respondError(403, 'API key is not allowed from this IP address.');
            }
        }

        $api['auth_info'] = $authInfo;
        $api['allowed_endpoints'] = $authInfo['allowed_endpoints'] ?? [];
        return $api;
    }

    private function enforceEndpointAccess(array $endpoint): void {
        if ($this->apiType !== 'system') {
            $this->respondError(403, 'Endpoint invocations are available only for system API type.');
        }
        $allowed = $this->apiAccount['allowed_endpoints'] ?? [];
        if (!empty($allowed) && !in_array($endpoint['s_slug'], $allowed, true)) {
            $this->respondError(403, 'API key cannot access this endpoint.');
        }
    }

    private function executeNamedEndpoint(array $endpoint): array {
        switch ($endpoint['s_type']) {
            case 'system_table':
            case 'system_service':
                return $this->handleSystemEndpoint($endpoint);
            case 'utility':
                $service = new UtilityApiService($this->runData['config']['sys']['api_gateway'] ?? []);
                return $service->execute($endpoint, $this->payload['params'] ?? [], $this->apiAccount);
            case 'vendor':
                $service = new VendorApiService();
                return $service->execute($endpoint, $this->payload['params'] ?? []);
            case 'ai':
                $aiClient = new AiService($this->runData['config'], $this->errorHandler);
                $service = new AiApiGatewayService($aiClient, $this->errorHandler);
                return $service->execute($endpoint, $this->payload['params'] ?? []);
            default:
                $this->respondError(400, 'Unsupported endpoint type.');
        }
    }

    private function handleSystemEndpoint(array $endpoint): array {
        $definition = $endpoint['definition'] ?? [];
        $payload = $this->payload['system'] ?? [];
        if (!is_array($payload)) {
            $payload = [];
        }
        $systemBlock = array_merge($definition, $payload);
        $systemBlock['target'] = $systemBlock['target'] ?? $endpoint['s_target'];
        $systemBlock['target_type'] = $systemBlock['target_type'] ?? ($endpoint['s_type'] === 'system_service' ? 'service' : 'table');
        $systemBlock['action'] = $systemBlock['action'] ?? 'select';
        $wrapper = ['system' => $systemBlock];
        try {
            $service = new SystemApiService($this->db, $this->errorHandler, $this->runData['config']['sys']['api_gateway'] ?? [], $this->runData);
            return $service->dispatch($wrapper, $this->apiAccount);
        } catch (\Throwable $e) {
            $this->respondError(400, $e->getMessage());
        }
        return [];
    }

    private function handleSystemApi(): void {
        try {
            $service = new SystemApiService(
                $this->db,
                $this->errorHandler,
                $this->runData['config']['sys']['api_gateway'] ?? [],
                $this->runData
            );
            $dataset = $service->dispatch($this->payload, $this->apiAccount);
            $this->respondJson(200, [
                'success' => true,
                'api_type' => 'system',
                'data' => $dataset,
            ]);
        } catch (\Throwable $e) {
            $this->respondError(400, $e->getMessage());
        }
    }

    private function decodeJsonField($value): array {
        if (empty($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function loadMeshDetails(string $msName): array {
        $msRows = $this->db->select('s_ms', ['livestatus' => '1', 's_name' => $msName], true);
        if (count($msRows) !== 1) {
            $this->respondError(404, 'Microservicelet not found.', ['ms' => $msName]);
        }

        $ms = $msRows[0];
        $this->runData['ms'] = [
            'id' => $ms['id'],
            'uid' => $ms['uid'],
            'parent_id' => 0,
            'name' => $ms['s_name'],
            'type' => $ms['s_type'],
            'version_number' => $ms['s_version_number'],
            'version_type' => $ms['version_type'],
            'scope' => $ms['s_scope'],
            'access_scope' => (strtolower($ms['s_scope'] ?? '') === 'global') ? 'public' : 'private',
            'access_role_ids' => [],
            'default_route_id' => $ms['s_default_route_id'],
            'tpl_name' => $ms['s_tpl_name'],
        ];

        $this->runData['ms']['parent_name'] = 'NA';
        $this->runData['ms']['parent_uid'] = '0';

        return $ms;
    }

    private function resolveRoute(array $ms): array {
        if (count($this->routeIndex) === 0) {
            $routeRows = $this->db->select('s_msroute', [
                'livestatus' => '1',
                'id' => $ms['s_default_route_id'],
                's_ms_id' => $ms['id']
            ], true);
            if (count($routeRows) !== 1) {
                $this->respondError(404, 'Default route not found for the microservice.', ['ms' => $ms['s_name']]);
            }
            $this->runData['route']['path'] = '';
            return $routeRows[0];
        }

        switch ($ms['s_type']) {
            case 'STA':
                return $this->resolveStaticRoute($ms);
            case 'DYN':
                return $this->resolveDynamicRoute($ms);
            case 'UID':
                return $this->resolveUidRoute($ms);
            case 'ID':
            default:
                return $this->resolveIdRoute($ms);
        }
    }

    private function resolveStaticRoute(array $ms): array {
        $degree = $ms['s_definition'] !== '' ? json_decode($ms['s_definition'], true)['degree'] ?? 1 : 1;
        $routeParts = array_slice($this->routeIndex, 0, $degree);
        $routeName = implode('/', $routeParts);

        $routeRows = $this->db->select('s_msroute', [
            'livestatus' => '1',
            's_name' => $routeName,
            's_ms_id' => $ms['id']
        ], true);

        if (count($routeRows) !== 1) {
            $this->respondError(404, 'Static route not found.', ['route' => $routeName]);
        }

        $this->runData['route']['path'] = $routeName;
        return $routeRows[0];
    }

    private function resolveDynamicRoute(array $ms): array {
        $this->serviceName = $this->routeIndex[0] ?? '';
        $this->serviceMethod = '';
        $this->serviceArgs = count($this->routeIndex) > 1 ? array_slice($this->routeIndex, 1) : [];

        if ($this->serviceName === '') {
            $routeRows = $this->db->select('s_msroute', [
                'livestatus' => '1',
                'id' => $ms['s_default_route_id'],
                's_ms_id' => $ms['id']
            ], true);
            if (count($routeRows) !== 1) {
                $this->respondError(404, 'Default route not found for the microservice.', ['ms' => $ms['s_name']]);
            }
            $routeName = $routeRows[0]['s_name'] ?? '';
            $this->runData['route']['path'] = $routeName;
            $this->runData['route']['dyn_default'] = 'Y';
            return $routeRows[0];
        }

        $routeName = $this->serviceName;
        $routeRows = $this->db->select('s_msroute', [
            'livestatus' => '1',
            's_name' => $routeName,
            's_ms_id' => $ms['id']
        ], true);

        if (count($routeRows) !== 1) {
            $this->respondError(404, 'Dynamic route not found.', ['route' => $routeName]);
        }

        $this->runData['route']['path'] = $routeName;
        return $routeRows[0];
    }

    private function resolveUidRoute(array $ms): array {
        $uid = $this->routeIndex[0];
        $routeRows = $this->db->select('s_msroute', [
            'livestatus' => '1',
            'uid' => $uid,
            's_ms_id' => $ms['id']
        ], true);

        if (count($routeRows) !== 1) {
            $this->respondError(404, 'UID route not found.', ['uid' => $uid]);
        }

        $this->runData['route']['path'] = $uid;
        return $routeRows[0];
    }

    private function resolveIdRoute(array $ms): array {
        $identifier = $this->routeIndex[0] ?? '';
        if ($identifier === '') {
            $this->respondError(400, 'Route identifier cannot be empty.');
        }

        $routeRows = [];

        if (ctype_digit($identifier)) {
            $routeRows = $this->db->select('s_msroute', [
                'livestatus' => '1',
                'id' => $identifier,
                's_ms_id' => $ms['id']
            ], true);
        }

        if (count($routeRows) !== 1) {
            $routeRows = $this->db->select('s_msroute', [
                'livestatus' => '1',
                's_name' => $identifier,
                's_ms_id' => $ms['id']
            ], true);
        }

        if (count($routeRows) !== 1) {
            $this->respondError(404, 'Route not found.', ['identifier' => $identifier]);
        }

        $this->runData['route']['path'] = $identifier;
        return $routeRows[0];
    }

    private function enforceAccess(array $routeDetails): void {
        $this->runData['route']['id'] = $routeDetails['id'];
        $this->runData['route']['uid'] = $routeDetails['uid'];

        $scope = strtoupper($routeDetails['s_entity_scope'] ?? 'U');
        if (!in_array($scope, ['A', 'UA'], true)) {
            $this->respondError(403, 'This route is not available for API access.');
        }

        $accessScope = $this->runData['ms']['access_scope'] ?? 'public';
        if ($accessScope !== 'private') {
            return;
        }

        if (!$this->session->get('entity_id')) {
            $this->respondError(401, 'Authentication required for this resource.');
        }

        $routeBindings = $this->permissionService->hasBindings('route', (int)$routeDetails['id']);
        $msBindings = $this->permissionService->hasBindings('ms', (int)$this->runData['ms']['id']);
        if ($routeBindings || $msBindings) {
            $entityId = $this->runData['entity']['id'] ?? null;
            if ($entityId === null) {
                $this->respondError(401, 'Authentication required for this resource.');
            }
            $spaceId = $this->payload['space_id'] ?? null;
            $msId = (int)$this->runData['ms']['id'];
            $allowed = $routeBindings
                ? $this->permissionService->canAccess($entityId, 'route', (int)$routeDetails['id'], 'use', $spaceId, $msId)
                : $this->permissionService->canAccess($entityId, 'ms', $msId, 'use', $spaceId, $msId);
            if (!$allowed) {
                $this->respondError(403, 'Entity does not have access to this route.');
            }
            return;
        }

        // No bindings present and no legacy roles: deny by default
        $this->respondError(403, 'Entity does not have access to this route.');
    }

    private function executeRoute(array $routeDetails, array $routeDefinition): array {
        $response = [
            'params' => $this->payload['params'] ?? [],
        ];

        switch ($this->runData['ms']['type']) {
            case 'STA':
                $response['content'] = $this->executeStaticRouteData($routeDefinition);
                break;
            case 'DYN':
                $this->serviceMethod = $routeDefinition['method'] ?? 'index';
                $response['result'] = $this->executeDynamicRouteData();
                break;
            default:
                $response['route'] = [
                    'id' => $routeDetails['id'],
                    'uid' => $routeDetails['uid'],
                ];
                break;
        }

        return $response;
    }

    private function executeStaticRouteData(array $routeDefinition): array {
        if (isset($routeDefinition['content_id'])) {
            $content = $this->getContent($routeDefinition['content_id'], 'id');
            if ($content) {
                return $content;
            }
        }

        if ($this->runData['route']['path'] !== '') {
            $content = $this->getContent($this->runData['route']['path'], 'slug');
            if ($content) {
                return $content;
            }
        }

        return [
            'message' => 'No static content found for the requested route.',
        ];
    }

    private function executeDynamicRouteData() {
        if ($this->serviceName === '' || $this->serviceMethod === '') {
            return ['message' => 'Dynamic service definition missing.'];
        }

        $serviceRows = $this->db->select('s_mscontroller', [
            'livestatus' => '1',
            's_name' => $this->serviceName,
            's_ms_id' => $this->runData['ms']['id']
        ], true);

        if (count($serviceRows) !== 1) {
            return ['message' => 'Service definition not found for the requested dynamic route.'];
        }

        $service = $serviceRows[0];
        if ($service['s_type'] === 'STD') {
            $serviceClass = '\App\\' . $this->runData['ms']['name'] . '\\' . ucfirst($this->serviceName);
            if (!class_exists($serviceClass)) {
                return ['message' => 'Service class not found.', 'class' => $serviceClass];
            }
            $serviceObject = new $serviceClass($this->runData);
        } else {
            $serviceObject = new \Core\Sys\DataService($this->runData, $this->errorHandler);
        }

        if (!method_exists($serviceObject, $this->serviceMethod)) {
            return ['message' => 'Service method not found.', 'method' => $this->serviceMethod];
        }

        $result = $serviceObject->{$this->serviceMethod}(...$this->serviceArgs);
        if ($result === null && isset($this->runData['data'])) {
            return $this->runData['data'];
        }

        return $result ?? ['message' => 'Service executed with no response payload.'];
    }

    private function getContent($identifier, string $refPath): array {
        $branchService = new BranchService(
            $this->db,
            $this->runData['config'] ?? [],
            $this->runData['entity'] ?? [],
            $this->runData['request'] ?? null
        );
        $branch = $branchService->resolveRuntimeBranch([
            'ms_name' => (string)($this->runData['ms']['name'] ?? ''),
            'content_id' => (int)$identifier,
        ]);
        if ($refPath === 'slug') {
            $contentRows = $this->db->select('s_content', ['livestatus' => '1', 's_slug' => $identifier], false);
        } else {
            $contentRows = $this->db->select('s_content', ['livestatus' => '1', 'id' => $identifier], false);
        }

        if (count($contentRows) !== 1) {
            return [];
        }

        $content = $contentRows[0];
        if ($branch === 'beta') {
            $beta = $this->extractContentBranch($content);
            if (!empty($beta)) {
                $content = $this->applyContentBranch($content, $beta);
            }
        }
        return [
            'id' => $content['id'],
            'title' => $content['s_title'],
            'meta_title' => $content['s_meta_title'],
            'meta_description' => $content['s_meta_description'],
            'content' => $content['s_content'],
        ];
    }

    private function parseRouteDefinition(array $routeDetails): array {
        if (!empty($routeDetails['s_service_definition'])) {
            $decoded = json_decode($routeDetails['s_service_definition'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function summarizeRoute(array $routeDetails, array $routeDefinition): array {
        return [
            'id' => $routeDetails['id'],
            'uid' => $routeDetails['uid'],
            'name' => $routeDetails['s_name'],
            'definition' => $routeDefinition,
        ];
    }

    private function respondJson(int $statusCode, array $payload): void {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit();
    }

    private function extractContentBranch(array $contentRow): array {
        $infoRaw = $contentRow['s_additional_info'] ?? '';
        if (!$infoRaw) {
            return [];
        }
        $info = is_array($infoRaw) ? $infoRaw : json_decode((string)$infoRaw, true);
        if (!is_array($info)) {
            return [];
        }
        $beta = $info['branch_beta'] ?? [];
        return is_array($beta) ? $beta : [];
    }

    private function applyContentBranch(array $contentRow, array $beta): array {
        foreach ($beta as $key => $value) {
            if (array_key_exists($key, $contentRow)) {
                $contentRow[$key] = $value;
            }
        }
        return $contentRow;
    }

    private function respondError(int $statusCode, string $message, array $details = []): void {
        $this->errorHandler->reportError($message);
        $payload = [
            'success' => false,
            'message' => $message,
        ];
        if (!empty($details)) {
            $payload['details'] = $details;
        }
        $this->respondJson($statusCode, $payload);
    }
}

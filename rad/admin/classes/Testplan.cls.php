<?php
namespace RadAdmin;

class Testplan {
    private $runData = [];
    private $db;
    private $errorHandler;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'] ?? null;
        $this->errorHandler = $runData['errorHandler'] ?? null;
    }

    private function requireAdmin() {
        // Assume RAD Admin access granted; add granular privilege checks if needed.
        return true;
    }

    public function view() {
        $this->runData['route']['h1'] = 'Test Plans';
        $this->runData['route']['meta_title'] = 'Test Plans';
        $this->runData['route']['breadcrumb'] = ['Home' => $this->runData['route']['rad_admin_url'] . '/home/view', 'Test Plans' => ''];

        $maps = $this->loadScopeMaps();

        $plans = [];
        try {
            $plans = $this->db->query(trim("
                SELECT p.*,
                    (SELECT COUNT(*) FROM s_test_item i WHERE i.s_test_plan_id = p.id AND i.livestatus <> '0') AS item_count,
                    (SELECT COUNT(*) FROM s_test_run r WHERE r.s_test_plan_id = p.id AND r.livestatus <> '0') AS run_count,
                    (SELECT r.s_status FROM s_test_run r WHERE r.s_test_plan_id = p.id AND r.livestatus <> '0' ORDER BY r.createstamp DESC LIMIT 1) AS last_status,
                    (SELECT r.createstamp FROM s_test_run r WHERE r.s_test_plan_id = p.id AND r.livestatus <> '0' ORDER BY r.createstamp DESC LIMIT 1) AS last_run_at
                FROM s_test_plan p
                WHERE p.livestatus <> '0'
                ORDER BY p.updatestamp DESC
                LIMIT 200
            "));
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
        }

        $this->runData['data']['plans'] = is_array($plans) ? $plans : [];
        $this->runData['data']['scope_maps'] = $maps;
        return $this->runData;
    }

    public function add() {
        $this->requireAdmin();
        $this->runData['route']['h1'] = 'Add Test Plan';
        $this->runData['route']['meta_title'] = 'Add Test Plan';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Test Plans' => $this->runData['route']['rad_admin_url'] . '/testplan/view',
            'Add' => ''
        ];

        $maps = $this->loadScopeMaps();
        $this->runData['data']['scope_maps'] = $maps;
        $get = isset($this->runData['request']->get) ? $this->runData['request']->get : [];
        $scopeDefault = $get['scope'] ?? 'microservice';
        $refDefault = (int)($get['ref_id'] ?? 0);
        $this->runData['data']['add_defaults'] = [
            's_scope' => $scopeDefault,
            's_ms_id' => $scopeDefault === 'microservice' ? $refDefault : null,
            's_route_id' => $scopeDefault === 'route' ? $refDefault : null,
            's_apiendpoint_id' => $scopeDefault === 'api' ? $refDefault : null,
        ];

        if (!empty($this->runData['request']->post['s_name'])) {
            $post = $this->runData['request']->post;
            $scope = $post['s_scope'] ?? 'microservice';
            $planData = [
                's_name' => $post['s_name'],
                's_description' => $post['s_description'] ?? '',
                's_scope' => $scope,
                's_ms_id' => $scope === 'microservice' ? ($post['s_ms_id'] ?? null) : null,
                's_route_id' => $scope === 'route' ? ($post['s_route_id'] ?? null) : null,
                's_apiendpoint_id' => $scope === 'api' ? ($post['s_apiendpoint_id'] ?? null) : null,
                's_auto' => $post['s_auto'] ?? 'N',
            ];
            $planData = $this->stripAudit($planData);
            try {
                $newId = $this->db->insert('s_test_plan', $planData);
                if ($newId) {
                    $this->runData['route']['alert'] = 'success';
                    $this->runData['route']['alert_message'] = 'Test plan created.';
                    $redirectUrl = $this->runData['route']['rad_admin_url'] . '/testplan/viewone/' . $newId;
                    header("Location: {$redirectUrl}");
                    exit;
                }
            } catch (\Throwable $e) {
                if ($this->errorHandler) {
                    $this->errorHandler->handleException($e);
                }
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Unable to create test plan.';
            }
        }

        return $this->runData;
    }

    public function additem() {
        $this->requireAdmin();
        $planId = (int)($this->runData['route']['pathparts'][3] ?? 0);
        if ($planId <= 0) {
            throw new \Exception('Invalid test plan reference', 404);
        }
        if (!empty($this->runData['request']->post['s_name'])) {
            $post = $this->runData['request']->post;
            $itemData = [
                's_test_plan_id' => $planId,
                's_name' => $post['s_name'],
                's_description' => $post['s_description'] ?? '',
                's_type' => $post['s_type'] ?? 'manual',
                's_url' => $post['s_url'] ?? '',
                's_method' => $post['s_method'] ?? 'GET',
                's_payload' => $post['s_payload'] ?? '',
                's_expected' => $post['s_expected'] ?? '',
                's_order' => (int)($post['s_order'] ?? 0),
            ];
            $itemData = $this->stripAudit($itemData);
            try {
                $newId = $this->db->insert('s_test_item', $itemData);
                if ($newId) {
                    $redirectUrl = $this->runData['route']['rad_admin_url'] . '/testplan/viewone/' . $planId;
                    header("Location: {$redirectUrl}");
                    exit;
                }
            } catch (\Throwable $e) {
                if ($this->errorHandler) {
                    $this->errorHandler->handleException($e);
                }
            }
        }
        $this->runData['route']['alert'] = 'danger';
        $this->runData['route']['alert_message'] = 'Invalid test item data.';
        return $this->runData;
    }

    public function createtestrun() {
        $this->requireAdmin();
        $planId = (int)($this->runData['route']['pathparts'][3] ?? 0);
        if ($planId <= 0) {
            throw new \Exception('Invalid test plan reference', 404);
        }
        $planRows = $this->db->select('s_test_plan', ['id' => $planId], true);
        $plan = $planRows[0] ?? null;
        if (!$plan) {
            throw new \Exception('Test plan not found', 404);
        }

        $items = $this->db->select('s_test_item', ['s_test_plan_id' => $planId, 'livestatus' => '1'], true);
        if (empty($items)) {
            // Auto-generate items for auto plans when none exist
            $scope = $plan['s_scope'] ?? '';
            $refId = $scope === 'microservice' ? ($plan['s_ms_id'] ?? 0)
                : ($scope === 'route' ? ($plan['s_route_id'] ?? 0) : ($plan['s_apiendpoint_id'] ?? 0));
            $source = $this->fetchScopeSource($scope, (int)$refId);
            if ($source) {
                $this->populateAutoItems($planId, $scope, (int)$refId, $source);
                $items = $this->db->select('s_test_item', ['s_test_plan_id' => $planId, 'livestatus' => '1'], true);
            }
            // If still empty, seed one manual placeholder so the run has rows
            if (empty($items)) {
                $placeholder = [
                    's_test_plan_id' => $planId,
                    's_name' => 'Manual verification',
                    's_description' => 'Record at least one outcome for this plan.',
                    's_type' => 'manual',
                    's_url' => '',
                    's_method' => 'GET',
                    's_payload' => '',
                    's_expected' => '',
                    's_order' => 10,
                ];
                $this->db->insert('s_test_item', $placeholder);
                $items = $this->db->select('s_test_item', ['s_test_plan_id' => $planId, 'livestatus' => '1'], true);
            }
        }
        $now = date('Y-m-d H:i:s');
        $actorId = $this->runData['entity']['id'] ?? 1;
        $uid = $this->makeUuid();
        try {
            $sql = "INSERT INTO s_test_run (uid, s_test_plan_id, s_status, s_notes, s_started_at, s_completed_at, runby) VALUES (:uid, :plan, 'pending', '', NULL, NULL, :runby)";
            $this->db->query($sql, [':uid' => $uid, ':plan' => $planId, ':runby' => $actorId]);
            $runIdRows = $this->db->query("SELECT LAST_INSERT_ID() AS id", []);
            $runId = (int)($runIdRows[0]['id'] ?? 0);
            if ($runId > 0 && is_array($items)) {
                foreach ($items as $item) {
                    $this->db->query(
                        "INSERT INTO s_test_result (s_test_run_id, s_test_item_id, s_status, s_comment, s_duration_ms, s_evidence)
                         VALUES (:run_id, :item_id, 'not_run', '', NULL, '')",
                        [':run_id' => $runId, ':item_id' => $item['id']]
                    );
                }
            }
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/testrun/viewone/' . $runId;
            header("Location: {$redirectUrl}");
            exit;
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
            throw $e;
        }
    }

    public function viewone() {
        $id = (int)($this->runData['route']['pathparts'][3] ?? 0);
        if ($id <= 0) {
            throw new \Exception('Invalid test plan reference', 404);
        }
        $this->runData['route']['h1'] = 'Test Plan';
        $this->runData['route']['meta_title'] = 'Test Plan';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Test Plans' => $this->runData['route']['rad_admin_url'] . '/testplan/view',
            'Test Plan' => ''
        ];

        $maps = $this->loadScopeMaps();

        $plan = null;
        $items = [];
        $runs = [];

        try {
            $planRows = $this->db->query("SELECT * FROM s_test_plan WHERE id = :id LIMIT 1", [':id' => $id]);
            $plan = $planRows[0] ?? null;
            if ($plan) {
                $items = $this->db->query("SELECT * FROM s_test_item WHERE s_test_plan_id = :pid AND livestatus <> '0' ORDER BY s_order ASC, id ASC", [':pid' => $id]);
                $runs = $this->db->query(trim("
                    SELECT r.*,
                        (SELECT COUNT(*) FROM s_test_result res WHERE res.s_test_run_id = r.id AND res.s_status = 'passed') AS passed_count,
                        (SELECT COUNT(*) FROM s_test_result res WHERE res.s_test_run_id = r.id AND res.s_status = 'failed') AS failed_count,
                        (SELECT COUNT(*) FROM s_test_result res WHERE res.s_test_run_id = r.id AND res.s_status = 'blocked') AS blocked_count
                    FROM s_test_run r
                    WHERE r.s_test_plan_id = :pid AND r.livestatus <> '0'
                    ORDER BY r.createstamp DESC
                    LIMIT 50
                "), [':pid' => $id]);
            }
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
        }

        if (!$plan) {
            throw new \Exception('Test plan not found', 404);
        }

        $this->runData['data']['plan'] = $plan;
        $this->runData['data']['items'] = is_array($items) ? $items : [];
        $this->runData['data']['runs'] = is_array($runs) ? $runs : [];
        $this->runData['data']['scope_maps'] = $maps;
        return $this->runData;
    }

    public function generate() {
        $post = isset($this->runData['request']->post) ? $this->runData['request']->post : [];
        $get = isset($this->runData['request']->get) ? $this->runData['request']->get : [];
        $input = array_merge($post ?? [], $get ?? []);
        $targetKey = $input['target_key'] ?? '';
        $scope = $input['scope'] ?? '';
        $refId = (int)($input['ref_id'] ?? 0);
        if ($targetKey && strpos($targetKey, ':') !== false) {
            [$scope, $ref] = explode(':', $targetKey, 2);
            $refId = (int)$ref;
        }
        $scope = strtolower($scope);
        if (!in_array($scope, ['microservice', 'route', 'api'], true) || $refId <= 0) {
            throw new \Exception('Invalid scope to generate test plan.', 400);
        }

        $source = $this->fetchScopeSource($scope, $refId);
        if (!$source) {
            throw new \Exception('Referenced object not found for test plan generation.', 404);
        }

        $planId = $this->findExistingAutoPlan($scope, $refId);
        if (!$planId) {
            $planId = $this->createAutoPlan($scope, $refId, $source, $input);
        }

        $createdItems = $this->populateAutoItems($planId, $scope, $refId, $source);
        $message = $createdItems > 0 ? "Auto plan ready with {$createdItems} item(s)." : 'Auto plan refreshed.';
        if (method_exists($this->runData['request'], 'setAlert')) {
            $this->runData['request']->setAlert($message, 'success');
        }
        $redirectUrl = $this->runData['route']['rad_admin_url'] . '/testplan/viewone/' . $planId;
        header("Location: {$redirectUrl}");
        exit;
    }

    private function fetchScopeSource(string $scope, int $refId): ?array {
        try {
            if ($scope === 'microservice') {
                $rows = $this->db->select('s_ms', ['id' => $refId], true);
                return $rows[0] ?? null;
            }
            if ($scope === 'route') {
                $rows = $this->db->select('s_msroute', ['id' => $refId], true);
                return $rows[0] ?? null;
            }
            if ($scope === 'api') {
                $rows = $this->db->select('s_api_endpoint', ['id' => $refId], true);
                return $rows[0] ?? null;
            }
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
        }
        return null;
    }

    private function findExistingAutoPlan(string $scope, int $refId): ?int {
        $where = "s_scope = :scope AND s_auto = 'Y' AND livestatus <> '0'";
        $params = [':scope' => $scope];
        if ($scope === 'microservice') {
            $where .= " AND s_ms_id = :ref";
        } elseif ($scope === 'route') {
            $where .= " AND s_route_id = :ref";
        } else {
            $where .= " AND s_apiendpoint_id = :ref";
        }
        $params[':ref'] = $refId;
        try {
            $rows = $this->db->query("SELECT id FROM s_test_plan WHERE {$where} ORDER BY updatestamp DESC LIMIT 1", $params);
            if (!empty($rows[0]['id'])) {
                return (int)$rows[0]['id'];
            }
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
        }
        return null;
    }

    private function createAutoPlan(string $scope, int $refId, array $source, array $input): int {
        $name = trim($input['override_name'] ?? '');
        $desc = trim($input['override_desc'] ?? '');
        if ($name === '') {
            if ($scope === 'microservice') {
                $name = 'Auto: ' . ($source['s_name'] ?? 'Microservice') . ' routes';
            } elseif ($scope === 'route') {
                $name = 'Auto: Route ' . ($source['s_name'] ?? 'Route');
            } else {
                $name = 'Auto: API ' . ($source['s_slug'] ?? $source['s_name'] ?? 'Endpoint');
            }
        }
        if ($desc === '') {
            $desc = 'Auto-generated from RAD metadata for quick smoke coverage.';
        }

        $data = [
            's_name' => $name,
            's_description' => $desc,
            's_scope' => $scope,
            's_ms_id' => $scope === 'microservice' ? $refId : null,
            's_route_id' => $scope === 'route' ? $refId : null,
            's_apiendpoint_id' => $scope === 'api' ? $refId : null,
            's_auto' => 'Y',
        ];
        $data = $this->stripAudit($data);
        return (int)$this->db->insert('s_test_plan', $data);
    }

    private function populateAutoItems(int $planId, string $scope, int $refId, array $source): int {
        $created = 0;
        $baseUrl = rtrim($this->runData['config']['sys']['base_url'] ?? '', '/');
        if ($scope === 'microservice') {
            $routes = $this->db->select('s_msroute', ['s_ms_id' => $source['id'], 'livestatus' => '1'], true);
            $order = 10;
            foreach ($routes as $route) {
                $created += $this->ensureTestItem($planId, [
                    's_name' => 'Route: ' . ($route['s_name'] ?: $route['uid']),
                    's_description' => $route['s_description'] ?? '',
                    's_type' => 'manual',
                    's_url' => $this->buildRouteUrl($baseUrl, $source['s_name'], $route['s_name']),
                    's_method' => 'GET',
                    's_expected' => 'Expect HTTP 200 and page render.',
                    's_order' => $order,
                ]);
                $order += 10;
            }
        } elseif ($scope === 'route') {
            $msRows = $this->db->select('s_ms', ['id' => $source['s_ms_id']], true);
            $ms = $msRows[0] ?? ['s_name' => ''];
            $created += $this->ensureTestItem($planId, [
                's_name' => 'Route: ' . ($source['s_name'] ?? 'Route'),
                's_description' => $source['s_description'] ?? '',
                's_type' => 'manual',
                's_url' => $this->buildRouteUrl($baseUrl, $ms['s_name'] ?? '', $source['s_name'] ?? ''),
                's_method' => 'GET',
                's_expected' => 'Expect HTTP 200 and correct response.',
                's_order' => 10,
            ]);
        } else {
            $payload = [
                'api_type' => 'application',
                'endpoint' => $source['s_slug'] ?? $source['s_name'] ?? '',
                'params' => (object)[],
            ];
            $created += $this->ensureTestItem($planId, [
                's_name' => 'API: ' . ($source['s_name'] ?? $source['s_slug'] ?? 'Endpoint'),
                's_description' => $source['s_description'] ?? '',
                's_type' => 'manual',
                's_url' => $baseUrl . '/api/',
                's_method' => 'POST',
                's_payload' => json_encode($payload, JSON_PRETTY_PRINT),
                's_expected' => 'Expect HTTP 200 and JSON payload.',
                's_order' => 10,
            ]);
        }
        return $created;
    }

    private function ensureTestItem(int $planId, array $itemData): int {
        try {
            $exists = $this->db->select('s_test_item', [
                's_test_plan_id' => $planId,
                's_name' => $itemData['s_name']
            ], true);
            if (!empty($exists)) {
                return 0;
            }
            $data = array_merge([
                's_test_plan_id' => $planId,
                's_payload' => '',
            ], $itemData);
            $data = $this->stripAudit($data);
            $this->db->insert('s_test_item', $data);
            return 1;
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
            return 0;
        }
    }

    private function buildRouteUrl(string $baseUrl, string $msName, string $routeName): string {
        $msSegment = trim($msName ?? '', '/');
        $routeSegment = trim($routeName ?? '', '/');
        $path = $msSegment;
        if ($routeSegment !== '' && strtolower($routeSegment) !== 'default') {
            $path .= '/' . $routeSegment;
        }
        return rtrim($baseUrl, '/') . '/' . $path;
    }

    public function report() {
        $this->runData['route']['h1'] = 'Test Plan Reports';
        $this->runData['route']['meta_title'] = 'Test Plan Reports';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Test Plans' => $this->runData['route']['rad_admin_url'] . '/testplan/view',
            'Reports' => '',
        ];

        $statusCounts = [];
        $scopeCounts = [];
        $recentRuns = [];
        $trend = [];
        try {
            $statusCounts = $this->db->query("SELECT s_status, COUNT(*) AS total FROM s_test_run WHERE livestatus <> '0' GROUP BY s_status");
            $scopeCounts = $this->db->query(trim("
                SELECT p.s_scope, COUNT(*) AS total
                FROM s_test_run r
                JOIN s_test_plan p ON p.id = r.s_test_plan_id
                WHERE r.livestatus <> '0'
                GROUP BY p.s_scope
            "));
            $recentRuns = $this->db->query(trim("
                SELECT r.*, p.s_name AS plan_name, p.s_scope,
                    (SELECT COUNT(*) FROM s_test_result res WHERE res.s_test_run_id = r.id AND res.s_status = 'passed') AS passed_count,
                    (SELECT COUNT(*) FROM s_test_result res WHERE res.s_test_run_id = r.id AND res.s_status = 'failed') AS failed_count,
                    (SELECT COUNT(*) FROM s_test_result res WHERE res.s_test_run_id = r.id AND res.s_status = 'blocked') AS blocked_count
                FROM s_test_run r
                JOIN s_test_plan p ON p.id = r.s_test_plan_id
                WHERE r.livestatus <> '0'
                ORDER BY r.createstamp DESC
                LIMIT 50
            "));
            $trend = $this->db->query(trim("
                SELECT DATE(r.createstamp) AS d,
                    SUM(CASE WHEN res.s_status = 'passed' THEN 1 ELSE 0 END) AS passed,
                    SUM(CASE WHEN res.s_status = 'failed' THEN 1 ELSE 0 END) AS failed
                FROM s_test_run r
                LEFT JOIN s_test_result res ON res.s_test_run_id = r.id
                WHERE r.livestatus <> '0'
                GROUP BY DATE(r.createstamp)
                ORDER BY d DESC
                LIMIT 14
            "));
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
        }

        $this->runData['data']['status_counts'] = is_array($statusCounts) ? $statusCounts : [];
        $this->runData['data']['scope_counts'] = is_array($scopeCounts) ? $scopeCounts : [];
        $this->runData['data']['recent_runs'] = is_array($recentRuns) ? $recentRuns : [];
        $this->runData['data']['trend'] = is_array($trend) ? array_reverse($trend) : [];
        return $this->runData;
    }

    private function loadScopeMaps(): array {
        $maps = [
            'ms' => [],
            'route' => [],
            'api' => [],
        ];
        try {
            $msRows = $this->db->select('s_ms', [], true);
            foreach ($msRows as $row) {
                $maps['ms'][$row['id']] = $row['s_name'];
            }
            $routeRows = $this->db->select('s_msroute', [], true);
            foreach ($routeRows as $row) {
                $maps['route'][$row['id']] = $row['s_name'];
            }
            $apiRows = $this->db->select('s_api_endpoint', [], true);
            foreach ($apiRows as $row) {
                $maps['api'][$row['id']] = $row['s_name'] ?? ($row['s_path'] ?? '');
            }
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->handleException($e);
            }
        }
        return $maps;
    }

    private function stripAudit(array $data): array {
        foreach (['uid','livestatus','versioncode','wf_status','space_id','createdby','createstamp','updatedby','updatestamp','runby'] as $k) {
            if (array_key_exists($k, $data)) {
                unset($data[$k]);
            }
        }
        return $data;
    }

    private function makeUuid(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

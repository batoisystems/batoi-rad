<?php
namespace RadAdmin;
use Core\Sys\BranchService;
use DateTime;
use Core\Sys\PrivilegeService;
class Microservice{
    private $runData = [];
    private PrivilegeService $priv;
    private $db;
    private $errorHandler;
    private $zipTempDir;
    private $testplanClass;
    private $testHookHelper;
    private $aiService;
    private $ipAccessService;
    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        $this->priv = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
        try {
            $this->aiService = new \Core\Sys\AiService($runData['config'] ?? [], $this->errorHandler);
        } catch (\Throwable $e) {
            $this->aiService = null;
        }
        $this->ipAccessService = new \Core\Sys\IpAccessService();
        $this->zipTempDir = rtrim($runData['config']['dir']['data'] ?? sys_get_temp_dir(), '/') . '/temp';
        $tpPath = $runData['config']['dir']['admin'].'/classes/Testplan.cls.php';
        if (file_exists($tpPath) && !class_exists('\\RadAdmin\\Testplan', false)) {
            require_once $tpPath;
        }
        if (class_exists('\\RadAdmin\\Testplan', false)) {
            $this->testplanClass = new \RadAdmin\Testplan($runData);
        }
        $hookPath = $runData['config']['dir']['admin'].'/classes/Testhookhelper.cls.php';
        if (file_exists($hookPath) && !class_exists('\\RadAdmin\\Testhookhelper', false)) {
            require_once $hookPath;
        }
        if (class_exists('\\RadAdmin\\Testhookhelper', false)) {
            $this->testHookHelper = new \RadAdmin\Testhookhelper($this->runData['db'], $this->errorHandler);
        }
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }
    public function view() {
        if (!$this->priv->can('api_manage') && !$this->priv->can('view')) {
            throw new \Exception('Access denied.', 403);
        }

        $perPageParam = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($this->isAllowedPerPage($perPageParam)) {
            $this->saveProfilePerPage($perPageParam);
        }
        $perPage = $this->isAllowedPerPage($perPageParam) ? $perPageParam : $this->getProfilePerPage(25);

        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'A microservicelet is a modular architectural component comprising a set of routes and controllers designed to fulfill a specific function within a larger application ecosystem. When the accessing entity is a user, the microservicelet is typically represented by a base route, which is the segment of the URL that follows immediately after the base URL. This base route acts as the gateway through which users interact with the functionalities provided by the microservicelet. Conversely, when the access is via an API, the microservicelet is identified by its unique name, which serves as a key identifier allowing for targeted interactions between different services within a microservicelet-based architecture. This dual mode of identification ensures a seamless and flexible approach to accessing and managing services, whether by direct user interaction or programmatically via APIs.';
        }

        $this->runData['route']['h1'] = 'Microservicelet';
        $this->runData['route']['meta_title'] = 'Microservicelet';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => '',
        ];
        $filters = [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
            'scope' => trim((string)($this->runData['request']->get['scope'] ?? '')),
            'status' => trim((string)($this->runData['request']->get['status'] ?? '')),
        ];
        // Select Microservice from s_ms table
        $msList = $this->runData['db']->select('s_ms', [], true);
        $msList = $this->filterRestricted($msList);
        $msList = array_values(array_filter($msList, function ($row) use ($filters) {
            if ($filters['scope'] !== '' && strcasecmp($row['s_scope'] ?? '', $filters['scope']) !== 0) {
                return false;
            }
            if ($filters['status'] !== '' && (string)($row['livestatus'] ?? '') !== $filters['status']) {
                return false;
            }
            if ($filters['q'] !== '') {
                $needle = strtolower($filters['q']);
                $blob = strtolower(($row['s_name'] ?? '') . ' ' . ($row['uid'] ?? '') . ' ' . ($row['s_description'] ?? ''));
                if (strpos($blob, $needle) === false) {
                    return false;
                }
            }
            return true;
        }));
        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['ms'] = $msList;
        $this->runData['data']['test_hooks'] = []; // list view doesn’t need hooks
        $this->runData['data']['per_page_pref'] = $perPage;
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
        $this->db->update('s_entity', [
            's_definition' => json_encode($definition, JSON_UNESCAPED_SLASHES),
        ], ['id' => $entityId]);
    }

    private function loadEntityDefinition(): array {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return [];
        }
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
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

    private function sanitizeRouteName(string $name): string {
        $name = strtolower($name);
        $name = str_replace(' ', '-', $name);
        return preg_replace('/[^A-Za-z0-9\-]/', '', $name);
    }

    private function buildMicroserviceIpAccessPayload(string $definitionJson, string $scope, string $type): array {
        $decoded = json_decode($definitionJson, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        if (strtoupper($type) !== 'DYN' || strtolower($scope) !== 'platform') {
            unset($decoded['ip_access']);
            return [
                'json' => json_encode($decoded, JSON_UNESCAPED_SLASHES),
                'rule' => ['enabled' => false, 'ips' => [], 'invalid' => [], 'raw' => ''],
            ];
        }
        $merged = $this->ipAccessService->mergeRuleIntoDefinition(
            $decoded,
            !empty($this->runData['request']->post['ip_access_enabled']),
            $this->runData['request']->post['ip_access_ips'] ?? ''
        );
        return [
            'json' => json_encode($merged['definition'], JSON_UNESCAPED_SLASHES),
            'rule' => $merged['rule'],
        ];
    }

    private function loadMicroserviceIpAccessRule(array $ms): array {
        return $this->ipAccessService->extractRuleFromDefinition($ms['s_definition'] ?? []);
    }

    private function createControllerRecord(array $ms, string $name, string $type, string $description, array &$warnings, array $meta = []): int {
        $sanitizedName = $this->sanitizeControllerName($name);
        if ($sanitizedName === '') {
            $warnings[] = 'Skipped controller with empty name.';
            return 0;
        }
        $exists = $this->runData['db']->select('s_mscontroller', ['s_name' => $sanitizedName, 's_ms_id' => $ms['id']], true);
        if (!empty($exists)) {
            $warnings[] = 'Skipped controller ' . $sanitizedName . ' (already exists).';
            return 0;
        }
        $newControllerId = $this->runData['db']->insert('s_mscontroller', [
            's_ms_id' => $ms['id'],
            's_name' => $sanitizedName,
            's_source_file' => ($type === 'BL') ? ($meta['source_file'] ?? null) : null,
            's_class_name' => ($type === 'BL') ? ($meta['class_name'] ?? null) : null,
            's_description' => $description,
            's_type' => $type,
        ]);
        if (!$newControllerId) {
            $warnings[] = 'Failed to create controller ' . $sanitizedName . '.';
            return 0;
        }

        if ($type === 'BL') {
            $createSkeleton = array_key_exists('create_skeleton', $meta) ? !empty($meta['create_skeleton']) : true;
            if ($createSkeleton) {
                $this->createBlSkeleton(
                    $ms['s_name'],
                    $sanitizedName,
                    (string)($meta['source_file'] ?? ''),
                    (string)($meta['class_name'] ?? '')
                );
            }
        } else {
            try {
                $this->createDataModelTable($sanitizedName, []);
            } catch (\Throwable $e) {
                $warnings[] = 'Data model table a_' . $sanitizedName . ' could not be created: ' . $e->getMessage();
            }
        }

        return (int)$newControllerId;
    }

    private function createContentBlock(int $msId, string $title, string $slug, string $type, string $body, string $summary, array &$warnings): int {
        $title = trim($title);
        if ($title === '') {
            $warnings[] = 'Skipped content block with empty title.';
            return 0;
        }
        $slug = $slug !== '' ? $slug : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $warnings[] = 'Skipped content block with empty slug for ' . $title . '.';
            return 0;
        }
        $slugExists = $this->runData['db']->select('s_content', ['s_slug' => $slug], true);
        if (!empty($slugExists)) {
            $warnings[] = 'Skipped content block ' . $title . ' (slug already exists).';
            return 0;
        }
        $type = strtoupper($type ?: 'C');
        $metaTitle = $title;
        $metaDescription = $summary !== '' ? $summary : $title;
        $newContentId = $this->runData['db']->insert('s_content', [
            's_ms_id' => $msId,
            's_title' => $title,
            's_summary' => $summary,
            's_content' => $body,
            's_definition' => '{}',
            's_meta_title' => $metaTitle,
            's_meta_description' => $metaDescription,
            's_slug' => $slug,
            's_type' => $type,
        ]);
        if (!$newContentId) {
            $warnings[] = 'Failed to create content block ' . $title . '.';
            return 0;
        }
        return (int)$newContentId;
    }

    /**
     * AI-assisted Microservicelet wizard
     */
    public function aiwizard() {
        if (!$this->priv->can('microservice_add') || !$this->priv->can('api_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $request = $this->runData['request'];
        $planJson = $request->post['plan_json'] ?? '';
        $action = $request->post['action'] ?? '';

        if ($request->method === 'POST' && $action === 'generate') {
            $spec = trim($request->post['spec'] ?? '');
            $responseSize = strtolower(trim((string)($request->post['response_size'] ?? '')));
            if ($spec === '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Enter a natural language spec before generating.';
            } elseif (!$this->aiService) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'AI service is unavailable. Configure AI settings first.';
            } else {
                try {
                    $maxTokens = $this->resolveAiWizardMaxTokens($responseSize);
                    $plan = $this->generateAiPlan($spec, $maxTokens);
                    $planJson = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    $this->runData['data']['ai_plan'] = $plan;
                    $this->runData['route']['alert'] = 'success';
                    $this->runData['route']['alert_message'] = 'AI proposal generated. Review and refine before applying.';
                } catch (\Throwable $e) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'AI generation failed: ' . $e->getMessage();
                }
            }
        }

        if ($request->method === 'POST' && $action === 'apply') {
            $planJson = html_entity_decode($request->post['plan_json'] ?? '', ENT_QUOTES);
            $plan = json_decode($planJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($plan)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid plan JSON. Please regenerate or correct it.';
            } else {
                try {
                    $result = $this->applyPlan($plan);
                    $this->runData['route']['alert'] = 'success';
                    $this->runData['route']['alert_message'] = 'Microservicelet created successfully.';
                    $this->runData['data']['applied'] = $result;
                } catch (\Throwable $e) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Apply failed: ' . $e->getMessage();
                }
            }
        }

        $this->runData['data']['plan_json'] = $planJson;
        $this->runData['data']['navsets'] = $this->loadNavsets();
        $this->runData['route']['h1'] = 'AI Microservicelet Wizard';
        $this->runData['route']['meta_title'] = 'AI Microservicelet Wizard';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelet' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            'AI Wizard' => '',
        ];
        return $this->runData;
    }
    
    /**
     * Add a Microservice
     */
    public function add() {
        if (!$this->priv->can('microservice_add')) {
            throw new \Exception('Access denied.', 403);
        }
        // Add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'The following form is meant to add a Microservicelet.';
        $this->runData['route']['h1'] = 'Add Microservicelet';
        $this->runData['route']['meta_title'] = 'Add Microservicelet';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            'Add' => '',
        ];
    
        // Check if the form has been submitted from the same URL
        if (isset($this->runData['request']->post['s_name'])) {
            $this->runData['request']->post['s_type'] = 'DYN';
            // Ensure the Microservice name is provided
            if (empty(trim($this->runData['request']->post['s_name']))) {
                // Set an error message if the name is empty
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Microservicelet name is mandatory.';
                return $this->runData;
            }
    
            // Sanitize 's_name' to remove spaces, special characters, and convert to lowercase but keep forward slash (/)
            $this->runData['request']->post['s_name'] = strtolower($this->runData['request']->post['s_name']);
            $this->runData['request']->post['s_name'] = str_replace(' ', '-', $this->runData['request']->post['s_name']);
            $this->runData['request']->post['s_name'] = preg_replace('/[^A-Za-z0-9\-\/]/', '', $this->runData['request']->post['s_name']);
    
            // Check if the s_name is unique (not duplicate)
            $ms = $this->runData['db']->select('s_ms', ['s_name' => $this->runData['request']->post['s_name']], true);
    
            if (empty($ms)) {
                // Validate the JSON for service definition
                $json_str = '{}';
                if (!empty($this->runData['request']->post['s_definition'])) {
                    $json_str = html_entity_decode($this->runData['request']->post['s_definition']);
                    $json_str = stripslashes($json_str);
                    $json = json_decode($json_str);
    
                    if (json_last_error() != JSON_ERROR_NONE) {
                        // Add alert and alert_message to runData - information to be displayed to the user
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Invalid JSON for Service Definition.';
                        return $this->runData;
                    }
                }
                // Insert the Microservice into the s_ms table
                $newMSId = $this->runData['db']->insert('s_ms', [
                    's_name' => $this->runData['request']->post['s_name'],
                    's_description' => $this->runData['request']->post['s_description'],
                    's_type' => $this->runData['request']->post['s_type'],
                    's_definition' => $json_str,
                    's_scope' => $this->runData['request']->post['s_scope'] ?? 'platform',
                    's_tpl_name' => $this->runData['request']->post['s_template']
                ]);
    
                if ($newMSId == 0) {
                    // Add alert and alert_message to runData - information to be displayed to the user
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Microservicelet could not be added.';
                } else {
                    $msRows = $this->runData['db']->select('s_ms', ['id' => $newMSId], true);
                    $ms = $msRows[0] ?? ['id' => $newMSId, 's_name' => $this->runData['request']->post['s_name']];

                    $warnings = [];
                    $created = [
                        'routes' => 0,
                        'business_classes' => 0,
                        'data_models' => 0,
                        'content_blocks' => 0,
                    ];

                    $routeNames = $this->runData['request']->post['route_name'] ?? [];
                    $routeDescriptions = $this->runData['request']->post['route_description'] ?? [];
                    $routeIds = [];
                    if (is_array($routeNames)) {
                        foreach ($routeNames as $idx => $name) {
                            $name = $this->sanitizeRouteName(trim((string)$name));
                            if ($name === '') {
                                continue;
                            }
                            $desc = trim((string)($routeDescriptions[$idx] ?? ''));
                            $existing = $this->runData['db']->select('s_msroute', ['s_ms_id' => $newMSId, 's_name' => $name], true);
                            if (!empty($existing)) {
                                $warnings[] = 'Skipped route ' . $name . ' (already exists).';
                                continue;
                            }
                            $routeId = $this->runData['db']->insert('s_msroute', [
                                's_ms_id' => $newMSId,
                                's_name' => $name,
                                's_description' => $desc,
                            ]);
                            if ($routeId) {
                                $routeIds[] = (int)$routeId;
                                $created['routes']++;
                                $routeKey = $this->getRouteFileKey(
                                    ['id' => $routeId, 's_name' => $name],
                                    $ms['s_type'] ?? 'STA'
                                );
                                $this->ensureRouteFiles($ms['s_name'], $routeKey);
                                $this->inheritMsBindingsToRoute($newMSId, (int)$routeId);
                            } else {
                                $warnings[] = 'Failed to create route ' . $name . '.';
                            }
                        }
                    }

                    if (empty($routeIds)) {
                        $defaultRouteId = $this->runData['db']->insert('s_msroute', [
                            's_ms_id' => $newMSId,
                            's_name' => 'default',
                            's_description' => 'Default Route',
                        ]);
                        if ($defaultRouteId) {
                            $routeIds[] = (int)$defaultRouteId;
                            $created['routes']++;
                            $routeKey = $this->getRouteFileKey(
                                ['id' => $defaultRouteId, 's_name' => 'default'],
                                $ms['s_type'] ?? 'STA'
                            );
                            $this->ensureRouteFiles($ms['s_name'], $routeKey);
                            $this->inheritMsBindingsToRoute($newMSId, (int)$defaultRouteId);
                        } else {
                            $warnings[] = 'Default Route could not be added. You need to add it manually.';
                        }
                    }

                    if (!empty($routeIds)) {
                        $this->runData['db']->update('s_ms', ['s_default_route_id' => $routeIds[0]], ['id' => $newMSId]);
                    }

                    $blNames = $this->runData['request']->post['bl_name'] ?? [];
                    $blDescriptions = $this->runData['request']->post['bl_description'] ?? [];
                    if (is_array($blNames)) {
                        foreach ($blNames as $idx => $name) {
                            $desc = trim((string)($blDescriptions[$idx] ?? ''));
                            if ($this->createControllerRecord($ms, (string)$name, 'BL', $desc, $warnings)) {
                                $created['business_classes']++;
                            }
                        }
                    }

                    $dmNames = $this->runData['request']->post['dm_name'] ?? [];
                    $dmDescriptions = $this->runData['request']->post['dm_description'] ?? [];
                    if (is_array($dmNames)) {
                        foreach ($dmNames as $idx => $name) {
                            $desc = trim((string)($dmDescriptions[$idx] ?? ''));
                            if ($this->createControllerRecord($ms, (string)$name, 'DM', $desc, $warnings)) {
                                $created['data_models']++;
                            }
                        }
                    }

                    $contentTitles = $this->runData['request']->post['content_title'] ?? [];
                    if (($this->runData['request']->post['s_type'] ?? '') === 'STA' && is_array($contentTitles)) {
                        $contentSlugs = $this->runData['request']->post['content_slug'] ?? [];
                        $contentTypes = $this->runData['request']->post['content_type'] ?? [];
                        $contentBodies = $this->runData['request']->post['content_body'] ?? [];
                        $contentSummaries = $this->runData['request']->post['content_summary'] ?? [];
                        foreach ($contentTitles as $idx => $title) {
                            $slug = trim((string)($contentSlugs[$idx] ?? ''));
                            $type = trim((string)($contentTypes[$idx] ?? 'C'));
                            $body = trim((string)($contentBodies[$idx] ?? ''));
                            $summary = trim((string)($contentSummaries[$idx] ?? ''));
                            if ($this->createContentBlock($newMSId, (string)$title, $slug, $type, $body, $summary, $warnings)) {
                                $created['content_blocks']++;
                            }
                        }
                    } elseif (!empty($contentTitles)) {
                        $warnings[] = 'Content Blocks were skipped (only available for Static microservicelets).';
                    }

                    $messageParts = [
                        'Microservicelet added successfully.',
                        sprintf('Routes: %d', $created['routes']),
                        sprintf('Business Classes: %d', $created['business_classes']),
                        sprintf('Data Models: %d', $created['data_models']),
                    ];
                    if (!empty($warnings)) {
                        $messageParts[] = 'Warnings: ' . implode(' ', $warnings);
                    }

                    $this->runData['route']['alert'] = 'success';
                    $this->runData['route']['alert_message'] = implode(' ', $messageParts);
                    $this->logMicroserviceActivity('create', (int)$newMSId, $this->runData['request']->post['s_name'], $this->runData['request']->post['s_description'] ?? '', $this->runData['request']->post['s_type'] ?? '');
                    $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                    $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/microservice/view';
                    header("Location: {$redirectUrl}");
                    exit;
                }
            } else {
                // Add alert and alert_message to runData - information to be displayed to the user
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Microservicelet already exists.';
            }
        }
        $this->runData['data']['ip_access_rule'] = ['enabled' => false, 'ips' => [], 'invalid' => [], 'raw' => ''];
        return $this->runData;
    }

    /**
     * Edit a Microservice
     */
    public function edit() {
        if (!$this->priv->can('microservice_edit')) {
            throw new \Exception('Access denied.', 403);
        }
        // Check if the form has been submitted from the same URL
        if (isset($this->runData['request']->post['ms_id'])) {
            // Sanitize 's_name' to remove spaces, special characters, and convert to lowercase but keep forward slash (/)
            $this->runData['request']->post['s_name'] = strtolower($this->runData['request']->post['s_name']);
            $this->runData['request']->post['s_name'] = str_replace(' ', '-', $this->runData['request']->post['s_name']);
            $this->runData['request']->post['s_name'] = preg_replace('/[^A-Za-z0-9\-\/]/', '', $this->runData['request']->post['s_name']);
            
            $msId = (int)$this->runData['request']->post['ms_id'];
            $currentMsRows = $this->runData['db']->select('s_ms', ['id' => $msId], true);
            if (count($currentMsRows) != 1) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid Microservicelet reference.';
                return $this->runData;
            }
            $currentMs = $currentMsRows[0];

            // Check if the sanitized name conflicts with another microservice
            $existingByName = $this->runData['db']->select('s_ms', ['s_name' => $this->runData['request']->post['s_name']], true);
            if (!empty($existingByName) && (int)$existingByName[0]['id'] !== $msId) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Microservicelet already exists.';
            } else {
                // Validate the JSON for service definition
                $json_str = '{}';
                if (!empty($this->runData['request']->post['s_definition'])) {
                    $json_str = html_entity_decode($this->runData['request']->post['s_definition']);
                    $json_str = stripslashes($json_str);
                    $json = json_decode($json_str);
    
                    if (json_last_error() != JSON_ERROR_NONE) {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Invalid JSON for Service Definition.';
                        return $this->runData;
                    }
                }
                // print '<pre>';print $json_str;print '</pre>';die('here');
    
                // Update the microservice in the s_ms table
                if ($this->runData['route']['alert'] != 'danger') {
                    // Check if s_template is set
                    $s_template = isset($this->runData['request']->post['s_tpl_name']) ? $this->runData['request']->post['s_tpl_name'] : '';
                    // print '<pre>';print $s_template;print '</pre>';die('here');
    
                    // print '<pre>';print_r($this->runData['request']->post);print '</pre>';die('here');
                    $currentMsName = $currentMs['s_name'];
                    $newMsName = $this->runData['request']->post['s_name'];

                    if ($currentMsName !== $newMsName && !$this->priv->can('rename')) {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Renaming microservicelets is restricted.';
                        return $this->runData;
                    }

                    $updateResult = $this->runData['db']->update('s_ms', [
                        's_name' => $this->runData['request']->post['s_name'],
                        's_description' => $this->runData['request']->post['s_description'],
                        's_type' => $this->runData['request']->post['s_type'],
                        's_definition' => $json_str,
                        's_scope' => $this->runData['request']->post['s_scope'] ?? 'platform',
                        's_tpl_name' => $s_template
                    ], ['id' => $this->runData['request']->post['ms_id']]);
             
                    if ($updateResult) {
                        $renameStatus = ['status' => true, 'message' => null];
                        if ($currentMsName !== $newMsName) {
                            $renameStatus = $this->renameMicroserviceFolder($currentMsName, $newMsName);
                        }

                        $folderIntegrityMessage = null;
                        if ($renameStatus['status']) {
                            $folderIntegrity = $this->ensureMicroserviceFolder(array_merge($currentMs, [
                                's_name' => $newMsName
                            ]), true);
                            if (!$folderIntegrity['status']) {
                                $renameStatus = ['status' => false, 'message' => $folderIntegrity['message']];
                            } else {
                                $folderIntegrityMessage = $folderIntegrity['message'];
                            }
                        }

                        if ($renameStatus['status']) {
                            $this->runData['route']['alert'] = 'success';
                            $message = 'The Microservicelet <strong>' . $newMsName . '</strong> has been updated successfully.';
                            if ($folderIntegrityMessage) {
                                $message .= ' ' . $folderIntegrityMessage;
                            }
                            $this->runData['route']['alert_message'] = $message;
                            $this->logMicroserviceActivity('update', (int)$this->runData['request']->post['ms_id'], $this->runData['request']->post['s_name'], $this->runData['request']->post['s_description'] ?? '', $this->runData['request']->post['s_type'] ?? '');
                        } else {
                            $this->runData['route']['alert'] = 'warning';
                            $this->runData['route']['alert_message'] = 'Microservicelet updated but filesystem folder could not be prepared: ' . $renameStatus['message'];
                        }

                        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                        
                        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/microservice/view/' . $currentMs['uid'];
                        header("Location: {$redirectUrl}");
                        exit;
                    } else {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Failed to update the Microservicelet. Please try again.';
                    }
                }
            }
        }
    
        // Load the Microservice details
        $msRow = $this->runData['db']->select('s_ms', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (empty($msRow) || count($msRow) != 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        if ($this->isRestrictedMs((int)$msRow[0]['id'])) {
            throw new \Exception('Access denied', 404);
        }
        $this->runData['data']['ms'] = $msRow[0];
        $this->runData['data']['ip_access_rule'] = $this->loadMicroserviceIpAccessRule($msRow[0]);
        $this->runData['route']['h1'] = 'Edit Microservicelet ' . $msRow[0]['s_name'];
        $this->runData['route']['meta_title'] = $this->runData['route']['h1'];
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            $msRow[0]['s_name'] => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $msRow[0]['uid'],
            'Edit' => '',
        ];
        $folderCheck = $this->ensureMicroserviceFolder($this->runData['data']['ms']);
        if (!$folderCheck['status']) {
            $this->runData['route']['alert'] = 'warning';
            $this->runData['route']['alert_message'] = $folderCheck['message'];
        } elseif (!empty($folderCheck['message'])) {
            if (!isset($this->runData['route']['alert']) || $this->runData['route']['alert'] !== 'danger') {
                $existingMessage = $this->runData['route']['alert_message'] ?? '';
                $combinedMessage = trim($existingMessage . ' ' . $folderCheck['message']);
                $this->runData['route']['alert'] = 'warning';
                $this->runData['route']['alert_message'] = $combinedMessage;
            }
        }
        // print '<pre>';print_r($this->runData['data']['ms']);print '</pre>';die('here');
        // Load roles by scope if needed
        $allRoles = $this->runData['db']->select('s_role', [], true);
        $nonSaasRoles = array_filter($allRoles, function($role) {
            return ($role['s_scope'] ?? 'platform') === 'platform';
        });
    
        // Set default alert message
        if (!isset($this->runData['route']['alert'])) {
            $this->runData['route']['alert'] = 'info';
        }
        return $this->runData;
    }                      

    public function ipaccess() {
        if (!$this->priv->can('microservice_edit')) {
            throw new \Exception('Access denied.', 403);
        }
        $uid = $this->runData['route']['pathparts'][3] ?? '';
        if ($uid === '') {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $msRows = $this->runData['db']->select('s_ms', ['uid' => $uid], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $ms = $msRows[0];
        if ($this->isRestrictedMs((int)$ms['id'])) {
            throw new \Exception('Access denied', 404);
        }

        if ($this->runData['request']->method === 'POST') {
            $definitionRaw = (string)($ms['s_definition'] ?? '{}');
            if ($definitionRaw === '') {
                $definitionRaw = '{}';
            }
            $scope = (string)($ms['s_scope'] ?? 'platform');
            $type = (string)($ms['s_type'] ?? 'DYN');
            $ipPayload = $this->buildMicroserviceIpAccessPayload($definitionRaw, $scope, $type);
            if (!empty($ipPayload['rule']['invalid'])) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid IP entries: ' . implode(', ', $ipPayload['rule']['invalid']);
            } elseif (!empty($this->runData['request']->post['ip_access_enabled']) && empty($ipPayload['rule']['ips']) && strtolower($scope) === 'platform' && strtoupper($type) === 'DYN') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Add at least one valid IP before enabling platform microservicelet restriction.';
            } else {
                $this->runData['db']->update('s_ms', [
                    's_definition' => $ipPayload['json'],
                ], ['id' => (int)$ms['id']]);
                $this->runData['request']->setAlert('IP restriction settings updated.', 'success');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/ipaccess/' . $ms['uid']);
                exit;
            }
        }

        $freshRows = $this->runData['db']->select('s_ms', ['uid' => $uid], true);
        $ms = $freshRows[0] ?? $ms;
        $this->runData['data']['ms'] = $ms;
        $this->runData['data']['ip_access_rule'] = $this->loadMicroserviceIpAccessRule($ms);
        $this->runData['route']['h1'] = 'Microservicelet IP Restriction ' . $ms['s_name'];
        $this->runData['route']['meta_title'] = $this->runData['route']['h1'];
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            $ms['s_name'] => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid'],
            'IP Restriction' => '',
        ];
        return $this->runData;
    }

    /**
     * View details of a Microservicelet
     */
    public function detail() {
        if (!isset($this->runData['route']['pathparts'][3]) || $this->runData['route']['pathparts'][3] == '') {
            throw new \Exception('Invalid Microservicelet', 404);
        }

        $msRows = $this->runData['db']->select('s_ms', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($msRows) != 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $ms = $msRows[0];
        if ($this->isRestrictedMs((int)$ms['id'])) {
            throw new \Exception('Access denied', 404);
        }

        $defaultRoute = null;
        if (!empty($ms['s_default_route_id'])) {
            $defaultRouteRows = $this->runData['db']->select('s_msroute', ['id' => $ms['s_default_route_id']], true);
            if (!empty($defaultRouteRows)) {
                $defaultRoute = $defaultRouteRows[0];
            }
        }

        $routeStats = $this->runData['db']->query(
            "SELECT COUNT(*) AS total FROM s_msroute WHERE s_ms_id = :ms_id",
            [':ms_id' => $ms['id']]
        );
        $controllerStats = $this->runData['db']->query(
            "SELECT COUNT(*) AS total FROM s_mscontroller WHERE s_ms_id = :ms_id",
            [':ms_id' => $ms['id']]
        );

        $recentRoutes = $this->runData['db']->select(
            's_msroute',
            ['s_ms_id' => $ms['id']],
            true,
            ['id' => 'DESC'],
            5
        );
        $recentControllers = $this->runData['db']->select(
            's_mscontroller',
            ['s_ms_id' => $ms['id']],
            true,
            ['id' => 'DESC'],
            5
        );

        $hasBindings = $this->runData['permissionService']->hasBindings('ms', (int)$ms['id']);
        $allowedRoleScopes = $this->allowedBindingRoleScopesForMicroservice($ms);
        $bindingRoles = $this->fetchBindingRoles('ms', (int)$ms['id']);
        $bindingRoles = $this->filterBindingRolesByAllowedScopes($bindingRoles, $allowedRoleScopes);
        $bindingRoleGroups = $this->groupBindingRolesByScope($bindingRoles);
        $branchService = new BranchService($this->runData['db'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [], $this->runData['request'] ?? null);
        $betaCount = 0;
        try {
            $betaCountRows = $this->runData['db']->query(
                "SELECT COUNT(*) AS total
                 FROM s_branch
                 WHERE s_object_type = 'route' AND s_branch = 'beta' AND s_status = 'active'
                   AND s_object_id IN (SELECT id FROM s_msroute WHERE s_ms_id = :msid)",
                [':msid' => (int)$ms['id']]
            );
            $betaCount = (int)($betaCountRows[0]['total'] ?? 0);
        } catch (\Throwable $e) {
            $betaCount = 0;
        }

        // App linkage (if any) — skip gracefully if s_app_ms is absent
        $appLink = [];
        try {
            $appLink = $this->runData['db']->query(
                "SELECT a.id, a.uid, a.s_name
                 FROM s_app_ms am
                 LEFT JOIN s_app a ON a.id = am.s_app_id
                 WHERE am.s_ms_id = :msid
                 ORDER BY a.id DESC
                 LIMIT 1",
                [':msid' => (int)$ms['id']]
            );
        } catch (\Throwable $e) {
            // s_app_ms may be removed; ignore linkage if table not found.
            $appLink = [];
        }
        $this->runData['data']['app_link'] = $appLink[0] ?? null;

        $this->runData['data']['ms'] = $ms;
        $this->runData['data']['ip_access_rule'] = $this->loadMicroserviceIpAccessRule($ms);
        $this->runData['data']['default_route'] = $defaultRoute;
        $this->runData['data']['stats'] = [
            'routes' => (int)($routeStats[0]['total'] ?? 0),
            'controllers' => (int)($controllerStats[0]['total'] ?? 0),
        ];
        $this->runData['data']['recent_routes'] = $recentRoutes;
        $this->runData['data']['recent_controllers'] = $recentControllers;
        $this->runData['data']['has_bindings'] = $hasBindings;
        $this->runData['data']['permission_binding_roles'] = $bindingRoles;
        $this->runData['data']['permission_binding_role_groups'] = $bindingRoleGroups;
        $this->runData['data']['allowed_binding_role_scopes'] = $allowedRoleScopes;
        $this->runData['data']['branch_counts'] = [
            'beta_routes' => $betaCount,
        ];
        $this->runData['data']['branch_can_manage'] = $branchService->canUseBeta();
        $this->runData['data']['branch_can_merge'] = $branchService->canMerge();
        // Test hooks for this microservice
        $this->runData['data']['test_hooks'] = $this->testHookHelper ? $this->testHookHelper->fetchForMicroservice((int)$ms['id']) : [];
        $this->runData['data']['filesystem_audit'] = $this->inspectMicroserviceFilesystem($ms);
        $detailPriv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        $this->runData['data']['can_register_class_files'] = $detailPriv->can('controller_add');
        $this->runData['data']['can_cleanup_files'] = ((int)($this->runData['entity']['id'] ?? 0) === 1) && $detailPriv->can('microservice_edit');

        $this->runData['route']['h1'] = 'Microservicelet Details';
        $this->runData['route']['meta_title'] = 'Microservicelet: ' . $ms['s_name'];
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/microservice/view';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            $ms['s_name'] => '',
        ];

        return $this->runData;
    }

    public function registerclassfiles() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('controller_add')) {
            throw new \Exception('Access denied.', 403);
        }

        $ms = $this->resolveMicroserviceFromDetailPath();
        $this->assertValidCsrfOrRedirect($ms['uid']);
        $requestedFiles = $this->normalizePostedFileList($this->runData['request']->post['class_files'] ?? ($this->runData['request']->post['class_file'] ?? []));
        if (empty($requestedFiles)) {
            $this->runData['request']->setAlert('Select at least one class file to register.', 'warning');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid']);
            exit;
        }

        $audit = $this->inspectMicroserviceFilesystem($ms);
        $candidateMap = [];
        foreach ($audit['unregistered_class_files'] as $candidate) {
            $candidateMap[$candidate['file']] = $candidate;
        }

        $created = [];
        $reconciled = [];
        $warnings = [];
        foreach ($requestedFiles as $file) {
            if (!isset($candidateMap[$file])) {
                $warnings[] = $file . ' is no longer available for registration.';
                continue;
            }
            $candidate = $candidateMap[$file];
            $requestedKey = trim((string)($this->runData['request']->post['controller_name_map'][$file] ?? ($candidate['controller_name'] ?? '')));
            $requestedClassName = trim((string)($this->runData['request']->post['class_name_map'][$file] ?? ($candidate['source_name'] ?? '')));
            $requestedSourceFile = trim((string)($this->runData['request']->post['source_file_map'][$file] ?? ($candidate['file'] ?? '')));

            $controllerName = $this->sanitizeControllerName($requestedKey);
            $className = $this->sanitizePhpClassName($requestedClassName);
            $sourceFile = $this->normalizeBusinessClassFileName($requestedSourceFile, $requestedClassName !== '' ? $requestedClassName : ($candidate['source_name'] ?? ''));

            if ($controllerName === '') {
                $warnings[] = $file . ' could not be mapped to a valid business class name.';
                continue;
            }
            if ($className === '') {
                $warnings[] = $file . ' could not be mapped to a valid PHP class name.';
                continue;
            }
            if ($sourceFile === '') {
                $warnings[] = $file . ' could not be mapped to a valid source filename.';
                continue;
            }
            if (!$this->prepareBusinessClassSourceFile($ms['s_name'] ?? '', (string)($candidate['file'] ?? ''), $sourceFile, $warnings)) {
                continue;
            }
            $result = $this->createOrReconcileBusinessClassRecord($ms, $controllerName, '', [
                'source_file' => $sourceFile,
                'class_name' => $className,
                'create_skeleton' => false,
            ], $warnings);
            if (($result['status'] ?? '') === 'created') {
                $created[] = $controllerName;
            } elseif (($result['status'] ?? '') === 'reconciled') {
                $reconciled[] = $controllerName;
            }
        }

        $messageParts = [];
        if (!empty($created)) {
            $messageParts[] = 'Registered business classes: ' . implode(', ', $created) . '.';
        }
        if (!empty($reconciled)) {
            $messageParts[] = 'Reconciled business classes: ' . implode(', ', $reconciled) . '.';
        }
        if (!empty($warnings)) {
            $messageParts[] = 'Warnings: ' . implode(' ', $warnings);
        }
        if (empty($messageParts)) {
            $messageParts[] = 'No business classes were registered.';
        }

        $level = (!empty($created) || !empty($reconciled)) ? 'success' : 'warning';
        $this->runData['request']->setAlert(implode(' ', $messageParts), $level);
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid']);
        exit;
    }

    private function createOrReconcileBusinessClassRecord(array $ms, string $name, string $description, array $meta, array &$warnings): array {
        $sanitizedName = $this->sanitizeControllerName($name);
        if ($sanitizedName === '') {
            $warnings[] = 'Skipped controller with empty name.';
            return ['status' => 'invalid'];
        }

        $existing = $this->runData['db']->select('s_mscontroller', ['s_name' => $sanitizedName, 's_ms_id' => $ms['id']], true);
        if (empty($existing)) {
            $createdId = $this->createControllerRecord($ms, $sanitizedName, 'BL', $description, $warnings, $meta);
            return $createdId ? ['status' => 'created', 'id' => (int)$createdId] : ['status' => 'failed'];
        }

        $controller = $existing[0];
        if (strtoupper((string)($controller['s_type'] ?? '')) !== 'BL') {
            $warnings[] = 'Skipped controller ' . $sanitizedName . ' because an existing Data Model uses that internal key.';
            return ['status' => 'conflict'];
        }

        $updates = [];
        $sourceFile = trim((string)($meta['source_file'] ?? ''));
        $className = trim((string)($meta['class_name'] ?? ''));
        if ($sourceFile !== '' && (string)($controller['s_source_file'] ?? '') !== $sourceFile) {
            $updates['s_source_file'] = $sourceFile;
        }
        if ($className !== '' && (string)($controller['s_class_name'] ?? '') !== $className) {
            $updates['s_class_name'] = $className;
        }
        if (trim((string)($controller['s_description'] ?? '')) === '' && trim($description) !== '') {
            $updates['s_description'] = $description;
        }

        if (!empty($updates)) {
            $this->runData['db']->update('s_mscontroller', $updates, ['id' => (int)$controller['id']]);
        }

        return ['status' => 'reconciled', 'id' => (int)$controller['id']];
    }

    private function prepareBusinessClassSourceFile(string $msName, string $currentFile, string $targetFile, array &$warnings): bool {
        $msName = trim($msName);
        $currentFile = basename(trim($currentFile));
        $targetFile = basename(trim($targetFile));
        if ($msName === '' || $currentFile === '' || $targetFile === '') {
            return false;
        }

        $msDir = rtrim($this->runData['config']['dir']['ms'] ?? '', '/') . '/' . $msName;
        $currentPath = $msDir . '/' . $currentFile;
        $targetPath = $msDir . '/' . $targetFile;
        if (!is_file($currentPath)) {
            $warnings[] = $currentFile . ' was not found on disk.';
            return false;
        }
        if ($currentFile === $targetFile) {
            return true;
        }
        if (file_exists($targetPath)) {
            $warnings[] = $currentFile . ' could not be renamed because ' . $targetFile . ' already exists.';
            return false;
        }
        if (!@rename($currentPath, $targetPath)) {
            $warnings[] = $currentFile . ' could not be renamed to ' . $targetFile . '.';
            return false;
        }
        return true;
    }

    private function sanitizePhpClassName(string $name): string {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name);
        if ($name === '' || preg_match('/^[0-9]/', $name)) {
            return '';
        }
        return $name;
    }

    private function normalizeBusinessClassFileName(string $fileName, string $fallbackStem = ''): string {
        $fileName = trim($fileName);
        if ($fileName === '') {
            $fileName = trim($fallbackStem);
        }
        $fileName = basename($fileName);
        if ($fileName === '') {
            return '';
        }
        if (str_ends_with(strtolower($fileName), '.cls.php')) {
            $stem = substr($fileName, 0, -8);
        } else {
            $stem = $fileName;
        }
        $stem = preg_replace('/[^A-Za-z0-9_]/', '', $stem);
        if ($stem === '') {
            return '';
        }
        return $stem . '.cls.php';
    }

    private function extractPrimaryPhpClassName(string $path): string {
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $content = @file_get_contents($path);
        if (!is_string($content) || $content === '') {
            return '';
        }

        $tokens = @token_get_all($content);
        if (!is_array($tokens)) {
            return '';
        }

        $tokenCount = count($tokens);
        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];
            if (!is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }

            $prevIndex = $i - 1;
            while ($prevIndex >= 0) {
                $prev = $tokens[$prevIndex];
                if (is_array($prev) && in_array($prev[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $prevIndex--;
                    continue;
                }
                break;
            }
            if ($prevIndex >= 0) {
                $prev = $tokens[$prevIndex];
                if (is_array($prev) && in_array($prev[0], [T_DOUBLE_COLON, T_NEW], true)) {
                    continue;
                }
            }

            $nextIndex = $i + 1;
            while ($nextIndex < $tokenCount) {
                $next = $tokens[$nextIndex];
                if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $nextIndex++;
                    continue;
                }
                if (is_array($next) && $next[0] === T_STRING) {
                    return trim((string)$next[1]);
                }
                break;
            }
        }

        return '';
    }

    public function cleanupfiles() {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId !== 1 || !$this->priv->can('microservice_edit')) {
            throw new \Exception('Access denied.', 403);
        }

        $ms = $this->resolveMicroserviceFromDetailPath();
        $this->assertValidCsrfOrRedirect($ms['uid']);
        $requestedFiles = $this->normalizePostedFileList($this->runData['request']->post['cleanup_files'] ?? ($this->runData['request']->post['cleanup_file'] ?? []));
        if (empty($requestedFiles)) {
            $this->runData['request']->setAlert('Select at least one cleanup file to delete.', 'warning');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid']);
            exit;
        }

        $audit = $this->inspectMicroserviceFilesystem($ms);
        $candidateMap = [];
        foreach ($audit['cleanup_candidates'] as $candidate) {
            $candidateMap[$candidate['file']] = $candidate;
        }

        $deleted = [];
        $warnings = [];
        foreach ($requestedFiles as $file) {
            if (!isset($candidateMap[$file])) {
                $warnings[] = $file . ' is no longer eligible for cleanup.';
                continue;
            }
            $path = $candidateMap[$file]['path'] ?? '';
            if ($path === '' || !is_file($path)) {
                $warnings[] = $file . ' was not found on disk.';
                continue;
            }
            if (!@unlink($path)) {
                $warnings[] = 'Unable to delete ' . $file . '.';
                continue;
            }
            $deleted[] = $file;
        }

        $messageParts = [];
        if (!empty($deleted)) {
            $messageParts[] = 'Deleted cleanup files: ' . implode(', ', $deleted) . '.';
        }
        if (!empty($warnings)) {
            $messageParts[] = 'Warnings: ' . implode(' ', $warnings);
        }
        if (empty($messageParts)) {
            $messageParts[] = 'No cleanup files were deleted.';
        }

        $level = !empty($deleted) ? 'success' : 'warning';
        $this->runData['request']->setAlert(implode(' ', $messageParts), $level);
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid']);
        exit;
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

    private function resolveMicroserviceFromDetailPath(): array {
        $uid = $this->runData['route']['pathparts'][3] ?? '';
        if ($uid === '') {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $rows = $this->runData['db']->select('s_ms', ['uid' => $uid], true);
        if (count($rows) !== 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        if ($this->isRestrictedMs((int)($rows[0]['id'] ?? 0))) {
            throw new \Exception('Access denied', 404);
        }
        return $rows[0];
    }

    private function assertValidCsrfOrRedirect(string $uid): void {
        $csrfToken = $this->runData['request']->post['csrf_token'] ?? '';
        if ($this->runData['request']->checkCSRFToken($csrfToken)) {
            return;
        }
        $this->runData['request']->setAlert('Invalid request token. Please try again.', 'danger');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $uid);
        exit;
    }

    private function normalizePostedFileList($value): array {
        $items = is_array($value) ? $value : [$value];
        $normalized = [];
        foreach ($items as $item) {
            $file = trim((string)$item);
            if ($file === '' || strpos($file, '/') !== false || strpos($file, '\\') !== false) {
                continue;
            }
            $normalized[$file] = $file;
        }
        return array_values($normalized);
    }

    private function inspectMicroserviceFilesystem(array $ms): array {
        $msName = (string)($ms['s_name'] ?? '');
        $msType = (string)($ms['s_type'] ?? 'STA');
        $msDir = rtrim((string)($this->runData['config']['dir']['ms'] ?? ''), '/') . '/' . $msName;
        $audit = [
            'directory' => $msDir,
            'directory_exists' => is_dir($msDir),
            'expected_route_pattern' => strtoupper($msType) === 'DYN' ? 'route.{route_name}.*.php' : 'route.{route_id}.*.php',
            'registered_class_files' => [],
            'unregistered_class_files' => [],
            'cleanup_candidates' => [],
        ];

        $routes = $this->runData['db']->select('s_msroute', ['s_ms_id' => (int)($ms['id'] ?? 0)], true);
        $expectedRouteFiles = [];
        foreach ($routes as $routeRow) {
            $routeKey = $this->getRouteFileKey($routeRow, $msType);
            if ($routeKey === '') {
                continue;
            }
            foreach (['php', 'pagepart.php', 'prepart.php', 'postpart.php'] as $suffix) {
                $expectedRouteFiles['route.' . $routeKey . '.' . $suffix] = true;
            }
        }

        $controllers = $this->runData['db']->select('s_mscontroller', ['s_ms_id' => (int)($ms['id'] ?? 0), 's_type' => 'BL'], true);
        $registeredBusinessClasses = [];
        foreach ($controllers as $controller) {
            foreach ($this->resolveBusinessClassFileCandidates($controller) as $fileName) {
                $registeredBusinessClasses[$fileName] = (string)($controller['s_name'] ?? '');
            }
        }

        if (!$audit['directory_exists']) {
            return $audit;
        }

        $entries = @scandir($msDir);
        if (!is_array($entries)) {
            return $audit;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $msDir . '/' . $entry;
            if (!is_file($path)) {
                continue;
            }

            if (preg_match('/\.cls\.php$/', $entry)) {
                $fileStem = substr($entry, 0, -8);
                $controllerName = $this->deriveControllerRegistrationName($fileStem);
                $isRegisterable = $this->isRegisterableControllerFileStem($fileStem, $controllerName);
                $declaredClassName = $this->extractPrimaryPhpClassName($path);
                if (isset($registeredBusinessClasses[$entry])) {
                    $audit['registered_class_files'][] = [
                        'file' => $entry,
                        'controller_name' => $registeredBusinessClasses[$entry],
                        'declared_class_name' => $declaredClassName,
                    ];
                } else {
                    $audit['unregistered_class_files'][] = [
                        'file' => $entry,
                        'path' => $path,
                        'controller_name' => $controllerName,
                        'source_name' => $declaredClassName !== '' ? $declaredClassName : $fileStem,
                        'declared_class_name' => $declaredClassName,
                        'is_registerable' => $isRegisterable,
                    ];
                }
                continue;
            }

            if (isset($expectedRouteFiles[$entry])) {
                continue;
            }

            $audit['cleanup_candidates'][] = [
                'file' => $entry,
                'path' => $path,
                'reason' => str_starts_with($entry, 'route.')
                    ? 'Does not match the expected route file key for this microservicelet.'
                    : 'Unexpected file in the microservicelet directory.',
            ];
        }

        usort($audit['registered_class_files'], fn($a, $b) => strcmp($a['file'], $b['file']));
        usort($audit['unregistered_class_files'], fn($a, $b) => strcmp($a['file'], $b['file']));
        usort($audit['cleanup_candidates'], fn($a, $b) => strcmp($a['file'], $b['file']));

        return $audit;
    }

    /**
     * Upgrade a Microservicelet to DYN type.
     */
    public function upgradetodyn() {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId !== 1 || !$this->priv->can('microservice_edit')) {
            throw new \Exception('Access denied.', 403);
        }
        $uid = $this->runData['route']['pathparts'][3] ?? '';
        if ($uid === '') {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $msRows = $this->runData['db']->select('s_ms', ['uid' => $uid], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $ms = $msRows[0];
        if (strtoupper($ms['s_type'] ?? '') === 'DYN') {
            $this->runData['request']->setAlert('Microservicelet is already DYN.', 'info');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $uid);
            exit;
        }
        $post = $this->runData['request']->post ?? [];
        $csrfToken = $post['csrf_token'] ?? '';
        if (!$this->runData['request']->checkCSRFToken($csrfToken)) {
            $this->runData['request']->setAlert('Invalid request token. Please try again.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $uid);
            exit;
        }

        $rewriteRoutes = !empty($post['rewrite_routes']);
        $rewriteTheme = !empty($post['rewrite_theme']);

        $msId = (int)$ms['id'];
        $msName = $ms['s_name'] ?? '';
        $workspacePrefix = $this->runData['config']['sys']['workspace_slug_prefix'] ?? null;

        $routes = $this->runData['db']->select('s_msroute', ['s_ms_id' => $msId], true);
        $renameCounts = ['renamed' => 0, 'overwritten' => 0, 'created' => 0, 'missing' => 0];
        $rewriteCount = 0;
        $routeWarnings = 0;

        foreach ($routes as $route) {
            $routeId = (int)($route['id'] ?? 0);
            $routeName = (string)($route['s_name'] ?? '');
            if ($routeId <= 0 || $routeName === '') {
                $routeWarnings++;
                continue;
            }
            if (strpos($routeName, '/') !== false) {
                $routeWarnings++;
                continue;
            }
            $rename = $this->renameRouteFilesByKey($msName, (string)$routeId, $routeName);
            $renameCounts['renamed'] += $rename['renamed'];
            $renameCounts['overwritten'] += $rename['overwritten'];
            $renameCounts['created'] += $rename['created'];
            $renameCounts['missing'] += $rename['missing'];

            if ($rewriteRoutes) {
                $rules = $this->buildDynRewriteRules($msName, $routeId, $routeName, $workspacePrefix);
                $msDir = rtrim($this->runData['config']['dir']['ms'] ?? '', '/') . '/' . $msName;
                foreach (['php', 'pagepart.php', 'prepart.php', 'postpart.php'] as $suffix) {
                    $filePath = $msDir . '/route.' . $routeName . '.' . $suffix;
                    if (!file_exists($filePath)) {
                        $filePath = $msDir . '/route.' . $routeId . '.' . $suffix;
                    }
                    $rewriteCount += $this->rewriteDynLinksInFile($filePath, $rules);
                }
            }
        }

        $themeRewriteCount = 0;
        if ($rewriteTheme) {
            $tplName = $ms['s_tpl_name'] ?? '';
            if ($tplName !== '') {
                $tplPath = rtrim($this->runData['config']['dir']['theme'] ?? '', '/') . '/' . $tplName . '.tpl.php';
                if (file_exists($tplPath)) {
                    foreach ($routes as $route) {
                        $routeId = (int)($route['id'] ?? 0);
                        $routeName = (string)($route['s_name'] ?? '');
                        if ($routeId <= 0 || $routeName === '') {
                            continue;
                        }
                        if (strpos($routeName, '/') !== false) {
                            continue;
                        }
                        $rules = $this->buildDynRewriteRules($msName, $routeId, $routeName, $workspacePrefix);
                        $themeRewriteCount += $this->rewriteDynLinksInFile($tplPath, $rules);
                    }
                }
            }
        }

        $this->runData['db']->update('s_ms', ['s_type' => 'DYN'], ['id' => $msId], ['updatedby' => $entityId]);

        $message = 'Microservicelet upgraded to DYN. ';
        $message .= 'Files renamed: ' . $renameCounts['renamed'] . '. ';
        if ($renameCounts['overwritten'] > 0) {
            $message .= 'Overwritten: ' . $renameCounts['overwritten'] . '. ';
        }
        if ($renameCounts['created'] > 0) {
            $message .= 'Created: ' . $renameCounts['created'] . '. ';
        }
        if ($rewriteRoutes) {
            $message .= 'Route rewrites: ' . $rewriteCount . '. ';
        }
        if ($rewriteTheme) {
            $message .= 'Theme rewrites: ' . $themeRewriteCount . '. ';
        }
        if ($routeWarnings > 0) {
            $message .= 'Skipped/conflicts: ' . $routeWarnings . '.';
        }

        $this->runData['request']->setAlert(trim($message), 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $uid);
        exit;
    }

    /**
     * Overwrite all route permission bindings with the parent microservicelet bindings.
     */
    public function overwritebindings() {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId !== 1 || !$this->priv->can('microservice_edit')) {
            throw new \Exception('Access denied.', 403);
        }
        $uid = $this->runData['route']['pathparts'][3] ?? '';
        if ($uid === '') {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $msRows = $this->runData['db']->select('s_ms', ['uid' => $uid], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $ms = $msRows[0];

        $post = $this->runData['request']->post ?? [];
        $csrfToken = $post['csrf_token'] ?? '';
        if (!$this->runData['request']->checkCSRFToken($csrfToken)) {
            $this->runData['request']->setAlert('Invalid request token. Please try again.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $uid);
            exit;
        }

        $msId = (int)$ms['id'];
        $routes = $this->runData['db']->select('s_msroute', ['s_ms_id' => $msId], true);
        $msRoleIds = $this->fetchMsBindingRoleIds($msId);

        $routesTouched = 0;
        $bindingsRemoved = 0;
        $bindingsInserted = 0;
        foreach ($routes as $route) {
            $routeId = (int)($route['id'] ?? 0);
            if ($routeId <= 0) {
                continue;
            }
            $countRows = $this->runData['db']->query(
                "SELECT COUNT(*) AS total FROM s_permission_binding
                 WHERE s_object_type = 'route' AND s_object_id = :rid AND livestatus != '0'",
                [':rid' => $routeId]
            );
            $bindingsRemoved += (int)($countRows[0]['total'] ?? 0);

            $this->runData['db']->delete('s_permission_binding', [
                's_object_type' => 'route',
                's_object_id' => $routeId,
            ]);

            if (!empty($msRoleIds)) {
                foreach ($msRoleIds as $roleId) {
                    $this->runData['db']->insert('s_permission_binding', [
                        's_object_type' => 'route',
                        's_object_id' => $routeId,
                        's_role_id' => $roleId,
                    ]);
                    $bindingsInserted++;
                }
            }
            $routesTouched++;
        }

        $message = sprintf(
            'Route bindings overwritten for %d route(s). Removed: %d. Added: %d.',
            $routesTouched,
            $bindingsRemoved,
            $bindingsInserted
        );
        $this->runData['request']->setAlert($message, 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $uid);
        exit;
    }

    private function fetchMsBindingRoleIds(int $msId): array {
        if ($msId <= 0) {
            return [];
        }
        $rows = $this->runData['db']->query(
            "SELECT s_role_id FROM s_permission_binding
             WHERE s_object_type = 'ms' AND s_object_id = :msid AND livestatus != '0'",
            [':msid' => $msId]
        );
        $roleIds = [];
        foreach ($rows as $row) {
            $rid = (int)($row['s_role_id'] ?? 0);
            if ($rid > 0) {
                $roleIds[] = $rid;
            }
        }
        return array_values(array_unique($roleIds));
    }

    private function inheritMsBindingsToRoute(int $msId, int $routeId): int {
        if ($msId <= 0 || $routeId <= 0) {
            return 0;
        }
        $msRoleIds = $this->fetchMsBindingRoleIds($msId);
        if (empty($msRoleIds)) {
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
        foreach ($msRoleIds as $roleId) {
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

    public function branchcreate() {
        if (!$this->priv->can('microservice_edit')) {
            throw new \Exception('Access denied.', 403);
        }
        $ms = $this->fetchMicroserviceByUid($this->runData['route']['pathparts'][3] ?? '');
        $branchService = new BranchService($this->runData['db'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [], $this->runData['request'] ?? null);
        if (!$branchService->canUseBeta()) {
            throw new \Exception('Access denied.', 403);
        }
        $routes = $this->runData['db']->select('s_msroute', ['s_ms_id' => $ms['id']], true);
        $created = 0;
        foreach ($routes as $route) {
            $result = $branchService->createRouteBeta($ms['s_name'], (int)$route['id']);
            if (!empty($result['status'])) {
                $created++;
            }
        }
        $this->runData['request']->setAlert("Beta branch initialized for {$created} route(s).", 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid']);
        exit;
    }

    public function branchmerge() {
        if (!$this->priv->can('microservice_edit')) {
            throw new \Exception('Access denied.', 403);
        }
        $ms = $this->fetchMicroserviceByUid($this->runData['route']['pathparts'][3] ?? '');
        $branchService = new BranchService($this->runData['db'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [], $this->runData['request'] ?? null);
        if (!$branchService->canMerge()) {
            throw new \Exception('Access denied.', 403);
        }
        $routes = $this->runData['db']->select('s_msroute', ['s_ms_id' => $ms['id']], true);
        $merged = 0;
        foreach ($routes as $route) {
            $result = $branchService->mergeRouteBeta($ms['s_name'], (int)$route['id']);
            if (!empty($result['status'])) {
                $merged++;
            }
        }
        $this->runData['request']->setAlert("Merged beta branch for {$merged} route(s).", 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid']);
        exit;
    }

    public function branchdiscard() {
        if (!$this->priv->can('microservice_edit')) {
            throw new \Exception('Access denied.', 403);
        }
        $ms = $this->fetchMicroserviceByUid($this->runData['route']['pathparts'][3] ?? '');
        $branchService = new BranchService($this->runData['db'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [], $this->runData['request'] ?? null);
        if (!$branchService->canUseBeta()) {
            throw new \Exception('Access denied.', 403);
        }
        $routes = $this->runData['db']->select('s_msroute', ['s_ms_id' => $ms['id']], true);
        $discarded = 0;
        foreach ($routes as $route) {
            $result = $branchService->discardRouteBeta($ms['s_name'], (int)$route['id']);
            if (!empty($result['status'])) {
                $discarded++;
            }
        }
        $this->runData['request']->setAlert("Discarded beta branch for {$discarded} route(s).", 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid']);
        exit;
    }

    /**
     * Export a Microservicelet as a portable ZIP.
     */
    public function export() {
        if (!isset($this->runData['route']['pathparts'][3]) || $this->runData['route']['pathparts'][3] == '') {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $ms = $this->fetchMicroserviceByUid($this->runData['route']['pathparts'][3]);
        $package = $this->buildPackage($ms);

        $zipPath = $this->createZip($ms['s_name'], $package['files'], $package['manifest'], $package['data']);
        if (!is_file($zipPath)) {
            throw new \Exception('Unable to create package.');
        }

        $this->streamFile($zipPath, $ms['s_name'] . '-microservice.zip');
        @unlink($zipPath);
        exit;
    }

    /**
     * Import a Microservicelet from a ZIP.
     */
    public function import() {
        $this->runData['route']['h1'] = 'Import Microservicelet';
        $this->runData['route']['meta_title'] = 'Import Microservicelet';
        $this->runData['route']['breadcrumb'] = [
            'Microservicelet' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            'Import' => '',
        ];

        if (strtoupper($this->runData['request']->method ?? '') === 'POST') {
            $result = $this->handleImportUpload();
            $this->runData['route']['alert'] = $result['status'];
            $this->runData['route']['alert_message'] = $result['message'];
        }

        return $this->runData;
    }

    /**
     * Inspect and export microservice metadata for downstream tooling.
     */
    public function sniff() {
        if (!isset($this->runData['route']['pathparts'][3]) || $this->runData['route']['pathparts'][3] == '') {
            throw new \Exception('Invalid Microservicelet', 404);
        }

        $msRows = $this->runData['db']->select('s_ms', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($msRows) != 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $ms = $msRows[0];
        if ($this->isRestrictedMs((int)$ms['id'])) {
            throw new \Exception('Access denied', 404);
        }

        $sniffPayload = $this->buildMicroserviceSniffPayload($ms);

        $this->runData['data']['ms'] = $ms;
        $this->runData['data']['sniff_payload'] = $sniffPayload;

        $this->runData['route']['h1'] = 'Meta Sniff: ' . ($ms['s_name'] ?? '');
        $this->runData['route']['meta_title'] = 'Meta Sniff - ' . ($ms['s_name'] ?? '');
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid'];

        return $this->runData;
    }

    private function buildMicroserviceSniffPayload(array $ms): array {
        $msId = (int)($ms['id'] ?? 0);
        $bindingRoles = $this->fetchBindingRoles('ms', $msId);
        $routeRows = $this->runData['db']->query(
            "SELECT id, uid, s_name, s_description, livestatus
             FROM s_msroute
             WHERE s_ms_id = :ms_id
             ORDER BY id ASC",
            [':ms_id' => $msId]
        );
        $routes = [];
        $defaultRoute = null;
        $defaultRouteId = (int)($ms['s_default_route_id'] ?? 0);
        foreach ($routeRows as $row) {
            $route = [
                'id' => (int)($row['id'] ?? 0),
                'uid' => (string)($row['uid'] ?? ''),
                'route_name' => (string)($row['s_name'] ?? ''),
                'description' => (string)($row['s_description'] ?? ''),
                'livestatus' => (string)($row['livestatus'] ?? ''),
                'is_default' => $defaultRouteId > 0 && (int)($row['id'] ?? 0) === $defaultRouteId,
            ];
            $routes[] = $route;
            if ($route['is_default']) {
                $defaultRoute = $route;
            }
        }

        $roleIds = array_values(array_unique(array_filter(array_map(function ($role) {
            return (int)($role['role_id'] ?? 0);
        }, $bindingRoles))));
        $roleRows = [];
        if (!empty($roleIds)) {
            $placeholders = [];
            $params = [];
            foreach ($roleIds as $index => $roleId) {
                $placeholder = ':rid' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $roleId;
            }
            $roleRows = $this->runData['db']->query(
                'SELECT id, uid, s_role_name, s_scope FROM s_role WHERE id IN (' . implode(',', $placeholders) . ') ORDER BY s_role_name ASC, id ASC',
                $params
            );
        }

        $roles = [];
        foreach ($roleRows as $row) {
            $roles[] = [
                'id' => (int)($row['id'] ?? 0),
                'uid' => (string)($row['uid'] ?? ''),
                'role' => (string)($row['s_role_name'] ?? ''),
                'type' => (string)($row['s_scope'] ?? ''),
            ];
        }

        $bindings = [];
        foreach ($bindingRoles as $row) {
            $bindings[] = [
                'binding_id' => (int)($row['binding_id'] ?? 0),
                'role_id' => (int)($row['role_id'] ?? 0),
                'role' => (string)($row['role_name'] ?? ''),
                'type' => (string)($row['role_scope'] ?? ''),
            ];
        }

        return [
            'object' => [
                'kind' => 'microservice',
                'id' => $msId,
                'uid' => (string)($ms['uid'] ?? ''),
                'name' => (string)($ms['s_name'] ?? ''),
                'scope' => (string)($ms['s_scope'] ?? ''),
                'type' => (string)($ms['s_type'] ?? ''),
            ],
            'default_route' => $defaultRoute,
            'routes' => $routes,
            'roles' => $roles,
            'bindings' => $bindings,
            'stats' => [
                'route_count' => count($routes),
                'role_count' => count($roles),
                'binding_count' => count($bindings),
            ],
        ];
    }

    /**
     * Archive a Microservice
     */
    public function archive() {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId !== 1 || !$this->priv->can('delete')) {
            throw new \Exception('Access denied.', 403);
        }
        // check if the Microservice exists from the s_ms table and with route id from the pathparts array 3rd element
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $msRow = $this->runData['db']->select('s_ms', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($msRow) != 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $msId = (int)$msRow[0]['id'];
        // Archive the Microservice and related assets
        $this->runData['db']->update('s_ms', ['livestatus' => '2'], ['id' => $msId], ['updatedby' => $entityId]);
        $this->bulkUpdateByMsId('s_msroute', $msId, '2', $entityId);
        $this->bulkUpdateByMsId('s_mscontroller', $msId, '2', $entityId);
        $this->bulkUpdateByMsId('s_content', $msId, '2', $entityId);

        $controllerIds = $this->collectMsControllerIds($msId);
        if (!empty($controllerIds)) {
            $this->bulkUpdateByIds('s_data_field', 's_mscontroller_id', $controllerIds, '2', $entityId);
            $this->bulkUpdateByIds('s_data_method', 's_service_id', $controllerIds, '2', $entityId);
        }
        // Add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Microservicelet archived successfully.';
        // Register alert into cookie
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        // Redirect to the Microservice listing page
        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/microservice/view';
        header("Location: {$redirectUrl}");exit;
    }

    /**
     * Activate a Microservice
     */
    public function activate() {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId !== 1 || !$this->priv->can('microservice_edit')) {
            throw new \Exception('Access denied.', 403);
        }
        // check if the Microservice exists from the s_ms table and with route id from the pathparts array 3rd element
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $msRow = $this->runData['db']->select('s_ms', ['uid' => $this->runData['route']['pathparts'][3]], true);
        // print '<pre>';print_r($msRow);print '</pre>';
        // print '<pre>';print_r($this->runData['route']['pathparts']);print '</pre>';die('here');
        if (count($msRow) != 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $msId = (int)$msRow[0]['id'];
        // Activate the Microservice and related assets
        $this->runData['db']->update('s_ms', ['livestatus' => '1'], ['id' => $msId], ['updatedby' => $entityId]);
        $this->bulkUpdateByMsId('s_msroute', $msId, '1', $entityId);
        $this->bulkUpdateByMsId('s_mscontroller', $msId, '1', $entityId);
        $this->bulkUpdateByMsId('s_content', $msId, '1', $entityId);

        $controllerIds = $this->collectMsControllerIds($msId);
        if (!empty($controllerIds)) {
            $this->bulkUpdateByIds('s_data_field', 's_mscontroller_id', $controllerIds, '1', $entityId);
            $this->bulkUpdateByIds('s_data_method', 's_service_id', $controllerIds, '1', $entityId);
        }
        // Add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Microservicelet activated successfully.';
        // Register alert into cookie
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        // Redirect to the Microservice listing page
        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/microservice/view';
        header("Location: {$redirectUrl}");exit;
    }

    /**
     * Clean archived microservicelets and related data.
     */
    public function trash_clean() {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId !== 1 || !$this->priv->can('delete')) {
            throw new \Exception('Access denied.', 403);
        }

        $archived = $this->runData['db']->select('s_ms', ['livestatus' => '2'], true);
        if (empty($archived)) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'No archived microservicelets found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/view');
            exit;
        }

        $radDir = rtrim((string)($this->runData['config']['dir']['rad'] ?? dirname(__DIR__, 2)), '/');
        $trashRoot = $radDir . '/data/trash/ms';
        $timestamp = date('Ymd_His');
        $totals = [
            'microservicelets' => 0,
            'routes' => 0,
            'controllers' => 0,
            'data_fields' => 0,
            'data_methods' => 0,
            'content_blocks' => 0,
            'folders_moved' => 0,
        ];

        foreach ($archived as $msRow) {
            $msId = (int)($msRow['id'] ?? 0);
            $msName = (string)($msRow['s_name'] ?? '');
            if ($msId <= 0) {
                continue;
            }

            $totals['microservicelets']++;
            $totals['routes'] += $this->countByMsId('s_msroute', $msId);
            $totals['controllers'] += $this->countByMsId('s_mscontroller', $msId);
            $totals['content_blocks'] += $this->countByMsId('s_content', $msId);

            $controllerIds = $this->collectMsControllerIds($msId);
            if (!empty($controllerIds)) {
                $totals['data_fields'] += $this->countByIds('s_data_field', 's_mscontroller_id', $controllerIds);
                $totals['data_methods'] += $this->countByIds('s_data_method', 's_service_id', $controllerIds);
                $this->deleteByIds('s_data_field', 's_mscontroller_id', $controllerIds);
                $this->deleteByIds('s_data_method', 's_service_id', $controllerIds);
            }

            $this->deleteByMsId('s_msroute', $msId);
            $this->deleteByMsId('s_content', $msId);
            $this->deleteByMsId('s_mscontroller', $msId);
            $this->runData['db']->delete('s_ms', ['id' => $msId]);

            if ($msName !== '') {
                $src = rtrim((string)($this->runData['config']['dir']['ms'] ?? ''), '/') . '/' . $msName;
                if (is_dir($src)) {
                    $dest = $trashRoot . '/' . $msName . '/' . $timestamp;
                    if (!is_dir($dest)) {
                        mkdir($dest, 0777, true);
                    }
                    if (@rename($src, $dest . '/' . $msName)) {
                        $totals['folders_moved']++;
                    }
                }
            }
        }

        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = sprintf(
            'Trash cleaned: %d microservicelets, %d routes, %d controllers, %d data fields, %d data methods, %d content blocks. Folders moved: %d.',
            $totals['microservicelets'],
            $totals['routes'],
            $totals['controllers'],
            $totals['data_fields'],
            $totals['data_methods'],
            $totals['content_blocks'],
            $totals['folders_moved']
        );
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/view');
        exit;
    }

    private function collectMsControllerIds(int $msId): array {
        $rows = $this->runData['db']->select('s_mscontroller', ['s_ms_id' => $msId], true);
        $ids = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function bulkUpdateByIds(string $table, string $idColumn, array $ids, string $status, int $actorId): void {
        if (empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = [':status' => $status, ':updatedby' => $actorId];
        foreach (array_values($ids) as $idx => $id) {
            $key = ':id' . ($idx + 1);
            $placeholders[] = $key;
            $params[$key] = (int)$id;
        }
        $sql = "UPDATE {$table}
                SET livestatus = :status,
                    versioncode = versioncode + 1,
                    updatedby = :updatedby,
                    updatestamp = '" . date('Y-m-d H:i:s') . "'
                WHERE {$idColumn} IN (" . implode(',', $placeholders) . ")";
        $this->runData['db']->query($sql, $params);
    }

    private function bulkUpdateByMsId(string $table, int $msId, string $status, int $actorId): void {
        $sql = "UPDATE {$table}
                SET livestatus = :status,
                    versioncode = versioncode + 1,
                    updatedby = :updatedby,
                    updatestamp = '" . date('Y-m-d H:i:s') . "'
                WHERE s_ms_id = :ms_id";
        $this->runData['db']->query($sql, [
            ':status' => $status,
            ':updatedby' => $actorId,
            ':ms_id' => $msId,
        ]);
    }

    private function deleteByMsId(string $table, int $msId): void {
        $sql = "DELETE FROM {$table} WHERE s_ms_id = :ms_id";
        $this->runData['db']->query($sql, [':ms_id' => $msId]);
    }

    private function deleteByIds(string $table, string $idColumn, array $ids): void {
        if (empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = [];
        foreach (array_values($ids) as $idx => $id) {
            $key = ':id' . ($idx + 1);
            $placeholders[] = $key;
            $params[$key] = (int)$id;
        }
        $sql = "DELETE FROM {$table} WHERE {$idColumn} IN (" . implode(',', $placeholders) . ")";
        $this->runData['db']->query($sql, $params);
    }

    private function countByMsId(string $table, int $msId): int {
        $rows = $this->runData['db']->query(
            "SELECT COUNT(*) AS total FROM {$table} WHERE s_ms_id = :ms_id",
            [':ms_id' => $msId]
        );
        return (int)($rows[0]['total'] ?? 0);
    }

    private function countByIds(string $table, string $idColumn, array $ids): int {
        if (empty($ids)) {
            return 0;
        }
        $placeholders = [];
        $params = [];
        foreach (array_values($ids) as $idx => $id) {
            $key = ':id' . ($idx + 1);
            $placeholders[] = $key;
            $params[$key] = (int)$id;
        }
        $sql = "SELECT COUNT(*) AS total FROM {$table} WHERE {$idColumn} IN (" . implode(',', $placeholders) . ")";
        $rows = $this->runData['db']->query($sql, $params);
        return (int)($rows[0]['total'] ?? 0);
    }

    /**
     * Rename the filesystem folder for a microservice when its name changes.
     *
     * @param string $oldName Existing microservice name
     * @param string $newName Updated microservice name
     * @return array ['status' => bool, 'message' => string|null]
     */
    private function renameMicroserviceFolder($oldName, $newName) {
        $oldName = trim((string)$oldName);
        $newName = trim((string)$newName);

        if ($oldName === '' || $newName === '' || $oldName === $newName) {
            return ['status' => true, 'message' => null];
        }

        $msBaseDir = $this->runData['config']['dir']['ms'] ?? null;
        if (!$msBaseDir) {
            return ['status' => false, 'message' => 'Microservicelet base directory is not configured.'];
        }

        $msBaseDir = rtrim($msBaseDir, '/');
        $oldPath = $msBaseDir . '/' . $oldName;
        $newPath = $msBaseDir . '/' . $newName;

        if (!is_dir($oldPath)) {
            return ['status' => false, 'message' => 'Folder ' . $oldName . ' does not exist under rad/ms.'];
        }

        if (is_dir($newPath)) {
            return ['status' => false, 'message' => 'Target folder ' . $newName . ' already exists under rad/ms.'];
        }

        if (!@rename($oldPath, $newPath)) {
            return ['status' => false, 'message' => 'Unable to rename folder on disk.'];
        }

        return ['status' => true, 'message' => null];
    }

    /**
     * Ensure that a microservice folder (and its default route files) exist.
     *
     * @param array $msRow Microservice row from s_ms
     * @param bool $forceFileCheck When true, re-check route files even if folder exists.
     * @return array ['status' => bool, 'message' => string|null]
     */
    private function ensureMicroserviceFolder(array $msRow, bool $forceFileCheck = false) {
        $msBaseDir = $this->runData['config']['dir']['ms'] ?? null;
        if (!$msBaseDir) {
            return ['status' => false, 'message' => 'Microservicelet base directory is not configured.'];
        }

        $msDir = rtrim($msBaseDir, '/') . '/' . $msRow['s_name'];
        $folderCreated = false;
        if (!is_dir($msDir)) {
            if (!@mkdir($msDir, 0775, true)) {
                return ['status' => false, 'message' => 'Unable to recreate directory ' . $msRow['s_name'] . ' under rad/ms.'];
            }
            $folderCreated = true;
        }

        $defaultRouteId = (int)($msRow['s_default_route_id'] ?? 0);
        $filesCreated = [];
        $shouldCreateFiles = $folderCreated || $forceFileCheck;
        if ($defaultRouteId > 0 && $shouldCreateFiles) {
            $routeRow = $this->runData['db']->select('s_msroute', ['id' => $defaultRouteId], true);
            if (empty($routeRow[0])) {
                return ['status' => true, 'message' => 'Microservicelet folder recreated but default route could not be resolved for file generation.'];
            }
            $routeKey = $this->getRouteFileKey($routeRow[0], $msRow['s_type'] ?? 'STA');
            $routeLabel = $routeRow[0]['s_name'] ?? $routeKey;
            $fileTemplates = [
                'route.%s.php' => "<?php\n// Auto-generated placeholder for %s route %s.\n",
                'route.%s.prepart.php' => "<?php\n// Auto-generated prepart placeholder for %s route %s.\n",
                'route.%s.postpart.php' => "<?php\n// Auto-generated postpart placeholder for %s route %s.\n",
                'route.%s.pagepart.php' => "<!-- Auto-generated page part placeholder for %s route %s. -->\n",
            ];
            foreach ($fileTemplates as $pattern => $template) {
                $filePath = $msDir . '/' . sprintf($pattern, $routeKey);
                if (!file_exists($filePath)) {
                    $content = sprintf($template, $msRow['s_name'], $routeLabel);
                    if (file_put_contents($filePath, $content) === false) {
                        return ['status' => false, 'message' => 'Unable to create ' . basename($filePath) . '.'];
                    }
                    $filesCreated[] = basename($filePath);
                }
            }
        } elseif ($folderCreated && $defaultRouteId <= 0) {
            return ['status' => true, 'message' => 'Microservicelet folder recreated but no default route is set for file generation.'];
        }

        if ($folderCreated || !empty($filesCreated)) {
            return ['status' => true, 'message' => 'Microservicelet directory was missing and has been restored along with default route files.'];
        }

        return ['status' => true, 'message' => null];
    }

    private function logMicroserviceActivity(string $action, int $msId, string $name, string $description, string $type): void {
        $db = $this->runData['db'] ?? null;
        if (!$db) {
            return;
        }
        $msRows = $db->select('s_ms', ['id' => $msId], true);
        if (empty($msRows[0])) {
            return;
        }
        $msRow = $msRows[0];
        $actorId = (int)($this->runData['entity']['id'] ?? 0);
        $actorName = $this->runData['entity']['fullname'] ?? $this->runData['entity']['username'] ?? '';

        $context = [
            '{action}' => $action,
            '{ms_id}' => (string)$msId,
            '{ms_uid}' => $msRow['uid'] ?? '',
            '{ms_name}' => $msRow['s_name'] ?? $name,
            '{ms_description}' => $description,
            '{ms_type}' => $type,
            '{actor}' => $actorName,
            '{timestamp}' => date('Y-m-d H:i:s T'),
        ];
        $message = $this->renderTemplateWithFallback('', $context, sprintf('Microservicelet %s: %s', $action, $context['{ms_name}']));

        try {
            $activitySvc = new \Core\Sys\ActivityService($db);
            $activitySvc->log([
                's_actor_id' => $actorId ?: null,
                's_object_type' => 'ms',
                's_object_id' => $msId,
                's_action' => $action,
                's_message' => $message,
                's_payload' => [
                    'ms_id' => $msId,
                    'ms_uid' => $msRow['uid'] ?? '',
                    'ms_name' => $context['{ms_name}'],
                    'description' => $description,
                    'type' => $type,
                    'actor' => $actorName,
                    'timestamp' => $context['{timestamp}'],
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore activity failures
        }

        try {
            $notifSvc = $this->runData['notificationService'] ?? new \Core\Sys\NotificationService($db);
            if ($notifSvc instanceof \Core\Sys\NotificationService) {
                $notifSvc->logGlobalEvent($message, [
                    'event_type' => 'ms_' . $action,
                    'created_by' => $actorId ?: null,
                    'metadata' => [
                        'ms_id' => $msId,
                        'ms_uid' => $msRow['uid'] ?? '',
                        'ms_name' => $context['{ms_name}'],
                        'actor' => $actorName,
                        'timestamp' => $context['{timestamp}'],
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            // ignore notification failures
        }

        // Redundant safeguard: log activity again if the earlier block was skipped (template empty).
        // This ensures every microservice change has an activity entry.
        try {
            $activitySvc = new \Core\Sys\ActivityService($db);
            $activitySvc->log([
                's_actor_id' => $actorId ?: null,
                's_object_type' => 'ms',
                's_object_id' => $msId,
                's_action' => $action,
                's_message' => $message ?: sprintf('Microservicelet %s: %s', $action, $context['{ms_name}']),
                's_payload' => [
                    'ms_id' => $msId,
                    'ms_uid' => $msRow['uid'] ?? '',
                    'ms_name' => $context['{ms_name}'],
                    'description' => $description,
                    'type' => $type,
                    'actor' => $actorName,
                    'timestamp' => $context['{timestamp}'],
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore activity failures
        }
    }

    private function renderTemplateWithFallback(string $template, array $context, string $fallback): string {
        $tpl = trim($template);
        if ($tpl !== '') {
            $rendered = strtr($tpl, $context);
            if ($rendered !== '') {
                return $rendered;
            }
        }
        return $fallback;
    }

    private function isRestrictedMs(int $msId): bool {
        return VisibilityHelper::isRestrictedMs($msId, $this->runData['config'] ?? [], $this->runData['entity'] ?? []);
    }

    private function generateAiPlan(string $spec, int $maxTokens): array {
        $prompt = "You are an expert RAD architect. Given a natural language spec, propose a microservicelet with routes, controllers, and data models.\n"
            . "Output strictly valid JSON with keys: microservice {name, description, scope, type}, routes [ {path, method, description} ], "
            . "controllers [ {name, type (BL|DM), description, fields:[{name,type,description}]} ], "
            . "nav [ {label, path, location, navset_id?} ]. Keep identifiers URL-safe and lowercase with dashes; keep 1-3 routes. Suggest nav location as sidebar/header and path under the microservice base.";
        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => $spec],
        ];
        $raw = $this->aiService->chat($messages, ['format' => 'json', 'max_tokens' => $maxTokens]);
        $plan = $this->decodeJsonLoose($raw);
        return $plan;
    }

    private function resolveAiWizardMaxTokens(string $responseSize = ''): int {
        $default = 1800;
        $value = $this->runData['config']['sys']['aiwizard_max_tokens'] ?? $this->runData['config']['app']['aiwizard_max_tokens'] ?? null;
        if ($value === null || $value === '') {
            try {
                $rows = $this->db->select('s_config', ['s_config_handle' => 'aiwizard_max_tokens'], true);
                if (!empty($rows[0]['s_config_value'])) {
                    $value = $rows[0]['s_config_value'];
                }
            } catch (\Throwable $e) {
                $value = null;
            }
        }
        $sizeMap = [
            'small' => 900,
            'medium' => 1800,
            'large' => 3600,
        ];
        $sizeTokens = $sizeMap[$responseSize] ?? null;
        $maxTokens = (int)($value ?? ($sizeTokens ?? $default));
        if ($maxTokens < 256) {
            $maxTokens = 256;
        }
        if ($maxTokens > 8192) {
            $maxTokens = 8192;
        }
        return $maxTokens;
    }

    private function decodeJsonLoose(string $raw): array {
        $try = trim($raw);
        $plan = json_decode($try, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($plan)) {
            return $plan;
        }
        // Strip Markdown fences if present
        $try = preg_replace('/^```(?:json)?/i', '', $try);
        $try = preg_replace('/```$/', '', $try);
        $try = trim($try);
        $plan = json_decode($try, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($plan)) {
            return $plan;
        }
        throw new \Exception('AI did not return valid JSON.');
    }

    private function applyPlan(array $plan): array {
        $ms = $plan['microservice'] ?? [];
        $routes = is_array($plan['routes'] ?? null) ? $plan['routes'] : [];
        $controllers = is_array($plan['controllers'] ?? null) ? $plan['controllers'] : [];
        $nav = is_array($plan['nav'] ?? null) ? $plan['nav'] : [];

        $msName = $this->sanitizeMsName($ms['name'] ?? '');
        if ($msName === '') {
            throw new \Exception('Microservice name is required.');
        }
        if (!empty($this->db->select('s_ms', ['s_name' => $msName], true))) {
            $msName = $this->generateUniqueName($msName);
        }

        $msType = 'DYN';
        $msScope = $this->sanitizeMsScope($ms['scope'] ?? '');
        $msId = $this->db->insert('s_ms', [
            's_name' => $msName,
            's_description' => $ms['description'] ?? '',
            's_type' => $msType,
            's_scope' => $msScope,
            's_default_route_id' => 0,
            's_tpl_name' => $ms['template'] ?? 'default.tpl.php',
        ], [
            'createdby' => $this->runData['entity']['id'] ?? 1,
            'livestatus' => '1',
            'space_id' => 0,
            'wf_status' => 0,
        ]);
        if (!$msId) {
            throw new \Exception('Failed to create microservicelet.');
        }

        $routeIds = [];
        foreach ($routes as $idx => $route) {
            $path = $this->sanitizeRoute($route['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $method = strtoupper(trim($route['method'] ?? 'GET'));
            if (!in_array($method, ['GET','POST','PUT','PATCH','DELETE'], true)) {
                $method = 'GET';
            }
            $serviceDefinition = json_encode(['method' => $method], JSON_UNESCAPED_SLASHES);
            $rid = $this->db->insert('s_msroute', [
                's_ms_id' => $msId,
                's_name' => $path,
                's_description' => $route['description'] ?? '',
                's_degree' => 0,
                's_entity_scope' => $this->sanitizeRouteScope($route['entity_scope'] ?? ''),
                's_service_definition' => $serviceDefinition,
            ], [
                'createdby' => $this->runData['entity']['id'] ?? 1,
                'livestatus' => '1',
                'space_id' => 0,
                'wf_status' => 0,
            ]);
            if ($rid) {
                $routeIds[] = $rid;
                $routeKey = $this->getRouteFileKey(['id' => $rid, 's_name' => $path], $msType);
                $this->ensureRouteFiles($msName, $routeKey);
                $this->inheritMsBindingsToRoute($msId, (int)$rid);
            }
            if ($idx === 0 && $rid) {
                $this->db->update('s_ms', ['s_default_route_id' => $rid], ['id' => $msId]);
            }
        }

        $controllerIds = [];
        foreach ($controllers as $controller) {
            $cName = $this->sanitizeControllerName($controller['name'] ?? '');
            if ($cName === '') {
                continue;
            }
            $cType = strtoupper($controller['type'] ?? 'BL');
            $cid = $this->db->insert('s_mscontroller', [
                's_ms_id' => $msId,
                's_name' => $cName,
                's_description' => $controller['description'] ?? '',
                's_type' => $cType === 'DM' ? 'DM' : 'BL',
                's_definition' => json_encode($controller['fields'] ?? []),
            ], [
                'createdby' => $this->runData['entity']['id'] ?? 1,
                'livestatus' => '1',
                'space_id' => 0,
                'wf_status' => 0,
            ]);
            if ($cid) {
                $controllerIds[] = $cid;
            }
            if ($cid && $cType === 'DM') {
                $this->createDataModelTable($cName, $controller['fields'] ?? []);
            }
            if ($cid && $cType === 'BL') {
                $this->createBlSkeleton($msName, $cName);
            }
        }

        $navResult = $this->createNavFromPlan($msId, $msName, $nav);

        return [
            'ms_id' => $msId,
            'routes_created' => $routeIds,
            'controllers_created' => $controllerIds,
            'navset' => $navResult['navset'] ?? null,
            'nav_items' => $navResult['nav_items'] ?? [],
        ];
    }

    private function sanitizeMsName(string $name): string {
        $name = strtolower(trim($name));
        $name = str_replace(' ', '-', $name);
        $name = preg_replace('/[^a-z0-9\\-]/', '', $name);
        return $this->truncate($name, 255);
    }

    private function sanitizeMsType(string $type): string {
        $type = strtoupper(trim($type));
        $allowed = ['STA', 'DYN', 'SSR', 'API'];
        if (!in_array($type, $allowed, true)) {
            return 'DYN';
        }
        return $type;
    }

    private function sanitizeMsScope(string $scope): string {
        $scope = strtolower(trim($scope));
        $allowed = ['global', 'platform', 'workspace'];
        if (!in_array($scope, $allowed, true)) {
            return 'platform';
        }
        return $scope;
    }

    private function sanitizeRouteScope(string $scope): string {
        $scope = strtoupper(trim($scope));
        $allowed = ['UA', 'U', 'A'];
        if (!in_array($scope, $allowed, true)) {
            return 'UA';
        }
        return $scope;
    }

    private function sanitizeRoute(string $path): string {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        $path = ltrim($path, '/');
        $path = preg_replace('/[^a-zA-Z0-9\\-\\/]/', '', $path);
        return $this->truncate($path, 255);
    }

    private function getRouteFileKey(array $routeRow, string $msType): string {
        if (strtoupper($msType) === 'DYN') {
            return (string)($routeRow['s_name'] ?? $routeRow['id'] ?? '');
        }
        return (string)($routeRow['id'] ?? '');
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

    private function renameRouteFilesByKey(string $msName, string $oldKey, string $newKey): array {
        $result = ['renamed' => 0, 'overwritten' => 0, 'created' => 0, 'missing' => 0];
        if ($msName === '' || $oldKey === '' || $newKey === '' || $oldKey === $newKey) {
            return $result;
        }
        $msDir = rtrim($this->runData['config']['dir']['ms'] ?? '', '/') . '/' . $msName;
        if (!is_dir($msDir)) {
            return $result;
        }
        foreach (['php', 'pagepart.php', 'prepart.php', 'postpart.php'] as $suffix) {
            $oldPath = $msDir . '/route.' . $oldKey . '.' . $suffix;
            $newPath = $msDir . '/route.' . $newKey . '.' . $suffix;
            if (file_exists($oldPath)) {
                if (file_exists($newPath)) {
                    @unlink($newPath);
                    $result['overwritten']++;
                }
                if (@rename($oldPath, $newPath)) {
                    $result['renamed']++;
                }
                continue;
            }
            $result['missing']++;
            if (!file_exists($newPath)) {
                file_put_contents($newPath, '');
                $result['created']++;
            }
        }
        return $result;
    }

    private function rewriteDynLinksInFile(string $filePath, array $rules): int {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return 0;
        }
        $content = file_get_contents($filePath);
        if ($content === false) {
            return 0;
        }
        $total = 0;
        foreach ($rules as $rule) {
            if ($rule['type'] === 'regex') {
                $count = 0;
                $content = preg_replace($rule['pattern'], $rule['replacement'], $content, -1, $count);
                $total += $count;
            } else {
                $count = substr_count($content, $rule['search']);
                if ($count > 0) {
                    $content = str_replace($rule['search'], $rule['replace'], $content);
                    $total += $count;
                }
            }
        }
        if ($total > 0) {
            file_put_contents($filePath, $content);
        }
        return $total;
    }

    private function buildDynRewriteRules(string $msName, int $routeId, string $routeName, ?string $workspacePrefix): array {
        $msName = trim($msName);
        $routeName = trim($routeName);
        if ($msName === '' || $routeName === '' || $routeId <= 0) {
            return [];
        }
        $prefixSegment = $workspacePrefix ? '/' . trim($workspacePrefix, "/ \t\n\r\0\x0B") : '';
        $workspaceReplacement = $prefixSegment . '/{space_name}/' . $msName . '/' . $routeName . '/';
        $routeIdStr = (string)$routeId;
        $escapedMs = preg_quote($msName, '~');
        $escapedId = preg_quote($routeIdStr, '~');
        $workspacePattern = '~/' . $escapedMs . '/' . $escapedId . '/(?:\\$[A-Za-z_][A-Za-z0-9_]*|\\{[A-Za-z_][A-Za-z0-9_]*\\}|[a-fA-F0-9-]{36})/~';
        return [
            [
                'type' => 'regex',
                'pattern' => $workspacePattern,
                'replacement' => $workspaceReplacement,
            ],
            [
                'type' => 'string',
                'search' => '/' . $msName . '/' . $routeIdStr . '/',
                'replace' => '/' . $msName . '/' . $routeName . '/',
            ],
        ];
    }

    private function sanitizeControllerName(string $name): string {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9\\_]/', '', $name);
        return $this->truncate($name, 25);
    }

    private function deriveControllerRegistrationName(string $fileStem): string {
        $normalized = strtolower(trim($fileStem));
        $normalized = preg_replace('/[^a-z0-9\\_]/', '', $normalized);
        if ($normalized === '') {
            return '';
        }
        if (strlen($normalized) <= 25) {
            return $normalized;
        }

        $prefix = substr($normalized, 0, 17);
        $suffix = substr(hash('crc32b', $normalized), 0, 8);
        return $prefix . $suffix;
    }

    private function isRegisterableControllerFileStem(string $fileStem, string $controllerName): bool {
        $fileStem = trim($fileStem);
        if ($fileStem === '' || $controllerName === '') {
            return false;
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $fileStem)) {
            return false;
        }
        return true;
    }

    private function resolveBusinessClassFileCandidates(array $controller): array {
        $sourceFile = trim((string)($controller['s_source_file'] ?? ''));
        if ($sourceFile !== '') {
            return [basename($sourceFile)];
        }

        $name = trim((string)($controller['s_name'] ?? ''));
        if ($name === '') {
            return [];
        }

        $candidates = [$name . '.cls.php'];
        $legacy = ucfirst($name) . '.cls.php';
        if ($legacy !== $candidates[0]) {
            $candidates[] = $legacy;
        }
        return $candidates;
    }

    private function createDataModelTable(string $controllerName, array $fields): void {
        $tableName = 'a_' . $controllerName;
        $columns = [
            "`id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY",
            "`uid` char(36) DEFAULT NULL",
            "`livestatus` enum('0','1','2','3') DEFAULT '1'",
            "`versioncode` int DEFAULT NULL",
            "`wf_status` int NOT NULL DEFAULT 0",
            "`space_id` bigint NOT NULL DEFAULT 0",
            "`createdby` bigint DEFAULT NULL",
            "`createstamp` datetime DEFAULT NULL",
            "`updatedby` bigint DEFAULT NULL",
            "`updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP",
        ];
        foreach ($fields as $field) {
            $fname = preg_replace('/[^a-z0-9_]/', '', strtolower($field['name'] ?? ''));
            if ($fname === '') {
                continue;
            }
            $ftype = strtolower($field['type'] ?? 'string');
            $sqlType = match ($ftype) {
                'int','integer' => 'INT',
                'bigint' => 'BIGINT',
                'bool','boolean' => 'TINYINT(1)',
                'decimal','number' => 'DECIMAL(12,2)',
                'text' => 'TEXT',
                'datetime','timestamp' => 'DATETIME',
                default => 'VARCHAR(255)',
            };
            $columns[] = "`{$fname}` {$sqlType} DEFAULT NULL";
        }
        $ddl = sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (\n%s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
            $tableName,
            implode(",\n", $columns)
        );
        $this->db->query($ddl);
    }

    private function createBlSkeleton(string $msName, string $controllerName, string $sourceFile = '', string $className = ''): void {
        $msDir = rtrim($this->runData['config']['dir']['ms'] ?? '', '/') . '/' . $msName;
        if (!is_dir($msDir)) {
            @mkdir($msDir, 0777, true);
        }
        $fileName = trim($sourceFile) !== '' ? basename($sourceFile) : ($controllerName . '.cls.php');
        $file = $msDir . '/' . $fileName;
        if (file_exists($file)) {
            return;
        }
        $className = trim($className) !== '' ? preg_replace('/[^A-Za-z0-9_]/', '', $className) : ucfirst($controllerName);
        $content = "<?php\nnamespace Ms\\" . ucfirst($msName) . ";\n\nclass {$className} {\n    public function handle(array \$params = []) {\n        // TODO: implement business logic\n        return ['status' => 'ok'];\n    }\n}\n";
        @file_put_contents($file, $content);
    }

    private function loadNavsets(): array {
        return $this->runData['db']->select('s_navset', [], true, ['s_name' => 'ASC']);
    }

    private function createNavFromPlan(int $msId, string $msName, array $navPlan): array {
        if (empty($navPlan)) {
            return ['navset' => null, 'nav_items' => []];
        }
        $navsetId = null;
        $navset = null;
        $existingNavsetId = isset($navPlan[0]['navset_id']) ? (int)$navPlan[0]['navset_id'] : 0;
        if ($existingNavsetId > 0) {
            $navset = $this->runData['db']->select('s_navset', ['id' => $existingNavsetId], true);
            if (!empty($navset)) {
                $navsetId = $existingNavsetId;
            }
        }
        if (!$navsetId) {
            $navsetName = 'Nav - ' . $msName;
            // Minimal insert for schema: s_name, s_description, s_sort_order
            $state = [
                'livestatus' => '1',
                'createdby' => $this->runData['entity']['id'] ?? 1,
                'space_id' => 0,
                'wf_status' => 0,
            ];
            $navsetId = $this->runData['db']->insert('s_navset', [
                's_name' => $this->truncate($navsetName, 255),
                's_description' => '',
                's_sort_order' => 0,
            ], $state);
            $navset = $this->runData['db']->select('s_navset', ['id' => $navsetId], true)[0] ?? null;
        }

        $navItems = [];
        foreach ($navPlan as $item) {
            $label = trim($item['label'] ?? '');
            $path = trim($item['path'] ?? '');
            if ($label === '' || $path === '' || !$navsetId) {
                continue;
            }
            $href = '/' . $msName . '/' . ltrim($path, '/');
            $navPayload = [
                's_navset_id' => $navsetId,
                's_name' => $this->truncate($label, 255),
                's_href' => $this->truncate($href, 255),
                's_sort_order' => 0,
                's_parent_id' => 0,
            ];
            $navItems[] = $this->runData['db']->insert('s_nav', $navPayload, [
                'livestatus' => '1',
                'createdby' => $this->runData['entity']['id'] ?? 1,
                'space_id' => 0,
                'wf_status' => 0,
            ]);
        }

        return [
            'navset' => $navset,
            'nav_items' => $navItems,
        ];
    }

    private function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    private function truncate(string $text, int $max): string {
        if ($max <= 0) {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max);
        }
        return substr($text, 0, $max);
    }

    private function columnExists(string $table, string $column): bool {
        static $cache = [];
        $key = $table . '::' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $rows = $this->runData['db']->query(
                "SHOW COLUMNS FROM {$table} LIKE :col",
                [':col' => $column]
            );
            $cache[$key] = !empty($rows);
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }
        return $cache[$key];
    }

    private function filterRestricted(array $msList): array {
        $filtered = [];
        foreach ($msList as $ms) {
            $id = (int)($ms['id'] ?? 0);
            if ($id === 0) {
                continue;
            }
            if ($this->isRestrictedMs($id)) {
                continue;
            }
            $filtered[] = $ms;
        }
        return $filtered;
    }

    private function fetchMicroserviceByUid(string $uid): array {
        $msRows = $this->runData['db']->select('s_ms', ['uid' => $uid], true);
        if (count($msRows) != 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        if ($this->isRestrictedMs((int)$msRows[0]['id'])) {
            throw new \Exception('Access denied', 404);
        }
        return $msRows[0];
    }

    private function buildPackage(array $ms): array {
        $routes = $this->runData['db']->select('s_msroute', ['s_ms_id' => $ms['id']], true);
        $controllers = $this->runData['db']->select('s_mscontroller', ['s_ms_id' => $ms['id']], true);
        $controllerIds = array_column($controllers, 'id');
        $dataFields = [];
        if (!empty($controllerIds)) {
            $placeholders = [];
            $params = [];
            foreach ($controllerIds as $idx => $cid) {
                $ph = ':cid' . $idx;
                $placeholders[] = $ph;
                $params[$ph] = $cid;
            }
            $sql = 'SELECT * FROM s_data_field WHERE s_mscontroller_id IN (' . implode(',', $placeholders) . ')';
            $dataFields = $this->runData['db']->query($sql, $params);
        }

        $manifest = [
            'version' => 1,
            'exported_at' => date('c'),
            'microservice' => [
                'uid' => $ms['uid'],
                'name' => $ms['s_name'],
                'type' => $ms['s_type'],
            ],
        ];

        $data = [
            'ms' => $ms,
            'routes' => $routes,
            'controllers' => $controllers,
            'data_fields' => $dataFields,
        ];

        $msDir = rtrim($this->runData['config']['dir']['ms'] ?? '', '/') . '/' . $ms['s_name'];
        $files = is_dir($msDir) ? $msDir : null;

        return ['manifest' => $manifest, 'data' => $data, 'files' => $files];
    }

    private function createZip(string $msName, ?string $msDir, array $manifest, array $data): string {
        if (!class_exists(\ZipArchive::class)) {
            throw new \Exception('ZipArchive is required for export.');
        }
        $this->ensureDir($this->zipTempDir);
        $zipPath = $this->zipTempDir . '/ms-export-' . $msName . '-' . time() . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Unable to create zip.');
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($msDir && is_dir($msDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($msDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                $filePath = $file->getPathname();
                $local = 'ms/' . $msName . '/' . substr($filePath, strlen($msDir) + 1);
                if ($file->isDir()) {
                    $zip->addEmptyDir($local);
                } else {
                    $zip->addFile($filePath, $local);
                }
            }
        }

        $zip->close();
        return $zipPath;
    }

    private function ensureDir(string $dir): void {
        if (is_dir($dir)) {
            return;
        }
        @mkdir($dir, 0775, true);
    }

    private function streamFile(string $path, string $downloadName): void {
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
        readfile($path);
    }

    private function handleImportUpload(): array {
        if (!isset($_FILES['package']) || ($_FILES['package']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['status' => 'danger', 'message' => 'Please upload a package file.'];
        }
        if (!class_exists(\ZipArchive::class)) {
            return ['status' => 'danger', 'message' => 'ZipArchive is required for import.'];
        }

        $tmpName = $_FILES['package']['tmp_name'];
        $zip = new \ZipArchive();
        if ($zip->open($tmpName) !== true) {
            return ['status' => 'danger', 'message' => 'Invalid package archive.'];
        }

        $manifest = json_decode($zip->getFromName('manifest.json') ?: '', true);
        $data = json_decode($zip->getFromName('data.json') ?: '', true);
        if (!is_array($manifest) || !is_array($data)) {
            $zip->close();
            return ['status' => 'danger', 'message' => 'Package manifest or data missing/invalid.'];
        }

        $extractDir = $this->zipTempDir . '/ms-import-' . uniqid();
        $this->ensureDir($extractDir);
        $zip->extractTo($extractDir);
        $zip->close();

        try {
            $this->restoreMicroservice($data, $extractDir);
        } catch (\Throwable $e) {
            return ['status' => 'danger', 'message' => 'Import failed: ' . $e->getMessage()];
        } finally {
            $this->cleanupDir($extractDir);
        }

        return ['status' => 'success', 'message' => 'Microservicelet imported successfully.'];
    }

    private function restoreMicroservice(array $data, string $extractDir): void {
        $msData = $data['ms'] ?? [];
        if (empty($msData['s_name'])) {
            throw new \Exception('Microservice name missing in package.');
        }

        $strategy = $this->runData['request']->post['collision_strategy'] ?? 'abort';
        $msName = $msData['s_name'];
        $existing = $this->runData['db']->select('s_ms', ['s_name' => $msName], true);
        if (!empty($existing)) {
            if ($strategy === 'rename') {
                $msName = $this->generateUniqueName($msName);
            } else {
                throw new \Exception('Microservicelet with the same name already exists.');
            }
        }

        // Insert microservice (without id/uid)
        $msInsert = $msData;
        unset($msInsert['id'], $msInsert['uid']);
        $msInsert['s_name'] = $msName;
        $msInsert['uid'] = $this->runData['db']->generateUuidV4();
        $msInsert['s_default_route_id'] = 0;
        $newMsId = $this->runData['db']->insert('s_ms', $msInsert);
        if (!$newMsId) {
            throw new \Exception('Failed to create Microservicelet.');
        }

        // Insert routes and map IDs (also map by name)
        $routeMapById = [];
        $routeMapByName = [];
        $routes = $data['routes'] ?? [];
        foreach ($routes as $route) {
            $oldId = (int)($route['id'] ?? 0);
            $oldName = $route['s_name'] ?? '';
            unset($route['id'], $route['uid']);
            $route['s_ms_id'] = $newMsId;
            $newId = $this->runData['db']->insert('s_msroute', $route);
            if ($newId) {
                $routeMapById[$oldId] = $newId;
                if ($oldName !== '') {
                    $routeMapByName[$oldName] = $newId;
                }
                $routeKey = $this->getRouteFileKey(
                    ['id' => $newId, 's_name' => $oldName],
                    $msInsert['s_type'] ?? 'STA'
                );
                $this->ensureRouteFiles($msName, $routeKey);
                $this->inheritMsBindingsToRoute($newMsId, (int)$newId);
            }
        }

        // Update default route
        $oldDefault = (int)($msData['s_default_route_id'] ?? 0);
        if ($oldDefault && isset($routeMapById[$oldDefault])) {
            $this->runData['db']->update('s_ms', ['s_default_route_id' => $routeMapById[$oldDefault]], ['id' => $newMsId]);
        }

        // Insert controllers and map IDs
        $controllerMapById = [];
        $controllerMapByName = [];
        $controllers = $data['controllers'] ?? [];
        foreach ($controllers as $controller) {
            $oldId = (int)($controller['id'] ?? 0);
            $oldName = $controller['s_name'] ?? '';
            unset($controller['id'], $controller['uid']);
            $controller['s_ms_id'] = $newMsId;
            $newId = $this->runData['db']->insert('s_mscontroller', $controller);
            if ($newId) {
                $controllerMapById[$oldId] = $newId;
                if ($oldName !== '') {
                    $controllerMapByName[$oldName] = $newId;
                }
            }
        }

        // Insert data fields with remapped controller IDs
        $dataFields = $data['data_fields'] ?? [];
        foreach ($dataFields as $field) {
            $oldControllerId = (int)($field['s_mscontroller_id'] ?? 0);
            $newControllerId = $controllerMapById[$oldControllerId] ?? null;
            if (!$newControllerId && isset($field['controller_name'])) {
                $newControllerId = $controllerMapByName[$field['controller_name']] ?? null;
            }
            if (!$newControllerId) {
                continue;
            }
            unset($field['id']);
            $field['s_mscontroller_id'] = $newControllerId;
            $this->runData['db']->insert('s_data_field', $field);
        }

        // Restore filesystem
        $msFolder = $extractDir . '/ms/' . $msData['s_name'];
        if (is_dir($msFolder)) {
            $targetDir = rtrim($this->runData['config']['dir']['ms'] ?? '', '/') . '/' . $msName;
            if (is_dir($targetDir)) {
                throw new \Exception('Target microservice directory already exists.');
            }
            $this->copyDir($msFolder, $targetDir);

            // Rename route files to new IDs
            foreach ($routeMapById as $oldId => $newId) {
                $this->renameRouteFiles($targetDir, $oldId, $newId, $msData['s_type'] ?? 'STA');
            }
        }
    }

    private function generateUniqueName(string $base): string {
        $suffix = 1;
        $candidate = $base . '-' . $suffix;
        while (!empty($this->runData['db']->select('s_ms', ['s_name' => $candidate], true))) {
            $suffix++;
            $candidate = $base . '-' . $suffix;
        }
        return $candidate;
    }

    private function copyDir(string $src, string $dst): void {
        $this->ensureDir($dst);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $dst . '/' . substr($item->getPathname(), strlen($src) + 1);
            if ($item->isDir()) {
                $this->ensureDir($target);
            } else {
                $this->ensureDir(dirname($target));
                @copy($item->getPathname(), $target);
            }
        }
    }

    private function renameRouteFiles(string $dir, int $oldId, int $newId, string $msType = 'STA'): void {
        if (strtoupper($msType) === 'DYN') {
            return;
        }
        $patterns = glob($dir . '/route.' . $oldId . '.*');
        if (!$patterns) {
            return;
        }
        foreach ($patterns as $file) {
            $newName = str_replace('route.' . $oldId . '.', 'route.' . $newId . '.', basename($file));
            @rename($file, dirname($file) . '/' . $newName);
        }
    }

    private function cleanupDir(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    private function loadTestHooks(string $scope, array $ids): array {
        if (!$this->testplanClass || empty($ids)) return [];
        $planRows = [];
        try {
            $idsIn = implode(',', array_map('intval', $ids));
            if ($scope === 'microservice') {
                $planRows = $this->runData['db']->query("SELECT * FROM s_test_plan WHERE s_scope='microservice' AND s_ms_id IN ({$idsIn}) AND livestatus <> '0' LIMIT 50");
            }
        } catch (\Throwable $e) {
            if ($this->errorHandler) $this->errorHandler->handleException($e);
        }
        return is_array($planRows) ? $planRows : [];
    
}
}

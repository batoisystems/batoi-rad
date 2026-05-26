<?php
namespace RadAdmin;

class Aiassist {
    use AiAssistAware;

    private $runData = [];
    private $db;
    private $errorHandler;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
    }

    public function view() {
        $this->runData['route']['h1'] = 'Batoi Intelligence';
        $this->runData['route']['meta_title'] = 'Batoi Intelligence';
        $this->runData['route']['breadcrumb'] = ['Batoi Intelligence' => null];

        $microservices = $this->filterRestrictedMs($this->db->select('s_ms', [], true, ['s_name' => 'ASC']));
        $msMap = [];
        foreach ($microservices as $ms) {
            if (isset($ms['id'])) {
                $msMap[$ms['id']] = $ms['s_name'] ?? '';
            }
        }

        $routes = $this->filterRoutesByMs(
            $this->db->select('s_msroute', [], true, ['s_name' => 'ASC']),
            $microservices
        );
        foreach ($routes as &$route) {
            $route['ms_name'] = $msMap[$route['s_ms_id']] ?? '';
        }
        unset($route);

        $controllers = $this->filterControllersByMs(
            $this->db->select('s_mscontroller', [], true, ['s_name' => 'ASC']),
            $microservices
        );
        foreach ($controllers as &$controller) {
            $controller['ms_name'] = $msMap[$controller['s_ms_id']] ?? '';
        }
        unset($controller);

        $this->runData['data']['microservices'] = $microservices;
        $this->runData['data']['routes'] = $routes;
        $this->runData['data']['controllers'] = $controllers;
        $this->runData['data']['upgrades'] = $this->listUpgradeScripts();
        $this->runData['data']['templates'] = $this->listThemeTemplates();
        $this->runData['data']['initial_prompt'] = trim($this->runData['request']->get['prompt'] ?? '');
        $this->runData['data']['context_title'] = trim($this->runData['request']->get['context_title'] ?? '');
        $this->runData['data']['context_url'] = trim($this->runData['request']->get['context_url'] ?? '');

        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Use Batoi Intelligence to analyze RAD assets and draft responses with contextual code snippets.';
        }

        return $this->runData;
    }

    public function context() {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            $payload = $this->runData['request']->post ?? [];
        }

        $type = $payload['type'] ?? '';
        $identifier = trim($payload['id'] ?? '');
        if ($type === '' || $identifier === '') {
            echo json_encode(['error' => 'Missing context selector.']);
            return;
        }

        try {
            $result = $this->buildContextAttachment($type, $identifier);
        } catch (\Throwable $e) {
            $this->errorHandler->logError('AI Context error: '.$e->getMessage());
            $result = ['error' => 'Unable to load the selected context.'];
        }

        echo json_encode($result);
    }

    public function chat() {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            $payload = $this->runData['request']->post ?? [];
        }

        $prompt = trim($payload['prompt'] ?? '');
        $attachments = $payload['attachments'] ?? [];
        if ($prompt === '') {
            echo json_encode(['error' => 'Please enter a question or prompt.']);
            return;
        }

        $contextText = '';
        $attachmentMeta = [];
        foreach ($attachments as $attachment) {
            $label = $attachment['label'] ?? ($attachment['type'] ?? 'Attachment');
            $snippet = trim($attachment['content'] ?? '');
            if ($snippet === '') {
                continue;
            }
            $contextText .= sprintf("=== %s (%s) ===\n%s\n\n", $label, $attachment['type'] ?? 'context', $snippet);
            $attachmentMeta[] = $label;
        }

        $composedPrompt = $contextText . "User Question:\n" . $prompt;

        try {
            $service = $this->getAiAssistService();
            $result = $service->suggest($composedPrompt, 'generic', [
                'attachments' => empty($attachmentMeta) ? 'none' : implode(', ', $attachmentMeta),
            ]);
        } catch (\Throwable $e) {
            $result = ['error' => 'AI service is currently unavailable.'];
        }

        echo json_encode($result);
    }

    private function buildContextAttachment(string $type, string $id): array {
        switch ($type) {
            case 'microservice':
                $rows = $this->db->select('s_ms', ['id' => $id], true);
                if (count($rows) !== 1) {
                    return ['error' => 'Microservicelet not found.'];
                }
                if (\RadAdmin\VisibilityHelper::isRestrictedMs((int)$rows[0]['id'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [])) {
                    return ['error' => 'Microservicelet not accessible.'];
                }
                $row = $rows[0];
                $accessScope = strtolower($row['s_scope'] ?? '') === 'global' ? 'public' : 'private';
                $content = sprintf(
                    "Name: %s\nDescription: %s\nAccess Scope: %s\nDefinition: %s",
                    $row['s_name'] ?? '',
                    $row['s_description'] ?? '',
                    $accessScope,
                    $row['s_definition'] ?? ''
                );
                return [
                    'type' => 'microservice',
                    'id' => $id,
                    'label' => $row['s_name'] ?? 'Microservicelet',
                    'content' => $content,
                ];
            case 'route':
                $rows = $this->db->select('s_msroute', ['id' => $id], true);
                if (count($rows) !== 1) {
                    return ['error' => 'Route not found.'];
                }
                $row = $rows[0];
                if (!empty($row['s_ms_id']) && \RadAdmin\VisibilityHelper::isRestrictedMs((int)$row['s_ms_id'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [])) {
                    return ['error' => 'Route not accessible.'];
                }
                $snippet = $this->readRouteFiles($row);
                return [
                    'type' => 'route',
                    'id' => $id,
                    'label' => $row['s_name'] ?? 'Route',
                    'content' => $snippet,
                ];
            case 'controller':
                $rows = $this->db->select('s_mscontroller', ['id' => $id], true);
                if (count($rows) !== 1) {
                    return ['error' => 'Controller not found.'];
                }
                $row = $rows[0];
                if (!empty($row['s_ms_id']) && \RadAdmin\VisibilityHelper::isRestrictedMs((int)$row['s_ms_id'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [])) {
                    return ['error' => 'Controller not accessible.'];
                }
                $content = $this->readControllerFile($row);
                return [
                    'type' => 'controller',
                    'id' => $id,
                    'label' => $row['s_name'] ?? 'Controller',
                    'content' => $content,
                ];
            case 'upgrade':
                $content = $this->readUpgrade($id);
                if ($content === null) {
                    return ['error' => 'Upgrade script not found.'];
                }
                return [
                    'type' => 'upgrade',
                    'id' => $id,
                    'label' => $id,
                    'content' => $content,
                ];
            case 'theme':
                $content = $this->readThemeTemplate($id);
                if ($content === null) {
                    return ['error' => 'Template not found.'];
                }
                return [
                    'type' => 'theme',
                    'id' => $id,
                    'label' => $id,
                    'content' => $content,
                ];
            default:
                return ['error' => 'Unknown context type.'];
        }
    }

    private function listUpgradeScripts(): array {
        $dir = rtrim($this->runData['config']['dir']['rad'], '/') . '/upgrades';
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.php') ?: [];
        $list = [];
        foreach ($files as $file) {
            $list[] = basename($file, '.php');
        }
        sort($list);
        return $list;
    }

    private function listThemeTemplates(): array {
        $dir = rtrim($this->runData['config']['dir']['theme'], '/');
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.tpl.php') ?: [];
        $list = [];
        foreach ($files as $file) {
            $list[] = basename($file, '.tpl.php');
        }
        sort($list);
        return $list;
    }

    private function readRouteFiles(array $routeRow): string {
        $msId = $routeRow['s_ms_id'] ?? 0;
        $ms = $this->db->select('s_ms', ['id' => $msId], true);
        $msName = $ms[0]['s_name'] ?? '';
        $routeId = $routeRow['id'];
        $baseDir = rtrim($this->runData['config']['dir']['ms'], '/') . '/' . $msName;
        $parts = [
            'php' => 'load',
            'pagepart.php' => 'pagepart',
            'prepart.php' => 'prepart',
            'postpart.php' => 'postpart'
        ];
        $snippet = sprintf(
            "Route: %s (Microservicelet: %s)\nScope: %s\n\n",
            $routeRow['s_name'] ?? '',
            $msName,
            $routeRow['s_entity_scope'] ?? 'U'
        );
        foreach ($parts as $suffix => $label) {
            $path = sprintf('%s/route.%s.%s', $baseDir, $routeId, $suffix);
            if (is_file($path)) {
                $snippet .= sprintf("--- %s ---\n%s\n\n", strtoupper($label), file_get_contents($path));
            }
        }
        return $snippet;
    }

    private function readControllerFile(array $controllerRow): string {
        $msId = $controllerRow['s_ms_id'] ?? 0;
        $ms = $this->db->select('s_ms', ['id' => $msId], true);
        $msName = $ms[0]['s_name'] ?? '';
        $branchService = new \Core\Sys\BranchService(
            $this->db,
            $this->runData['config'] ?? [],
            $this->runData['entity'] ?? [],
            $this->runData['request'] ?? null
        );
        $file = $branchService->getControllerFilePath($msName, (string)($controllerRow['s_name'] ?? ''), 'live', true);
        if (!is_file($file)) {
            return 'Controller file missing.';
        }
        return file_get_contents($file);
    }

    private function readUpgrade(string $id): ?string {
        $file = rtrim($this->runData['config']['dir']['rad'], '/') . '/upgrades/' . $id . '.php';
        return is_file($file) ? file_get_contents($file) : null;
    }

    private function readThemeTemplate(string $id): ?string {
        $file = rtrim($this->runData['config']['dir']['theme'], '/') . '/' . $id . '.tpl.php';
        return is_file($file) ? file_get_contents($file) : null;
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

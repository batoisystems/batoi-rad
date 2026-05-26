<?php
namespace RadAdmin;

use Core\Sys\NavService;
use Core\Sys\PrivilegeService;
use RuntimeException;

class Nav {
    private array $runData;
    private NavService $navService;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->navService = new NavService($runData['db'], $runData['errorHandler']);
    }

    public function view() {
        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Design structured nav sets (top bar, sidebar, footer) and attach role visibility before binding them into microservicelets.';
        }
        $this->runData['route']['h1'] = 'Navigation';
        $this->runData['route']['meta_title'] = 'Navigation';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/home/view';

        $selectedNavsetId = isset($this->runData['route']['pathparts'][3]) ? (int)$this->runData['route']['pathparts'][3] : 0;
        $navsets = $this->navService->listNavSets(['livestatus' => '1']);
        $filters = [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
        ];
        if ($filters['q'] !== '') {
            $needle = strtolower($filters['q']);
            $navsets = array_values(array_filter($navsets, function ($row) use ($needle) {
                $blob = strtolower(($row['s_name'] ?? '') . ' ' . ($row['s_description'] ?? ''));
                return strpos($blob, $needle) !== false;
            }));
        }
        if ($selectedNavsetId === 0 && !empty($navsets)) {
            $selectedNavsetId = (int)$navsets[0]['id'];
        }
        $navitems = $selectedNavsetId > 0 ? $this->navService->listNavItems($selectedNavsetId) : [];

        $this->runData['data']['navsets'] = $navsets;
        $this->runData['data']['selected_navset'] = $selectedNavsetId;
        $this->runData['data']['navitems'] = $navitems;
        $this->runData['data']['locations'] = $this->locationMap();
        $this->runData['data']['scopes'] = $this->scopeMap();
        $this->runData['data']['microservices'] = $this->loadMicroservices();
        $this->runData['data']['roles'] = $this->loadRoles();
        $this->runData['data']['filters'] = $filters;

        return $this->runData;
    }

    public function add() {
        $this->runData['request']->setAlert('Use the Navigation Studio page to manage menu entries.', 'info');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/nav/view');
        exit;
    }

    public function addnavset() {
        $this->runData['request']->setAlert('Use the “Add Nav Set” button on the Navigation Studio page.', 'info');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/nav/view');
        exit;
    }

    public function savenavset() {
        try {
            $payload = $this->jsonPayload();
            $navset = $this->navService->saveNavSet($payload, (int)($this->runData['entity']['id'] ?? 0));
            $this->logNavEvent('navset_save', (int)($navset['id'] ?? 0), [
                'name' => $navset['s_name'] ?? ($payload['s_name'] ?? ''),
                'ms_id' => $navset['s_ms_id'] ?? ($payload['s_ms_id'] ?? null),
            ]);
            $this->notifyNavEvent('navset_save', (int)($navset['id'] ?? 0), [
                'name' => $navset['s_name'] ?? ($payload['s_name'] ?? ''),
                'ms_id' => $navset['s_ms_id'] ?? ($payload['s_ms_id'] ?? null),
            ]);
            $this->respondJson(['success' => true, 'navset' => $navset]);
        } catch (\Throwable $e) {
            $this->respondJson(['success' => false, 'message' => $e->getMessage()], $this->resolveStatus($e));
        }
    }

    public function deletenavset() {
        try {
            $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
            if (!$priv->can('delete')) {
                throw new RuntimeException('Access denied.', 403);
            }
            $payload = $this->jsonPayload();
            $navsetId = (int)($payload['navset_id'] ?? $payload['id'] ?? 0);
            if ($navsetId <= 0) {
                throw new RuntimeException('Invalid nav set reference.');
            }
            $this->navService->archiveNavSet($navsetId, (int)($this->runData['entity']['id'] ?? 0), '2');
            $this->logNavEvent('navset_archive', $navsetId, []);
            $this->notifyNavEvent('navset_archive', $navsetId, []);
            $this->respondJson(['success' => true]);
        } catch (\Throwable $e) {
            $this->respondJson(['success' => false, 'message' => $e->getMessage()], $this->resolveStatus($e));
        }
    }

    public function saveitem() {
        try {
            $payload = $this->jsonPayload();
            if (empty($payload['s_navset_id']) && empty($payload['navset_id'])) {
                $payload['s_navset_id'] = $this->runData['route']['pathparts'][3] ?? 0;
            }
            $item = $this->navService->saveNavItem($payload, (int)($this->runData['entity']['id'] ?? 0));
            $this->logNavEvent('navitem_save', (int)($item['s_navset_id'] ?? 0), [
                'item_id' => $item['id'] ?? null,
                'name' => $item['s_name'] ?? '',
            ]);
            $this->notifyNavEvent('navitem_save', (int)($item['s_navset_id'] ?? 0), [
                'item_id' => $item['id'] ?? null,
                'name' => $item['s_name'] ?? '',
            ]);
            $this->respondJson(['success' => true, 'item' => $item]);
        } catch (\Throwable $e) {
            $this->respondJson(['success' => false, 'message' => $e->getMessage()], $this->resolveStatus($e));
        }
    }

    public function archive() {
        try {
            $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
            if ($priv->role() === 'developer' || !$priv->can('route_edit')) {
                throw new RuntimeException('Access denied.', 403);
            }
            $payload = $this->jsonPayload();
            $itemId = (int)($payload['id'] ?? $payload['nav_id'] ?? 0);
            if ($itemId <= 0) {
                throw new RuntimeException('Invalid nav item reference.');
            }
            $item = $this->navService->toggleNavItem($itemId, '2', (int)($this->runData['entity']['id'] ?? 0));
            $this->logNavEvent('navitem_archive', (int)($item['s_navset_id'] ?? 0), ['item_id' => $itemId]);
            $this->notifyNavEvent('navitem_archive', (int)($item['s_navset_id'] ?? 0), ['item_id' => $itemId]);
            $this->respondJson(['success' => true, 'item' => $item]);
        } catch (\Throwable $e) {
            $this->respondJson(['success' => false, 'message' => $e->getMessage()], $this->resolveStatus($e));
        }
    }

    public function activate() {
        try {
            $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
            if ($priv->role() === 'developer' || !$priv->can('route_edit')) {
                throw new RuntimeException('Access denied.', 403);
            }
            $payload = $this->jsonPayload();
            $itemId = (int)($payload['id'] ?? $payload['nav_id'] ?? 0);
            if ($itemId <= 0) {
                throw new RuntimeException('Invalid nav item reference.');
            }
            $item = $this->navService->toggleNavItem($itemId, '1', (int)($this->runData['entity']['id'] ?? 0));
            $this->logNavEvent('navitem_activate', (int)($item['s_navset_id'] ?? 0), ['item_id' => $itemId]);
            $this->notifyNavEvent('navitem_activate', (int)($item['s_navset_id'] ?? 0), ['item_id' => $itemId]);
            $this->respondJson(['success' => true, 'item' => $item]);
        } catch (\Throwable $e) {
            $this->respondJson(['success' => false, 'message' => $e->getMessage()], $this->resolveStatus($e));
        }
    }

    public function saveorder() {
        try {
            $payload = $this->jsonPayload();
            $order = $payload['order'] ?? '';
            $navsetId = (int)($payload['navset_id'] ?? $payload['s_navset_id'] ?? 0);
            if (is_string($order)) {
                $order = array_filter(array_map('trim', explode(',', $order)));
            }
            if (!is_array($order) || empty($order)) {
                throw new RuntimeException('No sort order supplied.');
            }
            $this->navService->sortNavItems($navsetId, $order);
            $this->notifyNavEvent('navitem_sort', $navsetId, ['order_count' => count($order)]);
            $this->respondJson(['success' => true]);
        } catch (\Throwable $e) {
            $this->respondJson(['success' => false, 'message' => $e->getMessage()], $this->resolveStatus($e));
        }
    }

    public function sortnavsets() {
        try {
            $payload = $this->jsonPayload();
            $order = $payload['order'] ?? [];
            if (is_string($order)) {
                $order = array_filter(array_map('trim', explode(',', $order)));
            }
            if (!is_array($order) || empty($order)) {
                throw new RuntimeException('No sort order supplied.');
            }
            $this->navService->sortNavSets($order);
            $this->notifyNavEvent('navset_sort', 0, ['order_count' => count($order)]);
            $this->respondJson(['success' => true]);
        } catch (\Throwable $e) {
            $this->respondJson(['success' => false, 'message' => $e->getMessage()], $this->resolveStatus($e));
        }
    }

    private function locationMap(): array {
        return [
            'topbar' => 'Top Navigation',
            'sidebar' => 'Sidebar / Drawer',
            'footer' => 'Footer Links',
            'utility' => 'Utility / Quick Links',
        ];
    }

    private function scopeMap(): array {
        return [
            'global' => 'Global (all experiences)',
            'microservice' => 'Microservicelet specific',
            'space' => 'Workspace / SaaS scope',
        ];
    }

    private function loadMicroservices(): array {
        $msList = $this->runData['db']->select('s_ms', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        $config = $this->runData['config'] ?? [];
        $entity = $this->runData['entity'] ?? [];
        if ((new PrivilegeService($config, $entity))->role() === 'system_admin') {
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

    private function loadRoles(): array {
        return $this->runData['db']->select('s_role', ['livestatus' => '1'], true, ['s_role_name' => 'ASC']);
    }

    private function jsonPayload(): array {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $this->runData['request']->post ?? [];
        }
        return $payload;
    }

    private function respondJson(array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    private function resolveStatus(\Throwable $e): int {
        $code = (int)$e->getCode();
        return ($code >= 400 && $code < 600) ? $code : 400;
    }

    private function logNavEvent(string $action, int $navsetId, array $extra = []): void {
        try {
            $activitySvc = new \Core\Sys\ActivityService($this->runData['db']);
            $activitySvc->log([
                's_actor_id' => (int)($this->runData['entity']['id'] ?? 0) ?: null,
                's_object_type' => 'nav',
                's_object_id' => $navsetId ?: null,
                's_action' => $action,
                's_message' => sprintf('Navigation %s %s', $action, $navsetId ? ('#' . $navsetId) : ''),
                's_payload' => array_merge(['navset_id' => $navsetId], $extra),
            ]);
        } catch (\Throwable $e) {
            // swallow
        }
    }

    private function notifyNavEvent(string $action, int $navsetId, array $extra = []): void {
        try {
            $notifSvc = $this->runData['notificationService'] ?? new \Core\Sys\NotificationService($this->runData['db']);
            if (!$notifSvc instanceof \Core\Sys\NotificationService) {
                return;
            }
            $message = sprintf('Navigation %s %s', $action, $navsetId ? ('#' . $navsetId) : '');
            $notifSvc->logGlobalEvent($message, [
                'event_type' => 'nav_' . $action,
                'created_by' => (int)($this->runData['entity']['id'] ?? 0) ?: null,
                'metadata' => array_merge(['navset_id' => $navsetId], $extra),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}

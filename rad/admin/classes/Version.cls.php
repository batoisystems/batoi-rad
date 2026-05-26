<?php
namespace RadAdmin;

use Core\Sys\FileVersionService;

class Version {
    private $runData = [];
    private $versionService;

    public function __construct(array $runData) {
        $this->runData = $runData;
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
    }

    public function view() {
        $request = $this->runData['request'];
        $channel = trim($request->get['channel'] ?? '');
        $search = trim($request->get['q'] ?? '');
        $type = trim($request->get['type'] ?? '');
        $page = max(1, (int)($request->get['page'] ?? 1));
        $perPage = 20;

        $rawSnapshots = $this->versionService->listSnapshots($channel ?: null, $search);
        foreach ($rawSnapshots as &$entry) {
            $entry['type'] = $this->resolveSnapshotType($entry);
        }
        unset($entry);

        $availableTypes = array_values(array_unique(array_map(function ($entry) {
            return $entry['type'];
        }, $rawSnapshots)));
        sort($availableTypes);

        $filtered = array_values(array_filter($rawSnapshots, function ($entry) use ($type) {
            if ($type === '') {
                return true;
            }
            return ($entry['type'] ?? '') === $type;
        }));

        $total = count($filtered);
        $pages = (int)ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $snapshots = array_slice($filtered, $offset, $perPage);

        $channels = $this->getAvailableChannels();

        $this->runData['route']['h1'] = 'Version Explorer';
        $this->runData['route']['meta_title'] = 'Version Explorer';
        $this->runData['data']['snapshots'] = $snapshots;
        $this->runData['data']['channels'] = $channels;
        $this->runData['data']['types'] = $availableTypes;
        $this->runData['data']['filters'] = [
            'channel' => $channel,
            'search' => $search,
            'type' => $type,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];

        return $this->runData;
    }

    public function purge() {
        $request = $this->runData['request'];
        if (strtoupper($request->method) !== 'POST') {
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/version/view');
            exit;
        }

        $channel = trim($request->post['channel'] ?? '');
        $item = trim($request->post['item'] ?? '');
        $versions = $request->post['versions'] ?? [];

        if ($channel === '' || $item === '' || empty($versions)) {
            $this->runData['request']->setAlert('Missing purge parameters.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/version/view');
            exit;
        }

        $deleted = $this->versionService->purgeSnapshots($channel, $item, (array)$versions);
        $this->runData['request']->setAlert("Removed {$deleted} version(s).", 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/version/view?channel=' . urlencode($channel));
        exit;
    }

    public function bulk() {
        $request = $this->runData['request'];
        if (strtoupper($request->method) !== 'POST') {
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/version/view');
            exit;
        }

        $action = $request->post['bulk_action'] ?? '';
        $selected = $request->post['snapshots'] ?? [];
        if ($action === '' || empty($selected)) {
            $this->runData['request']->setAlert('Select snapshots and an action.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/version/view');
            exit;
        }

        $parsed = $this->parseSelection($selected);
        if (empty($parsed)) {
            $this->runData['request']->setAlert('Invalid selection payload.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/version/view');
            exit;
        }

        if ($action === 'purge') {
            $this->handleBulkPurge($parsed);
        } elseif ($action === 'restore') {
            $this->handleBulkRestore($parsed);
        } else {
            $this->runData['request']->setAlert('Unknown bulk action.', 'danger');
        }

        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/version/view');
        exit;
    }

    private function getAvailableChannels(): array {
        $configChannels = $this->runData['config']['versioning']['channels'] ?? [];
        $enabled = array_keys(array_filter($configChannels, function ($settings) {
            return ($settings['enabled'] ?? true) === true;
        }));
        $discovered = $this->versionService->listSnapshots(null, '');
        $channelKeys = array_map(function ($entry) {
            return $entry['channel'] ?? '';
        }, $discovered);
        $channels = array_unique(array_filter(array_merge($enabled, $channelKeys)));
        sort($channels);
        return $channels;
    }

    private function resolveSnapshotType(array $entry): string {
        $channel = $entry['channel'] ?? '';
        $item = $entry['item'] ?? '';
        switch ($channel) {
            case 'route':
                $segments = explode('/', $item);
                $type = strtolower(end($segments));
                switch ($type) {
                    case 'load': return 'Route Load';
                    case 'pagepart': return 'Route Pagepart';
                    case 'prepart': return 'Route Prepart';
                    case 'postpart': return 'Route Postpart';
                    default: return 'Route';
                }
            case 'controller':
                return 'Controller';
            case 'upgrade':
                return 'Upgrade Script';
            case 'template':
                return 'Template';
            default:
                return ucfirst($channel);
        }
    }

    private function parseSelection(array $selection): array {
        $parsed = [];
        foreach ($selection as $payload) {
            $parts = explode('|', (string)$payload);
            if (count($parts) !== 3) {
                continue;
            }
            [$channel, $item, $version] = $parts;
            if ($channel === '' || $item === '' || $version === '') {
                continue;
            }
            $parsed[] = [
                'channel' => $channel,
                'item' => $item,
                'version' => $version,
            ];
        }
        return $parsed;
    }

    private function handleBulkPurge(array $entries): void {
        $grouped = [];
        foreach ($entries as $entry) {
            $key = $entry['channel'] . '|' . $entry['item'];
            $grouped[$key]['channel'] = $entry['channel'];
            $grouped[$key]['item'] = $entry['item'];
            $grouped[$key]['versions'][] = $entry['version'];
        }

        $totalDeleted = 0;
        foreach ($grouped as $itemGroup) {
            $totalDeleted += $this->versionService->purgeSnapshots(
                $itemGroup['channel'],
                $itemGroup['item'],
                $itemGroup['versions']
            );
        }
        $this->runData['request']->setAlert("Purged {$totalDeleted} snapshot(s).", 'success');
    }

    private function handleBulkRestore(array $entries): void {
        $success = 0;
        $failed = 0;
        foreach ($entries as $entry) {
            if ($this->restoreSnapshot($entry['channel'], $entry['item'], $entry['version'])) {
                $success++;
            } else {
                $failed++;
            }
        }
        $message = "{$success} snapshot(s) restored.";
        if ($failed > 0) {
            $message .= " {$failed} snapshot(s) could not be restored.";
            $this->runData['request']->setAlert($message, 'warning');
        } else {
            $this->runData['request']->setAlert($message, 'success');
        }
    }

    private function restoreSnapshot(string $channel, string $item, string $versionId): bool {
        $version = $this->versionService->fetchVersion($channel, $item, $versionId);
        if (!$version) {
            return false;
        }
        $content = $version['content'] ?? '';
        switch ($channel) {
            case 'template':
                return $this->restoreTemplate($item, $content);
            case 'route':
                return $this->restoreRoute($item, $content);
            case 'controller':
                return $this->restoreController($item, $content);
            case 'upgrade':
                return $this->restoreUpgrade($item, $content);
            default:
                return false;
        }
    }

    private function restoreTemplate(string $templateName, string $content): bool {
        if ($templateName === '') {
            return false;
        }
        $path = rtrim($this->runData['config']['dir']['theme'], '/') . '/' . $templateName . '.tpl.php';
        $this->ensureDir(dirname($path));
        return file_put_contents($path, $content) !== false;
    }

    private function restoreRoute(string $item, string $content): bool {
        $parts = explode('/', $item);
        if (count($parts) < 3) {
            return false;
        }
        $ms = $parts[0];
        $routeSegment = $parts[1];
        $part = $parts[2];
        if (stripos($routeSegment, 'route-') !== 0) {
            return false;
        }
        $routeId = substr($routeSegment, 6);
        $base = rtrim($this->runData['config']['dir']['ms'], '/');
        $prefix = $base . '/' . $ms . '/route.' . $routeId;
        switch ($part) {
            case 'load':
                $path = $prefix . '.php';
                break;
            case 'pagepart':
                $path = $prefix . '.pagepart.php';
                break;
            case 'prepart':
                $path = $prefix . '.prepart.php';
                break;
            case 'postpart':
                $path = $prefix . '.postpart.php';
                break;
            default:
                return false;
        }
        $this->ensureDir(dirname($path));
        return file_put_contents($path, $content) !== false;
    }

    private function restoreController(string $item, string $content): bool {
        $parts = explode('/', $item);
        if (count($parts) !== 2) {
            return false;
        }
        [$ms, $controller] = $parts;
        $branchService = new \Core\Sys\BranchService(
            $this->runData['db'],
            $this->runData['config'] ?? [],
            $this->runData['entity'] ?? [],
            $this->runData['request'] ?? null
        );
        $path = $branchService->getControllerFilePath($ms, $controller, 'live', true);
        $this->ensureDir(dirname($path));
        return file_put_contents($path, $content) !== false;
    }

    private function restoreUpgrade(string $upgradeId, string $content): bool {
        if ($upgradeId === '') {
            return false;
        }
        $path = rtrim($this->runData['config']['dir']['rad'], '/') . '/upgrades/' . $upgradeId . '.php';
        $this->ensureDir(dirname($path));
        return file_put_contents($path, $content) !== false;
    }

    private function ensureDir(string $dir): void {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}

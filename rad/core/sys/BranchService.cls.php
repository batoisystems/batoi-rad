<?php
namespace Core\Sys;

class BranchService {
    private const EDITOR_BRANCH_SESSION_KEY = 'rad_admin_editor_branch';
    private const PREVIEW_SESSION_KEY = 'rad_beta_preview';
    private Database $db;
    private array $config;
    private array $entity;
    private ?Request $request;
    private FileVersionService $versionService;

    public function __construct(Database $db, array $config, array $entity = [], ?Request $request = null) {
        $this->db = $db;
        $this->config = $config;
        $this->entity = $entity;
        $this->request = $request;
        $this->versionService = new FileVersionService($config, function (): string {
            return $this->entity['fullname']
                ?? $this->entity['username']
                ?? 'RAD Admin';
        });
    }

    public function resolveEditorBranch(): string {
        $allowed = $this->canUseBeta();
        $requested = '';
        if ($this->request) {
            $requested = strtolower(trim((string)($this->request->get['branch'] ?? '')));
        }
        if ($allowed && in_array($requested, ['live', 'beta'], true)) {
            $_SESSION[self::EDITOR_BRANCH_SESSION_KEY] = $requested;
        }
        $sessionBranch = $allowed ? strtolower((string)($_SESSION[self::EDITOR_BRANCH_SESSION_KEY] ?? '')) : '';
        return $sessionBranch === 'beta' ? 'beta' : 'live';
    }

    public function resolveRuntimeBranch(array $runtimeContext = []): string {
        if ($this->canUsePreview() && $this->isPreviewActiveFor($runtimeContext)) {
            return 'beta';
        }
        return 'live';
    }

    public function clearEditorBranch(): void {
        if (isset($_SESSION[self::EDITOR_BRANCH_SESSION_KEY])) {
            unset($_SESSION[self::EDITOR_BRANCH_SESSION_KEY]);
        }
    }

    public function resolveBranch(): string {
        return $this->resolveEditorBranch();
    }

    public function canUsePreview(): bool {
        return $this->canUseBeta() && (int)($this->entity['id'] ?? 0) > 0;
    }

    public function activatePreviewSession(array $scope, int $ttlSeconds = 1800): void {
        if (!$this->canUsePreview()) {
            return;
        }
        $ttlSeconds = max(60, $ttlSeconds);
        $_SESSION[self::PREVIEW_SESSION_KEY] = [
            'enabled' => true,
            'branch' => 'beta',
            'actor_id' => (int)($this->entity['id'] ?? 0),
            'created_at' => time(),
            'expires_at' => time() + $ttlSeconds,
            'scope' => $scope,
        ];
    }

    public function clearPreviewSession(): void {
        if (isset($_SESSION[self::PREVIEW_SESSION_KEY])) {
            unset($_SESSION[self::PREVIEW_SESSION_KEY]);
        }
    }

    public function getPreviewContext(): array {
        $preview = $_SESSION[self::PREVIEW_SESSION_KEY] ?? null;
        if (!is_array($preview) || empty($preview['enabled'])) {
            return [];
        }
        if ((int)($preview['expires_at'] ?? 0) < time()) {
            $this->clearPreviewSession();
            return [];
        }
        if ((int)($preview['actor_id'] ?? 0) !== (int)($this->entity['id'] ?? 0)) {
            return [];
        }
        return $preview;
    }

    public function isPreviewActiveFor(array $runtimeContext = []): bool {
        $preview = $this->getPreviewContext();
        if (empty($preview) || strtolower((string)($preview['branch'] ?? '')) !== 'beta') {
            return false;
        }
        $scope = $preview['scope'] ?? [];
        if (!is_array($scope) || empty($scope)) {
            return true;
        }
        if (!empty($scope['ms_name']) && strtolower((string)($scope['ms_name'])) !== strtolower((string)($runtimeContext['ms_name'] ?? ''))) {
            return false;
        }
        if (!empty($scope['route_key']) && strtolower((string)($scope['route_key'])) !== strtolower((string)($runtimeContext['route_key'] ?? ''))) {
            return false;
        }
        if (!empty($scope['content_id']) && (int)$scope['content_id'] !== (int)($runtimeContext['content_id'] ?? 0)) {
            return false;
        }
        return true;
    }

    public function canUseBeta(): bool {
        $entityId = (int)($this->entity['id'] ?? 0);
        if ($entityId === 1) {
            return true;
        }
        $roleIds = $this->entity['role_id'] ?? [];
        if (is_int($roleIds)) {
            $roleIds = [$roleIds];
        }
        return in_array(1, $roleIds, true);
    }

    public function canMerge(): bool {
        return $this->canUseBeta();
    }

    public function getRouteBranchStatus(int $routeId): array {
        $rows = $this->db->query(
            "SELECT * FROM s_branch
             WHERE livestatus != '0' AND s_object_type = 'route' AND s_object_id = :rid AND s_branch = 'beta'
             ORDER BY id DESC
             LIMIT 1",
            [':rid' => $routeId]
        );
        return $rows[0] ?? [];
    }

    public function getControllerBranchStatus(int $controllerId): array {
        $rows = $this->db->query(
            "SELECT * FROM s_branch
             WHERE livestatus != '0' AND s_object_type = 'controller' AND s_object_id = :cid AND s_branch = 'beta'
             ORDER BY id DESC
             LIMIT 1",
            [':cid' => $controllerId]
        );
        return $rows[0] ?? [];
    }

    public function getControllerSchemaBranchStatus(int $controllerId): array {
        $rows = $this->db->query(
            "SELECT * FROM s_branch
             WHERE livestatus != '0' AND s_object_type = 'controller_schema' AND s_object_id = :cid AND s_branch = 'beta'
             ORDER BY id DESC
             LIMIT 1",
            [':cid' => $controllerId]
        );
        return $rows[0] ?? [];
    }

    public function getContentBranchStatus(int $contentId): array {
        $rows = $this->db->query(
            "SELECT * FROM s_branch
             WHERE livestatus != '0' AND s_object_type = 'content' AND s_object_id = :cid AND s_branch = 'beta'
             ORDER BY id DESC
             LIMIT 1",
            [':cid' => $contentId]
        );
        return $rows[0] ?? [];
    }

    public function hasRouteBetaFiles(string $msName, string $routeKey): bool {
        $paths = $this->getRouteFiles($msName, $routeKey, 'beta');
        foreach ($paths as $path) {
            if (is_file($path)) {
                return true;
            }
        }
        return false;
    }

    public function hasRouteHelpBetaFile(string $msName, string $routeName): bool {
        return is_file($this->getRouteHelpFilePath($msName, $routeName, 'beta', false));
    }

    public function hasControllerBetaFile(string $msName, string $controllerName): bool {
        foreach ($this->getControllerCandidatePaths($msName, $controllerName, 'beta') as $path) {
            if (is_file($path)) {
                return true;
            }
        }
        return false;
    }

    public function hasContentBeta(int $contentId): bool {
        $rows = $this->db->select('s_content', ['id' => $contentId], true);
        if (count($rows) !== 1) {
            return false;
        }
        $info = $this->decodeJson($rows[0]['s_additional_info'] ?? '');
        return !empty($info['branch_beta']);
    }

    public function getRouteFiles(string $msName, string $routeKey, string $branch): array {
        $branch = $branch === 'beta' ? 'beta' : 'live';
        $msDir = rtrim($this->config['dir']['ms'] ?? '', '/');
        $base = $msDir . '/' . $msName;
        if ($branch === 'beta') {
            $base .= '/_beta';
        }
        $prefix = $base . '/route.' . $routeKey;
        return [
            'load' => $prefix . '.php',
            'pagepart' => $prefix . '.pagepart.php',
            'prepart' => $prefix . '.prepart.php',
            'postpart' => $prefix . '.postpart.php',
        ];
    }

    public function getControllerFilePath(string $msName, string $controllerName, string $branch = 'live', bool $fallback = true): string {
        $branch = $branch === 'beta' ? 'beta' : 'live';
        $msDir = rtrim($this->config['dir']['ms'] ?? '', '/');
        $base = $msDir . '/' . $msName;
        if ($branch === 'beta') {
            $base .= '/_beta';
        }
        $candidates = $this->getControllerCandidatePaths($msName, $controllerName, $branch);
        $path = $candidates[0] ?? ($base . '/' . $this->resolveControllerFileName($msName, $controllerName, $branch));
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }
        if ($branch === 'beta' && $fallback && !is_file($path)) {
            $liveCandidates = $this->getControllerCandidatePaths($msName, $controllerName, 'live');
            foreach ($liveCandidates as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
            if (!empty($liveCandidates)) {
                return $liveCandidates[0];
            }
        }
        return $path;
    }

    public function getRouteFilePath(string $msName, string $routeKey, string $part, string $branch = 'live', bool $fallback = true): string {
        $paths = $this->getRouteFiles($msName, $routeKey, $branch);
        $part = $this->normalizePart($part);
        $path = $paths[$part] ?? $paths['load'];
        if ($branch === 'beta' && $fallback) {
            if (!is_file($path)) {
                $livePaths = $this->getRouteFiles($msName, $routeKey, 'live');
                return $livePaths[$part] ?? $livePaths['load'];
            }
        }
        return $path;
    }

    public function getRouteHelpFilePath(string $msName, string $routeName, string $branch = 'live', bool $fallback = true): string {
        $branch = $branch === 'beta' ? 'beta' : 'live';
        $msDir = rtrim($this->config['dir']['ms'] ?? '', '/');
        $base = $msDir . '/' . $msName;
        if ($branch === 'beta') {
            $base .= '/_beta';
        }
        $path = $base . '/route.' . $routeName . '.help.md';
        if ($branch === 'beta' && $fallback && !is_file($path)) {
            return $this->getRouteHelpFilePath($msName, $routeName, 'live', false);
        }
        return $path;
    }

    public function createControllerBeta(string $msName, string $controllerName, string $note = 'Beta branch created'): array {
        $betaPath = $this->getControllerFilePath($msName, $controllerName, 'beta', false);
        $betaDir = dirname($betaPath);
        if (!is_dir($betaDir) && !mkdir($betaDir, 0777, true) && !is_dir($betaDir)) {
            return ['status' => false, 'message' => 'Unable to create beta folder.'];
        }
        $livePath = $this->getControllerFilePath($msName, $controllerName, 'live', true);
        if (is_file($livePath)) {
            copy($livePath, $betaPath);
        } elseif (!is_file($betaPath)) {
            file_put_contents($betaPath, '');
        }
        $this->recordBranchEvent('controller', $this->resolveControllerId($msName, $controllerName), 'beta', 'active', $note, [
            'ms_name' => $msName,
            'controller' => $controllerName,
        ]);
        return ['status' => true, 'message' => 'Beta branch created.'];
    }

    public function mergeControllerBeta(string $msName, string $controllerName): array {
        $betaPath = $this->getControllerFilePath($msName, $controllerName, 'beta', false);
        if (!is_file($betaPath)) {
            return ['status' => false, 'message' => 'No beta code found to merge.'];
        }
        $livePath = $this->getControllerFilePath($msName, $controllerName, 'live', true);
        if (!is_file($livePath)) {
            file_put_contents($livePath, '');
        }
        copy($betaPath, $livePath);
        $this->recordBranchEvent('controller', $this->resolveControllerId($msName, $controllerName), 'beta', 'merged', 'Beta branch merged into live', [
            'ms_name' => $msName,
            'controller' => $controllerName,
        ]);
        return ['status' => true, 'message' => 'Beta merged into live.'];
    }

    public function discardControllerBeta(string $msName, string $controllerName): array {
        $betaPath = $this->getControllerFilePath($msName, $controllerName, 'beta', false);
        if (is_file($betaPath)) {
            @unlink($betaPath);
        }
        $this->recordBranchEvent('controller', $this->resolveControllerId($msName, $controllerName), 'beta', 'discarded', 'Beta branch discarded', [
            'ms_name' => $msName,
            'controller' => $controllerName,
        ]);
        return ['status' => true, 'message' => 'Beta branch discarded.'];
    }

    public function createContentBeta(int $contentId): array {
        $rows = $this->db->select('s_content', ['id' => $contentId], true);
        if (count($rows) !== 1) {
            return ['status' => false, 'message' => 'Content not found.'];
        }
        $content = $rows[0];
        $info = $this->decodeJson($content['s_additional_info'] ?? '');
        if (empty($info['branch_beta'])) {
            $info['branch_beta'] = $this->extractContentPayload($content);
        }
        $this->db->update('s_content', [
            's_additional_info' => json_encode($info, JSON_UNESCAPED_SLASHES),
        ], ['id' => $contentId]);
        $this->recordBranchEvent('content', $contentId, 'beta', 'active', 'Beta branch created', [
            'content_id' => $contentId,
        ]);
        return ['status' => true, 'message' => 'Beta branch created.'];
    }

    public function mergeContentBeta(int $contentId): array {
        $rows = $this->db->select('s_content', ['id' => $contentId], true);
        if (count($rows) !== 1) {
            return ['status' => false, 'message' => 'Content not found.'];
        }
        $content = $rows[0];
        $info = $this->decodeJson($content['s_additional_info'] ?? '');
        $beta = $info['branch_beta'] ?? null;
        if (!is_array($beta)) {
            return ['status' => false, 'message' => 'No beta content found to merge.'];
        }
        $updates = $this->mergeContentPayload($beta);
        unset($info['branch_beta']);
        $updates['s_additional_info'] = json_encode($info, JSON_UNESCAPED_SLASHES);
        $this->db->update('s_content', $updates, ['id' => $contentId]);
        $this->recordBranchEvent('content', $contentId, 'beta', 'merged', 'Beta branch merged into live', [
            'content_id' => $contentId,
        ]);
        return ['status' => true, 'message' => 'Beta merged into live.'];
    }

    public function discardContentBeta(int $contentId): array {
        $rows = $this->db->select('s_content', ['id' => $contentId], true);
        if (count($rows) !== 1) {
            return ['status' => false, 'message' => 'Content not found.'];
        }
        $info = $this->decodeJson($rows[0]['s_additional_info'] ?? '');
        if (isset($info['branch_beta'])) {
            unset($info['branch_beta']);
        }
        $this->db->update('s_content', [
            's_additional_info' => json_encode($info, JSON_UNESCAPED_SLASHES),
        ], ['id' => $contentId]);
        $this->recordBranchEvent('content', $contentId, 'beta', 'discarded', 'Beta branch discarded', [
            'content_id' => $contentId,
        ]);
        return ['status' => true, 'message' => 'Beta branch discarded.'];
    }

    public function recordSchemaEvent(int $controllerId, string $status, string $note, array $payload = []): void {
        $this->recordBranchEvent('controller_schema', $controllerId, 'beta', $status, $note, $payload);
    }

    public function createRouteBeta(string $msName, int $routeId, string $routeKey = ''): array {
        $routeKey = $routeKey !== '' ? $routeKey : (string)$routeId;
        $paths = $this->getRouteFiles($msName, $routeKey, 'beta');
        $betaDir = dirname($paths['load']);
        if (!is_dir($betaDir) && !mkdir($betaDir, 0777, true) && !is_dir($betaDir)) {
            return ['status' => false, 'message' => 'Unable to create beta folder.'];
        }
        $livePaths = $this->getRouteFiles($msName, $routeKey, 'live');
        foreach ($paths as $part => $target) {
            $source = $livePaths[$part] ?? '';
            if ($source && is_file($source)) {
                copy($source, $target);
            } elseif (!is_file($target)) {
                file_put_contents($target, '');
            }
        }
        $routeName = $this->resolveRouteName($routeId);
        if ($routeName !== '') {
            $liveHelp = $this->getRouteHelpFilePath($msName, $routeName, 'live', false);
            $betaHelp = $this->getRouteHelpFilePath($msName, $routeName, 'beta', false);
            if (is_file($liveHelp)) {
                copy($liveHelp, $betaHelp);
            }
        }
        $this->recordBranchEvent('route', $routeId, 'beta', 'active', 'Beta branch created', [
            'ms_name' => $msName,
            'route_id' => $routeId,
        ]);
        return ['status' => true, 'message' => 'Beta branch created.'];
    }

    public function mergeRouteBeta(string $msName, int $routeId, string $routeKey = ''): array {
        $routeKey = $routeKey !== '' ? $routeKey : (string)$routeId;
        $betaPaths = $this->getRouteFiles($msName, $routeKey, 'beta');
        $livePaths = $this->getRouteFiles($msName, $routeKey, 'live');
        $merged = false;
        foreach ($betaPaths as $part => $source) {
            if (!is_file($source)) {
                continue;
            }
            $target = $livePaths[$part] ?? null;
            if (!$target) {
                continue;
            }
            $this->snapshotRoute($msName, $routeId, $routeKey, $part, $target, 'Pre-merge snapshot');
            copy($source, $target);
            $this->snapshotRoute($msName, $routeId, $routeKey, $part, $target, 'Merged beta to live');
            $merged = true;
        }
        $routeName = $this->resolveRouteName($routeId);
        if ($routeName !== '') {
            $betaHelp = $this->getRouteHelpFilePath($msName, $routeName, 'beta', false);
            $liveHelp = $this->getRouteHelpFilePath($msName, $routeName, 'live', false);
            if (is_file($betaHelp)) {
                $this->snapshotRouteHelp($msName, $routeName, $liveHelp, 'Pre-merge help snapshot');
                copy($betaHelp, $liveHelp);
                $this->snapshotRouteHelp($msName, $routeName, $liveHelp, 'Merged beta help to live');
                $merged = true;
            }
        }
        if (!$merged) {
            return ['status' => false, 'message' => 'No beta code found to merge.'];
        }
        $this->recordBranchEvent('route', $routeId, 'beta', 'merged', 'Beta branch merged into live', [
            'ms_name' => $msName,
            'route_id' => $routeId,
        ]);
        return ['status' => true, 'message' => 'Beta merged into live.'];
    }

    public function discardRouteBeta(string $msName, int $routeId, string $routeKey = ''): array {
        $routeKey = $routeKey !== '' ? $routeKey : (string)$routeId;
        $paths = $this->getRouteFiles($msName, $routeKey, 'beta');
        $removed = false;
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
                $removed = true;
            }
        }
        $routeName = $this->resolveRouteName($routeId);
        if ($routeName !== '') {
            $helpPath = $this->getRouteHelpFilePath($msName, $routeName, 'beta', false);
            if (is_file($helpPath)) {
                @unlink($helpPath);
                $removed = true;
            }
        }
        $this->recordBranchEvent('route', $routeId, 'beta', 'discarded', 'Beta branch discarded', [
            'ms_name' => $msName,
            'route_id' => $routeId,
        ]);
        return ['status' => true, 'message' => $removed ? 'Beta branch discarded.' : 'No beta files found.'];
    }

    private function snapshotRoute(string $msName, int $routeId, string $routeKey, string $part, string $path, string $note): void {
        if (!is_file($path)) {
            return;
        }
        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }
        $itemId = $msName . '/route-' . $routeKey . '/' . $part;
        $this->versionService->snapshot('route', $itemId, $content, ['note' => $note]);
    }

    private function snapshotRouteHelp(string $msName, string $routeName, string $path, string $note): void {
        if (!is_file($path)) {
            return;
        }
        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }
        $itemId = $msName . '/route-help-' . $routeName . '/help';
        $this->versionService->snapshot('route', $itemId, $content, ['note' => $note]);
    }

    private function recordBranchEvent(string $objectType, int $objectId, string $branch, string $status, string $note, array $payload = []): void {
        $actorId = (int)($this->entity['id'] ?? 0);
        $data = [
            's_object_type' => $objectType,
            's_object_id' => $objectId,
            's_branch' => $branch,
            's_status' => $status,
            's_note' => $note,
            's_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ];
        $this->db->insert('s_branch', $data, [
            'space_id' => 0,
            'createdby' => $actorId ?: 1,
            'livestatus' => '1',
        ]);
    }

    private function normalizePart(string $part): string {
        $part = strtolower(trim($part));
        $allowed = ['load', 'pagepart', 'prepart', 'postpart'];
        return in_array($part, $allowed, true) ? $part : 'load';
    }

    private function resolveControllerId(string $msName, string $controllerName): int {
        $msRows = $this->db->select('s_ms', ['s_name' => $msName], true);
        if (count($msRows) !== 1) {
            return 0;
        }
        $controllerRows = $this->db->select('s_mscontroller', [
            's_ms_id' => $msRows[0]['id'],
            's_name' => $controllerName,
        ], true);
        if (count($controllerRows) !== 1) {
            return 0;
        }
        return (int)$controllerRows[0]['id'];
    }

    private function resolveRouteName(int $routeId): string {
        if ($routeId <= 0) {
            return '';
        }
        $rows = $this->db->select('s_msroute', ['id' => $routeId], true);
        if (count($rows) !== 1) {
            return '';
        }
        return (string)($rows[0]['s_name'] ?? '');
    }

    private function resolveControllerFileName(string $msName, string $controllerName, string $branch = 'live'): string {
        $controller = $this->loadControllerRecord($msName, $controllerName);
        $sourceFile = trim((string)($controller['s_source_file'] ?? ''));
        if ($sourceFile !== '') {
            return basename($sourceFile);
        }

        $defaultFile = $controllerName . '.cls.php';
        $legacyFile = ucfirst($controllerName) . '.cls.php';
        $branch = $branch === 'beta' ? 'beta' : 'live';
        $msDir = rtrim($this->config['dir']['ms'] ?? '', '/') . '/' . $msName;
        if ($branch === 'beta') {
            $msDir .= '/_beta';
        }

        if (is_file($msDir . '/' . $defaultFile) || !is_file($msDir . '/' . $legacyFile)) {
            return $defaultFile;
        }

        return $legacyFile;
    }

    private function getControllerCandidatePaths(string $msName, string $controllerName, string $branch = 'live'): array {
        $branch = $branch === 'beta' ? 'beta' : 'live';
        $msDir = rtrim($this->config['dir']['ms'] ?? '', '/') . '/' . $msName;
        if ($branch === 'beta') {
            $msDir .= '/_beta';
        }

        $controller = $this->loadControllerRecord($msName, $controllerName);
        $candidates = [];

        $sourceFile = trim((string)($controller['s_source_file'] ?? ''));
        if ($sourceFile !== '') {
            $candidates[] = $msDir . '/' . basename($sourceFile);
        }

        $candidates[] = $msDir . '/' . $controllerName . '.cls.php';
        $candidates[] = $msDir . '/' . ucfirst($controllerName) . '.cls.php';

        return array_values(array_unique($candidates));
    }

    private function loadControllerRecord(string $msName, string $controllerName): array {
        $msRows = $this->db->select('s_ms', ['s_name' => $msName], true);
        if (count($msRows) !== 1) {
            return [];
        }

        $controllerRows = $this->db->select('s_mscontroller', [
            's_ms_id' => $msRows[0]['id'],
            's_name' => $controllerName,
        ], true);

        return $controllerRows[0] ?? [];
    }

    private function decodeJson($raw): array {
        if (!$raw) {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function extractContentPayload(array $content): array {
        return [
            's_ms_id' => $content['s_ms_id'] ?? null,
            's_title' => $content['s_title'] ?? '',
            's_summary' => $content['s_summary'] ?? '',
            's_content' => $content['s_content'] ?? '',
            's_publication' => $content['s_publication'] ?? null,
            's_definition' => $content['s_definition'] ?? '',
            's_meta_title' => $content['s_meta_title'] ?? '',
            's_meta_description' => $content['s_meta_description'] ?? '',
            's_canonical_url' => $content['s_canonical_url'] ?? '',
            's_slug' => $content['s_slug'] ?? '',
            's_type' => $content['s_type'] ?? '',
            's_additional_info' => $content['s_additional_info'] ?? null,
        ];
    }

    private function mergeContentPayload(array $beta): array {
        $fields = [
            's_ms_id',
            's_title',
            's_summary',
            's_content',
            's_publication',
            's_definition',
            's_meta_title',
            's_meta_description',
            's_canonical_url',
            's_slug',
            's_type',
        ];
        $updates = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $beta)) {
                $updates[$field] = $beta[$field];
            }
        }
        return $updates;
    }
}

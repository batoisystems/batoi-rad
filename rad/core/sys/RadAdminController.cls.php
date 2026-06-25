<?php
namespace Core\Sys;

class RadAdminController {
    private $db;
    private $view;
    private $session;
    private $errorHandler;
    private $runData = [];
    private $routeIndex = [];
    private $ipAccessService;

    public function __construct(array $runData, \Core\Sys\View $view, \Core\Sys\ErrorHandler $errorHandler) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->routeIndex = $runData['route']['pathparts'];
        $this->session = $runData['session'];
        $this->view = $view;
        $this->errorHandler = $errorHandler;
        $this->ipAccessService = new IpAccessService();
        $this->loadRadConfig();
        $baseUrl = $this->resolveBaseUrl();
        $this->runData['config']['sys']['base_url'] = $baseUrl;
        $this->runData['route']['url'] = rtrim($baseUrl, '/') . $this->runData['route']['path_full'];
        $this->runData['route']['rad_assets_url'] = rtrim($baseUrl, '/') . '/rad-admin/assets';
        $this->runData['route']['rad_admin_url'] = rtrim($baseUrl, '/') . '/rad-admin';
        // Allow assets to load even when the IP is blocked so error pages can be styled.
        if (!isset($this->routeIndex[1]) || $this->routeIndex[1] !== 'assets') {
            $this->checkIPRestriction();
        }
    }

    public function handle() {
        if (isset($this->routeIndex[1]) && $this->routeIndex[1] == 'assets') {
            $this->loadRadAsset();
        } else {
            // print '<pre>';print_r($this->runData);print '</pre>';exit;
            $this->loadAdminRoute();
        }
    }

    public function loadRadAsset() {
        array_shift($this->routeIndex);
        array_shift($this->routeIndex);
        if (count($this->routeIndex) == 0) {
            $this->errorHandler->handleException('Invalid asset path');
        }
        $fileNameWithPath = implode('/', $this->routeIndex);
        $filePath = $this->runData['config']['dir']['admin'].'/assets/'.$fileNameWithPath;

        if (file_exists($filePath)) {
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            switch ($ext) {
                case 'css':
                    header('Content-Type: text/css');
                    break;
                case 'js':
                    header('Content-Type: application/javascript');
                    break;
                case 'jpg':
                case 'jpeg':
                    header('Content-Type: image/jpeg');
                    break;
                case 'png':
                    header('Content-Type: image/png');
                    break;
                case 'svg':
                    header('Content-Type: image/svg+xml');
                    break;
                case 'gif':
                    header('Content-Type: image/gif');
                    break;
                case 'ttf':
                    header('Content-Type: font/ttf');
                    break;
                case 'woff':
                    header('Content-Type: font/woff');
                    break;
                case 'woff2':
                    header('Content-Type: font/woff2');
                    break;
                default:
                    header('Content-Type: application/octet-stream');
            }
            readfile($filePath);
        } else {
            header("HTTP/1.0 404 Not Found");
            echo "File not found: {$this->runData['config']['sys']['base_url']}/rad-admin/assets/{$fileNameWithPath}";
        }
    }

    public function loadAdminRoute() {
        // Determine module first to allow login screen without auth
        if (isset($this->routeIndex[1]) && $this->routeIndex[1] != '') {
            $moduleName = $this->routeIndex[1];
        } else {
            $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/home/view';
            header("Location: {$redirectUrl}"); exit;
        }

        // Allow the dedicated admin login screen to load without auth
        if ($moduleName !== 'login') {
            $this->checkUserAccess();
            unset($this->runData['session']);
        }

        $moduleClassName = ($moduleName === 'login') ? 'Adminlogin' : ucfirst($moduleName);
        $moduleClass = '\RadAdmin\\' . $moduleClassName;
        $communityHelperPath = $this->runData['config']['dir']['admin'].'/classes/RadAdminCommunity.cls.php';
        if (!class_exists('RadAdmin\\RadAdminCommunity', false) && file_exists($communityHelperPath)) {
            require_once $communityHelperPath;
        }
        if (class_exists('RadAdmin\\RadAdminCommunity', false)
            && !\RadAdmin\RadAdminCommunity::moduleAllowed($moduleName, $this->runData['config'] ?? [])) {
            throw new \Exception('This RAD Admin module is not included in Community Edition.', 404);
        }
        // Ensure shared admin traits are loaded once before module classes reference them
        $traitPath = $this->runData['config']['dir']['admin'].'/classes/AiAssistAware.cls.php';
        if (!trait_exists('RadAdmin\\AiAssistAware', false) && file_exists($traitPath)) {
            require_once $traitPath;
        }

        // Ensure shared helper is available for visibility filtering
        $visibilityHelperPath = $this->runData['config']['dir']['admin'].'/classes/VisibilityHelper.cls.php';
        if (!class_exists('RadAdmin\\VisibilityHelper', false) && file_exists($visibilityHelperPath)) {
            require_once $visibilityHelperPath;
        }
        // print '<pre>';print_r($moduleClass);print '</pre>';
        // print '<pre>';print_r($this->runData['config']['dir']['admin'].'/classes/'.ucfirst($moduleName).'.cls.php');print '</pre>';exit;
        $moduleFile = $this->runData['config']['dir']['admin'].'/classes/'.$moduleClassName.'.cls.php';
        if (!file_exists($moduleFile)) {
            throw new \Exception("Module {$moduleName} not found", 404);
        }
        require $moduleFile;
        $moduleObject = new $moduleClass($this->runData);
        // print '<pre>';print_r($moduleObject);print '</pre>';exit;
        if (isset($this->routeIndex[2]) && $this->routeIndex[2] != '') {
            $eventName = $this->routeIndex[2];
        } else {
            if ($moduleName === 'login') {
                $eventName = 'view';
            } else {
                $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/'.$moduleName.'/view';
                header("Location: {$redirectUrl}"); exit;
            }
        }

        // Normalize hyphenated method names to PHP-friendly underscores and dash suffix
        $candidates = [
            str_replace('-', '_', $eventName),
            $eventName,
            str_replace('-', '_', $eventName) . '_dash',
        ];
        $reflection = null;
        foreach ($candidates as $candidate) {
            try {
                $reflection = new \ReflectionMethod($moduleObject, $candidate);
                $eventName = $candidate;
                break;
            } catch (\ReflectionException $e) {
                continue;
            }
        }
        if ($reflection === null) {
            throw new \Exception("Module method {$eventName} not found", 404);
        }

        $start = microtime(true);
        $trustedIp = $this->runData['route']['client_ip'] ?? null;
        $trustedFlag = $this->runData['route']['ip_trusted'] ?? null;
        $this->runData = $moduleObject->$eventName();
        if ($trustedIp && empty($this->runData['route']['client_ip'])) {
            $this->runData['route']['client_ip'] = $trustedIp;
            if ($trustedFlag !== null) {
                $this->runData['route']['ip_trusted'] = $trustedFlag;
            }
        }

        // For admin login, render standalone page and exit (skip admin layout/nav)
        if ($moduleName === 'login') {
            $loginView = $this->runData['config']['dir']['admin'] . '/ui/adminlogin-view.html.php';
            if (file_exists($loginView)) {
                $runData = $this->runData;
                include $loginView;
                exit;
            }
        }

        $this->augmentGlobalNavData();
        $this->runData['ms']['tpl_name'] = 'rad-admin';
        $this->runData['route']['pagepart'] = $moduleName . '-' . $eventName;
        $this->runData['route']['meta_title'] = $this->runData['route']['meta_title'] ?? '';
        $this->runData['route']['meta_description'] = '';

        if (isset($this->runData['entity']['view_status']) && $this->runData['entity']['view_status'] == 'render') {
            $this->attachDebugBlock();
            $this->view->render($this->runData);
        } elseif (isset($this->runData['entity']['status']) && $this->runData['entity']['status'] == 'redirect') {
            $redirectUrl = $this->runData['entity']['redirect_url'];
            header("Location: {$redirectUrl}"); exit;
        } else {
            exit;
        }
    }

    private function recordTelemetryEvent(array $payload): void {
        if (!$this->telemetryService instanceof TelemetryService) {
            return;
        }
        try {
            $this->telemetryService->recordEvent($payload);
        } catch (\Throwable $e) {
            // swallow telemetry errors
        }
    }

    private function augmentGlobalNavData(): void {
        if (empty($this->runData['entity']['id'])) {
            return;
        }
        if (!isset($this->runData['notificationService']) || !$this->runData['notificationService'] instanceof NotificationService) {
            try {
                $this->runData['notificationService'] = new NotificationService($this->db);
            } catch (\Throwable $e) {
                return;
            }
        }
        if (!$this->runData['notificationService'] instanceof NotificationService) {
            return;
        }

        $entity = $this->runData['entity'];
        $entityId = (int)$entity['id'];
        $superAdmin = $this->isSuperAdminEntity($entity);
        $spaceIds = $superAdmin ? [] : $this->resolveEntitySpaceIds($entity);
        $canSee = method_exists($this->runData['notificationService'], 'canSeeRadAdmin')
            ? $this->runData['notificationService']->canSeeRadAdmin($entity)
            : ($entityId === 1);

        if (!$canSee) {
            $this->runData['nav']['notifications_unread'] = 0;
            $this->runData['nav']['notifications_recent'] = [];
            return;
        }

        try {
            $count = $this->runData['notificationService']->countUnread($entityId, $spaceIds, $superAdmin);
            $recent = $this->runData['notificationService']->fetchNotifications(
                $entityId,
                $spaceIds,
                [
                    'limit' => 5,
                    'include_workspace' => true,
                    'include_global' => true,
                    'super_admin' => $superAdmin,
                    'only_unread' => true,
                ]
            );
            foreach ($recent as &$row) {
                $row['relative_time'] = $this->formatRelativeTime($row['createstamp'] ?? null);
            }
            unset($row);
        } catch (\Throwable $e) {
            $count = 0;
            $recent = [];
        }

        $this->runData['nav']['notifications_unread'] = $count;
        $this->runData['nav']['notifications_recent'] = $recent;
    }

    private function resolveEntitySpaceIds(array $entity): array {
        $ids = [];
        if (isset($entity['space_id'])) {
            $space = $entity['space_id'];
            if (is_array($space)) {
                $ids = $space;
            } elseif (ctype_digit((string)$space)) {
                $ids = [(int)$space];
            }
        } elseif (isset($entity['spaces']) && is_array($entity['spaces'])) {
            $ids = array_keys($entity['spaces']);
        }

        if (empty($ids) && !empty($entity['id'])) {
            $rows = $this->db->query(
                "SELECT DISTINCT space_id FROM s_space_membership WHERE livestatus != '0' AND s_entity_id = :entity",
                [':entity' => (int)$entity['id']]
            );
            foreach ($rows as $row) {
                $ids[] = (int)$row['space_id'];
            }
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        return $ids;
    }

    private function isSuperAdminEntity(array $entity): bool {
        if (!empty($entity['id']) && (int)$entity['id'] === 1) {
            return true;
        }
        $roles = $entity['nonsaas_role_id'] ?? ($entity['s_nonsaas_role_id'] ?? ($entity['role_id'] ?? []));
        if (is_array($roles)) {
            return in_array(1, $roles, true);
        }
        return (int)$roles === 1;
    }

    private function formatRelativeTime(?string $date): string {
        if (!$date) {
            return '';
        }
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return (string)$date;
        }
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return $diff . 's ago';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        }
        return date('M j, Y H:i', $timestamp);
    }
    
    private function checkUserAccess() {
        // print '<pre>';print_r($this->runData);print '</pre>';exit;
        // Check if the entity session exists
        if (!isset($this->runData['entity']) || !$this->runData['entity']['is_logged_in']) {
            $debugFlag = strtoupper((string)($this->runData['config']['sys']['dev_debug_flag']
                ?? $this->runData['config']['app']['dev_debug_flag']
                ?? 'N')) === 'Y';
            if ($debugFlag && $this->session) {
                $this->session->set('login_debug', [
                    'time' => date('Y-m-d H:i:s'),
                    'message' => 'Admin access blocked: session not logged in.',
                    'is_logged_in' => $this->runData['entity']['is_logged_in'] ?? null,
                    'entity_id' => $this->runData['entity']['id'] ?? null,
                    'uri' => $this->runData['request']->uri ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);
            }
            $redirectUrlPostLogin = $this->runData['config']['sys']['base_url'] . $this->runData['request']->uri;
            setcookie('redirect_url_post_login', $redirectUrlPostLogin, time() + (86400 * 30), "/");
            $loginUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/login';
            header("Location: {$loginUrl}");
            exit;
        }
        
        // System admin (entity id = 1) can bypass auth info decoding
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId === 1) {
            return;
        }
    
        // Use privilege manifest to gate RAD Admin access (default: view privilege)
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('view')) {
            throw new \Exception('Entity does not have access to this route', 403);
        }
    }       

    private function attachDebugBlock(): void {
        if (!$this->shouldShowDebugBlock()) {
            return;
        }

        $debug = $this->runData['debug'] ?? [];
        if (!is_array($debug)) {
            $debug = ['value' => $debug];
        }

        $checkpointStats = [];
        $start = null;
        $prev = null;
        $checkpoints = $debug['checkpoints'] ?? [];
        if (is_array($checkpoints)) {
            foreach ($checkpoints as $idx => $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $ts = $entry['ts'] ?? $entry['time'] ?? null;
                if ($ts === null) {
                    continue;
                }
                $ts = (float)$ts;
                if ($start === null) {
                    $start = $ts;
                }
                $label = $entry['label'] ?? ('Checkpoint ' . ($idx + 1));
                $delta = $prev !== null ? $ts - $prev : 0.0;
                $elapsed = $start !== null ? $ts - $start : 0.0;
                $checkpointStats[] = [
                    'label' => $label,
                    'ts' => $ts,
                    'delta_ms' => round($delta * 1000, 2),
                    'elapsed_ms' => round($elapsed * 1000, 2),
                ];
                $prev = $ts;
            }
        }

        $debug['generated_at'] = date('Y-m-d H:i:s');
        if (!empty($checkpointStats)) {
            $debug['checkpoint_stats'] = $checkpointStats;
        }

        $this->runData['route']['debug_block'] = [
            'generated_at' => date('Y-m-d H:i:s'),
            'request_uri' => $this->runData['request']->uri ?? '',
            'payload' => $debug,
        ];
    }

    private function shouldShowDebugBlock(): bool {
        $debugFlag = strtoupper((string)($this->runData['config']['sys']['dev_debug_flag']
            ?? $this->runData['config']['app']['dev_debug_flag']
            ?? 'N')) === 'Y';
        if (!$debugFlag) {
            return false;
        }
        $query = $this->runData['request']->get['debug_block'] ?? '';
        if ($query !== '1') {
            return false;
        }
        $entity = $this->runData['entity'] ?? [];
        if (empty($entity['is_logged_in'])) {
            return false;
        }
        $entityId = (int)($entity['id'] ?? 0);
        if ($entityId === 1) {
            return true;
        }
        $roleId = $entity['nonsaas_role_id'] ?? ($entity['s_nonsaas_role_id'] ?? ($entity['role_id'] ?? null));
        if (is_array($roleId)) {
            return in_array(1, $roleId, true);
        }
        return (int)$roleId === 1;
    }

    public function loadRadConfig() {
        $radConfigFile = $this->runData['config']['dir']['admin'] . '/rad.config.php';
        $radConfig = include($radConfigFile);
        if (!is_array($radConfig)) {
            $radConfig = [];
        }
        // Merge top-level keys and ensure `rad` section is present at expected depth
        foreach ($radConfig as $key => $value) {
            if ($key === 'rad' && is_array($value)) {
                $this->runData['config']['rad'] = $value + ($this->runData['config']['rad'] ?? []);
            } else {
                $this->runData['config'][$key] = $value;
            }
        }
        $ipAccessOverridePath = $this->runData['config']['dir']['admin'] . '/ip-access.config.php';
        if (file_exists($ipAccessOverridePath)) {
            $ipOverride = include $ipAccessOverridePath;
            if (is_array($ipOverride)) {
                foreach ($ipOverride as $key => $value) {
                    if ($key === 'rad' && is_array($value)) {
                        $this->runData['config']['rad'] = array_replace_recursive($this->runData['config']['rad'] ?? [], $value);
                    } else {
                        $this->runData['config'][$key] = $value;
                    }
                }
            }
        }
        // Normalize ip_restrict: prefer rad.ip_restrict, fallback to top-level ip_restrict
        if (empty($this->runData['config']['rad']['ip_restrict']) && !empty($this->runData['config']['ip_restrict'])) {
            $this->runData['config']['rad']['ip_restrict'] = $this->runData['config']['ip_restrict'];
        }

        // Merge rad-vals.config.php overrides (privilege ids, manifest, visibility)
        $valsPath = $this->runData['config']['dir']['admin'] . '/rad-vals.config.php';
        if (file_exists($valsPath)) {
            $vals = include $valsPath;
            if (is_array($vals)) {
                // Privilege IDs
                if (!empty($vals['privilege_ids']) && is_array($vals['privilege_ids'])) {
                    $privIds = $this->runData['config']['rad']['privileges'] ?? [];
                    // Normalize access_admins key to access_admin for backward compatibility
                    if (isset($vals['privilege_ids']['access_admins']) && !isset($vals['privilege_ids']['access_admin'])) {
                        $vals['privilege_ids']['access_admin'] = $vals['privilege_ids']['access_admins'];
                    }
                    $this->runData['config']['rad']['privileges'] = array_merge($privIds, $vals['privilege_ids']);
                }
                // Privilege manifest
                if (!empty($vals['privilege_manifest']) && is_array($vals['privilege_manifest'])) {
                    $this->runData['config']['rad']['privilege_manifest'] = $vals['privilege_manifest'];
                }
                // Visibility overrides
                if (isset($vals['restricted_ms_ids'])) {
                    $this->runData['config']['rad']['visibility']['restricted_ms_ids'] = $vals['restricted_ms_ids'];
                }
            }
        }
    }

    /**
     * Check if the client IP is allowed to access the application
     */
    public function checkIPRestriction() {
        $cfg = $this->runData['config']['rad']['ip_restrict'] ?? [];
        $rule = $this->ipAccessService->normalizeRule(!empty($cfg['enabled']), $cfg['ip'] ?? '');
        $entityId = (int)($this->runData['entity']['id'] ?? $this->session->get('entity_id') ?? 0);
        $result = $this->ipAccessService->evaluate($rule, $entityId);
        $this->runData['route']['client_ip'] = $result['client_ip'] ?? '';
        $this->runData['route']['allowed_ip_list'] = $rule['ips'] ?? [];
        $this->runData['route']['ip_restriction_reason'] = $result['reason'] ?? '';
        if (!$result['allowed']) {
            http_response_code(403);
            $errorClassPath = $this->runData['config']['dir']['admin'] . '/classes/Errorpage.cls.php';
            if (file_exists($errorClassPath)) {
                require_once $errorClassPath;
            }
            $errorPage = new \RadAdmin\Errorpage($this->runData);
            $this->view->render($errorPage->ip_restricted());
            exit();
        }
        // Allowed
        $this->runData['route']['ip_trusted'] = true;
    }

    private function resolveBaseUrl(): string {
        $configured = trim($this->runData['config']['sys']['base_url'] ?? '');
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $scheme = 'http';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0];
        } elseif (!empty($_SERVER['HTTP_X_REAL_PROTO'])) {
            $scheme = $_SERVER['HTTP_X_REAL_PROTO'];
        } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }

        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        if (strpos($host, ',') !== false) {
            $host = trim(explode(',', $host)[0]);
        }

        $port = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? ($_SERVER['SERVER_PORT'] ?? '');
        $port = is_string($port) ? trim($port) : $port;
        $includePort = $port && !in_array((int)$port, [80, 443], true) && strpos($host, ':') === false;
        $authority = $includePort ? "{$host}:{$port}" : $host;

        return strtolower($scheme) . '://' . $authority;
    }
}

<?php
namespace RadAdmin;

use Core\Sys\NotificationService;
use Core\Sys\TimeHelper;

class Notifications {
    private $runData = [];
    private $db;
    private $notificationService;
    private $cachedSpaces;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->notificationService = $runData['notificationService'] ?? null;
        if (!$this->notificationService instanceof NotificationService) {
            // Fallback so listings work even if service was not injected.
            $this->notificationService = new NotificationService($this->db);
        }
    }

    public function view() {
        $this->runData['route']['h1'] = 'Notifications';
        $this->runData['route']['meta_title'] = 'Notifications';
        $this->runData['route']['breadcrumb'] = ['Engagement' => $this->runData['route']['rad_admin_url'] . '/home/view'];

        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        $superAdmin = $this->isSuperAdmin();

        $filters = $this->resolveFilters($superAdmin);
        $prefs = $this->loadProfilePrefs($entityId);
        $perPage = (int)($prefs['per_page'] ?? 25);
        $perPageOverride = (int)($this->runData['request']->get['per_page'] ?? 0);
        if (in_array($perPageOverride, [10, 25, 50, 100, 200], true)) {
            $perPage = $perPageOverride;
            if ($perPage !== (int)($prefs['per_page'] ?? 25)) {
                $prefs['per_page'] = $perPage;
                $this->saveProfilePrefs($entityId, $prefs);
            }
        } elseif (!in_array($perPage, [10, 25, 50, 100, 200], true)) {
            $perPage = 25;
        }
        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $spaceOptions = $this->loadSpaces($superAdmin, $entityId);
        $scopeSpaces = $this->deriveScopeSpaces($filters, $spaceOptions, $superAdmin);
        $list = $this->fetchNotifications($entityId, $filters, $scopeSpaces, $superAdmin, $perPage, $page);

        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['scope_options'] = $this->getScopeOptions($superAdmin);
        $this->runData['data']['space_options'] = $spaceOptions;
        $this->runData['data']['notifications'] = $list['rows'];
        $this->runData['data']['metrics'] = $list['metrics'];
        $this->runData['data']['is_super_admin'] = $superAdmin;
        $this->runData['data']['can_mark_all'] = !$superAdmin; // only user-facing inbox can mark-all
        $this->runData['data']['page'] = $page;
        $this->runData['data']['pages'] = $list['pages'];
        $this->runData['data']['per_page'] = $perPage;
        $this->runData['data']['total'] = $list['total'];

        return $this->runData;
    }

    public function settings() {
        if (!$this->isSuperAdmin()) {
            throw new \Exception('Access denied.', 403);
        }

        $this->runData['route']['h1'] = 'Notification Settings';
        $this->runData['route']['meta_title'] = 'Notification Settings';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Notifications' => $this->runData['route']['rad_admin_url'] . '/notifications/view',
            'Settings' => '',
        ];

        $definitions = $this->getSettingDefinitions();
        $settings = $this->loadSettings($definitions);

        if ($this->runData['request']->method === 'POST') {
            $token = $this->runData['request']->post['csrf_token'] ?? '';
            if (!$this->runData['request']->checkCSRFToken($token)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Security check failed. Please retry the form submission.';
            } else {
                $action = $this->runData['request']->post['action'] ?? '';
                if ($action === 'send_test') {
                    $entityId = (int)($this->runData['entity']['id'] ?? 0);
                    $message = trim((string)($this->runData['request']->post['test_message'] ?? 'Test notification'));
                    $result = $this->notificationService->logUserEvent($message, $entityId, [
                        'event_type' => 'test_notification',
                        'metadata' => ['activity_severity' => 'info'],
                    ]);
                    if ($result) {
                        $this->runData['request']->setAlert('Test notification queued.', 'success');
                    } else {
                        $this->runData['route']['alert'] = 'warning';
                        $this->runData['route']['alert_message'] = 'Unable to create the test notification. Check error logs.';
                    }
                } else {
                    $enabled = !empty($this->runData['request']->post['notifications_enabled']) ? 'Y' : 'N';
                    $mode = strtolower((string)($this->runData['request']->post['notifications_mode'] ?? 'both'));
                    $mode = in_array($mode, ['realtime', 'ingest', 'both'], true) ? $mode : 'both';
                    $severity = strtolower((string)($this->runData['request']->post['notifications_min_severity'] ?? 'info'));
                    $severity = in_array($severity, ['info', 'warn', 'critical'], true) ? $severity : 'info';

                    $this->saveSetting('notifications_enabled', $enabled, $definitions['notifications_enabled']['description']);
                    $this->saveSetting('notifications_mode', $mode, $definitions['notifications_mode']['description']);
                    $this->saveSetting('notifications_min_severity', $severity, $definitions['notifications_min_severity']['description']);

                    $this->runData['request']->setAlert('Notification settings updated.', 'success');
                    $settings = $this->loadSettings($definitions);
                }
            }
        }

        $this->runData['data']['settings'] = $settings;
        $this->runData['data']['setting_definitions'] = $definitions;
        return $this->runData;
    }

    private function resolveFilters(bool $superAdmin): array {
        $allowedScopes = array_keys($this->getScopeOptions($superAdmin));
        $scope = $this->runData['request']->get['scope'] ?? 'inbox';
        $scope = in_array($scope, $allowedScopes, true) ? $scope : 'inbox';

        $spaceId = (int)($this->runData['request']->get['space_id'] ?? 0);
        $search = trim((string)($this->runData['request']->get['q'] ?? ''));
        $severity = strtolower(trim((string)($this->runData['request']->get['severity'] ?? '')));
        $eventType = trim((string)($this->runData['request']->get['event_type'] ?? ''));

        return [
            'scope' => $scope,
            'space_id' => $spaceId,
            'q' => $search,
            'severity' => $severity,
            'event_type' => $eventType,
        ];
    }

    private function loadSpaces(bool $superAdmin, int $entityId): array {
        if ($superAdmin) {
            $rows = $this->db->select('s_space', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        } else {
            $sql = "SELECT DISTINCT s.id, s.s_name
                    FROM s_space_membership m
                    INNER JOIN s_space s ON s.id = m.space_id
                    WHERE m.livestatus != '0' AND m.s_entity_id = :entity AND s.livestatus != '0'
                    ORDER BY s.s_name";
            $rows = $this->db->query($sql, [':entity' => $entityId]);
        }

        $spaces = [];
        foreach ($rows as $row) {
            $spaces[(int)$row['id']] = $row['s_name'] ?? ('Workspace #' . $row['id']);
        }
        return $spaces;
    }

    private function deriveScopeSpaces(array $filters, array $spaceOptions, bool $superAdmin): array {
        if ($superAdmin && $filters['space_id'] > 0) {
            return [$filters['space_id']];
        }

        if (!$superAdmin && !empty($spaceOptions)) {
            if ($filters['space_id'] > 0 && isset($spaceOptions[$filters['space_id']])) {
                return [$filters['space_id']];
            }
            return array_keys($spaceOptions);
        }

        return array_keys($spaceOptions);
    }

    private function fetchNotifications(int $entityId, array $filters, array $spaceIds, bool $superAdmin, int $perPage, int $page): array {
        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->buildNotificationQuery($entityId, $filters, $spaceIds, $superAdmin);
        $sql = $query['sql'] . " ORDER BY createstamp DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $rows = $this->db->query($sql, $query['params']);

        $total = $this->countNotifications($entityId, $filters, $spaceIds, $superAdmin);
        $unread = $this->countNotifications($entityId, $filters, $spaceIds, $superAdmin, true);

        foreach ($rows as &$row) {
            $row['metadata'] = $this->decodeDefinition($row['s_definition'] ?? null);
            $row['is_read'] = (int)($row['s_is_read'] ?? 0) === 1;
            $row['scope_label'] = $this->formatScopeLabel($row);
            $row['relative_time'] = $this->formatRelativeTime($row['createstamp'] ?? null);
            $row['severity'] = strtolower((string)($row['metadata']['activity_severity'] ?? $row['metadata']['severity'] ?? 'info'));
            $row['link'] = $row['metadata']['link'] ?? null;
        }
        unset($row);

        return [
            'rows' => $rows,
            'metrics' => [
                'total' => $total,
                'unread' => $unread,
            ],
            'total' => $total,
            'pages' => max(1, (int)ceil($total / max(1, $perPage))),
        ];
    }

    private function buildNotificationQuery(int $entityId, array $filters, array $spaceIds, bool $superAdmin): array {
        $sql = "SELECT * FROM s_notification WHERE livestatus = '1'";
        $params = [];

        $scope = $filters['scope'] ?? 'inbox';
        $audienceSql = '';

        if ($superAdmin && in_array($scope, ['all', 'inbox'], true)) {
            $audienceSql = '';
        } else {
            $clauses = [];
            if ($scope === 'user' || $scope === 'inbox') {
                if ($entityId > 0) {
                    $clauses[] = 's_user_id = :aud_user';
                    $params[':aud_user'] = $entityId;
                }
            }
            if (in_array($scope, ['workspace', 'inbox'], true)) {
                if (!empty($spaceIds)) {
                    $spacePlaceholders = [];
                    foreach ($spaceIds as $index => $spaceId) {
                        $placeholder = ':space' . $index;
                        $spacePlaceholders[] = $placeholder;
                        $params[$placeholder] = $spaceId;
                    }
                    $clauses[] = sprintf(
                        "(space_id IN (%s) AND (s_user_id IS NULL OR s_user_id = 0))",
                        implode(',', $spacePlaceholders)
                    );
                } elseif ($scope === 'workspace') {
                    $clauses[] = '1=0';
                }
            }
            if (in_array($scope, ['global', 'inbox'], true)) {
                $clauses[] = "(space_id = 0 AND (s_user_id IS NULL OR s_user_id = 0))";
            }
            if ($scope === 'all') {
                $clauses = [];
            }
            if (!empty($clauses)) {
                $audienceSql = ' AND (' . implode(' OR ', $clauses) . ')';
            }
        }

        if ($audienceSql !== '') {
            $sql .= $audienceSql;
        }

        if ($filters['space_id'] > 0 && ($scope === 'workspace' || ($superAdmin && $scope === 'inbox'))) {
            $sql .= " AND space_id = :filter_space";
            $params[':filter_space'] = $filters['space_id'];
        }

        if ($filters['q'] !== '') {
            $sql .= " AND (s_message LIKE :q OR s_definition LIKE :q)";
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        if ($filters['severity'] !== '') {
            $sql .= " AND (JSON_UNQUOTE(JSON_EXTRACT(COALESCE(s_definition,'{}'),'$.metadata.activity_severity')) = :severity
                      OR JSON_UNQUOTE(JSON_EXTRACT(COALESCE(s_definition,'{}'),'$.metadata.severity')) = :severity)";
            $params[':severity'] = $filters['severity'];
        }
        if ($filters['event_type'] !== '') {
            $sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(COALESCE(s_definition,'{}'),'$.metadata.event_type')) = :event_type";
            $params[':event_type'] = $filters['event_type'];
        }

        return ['sql' => $sql, 'params' => $params];
    }

    private function countNotifications(int $entityId, array $filters, array $spaceIds, bool $superAdmin, bool $onlyUnread = false): int {
        $query = $this->buildNotificationQuery($entityId, $filters, $spaceIds, $superAdmin);
        $sql = str_replace('SELECT *', 'SELECT COUNT(*) AS total', $query['sql']);
        if ($onlyUnread) {
            $sql .= " AND s_is_read = 0";
        }
        $rows = $this->db->query($sql, $query['params']);
        return !empty($rows[0]['total']) ? (int)$rows[0]['total'] : 0;
    }

    private function decodeDefinition($value): array {
        if (empty($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function loadProfilePrefs(int $entityId): array {
        if ($entityId <= 0) {
            return ['per_page' => 25];
        }
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return ['per_page' => 25];
        }
        $definition = $this->decodeDefinition($rows[0]['s_definition'] ?? null);
        $prefs = $definition['profile_prefs'] ?? [];
        if (!is_array($prefs)) {
            $prefs = [];
        }
        $prefs['per_page'] = $prefs['per_page'] ?? 25;
        return $prefs;
    }

    private function saveProfilePrefs(int $entityId, array $prefs): void {
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return;
        }
        $definition = $this->decodeDefinition($rows[0]['s_definition'] ?? null);
        $definition['profile_prefs'] = $prefs;
        $this->db->update('s_entity', [
            's_definition' => json_encode($definition, JSON_UNESCAPED_SLASHES),
        ], ['id' => $entityId]);
    }

    private function formatScopeLabel(array $row): string {
        switch ($row['scope'] ?? '') {
            case 'user':
                return 'Direct';
            case 'workspace':
                return 'Workspace';
            default:
                return 'Global';
        }
    }

    private function formatRelativeTime(?string $date): string {
        if (!$date) {
            return '';
        }
        try {
            $dt = new \DateTime($date, new \DateTimeZone('UTC'));
            $timestamp = $dt->getTimestamp();
        } catch (\Throwable $e) {
            return $date;
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
        $timezone = TimeHelper::resolveTimezone(
            $this->runData['entity']['timezone'] ?? null,
            $this->runData['config']['sys']['timezone_default'] ?? null
        );
        return TimeHelper::formatUtc($timestamp, $timezone, 'M j, Y H:i') ?? $date;
    }

    private function sanitizeIds($input): array {
        if (!is_array($input)) {
            $input = [$input];
        }
        $clean = [];
        foreach ($input as $value) {
            if (ctype_digit((string)$value)) {
                $clean[] = (int)$value;
            }
        }
        return array_values(array_unique($clean));
    }

    private function buildFilterUrl(array $filters): string {
        $params = http_build_query([
            'scope' => $filters['scope'],
            'space_id' => $filters['space_id'],
            'q' => $filters['q'],
        ]);
        return $this->runData['route']['rad_admin_url'] . '/notifications/view?' . $params;
    }

    private function getScopeOptions(bool $superAdmin): array {
        $scopes = [
            'inbox' => 'Inbox',
            'workspace' => 'Workspace',
            'global' => 'Global',
            'user' => 'Direct',
        ];
        if ($superAdmin) {
            $scopes['all'] = 'All';
        }
        return $scopes;
    }

    private function getSettingDefinitions(): array {
        return [
            'notifications_enabled' => [
                'label' => 'Notifications enabled',
                'description' => 'Master switch for generating notifications from activity metadata.',
                'default' => 'Y',
                'options' => [
                    'Y' => 'Enabled (generate notifications when activity_notify is true)',
                    'N' => 'Disabled (no notifications; activity logging still runs)',
                ],
            ],
            'notifications_mode' => [
                'label' => 'Delivery mode',
                'description' => 'Controls when notifications are created.',
                'default' => 'both',
                'options' => [
                    'realtime' => 'Realtime only (create notifications during live requests)',
                    'ingest' => 'Ingest only (create notifications during log ingestion)',
                    'both' => 'Both (realtime + ingest; ingest skips items already notified)',
                ],
            ],
            'notifications_min_severity' => [
                'label' => 'Minimum severity',
                'description' => 'Ignore activity entries below the selected severity threshold.',
                'default' => 'info',
                'options' => [
                    'info' => 'Info (include info, warn, critical)',
                    'warn' => 'Warn (include warn, critical)',
                    'critical' => 'Critical only',
                ],
            ],
        ];
    }

    private function loadSettings(array $definitions): array {
        $settings = [];
        foreach ($definitions as $handle => $definition) {
            $settings[$handle] = $this->getSettingValue($handle, (string)$definition['default']);
        }
        return $settings;
    }

    private function getSettingValue(string $handle, string $fallback): string {
        $rows = $this->db->select('s_config', ['s_config_handle' => $handle], true);
        if (!empty($rows)) {
            return (string)($rows[0]['s_config_value'] ?? $fallback);
        }
        return $fallback;
    }

    private function saveSetting(string $handle, string $value, string $description): void {
        $existing = $this->db->select('s_config', ['s_config_handle' => $handle], true);
        if (!empty($existing)) {
            $this->db->update(
                's_config',
                ['s_config_value' => $value, 's_description' => $description],
                ['s_config_handle' => $handle]
            );
            return;
        }
        $this->db->insert('s_config', [
            's_config_handle' => $handle,
            's_config_value' => $value,
            's_config_origin' => 'S',
            's_description' => $description,
        ]);
    }

    public function markread() {
        $this->handleAjaxAction('read');
    }

    public function archive() {
        $this->handleAjaxAction('archive');
    }

    private function handleAjaxAction(string $action): void {
        header('Content-Type: application/json');
        if (!$this->notificationService instanceof NotificationService) {
            http_response_code(500);
            echo json_encode(['error' => 'Notification service unavailable']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $ids = $this->sanitizeIds($payload['ids'] ?? []);
        if (empty($ids)) {
            http_response_code(422);
            echo json_encode(['error' => 'No notifications selected.']);
            return;
        }

        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        $superAdmin = $this->isSuperAdmin();
        $allowed = $this->filterAccessibleNotificationIds($ids, $entityId, $superAdmin);
        if (empty($allowed)) {
            http_response_code(403);
            echo json_encode(['error' => 'Unable to modify the selected notifications.']);
            return;
        }

        if ($action === 'archive') {
            $updated = $this->notificationService->archive($allowed, $entityId);
        } else {
            $updated = $this->notificationService->markRead($allowed, $entityId, true);
        }

        echo json_encode(['updated' => $updated]);
    }

    private function filterAccessibleNotificationIds(array $ids, int $entityId, bool $superAdmin): array {
        if (empty($ids)) {
            return [];
        }

        $params = [];
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $placeholder = ':nid' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }

        $sql = sprintf(
            "SELECT id, s_user_id, space_id FROM s_notification WHERE id IN (%s)",
            implode(',', $placeholders)
        );
        $rows = $this->db->query($sql, $params);

        $spaceIds = $this->getUserSpaceIds($entityId);
        $allowed = [];
        foreach ($rows as $row) {
            $rowId = (int)$row['id'];
            if ($superAdmin) {
                $allowed[] = $rowId;
                continue;
            }

            if ((int)$row['s_user_id'] === $entityId) {
                $allowed[] = $rowId;
                continue;
            }

            if ((int)$row['space_id'] > 0 && in_array((int)$row['space_id'], $spaceIds, true)) {
                $allowed[] = $rowId;
                continue;
            }

            if ((int)$row['space_id'] === 0) {
                $allowed[] = $rowId;
            }
        }

        return array_values(array_unique($allowed));
    }

    private function getUserSpaceIds(int $entityId): array {
        if ($this->cachedSpaces !== null) {
            return $this->cachedSpaces;
        }

        $spaces = [];
        if (!empty($this->runData['entity']['spaces'])) {
            $spaces = array_map('intval', array_keys($this->runData['entity']['spaces']));
        }

        if (empty($spaces) && $entityId > 0) {
            $rows = $this->db->query(
                "SELECT DISTINCT space_id FROM s_space_membership WHERE livestatus != '0' AND s_entity_id = :uid",
                [':uid' => $entityId]
            );
            foreach ($rows as $row) {
                $spaces[] = (int)$row['space_id'];
            }
        }

        return $this->cachedSpaces = array_values(array_unique(array_filter($spaces)));
    }

    private function isSuperAdmin(): bool {
        $entity = $this->runData['entity'] ?? [];
        if (!empty($entity['id']) && (int)$entity['id'] === 1) {
            return true;
        }
        $roles = $entity['role_id'] ?? [];
        if (is_array($roles)) {
            return in_array(1, $roles, true);
        }
        return (int)$roles === 1;
    }
}

<?php
namespace Core\App;

/**
 * Profile data service for non-RAD Admin UI pages.
 *
 * Usage (inside a route.* file):
 * $profile = new \Core\App\Profile($this->runData);
 * $view = $profile->overview((int)$this->runData['entity']['id']);
 * $ui = new \Core\App\ProfileUi($this->runData);
 * echo $ui->renderOverview([
 *     'nav' => $profile->getNav('overview', $this->runData['route']['base_url'] . '/profile'),
 *     'data' => $view['data'],
 * ]);
 */
class Profile {
    private array $runData;
    private $db;
    private $errorHandler;
    private AuthUi $authUi;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'] ?? null;
        $this->authUi = new AuthUi($runData);
    }

    public function getSections(): array {
        return [
            'overview' => ['label' => 'Overview'],
            'sessions' => ['label' => 'Sessions'],
            'preferences' => ['label' => 'Preferences'],
            'notifications' => ['label' => 'Notifications'],
            'mfa' => ['label' => 'MFA'],
            'changepwd' => ['label' => 'Change Password'],
        ];
    }

    public function getNav(string $active, string $baseUrl): array {
        $nav = [];
        foreach ($this->getSections() as $key => $meta) {
            $nav[] = [
                'key' => $key,
                'label' => $meta['label'],
                'url' => rtrim($baseUrl, '/') . '/' . $key,
                'active' => $key === $active,
            ];
        }
        return $nav;
    }

    public function overview(int $entityId): array {
        $entity = $this->loadEntity($entityId);
        $definition = $this->decodeDefinition($entity['s_definition'] ?? null);
        $prefs = $this->normalizeProfilePrefs($definition['profile_prefs'] ?? []);
        $notify = $this->normalizeNotificationPrefs($definition['profile_notifications'] ?? []);
        $lastLogin = $this->getLastLoginTimestamp($entityId);
        $timezone = $this->resolveTimezoneFromPrefs($prefs);
        $lastLoginDisplay = $this->formatTimestamp($lastLogin, $timezone);
        $sessionTotal = $this->countSessions($entityId, []);

        return [
            'status' => 'success',
            'message' => null,
            'data' => [
                'entity' => $entity,
                'prefs' => $prefs,
                'notifications' => $notify,
                'last_login' => $lastLogin,
                'last_login_display' => $lastLoginDisplay,
                'timezone' => $timezone,
                'session_total' => $sessionTotal,
                'mfa_enabled' => ($entity['s_enable_mfa'] ?? 'N') === 'Y',
            ],
        ];
    }

    public function sessions(int $entityId, array $filters = []): array {
        $entity = $this->loadEntity($entityId);
        $definition = $this->decodeDefinition($entity['s_definition'] ?? null);
        $prefs = $this->normalizeProfilePrefs($definition['profile_prefs'] ?? []);
        $perPage = (int)($prefs['per_page'] ?? 25);
        $page = max(1, (int)($filters['page'] ?? 1));
        $search = trim((string)($filters['q'] ?? ''));
        $start = trim((string)($filters['start'] ?? ''));
        $end = trim((string)($filters['end'] ?? ''));
        $override = (int)($filters['per_page'] ?? 0);

        if ($this->isAllowedPerPage($override)) {
            $perPage = $override;
            if ($prefs['per_page'] !== $override) {
                $prefs['per_page'] = $override;
                $definition['profile_prefs'] = $prefs;
                $this->saveDefinition($entityId, $definition);
            }
        }

        $offset = ($page - 1) * $perPage;
        $criteria = [
            'q' => $search,
            'start' => $start,
            'end' => $end,
        ];
        $sessions = $this->getSessionsPage($entityId, $perPage, $offset, $criteria);
        $timezone = $this->resolveTimezoneFromPrefs($prefs);
        foreach ($sessions as &$session) {
            $session['createstamp_display'] = $this->formatTimestamp($session['createstamp'] ?? null, $timezone);
        }
        unset($session);
        $total = $this->countSessions($entityId, $criteria);
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

        return [
            'status' => 'success',
            'message' => null,
            'data' => [
                'sessions' => $sessions,
                'filters' => [
                    'q' => $search,
                    'start' => $start,
                    'end' => $end,
                ],
                'timezone' => $timezone,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => max(1, $totalPages),
                ],
            ],
        ];
    }

    public function preferences(int $entityId, array $input = [], bool $save = false): array {
        $entity = $this->loadEntity($entityId);
        $definition = $this->decodeDefinition($entity['s_definition'] ?? null);
        $prefs = $this->normalizeProfilePrefs($definition['profile_prefs'] ?? []);

        if ($save) {
            $prefs = $this->normalizeProfilePrefs($input, $prefs);
            $definition['profile_prefs'] = $prefs;
            $this->saveDefinition($entityId, $definition);
        }

        return [
            'status' => 'success',
            'message' => $save ? 'Preferences updated.' : null,
            'data' => ['prefs' => $prefs],
        ];
    }

    public function notifications(int $entityId, array $input = [], bool $save = false): array {
        $entity = $this->loadEntity($entityId);
        $definition = $this->decodeDefinition($entity['s_definition'] ?? null);
        $prefs = $this->normalizeNotificationPrefs($definition['profile_notifications'] ?? []);

        if ($save) {
            $prefs = $this->normalizeNotificationPrefs($input);
            $definition['profile_notifications'] = $prefs;
            $this->saveDefinition($entityId, $definition);
        }

        return [
            'status' => 'success',
            'message' => $save ? 'Notification preferences updated.' : null,
            'data' => ['prefs' => $prefs],
        ];
    }

    public function changePassword(int $entityId, string $current, string $new, string $confirm): array {
        if ($new === '' || strlen($new) < 8) {
            return ['status' => 'danger', 'message' => 'New password must be at least 8 characters.'];
        }
        if ($new !== $confirm) {
            return ['status' => 'danger', 'message' => 'New passwords do not match.'];
        }
        return $this->authUi->changePassword($entityId, $current, $new);
    }

    public function mfaState(int $entityId): array {
        return $this->authUi->getMfaState($entityId);
    }

    public function mfaAction(int $entityId, string $action, array $data = []): array {
        return $this->authUi->handleMfaAction($entityId, $action, $data);
    }

    private function loadEntity(int $entityId): array {
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            throw new \RuntimeException('User not found.');
        }
        return $rows[0];
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

    private function saveDefinition(int $entityId, array $definition): void {
        $this->db->update('s_entity', [
            's_definition' => json_encode($definition, JSON_UNESCAPED_SLASHES),
        ], ['id' => $entityId]);
    }

    private function normalizeProfilePrefs(array $input, ?array $base = null): array {
        $base = $base ?: [
            'per_page' => 25,
            'density' => 'comfortable',
            'timezone' => $this->getDefaultTimezone(),
            'show_shortcuts' => true,
        ];
        $perPage = isset($input['per_page']) ? (int)$input['per_page'] : (int)($base['per_page'] ?? 25);
        if (!$this->isAllowedPerPage($perPage)) {
            $perPage = (int)($base['per_page'] ?? 25);
        }
        $density = $input['density'] ?? ($base['density'] ?? 'comfortable');
        $timezone = trim((string)($input['timezone'] ?? ($base['timezone'] ?? '')));
        $timezone = \Core\Sys\TimeHelper::resolveTimezone($timezone, $this->getDefaultTimezone());
        $showShortcuts = isset($input['show_shortcuts']) ? (bool)$input['show_shortcuts'] : (bool)($base['show_shortcuts'] ?? true);

        return [
            'per_page' => $perPage,
            'density' => in_array($density, ['comfortable', 'compact'], true) ? $density : 'comfortable',
            'timezone' => (string)$timezone,
            'show_shortcuts' => $showShortcuts,
        ];
    }

    private function getDefaultTimezone(): string {
        return \Core\Sys\TimeHelper::resolveTimezone($this->runData['config']['sys']['timezone'] ?? null, 'UTC');
    }

    private function resolveTimezoneFromPrefs(array $prefs): string {
        return \Core\Sys\TimeHelper::resolveTimezone($prefs['timezone'] ?? '', $this->getDefaultTimezone());
    }

    private function formatTimestamp(?string $timestamp, string $timezone): ?string {
        return \Core\Sys\TimeHelper::formatUtc($timestamp, $timezone);
    }

    private function normalizeNotificationPrefs(array $input): array {
        $defaults = [
            'categories' => [
                'security' => true,
                'workspace' => true,
                'system' => true,
                'product' => false,
            ],
            'channels' => [
                'inapp' => true,
                'email' => false,
                'sms' => false,
            ],
            'frequency' => 'immediate',
        ];
        $categories = $input['categories'] ?? [];
        $channels = $input['channels'] ?? [];
        $frequency = $input['frequency'] ?? $defaults['frequency'];

        return [
            'categories' => [
                'security' => !empty($categories['security']),
                'workspace' => !empty($categories['workspace']),
                'system' => !empty($categories['system']),
                'product' => !empty($categories['product']),
            ],
            'channels' => [
                'inapp' => !empty($channels['inapp']),
                'email' => !empty($channels['email']),
                'sms' => !empty($channels['sms']),
            ],
            'frequency' => $this->normalizeFrequency($frequency),
        ];
    }

    private function normalizeFrequency(string $value): string {
        $value = strtolower(trim($value));
        if (in_array($value, ['immediate', 'daily', 'weekly'], true)) {
            return $value;
        }
        return 'immediate';
    }

    private function getLastLoginTimestamp(int $entityId): ?string {
        $rows = $this->db->query(
            'SELECT createstamp FROM s_entity_session WHERE s_entity_id = :entity_id ORDER BY id DESC LIMIT 1',
            [':entity_id' => $entityId]
        );
        if (empty($rows)) {
            return null;
        }
        return $rows[0]['createstamp'] ?? null;
    }

    private function countSessions(int $entityId, array $filters): int {
        $where = 's_entity_id = :entity_id';
        $params = [':entity_id' => $entityId];
        $this->applySessionFilters($filters, $where, $params);
        $rows = $this->db->query(
            'SELECT COUNT(*) AS total FROM s_entity_session WHERE ' . $where,
            $params
        );
        return (int)($rows[0]['total'] ?? 0);
    }

    private function getSessionsPage(int $entityId, int $limit, int $offset, array $filters): array {
        $where = 's_entity_id = :entity_id';
        $params = [':entity_id' => $entityId];
        $this->applySessionFilters($filters, $where, $params);
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);
        return $this->db->query(
            'SELECT * FROM s_entity_session WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
    }

    private function applySessionFilters(array $filters, string &$where, array &$params): void {
        $search = trim((string)($filters['q'] ?? ''));
        if ($search !== '') {
            $where .= ' AND (s_ip LIKE :search OR s_browser LIKE :search OR s_operating_system LIKE :search OR s_device_type LIKE :search OR s_session_key LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $start = trim((string)($filters['start'] ?? ''));
        if ($start !== '') {
            $where .= ' AND createstamp >= :start_date';
            $params[':start_date'] = $start . ' 00:00:00';
        }
        $end = trim((string)($filters['end'] ?? ''));
        if ($end !== '') {
            $where .= ' AND createstamp <= :end_date';
            $params[':end_date'] = $end . ' 23:59:59';
        }
    }

    private function isAllowedPerPage(int $perPage): bool {
        return in_array($perPage, [10, 25, 50, 100, 200], true);
    }
}

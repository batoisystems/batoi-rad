<?php
namespace RadAdmin;
use DateTime;
class Profile{
    private $runData = [];
    private $db;
    private $errorHandler;
    private $priv;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        $this->priv = new \Core\Sys\PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }

    public function changepwd() {
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'Enter the current password; then input the new password and repeat-enter the new password to confirm. Please make sure that new password is at least 8 characters long and includes at least one letter (both uppercase and lower case), one number, and one special character.';
        // print 'Hi there!';die('here');
        // print '<pre>';print_r($_POST);print '</pre>';
        $request = $this->runData['request'];
        if ($request->method === 'POST') {
            $currentPassword = $request->post['currentPassword'] ?? '';
            $newPassword = $request->post['newPassword'] ?? '';
            $retypePassword = $request->post['retypePassword'] ?? '';
            $userId = (int)($this->runData['entity']['id'] ?? 0);

            if ($userId <= 0) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Access denied.';
                return;
            }

            if ($currentPassword === '' || $newPassword === '' || $retypePassword === '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'All password fields are required.';
                return;
            }

            // Verify if new passwords match
            if ($newPassword !== $retypePassword) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'New passwords do not match. Please try again.';
                return;
            }

            $strengthOk = (
                strlen($newPassword) >= 8 &&
                preg_match('/[A-Z]/', $newPassword) &&
                preg_match('/[a-z]/', $newPassword) &&
                preg_match('/[0-9]/', $newPassword) &&
                preg_match('/[^a-zA-Z0-9]/', $newPassword)
            );
            if (!$strengthOk) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'New password does not meet complexity requirements.';
                return;
            }

            $userRow = $this->db->select('s_entity', ['id' => $userId], true);

            // Check if the user exists
            if (count($userRow) < 1) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'User not found. Please try again.';
                return;
            }

            $userRow = $userRow[0];

            // Verify the current password
            if (!password_verify($currentPassword, $userRow['s_identity_secret'])) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Current password is incorrect. Please try again.';
                return;
            }

            if (password_verify($newPassword, $userRow['s_identity_secret'])) {
                $this->runData['route']['alert'] = 'info';
                $this->runData['route']['alert_message'] = 'New password matches the current password.';
                return;
            }

            // Hash the new password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update the password in the database
            try {
                $updateId = $this->db->update('s_entity', ['s_identity_secret' => $newPasswordHash], ['id' => $userId], ['updatedby' => $userId]);
            } catch (\Throwable $e) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Error updating password. Please try again.';
                return;
            }

            if ($updateId >= 0) {
                $this->runData['route']['alert'] = 'success';
                $this->runData['route']['alert_message'] = 'Password updated successfully!';
                $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                // redirect to logout page
                $redirectUrl = $this->runData['config']['sys']['base_url'].'/login/logout';
                if (headers_sent()) {
                    $this->runData['route']['redirect'] = $redirectUrl;
                    return;
                }
                header("Location: {$redirectUrl}");
                exit;
            } else {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Error updating password in the database. Please try again.';
            }
        }
        $this->runData['route']['h1'] = 'Change Password';
        $this->runData['route']['meta_title'] = $this->runData['route']['h1'];
        $this->runData['route']['meta_description'] = '';
        $this->runData['route']['backlink'] = '/rad-admin/home/view';
        // print '<pre>';print_r($this->runData);print '</pre>';die('here');
        return $this->runData;
    }    

    public function mfa() {
        if (!$this->priv->can('settings') && ($this->runData['entity']['id'] ?? 0) <= 0) {
            throw new \Exception('Access denied.', 403);
        }
        $userId = (int)($this->runData['entity']['id'] ?? 0);
        $userRows = $this->db->select('s_entity', ['id' => $userId], true);
        if (empty($userRows)) {
            throw new \Exception('User not found', 404);
        }
        $user = $userRows[0];
        $auth = [
            'mfa_secret' => $user['s_mfa_secret'] ?? null,
            'mfa_backup_codes' => $this->decodeJsonField($user['s_mfa_backup_codes'] ?? null),
            'enable_mfa' => $user['s_enable_mfa'] ?? 'N',
        ];
        $this->runData['route']['h1'] = 'MFA Settings';
        $this->runData['route']['meta_title'] = 'MFA Settings';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/home/view';

        $mfaService = new \Core\Sys\MfaService($this->runData['config'], $this->errorHandler);
        if ($this->runData['request']->method === 'POST') {
            $action = $this->runData['request']->post['action'] ?? '';
            if ($action === 'reset') {
                $secret = $this->generateSecret();
                $codes = $this->generateBackupCodes();
                $auth['mfa_secret'] = $secret;
                $auth['enable_mfa'] = 'N';
                $auth['mfa_backup_codes'] = array_map(fn($c) => hash('sha256', $c), $codes);
                $this->db->update('s_entity', [
                    's_mfa_secret' => $auth['mfa_secret'],
                    's_enable_mfa' => $auth['enable_mfa'],
                    's_mfa_backup_codes' => json_encode($auth['mfa_backup_codes']),
                ], ['id' => $userId]);
                $this->runData['data']['plain_backup'] = $codes;
                $this->runData['data']['secret'] = $secret;
                $this->runData['request']->setAlert('MFA secret regenerated. Verify to enable.', 'info');
            } elseif ($action === 'verify') {
                $code = trim($this->runData['request']->post['code'] ?? '');
                $secret = $auth['mfa_secret'] ?? '';
                if ($secret && $mfaService->totpVerify($secret, $code)) {
                    $auth['enable_mfa'] = 'Y';
                    $this->db->update('s_entity', ['s_enable_mfa' => $auth['enable_mfa']], ['id' => $userId]);
                    $this->runData['request']->setAlert('MFA enabled.', 'success');
                } else {
                    $this->runData['request']->setAlert('Invalid code.', 'danger');
                }
            } elseif ($action === 'disable') {
                unset($auth['mfa_secret'], $auth['mfa_backup_codes']);
                $auth['enable_mfa'] = 'N';
                $this->db->update('s_entity', [
                    's_mfa_secret' => null,
                    's_mfa_backup_codes' => null,
                    's_enable_mfa' => $auth['enable_mfa'],
                ], ['id' => $userId]);
                $this->runData['request']->setAlert('MFA disabled.', 'info');
            } elseif ($action === 'regen-codes') {
                $codes = $this->generateBackupCodes();
                $auth['mfa_backup_codes'] = array_map(fn($c) => hash('sha256', $c), $codes);
                $this->db->update('s_entity', ['s_mfa_backup_codes' => json_encode($auth['mfa_backup_codes'])], ['id' => $userId]);
                $this->runData['data']['plain_backup'] = $codes;
                $this->runData['request']->setAlert('Backup codes regenerated.', 'success');
            }
        }
        $this->runData['data']['mfa'] = [
            'enabled' => ($auth['enable_mfa'] ?? 'N') === 'Y',
            'secret' => $auth['mfa_secret'] ?? null,
            'backup_count' => isset($auth['mfa_backup_codes']) && is_array($auth['mfa_backup_codes']) ? count($auth['mfa_backup_codes']) : 0,
        ];
        $this->runData['data']['otpauth'] = $this->buildOtpAuth($this->runData['config']['sys']['project_title'] ?? 'RAD', $user['s_identity'], $auth['mfa_secret'] ?? null);
        return $this->runData;
    }

    public function overview() {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            throw new \Exception('Access denied.', 403);
        }
        $entity = $this->loadEntity($entityId);
        $definition = $this->decodeDefinition($entity['s_definition'] ?? null);
        $prefs = $this->getProfilePrefs($definition);
        $lastLogin = $this->getLastLoginTimestamp($entityId);
        $recentSessions = $this->getRecentSessions($entityId, 5);
        $timezone = $this->resolveTimezoneFromPrefs($prefs);
        $lastLoginDisplay = $this->formatTimestamp($lastLogin, $timezone);
        foreach ($recentSessions as &$session) {
            $session['createstamp_display'] = $this->formatTimestamp($session['createstamp'] ?? null, $timezone);
        }
        unset($session);

        $this->runData['route']['h1'] = 'Profile Overview';
        $this->runData['route']['meta_title'] = $this->runData['route']['h1'];
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/home/view';
        $this->runData['data']['entity'] = $entity;
        $this->runData['data']['profile_prefs'] = $prefs;
        $this->runData['data']['mfa_enabled'] = ($entity['s_enable_mfa'] ?? 'N') === 'Y';
        $this->runData['data']['last_login'] = $lastLogin;
        $this->runData['data']['last_login_display'] = $lastLoginDisplay;
        $this->runData['data']['timezone'] = $timezone;
        $this->runData['data']['recent_sessions'] = $recentSessions;
        return $this->runData;
    }

    public function sessions() {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            throw new \Exception('Access denied.', 403);
        }
        $entity = $this->loadEntity($entityId);
        $definition = $this->decodeDefinition($entity['s_definition'] ?? null);
        $prefs = $this->getProfilePrefs($definition);
        $limit = (int)($prefs['per_page'] ?? 25);
        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $search = trim((string)($this->runData['request']->get['q'] ?? ''));
        $start = trim((string)($this->runData['request']->get['start'] ?? ''));
        $end = trim((string)($this->runData['request']->get['end'] ?? ''));
        $perPageOverride = (int)($this->runData['request']->get['per_page'] ?? 0);
        if (in_array($perPageOverride, [10, 25, 50, 100, 200], true)) {
            $limit = $perPageOverride;
            if (($prefs['per_page'] ?? 25) !== $perPageOverride) {
                $prefs['per_page'] = $perPageOverride;
                $definition['profile_prefs'] = $prefs;
                $this->saveDefinition($entityId, $definition);
            }
        }
        $offset = ($page - 1) * $limit;
        $filters = $this->buildSessionFilters($search, $start, $end);
        $sessions = $this->getSessionsPage($entityId, $limit, $offset, $filters);
        $timezone = $this->resolveTimezoneFromPrefs($prefs);
        foreach ($sessions as &$session) {
            $session['createstamp_display'] = $this->formatTimestamp($session['createstamp'] ?? null, $timezone);
        }
        unset($session);
        $total = $this->countSessions($entityId, $filters);
        $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;

        $this->runData['route']['h1'] = 'Profile Sessions';
        $this->runData['route']['meta_title'] = $this->runData['route']['h1'];
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/profile/overview';
        $this->runData['data']['sessions'] = $sessions;
        $this->runData['data']['session_limit'] = $limit;
        $this->runData['data']['session_total'] = $total;
        $this->runData['data']['session_page'] = $page;
        $this->runData['data']['session_pages'] = max(1, $totalPages);
        $this->runData['data']['session_search'] = $search;
        $this->runData['data']['session_start'] = $start;
        $this->runData['data']['session_end'] = $end;
        $this->runData['data']['timezone'] = $timezone;
        return $this->runData;
    }

    public function preferences() {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            throw new \Exception('Access denied.', 403);
        }
        $entity = $this->loadEntity($entityId);
        $definition = $this->decodeDefinition($entity['s_definition'] ?? null);
        $prefs = $this->getProfilePrefs($definition);

        if ($this->runData['request']->method === 'POST') {
            $prefs = $this->normalizeProfilePrefs($this->runData['request']->post ?? [], $prefs);
            $definition['profile_prefs'] = $prefs;
            $this->saveDefinition($entityId, $definition);
            $this->runData['request']->setAlert('Preferences updated.', 'success');
        }

        $this->runData['route']['h1'] = 'Profile Preferences';
        $this->runData['route']['meta_title'] = $this->runData['route']['h1'];
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/profile/overview';
        $this->runData['data']['profile_prefs'] = $prefs;
        return $this->runData;
    }

    public function notifications() {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            throw new \Exception('Access denied.', 403);
        }
        $entity = $this->loadEntity($entityId);
        $definition = $this->decodeDefinition($entity['s_definition'] ?? null);
        $settings = $this->normalizeNotificationPrefs($definition['profile_notifications'] ?? []);

        if ($this->runData['request']->method === 'POST') {
            $settings = [
                'categories' => [
                    'security' => !empty($this->runData['request']->post['security']),
                    'workspace' => !empty($this->runData['request']->post['workspace']),
                    'system' => !empty($this->runData['request']->post['system']),
                    'product' => !empty($this->runData['request']->post['product']),
                ],
                'channels' => [
                    'inapp' => !empty($this->runData['request']->post['channel_inapp']),
                    'email' => !empty($this->runData['request']->post['channel_email']),
                    'sms' => !empty($this->runData['request']->post['channel_sms']),
                ],
                'frequency' => $this->normalizeNotificationFrequency($this->runData['request']->post['frequency'] ?? ''),
            ];
            $definition['profile_notifications'] = $settings;
            $this->saveDefinition($entityId, $definition);
            $this->runData['request']->setAlert('Notification preferences updated.', 'success');
        }

        $this->runData['route']['h1'] = 'Profile Notifications';
        $this->runData['route']['meta_title'] = $this->runData['route']['h1'];
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/profile/overview';
        $this->runData['data']['notification_prefs'] = $settings;
        return $this->runData;
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

    private function loadEntity(int $entityId): array {
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            throw new \Exception('User not found', 404);
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

    private function getProfilePrefs(array $definition): array {
        $defaults = [
            'per_page' => 25,
            'density' => 'comfortable',
            'timezone' => $this->getDefaultTimezone(),
            'show_shortcuts' => true,
        ];
        $existing = $definition['profile_prefs'] ?? [];
        if (!is_array($existing)) {
            $existing = [];
        }
        $merged = array_merge($defaults, $existing);
        $merged['timezone'] = \Core\Sys\TimeHelper::resolveTimezone($merged['timezone'] ?? '', $this->getDefaultTimezone());
        return $merged;
    }

    private function normalizeProfilePrefs(array $input, array $current): array {
        $perPage = (int)($input['per_page'] ?? $current['per_page'] ?? 25);
        if (!in_array($perPage, [10, 25, 50, 100, 200], true)) {
            $perPage = 25;
        }
        $density = $input['density'] ?? $current['density'] ?? 'comfortable';
        if (!in_array($density, ['comfortable', 'compact'], true)) {
            $density = 'comfortable';
        }
        $timezone = trim((string)($input['timezone'] ?? $current['timezone'] ?? ''));
        $timezone = \Core\Sys\TimeHelper::resolveTimezone($timezone, $this->getDefaultTimezone());
        $showShortcuts = !empty($input['show_shortcuts']);
        return [
            'per_page' => $perPage,
            'density' => $density,
            'timezone' => $timezone,
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

    private function getLastLoginTimestamp(int $entityId): ?string {
        $sql = "SELECT createstamp FROM s_entity_session WHERE s_entity_id = :entity_id AND s_browser IS NOT NULL ORDER BY createstamp DESC LIMIT 1";
        $rows = $this->db->query($sql, [':entity_id' => $entityId]);
        if (!empty($rows[0]['createstamp'])) {
            return $rows[0]['createstamp'];
        }
        return null;
    }

    private function getRecentSessions(int $entityId, int $limit): array {
        $sql = "SELECT s_device_type, s_operating_system, s_browser, s_ip, createstamp
                FROM s_entity_session
                WHERE s_entity_id = :entity_id AND s_browser IS NOT NULL
                ORDER BY createstamp DESC
                LIMIT " . (int)$limit;
        return $this->db->query($sql, [':entity_id' => $entityId]);
    }

    private function buildSessionFilters(string $search, string $start, string $end): array {
        $filters = [
            'sql' => '',
            'params' => [],
        ];
        if ($search !== '') {
            $filters['sql'] = " AND (s_browser LIKE :q OR s_operating_system LIKE :q OR s_device_type LIKE :q OR s_ip LIKE :q)";
            $filters['params'][':q'] = '%' . $search . '%';
        }
        if ($start !== '') {
            $filters['sql'] .= " AND createstamp >= :start";
            $filters['params'][':start'] = $start . ' 00:00:00';
        }
        if ($end !== '') {
            $filters['sql'] .= " AND createstamp <= :end";
            $filters['params'][':end'] = $end . ' 23:59:59';
        }
        return $filters;
    }

    private function getSessionsPage(int $entityId, int $limit, int $offset, array $filters): array {
        $sql = "SELECT s_device_type, s_operating_system, s_browser, s_ip, createstamp
                FROM s_entity_session
                WHERE s_entity_id = :entity_id AND s_browser IS NOT NULL"
                . ($filters['sql'] ?? '')
                . " ORDER BY createstamp DESC"
                . " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $params = array_merge([':entity_id' => $entityId], $filters['params'] ?? []);
        return $this->db->query($sql, $params);
    }

    private function countSessions(int $entityId, array $filters): int {
        $sql = "SELECT COUNT(*) AS total
                FROM s_entity_session
                WHERE s_entity_id = :entity_id AND s_browser IS NOT NULL"
                . ($filters['sql'] ?? '');
        $params = array_merge([':entity_id' => $entityId], $filters['params'] ?? []);
        $rows = $this->db->query($sql, $params);
        return !empty($rows[0]['total']) ? (int)$rows[0]['total'] : 0;
    }

    private function normalizeNotificationPrefs(array $settings): array {
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
            'frequency' => 'instant',
        ];
        if (!is_array($settings)) {
            return $defaults;
        }
        if (isset($settings['security']) || isset($settings['workspace'])) {
            $settings = [
                'categories' => [
                    'security' => !empty($settings['security']),
                    'workspace' => !empty($settings['workspace']),
                    'system' => !empty($settings['system']),
                    'product' => !empty($settings['product']),
                ],
            ];
        }
        $settings['categories'] = array_merge($defaults['categories'], $settings['categories'] ?? []);
        $settings['channels'] = array_merge($defaults['channels'], $settings['channels'] ?? []);
        $settings['frequency'] = $this->normalizeNotificationFrequency($settings['frequency'] ?? $defaults['frequency']);
        return array_merge($defaults, $settings);
    }

    private function normalizeNotificationFrequency(string $value): string {
        $value = trim($value);
        $allowed = ['instant', 'daily', 'weekly'];
        return in_array($value, $allowed, true) ? $value : 'instant';
    }

    private function generateSecret(): string {
        $bytes = random_bytes(10);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        foreach (str_split(bin2hex($bytes), 2) as $pair) {
            $secret .= $alphabet[(hexdec($pair) % strlen($alphabet))];
        }
        return $secret;
    }

    private function generateBackupCodes(): array {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }
        return $codes;
    }

    private function buildOtpAuth(string $issuer, string $email, ?string $secret): ?string {
        if (!$secret) {
            return null;
        }
        $label = rawurlencode($issuer . ':' . $email);
        $issuerEnc = rawurlencode($issuer);
        return sprintf('otpauth://totp/%s?secret=%s&issuer=%s', $label, $secret, $issuerEnc);
    }

}

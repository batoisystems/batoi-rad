<?php
namespace RadAdmin;

use Core\Sys\PrivilegeService;

class Sso {
    private array $runData = [];
    private $db;
    private PrivilegeService $priv;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->priv = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
    }

    public function view() {
        $this->guard();

        $providers = [];
        try {
            $providers = $this->db->select('s_sso_provider', [], true, ['id' => 'DESC']);
        } catch (\Throwable $e) {
            $providers = [];
        }

        $summary = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'needs_attention' => 0,
        ];

        foreach ($providers as &$row) {
            $row = $this->decorateProvider($row);
            $summary['total']++;
            if (($row['s_status'] ?? 'active') === 'inactive') {
                $summary['inactive']++;
            } else {
                $summary['active']++;
            }
            if (!empty($row['_diagnostics']['missing']) || !empty($row['_diagnostics']['warnings'])) {
                $summary['needs_attention']++;
            }
        }

        $this->runData['data']['providers'] = $providers;
        $this->runData['data']['summary'] = $summary;
        $this->runData['route']['h1'] = 'SSO Providers';
        $this->runData['route']['meta_title'] = 'SSO Providers';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'SSO' => '',
        ];
        return $this->runData;
    }

    public function setup() {
        $this->guard();

        $this->runData['route']['h1'] = 'SSO Setup Wizard';
        $this->runData['route']['meta_title'] = 'SSO Setup Wizard';
        $this->runData['route']['breadcrumb'] = [
            'SSO' => $this->runData['route']['rad_admin_url'] . '/sso/view',
            'Setup Wizard' => '',
        ];
        $this->runData['data']['presets'] = $this->getProviderPresets();
        return $this->runData;
    }

    public function wizard() {
        $this->guard();

        $presets = $this->getProviderPresets();
        $providerKey = trim((string)($this->runData['request']->get['provider'] ?? $this->runData['request']->post['provider'] ?? 'google'));
        if (!isset($presets[$providerKey])) {
            $providerKey = 'google';
        }
        $preset = $presets[$providerKey];
        $defaults = $this->wizardDefaultsFromPreset($providerKey, $preset);
        $form = $defaults;

        if ($this->runData['request']->method === 'POST') {
            $csrf = $this->runData['request']->post['csrf_token'] ?? '';
            if (!$this->runData['request']->checkCSRFToken($csrf)) {
                $this->runData['request']->setAlert('Invalid request. Please refresh and try again.', 'danger');
            } else {
                $form = $this->collectWizardInput($defaults, $this->runData['request']->post);
                $action = trim((string)($this->runData['request']->post['action'] ?? ''));
                if ($action === 'discover') {
                    $discovered = $this->discoverProviderEndpoints($form);
                    if (!empty($discovered['error'])) {
                        $this->runData['request']->setAlert($discovered['error'], 'warning');
                    } else {
                        $form = array_merge($form, $discovered['values']);
                        $this->runData['request']->setAlert('Provider endpoints discovered successfully.', 'success');
                    }
                } elseif ($action === 'save') {
                    $build = $this->buildPayloadFromInput($form);
                    if (!empty($build['error'])) {
                        $this->runData['request']->setAlert($build['error'], 'danger');
                    } else {
                        try {
                            $newId = (int)$this->db->insert('s_sso_provider', $build['payload']);
                            $this->runData['request']->setAlert('Provider created through wizard.', 'success');
                            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/sso/manage/' . $newId);
                            exit;
                        } catch (\Throwable $e) {
                            $this->runData['request']->setAlert('Unable to save provider: ' . $e->getMessage(), 'danger');
                        }
                    }
                }
            }
        }

        $callbackPath = $this->normalizeRedirectPath((string)($form['s_redirect_path'] ?? '/login/sso-callback'));
        $baseUrl = rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/');

        $this->runData['route']['h1'] = 'SSO Wizard · ' . ($preset['label'] ?? 'Provider');
        $this->runData['route']['meta_title'] = 'SSO Wizard';
        $this->runData['route']['breadcrumb'] = [
            'SSO' => $this->runData['route']['rad_admin_url'] . '/sso/view',
            'Setup Wizard' => $this->runData['route']['rad_admin_url'] . '/sso/setup',
            ($preset['label'] ?? 'Provider') => '',
        ];
        $this->runData['data']['wizard_provider_key'] = $providerKey;
        $this->runData['data']['wizard_preset'] = $preset;
        $this->runData['data']['wizard_form'] = $form;
        $this->runData['data']['wizard_callback_url'] = $baseUrl . $callbackPath;
        return $this->runData;
    }

    public function manage() {
        $this->guard();

        $id = (int)($this->runData['route']['pathparts'][3] ?? 0);
        if ($id <= 0) {
            throw new \Exception('Invalid provider', 404);
        }

        $provider = $this->getProviderOrFail($id);

        if ($this->runData['request']->method === 'POST') {
            $csrf = $this->runData['request']->post['csrf_token'] ?? '';
            if (!$this->runData['request']->checkCSRFToken($csrf)) {
                $this->runData['request']->setAlert('Invalid request. Please refresh and try again.', 'danger');
            } else {
                $action = trim((string)($this->runData['request']->post['action'] ?? ''));
                if ($action === 'activate' || $action === 'deactivate') {
                    $status = $action === 'activate' ? 'active' : 'inactive';
                    $this->db->update('s_sso_provider', [
                        's_status' => $status,
                        'livestatus' => $status === 'active' ? '1' : '0',
                    ], ['id' => $id]);
                    $this->runData['request']->setAlert('Provider status updated.', 'success');
                    header('Location: ' . $this->runData['route']['rad_admin_url'] . '/sso/manage/' . $id);
                    exit;
                }
            }
        }

        $provider = $this->decorateProvider($provider);
        $provider['_latest_test'] = $this->readLatestTest($provider);
        $this->runData['data']['provider'] = $provider;
        $this->runData['route']['h1'] = 'Manage SSO Provider';
        $this->runData['route']['meta_title'] = 'Manage SSO Provider';
        $this->runData['route']['breadcrumb'] = [
            'SSO' => $this->runData['route']['rad_admin_url'] . '/sso/view',
            $provider['s_provider_name'] ?? ('Provider #' . $id) => '',
        ];
        return $this->runData;
    }

    public function test() {
        $this->guard();

        $id = (int)($this->runData['route']['pathparts'][3] ?? 0);
        if ($id <= 0) {
            throw new \Exception('Invalid provider', 404);
        }
        $provider = $this->decorateProvider($this->getProviderOrFail($id));
        $provider['_latest_test'] = $this->readLatestTest($provider);

        $token = bin2hex(random_bytes(16));
        $testTokens = $this->runData['session']->get('sso_test_tokens') ?? [];
        if (!is_array($testTokens)) {
            $testTokens = [];
        }
        $testTokens[$id] = $token;
        $this->runData['session']->set('sso_test_tokens', $testTokens);

        $baseUrl = rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/');
        $returnUrl = $baseUrl . '/rad-admin/sso/testresult/' . $id . '?token=' . urlencode($token);
        $launchUrl = $baseUrl . '/login/sso-init?provider=' . $id . '&redirect=' . urlencode($returnUrl);

        $this->runData['data']['provider'] = $provider;
        $this->runData['data']['test_launch_url'] = $launchUrl;
        $this->runData['data']['test_return_url'] = $returnUrl;
        $this->runData['route']['h1'] = 'SSO Provider Test';
        $this->runData['route']['meta_title'] = 'SSO Provider Test';
        $this->runData['route']['breadcrumb'] = [
            'SSO' => $this->runData['route']['rad_admin_url'] . '/sso/view',
            $provider['s_provider_name'] ?? ('Provider #' . $id) => $this->runData['route']['rad_admin_url'] . '/sso/manage/' . $id,
            'Test' => '',
        ];
        return $this->runData;
    }

    public function testresult() {
        $this->guard();

        $id = (int)($this->runData['route']['pathparts'][3] ?? 0);
        if ($id <= 0) {
            throw new \Exception('Invalid provider', 404);
        }
        $token = trim((string)($this->runData['request']->get['token'] ?? ''));
        $status = trim((string)($this->runData['request']->get['status'] ?? 'passed'));
        $reason = trim((string)($this->runData['request']->get['reason'] ?? 'Login flow completed.'));

        $testTokens = $this->runData['session']->get('sso_test_tokens') ?? [];
        $expected = is_array($testTokens) ? (string)($testTokens[$id] ?? '') : '';
        if ($token === '' || $expected === '' || !hash_equals($expected, $token)) {
            $this->runData['request']->setAlert('SSO test token is invalid or expired. Please run the test again.', 'warning');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/sso/test/' . $id);
            exit;
        }
        unset($testTokens[$id]);
        $this->runData['session']->set('sso_test_tokens', $testTokens);

        $passed = strtolower($status) !== 'failed';
        $this->saveTestResult($id, $passed, $reason);
        $this->runData['request']->setAlert(
            $passed ? 'SSO test passed and result recorded.' : ('SSO test failed: ' . $reason),
            $passed ? 'success' : 'danger'
        );
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/sso/test/' . $id);
        exit;
    }

    public function issue() {
        $this->guard();

        $this->runData['route']['h1'] = 'Issue SSO Assertion';
        $this->runData['route']['meta_title'] = 'Issue SSO Assertion';
        $this->runData['route']['breadcrumb'] = [
            'SSO' => $this->runData['route']['rad_admin_url'] . '/sso/view',
            'Issue' => '',
        ];
        if ($this->runData['request']->method === 'POST') {
            $csrf = $this->runData['request']->post['csrf_token'] ?? '';
            if (!$this->runData['request']->checkCSRFToken($csrf)) {
                $this->runData['request']->setAlert('Invalid request. Please refresh and try again.', 'danger');
                return $this->runData;
            }

            $email = trim($this->runData['request']->post['email'] ?? '');
            $aud = trim($this->runData['request']->post['aud'] ?? 'rad-admin');
            $redirect = trim($this->runData['request']->post['redirect'] ?? '');
            $ttl = max(60, (int)($this->runData['request']->post['ttl'] ?? 900));
            if ($email === '') {
                $this->runData['request']->setAlert('Email is required.', 'danger');
                return $this->runData;
            }
            $secret = $this->runData['config']['app']['sso_shared_secret'] ?? '';
            if ($secret === '') {
                $this->runData['request']->setAlert('SSO shared secret not configured.', 'danger');
                return $this->runData;
            }
            $payload = [
                'email' => $email,
                'aud' => $aud,
                'exp' => time() + $ttl,
            ];
            $assertion = $this->signAssertion($payload, $secret);
            $this->runData['data']['assertion'] = $assertion;
            $loginUrl = rtrim($this->runData['config']['sys']['base_url'], '/') . '/login/sso?assertion=' . urlencode($assertion);
            if ($redirect !== '') {
                $loginUrl .= '&redirect=' . urlencode($redirect);
            }
            $this->runData['data']['login_url'] = $loginUrl;
            $this->runData['request']->setAlert('Assertion generated.', 'success');
        }
        return $this->runData;
    }

    public function configassistant() {
        $this->guard();

        $defaults = $this->getConfigAssistantDefaults();
        $selectedMode = trim((string)($this->runData['request']->get['mode'] ?? $this->runData['request']->post['mode'] ?? $defaults['mode']));
        if (!in_array($selectedMode, ['disabled', 'server', 'client'], true)) {
            $selectedMode = $defaults['mode'];
        }

        $form = $this->collectConfigAssistantInput($defaults, $this->runData['request']->post ?? []);
        $generatedSnippet = '';
        $testReport = null;
        $keyGenerationReport = null;

        if ($this->runData['request']->method === 'POST') {
            $csrf = $this->runData['request']->post['csrf_token'] ?? '';
            if (!$this->runData['request']->checkCSRFToken($csrf)) {
                $this->runData['request']->setAlert('Invalid request. Please refresh and try again.', 'danger');
            } else {
                $action = trim((string)($this->runData['request']->post['action'] ?? 'generate'));
                if ($action === 'test') {
                    $testReport = $this->runSsoRuntimeConfigTest();
                    $this->runData['request']->setAlert(
                        $testReport['summary']['errors'] > 0
                            ? 'SSO configuration test found errors. Review the checklist below.'
                            : 'SSO configuration test passed without errors.',
                        $testReport['summary']['errors'] > 0 ? 'warning' : 'success'
                    );
                } elseif ($action === 'generate_keys') {
                    if ($selectedMode !== 'server') {
                        $this->runData['request']->setAlert('Key generation is available only for server mode.', 'warning');
                    } else {
                        $result = $this->generateServerKeys($form);
                        if (!empty($result['error'])) {
                            $this->runData['request']->setAlert($result['error'], 'danger');
                            $keyGenerationReport = [
                                'summary' => ['ok' => 0, 'warning' => 0, 'error' => 1],
                                'items' => [
                                    $this->diagItem('error', 'Key generation', (string)$result['error']),
                                ],
                            ];
                        } else {
                            $form['server_private_key_path'] = $result['private_path'] ?? ($form['server_private_key_path'] ?? '');
                            $form['server_public_key_path'] = $result['public_path'] ?? ($form['server_public_key_path'] ?? '');
                            $this->runData['request']->setAlert($result['message'] ?? 'Server key pair generated.', 'success');
                            $keyGenerationReport = $this->buildServerKeyReadinessReport($form);
                        }
                    }
                } else {
                    $generatedSnippet = $this->buildAuthConfigSnippet($selectedMode, $form);
                    $this->runData['request']->setAlert('Snippet generated. Paste into rad/config/sys.inc.php under the main return array.', 'success');
                }
            }
        }

        $this->runData['route']['h1'] = 'SSO Config Assistant';
        $this->runData['route']['meta_title'] = 'SSO Config Assistant';
        $this->runData['route']['breadcrumb'] = [
            'SSO' => $this->runData['route']['rad_admin_url'] . '/sso/view',
            'Config Assistant' => '',
        ];
        $this->runData['data']['sso_config_assistant'] = [
            'mode' => $selectedMode,
            'form' => $form,
            'runtime_role' => $this->getConfiguredSsoRoleFromRuntime(),
            'snippet' => $generatedSnippet,
            'test_report' => $testReport,
            'key_generation_report' => $keyGenerationReport,
        ];
        return $this->runData;
    }

    private function signAssertion(array $payload, string $secret): string {
        $payloadJson = json_encode($payload);
        $payloadB64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $payloadB64, $secret, true);
        $sigB64 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        return $payloadB64 . '.' . $sigB64;
    }

    public function add() {
        $this->guard();

        $this->runData['route']['h1'] = 'Add SSO Provider';
        $this->runData['route']['meta_title'] = 'Add SSO Provider';
        $this->runData['route']['breadcrumb'] = [
            'SSO' => $this->runData['route']['rad_admin_url'] . '/sso/view',
            'Add' => '',
        ];
        if ($this->runData['request']->method === 'POST') {
            return $this->handleSave();
        }
        return $this->runData;
    }

    public function edit() {
        $this->guard();

        $id = (int)($this->runData['route']['pathparts'][3] ?? 0);
        if ($id <= 0) {
            throw new \Exception('Invalid provider', 404);
        }

        $this->runData['data']['provider'] = $this->getProviderOrFail($id);
        $this->runData['route']['h1'] = 'Edit SSO Provider';
        $this->runData['route']['meta_title'] = 'Edit SSO Provider';
        $this->runData['route']['breadcrumb'] = [
            'SSO' => $this->runData['route']['rad_admin_url'] . '/sso/view',
            'Edit' => '',
        ];
        if ($this->runData['request']->method === 'POST') {
            return $this->handleSave($id);
        }
        return $this->runData;
    }

    private function handleSave(?int $id = null) {
        $csrf = $this->runData['request']->post['csrf_token'] ?? '';
        if (!$this->runData['request']->checkCSRFToken($csrf)) {
            $this->runData['request']->setAlert('Invalid request. Please refresh and try again.', 'danger');
            $this->runData['data']['provider'] = $this->runData['request']->post;
            return $this->runData;
        }

        $build = $this->buildPayloadFromInput($this->runData['request']->post);
        if (!empty($build['error'])) {
            return $this->saveError($build['error']);
        }
        $payload = $build['payload'];

        try {
            if ($id) {
                $this->db->update('s_sso_provider', $payload, ['id' => $id]);
                $this->runData['request']->setAlert('Provider updated.', 'success');
            } else {
                $this->db->insert('s_sso_provider', $payload);
                $this->runData['request']->setAlert('Provider added.', 'success');
            }
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/sso/view');
            exit;
        } catch (\Throwable $e) {
            return $this->saveError('Unable to save provider: ' . $e->getMessage());
        }
    }

    private function saveError(string $message) {
        $this->runData['request']->setAlert($message, 'danger');
        $this->runData['data']['provider'] = $this->runData['request']->post;
        return $this->runData;
    }

    private function readLatestTest(array $provider): ?array {
        $configRaw = trim((string)($provider['s_sso_configuration'] ?? ''));
        if ($configRaw === '') {
            return null;
        }
        $decoded = json_decode($configRaw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $meta = $decoded['_rad_admin'] ?? null;
        if (!is_array($meta)) {
            return null;
        }
        $last = $meta['sso_last_test'] ?? null;
        return is_array($last) ? $last : null;
    }

    private function saveTestResult(int $providerId, bool $passed, string $reason): void {
        $provider = $this->getProviderOrFail($providerId);
        $configRaw = trim((string)($provider['s_sso_configuration'] ?? ''));
        $config = [];
        if ($configRaw !== '') {
            $decoded = json_decode($configRaw, true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }
        if (!isset($config['_rad_admin']) || !is_array($config['_rad_admin'])) {
            $config['_rad_admin'] = [];
        }
        $config['_rad_admin']['sso_last_test'] = [
            'passed' => $passed,
            'status' => $passed ? 'passed' : 'failed',
            'reason' => $reason,
            'at' => date('Y-m-d H:i:s'),
            'by_entity_id' => (int)($this->runData['entity']['id'] ?? 0),
        ];
        $this->db->update('s_sso_provider', [
            's_sso_configuration' => json_encode($config, JSON_UNESCAPED_SLASHES),
        ], ['id' => $providerId]);
    }

    private function guard(): void {
        if (!$this->priv->can('settings')) {
            throw new \Exception('Access denied.', 403);
        }
    }

    private function getProviderOrFail(int $id): array {
        $rows = $this->db->select('s_sso_provider', ['id' => $id], true);
        if (count($rows) !== 1) {
            throw new \Exception('Invalid provider', 404);
        }
        return $rows[0];
    }

    private function decorateProvider(array $row): array {
        $row['status_label'] = ($row['s_status'] ?? '') === 'inactive' ? 'Inactive' : 'Active';
        $row['type_label'] = $this->providerTypeLabel((string)($row['s_provider_type'] ?? ''));
        $row['s_redirect_path'] = $this->normalizeRedirectPath((string)($row['s_redirect_path'] ?? ''));
        $row['s_scopes'] = $this->normalizeScopes((string)($row['s_scopes'] ?? ''));
        $row['_diagnostics'] = $this->diagnosticsForProvider($row);
        $row['_urls'] = $this->providerUrls($row);
        return $row;
    }

    private function diagnosticsForProvider(array $row): array {
        $missing = [];
        $warnings = [];

        if (trim((string)($row['s_provider_name'] ?? '')) === '') {
            $missing[] = 'Provider name';
        }

        $type = trim((string)($row['s_provider_type'] ?? ''));
        if (in_array($type, ['google', 'microsoft', 'oidc', 'oauth2'], true)) {
            if (trim((string)($row['s_client_id'] ?? '')) === '') {
                $missing[] = 'Client ID';
            }
            if (trim((string)($row['s_auth_url'] ?? '')) === '') {
                $missing[] = 'Auth URL';
            }
            if (trim((string)($row['s_token_url'] ?? '')) === '') {
                $missing[] = 'Token URL';
            }
        }

        $redirectPath = trim((string)($row['s_redirect_path'] ?? ''));
        if ($redirectPath === '') {
            $warnings[] = 'Redirect path will default to /login/sso-callback';
        } elseif ($redirectPath === '/login/sso/callback') {
            $warnings[] = 'Legacy callback path detected; canonical path is /login/sso-callback';
        }

        $status = (string)($row['s_status'] ?? 'active');
        if ($status === 'inactive') {
            $warnings[] = 'Provider is inactive and cannot be used for login';
        }

        return [
            'missing' => $missing,
            'warnings' => $warnings,
            'is_ready' => empty($missing),
        ];
    }

    private function providerUrls(array $row): array {
        $baseUrl = rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/');
        $id = (int)($row['id'] ?? 0);
        $redirectPath = $this->normalizeRedirectPath((string)($row['s_redirect_path'] ?? ''));

        return [
            'init_url' => $id > 0 ? $baseUrl . '/login/sso-init?provider=' . $id : '',
            'callback_url' => $baseUrl . $redirectPath,
        ];
    }

    private function normalizeRedirectPath(string $path): string {
        $path = trim($path);
        if ($path === '') {
            return '/login/sso-callback';
        }

        if (preg_match('#^https?://#i', $path)) {
            $parsed = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsed) ? $parsed : '';
        }

        if ($path === '/login/sso/callback') {
            return '/login/sso-callback';
        }

        if ($path === '') {
            return '/login/sso-callback';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    private function normalizeScopes(string $scopes): string {
        $scopes = trim($scopes);
        if ($scopes === '') {
            return 'openid profile email';
        }

        $scopes = str_replace(',', ' ', $scopes);
        $scopes = preg_replace('/\s+/', ' ', $scopes) ?? $scopes;
        return trim($scopes);
    }

    private function isValidUrl(string $url): bool {
        if ($url === '') {
            return false;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function buildPayloadFromInput(array $input): array {
        $name = trim((string)($input['s_provider_name'] ?? ''));
        $config = trim((string)($input['s_sso_configuration'] ?? ''));
        $type = trim((string)($input['s_provider_type'] ?? ''));
        $clientId = trim((string)($input['s_client_id'] ?? ''));
        $clientSecret = trim((string)($input['s_client_secret'] ?? ''));
        $issuer = trim((string)($input['s_issuer'] ?? ''));
        $authUrl = trim((string)($input['s_auth_url'] ?? ''));
        $tokenUrl = trim((string)($input['s_token_url'] ?? ''));
        $userinfoUrl = trim((string)($input['s_userinfo_url'] ?? ''));
        $jwksUrl = trim((string)($input['s_jwks_url'] ?? ''));
        $redirectPath = $this->normalizeRedirectPath((string)($input['s_redirect_path'] ?? ''));
        $scopes = $this->normalizeScopes((string)($input['s_scopes'] ?? ''));
        $claimMapRaw = trim((string)($input['s_claim_map'] ?? ''));
        $status = trim((string)($input['s_status'] ?? 'active'));
        $notes = trim((string)($input['s_notes'] ?? ''));

        if ($name === '') {
            return ['error' => 'Provider name is required.'];
        }

        $providerType = $type !== '' ? $type : null;
        $requiresOidcFields = in_array($providerType, ['google', 'microsoft', 'oidc', 'oauth2', 'okta', 'auth0'], true);
        if ($requiresOidcFields) {
            if ($clientId === '' || $authUrl === '' || $tokenUrl === '') {
                return ['error' => 'Client ID, Auth URL, and Token URL are required for this provider type.'];
            }
        }

        $urlFields = [
            'Issuer' => $issuer,
            'Auth URL' => $authUrl,
            'Token URL' => $tokenUrl,
            'Userinfo URL' => $userinfoUrl,
            'JWKS URL' => $jwksUrl,
        ];
        foreach ($urlFields as $label => $value) {
            if ($value !== '' && !$this->isValidUrl($value)) {
                return ['error' => $label . ' must be a valid URL.'];
            }
        }

        $claimMap = null;
        if ($claimMapRaw !== '') {
            $decoded = json_decode($claimMapRaw, true);
            if (!is_array($decoded)) {
                return ['error' => 'Claim map must be valid JSON.'];
            }
            foreach ($decoded as $k => $v) {
                if (!is_string($k) || !is_string($v)) {
                    return ['error' => 'Claim map keys and values must be strings.'];
                }
            }
            $claimMap = json_encode($decoded);
        }

        if ($config !== '') {
            $configDecoded = json_decode($config, true);
            if (!is_array($configDecoded)) {
                return ['error' => 'Raw configuration must be valid JSON.'];
            }
            $config = json_encode($configDecoded, JSON_UNESCAPED_SLASHES);
        }

        $status = $status === 'inactive' ? 'inactive' : 'active';
        $payload = [
            's_provider_name' => $name,
            's_sso_configuration' => $config !== '' ? $config : null,
            's_provider_type' => $providerType,
            's_client_id' => $clientId !== '' ? $clientId : null,
            's_client_secret' => $clientSecret !== '' ? $clientSecret : null,
            's_issuer' => $issuer !== '' ? $issuer : null,
            's_auth_url' => $authUrl !== '' ? $authUrl : null,
            's_token_url' => $tokenUrl !== '' ? $tokenUrl : null,
            's_userinfo_url' => $userinfoUrl !== '' ? $userinfoUrl : null,
            's_jwks_url' => $jwksUrl !== '' ? $jwksUrl : null,
            's_redirect_path' => $redirectPath,
            's_scopes' => $scopes,
            's_claim_map' => $claimMap,
            's_status' => $status,
            's_notes' => $notes !== '' ? $notes : null,
            'livestatus' => $status === 'active' ? '1' : '0',
        ];
        return ['payload' => $payload];
    }

    private function getProviderPresets(): array {
        return [
            'google' => [
                'key' => 'google',
                'label' => 'Google',
                'description' => 'Google Workspace or Google Accounts OIDC setup.',
                'provider_type' => 'google',
                'issuer' => 'https://accounts.google.com',
                'scopes' => 'openid profile email',
                'claim_map' => ['email' => 'email', 'name' => 'name', 'sub' => 'sub'],
                'instructions' => [
                    'Open Google Cloud Console and create an OAuth client for a web app.',
                    'Copy the callback URL from this page and add it under Authorized redirect URIs.',
                    'Paste the Client ID and Client Secret here, then run discovery.',
                ],
                'friendly_help' => [
                    'before_you_start' => [
                        'You need access to the Google Cloud project where your login app is managed.',
                        'Keep this page open so you can copy the callback URL exactly.',
                    ],
                    'field_help' => [
                        's_client_id' => 'Looks like a long ID ending with `.apps.googleusercontent.com`.',
                        's_client_secret' => 'Secret shown once in Google Cloud. Paste exactly as copied.',
                        's_issuer' => 'For Google this is usually fixed as `https://accounts.google.com`.',
                    ],
                ],
                'resources' => [
                    'docs_url' => 'https://developers.google.com/identity/openid-connect/openid-connect',
                    'video_url' => 'https://www.youtube.com/results?search_query=google+oauth+redirect+uri+setup',
                ],
            ],
            'microsoft' => [
                'key' => 'microsoft',
                'label' => 'Microsoft Entra',
                'description' => 'Microsoft Entra ID / Azure AD OIDC setup.',
                'provider_type' => 'microsoft',
                'issuer_template' => 'https://login.microsoftonline.com/{tenant}/v2.0',
                'tenant_label' => 'Tenant ID or Domain',
                'tenant_placeholder' => 'common | organizations | <tenant-id>',
                'scopes' => 'openid profile email',
                'claim_map' => ['email' => 'preferred_username', 'name' => 'name', 'sub' => 'sub'],
                'instructions' => [
                    'Register an app in Microsoft Entra ID.',
                    'Add the callback URL under Authentication > Redirect URIs.',
                    'Copy Application (client) ID and create a client secret to paste here.',
                ],
                'friendly_help' => [
                    'before_you_start' => [
                        'You need tenant admin/app registration permissions in Entra ID.',
                        'If unsure, start with tenant value `common` and move to tenant-id later.',
                    ],
                    'field_help' => [
                        'wizard_tenant_value' => 'Use `common` for broad testing, or paste your exact tenant ID/domain for production.',
                        's_client_id' => 'This is the Application (client) ID from Entra app overview.',
                        's_client_secret' => 'Create a new client secret under Certificates & secrets.',
                    ],
                ],
                'resources' => [
                    'docs_url' => 'https://learn.microsoft.com/en-us/entra/identity-platform/v2-protocols-oidc',
                    'video_url' => 'https://www.youtube.com/results?search_query=microsoft+entra+oidc+app+registration+redirect+uri',
                ],
            ],
            'okta' => [
                'key' => 'okta',
                'label' => 'Okta',
                'description' => 'Okta OIDC with default authorization server.',
                'provider_type' => 'okta',
                'issuer_template' => 'https://{domain}/oauth2/default',
                'tenant_label' => 'Okta Domain',
                'tenant_placeholder' => 'dev-123456.okta.com',
                'scopes' => 'openid profile email',
                'claim_map' => ['email' => 'email', 'name' => 'name', 'sub' => 'sub'],
                'instructions' => [
                    'Create an OIDC Web App in Okta.',
                    'Add the callback URL as the Sign-in redirect URI.',
                    'Use Client ID and Client Secret from the Okta app settings.',
                ],
                'friendly_help' => [
                    'before_you_start' => [
                        'You need your Okta domain (for example `dev-123456.okta.com`).',
                        'Use the default authorization server unless your org uses a custom one.',
                    ],
                    'field_help' => [
                        'wizard_tenant_value' => 'Only the domain part, without `https://`.',
                        's_issuer' => 'Expected format: `https://<domain>/oauth2/default`.',
                    ],
                ],
                'resources' => [
                    'docs_url' => 'https://developer.okta.com/docs/guides/sign-into-web-app-redirect/',
                    'video_url' => 'https://www.youtube.com/results?search_query=okta+oidc+web+app+redirect+uri+setup',
                ],
            ],
            'auth0' => [
                'key' => 'auth0',
                'label' => 'Auth0',
                'description' => 'Auth0 OIDC application setup.',
                'provider_type' => 'auth0',
                'issuer_template' => 'https://{domain}/',
                'tenant_label' => 'Auth0 Domain',
                'tenant_placeholder' => 'your-tenant.us.auth0.com',
                'scopes' => 'openid profile email',
                'claim_map' => ['email' => 'email', 'name' => 'name', 'sub' => 'sub'],
                'instructions' => [
                    'Create a Regular Web Application in Auth0.',
                    'Paste callback URL into Allowed Callback URLs.',
                    'Copy Client ID and Client Secret from application settings.',
                ],
                'friendly_help' => [
                    'before_you_start' => [
                        'You need your Auth0 tenant domain (for example `myteam.us.auth0.com`).',
                        'Do not include extra spaces while pasting URLs or secrets.',
                    ],
                    'field_help' => [
                        'wizard_tenant_value' => 'Only domain name, without `https://`.',
                        's_issuer' => 'Expected format: `https://<your-domain>/`.',
                    ],
                ],
                'resources' => [
                    'docs_url' => 'https://auth0.com/docs/get-started/authentication-and-authorization-flow/authorization-code-flow',
                    'video_url' => 'https://www.youtube.com/results?search_query=auth0+regular+web+application+openid+connect+setup',
                ],
            ],
            'custom' => [
                'key' => 'custom',
                'label' => 'Custom OIDC',
                'description' => 'Use discovery or fill all endpoints manually.',
                'provider_type' => 'oidc',
                'issuer' => '',
                'scopes' => 'openid profile email',
                'claim_map' => ['email' => 'email', 'name' => 'name', 'sub' => 'sub'],
                'instructions' => [
                    'Ask your identity provider team for issuer URL, client ID, and client secret.',
                    'Run discovery if your provider supports OpenID discovery.',
                    'Save as inactive first, run test login, then activate.',
                ],
                'friendly_help' => [
                    'before_you_start' => [
                        'Keep provider documentation handy while filling these fields.',
                        'If discovery fails, you can still fill endpoint URLs manually.',
                    ],
                    'field_help' => [
                        's_auth_url' => 'Endpoint where users are sent to sign in.',
                        's_token_url' => 'Endpoint used by system to exchange auth code for tokens.',
                        's_jwks_url' => 'Public key endpoint used to verify token signatures.',
                    ],
                ],
                'resources' => [
                    'docs_url' => 'https://openid.net/specs/openid-connect-core-1_0.html',
                    'video_url' => 'https://www.youtube.com/results?search_query=openid+connect+setup+for+beginners',
                ],
            ],
        ];
    }

    private function wizardDefaultsFromPreset(string $providerKey, array $preset): array {
        $issuer = trim((string)($preset['issuer'] ?? ''));
        if ($issuer === '' && !empty($preset['issuer_template'])) {
            $issuer = '';
        }
        return [
            'provider' => $providerKey,
            'wizard_tenant_value' => '',
            's_provider_name' => $preset['label'] . ' SSO',
            's_provider_type' => $preset['provider_type'] ?? 'oidc',
            's_client_id' => '',
            's_client_secret' => '',
            's_issuer' => $issuer,
            's_auth_url' => '',
            's_token_url' => '',
            's_userinfo_url' => '',
            's_jwks_url' => '',
            's_redirect_path' => '/login/sso-callback',
            's_scopes' => $preset['scopes'] ?? 'openid profile email',
            's_claim_map' => json_encode($preset['claim_map'] ?? ['email' => 'email', 'name' => 'name', 'sub' => 'sub'], JSON_PRETTY_PRINT),
            's_notes' => 'Configured via SSO wizard (' . ($preset['label'] ?? 'Custom') . ').',
            's_sso_configuration' => '',
            's_status' => 'inactive',
        ];
    }

    private function collectWizardInput(array $defaults, array $input): array {
        $state = $defaults;
        foreach ($state as $key => $defaultVal) {
            if (array_key_exists($key, $input)) {
                $state[$key] = is_string($input[$key]) ? trim($input[$key]) : $input[$key];
            }
        }

        $provider = (string)($state['provider'] ?? 'custom');
        $presets = $this->getProviderPresets();
        $preset = $presets[$provider] ?? $presets['custom'];
        $tenantValue = trim((string)($state['wizard_tenant_value'] ?? ''));
        if ($tenantValue !== '' && !empty($preset['issuer_template']) && trim((string)$state['s_issuer']) === '') {
            $state['s_issuer'] = str_replace('{domain}', $tenantValue, str_replace('{tenant}', $tenantValue, (string)$preset['issuer_template']));
        }

        return $state;
    }

    private function discoverProviderEndpoints(array $form): array {
        $issuer = rtrim(trim((string)($form['s_issuer'] ?? '')), '/');
        if ($issuer === '') {
            return ['error' => 'Issuer URL is required for discovery.'];
        }
        if (!$this->isValidUrl($issuer)) {
            return ['error' => 'Issuer URL is invalid.'];
        }

        $wellKnown = $issuer . '/.well-known/openid-configuration';
        $doc = $this->httpGetJson($wellKnown);
        if (!is_array($doc)) {
            return ['error' => 'Unable to fetch OpenID configuration from issuer.'];
        }

        $values = [
            's_issuer' => trim((string)($doc['issuer'] ?? $issuer)),
            's_auth_url' => trim((string)($doc['authorization_endpoint'] ?? ($form['s_auth_url'] ?? ''))),
            's_token_url' => trim((string)($doc['token_endpoint'] ?? ($form['s_token_url'] ?? ''))),
            's_userinfo_url' => trim((string)($doc['userinfo_endpoint'] ?? ($form['s_userinfo_url'] ?? ''))),
            's_jwks_url' => trim((string)($doc['jwks_uri'] ?? ($form['s_jwks_url'] ?? ''))),
        ];
        return ['values' => $values];
    }

    private function httpGetJson(string $url): ?array {
        if (!$this->isValidUrl($url)) {
            return null;
        }
        $response = null;
        $status = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $response = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 15,
                    'header' => "Accept: application/json\r\n",
                ],
            ]);
            $response = @file_get_contents($url, false, $context);
            $headers = $http_response_header ?? [];
            if (is_array($headers) && isset($headers[0]) && preg_match('/\s(\d{3})\s/', (string)$headers[0], $m)) {
                $status = (int)$m[1];
            }
        }

        if ($response === false || $response === null || $status >= 400) {
            return null;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function getConfigAssistantDefaults(): array {
        $auth = $this->runData['config']['auth'] ?? [];
        $server = is_array($auth['sso_server'] ?? null) ? $auth['sso_server'] : [];
        $client = is_array($auth['sso_client'] ?? null) ? $auth['sso_client'] : [];
        $keySuggestion = $this->suggestSsoServerKeyPaths();

        return [
            'mode' => $this->getConfiguredSsoRoleFromRuntime(),
            'server_issuer' => trim((string)($server['issuer'] ?? rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/'))),
            'server_private_key_path' => trim((string)($server['private_key_path'] ?? $keySuggestion['private'])),
            'server_public_key_path' => trim((string)($server['public_key_path'] ?? $keySuggestion['public'])),
            'server_access_token_ttl' => (string)((int)($server['access_token_ttl'] ?? 900)),
            'server_id_token_ttl' => (string)((int)($server['id_token_ttl'] ?? 900)),
            'server_default_client_level' => strtolower(trim((string)($server['default_client_level'] ?? 'verify_only'))),
            'server_include_sample_clients' => '1',
            'server_overwrite_keys' => '0',
            'client_label' => trim((string)($client['label'] ?? 'Sign in with Organization SSO')),
            'client_server_base_url' => trim((string)($client['server_base_url'] ?? '')),
            'client_server_issuer' => trim((string)($client['server_issuer'] ?? '')),
            'client_client_id' => trim((string)($client['client_id'] ?? '')),
            'client_client_secret' => trim((string)($client['client_secret'] ?? '')),
            'client_integration_level' => strtolower(trim((string)($client['integration_level'] ?? 'verify_only'))),
            'client_scope' => trim((string)($client['scope'] ?? 'openid profile email')),
            'client_authorize_path' => trim((string)($client['authorize_path'] ?? '/login/sso-server-authorize')),
            'client_token_path' => trim((string)($client['token_path'] ?? '/login/sso-server-token')),
            'client_userinfo_path' => trim((string)($client['userinfo_path'] ?? '/login/sso-server-userinfo')),
            'client_jwks_path' => trim((string)($client['jwks_path'] ?? '/login/sso-server-jwks')),
            'server_private_key_candidates' => $keySuggestion['private_candidates'],
            'server_public_key_candidates' => $keySuggestion['public_candidates'],
            'server_key_folder_hint' => $keySuggestion['folder_hint'],
        ];
    }

    private function suggestSsoServerKeyPaths(): array {
        $dirs = $this->runData['config']['dir'] ?? [];
        $roots = [];
        foreach (['data', 'site', 'log'] as $key) {
            $value = trim((string)($dirs[$key] ?? ''));
            if ($value !== '') {
                $roots[] = rtrim($value, '/');
            }
        }
        $roots = array_values(array_unique($roots));

        $privateCandidates = [];
        $publicCandidates = [];
        foreach ($roots as $root) {
            $privateCandidates[] = $root . '/keys/sso-private.pem';
            $privateCandidates[] = $root . '/security/sso-private.pem';
            $privateCandidates[] = $root . '/secure/sso-private.pem';
            $publicCandidates[] = $root . '/keys/sso-public.pem';
            $publicCandidates[] = $root . '/security/sso-public.pem';
            $publicCandidates[] = $root . '/secure/sso-public.pem';
        }
        $privateCandidates = array_values(array_unique($privateCandidates));
        $publicCandidates = array_values(array_unique($publicCandidates));

        $private = $this->pickExistingPathOrFirst($privateCandidates);
        $public = $this->pickExistingPathOrFirst($publicCandidates);

        $folderHint = '';
        if ($private !== '') {
            $folderHint = dirname($private);
        } elseif (!empty($privateCandidates)) {
            $folderHint = dirname($privateCandidates[0]);
        }

        return [
            'private' => $private,
            'public' => $public,
            'private_candidates' => array_slice($privateCandidates, 0, 3),
            'public_candidates' => array_slice($publicCandidates, 0, 3),
            'folder_hint' => $folderHint,
        ];
    }

    private function pickExistingPathOrFirst(array $candidates): string {
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return $candidates[0] ?? '';
    }

    private function collectConfigAssistantInput(array $defaults, array $input): array {
        $state = $defaults;
        foreach ($defaults as $key => $defaultValue) {
            if (array_key_exists($key, $input)) {
                $state[$key] = is_string($input[$key]) ? trim($input[$key]) : $input[$key];
            }
        }
        $state['server_default_client_level'] = ($state['server_default_client_level'] ?? 'verify_only') === 'full_integration' ? 'full_integration' : 'verify_only';
        $state['client_integration_level'] = ($state['client_integration_level'] ?? 'verify_only') === 'full_integration' ? 'full_integration' : 'verify_only';
        $state['server_include_sample_clients'] = !empty($state['server_include_sample_clients']) ? '1' : '0';
        $state['server_overwrite_keys'] = !empty($state['server_overwrite_keys']) ? '1' : '0';
        return $state;
    }

    private function generateServerKeys(array $form): array {
        if (!function_exists('openssl_pkey_new')) {
            return ['error' => 'OpenSSL extension is not available on this PHP runtime.'];
        }

        $privatePath = trim((string)($form['server_private_key_path'] ?? ''));
        $publicPath = trim((string)($form['server_public_key_path'] ?? ''));
        if ($privatePath === '' || $publicPath === '') {
            $suggestion = $this->suggestSsoServerKeyPaths();
            if ($privatePath === '') {
                $privatePath = (string)($suggestion['private'] ?? '');
            }
            if ($publicPath === '') {
                $publicPath = (string)($suggestion['public'] ?? '');
            }
        }

        if ($privatePath === '' || $publicPath === '') {
            return ['error' => 'Key paths are empty. Provide private/public key paths first.'];
        }

        $overwrite = !empty($form['server_overwrite_keys']);
        if (!$overwrite && (is_file($privatePath) || is_file($publicPath))) {
            return ['error' => 'Key file already exists. Enable overwrite to rotate keys.'];
        }

        $ensurePrivateDir = $this->ensureParentDir($privatePath);
        if (!empty($ensurePrivateDir['error'])) {
            return $ensurePrivateDir;
        }
        $ensurePublicDir = $this->ensureParentDir($publicPath);
        if (!empty($ensurePublicDir['error'])) {
            return $ensurePublicDir;
        }

        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        if ($key === false) {
            return ['error' => 'Unable to generate RSA key pair using OpenSSL.'];
        }

        $privatePem = '';
        if (!openssl_pkey_export($key, $privatePem)) {
            return ['error' => 'Generated key could not be exported as private PEM.'];
        }
        $details = openssl_pkey_get_details($key);
        $publicPem = is_array($details) ? (string)($details['key'] ?? '') : '';
        if ($publicPem === '') {
            return ['error' => 'Generated key details did not include a public PEM key.'];
        }

        if (@file_put_contents($privatePath, $privatePem, LOCK_EX) === false) {
            return ['error' => 'Failed to write private key file: ' . $privatePath];
        }
        if (@file_put_contents($publicPath, $publicPem, LOCK_EX) === false) {
            return ['error' => 'Failed to write public key file: ' . $publicPath];
        }

        @chmod($privatePath, 0600);
        @chmod($publicPath, 0644);

        return [
            'private_path' => $privatePath,
            'public_path' => $publicPath,
            'message' => 'SSO server key pair generated at configured paths.',
        ];
    }

    private function ensureParentDir(string $filePath): array {
        $dir = dirname($filePath);
        if ($dir === '' || $dir === '.') {
            return ['error' => 'Invalid key path directory for ' . $filePath];
        }
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0700, true)) {
                return ['error' => 'Unable to create key directory: ' . $dir];
            }
        }
        if (!is_writable($dir)) {
            return ['error' => 'Key directory is not writable: ' . $dir];
        }
        return [];
    }

    private function getConfiguredSsoRoleFromRuntime(): string {
        $role = strtolower(trim((string)($this->runData['config']['auth']['sso_role'] ?? 'disabled')));
        return in_array($role, ['disabled', 'server', 'client'], true) ? $role : 'disabled';
    }

    private function buildAuthConfigSnippet(string $mode, array $form): string {
        if ($mode === 'server') {
            $sampleClients = '';
            if (($form['server_include_sample_clients'] ?? '0') === '1') {
                $sampleClients = "\n"
                    . "            // First-party full integration example\n"
                    . "            'batoi-subdomain-client' => [\n"
                    . "                'client_id' => 'batoi-subdomain-client',\n"
                    . "                'client_secret' => 'REPLACE_WITH_STRONG_SECRET',\n"
                    . "                'redirect_uris' => [\n"
                    . "                    'https://alpha.example.com/login/sso-client-callback',\n"
                    . "                ],\n"
                    . "                'allowed_level' => 'full_integration',\n"
                    . "                'status' => 'active',\n"
                    . "            ],\n\n"
                    . "            // External verify-only example\n"
                    . "            'external-client-1' => [\n"
                    . "                'client_id' => 'external-client-1',\n"
                    . "                'client_secret' => 'REPLACE_WITH_STRONG_SECRET',\n"
                    . "                'redirect_uris' => [\n"
                    . "                    'https://partner-example.com/login/sso-client-callback',\n"
                    . "                ],\n"
                    . "                'allowed_level' => 'verify_only',\n"
                    . "                'status' => 'active',\n"
                    . "            ],\n";
            }

            return "'auth' => [\n"
                . "    'sso_role' => 'server', // disabled | server | client\n\n"
                . "    'sso_server' => [\n"
                . "        'issuer' => " . var_export((string)($form['server_issuer'] ?? ''), true) . ",\n"
                . "        'private_key_path' => " . var_export((string)($form['server_private_key_path'] ?? ''), true) . ",\n"
                . "        'public_key_path' => " . var_export((string)($form['server_public_key_path'] ?? ''), true) . ",\n"
                . "        'access_token_ttl' => " . max(60, (int)($form['server_access_token_ttl'] ?? 900)) . ",\n"
                . "        'id_token_ttl' => " . max(60, (int)($form['server_id_token_ttl'] ?? 900)) . ",\n"
                . "        'default_client_level' => " . var_export((string)($form['server_default_client_level'] ?? 'verify_only'), true) . ",\n\n"
                . "        // Optional legacy file-based registry. Prefer RAD Admin > SSO Server Clients.\n"
                . "        'clients' => [" . $sampleClients . "        ],\n"
                . "    ],\n"
                . "],";
        }

        if ($mode === 'client') {
            return "'auth' => [\n"
                . "    'sso_role' => 'client', // disabled | server | client\n\n"
                . "    'sso_client' => [\n"
                . "        'label' => " . var_export((string)($form['client_label'] ?? 'Sign in with Organization SSO'), true) . ",\n"
                . "        'server_base_url' => " . var_export((string)($form['client_server_base_url'] ?? ''), true) . ",\n"
                . "        'server_issuer' => " . var_export((string)($form['client_server_issuer'] ?? ''), true) . ",\n\n"
                . "        'client_id' => " . var_export((string)($form['client_client_id'] ?? ''), true) . ",\n"
                . "        'client_secret' => " . var_export((string)($form['client_client_secret'] ?? ''), true) . ",\n\n"
                . "        'integration_level' => " . var_export((string)($form['client_integration_level'] ?? 'verify_only'), true) . ", // verify_only | full_integration\n"
                . "        'scope' => " . var_export((string)($form['client_scope'] ?? 'openid profile email'), true) . ",\n\n"
                . "        // Optional overrides (defaults already match these)\n"
                . "        'authorize_path' => " . var_export((string)($form['client_authorize_path'] ?? '/login/sso-server-authorize'), true) . ",\n"
                . "        'token_path' => " . var_export((string)($form['client_token_path'] ?? '/login/sso-server-token'), true) . ",\n"
                . "        'userinfo_path' => " . var_export((string)($form['client_userinfo_path'] ?? '/login/sso-server-userinfo'), true) . ",\n"
                . "        'jwks_path' => " . var_export((string)($form['client_jwks_path'] ?? '/login/sso-server-jwks'), true) . ",\n"
                . "    ],\n"
                . "],";
        }

        return "'auth' => [\n"
            . "    'sso_role' => 'disabled', // disabled | server | client\n"
            . "],";
    }

    private function runSsoRuntimeConfigTest(): array {
        $role = $this->getConfiguredSsoRoleFromRuntime();
        $items = [];
        $authRaw = $this->runData['config']['auth'] ?? [];

        if (!array_key_exists('sso_role', $authRaw)) {
            $items[] = $this->diagItem('warning', 'auth.sso_role declaration', 'Not explicitly declared in config; runtime defaults to disabled.');
        } else {
            $items[] = $this->diagItem('ok', 'auth.sso_role declaration', 'Declared in config.');
        }
        $items[] = $this->diagItem('ok', 'Runtime role', 'Current runtime role is `' . $role . '`.');

        if ($role === 'server') {
            $items = array_merge($items, $this->runServerModeDiagnostics());
        } elseif ($role === 'client') {
            $items = array_merge($items, $this->runClientModeDiagnostics());
        } else {
            $items[] = $this->diagItem('ok', 'SSO mode', 'SSO is disabled.');
        }

        $summary = ['ok' => 0, 'warning' => 0, 'error' => 0];
        foreach ($items as $item) {
            $status = $item['status'];
            if (!isset($summary[$status])) {
                $summary[$status] = 0;
            }
            $summary[$status]++;
        }

        return [
            'role' => $role,
            'items' => $items,
            'summary' => $summary,
        ];
    }

    private function runServerModeDiagnostics(): array {
        $items = [];
        $cfg = $this->runData['config']['auth']['sso_server'] ?? [];

        $issuer = trim((string)($cfg['issuer'] ?? ''));
        if ($issuer === '') {
            $items[] = $this->diagItem('error', 'sso_server.issuer', 'Missing issuer URL.');
        } elseif (!$this->isValidUrl($issuer)) {
            $items[] = $this->diagItem('error', 'sso_server.issuer', 'Issuer URL is invalid.');
        } else {
            $items[] = $this->diagItem('ok', 'sso_server.issuer', 'Issuer URL looks valid.');
        }

        $privatePath = trim((string)($cfg['private_key_path'] ?? ''));
        $publicPath = trim((string)($cfg['public_key_path'] ?? ''));
        $items = array_merge($items, $this->checkKeyPath('sso_server.private_key_path', $privatePath, true));
        $items = array_merge($items, $this->checkKeyPath('sso_server.public_key_path', $publicPath, false));

        $accessTtl = (int)($cfg['access_token_ttl'] ?? 0);
        $idTtl = (int)($cfg['id_token_ttl'] ?? 0);
        $items[] = $accessTtl > 0
            ? $this->diagItem('ok', 'sso_server.access_token_ttl', 'Configured as ' . $accessTtl . ' seconds.')
            : $this->diagItem('warning', 'sso_server.access_token_ttl', 'Not set or invalid. Runtime uses default.');
        $items[] = $idTtl > 0
            ? $this->diagItem('ok', 'sso_server.id_token_ttl', 'Configured as ' . $idTtl . ' seconds.')
            : $this->diagItem('warning', 'sso_server.id_token_ttl', 'Not set or invalid. Runtime uses default.');

        $baseUrl = rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/');
        if ($baseUrl !== '') {
            $metadata = $this->httpGetJson($baseUrl . '/login/sso-server-metadata');
            $items[] = is_array($metadata)
                ? $this->diagItem('ok', 'Metadata endpoint', 'Reachable at /login/sso-server-metadata.')
                : $this->diagItem('warning', 'Metadata endpoint', 'Not reachable from this request context. Test from browser/cURL if needed.');
            $jwks = $this->httpGetJson($baseUrl . '/login/sso-server-jwks');
            $items[] = is_array($jwks)
                ? $this->diagItem('ok', 'JWKS endpoint', 'Reachable at /login/sso-server-jwks.')
                : $this->diagItem('warning', 'JWKS endpoint', 'Not reachable from this request context. Test from browser/cURL if needed.');
        }

        $clients = $this->loadRegisteredServerClients();
        if (empty($clients)) {
            $items[] = $this->diagItem('warning', 'Registered clients', 'No clients found in DB registry or file config.');
        } else {
            $active = 0;
            foreach ($clients as $clientId => $client) {
                if (($client['status'] ?? 'active') === 'active') {
                    $active++;
                }
                $redirectUris = $client['redirect_uris'] ?? [];
                if (empty($redirectUris)) {
                    $items[] = $this->diagItem('error', 'Client ' . $clientId, 'No redirect URIs configured.');
                    continue;
                }
                $hasSecret = trim((string)($client['client_secret_hash'] ?? '')) !== '' || trim((string)($client['client_secret'] ?? '')) !== '';
                if (!$hasSecret) {
                    $items[] = $this->diagItem('error', 'Client ' . $clientId, 'Client secret/secret hash is missing.');
                }
            }
            $items[] = $this->diagItem('ok', 'Registered clients', 'Found ' . count($clients) . ' clients (' . $active . ' active).');
        }

        return $items;
    }

    private function runClientModeDiagnostics(): array {
        $items = [];
        $cfg = $this->runData['config']['auth']['sso_client'] ?? [];

        $required = [
            'server_base_url' => 'SSO server base URL',
            'server_issuer' => 'SSO server issuer',
            'client_id' => 'Client ID',
            'client_secret' => 'Client secret',
        ];
        foreach ($required as $key => $label) {
            $value = trim((string)($cfg[$key] ?? ''));
            if ($value === '') {
                $items[] = $this->diagItem('error', 'sso_client.' . $key, $label . ' is missing.');
            } else {
                $items[] = $this->diagItem('ok', 'sso_client.' . $key, $label . ' is configured.');
            }
        }

        $baseUrl = trim((string)($cfg['server_base_url'] ?? ''));
        if ($baseUrl !== '' && !$this->isValidUrl($baseUrl)) {
            $items[] = $this->diagItem('error', 'sso_client.server_base_url', 'Invalid URL format.');
        }

        $scope = trim((string)($cfg['scope'] ?? ''));
        if ($scope === '' || stripos($scope, 'openid') === false) {
            $items[] = $this->diagItem('warning', 'sso_client.scope', 'Scope should include `openid`.');
        } else {
            $items[] = $this->diagItem('ok', 'sso_client.scope', 'Scope includes openid.');
        }

        $endpointKeys = ['authorize_path', 'token_path', 'userinfo_path', 'jwks_path'];
        foreach ($endpointKeys as $key) {
            $path = trim((string)($cfg[$key] ?? ''));
            if ($path === '') {
                $items[] = $this->diagItem('warning', 'sso_client.' . $key, 'Not set. Runtime default will be used.');
                continue;
            }
            if ($path[0] !== '/') {
                $items[] = $this->diagItem('error', 'sso_client.' . $key, 'Must start with `/`.');
            } else {
                $items[] = $this->diagItem('ok', 'sso_client.' . $key, 'Path looks valid.');
            }
        }

        if ($baseUrl !== '' && $this->isValidUrl($baseUrl)) {
            $metadata = $this->httpGetJson(rtrim($baseUrl, '/') . '/login/sso-server-metadata');
            if (!is_array($metadata)) {
                $items[] = $this->diagItem('warning', 'Server metadata reachability', 'Could not fetch /login/sso-server-metadata.');
            } else {
                $items[] = $this->diagItem('ok', 'Server metadata reachability', 'Metadata endpoint is reachable.');
                $runtimeIssuer = trim((string)($cfg['server_issuer'] ?? ''));
                $metadataIssuer = trim((string)($metadata['issuer'] ?? ''));
                if ($runtimeIssuer !== '' && $metadataIssuer !== '' && !hash_equals(rtrim($runtimeIssuer, '/'), rtrim($metadataIssuer, '/'))) {
                    $items[] = $this->diagItem('warning', 'Issuer consistency', 'Configured issuer and metadata issuer do not match.');
                } else {
                    $items[] = $this->diagItem('ok', 'Issuer consistency', 'Configured issuer matches metadata issuer.');
                }
            }
        }

        return $items;
    }

    private function diagItem(string $status, string $label, string $message): array {
        return [
            'status' => $status,
            'label' => $label,
            'message' => $message,
        ];
    }

    private function checkKeyPath(string $label, string $path, bool $private): array {
        $items = [];
        if ($path === '') {
            $items[] = $this->diagItem('error', $label, 'Path is missing.');
            return $items;
        }
        if (!is_file($path) || !is_readable($path)) {
            $items[] = $this->diagItem('error', $label, 'File is missing or not readable.');
            return $items;
        }
        $pem = @file_get_contents($path);
        if ($pem === false || trim($pem) === '') {
            $items[] = $this->diagItem('error', $label, 'File is empty or unreadable.');
            return $items;
        }
        if ($private) {
            $key = openssl_pkey_get_private($pem);
        } else {
            $key = openssl_pkey_get_public($pem);
        }
        if ($key === false) {
            $items[] = $this->diagItem('error', $label, 'PEM content could not be parsed by OpenSSL.');
            return $items;
        }
        if (is_resource($key) || $key instanceof \OpenSSLAsymmetricKey) {
            openssl_free_key($key);
        }
        $items[] = $this->diagItem('ok', $label, 'Key file is readable and valid PEM.');
        return $items;
    }

    private function loadRegisteredServerClients(): array {
        $clients = [];

        try {
            $rows = $this->db->select('s_config', ['s_config_handle' => 'sso_server_clients'], true);
            $json = $rows[0]['s_config_value'] ?? '';
            $decoded = json_decode((string)$json, true);
            if (is_array($decoded)) {
                $isAssoc = array_keys($decoded) !== array_keys(array_values($decoded));
                if ($isAssoc) {
                    foreach ($decoded as $key => $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $clientId = trim((string)($row['client_id'] ?? $key));
                        if ($clientId === '') {
                            continue;
                        }
                        $clients[$clientId] = $row;
                    }
                } else {
                    foreach ($decoded as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $clientId = trim((string)($row['client_id'] ?? ''));
                        if ($clientId === '') {
                            continue;
                        }
                        $clients[$clientId] = $row;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore and keep fallback behavior.
        }

        $cfgClients = $this->runData['config']['auth']['sso_server']['clients'] ?? [];
        if (is_array($cfgClients)) {
            foreach ($cfgClients as $key => $row) {
                if (is_array($row)) {
                    $clientId = trim((string)($row['client_id'] ?? (is_string($key) ? $key : '')));
                    if ($clientId === '') {
                        continue;
                    }
                    if (!isset($clients[$clientId])) {
                        $clients[$clientId] = $row;
                    }
                }
            }
        }

        foreach ($clients as $id => &$client) {
            $client['client_id'] = (string)($client['client_id'] ?? $id);
            $client['status'] = ((string)($client['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active';
            $redirectUris = $client['redirect_uris'] ?? [];
            if (is_string($redirectUris)) {
                $decoded = json_decode($redirectUris, true);
                $redirectUris = is_array($decoded) ? $decoded : [$redirectUris];
            }
            if (!is_array($redirectUris)) {
                $redirectUris = [];
            }
            $client['redirect_uris'] = array_values(array_filter(array_map(static function ($uri) {
                return trim((string)$uri);
            }, $redirectUris), static function ($uri) {
                return $uri !== '';
            }));
        }
        unset($client);

        return $clients;
    }

    private function buildServerKeyReadinessReport(array $form): array {
        $privatePath = trim((string)($form['server_private_key_path'] ?? ''));
        $publicPath = trim((string)($form['server_public_key_path'] ?? ''));
        $items = [];
        $items = array_merge($items, $this->checkKeyPath('Private key path', $privatePath, true));
        $items = array_merge($items, $this->checkKeyPath('Public key path', $publicPath, false));

        $summary = ['ok' => 0, 'warning' => 0, 'error' => 0];
        foreach ($items as $item) {
            $status = (string)($item['status'] ?? 'ok');
            if (!isset($summary[$status])) {
                $summary[$status] = 0;
            }
            $summary[$status]++;
        }

        return [
            'summary' => $summary,
            'items' => $items,
        ];
    }

    private function providerTypeLabel(string $type): string {
        $map = [
            'google' => 'Google (OIDC)',
            'microsoft' => 'Microsoft Entra',
            'okta' => 'Okta',
            'auth0' => 'Auth0',
            'oidc' => 'Generic OIDC',
            'oauth2' => 'Generic OAuth2',
            'batoi_idp' => 'Batoi IDP',
            '' => 'Custom',
        ];

        return $map[$type] ?? ucfirst($type);
    }
}

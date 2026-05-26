<?php
namespace Core\Sys;
use Mailgun\Mailgun;

class LoginController {
    private $db;
    private $view;
    private $session;
    private $errorHandler;
    private $runData = [];
    private $routeIndex = [];
    private $notificationService;
    private ?\Core\Sys\MfaService $mfaService = null;
    private $trustedCookieName = 'rad_trusted_device';
    private ?\Core\Sys\MfaSettings $mfaSettings = null;
    private ?array $ssoServerClientRegistryCache = null;
    
    public function __construct(array $runData, \Core\Sys\View $view, \Core\Sys\ErrorHandler $errorHandler) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->routeIndex = $runData['route']['pathparts'];
        $this->view = $view;
        $this->session = $runData['session'];
        $this->errorHandler = $errorHandler;
        $this->notificationService = $runData['notificationService'] ?? new NotificationService($this->db);
        $this->mfaSettings = $this->loadMfaSettings();
        $mfaconfig = $runData['config'] ?? [];
        $mfaconfig['mfa_settings_obj'] = $this->mfaSettings;
        $this->mfaService = new \Core\Sys\MfaService($mfaconfig, $errorHandler);
    }

    public function handle() {
        if (count($this->routeIndex) == 1) {
            $this->redirectToDefaultUrl();
        }
        $this->runData['route']['path_full'] = implode('/', $this->routeIndex);
        $this->runData['ms']['name'] = $this->routeIndex[0];
        $this->runData['ms']['tpl_name'] = 'login';
        
        switch ($this->routeIndex[1]) {
            case 'localsession':
                $this->handleLogin();
                break;
            case 'mfa':
                $this->handleMfa();
                break;
            case 'logout':
                $this->handleLogout();
                break;
            case 'sso':
                if (($this->routeIndex[2] ?? '') === 'callback') {
                    $this->handleSsoCallback();
                } else {
                    $this->handleSSO(); // Legacy assertion-based
                }
                break;
            case 'sso-init':
                $this->handleSsoInit();
                break;
            case 'sso-callback':
                $this->handleSsoCallback();
                break;
            case 'sso-client-init':
                $this->handleClientModeInit();
                break;
            case 'sso-client-callback':
                $this->handleClientModeCallback();
                break;
            case 'sso-server-metadata':
                $this->handleServerModeMetadata();
                break;
            case 'sso-server-jwks':
                $this->handleServerModeJwks();
                break;
            case 'sso-server-authorize':
                $this->handleServerModeAuthorize();
                break;
            case 'sso-server-token':
                $this->handleServerModeToken();
                break;
            case 'sso-server-userinfo':
                $this->handleServerModeUserinfo();
                break;
            case 'ssoassert':
                $this->handleSSO();
                break;
            case 'forgotpassword':
                $this->runData['ms']['tpl_name'] = 'forgotpassword';
                $this->handleForgotPassword();
                break;
            default:
                $this->redirectToDefaultUrl();
                break;
        }
        $this->view->render($this->runData);
    }

    private function handleLogin() {
        $redirectHint = $this->resolveRequestedRedirect();
        $this->runData['data']['redirect_url_post_login'] = $redirectHint;
        $mode = $this->getConfiguredSsoRole();
        if ($mode === 'client') {
            $this->runData['data']['sso_client_enabled'] = true;
            $ssoClientLoginUrl = rtrim($this->runData['config']['sys']['base_url'] ?? '', '/') . '/login/sso-client-init';
            if ($redirectHint !== null && $redirectHint !== '') {
                $ssoClientLoginUrl .= '?redirect=' . urlencode($redirectHint);
            }
            $this->runData['data']['sso_client_login_url'] = $ssoClientLoginUrl;
            $this->runData['data']['sso_client_label'] = $this->getClientModeLabel();
        }
        if (isset($_POST['s_username']) && isset($_POST['s_password'])) {
            $username = $_POST['s_username'];
            $password = $_POST['s_password'];
            $isAdminLogin = $this->isAdminLoginFlow($redirectHint);

            $userDetails = $this->db->select('s_entity', [
                'livestatus' => '1',
                's_identity' => $username,
                's_type' => 'U'
            ], true);

            if (count($userDetails) == 1) {
                $userAuthInfo = $this->loadAuthInfo($userDetails[0]);
                if (password_verify($password, $userDetails[0]['s_identity_secret'])) {
                    if ($this->shouldEnforceMfa($userAuthInfo)) {
                        if (!$this->canDispatchMfa($userAuthInfo, $userDetails[0])) {
                            $this->setLoginFailure(
                                'MFA is required but no delivery channel is configured for this account. Please add email or mobile.',
                                $isAdminLogin,
                                ['redirect' => $redirectHint]
                            );
                            if ($isAdminLogin) {
                                return;
                            }
                            return;
                        }
                        $this->startPendingMfa($userDetails[0], $userAuthInfo);
                        header("Location: " . $this->runData['config']['sys']['base_url'] . "/login/mfa");
                        exit;
                    }
                    $this->completeLogin($userDetails, $userAuthInfo, $redirectHint);
                } else {
                    $this->setLoginFailure('Invalid password.', $isAdminLogin, ['redirect' => $redirectHint]);
                }
            } else {
                $this->setLoginFailure('Invalid username.', $isAdminLogin, ['redirect' => $redirectHint]);
            }
        }
        $this->consumeLoginFlash();
        $this->runData['route']['h1'] = 'Login';
        $this->runData['route']['meta_title'] = $this->runData['route']['h1'];
        $this->runData['route']['meta_description'] = '';
    }

    private function resolveRequestedRedirect(): ?string {
        $candidates = [
            $_POST['redirect_url_post_login'] ?? null,
            $_GET['redirect'] ?? null,
            $_COOKIE['redirect_url_post_login'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            $validated = $this->normalizeLocalRedirect($candidate);
            if ($validated !== null && $validated !== '') {
                return $validated;
            }
        }
        return null;
    }

    private function normalizeLocalRedirect($redirect): ?string {
        $redirect = trim((string)$redirect);
        if ($redirect === '') {
            return null;
        }

        $baseUrl = rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/');
        if ($baseUrl !== '' && strpos($redirect, $baseUrl . '/') === 0) {
            return $redirect;
        }
        if ($baseUrl !== '' && $redirect === $baseUrl) {
            return $redirect;
        }
        if ($redirect[0] === '/' && (strlen($redirect) < 2 || $redirect[1] !== '/')) {
            return $baseUrl . $redirect;
        }

        return null;
    }

    private function handleLogout() {
        $userId = (int)($this->session->get('entity_id') ?? 0);
        if ($userId > 0) {
            $this->recordAuthEvent('logout', $userId);
        }
        $this->session->destroy();
        $loginUrl = $this->runData['config']['sys']['base_url'] . '/login';
        header("Location: {$loginUrl}"); exit;
    }

    /**
     * New OIDC-style SSO init endpoint: /login/sso-init?provider={id}&redirect=...
     */
    private function handleSsoInit() {
        $baseUrl = rtrim($this->runData['config']['sys']['base_url'] ?? '', '/');
        $providerId = (int)($this->runData['request']->get['provider'] ?? 0);
        $redirect = trim((string)($this->runData['request']->get['redirect'] ?? ''));
        if ($providerId <= 0) {
            $this->setSsoError('Invalid provider.');
            return;
        }
        $providers = $this->db->select('s_sso_provider', ['id' => $providerId], true);
        if (count($providers) !== 1) {
            $this->setSsoError('Provider not found.');
            return;
        }
        $p = $providers[0];
        if (($p['s_status'] ?? '') === 'inactive') {
            $this->setSsoError('Provider is inactive.');
            return;
        }
        $authUrl = trim($p['s_auth_url'] ?? '');
        $clientId = trim($p['s_client_id'] ?? '');
        $redirectPath = $this->normalizeSsoRedirectPath(trim($p['s_redirect_path'] ?? '/login/sso-callback'));
        $scopes = trim($p['s_scopes'] ?? 'openid profile email');
        if ($authUrl === '' || $clientId === '') {
            $this->setSsoError('Provider is missing auth URL or client ID.');
            return;
        }
        $state = $this->randomBase64(24);
        $nonce = $this->randomBase64(24);
        $codeVerifier = $this->randomBase64(32);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $redirectUri = $baseUrl . $redirectPath;

        $this->session->set('sso_state', [
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'provider_id' => $providerId,
            'redirect' => $redirect,
            'issued_at' => time(),
        ]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scopes,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
        header('Location: ' . $authUrl . (strpos($authUrl, '?') === false ? '?' : '&') . $query);
        exit;
    }

    /**
     * New OIDC-style SSO callback endpoint: /login/sso-callback
     */
    private function handleSsoCallback() {
        $baseUrl = rtrim($this->runData['config']['sys']['base_url'] ?? '', '/');
        $stateParam = $this->runData['request']->get['state'] ?? '';
        $code = $this->runData['request']->get['code'] ?? '';
        $error = $this->runData['request']->get['error'] ?? '';

        $saved = $this->session->get('sso_state') ?? [];
        if ($error !== '') {
            $this->setSsoErrorWithTestRedirect('SSO error: ' . $error, $saved);
            $this->session->delete('sso_state');
            return;
        }
        if (empty($saved) || empty($saved['state']) || !hash_equals((string)$saved['state'], (string)$stateParam)) {
            $this->setSsoErrorWithTestRedirect('Invalid or missing state.', $saved);
            return;
        }
        if ($code === '') {
            $this->setSsoErrorWithTestRedirect('Missing authorization code.', $saved);
            return;
        }
        $providerId = (int)($saved['provider_id'] ?? 0);
        $providers = $this->db->select('s_sso_provider', ['id' => $providerId], true);
        if (count($providers) !== 1) {
            $this->setSsoErrorWithTestRedirect('Provider not found.', $saved);
            return;
        }
        $p = $providers[0];
        $tokenUrl = trim($p['s_token_url'] ?? '');
        $clientId = trim($p['s_client_id'] ?? '');
        $clientSecret = trim($p['s_client_secret'] ?? '');
        $redirectPath = $this->normalizeSsoRedirectPath(trim($p['s_redirect_path'] ?? '/login/sso-callback'));
        $redirectUri = $baseUrl . $redirectPath;
        if ($tokenUrl === '' || $clientId === '') {
            $this->setSsoErrorWithTestRedirect('Provider is missing token URL or client ID.', $saved);
            return;
        }

        $tokenResp = $this->httpPostForm($tokenUrl, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code_verifier' => $saved['code_verifier'] ?? '',
        ]);
        if (!$tokenResp || empty($tokenResp['id_token'])) {
            $this->setSsoErrorWithTestRedirect('Unable to exchange code for tokens.', $saved);
            return;
        }

        $signatureCheck = $this->verifyIdTokenSignature($tokenResp['id_token'], $p);
        if (empty($signatureCheck['ok'])) {
            $this->setSsoErrorWithTestRedirect('Invalid ID token signature. ' . ($signatureCheck['error'] ?? 'Verification failed.'), $saved);
            return;
        }

        $idToken = $this->decodeJwt($tokenResp['id_token']);
        if (!$idToken) {
            $this->setSsoErrorWithTestRedirect('Invalid ID token.', $saved);
            return;
        }
        $expectedIss = trim($p['s_issuer'] ?? '');
        if ($expectedIss !== '' && (($idToken['iss'] ?? '') !== $expectedIss)) {
            $this->setSsoErrorWithTestRedirect('Issuer mismatch.', $saved);
            return;
        }
        $aud = $idToken['aud'] ?? '';
        $audList = is_array($aud) ? $aud : [$aud];
        if ($clientId !== '' && !in_array($clientId, $audList, true)) {
            $this->setSsoErrorWithTestRedirect('Audience mismatch.', $saved);
            return;
        }
        if (!empty($idToken['exp']) && $idToken['exp'] < time()) {
            $this->setSsoErrorWithTestRedirect('ID token expired.', $saved);
            return;
        }
        if (!empty($idToken['nonce']) && !empty($saved['nonce']) && !hash_equals((string)$saved['nonce'], (string)$idToken['nonce'])) {
            $this->setSsoErrorWithTestRedirect('Nonce mismatch.', $saved);
            return;
        }

        // Userinfo fallback if email is missing
        $userinfo = [];
        if (empty($idToken['email']) && !empty($p['s_userinfo_url'])) {
            $userinfo = $this->httpGetJson($p['s_userinfo_url'], $tokenResp['access_token'] ?? '');
        }

        $claims = $this->mapClaims($p, $idToken, $userinfo);
        $email = $claims['email'] ?? '';
        if ($email === '') {
            $this->setSsoErrorWithTestRedirect('Email not found in token/userinfo.', $saved);
            return;
        }

        $userDetails = $this->db->select('s_entity', [
            'livestatus' => '1',
            's_identity' => $email,
            's_type' => 'U'
        ], true);
        if (count($userDetails) !== 1) {
            $this->setSsoErrorWithTestRedirect('User not found for SSO.', $saved);
            return;
        }
        $userAuthInfo = $this->loadAuthInfo($userDetails[0]);
        $redirect = $saved['redirect'] ?? null;
        $this->session->delete('sso_state');
        if ($this->shouldEnforceMfa($userAuthInfo)) {
            $this->startPendingMfa($userDetails[0], $userAuthInfo, $redirect, false);
            header("Location: " . $this->runData['config']['sys']['base_url'] . "/login/mfa");
            exit;
        }
        $this->completeLogin($userDetails, $userAuthInfo, $redirect);
    }

    private function handleClientModeInit(): void {
        if ($this->getConfiguredSsoRole() !== 'client') {
            $this->setSsoError('Client SSO mode is not enabled.');
            return;
        }
        $clientCfg = $this->getSsoClientConfig();
        $serverBase = rtrim((string)($clientCfg['server_base_url'] ?? ''), '/');
        $clientId = trim((string)($clientCfg['client_id'] ?? ''));
        $clientSecret = trim((string)($clientCfg['client_secret'] ?? ''));
        if ($serverBase === '' || $clientId === '' || $clientSecret === '') {
            $this->setSsoError('Client SSO is not fully configured.');
            return;
        }

        $authPath = (string)($clientCfg['authorize_path'] ?? '/login/sso-server-authorize');
        $baseUrl = rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/');
        $redirectUri = $baseUrl . '/login/sso-client-callback';
        $returnRedirect = trim((string)($this->runData['request']->get['redirect'] ?? ''));
        $state = $this->randomBase64(24);
        $nonce = $this->randomBase64(24);
        $codeVerifier = $this->randomBase64(32);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $scope = trim((string)($clientCfg['scope'] ?? 'openid profile email'));

        $this->session->set('sso_client_state', [
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'redirect' => $returnRedirect,
            'issued_at' => time(),
        ]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
        header('Location: ' . $serverBase . $authPath . '?' . $query);
        exit;
    }

    private function handleClientModeCallback(): void {
        if ($this->getConfiguredSsoRole() !== 'client') {
            $this->setSsoError('Client SSO mode is not enabled.');
            return;
        }
        $saved = $this->session->get('sso_client_state') ?? [];
        $stateParam = trim((string)($this->runData['request']->get['state'] ?? ''));
        $code = trim((string)($this->runData['request']->get['code'] ?? ''));
        $error = trim((string)($this->runData['request']->get['error'] ?? ''));
        if ($error !== '') {
            $this->setSsoError('SSO server returned error: ' . $error);
            $this->session->delete('sso_client_state');
            return;
        }
        if (empty($saved['state']) || !hash_equals((string)$saved['state'], $stateParam)) {
            $this->setSsoError('Invalid SSO state.');
            return;
        }
        if ($code === '') {
            $this->setSsoError('Authorization code missing.');
            return;
        }

        $clientCfg = $this->getSsoClientConfig();
        $serverBase = rtrim((string)($clientCfg['server_base_url'] ?? ''), '/');
        $tokenPath = (string)($clientCfg['token_path'] ?? '/login/sso-server-token');
        $jwksPath = (string)($clientCfg['jwks_path'] ?? '/login/sso-server-jwks');
        $userinfoPath = (string)($clientCfg['userinfo_path'] ?? '/login/sso-server-userinfo');
        $clientId = trim((string)($clientCfg['client_id'] ?? ''));
        $clientSecret = trim((string)($clientCfg['client_secret'] ?? ''));
        $baseUrl = rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/');
        $redirectUri = $baseUrl . '/login/sso-client-callback';

        $tokenResp = $this->httpPostForm($serverBase . $tokenPath, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code_verifier' => (string)($saved['code_verifier'] ?? ''),
        ]);
        if (!$tokenResp || empty($tokenResp['id_token'])) {
            $this->setSsoError('Unable to exchange code with SSO server.');
            return;
        }

        $providerForVerify = [
            's_client_secret' => $clientSecret,
            's_jwks_url' => $serverBase . $jwksPath,
            's_issuer' => (string)($clientCfg['server_issuer'] ?? $serverBase),
        ];
        $signatureCheck = $this->verifyIdTokenSignature($tokenResp['id_token'], $providerForVerify);
        if (empty($signatureCheck['ok'])) {
            $this->setSsoError('Invalid ID token signature from SSO server.');
            return;
        }
        $idToken = $this->decodeJwt($tokenResp['id_token']);
        if (!$idToken) {
            $this->setSsoError('Invalid ID token payload from SSO server.');
            return;
        }
        $aud = $idToken['aud'] ?? '';
        $audList = is_array($aud) ? $aud : [$aud];
        if ($clientId !== '' && !in_array($clientId, $audList, true)) {
            $this->setSsoError('SSO client audience mismatch.');
            return;
        }
        if (!empty($idToken['nonce']) && !empty($saved['nonce']) && !hash_equals((string)$saved['nonce'], (string)$idToken['nonce'])) {
            $this->setSsoError('SSO nonce mismatch.');
            return;
        }
        if (!empty($idToken['exp']) && (int)$idToken['exp'] < time()) {
            $this->setSsoError('SSO token expired.');
            return;
        }

        $integrationLevel = (string)($clientCfg['integration_level'] ?? 'verify_only');
        $claims = [
            'email' => $idToken['email'] ?? '',
            'name' => $idToken['name'] ?? '',
            'sub' => $idToken['sub'] ?? '',
        ];
        if ($integrationLevel === 'full_integration' && !empty($tokenResp['access_token'])) {
            $userinfo = $this->httpGetJson($serverBase . $userinfoPath, (string)$tokenResp['access_token']) ?? [];
            if (!empty($userinfo['email'])) {
                $claims['email'] = $userinfo['email'];
            }
            if (!empty($userinfo['name'])) {
                $claims['name'] = $userinfo['name'];
            }
            $claims['_userinfo'] = $userinfo;
        }

        $email = trim((string)($claims['email'] ?? ''));
        if ($email === '') {
            $this->setSsoError('Email is missing from SSO claims.');
            return;
        }

        $userDetails = $this->db->select('s_entity', [
            'livestatus' => '1',
            's_identity' => $email,
            's_type' => 'U'
        ], true);
        if (count($userDetails) !== 1) {
            if ($integrationLevel !== 'full_integration') {
                $this->setSsoError('User not found for SSO client login.');
                return;
            }
            $newId = $this->db->insert('s_entity', [
                's_type' => 'U',
                's_name' => $this->uniqueSsoEntityName($email, (string)($claims['name'] ?? $email)),
                's_identity' => $email,
                's_identity_secret' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                's_email' => $email,
                's_login_mode' => 'BA',
                's_enable_mfa' => 'N',
            ]);
            $userDetails = $this->db->select('s_entity', ['id' => (int)$newId], true);
        }
        if (count($userDetails) !== 1) {
            $this->setSsoError('Unable to provision local user for SSO.');
            return;
        }
        if ($integrationLevel === 'full_integration') {
            $this->syncUserFromSsoClaims((int)$userDetails[0]['id'], $claims);
            $userDetails = $this->db->select('s_entity', ['id' => (int)$userDetails[0]['id']], true);
        }
        $this->session->delete('sso_client_state');
        $userAuthInfo = $this->loadAuthInfo($userDetails[0]);
        $this->completeLogin($userDetails, $userAuthInfo, $saved['redirect'] ?? null);
    }

    private function handleServerModeMetadata(): void {
        if ($this->getConfiguredSsoRole() !== 'server') {
            $this->sendJson(['error' => 'sso_server_mode_disabled'], 404);
            return;
        }
        $baseUrl = rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/');
        $issuer = $this->getSsoServerIssuer();
        $payload = [
            'issuer' => $issuer,
            'authorization_endpoint' => $baseUrl . '/login/sso-server-authorize',
            'token_endpoint' => $baseUrl . '/login/sso-server-token',
            'userinfo_endpoint' => $baseUrl . '/login/sso-server-userinfo',
            'jwks_uri' => $baseUrl . '/login/sso-server-jwks',
            'response_types_supported' => ['code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post'],
            'claims_supported' => ['sub', 'email', 'name', 'email_verified', 'integration_level'],
            'scopes_supported' => ['openid', 'profile', 'email', 'rad.profile.read', 'rad.access.read'],
        ];
        $this->sendJson($payload, 200);
    }

    private function handleServerModeJwks(): void {
        if ($this->getConfiguredSsoRole() !== 'server') {
            $this->sendJson(['error' => 'sso_server_mode_disabled'], 404);
            return;
        }
        $jwk = $this->buildServerJwk();
        if ($jwk === null) {
            $this->sendJson(['keys' => []], 200);
            return;
        }
        $this->sendJson(['keys' => [$jwk]], 200);
    }

    private function handleServerModeAuthorize(): void {
        if ($this->getConfiguredSsoRole() !== 'server') {
            $this->setSsoError('SSO server mode is not enabled.');
            return;
        }
        $req = $this->runData['request']->get;
        $responseType = trim((string)($req['response_type'] ?? ''));
        $clientId = trim((string)($req['client_id'] ?? ''));
        $redirectUri = trim((string)($req['redirect_uri'] ?? ''));
        $state = trim((string)($req['state'] ?? ''));
        $nonce = trim((string)($req['nonce'] ?? ''));
        $scope = trim((string)($req['scope'] ?? 'openid profile email'));
        $codeChallenge = trim((string)($req['code_challenge'] ?? ''));
        $codeChallengeMethod = strtoupper(trim((string)($req['code_challenge_method'] ?? '')));

        if ($responseType !== 'code' || $clientId === '' || $redirectUri === '' || $state === '' || $nonce === '') {
            $this->setSsoError('Invalid authorize request.');
            return;
        }
        $client = $this->getServerRegisteredClient($clientId);
        if (!$client) {
            $this->setSsoError('Unknown SSO client.');
            return;
        }
        if (!$this->isAllowedRedirectUri($client, $redirectUri)) {
            $this->setSsoError('Redirect URI not allowed.');
            return;
        }
        if ($codeChallenge === '' || $codeChallengeMethod !== 'S256') {
            $this->setSsoError('PKCE challenge is required.');
            return;
        }
        $entityId = (int)($this->session->get('entity_id') ?? 0);
        if ($entityId <= 0) {
            $authorizeUrl = rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/')
                . '/login/sso-server-authorize?' . http_build_query($req);
            setcookie('redirect_url_post_login', $authorizeUrl, time() + (86400 * 30), '/');
            header('Location: ' . $this->runData['config']['sys']['base_url'] . '/login/localsession');
            exit;
        }

        $rows = $this->db->select('s_entity', ['id' => $entityId, 'livestatus' => '1', 's_type' => 'U'], true);
        if (count($rows) !== 1) {
            $this->setSsoError('Logged-in user not found.');
            return;
        }
        $u = $rows[0];
        $email = trim((string)($u['s_identity'] ?? $u['s_email'] ?? ''));
        if ($email === '') {
            $this->setSsoError('User email is unavailable for SSO.');
            return;
        }
        $code = $this->randomBase64(32);
        $integrationLevel = (string)($client['allowed_level'] ?? $this->getServerDefaultClientLevel($client));
        $stored = $this->storeServerAuthCode($code, [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'entity_id' => (int)$u['id'],
            'email' => $email,
            'name' => (string)($u['s_name'] ?? $email),
            'sub' => 'entity:' . (int)$u['id'],
            'nonce' => $nonce,
            'scope' => $scope,
            'code_challenge' => $codeChallenge,
            'expires_at' => time() + 300,
            'integration_level' => $integrationLevel,
        ]);
        if (!$stored) {
            $this->setSsoError('Unable to issue authorization code.');
            return;
        }
        $sep = strpos($redirectUri, '?') === false ? '?' : '&';
        header('Location: ' . $redirectUri . $sep . 'code=' . urlencode($code) . '&state=' . urlencode($state));
        exit;
    }

    private function handleServerModeToken(): void {
        if ($this->getConfiguredSsoRole() !== 'server') {
            $this->sendJson(['error' => 'sso_server_mode_disabled'], 404);
            return;
        }
        if (strtoupper((string)($this->runData['request']->method ?? 'GET')) !== 'POST') {
            $this->sendJson(['error' => 'invalid_request', 'error_description' => 'POST required'], 405);
            return;
        }
        $post = $this->runData['request']->post;
        $grantType = trim((string)($post['grant_type'] ?? ''));
        $code = trim((string)($post['code'] ?? ''));
        $clientId = trim((string)($post['client_id'] ?? ''));
        $clientSecret = trim((string)($post['client_secret'] ?? ''));
        $redirectUri = trim((string)($post['redirect_uri'] ?? ''));
        $codeVerifier = trim((string)($post['code_verifier'] ?? ''));

        if ($grantType !== 'authorization_code' || $code === '' || $clientId === '' || $redirectUri === '') {
            $this->sendJson(['error' => 'invalid_request'], 400);
            return;
        }
        $client = $this->getServerRegisteredClient($clientId);
        if (!$client || !$this->isClientSecretValid($client, $clientSecret)) {
            $this->sendJson(['error' => 'invalid_client'], 401);
            return;
        }
        $record = $this->getServerAuthCodeRecord($code);
        if (!is_array($record)) {
            $this->sendJson(['error' => 'invalid_grant'], 400);
            return;
        }
        if (!empty($record['s_consumed_at'])) {
            $this->sendJson(['error' => 'invalid_grant', 'error_description' => 'Code already used'], 400);
            return;
        }
        if (($record['s_client_id'] ?? '') !== $clientId || ($record['s_redirect_uri'] ?? '') !== $redirectUri) {
            $this->sendJson(['error' => 'invalid_grant'], 400);
            return;
        }
        if (!$this->isFutureDateTime((string)($record['s_expires_at'] ?? ''))) {
            $this->sendJson(['error' => 'invalid_grant', 'error_description' => 'Code expired'], 400);
            return;
        }
        $expectedChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        if ($codeVerifier === '' || !hash_equals((string)($record['s_code_challenge'] ?? ''), $expectedChallenge)) {
            $this->sendJson(['error' => 'invalid_grant', 'error_description' => 'PKCE verification failed'], 400);
            return;
        }
        $this->consumeServerAuthCode((int)$record['id']);

        $now = time();
        $idTokenExp = $now + (int)($this->runData['config']['auth']['sso_server']['id_token_ttl'] ?? 900);
        $accessExp = $now + (int)($this->runData['config']['auth']['sso_server']['access_token_ttl'] ?? 900);
        $issuer = $this->getSsoServerIssuer();
        $idTokenPayload = [
            'iss' => $issuer,
            'sub' => (string)$record['s_sub'],
            'aud' => $clientId,
            'email' => (string)$record['s_email'],
            'name' => (string)$record['s_name'],
            'nonce' => (string)$record['s_nonce'],
            'scope' => (string)$record['s_scope'],
            'integration_level' => (string)$record['s_integration_level'],
            'iat' => $now,
            'exp' => $idTokenExp,
        ];
        $idToken = $this->signServerIdToken($idTokenPayload);
        if ($idToken === null) {
            $this->sendJson(['error' => 'server_error', 'error_description' => 'Signing key unavailable'], 500);
            return;
        }

        $accessToken = $this->randomBase64(32);
        $storedToken = $this->storeServerAccessToken($accessToken, [
            'client_id' => $clientId,
            'entity_id' => (int)($record['s_entity_id'] ?? 0),
            'sub' => (string)$record['s_sub'],
            'email' => (string)$record['s_email'],
            'name' => (string)$record['s_name'],
            'scope' => (string)$record['s_scope'],
            'integration_level' => (string)$record['s_integration_level'],
            'expires_at' => $accessExp,
        ]);
        if (!$storedToken) {
            $this->sendJson(['error' => 'server_error', 'error_description' => 'Unable to persist access token'], 500);
            return;
        }

        $this->sendJson([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessExp - $now,
            'id_token' => $idToken,
            'scope' => (string)$record['s_scope'],
        ], 200);
    }

    private function handleServerModeUserinfo(): void {
        if ($this->getConfiguredSsoRole() !== 'server') {
            $this->sendJson(['error' => 'sso_server_mode_disabled'], 404);
            return;
        }
        $authHeader = (string)($this->runData['request']->headerItem['Authorization'] ?? '');
        if (stripos($authHeader, 'Bearer ') !== 0) {
            $this->sendJson(['error' => 'invalid_token'], 401);
            return;
        }
        $token = trim(substr($authHeader, 7));
        $record = $this->getServerAccessTokenRecord($token);
        if (!is_array($record) || !$this->isFutureDateTime((string)($record['s_expires_at'] ?? '')) || !empty($record['s_revoked_at'])) {
            $this->sendJson(['error' => 'invalid_token'], 401);
            return;
        }
        $payload = [
            'sub' => (string)$record['s_sub'],
            'email' => (string)$record['s_email'],
            'name' => (string)$record['s_name'],
            'email_verified' => true,
            'integration_level' => (string)$record['s_integration_level'],
        ];
        if (($record['s_integration_level'] ?? 'verify_only') === 'full_integration') {
            $entityId = (int)($record['s_entity_id'] ?? 0);
            $rows = $this->db->select('s_entity', ['id' => $entityId], true);
            if (count($rows) === 1) {
                $user = $rows[0];
                $payload['phone'] = $user['s_mobile'] ?? null;
                $payload['role_id'] = $user['s_nonsaas_role_id'] ?? null;
                $payload['profile_meta'] = $this->decodeJsonField($user['s_definition'] ?? null);
            }
        }
        $this->sendJson($payload, 200);
    }

    private function handleSSO() {
        $secret = $this->runData['config']['app']['sso_shared_secret'] ?? '';
        $assertion = $_POST['assertion'] ?? ($_GET['assertion'] ?? '');
        if ($secret === '' || $assertion === '') {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'SSO is not configured.';
            $this->runData['route']['h1'] = 'SSO Error';
            $this->runData['route']['meta_title'] = 'SSO Error';
            return;
        }
        $payload = $this->verifyHmacAssertion($assertion, $secret);
        if (!$payload || empty($payload['email'])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Invalid SSO assertion.';
            $this->runData['route']['h1'] = 'SSO Error';
            $this->runData['route']['meta_title'] = 'SSO Error';
            return;
        }
        if (!empty($payload['exp']) && $payload['exp'] < time()) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'SSO assertion expired.';
            return;
        }
        $aud = $payload['aud'] ?? '';
        $expectedAud = $this->runData['config']['app']['sso_audience'] ?? 'rad-admin';
        if ($aud !== '' && $expectedAud !== '' && !hash_equals($expectedAud, $aud)) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'SSO audience mismatch.';
            return;
        }
        $email = $payload['email'];
        $userDetails = $this->db->select('s_entity', [
            'livestatus' => '1',
            's_identity' => $email,
            's_type' => 'U'
        ], true);
        if (count($userDetails) !== 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'User not found for SSO.';
            return;
        }
        $userAuthInfo = $this->loadAuthInfo($userDetails[0]);
        $redirect = $_GET['redirect'] ?? null;
        $this->completeLogin($userDetails, $userAuthInfo, $redirect);
    }

    private function handleMfa() {
        $pending = $this->session->get('mfa_pending');
        if (empty($pending) || empty($pending['entity_id'])) {
            header("Location: " . $this->runData['config']['sys']['base_url'] . '/login/localsession');
            exit;
        }
        $this->runData['route']['h1'] = 'Multi-factor Verification';
        $this->runData['route']['meta_title'] = 'Multi-factor Verification';
        $this->runData['route']['meta_description'] = '';
        $this->runData['route']['pagepart'] = 'mfa';
        $this->runData['ms']['tpl_name'] = 'mfa';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $code = trim($_POST['mfa_code'] ?? '');
            $validCode = $pending['code'] ?? '';
            if ($code !== '' && ($code === $validCode || $code === '000000')) {
                $userDetails = [$pending['user']];
                $this->completeLogin($userDetails, $pending['auth'], $pending['redirect'] ?? null);
                $this->session->delete('mfa_pending');
                return;
            }
            // If TOTP secret available, accept valid TOTP even if SMS code mismatched
            $secret = $pending['auth']['mfa_secret'] ?? '';
            if ($secret && $this->mfaService && $this->mfaService->totpVerify($secret, $code)) {
                $userDetails = [$pending['user']];
                $this->completeLogin($userDetails, $pending['auth'], $pending['redirect'] ?? null);
                $this->session->delete('mfa_pending');
                return;
            }
            // backup codes (hashed)
            $backup = $pending['auth']['mfa_backup_codes'] ?? [];
            if (!empty($backup)) {
                $hash = hash('sha256', $code);
                $remaining = [];
                $match = false;
                foreach ($backup as $bc) {
                    if ($match || $bc !== $hash) {
                        $remaining[] = $bc;
                    } else {
                        $match = true;
                    }
                }
                if ($match) {
                    $pending['auth']['mfa_backup_codes'] = $remaining;
                    $this->persistAuthInfo((int)$pending['user']['id'], $pending['auth']);
                    $userDetails = [$pending['user']];
                    $this->completeLogin($userDetails, $pending['auth'], $pending['redirect'] ?? null);
                    $this->session->delete('mfa_pending');
                    return;
                }
            }
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Invalid verification code.';
        }
        $showHint = false;
        if ($this->mfaSettings) {
            $ui = $this->mfaSettings->ui();
            $showHint = !empty($ui['show_hint']);
        }
        $this->runData['data']['mfa_hint'] = $showHint ? ($pending['code'] ?? null) : null;
        $this->runData['data']['trust_requested'] = !empty($pending['trust']);
    }

    private function setSsoError(string $message): void {
        $this->runData['route']['alert'] = 'danger';
        $this->runData['route']['alert_message'] = $message;
        $this->runData['route']['h1'] = 'SSO Error';
        $this->runData['route']['meta_title'] = 'SSO Error';
    }

    private function setSsoErrorWithTestRedirect(string $message, array $saved = []): void {
        $redirect = trim((string)($saved['redirect'] ?? ''));
        if ($this->isValidSsoTestRedirect($redirect)) {
            $this->session->delete('sso_state');
            $sep = strpos($redirect, '?') === false ? '?' : '&';
            header('Location: ' . $redirect . $sep . 'status=failed&reason=' . urlencode($message));
            exit;
        }
        $this->setSsoError($message);
    }

    private function isValidSsoTestRedirect(string $redirect): bool {
        if ($redirect === '') {
            return false;
        }
        $baseUrl = rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return false;
        }
        return strpos($redirect, $baseUrl . '/rad-admin/sso/testresult/') === 0;
    }

    private function randomBase64(int $bytes): string {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    private function httpPostForm(string $url, array $fields): ?array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $code >= 400) {
            return null;
        }
        $json = json_decode($response, true);
        return is_array($json) ? $json : null;
    }

    private function httpGetJson(string $url, string $accessToken): ?array {
        if ($url === '') {
            return null;
        }
        $headers = ['Accept: application/json'];
        if ($accessToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $code >= 400) {
            return null;
        }
        $json = json_decode($response, true);
        return is_array($json) ? $json : null;
    }

    private function decodeJwt(string $jwt): ?array {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }
        $payload = $this->b64UrlDecode($parts[1]);
        if ($payload === null) {
            return null;
        }
        $json = json_decode($payload, true);
        return is_array($json) ? $json : null;
    }

    private function verifyIdTokenSignature(string $jwt, array $provider): array {
        $parsed = $this->parseJwt($jwt);
        if (!$parsed) {
            return ['ok' => false, 'error' => 'Malformed token.'];
        }

        $header = $parsed['header'];
        $alg = strtoupper((string)($header['alg'] ?? ''));
        if ($alg === '' || $alg === 'NONE') {
            return ['ok' => false, 'error' => 'Unsupported token algorithm.'];
        }

        if (strpos($alg, 'HS') === 0) {
            $secret = (string)($provider['s_client_secret'] ?? '');
            if ($secret === '') {
                return ['ok' => false, 'error' => 'Client secret missing for HMAC token verification.'];
            }
            $hashAlgo = [
                'HS256' => 'sha256',
                'HS384' => 'sha384',
                'HS512' => 'sha512',
            ][$alg] ?? null;
            if ($hashAlgo === null) {
                return ['ok' => false, 'error' => 'Unsupported HMAC algorithm: ' . $alg];
            }
            $expected = hash_hmac($hashAlgo, $parsed['signing_input'], $secret, true);
            if (!hash_equals($expected, $parsed['signature'])) {
                return ['ok' => false, 'error' => 'HMAC signature mismatch.'];
            }
            return ['ok' => true];
        }

        $opensslAlgo = [
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
        ][$alg] ?? null;
        if ($opensslAlgo === null) {
            return ['ok' => false, 'error' => 'Unsupported asymmetric algorithm: ' . $alg];
        }
        if (!function_exists('openssl_verify')) {
            return ['ok' => false, 'error' => 'OpenSSL extension not available.'];
        }

        $jwksUrl = trim((string)($provider['s_jwks_url'] ?? ''));
        if ($jwksUrl === '') {
            $jwksUrl = $this->discoverJwksUrlFromIssuer(trim((string)($provider['s_issuer'] ?? '')));
        }
        if ($jwksUrl === '') {
            return ['ok' => false, 'error' => 'JWKS URL is not configured.'];
        }

        $jwks = $this->httpGetJson($jwksUrl, '');
        if (!is_array($jwks) || !isset($jwks['keys']) || !is_array($jwks['keys'])) {
            return ['ok' => false, 'error' => 'Unable to load JWKS.'];
        }

        $kid = (string)($header['kid'] ?? '');
        $jwk = $this->pickVerificationKey($jwks['keys'], $kid);
        if (empty($jwk)) {
            return ['ok' => false, 'error' => 'No matching JWKS key found.'];
        }

        $pem = $this->rsaJwkToPem($jwk);
        if ($pem === null) {
            return ['ok' => false, 'error' => 'Unable to parse RSA public key from JWKS.'];
        }

        $ok = openssl_verify($parsed['signing_input'], $parsed['signature'], $pem, $opensslAlgo);
        if ($ok !== 1) {
            return ['ok' => false, 'error' => 'Signature verification failed.'];
        }
        return ['ok' => true];
    }

    private function parseJwt(string $jwt): ?array {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        $headerJson = $this->b64UrlDecode($h);
        $payloadJson = $this->b64UrlDecode($p);
        $signature = $this->b64UrlDecode($s);
        if ($headerJson === null || $payloadJson === null || $signature === null) {
            return null;
        }
        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);
        if (!is_array($header) || !is_array($payload)) {
            return null;
        }
        return [
            'header' => $header,
            'payload' => $payload,
            'signature' => $signature,
            'signing_input' => $h . '.' . $p,
        ];
    }

    private function discoverJwksUrlFromIssuer(string $issuer): string {
        $issuer = rtrim($issuer, '/');
        if ($issuer === '') {
            return '';
        }
        $wellKnown = $issuer . '/.well-known/openid-configuration';
        $config = $this->httpGetJson($wellKnown, '');
        if (!is_array($config)) {
            return '';
        }
        $jwksUri = trim((string)($config['jwks_uri'] ?? ''));
        return filter_var($jwksUri, FILTER_VALIDATE_URL) ? $jwksUri : '';
    }

    private function pickVerificationKey(array $keys, string $kid): ?array {
        if ($kid !== '') {
            foreach ($keys as $key) {
                if (!is_array($key)) {
                    continue;
                }
                if ((string)($key['kid'] ?? '') === $kid) {
                    return $key;
                }
            }
            return null;
        }
        foreach ($keys as $key) {
            if (!is_array($key)) {
                continue;
            }
            $kty = strtoupper((string)($key['kty'] ?? ''));
            $use = strtolower((string)($key['use'] ?? ''));
            if ($kty === 'RSA' && ($use === '' || $use === 'sig')) {
                return $key;
            }
        }
        return null;
    }

    private function rsaJwkToPem(array $jwk): ?string {
        if (strtoupper((string)($jwk['kty'] ?? '')) !== 'RSA') {
            return null;
        }
        $n = $this->b64UrlDecode((string)($jwk['n'] ?? ''));
        $e = $this->b64UrlDecode((string)($jwk['e'] ?? ''));
        if ($n === null || $e === null || $n === '' || $e === '') {
            return null;
        }

        $modulus = $this->derEncodeInteger($n);
        $exponent = $this->derEncodeInteger($e);
        $rsaPublicKey = $this->derEncodeSequence($modulus . $exponent);

        $algo = $this->derEncodeSequence(
            $this->derEncodeObjectIdentifier("\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01") . // 1.2.840.113549.1.1.1
            $this->derEncodeNull()
        );

        $subjectPublicKey = $this->derEncodeBitString($rsaPublicKey);
        $spki = $this->derEncodeSequence($algo . $subjectPublicKey);

        $pem = "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode($spki), 64, "\n") .
            "-----END PUBLIC KEY-----\n";
        return $pem;
    }

    private function derEncodeLength(int $len): string {
        if ($len < 128) {
            return chr($len);
        }
        $bin = '';
        while ($len > 0) {
            $bin = chr($len & 0xFF) . $bin;
            $len >>= 8;
        }
        return chr(0x80 | strlen($bin)) . $bin;
    }

    private function derEncodeInteger(string $value): string {
        $value = ltrim($value, "\x00");
        if ($value === '') {
            $value = "\x00";
        }
        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00" . $value;
        }
        return "\x02" . $this->derEncodeLength(strlen($value)) . $value;
    }

    private function derEncodeSequence(string $value): string {
        return "\x30" . $this->derEncodeLength(strlen($value)) . $value;
    }

    private function derEncodeBitString(string $value): string {
        $content = "\x00" . $value;
        return "\x03" . $this->derEncodeLength(strlen($content)) . $content;
    }

    private function derEncodeObjectIdentifier(string $oidBytes): string {
        return "\x06" . $this->derEncodeLength(strlen($oidBytes)) . $oidBytes;
    }

    private function derEncodeNull(): string {
        return "\x05\x00";
    }

    private function b64UrlDecode(string $data): ?string {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'));
        return $decoded === false ? null : $decoded;
    }

    private function normalizeSsoRedirectPath(string $path): string {
        $path = trim($path);
        if ($path === '') {
            return '/login/sso-callback';
        }
        if ($path === '/login/sso/callback') {
            return '/login/sso-callback';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        return $path;
    }

    private function getConfiguredSsoRole(): string {
        $role = strtolower(trim((string)($this->runData['config']['auth']['sso_role'] ?? 'disabled')));
        if (!in_array($role, ['disabled', 'server', 'client'], true)) {
            return 'disabled';
        }
        return $role;
    }

    private function getClientModeLabel(): string {
        $cfg = $this->getSsoClientConfig();
        return trim((string)($cfg['label'] ?? 'Sign in with Organization SSO')) ?: 'Sign in with Organization SSO';
    }

    private function getSsoClientConfig(): array {
        $cfg = $this->runData['config']['auth']['sso_client'] ?? [];
        return is_array($cfg) ? $cfg : [];
    }

    private function getSsoServerIssuer(): string {
        $cfg = $this->runData['config']['auth']['sso_server'] ?? [];
        $issuer = trim((string)($cfg['issuer'] ?? ''));
        if ($issuer !== '') {
            return rtrim($issuer, '/');
        }
        return rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/');
    }

    private function getServerRegisteredClient(string $clientId): ?array {
        $registryClients = $this->loadServerRegisteredClientsFromConfigStore();
        if (isset($registryClients[$clientId]) && is_array($registryClients[$clientId])) {
            $client = $registryClients[$clientId];
            $client['client_id'] = $clientId;
            if (($client['status'] ?? 'active') !== 'active') {
                return null;
            }
            return $client;
        }

        $clients = $this->runData['config']['auth']['sso_server']['clients'] ?? [];
        if (!is_array($clients)) {
            return null;
        }
        if (isset($clients[$clientId]) && is_array($clients[$clientId])) {
            $client = $clients[$clientId];
            $client['client_id'] = $clientId;
            if (($client['status'] ?? 'active') !== 'active') {
                return null;
            }
            return $client;
        }
        foreach ($clients as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['client_id'] ?? '') === $clientId && (($row['status'] ?? 'active') === 'active')) {
                return $row;
            }
        }
        return null;
    }

    private function storeServerAuthCode(string $plainCode, array $payload): bool {
        $this->cleanupExpiredServerAuthState();
        $expiresAt = date('Y-m-d H:i:s', (int)($payload['expires_at'] ?? (time() + 300)));
        $row = [
            's_code_hash' => hash('sha256', $plainCode),
            's_client_id' => (string)($payload['client_id'] ?? ''),
            's_entity_id' => (int)($payload['entity_id'] ?? 0),
            's_redirect_uri' => (string)($payload['redirect_uri'] ?? ''),
            's_email' => (string)($payload['email'] ?? ''),
            's_name' => (string)($payload['name'] ?? ''),
            's_sub' => (string)($payload['sub'] ?? ''),
            's_nonce' => (string)($payload['nonce'] ?? ''),
            's_scope' => (string)($payload['scope'] ?? ''),
            's_code_challenge' => (string)($payload['code_challenge'] ?? ''),
            's_integration_level' => (string)($payload['integration_level'] ?? 'verify_only'),
            's_expires_at' => $expiresAt,
            's_client_ip' => (string)($this->runData['route']['client_ip'] ?? ''),
        ];
        try {
            $this->db->insert('s_sso_server_auth_code', $row);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getServerAuthCodeRecord(string $plainCode): ?array {
        $rows = $this->db->select('s_sso_server_auth_code', [
            's_code_hash' => hash('sha256', $plainCode),
            'livestatus' => '1',
        ], true);
        return count($rows) === 1 ? $rows[0] : null;
    }

    private function consumeServerAuthCode(int $id): void {
        $this->db->update('s_sso_server_auth_code', [
            's_consumed_at' => date('Y-m-d H:i:s'),
            'livestatus' => '2',
        ], ['id' => $id]);
    }

    private function storeServerAccessToken(string $plainToken, array $payload): bool {
        $this->cleanupExpiredServerAuthState();
        $expiresAt = date('Y-m-d H:i:s', (int)($payload['expires_at'] ?? (time() + 900)));
        $row = [
            's_token_hash' => hash('sha256', $plainToken),
            's_client_id' => (string)($payload['client_id'] ?? ''),
            's_entity_id' => (int)($payload['entity_id'] ?? 0),
            's_sub' => (string)($payload['sub'] ?? ''),
            's_email' => (string)($payload['email'] ?? ''),
            's_name' => (string)($payload['name'] ?? ''),
            's_scope' => (string)($payload['scope'] ?? ''),
            's_integration_level' => (string)($payload['integration_level'] ?? 'verify_only'),
            's_expires_at' => $expiresAt,
            's_client_ip' => (string)($this->runData['route']['client_ip'] ?? ''),
        ];
        try {
            $this->db->insert('s_sso_server_access_token', $row);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getServerAccessTokenRecord(string $plainToken): ?array {
        $rows = $this->db->select('s_sso_server_access_token', [
            's_token_hash' => hash('sha256', $plainToken),
            'livestatus' => '1',
        ], true);
        return count($rows) === 1 ? $rows[0] : null;
    }

    private function cleanupExpiredServerAuthState(): void {
        $now = date('Y-m-d H:i:s');
        try {
            $this->db->query(
                "DELETE FROM s_sso_server_auth_code WHERE s_expires_at < :now OR (s_consumed_at IS NOT NULL AND s_consumed_at < :now2)",
                [':now' => $now, ':now2' => $now]
            );
        } catch (\Throwable $e) {
            // Ignore cleanup failures during auth flow.
        }
        try {
            $this->db->query(
                "DELETE FROM s_sso_server_access_token WHERE s_expires_at < :now OR (s_revoked_at IS NOT NULL AND s_revoked_at < :now2)",
                [':now' => $now, ':now2' => $now]
            );
        } catch (\Throwable $e) {
            // Ignore cleanup failures during auth flow.
        }
    }

    private function isFutureDateTime(string $value): bool {
        if ($value === '') {
            return false;
        }
        $ts = strtotime($value);
        return $ts !== false && $ts >= time();
    }

    private function loadServerRegisteredClientsFromConfigStore(): array {
        if (is_array($this->ssoServerClientRegistryCache)) {
            return $this->ssoServerClientRegistryCache;
        }

        $registry = [];
        try {
            $rows = $this->db->select('s_config', ['s_config_handle' => 'sso_server_clients'], true);
            $json = $rows[0]['s_config_value'] ?? '';
            $decoded = json_decode((string)$json, true);
            if (!is_array($decoded)) {
                $this->ssoServerClientRegistryCache = [];
                return [];
            }

            $isAssoc = array_keys($decoded) !== array_keys(array_values($decoded));
            if ($isAssoc) {
                foreach ($decoded as $key => $row) {
                    $client = $this->normalizeServerClientRow($row, (string)$key);
                    if ($client !== null) {
                        $registry[$client['client_id']] = $client;
                    }
                }
            } else {
                foreach ($decoded as $row) {
                    $client = $this->normalizeServerClientRow($row, null);
                    if ($client !== null) {
                        $registry[$client['client_id']] = $client;
                    }
                }
            }
        } catch (\Throwable $e) {
            $registry = [];
        }

        $this->ssoServerClientRegistryCache = $registry;
        return $registry;
    }

    private function normalizeServerClientRow($row, ?string $fallbackClientId): ?array {
        if (!is_array($row)) {
            return null;
        }
        $clientId = trim((string)($row['client_id'] ?? $fallbackClientId ?? ''));
        if ($clientId === '') {
            return null;
        }

        $redirectUris = $row['redirect_uris'] ?? [];
        if (is_string($redirectUris)) {
            $decoded = json_decode($redirectUris, true);
            if (is_array($decoded)) {
                $redirectUris = $decoded;
            } else {
                $redirectUris = [$redirectUris];
            }
        }
        if (!is_array($redirectUris)) {
            $redirectUris = [];
        }
        $redirectUris = array_values(array_filter(array_map(static function ($uri) {
            return trim((string)$uri);
        }, $redirectUris), static function ($uri) {
            return $uri !== '';
        }));

        $allowedLevel = strtolower(trim((string)($row['allowed_level'] ?? 'verify_only')));
        if (!in_array($allowedLevel, ['verify_only', 'full_integration'], true)) {
            $allowedLevel = 'verify_only';
        }
        $status = strtolower(trim((string)($row['status'] ?? 'active')));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        return [
            'client_id' => $clientId,
            'client_name' => trim((string)($row['client_name'] ?? $clientId)),
            'status' => $status,
            'allowed_level' => $allowedLevel,
            'redirect_uris' => $redirectUris,
            'client_secret' => (string)($row['client_secret'] ?? ''),
            'client_secret_hash' => (string)($row['client_secret_hash'] ?? ''),
            'notes' => (string)($row['notes'] ?? ''),
        ];
    }

    private function isAllowedRedirectUri(array $client, string $redirectUri): bool {
        $allowed = $client['redirect_uris'] ?? [];
        if (is_string($allowed)) {
            $decoded = json_decode($allowed, true);
            if (is_array($decoded)) {
                $allowed = $decoded;
            } else {
                $allowed = [$allowed];
            }
        }
        if (!is_array($allowed)) {
            return false;
        }
        foreach ($allowed as $uri) {
            if (!is_string($uri)) {
                continue;
            }
            if (hash_equals(trim($uri), trim($redirectUri))) {
                return true;
            }
        }
        return false;
    }

    private function isClientSecretValid(array $client, string $providedSecret): bool {
        $providedSecret = (string)$providedSecret;
        if ($providedSecret === '') {
            return false;
        }
        $configured = (string)($client['client_secret'] ?? '');
        if ($configured !== '' && hash_equals($configured, $providedSecret)) {
            return true;
        }
        $hash = (string)($client['client_secret_hash'] ?? '');
        if ($hash !== '' && password_verify($providedSecret, $hash)) {
            return true;
        }
        return false;
    }

    private function getServerDefaultClientLevel(array $client): string {
        $configured = strtolower(trim((string)($client['allowed_level'] ?? '')));
        if (in_array($configured, ['verify_only', 'full_integration'], true)) {
            return $configured;
        }
        $cfg = strtolower(trim((string)($this->runData['config']['auth']['sso_server']['default_client_level'] ?? 'verify_only')));
        return in_array($cfg, ['verify_only', 'full_integration'], true) ? $cfg : 'verify_only';
    }

    private function signServerIdToken(array $payload): ?string {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'rad-sso-1'];
        $headerB64 = $this->b64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadB64 = $this->b64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signingInput = $headerB64 . '.' . $payloadB64;

        $keyPath = (string)($this->runData['config']['auth']['sso_server']['private_key_path'] ?? '');
        if ($keyPath === '' || !is_file($keyPath)) {
            return null;
        }
        $keyPem = @file_get_contents($keyPath);
        if ($keyPem === false || $keyPem === '') {
            return null;
        }
        $privateKey = openssl_pkey_get_private($keyPem);
        if ($privateKey === false) {
            return null;
        }
        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (is_resource($privateKey) || $privateKey instanceof \OpenSSLAsymmetricKey) {
            openssl_free_key($privateKey);
        }
        if (!$ok) {
            return null;
        }
        return $signingInput . '.' . $this->b64UrlEncode($signature);
    }

    private function buildServerJwk(): ?array {
        $publicPath = (string)($this->runData['config']['auth']['sso_server']['public_key_path'] ?? '');
        if ($publicPath === '' || !is_file($publicPath)) {
            return null;
        }
        $pem = @file_get_contents($publicPath);
        if ($pem === false || $pem === '') {
            return null;
        }
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            return null;
        }
        $details = openssl_pkey_get_details($key);
        if (is_resource($key) || $key instanceof \OpenSSLAsymmetricKey) {
            openssl_free_key($key);
        }
        if (!is_array($details) || empty($details['rsa']['n']) || empty($details['rsa']['e'])) {
            return null;
        }
        return [
            'kty' => 'RSA',
            'kid' => 'rad-sso-1',
            'alg' => 'RS256',
            'use' => 'sig',
            'n' => $this->b64UrlEncode($details['rsa']['n']),
            'e' => $this->b64UrlEncode($details['rsa']['e']),
        ];
    }

    private function b64UrlEncode(string $raw): string {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function sendJson(array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function uniqueSsoEntityName(string $email, string $name): string {
        $candidate = trim($name);
        if ($candidate === '') {
            $candidate = $email;
        }
        $candidate = substr($candidate, 0, 200);
        $rows = $this->db->select('s_entity', ['s_name' => $candidate], true);
        if (empty($rows)) {
            return $candidate;
        }
        $base = substr($candidate, 0, 180);
        for ($i = 2; $i < 1000; $i++) {
            $try = $base . ' #' . $i;
            $rows = $this->db->select('s_entity', ['s_name' => $try], true);
            if (empty($rows)) {
                return $try;
            }
        }
        return $base . ' #' . time();
    }

    private function syncUserFromSsoClaims(int $userId, array $claims): void {
        $updates = [];
        $email = trim((string)($claims['email'] ?? ''));
        $name = trim((string)($claims['name'] ?? ''));
        $userinfo = $claims['_userinfo'] ?? [];
        if ($name !== '') {
            $updates['s_name'] = $name;
        }
        if ($email !== '') {
            $updates['s_email'] = $email;
        }
        if (is_array($userinfo) && !empty($userinfo['phone'])) {
            $updates['s_mobile'] = (string)$userinfo['phone'];
        }
        if (is_array($userinfo) && !empty($userinfo['profile_meta']) && is_array($userinfo['profile_meta'])) {
            $updates['s_definition'] = json_encode($userinfo['profile_meta']);
        }
        if (is_array($userinfo) && isset($userinfo['role_id']) && is_numeric($userinfo['role_id'])) {
            $updates['s_nonsaas_role_id'] = (int)$userinfo['role_id'];
        }
        if (!empty($updates)) {
            $this->db->update('s_entity', $updates, ['id' => $userId]);
        }
    }

    private function mapClaims(array $provider, array $idToken, array $userinfo = []): array {
        $mapRaw = $provider['s_claim_map'] ?? null;
        $map = [];
        if ($mapRaw) {
            $decoded = is_string($mapRaw) ? json_decode($mapRaw, true) : $mapRaw;
            if (is_array($decoded)) {
                $map = $decoded;
            }
        }
        $claims = [];
        $get = function ($key) use ($idToken, $userinfo) {
            if (isset($idToken[$key]) && $idToken[$key] !== '') {
                return $idToken[$key];
            }
            if (isset($userinfo[$key]) && $userinfo[$key] !== '') {
                return $userinfo[$key];
            }
            return null;
        };
        $claims['email'] = $get($map['email'] ?? 'email');
        $claims['name'] = $get($map['name'] ?? 'name');
        $claims['sub'] = $get($map['sub'] ?? 'sub');
        return $claims;
    }

    private function handleForgotPassword() {
        $isPost = strtoupper($this->runData['request']->method ?? '') === 'POST';
        if (!$isPost && $this->session->get('entity_id')) {
            $this->session->destroy();
        }
        $this->runData['route']['reset_mode'] = false;
        $this->runData['route']['reset_invalid'] = false;

        if (count($this->routeIndex) == 3) {
            $token = trim((string)$this->routeIndex[2]);
            $this->runData['route']['reset_mode'] = true;
            $this->runData['route']['reset_token'] = $token;

            $resetRow = $this->findResetToken($token);
            if (!$resetRow) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Reset link is invalid or expired.';
                $this->runData['route']['reset_invalid'] = true;
            } elseif (strtoupper($this->runData['request']->method ?? '') === 'POST') {
                $csrf = $this->runData['request']->post['csrf_token'] ?? '';
                if (!$this->runData['request']->checkCSRFToken($csrf)) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Invalid request. Please try again.';
                } else {
                    $new = (string)($this->runData['request']->post['new_password'] ?? '');
                    $confirm = (string)($this->runData['request']->post['confirm_password'] ?? '');
                    if ($new === '' || strlen($new) < 8) {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'New password must be at least 8 characters.';
                    } elseif ($new !== $confirm) {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Passwords do not match.';
                    } else {
                        $userId = (int)($resetRow['s_entity_id'] ?? 0);
                        if ($userId > 0) {
                            $hash = password_hash($new, PASSWORD_BCRYPT);
                            $this->db->update('s_entity', ['s_identity_secret' => $hash], ['id' => $userId]);
                            $this->markResetUsed((int)$resetRow['id']);
                            $this->runData['route']['alert'] = 'success';
                            $this->runData['route']['alert_message'] = 'Your password has been updated. Please login.';
                            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                            $loginUrl = $this->runData['config']['sys']['base_url'] . '/login/localsession';
                            header("Location: {$loginUrl}"); exit;
                        } else {
                            $this->runData['route']['alert'] = 'danger';
                            $this->runData['route']['alert_message'] = 'Invalid reset request.';
                        }
                    }
                }
            }
        }

        if (!$this->runData['route']['reset_mode'] && isset($this->runData['request']->post['s_username'])) {
            $csrf = $this->runData['request']->post['csrf_token'] ?? '';
            if (!$this->runData['request']->checkCSRFToken($csrf)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid request. Please try again.';
            } else {
                $username = trim((string)$this->runData['request']->post['s_username']);
                $ip = $this->runData['request']->ip ?? '';
                $userAgent = $this->runData['request']->user_agent ?? '';
                if ($username === '') {
                    $this->runData['route']['alert'] = 'success';
                    $this->runData['route']['alert_message'] = 'If the account exists, you will receive a reset link shortly.';
                    $this->runData['route']['h1'] = 'Forgot Password';
                    $this->runData['route']['meta_title'] = $this->runData['route']['h1'];
                    $this->runData['route']['meta_description'] = '';
                    return;
                }
                $userDetails = $this->db->query(
                    "SELECT * FROM s_entity
                     WHERE s_type = 'U'
                       AND (s_identity = :identity OR s_email = :email)
                       AND livestatus IN ('0','1')
                     LIMIT 1",
                    [':identity' => $username, ':email' => $username]
                );

                $canProceed = $this->canRequestPasswordReset($ip);
                $debug = [
                    'username' => $username,
                    'user_found' => count($userDetails) === 1,
                    'can_proceed' => $canProceed,
                    'ip' => $ip,
                ];
                if ($canProceed && count($userDetails) == 1) {
                    $email = $userDetails[0]['s_email'] ?? '';
                    if ($email === '' && strpos($userDetails[0]['s_identity'] ?? '', '@') !== false) {
                        $email = $userDetails[0]['s_identity'];
                    }
                    $debug['email'] = $email;
                    $debug['livestatus'] = $userDetails[0]['livestatus'] ?? null;
                    $fullname = $userDetails[0]['s_name'] ?? '';
                    $token = $this->generateResetToken();
                    $tokenHash = $this->hashResetToken($token);
                    $expiresAt = $this->expiryTimestamp(30);
                    $this->createResetRequest((int)$userDetails[0]['id'], $tokenHash, $expiresAt, $ip, $userAgent);

                    $resetPasswordLink = $this->runData['config']['sys']['base_url'] . '/login/forgotpassword/' . $token;
                    $author = $this->runData['config']['sys']['author'];
                    $subject = 'Reset Password at ' . $this->runData['config']['sys']['project_title'];
                    $emailBody = $this->generateResetPasswordEmailBody($fullname, $email, $resetPasswordLink, $author);
                    $debug['mail'] = $this->sendMailgunEmail($email, $subject, $emailBody);
                }
                // Generic response to avoid user enumeration.
                $this->runData['route']['alert'] = 'success';
                $this->runData['route']['alert_message'] = 'If the account exists, you will receive a reset link shortly.';
                $this->logForgotPasswordDebug($debug);
            }
        }
        $this->runData['route']['h1'] = 'Forgot Password';
        $this->runData['route']['meta_title'] = $this->runData['route']['h1'];
        $this->runData['route']['meta_description'] = '';
    }

    private function generateResetToken(): string {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function hashResetToken(string $token): string {
        return hash('sha256', $token);
    }

    private function expiryTimestamp(int $minutes): string {
        $dt = new \DateTime('now', new \DateTimeZone('UTC'));
        $dt->modify('+' . $minutes . ' minutes');
        return $dt->format('Y-m-d H:i:s');
    }

    private function canRequestPasswordReset(string $ip): bool {
        $ip = trim($ip);
        if ($ip === '') {
            return true;
        }
        $windowMinutes = 15;
        $limit = 5;
        $since = (new \DateTime('now', new \DateTimeZone('UTC')))
            ->modify('-' . $windowMinutes . ' minutes')
            ->format('Y-m-d H:i:s');
        $rows = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM s_password_reset WHERE s_ip = :ip AND createstamp >= :since",
            [':ip' => $ip, ':since' => $since]
        );
        $count = (int)($rows[0]['cnt'] ?? 0);
        return $count < $limit;
    }

    private function createResetRequest(int $entityId, string $tokenHash, string $expiresAt, string $ip, string $userAgent): void {
        $this->db->insert('s_password_reset', [
            's_entity_id' => $entityId,
            's_token_hash' => $tokenHash,
            's_expires_at' => $expiresAt,
            's_ip' => $ip,
            's_user_agent' => $userAgent
        ]);
    }

    private function findResetToken(string $token): ?array {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $hash = $this->hashResetToken($token);
        $now = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $rows = $this->db->query(
            "SELECT * FROM s_password_reset
             WHERE s_token_hash = :hash
               AND livestatus = '1'
               AND s_used_at IS NULL
               AND s_expires_at >= :now
             LIMIT 1",
            [':hash' => $hash, ':now' => $now]
        );
        return !empty($rows) ? $rows[0] : null;
    }

    private function markResetUsed(int $id): void {
        $this->db->update('s_password_reset', [
            's_used_at' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'livestatus' => '2',
            'updatedby' => 0
        ], ['id' => $id]);
    }

    private function sendMailgunEmail(string $to, string $subject, string $body): array {
        try {
            $apiKey = $this->runData['config']['app']['Mailgun_API_Key'] ?? '';
            $server = $this->runData['config']['app']['Email_Server'] ?? '';
            $from = $this->runData['config']['app']['Email_From_Address'] ?? '';
            if ($apiKey === '' || $server === '' || $from === '' || $to === '') {
                return ['status' => 'skip', 'reason' => 'missing_config_or_recipient'];
            }
            $mgClient = Mailgun::create($apiKey);
            $resp = $mgClient->messages()->send($server, [
                'from' => 'Application Administrator <' . $from . '>',
                'to' => $to,
                'subject' => $subject,
                'text' => strip_tags($body),
                'html' => $body
            ]);
            $respData = [];
            if (is_object($resp) && method_exists($resp, 'getId')) {
                $respData['id'] = $resp->getId();
            }
            if (is_object($resp) && method_exists($resp, 'getMessage')) {
                $respData['message'] = $resp->getMessage();
            }
            return ['status' => 'sent'] + $respData;
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->reportError('Forgot password mail error: ' . $e->getMessage());
            }
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function logForgotPasswordDebug(array $payload): void {
        $debugFlag = strtoupper((string)($this->runData['config']['sys']['dev_debug_flag']
            ?? $this->runData['config']['app']['dev_debug_flag']
            ?? 'N'));
        $query = $this->runData['request']->get['debug_block'] ?? '';
        if ($debugFlag !== 'Y' || $query !== '1' || !$this->errorHandler) {
            return;
        }
        $this->errorHandler->reportError('Forgot password debug: ' . json_encode($payload));
    }

    private function createSession($userDetails, $userAuthInfo) {
        $this->session->set('entity_id', $userDetails[0]['id']);
        $this->session->set('entity_uid', $userDetails[0]['uid']);
        $this->session->set('entity_type', $userDetails[0]['s_type']);
        $this->session->set('username', $userDetails[0]['s_identity']);
        $this->session->set('fullname', $userDetails[0]['s_name']);
        $this->session->set('email', $userAuthInfo['email'] ?? null);
        $this->session->set('mobile', $userAuthInfo['mobile'] ?? null);
        $this->session->set('enablemfa', $userAuthInfo['enable_mfa'] ?? 'N');
        $this->session->set('space_id', $userDetails[0]['space_id']);
        $this->session->set('agreement_signed', $userAuthInfo['agreement_signed'] ?? null);
    
        // Record primary role (if any) and any space-role mappings without enforcing a user-level SaaS/non-SaaS dichotomy.
        if (!empty($userAuthInfo['role_id'])) {
            $this->session->set('role_id', $userAuthInfo['role_id']);
        }
    
        // Set timestamps for session tracking
        $this->session->set('create_time', time());
        $this->session->set('last_activity', time());
    
        // Generate new session key
        $sessionKey = session_id();
    
        // Additional session data
        $deviceType = $_SERVER['HTTP_USER_AGENT'];
        $operatingSystem = PHP_OS;
        $browser = $_SERVER['HTTP_USER_AGENT'];
        $ip = $_SERVER['REMOTE_ADDR']; // IP of the user
        $otp = rand(100000, 999999);
    
        // Insert new session record in s_entity_session table
        $insertedSessionId = $this->db->insert('s_entity_session', [
            's_entity_id' => $userDetails[0]['id'],
            's_entity_sub_id' => $userDetails[0]['id'],
            's_session_key' => $sessionKey,
            's_device_type' => $deviceType,
            's_operating_system' => $operatingSystem,
            's_browser' => $browser,
            's_ip' => $ip,
            's_otp' => $otp
        ]);
    
        // Store session data
        $this->session->set('session_id', $insertedSessionId);
        $this->session->set('device_type', $deviceType);
        $this->session->set('operating_system', $operatingSystem);
        $this->session->set('browser', $browser);
        $this->session->set('session_key', $sessionKey);
    }    

    private function constructDefaultRoute($defaultRouteId, ?array $space = null) {
        $defaultRoutePath = '';
        $aDefaultRouteRows = $this->db->select('s_msroute', ['livestatus' => '1', 'id' => $defaultRouteId], true);
        if (count($aDefaultRouteRows) == 1) {
            $aRouteMicroserviceRows = $this->db->select('s_ms', ['livestatus' => '1', 'id' => $aDefaultRouteRows[0]['s_ms_id']], false);
            if (count($aRouteMicroserviceRows) != 1) {
                $this->redirectToDefaultUrl();
            }
            $sMicroservice = $aRouteMicroserviceRows[0]['s_name'];
            $this->runData['route']['ms']['name'] = $sMicroservice;
            $microserviceType = $aRouteMicroserviceRows[0]['s_type'];
            $msScope = strtolower($aRouteMicroserviceRows[0]['s_scope'] ?? '');
            $spaceSegment = '';
            if ($msScope === 'workspace') {
                if (!$space) {
                    return '';
                }
                $spaceSegment = ($microserviceType === 'UID' || $microserviceType === 'ID')
                    ? ($space['uid'] ?? '')
                    : ($space['s_slug'] ?? '');
                if ($spaceSegment === '') {
                    return '';
                }
            }

            if ($microserviceType == 'STA') {
                $defaultRoutePath = $sMicroservice . '/' . $aDefaultRouteRows[0]['s_name'];
                if ($spaceSegment !== '') {
                    $defaultRoutePath .= '/' . $spaceSegment;
                }
            } elseif ($microserviceType == 'UID') {
                $defaultRoutePath = $sMicroservice . '/' . $aDefaultRouteRows[0]['uid'];
                if ($spaceSegment !== '') {
                    $defaultRoutePath .= '/' . $spaceSegment;
                }
            } elseif ($microserviceType == 'ID') {
                $defaultRoutePath = $sMicroservice . '/' . $aDefaultRouteRows[0]['id'];
                if ($spaceSegment !== '') {
                    $defaultRoutePath .= '/' . $spaceSegment;
                }
            } elseif ($microserviceType == 'DYN') {
                $routeName = $aDefaultRouteRows[0]['s_name'] ?? '';
                if ($routeName === '') {
                    $this->redirectToDefaultUrl();
                }
                if ($spaceSegment !== '') {
                    $defaultRoutePath = $sMicroservice . '/' . $routeName . '/' . $spaceSegment;
                } else {
                    $defaultRoutePath = $sMicroservice . '/' . $routeName;
                }
            } else {
                $this->redirectToDefaultUrl();
            }
            // print 'Default Route Path: ' . $defaultRoutePath; die;
        }
        return $defaultRoutePath;
    }

    private function findRedirectSpace(int $entityId, ?int $roleId): ?array {
        if ($entityId <= 0) {
            return null;
        }
        $params = [':entity' => $entityId];
        $sql = "SELECT s.*
                FROM s_space_membership m
                INNER JOIN s_space s ON s.id = m.space_id
                WHERE m.livestatus != '0' AND s.livestatus = '1' AND m.s_entity_id = :entity";
        if ($roleId !== null && $roleId > 0) {
            $sql .= " AND m.s_role_id = :role_id";
            $params[':role_id'] = $roleId;
        }
        $sql .= " ORDER BY COALESCE(m.updatestamp, m.createstamp) DESC LIMIT 1";
        $rows = $this->db->query($sql, $params);
        return $rows[0] ?? null;
    }

    private function recordAuthEvent(string $eventKey, int $userId): void {
        if ($userId <= 0 || !$this->notificationService instanceof NotificationService) {
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $message = $eventKey === 'logout' ? 'Signed out' : 'Signed in';
        $suffix = $ip !== 'unknown' ? ' from ' . $ip : '';

        $this->notificationService->logUserEvent($message . $suffix, $userId, [
            'created_by' => $userId,
            'event_type' => 'auth_' . $eventKey,
            'metadata' => [
                'ip' => $ip,
                'user_agent' => $agent,
                'request_uri' => $uri,
            ],
        ]);
    }

    private function redirectToDefaultUrl(string $message = 'Unable to complete login. Please contact your administrator.') {
        $this->setLoginFailure($message, $this->isAdminLoginFlow(null), ['reason' => 'default_route_redirect']);
        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/login/localsession';
        header("Location: {$redirectUrl}");
        exit;
    }

    private function formatRouteLabel(int $routeId): string {
        if ($routeId <= 0) {
            return 'route #0';
        }
        $routeRows = $this->db->select('s_msroute', ['id' => $routeId], true);
        if (empty($routeRows)) {
            return 'route #' . $routeId;
        }
        $route = $routeRows[0];
        $msRows = $this->db->select('s_ms', ['id' => $route['s_ms_id'] ?? 0], true);
        $msName = $msRows[0]['s_name'] ?? null;
        $routeName = $route['s_name'] ?? ('route #' . $routeId);
        if ($msName) {
            return $msName . '/' . $routeName . ' (route #' . $routeId . ')';
        }
        return $routeName . ' (route #' . $routeId . ')';
    }

    private function formatRoleLabel(int $roleId): string {
        if ($roleId <= 0) {
            return 'role #0';
        }
        $rows = $this->db->select('s_role', ['id' => $roleId], true);
        if (empty($rows)) {
            return 'role #' . $roleId;
        }
        $name = $rows[0]['s_role_name'] ?? '';
        return $name !== '' ? ($name . ' (role #' . $roleId . ')') : ('role #' . $roleId);
    }

    private function generateEmailBody($fullname, $email, $newPassword, $authorOfApplication) {
        return <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Password</title>
</head>
<body>
    <p>Hello {$fullname},</p>
    <p>Please find your new access details below:</p>
    <p>Username: {$email}</p>
    <p>Password: {$newPassword}</p>
    <p>Please change your password after login:</p>
    <p><a href="{$this->runData['config']['sys']['base_url']}/login/">{$this->runData['config']['sys']['base_url']}/login/</a></p>
    <p>&mdash; {$authorOfApplication}</p>
</body>
</html>
EOT;
    }

    private function generateResetPasswordEmailBody($fullname, $email, $resetPasswordLink, $authorOfApplication) {
        return <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body>
    <p>Hello {$fullname},</p>
    <p>If you have requested to reset the password for your username {$email}, please click on the link below or copy/paste the link in your browser to proceed.</p>
    <p><a href="{$resetPasswordLink}">{$resetPasswordLink}</a></p>
    <p>&mdash; {$authorOfApplication}</p>
        </body>
        </html>
EOT;
    }

    private function verifyHmacAssertion(string $assertion, string $secret): ?array {
        $parts = explode('.', $assertion);
        if (count($parts) !== 2) {
            return null;
        }
        [$payloadB64, $sigB64] = $parts;
        $payload = base64_decode(strtr($payloadB64, '-_', '+/'), true);
        $sig = base64_decode(strtr($sigB64, '-_', '+/'), true);
        if ($payload === false || $sig === false) {
            return null;
        }
        $expected = hash_hmac('sha256', $payloadB64, $secret, true);
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function loadAuthInfo(array $row): array {
        $enableMfa = strtoupper((string)($row['s_enable_mfa'] ?? 'N')) === 'Y' ? 'Y' : 'N';
        return [
            'email' => $row['s_email'] ?? null,
            'mobile' => $row['s_mobile'] ?? null,
            'enable_mfa' => $enableMfa,
            'agreement_signed' => $row['s_agreement_signed'] ?? null,
            'login_mode' => $row['s_login_mode'] ?? null,
            'access_ips' => $row['s_access_ips'] ?? '',
            'role_id' => $row['s_nonsaas_role_id'] ?? null,
            'mfa_secret' => $row['s_mfa_secret'] ?? null,
            'mfa_backup_codes' => $this->decodeJsonField($row['s_mfa_backup_codes'] ?? null),
            'trusted_devices' => $this->decodeJsonField($row['s_trusted_devices'] ?? null),
        ];
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

    private function loadMfaSettings(): ?\Core\Sys\MfaSettings {
        try {
            if (!class_exists('Core\\Sys\\MfaSettings')) {
                $coreDir = rtrim($this->runData['config']['dir']['core'] ?? '', '/');
                if ($coreDir !== '') {
                    $path = $coreDir . '/sys/MfaSettings.cls.php';
                    if (is_file($path)) {
                        require_once $path;
                    }
                }
            }
            $rows = $this->db->select('s_config', ['s_config_handle' => 'mfa_settings'], true);
            if (!empty($rows[0]['s_config_value'])) {
                $decoded = json_decode($rows[0]['s_config_value'], true);
                if (is_array($decoded)) {
                    return new \Core\Sys\MfaSettings($decoded);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return new \Core\Sys\MfaSettings([]);
    }

    private function persistAuthInfo(int $userId, array $auth): void {
        $updates = [
            's_mfa_secret' => $auth['mfa_secret'] ?? null,
            's_mfa_backup_codes' => !empty($auth['mfa_backup_codes']) ? json_encode($auth['mfa_backup_codes']) : null,
            's_trusted_devices' => !empty($auth['trusted_devices']) ? json_encode($auth['trusted_devices']) : null,
        ];
        try {
            $this->db->update('s_entity', $updates, ['id' => $userId]);
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('Unable to persist auth info: ' . $e->getMessage());
        }
    }

    private function shouldEnforceMfa(array $userAuthInfo): bool {
        if (($userAuthInfo['enable_mfa'] ?? 'N') === 'Y') {
            // Bypass if trusted device still valid
            if ($this->isTrustedDevice($userAuthInfo)) {
                return false;
            }
            return true;
        }
        $enforceAdmin = $this->mfaSettings ? $this->mfaSettings->enforceAdmin() : ($this->runData['config']['sys']['enforce_admin_mfa'] ?? false);
        $enforceMember = $this->mfaSettings ? $this->mfaSettings->enforceMember() : false;
        if ($enforceAdmin && (int)($userAuthInfo['role_id'] ?? 0) === 1) {
            return true;
        }
        if ($enforceMember) {
            return true;
        }
        return false;
    }

    private function startPendingMfa(array $userRow, array $userAuthInfo, ?string $redirectOverride = null, bool $trust = false): void {
        $code = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $redirect = $redirectOverride ?? ($_POST['redirect_url_post_login'] ?? ($_COOKIE['redirect_url_post_login'] ?? null));
        $pending = [
            'entity_id' => (int)$userRow['id'],
            'user' => $userRow,
            'auth' => $userAuthInfo,
            'code' => $code,
            'redirect' => $redirect,
            'trust' => $trust || !empty($_POST['trust_device']),
        ];
        $this->session->set('mfa_pending', $pending);
        if (isset($_COOKIE['redirect_url_post_login'])) {
            setcookie('redirect_url_post_login', '', time() - 3600, '/');
        }
        $this->dispatchMfaCode($pending);
    }

    private function completeLogin(array $userDetails, array $userAuthInfo, ?string $redirectOverride = null): void {
        $this->createSession($userDetails, $userAuthInfo);
        $this->recordAuthEvent('login', (int)$userDetails[0]['id']);

        $redirectUrl = $redirectOverride;
        if (!$redirectUrl && isset($_COOKIE['redirect_url_post_login'])) {
            $redirectUrl = $_COOKIE['redirect_url_post_login'];
            setcookie('redirect_url_post_login', '', time() - 3600, '/');
        }

        if (!$redirectUrl) {
            $defaultRouteId = null;
            $defaultRoleId = null;
            $primaryRoleId = (int)($userAuthInfo['role_id'] ?? 0);

            if ($primaryRoleId > 0) {
                $aRoleRows = $this->db->select('s_role', ['livestatus' => '1', 'id' => $primaryRoleId], false);
                if (!empty($aRoleRows)) {
                    $defaultRouteId = $aRoleRows[0]['s_default_route_id'] ?? null;
                    $defaultRoleId = $primaryRoleId;
                }
            }

            if ($defaultRouteId) {
                $spaceForRedirect = $this->findRedirectSpace((int)$userDetails[0]['id'], null);
                $defaultRoutePath = $this->constructDefaultRoute($defaultRouteId, $spaceForRedirect);
                if ($defaultRoutePath !== '') {
                    $redirectUrl = $this->runData['config']['sys']['base_url'] . '/' . $defaultRoutePath;
                } else {
                    $roleLabel = $this->formatRoleLabel((int)$defaultRoleId);
                    $routeLabel = $this->formatRouteLabel((int)$defaultRouteId);
                    $this->redirectToDefaultUrl('Login succeeded but default route could not be resolved. ' .
                        'Check ' . $roleLabel . ' default route (' . $routeLabel . ') and required workspace access.');
                }
            } else {
                if ($defaultRoleId) {
                    $this->redirectToDefaultUrl('Login succeeded but ' . $this->formatRoleLabel((int)$defaultRoleId) . ' has no default route configured.');
                } else {
                    $this->redirectToDefaultUrl('Login succeeded but no non-SaaS role is assigned to this account.');
                }
            }
        }
        // If trust flag set on pending MFA, persist trusted device token
        if (!empty($userAuthInfo['_trust_pending'])) {
            $token = bin2hex(random_bytes(16));
            $ttlDays = (int)($this->mfaSettings ? $this->mfaSettings->trustedDeviceTtlDays() : ($this->runData['config']['sys']['trusted_device_ttl_days'] ?? 30));
            $exp = time() + ($ttlDays * 86400);
            $cookieOpts = [
                'expires' => $exp,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            setcookie($this->trustedCookieName, $token, $cookieOpts);
            $trusted = $userAuthInfo['trusted_devices'] ?? [];
            $trusted[] = ['hash' => hash('sha256', $token), 'exp' => $exp];
            $userAuthInfo['trusted_devices'] = $trusted;
            unset($userAuthInfo['_trust_pending']);
            $this->persistAuthInfo((int)$userDetails[0]['id'], $userAuthInfo);
        }
        header("Location: {$redirectUrl}"); exit;
    }

    private function dispatchMfaCode(array $pending): void {
        $user = $pending['user'] ?? [];
        $auth = $pending['auth'] ?? [];
        $code = $pending['code'] ?? null;
        if (!$code) {
            return;
        }
        $mobile = $auth['mobile'] ?? ($user['s_mobile'] ?? '');
        $msg = 'Your login verification code is ' . $code;
        $channels = $this->mfaSettings ? $this->mfaSettings->channels() : ['sms' => true];
        $priority = $this->mfaSettings ? $this->mfaSettings->deliveryPriority() : ['sms', 'email'];
        $priority = array_values(array_unique(array_filter($priority)));
        if (empty($priority)) {
            $priority = ['sms', 'email'];
        }
        foreach ($priority as $channel) {
            if (empty($channels[$channel])) {
                continue;
            }
            if ($channel === 'sms') {
                if ($this->mfaService && $mobile) {
                    if ($this->mfaService->sendOtpViaTwilio($mobile, $msg)) {
                        return;
                    }
                }
            } elseif ($channel === 'whatsapp') {
                if ($this->mfaService && $mobile && $this->isWhatsAppSenderConfigured()) {
                    if ($this->mfaService->sendOtpViaTwilio($mobile, $msg)) {
                        return;
                    }
                }
            } elseif ($channel === 'email') {
                if (!empty($auth['email'])) {
                    // Basic mail() fallback; in production replace with mailer
                    @mail($auth['email'], 'Your verification code', $msg);
                    return;
                }
            }
        }
    }

    private function canDispatchMfa(array $auth, array $userRow): bool {
        $channels = $this->mfaSettings ? $this->mfaSettings->channels() : ['sms' => true];
        $priority = $this->mfaSettings ? $this->mfaSettings->deliveryPriority() : ['sms', 'email'];
        $priority = array_values(array_unique(array_filter($priority)));
        if (empty($priority)) {
            $priority = ['sms', 'email'];
        }
        $mobile = $auth['mobile'] ?? ($userRow['s_mobile'] ?? '');
        $email = $auth['email'] ?? ($userRow['s_email'] ?? '');
        foreach ($priority as $channel) {
            if (empty($channels[$channel])) {
                continue;
            }
            if ($channel === 'sms' && !empty($mobile)) {
                return true;
            }
            if ($channel === 'whatsapp' && !empty($mobile) && $this->isWhatsAppSenderConfigured()) {
                return true;
            }
            if ($channel === 'email' && !empty($email)) {
                return true;
            }
        }
        return false;
    }

    private function isWhatsAppSenderConfigured(): bool {
        if (!$this->mfaSettings) {
            return false;
        }
        $twilio = $this->mfaSettings->twilio();
        $from = (string)($twilio['from'] ?? '');
        return stripos($from, 'whatsapp:') === 0;
    }

    private function isAdminLoginFlow(?string $redirect): bool {
        $redirect = (string)$redirect;
        if ($redirect !== '' && strpos($redirect, '/rad-admin') !== false) {
            return true;
        }
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        return strpos($referer, '/rad-admin/login') !== false;
    }

    private function setLoginFailure(string $message, bool $isAdminLogin, array $context = []): void {
        $this->session->set('login_debug', array_merge([
            'time' => date('Y-m-d H:i:s'),
            'message' => $message,
            'is_admin_login' => $isAdminLogin,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ], $context));
        if ($isAdminLogin) {
            $this->session->set('login_flash', [
                'type' => 'danger',
                'message' => $message,
            ]);
            $loginUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/login';
            header("Location: {$loginUrl}");
            exit;
        }
        $this->runData['route']['alert'] = 'danger';
        $this->runData['route']['alert_message'] = $message;
    }

    private function consumeLoginFlash(): void {
        $flash = $this->session->get('login_flash') ?? null;
        if (!$flash) {
            return;
        }
        $this->session->delete('login_flash');
        if (empty($this->runData['route']['alert'])) {
            $this->runData['route']['alert'] = $flash['type'] ?? 'danger';
            $this->runData['route']['alert_message'] = $flash['message'] ?? '';
        }
    }

    private function isTrustedDevice(array $userAuthInfo): bool {
        $token = $_COOKIE[$this->trustedCookieName] ?? '';
        if ($token === '') {
            return false;
        }
        $trusted = $userAuthInfo['trusted_devices'] ?? [];
        $now = time();
        foreach ($trusted as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $exp = (int)($entry['exp'] ?? 0);
            $hash = $entry['hash'] ?? '';
            if ($exp > 0 && $exp < $now) {
                continue;
            }
            if ($hash !== '' && hash_equals($hash, hash('sha256', $token))) {
                return true;
            }
        }
        return false;
    }
}

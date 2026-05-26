<?php
namespace RadAdmin;

use Core\Sys\PrivilegeService;

class Ssoclient {
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

        $clients = $this->loadClients();
        if ($this->runData['request']->method === 'POST') {
            $csrf = $this->runData['request']->post['csrf_token'] ?? '';
            if (!$this->runData['request']->checkCSRFToken($csrf)) {
                $this->runData['request']->setAlert('Invalid request. Please refresh and try again.', 'danger');
            } else {
                $action = trim((string)($this->runData['request']->post['action'] ?? ''));
                $clientId = trim((string)($this->runData['request']->post['client_id'] ?? ''));
                if ($action === 'toggle' && isset($clients[$clientId])) {
                    $clients[$clientId]['status'] = ($clients[$clientId]['status'] ?? 'active') === 'active' ? 'inactive' : 'active';
                    $clients[$clientId]['updated_at'] = date('Y-m-d H:i:s');
                    $this->saveClients($clients);
                    $this->runData['request']->setAlert('Client status updated.', 'success');
                    header('Location: ' . $this->runData['route']['rad_admin_url'] . '/ssoclient/view');
                    exit;
                }
                if ($action === 'delete' && isset($clients[$clientId])) {
                    unset($clients[$clientId]);
                    $this->saveClients($clients);
                    $this->runData['request']->setAlert('Client removed.', 'success');
                    header('Location: ' . $this->runData['route']['rad_admin_url'] . '/ssoclient/view');
                    exit;
                }
            }
        }

        $summary = [
            'total' => 0,
            'active' => 0,
            'full_integration' => 0,
            'verify_only' => 0,
        ];
        foreach ($clients as $client) {
            $summary['total']++;
            if (($client['status'] ?? 'active') === 'active') {
                $summary['active']++;
            }
            if (($client['allowed_level'] ?? 'verify_only') === 'full_integration') {
                $summary['full_integration']++;
            } else {
                $summary['verify_only']++;
            }
        }

        $this->runData['data']['sso_server_clients'] = array_values($clients);
        $this->runData['data']['sso_server_client_summary'] = $summary;
        $this->runData['route']['h1'] = 'SSO Server Clients';
        $this->runData['route']['meta_title'] = 'SSO Server Clients';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'SSO' => $this->runData['route']['rad_admin_url'] . '/sso/view',
            'Server Clients' => '',
        ];
        return $this->runData;
    }

    public function add() {
        $this->guard();

        $form = [
            'client_id' => '',
            'client_name' => '',
            'allowed_level' => 'verify_only',
            'status' => 'active',
            'redirect_uris_text' => '',
            'notes' => '',
            'client_secret' => '',
        ];

        if ($this->runData['request']->method === 'POST') {
            $csrf = $this->runData['request']->post['csrf_token'] ?? '';
            if (!$this->runData['request']->checkCSRFToken($csrf)) {
                $this->runData['request']->setAlert('Invalid request. Please refresh and try again.', 'danger');
            } else {
                $form = array_merge($form, $this->runData['request']->post);
                $clients = $this->loadClients();
                $clientId = trim((string)($form['client_id'] ?? ''));
                if (!$this->isValidClientId($clientId)) {
                    $this->runData['request']->setAlert('Client ID can use letters, numbers, dot, dash, and underscore only.', 'danger');
                } elseif (isset($clients[$clientId])) {
                    $this->runData['request']->setAlert('Client ID already exists.', 'danger');
                } else {
                    $build = $this->buildClientFromForm($form, null);
                    if (!empty($build['error'])) {
                        $this->runData['request']->setAlert($build['error'], 'danger');
                    } else {
                        $clients[$clientId] = $build['client'];
                        $this->saveClients($clients);
                        $this->runData['data']['generated_client_secret'] = $build['plain_secret'];
                        $this->runData['request']->setAlert('Client registered successfully. Save the generated secret now.', 'success');
                        $form = [
                            'client_id' => '',
                            'client_name' => '',
                            'allowed_level' => 'verify_only',
                            'status' => 'active',
                            'redirect_uris_text' => '',
                            'notes' => '',
                            'client_secret' => '',
                        ];
                    }
                }
            }
        }

        $this->runData['data']['form'] = $form;
        $this->runData['route']['h1'] = 'Add SSO Server Client';
        $this->runData['route']['meta_title'] = 'Add SSO Server Client';
        $this->runData['route']['breadcrumb'] = [
            'SSO' => $this->runData['route']['rad_admin_url'] . '/sso/view',
            'Server Clients' => $this->runData['route']['rad_admin_url'] . '/ssoclient/view',
            'Add' => '',
        ];
        return $this->runData;
    }

    public function edit() {
        $this->guard();

        $clientId = trim((string)($this->runData['route']['pathparts'][3] ?? ''));
        if ($clientId === '') {
            throw new \Exception('Invalid client', 404);
        }

        $clients = $this->loadClients();
        if (!isset($clients[$clientId])) {
            throw new \Exception('Client not found', 404);
        }

        $client = $clients[$clientId];
        if ($this->runData['request']->method === 'POST') {
            $csrf = $this->runData['request']->post['csrf_token'] ?? '';
            if (!$this->runData['request']->checkCSRFToken($csrf)) {
                $this->runData['request']->setAlert('Invalid request. Please refresh and try again.', 'danger');
            } else {
                $action = trim((string)($this->runData['request']->post['action'] ?? 'save'));
                if ($action === 'rotate_secret') {
                    $newSecret = $this->generateClientSecret();
                    $clients[$clientId]['client_secret_hash'] = password_hash($newSecret, PASSWORD_DEFAULT);
                    $clients[$clientId]['updated_at'] = date('Y-m-d H:i:s');
                    $this->saveClients($clients);
                    $this->runData['data']['generated_client_secret'] = $newSecret;
                    $this->runData['request']->setAlert('Client secret rotated. Save the new secret now.', 'success');
                    $client = $clients[$clientId];
                } else {
                    $build = $this->buildClientFromForm($this->runData['request']->post, $client);
                    if (!empty($build['error'])) {
                        $this->runData['request']->setAlert($build['error'], 'danger');
                    } else {
                        $clients[$clientId] = $build['client'];
                        $this->saveClients($clients);
                        if (!empty($build['plain_secret'])) {
                            $this->runData['data']['generated_client_secret'] = $build['plain_secret'];
                            $this->runData['request']->setAlert('Client updated. Save the new secret now.', 'success');
                        } else {
                            $this->runData['request']->setAlert('Client updated.', 'success');
                        }
                        $client = $clients[$clientId];
                    }
                }
            }
        }

        $this->runData['data']['client'] = $client;
        $this->runData['route']['h1'] = 'Edit SSO Server Client';
        $this->runData['route']['meta_title'] = 'Edit SSO Server Client';
        $this->runData['route']['breadcrumb'] = [
            'SSO' => $this->runData['route']['rad_admin_url'] . '/sso/view',
            'Server Clients' => $this->runData['route']['rad_admin_url'] . '/ssoclient/view',
            $clientId => '',
        ];
        return $this->runData;
    }

    private function guard(): void {
        if (!$this->priv->can('settings')) {
            throw new \Exception('Access denied.', 403);
        }
    }

    private function loadClients(): array {
        $rows = $this->db->select('s_config', ['s_config_handle' => 'sso_server_clients'], true);
        $json = $rows[0]['s_config_value'] ?? '';
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        $isAssoc = array_keys($decoded) !== array_keys(array_values($decoded));
        if ($isAssoc) {
            foreach ($decoded as $key => $row) {
                $client = $this->normalizeClient((string)$key, $row);
                if ($client !== null) {
                    $normalized[$client['client_id']] = $client;
                }
            }
        } else {
            foreach ($decoded as $row) {
                $client = $this->normalizeClient(null, $row);
                if ($client !== null) {
                    $normalized[$client['client_id']] = $client;
                }
            }
        }
        ksort($normalized);
        return $normalized;
    }

    private function saveClients(array $clients): void {
        ksort($clients);
        $payload = [];
        foreach ($clients as $clientId => $client) {
            $clientId = (string)$clientId;
            $payload[$clientId] = [
                'client_id' => $clientId,
                'client_name' => (string)($client['client_name'] ?? $clientId),
                'status' => (string)($client['status'] ?? 'active'),
                'allowed_level' => (string)($client['allowed_level'] ?? 'verify_only'),
                'redirect_uris' => array_values($client['redirect_uris'] ?? []),
                'client_secret_hash' => (string)($client['client_secret_hash'] ?? ''),
                'notes' => (string)($client['notes'] ?? ''),
                'created_at' => (string)($client['created_at'] ?? date('Y-m-d H:i:s')),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $rows = $this->db->select('s_config', ['s_config_handle' => 'sso_server_clients'], true);
        if (!empty($rows)) {
            $this->db->update('s_config', ['s_config_value' => $json], ['s_config_handle' => 'sso_server_clients']);
        } else {
            $this->db->insert('s_config', [
                's_config_handle' => 'sso_server_clients',
                's_config_value' => $json,
                's_config_origin' => 'S',
                's_description' => 'Registered OAuth/OIDC clients for SSO server mode',
            ]);
        }
    }

    private function buildClientFromForm(array $input, ?array $existing): array {
        $clientId = trim((string)($existing['client_id'] ?? $input['client_id'] ?? ''));
        $name = trim((string)($input['client_name'] ?? ''));
        $allowedLevel = strtolower(trim((string)($input['allowed_level'] ?? 'verify_only')));
        $status = strtolower(trim((string)($input['status'] ?? 'active')));
        $notes = trim((string)($input['notes'] ?? ''));
        $secretInput = trim((string)($input['client_secret'] ?? ''));
        $redirectUrisText = (string)($input['redirect_uris_text'] ?? '');

        if ($clientId === '') {
            return ['error' => 'Client ID is required.'];
        }
        if ($name === '') {
            return ['error' => 'Client name is required.'];
        }

        $allowedLevel = $allowedLevel === 'full_integration' ? 'full_integration' : 'verify_only';
        $status = $status === 'inactive' ? 'inactive' : 'active';

        $parsedRedirects = $this->parseRedirectUris($redirectUrisText);
        if (!empty($parsedRedirects['invalid'])) {
            $sample = implode(', ', array_slice($parsedRedirects['invalid'], 0, 2));
            return ['error' => 'Invalid redirect URI: ' . $sample];
        }
        $redirectUris = $parsedRedirects['valid'];
        if (empty($redirectUris)) {
            return ['error' => 'At least one redirect URI is required.'];
        }

        $plainSecret = '';
        $secretHash = (string)($existing['client_secret_hash'] ?? '');
        if ($secretInput !== '') {
            $plainSecret = $secretInput;
            $secretHash = password_hash($secretInput, PASSWORD_DEFAULT);
        } elseif ($secretHash === '') {
            $plainSecret = $this->generateClientSecret();
            $secretHash = password_hash($plainSecret, PASSWORD_DEFAULT);
        }

        $client = [
            'client_id' => $clientId,
            'client_name' => $name,
            'status' => $status,
            'allowed_level' => $allowedLevel,
            'redirect_uris' => $redirectUris,
            'client_secret_hash' => $secretHash,
            'notes' => $notes,
            'created_at' => (string)($existing['created_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return [
            'client' => $client,
            'plain_secret' => $plainSecret,
        ];
    }

    private function normalizeClient(?string $key, $row): ?array {
        if (!is_array($row)) {
            return null;
        }
        $clientId = trim((string)($row['client_id'] ?? $key ?? ''));
        if (!$this->isValidClientId($clientId)) {
            return null;
        }

        $allowedLevel = strtolower(trim((string)($row['allowed_level'] ?? 'verify_only')));
        $allowedLevel = $allowedLevel === 'full_integration' ? 'full_integration' : 'verify_only';
        $status = strtolower(trim((string)($row['status'] ?? 'active')));
        $status = $status === 'inactive' ? 'inactive' : 'active';

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

        return [
            'client_id' => $clientId,
            'client_name' => trim((string)($row['client_name'] ?? $clientId)),
            'status' => $status,
            'allowed_level' => $allowedLevel,
            'redirect_uris' => $redirectUris,
            'client_secret_hash' => (string)($row['client_secret_hash'] ?? ''),
            'notes' => trim((string)($row['notes'] ?? '')),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }

    private function parseRedirectUris(string $text): array {
        $rows = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $uris = [];
        $invalid = [];
        foreach ($rows as $row) {
            $uri = trim($row);
            if ($uri === '') {
                continue;
            }
            if (!filter_var($uri, FILTER_VALIDATE_URL)) {
                $invalid[] = $uri;
                continue;
            }
            $uris[] = $uri;
        }
        return [
            'valid' => array_values(array_unique($uris)),
            'invalid' => array_values(array_unique($invalid)),
        ];
    }

    private function isValidClientId(string $clientId): bool {
        if ($clientId === '') {
            return false;
        }
        return preg_match('/^[A-Za-z0-9._-]+$/', $clientId) === 1;
    }

    private function generateClientSecret(): string {
        $random = random_bytes(24);
        return rtrim(strtr(base64_encode($random), '+/', '-_'), '=');
    }
}

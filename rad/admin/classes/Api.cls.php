<?php
namespace RadAdmin;

use DateTime;

class Api {
    private $runData = [];
    private $errorHandler;
    private ?array $apiGatewayConfig = null;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->errorHandler = $runData['errorHandler'];
    }

    private function getApiGatewayConfig(): array {
        if ($this->apiGatewayConfig !== null) {
            return $this->apiGatewayConfig;
        }
        $config = $this->runData['config']['sys']['api_gateway'] ?? [];
        $config['default_api_types'] = $config['default_api_types'] ?? ['application'];
        $config['system_tables'] = $config['system_tables'] ?? [];
        $config['system_services'] = $config['system_services'] ?? [];
        $this->apiGatewayConfig = $config;
        return $this->apiGatewayConfig;
    }

    private function normalizeApiTypes($input): array {
        $types = [];
        if (is_array($input)) {
            foreach ($input as $value) {
                $value = strtolower(trim((string)$value));
                if (in_array($value, ['application', 'system'], true)) {
                    $types[] = $value;
                }
            }
        }
        if (empty($types)) {
            $types = $this->getApiGatewayConfig()['default_api_types'];
        }
        return array_values(array_unique($types));
    }

    private function sanitizeSelection($values, array $allowed): array {
        if (!is_array($values) || empty($values)) {
            return [];
        }
        $allowedMap = array_flip($allowed);
        $selected = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '' && isset($allowedMap[$value])) {
                $selected[] = $value;
            }
        }
        return array_values(array_unique($selected));
    }

    public function view() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('api_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $this->runData['data']['can_idm_manage'] = $priv->can('api_manage');
        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Here you can manage the APIs.';
        }
        $this->runData['route']['h1'] = 'Manage API Keys';
        $this->runData['route']['meta_title'] = 'Manage API Keys';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/apiendpoint/view';
        $this->runData['route']['breadcrumb'] = [
            'API' => $this->runData['route']['rad_admin_url'] . '/apiendpoint/view',
        ];
        $apis = $this->runData['db']->select('s_entity', ['s_type' => 'A', 'livestatus' => '1'], true);
        $preparedApis = [];
        foreach ($apis as $api) {
            $authInfo = $this->buildApiAuthInfo($api);
            $api['auth_info'] = $authInfo;
            $api['api_types'] = $authInfo['api_types'] ?? $this->getApiGatewayConfig()['default_api_types'];
            $preparedApis[] = $api;
        }
        $this->runData['data']['apis'] = $preparedApis;
        $this->runData['data']['api_gateway'] = $this->getApiGatewayConfig();
        return $this->runData;
    }

    public function viewone() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('api_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'API not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/api/view';
            header("Location: $redirectUrl"); exit;
        }
    
        $uid = $this->runData['route']['pathparts'][3];
        $apiRows = $this->runData['db']->select('s_entity', ['uid' => $uid, 's_type' => 'A'], true);
        if (count($apiRows) != 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'API not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/api/view';
            header("Location: $redirectUrl"); exit;
        }
    
        $this->runData['data']['api'] = $apiRows[0];
        $this->runData['route']['h1'] = 'View API - ' . $this->runData['data']['api']['s_name'];
        $this->runData['route']['meta_title'] = 'View API';
    
        return $this->runData;
    }

    public function add() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('api_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $gatewayConfig = $this->getApiGatewayConfig();
        if (isset($this->runData['request']->post['s_name']) && ($this->runData['request']->post['s_name'] != '')) {
            $s_name = $this->runData['request']->post['s_name'];
            $s_secret = $this->runData['request']->post['s_secret'];
            $s_access_ips = $this->runData['request']->post['s_access_ips'];
            $apiTypes = $this->normalizeApiTypes($this->runData['request']->post['api_types'] ?? null);
            $systemTables = $this->sanitizeSelection($this->runData['request']->post['system_tables'] ?? [], $gatewayConfig['system_tables']);
            $serviceKeys = array_map(function ($service) {
                return $service['key'] ?? null;
            }, $gatewayConfig['system_services']);
            $serviceKeys = array_filter($serviceKeys);
            $systemServices = $this->sanitizeSelection($this->runData['request']->post['system_services'] ?? [], $serviceKeys);

            // Auto-generate a unique API identity
            do {
                $s_identity = $this->generateUniqueApiIdentity();
                $api = $this->runData['db']->select('s_entity', ['s_identity' => $s_identity, 's_type' => 'A'], false);
            } while (count($api) > 0); // Ensure uniqueness
    
            if ($s_name == '' || $s_secret == '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Name and secret are mandatory fields.';
                return $this->runData;
            }
    
            // Validate IP addresses
            if ($s_access_ips != '' && !$this->validateIPAddresses($s_access_ips)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid IP address format. Please ensure they are comma-separated and correctly formatted.';
                return $this->runData;
            }
    
            // Insert into s_entity table
            $iNewEntityId = $this->runData['db']->insert('s_entity', [
                's_type' => 'A',
                's_name' => $s_name,
                's_identity' => $s_identity,
                's_identity_secret' => password_hash($s_secret, PASSWORD_DEFAULT),
                's_access_ips' => $s_access_ips,
                's_api_types' => json_encode($apiTypes),
                's_api_system_tables' => json_encode($systemTables),
                's_api_system_services' => json_encode($systemServices),
                's_definition' => json_encode([]),
            ]);
    
            if (!$iNewEntityId) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'There was an error adding the API.';
                return $this->runData;
            }
    
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'API added successfully!';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/api/view';
            header("Location: $redirectUrl");
            exit;
        } else {
            if (!$this->runData['route']['alert_from_request']) {
                $this->runData['route']['alert'] = 'info';
                $this->runData['route']['alert_message'] = 'You may add a new API here.';
            }
            $this->runData['route']['h1'] = 'Add API Key';
            $this->runData['route']['meta_title'] = 'Add API Key';
            $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/api/view';
            $this->runData['route']['breadcrumb'] = [
                'API' => $this->runData['route']['rad_admin_url'] . '/apiendpoint/view',
                'Manage API Keys' => $this->runData['route']['rad_admin_url'] . '/api/view',
            ];
            $this->runData['data']['api'] = [];
            $this->runData['data']['api_gateway'] = $gatewayConfig;
            $this->runData['data']['selected_api_types'] = $gatewayConfig['default_api_types'];
            $this->runData['data']['selected_system_tables'] = [];
            $this->runData['data']['selected_system_services'] = [];
            return $this->runData;
        }
    }
    
    private function generateUniqueApiIdentity() {
        // Generates a random string for API identity
        return 'api_' . bin2hex(random_bytes(8)); // Example: api_ab12cd34ef56gh78
    }
    
    private function validateIPAddresses($ips) {
        $ipArray = explode(',', $ips);
        foreach ($ipArray as $ip) {
            if (!filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                return false;
            }
        }
        return true;
    }        

    public function edit() {
        $gatewayConfig = $this->getApiGatewayConfig();
        if (isset($this->runData['request']->post['s_name']) && ($this->runData['request']->post['s_name'] != '')) {
            $s_name = $this->runData['request']->post['s_name'];
            $s_secret = $this->runData['request']->post['s_secret'];
            $s_access_ips = $this->runData['request']->post['s_access_ips'];
            $apiTypes = $this->normalizeApiTypes($this->runData['request']->post['api_types'] ?? null);
            $systemTables = $this->sanitizeSelection($this->runData['request']->post['system_tables'] ?? [], $gatewayConfig['system_tables']);
            $serviceKeys = array_map(static function ($service) {
                return $service['key'] ?? null;
            }, $gatewayConfig['system_services']);
            $serviceKeys = array_values(array_filter($serviceKeys));
            $systemServices = $this->sanitizeSelection($this->runData['request']->post['system_services'] ?? [], $serviceKeys);
    
            // Validate IP addresses if provided
            if ($s_access_ips !== '') {
                $ips = explode(',', $s_access_ips);
                foreach ($ips as $ip) {
                    if (!filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Invalid IP address: ' . $ip;
                        return $this->runData;
                    }
                }
            }
    
            // Update the API details in s_entity table
            $updateData = [
                's_name' => $s_name,
                's_access_ips' => $s_access_ips,
                's_api_types' => json_encode($apiTypes),
                's_api_system_tables' => json_encode($systemTables),
                's_api_system_services' => json_encode($systemServices),
            ];
    
            // Update secret only if provided
            if ($s_secret !== '') {
                $updateData['s_identity_secret'] = password_hash($s_secret, PASSWORD_DEFAULT);
            }
    
            $updateWhere = ['uid' => $this->runData['route']['pathparts'][3]];
            $updated = $this->runData['db']->update('s_entity', $updateData, $updateWhere);
    
            if (!$updated) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'There was an error updating the API.';
                return $this->runData;
            }
    
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'API updated successfully!';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/api/view';
            header("Location: $redirectUrl");
            exit;
        } else {
            // Fetch API details and display the form
            $uid = $this->runData['route']['pathparts'][3];
            $apiRows = $this->runData['db']->select('s_entity', ['uid' => $uid], true);
            if (count($apiRows) != 1) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'API not found.';
                return $this->runData;
            }
    
            $apiRow = $apiRows[0];
            $authInfo = $this->buildApiAuthInfo($apiRow);
            $this->runData['data']['api'] = $apiRow;
            $this->runData['data']['api_gateway'] = $gatewayConfig;
            $this->runData['data']['selected_api_types'] = $authInfo['api_types'] ?? $gatewayConfig['default_api_types'];
            $this->runData['data']['selected_system_tables'] = $authInfo['system_tables'] ?? [];
            $this->runData['data']['selected_system_services'] = $authInfo['system_services'] ?? [];
            $this->runData['route']['h1'] = 'Edit API Key - ' . $this->runData['data']['api']['s_name'];
            $this->runData['route']['meta_title'] = 'Edit API Key';
            $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/api/view';
            $this->runData['route']['breadcrumb'] = [
                'API' => $this->runData['route']['rad_admin_url'] . '/apiendpoint/view',
                'Manage API Keys' => $this->runData['route']['rad_admin_url'] . '/api/view',
            ];

            return $this->runData;
        }
    }        

    public function archive() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('api_manage') || $priv->role() === 'developer') {
            throw new \Exception('Access denied.', 403);
        }
        $uid = $this->runData['route']['pathparts'][3];
        $api = $this->runData['db']->select('s_entity', ['uid' => $uid, 's_type' => 'A'], false);

        if (count($api) != 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'API not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/api/view';
            header("Location: $redirectUrl"); exit;
        }

        $this->runData['db']->update('s_entity', ['livestatus' => '0'], ['uid' => $uid, 's_type' => 'A']);
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'API <strong>' . $api[0]['s_name'] . '</strong> has been archived successfully.';
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        $redirectUrl = $this->runData['route']['rad_admin_url'] . '/api/view';
        header("Location: $redirectUrl"); exit;
    }

    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function buildApiAuthInfo(array $api): array {
        return [
            'access_ips' => $api['s_access_ips'] ?? '',
            'api_types' => $this->decodeJsonField($api['s_api_types'] ?? null),
            'system_tables' => $this->decodeJsonField($api['s_api_system_tables'] ?? null),
            'system_services' => $this->decodeJsonField($api['s_api_system_services'] ?? null),
            'allowed_endpoints' => $this->decodeJsonField($api['s_api_allowed_endpoints'] ?? null),
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
}

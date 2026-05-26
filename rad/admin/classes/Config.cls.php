<?php
namespace RadAdmin;
use DateTime;
use Core\Sys\PrivilegeService;
class Config{
    private $runData = [];
    private $db;
    private $errorHandler;
    private PrivilegeService $priv;
    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        $this->priv = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }
    public function view() {
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Configuration Parameters are displayed below. You can add new application parameters only. System parameters can only be edited by clicking on the edit button. The directory parameters are not editable. You can copy the parameter names by clicking on the copy button.';
        }

        $this->runData['route']['h1'] = 'Configuration Parameters';
        // Select config parameters from s_config table
        // if there is no additional route pathparts > 3, then select all config parameters of type 'A' - application parameters
        // print '<pre>';print_r($this->runData['route']['pathparts']);print '</pre>';die('here');
        if (!isset($this->runData['route']['pathparts'][3]) || $this->runData['route']['pathparts'][3] == '') {
            $this->runData['data']['config_origin'] = 'A';
            $this->runData['data']['configParams'] = $this->runData['db']->select('s_config', ['s_config_origin' => 'A'], true, ['s_config_handle' => 'ASC']);
        }
        // if there is an additional route pathparts > 3, then if it is 'S' - system parameters, select all config parameters of type 'S'. If it is 'D', display all directory parameters
        elseif ($this->runData['route']['pathparts'][3] == 'S') {
            $this->runData['data']['config_origin'] = 'S';
            $this->runData['data']['configParams'] = $this->runData['db']->select('s_config', ['s_config_origin' => 'S'], true, ['s_config_handle' => 'ASC']);
        }
        elseif ($this->runData['route']['pathparts'][3] == 'D') {
            $this->runData['data']['config_origin'] = 'D';
            // manually assign directory parameters to runData['data'] array
            $this->runData['data']['configParams'] = [
                [
                    's_config_handle' => 'site',
                    's_config_value' => $this->runData['config']['dir']['site'],
                    's_description' => 'The root directory of the Site - the container of the application'
                ],
                [
                    's_config_handle' => 'rad',
                    's_config_value' => $this->runData['config']['dir']['rad'],
                    's_description' => 'The root directory of the RAD framework'
                ],
                [
                    's_config_handle' => 'log',
                    's_config_value' => $this->runData['config']['dir']['log'],
                    's_description' => 'The directory where the log files are stored'
                ],
                [
                    's_config_handle' => 'session',
                    's_config_value' => $this->runData['config']['dir']['session'],
                    's_description' => 'The directory where the session files are stored'
                ],
                // [
                //     's_config_handle' => 'data',
                //     's_config_value' => $this->runData['config']['dir']['data'],
                //     's_description' => 'The directory where the data files are stored'
                // ],
                [
                    's_config_handle' => 'data_uploads',
                    's_config_value' => $this->runData['config']['dir']['data'].'/uploads',
                    's_description' => 'The directory where the user uploads files are stored'
                ],
                [
                    's_config_handle' => 'data_temp',
                    's_config_value' => $this->runData['config']['dir']['data'].'/temp',
                    's_description' => 'The directory where the temporary data files are stored'
                ],
                // [
                //     's_config_handle' => 'Core\Sys',
                //     's_config_value' => $this->runData['config']['dir']['core'].'/sys',
                //     's_description' => 'The directory where the core system classes are stored'
                // ],
                // [
                //     's_config_handle' => 'Core\App',
                //     's_config_value' => $this->runData['config']['dir']['core'].'/app',
                //     's_description' => 'The directory where the core classes for developers are stored'
                // ],
                [
                    's_config_handle' => 'ms',
                    's_config_value' => $this->runData['config']['dir']['ms'],
                    's_description' => 'The directory where the microservices directories and their files are stored'
                ],
                [
                    's_config_handle' => 'theme',
                    's_config_value' => $this->runData['config']['dir']['theme'],
                    's_description' => 'The directory where the theme files are stored'
                ],
                [
                    's_config_handle' => 'vendor',
                    's_config_value' => $this->runData['config']['dir']['vendor'],
                    's_description' => 'The directory where the vendor files are stored'
                ],
                [
                    's_config_handle' => 'www',
                    's_config_value' => $this->runData['config']['dir']['www'],
                    's_description' => 'The directory where the www files are stored. The folder or directory where the domain name is pointed to; e.g., it is [site]/rad/public_html in cPanel based servers.'
                ],
                [
                    's_config_handle' => 'assets',
                    's_config_value' => $this->runData['config']['dir']['assets'],
                    's_description' => 'The directory where the UI asset files are stored'
                ],
                [
                    's_config_handle' => 'pub',
                    's_config_value' => $this->runData['config']['dir']['pub'],
                    's_description' => 'The directory where the pub files are stored'
                ]
            ];
            // sort the array $this->runData['data']['configParams'] by s_config_handle in ascending order
            usort($this->runData['data']['configParams'], function($a, $b) {
                return $a['s_config_handle'] <=> $b['s_config_handle'];
            });
        }

        $this->runData['data']['configStats'] = $this->summarizeParameters(
            $this->runData['data']['configParams'],
            $this->runData['data']['config_origin']
        );

        return $this->runData;
    }
    
    /**
     * Add a Config Parameter
     */
    public function add() {
        // add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'The following form is meant to add an Application Parameter.';
        $this->runData['route']['h1'] = 'Add Application Parameter';
        // print '<pre>';print_r($this->runData['request']->post);print '</pre>';die('here');
        // Check if the form has been submitted from the same URL
        if (isset($this->runData['request']->post['s_config_handle'])) {
            // remove any spaces and special characters from the s_config_handle except underscore (_)
            $this->runData['request']->post['s_config_handle'] = str_replace(' ', '', $this->runData['request']->post['s_config_handle']);
            $this->runData['request']->post['s_config_handle'] = preg_replace('/[^A-Za-z0-9\_]/', '', $this->runData['request']->post['s_config_handle']);
            // Check if the form fields are not empty
            if (empty($this->runData['request']->post['s_config_handle']) || empty($this->runData['request']->post['s_config_value'])) {
                // Add alert and alert_message to runData - information to be displayed to the user
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Parameter Name and Value cannot be empty.';
            }
            else {
                // Check if the s_config_handle is not blank and is not the same as the existing s_config_handle
                $configParam = $this->runData['db']->select('s_config', ['s_config_handle' => $this->runData['request']->post['s_config_handle']], true);
                // print '<pre>';print_r($configParam);print_r($this->runData['request']->post);print '</pre>';die('here - configParam found.');
                if (count($configParam) == 1) {
                    // Add alert and alert_message to runData - information to be displayed to the user
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Application Parameter already exists.';
                }
                else {
                    // Insert the config parameter into the s_config table
                    $newConfigParamId = $this->runData['db']->insert('s_config', [
                        's_config_handle' => $this->runData['request']->post['s_config_handle'],
                        's_config_value' => $this->runData['request']->post['s_config_value'],
                        's_config_origin' => 'A',
                        's_description' => $this->runData['request']->post['s_description']
                    ]);
                    if ($newConfigParamId == 0) {
                        // Add alert and alert_message to runData - information to be displayed to the user
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Application Parameter could not be added.';
                    }
                    else {
                        // Add alert and alert_message to runData - information to be displayed to the user
                        $this->runData['route']['alert'] = 'success';
                        $this->runData['route']['alert_message'] = 'Application Parameter <code>'. $this->runData['request']->post['s_config_handle'] .'</code> added successfully.';
                        // Register alert into cookie
                        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                        // Redirect to the config parameter listing page
                        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/config/view';
                        header("Location: {$redirectUrl}");exit;
                    }
                }
            }
        }
        // print '<pre>';print_r($this->runData['request']);print '</pre>';die('here');
        return $this->runData;
    }

    public function systemtables() {
        $this->runData['route']['h1'] = 'System Tables Inventory';
        $this->runData['route']['meta_title'] = 'System Tables Inventory';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'System Tables' => '',
        ];
        $inventory = $this->listSystemTables();
        $this->runData['data']['system_table_inventory'] = $inventory;
        $this->runData['data']['total_present'] = count(array_filter($inventory, function ($row) {
            return !empty($row['present']);
        }));
        $this->runData['data']['total_missing'] = count(array_filter($inventory, function ($row) {
            return empty($row['present']);
        }));
        return $this->runData;
    }

    public function mfa() {
        $redirectUrl = $this->runData['route']['rad_admin_url'] . '/mfa/dashboard';
        header("Location: {$redirectUrl}");
        exit;
    }

    private function listSystemTables(): array {
        $tables = [
            's_config' => 'Config parameters',
            's_content' => 'Content blocks',
            's_data_field' => 'Data controller fields',
            's_data_field_group' => 'Field groups',
            's_data_field_type' => 'Field type definitions',
            's_data_method' => 'Data methods',
            's_entity' => 'Entities (users/apps)',
            's_entity_session' => 'Entity sessions',
            's_external_notification' => 'External notifications (legacy)',
            's_invalid_table' => 'Invalid/legacy placeholder',
            's_ms' => 'Microservicelets',
            's_mscontroller' => 'Controllers (DM/BL)',
            's_msroute' => 'Routes',
            's_nav' => 'Nav items',
            's_navset' => 'Nav sets',
            's_notification' => 'Notifications',
            's_permission_binding' => 'Permission bindings',
            's_queue' => 'Queues',
            's_role' => 'Roles',
            's_space' => 'Spaces',
            's_space_membership' => 'Space memberships',
            's_sso_server_access_token' => 'SSO server access tokens',
            's_sso_server_auth_code' => 'SSO server auth codes',
            's_sso_provider' => 'SSO providers',
            's_telemetry_config' => 'Telemetry config',
            's_telemetry_event' => 'Telemetry events',
            's_telemetry_rollup' => 'Telemetry rollups',
            's_telemetry_token' => 'Telemetry tokens',
            's_vendor' => 'Vendor libraries',
            's_version_history' => 'Version history',
            's_wf_action' => 'Workflow actions',
            's_wf_state' => 'Workflow states',
        ];
        $fieldNotes = [
            's_config' => [
                's_config_handle' => 'Unique handle for config lookup.',
                's_config_value' => 'Stored config payload/value.',
            ],
            's_entity' => [
                's_type' => 'U=user, A=API.',
                's_nonsaas_role_id' => 'Primary non-SaaS role.',
                's_login_mode' => 'SE/BA/GL/TW login channel.',
                's_access_ips' => 'Comma-separated IP allowlist.',
            ],
            's_entity_session' => [
                's_entity_id' => 'Entity id for this session.',
                's_session_key' => 'Session token/key.',
                's_ip' => 'Client IP for the session.',
            ],
            's_ms' => [
                's_scope' => 'global/platform/workspace.',
                's_type' => 'STA/DYN/UID/ID.',
                'default_route_id' => 'Default landing route.',
            ],
            's_msroute' => [
                's_ms_id' => 'Owner microservice id.',
                's_path' => 'Route path or slug.',
            ],
            's_role' => [
                's_scope' => 'platform/workspace/ms.',
                's_default_route_id' => 'Default landing route.',
            ],
            's_space' => [
                's_slug' => 'Workspace slug.',
                's_owner_entity_id' => 'Owner entity id.',
            ],
            's_permission_binding' => [
                's_object_type' => 'ms or route.',
                's_object_id' => 'Target object id.',
                's_role_id' => 'Role that grants access.',
            ],
            's_space_membership' => [
                's_entity_id' => 'User entity id.',
                's_role_id' => 'Assigned SaaS role.',
                's_scope_level' => 'workspace or ms.',
                's_ms_id' => 'Microservice id for ms-scoped roles.',
            ],
            's_nav' => [
                's_navset_id' => 'Parent navset id.',
                's_parent_id' => 'Parent nav item id.',
            ],
            's_navset' => [
                's_ms_id' => 'Microservice id (optional).',
            ],
            's_notification' => [
                's_entity_id' => 'Recipient entity id.',
                's_message' => 'Notification text.',
            ],
            's_queue' => [
                's_queue_name' => 'Queue name/handle.',
                's_payload' => 'Queued payload.',
            ],
            's_version_history' => [
                's_object_type' => 'Table/route/template target.',
                's_object_id' => 'Target object id.',
                's_version' => 'Version number.',
            ],
            's_vendor' => [
                's_handle' => 'Library handle/slug.',
                's_path' => 'Filesystem path.',
            ],
        ];
        $presentMap = [];
        try {
            $rows = $this->db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()");
            foreach ($rows as $row) {
                $presentMap[strtolower($row['table_name'])] = true;
            }
        } catch (\Throwable $e) {
            $presentMap = [];
        }
        $columnsByTable = [];
        if (!empty($presentMap)) {
            $placeholders = [];
            $params = [];
            $idx = 0;
            foreach ($tables as $name => $desc) {
                if (!isset($presentMap[strtolower($name)])) {
                    continue;
                }
                $key = ':t' . $idx++;
                $placeholders[] = $key;
                $params[$key] = $name;
            }
            if ($placeholders) {
                $sql = "SELECT table_name,
                               column_name,
                               data_type,
                               is_nullable,
                               column_default,
                               column_key,
                               column_comment,
                               ordinal_position
                        FROM information_schema.columns
                        WHERE table_schema = DATABASE()
                          AND table_name IN (" . implode(',', $placeholders) . ")
                        ORDER BY table_name, ordinal_position";
                try {
                    $cols = $this->db->query($sql, $params);
                    foreach ($cols as $col) {
                        $tname = $col['table_name'] ?? '';
                        if ($tname === '') {
                            continue;
                        }
                        $columnsByTable[$tname][] = [
                            'name' => $col['column_name'] ?? '',
                            'type' => $col['data_type'] ?? '',
                            'nullable' => ($col['is_nullable'] ?? '') === 'YES',
                            'default' => $col['column_default'] ?? null,
                            'key' => $col['column_key'] ?? '',
                            'comment' => $col['column_comment'] ?? '',
                        ];
                    }
                } catch (\Throwable $e) {
                    $columnsByTable = [];
                }
            }
        }
        $inventory = [];
        foreach ($tables as $name => $desc) {
            $fields = $columnsByTable[$name] ?? [];
            foreach ($fields as &$field) {
                if ($field['comment'] === '' && isset($fieldNotes[$name][$field['name']])) {
                    $field['comment'] = $fieldNotes[$name][$field['name']];
                }
            }
            unset($field);
            $inventory[] = [
                'name' => $name,
                'description' => $desc,
                'present' => isset($presentMap[strtolower($name)]),
                'fields' => $fields,
            ];
        }
        return $inventory;
    }

    /**
     * Edit a Config Parameter
     */
    public function edit() {
        $isSystemParam = false;
        // find the parameter from the s_config table with the uid from the pathparts array 3rd element
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Config Parameter', 404);
        }
        $configRow = $this->runData['db']->select('s_config', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($configRow) != 1) {
            throw new \Exception('Invalid Config Parameter', 404);
        }
        $isSystemParam = ($configRow[0]['s_config_origin'] ?? '') === 'S';
        if ($isSystemParam && $this->priv->role() !== 'system_admin') {
            throw new \Exception('Access denied', 403);
        }
        // print '<pre>';print_r($configRow);print '</pre>';die('here');
        // add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'The following form is meant to edit the parameter.';
        $this->runData['route']['h1'] = 'Edit Parameter - <code>'.$configRow[0]['s_config_handle'].'</code>';
        // Check if the form has been submitted from the same URL
        if (isset($this->runData['request']->post['config_id'])) {
            // remove any spaces and special characters from the s_config_handle except underscore (_)
            $this->runData['request']->post['s_config_handle'] = str_replace(' ', '', $this->runData['request']->post['s_config_handle']);
            $this->runData['request']->post['s_config_handle'] = preg_replace('/[^A-Za-z0-9\_]/', '', $this->runData['request']->post['s_config_handle']);
            // Check if the form fields are not empty
            if (empty($this->runData['request']->post['s_config_handle']) || empty($this->runData['request']->post['s_config_value'])) {
                // Add alert and alert_message to runData - information to be displayed to the user
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Parameter Name and Value cannot be empty.';
            }
            else {
                // Check if the s_config_handle is not blank and is not the same as the existing s_config_handle except for the current config_id
                $configParam = $this->runData['db']->select('s_config', ['s_config_handle' => $this->runData['request']->post['s_config_handle']], true);
                // print '<pre>';print_r($configParam);print_r($this->runData['request']->post);print '</pre>';die('here - configParam found.');
                if (count($configParam) == 1 && $configParam[0]['id'] != $this->runData['request']->post['config_id']) {
                    // Add alert and alert_message to runData - information to be displayed to the user
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Application Parameter already exists.';
                }
                else {
                    if ($isSystemParam && $this->priv->role() !== 'system_admin') {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'System parameters can only be edited by system administrators.';
                        return $this->runData;
                    }
                    // Update the config parameter into the s_config table. If the config parameter is of type 'S' - system parameter, then the s_config_handle is readonly so skip its update
                    if ($configRow[0]['s_config_origin'] == 'S') {
                        $this->runData['request']->post['s_config_handle'] = $configRow[0]['s_config_handle'];
                    }
                    $this->runData['db']->update('s_config', [
                        's_config_handle' => $this->runData['request']->post['s_config_handle'],
                        's_config_value' => $this->runData['request']->post['s_config_value'],
                        's_description' => $this->runData['request']->post['s_description']
                    ], ['id' => $this->runData['request']->post['config_id']]);
                    // Add alert and alert_message to runData - information to be displayed to the user
                    $this->runData['route']['alert'] = 'success';
                    $this->runData['route']['alert_message'] = 'Application Parameter <code>'. $this->runData['request']->post['s_config_handle'] .'</code> edited successfully.';
                    // Register alert into cookie
                    $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                    // Redirect to the config parameter listing page
                    // if the config parameter is of type 'A' - application parameter url is /rad-admin/config/view and if it is of type 'S' - system parameter url is /rad-admin/config/view/S
                    if ($configRow[0]['s_config_origin'] == 'A') {
                        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/config/view';
                    }
                    elseif ($configRow[0]['s_config_origin'] == 'S') {
                        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/config/view/S';
                    }
                    header("Location: {$redirectUrl}");exit;
                }
            }
        }
        $this->runData['data']['config'] = $configRow[0];
        return $this->runData;
    }

    /**
     * Archive a Config Parameter - only application parameters can be archived
     */
    public function archive() {
        if (!$this->priv->can('settings')) {
            throw new \Exception('Access denied', 403);
        }
        if ($this->priv->role() === 'developer') {
            throw new \Exception('Access denied', 403);
        }
        // check if the config parameter exists from the s_config table and with route id from the pathparts array 3rd element
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Config Parameter', 404);
        }
        $configRow = $this->runData['db']->select('s_config', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($configRow) != 1) {
            throw new \Exception('Invalid Config Parameter', 404);
        }
        if (($configRow[0]['s_config_origin'] ?? '') === 'S' && $this->priv->role() !== 'system_admin') {
            throw new \Exception('Access denied', 403);
        }
        // print '<pre>';print_r($configRow);print '</pre>';die('here');
        // check if the config parameter is of type 'A' - application parameter
        if ($configRow[0]['s_config_origin'] != 'A') {
            throw new \Exception('Invalid Config Parameter', 404);
        }
        // Archive the Config Parameter
        $this->runData['db']->update('s_config', ['livestatus' => '2'], ['id' => $configRow[0]['id']]);
        // Add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Config Parameter <code>'. $configRow[0]['s_config_handle'] .'</code> archived successfully.';
        // Register alert into cookie
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        // Redirect to the Config Parameter listing page
        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/config/view';
        header("Location: {$redirectUrl}");exit;
    }

    /**
     * Activate a Microservice
     */
    public function activate() {
        // check if the config parameter exists from the s_config table and with route id from the pathparts array 3rd element
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Config Parameter', 404);
        }
        $configRow = $this->runData['db']->select('s_config', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($configRow) != 1) {
            throw new \Exception('Invalid Config Parameter', 404);
        }
        // print '<pre>';print_r($configRow);print '</pre>';die('here');
        // check if the config parameter is of type 'A' - application parameter
        if ($configRow[0]['s_config_origin'] != 'A') {
            throw new \Exception('Invalid Config Parameter', 404);
        }
        // Activate the Config Parameter
        $this->runData['db']->update('s_config', ['livestatus' => '1'], ['id' => $configRow[0]['id']]);
        // Add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Config Parameter <code>'. $configRow[0]['s_config_handle'] .'</code> activated successfully.';
        // Register alert into cookie
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        // Redirect to the Config Parameter listing page
        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/config/view';
        header("Location: {$redirectUrl}");exit;
    }

    public function history() {
        $reference = $this->runData['route']['pathparts'][3] ?? '';
        if ($reference === '') {
            $this->renderHistoryResponse(['error' => 'Config reference missing.'], 404);
            return;
        }

        $record = $this->locateConfigRecord($reference);
        if (!$record) {
            $this->renderHistoryResponse(['error' => 'Config parameter not found.'], 404);
            return;
        }

        $entries = $this->fetchHistoryEntries('s_config', (int)$record['id']);
        $this->renderHistoryResponse(['entries' => $entries]);
    }

    private function renderHistoryResponse(array $payload, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    private function summarizeParameters(array $params, string $origin): array {
        $summary = [
            'total' => count($params),
            'active' => 0,
            'archived' => 0,
            'inactive' => 0,
            'last_updated' => null,
            'origin' => $origin,
        ];

        if ($origin === 'D') {
            $summary['active'] = $summary['total'];
            return $summary;
        }

        $latest = null;
        foreach ($params as $param) {
            $status = $param['livestatus'] ?? null;
            if ($status === '1') {
                $summary['active']++;
            } elseif ($status === '2') {
                $summary['archived']++;
            } else {
                $summary['inactive']++;
            }

            $stamp = $param['updatestamp'] ?? $param['createstamp'] ?? null;
            if ($stamp && (!$latest || strtotime($stamp) > strtotime($latest))) {
                $latest = $stamp;
            }
        }

        if ($latest) {
            $dt = new DateTime($latest);
            $summary['last_updated'] = $dt->format('M d, Y H:i');
        }

        return $summary;
    }

    private function locateConfigRecord(string $reference): ?array {
        $criteria = ctype_digit($reference) ? ['id' => $reference] : ['uid' => $reference];
        try {
            $rows = $this->runData['db']->select('s_config', $criteria, true);
            if (count($rows) === 1) {
                return $rows[0];
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    private function fetchHistoryEntries(string $table, int $recordId): array {
        try {
            $entries = $this->runData['db']->select(
                's_version_history',
                ['s_db_table' => $table, 's_data_record_id' => $recordId],
                true,
                ['id' => 'DESC'],
                8
            );
        } catch (\Throwable $e) {
            return [];
        }

        foreach ($entries as &$entry) {
            $entry['modifier_label'] = $this->resolveUserName($entry['s_modified_by'] ?? 0);
            $entry['snapshot'] = $this->decodeSnapshot($entry['s_data_record_dump'] ?? null);
            if (!empty($entry['s_modified_timestamp'])) {
                $dt = new DateTime($entry['s_modified_timestamp']);
                $entry['s_modified_human'] = $dt->format('M d, Y H:i');
            }
        }

        return $entries;
    }

    private function resolveUserName($userId): string {
        $userId = (int)$userId;
        if ($userId === 0) {
            return 'System';
        }
        try {
            $rows = $this->runData['db']->select('s_user', ['id' => $userId], true);
        } catch (\Throwable $e) {
            return 'User #' . $userId;
        }
        if (!empty($rows)) {
            $name = trim(($rows[0]['s_first_name'] ?? '') . ' ' . ($rows[0]['s_last_name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
            if (!empty($rows[0]['s_display_name'])) {
                return $rows[0]['s_display_name'];
            }
        }
        return 'User #' . $userId;
    }

    private function decodeSnapshot(?string $payload): string {
        if (!$payload) {
            return '';
        }
        $decoded = @base64_decode($payload);
        if ($decoded === false) {
            return '';
        }
        $inflated = @gzuncompress($decoded);
        if ($inflated === false) {
            return '';
        }
        $data = @unserialize($inflated);
        if (is_array($data) && isset($data[0])) {
            $data = $data[0];
        }
        if (!is_array($data)) {
            return '';
        }
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

}

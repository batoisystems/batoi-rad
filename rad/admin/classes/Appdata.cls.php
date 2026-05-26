<?php
namespace RadAdmin;
use DateTime;
use Core\Sys\DataSchemaService;
class Appdata{
    private $runData = [];
    private $db;
    private $errorHandler;
    private $fieldTypeMap = [];
    private $schemaService;
    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }
    public function view() {
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'The following table is the list of application data tables.';
        }

        $filters = [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
            'status' => trim((string)($this->runData['request']->get['status'] ?? '')),
            'scope' => trim((string)($this->runData['request']->get['scope'] ?? '')),
            'quick' => trim((string)($this->runData['request']->get['quick'] ?? '')),
        ];

        $this->runData['route']['h1'] = 'Application Data';
        // Controllers (raw + filtered for visibility)
        $allControllers = $this->runData['db']->select('s_mscontroller', ['s_type' => 'DM'], true);
        $this->runData['data']['appdata'] = $this->filterControllersByMs($allControllers);
        $msMap = [];
        $msRowsAll = $this->runData['db']->select('s_ms', [], true);
        foreach ($msRowsAll as $row) {
            $msMap[(int)$row['id']] = $row;
        }
        foreach ($this->runData['data']['appdata'] as $key => $ms) {
            if ((int)($ms['s_ms_id'] ?? 0) === 0) {
                $this->runData['data']['appdata'][$key]['s_ms_name'] = 'Global';
                $this->runData['data']['appdata'][$key]['s_ms_uid'] = '';
                $this->runData['data']['appdata'][$key]['s_ms_id'] = 0;
                continue;
            }
            $msRow = $msMap[(int)$ms['s_ms_id']] ?? null;
            if (!empty($msRow)) {
                $this->runData['data']['appdata'][$key]['s_ms_name'] = $msRow['s_name'];
                $this->runData['data']['appdata'][$key]['s_ms_uid'] = $msRow['uid'];
                $this->runData['data']['appdata'][$key]['s_ms_id'] = $msRow['id'];
            }
        }
        // Controllers hidden by visibility
        $visibleIds = array_map('intval', array_column($this->runData['data']['appdata'], 'id'));
        $hiddenControllers = [];
        foreach ($allControllers as $controller) {
            if (!in_array((int)$controller['id'], $visibleIds, true)) {
                $hid = (int)($controller['s_ms_id'] ?? 0);
                $controller['s_ms_name'] = $hid === 0 ? 'Global' : ($msMap[$hid]['s_name'] ?? 'Unknown');
                $controller['s_ms_uid'] = $hid === 0 ? '' : ($msMap[$hid]['uid'] ?? '');
                $controller['s_ms_id'] = $hid;
                $controller['s_table_name'] = 'a_' . ($controller['s_name'] ?? '');
                $hiddenControllers[] = $controller;
            }
        }
        // Orphan a_* tables (no controller)
        // Collect a_* tables using multiple strategies to avoid driver quirks
        $tables = [];
        $tablesResult = $this->runData['db']->query('SHOW TABLES LIKE \'a\_%\'');
        if (is_array($tablesResult)) {
            foreach ($tablesResult as $row) {
                $tables[] = reset($row);
            }
        }
        // Merge with db helper list
        if (method_exists($this->runData['db'], 'getTables')) {
            $tables = array_merge($tables, (array)$this->runData['db']->getTables('a'));
        }
        $tables = array_values(array_unique(array_filter($tables)));
        $controllersByTable = [];
        foreach ($allControllers as $controller) {
            $controllersByTable['a_' . $controller['s_name']] = true;
        }
        $orphanTables = [];
        foreach ($tables as $table) {
            if (!isset($controllersByTable[$table])) {
                $orphanTables[] = $table;
            }
        }
        $this->runData['data']['orphan_tables'] = $orphanTables;
        $orphanMap = array_fill_keys($orphanTables, true);
        $this->runData['data']['total_tables'] = count($tables);
        $this->runData['data']['total_controllers'] = count($allControllers);
        $this->runData['data']['hidden_controllers'] = $hiddenControllers;
        $this->runData['data']['all_tables'] = $tables;
        $this->runData['data']['all_controllers_raw'] = $allControllers;
        $this->runData['data']['sync_status'] = $this->getLatestSyncStatus();
        foreach ($this->runData['data']['appdata'] as $key => $row) {
            $tableName = 'a_' . ($row['s_name'] ?? '');
            $this->runData['data']['appdata'][$key]['s_table_name'] = $tableName;
            $this->runData['data']['appdata'][$key]['table_exists'] = in_array($tableName, $tables, true);
            $this->runData['data']['appdata'][$key]['is_orphan_table'] = isset($orphanMap[$tableName]);
        }

        $orphanRows = [];
        foreach ($orphanTables as $table) {
            $name = preg_replace('/^a_/', '', $table);
            $orphanRows[] = [
                'id' => 0,
                'uid' => '',
                's_name' => $name,
                's_table_name' => $table,
                's_description' => 'Orphan table (no data model registered).',
                'livestatus' => null,
                's_ms_name' => 'Unassigned',
                's_ms_uid' => '',
                's_ms_id' => 0,
                'table_exists' => true,
                'is_orphan_table' => true,
            ];
        }

        $listMode = 'normal';
        if ($filters['quick'] === 'global') {
            $filters['scope'] = 'global';
        } elseif ($filters['quick'] === 'hidden') {
            $listMode = 'hidden';
            $this->runData['data']['appdata'] = $hiddenControllers;
        } elseif ($filters['quick'] === 'orphan') {
            $listMode = 'orphan';
            $this->runData['data']['appdata'] = $orphanRows;
        }

        // Apply filters to appdata
        $this->runData['data']['appdata'] = array_values(array_filter($this->runData['data']['appdata'], function ($row) use ($filters, $listMode) {
            if ($listMode === 'normal') {
                if ($filters['status'] !== '' && (string)($row['livestatus'] ?? '') !== $filters['status']) {
                    return false;
                }
                if ($filters['scope'] !== '' && strtolower($row['scope_slug'] ?? '') !== strtolower($filters['scope'])) {
                    return false;
                }
                if ($filters['quick'] === 'missing' && !empty($row['table_exists'])) {
                    return false;
                }
            }
            if ($filters['q'] !== '') {
                $needle = strtolower($filters['q']);
                $blob = strtolower(
                    ($row['s_ms_name'] ?? '') . ' ' .
                    ($row['s_name'] ?? '') . ' ' .
                    ($row['s_description'] ?? '') . ' ' .
                    ($row['s_table_name'] ?? '')
                );
                if (strpos($blob, $needle) === false) {
                    return false;
                }
            }
            return true;
        }));

        if ($listMode === 'hidden') {
            foreach ($this->runData['data']['appdata'] as $key => $row) {
                $tableName = $row['s_table_name'] ?? ('a_' . ($row['s_name'] ?? ''));
                $this->runData['data']['appdata'][$key]['table_exists'] = in_array($tableName, $tables, true);
                $this->runData['data']['appdata'][$key]['is_orphan_table'] = isset($orphanMap[$tableName]);
                $this->runData['data']['appdata'][$key]['s_table_name'] = $tableName;
            }
        }

        $this->runData['data']['list_mode'] = $listMode;
        $this->runData['data']['filters'] = $filters;
        return $this->runData;
    }

    public function listSystemTables(): array {
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
            's_queue' => 'Queue',
            's_role' => 'Roles',
            's_space' => 'Spaces',
            's_space_membership' => 'Space memberships',
            's_sso_provider' => 'SSO providers',
            's_telemetry_config' => 'Telemetry config',
            's_telemetry_event' => 'Telemetry events',
            's_telemetry_rollup' => 'Telemetry rollups',
            's_telemetry_token' => 'Telemetry tokens',
            's_vendor' => 'Vendors',
            's_version_history' => 'Version history',
            's_wf_action' => 'Workflow actions',
            's_wf_state' => 'Workflow states',
        ];
        $rows = $this->runData['db']->query('SHOW TABLES');
        $present = [];
        foreach ($rows as $row) {
            $name = reset($row);
            $present[$name] = true;
        }
        $inventory = [];
        foreach ($tables as $name => $desc) {
            $inventory[] = [
                'name' => $name,
                'description' => $desc,
                'present' => isset($present[$name]),
                'status' => $this->classifySystemTable($name),
            ];
        }
        return $inventory;
    }

    private function classifySystemTable(string $name): string {
        $legacy = ['s_invalid_table', 's_external_notification'];
        if (in_array($name, $legacy, true)) {
            return 'legacy';
        }
        return 'active';
    }

    private function getProfilePerPage(int $fallback): int {
        $definition = $this->loadEntityDefinition();
        $prefs = $definition['profile_prefs'] ?? [];
        $perPage = (int)($prefs['per_page'] ?? 0);
        return $this->isAllowedPerPage($perPage) ? $perPage : $fallback;
    }

    private function loadEntityDefinition(): array {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return [];
        }
        $rows = $this->runData['db']->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return [];
        }
        $raw = $rows[0]['s_definition'] ?? '';
        if (empty($raw)) {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function isAllowedPerPage(int $perPage): bool {
        return in_array($perPage, [10, 25, 50, 100, 200], true);
    }
    
    /**
     * Add a application data table
     */
    public function add() {
        $scope = strtolower($this->runData['request']->get['scope'] ?? '');
        if ($scope !== 'global') {
            $scope = 'scoped';
        }
        // Check if the form has been submitted from the same URL
        if (isset($this->runData['request']->post['s_name'])) {
            // print '<pre>';print_r($this->runData['request']->post);print '</pre>';die('here');
            // $this->runData['request']->post['s_name'] must be converted into a string without spaces and special characters. spaces to be replaced with dash (-)
            $this->runData['request']->post['s_name'] = strtolower($this->runData['request']->post['s_name']);
            $this->runData['request']->post['s_name'] = str_replace(' ', '', $this->runData['request']->post['s_name']);
            $this->runData['request']->post['s_name'] = str_replace('-', '', $this->runData['request']->post['s_name']);
            $this->runData['request']->post['s_name'] = preg_replace('/[^A-Za-z0-9\-]/', '', $this->runData['request']->post['s_name']);
            // print '<pre>';print_r($this->runData['request']->post);print '</pre>';die('here');
            // Check if the form fields are not empty
            $msId = $scope === 'global' ? 0 : (int)($this->runData['request']->post['s_ms_id'] ?? 0);
            if ($msId <= 0) {
                $msRows = [];
            } else {
                $msRows = $this->runData['db']->select('s_ms', ['id' => $msId], true);
            }
            if ($msId > 0 && empty($msRows)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid microservicelet selected.';
                return $this->runData;
            }

            $controllerName = $this->runData['request']->post['s_name'];
            $existingController = $this->runData['db']->select('s_mscontroller', ['s_name' => $controllerName], true);
            if (!empty($existingController)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'A data controller with this name already exists.';
                return $this->runData;
            }

            $newMSControllerId = $this->runData['db']->insert('s_mscontroller', [
                's_ms_id' => $msId,
                's_name' => $controllerName,
                's_description' => $this->runData['request']->post['s_description'],
                's_type' => 'DM',
                's_definition' => json_encode([])
            ]);
            if ($newMSControllerId == 0) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Data controller could not be added.';
                return $this->runData;
            }

            // Always create backing table
            $tableName = 'a_' . $controllerName;
            $sql = sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` ( `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
                $tableName
            );
            try {
                $this->runData['db']->query($sql);
            } catch (\Throwable $e) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Controller created, but table creation failed: ' . $e->getMessage();
                return $this->runData;
            }

            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = $msId === 0 ? 'Global data controller created.' : 'Scoped data controller created.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/appdata/view';
            header("Location: {$redirectUrl}");exit;
        } else {
            $this->runData['data']['microservices'] = $this->filterRestrictedMs(
                $this->runData['db']->select('s_ms', [], true, ['s_name' => 'ASC'])
            );
            $this->runData['route']['alert'] = 'info';
            if ($scope === 'global') {
                $this->runData['route']['alert_message'] = 'Create a global data controller (s_ms_id = 0).';
                $this->runData['route']['h1'] = 'Create Global Data Controller';
            } else {
                $this->runData['route']['alert_message'] = 'Create a data controller scoped to an existing microservicelet.';
                $this->runData['route']['h1'] = 'Create Scoped Data Controller';
            }
        }
        // print '<pre>';print_r($this->runData['request']);print '</pre>';die('here');
        return $this->runData;
    }

    /**
     * Create a Global Data Controller (s_ms_id = 0)
     */
    public function addglobal() {
        $this->runData['route']['h1'] = 'Create Global Data Controller';
        $this->runData['route']['meta_title'] = 'Create Global Data Controller';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/appdata/view';
        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Global data controllers are not tied to any microservicelet (s_ms_id = 0).';
        }

        if (isset($this->runData['request']->post['s_name'])) {
            $name = strtolower(trim($this->runData['request']->post['s_name']));
            $name = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
            if ($name === '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Controller name is required.';
                return $this->runData;
            }
            $existing = $this->runData['db']->select('s_mscontroller', ['s_name' => $name], true);
            if (!empty($existing)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'A controller with this name already exists.';
                return $this->runData;
            }

            $definition = $this->runData['request']->post['s_definition'] ?? '';
            if ($definition === '') {
                $definition = '{}';
            }
            $json = json_decode($definition, true);
            if ($definition !== '' && $json === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid JSON definition.';
                return $this->runData;
            }

            $desc = trim($this->runData['request']->post['s_description'] ?? '');
            $createTable = isset($this->runData['request']->post['create_table']) && $this->runData['request']->post['create_table'] === 'Y';

            $controllerId = $this->runData['db']->insert('s_mscontroller', [
                's_ms_id' => 0,
                's_name' => $name,
                's_description' => $desc,
                's_type' => 'DM',
                's_definition' => json_encode($json ?? []),
            ]);
            if (!$controllerId) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Unable to create data controller.';
                return $this->runData;
            }

            // Always create backing table
            $tableName = 'a_' . $name;
            $sql = sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` ( `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
                $tableName
            );
            try {
                $this->runData['db']->query($sql);
            } catch (\Throwable $e) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Controller created, but table creation failed: ' . $e->getMessage();
                return $this->runData;
            }

            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'Global data controller created.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/appdata/view');
            exit;
        }

        return $this->runData;
    }

    /**
     * Import a database table as a MSController
     */
    public function import() {
        // get the table name from the 3rd element of the pathparts array
        $tableName = $this->runData['route']['pathparts'][3];
        // print $tableName;die('here');
        // Check if the table exists in the database
        $dbTables = $this->runData['db']->getTables('a');
        // print '<pre>';print_r($dbTables);print '</pre>';die('here');
        if (!in_array($tableName, $dbTables)) {
            // Add alert and alert_message to runData - information to be displayed to the user
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Table does not exist.';
            // Register alert into cookie
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to the Microservice listing page
            $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/appdata/view';
            header("Location: {$redirectUrl}");exit;
        }
        // Check if the table exists in the s_mscontroller table
        // Remove a_ from the table name
        $tableName = substr($tableName, 2);
        $msControllers = $this->runData['db']->select('s_mscontroller', ['s_name' => $tableName], true);
        // print '<pre>';print_r($msControllers);print '</pre>';die('here');
        if (count($msControllers) == 1) {
            // Add alert and alert_message to runData - information to be displayed to the user
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Table already exists as Application Data Table.';
            // Register alert into cookie
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to the Microservice listing page
            $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/appdata/view';
            header("Location: {$redirectUrl}");exit;
        }
        // Insert the table into s_mscontroller table and the columns into s_data_field table
        // Get the columns of the table
        $tableColumns = $this->runData['db']->getColumns($tableName);
        // print '<pre>';print_r($tableColumns);print '</pre>';die('here');
        // Get the s_ms_id value from the fourth element of the pathparts array
        $msUid = $this->runData['route']['pathparts'][4];
        // Get the id from the s_ms table
        $ms = $this->runData['db']->select('s_ms', ['uid' => $msUid], true);
        // print '<pre>';print_r($ms);print '</pre>';die('here');
        if (empty($ms) || $this->isRestrictedMs((int)($ms[0]['id'] ?? 0))) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'You do not have access to this Microservicelet.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/appdata/view';
            header("Location: {$redirectUrl}");exit;
        }
        $msId = (int)$ms[0]['id'];
        // Insert the table into s_mscontroller table
        $newMSControllerId = $this->runData['db']->insert('s_mscontroller', [
            's_ms_id' => $msId,
            's_name' => $tableName,
            's_description' => $tableName,
            's_type' => 'DM',
            's_definition' => json_encode([])
        ]);
        // print '<pre>';print_r($newMSControllerId);print '</pre>';die('here');
        if ($newMSControllerId == 0) {
            // Add alert and alert_message to runData - information to be displayed to the user
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Application Data Table could not be added.';
            // Register alert into cookie
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to the Microservice listing page
            $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/appdata/view';
            header("Location: {$redirectUrl}");exit;
        }
        // Insert the columns into s_data_field table
        foreach ($tableColumns as $tableColumn) {
            // print '<pre>';print_r($tableColumn);print '</pre>';die('here');
            $this->runData['db']->insert('s_data_field', [
                's_mscontroller_id' => $newMSControllerId,
                's_field_name' => $tableColumn['Field'],
                's_field_label' => $tableColumn['Field'],
                's_data_type' => $tableColumn['Type'],
                's_is_nullable' => $tableColumn['Null'] === 'YES' ? true : false,
                's_constraints' => json_encode([]),  // You can populate this JSON as per your need
                's_range_settings' => json_encode([]),  // You can populate this JSON as per your need
                's_data_enc' => 'N'
            ]);
        }
        // Add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Application Data Table added successfully.';
        // Register alert into cookie
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        // Redirect to the appdata listing page
        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/appdata/view';
        header("Location: {$redirectUrl}");exit;
    }

    /**
     * Edit a Microservice
     */
    public function edit() {
        // print '<pre>';print_r($this->runData['request']->post);print '</pre>';die('here - post loaded');
        // add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'The following form is meant to edit a Microservicelet.';
        $this->runData['route']['h1'] = 'Edit Microservicelet';
        // print '<pre>';print_r($this->runData['request']->post);print '</pre>';die('here');
        // Check if the form has been submitted from the same URL
        if (isset($this->runData['request']->post['ms_id'])) {
            // print '<pre>';print_r($this->runData['request']->post);print '</pre>';die('here');
            // $this->runData['request']->post['s_name'] must be converted into a string without spaces and special characters. spaces to be replaced with dash (-)
            $this->runData['request']->post['s_name'] = strtolower($this->runData['request']->post['s_name']);
            $this->runData['request']->post['s_name'] = str_replace(' ', '', $this->runData['request']->post['s_name']);
            $this->runData['request']->post['s_name'] = str_replace('-', '', $this->runData['request']->post['s_name']);
            $this->runData['request']->post['s_name'] = preg_replace('/[^A-Za-z0-9\-]/', '', $this->runData['request']->post['s_name']);
            // print '<pre>';print_r($this->runData['request']->post);print '</pre>';die('here');
            // Check if the s_name is not blank and is not the same as the existing s_name except for the current ms_id
            $ms = $this->runData['db']->select('s_ms', ['s_name' => $this->runData['request']->post['s_name']], true);
            // print '<pre>';print_r($ms);print_r($this->runData['request']->post);print '</pre>';die('here - MS found.');
            if (count($ms) == 1 && $ms[0]['id'] != $this->runData['request']->post['ms_id']) {
                // Add alert and alert_message to runData - information to be displayed to the user
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Microservicelet already exists.';
            }
            else {
                $this->runData['db']->update('s_ms', [
                    's_name' => $this->runData['request']->post['s_name'],
                    's_description' => $this->runData['request']->post['s_description'],
                    's_type' => $this->runData['request']->post['s_type'],
                    's_scope' => $this->runData['request']->post['s_scope'] ?? 'platform',
                    's_tpl_name' => $this->runData['request']->post['s_tpl_name']
                ], ['id' => $this->runData['request']->post['ms_id']]);
                // Add alert and alert_message to runData - information to be displayed to the user
                $this->runData['route']['alert'] = 'success';
                $this->runData['route']['alert_message'] = 'Microservicelet <strong>'.$this->runData['request']->post['s_name'].'</strong> edited successfully.';
                // Register alert into cookie
                $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                // Redirect to the Microservice listing page
                $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/microservice/view';
                header("Location: {$redirectUrl}");exit;
            }
        }
        // Get the Microservice details from s_ms table
        $msRow = $this->runData['db']->select('s_ms', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($msRow) != 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        if ($this->isRestrictedMs((int)$msRow[0]['id'])) {
            throw new \Exception('Access denied for this Microservicelet', 403);
        }
        $this->runData['data']['ms'] = $msRow[0];
        return $this->runData;
    }

    /**
     * Archive a Microservice
     */
    public function archive() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() === 'developer' || !$priv->can('microservice_edit')) {
            throw new \Exception('Access denied for this action.', 403);
        }
        // check if the Microservice exists from the s_ms table and with route id from the pathparts array 3rd element
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $msRow = $this->runData['db']->select('s_ms', ['id' => $this->runData['route']['pathparts'][3]], true);
        if (count($msRow) != 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        if ($this->isRestrictedMs((int)$msRow[0]['id'])) {
            throw new \Exception('Access denied for this Microservicelet', 403);
        }
        // print '<pre>';print_r($msRow);print '</pre>';die('here');
        // Archive the Microservice
        $this->runData['db']->update('s_ms', ['livestatus' => '2'], ['id' => $msRow[0]['id']]);
        // Add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Microservicelet archived successfully.';
        // Register alert into cookie
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        // Redirect to the Microservice listing page
        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/microservice/view';
        header("Location: {$redirectUrl}");exit;
    }

    /**
     * Activate a Microservice
     */
    public function activate() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() === 'developer' || !$priv->can('microservice_edit')) {
            throw new \Exception('Access denied for this action.', 403);
        }
        // check if the Microservice exists from the s_ms table and with route id from the pathparts array 3rd element
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $msRow = $this->runData['db']->select('s_ms', ['id' => $this->runData['route']['pathparts'][3]], true);
        if (count($msRow) != 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        if ($this->isRestrictedMs((int)$msRow[0]['id'])) {
            throw new \Exception('Access denied for this Microservicelet', 403);
        }
        // print '<pre>';print_r($msRow);print '</pre>';die('here');
        // Archive the Microservice
        $this->runData['db']->update('s_ms', ['livestatus' => '1'], ['id' => $msRow[0]['id']]);
        // Add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Microservicelet activated successfully.';
        // Register alert into cookie
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        // Redirect to the Microservice listing page
        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/microservice/view';
        header("Location: {$redirectUrl}");exit;
    }

    /**
     * Execute SQL
     */
    public function executesql() {
        // Set up initial runData values for the view with validation guidelines
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'This console works only with application data tables prefixed with "a_". Dropping tables, listing schema metadata, or touching protected system tables is blocked by design.';
        $this->runData['route']['h1'] = 'Execute SQL';
        $this->runData['route']['meta_title'] = 'Execute SQL';
        $this->runData['data']['per_page_pref'] = $this->getProfilePerPage(50);

        $this->purgeSqlResults();

        $resultToken = $this->runData['request']->get['result_token'] ?? '';
        $payload = $resultToken ? $this->loadSqlResult($resultToken) : null;
        if (empty($payload) && !empty($_SESSION['appdata_exec_result'])) {
            $payload = $_SESSION['appdata_exec_result'];
            unset($_SESSION['appdata_exec_result']);
        }
        if (!empty($payload)) {
            if (!empty($payload['summary'])) {
                $this->runData['route']['sql_summary'] = $payload['summary'];
            }
            if (array_key_exists('message', $payload)) {
                $this->runData['route']['sql_message'] = $payload['message'];
            }
            if (array_key_exists('table', $payload)) {
                $this->runData['route']['sql_table_html'] = $payload['table'];
            }
            if (array_key_exists('debug', $payload)) {
                $this->runData['route']['sql_debug'] = $payload['debug'];
            }
            if (!empty($payload['alert']) && is_array($payload['alert'])) {
                $this->runData['route']['alert'] = $payload['alert']['type'] ?? 'info';
                $this->runData['route']['alert_message'] = $payload['alert']['message'] ?? '';
            }
        } elseif (!empty($resultToken)) {
            $this->runData['route']['alert'] = 'warning';
            $this->runData['route']['alert_message'] = 'SQL result payload expired or missing. Please run the query again.';
        }
    
        // Check if a SQL query has been submitted
        $sqlInput = $this->runData['request']->post['sql_query'] ?? ($_POST['sql_query'] ?? null);
        $sqlB64 = $this->runData['request']->post['sql_query_b64'] ?? ($_POST['sql_query_b64'] ?? null);
        if (($sqlInput === null || $sqlInput === '') && is_string($sqlB64) && $sqlB64 !== '') {
            $decoded = base64_decode($sqlB64, true);
            if ($decoded !== false) {
                $sqlInput = $decoded;
            }
        }
        $debugEnabled = $this->isDebugEnabled();
        $debug = [
            'method' => $this->runData['request']->method ?? '',
            'post_seen' => $sqlInput !== null,
            'post_len' => is_string($sqlInput) ? strlen($sqlInput) : 0,
            'token_present' => $resultToken !== '',
            'session_payload' => !empty($_SESSION['appdata_exec_result']),
            'b64_present' => is_string($sqlB64) && $sqlB64 !== '',
            'content_length' => $_SERVER['CONTENT_LENGTH'] ?? null,
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
            'post_keys' => array_keys($_POST ?? []),
            'request_post_keys' => array_keys($this->runData['request']->post ?? []),
        ];
        if ($sqlInput !== null) {
            $csrfToken = $this->runData['request']->post['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
            $csrfOk = $this->runData['request']->checkCSRFToken($csrfToken);
            if ($debugEnabled) {
                $debug['csrf_present'] = $csrfToken !== '';
                $debug['csrf_ok'] = $csrfOk;
            }
            if (!$csrfOk) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Missing or invalid CSRF token. Please refresh and try again.';
                $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                $_SESSION['appdata_exec_result'] = [
                    'summary' => [],
                    'message' => '',
                    'table' => '',
                    'alert' => [
                        'type' => $this->runData['route']['alert'],
                        'message' => $this->runData['route']['alert_message'],
                    ],
                ];
                if ($debugEnabled) {
                    $_SESSION['appdata_exec_result']['debug'] = $debug;
                }
                $token = $this->storeSqlResult($_SESSION['appdata_exec_result']);
                $redirectUrl = $this->runData['route']['url'] ?? ($this->runData['route']['rad_admin_url'] . '/appdata/executesql');
                if ($token) {
                    $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . 'result_token=' . urlencode($token);
                }
                header('Location: ' . $redirectUrl);
                exit;
            }
            $sqlQuery = $this->restoreRawSql($sqlInput);
            if ($debugEnabled) {
                $debug['query_type'] = strtoupper(strtok(ltrim($sqlQuery), " \n\t")) ?: 'UNKNOWN';
                $debug['query_snippet'] = substr(preg_replace('/\s+/', ' ', $sqlQuery), 0, 120);
            }
            $queryType = strtoupper(strtok(ltrim($sqlQuery), " \n\t"));
            $queryType = $queryType ?: 'UNKNOWN';
            $summary = [
                'query' => $sqlQuery,
                'type' => $queryType,
                'status' => 'pending',
                'executed_at' => date('Y-m-d H:i:s'),
                'duration_ms' => null,
                'rows' => null,
            ];
            $this->runData['route']['sql_summary'] = $summary;
    
            // Validate table names and field names in the SQL query
            $validationResult = $this->validateSQL($sqlQuery);
            if ($validationResult === true) {
                try {
                    $startedAt = microtime(true);
                    // Execute the SQL query
                    $result = $this->runData['db']->query($sqlQuery);
                    $durationMs = round((microtime(true) - $startedAt) * 1000, 2);
                    $summary['duration_ms'] = $durationMs;
                    $summary['status'] = 'success';
                    $message = '';
                    $tableHtml = '';
    
                    // Provide user-friendly output based on the type of query
                    if (stripos($sqlQuery, 'select') === 0) {
                        if (is_array($result) && count($result) > 0) {
                            $summary['rows'] = count($result);
                            $message = 'Query executed successfully. Retrieved ' . count($result) . ' record(s).';
                            $tableHtml = $this->formatSelectResults($result);
                        } else {
                            $summary['rows'] = 0;
                            $message = 'Query executed successfully. No records found.';
                        }
                    } elseif (stripos($sqlQuery, 'insert') === 0) {
                        $summary['rows'] = 1;
                        $message = 'Query executed successfully. Record inserted.';
                    } elseif (stripos($sqlQuery, 'update') === 0) {
                        $affectedRows = is_numeric($result) ? (int)$result : 0;
                        $summary['rows'] = $affectedRows;
                        $message = 'Query executed successfully. ' . $affectedRows . ' record(s) updated.';
                    } elseif (stripos($sqlQuery, 'delete') === 0) {
                        $affectedRows = is_numeric($result) ? (int)$result : 0;
                        $summary['rows'] = $affectedRows;
                        $message = 'Query executed successfully. ' . $affectedRows . ' record(s) deleted.';
                    } elseif (stripos($sqlQuery, 'create') === 0 || stripos($sqlQuery, 'alter') === 0 || stripos($sqlQuery, 'drop') === 0) {
                        $message = 'Schema modification query executed successfully.';
                    } else {
                        $message = 'Query executed successfully.';
                    }
                    $this->runData['route']['sql_message'] = $message;
                    $this->runData['route']['sql_table_html'] = $tableHtml;
                    $this->runData['route']['sql_result'] = $tableHtml ? $message . $tableHtml : $message;
                    $this->runData['route']['sql_summary'] = $summary;
                    if ($debugEnabled) {
                        $debug['exec_result_type'] = is_array($result) ? 'array' : gettype($result);
                        $debug['exec_duration_ms'] = $durationMs;
                    }
                } catch (\Exception $e) {
                    // Handle SQL errors
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Error executing SQL: ' . $e->getMessage();
                    $summary['status'] = 'error';
                    $summary['error'] = $e->getMessage();
                    $this->runData['route']['sql_summary'] = $summary;
                    $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                    if ($debugEnabled) {
                        $debug['exec_error'] = $e->getMessage();
                    }
                }
            } else {
                // Validation failed, set error message with detailed validation feedback
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid SQL query: ' . $validationResult;
                $summary['status'] = 'error';
                $summary['error'] = $validationResult;
                $this->runData['route']['sql_summary'] = $summary;
                $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                if ($debugEnabled) {
                    $debug['validation_error'] = $validationResult;
                }
            }

            $_SESSION['appdata_exec_result'] = [
                'summary' => $this->runData['route']['sql_summary'] ?? [],
                'message' => $this->runData['route']['sql_message'] ?? '',
                'table' => $this->runData['route']['sql_table_html'] ?? '',
            ];
            if (!empty($this->runData['route']['alert_message'])) {
                $_SESSION['appdata_exec_result']['alert'] = [
                    'type' => $this->runData['route']['alert'] ?? 'info',
                    'message' => $this->runData['route']['alert_message'],
                ];
            }
            $token = $this->storeSqlResult($_SESSION['appdata_exec_result']);
            $redirectUrl = $this->runData['route']['url'] ?? ($this->runData['route']['rad_admin_url'] . '/appdata/executesql');
            if ($token) {
                $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . 'result_token=' . urlencode($token);
            }
            if ($debugEnabled) {
                $_SESSION['appdata_exec_result']['debug'] = $debug;
            }
            header('Location: ' . $redirectUrl);
            exit;
        }

        if ($debugEnabled && empty($this->runData['route']['sql_debug'])) {
            $this->runData['route']['sql_debug'] = $debug;
        }
    
        return $this->runData;
    }    
    
    /**
     * Format the SELECT query results into a nice HTML table.
     * @param array $result
     * @return string
     */
    private function formatSelectResults(array $result): string {
        if (empty($result)) {
            return '<p>No results found.</p>';
        }
    
        $output = '<table class="table table-bordered table-striped mt-3" id="sql-result-data-table">';
        $output .= '<thead><tr>';
    
        // Add table headers
        foreach (array_keys($result[0]) as $header) {
            $output .= '<th>' . htmlspecialchars($header) . '</th>';
        }
    
        $output .= '</tr></thead>';
        $output .= '<tbody>';
    
        // Add table rows
        foreach ($result as $row) {
            $output .= '<tr>';
            foreach ($row as $cell) {
                $output .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $output .= '</tr>';
        }
    
        $output .= '</tbody></table>';
    
        return $output;
    }        
    
    /**
     * Validate the SQL query to ensure it follows the naming conventions.
     * @param string $sqlQuery
     * @return bool|string
     */
    private function validateSQL($sqlQuery) {
        $sqlQuery = trim($sqlQuery);
        if ($sqlQuery === '') {
            return 'SQL query cannot be empty.';
        }
        // Convert to lowercase clone for pattern checks
        $normalized = strtolower($sqlQuery);

        if (preg_match('/\bshow\s+(full\s+)?tables\b/', $normalized) || preg_match('/\bshow\s+table\s+status\b/', $normalized) || strpos($normalized, 'information_schema.tables') !== false) {
            return 'Listing database tables is not permitted within this tool.';
        }

        if (preg_match('/\bdrop\s+table\b/', $normalized)) {
            return 'Dropping tables is not allowed from this console.';
        }

        if (preg_match('/\b(alter|create)\s+table\s+`?(s_[a-z0-9_]+)`?/', $normalized)) {
            return "Protected system tables cannot be created or altered here.";
        }

        if (preg_match('/\brename\s+table\s+`?(s_[a-z0-9_]+)`?/', $normalized) || preg_match('/\balter\s+table\s+`?(s_[a-z0-9_]+)`?\s+rename\b/', $normalized)) {
            return "Protected system tables cannot be renamed.";
        }

        if (preg_match('/\b(s_entity|s_entity_session)\b/', $normalized)) {
            return "Direct access to the RAD entity store is not permitted.";
        }
    
        // Define patterns for validation
        $tablePattern = '/\b(?:from|into|update|join|table|alter\s+table|create\s+table)\s+`?([a-z0-9_]+)`?/i';
        $fieldPattern = '/\b(?:add|change|drop|alter|modify)\s+column\s+`?([a-z0-9_]+)`?/i';
    
        // Check for table names
        if (preg_match_all($tablePattern, $normalized, $matches)) {
            foreach ($matches[1] as $tableName) {
                if (strpos($tableName, 'a_') !== 0) {
                    return "Table names must start with 'a_'. Invalid table name found: '$tableName'.";
                }
            }
        } else {
            return "No valid table name found in the query.";
        }
    
        // Check for field names in schema modifications
        if (preg_match_all($fieldPattern, $normalized, $matches)) {
            foreach ($matches[1] as $fieldName) {
                if (strpos($fieldName, 'a_') !== 0) {
                    return "Fields being added or removed must start with 'a_'. Invalid field name found: '$fieldName'.";
                }
            }
        }

        // If no issues, return true
        return true;
    }                                                         

    private function restoreRawSql($value) {
        if (!is_string($value)) {
            return '';
        }
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function isDebugEnabled(): bool {
        $rows = $this->runData['db']->select('s_config', ['s_config_handle' => 'dev_debug_flag'], true);
        if (empty($rows)) {
            return false;
        }
        $value = strtolower((string)($rows[0]['s_config_value'] ?? ''));
        return in_array($value, ['1', 'y', 'yes', 'true'], true);
    }

    private function storeSqlResult(array $payload): ?string {
        $dir = rtrim($this->runData['config']['dir']['rad'] ?? '', '/') . '/data/temp/sqlconsole';
        if ($dir === '/data/temp/sqlconsole') {
            return null;
        }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return null;
        }
        $token = bin2hex(random_bytes(16));
        $payload['_meta'] = [
            'created_at' => time(),
        ];
        $json = json_encode($payload);
        if ($json === false) {
            return null;
        }
        if (strlen($json) > 2 * 1024 * 1024) {
            return null;
        }
        $path = $dir . '/' . $token . '.json';
        if (@file_put_contents($path, $json) === false) {
            return null;
        }
        return $token;
    }

    private function loadSqlResult(string $token): ?array {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
        if ($token === '' || strlen($token) < 16) {
            return null;
        }
        $dir = rtrim($this->runData['config']['dir']['rad'] ?? '', '/') . '/data/temp/sqlconsole';
        $path = $dir . '/' . $token . '.json';
        if (!is_file($path)) {
            return null;
        }
        $json = file_get_contents($path);
        if ($json === false || $json === '') {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        $created = (int)($data['_meta']['created_at'] ?? 0);
        if ($created > 0 && (time() - $created) > 300) {
            @unlink($path);
            return null;
        }
        @unlink($path);
        return $data;
    }

    private function purgeSqlResults(int $maxAgeSeconds = 600): void {
        $dir = rtrim($this->runData['config']['dir']['rad'] ?? '', '/') . '/data/temp/sqlconsole';
        if (!is_dir($dir)) {
            return;
        }
        $cutoff = time() - max(60, $maxAgeSeconds);
        foreach (glob($dir . '/*.json') as $file) {
            if (!is_file($file)) {
                continue;
            }
            $mtime = filemtime($file);
            if ($mtime !== false && $mtime < $cutoff) {
                @unlink($file);
            }
        }
    }

    private function generateSyncReport(): array {
        $controllers = $this->filterControllersByMs(
            $this->db->select('s_mscontroller', ['s_type' => 'DM'], true, ['s_name' => 'ASC'])
        );
        $microservices = $this->filterRestrictedMs($this->db->select('s_ms', [], true));
        $microserviceMap = [];
        $microserviceMap[0] = ['s_name' => 'Global', 'id' => 0];
        foreach ($microservices as $ms) {
            $microserviceMap[$ms['id']] = $ms;
        }
        // Collect a_* tables using multiple strategies
        $tables = [];
        $tablesResult = $this->db->query('SHOW TABLES LIKE \'a\_%\'');
        if (is_array($tablesResult)) {
            foreach ($tablesResult as $row) {
                $tables[] = reset($row);
            }
        }
        if (method_exists($this->db, 'getTables')) {
            $tables = array_merge($tables, (array)$this->db->getTables('a'));
        }
        // Fallback to information_schema
        $infoRows = $this->db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'a\\_%'");
        if (is_array($infoRows)) {
            foreach ($infoRows as $row) {
                $tables[] = $row['table_name'] ?? '';
            }
        }
        $tables = array_values(array_unique(array_filter($tables)));
        $tablesLower = array_map('strtolower', $tables);
        $controllersByTable = [];
        foreach ($controllers as $controller) {
            $controllersByTable['a_' . $controller['s_name']] = $controller;
        }
        $missingTables = [];
        foreach ($controllers as $controller) {
            $table = 'a_' . $controller['s_name'];
            $exists = false;
            // Prefer DESCRIBE check to avoid false positives
            try {
                $this->db->query(sprintf('DESCRIBE `%s`', $table));
                $exists = true;
            } catch (\Throwable $e) {
                // ignore
            }
            if ($exists) {
                continue;
            }
            if (in_array(strtolower($table), $tablesLower, true)) {
                // table listed but DESCRIBE failed; treat as exists
                continue;
            }
            $missingTables[] = [
                'controller' => $controller,
                'table' => $table,
                'microservice' => $microserviceMap[$controller['s_ms_id']] ?? null,
            ];
        }
        $orphanTables = [];
        foreach ($tables as $table) {
            if (!isset($controllersByTable[$table])) {
                $orphanTables[] = $table;
            }
        }
        $schemaService = $this->getSchemaService();
        $systemColumns = array_map('strtolower', $schemaService->getSystemColumns());
        $columnIssues = [];
        foreach ($controllers as $controller) {
            $table = 'a_' . $controller['s_name'];
            if (!in_array($table, $tables, true)) {
                continue;
            }
            try {
                $columnsResult = $this->db->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($columnsResult)) {
                continue;
            }
            $columnMap = [];
            foreach ($columnsResult as $col) {
                $columnMap[$col['Field']] = $col;
            }
            $fields = $this->db->select('s_data_field', ['s_mscontroller_id' => $controller['id']], true);
            $missing = [];
            foreach ($fields as $field) {
                if (empty($field['s_field_name'])) {
                    continue;
                }
                if ($schemaService->isSystemColumn($field['s_field_name'])) {
                    continue;
                }
                if (!isset($columnMap[$field['s_field_name']])) {
                    $missing[] = $field['s_field_name'];
                }
            }
            $extra = [];
            foreach ($columnMap as $name => $meta) {
                if (in_array(strtolower($name), $systemColumns, true)) {
                    continue;
                }
                $found = false;
                foreach ($fields as $field) {
                    if ($field['s_field_name'] === $name) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $extra[] = $name;
                }
            }
            if (!empty($missing) || !empty($extra)) {
                $columnIssues[] = [
                    'controller' => $controller,
                    'table' => $table,
                    'missing' => $missing,
                    'extra' => $extra,
                    'microservice' => $microserviceMap[$controller['s_ms_id']] ?? null,
                ];
            }
        }
        return [
            'missing_tables' => $missingTables,
            'orphan_tables' => $orphanTables,
            'column_issues' => $columnIssues,
        ];
    }

    private function repairMissingTables(DataSchemaService $schemaService): array {
        $report = $this->generateSyncReport();
        $missing = $report['missing_tables'] ?? [];
        if (empty($missing)) {
            return ['success' => true, 'message' => 'No missing tables detected.'];
        }
        $created = 0;
        foreach ($missing as $entry) {
            $controller = $entry['controller'];
            // Skip creation if table already exists (defensive)
            $tableName = 'a_' . ($controller['s_name'] ?? '');
            try {
                $this->db->query(sprintf('DESCRIBE `%s`', $tableName));
                continue;
            } catch (\Throwable $e) {
                // proceed to create
            }
            try {
                $this->createDataModelTable($controller);
                $this->syncControllerColumns($controller, $schemaService);
                $created++;
            } catch (\Throwable $e) {
                $this->errorHandler->reportError('Sync table creation failed: ' . $e->getMessage());
            }
        }
        return [
            'success' => $created === count($missing),
            'message' => sprintf('Created %d of %d missing table(s).', $created, count($missing)),
        ];
    }

    private function syncColumnMismatches(DataSchemaService $schemaService, int $controllerId = 0): array {
        $report = $this->generateSyncReport();
        $targets = [];
        foreach ($report['column_issues'] ?? [] as $issue) {
            if ($controllerId > 0 && (int)$issue['controller']['id'] !== $controllerId) {
                continue;
            }
            $targets[] = $issue;
        }
        if (empty($targets) && $controllerId > 0) {
            $controllerRows = $this->db->select('s_mscontroller', ['id' => $controllerId, 's_type' => 'DM'], true);
            if (!empty($controllerRows)) {
                $targets[] = [
                    'controller' => $controllerRows[0],
                    'table' => 'a_' . $controllerRows[0]['s_name'],
                    'missing' => [],
                    'extra' => [],
                ];
            }
        }
        if (empty($targets)) {
            return ['success' => true, 'message' => 'No column differences to repair.'];
        }
        $fixed = 0;
        foreach ($targets as $issue) {
            try {
                $columnsToAdd = $issue['missing'] ?? [];
                $this->syncControllerColumns($issue['controller'], $schemaService, $columnsToAdd);
                $fixed++;
            } catch (\Throwable $e) {
                $this->errorHandler->reportError('Sync columns failed: ' . $e->getMessage());
            }
        }
        return [
            'success' => $fixed === count($targets),
            'message' => sprintf('Synced %d of %d controller(s).', $fixed, count($targets)),
        ];
    }

    private function registerExistingTable(DataSchemaService $schemaService, string $tableName, int $microserviceId): array {
        $tableName = trim($tableName);
        if ($tableName === '' || strpos($tableName, 'a_') !== 0) {
            return ['success' => false, 'message' => 'Invalid table name.'];
        }
        $ms = null;
        if ($microserviceId > 0) {
            $msRows = $this->db->select('s_ms', ['id' => $microserviceId], true);
            if (count($msRows) !== 1) {
                return ['success' => false, 'message' => 'Invalid microservicelet selected.'];
            }
            if ($this->isRestrictedMs((int)$msRows[0]['id'])) {
                return ['success' => false, 'message' => 'You are not permitted to use this microservicelet.'];
            }
            $ms = $msRows[0];
        }
        $controllerName = $this->sanitizeControllerName($tableName);
        if ($controllerName === '') {
            return ['success' => false, 'message' => 'Unable to derive controller name for the table.'];
        }
        $existing = $this->db->select('s_mscontroller', ['s_name' => $controllerName], true);
        if (!empty($existing)) {
            return ['success' => false, 'message' => 'A controller already exists with that name.'];
        }
        try {
            $columns = $this->describeTable($tableName);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unable to inspect table columns.'];
        }
        $indexedColumns = $this->getTableIndexes($tableName);
        $controllerId = $this->db->insert('s_mscontroller', [
            's_ms_id' => $microserviceId,
            's_name' => $controllerName,
            's_description' => ucfirst($controllerName) . ' data model',
            's_type' => 'DM',
            'livestatus' => '1',
        ]);
        if ($controllerId == 0) {
            return ['success' => false, 'message' => 'Unable to create controller record.'];
        }
        $order = 1;
        foreach ((array)$columns as $name => $meta) {
            if (in_array(strtolower($name), array_map('strtolower', $schemaService->getSystemColumns()), true)) {
                continue;
            }
            $fieldTypeId = $this->guessFieldTypeId($meta['Type'] ?? '');
            $definition = $this->buildDefinitionFromColumn($meta, !empty($indexedColumns[$name]));
            $this->db->insert('s_data_field', [
                's_mscontroller_id' => $controllerId,
                's_field_name' => $name,
                's_field_label' => ucwords(str_replace('_', ' ', $name)),
                's_field_type_id' => $fieldTypeId,
                's_is_nullable' => (strtoupper($meta['Null'] ?? 'NO') === 'YES') ? 1 : 0,
                's_sort_order' => $order++,
                's_definition' => $definition,
            ]);
        }
        return ['success' => true, 'message' => sprintf('Controller "%s" registered for table %s.', $controllerName, $tableName)];
    }

    private function createDataModelTable(array $controller): void {
        // Delegate to schema service to avoid duplicate system columns
        $schemaService = $this->getSchemaService();
        $tableName = 'a_' . ($controller['s_name'] ?? '');
        // If table exists, skip creation
        try {
            $this->db->query(sprintf('DESCRIBE `%s`', $tableName));
            return;
        } catch (\Throwable $e) {
            // proceed to ensure/create
        }
        $schemaService->ensureControllerTable($controller);
    }

    private function describeTable(string $tableName): array {
        try {
            $result = $this->db->query(sprintf('SHOW COLUMNS FROM `%s`', $tableName));
        } catch (\Throwable $e) {
            return [];
        }
        if (!is_array($result)) {
            return [];
        }
        $columns = [];
        foreach ($result as $column) {
            if (!isset($column['Field'])) {
                continue;
            }
            $columns[$column['Field']] = $column;
        }
        return $columns;
    }

    private function syncControllerColumns(array $controller, DataSchemaService $schemaService, array $limitColumns = []): array {
        $tableName = 'a_' . $controller['s_name'];
        $existingColumns = [];
        try {
            $existingColumns = $this->describeTable($tableName);
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('Unable to describe table ' . $tableName . ': ' . $e->getMessage());
            return ['added' => 0];
        }
        if (!is_array($existingColumns)) {
            return ['added' => 0];
        }
        $fields = $this->db->select('s_data_field', ['s_mscontroller_id' => $controller['id']], true, ['s_sort_order' => 'ASC']);
        $added = 0;
        foreach ($fields as $field) {
            if (!empty($limitColumns) && !in_array($field['s_field_name'], $limitColumns, true)) {
                continue;
            }
            if ($schemaService->isSystemColumn($field['s_field_name'])) {
                continue;
            }
            if (isset($existingColumns[$field['s_field_name']])) {
                continue;
            }
            $definition = $schemaService->buildSqlFromFieldRow($field);
            if (!$definition) {
                continue;
            }
            $sql = sprintf(
                'ALTER TABLE `%s` ADD COLUMN `%s` %s',
                $tableName,
                $field['s_field_name'],
                $definition['sql']
            );
            try {
                $this->db->query($sql);
                if (!empty($definition['index'])) {
                    $schemaService->applyIndexState((int)$controller['id'], $field['s_field_name'], true);
                }
                $added++;
            } catch (\Throwable $e) {
                $this->errorHandler->reportError('Unable to add column ' . $field['s_field_name'] . ': ' . $e->getMessage());
            }
        }
        return ['added' => $added];
    }

    private function guessFieldTypeId(string $columnType): int {
        if (empty($this->fieldTypeMap)) {
            $types = $this->db->select('s_data_field_type', ['livestatus' => 1], true);
            foreach ($types as $type) {
                $this->fieldTypeMap[strtoupper($type['s_name'])] = (int)$type['id'];
            }
        }
        $type = strtolower($columnType);
        $map = [
            'NUMBER' => ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'],
            'DECIMAL' => ['decimal', 'double', 'float'],
            'DATE' => ['date'],
            'DATETIME' => ['datetime', 'timestamp'],
            'TIME' => ['time'],
            'TEXT_AREA' => ['text', 'longtext', 'mediumtext'],
            'JSON' => ['json'],
        ];
        foreach ($map as $typeName => $needles) {
            foreach ($needles as $needle) {
                if (strpos($type, $needle) !== false && isset($this->fieldTypeMap[$typeName])) {
                    return $this->fieldTypeMap[$typeName];
                }
            }
        }
        return $this->fieldTypeMap['TEXT_BOX'] ?? 1;
    }

    private function buildDefinitionFromColumn(array $column, bool $indexed = false): ?string {
        $type = strtolower($column['Type'] ?? '');
        $meta = [];
        if (preg_match('/varchar\((\d+)\)/', $type, $matches)) {
            $meta['length'] = (int)$matches[1];
        }
        if (preg_match('/decimal\((\d+),(\d+)\)/', $type, $matches)) {
            $meta['precision'] = (int)$matches[1];
            $meta['scale'] = (int)$matches[2];
        }
        if ($indexed) {
            $meta['index'] = true;
        }
        return !empty($meta) ? json_encode($meta) : null;
    }

    private function sanitizeControllerName(string $table): string {
        $name = substr($table, 2);
        $name = preg_replace('/[^a-z0-9_]+/i', '', $name);
        return strtolower($name);
    }

    public function sync() {
        $this->runData['route']['h1'] = 'Application Data Sync';
        $this->runData['route']['meta_title'] = 'Sync Application Data';
        $this->runData['data']['sync'] = $this->generateSyncReport();
        $this->runData['data']['microservices'] = $this->filterRestrictedMs(
            $this->db->select('s_ms', [], true, ['s_name' => 'ASC'])
        );
        // Inventory counts
        $tablesResult = $this->db->query('SHOW TABLES LIKE \'a\_%\'');
        $tableCount = is_array($tablesResult) ? count($tablesResult) : 0;
        $controllerCount = $this->db->query('SELECT COUNT(*) AS c FROM s_mscontroller WHERE s_type = :type', [':type' => 'DM']);
        $controllerTotal = is_array($controllerCount) && isset($controllerCount[0]['c']) ? (int)$controllerCount[0]['c'] : 0;
        $this->runData['data']['total_tables'] = $tableCount;
        $this->runData['data']['total_controllers'] = $controllerTotal;
        $pending = $this->getPendingSyncAction();
        if ($pending && empty($pending['action'])) {
            $this->clearPendingSyncAction();
            $pending = null;
        }
        $this->runData['data']['sync_confirm'] = $pending;
        $this->runData['data']['sync_logs'] = $this->readSyncLogs(10);
        return $this->runData;
    }

    public function runsync() {
        $request = $this->runData['request'];
        $action = $request->post['action'] ?? '';
        if ($action === '') {
            $request->setAlert('No sync action specified.', 'danger');
            $this->redirectToSync();
        }
        $schemaService = $this->getSchemaService();
        $confirm = isset($request->post['confirm']);

        if (!$confirm) {
            $summary = $this->buildSyncSummary($action, $request->post);
            if (!$summary['success']) {
                $request->setAlert($summary['message'], 'danger');
                $this->redirectToSync();
            }
            $this->setPendingSyncAction([
                'action' => $action,
                'payload' => $summary['payload'],
                'message' => $summary['message'],
            ]);
            $request->setAlert('Review the pending sync action below to confirm or cancel.', 'warning');
            $this->redirectToSync();
        }

        $pending = $this->getPendingSyncAction();
        if (!$pending || $pending['action'] !== $action) {
            $request->setAlert('No matching pending sync action found.', 'danger');
            $this->redirectToSync();
        }
        try {
            $result = $this->executeSyncAction($action, $pending['payload'], $schemaService);
        } catch (\Throwable $e) {
            $this->appendSyncLog($action, $pending['message'], false, $e->getMessage());
            $this->clearPendingSyncAction();
            $request->setAlert('Sync failed: ' . $e->getMessage(), 'danger');
            $this->redirectToSync();
        }
        $this->appendSyncLog($action, $pending['message'], $result['success'], $result['message']);
        $this->clearPendingSyncAction();
        $request->setAlert($result['message'], $result['success'] ? 'success' : 'danger');
        $this->redirectToSync();
    }

    public function cancelsync() {
        $this->clearPendingSyncAction();
        $this->runData['request']->setAlert('Pending sync action cancelled.', 'info');
        $this->redirectToSync();
    }

    private function getSchemaService(): DataSchemaService {
        if (!$this->schemaService instanceof DataSchemaService) {
            $this->schemaService = new DataSchemaService($this->db, $this->errorHandler);
        }
        return $this->schemaService;
    }

    private function redirectToSync(): void {
        $redirectUrl = $this->runData['route']['rad_admin_url'] . '/appdata/sync';
        header("Location: {$redirectUrl}");
        exit;
    }

    private function setPendingSyncAction(array $data): void {
        $_SESSION['appdata_sync_confirm'] = $data;
    }

    private function getPendingSyncAction(): ?array {
        return $_SESSION['appdata_sync_confirm'] ?? null;
    }

    private function clearPendingSyncAction(): void {
        unset($_SESSION['appdata_sync_confirm']);
    }

    private function buildSyncSummary(string $action, array $payload): array {
        $report = $this->generateSyncReport();
        switch ($action) {
            case 'repair_tables':
                $missing = $report['missing_tables'] ?? [];
                if (empty($missing)) {
                    return ['success' => false, 'message' => 'There are no missing tables to create.'];
                }
                $names = array_map(function ($entry) {
                    return $entry['controller']['s_name'] ?? '';
                }, $missing);
                return [
                    'success' => true,
                    'message' => sprintf('Create tables for %d controller(s): %s', count($missing), implode(', ', $names)),
                    'payload' => [],
                ];
            case 'sync_columns':
                $controllerId = (int)($payload['controller_id'] ?? 0);
                if ($controllerId > 0) {
                    $controller = $this->db->select('s_mscontroller', ['id' => $controllerId], true);
                    if (empty($controller)) {
                        return ['success' => false, 'message' => 'Invalid controller selected for sync.'];
                    }
                    if ($this->isRestrictedMs((int)($controller[0]['s_ms_id'] ?? 0))) {
                        return ['success' => false, 'message' => 'You are not permitted to sync this controller.'];
                    }
                    $name = $controller[0]['s_name'];
                    return [
                        'success' => true,
                        'message' => 'Synchronize columns for controller ' . $name,
                        'payload' => ['controller_id' => $controllerId],
                    ];
                }
                if (empty($report['column_issues'])) {
                    return ['success' => false, 'message' => 'No column differences to repair.'];
                }
                return [
                    'success' => true,
                    'message' => 'Synchronize columns for all controllers with mismatched fields.',
                    'payload' => ['controller_id' => 0],
                ];
            case 'register_table':
                $tableName = $payload['table_name'] ?? '';
                $tableName = strtolower(trim($tableName));
                if ($tableName === '' || strpos($tableName, 'a_') !== 0) {
                    return ['success' => false, 'message' => 'Invalid table name.'];
                }
                $orphanTables = $report['orphan_tables'] ?? [];
                if (!in_array($tableName, $orphanTables, true)) {
                    return ['success' => false, 'message' => 'Table is not available for registration.'];
                }
                $microserviceId = (int)($payload['microservice_id'] ?? 0);
                if ($microserviceId === 0) {
                    return [
                        'success' => true,
                        'message' => sprintf('Register table %s as a global data controller (s_ms_id = 0)', $tableName),
                        'payload' => [
                            'table_name' => $tableName,
                            'microservice_id' => 0,
                        ],
                    ];
                }
                $msRows = $this->db->select('s_ms', ['id' => $microserviceId], true);
                if (count($msRows) !== 1) {
                    return ['success' => false, 'message' => 'Select a valid microservicelet.'];
                }
                return [
                    'success' => true,
                    'message' => sprintf('Register table %s under microservicelet %s', $tableName, $msRows[0]['s_name']),
                    'payload' => [
                        'table_name' => $tableName,
                        'microservice_id' => $microserviceId,
                    ],
                ];
            default:
                return ['success' => false, 'message' => 'Unknown sync action requested.'];
        }
    }

    private function executeSyncAction(string $action, array $payload, DataSchemaService $schemaService): array {
        switch ($action) {
            case 'repair_tables':
                return $this->repairMissingTables($schemaService);
            case 'sync_columns':
                $controllerId = (int)($payload['controller_id'] ?? 0);
                return $this->syncColumnMismatches($schemaService, $controllerId);
            case 'register_table':
                return $this->registerExistingTable(
                    $schemaService,
                    $payload['table_name'] ?? '',
                    (int)($payload['microservice_id'] ?? 0)
                );
            default:
                return ['success' => false, 'message' => 'Unknown sync action.'];
        }
    }

    private function appendSyncLog(string $action, string $summary, bool $success, string $details = ''): void {
        $path = $this->getSyncLogPath();
        $this->ensureLogDirectory(dirname($path));
        $entry = [
            'timestamp' => date('c'),
            'action' => $action,
            'summary' => $summary,
            'result' => $success ? 'success' : 'failure',
            'details' => $details,
        ];
        file_put_contents($path, json_encode($entry) . PHP_EOL, FILE_APPEND);
    }

    private function readSyncLogs(int $limit = 10): array {
        $path = $this->getSyncLogPath();
        if (!file_exists($path)) {
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }
        $lines = array_slice($lines, -1 * $limit);
        $entries = [];
        foreach (array_reverse($lines) as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }
        return $entries;
    }

    private function getLatestSyncStatus(): ?array {
        $logs = $this->readSyncLogs(1);
        return $logs[0] ?? null;
    }

    private function getSyncLogPath(): string {
        $logDir = rtrim($this->runData['config']['dir']['log'] ?? (__DIR__ . '/../../log'), '/');
        return $logDir . '/appdata-sync.log';
    }

    private function ensureLogDirectory(string $dir): void {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private function getTableIndexes(string $tableName): array {
        $indexes = [];
        try {
            $rows = $this->db->query(sprintf('SHOW INDEX FROM `%s`', $tableName));
            foreach ($rows as $row) {
                $col = $row['Column_name'] ?? null;
                if ($col) {
                    $indexes[$col][] = $row;
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $indexes;
    }

    private function filterRestrictedMs(array $msList): array {
        $role = (new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []))->role();
        if ($role === 'system_admin') {
            return $msList;
        }
        return array_values(array_filter($msList, function ($ms) {
            $msId = (int)($ms['id'] ?? 0);
            if ($msId === 0) {
                return true;
            }
            return !\RadAdmin\VisibilityHelper::isRestrictedMs($msId, $this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        }));
    }

    private function filterControllersByMs(array $controllers): array {
        $role = (new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []))->role();
        if ($role === 'system_admin') {
            return $controllers;
        }
        $controllers = array_values(array_filter($controllers, function ($controller) {
            $msId = (int)($controller['s_ms_id'] ?? 0);
            if ($msId === 0) {
                return true;
            }
            return !\RadAdmin\VisibilityHelper::isRestrictedMs($msId, $this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        }));
        return $controllers;
    }

    private function isRestrictedMs(int $msId): bool {
        $role = (new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []))->role();
        if ($role === 'system_admin') {
            return false;
        }
        return \RadAdmin\VisibilityHelper::isRestrictedMs($msId, $this->runData['config'] ?? [], $this->runData['entity'] ?? []);
    }
}

<?php
namespace RadAdmin;
use DateTime;
use Core\Sys\DataSchemaService;
use Core\Sys\FileVersionService;
use Core\Sys\PrivilegeService;
use Core\Sys\BranchService;
class Controller{
    use AiAssistAware;
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
    private $runData = [];
    // private $db;
    private $errorHandler;
    private $dataSchemaService;
    private $versionService;
    private $branchService;
    public function __construct(array $runData) {
        $this->runData = $runData;
        // $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        $config = $runData['config'] ?? [];
        $this->versionService = new FileVersionService($config, function () {
            $entity = $this->runData['entity'] ?? [];
            if (!empty($entity['fullname'])) {
                return $entity['fullname'];
            }
            if (!empty($entity['username'])) {
                return $entity['username'];
            }
            return 'RAD Admin';
        });
        $this->branchService = new BranchService(
            $this->runData['db'],
            $config,
            $this->runData['entity'] ?? [],
            $this->runData['request'] ?? null
        );
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }

    private function getSchemaService(): DataSchemaService {
        if (!$this->dataSchemaService instanceof DataSchemaService) {
            $this->dataSchemaService = new DataSchemaService($this->runData['db'], $this->errorHandler);
        }
        return $this->dataSchemaService;
    }

    /**
     * View all Controllers within a Microservice
     */
    public function view() {
        if (empty($this->runData['route']['pathparts'][3])) {
            $this->runData['request']->setAlert('Select a microservicelet to view its controllers.', 'warning');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/view');
            exit;
        }
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Controllers are the service objects that change its state through actions.';
        }
        // Get the Microservice details from s_ms table with uid = 3rd routeparts element
        $msRow = $this->runData['db']->select('s_ms', ['uid' => $this->runData['route']['pathparts'][3]], true);
        // print '<pre>';print_r($msRow);print '</pre>';die('here');
        if (count($msRow) != 1) {
            $this->runData['request']->setAlert('Invalid microservicelet reference.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/microservice/view');
            exit;
        }
        if (\RadAdmin\VisibilityHelper::isRestrictedMs((int)$msRow[0]['id'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [])) {
            throw new \Exception('Access denied.', 404);
        }
        $this->runData['data']['ms'] = $msRow[0];
        $scope = strtolower($this->runData['data']['ms']['s_scope'] ?? '');
        $this->runData['data']['ms_status_meta'] = [
            'label' => ($this->runData['data']['ms']['livestatus'] == 1) ? 'Active' : (($this->runData['data']['ms']['livestatus'] == 2) ? 'Archived' : 'Inactive'),
            'badge' => ($this->runData['data']['ms']['livestatus'] == 1) ? 'success' : (($this->runData['data']['ms']['livestatus'] == 2) ? 'danger' : 'secondary'),
        ];
        $isSaas = ($scope === 'workspace');
        $this->runData['data']['ms_scope_meta'] = [
            'label' => $isSaas ? 'SaaS (workspace)' : 'Non-SaaS ('.$scope.')',
            'badge' => $isSaas ? 'primary' : 'success',
            'access' => $scope === 'global' ? 'public' : 'private',
        ];
        $this->runData['route']['h1'] = 'Business Classes & Data Models of the Microservicelet '.$this->runData['data']['ms']['s_name'];
        $this->runData['route']['meta_title'] = 'Controllers: ' . ($this->runData['data']['ms']['s_name'] ?? '');
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            ($this->runData['data']['ms']['s_name'] ?? 'Microservicelet') => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . ($this->runData['data']['ms']['uid'] ?? ''),
            'Controllers' => '',
        ];
        // Select Route from s_mscontroller table with Microservice id runData['route']['ms']['id']
        $this->runData['data']['controller'] = $this->runData['db']->select('s_mscontroller', ['s_ms_id' => $this->runData['data']['ms']['id']], true);
        // print '<pre>';print_r($this->runData['data']['controller']);print '</pre>';die('here');
        return $this->runData;
    }

    /**
     * View all controllers across microservicelets.
     */
    public function viewall() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('controller_edit') && !$priv->can('view')) {
            throw new \Exception('Access denied.', 403);
        }

        $filters = [
            'search' => trim($this->runData['request']->get['search'] ?? ''),
            'scope' => trim($this->runData['request']->get['scope'] ?? ''),
            'livestatus' => trim($this->runData['request']->get['livestatus'] ?? ''),
        ];

        $controllers = $this->runData['db']->query(
            "SELECT c.*, m.s_name AS ms_name, m.s_scope, m.uid AS s_ms_uid
             FROM s_mscontroller c
             LEFT JOIN s_ms m ON m.id = c.s_ms_id
             WHERE c.livestatus != '0'
             ORDER BY m.s_name, c.s_name",
            []
        );

        $controllers = array_values(array_filter($controllers, function ($row) use ($filters) {
            if ($filters['livestatus'] !== '' && (string)($row['livestatus'] ?? '') !== $filters['livestatus']) {
                return false;
            }
            if ($filters['scope'] !== '' && strtolower($row['s_scope'] ?? '') !== strtolower($filters['scope'])) {
                return false;
            }
            if ($filters['search'] !== '') {
                $blob = strtolower(($row['s_name'] ?? '') . ' ' . ($row['ms_name'] ?? '') . ' ' . ($row['s_description'] ?? ''));
                if (strpos($blob, strtolower($filters['search'])) === false) {
                    return false;
                }
            }
            return true;
        }));

        $this->runData['data']['controllers'] = $controllers;
        $this->runData['data']['filters'] = $filters;
        $this->runData['route']['h1'] = 'Business Classes & Data Models';
        $this->runData['route']['meta_title'] = 'Business Classes & Data Models';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Business Classes' => '',
        ];
        return $this->runData;
    }

    public function detail() {
        $ref = $this->runData['route']['pathparts'][4] ?? ($this->runData['route']['pathparts'][3] ?? '');
        if ($ref === '') {
            throw new \Exception('Controller identifier missing.', 404);
        }

        $controllerRows = $this->locateControllerRecord($ref);
        if (count($controllerRows) !== 1) {
            throw new \Exception('Controller not found.', 404);
        }
        $controller = $controllerRows[0];

        $msRows = $this->runData['db']->select('s_ms', ['id' => $controller['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Parent microservice missing.', 404);
        }
        if (\RadAdmin\VisibilityHelper::isRestrictedMs((int)$msRows[0]['id'], $this->runData['config'] ?? [], $this->runData['entity'] ?? [])) {
            throw new \Exception('Access denied.', 404);
        }
        $microservice = $msRows[0];

        $fieldsCount = $this->runData['db']->query(
            "SELECT COUNT(*) AS total FROM s_data_field WHERE s_mscontroller_id = :controller",
            [':controller' => $controller['id']]
        );
        $history = $this->fetchHistoryEntries('s_mscontroller', (int)$controller['id']);
        $workflowBinding = $this->fetchWorkflowBinding($controller);

        $this->runData['data']['controller'] = $controller;
        $this->runData['data']['microservice'] = $microservice;
        $this->runData['data']['field_count'] = (int)($fieldsCount[0]['total'] ?? 0);
        $this->runData['data']['history'] = $history;
        $this->runData['data']['controller_created_by'] = $this->resolveUserName($controller['createdby'] ?? 0);
        $this->runData['data']['controller_updated_by'] = $this->resolveUserName($controller['updatedby'] ?? 0);
        $this->runData['data']['workflow_binding'] = $workflowBinding;
        $this->runData['data']['controller_runtime'] = $this->buildControllerRuntimeMeta($controller, $microservice);
        $this->hydrateDataModelContext($controller, $microservice);

        $this->runData['route']['h1'] = 'Business Class / Data Model Details';
        $this->runData['route']['meta_title'] = 'Business Class / Data Model: ' . ($controller['s_name'] ?? '');
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $microservice['uid'];
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            $microservice['s_name'] => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $microservice['uid'],
            'Controllers' => $this->runData['route']['rad_admin_url'] . '/controller/view/' . $microservice['uid'],
            ($controller['s_name'] ?? 'Controller') => '',
        ];

        return $this->runData;
    }
    
    /**
     * Add a Controller
     */
    public function add() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('controller_add')) {
            throw new \Exception('Access denied.', 403);
        }
        // print '<pre>';print_r($this->runData);print '</pre>';die('here');
        // find the name of the Microservice from s_ms table with uid = 3rd routeparts element
        $msRow = $this->runData['db']->select('s_ms', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($msRow) != 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        // print '<pre>';print_r($msRow);print '</pre>';die('Microservice Loaded');
        // get Microservice id from Microservice row
        $this->runData['data']['ms'] = $msRow[0];
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');

        if (isset($this->runData['request']->post['s_name'])) {
            $rawName = $this->runData['request']->post['s_name'];
            $sanitizedName = strtolower($rawName);
            $sanitizedName = preg_replace('/[^a-z0-9_]/', '', $sanitizedName);

            if ($sanitizedName === '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Controller Name cannot be blank.';
                $this->runData['request']->post['s_name'] = '';
            } elseif (strlen($sanitizedName) > 25) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Controller Name must be 25 characters or fewer.';
                $this->runData['request']->post['s_name'] = substr($sanitizedName, 0, 25);
            }
            else {
                $this->runData['request']->post['s_name'] = $sanitizedName;

                // Check for duplicate names in the same Microservice
                $controller = $this->runData['db']->select('s_mscontroller', ['s_name' => $this->runData['request']->post['s_name'], 's_ms_id' => $this->runData['data']['ms']['id']], true);
                // print '<pre>';print_r($controller);print '</pre>';die('here');
                // print '<pre>';print_r($this->runData);print '</pre>';die('here');
                // if no duplicate name found, insert the controller into the s_mscontroller table.
                if (count($controller) == 0)
                {
                    // insert the controller into the s_mscontroller table
                    $newControllerId = $this->runData['db']->insert('s_mscontroller', [
                        's_ms_id' => $this->runData['data']['ms']['id'],
                        's_name' => $this->runData['request']->post['s_name'],
                        's_source_file' => $this->runData['request']->post['s_type'] === 'BL' ? ucfirst($this->runData['request']->post['s_name']) . '.cls.php' : null,
                        's_class_name' => $this->runData['request']->post['s_type'] === 'BL' ? ucfirst($this->runData['request']->post['s_name']) : null,
                        's_description' => $this->runData['request']->post['s_description'],
                        's_type' => $this->runData['request']->post['s_type']
                    ]);
                    // If s_type=BL, create a class with a class name as controller_name with first letter capitalized and in /app/[microservice_name]/[class_name].cls.php. If s_type=DM, create a table a_[controller_name] in the database
                    if ($this->runData['request']->post['s_type'] == 'BL') {
                        // create a class with a class name as controller_name with first letter capitalized and in /app/[microservice_name]/[class_name].cls.php
                        $controllerName = ucfirst($this->runData['request']->post['s_name']);
                        $controllerFileName = $controllerName . '.cls.php';
                        $controllerClass = <<<EOT
<?php
namespace Microservice\\{$this->runData['data']['ms']['s_name']};
class {$controllerName} {
    private \$runData = [];
    public function __construct(array \$runData) {
        \$this->runData = \$runData;
    }
}
EOT;
                        // print '<pre>';print_r($controllerClass);print '</pre>';die('here');
                        // if directory does not exist, create it
                        if (!file_exists($this->runData['config']['dir']['ms'] . '/' . $this->runData['data']['ms']['s_name'])) {
                            mkdir($this->runData['config']['dir']['ms'] . '/' . $this->runData['data']['ms']['s_name'], 0777, true);
                        }
                        // if file does not exist, create it
                        if (!file_exists($this->runData['config']['dir']['ms'] . '/' . $this->runData['data']['ms']['s_name'] . '/' . $controllerFileName)) {
                            file_put_contents($this->runData['config']['dir']['ms'] . '/' . $this->runData['data']['ms']['s_name'] . '/' . $controllerFileName, $controllerClass);
                        }
                    }
                    else {
                        // create a table a_[controller_name] in the database
                        $controllerName = $this->runData['request']->post['s_name'];
                        $controllerTable = <<<EOT
CREATE TABLE `a_{$controllerName}` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `livestatus` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '0 = inactive, 1 = active, 2 = archived, 3 = suspended',
    `versioncode` int(11) DEFAULT NULL,
    `wf_status` int(11) NOT NULL DEFAULT 0,
    `space_id` bigint(20) NOT NULL DEFAULT 0,
    `createdby` bigint(20) DEFAULT NULL,
    `createstamp` datetime DEFAULT NULL,
    `updatedby` bigint(20) DEFAULT NULL,
    `updatestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOT;
                        $this->runData['db']->query($controllerTable);
                    }
                    // Add alert and alert_message to runData - information to be displayed to the user
                    $this->runData['route']['alert'] = 'success';
                    $this->runData['route']['alert_message'] = 'The Controller <strong>'.$this->runData['request']->post['s_name'].'</strong> has been added successfully.';
                    if (!empty($newControllerId)) {
                        $this->logControllerActivity('create', (int)$newControllerId, (int)$this->runData['data']['ms']['id'], $this->runData['request']->post['s_name'], $this->runData['request']->post['s_description'] ?? '', $this->runData['request']->post['s_type'] ?? '');
                    }
                    // Register alert into cookie
                    $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                    // Redirect to the Microservice listing page
                    $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/controller/view/'.$this->runData['data']['ms']['uid'];
                    header("Location: {$redirectUrl}");exit;
                } else {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Route already exists.';
                }
            }
        }
        // define h1 and meta_title in runData
        $this->runData['route']['h1'] = 'Add Business Class / Data Model to Microservicelet '.$this->runData['data']['ms']['s_name'];
        $this->runData['route']['meta_title'] = 'Add Business Class / Data Model to Microservicelet '.$this->runData['data']['ms']['s_name'];
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            ($this->runData['data']['ms']['s_name'] ?? 'Microservicelet') => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . ($this->runData['data']['ms']['uid'] ?? ''),
            'Controllers' => $this->runData['route']['rad_admin_url'] . '/controller/view/' . ($this->runData['data']['ms']['uid'] ?? ''),
            'Add' => '',
        ];
        return $this->runData;
    }


    /**
     * Edit a Controller
     */
    public function edit() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('controller_edit')) {
            throw new \Exception('Access denied.', 403);
        }
        // Check if the form has been submitted
        if (isset($this->runData['request']->post['controller_id'])) {
            $controllerId = $this->runData['request']->post['controller_id'];
            $s_description = $this->runData['request']->post['s_description'];
            // Do not allow changing s_type on edit, always use the existing value from DB
            $controllerRow = $this->runData['db']->select('s_mscontroller', ['uid' => $controllerId], true);
            if (count($controllerRow) != 1) {
                throw new \Exception('Invalid Controller', 404);
            }
            $msId = (int)($controllerRow[0]['s_ms_id'] ?? 0);
            $s_type = $controllerRow[0]['s_type'];
            $s_name = (string)($controllerRow[0]['s_name'] ?? '');

            if (trim((string)$s_description) === '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Description is required.';
            } else {
                $this->runData['db']->update('s_mscontroller', [
                    's_description' => $s_description,
                    's_type' => $s_type
                ], ['uid' => $controllerId]);
                $this->runData['route']['alert'] = 'success';
                $this->runData['route']['alert_message'] = 'Controller updated successfully!';
                $this->logControllerActivity('update', (int)$controllerRow[0]['id'], (int)$msId, $s_name, $s_description, $s_type);
                $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/controller/view/' . $msId;
                header("Location: {$redirectUrl}");exit;
            }
        }
        // Load controller and microservice data for the form
        $controllerRow = $this->runData['db']->select('s_mscontroller', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($controllerRow) != 1) {
            throw new \Exception('Invalid Controller', 404);
        }
        $msRow = $this->runData['db']->select('s_ms', ['uid' => $this->runData['route']['pathparts'][4]], true);
        if (count($msRow) != 1) {
            throw new \Exception('Invalid Microservicelet', 404);
        }
        $this->runData['data']['controller'] = $controllerRow[0];
        $this->runData['data']['ms'] = $msRow[0];
        $this->runData['data']['controller_runtime'] = $this->buildControllerRuntimeMeta($controllerRow[0], $msRow[0]);
        $this->runData['route']['h1'] = 'Edit Controller ' . $controllerRow[0]['s_name'] . ' of Microservicelet ' . $msRow[0]['s_name'];
        $this->runData['route']['meta_title'] = 'Edit Controller';
        $this->runData['route']['breadcrumb'] = [
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            $msRow[0]['s_name'] => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $msRow[0]['uid'],
            $controllerRow[0]['s_name'] => $this->runData['route']['rad_admin_url'] . '/controller/detail/' . $controllerRow[0]['uid'],
            'Edit' => '',
        ];
        return $this->runData;
    }

    /**
     * Archive a Controller
     */
    public function archive() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('delete')) {
            throw new \Exception('Access denied.', 403);
        }
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Route', 404);
        }
        $routeRow = $this->runData['db']->select('s_msroute', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($routeRow) != 1) {
            throw new \Exception('Invalid Route', 404);
        }
        // Archive the Route
        $this->runData['db']->update('s_msroute', ['livestatus' => '2'], ['id' => $routeRow[0]['id']]);
        // Add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Route archived successfully.';
        $this->logControllerActivity('archive', (int)$routeRow[0]['id'], (int)$routeRow[0]['s_ms_id'], $routeRow[0]['s_name'], $routeRow[0]['s_description'] ?? '', $routeRow[0]['s_type'] ?? '');
        // Register alert into cookie
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        // Get Microservice uid fronm Microservice id
        $msRow = $this->runData['db']->select('s_ms', ['id' => $routeRow[0]['s_ms_id']], true);
        // Redirect to the Microservice listing page
        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/route/view/' . $msRow[0]['uid'];
        header("Location: {$redirectUrl}");exit;
    }

    /**
     * Activate a Controller
     */
    public function activate() {
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Route', 404);
        }
        $routeRow = $this->runData['db']->select('s_msroute', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (count($routeRow) != 1) {
            throw new \Exception('Invalid Route', 404);
        }
        // print '<pre>';print_r($routeRow);print '</pre>';die('here');
        // Archive the Route
        $this->runData['db']->update('s_msroute', ['livestatus' => '1'], ['id' => $routeRow[0]['id']]);
        // Add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Route activated successfully.';
        $this->logControllerActivity('activate', (int)$routeRow[0]['id'], (int)$routeRow[0]['s_ms_id'], $routeRow[0]['s_name'], $routeRow[0]['s_description'] ?? '', $routeRow[0]['s_type'] ?? '');
        // Register alert into cookie
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        // Redirect to the Microservice listing page
        // Get Microservice uid fronm Microservice id
        $msRow = $this->runData['db']->select('s_ms', ['id' => $routeRow[0]['s_ms_id']], true);
        $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/route/view/' . $msRow[0]['uid'];
        header("Location: {$redirectUrl}");exit;
    }

    /**
     * Code for a Controller
     */
    public function code () {
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Edit the code for the Microservicelet Controller.';
        }
        $msUid = $this->runData['route']['pathparts'][3] ?? '';
        $controllerSlug = $this->runData['route']['pathparts'][4] ?? '';
        if ($msUid === '' || $controllerSlug === '') {
            $this->errorHandler->reportError('Invalid Microservicelet or Controller reference');
            exit;
        }

        // Resolve microservice first
        $msRow = $this->runData['db']->select('s_ms', ['uid' => $msUid], true);
        // print '<pre>';print_r($msRow);print '</pre>';die('here msrow');
        if (count($msRow) != 1) {
            $this->errorHandler->reportError("Invalid Microservicelet found");
            exit;
        }

        $controllerRow = $this->runData['db']->select('s_mscontroller', [
            's_ms_id' => $msRow[0]['id'],
            's_name' => strtolower($controllerSlug),
        ], true);
        if (count($controllerRow) != 1) {
            $this->errorHandler->reportError("Invalid Microservicelet Controller found");
            exit;
        }
        $this->runData['data']['controller'] = $controllerRow[0];
        $branch = $this->branchService->resolveEditorBranch();
        $this->runData['data']['branch'] = $branch;
        $this->runData['data']['controller_runtime'] = $this->buildControllerRuntimeMeta($controllerRow[0], $msRow[0], $branch);
        $this->runData['data']['branch_status'] = $this->branchService->getControllerBranchStatus((int)$this->runData['data']['controller']['id']);
        $this->runData['data']['branch_has_beta'] = $this->branchService->hasControllerBetaFile(
            $msRow[0]['s_name'],
            $this->runData['data']['controller']['s_name']
        );
        $this->runData['data']['branch_can_manage'] = $this->branchService->canUseBeta();
        $this->runData['data']['branch_can_merge'] = $this->branchService->canMerge();
        $this->runData['data']['preview_can_manage'] = $this->branchService->canUsePreview();
        $this->runData['data']['preview_context'] = $this->branchService->getPreviewContext();
        $this->runData['data']['preview_active'] = $this->branchService->isPreviewActiveFor([
            'ms_name' => (string)($msRow[0]['s_name'] ?? ''),
        ]);
        try {
            $this->runData['data']['branch_history'] = $this->runData['db']->query(
                "SELECT * FROM s_branch
                 WHERE s_object_type = 'controller' AND s_object_id = :cid
                 ORDER BY id DESC
                 LIMIT 10",
                [':cid' => (int)$this->runData['data']['controller']['id']]
            );
        } catch (\Throwable $e) {
            $this->runData['data']['branch_history'] = [];
        }
        // print '<pre>';print_r($this->runData['data']['controller']);print '</pre>';die('here');
        // create a badge for status of the route livestatus
        if ($this->runData['data']['controller']['livestatus'] == 0) {
            $livestatus_badge = '<span class="badge bg-info">Inactive</span>';
        }
        elseif ($this->runData['data']['controller']['livestatus'] == 1) {
            $livestatus_badge = '<span class="badge bg-success">Active</span>';
        }
        elseif ($this->runData['data']['controller']['livestatus'] == 2) {
            $livestatus_badge = '<span class="badge bg-danger">Archived</span>';
        }
        else {
            $livestatus_badge = '<span class="badge bg-warning">Suspended</span>';
        }

        // print '<pre>';print_r($this->runData['data']['controller']['s_ms_id']);print '</pre>';print $this->runData['data']['controller']['id'].'<br/>';die('here');
        // Get the class content from /app/[ms]/[controller].cls.php if the file exists, else create the directory and class file with initial content for the class
        if (!file_exists($this->runData['config']['dir']['ms'] . '/' . $msRow[0]['s_name'])) {
            mkdir($this->runData['config']['dir']['ms'] . '/' . $msRow[0]['s_name'], 0777, true);
        }
        $controllerFile = (string)($this->runData['data']['controller_runtime']['resolved_path'] ?? '');
        $controllerBetaFile = $this->branchService->getControllerFilePath(
            $msRow[0]['s_name'],
            $this->runData['data']['controller']['s_name'],
            'beta',
            false
        );
        if (!file_exists($controllerFile) && $branch !== 'beta') {
            $controllerName = $this->resolveControllerClassName($this->runData['data']['controller']);
            $controllerClass = <<<EOT
<?php
namespace Microservice\\{$msRow[0]['s_name']};
class {$controllerName} {
    private \$runData = [];
    public function __construct(array \$runData) {
        \$this->runData = \$runData;
    }
}
EOT;
            file_put_contents($controllerFile, $controllerClass);
            $this->runData['data']['code_class'] = $controllerClass;
        } elseif (file_exists($controllerFile)) {
            $this->runData['data']['code_class'] = file_get_contents($controllerFile);
        } else {
            $this->runData['data']['code_class'] = '';
            if ($branch === 'beta' && !is_file($controllerBetaFile)) {
                $this->runData['data']['branch_missing'] = true;
            }
        }
        $itemId = $this->getControllerVersionItemId(
            (string)($msRow[0]['s_name'] ?? $msUid),
            (string)($this->runData['data']['controller']['s_name'] ?? ''),
            $branch
        );
        $this->runData['data']['versions'] = $this->versionService->listVersions('controller', $itemId);
        $this->runData['route']['ms_name'] = $msRow[0]['s_name'];
        $this->runData['route']['h1'] = 'Microservicelet Controller: '.$this->runData['data']['controller']['s_name'];
        $this->runData['route']['meta_title'] = 'Microservicelet Controller: '.$this->runData['data']['controller']['s_name'];
        return $this->runData;
    }

    /**
     * Save code for a Controller
     */
    public function codesave() {
        $data = json_decode(file_get_contents("php://input"), true);
    
        $response = [];
        header('Content-Type: application/json');
        $this->traceControllerCodeSave('codesave request received', [
            'path3' => $this->runData['route']['pathparts'][3] ?? '',
            'path4' => $this->runData['route']['pathparts'][4] ?? '',
        ]);
    
        if (!$data || !isset($data['type']) || !isset($data['content'])) {
            $response = ['message' => 'Invalid data provided'];
            echo json_encode($response);
            exit;
        }
    
        $type = $data['type'];
        $content = $data['content'];
        $createVersion = !empty($data['create_version']);
        $expectedChecksum = trim((string)($data['expected_checksum'] ?? ''));
        $lintBeforeSave = !empty($data['lint_before_save']);
        $branch = $this->branchService->resolveEditorBranch();
        $this->traceControllerCodeSave('codesave payload parsed', [
            'type' => $type,
            'create_version' => $createVersion ? 1 : 0,
            'expected_checksum' => $expectedChecksum !== '' ? 'yes' : 'no',
            'lint_before_save' => $lintBeforeSave ? 1 : 0,
            'branch' => $branch,
        ]);
    
        $file_path = '';
        switch ($type) {
            case 'code_class':
                $file_path = $this->branchService->getControllerFilePath(
                    $this->runData['route']['pathparts'][3],
                    $this->runData['route']['pathparts'][4],
                    $branch,
                    false
                );
                $this->traceControllerCodeSave('codesave resolved file path', ['file_path' => $file_path]);
                break;
            default:
                $response = ['message' => 'Error: Invalid type'];
                echo json_encode($response);
                exit;
        }
    
        // Save the content to the file
        if ($branch === 'beta' && !$this->branchService->hasControllerBetaFile($this->runData['route']['pathparts'][3], $this->runData['route']['pathparts'][4])) {
            $response = ['message' => 'Create a beta branch before saving beta code.'];
            echo json_encode($response);
            exit;
        }
        if ($file_path) {
            if ($expectedChecksum !== '') {
                $currentContent = is_file($file_path) ? (string)file_get_contents($file_path) : '';
                $currentChecksum = sha1($currentContent);
                $this->traceControllerCodeSave('codesave checksum compared', [
                    'current_checksum' => $currentChecksum,
                    'expected_checksum' => $expectedChecksum,
                ]);
                if ($currentChecksum !== $expectedChecksum) {
                    http_response_code(409);
                    $response = [
                        'message' => 'The controller file changed since the patch was generated. Refresh context and regenerate the patch.',
                        'current_checksum' => $currentChecksum,
                    ];
                    echo json_encode($response, self::JSON_FLAGS);
                    exit;
                }
            }

            if ($lintBeforeSave) {
                $this->traceControllerCodeSave('codesave starting lint');
                $lint = $this->lintPhpContent($content);
                $this->traceControllerCodeSave('codesave finished lint', [
                    'ok' => !empty($lint['ok']) ? 1 : 0,
                    'status' => $lint['status'] ?? '',
                    'skipped' => !empty($lint['skipped']) ? 1 : 0,
                    'output' => $lint['output'] ?? '',
                ]);
                if (!($lint['ok'] ?? false)) {
                    http_response_code(422);
                    $response = [
                        'message' => 'Generated patch failed PHP lint.',
                        'lint' => $lint,
                    ];
                    echo json_encode($response, self::JSON_FLAGS);
                    exit;
                }
            }

            if (file_put_contents($file_path, $content) === false) {
                $this->traceControllerCodeSave('codesave file write failed');
                $response = ['message' => 'Failed to save the content'];
                echo json_encode($response, self::JSON_FLAGS);
                exit;
            }
            $this->traceControllerCodeSave('codesave file written', ['file_path' => $file_path]);

            if ($createVersion) {
                $this->traceControllerCodeSave('codesave starting snapshot');
                $this->snapshotControllerCode($content, $branch);
                $this->traceControllerCodeSave('codesave finished snapshot');
            }
            $response = ['message' => '', 'checksum' => sha1($content)];
            if (!empty($lint)) {
                $response['lint'] = $lint;
            }
            if ($createVersion) {
                $versions = $this->versionService->listVersions(
                    'controller',
                    $this->getControllerVersionItemId(
                        (string)($this->runData['route']['pathparts'][3] ?? ''),
                        (string)($this->runData['route']['pathparts'][4] ?? ''),
                        $branch
                    )
                );
                $response['latest_version'] = $versions[0] ?? null;
            }
            $this->traceControllerCodeSave('codesave returning success response');
            echo json_encode($response, self::JSON_FLAGS);
            exit;
        }
    }
    
    /**
     * AI Assist code for a Controller
     */
    public function aiassist() {
        header('Content-Type: application/json');
        if ($this->errorHandler && method_exists($this->errorHandler, 'setResponseMode')) {
            $this->errorHandler->setResponseMode('json');
        }
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data || !isset($data['content'])) {
            echo json_encode(['error' => 'Invalid data provided']);
            return;
        }
        try {
            $service = $this->getAiAssistService('coding', 'full');
            $result = $service->suggest($data['content'], 'controller', [
                'microservice' => $this->runData['route']['pathparts'][3] ?? '',
                'controller' => $this->runData['route']['pathparts'][4] ?? '',
            ]);
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->logError('Controller AI Assist error: ' . $e->getMessage());
            }
            $result = ['error' => $e->getMessage() ?: 'AI service is currently unavailable.'];
        }

        echo json_encode($result, JSON_UNESCAPED_SLASHES);
    }

    public function agentcontext() {
        header('Content-Type: application/json');
        if ($this->errorHandler && method_exists($this->errorHandler, 'setResponseMode')) {
            $this->errorHandler->setResponseMode('json');
        }

        $msRef = (string)($this->runData['route']['pathparts'][3] ?? '');
        $controllerRef = (string)($this->runData['route']['pathparts'][4] ?? '');
        $workspace = $this->resolveControllerWorkspaceContext($msRef, $controllerRef);
        if ($workspace === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Controller workspace not found.'], JSON_UNESCAPED_SLASHES);
            return;
        }

        echo json_encode([
            'context' => $this->buildControllerAgentContextPayload($workspace),
        ], JSON_UNESCAPED_SLASHES);
    }

    public function agentplan() {
        header('Content-Type: application/json');
        if ($this->errorHandler && method_exists($this->errorHandler, 'setResponseMode')) {
            $this->errorHandler->setResponseMode('json');
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $this->runData['request']->post ?? [];
        }

        $task = trim((string)($payload['task'] ?? ''));
        $scope = trim((string)($payload['scope'] ?? 'controller_only'));
        if ($task === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Please describe what you want the agent to do.'], JSON_UNESCAPED_SLASHES);
            return;
        }

        $msRef = (string)($this->runData['route']['pathparts'][3] ?? '');
        $controllerRef = (string)($this->runData['route']['pathparts'][4] ?? '');
        $workspace = $this->resolveControllerWorkspaceContext($msRef, $controllerRef);
        if ($workspace === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Controller workspace not found.'], JSON_UNESCAPED_SLASHES);
            return;
        }

        $plan = $this->buildControllerAgentPlan($task, $scope, $workspace);
        echo json_encode([
            'plan' => $plan,
            'context' => $this->buildControllerAgentContextPayload($workspace),
        ], JSON_UNESCAPED_SLASHES);
    }

    public function agentpatch() {
        header('Content-Type: application/json');
        if ($this->errorHandler && method_exists($this->errorHandler, 'setResponseMode')) {
            $this->errorHandler->setResponseMode('json');
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $this->runData['request']->post ?? [];
        }

        $task = trim((string)($payload['task'] ?? ''));
        $scope = trim((string)($payload['scope'] ?? 'controller_only'));
        if ($task === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Please describe the requested controller change first.'], JSON_UNESCAPED_SLASHES);
            return;
        }

        $msRef = (string)($this->runData['route']['pathparts'][3] ?? '');
        $controllerRef = (string)($this->runData['route']['pathparts'][4] ?? '');
        $workspace = $this->resolveControllerWorkspaceContext($msRef, $controllerRef);
        if ($workspace === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Controller workspace not found.'], JSON_UNESCAPED_SLASHES);
            return;
        }

        try {
            $proposal = $this->generateControllerAgentPatch($task, $scope, $workspace);
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->logError('Controller agent patch error: ' . $e->getMessage());
            }
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage() ?: 'Unable to generate controller patch.'], JSON_UNESCAPED_SLASHES);
            return;
        }

        echo json_encode([
            'proposal' => $proposal,
            'context' => $this->buildControllerAgentContextPayload($workspace),
        ], JSON_UNESCAPED_SLASHES);
    }

    public function agentapply() {
        header('Content-Type: application/json');
        if ($this->errorHandler && method_exists($this->errorHandler, 'setResponseMode')) {
            $this->errorHandler->setResponseMode('json');
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $this->runData['request']->post ?? [];
        }
        $responseMode = strtolower(trim((string)($payload['response_mode'] ?? 'json')));
        $msRef = (string)($this->runData['route']['pathparts'][3] ?? '');
        $controllerRef = (string)($this->runData['route']['pathparts'][4] ?? '');
        $branch = $this->branchService->resolveEditorBranch();
        $branchQuery = '?branch=' . ($branch === 'beta' ? 'beta' : 'live');
        $redirectUrl = $this->runData['route']['rad_admin_url'] . '/controller/code/' . $msRef . '/' . $controllerRef . $branchQuery;
        $steps = [];
        try {
            $steps[] = ['key' => 'request_received', 'message' => 'Apply request received.'];
            $proposalToken = trim((string)($payload['proposal_token'] ?? ''));
            $steps[] = ['key' => 'proposal_loaded', 'message' => 'Stored proposal loaded from session.'];
            $storedProposal = $this->readControllerAgentProposal($proposalToken);
            $content = (string)($payload['content'] ?? ($storedProposal['content'] ?? ''));
            $baseChecksum = trim((string)($payload['base_checksum'] ?? ''));
            if ($baseChecksum === '' && !empty($storedProposal['base_checksum'])) {
                $baseChecksum = trim((string)$storedProposal['base_checksum']);
            }
            $task = trim((string)($payload['task'] ?? ($storedProposal['task'] ?? 'Agent patch')));
            if ($content === '' || $baseChecksum === '') {
                if ($responseMode === 'html') {
                    $this->runData['request']->setAlert('Patch content and checksum are required.', 'danger');
                    header("Location: {$redirectUrl}");
                    exit;
                }
                http_response_code(422);
                echo json_encode(['error' => 'Patch content and checksum are required.'], self::JSON_FLAGS);
                return;
            }

            $workspace = $this->resolveControllerWorkspaceContext($msRef, $controllerRef);
            $steps[] = ['key' => 'workspace_resolved', 'message' => 'Controller workspace resolved.'];
            if ($workspace === null) {
                if ($responseMode === 'html') {
                    $this->runData['request']->setAlert('Controller workspace not found.', 'danger');
                    header("Location: {$redirectUrl}");
                    exit;
                }
                http_response_code(404);
                echo json_encode(['error' => 'Controller workspace not found.'], self::JSON_FLAGS);
                return;
            }

            $filePath = (string)($workspace['file_path'] ?? '');
            $steps[] = ['key' => 'file_resolved', 'message' => 'Controller file path resolved.'];
            if ($filePath === '') {
                if ($responseMode === 'html') {
                    $this->runData['request']->setAlert('Controller file path could not be resolved.', 'danger');
                    header("Location: {$redirectUrl}");
                    exit;
                }
                http_response_code(500);
                echo json_encode(['error' => 'Controller file path could not be resolved.'], self::JSON_FLAGS);
                return;
            }

            $currentContent = is_file($filePath) ? (string)file_get_contents($filePath) : '';
            $currentChecksum = sha1($currentContent);
            if ($currentChecksum !== $baseChecksum) {
                if ($responseMode === 'html') {
                    $this->runData['request']->setAlert('The controller file changed since the patch was generated. Refresh context and regenerate the patch.', 'warning');
                    header("Location: {$redirectUrl}");
                    exit;
                }
                http_response_code(409);
                echo json_encode([
                    'error' => 'The controller file changed since the patch was generated. Refresh context and regenerate the patch.',
                    'current_checksum' => $currentChecksum,
                ], self::JSON_FLAGS);
                return;
            }
            $steps[] = ['key' => 'checksum_verified', 'message' => 'Checksum verified against current controller file.'];

            $lint = $this->lintPhpContent($content);
            if (!($lint['ok'] ?? false)) {
                if ($responseMode === 'html') {
                    $lintMessage = trim((string)($lint['output'] ?? 'PHP lint failed.'));
                    $this->runData['request']->setAlert('Generated patch failed PHP lint. ' . $lintMessage, 'danger');
                    header("Location: {$redirectUrl}");
                    exit;
                }
                http_response_code(422);
                echo json_encode([
                    'error' => 'Generated patch failed PHP lint.',
                    'lint' => $lint,
                ], self::JSON_FLAGS);
                return;
            }
            $steps[] = ['key' => 'lint_passed', 'message' => 'PHP lint passed for generated controller code.'];

            if (file_put_contents($filePath, $content) === false) {
                if ($responseMode === 'html') {
                    $this->runData['request']->setAlert('Failed to write the controller file.', 'danger');
                    header("Location: {$redirectUrl}");
                    exit;
                }
                http_response_code(500);
                echo json_encode(['error' => 'Failed to write the controller file.'], self::JSON_FLAGS);
                return;
            }
            $steps[] = ['key' => 'file_written', 'message' => 'Controller file updated on disk.'];

            $branch = (string)($workspace['branch'] ?? 'live');
            $this->snapshotControllerCode($content, $branch);
            $steps[] = ['key' => 'version_created', 'message' => 'Version snapshot created.'];
            $versions = $this->versionService->listVersions(
                'controller',
                $this->getControllerVersionItemId(
                    (string)($workspace['microservice']['s_name'] ?? ''),
                    (string)($workspace['controller']['s_name'] ?? ''),
                    $branch
                )
            );

            if ($responseMode === 'html') {
                $lintMessage = trim((string)($lint['output'] ?? ''));
                $message = 'Agent patch applied, linted, and versioned successfully.';
                if ($lintMessage !== '') {
                    $message .= ' ' . $lintMessage;
                }
                $this->forgetControllerAgentProposal($proposalToken);
                $this->runData['request']->setAlert($message, 'success');
                header("Location: {$redirectUrl}");
                exit;
            }

            $this->forgetControllerAgentProposal($proposalToken);
            echo json_encode([
                'success' => true,
                'message' => 'Agent patch applied, linted, and versioned successfully.',
                'lint' => $lint,
                'latest_version' => $versions[0] ?? null,
                'checksum' => sha1($content),
                'task' => $task,
                'steps' => $steps,
            ], self::JSON_FLAGS);
            return;
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->logError('Controller agent apply error: ' . $e->getMessage(), ['steps' => $steps]);
            }
            if ($responseMode === 'html') {
                $this->runData['request']->setAlert($e->getMessage() ?: 'Unable to apply controller patch.', 'danger');
                header("Location: {$redirectUrl}");
                exit;
            }
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage() ?: 'Unable to apply controller patch.',
                'steps' => $steps,
            ], self::JSON_FLAGS);
            return;
        }
    }

    public function branchcreate() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('controller_edit') || !$this->branchService->canUseBeta()) {
            throw new \Exception('Access denied.', 403);
        }
        $ref = $this->runData['route']['pathparts'][3] ?? '';
        if ($ref === '') {
            throw new \Exception('Controller identifier missing.', 404);
        }
        $controllerRows = $this->locateControllerRecord($ref);
        if (count($controllerRows) !== 1) {
            throw new \Exception('Controller not found.', 404);
        }
        $controller = $controllerRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $controller['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Microservicelet not found.', 404);
        }
        $msName = $msRows[0]['s_name'];
        $result = $this->branchService->createControllerBeta($msName, $controller['s_name']);
        $this->runData['request']->setAlert($result['message'], $result['status'] ? 'success' : 'danger');
        $redirect = $this->runData['route']['rad_admin_url'] . '/controller/code/' . $msRows[0]['uid'] . '/' . $controller['s_name'] . '?branch=beta';
        header("Location: {$redirect}");
        exit;
    }

    public function branchmerge() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('controller_edit') || !$this->branchService->canMerge()) {
            throw new \Exception('Access denied.', 403);
        }
        $ref = $this->runData['route']['pathparts'][3] ?? '';
        if ($ref === '') {
            throw new \Exception('Controller identifier missing.', 404);
        }
        $controllerRows = $this->locateControllerRecord($ref);
        if (count($controllerRows) !== 1) {
            throw new \Exception('Controller not found.', 404);
        }
        $controller = $controllerRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $controller['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Microservicelet not found.', 404);
        }
        $msName = $msRows[0]['s_name'];
        $result = $this->branchService->mergeControllerBeta($msName, $controller['s_name']);
        if ($result['status']) {
            $livePath = $this->branchService->getControllerFilePath($msName, $controller['s_name'], 'live', true);
            $content = is_file($livePath) ? (string)file_get_contents($livePath) : '';
            $this->snapshotControllerCode($content, 'live');
        }
        $this->runData['request']->setAlert($result['message'], $result['status'] ? 'success' : 'danger');
        $redirect = $this->runData['route']['rad_admin_url'] . '/controller/code/' . $msRows[0]['uid'] . '/' . $controller['s_name'];
        header("Location: {$redirect}");
        exit;
    }

    public function branchdiscard() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('controller_edit') || !$this->branchService->canUseBeta()) {
            throw new \Exception('Access denied.', 403);
        }
        $ref = $this->runData['route']['pathparts'][3] ?? '';
        if ($ref === '') {
            throw new \Exception('Controller identifier missing.', 404);
        }
        $controllerRows = $this->locateControllerRecord($ref);
        if (count($controllerRows) !== 1) {
            throw new \Exception('Controller not found.', 404);
        }
        $controller = $controllerRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $controller['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Microservicelet not found.', 404);
        }
        $msName = $msRows[0]['s_name'];
        $result = $this->branchService->discardControllerBeta($msName, $controller['s_name']);
        $this->runData['request']->setAlert($result['message'], $result['status'] ? 'success' : 'danger');
        $redirect = $this->runData['route']['rad_admin_url'] . '/controller/code/' . $msRows[0]['uid'] . '/' . $controller['s_name'];
        header("Location: {$redirect}");
        exit;
    }

    public function previewstart() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('controller_edit') || !$this->branchService->canUsePreview()) {
            throw new \Exception('Access denied.', 403);
        }
        $ref = $this->runData['route']['pathparts'][3] ?? '';
        if ($ref === '') {
            throw new \Exception('Controller identifier missing.', 404);
        }
        $controllerRows = $this->locateControllerRecord($ref);
        if (count($controllerRows) !== 1) {
            throw new \Exception('Controller not found.', 404);
        }
        $controller = $controllerRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $controller['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Microservicelet not found.', 404);
        }
        $ms = $msRows[0];
        if (!$this->branchService->hasControllerBetaFile($ms['s_name'], $controller['s_name'])) {
            $this->runData['request']->setAlert('Create a beta branch before starting preview.', 'warning');
        } else {
            $this->branchService->activatePreviewSession([
                'object_type' => 'controller',
                'ms_name' => (string)$ms['s_name'],
                'controller_name' => (string)($controller['s_name'] ?? ''),
                'controller_uid' => (string)($controller['uid'] ?? ''),
            ]);
            $this->runData['request']->setAlert('Beta preview started for this microservicelet context.', 'success');
        }
        $redirect = $this->runData['route']['rad_admin_url'] . '/controller/code/' . $ms['uid'] . '/' . $controller['s_name'] . '?branch=beta';
        header("Location: {$redirect}");
        exit;
    }

    public function previewstop() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('controller_edit') || !$this->branchService->canUsePreview()) {
            throw new \Exception('Access denied.', 403);
        }
        $ref = $this->runData['route']['pathparts'][3] ?? '';
        if ($ref === '') {
            throw new \Exception('Controller identifier missing.', 404);
        }
        $controllerRows = $this->locateControllerRecord($ref);
        if (count($controllerRows) !== 1) {
            throw new \Exception('Controller not found.', 404);
        }
        $controller = $controllerRows[0];
        $msRows = $this->runData['db']->select('s_ms', ['id' => $controller['s_ms_id']], true);
        if (count($msRows) !== 1) {
            throw new \Exception('Microservicelet not found.', 404);
        }
        $ms = $msRows[0];
        $this->branchService->clearPreviewSession();
        $this->runData['request']->setAlert('Beta preview stopped.', 'success');
        $redirect = $this->runData['route']['rad_admin_url'] . '/controller/code/' . $ms['uid'] . '/' . $controller['s_name'] . '?branch=beta';
        header("Location: {$redirect}");
        exit;
    }

    public function downloadversion() {
        $ms = $this->runData['route']['pathparts'][3] ?? '';
        $controller = $this->runData['route']['pathparts'][4] ?? '';
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][5] ?? '');
        $branch = $this->branchService->resolveEditorBranch();
        if ($ms === '' || $controller === '' || $versionId === '') {
            throw new \Exception('Invalid version request', 404);
        }
        $context = $this->resolveControllerVersionContext($ms, $controller);
        if ($context === null) {
            throw new \Exception('Version target not found', 404);
        }
        $itemId = $this->getControllerVersionItemId($context['ms_name'], $context['controller_name'], $branch);
        $version = $this->versionService->fetchVersion('controller', $itemId, $versionId);
        if (!$version) {
            throw new \Exception('Version not found', 404);
        }
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $ms . '-' . $controller . '-' . $versionId . '.txt"');
        echo $version['content'] ?? '';
        exit;
    }

    public function diffversion() {
        $ms = $this->runData['route']['pathparts'][3] ?? '';
        $controller = $this->runData['route']['pathparts'][4] ?? '';
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][5] ?? '');
        $branch = $this->branchService->resolveEditorBranch();
        if ($ms === '' || $controller === '' || $versionId === '') {
            throw new \Exception('Invalid version request', 404);
        }
        $context = $this->resolveControllerVersionContext($ms, $controller);
        if ($context === null) {
            throw new \Exception('Version target not found', 404);
        }
        $itemId = $this->getControllerVersionItemId($context['ms_name'], $context['controller_name'], $branch);
        $version = $this->versionService->fetchVersion('controller', $itemId, $versionId);
        $filePath = $this->branchService->getControllerFilePath($context['ms_name'], $context['controller_name'], $branch, true);
        if (!$version || !is_file($filePath)) {
            throw new \Exception('Version not found', 404);
        }
        $currentContent = file_get_contents($filePath) ?: '';
        $diff = $this->versionService->diff('controller', $itemId, $versionId, $currentContent);
        $msRows = $this->runData['db']->select('s_ms', ['s_name' => $context['ms_name']], true);
        $controllerRows = !empty($msRows)
            ? $this->runData['db']->select('s_mscontroller', ['s_ms_id' => $msRows[0]['id'], 's_name' => $context['controller_name']], true)
            : [];

        $this->runData['route']['h1'] = 'Controller Diff';
        $this->runData['route']['meta_title'] = 'Controller Diff';
        $branchQuery = '?branch=' . ($branch === 'beta' ? 'beta' : 'live');
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/controller/code/' . $ms . '/' . $controller . $branchQuery;
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelets' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            ($context['ms_name'] ?: 'Microservicelet') => !empty($msRows[0]['uid'])
                ? $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $msRows[0]['uid']
                : '',
            'Controllers' => !empty($msRows[0]['uid'])
                ? $this->runData['route']['rad_admin_url'] . '/controller/view/' . $msRows[0]['uid']
                : '',
            ($context['controller_name'] ?: 'Controller') => !empty($controllerRows[0]['uid'])
                ? $this->runData['route']['rad_admin_url'] . '/controller/detail/' . $controllerRows[0]['uid']
                : '',
            'Diff' => '',
        ];
        $this->runData['data']['diff'] = [
            'template' => $context['ms_name'] . '/' . $context['controller_name'],
            'version' => $version,
            'diff' => $diff,
            'branch' => $branch,
            'microservice_name' => $context['ms_name'],
            'controller_name' => $context['controller_name'],
            'controller_uid' => $controllerRows[0]['uid'] ?? '',
            'microservice_uid' => $msRows[0]['uid'] ?? '',
        ];
        return $this->runData;
    }

    public function restoreversion() {
        $ms = $this->runData['route']['pathparts'][3] ?? '';
        $controller = $this->runData['route']['pathparts'][4] ?? '';
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][5] ?? '');
        $branch = $this->branchService->resolveEditorBranch();
        $branchQuery = '?branch=' . ($branch === 'beta' ? 'beta' : 'live');
        $redirect = $this->runData['route']['rad_admin_url'] . '/controller/code/' . $ms . '/' . $controller . $branchQuery;

        if ($ms === '' || $controller === '' || $versionId === '') {
            header("Location: {$redirect}");
            exit;
        }
        if (strtoupper($this->runData['request']->method) !== 'POST') {
            header("Location: {$redirect}");
            exit;
        }

        $context = $this->resolveControllerVersionContext($ms, $controller);
        if ($context === null) {
            $this->runData['request']->setAlert('Version target not found.', 'danger');
            header("Location: {$redirect}");
            exit;
        }
        $itemId = $this->getControllerVersionItemId($context['ms_name'], $context['controller_name'], $branch);
        $version = $this->versionService->fetchVersion('controller', $itemId, $versionId);
        $filePath = $this->branchService->getControllerFilePath($context['ms_name'], $context['controller_name'], $branch, true);
        if (!$version || !is_file($filePath)) {
            $this->runData['request']->setAlert('Version not found.', 'danger');
            header("Location: {$redirect}");
            exit;
        }

        if (file_put_contents($filePath, $version['content'] ?? '') === false) {
            $this->runData['request']->setAlert('Failed to restore version.', 'danger');
            header("Location: {$redirect}");
            exit;
        }

        $this->snapshotControllerCode($version['content'] ?? '', $branch);
        $this->runData['request']->setAlert('Version restored successfully.', 'success');
        header("Location: {$redirect}");
        exit;
    }

    /**
     * View Schema of a Data Controller
     */
    public function viewschema() {
        // define info alert if there is no alert from request
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'The following is the schema of the Data Controller.';
        }
        else {
            $this->runData['route']['alert'] = $this->runData['route']['alert'];
            $this->runData['route']['alert_message'] = $this->runData['route']['alert_message'];
        }
        // Get the controller from the route pathparts 3
        $controllerRow = $this->runData['db']->select('s_mscontroller', ['uid' => $this->runData['route']['pathparts'][3]], true);
        // print '<pre>';print_r($controllerRow);print '<br/>'.count($controllerRow);print '</pre>';die('here');
        if (count($controllerRow) != 1) {
            $this->errorHandler->reportError("Invalid Microservicelet Controller found");
            exit;
        }
        $this->runData['data']['controller'] = $controllerRow[0];
        // define h1 and meta_title in runData
        $this->runData['route']['h1'] = 'Schema of Data Controller '.$this->runData['data']['controller']['s_name'];
        $this->runData['route']['meta_title'] = 'Schema of Data Controller '.$this->runData['data']['controller']['s_name'];
        // Get the microservice from the route pathparts 4
        $msRow = $this->runData['db']->select('s_ms', ['uid' => $this->runData['route']['pathparts'][4]], true);
        // print '<pre>';print_r($msRow);print '</pre>';die('here msrow');
        if (count($msRow) != 1) {
            $this->errorHandler->reportError("Invalid Microservicelet found");
            exit;
        }
        $controllerId = (int)$this->runData['data']['controller']['id'];
        $branch = $this->branchService->resolveEditorBranch();
        $this->runData['data']['schema_branch'] = $branch;
        $this->runData['data']['schema_branch_status'] = $this->branchService->getControllerSchemaBranchStatus($controllerId);
        $this->runData['data']['schema_branch_can_manage'] = $this->branchService->canUseBeta();
        $this->runData['data']['schema_branch_can_merge'] = $this->branchService->canMerge();
        try {
            $this->runData['data']['schema_branch_history'] = $this->runData['db']->query(
                "SELECT * FROM s_branch
                 WHERE s_object_type = 'controller_schema' AND s_object_id = :cid
                 ORDER BY id DESC
                 LIMIT 10",
                [':cid' => $controllerId]
            );
        } catch (\Throwable $e) {
            $this->runData['data']['schema_branch_history'] = [];
        }
        foreach ($this->runData['data']['schema_branch_history'] as &$branchEntry) {
            $branchEntry['actor_label'] = $this->resolveUserName($branchEntry['createdby'] ?? 0);
        }
        unset($branchEntry);

        // Assign to runData
        $this->runData['data']['ms'] = $msRow[0];
        try {
            $this->runData['data']['fields'] = $this->runData['db']->select('s_data_field', ['s_mscontroller_id' => $controllerId], true, ['s_sort_order' => 'ASC']);
        } catch (\Throwable $e) {
            $this->runData['data']['fields'] = [];
            $this->errorHandler->reportError('Unable to load controller fields: ' . $e->getMessage());
        }
        $betaSchema = $this->getControllerSchemaBeta($controllerId);
        if (!empty($betaSchema)) {
            $this->runData['data']['schema_branch_has_beta'] = true;
            if ($branch === 'beta') {
                $this->runData['data']['fields'] = $betaSchema['fields'] ?? [];
            }
        } elseif ($branch === 'beta') {
            $this->runData['data']['schema_branch_missing'] = true;
        }
        foreach ($this->runData['data']['fields'] as &$fieldRow) {
            $fieldRow['is_indexed'] = $this->dataFieldIsIndexed($fieldRow);
        }
        unset($fieldRow);
        try {
            $this->runData['data']['field_types'] = $this->runData['db']->select('s_data_field_type', ['livestatus' => 1], true, ['s_description' => 'ASC']);
        } catch (\Throwable $e) {
            $this->runData['data']['field_types'] = [];
        }
        $service = $this->getSchemaService();
        foreach ($this->runData['data']['field_types'] as &$fieldType) {
            $fieldType['meta'] = $service->describeFieldTypeRow($fieldType);
        }
        unset($fieldType);
        try {
            $this->runData['data']['field_groups'] = $this->runData['db']->select(
                's_data_field_group',
                ['s_service_id' => $this->runData['data']['ms']['id']],
                true,
                ['s_group_title' => 'ASC']
            );
        } catch (\Throwable $e) {
            $this->runData['data']['field_groups'] = [];
        }
        $this->runData['data']['schema_controller_id'] = $controllerId;
        return $this->runData;
    }

    /**
     * Dedicated Records manager for Data Model controllers
     */
    public function viewrecords() {
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Review, add, or edit the application data captured through this Data Model.';
        }

        $controllerUid = $this->runData['route']['pathparts'][3] ?? '';
        $microserviceUid = $this->runData['route']['pathparts'][4] ?? '';
        if ($controllerUid === '' || $microserviceUid === '') {
            throw new \Exception('Controller or Microservice reference is missing.', 404);
        }

        $controllerRows = $this->runData['db']->select('s_mscontroller', ['uid' => $controllerUid], true);
        if (count($controllerRows) !== 1) {
            throw new \Exception('Invalid controller reference.', 404);
        }
        $controller = $controllerRows[0];
        if (strtoupper($controller['s_type'] ?? '') !== 'DM') {
            throw new \Exception('Records are available only for Data Model controllers.', 400);
        }

        $msRows = $this->runData['db']->select('s_ms', ['uid' => $microserviceUid], true);
        if (count($msRows) !== 1) {
            // Fall back to the controller link if UID mismatch occurs.
            $fallbackRows = $this->runData['db']->select('s_ms', ['id' => $controller['s_ms_id']], true);
            if (count($fallbackRows) !== 1) {
                throw new \Exception('Microservicelet not found for this controller.', 404);
            }
            $msRows = $fallbackRows;
        }
        $microservice = $msRows[0];

        $controllerId = (int)$controller['id'];
        $service = $this->getSchemaService();
        try {
            $fields = $service->listFields($controllerId);
        } catch (\Throwable $e) {
            $fields = [];
            $this->errorHandler->reportError('Unable to fetch data model fields: ' . $e->getMessage());
        }
        foreach ($fields as &$fieldRow) {
            $fieldRow['is_indexed'] = $this->dataFieldIsIndexed($fieldRow);
        }
        unset($fieldRow);

        try {
            $fieldTypes = $this->runData['db']->select('s_data_field_type', ['livestatus' => 1], true, ['s_description' => 'ASC']);
        } catch (\Throwable $e) {
            $fieldTypes = [];
        }
        foreach ($fieldTypes as &$fieldType) {
            $fieldType['meta'] = $service->describeFieldTypeRow($fieldType);
        }
        unset($fieldType);
        $fieldTypesIndex = [];
        foreach ($fieldTypes as $fieldTypeRow) {
            $fieldTypesIndex[$fieldTypeRow['id']] = $fieldTypeRow;
        }
        foreach ($fields as &$fieldRow) {
            $definitionMeta = [];
            if (!empty($fieldRow['s_definition'])) {
                $definitionMeta = json_decode($fieldRow['s_definition'], true) ?: [];
            }
            $fieldRow['definition_meta'] = $definitionMeta;
            $fieldRow['ui'] = $fieldTypesIndex[$fieldRow['s_field_type_id']]['meta']['ui'] ?? [];
        }
        unset($fieldRow);

        $tableName = 'a_' . $controller['s_name'];
        $tableExists = true;
        $columns = [];
        $recordColumns = [];
        try {
            $columns = $this->runData['db']->query(sprintf('SHOW COLUMNS FROM `%s`', $tableName));
            foreach ($columns as $column) {
                if (!empty($column['Field'])) {
                    $recordColumns[] = $column['Field'];
                }
            }
        } catch (\Throwable $e) {
            $tableExists = false;
            $columns = [];
            $this->errorHandler->reportError('Unable to inspect data table: ' . $e->getMessage());
        }
        if (empty($recordColumns) && !empty($fields)) {
            foreach ($fields as $fieldRow) {
                if (!empty($fieldRow['s_field_name'])) {
                    $recordColumns[] = $fieldRow['s_field_name'];
                }
            }
        }

        $rows = [];
        $total = 0;
        $limit = 25;
        if ($tableExists) {
            try {
                $totalRow = $this->runData['db']->query(sprintf('SELECT COUNT(*) AS total FROM `%s`', $tableName));
                $total = (int)($totalRow[0]['total'] ?? 0);
                $rows = $this->runData['db']->query(sprintf('SELECT * FROM `%s` ORDER BY id DESC LIMIT %d', $tableName, $limit));
            } catch (\Throwable $e) {
                $rows = [];
                $this->errorHandler->reportError('Unable to load records: ' . $e->getMessage());
            }
        }
        $pages = $tableExists ? max(1, (int)ceil(($total ?: 1) / max(1, $limit))) : 1;

        $this->runData['data']['controller'] = $controller;
        $this->runData['data']['microservice'] = $microservice;
        $this->runData['data']['records'] = [
            'controller_id' => $controllerId,
            'table' => $tableName,
            'table_exists' => $tableExists,
            'fields' => $fields,
            'field_types' => $fieldTypes,
            'columns' => $columns,
            'record_columns' => $recordColumns,
            'rows' => $rows,
            'pagination' => [
                'page' => 1,
                'limit' => $limit,
                'total' => $total,
                'pages' => $pages,
            ],
            'can_delete_records' => ((int)($this->runData['entity']['id'] ?? 0) === 1),
            'system_columns' => $service->getSystemColumns(),
        ];

        $this->runData['route']['h1'] = 'Manage Records';
        $this->runData['route']['meta_title'] = 'Records: ' . ($controller['s_name'] ?? '');
        $this->runData['route']['breadcrumb'] = [
            $microservice['s_name'] => $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $microservice['uid'],
            $controller['s_name'] => $this->runData['route']['rad_admin_url'] . '/controller/detail/' . $controller['uid'],
            'Records' => ''
        ];

        return $this->runData;
    }

    /**
     * Add a Field to a Data Controller
     */
    public function addschema() {
        // Capture input data
        $fieldType = $this->runData['request']->post['fieldType'];
        $controllerId = $this->runData['route']['pathparts'][3];
        print '<pre>';print_r($_POST);print '</pre>';die('here');

        // Debugging statements
        error_log("Field Type: " . $fieldType);
        error_log("Controller ID: " . $controllerId);

        // Validate input data
        if (empty($fieldType) || empty($controllerId)) {
            echo json_encode(['error' => 'Invalid input data']);
            return;
        }

        // Fetch the field type details
        $fieldTypeDetails = $this->runData['db']->select('s_data_field_type', ['id' => $fieldType], true);
        if (count($fieldTypeDetails) != 1) {
            echo json_encode(['error' => 'Invalid field type']);
            return;
        }
        $fieldTypeDetail = $fieldTypeDetails[0];
        // print '<pre>';print_r($fieldTypeDetail);print '</pre>';die('here');

        $newRecId = $this->runData['db']->insert('s_data_field', [
            's_mscontroller_id' => $controllerId,
            's_name' => $fieldTypeDetail['s_name'],
            's_label' => $fieldTypeDetail['s_description'],
            's_type' => $fieldTypeDetail['id'],
            's_definition' => $fieldTypeDetail['s_definition']
        ]);

        if ($newRecId == 0) {
            // Add alert and alert_message to runData - information to be displayed to the user
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'The new field could not be added.';
        } else {
            // Add alert and alert_message to runData - information to be displayed to the user
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'The new field has been added successfully.';
            // Register alert into cookie
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to the Microservice listing page
            $redirectUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/controller/view/' . $this->runData['data']['ms']['uid'];
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    public function schemaaddfield() {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            $payload = $this->runData['request']->post ?? [];
        }
        $branch = $this->branchService->resolveEditorBranch();
        $controllerId = (int)($payload['controller_id'] ?? 0);
        if ($controllerId <= 0 && !empty($payload['controller_uid'])) {
            $controllerId = $this->resolveControllerIdByUid($payload['controller_uid']);
        }
        if ($controllerId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Controller reference missing.']);
            return;
        }
        if ($branch === 'beta') {
            $result = $this->addSchemaFieldBeta($controllerId, $payload);
            echo json_encode($result);
            return;
        }
        try {
            $service = $this->getSchemaService();
            $result = $service->addField($controllerId, $payload, (int)($this->runData['entity']['id'] ?? 0));
            if (!empty($result['success'])) {
                try {
                    $controller = $service->resolveController($controllerId);
                    $service->ensureControllerTable($controller);
                } catch (\Throwable $syncError) {
                    $this->errorHandler->reportError('Schema add sync warning: ' . $syncError->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('Schema add failed: ' . $e->getMessage());
            $result = ['success' => false, 'message' => 'Unable to create field.'];
        }
        echo json_encode($result);
    }

    public function schemaupdatefield() {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            $payload = $this->runData['request']->post ?? [];
        }
        $branch = $this->branchService->resolveEditorBranch();
        $controllerId = (int)($payload['controller_id'] ?? 0);
        if ($controllerId <= 0 && !empty($payload['controller_uid'])) {
            $controllerId = $this->resolveControllerIdByUid($payload['controller_uid']);
        }
        $fieldId = (int)($payload['field_id'] ?? 0);
        if ($controllerId <= 0 || $fieldId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            return;
        }
        if ($branch === 'beta') {
            $result = $this->updateSchemaFieldBeta($controllerId, $fieldId, $payload);
            echo json_encode($result);
            return;
        }
        try {
            $service = $this->getSchemaService();
            $result = $service->updateField($controllerId, $fieldId, $payload);
            if (!empty($result['success'])) {
                try {
                    $controller = $service->resolveController($controllerId);
                    $service->ensureControllerTable($controller);
                } catch (\Throwable $syncError) {
                    $this->errorHandler->reportError('Schema update sync warning: ' . $syncError->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('Schema update failed: ' . $e->getMessage());
            $result = ['success' => false, 'message' => 'Unable to update field.'];
        }
        echo json_encode($result);
    }

    public function schemadeletefield() {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            $payload = $this->runData['request']->post ?? [];
        }
        $branch = $this->branchService->resolveEditorBranch();
        $controllerId = (int)($payload['controller_id'] ?? 0);
        if ($controllerId <= 0 && !empty($payload['controller_uid'])) {
            $controllerId = $this->resolveControllerIdByUid($payload['controller_uid']);
        }
        $fieldId = (int)($payload['field_id'] ?? 0);
        if ($controllerId <= 0 || $fieldId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            return;
        }
        if ($branch === 'beta') {
            $result = $this->deleteSchemaFieldBeta($controllerId, $fieldId);
            echo json_encode($result);
            return;
        }
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('delete')) {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            return;
        }
        try {
            $service = $this->getSchemaService();
            $result = $service->deleteField($controllerId, $fieldId);
            if (!empty($result['success'])) {
                try {
                    $controller = $service->resolveController($controllerId);
                    $service->ensureControllerTable($controller);
                } catch (\Throwable $syncError) {
                    $this->errorHandler->reportError('Schema delete sync warning: ' . $syncError->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('Schema delete failed: ' . $e->getMessage());
            $result = ['success' => false, 'message' => 'Unable to delete field.'];
        }
        echo json_encode($result);
    }

    public function addfield() {
        return $this->schemaaddfield();
    }

    public function updatefield() {
        return $this->schemaupdatefield();
    }

    public function deletefield() {
        return $this->schemadeletefield();
    }

    public function schemabranchcreate() {
        $controllerUid = $this->runData['route']['pathparts'][3] ?? '';
        $msUid = $this->runData['route']['pathparts'][4] ?? '';
        $redirect = $this->runData['route']['rad_admin_url'] . '/controller/viewschema/' . $controllerUid . '/' . $msUid;
        if ($controllerUid === '') {
            header("Location: {$redirect}");
            exit;
        }
        if (!$this->branchService->canUseBeta()) {
            $this->runData['request']->setAlert('Access denied.', 'danger');
            header("Location: {$redirect}");
            exit;
        }
        $controllerRows = $this->runData['db']->select('s_mscontroller', ['uid' => $controllerUid], true);
        if (count($controllerRows) !== 1) {
            $this->runData['request']->setAlert('Controller not found.', 'danger');
            header("Location: {$redirect}");
            exit;
        }
        $controllerId = (int)$controllerRows[0]['id'];
        $betaSchema = $this->getControllerSchemaBeta($controllerId);
        if (!empty($betaSchema['fields'])) {
            $this->runData['request']->setAlert('Beta schema already exists.', 'warning');
            header("Location: {$redirect}?branch=beta");
            exit;
        }
        $this->initSchemaBetaFromLive($controllerId);
        $this->branchService->recordSchemaEvent($controllerId, 'active', 'Beta schema created', [
            'controller_uid' => $controllerUid,
        ]);
        $this->runData['request']->setAlert('Beta schema created.', 'success');
        header("Location: {$redirect}?branch=beta");
        exit;
    }

    public function schemabranchmerge() {
        $controllerUid = $this->runData['route']['pathparts'][3] ?? '';
        $msUid = $this->runData['route']['pathparts'][4] ?? '';
        $redirect = $this->runData['route']['rad_admin_url'] . '/controller/viewschema/' . $controllerUid . '/' . $msUid;
        if ($controllerUid === '') {
            header("Location: {$redirect}");
            exit;
        }
        if (!$this->branchService->canMerge()) {
            $this->runData['request']->setAlert('Access denied.', 'danger');
            header("Location: {$redirect}");
            exit;
        }
        $controllerRows = $this->runData['db']->select('s_mscontroller', ['uid' => $controllerUid], true);
        if (count($controllerRows) !== 1) {
            $this->runData['request']->setAlert('Controller not found.', 'danger');
            header("Location: {$redirect}");
            exit;
        }
        $controllerId = (int)$controllerRows[0]['id'];
        $result = $this->mergeSchemaBetaIntoLive($controllerId);
        if (!empty($result['success'])) {
            $this->branchService->recordSchemaEvent($controllerId, 'merged', 'Beta schema merged into live', [
                'controller_uid' => $controllerUid,
                'summary' => $result['summary'] ?? [],
            ]);
            $this->runData['request']->setAlert('Beta schema merged into live.', 'success');
            header("Location: {$redirect}");
            exit;
        }
        $this->branchService->recordSchemaEvent($controllerId, 'merge_failed', $result['message'] ?? 'Merge failed', [
            'controller_uid' => $controllerUid,
        ]);
        $this->runData['request']->setAlert($result['message'] ?? 'Unable to merge beta schema.', 'danger');
        header("Location: {$redirect}?branch=beta");
        exit;
    }

    public function schemabranchdiscard() {
        $controllerUid = $this->runData['route']['pathparts'][3] ?? '';
        $msUid = $this->runData['route']['pathparts'][4] ?? '';
        $redirect = $this->runData['route']['rad_admin_url'] . '/controller/viewschema/' . $controllerUid . '/' . $msUid;
        if ($controllerUid === '') {
            header("Location: {$redirect}");
            exit;
        }
        if (!$this->branchService->canUseBeta()) {
            $this->runData['request']->setAlert('Access denied.', 'danger');
            header("Location: {$redirect}");
            exit;
        }
        $controllerRows = $this->runData['db']->select('s_mscontroller', ['uid' => $controllerUid], true);
        if (count($controllerRows) !== 1) {
            $this->runData['request']->setAlert('Controller not found.', 'danger');
            header("Location: {$redirect}");
            exit;
        }
        $controllerId = (int)$controllerRows[0]['id'];
        $this->clearSchemaBeta($controllerId);
        $this->branchService->recordSchemaEvent($controllerId, 'discarded', 'Beta schema discarded', [
            'controller_uid' => $controllerUid,
        ]);
        $this->runData['request']->setAlert('Beta schema discarded.', 'success');
        header("Location: {$redirect}");
        exit;
    }

    public function datamodelfetch() {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = null;
        }
        $request = $this->runData['request'];
        $controllerId = (int)(
            $payload['controller_id'] ??
            $request->post['controller_id'] ??
            $request->get['controller_id'] ??
            0
        );
        if ($controllerId <= 0) {
            $controllerUid = $payload['controller_uid'] ?? $request->post['controller_uid'] ?? $request->get['controller_uid'] ?? '';
            if ($controllerUid !== '') {
                $controllerId = $this->resolveControllerIdByUid($controllerUid);
            }
        }
        if ($controllerId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Controller reference missing.']);
            return;
        }
        $service = $this->getSchemaService();
        try {
            $context = $this->resolveDataModelContext($controllerId);
        } catch (\Throwable $e) {
            try {
                $controller = $service->resolveController($controllerId);
                $service->ensureControllerTable($controller);
                $context = $this->resolveDataModelContext($controllerId);
            } catch (\Throwable $inner) {
                $this->errorHandler->reportError('DM fetch failed: ' . $inner->getMessage());
                echo json_encode(['success' => false, 'message' => 'Unable to load data model: ' . $inner->getMessage()]);
                return;
            }
        }
        $page = max(1, (int)($payload['page'] ?? $request->post['page'] ?? $request->get['page'] ?? 1));
        $limit = min(100, max(1, (int)($payload['limit'] ?? $request->post['limit'] ?? $request->get['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;
        $microservice = $context['microservice'] ?? [];
        $scope = $microservice['s_scope'] ?? 'platform';
        $isSaas = in_array($scope, ['workspace','app','member_org'], true);
        $spaceId = $isSaas ? (int)($this->runData['route']['space_id'] ?? 0) : 0;
        try {
            $whereSql = '';
            $params = [];
            if ($isSaas && $spaceId > 0) {
                $whereSql = ' WHERE space_id = :space';
                $params[':space'] = $spaceId;
            }
            $totalRow = $this->runData['db']->query(
                sprintf('SELECT COUNT(*) AS total FROM `%s`%s', $context['table'], $whereSql),
                $params
            );
            $total = (int)($totalRow[0]['total'] ?? 0);
            $rows = $this->runData['db']->query(
                sprintf(
                    'SELECT * FROM `%s`%s ORDER BY id DESC LIMIT %d OFFSET %d',
                    $context['table'],
                    $whereSql,
                    $limit,
                    $offset
                ),
                $params
            );
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('DM list error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Unable to fetch records.']);
            return;
        }
        echo json_encode([
            'success' => true,
            'rows' => $rows,
            'columns' => array_values(array_keys($context['columns'])),
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $limit)),
        ]);
    }

    public function datamodelsave() {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            $payload = $this->runData['request']->post ?? [];
        }
        $controllerId = (int)($payload['controller_id'] ?? 0);
        if ($controllerId <= 0 && !empty($payload['controller_uid'])) {
            $controllerId = $this->resolveControllerIdByUid($payload['controller_uid']);
        }
        if ($controllerId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Controller reference missing.']);
            return;
        }
        $service = $this->getSchemaService();
        try {
            $context = $this->resolveDataModelContext($controllerId);
        } catch (\Throwable $e) {
            try {
                $controller = $service->resolveController($controllerId);
                $service->ensureControllerTable($controller);
                $context = $this->resolveDataModelContext($controllerId);
            } catch (\Throwable $inner) {
                $this->errorHandler->reportError('DM save context failed: ' . $inner->getMessage());
                echo json_encode(['success' => false, 'message' => 'Unable to load data model: ' . $inner->getMessage()]);
                return;
            }
        }
        $rowId = isset($payload['row_id']) ? (int)$payload['row_id'] : null;
        $values = $payload['values'] ?? [];
        $filtered = $this->filterRecordValues($context['columns'], $context['system_columns'], $values);
        if (empty($filtered)) {
            echo json_encode(['success' => false, 'message' => 'No editable columns supplied.']);
            return;
        }
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        $microservice = $context['microservice'] ?? [];
        $scope = $microservice['s_scope'] ?? 'platform';
        $isSaas = in_array($scope, ['workspace','app','member_org'], true);
        $spaceId = $isSaas ? (int)($this->runData['route']['space_id'] ?? 0) : 0;
        try {
            if ($rowId) {
                $where = ['id' => $rowId];
                if ($isSaas && $spaceId > 0) {
                    $where['space_id'] = $spaceId;
                }
                $this->runData['db']->update($context['table'], $filtered, $where, ['updatedby' => $entityId ?: 1]);
                $updatedRow = $this->runData['db']->query(sprintf('SELECT * FROM `%s` WHERE id = :id', $context['table']), [':id' => $rowId]);
                $row = $updatedRow[0] ?? null;
                $message = 'Record updated.';
            } else {
                $newId = $this->runData['db']->insert($context['table'], $filtered, [
                    'createdby' => $entityId ?: 1,
                    'updatedby' => $entityId ?: 1,
                    'space_id' => $isSaas ? $spaceId : 0,
                ]);
                $insertedRow = $this->runData['db']->query(sprintf('SELECT * FROM `%s` WHERE id = :id', $context['table']), [':id' => $newId]);
                $row = $insertedRow[0] ?? null;
                $message = 'Record created.';
            }
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('DM save failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Unable to save record.']);
            return;
        }
        echo json_encode(['success' => true, 'message' => $message, 'row' => $row]);
    }

    public function datamodeldelete() {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            $payload = $this->runData['request']->post ?? [];
        }
        $controllerId = (int)($payload['controller_id'] ?? 0);
        if ($controllerId <= 0 && !empty($payload['controller_uid'])) {
            $controllerId = $this->resolveControllerIdByUid($payload['controller_uid']);
        }
        $rowId = (int)($payload['row_id'] ?? 0);
        if ($controllerId <= 0 || $rowId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            return;
        }
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() !== 'system_admin') {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            return;
        }
        try {
            $context = $this->resolveDataModelContext($controllerId);
            $microservice = $context['microservice'] ?? [];
            $scope = $microservice['s_scope'] ?? 'platform';
            $isSaas = in_array($scope, ['workspace','app','member_org'], true);
            $spaceId = $isSaas ? (int)($this->runData['route']['space_id'] ?? 0) : 0;
            $where = ['id' => $rowId];
            if ($isSaas && $spaceId > 0) {
                $where['space_id'] = $spaceId;
            }
            $this->runData['db']->delete($context['table'], $where);
        } catch (\Throwable $e) {
            $this->errorHandler->reportError('DM delete failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Unable to delete record.']);
            return;
        }
        echo json_encode(['success' => true, 'message' => 'Record deleted.']);
    }

    private function hydrateDataModelContext(array $controller, array $microservice): void {
        $isDataModel = strtoupper($controller['s_type'] ?? '') === 'DM';
        $this->runData['data']['is_data_model'] = $isDataModel;
        if (!$isDataModel) {
            return;
        }
        $service = $this->getSchemaService();
        $controllerId = (int)$controller['id'];
        try {
            $fields = $service->listFields($controllerId);
        } catch (\Throwable $e) {
            $fields = [];
            $this->errorHandler->reportError('Unable to fetch data model fields: ' . $e->getMessage());
        }
        foreach ($fields as &$fieldRow) {
            $fieldRow['is_indexed'] = $this->dataFieldIsIndexed($fieldRow);
        }
        unset($fieldRow);
        try {
            $fieldTypes = $this->runData['db']->select('s_data_field_type', ['livestatus' => 1], true, ['s_description' => 'ASC']);
        } catch (\Throwable $e) {
            $fieldTypes = [];
        }
        foreach ($fieldTypes as &$fieldType) {
            $fieldType['meta'] = $service->describeFieldTypeRow($fieldType);
        }
        unset($fieldType);
        $tableName = 'a_' . $controller['s_name'];
        $tableExists = true;
        try {
            $service->ensureControllerTable($controller);
            $columns = $this->runData['db']->query(sprintf('SHOW COLUMNS FROM `%s`', $tableName));
        } catch (\Throwable $e) {
            $tableExists = false;
            $columns = [];
            $this->errorHandler->reportError('Unable to inspect data model table: ' . $e->getMessage());
        }
        $rows = [];
        if ($tableExists) {
            try {
                $rows = $this->runData['db']->query(sprintf('SELECT * FROM `%s` ORDER BY id DESC LIMIT 25', $tableName));
            } catch (\Throwable $e) {
                $rows = [];
            }
        }
        $this->runData['data']['dm'] = [
            'controller_id' => $controllerId,
            'table' => $tableName,
            'table_exists' => $tableExists,
            'fields' => $fields,
            'field_types' => $fieldTypes,
            'columns' => $columns,
            'rows' => $rows,
            'can_delete_records' => ((int)($this->runData['entity']['id'] ?? 0) === 1),
        ];
    }

    private function resolveDataModelContext(int $controllerId): array {
        $service = $this->getSchemaService();
        $controller = $service->resolveController($controllerId);
        $tableName = 'a_' . $controller['s_name'];
        try {
            $service->ensureControllerTable($controller);
            $columnsResult = $this->runData['db']->query(sprintf('SHOW COLUMNS FROM `%s`', $tableName));
        } catch (\Throwable $e) {
            throw new \RuntimeException('Data table not found for this controller.');
        }
        $columns = [];
        foreach ($columnsResult as $column) {
            $columns[$column['Field']] = $column;
        }
        if (empty($columns)) {
            $columns = $this->buildColumnsFromSchema($controller, $service);
        }
        if (empty($columns)) {
            throw new \RuntimeException('No columns discovered for this data model.');
        }
        $microserviceRows = $this->runData['db']->select('s_ms', ['id' => $controller['s_ms_id']], true);
        $microservice = $microserviceRows[0] ?? [];
        return [
            'controller' => $controller,
            'microservice' => $microservice,
            'table' => $tableName,
            'columns' => $columns,
            'system_columns' => $service->getSystemColumns(),
        ];
    }

    private function buildColumnsFromSchema(array $controller, DataSchemaService $service): array {
        $columns = [];
        foreach ($service->getSystemColumnMetadata() as $meta) {
            $columns[$meta['Field']] = [
                'Field' => $meta['Field'],
                'Type' => strtolower($meta['Type']),
                'Null' => $meta['Null'],
                'Default' => $meta['Default'],
                'Extra' => $meta['Extra'],
            ];
        }
        try {
            $fields = $service->listFields((int)($controller['id'] ?? 0));
        } catch (\Throwable $e) {
            $fields = [];
        }
        foreach ($fields as $fieldRow) {
            if (empty($fieldRow['s_field_name'])) {
                continue;
            }
            $definition = $service->buildSqlFromFieldRow($fieldRow);
            if (!$definition) {
                continue;
            }
            $columns[$fieldRow['s_field_name']] = [
                'Field' => $fieldRow['s_field_name'],
                'Type' => strtolower($definition['definition']),
                'Null' => $definition['nullable'] ? 'YES' : 'NO',
                'Default' => null,
                'Extra' => '',
            ];
        }
        return $columns;
    }

    private function filterRecordValues(array $columns, array $systemColumns, array $values): array {
        $filtered = [];
        $systemLookup = array_map('strtolower', $systemColumns);
        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (!isset($columns[$key])) {
                continue;
            }
            if (in_array(strtolower($key), $systemLookup, true)) {
                continue;
            }
            $columnMeta = $columns[$key];
            if (is_array($value)) {
                $value = json_encode(array_values($value));
            }
            if ($value === '' && strtoupper($columnMeta['Null'] ?? 'NO') === 'YES') {
                $filtered[$key] = null;
            } else {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }

    private function dataFieldIsIndexed(array $field): bool {
        if (empty($field['s_definition'])) {
            return false;
        }
        $decoded = json_decode($field['s_definition'], true);
        if (!is_array($decoded)) {
            return false;
        }
        return !empty($decoded['index']);
    }

    private function getControllerDefinition(int $controllerId): array {
        $rows = $this->runData['db']->select('s_mscontroller', ['id' => $controllerId], true);
        if (count($rows) !== 1) {
            return [];
        }
        $definition = json_decode($rows[0]['s_definition'] ?? '', true);
        return is_array($definition) ? $definition : [];
    }

    private function saveControllerDefinition(int $controllerId, array $definition): void {
        $this->runData['db']->update('s_mscontroller', [
            's_definition' => json_encode($definition, JSON_UNESCAPED_SLASHES),
        ], ['id' => $controllerId]);
    }

    private function getControllerSchemaBeta(int $controllerId): array {
        $definition = $this->getControllerDefinition($controllerId);
        $schema = $definition['schema_branch_beta'] ?? [];
        if (!is_array($schema)) {
            return [];
        }
        if (empty($schema['fields']) || !is_array($schema['fields'])) {
            $schema['fields'] = [];
        }
        return $schema;
    }

    private function clearSchemaBeta(int $controllerId): void {
        $definition = $this->getControllerDefinition($controllerId);
        if (isset($definition['schema_branch_beta'])) {
            unset($definition['schema_branch_beta']);
            $this->saveControllerDefinition($controllerId, $definition);
        }
    }

    private function initSchemaBetaFromLive(int $controllerId): void {
        $fields = [];
        try {
            $fields = $this->runData['db']->select('s_data_field', ['s_mscontroller_id' => $controllerId], true, ['s_sort_order' => 'ASC']);
        } catch (\Throwable $e) {
            $fields = [];
        }
        $betaFields = [];
        foreach ($fields as $field) {
            $betaFields[] = [
                'id' => (int)$field['id'],
                's_mscontroller_id' => $controllerId,
                's_field_group_id' => $field['s_field_group_id'] ?? null,
                's_sort_order' => $field['s_sort_order'] ?? null,
                's_field_name' => $field['s_field_name'] ?? '',
                's_field_label' => $field['s_field_label'] ?? '',
                's_help_text' => $field['s_help_text'] ?? '',
                's_field_type_id' => $field['s_field_type_id'] ?? null,
                's_is_nullable' => $field['s_is_nullable'] ?? 1,
                's_definition' => $field['s_definition'] ?? null,
            ];
        }
        $definition = $this->getControllerDefinition($controllerId);
        $definition['schema_branch_beta'] = [
            'fields' => $betaFields,
            'next_temp_id' => -1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $this->saveControllerDefinition($controllerId, $definition);
    }

    private function addSchemaFieldBeta(int $controllerId, array $payload): array {
        $betaSchema = $this->getControllerSchemaBeta($controllerId);
        if (empty($betaSchema)) {
            return ['success' => false, 'message' => 'Create a beta schema before adding fields.'];
        }
        $label = trim((string)($payload['label'] ?? ''));
        if ($label === '') {
            return ['success' => false, 'message' => 'Field label is required.'];
        }
        $fieldName = $this->sanitizeSchemaFieldName($payload['field_name'] ?? $label);
        if ($fieldName === '') {
            return ['success' => false, 'message' => 'Field name is invalid.'];
        }
        $fieldTypeId = (int)($payload['field_type_id'] ?? 0);
        $fieldTypeRows = $this->runData['db']->select('s_data_field_type', ['id' => $fieldTypeId], true);
        if (count($fieldTypeRows) !== 1) {
            return ['success' => false, 'message' => 'Invalid field type.'];
        }
        $systemColumns = $this->getSchemaService()->getSystemColumns();
        foreach ($systemColumns as $column) {
            if (strtolower($column) === strtolower($fieldName)) {
                return ['success' => false, 'message' => 'The specified field name is reserved.'];
            }
        }
        foreach ($betaSchema['fields'] as $field) {
            if (strtolower($field['s_field_name'] ?? '') === strtolower($fieldName)) {
                return ['success' => false, 'message' => 'Field name already exists in beta schema.'];
            }
        }
        $tempId = (int)($betaSchema['next_temp_id'] ?? -1);
        if ($tempId >= 0) {
            $tempId = -1;
        }
        $betaSchema['next_temp_id'] = $tempId - 1;
        $nullable = array_key_exists('nullable', $payload) ? (int)(!empty($payload['nullable'])) : 1;
        $definition = $this->buildFieldDefinitionPayload($payload);
        $betaSchema['fields'][] = [
            'id' => $tempId,
            's_mscontroller_id' => $controllerId,
            's_field_group_id' => $payload['field_group_id'] ?? null,
            's_sort_order' => $payload['sort_order'] ?? null,
            's_field_name' => $fieldName,
            's_field_label' => $label,
            's_help_text' => $payload['help_text'] ?? '',
            's_field_type_id' => $fieldTypeId,
            's_is_nullable' => $nullable ? 1 : 0,
            's_definition' => $definition,
        ];
        $betaSchema['updated_at'] = date('Y-m-d H:i:s');
        $definitionRoot = $this->getControllerDefinition($controllerId);
        $definitionRoot['schema_branch_beta'] = $betaSchema;
        $this->saveControllerDefinition($controllerId, $definitionRoot);
        return ['success' => true, 'message' => 'Field added to beta schema.', 'field_id' => $tempId];
    }

    private function updateSchemaFieldBeta(int $controllerId, int $fieldId, array $payload): array {
        $betaSchema = $this->getControllerSchemaBeta($controllerId);
        if (empty($betaSchema)) {
            return ['success' => false, 'message' => 'Create a beta schema before updating fields.'];
        }
        $index = null;
        foreach ($betaSchema['fields'] as $i => $field) {
            if ((int)($field['id'] ?? 0) === $fieldId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return ['success' => false, 'message' => 'Field not found in beta schema.'];
        }
        $current = $betaSchema['fields'][$index];
        $payload = $this->mergeDefinitionDefaults($payload, $current['s_definition'] ?? null);
        $label = trim((string)($payload['label'] ?? $current['s_field_label'] ?? ''));
        if ($label === '') {
            return ['success' => false, 'message' => 'Field label is required.'];
        }
        $fieldName = $this->sanitizeSchemaFieldName($payload['field_name'] ?? $current['s_field_name'] ?? '');
        if ($fieldName === '') {
            $fieldName = $current['s_field_name'] ?? '';
        }
        foreach ($betaSchema['fields'] as $i => $field) {
            if ($i === $index) {
                continue;
            }
            if (strtolower($field['s_field_name'] ?? '') === strtolower($fieldName)) {
                return ['success' => false, 'message' => 'Field name already exists in beta schema.'];
            }
        }
        $fieldTypeId = (int)($payload['field_type_id'] ?? $current['s_field_type_id'] ?? 0);
        $fieldTypeRows = $this->runData['db']->select('s_data_field_type', ['id' => $fieldTypeId], true);
        if (count($fieldTypeRows) !== 1) {
            return ['success' => false, 'message' => 'Invalid field type.'];
        }
        $nullable = array_key_exists('nullable', $payload)
            ? (int)(!empty($payload['nullable']))
            : (int)($current['s_is_nullable'] ?? 1);
        $definition = $this->buildFieldDefinitionPayload($payload);
        $betaSchema['fields'][$index] = [
            'id' => $current['id'],
            's_mscontroller_id' => $controllerId,
            's_field_group_id' => $payload['field_group_id'] ?? $current['s_field_group_id'] ?? null,
            's_sort_order' => $payload['sort_order'] ?? $current['s_sort_order'] ?? null,
            's_field_name' => $fieldName,
            's_field_label' => $label,
            's_help_text' => $payload['help_text'] ?? $current['s_help_text'] ?? '',
            's_field_type_id' => $fieldTypeId,
            's_is_nullable' => $nullable ? 1 : 0,
            's_definition' => $definition,
        ];
        $betaSchema['updated_at'] = date('Y-m-d H:i:s');
        $definitionRoot = $this->getControllerDefinition($controllerId);
        $definitionRoot['schema_branch_beta'] = $betaSchema;
        $this->saveControllerDefinition($controllerId, $definitionRoot);
        return ['success' => true, 'message' => 'Field updated in beta schema.'];
    }

    private function deleteSchemaFieldBeta(int $controllerId, int $fieldId): array {
        $betaSchema = $this->getControllerSchemaBeta($controllerId);
        if (empty($betaSchema)) {
            return ['success' => false, 'message' => 'Create a beta schema before deleting fields.'];
        }
        $nextFields = [];
        $removed = false;
        foreach ($betaSchema['fields'] as $field) {
            if ((int)($field['id'] ?? 0) === $fieldId) {
                $removed = true;
                continue;
            }
            $nextFields[] = $field;
        }
        if (!$removed) {
            return ['success' => false, 'message' => 'Field not found in beta schema.'];
        }
        $betaSchema['fields'] = $nextFields;
        $betaSchema['updated_at'] = date('Y-m-d H:i:s');
        $definitionRoot = $this->getControllerDefinition($controllerId);
        $definitionRoot['schema_branch_beta'] = $betaSchema;
        $this->saveControllerDefinition($controllerId, $definitionRoot);
        return ['success' => true, 'message' => 'Field removed from beta schema.'];
    }

    private function mergeSchemaBetaIntoLive(int $controllerId): array {
        $betaSchema = $this->getControllerSchemaBeta($controllerId);
        if (empty($betaSchema)) {
            return ['success' => false, 'message' => 'No beta schema found to merge.'];
        }
        $liveFields = [];
        try {
            $liveFields = $this->runData['db']->select('s_data_field', ['s_mscontroller_id' => $controllerId], true);
        } catch (\Throwable $e) {
            $liveFields = [];
        }
        $liveIndex = [];
        foreach ($liveFields as $field) {
            $liveIndex[(int)$field['id']] = $field;
        }
        $betaIds = [];
        $service = $this->getSchemaService();
        $summary = ['added' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => []];
        foreach ($betaSchema['fields'] as $betaField) {
            $betaId = (int)($betaField['id'] ?? 0);
            if ($betaId > 0) {
                $betaIds[] = $betaId;
            }
            $payload = $this->buildSchemaPayloadFromField($betaField);
            if ($betaId <= 0 || !isset($liveIndex[$betaId])) {
                $result = $service->addField($controllerId, $payload, (int)($this->runData['entity']['id'] ?? 0));
                if (!empty($result['success'])) {
                    $summary['added']++;
                } else {
                    $summary['errors'][] = $result['message'] ?? 'Add failed';
                }
                continue;
            }
            $liveField = $liveIndex[$betaId];
            if ($this->fieldsEqual($liveField, $betaField)) {
                continue;
            }
            $result = $service->updateField($controllerId, $betaId, $payload);
            if (!empty($result['success'])) {
                $summary['updated']++;
            } else {
                $summary['errors'][] = $result['message'] ?? 'Update failed';
            }
        }
        foreach ($liveIndex as $liveId => $liveField) {
            if (!in_array($liveId, $betaIds, true)) {
                $result = $service->deleteField($controllerId, (int)$liveId);
                if (!empty($result['success'])) {
                    $summary['deleted']++;
                } else {
                    $summary['errors'][] = $result['message'] ?? 'Delete failed';
                }
            }
        }
        if (!empty($summary['errors'])) {
            return ['success' => false, 'message' => 'Some schema changes failed to apply.', 'summary' => $summary];
        }
        $this->clearSchemaBeta($controllerId);
        return ['success' => true, 'summary' => $summary];
    }

    private function buildSchemaPayloadFromField(array $field): array {
        $meta = $this->extractDefinitionMeta($field['s_definition'] ?? null);
        $payload = [
            'label' => $field['s_field_label'] ?? '',
            'field_name' => $field['s_field_name'] ?? '',
            'field_type_id' => $field['s_field_type_id'] ?? null,
            'help_text' => $field['s_help_text'] ?? '',
            'nullable' => (int)($field['s_is_nullable'] ?? 1),
            'field_group_id' => $field['s_field_group_id'] ?? null,
            'sort_order' => $field['s_sort_order'] ?? null,
            'create_index' => !empty($meta['index']) ? 1 : 0,
        ];
        foreach (['length', 'precision', 'scale'] as $key) {
            if (isset($meta[$key])) {
                $payload[$key] = $meta[$key];
            }
        }
        if (!empty($meta['options'])) {
            $payload['options'] = $meta['options'];
        }
        if (!empty($meta['related_table'])) {
            $payload['foreign_table'] = $meta['related_table'];
        }
        if (!empty($meta['related_field'])) {
            $payload['foreign_field'] = $meta['related_field'];
        }
        if (!empty($meta['source'])) {
            $payload['source'] = $meta['source'];
        }
        if (!empty($meta['custom_sql'])) {
            $payload['custom_sql'] = $meta['custom_sql'];
        }
        return $payload;
    }

    private function fieldsEqual(array $liveField, array $betaField): bool {
        $keys = [
            's_field_name',
            's_field_label',
            's_help_text',
            's_field_type_id',
            's_is_nullable',
            's_field_group_id',
            's_sort_order',
        ];
        foreach ($keys as $key) {
            $liveVal = $liveField[$key] ?? null;
            $betaVal = $betaField[$key] ?? null;
            if ((string)$liveVal !== (string)$betaVal) {
                return false;
            }
        }
        $liveDef = $this->normalizeDefinitionValue($liveField['s_definition'] ?? null);
        $betaDef = $this->normalizeDefinitionValue($betaField['s_definition'] ?? null);
        return $liveDef === $betaDef;
    }

    private function normalizeDefinitionValue($definition): ?array {
        if (is_array($definition)) {
            return $definition;
        }
        if (!$definition) {
            return null;
        }
        $decoded = json_decode((string)$definition, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function extractDefinitionMeta($definition): array {
        $decoded = $this->normalizeDefinitionValue($definition);
        return is_array($decoded) ? $decoded : [];
    }

    private function mergeDefinitionDefaults(array $payload, $definition): array {
        $decoded = $this->normalizeDefinitionValue($definition);
        if (!is_array($decoded)) {
            return $payload;
        }
        foreach (['length', 'precision', 'scale'] as $key) {
            if (!array_key_exists($key, $payload) && isset($decoded[$key])) {
                $payload[$key] = $decoded[$key];
            }
        }
        if (!array_key_exists('create_index', $payload) && isset($decoded['index'])) {
            $payload['create_index'] = (bool)$decoded['index'];
        }
        foreach (['options', 'related_table', 'related_field', 'source', 'custom_sql'] as $key) {
            if (!array_key_exists($key, $payload) && isset($decoded[$key])) {
                $payload[$key] = $decoded[$key];
            }
        }
        return $payload;
    }

    private function buildFieldDefinitionPayload(array $payload): ?string {
        $meta = [];
        if (isset($payload['length']) && $payload['length'] !== '') {
            $meta['length'] = (int)$payload['length'];
        }
        if (isset($payload['precision']) && $payload['precision'] !== '') {
            $meta['precision'] = (int)$payload['precision'];
        }
        if (isset($payload['scale']) && $payload['scale'] !== '') {
            $meta['scale'] = (int)$payload['scale'];
        }
        if (isset($payload['create_index'])) {
            $meta['index'] = !empty($payload['create_index']);
        }
        if (!empty($payload['options']) && is_array($payload['options'])) {
            $meta['options'] = $this->normalizeOptions($payload['options']);
        }
        if (!empty($payload['foreign_table'])) {
            $meta['related_table'] = $payload['foreign_table'];
        }
        if (!empty($payload['foreign_field'])) {
            $meta['related_field'] = $payload['foreign_field'];
        }
        if (!empty($payload['source'])) {
            $meta['source'] = $payload['source'];
        }
        if (!empty($payload['custom_sql'])) {
            $meta['custom_sql'] = $payload['custom_sql'];
        }
        return !empty($meta) ? json_encode($meta) : null;
    }

    private function normalizeOptions(array $raw): array {
        $options = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $value = trim($item);
                if ($value === '') {
                    continue;
                }
                $options[] = ['value' => $value, 'label' => $value];
                continue;
            }
            if (is_array($item)) {
                $value = trim((string)($item['value'] ?? $item[0] ?? ''));
                if ($value === '') {
                    continue;
                }
                $label = trim((string)($item['label'] ?? $item['text'] ?? $item[1] ?? $value));
                $options[] = ['value' => $value, 'label' => $label];
                continue;
            }
            if (is_object($item) && isset($item->value)) {
                $value = trim((string)$item->value);
                if ($value === '') {
                    continue;
                }
                $label = trim((string)($item->label ?? $value));
                $options[] = ['value' => $value, 'label' => $label];
            }
        }
        return $options;
    }

    private function sanitizeSchemaFieldName(string $input): string {
        $input = strtolower(trim($input));
        $input = preg_replace('/[^a-z0-9_]+/', '_', $input);
        $input = trim($input ?? '', '_');
        return $input ?? '';
    }

    private function locateControllerRecord(string $ref): array {
        if (ctype_digit($ref)) {
            return $this->runData['db']->select('s_mscontroller', ['id' => $ref], true);
        }
        return $this->runData['db']->select('s_mscontroller', ['uid' => $ref], true);
    }

    private function resolveControllerIdByUid(string $uid): int {
        if ($uid === '') {
            return 0;
        }
        $rows = $this->runData['db']->select('s_mscontroller', ['uid' => $uid], true);
        return (int)($rows[0]['id'] ?? 0);
    }

    private function resolveUserName($userId): string {
        $userId = (int)$userId;
        if ($userId === 0) {
            return 'System';
        }
        try {
            $rows = $this->runData['db']->select('s_entity', ['id' => $userId, 's_type' => 'U'], true);
        } catch (\Throwable $e) {
            $rows = [];
        }
        if (!empty($rows)) {
            $name = trim((string)($rows[0]['s_name'] ?? ''));
            if ($name !== '') {
                return $name . ' (#' . $userId . ')';
            }
            if (!empty($rows[0]['s_identity'])) {
                return trim((string)$rows[0]['s_identity']) . ' (#' . $userId . ')';
            }
        }
        try {
            $rows = $this->runData['db']->select('s_user', ['id' => $userId], true);
        } catch (\Throwable $e) {
            return 'User #' . $userId;
        }
        if (!empty($rows)) {
            $name = trim(($rows[0]['s_first_name'] ?? '') . ' ' . ($rows[0]['s_last_name'] ?? ''));
            if ($name !== '') {
                return $name . ' (#' . $userId . ')';
            }
            if (!empty($rows[0]['s_display_name'])) {
                return trim((string)$rows[0]['s_display_name']) . ' (#' . $userId . ')';
            }
            if (!empty($rows[0]['s_username'])) {
                return trim((string)$rows[0]['s_username']) . ' (#' . $userId . ')';
            }
        }
        return 'User #' . $userId;
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
        }
        return $entries;
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

    private function snapshotControllerCode(string $content, string $branch = 'live'): void {
        if (!$this->versionService) {
            return;
        }
        $context = $this->resolveControllerVersionContext(
            (string)($this->runData['route']['pathparts'][3] ?? ''),
            (string)($this->runData['route']['pathparts'][4] ?? '')
        );
        if ($context === null) {
            return;
        }
        $itemId = $this->getControllerVersionItemId($context['ms_name'], $context['controller_name'], $branch);
        $this->versionService->snapshot('controller', $itemId, $content, [
            'note' => 'Controller class updated',
        ]);
    }

    private function getControllerVersionItemId(string $ms, string $controller, string $branch = 'live'): string {
        $suffix = $branch === 'beta' ? '@beta' : '';
        return trim($ms, '/') . '/' . trim($controller, '/') . $suffix;
    }

    private function resolveControllerWorkspaceContext(string $msRef, string $controllerRef): ?array {
        $context = $this->resolveControllerVersionContext($msRef, $controllerRef);
        if ($context === null) {
            return null;
        }

        $msRows = $this->runData['db']->select('s_ms', ['s_name' => $context['ms_name']], true);
        if (count($msRows) !== 1) {
            return null;
        }
        $microservice = $msRows[0];

        $controllerRows = $this->runData['db']->select('s_mscontroller', [
            's_ms_id' => $microservice['id'],
            's_name' => $context['controller_name'],
        ], true);
        if (count($controllerRows) !== 1) {
            return null;
        }
        $controller = $controllerRows[0];

        $branch = $this->branchService->resolveEditorBranch();
        $filePath = $this->branchService->getControllerFilePath($context['ms_name'], $context['controller_name'], $branch, true);
        $code = is_file($filePath) ? (string)file_get_contents($filePath) : '';

        $routes = $this->runData['db']->select('s_msroute', ['s_ms_id' => $microservice['id']], true, ['s_name' => 'ASC']);
        $controllers = $this->runData['db']->select('s_mscontroller', ['s_ms_id' => $microservice['id']], true, ['s_name' => 'ASC']);
        $dmRows = array_values(array_filter($controllers, function ($row) use ($controller) {
            return strtoupper((string)($row['s_type'] ?? '')) === 'DM' && (int)($row['id'] ?? 0) !== (int)($controller['id'] ?? 0);
        }));

        $itemId = $this->getControllerVersionItemId($context['ms_name'], $context['controller_name'], $branch);
        $versions = $this->versionService->listVersions('controller', $itemId);

        return [
            'microservice' => $microservice,
            'controller' => $controller,
            'branch' => $branch,
            'file_path' => $filePath,
            'code' => $code,
            'routes' => $routes,
            'controllers' => $controllers,
            'data_models' => $dmRows,
            'versions' => array_slice($versions, 0, 5),
        ];
    }

    private function buildControllerAgentContextPayload(array $workspace): array {
        $microservice = $workspace['microservice'] ?? [];
        $controller = $workspace['controller'] ?? [];
        $routes = $workspace['routes'] ?? [];
        $controllers = $workspace['controllers'] ?? [];
        $dataModels = $workspace['data_models'] ?? [];
        $versions = $workspace['versions'] ?? [];
        $code = (string)($workspace['code'] ?? '');

        return [
            'microservice' => [
                'id' => (int)($microservice['id'] ?? 0),
                'uid' => (string)($microservice['uid'] ?? ''),
                'name' => (string)($microservice['s_name'] ?? ''),
                'scope' => (string)($microservice['s_scope'] ?? ''),
                'description' => (string)($microservice['s_description'] ?? ''),
            ],
            'controller' => [
                'id' => (int)($controller['id'] ?? 0),
                'uid' => (string)($controller['uid'] ?? ''),
                'name' => (string)($controller['s_name'] ?? ''),
                'type' => (string)($controller['s_type'] ?? ''),
                'description' => (string)($controller['s_description'] ?? ''),
                'source_file' => (string)($controller['s_source_file'] ?? ''),
                'class_name' => (string)($controller['s_class_name'] ?? ''),
                'branch' => (string)($workspace['branch'] ?? 'live'),
                'file_path' => (string)($workspace['file_path'] ?? ''),
                'code_size' => strlen($code),
            ],
            'related_routes' => array_map(function ($row) {
                return [
                    'id' => (int)($row['id'] ?? 0),
                    'uid' => (string)($row['uid'] ?? ''),
                    'name' => (string)($row['s_name'] ?? ''),
                    'type' => (string)($row['s_type'] ?? ''),
                    'scope' => (string)($row['s_entity_scope'] ?? ''),
                ];
            }, array_slice($routes, 0, 12)),
            'related_controllers' => array_map(function ($row) {
                return [
                    'id' => (int)($row['id'] ?? 0),
                    'uid' => (string)($row['uid'] ?? ''),
                    'name' => (string)($row['s_name'] ?? ''),
                    'type' => (string)($row['s_type'] ?? ''),
                ];
            }, array_slice($controllers, 0, 12)),
            'data_models' => array_map(function ($row) {
                return [
                    'id' => (int)($row['id'] ?? 0),
                    'uid' => (string)($row['uid'] ?? ''),
                    'name' => (string)($row['s_name'] ?? ''),
                    'description' => (string)($row['s_description'] ?? ''),
                ];
            }, array_slice($dataModels, 0, 8)),
            'recent_versions' => array_map(function ($row) {
                return [
                    'id' => (string)($row['id'] ?? ''),
                    'timestamp' => (int)($row['timestamp'] ?? 0),
                    'user' => (string)($row['user'] ?? ''),
                    'note' => (string)($row['note'] ?? ''),
                ];
            }, $versions),
            'code_excerpt' => $this->summarizeControllerCode($code),
        ];
    }

    private function summarizeControllerCode(string $code): string {
        $code = trim($code);
        if ($code === '') {
            return '';
        }
        if (strlen($code) <= 2000) {
            return $code;
        }
        return substr($code, 0, 2000) . "\n\n// ... truncated for agent context";
    }

    private function buildControllerAgentPlan(string $task, string $scope, array $workspace): array {
        $taskLower = strtolower($task);
        $controller = $workspace['controller'] ?? [];
        $microservice = $workspace['microservice'] ?? [];
        $branch = (string)($workspace['branch'] ?? 'live');
        $risks = [];
        $steps = [
            'Inspect the current controller code and identify the exact method/class areas affected by the request.',
            'Review related routes and nearby data models to understand how this controller is invoked and what data it touches.',
            'Draft the code changes in the current branch and verify the implementation remains consistent with RAD controller conventions.',
            'Run PHP lint on the controller file and create a version snapshot after successful review.',
        ];
        $focus = ['controller_code'];
        $suggestedFiles = [
            [
                'path' => (string)($workspace['file_path'] ?? ''),
                'role' => 'primary',
                'reason' => 'Current controller source file',
            ],
        ];

        if ($scope === 'controller_routes' || str_contains($taskLower, 'route')) {
            $focus[] = 'related_routes';
            $steps[1] = 'Review related routes and nearby data models to understand how this controller is invoked, what payload it receives, and which route contracts may be affected.';
        }
        if ($scope === 'microservice') {
            $focus[] = 'microservice_context';
            $steps[] = 'Review sibling controllers in the same microservicelet for shared patterns before finalizing the implementation plan.';
        }

        if (str_contains($taskLower, 'refactor')) {
            $risks[] = 'Refactors can change method signatures or side effects that related routes depend on.';
        }
        if (str_contains($taskLower, 'validation')) {
            $steps[2] = 'Add validation close to the input boundary and preserve the existing controller response pattern.';
            $risks[] = 'Validation changes can affect existing callers if error handling format changes.';
        }
        if (str_contains($taskLower, 'crud')) {
            $steps[2] = 'Design or extend CRUD-oriented methods in the controller and check whether related data models already provide the required schema.';
            $focus[] = 'data_models';
        }
        if ($branch === 'live') {
            $risks[] = 'You are on the live branch, so any accepted change affects production-facing code immediately.';
        } else {
            $risks[] = 'You are on the beta branch; remember to merge after validation if the result is approved.';
        }

        $relatedRouteNames = array_values(array_filter(array_map(function ($row) {
            return trim((string)($row['s_name'] ?? ''));
        }, $workspace['routes'] ?? [])));
        $relatedDmNames = array_values(array_filter(array_map(function ($row) {
            return trim((string)($row['s_name'] ?? ''));
        }, $workspace['data_models'] ?? [])));
        $architectureRules = $this->getControllerAgentArchitectureGuidance();

        return [
            'objective' => $task,
            'scope' => $scope,
            'target' => [
                'microservice' => (string)($microservice['s_name'] ?? ''),
                'controller' => (string)($controller['s_name'] ?? ''),
                'branch' => $branch,
            ],
            'summary' => sprintf(
                'Work on controller `%s` inside microservicelet `%s`, using the %s branch, and prepare a safe implementation plan before any code change.',
                (string)($controller['s_name'] ?? ''),
                (string)($microservice['s_name'] ?? ''),
                strtoupper($branch)
            ),
            'focus' => $focus,
            'steps' => array_values(array_unique($steps)),
            'suggested_files' => $suggestedFiles,
            'architecture_rules' => $architectureRules,
            'related_routes' => array_slice($relatedRouteNames, 0, 8),
            'related_data_models' => array_slice($relatedDmNames, 0, 8),
            'risks' => array_values(array_unique($risks)),
            'checks' => [
                'php -l on the controller file',
                'review controller diff before apply',
                'create version snapshot after successful change',
            ],
            'next_actions' => [
                'Generate a patch proposal for the current controller file',
                'Review the proposed diff before applying',
            ],
        ];
    }

    private function generateControllerAgentPatch(string $task, string $scope, array $workspace): array {
        $context = $this->buildControllerAgentContextPayload($workspace);
        $currentContent = (string)($workspace['code'] ?? '');
        $microserviceName = (string)($workspace['microservice']['s_name'] ?? '');
        $controllerName = (string)($workspace['controller']['s_name'] ?? '');
        $className = $this->resolveControllerClassName($workspace['controller'] ?? []);

        $prompt = "Task:\n{$task}\n\n";
        $prompt .= "Scope: {$scope}\n";
        $prompt .= "Microservicelet: {$microserviceName}\n";
        $prompt .= "Controller: {$controllerName}\n";
        $prompt .= "PHP Class Name: {$className}\n\n";
        $prompt .= "RAD Architecture Guidance:\n" . $this->getControllerAgentArchitecturePrompt() . "\n\n";
        $prompt .= "Context Summary:\n" . json_encode([
            'related_routes' => $context['related_routes'] ?? [],
            'related_controllers' => $context['related_controllers'] ?? [],
            'data_models' => $context['data_models'] ?? [],
            'recent_versions' => $context['recent_versions'] ?? [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $prompt .= "\n\nCurrent controller code:\n" . $currentContent;
        $prompt .= "\n\nInstructions:\n";
        $prompt .= "- Return the full updated PHP controller file only.\n";
        $prompt .= "- Preserve the namespace and class identity unless the task explicitly requires otherwise.\n";
        $prompt .= "- Do not include markdown fences or explanation.\n";
        $prompt .= "- Keep the code compatible with the current RAD controller patterns.\n";
        $prompt .= "- Respect the editable-file boundary rules and only propose intended RAD app file changes.\n";
        $prompt .= "- Use rad/core/app/*.cls.php and rad/vendor/* appropriately instead of reimplementing framework services.\n";
        $prompt .= "- Keep SQL and db->{method} usage aligned with RAD data-access patterns.\n";

        $fallbackUsed = false;
        try {
            $proposedContent = trim($this->requestControllerPatchContent($prompt));
        } catch (\Throwable $e) {
            $fallbackContent = $this->buildLocalControllerPatchFallback($task, $workspace, $currentContent);
            if ($fallbackContent === null) {
                throw $e;
            }
            $fallbackUsed = true;
            $proposedContent = trim($fallbackContent);
        }
        if ($proposedContent === '') {
            throw new \RuntimeException('AI did not return controller content. Increase AI max tokens if this controller is large.');
        }
        if (!str_starts_with($proposedContent, '<?php')) {
            $proposedContent = "<?php\n" . ltrim($proposedContent);
        }

        $warnings = [];
        if ($scope !== 'controller_only') {
            $warnings[] = 'Patch generation is still limited to the current controller file even when broader scope context is selected.';
        }
        if (($workspace['branch'] ?? 'live') === 'live') {
            $warnings[] = 'Advisory: you are generating a patch on the live branch, so applying it will update live code immediately.';
        }
        if ($fallbackUsed) {
            $warnings[] = 'AI was unavailable for this request, so a local controller method starter was generated instead.';
        }

        return [
            'summary' => sprintf(
                'Proposed controller update for `%s/%s` based on the requested task.',
                $microserviceName,
                $controllerName
            ),
            'warnings' => $warnings,
            'file_path' => (string)($workspace['file_path'] ?? ''),
            'original_content' => $currentContent,
            'proposed_content' => $proposedContent,
            'base_checksum' => sha1($currentContent),
            'proposal_token' => $this->storeControllerAgentProposal([
                'content' => $proposedContent,
                'base_checksum' => sha1($currentContent),
                'task' => $task,
                'ms' => $microserviceName,
                'controller' => $controllerName,
                'branch' => (string)($workspace['branch'] ?? 'live'),
            ]),
            'branch' => (string)($workspace['branch'] ?? 'live'),
            'task' => $task,
        ];
    }

    private function requestControllerPatchContent(string $prompt): string {
        $aiConfig = $this->runData['config']['ai'] ?? ($this->runData['config']['rad']['ai'] ?? []);
        $maxTokens = (int)($aiConfig['agent_patch_max_tokens'] ?? $aiConfig['patch_max_tokens'] ?? 1800);
        if ($maxTokens < 512) {
            $maxTokens = 512;
        }

        $client = $this->getAiAssistClient($maxTokens, null, 'coding', 'full');
        try {
            $response = $client->getSuggestion($prompt);
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->logError('Controller patch AI request failed: ' . $e->getMessage());
            }
            throw new \RuntimeException('AI service is currently unavailable for patch generation.');
        }

        $response = trim((string)$response);
        if ($response === '') {
            throw new \RuntimeException('AI returned an empty response.');
        }

        $response = preg_replace('/^```[a-zA-Z0-9]*\s*/', '', $response);
        $response = preg_replace('/```$/', '', $response);
        return ltrim((string)$response);
    }

    private function buildLocalControllerPatchFallback(string $task, array $workspace, string $currentContent): ?string {
        $taskLower = strtolower($task);
        if (!preg_match('/\b(write|add|create|implement)\b/', $taskLower) || !str_contains($taskLower, 'method')) {
            return null;
        }

        if (!preg_match('/\bclass\s+[A-Za-z_][A-Za-z0-9_]*\b/', $currentContent)) {
            return null;
        }

        $methodName = $this->deriveLocalFallbackMethodName($taskLower);
        if ($methodName === '') {
            $methodName = 'generatedHelperMethod';
        }
        if (preg_match('/function\s+' . preg_quote($methodName, '/') . '\s*\(/i', $currentContent)) {
            $methodName .= 'Generated';
        }

        $methodBody = $this->buildLocalFallbackMethodBody($taskLower, $methodName);
        if ($methodBody === '') {
            return null;
        }

        $insertion = "\n    /**\n     * Local fallback method generated because AI patch generation was unavailable.\n     */\n" . $methodBody . "\n";
        $updated = preg_replace('/\n}\s*$/', $insertion . "}\n", $currentContent, 1);
        return is_string($updated) ? $updated : null;
    }

    private function deriveLocalFallbackMethodName(string $taskLower): string {
        if (preg_match('/method\s+to\s+([a-z0-9_ ]+)/i', $taskLower, $matches)) {
            $phrase = trim((string)$matches[1]);
        } elseif (preg_match('/\b(write|add|create|implement)\s+(?:a|an)?\s*method\s+(?:that\s+)?([a-z0-9_ ]+)/i', $taskLower, $matches)) {
            $phrase = trim((string)$matches[2]);
        } else {
            $phrase = '';
        }

        $phrase = preg_replace('/[^a-z0-9 ]+/', ' ', $phrase);
        $phrase = trim((string)$phrase);
        if ($phrase === '') {
            return '';
        }
        $parts = array_values(array_filter(explode(' ', $phrase)));
        $parts = array_slice($parts, 0, 5);
        $method = array_shift($parts);
        foreach ($parts as $part) {
            $method .= ucfirst($part);
        }
        $method = preg_replace('/[^a-zA-Z0-9_]/', '', $method);
        if ($method === '' || ctype_digit(substr($method, 0, 1))) {
            return '';
        }
        return $method;
    }

    private function buildLocalFallbackMethodBody(string $taskLower, string $methodName): string {
        if (str_contains($taskLower, 'random') && str_contains($taskLower, 'name')) {
            return <<<PHP
    public function {$methodName}(): string
    {
        \$names = ['Aarav', 'Diya', 'Ishaan', 'Meera', 'Vivaan'];
        return \$names[array_rand(\$names)];
    }
PHP;
        }

        return <<<PHP
    public function {$methodName}(): string
    {
        return 'TODO: implement {$methodName}.';
    }
PHP;
    }

    private function getControllerAgentArchitectureGuidance(): array {
        return [
            'repo_layout' => [
                'rad/' => 'RAD application code',
                'public_html/' => 'Web/public assets including assets/',
                'rad/theme/' => 'Themes',
                'rad/ms/{ms_name}/' => 'Microservicelet and route logic',
                'rad/data/uploads/{yyyy}/{mm}/{dd}/{file_name}' => 'Private uploads',
                'rad/log/' => 'Logs',
                'rad/data/cache/' => 'Cache',
                'rad/data/tmp/' => 'Runtime artifacts',
            ],
            'storage' => [
                'public_html/assets/{yyyy}/{mm}/{dd}/{file_name}' => 'Public assets',
                "config['dir']['data']/workspaces/{shard}/{normalized_uid}/{yyyy}/{mm}/{dd}/{file_name}" => 'Private workspace uploads',
                "config['dir']['data']/global/{yyyy}/{mm}/{dd}/{file_name}" => 'Private platform uploads',
            ],
            'editable_files' => [
                'rad/ms/{ms_name}/route.{route_name}.php',
                'rad/ms/{ms_name}/route.{route_name}.pagepart.php',
                'rad/ms/{ms_name}/route.{route_name}.prepart.php',
                'rad/ms/{ms_name}/route.{route_name}.postpart.php',
                'rad/ms/{ms_name}/*.cls.php',
                'rad/theme/*.tpl.php',
            ],
            'url_rules' => [
                '{base_url}/{ms_name}/{route_name}/...' => 'Global and platform-scoped routes',
                '{base_url}/{prefix}/{space_slug}/{ms_name}/{route_name}/...' => 'Workspace-scoped routes',
            ],
            'implementation_rules' => [
                'Use rad/core/app/*.cls.php appropriately for feature work.',
                'Use rad/vendor/* when needed.',
                'Keep SQL aligned with db->{method} arguments.',
                'a_ tables are RAD data-model tables with standard system columns such as id, uid, livestatus, versioncode, wf_status, space_id, createdby, createstamp, updatedby, updatestamp.',
            ],
        ];
    }

    private function getControllerAgentArchitecturePrompt(): string {
        return json_encode($this->getControllerAgentArchitectureGuidance(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function lintPhpContent(string $content): array {
        if (!function_exists('exec')) {
            return [
                'ok' => true,
                'status' => 0,
                'skipped' => true,
                'output' => 'PHP lint skipped because shell execution is unavailable in this environment.',
            ];
        }
        $configuredBinary = trim((string)($this->runData['config']['sys']['php_cli_binary'] ?? ''));
        $phpBinary = $configuredBinary !== '' ? $configuredBinary : 'php';
        if ($configuredBinary === '' && PHP_SAPI === 'cli' && defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
            $phpBinary = PHP_BINARY;
        }
        if (PHP_SAPI !== 'cli' && $configuredBinary === '') {
            return [
                'ok' => true,
                'status' => 0,
                'skipped' => true,
                'output' => 'PHP lint skipped in web runtime because no dedicated CLI binary is configured.',
            ];
        }
        $tempDir = rtrim((string)($this->runData['config']['dir']['rad'] ?? sys_get_temp_dir()), '/') . '/data/temp';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }
        $tempFile = tempnam($tempDir, 'rad-controller-lint-');
        if ($tempFile === false) {
            return ['ok' => false, 'output' => 'Unable to allocate temporary file for lint.'];
        }
        $phpFile = $tempFile . '.php';
        @rename($tempFile, $phpFile);
        file_put_contents($phpFile, $content);

        $cmd = escapeshellcmd($phpBinary) . ' -l ' . escapeshellarg($phpFile);
        $output = [];
        $status = 0;
        @exec($cmd . ' 2>&1', $output, $status);
        @unlink($phpFile);

        if ($status === 127 && trim(implode("\n", $output)) === '') {
            return [
                'ok' => true,
                'status' => 0,
                'skipped' => true,
                'output' => 'PHP lint skipped because the PHP CLI binary could not be executed.',
            ];
        }

        return [
            'ok' => $status === 0,
            'status' => $status,
            'output' => trim(implode("\n", $output)),
        ];
    }

    private function storeControllerAgentProposal(array $payload): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $token = bin2hex(random_bytes(16));
        if (!isset($_SESSION['controller_agent_proposals']) || !is_array($_SESSION['controller_agent_proposals'])) {
            $_SESSION['controller_agent_proposals'] = [];
        }
        $_SESSION['controller_agent_proposals'][$token] = [
            'payload' => $payload,
            'created_at' => time(),
        ];
        return $token;
    }

    private function traceControllerCodeSave(string $message, array $context = []): void {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        if (!empty($context)) {
            $line .= ' || ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        $line .= "\n";
        @file_put_contents(sys_get_temp_dir() . '/rad-controller-codesave-trace.log', $line, FILE_APPEND);
    }

    private function readControllerAgentProposal(string $token): ?array {
        if ($token === '') {
            return null;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $entry = $_SESSION['controller_agent_proposals'][$token] ?? null;
        if (!is_array($entry)) {
            return null;
        }
        if ((int)($entry['created_at'] ?? 0) < (time() - 3600)) {
            unset($_SESSION['controller_agent_proposals'][$token]);
            return null;
        }
        return is_array($entry['payload'] ?? null) ? $entry['payload'] : null;
    }

    private function forgetControllerAgentProposal(string $token): void {
        if ($token === '') {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (isset($_SESSION['controller_agent_proposals'][$token])) {
            unset($_SESSION['controller_agent_proposals'][$token]);
        }
    }

    private function resolveControllerVersionContext(string $msRef, string $controllerRef): ?array {
        $msRef = trim($msRef);
        $controllerRef = trim($controllerRef);
        if ($msRef === '' || $controllerRef === '') {
            return null;
        }

        $msRows = $this->runData['db']->select('s_ms', ['uid' => $msRef], true);
        if (count($msRows) !== 1) {
            $msRows = $this->runData['db']->select('s_ms', ['s_name' => $msRef], true);
        }
        if (count($msRows) !== 1) {
            return null;
        }
        $ms = $msRows[0];

        $controllerRows = $this->runData['db']->select('s_mscontroller', [
            's_ms_id' => $ms['id'],
            's_name' => strtolower($controllerRef),
        ], true);
        if (count($controllerRows) !== 1) {
            return null;
        }

        return [
            'ms_name' => (string)($ms['s_name'] ?? ''),
            'controller_name' => (string)($controllerRows[0]['s_name'] ?? ''),
        ];
    }

    private function getControllerFilePath(string $ms, string $controller): string {
        $base = rtrim($this->runData['config']['dir']['ms'], '/');
        return $base . '/' . $ms . '/' . $controller . '.cls.php';
    }

    private function buildControllerRuntimeMeta(array $controller, array $microservice, string $branch = 'live'): array {
        $type = strtoupper((string)($controller['s_type'] ?? 'BL'));
        $meta = [
            'type' => $type,
            'internal_name' => (string)($controller['s_name'] ?? ''),
            'source_file' => '',
            'class_name' => '',
            'file_path' => '',
            'resolved_path' => '',
            'branch' => $branch,
            'file_exists' => false,
            'table_name' => '',
        ];

        if ($type === 'DM') {
            $meta['table_name'] = 'a_' . (string)($controller['s_name'] ?? '');
            return $meta;
        }

        $meta['source_file'] = (string)($controller['s_source_file'] ?? '');
        if ($meta['source_file'] === '') {
            $meta['source_file'] = basename($this->branchService->getControllerFilePath(
                (string)($microservice['s_name'] ?? ''),
                (string)($controller['s_name'] ?? ''),
                'live',
                true
            ));
        }
        $meta['class_name'] = trim((string)($controller['s_class_name'] ?? '')) ?: ucfirst((string)($controller['s_name'] ?? ''));
        $meta['file_path'] = $this->branchService->getControllerFilePath(
            (string)($microservice['s_name'] ?? ''),
            (string)($controller['s_name'] ?? ''),
            'live',
            true
        );
        $meta['resolved_path'] = $this->branchService->getControllerFilePath(
            (string)($microservice['s_name'] ?? ''),
            (string)($controller['s_name'] ?? ''),
            $branch,
            true
        );
        $meta['file_exists'] = is_file($meta['resolved_path']);

        return $meta;
    }

    private function resolveControllerClassName(array $controllerRow): string {
        $className = trim((string)($controllerRow['s_class_name'] ?? ''));
        if ($className !== '') {
            return preg_replace('/[^A-Za-z0-9_]/', '', $className);
        }
        return ucfirst((string)($controllerRow['s_name'] ?? ''));
    }

    private function sanitizeVersionId(?string $value): string {
        $value = strtolower(trim((string)$value));
        return preg_replace('/[^a-z0-9_]+/', '', $value);
    }

    private function logControllerActivity(string $action, int $controllerId, int $msId, string $name, string $description, string $type): void {
        $db = $this->runData['db'] ?? null;
        if (!$db) {
            return;
        }
        $controllerRows = $db->select('s_mscontroller', ['id' => $controllerId], true);
        $msRows = $db->select('s_ms', ['id' => $msId], true);
        if (empty($controllerRows[0]) || empty($msRows[0])) {
            return;
        }
        $msRow = $msRows[0];
        $controllerRow = $controllerRows[0];
        $actorId = (int)($this->runData['entity']['id'] ?? 0);
        $actorName = $this->runData['entity']['fullname'] ?? $this->runData['entity']['username'] ?? '';

        $context = [
            '{action}' => $action,
            '{controller_id}' => (string)$controllerId,
            '{controller_uid}' => $controllerRow['uid'] ?? '',
            '{controller_name}' => $controllerRow['s_name'] ?? $name,
            '{controller_description}' => $description,
            '{controller_type}' => $type,
            '{ms_id}' => (string)$msId,
            '{ms_uid}' => $msRow['uid'] ?? '',
            '{ms_name}' => $msRow['s_name'] ?? '',
            '{actor}' => $actorName,
            '{timestamp}' => date('Y-m-d H:i:s T'),
        ];
        $message = $this->renderTemplateWithFallback('', $context, sprintf('Controller %s: %s', $action, $context['{controller_name}']));

        try {
            $activitySvc = new \Core\Sys\ActivityService($db);
            $activitySvc->log([
                's_actor_id' => $actorId ?: null,
                's_object_type' => 'controller',
                's_object_id' => $controllerId,
                's_action' => $action,
                's_message' => $message,
                's_payload' => [
                    'controller_id' => $controllerId,
                    'controller_uid' => $controllerRow['uid'] ?? '',
                    'controller_name' => $context['{controller_name}'],
                    'controller_type' => $type,
                    'ms_id' => $msId,
                    'ms_uid' => $msRow['uid'] ?? '',
                    'ms_name' => $context['{ms_name}'],
                    'actor' => $actorName,
                    'description' => $description,
                    'timestamp' => $context['{timestamp}'],
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore activity failures
        }

        try {
            $notifSvc = $this->runData['notificationService'] ?? new \Core\Sys\NotificationService($db);
            if ($notifSvc instanceof \Core\Sys\NotificationService) {
                $notifSvc->logGlobalEvent($message, [
                    'event_type' => 'controller_' . $action,
                    'created_by' => $actorId ?: null,
                    'link' => $this->runData['route']['rad_admin_url'] . '/controller/detail/' . ($controllerRow['uid'] ?? $controllerId),
                    'metadata' => [
                        'controller_id' => $controllerId,
                        'controller_uid' => $controllerRow['uid'] ?? '',
                        'controller_name' => $context['{controller_name}'],
                        'ms_id' => $msId,
                        'ms_uid' => $msRow['uid'] ?? '',
                        'ms_name' => $context['{ms_name}'],
                        'actor' => $actorName,
                        'timestamp' => $context['{timestamp}'],
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            // ignore notification failures
        }
    }

    private function renderTemplateWithFallback(string $template, array $context, string $fallback): string {
        $tpl = trim($template);
        if ($tpl !== '') {
            $rendered = strtr($tpl, $context);
            if ($rendered !== '') {
                return $rendered;
            }
        }
        return $fallback;
    }

    private function fetchWorkflowBinding(array $controller): ?array {
        $definitionRaw = $controller['s_definition'] ?? null;
        if (!$definitionRaw) {
            return null;
        }
        $decoded = json_decode($definitionRaw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $binding = $decoded['workflow'] ?? $decoded['workflow_binding'] ?? null;
        if (!$binding && isset($decoded['workflow_id'])) {
            $binding = ['workflow_id' => $decoded['workflow_id']];
        }
        if (!is_array($binding) && is_numeric($binding)) {
            $binding = ['workflow_id' => (int)$binding];
        }
        if (!is_array($binding)) {
            return null;
        }
        if (!empty($binding['workflow_id'])) {
            try {
                $stateRows = $this->runData['db']->select('s_wf_state', ['id' => (int)$binding['workflow_id']], true);
                if (!empty($stateRows[0]['s_name'])) {
                    $binding['workflow_name'] = $stateRows[0]['s_name'];
                }
            } catch (\Throwable $e) {
                // ignore lookup issues
            }
        }
        return $binding;
    }
}

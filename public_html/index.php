<?php
namespace Core\Sys;

// Include the AutoLoader class
require_once dirname(__DIR__) . '/rad/core/sys/AutoLoader.cls.php';

// Create an instance of the AutoLoader
$autoloader = new \Core\Sys\AutoLoader();

// Register core app classes directory
$autoloader->addDirectory(dirname(__DIR__) . '/rad/core/app');

// Register core system classes directory
$autoloader->addDirectory(dirname(__DIR__) . '/rad/core/sys');

// Register admin classes directory
$autoloader->addDirectory(dirname(__DIR__) . '/rad/admin/classes');

// Register microservices parent directory for all ms namespaces
$autoloader->addDirectory(dirname(__DIR__) . '/rad');
// Optionally, still register each microservice subdirectory for legacy or direct class loading
// $microservicesDir = dirname(__DIR__) . '/rad/ms';
// foreach (glob($microservicesDir . '/*', GLOB_ONLYDIR) as $msDirectory) {
//     $autoloader->addDirectory($msDirectory);
// }

$autoloader->register();

// Load the configuration
$configLoader = new \Core\Sys\ConfigLoader(__DIR__ . '/../rad/config/sys.inc.php');

// Get the complete configuration
$configInit = $configLoader->getInitAll();

// Initialize the logger and error handler
$logger = new \Core\Sys\Logger($configInit['dir']['log']);
$errorHandler = new \Core\Sys\ErrorHandler($logger);

// Create a new Database instance
$db = new \Core\Sys\Database($configInit['database'], $errorHandler);

// Clear the sensitive data in the configInit array
$configInit = [];
$dbConfig = $configLoader->fetchDbConfig($db);

// Get the complete configuration using the configLoader
$config = $configLoader->getAll($dbConfig);
$configLoader->setEnvConfig();

// Create an instance of the IndexController
$controller = new \Core\Sys\IndexController($config, $db, $logger, $errorHandler);

/**
 * The IndexController class handles the incoming requests and routes them to the appropriate controllers.
 */
class IndexController {
    private $session;
    private $logger;
    private $errorHandler;
    private $config;
    private $db;
    private $request;
    private $route = [];
    private $baseRoutes = [];
    private $runData = [];
    private $view;

    public function __construct(array $config, \Core\Sys\Database $db, \Core\Sys\Logger $logger, \Core\Sys\ErrorHandler $errorHandler) {
        $this->config = $config;
        $this->db = $db;
        $this->logger = $logger;
        $this->errorHandler = $errorHandler;

        // Start the session
        $sessionParams = [
            'dir' => $this->config['dir']['session'],
            'name' => $this->config['sys']['session_name'],
            'lifetime' => $this->config['sys']['session_lifetime'],
            'domain' => $this->config['sys']['session_domain'],
            'secure' => $this->config['sys']['session_secure'],
            'httponly' => $this->config['sys']['session_httponly'],
            'idle_timeout' => $this->config['sys']['session_idle_timeout'],
        ];
        $this->session = new \Core\Sys\SessionManager($db, $sessionParams);
        $this->session->start();

        // Measure the request execution time
        $requestStartTime = microtime(true);

        $this->runData['errorHandler'] = $this->errorHandler;
        $this->runData['logger'] = $this->logger;
        $this->request = new \Core\Sys\Request();
        $this->view = new \Core\Sys\View();
        $this->initializeRunData();
        $this->errorHandler->attachContext($this->runData, $this->view);
  
          // Register the base routes
          $this->registerGateways();
  
          // Handle the incoming request
          $this->handleRequest();
  
          // Calculate the execution time
          $executionTime = microtime(true) - $requestStartTime;

          $activityContext = [];
          if (class_exists('\\Core\\Sys\\ActivityContext')) {
              $activityContext = \Core\Sys\ActivityContext::get();
          }
          if (isset($this->runData['activity']) && is_array($this->runData['activity'])) {
              $activityContext = array_merge($activityContext, $this->runData['activity']);
          }
          if (!empty($activityContext)) {
              $baseUrl = rtrim((string)($this->config['sys']['base_url'] ?? ''), '/');
              $link = $baseUrl !== '' ? $baseUrl . $this->request->uri : $this->request->uri;
              $bridge = new \Core\Sys\NotificationBridge($this->db, $this->config);
              $created = $bridge->notifyFromActivity($activityContext, [
                  'entity_id' => (int)($this->runData['entity']['id'] ?? 0),
                  'actor_id' => (int)($this->runData['entity']['id'] ?? 0),
                  'space_id' => (int)($activityContext['space_id'] ?? 0),
                  'link' => $link,
                  'event_type' => $activityContext['event_type'] ?? $activityContext['activity_event'] ?? '',
              ], 'realtime');
              if ($created > 0 && class_exists('\\Core\\Sys\\ActivityContext')) {
                  $activityContext['activity_notified'] = true;
                  \Core\Sys\ActivityContext::set($activityContext);
              }
          }
  
          // Log the access with the execution time
          $this->logger->logAccess($executionTime);
      }
  
      private function registerGateways() {
          $this->baseRoutes['/api'] = 'ApiController';
          $this->baseRoutes['/login'] = 'LoginController';
          $this->baseRoutes['/queue'] = 'QueueController';
          $this->baseRoutes['/rad-admin'] = 'RadAdminController';
          $this->baseRoutes['/fs-store'] = 'FileStorageController';
          $this->baseRoutes['/error-pages'] = 'ErrorController';
  
          // Register a catch-all route handler for other URIs
          $this->baseRoutes['.*'] = 'GenericController';
      }
  
      private function handleRequest() {
          $url = $_SERVER['REQUEST_URI'];
          $url = strtok($url, '?');
  
          $controllerName = null;
  
          if ($url === '/') {
              $controllerName = '\\Core\\Sys\\HomeController';
          } else {
              foreach ($this->baseRoutes as $baseRoute => $controller) {
                  $baseRoute = preg_quote($baseRoute, '#');
                  if (preg_match("#^$baseRoute#", $url)) {
                      $controllerName = '\\Core\\Sys\\'.$controller;
                      break;
                  }
              }
          }
  
          if ($controllerName === null) {
              $controllerName = '\\Core\\Sys\\GenericController';
          }
  
          $routeParts = explode('/', trim($url, '/'));
          foreach ($routeParts as $routePart) {
              $this->route[] = $routePart;
          }
          $this->runData['route']['pathparts'] = $this->route;
          $this->runData['route']['path'] = substr($this->request->uri, strlen($this->route[0]) + 2);
  
        if ($controllerName === '\\Core\\Sys\\ApiController') {
            $routeController = new $controllerName($this->runData, $this->errorHandler);
        } else {
            $routeController = new $controllerName($this->runData, $this->view, $this->errorHandler);
        }
        $routeController->handle();
    }
  
      private function initializeRunData() {
          $this->runData['request'] = $this->request;
          $this->runData['db'] = $this->db;
        $this->runData['permissionService'] = new \Core\Sys\PermissionService($this->db);
        $this->runData['notificationService'] = new \Core\Sys\NotificationService($this->db);
          $this->runData['config'] = [
              'sys' => $this->config['sys'],
              'app' => $this->config['app'],
              'dir' => $this->config['dir'],
          ];
          $defaultTimezone = \Core\Sys\TimeHelper::resolveTimezone($this->runData['config']['sys']['timezone'] ?? null, 'UTC');
          $this->runData['config']['sys']['timezone_default'] = $defaultTimezone;
      
          $this->runData['session'] = $this->session;
          $this->runData['entity'] = [];
      
          if ($this->session->get('entity_id') && $this->session->get('entity_id') != '') {
              $this->runData['entity']['is_logged_in'] = true;
              $this->runData['entity']['id'] = $this->session->get('entity_id');
              $this->runData['entity']['uid'] = $this->session->get('entity_uid');
              $this->runData['entity']['type'] = $this->session->get('entity_type');
      
              if ($this->session->get('entity_type') == 'U') {
                  $this->runData['entity']['username'] = $this->session->get('username');
                  $this->runData['entity']['fullname'] = $this->session->get('fullname');
                  $this->runData['entity']['email'] = $this->session->get('email');
                  $this->runData['entity']['mobile'] = $this->session->get('mobile');
                  $this->runData['entity']['agreement_signed'] = $this->session->get('agreement_signed');
                  $this->runData['entity']['view_status'] = 'render';
                  $this->runData['entity']['view_render_scope'] = 'full';
                  $this->runData['entity']['view_redirect_url'] = '';
  
                  $roleIds = [];
                  $workspaceRoles = [];

                  $entityRows = $this->db->select('s_entity', ['id' => (int)$this->runData['entity']['id']], true);
                  if (!empty($entityRows)) {
                      $nonSaasRoleId = (int)($entityRows[0]['s_nonsaas_role_id'] ?? 0);
                      $this->runData['entity']['nonsaas_role_id'] = $nonSaasRoleId > 0 ? $nonSaasRoleId : null;
                      if ($nonSaasRoleId > 0) {
                          $roleIds[] = $nonSaasRoleId;
                          $this->runData['entity']['role_id'] = $nonSaasRoleId;
                      }
                      $profileTimezone = '';
                      $definition = $entityRows[0]['s_definition'] ?? '';
                      if ($definition !== '') {
                          $decoded = json_decode($definition, true);
                          if (is_array($decoded)) {
                              $profileTimezone = trim((string)($decoded['profile_prefs']['timezone'] ?? ''));
                          }
                      }
                      $this->runData['entity']['timezone'] = \Core\Sys\TimeHelper::resolveTimezone($profileTimezone, $defaultTimezone);
                      $this->runData['entity']['timezone_source'] = \Core\Sys\TimeHelper::isValidTimezone($profileTimezone) ? 'user' : 'default';
                  }
                  if (!isset($this->runData['entity']['nonsaas_role_id'])) {
                      $this->runData['entity']['nonsaas_role_id'] = null;
                  }

                  $membershipRows = $this->db->query(
                      "SELECT space_id, s_role_id, s_scope_level, s_ms_id
                       FROM s_space_membership
                       WHERE livestatus != '0' AND s_entity_id = :entity AND s_role_id IS NOT NULL",
                      [':entity' => (int)$this->runData['entity']['id']]
                  );
                  foreach ($membershipRows as $row) {
                      $spaceIdInt = (int)($row['space_id'] ?? 0);
                      $roleIdInt = (int)($row['s_role_id'] ?? 0);
                      if ($spaceIdInt > 0 && $roleIdInt > 0) {
                          $roleIds[] = $roleIdInt;
                          $workspaceRoles[$spaceIdInt][] = [
                              'role_id' => $roleIdInt,
                              'scope_level' => $row['s_scope_level'] ?? 'workspace',
                              'ms_id' => isset($row['s_ms_id']) ? (int)$row['s_ms_id'] : null,
                          ];
                      }
                  }

                  $roleIds = array_values(array_unique(array_filter($roleIds, fn($v) => $v !== null)));

                  // Look up role scopes for structure
                  $roleMeta = [];
                  if (!empty($roleIds)) {
                      $params = [];
                      $placeholders = [];
                      foreach (array_values($roleIds) as $idx => $rid) {
                          $key = ':r' . ($idx + 1);
                          $placeholders[] = $key;
                          $params[$key] = $rid;
                      }
                      $inList = implode(',', $placeholders);
                      $rows = $this->db->query("SELECT id, s_scope FROM s_role WHERE id IN ({$inList})", $params);
                      foreach ($rows as $row) {
                          $roleMeta[(int)$row['id']] = $row['s_scope'] ?? 'platform';
                      }
                  }

                  $spacesList = array_keys($workspaceRoles);
                  $this->runData['entity']['space_id'] = $spacesList;
                  $this->runData['entity']['space_ids_csv'] = implode(',', $spacesList);
                  $this->runData['entity']['spaces_roles'] = $workspaceRoles;

                  // Roles structured summary
                  $byScope = [
                      'platform' => [],
                      'workspace' => [],
                      'ms' => [],
                  ];
                  foreach ($roleMeta as $rid => $scope) {
                      if (!isset($byScope[$scope])) {
                          $byScope[$scope] = [];
                      }
                      $byScope[$scope][] = $rid;
                  }

                  $this->runData['entity']['role_id'] = $roleIds;
                  $this->runData['entity']['roles'] = [
                      'all' => $roleIds,
                      'by_scope' => $byScope,
                      'by_space' => $workspaceRoles,
                  ];
              }
          } else {
              $this->runData['entity']['is_logged_in'] = false;
          }
      
          $this->runData['data'] = [];
          $this->runData['route'] = [];
      
          if (isset($_SESSION['alert_from_request']) && isset($_SESSION['route_alert']) && isset($_SESSION['route_alert_message'])) {
              $this->runData['route']['alert_from_request'] = true;
              $this->runData['route']['alert'] = $_SESSION['route_alert'];
              $this->runData['route']['alert_message'] = $_SESSION['route_alert_message'];
              unset($_SESSION['alert_from_request']);
              unset($_SESSION['route_alert']);
              unset($_SESSION['route_alert_message']);
          } else {
              $this->runData['route']['alert_from_request'] = false;
              $this->runData['route']['alert'] = '';
              $this->runData['route']['alert_message'] = '';
          }
          
        $this->runData['route']['h1'] = '';
        $this->runData['route']['meta_title'] = '';
        $this->runData['route']['meta_description'] = '';
        $this->runData['route']['path_full'] = $this->request->uri;
    }    
}

<?php
namespace RadAdmin;
use DateTime;
use Core\Sys\WorkspaceService;
use Core\Sys\HomeDashboardService;
class Home{
    private $runData = [];
    private $db;
    private $errorHandler;
    private $dashboardService;
    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        $workspaceService = new WorkspaceService($runData['db']);
        $this->dashboardService = new HomeDashboardService($runData['config'], $workspaceService, $runData['db']);
    }
    public function view() {
        $this->runData['route']['h1'] = 'RAD Admin Dashboard';
        $this->runData['route']['meta_title'] = 'RAD Admin Dashboard';
        $forceRefresh = strtoupper((string)($this->runData['request']->get['refresh'] ?? 'N')) === 'Y';
        $scope = strtolower((string)($this->runData['request']->get['scope'] ?? 'all'));
        $scope = in_array($scope, ['all', 'logs'], true) ? $scope : 'all';
        $this->runData['data']['dashboard'] = $this->dashboardService->getDashboardData($forceRefresh, $scope);
        return $this->runData;
    }
    
}

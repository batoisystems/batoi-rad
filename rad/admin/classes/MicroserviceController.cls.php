<?php
namespace RadAdmin;
use DateTime;
class MicroserviceController{
    private $runData = [];
    private $errorHandler;
    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->errorHandler = $runData['errorHandler'];
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }

    public function view() {
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'This is the Services page.';
        }
        $this->runData['route']['h1'] = 'Controllers';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Microservicelet' => $this->runData['route']['rad_admin_url'] . '/microservice/view',
            'Controllers' => '',
        ];
        $this->runData['data']['route'] = '';
        return $this->runData;
    }

    public function add() {
        $this->runData['route']['h1'] = 'Add Service';
        $this->runData['data']['route'] = '';
        return $this->runData;
    }

    public function edit() {
        $this->runData['route']['h1'] = 'Edit Service';
        $this->runData['data']['route'] = '';
        return $this->runData;
    }

    public function viewone() {
        $this->runData['route']['h1'] = 'Controller';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Controllers' => $this->runData['route']['rad_admin_url'] . '/controller/view',
            'Controller' => ''
        ];
        $this->runData['data']['route'] = '';
        return $this->runData;
    }

    public function save() {
        $this->runData['route']['h1'] = 'Save Service';
        $this->runData['data']['route'] = '';
        return $this->runData;
    }

    public function archive() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() === 'developer' || !$priv->can('controller_edit')) {
            throw new \Exception('Access denied.', 403);
        }
        $this->runData['route']['h1'] = 'Archive Service';
        $this->runData['data']['route'] = '';
        return $this->runData;
    }
}

<?php
namespace RadAdmin;

use Core\Sys\PrivilegeService;

class Wf {
    private $runData = [];
    private $db;
    private PrivilegeService $priv;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->priv = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
    }

    public function inventory() {
        if (!$this->priv->can('settings')) {
            throw new \Exception('Access denied.', 403);
        }
        $states = $this->db->select('s_wf_state', [], true, ['s_name' => 'ASC']);
        $actions = $this->db->query(
            'SELECT a.*, fs.s_name AS from_state, ts.s_name AS to_state 
             FROM s_wf_action a 
             LEFT JOIN s_wf_state fs ON fs.id = a.s_wf_state_id 
             LEFT JOIN s_wf_state ts ON ts.id = a.s_next_wf_state_id 
             ORDER BY a.s_name ASC'
        );

        $this->runData['data']['wf_states'] = $states;
        $this->runData['data']['wf_actions'] = $actions;
        $this->runData['route']['h1'] = 'Workflow Inventory';
        $this->runData['route']['meta_title'] = 'Workflow Inventory';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Workflow' => '',
        ];
        return $this->runData;
    }
}

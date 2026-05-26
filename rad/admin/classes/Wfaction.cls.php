<?php
namespace RadAdmin;

use Core\Sys\PrivilegeService;

class Wfaction {
    private $runData = [];
    private $db;
    private PrivilegeService $priv;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->priv = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
    }

    public function add() {
        if (!$this->priv->can('settings')) {
            throw new \Exception('Access denied.', 403);
        }
        $states = $this->db->select('s_wf_state', [], true, ['s_name' => 'ASC']);
        $this->runData['data']['wf_states'] = $states;
        $this->runData['route']['h1'] = 'Add Workflow Transition';
        $this->runData['route']['meta_title'] = 'Add Workflow Transition';
        $this->runData['route']['breadcrumb'] = [
            'Workflow' => $this->runData['route']['rad_admin_url'] . '/wf/inventory',
            'Transitions' => '',
        ];
        if ($this->runData['request']->method === 'POST') {
            $name = trim($this->runData['request']->post['s_name'] ?? '');
            $from = (int)($this->runData['request']->post['s_wf_state_id'] ?? 0);
            $to = (int)($this->runData['request']->post['s_next_wf_state_id'] ?? 0);
            if ($name === '' || $from <= 0 || $to <= 0) {
                $this->runData['request']->setAlert('Name and states are required.', 'danger');
                return $this->runData;
            }
            $this->db->insert('s_wf_action', [
                's_name' => $name,
                's_wf_state_id' => $from,
                's_next_wf_state_id' => $to,
            ]);
            $this->runData['request']->setAlert('Transition added.', 'success');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/wf/inventory');
            exit;
        }
        return $this->runData;
    }
}

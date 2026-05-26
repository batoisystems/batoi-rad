<?php
namespace RadAdmin;

use Core\Sys\PrivilegeService;

class Wfstate {
    private $runData = [];
    private $db;
    private PrivilegeService $priv;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->priv = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
    }

    public function view() {
        if (!$this->priv->can('settings')) {
            throw new \Exception('Access denied.', 403);
        }
        $states = $this->db->select('s_wf_state', [], true, ['s_name' => 'ASC']);
        $this->runData['data']['wf_states'] = $states;
        $this->runData['route']['h1'] = 'Workflow States';
        $this->runData['route']['meta_title'] = 'Workflow States';
        $this->runData['route']['breadcrumb'] = [
            'Workflow' => $this->runData['route']['rad_admin_url'] . '/wf/inventory',
            'States' => '',
        ];
        return $this->runData;
    }

    public function add() {
        if (!$this->priv->can('settings')) {
            throw new \Exception('Access denied.', 403);
        }
        $this->runData['route']['h1'] = 'Add Workflow State';
        $this->runData['route']['meta_title'] = 'Add Workflow State';
        $this->runData['route']['breadcrumb'] = [
            'Workflow' => $this->runData['route']['rad_admin_url'] . '/wf/inventory',
            'States' => $this->runData['route']['rad_admin_url'] . '/wfstate/view',
            'Add' => '',
        ];
        if ($this->runData['request']->method === 'POST') {
            $name = trim($this->runData['request']->post['s_name'] ?? '');
            $order = (int)($this->runData['request']->post['s_flow_order'] ?? 0);
            $definition = trim($this->runData['request']->post['s_definition'] ?? '');
            if ($name === '') {
                $this->runData['request']->setAlert('Name is required.', 'danger');
                return $this->runData;
            }
            $this->db->insert('s_wf_state', [
                's_name' => $name,
                's_flow_order' => $order ?: null,
                's_definition' => $definition !== '' ? $definition : null,
            ]);
            $this->runData['request']->setAlert('State added.', 'success');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/wfstate/view');
            exit;
        }
        return $this->runData;
    }
}

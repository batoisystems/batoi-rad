<?php
namespace RadAdmin;

use Core\Sys\DotPhraseService;
use Core\Sys\PrivilegeService;

class Dotphrase {
    private $runData = [];
    private $db;
    private $service;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->service = new DotPhraseService($this->db, $runData['errorHandler'] ?? null);
    }

    public function view() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }
        $filters = [
            'scope' => $this->runData['request']->get['scope'] ?? '',
            'space_id' => $this->runData['request']->get['space_id'] ?? '',
            'owner_id' => $this->runData['request']->get['owner_id'] ?? '',
            'is_public' => $this->runData['request']->get['is_public'] ?? '',
            'search' => $this->runData['request']->get['search'] ?? '',
        ];
        $phrases = $this->service->list($filters);
        $spaces = $this->db->select('s_space', ['livestatus' => '1'], true, ['s_name' => 'ASC']);

        $this->runData['route']['h1'] = 'Dot Phrases';
        $this->runData['route']['meta_title'] = 'Dot Phrases';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/home/view';
        $this->runData['data']['phrases'] = $phrases;
        $this->runData['data']['spaces'] = $spaces;
        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['can_manage'] = $priv->can('idm_manage');
        return $this->runData;
    }

    public function add() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        if (!empty($this->runData['request']->post)) {
            try {
                $data = $this->collectPayload($this->runData['request']->post);
                $this->service->create($data, $this->runData['entity']['id'] ?? null);
                $this->runData['request']->setAlert('Dot phrase created.', 'success');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/dotphrase/view');
                exit;
            } catch (\Exception $e) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = $e->getMessage();
            }
        }
        $this->prepareForm('Add Dot Phrase');
        return $this->runData;
    }

    public function edit() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['request']->setAlert('Dot phrase not found.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/dotphrase/view');
            exit;
        }
        $uid = $this->runData['route']['pathparts'][3];
        $phrase = $this->service->get($uid);
        if (!$phrase) {
            $this->runData['request']->setAlert('Dot phrase not found.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/dotphrase/view');
            exit;
        }
        if (!empty($this->runData['request']->post)) {
            try {
                $data = $this->collectPayload($this->runData['request']->post);
                $this->service->update($uid, $data, $this->runData['entity']['id'] ?? null);
                $this->runData['request']->setAlert('Dot phrase updated.', 'success');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/dotphrase/view');
                exit;
            } catch (\Exception $e) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = $e->getMessage();
            }
        }
        $this->runData['data']['phrase'] = $phrase;
        $this->prepareForm('Edit Dot Phrase: ' . ($phrase['s_phrase'] ?? ''), true);
        return $this->runData;
    }

    public function archive() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['request']->setAlert('Dot phrase not found.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/dotphrase/view');
            exit;
        }
        $uid = $this->runData['route']['pathparts'][3];
        $this->service->archive($uid);
        $this->runData['request']->setAlert('Dot phrase archived.', 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/dotphrase/view');
        exit;
    }

    private function collectPayload(array $post): array {
        return [
            's_phrase' => trim($post['s_phrase'] ?? ''),
            's_content' => $post['s_content'] ?? '',
            's_scope' => $post['s_scope'] ?? 'platform',
            'space_id' => (int)($post['space_id'] ?? 0),
            's_is_public' => $post['s_is_public'] ?? 'N',
            's_description' => $post['s_description'] ?? '',
            's_owner_id' => isset($post['s_owner_id']) ? (int)$post['s_owner_id'] : null,
            's_tags' => $post['s_tags'] ?? null,
        ];
    }

    private function prepareForm(string $title, bool $isEdit = false): void {
        $this->runData['route']['h1'] = $title;
        $this->runData['route']['meta_title'] = $title;
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/dotphrase/view';
        $this->runData['data']['spaces'] = $this->db->select('s_space', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
    }
}

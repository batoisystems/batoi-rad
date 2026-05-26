<?php
namespace RadAdmin;
use DateTime;
use Core\Sys\WorkspaceService;
use Core\Sys\WorkspaceStorageService;

require_once __DIR__ . '/WorkspaceMembershipHelper.cls.php';

class Space{
    private $runData = [];
    private $errorHandler;
    private $workspaceService;
    private $storageService;
    private $membershipHelper;
    private $ipAccessService;
    private function slugify(string $value): string {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'workspace';
        }
        return substr($slug, 0, 50);
    }

    private function getProfilePerPage(int $fallback): int {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return $fallback;
        }
        $rows = $this->runData['db']->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return $fallback;
        }
        $definition = json_decode((string)($rows[0]['s_definition'] ?? '{}'), true);
        $perPage = (int)($definition['profile_prefs']['per_page'] ?? $fallback);
        return $this->isAllowedPerPage($perPage) ? $perPage : $fallback;
    }

    private function saveProfilePerPage(int $perPage): void {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return;
        }
        $rows = $this->runData['db']->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return;
        }
        $definition = json_decode((string)($rows[0]['s_definition'] ?? '{}'), true);
        if (!is_array($definition)) {
            $definition = [];
        }
        $definition['profile_prefs']['per_page'] = $perPage;
        $this->runData['db']->update('s_entity', [
            's_definition' => json_encode($definition, JSON_UNESCAPED_SLASHES),
        ], ['id' => $entityId]);
    }

    private function isAllowedPerPage(int $perPage): bool {
        return in_array($perPage, [10, 25, 50, 100, 200], true);
    }
    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->errorHandler = $runData['errorHandler'];
        $this->workspaceService = new WorkspaceService($runData['db']);
        $this->storageService = new WorkspaceStorageService($runData['config']);
        $this->membershipHelper = new WorkspaceMembershipHelper($runData['db'], $runData['entity']['id'] ?? null);
        $this->ipAccessService = new \Core\Sys\IpAccessService();
    }

    private function mergeWorkspaceIpAccessDefinition($definition): array {
        return $this->ipAccessService->mergeRuleIntoDefinition(
            $definition,
            !empty($this->runData['request']->post['ip_access_enabled']),
            $this->runData['request']->post['ip_access_ips'] ?? ''
        );
    }

    public function view() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        $this->runData['data']['can_idm_manage'] = $priv->can('idm_manage');
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Here, you can manage the Workspaces.';
        }
        $this->runData['route']['h1'] = 'Workspaces';
        $this->runData['route']['meta_title'] = 'Workspaces';
        $this->runData['route']['backlink'] = $radAdminUrl . '/home/view';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Workspaces' => '',
        ];
        $filters = [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
            'status' => trim((string)($this->runData['request']->get['status'] ?? '')),
            'missing_owner' => (int)($this->runData['request']->get['missing_owner'] ?? 0),
            'missing_slug' => (int)($this->runData['request']->get['missing_slug'] ?? 0),
            'no_members' => (int)($this->runData['request']->get['no_members'] ?? 0),
        ];
        $sort = trim((string)($this->runData['request']->get['sort'] ?? 'name_asc'));
        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $perPage = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($perPage === 0) {
            $perPage = $this->getProfilePerPage(25);
        } elseif ($this->isAllowedPerPage($perPage)) {
            $this->saveProfilePerPage($perPage);
        } else {
            $perPage = 25;
        }
        if ($perPage < 20) {
            $perPage = 20;
        }
        $allSpaces = $this->workspaceService->listSummaries(true);
        $ownerIds = [];
        foreach ($allSpaces as $row) {
            $ownerId = (int)($row['s_owner_entity_id'] ?? 0);
            if ($ownerId > 0) {
                $ownerIds[$ownerId] = true;
            }
        }
        $ownerMap = [];
        if (!empty($ownerIds)) {
            $placeholders = [];
            $params = [];
            $index = 0;
            foreach (array_keys($ownerIds) as $ownerId) {
                $placeholder = ':owner' . $index++;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $ownerId;
            }
            $ownerRows = $this->runData['db']->query(
                'SELECT id, s_name, s_identity FROM s_entity WHERE id IN (' . implode(',', $placeholders) . ')',
                $params
            );
            foreach ($ownerRows as $ownerRow) {
                $ownerMap[(int)$ownerRow['id']] = [
                    'name' => (string)($ownerRow['s_name'] ?? ''),
                    'identity' => (string)($ownerRow['s_identity'] ?? ''),
                ];
            }
        }
        foreach ($allSpaces as &$row) {
            $ownerId = (int)($row['s_owner_entity_id'] ?? 0);
            $owner = $ownerMap[$ownerId] ?? null;
            $row['owner_name'] = $owner['name'] ?? '';
            $row['owner_identity'] = $owner['identity'] ?? '';
            $issueCount = 0;
            if (empty($row['s_owner_entity_id'])) {
                $issueCount++;
            }
            if (empty($row['s_slug'])) {
                $issueCount++;
            }
            if (empty($row['member_count'])) {
                $issueCount++;
            }
            if ((string)($row['livestatus'] ?? '') !== '1') {
                $issueCount++;
            }
            $row['issue_count'] = $issueCount;
        }
        unset($row);
        $this->runData['data']['workspaces_all'] = $allSpaces;
        $spaces = $allSpaces;
        $spaces = array_values(array_filter($spaces, function ($row) use ($filters) {
            if ($filters['status'] !== '' && (string)($row['livestatus'] ?? '') !== $filters['status']) {
                return false;
            }
            if ($filters['q'] !== '') {
                $needle = strtolower($filters['q']);
                $blob = strtolower(
                    ($row['s_name'] ?? '') . ' ' .
                    ($row['uid'] ?? '') . ' ' .
                    ($row['s_slug'] ?? '') . ' ' .
                    ($row['s_description'] ?? '') . ' ' .
                    ($row['owner_name'] ?? '') . ' ' .
                    ($row['owner_identity'] ?? '')
                );
                return strpos($blob, $needle) !== false;
            }
            if (!empty($filters['missing_owner']) && !empty($row['s_owner_entity_id'])) {
                return false;
            }
            if (!empty($filters['missing_slug']) && !empty($row['s_slug'])) {
                return false;
            }
            if (!empty($filters['no_members']) && !empty($row['member_count'])) {
                return false;
            }
            return true;
        }));
        $sorters = [
            'name_asc' => fn($a, $b) => strcasecmp($a['s_name'] ?? '', $b['s_name'] ?? ''),
            'name_desc' => fn($a, $b) => strcasecmp($b['s_name'] ?? '', $a['s_name'] ?? ''),
            'created_desc' => fn($a, $b) => strcmp($b['createstamp'] ?? '', $a['createstamp'] ?? ''),
            'created_asc' => fn($a, $b) => strcmp($a['createstamp'] ?? '', $b['createstamp'] ?? ''),
            'updated_desc' => fn($a, $b) => strcmp((string)($b['updatestamp'] ?? $b['createstamp'] ?? ''), (string)($a['updatestamp'] ?? $a['createstamp'] ?? '')),
            'updated_asc' => fn($a, $b) => strcmp((string)($a['updatestamp'] ?? $a['createstamp'] ?? ''), (string)($b['updatestamp'] ?? $b['createstamp'] ?? '')),
            'members_desc' => fn($a, $b) => (int)($b['member_count'] ?? 0) <=> (int)($a['member_count'] ?? 0),
            'members_asc' => fn($a, $b) => (int)($a['member_count'] ?? 0) <=> (int)($b['member_count'] ?? 0),
            'issues_desc' => fn($a, $b) => (int)($b['issue_count'] ?? 0) <=> (int)($a['issue_count'] ?? 0),
            'issues_asc' => fn($a, $b) => (int)($a['issue_count'] ?? 0) <=> (int)($b['issue_count'] ?? 0),
        ];
        $sorter = $sorters[$sort] ?? $sorters['name_asc'];
        usort($spaces, $sorter);

        $total = count($spaces);
        $offset = ($page - 1) * $perPage;
        $pagedSpaces = array_slice($spaces, $offset, $perPage);
        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['workspaces'] = $pagedSpaces;
        $this->runData['data']['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
            'sort' => $sort,
        ];
        $statusStats = [
            'total' => count($allSpaces),
            'active' => 0,
            'inactive' => 0,
            'archived' => 0,
            'suspended' => 0,
            'missing_owner' => 0,
            'missing_slug' => 0,
            'no_members' => 0,
        ];
        foreach ($allSpaces as $row) {
            $status = (string)($row['livestatus'] ?? '');
            if ($status === '1') {
                $statusStats['active']++;
            } elseif ($status === '2') {
                $statusStats['archived']++;
            } elseif ($status === '3') {
                $statusStats['suspended']++;
            } else {
                $statusStats['inactive']++;
            }
            if (empty($row['s_owner_entity_id'])) {
                $statusStats['missing_owner']++;
            }
            if (empty($row['s_slug'])) {
                $statusStats['missing_slug']++;
            }
            if (empty($row['member_count'])) {
                $statusStats['no_members']++;
            }
        }
        $this->runData['data']['workspace_stats'] = $statusStats;
        return $this->runData;
    }

    public function viewone() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        // Check if the 3rd element of the pathparts exists
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $radAdminUrl . '/space/view';
            header("Location: $redirectUrl"); exit;
        }
    
        // Get the UID from the 3rd element of the pathparts
        $uid = $this->runData['route']['pathparts'][3];
    
        // Get the space data from s_space table
        $spaceRows = $this->runData['db']->select('s_space', ['uid' => $uid], true);
        if (count($spaceRows) != 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $radAdminUrl . '/space/view';
            header("Location: $redirectUrl"); exit;
        }
    
        $this->runData['data']['space'] = $spaceRows[0];
        $this->runData['data']['ip_access_rule'] = $this->ipAccessService->extractRuleFromDefinition($spaceRows[0]['s_definition'] ?? []);
        $spaceName = (string)($this->runData['data']['space']['s_name'] ?? 'Workspace');
        $this->runData['route']['h1'] = 'Workspace · ' . $spaceName;
        $this->runData['route']['meta_title'] = 'Workspace: ' . $spaceName;
        $this->runData['route']['backlink'] = $radAdminUrl . '/space/view';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Workspaces' => $radAdminUrl . '/space/view',
            $spaceName => '',
        ];
        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Inspect workspace ownership, access rules, and memberships.';
        }

        $this->membershipHelper->syncLegacyAssignments($this->runData['data']['space']);
        $this->runData['data']['assignments'] = $this->membershipHelper->fetchAssignments((int)$this->runData['data']['space']['id']);
        if (!empty($this->runData['data']['space']['s_owner_entity_id'])) {
            $ownerRows = $this->runData['db']->select('s_entity', ['id' => (int)$this->runData['data']['space']['s_owner_entity_id']], true);
            $this->runData['data']['owner_entity'] = $ownerRows[0] ?? null;
        } else {
            $this->runData['data']['owner_entity'] = null;
        }

        return $this->runData;
    }

    public function sniff() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        $uid = $this->runData['route']['pathparts'][3] ?? '';
        if ($uid === '') {
            throw new \Exception('Workspace not found.', 404);
        }
        $spaceRows = $this->runData['db']->select('s_space', ['uid' => $uid], true);
        if (count($spaceRows) !== 1) {
            throw new \Exception('Workspace not found.', 404);
        }

        $space = $spaceRows[0];
        $assignments = $this->membershipHelper->fetchAssignments((int)$space['id']);
        $roleIds = [];
        foreach ($assignments as $assignment) {
            $roleId = (int)($assignment['role_id'] ?? 0);
            if ($roleId > 0) {
                $roleIds[$roleId] = true;
            }
        }

        $roles = [];
        if (!empty($roleIds)) {
            $placeholders = [];
            $params = [];
            $index = 0;
            foreach (array_keys($roleIds) as $roleId) {
                $placeholder = ':rid' . $index++;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $roleId;
            }
            $roleRows = $this->runData['db']->query(
                'SELECT id, uid, s_role_name, s_scope FROM s_role WHERE id IN (' . implode(',', $placeholders) . ') ORDER BY s_role_name ASC, id ASC',
                $params
            );
            foreach ($roleRows as $row) {
                $roles[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'uid' => (string)($row['uid'] ?? ''),
                    'role' => (string)($row['s_role_name'] ?? ''),
                    'type' => (string)($row['s_scope'] ?? ''),
                ];
            }
        }

        $payloadAssignments = [];
        foreach ($assignments as $assignment) {
            $payloadAssignments[] = [
                'user_id' => (int)($assignment['user_id'] ?? 0),
                'user_uid' => (string)($assignment['user_uid'] ?? ''),
                'user' => (string)($assignment['user_name'] ?? ''),
                'role_id' => (int)($assignment['role_id'] ?? 0),
                'role' => (string)($assignment['role_name'] ?? ''),
                'type' => (string)($assignment['role_scope'] ?? ''),
            ];
        }

        $payload = [
            'object' => [
                'kind' => 'workspace',
                'id' => (int)($space['id'] ?? 0),
                'uid' => (string)($space['uid'] ?? ''),
                'name' => (string)($space['s_name'] ?? ''),
                'slug' => (string)($space['s_slug'] ?? ''),
            ],
            'roles' => $roles,
            'assignments' => $payloadAssignments,
            'stats' => [
                'role_count' => count($roles),
                'assignment_count' => count($payloadAssignments),
            ],
        ];

        $this->runData['data']['space'] = $space;
        $this->runData['data']['sniff_payload'] = $payload;
        $this->runData['route']['h1'] = 'Meta Sniff: ' . ($space['s_name'] ?? '');
        $this->runData['route']['meta_title'] = 'Meta Sniff - ' . ($space['s_name'] ?? '');
        $this->runData['route']['backlink'] = $radAdminUrl . '/space/viewone/' . $space['uid'];
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Workspaces' => $radAdminUrl . '/space/view',
            $space['s_name'] => $radAdminUrl . '/space/viewone/' . $space['uid'],
            'Meta Sniff' => '',
        ];

        return $this->runData;
    }

    public function add() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        // Alert
        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Here, you can add a new Workspace.';
        }
        
        // Check the post data
        if (isset($this->runData['request']->post['s_name'])) {
            $name = trim((string)$this->runData['request']->post['s_name']);
            $slugInput = trim((string)($this->runData['request']->post['s_slug'] ?? ''));
            $status = (string)($this->runData['request']->post['livestatus'] ?? '1');
            $ownerId = (int)($this->runData['request']->post['s_owner_entity_id'] ?? 0);
            $definitionRaw = trim((string)($this->runData['request']->post['s_definition'] ?? '{}'));
            $definitionRaw = $definitionRaw === '' ? '{}' : $definitionRaw;
            $definitionDecoded = json_decode($definitionRaw, true);
            if ($name === '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Workspace name is required.';
                $this->runData['data']['space'] = $this->runData['request']->post;
                return $this->runData;
            }
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Definition must be valid JSON.';
                $this->runData['data']['space'] = $this->runData['request']->post;
                return $this->runData;
            }
            $ipPayload = $this->mergeWorkspaceIpAccessDefinition($definitionDecoded);
            if (!empty($ipPayload['rule']['invalid'])) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid IP entries: ' . implode(', ', $ipPayload['rule']['invalid']);
                $this->runData['data']['space'] = $this->runData['request']->post;
                return $this->runData;
            }
            if (!empty($this->runData['request']->post['ip_access_enabled']) && empty($ipPayload['rule']['ips'])) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Add at least one valid IP before enabling workspace restriction.';
                $this->runData['data']['space'] = $this->runData['request']->post;
                return $this->runData;
            }
            if (!in_array($status, ['0','1','2','3'], true)) {
                $status = '1';
            }
            $slug = $slugInput !== '' ? $slugInput : $this->slugify($name);

            // Insert the Workspace
            $insertData = [
                's_name' => $name,
                's_slug' => $slug,
                's_description' => $this->runData['request']->post['s_description'],
                's_definition' => json_encode($ipPayload['definition'], JSON_UNESCAPED_SLASHES),
            ];
            if ($ownerId > 0) {
                $insertData['s_owner_entity_id'] = $ownerId;
            }
            $stateData = ['livestatus' => $status];
            $newId = $this->runData['db']->insert('s_space', $insertData, $stateData);
            if ($newId) {
                $spaceRow = $this->runData['db']->select('s_space', ['id' => $newId], true);
                if (!empty($spaceRow[0]['uid'])) {
                    $this->storageService->workspaceAbsolutePath($spaceRow[0]['uid'], true);
                }
                $this->logSpaceActivity('create', (int)$newId, $name, $slug, $status);
                $this->notifySpaceEvent('create', (int)$newId, [
                    'name' => $name,
                    'slug' => $slug,
                    'status' => $status,
                ]);
            }
            
            // Set the alert
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'Workspace added successfully';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            
            // Redirect to Workspace list
            $redirectUrl = $radAdminUrl . '/space/view';
            if (!empty($spaceRow[0]['uid'])) {
                $redirectUrl = $radAdminUrl . '/space/viewone/' . $spaceRow[0]['uid'];
            }
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        
        $this->runData['route']['h1'] = 'Add Workspace';
        $this->runData['route']['meta_title'] = 'Add Workspace';
        $this->runData['data']['space'] = [];
        $this->runData['data']['ip_access_rule'] = ['enabled' => false, 'ips' => [], 'invalid' => [], 'raw' => ''];
        
        // Backlink
        $this->runData['route']['backlink'] = $radAdminUrl . '/space/view';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Workspaces' => $radAdminUrl . '/space/view',
            'Add Workspace' => '',
        ];
        return $this->runData;
    }    

    public function edit() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        // Check if the 3rd element of the pathparts exists
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to space list
            $redirectUrl = $radAdminUrl . '/space/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        // Find the space from the UID in pathparts[3]
        $spaceRows = $this->runData['db']->select('s_space', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if (!$spaceRows) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to space list
            $redirectUrl = $radAdminUrl . '/space/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        $this->runData['data']['space'] = $spaceRows[0];
        $this->runData['data']['ip_access_rule'] = $this->ipAccessService->extractRuleFromDefinition($spaceRows[0]['s_definition'] ?? []);
    
        // Check the post data
        if (isset($this->runData['request']->post['s_name'])) {
            $name = trim((string)$this->runData['request']->post['s_name']);
            $slugInput = trim((string)($this->runData['request']->post['s_slug'] ?? ''));
            $status = (string)($this->runData['request']->post['livestatus'] ?? $this->runData['data']['space']['livestatus'] ?? '1');
            $ownerId = (int)($this->runData['request']->post['s_owner_entity_id'] ?? 0);
            $definitionRaw = trim((string)($this->runData['request']->post['s_definition'] ?? '{}'));
            $definitionRaw = $definitionRaw === '' ? '{}' : $definitionRaw;
            $definitionDecoded = json_decode($definitionRaw, true);
            if ($name === '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Workspace name is required.';
                $this->runData['data']['space'] = $this->runData['request']->post;
                return $this->runData;
            }
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Definition must be valid JSON.';
                $this->runData['data']['space'] = $this->runData['request']->post;
                return $this->runData;
            }
            if (!in_array($status, ['0','1','2','3'], true)) {
                $status = (string)($this->runData['data']['space']['livestatus'] ?? '1');
            }
            $slug = $slugInput !== '' ? $slugInput : $this->slugify($name);
            $ipPayload = $this->mergeWorkspaceIpAccessDefinition($definitionDecoded);
            if (!empty($ipPayload['rule']['invalid'])) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid IP entries: ' . implode(', ', $ipPayload['rule']['invalid']);
                $this->runData['data']['space'] = $this->runData['request']->post;
                return $this->runData;
            }
            if (!empty($this->runData['request']->post['ip_access_enabled']) && empty($ipPayload['rule']['ips'])) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Add at least one valid IP before enabling workspace restriction.';
                $this->runData['data']['space'] = $this->runData['request']->post;
                return $this->runData;
            }

            $updateData = [
                's_name' => $name,
                's_slug' => $slug,
                's_description' => $this->runData['request']->post['s_description'],
                's_definition' => json_encode($ipPayload['definition'], JSON_UNESCAPED_SLASHES),
                'livestatus' => $status,
            ];
            $updateData['s_owner_entity_id'] = $ownerId > 0 ? $ownerId : null;
            $updateWhere = [
                'uid' => $this->runData['route']['pathparts'][3],
            ];
            $this->runData['db']->update('s_space', $updateData, $updateWhere);
            // Set the alert
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'Workspace updated successfully';
            if (!empty($this->runData['data']['space']['uid'])) {
                $this->storageService->workspaceAbsolutePath($this->runData['data']['space']['uid'], true);
            }
            $this->logSpaceActivity('update', (int)$this->runData['data']['space']['id'], $name, $slug, $status);
            $this->notifySpaceEvent('update', (int)$this->runData['data']['space']['id'], [
                'name' => $name,
                'slug' => $slug,
                'status' => $status,
            ]);
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to space list
            $redirectUrl = $radAdminUrl . '/space/viewone/' . $this->runData['route']['pathparts'][3];
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        $this->runData['route']['h1'] = 'Edit Workspace: ' . $this->runData['data']['space']['s_name'];
        $this->runData['route']['meta_title'] = 'Edit Workspace: ' . $this->runData['data']['space']['s_name'];
        // Backlink
        $this->runData['route']['backlink'] = $radAdminUrl . '/space/viewone/' . $this->runData['data']['space']['uid'];
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Workspaces' => $radAdminUrl . '/space/view',
            $this->runData['data']['space']['s_name'] => $radAdminUrl . '/space/viewone/' . $this->runData['data']['space']['uid'],
            'Edit' => '',
        ];
        return $this->runData;
    }    

    public function ipaccess() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $uid = $this->runData['route']['pathparts'][3] ?? '';
        if ($uid === '') {
            $this->runData['request']->setAlert('Workspace not found', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/space/view', true, 302);
            die();
        }
        $spaceRows = $this->runData['db']->select('s_space', ['uid' => $uid], true);
        if (!$spaceRows) {
            $this->runData['request']->setAlert('Workspace not found', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/space/view', true, 302);
            die();
        }
        $space = $spaceRows[0];

        if ($this->runData['request']->method === 'POST') {
            $definitionRaw = trim((string)($space['s_definition'] ?? '{}'));
            $definitionRaw = $definitionRaw === '' ? '{}' : $definitionRaw;
            $definitionDecoded = json_decode($definitionRaw, true);
            if (!is_array($definitionDecoded)) {
                $definitionDecoded = [];
            }
            $ipPayload = $this->mergeWorkspaceIpAccessDefinition($definitionDecoded);
            if (!empty($ipPayload['rule']['invalid'])) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid IP entries: ' . implode(', ', $ipPayload['rule']['invalid']);
            } elseif (!empty($this->runData['request']->post['ip_access_enabled']) && empty($ipPayload['rule']['ips'])) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Add at least one valid IP before enabling workspace restriction.';
            } else {
                $this->runData['db']->update('s_space', [
                    's_definition' => json_encode($ipPayload['definition'], JSON_UNESCAPED_SLASHES),
                ], ['id' => (int)$space['id']]);
                $this->runData['request']->setAlert('IP restriction settings updated.', 'success');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/space/ipaccess/' . $space['uid'], true, 302);
                die();
            }
        }

        $freshRows = $this->runData['db']->select('s_space', ['uid' => $uid], true);
        $space = $freshRows[0] ?? $space;
        $this->runData['data']['space'] = $space;
        $this->runData['data']['ip_access_rule'] = $this->ipAccessService->extractRuleFromDefinition($space['s_definition'] ?? []);
        $this->runData['route']['h1'] = 'Workspace IP Restriction: ' . ($space['s_name'] ?? '');
        $this->runData['route']['meta_title'] = $this->runData['route']['h1'];
        $this->runData['route']['breadcrumb'] = [
            'Home' => $this->runData['route']['rad_admin_url'] . '/home/view',
            'Workspaces' => $this->runData['route']['rad_admin_url'] . '/space/view',
            $space['s_name'] => $this->runData['route']['rad_admin_url'] . '/space/viewone/' . $space['uid'],
            'IP Restriction' => '',
        ];
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/space/viewone/' . $space['uid'];
        return $this->runData;
    }

    /*
     * Archive Role
     */
    public function archive() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage') || $priv->role() === 'developer') {
            throw new \Exception('Access denied.', 403);
        }
        // Check the pathparts[3] is set
        if(!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to space list
            $redirectUrl = '/rad-admin/space/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        // Find the space from the UID in pathparts[3]
        $spaceRows = $this->runData['db']->select('s_space', ['uid' => $this->runData['route']['pathparts'][3]], true);
        if(!$spaceRows) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to space list
            $redirectUrl = '/rad-admin/space/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        // Archive the space
        $updateData = [
            'livestatus' => '0'
        ];
        $updateWhere = [
            'uid' => $this->runData['route']['pathparts'][3],
        ];
        $this->runData['db']->update('s_space', $updateData, $updateWhere);
        // Set the alert
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Workspace <strong>'. $spaceRows[0]['s_name'] .'</strong> archived successfully';
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        $this->logSpaceActivity('archive', (int)$spaceRows[0]['id'], $spaceRows[0]['s_name'] ?? '', $spaceRows[0]['s_slug'] ?? '', '2');
        $this->notifySpaceEvent('archive', (int)$spaceRows[0]['id'], [
            'name' => $spaceRows[0]['s_name'] ?? '',
            'slug' => $spaceRows[0]['s_slug'] ?? '',
            'status' => '2',
        ]);
        // Redirect to space list
        $redirectUrl = '/rad-admin/space/view';
        header('Location: ' . $redirectUrl, true, 302);
        die();
    }

    public function activate() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage') || $priv->role() === 'developer') {
            throw new \Exception('Access denied.', 403);
        }
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/space/view', true, 302);
            die();
        }
        $uid = $this->runData['route']['pathparts'][3];
        $spaceRows = $this->runData['db']->select('s_space', ['uid' => $uid], true);
        if (!$spaceRows) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/space/view', true, 302);
            die();
        }
        $this->runData['db']->update('s_space', ['livestatus' => '1'], ['uid' => $uid]);
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Workspace activated successfully';
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        $this->logSpaceActivity('activate', (int)$spaceRows[0]['id'], $spaceRows[0]['s_name'] ?? '', $spaceRows[0]['s_slug'] ?? '', '1');
        $this->notifySpaceEvent('activate', (int)$spaceRows[0]['id'], [
            'name' => $spaceRows[0]['s_name'] ?? '',
            'slug' => $spaceRows[0]['s_slug'] ?? '',
            'status' => '1',
        ]);
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/space/view', true, 302);
        die();
    }

    public function suspend() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage') || $priv->role() === 'developer') {
            throw new \Exception('Access denied.', 403);
        }
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/space/view', true, 302);
            die();
        }
        $uid = $this->runData['route']['pathparts'][3];
        $spaceRows = $this->runData['db']->select('s_space', ['uid' => $uid], true);
        if (!$spaceRows) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/space/view', true, 302);
            die();
        }
        $this->runData['db']->update('s_space', ['livestatus' => '3'], ['uid' => $uid]);
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Workspace suspended successfully';
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        $this->logSpaceActivity('suspend', (int)$spaceRows[0]['id'], $spaceRows[0]['s_name'] ?? '', $spaceRows[0]['s_slug'] ?? '', '3');
        $this->notifySpaceEvent('suspend', (int)$spaceRows[0]['id'], [
            'name' => $spaceRows[0]['s_name'] ?? '',
            'slug' => $spaceRows[0]['s_slug'] ?? '',
            'status' => '3',
        ]);
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/space/view', true, 302);
        die();
    }

    /*
     * Add User with their Role
     */
    public function adduser() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        // Check if the space UID exists in the pathparts
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/space/view';
            header("Location: $redirectUrl"); exit;
        }
    
        // Get the space details using the UID
        $uid = $this->runData['route']['pathparts'][3];
        $spaceRows = $this->runData['db']->select('s_space', ['uid' => $uid], true);
        if (!$spaceRows) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/space/view';
            header("Location: $redirectUrl"); exit;
        }
    
        $this->runData['data']['space'] = $spaceRows[0];
        $spaceId = $this->runData['data']['space']['id'];
    
        // Handle the form submission
        if (isset($this->runData['request']->post['s_entity_id'])) {
            $csrfToken = $this->runData['request']->post['csrf_token'] ?? '';
            if (!$this->runData['request']->checkCSRFToken($csrfToken)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Missing or invalid CSRF token. Please refresh and try again.';
                return $this->runData;
            }
            $userId = $this->runData['request']->post['s_entity_id'];
            $roleId = $this->runData['request']->post['s_role_id'];
            $entityRows = $this->runData['db']->select('s_entity', [
                'id' => (int)$userId,
                's_type' => 'U',
                'livestatus' => '1',
            ], true);
            if (empty($entityRows)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Select a valid active user.';
                return $this->runData;
            }
            $roleRows = $this->runData['db']->select('s_role', [
                'id' => (int)$roleId,
                's_scope' => 'workspace',
            ], true);
            if (empty($roleRows)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Select a valid workspace role.';
                return $this->runData;
            }

            $added = $this->membershipHelper->assignUserRole($spaceId, (int)$userId, (int)$roleId, 'workspace', null);
            if (!$added) {
                $this->runData['route']['alert'] = 'warning';
                $this->runData['route']['alert_message'] = 'User already has that role.';
                $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                $redirectUrl = $this->runData['route']['rad_admin_url'] . '/space/viewone/' . $uid;
                header('Location: ' . $redirectUrl, true, 302);
                die();
            }

            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'User added to Workspace successfully';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);

            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/space/viewone/' . $uid;
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
    
        // If no post data, load the form
        $this->runData['route']['h1'] = 'Add Member: ' . $this->runData['data']['space']['s_name'];
        $this->runData['route']['meta_title'] = 'Add Member to Workspace';
    
        // Fetch all users with s_type 'U' who are not already associated with this space
        $allUsers = $this->runData['db']->select('s_entity', ['s_type' => 'U'], true);
        $filteredUsers = [];

        $assignedIds = $this->membershipHelper->getAssignedUserIds($spaceId);

        foreach ($allUsers as $user) {
            if (in_array((int)$user['id'], $assignedIds, true)) {
                continue;
            }
            $filteredUsers[] = $user;
        }

        // Fetch only roles with SaaS scopes
        $this->runData['data']['users'] = $filteredUsers;
        $this->runData['data']['roles'] = $this->runData['db']->query("SELECT * FROM s_role WHERE s_scope IN ('workspace')");
        $this->runData['data']['assigned_user_ids'] = $assignedIds;
        $this->runData['route']['backlink'] = $radAdminUrl . '/space/viewone/' . $uid;
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Workspaces' => $radAdminUrl . '/space/view',
            $this->runData['data']['space']['s_name'] => $radAdminUrl . '/space/viewone/' . $uid,
            'Add Member' => '',
        ];
    
        return $this->runData;
    }

    public function setowner() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        $uid = $this->runData['route']['pathparts'][3] ?? '';
        if ($uid === '') {
            $this->runData['request']->setAlert('Workspace not found', 'danger');
            header('Location: ' . $radAdminUrl . '/space/view', true, 302);
            die();
        }
        $spaceRows = $this->runData['db']->select('s_space', ['uid' => $uid], true);
        if (!$spaceRows) {
            $this->runData['request']->setAlert('Workspace not found', 'danger');
            header('Location: ' . $radAdminUrl . '/space/view', true, 302);
            die();
        }
        $space = $spaceRows[0];

        if (isset($this->runData['request']->post['s_owner_entity_id'])) {
            $csrfToken = $this->runData['request']->post['csrf_token'] ?? '';
            if (!$this->runData['request']->checkCSRFToken($csrfToken)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Missing or invalid CSRF token. Please refresh and try again.';
                return $this->runData;
            }
            $ownerId = (int)($this->runData['request']->post['s_owner_entity_id'] ?? 0);
            if ($ownerId <= 0) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Select a valid owner.';
                return $this->runData;
            }
            $entityRows = $this->runData['db']->select('s_entity', ['id' => $ownerId, 's_type' => 'U', 'livestatus' => '1'], true);
            if (empty($entityRows)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Owner must be an active user.';
                return $this->runData;
            }
            $this->runData['db']->update('s_space', ['s_owner_entity_id' => $ownerId], ['uid' => $uid]);
            $this->runData['request']->setAlert('Workspace owner updated successfully.', 'success');
            header('Location: ' . $radAdminUrl . '/space/viewone/' . $uid, true, 302);
            die();
        }

        $this->runData['route']['h1'] = 'Set Owner: ' . ($space['s_name'] ?? 'Workspace');
        $this->runData['route']['meta_title'] = 'Set Workspace Owner';
        $this->runData['route']['backlink'] = $radAdminUrl . '/space/viewone/' . $uid;
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Workspaces' => $radAdminUrl . '/space/view',
            $space['s_name'] => $radAdminUrl . '/space/viewone/' . $uid,
            'Set Owner' => '',
        ];
        $this->runData['data']['space'] = $space;
        $this->runData['data']['search_endpoint'] = $radAdminUrl . '/membership/searchEntities';
        return $this->runData;
    }

    /**
     * JSON endpoint to search eligible users for a workspace (async selector).
     */
    public function searchusers() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }
        $spaceUid = $this->runData['route']['pathparts'][3] ?? '';
        $spaceRows = $this->runData['db']->select('s_space', ['uid' => $spaceUid], true);
        if (!$spaceRows) {
            throw new \Exception('Workspace not found', 404);
        }
        $spaceId = (int)$spaceRows[0]['id'];

        $q = trim((string)($this->runData['request']->get['q'] ?? ''));
        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $pageSize = (int)($this->runData['request']->get['pageSize'] ?? 20);
        $pageSize = min(max($pageSize, 5), 50);
        $offset = ($page - 1) * $pageSize;

        $assignedIds = $this->membershipHelper->getAssignedUserIds($spaceId);
        $assignedPlaceholders = [];
        $params = [];
        foreach ($assignedIds as $idx => $id) {
            $assignedPlaceholders[] = ':aid' . $idx;
            $params[':aid' . $idx] = $id;
        }

        $sql = "SELECT id, s_name, s_identity, s_email FROM s_entity WHERE s_type = 'U'";
        if (!empty($assignedPlaceholders)) {
            $sql .= " AND id NOT IN (" . implode(',', $assignedPlaceholders) . ")";
        }
        if ($q !== '') {
            $sql .= " AND (LOWER(s_name) LIKE :q OR LOWER(s_identity) LIKE :q)";
            $params[':q'] = '%' . strtolower($q) . '%';
        }
        $sql .= " ORDER BY s_name ASC LIMIT " . (int)$pageSize . " OFFSET " . (int)$offset;

        // PDO-style query binding
        $rows = $this->runData['db']->query($sql, $params);
        $results = array_map(function ($row) {
            $row['email'] = $row['s_email'] ?? '';
            return $row;
        }, $rows);

        header('Content-Type: application/json');
        echo json_encode([
            'data' => $results,
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
        exit;
    }
    
    /*
     * Remove User from Space
     */
    public function removeUser() {
        // Check if the space UID, role ID, and user ID exist in the pathparts
        if (!isset($this->runData['route']['pathparts'][3]) || !isset($this->runData['route']['pathparts'][4]) || !isset($this->runData['route']['pathparts'][5])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Invalid request. Required parameters missing.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/space/view';
            header("Location: $redirectUrl"); exit;
        }
    
        // Get the space UID, role ID, and user ID from the pathparts
        $uid = $this->runData['route']['pathparts'][3];
        $roleId = $this->runData['route']['pathparts'][4];
        $userId = $this->runData['route']['pathparts'][5];
    
        // Get the space details using the UID
        $spaceRows = $this->runData['db']->select('s_space', ['uid' => $uid], true);
        if (!$spaceRows) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Workspace not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/space/view';
            header("Location: $redirectUrl"); exit;
        }
    
        $this->runData['data']['space'] = $spaceRows[0];
        $spaceId = $this->runData['data']['space']['id'];
    
        // Retrieve the current roles and users for this space
        $this->membershipHelper->syncLegacyAssignments($this->runData['data']['space']);
        $removed = $this->membershipHelper->removeUserRole($spaceId, (int)$userId, (int)$roleId);
        if (!$removed) {
            $this->runData['route']['alert'] = 'warning';
            $this->runData['route']['alert_message'] = 'Assignment not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/space/viewone/' . $uid;
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }

        // Set the alert
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'User removed from Workspace successfully';
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
    
        // Redirect to the view page of the space
        $redirectUrl = $this->runData['route']['rad_admin_url'] . '/space/viewone/' . $uid;
        header('Location: ' . $redirectUrl, true, 302);
        die();
    }    

    private function logSpaceActivity(string $action, int $spaceId, string $name, string $slug, string $status): void {
        try {
            $activitySvc = new \Core\Sys\ActivityService($this->runData['db']);
            $activitySvc->log([
                's_actor_id' => (int)($this->runData['entity']['id'] ?? 0) ?: null,
                's_object_type' => 'workspace',
                's_object_id' => $spaceId,
                's_action' => $action,
                's_message' => sprintf('Workspace %s: %s', $action, $name),
                's_payload' => [
                    'space_id' => $spaceId,
                    'name' => $name,
                    'slug' => $slug,
                    'status' => $status,
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore logging failures
        }
    }

    private function notifySpaceEvent(string $action, int $spaceId, array $extra = []): void {
        try {
            $notifSvc = $this->runData['notificationService'] ?? new \Core\Sys\NotificationService($this->runData['db']);
            if (!$notifSvc instanceof \Core\Sys\NotificationService) {
                return;
            }
            $message = sprintf('Workspace %s: %s', $action, $extra['name'] ?? ('#' . $spaceId));
            $notifSvc->logGlobalEvent($message, [
                'event_type' => 'workspace_' . $action,
                'created_by' => (int)($this->runData['entity']['id'] ?? 0) ?: null,
                'metadata' => array_merge(['space_id' => $spaceId], $extra),
            ]);
        } catch (\Throwable $e) {
            // ignore notification failures
        }
    }
}

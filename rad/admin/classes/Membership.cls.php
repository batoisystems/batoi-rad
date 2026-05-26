<?php
namespace RadAdmin;

use Core\Sys\NotificationService;

require_once __DIR__ . '/WorkspaceMembershipHelper.cls.php';

class Membership {
    private $runData = [];
    private $db;
    private $errorHandler;
    private $notificationService;
    private $membershipHelper;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        $this->notificationService = $runData['notificationService'] ?? null;
        if (!$this->notificationService instanceof NotificationService) {
            $this->notificationService = new NotificationService($this->db);
        }
        $this->membershipHelper = new WorkspaceMembershipHelper($this->db, $runData['entity']['id'] ?? null);
    }

    public function view() {
        $this->syncLegacySpaces();
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        $this->runData['route']['h1'] = 'Workspace Memberships';
        $this->runData['route']['meta_title'] = 'Workspace Memberships';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/home/view';

        $filters = [
            'space_id' => (int)($this->runData['request']->get['space_id'] ?? 0),
            'search' => trim((string)($this->runData['request']->get['search'] ?? '')),
            'status' => trim((string)($this->runData['request']->get['status'] ?? '')),
            'role_id' => (int)($this->runData['request']->get['role_id'] ?? 0),
            'scope_level' => trim((string)($this->runData['request']->get['scope_level'] ?? '')),
            'ms_id' => (int)($this->runData['request']->get['ms_id'] ?? 0),
            'missing_role' => (int)($this->runData['request']->get['missing_role'] ?? 0),
        ];

        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $perPage = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($perPage === 0) {
            $perPage = $this->getProfilePerPage(25);
        } elseif ($this->isAllowedPerPage($perPage)) {
            $this->saveProfilePerPage($perPage);
        } else {
            $perPage = 25;
        }

        $sort = trim((string)($this->runData['request']->get['sort'] ?? 'created_desc'));

        $method = strtoupper($this->runData['request']->method);
        if ($method === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->runData;
            }
            if (!$priv->can('idm_manage')) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Access restricted.';
                return $this->runData;
            }
            if (isset($this->runData['request']->post['export_simulation'])) {
                $this->exportSimulation();
            } elseif (isset($this->runData['request']->post['invite_membership'])) {
                $this->inviteMembership();
            } elseif (isset($this->runData['request']->post['simulate_membership'])) {
                $this->simulateMembership();
            } elseif (isset($this->runData['request']->post['assign_membership_role'])) {
                $this->assignRole();
            } elseif (isset($this->runData['request']->post['remove_membership_role'])) {
                $this->removeRole();
            } elseif (isset($this->runData['request']->post['bulk_action'])) {
                $this->handleBulkAction();
            }
        } elseif ($method === 'GET' && isset($this->runData['request']->get['bulk_action'])) {
            $this->handleBulkQuickAction();
        }

        $totalCount = $this->fetchMembershipCount($filters);
        $memberships = $this->fetchMemberships($filters, $page, $perPage, $sort);

        foreach ($memberships as &$membership) {
            $membership['principal_label'] = $this->buildPrincipalLabel($membership);
            $membership['principal_display'] = $this->buildPrincipalDisplay($membership);
        }

        $this->runData['data']['memberships'] = $memberships;
        $this->runData['data']['roles'] = $this->db->query(
            "SELECT * FROM s_role WHERE livestatus = '1' AND s_scope IN ('workspace','ms') ORDER BY s_role_name"
        );
        $this->runData['data']['workspace_roles'] = $this->db->query(
            "SELECT * FROM s_role WHERE livestatus = '1' AND s_scope = 'workspace' ORDER BY s_role_name"
        );
        $msFilter = ['livestatus' => '1'];
        if (!empty($filters['space_id'])) {
            $msFilter['space_id'] = $filters['space_id'];
        }
        $this->runData['data']['microservices'] = $this->db->select('s_ms', $msFilter, true, ['s_name' => 'ASC']);
        $this->runData['data']['spaces'] = $this->db->select('s_space', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        $this->runData['data']['current_space'] = null;
        if (!empty($filters['space_id'])) {
            $spaceRows = $this->db->select('s_space', ['id' => $filters['space_id']], true);
            $this->runData['data']['current_space'] = $spaceRows[0] ?? null;
        }
        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['can_idm_manage'] = $priv->can('idm_manage');
        $this->runData['data']['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalCount,
            'pages' => $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1,
            'sort' => $sort,
        ];
        if (!empty($this->simulationContext)) {
            $this->runData['data']['simulation'] = $this->simulationContext;
        }

        return $this->runData;
    }

    public function searchEntities() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            header('Content-Type: application/json');
            echo json_encode([]);
            exit;
        }
        header('Content-Type: application/json');
        $term = trim($this->runData['request']->get['q'] ?? '');
        if (mb_strlen($term) < 2) {
            echo json_encode([]);
            exit;
        }
        $like = '%' . $term . '%';
        $sql = "SELECT id, uid, s_name, s_identity, s_email
                FROM s_entity
                WHERE s_type = 'U' AND livestatus != '0'
                  AND (s_name LIKE :q OR s_identity LIKE :q OR s_email LIKE :q OR uid LIKE :q)
                ORDER BY s_name ASC
                LIMIT 20";
        $rows = $this->db->query($sql, [':q' => $like]);
        echo json_encode($rows ?? []);
        exit;
    }

    private function syncLegacySpaces(): void {
        $spaces = $this->db->select('s_space', [], true);
        foreach ($spaces as $space) {
            if (empty($space['s_roles_and_users'])) {
                continue;
            }
            $this->membershipHelper->syncLegacyAssignments($space);
        }
    }

    private function fetchMemberships(array $filters, int $page, int $perPage, string $sort): array {
        $sql = "SELECT m.*,
                       e.s_name AS entity_name,
                       e.s_identity AS entity_identity,
                       e.uid AS entity_uid,
                       s.s_name AS space_name,
                       s.uid AS space_uid,
                       r.s_role_name,
                       r.s_scope,
                       ms.s_name AS ms_name
                FROM s_space_membership m
                LEFT JOIN s_entity e ON e.id = m.s_entity_id
                LEFT JOIN s_space s ON m.space_id = s.id
                LEFT JOIN s_role r ON r.id = m.s_role_id
                LEFT JOIN s_ms ms ON ms.id = m.s_ms_id
                WHERE m.livestatus != '0'";
        $params = [];

        if ($filters['status'] !== '') {
            $sql .= " AND m.livestatus = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['space_id'])) {
            $sql .= " AND m.space_id = :space_id";
            $params[':space_id'] = $filters['space_id'];
        }

        if (!empty($filters['role_id'])) {
            $sql .= " AND m.s_role_id = :role_id";
            $params[':role_id'] = $filters['role_id'];
        }

        if ($filters['scope_level'] !== '') {
            $sql .= " AND m.s_scope_level = :scope_level";
            $params[':scope_level'] = $filters['scope_level'];
        }

        if (!empty($filters['ms_id'])) {
            $sql .= " AND m.s_ms_id = :ms_id";
            $params[':ms_id'] = $filters['ms_id'];
        }

        if (!empty($filters['missing_role'])) {
            $sql .= " AND (m.s_role_id IS NULL OR m.s_role_id = 0)";
        }

        if ($filters['search'] !== '') {
            $sql .= " AND (LOWER(s.s_name) LIKE :query
                       OR LOWER(e.s_name) LIKE :query
                       OR LOWER(e.s_identity) LIKE :query
                       OR CAST(m.s_entity_id AS CHAR) LIKE :raw
                       OR CAST(m.id AS CHAR) LIKE :raw)";
            $params[':query'] = '%' . strtolower($filters['search']) . '%';
            $params[':raw'] = '%' . $filters['search'] . '%';
        }

        $orderMap = [
            'created_desc' => 'm.createstamp DESC',
            'created_asc' => 'm.createstamp ASC',
            'entity_asc' => 'e.s_name ASC',
            'entity_desc' => 'e.s_name DESC',
            'space_asc' => 's.s_name ASC',
            'space_desc' => 's.s_name DESC',
        ];
        $orderBy = $orderMap[$sort] ?? $orderMap['created_desc'];
        $offset = max(0, ($page - 1) * $perPage);
        $sql .= " ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
        return $this->db->query($sql, $params);
    }

    private function fetchMembershipCount(array $filters): int {
        $sql = "SELECT COUNT(*) AS total
                FROM s_space_membership m
                LEFT JOIN s_entity e ON e.id = m.s_entity_id
                LEFT JOIN s_space s ON m.space_id = s.id
                WHERE m.livestatus != '0'";
        $params = [];

        if ($filters['status'] !== '') {
            $sql .= " AND m.livestatus = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['space_id'])) {
            $sql .= " AND m.space_id = :space_id";
            $params[':space_id'] = $filters['space_id'];
        }

        if (!empty($filters['role_id'])) {
            $sql .= " AND m.s_role_id = :role_id";
            $params[':role_id'] = $filters['role_id'];
        }

        if ($filters['scope_level'] !== '') {
            $sql .= " AND m.s_scope_level = :scope_level";
            $params[':scope_level'] = $filters['scope_level'];
        }

        if (!empty($filters['ms_id'])) {
            $sql .= " AND m.s_ms_id = :ms_id";
            $params[':ms_id'] = $filters['ms_id'];
        }

        if (!empty($filters['missing_role'])) {
            $sql .= " AND (m.s_role_id IS NULL OR m.s_role_id = 0)";
        }

        if ($filters['search'] !== '') {
            $sql .= " AND (LOWER(s.s_name) LIKE :query
                       OR LOWER(e.s_name) LIKE :query
                       OR LOWER(e.s_identity) LIKE :query
                       OR CAST(m.s_entity_id AS CHAR) LIKE :raw
                       OR CAST(m.id AS CHAR) LIKE :raw)";
            $params[':query'] = '%' . strtolower($filters['search']) . '%';
            $params[':raw'] = '%' . $filters['search'] . '%';
        }

        $rows = $this->db->query($sql, $params);
        return (int)($rows[0]['total'] ?? 0);
    }

    private function assignRole(): void {
        $membershipId = (int)($this->runData['request']->post['membership_id'] ?? 0);
        $roleId = (int)($this->runData['request']->post['role_id'] ?? 0);
        $scopeLevel = $this->runData['request']->post['scope_level'] ?? 'workspace';
        $msId = $this->runData['request']->post['s_ms_id'] ?? null;
        $effectiveFrom = $this->runData['request']->post['s_effective_from'] ?? null;
        $effectiveTo = $this->runData['request']->post['s_effective_to'] ?? null;

        if ($membershipId <= 0 || $roleId <= 0 || !in_array($scopeLevel, ['workspace', 'ms'], true)) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Invalid role assignment request.';
            return;
        }

        $roleRow = $this->db->select('s_role', ['id' => $roleId, 'livestatus' => '1'], true);
        if (empty($roleRow)) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Role not found.';
            return;
        }
        $scope = $roleRow[0]['s_scope'] ?? 'platform';
        $isSaasRole = in_array($scope, ['workspace','ms'], true);

        $membershipRows = $this->db->select('s_space_membership', ['id' => $membershipId, 'livestatus' => '1'], true);
        if (empty($membershipRows)) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Membership not found.';
            return;
        }
        $membership = $membershipRows[0];
        if (!isset($membership['livestatus']) || (string)$membership['livestatus'] !== '1') {
            $this->runData['route']['alert'] = 'warning';
            $this->runData['route']['alert_message'] = 'Cannot assign roles to inactive memberships.';
            return;
        }

        if (!$isSaasRole) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Only workspace or microservice roles are allowed in memberships.';
            return;
        }

        if ($scope === 'workspace') {
            $scopeLevel = 'workspace';
            $msId = null;
        } else {
            $scopeLevel = 'ms';
            if (empty($msId) || !ctype_digit((string)$msId)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Microservice-scoped roles must specify a microservice.';
                return;
            }
            $msRow = $this->db->select('s_ms', ['id' => (int)$msId], true);
            if (empty($msRow) || (int)($msRow[0]['space_id'] ?? 0) !== (int)($membership['space_id'] ?? 0)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Microservicelet must belong to the same workspace.';
                return;
            }
        }

        if ($effectiveFrom && $effectiveTo) {
            $fromTs = strtotime($effectiveFrom);
            $toTs = strtotime($effectiveTo);
            if (!$fromTs || !$toTs || $fromTs > $toTs) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Effective start must be before end.';
                return;
            }
        }

        $this->db->update('s_space_membership', [
            's_role_id' => $roleId,
            's_scope_level' => $scopeLevel,
            's_ms_id' => $scopeLevel === 'ms' ? (int)$msId : null,
            's_effective_from' => $effectiveFrom ?: null,
            's_effective_to' => $effectiveTo ?: null,
        ], ['id' => $membershipId], ['updatedby' => $this->runData['entity']['id'] ?? 1]);
        $this->logMembershipEvent('assign_role', $membershipId, [
            'role_id' => $roleId,
            'scope_level' => $scopeLevel,
            'ms_id' => $scopeLevel === 'ms' ? (int)$msId : null,
        ]);

        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Role assigned successfully.';
        $this->notifyMembershipEvent($membership, sprintf('Role #%d assigned to membership #%d', $roleId, $membershipId), [
            'event_type' => 'membership_role_assign',
            'role_id' => $roleId,
            'scope_level' => $scopeLevel,
            'ms_id' => $scopeLevel === 'ms' ? (int)$msId : null,
        ]);
    }

    private function removeRole(): void {
        $membershipId = (int)($this->runData['request']->post['membership_id'] ?? 0);
        if ($membershipId <= 0) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Invalid role reference.';
            return;
        }

        $membershipRows = $this->db->select('s_space_membership', ['id' => $membershipId], true);
        $this->db->update('s_space_membership', [
            's_role_id' => null,
            's_scope_level' => 'workspace',
            's_ms_id' => null,
            's_effective_from' => null,
            's_effective_to' => null,
        ], ['id' => $membershipId], ['updatedby' => $this->runData['entity']['id'] ?? 1]);
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Role assignment removed.';
        if (!empty($membershipRows)) {
            $this->notifyMembershipEvent($membershipRows[0], sprintf('Role removed from membership #%d', $membershipRows[0]['id']), [
                'event_type' => 'membership_role_remove',
            ]);
            $this->logMembershipEvent('remove_role', (int)$membershipRows[0]['id'], [
                'role_id' => (int)($membershipRows[0]['s_role_id'] ?? 0),
            ]);
        }
    }


    private function inviteMembership(): void {
        $spaceId = (int)($this->runData['request']->post['invite_space_id'] ?? 0);
        $rawEntity = trim((string)($this->runData['request']->post['invite_entity'] ?? ''));
        $entityId = (int)($this->runData['request']->post['invite_entity_id'] ?? 0);
        $roleId = (int)($this->runData['request']->post['invite_role_id'] ?? 0);
        $scopeLevel = $this->runData['request']->post['invite_scope_level'] ?? 'workspace';
        $msId = $this->runData['request']->post['invite_ms_id'] ?? null;
        $effectiveFrom = $this->runData['request']->post['invite_effective_from'] ?? null;
        $effectiveTo = $this->runData['request']->post['invite_effective_to'] ?? null;
        $createNewUser = !empty($this->runData['request']->post['invite_create_user']);
        $newUserName = trim((string)($this->runData['request']->post['invite_new_name'] ?? ''));
        $newUserIdentity = trim((string)($this->runData['request']->post['invite_new_identity'] ?? ''));
        $newUserEmail = trim((string)($this->runData['request']->post['invite_new_email'] ?? ''));
        $newUserPassword = (string)($this->runData['request']->post['invite_new_password'] ?? '');
        $newUserAutoPassword = !empty($this->runData['request']->post['invite_new_autopass']);

        if ($spaceId <= 0 && $entityId <= 0 && $rawEntity === '') {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Provide workspace and entity ID.';
            return;
        }

        if ($createNewUser) {
            if ($newUserIdentity === '' && $newUserEmail !== '') {
                $newUserIdentity = $newUserEmail;
            }
            if ($newUserIdentity === '' || $newUserName === '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Provide name and username/email for new user.';
                return;
            }
            if ($newUserAutoPassword) {
                $newUserPassword = $this->generateTempPassword();
            }
            if ($newUserPassword === '' || strlen($newUserPassword) < 8) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Password must be at least 8 characters long.';
                return;
            }
            if ($roleId > 0) {
                $roleRow = $this->db->select('s_role', ['id' => $roleId, 'livestatus' => '1'], true);
                if (empty($roleRow)) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Role not found.';
                    return;
                }
                $scope = $roleRow[0]['s_scope'] ?? 'platform';
                if (!in_array($scope, ['workspace', 'ms'], true)) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Only workspace or microservice roles can be assigned in memberships.';
                    return;
                }
                if ($scope === 'ms') {
                    if (empty($msId) || !ctype_digit((string)$msId)) {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Microservice-scoped roles require a microservice selection.';
                        return;
                    }
                    $msRow = $this->db->select('s_ms', ['id' => (int)$msId], true);
                    if (empty($msRow) || (int)($msRow[0]['space_id'] ?? 0) !== $spaceId) {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Microservicelet must belong to the selected workspace.';
                        return;
                    }
                }
            }
            try {
                $userService = new \Core\App\User($this->runData);
                $createPayload = [
                    's_identity' => $newUserIdentity,
                    's_name' => $newUserName,
                    'password' => $newUserPassword,
                    'email' => $newUserEmail ?: null,
                ];
                if ($roleId > 0) {
                    $createPayload['space_id'] = $spaceId;
                    $createPayload['workspace_role_id'] = $roleId;
                    if (!empty($msId)) {
                        $createPayload['ms_id'] = (int)$msId;
                    }
                }
                $entityId = (int)$userService->create($createPayload);
            } catch (\Throwable $e) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Unable to create user: ' . $e->getMessage();
                return;
            }
        }

        if ($entityId <= 0 && $rawEntity !== '') {
            if (ctype_digit($rawEntity)) {
                $entityId = (int)$rawEntity;
            } else {
                $lookup = $this->db->query(
                    "SELECT id FROM s_entity WHERE livestatus = '1' AND s_type = 'U'
                     AND (LOWER(s_identity) = :identity OR LOWER(uid) = :uid)",
                    [
                        ':identity' => strtolower($rawEntity),
                        ':uid' => strtolower($rawEntity),
                    ]
                );
                if (!empty($lookup)) {
                    $entityId = (int)$lookup[0]['id'];
                }
            }
        }

        if ($spaceId <= 0 || $entityId <= 0) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Provide a valid workspace and user.';
            return;
        }

        if ($createNewUser && $roleId > 0) {
            $this->runData['route']['alert'] = 'success';
            $message = 'User created and membership assigned.';
            if ($newUserAutoPassword) {
                $message .= ' Temp password: ' . $newUserPassword;
            }
            $this->runData['route']['alert_message'] = $message;
            return;
        }

        $entityRows = $this->db->select('s_entity', ['id' => $entityId, 'livestatus' => '1'], true);
        if (empty($entityRows) || ($entityRows[0]['s_type'] ?? '') !== 'U') {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Entity not found or not a user.';
            return;
        }

        $existing = $this->db->select('s_space_membership', [
            'space_id' => $spaceId,
            's_entity_id' => $entityId,
            'livestatus' => '1',
        ], true);

        if (!empty($existing)) {
            $this->runData['route']['alert'] = 'warning';
            $this->runData['route']['alert_message'] = 'Membership already exists for this principal.';
            return;
        }

        $rolePayload = [];
        if ($roleId > 0) {
            $roleRow = $this->db->select('s_role', ['id' => $roleId, 'livestatus' => '1'], true);
            if (empty($roleRow)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Role not found.';
                return;
            }
            $scope = $roleRow[0]['s_scope'] ?? 'platform';
            if (!in_array($scope, ['workspace','ms'], true)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Only workspace or microservice roles can be assigned in memberships.';
                return;
            }
            if ($scope === 'workspace') {
                $scopeLevel = 'workspace';
                $msId = null;
            } else {
                $scopeLevel = 'ms';
                if (empty($msId) || !ctype_digit((string)$msId)) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Microservice-scoped roles require a microservice selection.';
                    return;
                }
                $msRow = $this->db->select('s_ms', ['id' => (int)$msId], true);
                if (empty($msRow) || (int)($msRow[0]['space_id'] ?? 0) !== $spaceId) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Microservicelet must belong to the selected workspace.';
                    return;
                }
            }
            $rolePayload = [
                's_role_id' => $roleId,
                's_scope_level' => $scopeLevel,
                's_ms_id' => $scopeLevel === 'ms' ? (int)$msId : null,
                's_effective_from' => $effectiveFrom ?: null,
                's_effective_to' => $effectiveTo ?: null,
            ];
        }

        $newMembershipId = $this->db->insert('s_space_membership', array_merge([
            's_entity_id' => $entityId,
        ], $rolePayload), [
            // Database::insert auto-adds default fields like space_id from state_data.
            'space_id' => $spaceId,
            'createdby' => $this->runData['entity']['id'] ?? 1,
            'livestatus' => '1',
        ]);

        $this->runData['route']['alert'] = 'success';
        $message = 'Membership created successfully.';
        if ($createNewUser && $newUserAutoPassword) {
            $message .= ' Temp password: ' . $newUserPassword;
        }
        $this->runData['route']['alert_message'] = $message;
        $freshMembership = $this->db->select('s_space_membership', ['id' => $newMembershipId], true);
        if (!empty($freshMembership)) {
            $this->notifyMembershipEvent($freshMembership[0], sprintf('Membership #%d created for entity #%d', $newMembershipId, $entityId), [
                'event_type' => 'membership_invite',
            ], true);
            $this->logMembershipEvent('create', (int)$newMembershipId, [
                'entity_id' => $entityId,
                'space_id' => $spaceId,
            ]);
        }
    }

    private function handleBulkAction(): void {
        $action = trim((string)($this->runData['request']->post['bulk_action'] ?? ''));
        $ids = $this->sanitizeIdList($this->runData['request']->post['selected_memberships'] ?? []);
        if (empty($ids)) {
            $this->runData['route']['alert'] = 'warning';
            $this->runData['route']['alert_message'] = 'Select at least one membership.';
            return;
        }
        if ($action === '') {
            $this->runData['route']['alert'] = 'warning';
            $this->runData['route']['alert_message'] = 'Choose a bulk action before applying.';
            return;
        }
        switch ($action) {
            case 'suspend':
                $this->bulkUpdateStatus($ids, '3', 'Selected memberships suspended.');
                break;
            case 'activate':
                $this->bulkUpdateStatus($ids, '1', 'Selected memberships activated.');
                break;
            case 'set_role':
                $this->bulkAssignRole($ids);
                break;
            default:
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Unsupported action.';
                break;
        }
    }

    private function handleBulkQuickAction(): void {
        // placeholder for additional quick GET actions if needed later
    }

    private function bulkUpdateStatus(array $ids, string $status, string $successMessage): void {
        $memberships = $this->fetchMembershipRowsByIds($ids);

        $params = [];
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $placeholder = ':mid' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }
        $sql = sprintf("UPDATE s_space_membership SET livestatus = :status WHERE id IN (%s)", implode(',', $placeholders));
        $params[':status'] = $status;
        $this->db->query($sql, $params);
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = $successMessage;

        $label = $this->resolveLivestatusLabel($status);
        foreach ($memberships as $membership) {
            $this->notifyMembershipEvent($membership, sprintf('Membership #%d set to %s', $membership['id'], $label), [
                'event_type' => 'membership_status_change',
                'new_status' => $status,
            ]);
            $this->logMembershipEvent($status === '1' ? 'activate' : 'suspend', (int)$membership['id'], [
                'entity_id' => isset($membership['s_entity_id']) ? (int)$membership['s_entity_id'] : null,
                'space_id' => $membership['space_id'] ?? null,
            ]);
        }
    }

    private function bulkAssignRole(array $ids): void {
        $roleId = (int)($this->runData['request']->post['bulk_role_id'] ?? 0);
        $scopeLevel = $this->runData['request']->post['bulk_scope_level'] ?? 'workspace';
        $msId = $this->runData['request']->post['bulk_ms_id'] ?? null;
        $effectiveFrom = $this->runData['request']->post['bulk_effective_from'] ?? null;
        $effectiveTo = $this->runData['request']->post['bulk_effective_to'] ?? null;

        if ($roleId <= 0) {
            $this->runData['route']['alert'] = 'warning';
            $this->runData['route']['alert_message'] = 'Select a role for bulk assignment.';
            return;
        }

        $roleRow = $this->db->select('s_role', ['id' => $roleId, 'livestatus' => '1'], true);
        if (empty($roleRow)) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Role not found.';
            return;
        }
        $scope = $roleRow[0]['s_scope'] ?? 'platform';
        if (!in_array($scope, ['workspace','ms'], true)) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Only workspace or microservice roles can be assigned.';
            return;
        }

        if ($scope === 'workspace') {
            $scopeLevel = 'workspace';
            $msId = null;
        } else {
            $scopeLevel = 'ms';
            if (empty($msId) || !ctype_digit((string)$msId)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Microservice-scoped roles require a microservice selection.';
                return;
            }
        }

        if ($effectiveFrom && $effectiveTo) {
            $fromTs = strtotime($effectiveFrom);
            $toTs = strtotime($effectiveTo);
            if (!$fromTs || !$toTs || $fromTs > $toTs) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Effective start must be before end.';
                return;
            }
        }

        $memberships = $this->fetchMembershipRowsByIds($ids);
        $active = array_filter($memberships, static function ($row) {
            return isset($row['livestatus']) && (string)$row['livestatus'] === '1';
        });
        if (empty($active)) {
            $this->runData['route']['alert'] = 'warning';
            $this->runData['route']['alert_message'] = 'No active memberships selected.';
            return;
        }

        if ($scopeLevel === 'ms') {
            $msRow = $this->db->select('s_ms', ['id' => (int)$msId], true);
            if (empty($msRow)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Microservicelet not found.';
                return;
            }
            $msSpaceId = (int)($msRow[0]['space_id'] ?? 0);
            foreach ($active as $membership) {
                if ((int)($membership['space_id'] ?? 0) !== $msSpaceId) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Selected memberships span multiple workspaces. MS role assignments must match the microservice workspace.';
                    return;
                }
            }
        }

        $payload = [
            's_role_id' => $roleId,
            's_scope_level' => $scopeLevel,
            's_ms_id' => $scopeLevel === 'ms' ? (int)$msId : null,
            's_effective_from' => $effectiveFrom ?: null,
            's_effective_to' => $effectiveTo ?: null,
        ];

        foreach ($active as $membership) {
            $this->db->update('s_space_membership', $payload, ['id' => (int)$membership['id']], ['updatedby' => $this->runData['entity']['id'] ?? 1]);
            $this->notifyMembershipEvent($membership, sprintf('Membership #%d bulk role assigned', $membership['id']), [
                'event_type' => 'membership_role_assign_bulk',
                'role_id' => $roleId,
                'scope_level' => $scopeLevel,
                'ms_id' => $scopeLevel === 'ms' ? (int)$msId : null,
            ]);
            $this->logMembershipEvent('assign_role', (int)$membership['id'], [
                'role_id' => $roleId,
                'scope_level' => $scopeLevel,
                'ms_id' => $scopeLevel === 'ms' ? (int)$msId : null,
            ]);
        }

        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Bulk role assignment applied to selected memberships.';
    }

    private function sanitizeIdList($input): array {
        if (!is_array($input)) {
            if (is_string($input) && trim($input) !== '') {
                if ($input[0] === '[') {
                    $decoded = json_decode($input, true);
                    $input = is_array($decoded) ? $decoded : [];
                } else {
                    $input = array_filter(array_map('trim', explode(',', $input)));
                }
            } else {
                return [];
            }
        }
        $ids = [];
        foreach ($input as $value) {
            if (ctype_digit((string)$value)) {
                $ids[] = (int)$value;
            }
        }
        return array_values(array_unique($ids));
    }

    private function resolvePrincipalLabel(int $id): string {
        $rows = $this->db->select('s_entity', ['id' => $id], true);
        if (!empty($rows)) {
            return ($rows[0]['s_name'] ?? $rows[0]['s_identity'] ?? 'Entity') . " (#{$id})";
        }
        return "Entity (#{$id})";
    }

    private function resolvePrincipalDisplay(int $id): string {
        $rows = $this->db->select('s_entity', ['id' => $id], true);
        if (!empty($rows)) {
            $name = $rows[0]['s_name'] ?? '';
            $username = $rows[0]['s_identity'] ?? '';
            return trim(($name ?: 'Entity #' . $id) . ($username ? ' (@' . $username . ')' : ''));
        }
        return 'Entity #' . $id;
    }

    private function buildPrincipalLabel(array $membership): string {
        $id = (int)($membership['s_entity_id'] ?? 0);
        $name = trim((string)($membership['entity_name'] ?? ''));
        $identity = trim((string)($membership['entity_identity'] ?? ''));
        if ($name !== '' || $identity !== '') {
            $label = $name ?: $identity ?: 'Entity';
            return $label . " (#{$id})";
        }
        return $this->resolvePrincipalLabel($id);
    }

    private function buildPrincipalDisplay(array $membership): string {
        $id = (int)($membership['s_entity_id'] ?? 0);
        $name = trim((string)($membership['entity_name'] ?? ''));
        $identity = trim((string)($membership['entity_identity'] ?? ''));
        if ($name !== '' || $identity !== '') {
            return trim(($name ?: 'Entity #' . $id) . ($identity ? ' (@' . $identity . ')' : ''));
        }
        return $this->resolvePrincipalDisplay($id);
    }

    private function simulateMembership(): void {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Access denied for simulation.';
            return;
        }
        $membershipId = (int)($this->runData['request']->post['membership_id'] ?? 0);
        if ($membershipId <= 0) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Invalid membership reference for simulation.';
            return;
        }

        $data = $this->buildSimulationData($membershipId);
        if (!$data) {
            return;
        }
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'Showing role simulation for membership #' . $membershipId;
        $this->simulationContext = $data;
    }

    private function exportSimulation(): void {
        $membershipId = (int)($this->runData['request']->post['membership_id'] ?? 0);
        $data = $this->buildSimulationData($membershipId, false);
        if (!$data) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Unable to export simulation.';
            return;
        }

        $filename = sprintf('membership_%d_simulation.csv', $membershipId);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.$filename);
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Membership ID', 'Entity', 'Workspace', 'Role', 'Scope', 'Object Type', 'Object ID', 'Object Name']);
        foreach ($data['bindings'] as $binding) {
            fputcsv($out, [
                $membershipId,
                'Entity #' . (int)$data['membership']['s_entity_id'],
                $data['membership']['space_id'],
                $binding['s_role_name'] ?? ('Role #' . $binding['s_role_id']),
                $binding['scope_label'] ?? '',
                $binding['s_object_type'],
                $binding['s_object_id'],
                $binding['object_name'] ?? ''
            ]);
        }
        fclose($out);
        exit;
    }

    private function buildSimulationData(int $membershipId, bool $setAlerts = true): ?array {
        if ($membershipId <= 0) {
            if ($setAlerts) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid membership reference for simulation.';
            }
            return null;
        }
        $membershipRows = $this->db->select('s_space_membership', ['id' => $membershipId], true);
        if (empty($membershipRows)) {
            if ($setAlerts) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Membership not found for simulation.';
            }
            return null;
        }
        $membership = $membershipRows[0];
        if (in_array((string)($membership['livestatus'] ?? '0'), ['0','2','3'], true)) {
            if ($setAlerts) {
                $this->runData['route']['alert'] = 'warning';
                $this->runData['route']['alert_message'] = 'Simulation unavailable for inactive/archived memberships.';
            }
            return null;
        }

        $roles = [];
        $roleIds = [];
        if (!empty($membership['s_role_id'])) {
            $roleRow = $this->db->select('s_role', ['id' => $membership['s_role_id']], true);
            if (!empty($roleRow)) {
                $roles[] = [
                    's_role_id' => (int)$membership['s_role_id'],
                    's_role_name' => $roleRow[0]['s_role_name'] ?? ('Role #' . $membership['s_role_id']),
                    's_scope_level' => $membership['s_scope_level'] ?? 'workspace',
                    's_ms_id' => $membership['s_ms_id'] ?? null,
                ];
                $roleIds[] = (int)$membership['s_role_id'];
            }
        }

        $bindings = [];
        if (!empty($roleIds)) {
            $placeholders = [];
            $params = [];
            foreach ($roleIds as $index => $roleId) {
                $placeholder = ':rid' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $roleId;
            }
            $bindingSql = sprintf(
                "SELECT pb.*, role.s_role_name
                 FROM s_permission_binding pb
                 LEFT JOIN s_role role ON role.id = pb.s_role_id
                 WHERE pb.livestatus != '0' AND pb.s_role_id IN (%s)
                 ORDER BY pb.s_object_type, pb.s_object_id",
                implode(',', $placeholders)
            );
            $bindings = $this->db->query($bindingSql, $params);
            $bindings = $this->attachBindingObjectNames($bindings);
        }

        $roleScopes = [];
        foreach ($roles as &$roleRow) {
            $level = $roleRow['s_scope_level'] ?? 'workspace';
            $roleRow['scope_label'] = $level === 'ms' && !empty($roleRow['s_ms_id'])
                ? sprintf('Microservicelet (MS #%d)', $roleRow['s_ms_id'])
                : 'Workspace';
            $roleScopes[(int)$roleRow['s_role_id']] = $roleRow['scope_label'];
        }
        unset($roleRow);

        foreach ($bindings as &$binding) {
            $binding['scope_label'] = $roleScopes[(int)$binding['s_role_id']] ?? 'Workspace';
        }
        unset($binding);

        return [
            'membership' => $membership,
            'roles' => $roles,
            'bindings' => $bindings,
        ];
    }

    private function fetchMembershipRowsByIds(array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }

        $params = [];
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $placeholder = ':mem' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }
        $sql = sprintf(
            "SELECT * FROM s_space_membership WHERE id IN (%s)",
            implode(',', $placeholders)
        );
        return $this->db->query($sql, $params);
    }

    private function resolveLivestatusLabel(string $status): string {
        switch ($status) {
            case '1':
                return 'Active';
            case '2':
                return 'Archived';
            case '3':
                return 'Suspended';
            default:
                return 'Updated';
        }
    }

    private function notifyMembershipEvent(array $membership, string $message, array $metadata = [], bool $notifyPrincipal = true): void {
        if (!$this->notificationService instanceof NotificationService) {
            return;
        }
        $spaceId = isset($membership['space_id']) ? (int)$membership['space_id'] : 0;
        $radAdminUrl = $this->runData['route']['rad_admin_url'] ?? ($this->runData['config']['sys']['base_url'] . '/rad-admin');
        $context = [
            'created_by' => $this->runData['entity']['id'] ?? null,
            'metadata' => array_merge([
                'membership_id' => (int)($membership['id'] ?? 0),
                'entity_id' => isset($membership['s_entity_id']) ? (int)$membership['s_entity_id'] : null,
            ], $metadata),
            'link' => $radAdminUrl . '/membership/view?space_id=' . $spaceId,
            'event_type' => $metadata['event_type'] ?? 'membership_update',
        ];
        $this->notificationService->logWorkspaceEvent($message, $spaceId, $context);

        if ($notifyPrincipal && isset($membership['s_entity_id'])) {
            $this->notificationService->logUserEvent($message, (int)$membership['s_entity_id'], $context);
        }
    }

    private function logMembershipEvent(string $action, int $membershipId, array $extra = []): void {
        try {
            $activitySvc = new \Core\Sys\ActivityService($this->db);
            $activitySvc->log([
                's_actor_id' => (int)($this->runData['entity']['id'] ?? 0) ?: null,
                's_object_type' => 'membership',
                's_object_id' => $membershipId,
                's_action' => $action,
                's_message' => sprintf('Membership %s: #%d', $action, $membershipId),
                's_payload' => array_merge($extra, [
                    'membership_id' => $membershipId,
                ]),
            ]);
        } catch (\Throwable $e) {
            // swallow logging errors
        }
    }

    private function attachBindingObjectNames(array $bindings): array {
        $idsByType = [];
        foreach ($bindings as $binding) {
            $type = $binding['s_object_type'];
            $idsByType[$type][] = (int)$binding['s_object_id'];
        }
        $namesByType = [];
        foreach ($idsByType as $type => $ids) {
            $ids = array_values(array_unique(array_filter($ids)));
            if (empty($ids)) {
                continue;
            }
            switch ($type) {
                case 'ms':
                    $namesByType[$type] = $this->fetchObjectNames('s_ms', 's_name', $ids);
                    break;
                case 'route':
                    $namesByType[$type] = $this->fetchObjectNames('s_msroute', 's_name', $ids);
                    break;
                default:
                    $namesByType[$type] = [];
                    break;
            }
        }

        foreach ($bindings as &$binding) {
            $type = $binding['s_object_type'];
            $oid = (int)$binding['s_object_id'];
            $binding['object_name'] = $namesByType[$type][$oid] ?? '';
        }
        unset($binding);

        return $bindings;
    }

    private function fetchObjectNames(string $table, string $labelField, array $ids): array {
        if (empty($ids)) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $placeholder = ':obj' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }
        $sql = sprintf("SELECT id, %s FROM %s WHERE id IN (%s)", $labelField, $table, implode(',', $placeholders));
        $rows = $this->db->query($sql, $params);
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row[$labelField] ?? '';
        }
        return $map;
    }

    private function getProfilePerPage(int $fallback): int {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return $fallback;
        }
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
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
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return;
        }
        $definition = json_decode((string)($rows[0]['s_definition'] ?? '{}'), true);
        if (!is_array($definition)) {
            $definition = [];
        }
        $definition['profile_prefs']['per_page'] = $perPage;
        $this->db->update('s_entity', [
            's_definition' => json_encode($definition, JSON_UNESCAPED_SLASHES),
        ], ['id' => $entityId]);
    }

    private function isAllowedPerPage(int $perPage): bool {
        return in_array($perPage, [10, 25, 50, 100, 200], true);
    }

    private function verifyCsrf(): bool {
        $token = $this->runData['request']->post['csrf_token'] ?? '';
        if (!$this->runData['request']->checkCSRFToken($token)) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Missing or invalid CSRF token. Please refresh and try again.';
            return false;
        }
        return true;
    }

    private function generateTempPassword(int $length = 12): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@$%*_-';
        $max = strlen($chars) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }
}

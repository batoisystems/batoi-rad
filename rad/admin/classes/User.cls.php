<?php
namespace RadAdmin;

use Core\Sys\TimeHelper;
use Core\Sys\PrivilegeService;

require_once __DIR__ . '/WorkspaceMembershipHelper.cls.php';

class User {
    private $runData = [];
    private $errorHandler;
    private $membershipHelper;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->errorHandler = $runData['errorHandler'];
        $this->membershipHelper = new WorkspaceMembershipHelper($runData['db'], $runData['entity']['id'] ?? null);
    }

    public function view() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('user_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $this->runData['data']['can_idm_manage'] = $priv->can('user_manage');
        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Manage user accounts, inspect MFA status, and review workspace access.';
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        $this->runData['route']['h1'] = 'User Directory';
        $this->runData['route']['meta_title'] = 'Users';
        $this->runData['route']['backlink'] = $radAdminUrl . '/home/view';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Users & Roles' => $radAdminUrl . '/user/view',
            'Users' => '',
        ];

        $filters = [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
            'status' => trim((string)($this->runData['request']->get['status'] ?? '')),
            'mfa' => trim((string)($this->runData['request']->get['mfa'] ?? '')),
            'agreement' => trim((string)($this->runData['request']->get['agreement'] ?? '')),
        ];
        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $perPage = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($perPage === 0) {
            $perPage = $this->getProfilePerPage(25);
        } elseif ($this->isAllowedPerPage($perPage)) {
            $this->saveProfilePerPage($perPage);
        }
        if ($perPage < 10) {
            $perPage = 10;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }
        $onlyIssues = strtoupper((string)($this->runData['request']->get['only_issues'] ?? 'Y')) === 'Y';
        $issueType = strtolower(trim((string)($this->runData['request']->get['issue_type'] ?? '')));
        $filterUid = trim((string)($this->runData['request']->get['user_uid'] ?? ''));

        $where = ["s_type = 'U'", 'id != 1'];
        $params = [];
        if ($filters['status'] !== '') {
            $where[] = 'livestatus = :status';
            $params[':status'] = $filters['status'];
        }
        if ($filters['mfa'] !== '') {
            $where[] = 's_enable_mfa = :mfa';
            $params[':mfa'] = $filters['mfa'] === 'enabled' ? 'Y' : 'N';
        }
        if ($filters['agreement'] !== '') {
            $where[] = 's_agreement_signed = :agreement';
            $params[':agreement'] = $filters['agreement'] === 'signed' ? 'Y' : 'N';
        }
        if ($filters['q'] !== '') {
            $where[] = '(s_name LIKE :q OR s_identity LIKE :q OR uid LIKE :q OR s_email LIKE :q OR s_mobile LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        $whereSql = implode(' AND ', $where);

        $countRows = $this->runData['db']->query(
            "SELECT COUNT(*) AS total FROM s_entity WHERE {$whereSql}",
            $params
        );
        $totalUsers = (int)($countRows[0]['total'] ?? 0);
        $totalPages = (int)ceil($totalUsers / $perPage);
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $users = $this->runData['db']->query(
            "SELECT id, uid, livestatus, s_type, s_name, s_identity, s_nonsaas_role_id,
                    s_email, s_mobile, s_login_mode, s_enable_mfa, s_access_ips, s_agreement_signed
             FROM s_entity
             WHERE {$whereSql}
             ORDER BY COALESCE(updatestamp, createstamp) DESC, id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $membershipMap = [];
        if (!empty($users)) {
            $userIds = array_map(static function ($row) {
                return (int)$row['id'];
            }, $users);
            $userIds = array_values(array_filter($userIds));
            if (!empty($userIds)) {
                $idList = implode(',', $userIds);
                $membershipCounts = $this->runData['db']->query(
                    "SELECT s_entity_id, COUNT(DISTINCT space_id) AS total
                     FROM s_space_membership
                     WHERE livestatus != '0' AND s_entity_id IN ({$idList})
                     GROUP BY s_entity_id"
                );
                foreach ($membershipCounts as $row) {
                    $membershipMap[(int)$row['s_entity_id']] = (int)$row['total'];
                }
            }
        }

        $stats = [
            'total' => $totalUsers,
            'active' => 0,
            'mfa' => 0,
        ];
        $statusMeta = [
            '0' => ['label' => 'Inactive', 'badge' => 'secondary', 'slug' => 'inactive'],
            '1' => ['label' => 'Active', 'badge' => 'success', 'slug' => 'active'],
            '2' => ['label' => 'Archived', 'badge' => 'danger', 'slug' => 'archived'],
            '3' => ['label' => 'Suspended', 'badge' => 'warning', 'slug' => 'suspended'],
        ];

        foreach ($users as &$user) {
            $status = $statusMeta[$user['livestatus']] ?? $statusMeta['0'];
            $user['status_meta'] = $status;
            $user['status_slug'] = $status['slug'];
            $user['status_label'] = $status['label'];
            $mfaEnabled = ($user['s_enable_mfa'] ?? '') === 'Y';

            $user['mfa_slug'] = $mfaEnabled ? 'enabled' : 'disabled';
            $user['mfa_label'] = $mfaEnabled ? 'Enabled' : 'Disabled';
            $user['agreement_slug'] = ($user['s_agreement_signed'] ?? '') === 'Y' ? 'signed' : 'pending';
            $user['agreement_label'] = $user['agreement_slug'] === 'signed' ? 'Signed' : 'Pending';
            $user['primary_email'] = $user['s_email'] ?? '';
            $user['primary_mobile'] = $user['s_mobile'] ?? '';
            $user['access_ips'] = $user['s_access_ips'] ?? '';
            $user['spaces_count'] = $membershipMap[(int)$user['id']] ?? 0;
            $user['login_mode'] = $this->normalizeLoginMode($user['s_login_mode'] ?? null);
            $user['login_mode_label'] = $this->loginModeLabel($user['login_mode']);
            $user['is_protected'] = (int)$user['id'] === 1;
            $user['search_blob'] = strtolower(trim(
                ($user['s_name'] ?? '') . ' ' .
                ($user['s_identity'] ?? '') . ' ' .
                ($user['uid'] ?? '') . ' ' .
                ($user['primary_email'] ?? '') . ' ' .
                ($user['primary_mobile'] ?? '')
            ));
        }
        unset($user);
        $statsRows = $this->runData['db']->query(
            "SELECT
                SUM(CASE WHEN livestatus = '1' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN s_enable_mfa = 'Y' THEN 1 ELSE 0 END) AS mfa_count
             FROM s_entity
             WHERE s_type = 'U' AND id != 1"
        );
        $stats['active'] = (int)($statsRows[0]['active_count'] ?? 0);
        $stats['mfa'] = (int)($statsRows[0]['mfa_count'] ?? 0);

        $this->runData['data']['users'] = $users;
        $this->runData['data']['user_stats'] = $stats;
        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalUsers,
            'total_pages' => $totalPages,
        ];
        return $this->runData;
    }

    public function backfillportalrole() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('user_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $this->runData['route']['alert'] = 'warning';
        $this->runData['route']['alert_message'] = 'Portal roles are deprecated. Use s_nonsaas_role_id for primary roles.';
        return $this->runData;
    }

    public function viewone() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId !== 1 && !$priv->can('user_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $debugFlag = strtoupper((string)($this->runData['config']['sys']['dev_debug_flag']
            ?? $this->runData['config']['app']['dev_debug_flag']
            ?? 'N')) === 'Y';
        if ($debugFlag) {
            $this->runData['data']['debug_priv_enabled'] = true;
            $this->runData['data']['debug_priv'] = [
                'entity_id' => $entityId,
                'role' => $priv->role(),
                'can_user_manage' => $priv->can('user_manage'),
                'can_idm_manage' => $priv->can('idm_manage'),
            ];
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'User not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            header('Location: ' . $radAdminUrl . '/user/view'); exit;
        }

        $uid = $this->runData['route']['pathparts'][3];
        $userRows = $this->runData['db']->select('s_entity', ['uid' => $uid, 's_type' => 'U'], true);
        if (count($userRows) !== 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'User not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            header('Location: ' . $radAdminUrl . '/user/view'); exit;
        }

        $user = $userRows[0];
        $statusMeta = [
            '0' => ['label' => 'Inactive', 'badge' => 'secondary', 'slug' => 'inactive'],
            '1' => ['label' => 'Active', 'badge' => 'success', 'slug' => 'active'],
            '2' => ['label' => 'Archived', 'badge' => 'danger', 'slug' => 'archived'],
            '3' => ['label' => 'Suspended', 'badge' => 'warning', 'slug' => 'suspended'],
        ];
        $status = $statusMeta[$user['livestatus']] ?? $statusMeta['0'];
        $mfaEnabled = ($user['s_enable_mfa'] ?? '') === 'Y';
        $agreementSigned = ($user['s_agreement_signed'] ?? '') === 'Y';

        $spaceAssignments = $this->fetchUserWorkspaceRoles((int)$user['id']);
        $workspaceRoleCount = 0;
        $workspaceConflicts = [];
        foreach ($spaceAssignments as $spaceAssign) {
            $workspaceRoles = array_values(array_filter($spaceAssign['roles'] ?? [], static function ($role) {
                return strtolower((string)($role['scope_level'] ?? 'workspace')) === 'workspace';
            }));
            $rolesPerSpace = count($workspaceRoles);
            $workspaceRoleCount += $rolesPerSpace;
            if ($rolesPerSpace > 1) {
                $workspaceConflicts[] = [
                    'space_id' => (int)($spaceAssign['id'] ?? 0),
                    'space_name' => $spaceAssign['name'] ?? 'Workspace',
                    'role_count' => $rolesPerSpace,
                ];
            }
        }

        $platformRoleIds = $this->extractPlatformRoleIds($user);
        $platformRoles = $this->buildRoleDetails($platformRoleIds);
        $nonSaasRole = count($platformRoles) === 1 ? $platformRoles[0] : null;
        $totalRoleCount = $workspaceRoleCount + count($platformRoles);
        $nonSaasRoleDetail = null;
        $nonSaasRoleIssues = [];
        if ($nonSaasRole) {
            $roleRows = $this->runData['db']->select('s_role', ['id' => $nonSaasRole['id']], true);
            if (!empty($roleRows)) {
                $roleRow = $roleRows[0];
                $roleScope = strtolower((string)($roleRow['s_scope'] ?? ''));
                if ($roleScope !== 'platform') {
                    $nonSaasRoleIssues[] = 'Non-SaaS role scope is not platform';
                }
                $defaultRouteId = (int)($roleRow['s_default_route_id'] ?? 0);
                $defaultRoute = null;
                if ($defaultRouteId > 0) {
                    $routeRows = $this->runData['db']->select('s_msroute', ['id' => $defaultRouteId], true);
                    if (!empty($routeRows)) {
                        $routeRow = $routeRows[0];
                        $msRows = $this->runData['db']->select('s_ms', ['id' => $routeRow['s_ms_id'] ?? 0], true);
                        $defaultRoute = [
                            'id' => $defaultRouteId,
                            'name' => $routeRow['s_name'] ?? ('Route #' . $defaultRouteId),
                            'ms_name' => $msRows[0]['s_name'] ?? null,
                        ];
                    }
                }
                if ($defaultRouteId > 0 && !$defaultRoute) {
                    $nonSaasRoleIssues[] = 'Default route not found';
                }
                if ($defaultRouteId === 0) {
                    $nonSaasRoleIssues[] = 'Default route not set';
                }
                $nonSaasRoleDetail = [
                    'scope' => $roleScope ?: 'platform',
                    'default_route' => $defaultRoute,
                ];
            } else {
                $nonSaasRoleIssues[] = 'Non-SaaS role not found';
            }
        }
        $adminMfaMissingContact = false;
        if ($nonSaasRole && (int)$nonSaasRole['id'] === 1 && $this->isAdminMfaEnforced()) {
            $adminMfaMissingContact = empty($user['s_email']) && empty($user['s_mobile']);
        }

        $profile = [
            'id' => (int)$user['id'],
            'uid' => $user['uid'],
            'name' => $user['s_name'],
            'username' => $user['s_identity'],
            'status_meta' => $status,
            'status_slug' => $status['slug'],
            'status_label' => $status['label'],
            'mfa_slug' => $mfaEnabled ? 'enabled' : 'disabled',
            'mfa_label' => $mfaEnabled ? 'Enabled' : 'Disabled',
            'agreement_slug' => $agreementSigned ? 'signed' : 'pending',
            'agreement_label' => $agreementSigned ? 'Signed' : 'Pending',
            'email' => $user['s_email'] ?? '',
            'mobile' => $user['s_mobile'] ?? '',
            'access_ips' => $user['s_access_ips'] ?? '',
            'login_mode_slug' => $this->normalizeLoginMode($user['s_login_mode'] ?? null),
            'login_mode_label' => $this->loginModeLabel($this->normalizeLoginMode($user['s_login_mode'] ?? null)),
            'spaces_count' => count($spaceAssignments),
            'created_at' => $this->formatTimestamp($user['createstamp'] ?? null),
            'updated_at' => $this->formatTimestamp($user['updatestamp'] ?? null),
            'created_raw' => $user['createstamp'] ?? null,
            'updated_raw' => $user['updatestamp'] ?? null,
        ];

        $actorIds = [];
        if (!empty($user['createdby'])) {
            $actorIds[] = (int)$user['createdby'];
        }
        if (!empty($user['updatedby'])) {
            $actorIds[] = (int)$user['updatedby'];
        }
        $actorMap = $this->buildLookupMap('s_entity', $actorIds, 's_name');
        $activity = [
            'created' => [
                'timestamp' => $profile['created_at'],
                'actor' => $actorMap[(int)($user['createdby'] ?? 0)] ?? 'System',
            ],
            'updated' => [
                'timestamp' => $profile['updated_at'],
                'actor' => $actorMap[(int)($user['updatedby'] ?? 0)] ?? 'System',
            ],
        ];
        $lastLoginRows = $this->runData['db']->query(
            "SELECT MAX(s.createstamp) AS last_login
             FROM s_entity_session s
             INNER JOIN s_entity e ON e.id = s.s_entity_id
             WHERE s.s_entity_id = :entity_id
               AND s.livestatus != '0'
               AND e.s_type = 'U'",
            [':entity_id' => (int)$user['id']]
        );
        $lastLoginRaw = $lastLoginRows[0]['last_login'] ?? null;
        $activity['last_login'] = [
            'timestamp' => $this->formatTimestamp($lastLoginRaw),
            'raw' => $lastLoginRaw,
        ];

        $detailStats = [
            'spaces' => $profile['spaces_count'],
            'roles' => $totalRoleCount,
            'workspace_roles' => $workspaceRoleCount,
            'mfa_slug' => $profile['mfa_slug'],
            'mfa_label' => $profile['mfa_label'],
            'agreement_slug' => $profile['agreement_slug'],
            'agreement_label' => $profile['agreement_label'],
            'workspace_conflict_count' => count($workspaceConflicts),
        ];

        $safeName = htmlspecialchars($user['s_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
        $this->runData['route']['h1'] = 'User · ' . $safeName;
        $this->runData['route']['meta_title'] = 'User: ' . ($user['s_name'] ?? '');
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Users & Roles' => $radAdminUrl . '/user/view',
            'Users' => $radAdminUrl . '/user/view',
            $safeName => '',
        ];
        $this->runData['route']['backlink'] = $radAdminUrl . '/user/view';
        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Review credentials, MFA, and workspace access for this user.';
        }

        $this->runData['data']['user'] = $user;
        $this->runData['data']['user_profile'] = $profile;
        $this->runData['data']['user_spaces'] = $spaceAssignments;
        $this->runData['data']['non_saas_role'] = $nonSaasRole;
        $this->runData['data']['non_saas_role_detail'] = $nonSaasRoleDetail;
        $this->runData['data']['non_saas_role_issues'] = $nonSaasRoleIssues;
        $this->runData['data']['admin_mfa_missing_contact'] = $adminMfaMissingContact;
        $this->runData['data']['platform_roles'] = $platformRoles;
        $this->runData['data']['platform_role_conflict'] = count($platformRoles) > 1;
        $this->runData['data']['user_detail_stats'] = $detailStats;
        $this->runData['data']['workspace_role_conflicts'] = $workspaceConflicts;
        $this->runData['data']['user_activity'] = $activity;
        $this->runData['data']['can_idm_manage'] = $priv->can('idm_manage');

        return $this->runData;
    }

    public function save() {
        $this->runData['route']['h1'] = 'Save User';
        $this->runData['data']['route'] = '';
        return $this->runData;
    }

    public function diagnostics() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }

        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        $this->runData['route']['h1'] = 'User Role Diagnostics';
        $this->runData['route']['meta_title'] = 'User Role Diagnostics';
        $this->runData['route']['backlink'] = $radAdminUrl . '/user/view';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Users & Roles' => $radAdminUrl . '/user/view',
            'Diagnostics' => '',
        ];

        $page = max(1, (int)($this->runData['request']->get['page'] ?? 1));
        $perPage = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($perPage === 0) {
            $perPage = $this->getProfilePerPage(25);
        } elseif ($this->isAllowedPerPage($perPage)) {
            $this->saveProfilePerPage($perPage);
        }
        if ($perPage < 10) {
            $perPage = 10;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }

        $onlyIssues = strtoupper((string)($this->runData['request']->get['only_issues'] ?? 'Y')) === 'Y';
        $issueType = strtolower(trim((string)($this->runData['request']->get['issue_type'] ?? '')));
        $filterUid = trim((string)($this->runData['request']->get['user_uid'] ?? ''));

        if ($filterUid !== '') {
            $users = $this->runData['db']->select('s_entity', [
                'uid' => $filterUid,
                's_type' => 'U',
            ], true);
            $users = array_values(array_filter($users, static function ($u) {
                return (int)($u['id'] ?? 0) !== 1;
            }));
            $totalUsers = count($users);
            $totalPages = $totalUsers > 0 ? 1 : 0;
            $page = 1;
        } else {
            $countRows = $this->runData['db']->query(
                "SELECT COUNT(*) AS total FROM s_entity WHERE s_type = 'U' AND id != 1"
            );
            $totalUsers = (int)($countRows[0]['total'] ?? 0);
            $totalPages = (int)ceil($totalUsers / $perPage);
            if ($totalPages > 0 && $page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;

            $users = $this->runData['db']->query(
                "SELECT id, uid, s_name, s_identity, s_nonsaas_role_id
                 FROM s_entity
                 WHERE s_type = 'U' AND id != 1
                 ORDER BY COALESCE(updatestamp, createstamp) DESC, id DESC
                 LIMIT {$perPage} OFFSET {$offset}"
            );
        }

        $roles = $this->runData['db']->select('s_role', [], true);
        $roleMap = [];
        foreach ($roles as $role) {
            $roleMap[(int)$role['id']] = $role;
        }
        $membershipMap = [];
        if (!empty($users)) {
            $userIds = array_map(static function ($row) {
                return (int)$row['id'];
            }, $users);
            $userIds = array_values(array_filter($userIds));
            if (!empty($userIds)) {
                $idList = implode(',', $userIds);
                $membershipRows = $this->runData['db']->query(
                    "SELECT s_entity_id, space_id, s_role_id, s_scope_level, s_ms_id
                     FROM s_space_membership
                     WHERE livestatus != '0' AND s_role_id IS NOT NULL
                       AND s_entity_id IN ({$idList})"
                );
                foreach ($membershipRows as $row) {
                    $entityId = (int)$row['s_entity_id'];
                    $spaceId = (int)$row['space_id'];
                    $roleId = (int)$row['s_role_id'];
                    $scopeLevel = strtolower((string)($row['s_scope_level'] ?? 'workspace'));
                    if (!in_array($scopeLevel, ['workspace', 'ms'], true)) {
                        $scopeLevel = 'workspace';
                    }
                    if ($scopeLevel === 'ms') {
                        $msId = (int)($row['s_ms_id'] ?? 0);
                        if ($msId > 0) {
                            $membershipMap[$entityId]['ms'][$spaceId][$msId][] = $roleId;
                        } else {
                            $membershipMap[$entityId]['ms_invalid'][] = [
                                'space_id' => $spaceId,
                                'role_id' => $roleId,
                                'ms_id' => $msId,
                            ];
                        }
                    } else {
                        $membershipMap[$entityId]['workspace'][$spaceId][] = $roleId;
                    }
                    $membershipMap[$entityId]['rows'][] = [
                        'space_id' => $spaceId,
                        'role_id' => $roleId,
                        'scope_level' => $scopeLevel,
                        'ms_id' => (int)($row['s_ms_id'] ?? 0),
                    ];
                }
            }
        }

        $issues = [];
        foreach ($users as $user) {
            $nonSaasRole = $user['s_nonsaas_role_id'] ?? null;
            $spaces = $membershipMap[(int)$user['id']]['workspace'] ?? [];
            $msAssignments = $membershipMap[(int)$user['id']]['ms'] ?? [];
            $msInvalidAssignments = $membershipMap[(int)$user['id']]['ms_invalid'] ?? [];
            $membershipRows = $membershipMap[(int)$user['id']]['rows'] ?? [];

            $userIssues = [];
            $auth = [
                'role_id' => $user['s_nonsaas_role_id'] ?? null,
            ];

            $saasConflicts = [];
            if (!empty($spaces) && is_array($spaces)) {
                foreach ($spaces as $spaceId => $roleList) {
                    if (is_array($roleList) && count($roleList) > 1) {
                        $saasConflicts[(int)$spaceId] = array_values(array_unique(array_map('intval', $roleList)));
                    }
                }
            }
            $hasWorkspaceConflict = !empty($saasConflicts);
            if ($hasWorkspaceConflict) {
                $userIssues[] = 'Multiple SaaS roles per workspace';
            }

            $msConflicts = [];
            if (!empty($msAssignments) && is_array($msAssignments)) {
                foreach ($msAssignments as $spaceId => $msRoles) {
                    if (!is_array($msRoles)) {
                        continue;
                    }
                    foreach ($msRoles as $msId => $roleList) {
                        if (is_array($roleList) && count($roleList) > 1) {
                            if (!isset($msConflicts[$spaceId])) {
                                $msConflicts[$spaceId] = [];
                            }
                            $msConflicts[$spaceId][(int)$msId] = array_values(array_unique(array_map('intval', $roleList)));
                        }
                    }
                }
            }
            if (!empty($msConflicts)) {
                $userIssues[] = 'Multiple MS roles per microservice';
            }

            $invalidRoles = [];
            $invalidWorkspaceRoles = [];
            $invalidMsRoles = [];
            if (!empty($nonSaasRole) && !isset($roleMap[(int)$nonSaasRole])) {
                $invalidRoles[] = (int)$nonSaasRole;
            }
            if (!empty($membershipRows)) {
                foreach ($membershipRows as $row) {
                    $rid = (int)($row['role_id'] ?? 0);
                    $spaceId = (int)($row['space_id'] ?? 0);
                    $scopeLevel = strtolower((string)($row['scope_level'] ?? 'workspace'));
                    $msId = (int)($row['ms_id'] ?? 0);
                    if (!isset($roleMap[$rid])) {
                        $invalidRoles[] = $rid;
                        $invalidWorkspaceRoles[] = ['space_id' => $spaceId, 'role_id' => $rid];
                        continue;
                    }
                    $scope = strtolower((string)($roleMap[$rid]['s_scope'] ?? ''));
                    if ($scopeLevel === 'workspace' && $scope !== 'workspace') {
                        $invalidWorkspaceRoles[] = ['space_id' => $spaceId, 'role_id' => $rid];
                    }
                    if ($scopeLevel === 'ms') {
                        if ($scope !== 'ms') {
                            $invalidMsRoles[] = ['space_id' => $spaceId, 'role_id' => $rid, 'ms_id' => $msId];
                        }
                        if ($msId <= 0) {
                            $invalidMsRoles[] = ['space_id' => $spaceId, 'role_id' => $rid, 'ms_id' => $msId];
                        }
                    }
                }
            }
            if (!empty($invalidWorkspaceRoles)) {
                $userIssues[] = 'Invalid workspace role scope';
            }
            if (!empty($invalidMsRoles) || !empty($msInvalidAssignments)) {
                $userIssues[] = 'Invalid microservice role assignments';
            }
            if (!empty($invalidRoles)) {
                $userIssues[] = 'Invalid/archived roles assigned';
            }

            if (!empty($nonSaasRole) && isset($roleMap[(int)$nonSaasRole])) {
                $nsScope = strtolower((string)($roleMap[(int)$nonSaasRole]['s_scope'] ?? ''));
                if ($nsScope !== '' && $nsScope !== 'platform') {
                    $userIssues[] = 'Non-SaaS role scope is not platform';
                }
            }

            if ($issueType === 'workspace_conflict' && !$hasWorkspaceConflict) {
                continue;
            }
            if ($issueType === 'ms_conflict' && empty($msConflicts)) {
                continue;
            }
            if (!empty($userIssues)) {
                $issues[] = [
                    'user' => $user,
                    'auth' => $auth,
                    'issues' => $userIssues,
                    'saas_conflicts' => $saasConflicts,
                    'ms_conflicts' => $msConflicts,
                    'invalid_roles' => array_values(array_unique($invalidRoles)),
                    'invalid_workspace_roles' => $invalidWorkspaceRoles,
                    'invalid_ms_roles' => $invalidMsRoles,
                ];
            } elseif (!$onlyIssues) {
                $issues[] = [
                    'user' => $user,
                    'auth' => $auth,
                    'issues' => [],
                    'saas_conflicts' => [],
                    'ms_conflicts' => [],
                    'invalid_roles' => [],
                    'invalid_workspace_roles' => [],
                    'invalid_ms_roles' => [],
                ];
            }
        }

        $this->runData['data']['diagnostics'] = [
            'total_users' => $totalUsers,
            'issues' => $issues,
            'roles' => $roleMap,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalUsers,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'only_issues' => $onlyIssues ? 'Y' : 'N',
                'issue_type' => $issueType,
                'user_uid' => $filterUid,
                'dry_run' => strtoupper((string)($this->runData['request']->get['dry_run'] ?? 'N')) === 'Y' ? 'Y' : 'N',
            ],
        ];
        return $this->runData;
    }

    public function normalize() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        if (strtoupper($this->runData['request']->method) !== 'POST') {
            throw new \Exception('Invalid request method.', 405);
        }
        $payload = $this->runData['request']->post ?? [];
        $targetUid = $payload['user_uid'] ?? '';
        $nonSaasRole = isset($payload['non_saas_role']) ? (int)$payload['non_saas_role'] : null;
        $saasChoices = $payload['saas_choice'] ?? [];
        $msChoices = $payload['ms_choice'] ?? [];
        $removeScope = strtolower(trim((string)($payload['remove_scope'] ?? 'all')));
        if (!in_array($removeScope, ['all', 'workspace', 'ms'], true)) {
            $removeScope = 'all';
        }
        if (!is_array($saasChoices)) {
            $saasChoices = [];
        }
        if (!is_array($msChoices)) {
            $msChoices = [];
        }
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/user/diagnostics';

        if ($targetUid === '') {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Missing user reference.';
            return $this->diagnostics();
        }

        $userRows = $this->runData['db']->select('s_entity', ['uid' => $targetUid, 's_type' => 'U'], true);
        if (count($userRows) !== 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'User not found.';
            return $this->diagnostics();
        }
        $user = $userRows[0];
        if ((int)$user['id'] === 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Cannot normalize the system administrator.';
            return $this->diagnostics();
        }

        // Normalize primary role if provided
        if ($nonSaasRole !== null && $nonSaasRole > 0) {
            $this->runData['db']->update('s_entity', ['s_nonsaas_role_id' => $nonSaasRole], ['id' => $user['id']]);
        }

        // Normalize workspace roles if provided
        if (is_array($saasChoices)) {
            foreach ($saasChoices as $spaceId => $roleId) {
                $spaceId = (int)$spaceId;
                $roleId = (int)$roleId;
                if ($spaceId > 0 && $roleId > 0) {
                    $this->membershipHelper->assignUserRole($spaceId, (int)$user['id'], $roleId, 'workspace', null);
                } elseif ($spaceId > 0) {
                    $existing = $this->runData['db']->select('s_space_membership', [
                        'space_id' => $spaceId,
                        's_entity_id' => (int)$user['id'],
                        'livestatus' => '1',
                    ], true);
                    if (!empty($existing) && !empty($existing[0]['s_role_id'])) {
                        $this->membershipHelper->removeUserRole(
                            $spaceId,
                            (int)$user['id'],
                            (int)$existing[0]['s_role_id'],
                            (string)($existing[0]['s_scope_level'] ?? 'workspace'),
                            isset($existing[0]['s_ms_id']) ? (int)$existing[0]['s_ms_id'] : null
                        );
                    }
                }
            }
        }
        $removedCount = 0;
        $msRemovedCount = 0;
        if ($removeScope !== 'ms') {
            $removedCount = $this->pruneExtraWorkspaceMemberships((int)$user['id'], $saasChoices);
        }
        if ($removeScope !== 'workspace') {
            $msRemovedCount = $this->pruneExtraMsMemberships((int)$user['id'], $msChoices);
        }
        $this->runData['route']['alert'] = 'success';
        $totalRemoved = $removedCount + $msRemovedCount;
        $this->runData['route']['alert_message'] = $totalRemoved > 0
            ? 'User roles normalized. Removed ' . $totalRemoved . ' extra role(s).'
            : 'User roles normalized.';
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);

        $redirectParams = [];
        $onlyIssues = $payload['only_issues'] ?? null;
        $issueType = $payload['issue_type'] ?? null;
        $userUidFilter = $payload['user_uid_filter'] ?? null;
        $perPage = $payload['per_page'] ?? null;
        $page = $payload['page'] ?? null;
        if ($onlyIssues !== null && $onlyIssues !== '') {
            $redirectParams['only_issues'] = $onlyIssues;
        }
        if ($issueType !== null && $issueType !== '') {
            $redirectParams['issue_type'] = $issueType;
        }
        if ($userUidFilter !== null && $userUidFilter !== '') {
            $redirectParams['user_uid'] = $userUidFilter;
        }
        if ($perPage !== null && $perPage !== '') {
            $redirectParams['per_page'] = $perPage;
        }
        if ($page !== null && $page !== '') {
            $redirectParams['page'] = $page;
        }
        $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/diagnostics';
        if (!empty($redirectParams)) {
            $redirectUrl .= '?' . http_build_query($redirectParams);
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    public function normalizeAll() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        if (strtoupper($this->runData['request']->method) !== 'POST') {
            throw new \Exception('Invalid request method.', 405);
        }

        $dryRun = strtoupper((string)($this->runData['request']->post['dry_run'] ?? 'N')) === 'Y';
        $export = strtolower(trim((string)($this->runData['request']->post['export'] ?? '')));
        $conflictRows = $this->runData['db']->query(
            "SELECT s_entity_id, space_id
             FROM s_space_membership
             WHERE livestatus = '1' AND s_scope_level = 'workspace'
             GROUP BY s_entity_id, space_id
             HAVING COUNT(*) > 1"
        );
        $msConflictRows = $this->runData['db']->query(
            "SELECT s_entity_id, space_id, s_ms_id
             FROM s_space_membership
             WHERE livestatus = '1' AND s_scope_level = 'ms' AND s_ms_id IS NOT NULL
             GROUP BY s_entity_id, space_id, s_ms_id
             HAVING COUNT(*) > 1"
        );
        $users = [];
        foreach ($conflictRows as $row) {
            $uid = (int)($row['s_entity_id'] ?? 0);
            if ($uid > 0) {
                $users[$uid] = true;
            }
        }
        foreach ($msConflictRows as $row) {
            $uid = (int)($row['s_entity_id'] ?? 0);
            if ($uid > 0) {
                $users[$uid] = true;
            }
        }

        $removedTotal = 0;
        $userCount = 0;
        $report = [];
        foreach (array_keys($users) as $userId) {
            $preview = $this->previewExtraWorkspaceMemberships($userId, []);
            $msPreview = $this->previewExtraMsMemberships($userId, []);
            if (!empty($preview['removed']) || !empty($msPreview['removed'])) {
                $userCount++;
            }
            if (!empty($preview['removed'])) {
                $removedTotal += count($preview['removed']);
                $report[] = $preview;
            }
            if (!empty($msPreview['removed'])) {
                $removedTotal += count($msPreview['removed']);
                $report[] = $msPreview;
            }
            if (!$dryRun) {
                if (!empty($preview['removed'])) {
                    $this->applyWorkspaceMembershipPrune($preview);
                }
                if (!empty($msPreview['removed'])) {
                    $this->applyMsMembershipPrune($msPreview);
                }
            }
        }

        if ($dryRun) {
            $message = $userCount > 0
                ? 'Dry run: ' . $userCount . ' user(s) have role conflicts. ' . $removedTotal . ' role(s) would be archived.'
                : 'Dry run: no role conflicts found.';
            $this->runData['request']->setAlert($message, $userCount > 0 ? 'warning' : 'info');
        } else {
            $message = $userCount > 0
                ? 'Normalized role conflicts for ' . $userCount . ' user(s). Removed ' . $removedTotal . ' extra role(s).'
                : 'No role conflicts found.';
            $this->runData['request']->setAlert($message, $userCount > 0 ? 'success' : 'info');
        }
        $_SESSION['normalize_report'] = $report;

        $suffix = $dryRun ? '&dry_run=Y' : '';
        if ($export !== '') {
            $this->exportNormalizeReport($report, $export);
            exit;
        }
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/diagnostics?only_issues=Y' . $suffix);
        exit;
    }

    public function add() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        // Check if form has been submitted
        if (isset($this->runData['request']->post['s_username']) && ($this->runData['request']->post['s_username'] != '')) {
            $s_login_mode = $this->resolveLoginModeInput($this->runData['request']->post['s_login_mode'] ?? null);
            $s_fullname = $this->runData['request']->post['s_fullname'];
            $s_email = $this->sanitizeEmail($this->runData['request']->post['s_email'] ?? '');
            if ($s_email === false) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Please provide a valid email address.';
                return $this->runData;
            }
            $s_username = $this->runData['request']->post['s_username'];
            $s_mobile = $this->runData['request']->post['s_mobile'];
            $s_password = $this->runData['request']->post['s_password'];
            $s_enablemfa = $this->runData['request']->post['s_enablemfa'];
            $s_access_ips = $this->runData['request']->post['s_access_ips'];
            $s_roleId = isset($this->runData['request']->post['s_roleid']) ? (int)$this->runData['request']->post['s_roleid'] : 0;

            // Basic validation
            if (($s_fullname == '') || ($s_username == '') || ($s_password == '')) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Name, username and password are mandatory fields.';
                return $this->runData;
            }
            if (strlen($s_password) < 8) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Password must be at least 8 characters long.';
                return $this->runData;
            }

            $roleRows = $this->runData['db']->select('s_role', [
                'id' => $s_roleId,
                's_scope' => 'platform',
                'livestatus' => '1',
            ], true);
            if (empty($roleRows)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Please select a valid non-SaaS role for this user.';
                return $this->runData;
            }

            if ($s_roleId === 1 && $this->isAdminMfaEnforced() && empty($s_email) && empty($s_mobile)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Administrator login requires email or mobile for MFA delivery.';
                return $this->runData;
            }
    
            if ($s_enablemfa === 'Y' && !$s_email && !$s_mobile) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'When MFA is enabled, at least one of Email or Mobile Number must be filled out.';
                return $this->runData;
            }

            // Check if the username already exists
            $user = $this->runData['db']->select('s_entity', ['s_identity' => $s_username, 's_type' => 'U'], false);
            if (count($user) == 1) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Username already exists.';
                return $this->runData;
            }

            // Insert into s_entity table
            $iNewEntityId = $this->runData['db']->insert('s_entity', [
                's_type' => 'U',
                's_name' => $s_fullname,
                's_identity' => $s_username,
                's_identity_secret' => password_hash($s_password, PASSWORD_DEFAULT),
                's_login_mode' => $s_login_mode,
                's_email' => $s_email,
                's_mobile' => $s_mobile,
                's_enable_mfa' => $s_enablemfa,
                's_nonsaas_role_id' => $s_roleId,
                's_access_ips' => $s_access_ips,
                's_agreement_signed' => 'N',
                's_definition' => json_encode([]),
            ]);
            if (!$iNewEntityId) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'There was an error adding the user.';
                return $this->runData;
            }
              
            // If validations pass, attempt to create user
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'User added successfully!';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to the user view page
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/view';
            header("Location: $redirectUrl");exit;
        } else {
            if (!$this->runData['route']['alert_from_request']) {
                $this->runData['route']['alert'] = 'info';
                $this->runData['route']['alert_message'] = 'You may Add a new User here. Please choose Access Type as Human or Application. If you choose Application, the user will not be able to login to the system; however, you can use the API to access the system.';
            }
            $this->runData['route']['h1'] = 'Add User';
            $this->runData['route']['meta_title'] = 'Add User';
            $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/user/view';
            $this->runData['data']['user'] = [];
            $this->runData['data']['roles_non_saas'] = $this->runData['db']->select('s_role', ['s_scope' => 'platform'], true, ['s_role_name' => 'ASC']);
            return $this->runData;
        }
    }    

    public function edit() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        // Get the UID from the 3rd element of the pathparts
        $uid = $this->runData['route']['pathparts'][3];

        // Get the user data from s_entity table
        $userRows = $this->runData['db']->select('s_entity', ['uid' => $uid], true);

        if (count($userRows) != 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'User not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/view';
            header("Location: $redirectUrl");exit;
        }

        $this->runData['data']['user'] = $userRows[0];

        // Prevent edit display for User ID = 1
        if ($this->runData['data']['user']['id'] == 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Editing this user is not allowed.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/view';
            header("Location: $redirectUrl");exit;
        }

        // Check if form has been submitted
        if (isset($this->runData['request']->post['s_username']) && ($this->runData['request']->post['s_username'] != '')) {
            $s_login_mode = $this->resolveLoginModeInput($this->runData['request']->post['s_login_mode'] ?? null);
            $s_fullname = $this->runData['request']->post['s_fullname'];
            $s_email = $this->sanitizeEmail($this->runData['request']->post['s_email'] ?? '');
            if ($s_email === false) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Please provide a valid email address.';
                return $this->runData;
            }
            $s_username = $this->runData['request']->post['s_username'];
            $s_mobile = $this->runData['request']->post['s_mobile'];
            $s_enablemfa = $this->runData['request']->post['s_enablemfa'];
            $s_access_ips = $this->runData['request']->post['s_access_ips'];

            // Basic validation
            if (($s_fullname == '') || ($s_username == '')) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Name and username are mandatory fields.';
                return $this->runData;
            }

            if ($s_enablemfa === 'Y' && !$s_email && !$s_mobile) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'When MFA is enabled, at least one of Email or Mobile Number must be filled out.';
                return $this->runData;
            }

            // Check if the username already exists but not for the current user selected for edit
            $user = $this->runData['db']->select('s_entity', ['s_identity' => $s_username], true);
            // print $uid;print '<pre>';print_r($user); die('debug');
            if (count($user) == 1 && $user[0]['uid'] != $uid) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Username already exists.';
                return $this->runData;
            }

            // Update user data
            $updateData = [
                's_name' => $s_fullname,
                's_identity' => $s_username,
            ];

            if (isset($this->runData['request']->post['s_password'])) {
                $s_password = $this->runData['request']->post['s_password'];
                if ($s_password !== '') {
                    if (strlen($s_password) < 8) {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Password must be at least 8 characters long.';
                        return $this->runData;
                    }
                    $updateData['s_identity_secret'] = password_hash($s_password, PASSWORD_DEFAULT);
                }
            }

            $updateData['s_login_mode'] = $s_login_mode;
            $updateData['s_email'] = $s_email;
            $updateData['s_mobile'] = $s_mobile;
            $updateData['s_enable_mfa'] = $s_enablemfa;
            $updateData['s_access_ips'] = $s_access_ips;

            $userUpdateStatus = $this->runData['db']->update('s_entity', $updateData, [
                'uid' => $uid,
                's_type' => 'U',
            ], ['updatedby' => $this->runData['entity']['id'] ?? null]);

            if ($userUpdateStatus) {
                $this->runData['route']['alert'] = 'success';
                $this->runData['route']['alert_message'] = 'User updated successfully!';
                $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                // Redirect to the user view page
                $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/viewone/' . $uid;
                header("Location: $redirectUrl");exit;
            } else {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'There was an error updating the user.';
                return $this->runData;
            }
        } else {
            if (!$this->runData['route']['alert_from_request']) {
                $this->runData['route']['alert'] = 'info';
                $this->runData['route']['alert_message'] = 'You may edit the User details here.';
            }
            $this->runData['route']['h1'] = 'Edit User - ' . $this->runData['data']['user']['s_name'];
            $this->runData['route']['meta_title'] = 'Edit User';
            $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/user/viewone/' . $uid;
            return $this->runData;
        }
    }

    public function addRole() {
        $radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'User not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $radAdminUrl . '/user/view';
            header("Location: $redirectUrl"); exit;
        }

        $uid = $this->runData['route']['pathparts'][3];

        $isPost = strtoupper((string)($this->runData['request']->method ?? 'GET')) === 'POST';
        if ($isPost) {
            $roleKind = $this->runData['request']->post['role_kind'] ?? '';
            $target = ($roleKind === 'saas') ? 'manageWorkspaceRole' : 'managePlatformRole';
            $this->runData['request']->setAlert('Role management moved to the new workflow. Please continue there.', 'info');
            header('Location: ' . $radAdminUrl . '/user/' . $target . '/' . $uid);
            exit;
        }

        $this->runData['request']->setAlert('Role management moved to the new workflow.', 'info');
        header('Location: ' . $radAdminUrl . '/user/managePlatformRole/' . $uid);
        exit;
    }

    public function resetPassword() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('user_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['request']->setAlert('User not found.', 'danger');
            header('Location: ' . $radAdminUrl . '/user/view');
            exit;
        }

        $uid = $this->runData['route']['pathparts'][3];
        $userRows = $this->runData['db']->select('s_entity', ['uid' => $uid, 's_type' => 'U'], true);
        if (count($userRows) !== 1) {
            $this->runData['request']->setAlert('User not found.', 'danger');
            header('Location: ' . $radAdminUrl . '/user/view');
            exit;
        }

        $user = $userRows[0];
        $accessUrl = rtrim($this->runData['config']['sys']['base_url'] ?? '', '/') . '/login';
        $this->runData['data']['user'] = [
            'id' => (int)$user['id'],
            'uid' => $user['uid'],
            'name' => $user['s_name'] ?? '',
            'username' => $user['s_identity'] ?? '',
        ];
        $this->runData['data']['access_url'] = $accessUrl;

        $isPost = strtoupper((string)($this->runData['request']->method ?? 'GET')) === 'POST';
        if ($isPost) {
            $password = trim((string)($this->runData['request']->post['s_password'] ?? ''));
            $autoGenerate = strtoupper((string)($this->runData['request']->post['auto_generate'] ?? 'N')) === 'Y';
            if ($password === '' || $autoGenerate) {
                $password = $this->generatePassword();
                $autoGenerate = true;
            }

            if (strlen($password) < 8) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Password must be at least 8 characters long.';
            } else {
                $updateData = [
                    's_identity_secret' => password_hash($password, PASSWORD_DEFAULT),
                ];
                $updated = $this->runData['db']->update(
                    's_entity',
                    $updateData,
                    ['id' => (int)$user['id'], 's_type' => 'U'],
                    ['updatedby' => $this->runData['entity']['id'] ?? null]
                );
                if ($updated) {
                    $this->runData['route']['alert'] = 'success';
                    $this->runData['route']['alert_message'] = 'Password reset successfully.';
                    $this->runData['data']['reset_result'] = [
                        'password' => $password,
                        'auto_generated' => $autoGenerate,
                    ];
                } else {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Unable to reset the password. Please try again.';
                }
            }
        } elseif (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Set a new password for this user or generate one automatically.';
        }

        $this->runData['route']['h1'] = 'Reset Password';
        $this->runData['route']['meta_title'] = 'Reset Password';
        $this->runData['route']['backlink'] = $radAdminUrl . '/user/viewone/' . $uid;
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Users' => $radAdminUrl . '/user/view',
            $this->runData['data']['user']['name'] => $radAdminUrl . '/user/viewone/' . $uid,
            'Reset Password' => '',
        ];
        return $this->runData;
    }

    public function managePlatformRole() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }

        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['request']->setAlert('User not found.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/view');
            exit;
        }

        $uid = $this->runData['route']['pathparts'][3];
        $userRows = $this->runData['db']->select('s_entity', ['uid' => $uid, 's_type' => 'U'], true);
        if (count($userRows) !== 1) {
            $this->runData['request']->setAlert('User not found.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/view');
            exit;
        }

        $user = $userRows[0];
        $platformRoleIds = $this->extractPlatformRoleIds($user);
        $platformRoles = $this->buildRoleDetails($platformRoleIds);
        $roleOptions = $this->runData['db']->select(
            's_role',
            ['s_scope' => 'platform', 'livestatus' => '1'],
            true,
            ['s_role_name' => 'ASC']
        );

        $isPost = strtoupper((string)($this->runData['request']->method ?? 'GET')) === 'POST';
        if ($isPost && isset($this->runData['request']->post['action'])) {
            $action = $this->runData['request']->post['action'];
            $roleId = isset($this->runData['request']->post['role_id']) ? (int)$this->runData['request']->post['role_id'] : 0;

            if (!in_array($action, ['set_role', 'keep_role'], true)) {
                $this->runData['request']->setAlert('Invalid action.', 'danger');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/managePlatformRole/' . $uid);
                exit;
            }

            $roleRows = $this->runData['db']->select('s_role', [
                'id' => $roleId,
                's_scope' => 'platform',
                'livestatus' => '1',
            ], true);
            if (empty($roleRows)) {
                $this->runData['request']->setAlert('Select a valid platform role.', 'danger');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/managePlatformRole/' . $uid);
                exit;
            }

            $this->runData['db']->update('s_entity', [
                's_nonsaas_role_id' => $roleId,
            ], ['id' => $user['id']]);

            $this->runData['request']->setAlert('Platform role updated.', 'success');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/managePlatformRole/' . $uid);
            exit;
        }

        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        $this->runData['route']['h1'] = 'Non-SaaS Role';
        $this->runData['route']['meta_title'] = 'Manage Non-SaaS Role';
        $this->runData['route']['backlink'] = $radAdminUrl . '/user/viewone/' . $uid;
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Users & Roles' => $radAdminUrl . '/user/view',
            'User' => $radAdminUrl . '/user/viewone/' . $uid,
            'Non-SaaS Role' => '',
        ];

        $this->runData['data']['user'] = $user;
        $this->runData['data']['platform_roles'] = $platformRoles;
        $this->runData['data']['platform_role_conflict'] = count($platformRoles) > 1;
        $this->runData['data']['platform_role_options'] = $roleOptions;
        $this->runData['data']['platform_role_history'] = $this->buildEntityHistory($user);
        return $this->runData;
    }

    public function manageWorkspaceRole() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }

        if (!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['request']->setAlert('User not found.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/view');
            exit;
        }

        $uid = $this->runData['route']['pathparts'][3];
        $userRows = $this->runData['db']->select('s_entity', ['uid' => $uid, 's_type' => 'U'], true);
        if (count($userRows) !== 1) {
            $this->runData['request']->setAlert('User not found.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/view');
            exit;
        }

        $user = $userRows[0];
        $platformRoleIds = $this->extractPlatformRoleIds($user);
        $workspaceAssignments = $this->fetchUserWorkspaceRoles((int)$user['id']);
        $assignedSpaceIds = [];
        $assignedMsRoles = [];
        foreach ($workspaceAssignments as $assignment) {
            $spaceId = (int)($assignment['id'] ?? 0);
            foreach ($assignment['roles'] ?? [] as $role) {
                $scopeLevel = strtolower((string)($role['scope_level'] ?? 'workspace'));
                if ($scopeLevel === 'workspace') {
                    $assignedSpaceIds[] = $spaceId;
                } elseif ($scopeLevel === 'ms' && !empty($role['ms_id'])) {
                    $assignedMsRoles[$spaceId][(int)$role['ms_id']] = true;
                }
            }
        }
        $assignedSpaceIds = array_values(array_unique($assignedSpaceIds));

        $rolesSaas = $this->runData['db']->query(
            "SELECT * FROM s_role WHERE s_scope IN ('workspace','ms') AND livestatus = '1' ORDER BY s_role_name"
        );
        $spaces = $this->runData['db']->select('s_space', ['livestatus' => '1'], true, ['s_name' => 'ASC']);
        $microservices = $this->runData['db']->select('s_ms', ['livestatus' => '1'], true, ['s_name' => 'ASC']);

        $isPost = strtoupper((string)($this->runData['request']->method ?? 'GET')) === 'POST';
        if ($isPost && isset($this->runData['request']->post['action'])) {
            $action = $this->runData['request']->post['action'];
            $spaceId = isset($this->runData['request']->post['space_id']) ? (int)$this->runData['request']->post['space_id'] : 0;
            $roleId = isset($this->runData['request']->post['role_id']) ? (int)$this->runData['request']->post['role_id'] : 0;
            $msId = isset($this->runData['request']->post['ms_id']) ? (int)$this->runData['request']->post['ms_id'] : null;

            $roleRow = null;
            if ($roleId > 0) {
                $roleRows = $this->runData['db']->select('s_role', ['id' => $roleId, 'livestatus' => '1'], true);
                if (!empty($roleRows)) {
                    $roleRow = $roleRows[0];
                }
            }

            if (!in_array($action, ['add', 'change', 'remove'], true)) {
                $this->runData['request']->setAlert('Invalid action.', 'danger');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/manageWorkspaceRole/' . $uid);
                exit;
            }

            if ($spaceId <= 0) {
                $this->runData['request']->setAlert('Select a workspace.', 'danger');
                header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/manageWorkspaceRole/' . $uid);
                exit;
            }

            if ($action !== 'remove') {
                if (empty($platformRoleIds)) {
                    $this->runData['request']->setAlert('Assign a non-SaaS role before managing workspace roles.', 'danger');
                    header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/manageWorkspaceRole/' . $uid);
                    exit;
                }
                if (empty($roleRow) || !in_array(strtolower($roleRow['s_scope'] ?? ''), ['workspace', 'ms'], true)) {
                    $this->runData['request']->setAlert('Select a valid workspace/ms role.', 'danger');
                    header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/manageWorkspaceRole/' . $uid);
                    exit;
                }
                $roleScope = strtolower($roleRow['s_scope'] ?? 'workspace');
                $scopeLevel = $roleScope === 'ms' ? 'ms' : 'workspace';
                if ($scopeLevel === 'ms' && ($msId === null || $msId <= 0)) {
                    $this->runData['request']->setAlert('Select a microservice for ms-scoped role.', 'danger');
                    header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/manageWorkspaceRole/' . $uid);
                    exit;
                }
            }

            if ($action === 'remove') {
                if ($roleId <= 0) {
                    $this->runData['request']->setAlert('Missing role reference.', 'danger');
                    header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/manageWorkspaceRole/' . $uid);
                    exit;
                }
                $scopeLevel = strtolower((string)($this->runData['request']->post['scope_level'] ?? 'workspace'));
                if (!in_array($scopeLevel, ['workspace', 'ms'], true)) {
                    $scopeLevel = 'workspace';
                }
                if ($scopeLevel === 'ms' && ($msId === null || $msId <= 0)) {
                    $this->runData['request']->setAlert('Missing microservice for ms-scoped role.', 'danger');
                    header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/manageWorkspaceRole/' . $uid);
                    exit;
                }

                $this->membershipHelper->removeUserRole($spaceId, (int)$user['id'], $roleId, $scopeLevel, $msId);
                if (isset($authInfo['spaces'][$spaceId]) && is_array($authInfo['spaces'][$spaceId])) {
                    $authInfo['spaces'][$spaceId] = array_values(array_filter($authInfo['spaces'][$spaceId], static function ($rid) use ($roleId) {
                        return (int)$rid !== (int)$roleId;
                    }));
                    if (empty($authInfo['spaces'][$spaceId])) {
                        unset($authInfo['spaces'][$spaceId]);
                    }
                }
            }

            if ($action === 'change') {
                $targetSpace = null;
                foreach ($workspaceAssignments as $assignment) {
                    if ((int)($assignment['id'] ?? 0) === $spaceId) {
                        $targetSpace = $assignment;
                        break;
                    }
                }
                if ($targetSpace === null) {
                    $this->runData['request']->setAlert('Workspace assignment not found.', 'danger');
                    header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/manageWorkspaceRole/' . $uid);
                    exit;
                }

                foreach (($targetSpace['roles'] ?? []) as $role) {
                    $roleScope = strtolower((string)($role['scope_level'] ?? 'workspace'));
                    if ($scopeLevel === 'workspace' && $roleScope !== 'workspace') {
                        continue;
                    }
                    if ($scopeLevel === 'ms') {
                        if ($roleScope !== 'ms') {
                            continue;
                        }
                        if ((int)($role['ms_id'] ?? 0) !== (int)$msId) {
                            continue;
                        }
                    }
                    $this->membershipHelper->removeUserRole(
                        $spaceId,
                        (int)$user['id'],
                        (int)($role['id'] ?? 0),
                        $roleScope,
                        $role['ms_id'] ?? null
                    );
                }

                $this->membershipHelper->assignUserRole(
                    $spaceId,
                    (int)$user['id'],
                    $roleId,
                    $scopeLevel,
                    $msId
                );
            }

            if ($action === 'add') {
                if ($scopeLevel === 'workspace' && in_array($spaceId, $assignedSpaceIds, true)) {
                    $this->runData['request']->setAlert('This workspace already has a role. Use change instead.', 'danger');
                    header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/manageWorkspaceRole/' . $uid);
                    exit;
                }
                if ($scopeLevel === 'ms' && isset($assignedMsRoles[$spaceId][(int)$msId])) {
                    $this->runData['request']->setAlert('This microservice already has a role for the user.', 'danger');
                    header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/manageWorkspaceRole/' . $uid);
                    exit;
                }

                $this->membershipHelper->assignUserRole(
                    $spaceId,
                    (int)$user['id'],
                    $roleId,
                    $scopeLevel,
                    $msId
                );
            }

            $this->runData['request']->setAlert('Workspace roles updated.', 'success');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/manageWorkspaceRole/' . $uid);
            exit;
        }

        $availableSpaces = $spaces;

        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        $this->runData['route']['h1'] = 'Workspace Roles';
        $this->runData['route']['meta_title'] = 'Manage Workspace Roles';
        $this->runData['route']['backlink'] = $radAdminUrl . '/user/viewone/' . $uid;
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Users & Roles' => $radAdminUrl . '/user/view',
            'User' => $radAdminUrl . '/user/viewone/' . $uid,
            'Workspace Roles' => '',
        ];

        $this->runData['data']['user'] = $user;
        $this->runData['data']['workspace_assignments'] = $workspaceAssignments;
        $this->runData['data']['available_spaces'] = $availableSpaces;
        $this->runData['data']['assigned_space_ids'] = $assignedSpaceIds;
        $this->runData['data']['roles_saas'] = $rolesSaas;
        $this->runData['data']['microservices'] = $microservices;
        $this->runData['data']['has_platform_role'] = !empty($platformRoleIds);
        $this->runData['data']['workspace_role_history'] = $this->buildWorkspaceRoleHistory((int)$user['id']);
        return $this->runData;
    }

    public function deleteRole() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('delete')) {
            $this->runData['request']->setAlert('Access denied.', 'danger');
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/view';
            header("Location: $redirectUrl");
            exit;
        }
        if (!isset($this->runData['route']['pathparts'][3]) || !isset($this->runData['route']['pathparts'][4]) || !isset($this->runData['route']['pathparts'][5])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'User or role not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/view';
            header("Location: $redirectUrl");
            exit;
        }
    
        $uid = $this->runData['route']['pathparts'][3];
        $spaceId = $this->runData['route']['pathparts'][4];
        $roleId = $this->runData['route']['pathparts'][5];
        $scopeLevel = $this->runData['route']['pathparts'][6] ?? 'workspace';
        $msId = $this->runData['route']['pathparts'][7] ?? null;
    
        $userRows = $this->runData['db']->select('s_entity', ['uid' => $uid, 's_type' => 'U'], true);
        if (count($userRows) != 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'User not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/view';
            header("Location: $redirectUrl");
            exit;
        }
    
        $user = $userRows[0];
    
        if ($spaceId == '0') {
            // Remove primary (non-SaaS) role if it matches
            if ((int)($user['s_nonsaas_role_id'] ?? 0) === (int)$roleId) {
                $this->runData['db']->update('s_entity', ['s_nonsaas_role_id' => null], ['uid' => $uid]);
            } else {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Role not found for the user.';
                $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/viewone/' . $uid;
                header("Location: $redirectUrl");
                exit;
            }
        } else {
            $msId = is_numeric($msId) ? (int)$msId : null;
            $this->membershipHelper->removeUserRole((int)$spaceId, (int)$user['id'], (int)$roleId, (string)$scopeLevel, $msId);
        }

        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Role deleted successfully!';
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
    
        $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/viewone/' . $uid;
        header("Location: $redirectUrl");
        exit;
    }        

    public function archive() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() === 'developer' || !$priv->can('user_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        // Get the UID from the 3rd element of the pathparts
        $uid = $this->runData['route']['pathparts'][3];
    
        // Get the user data from s_entity table
        $user = $this->runData['db']->select('s_entity', ['uid' => $uid, 's_type' => 'U'], false);
    
        // If the user is not found, return an error
        if (count($user) != 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'User not found.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/view';
            header("Location: $redirectUrl");exit;
        }
    
        // Prevent archive action for User ID = 1
        if ($user[0]['id'] == 1) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Archiving this user is not allowed.';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/view';
            header("Location: $redirectUrl");exit;
        }
    
        // Set the livestatus to 2 (archived)
        $this->runData['db']->update('s_entity', ['livestatus' => '2'], ['uid' => $uid, 's_type' => 'U']);
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'User <strong>'. $user[0]['s_name']. '</strong> has been archived successfully.';
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        $redirectUrl = $this->runData['route']['rad_admin_url'] . '/user/view';
        header("Location: $redirectUrl");exit;
    }    

    public function deactivate() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() === 'developer' || !$priv->can('user_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $uid = $this->runData['route']['pathparts'][3] ?? '';
        $user = $this->runData['db']->select('s_entity', ['uid' => $uid, 's_type' => 'U'], false);
        if (count($user) != 1) {
            $this->runData['request']->setAlert('User not found.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/view');
            exit;
        }
        if ($user[0]['id'] == 1) {
            $this->runData['request']->setAlert('Deactivating this user is not allowed.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/view');
            exit;
        }
        $this->runData['db']->update('s_entity', ['livestatus' => '0'], ['uid' => $uid, 's_type' => 'U']);
        $this->runData['request']->setAlert('User deactivated successfully.', 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/viewone/' . $uid);
        exit;
    }

    public function suspend() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() === 'developer' || !$priv->can('user_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $uid = $this->runData['route']['pathparts'][3] ?? '';
        $user = $this->runData['db']->select('s_entity', ['uid' => $uid, 's_type' => 'U'], false);
        if (count($user) != 1) {
            $this->runData['request']->setAlert('User not found.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/view');
            exit;
        }
        if ($user[0]['id'] == 1) {
            $this->runData['request']->setAlert('Suspending this user is not allowed.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/view');
            exit;
        }
        $this->runData['db']->update('s_entity', ['livestatus' => '3'], ['uid' => $uid, 's_type' => 'U']);
        $this->runData['request']->setAlert('User suspended successfully.', 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/viewone/' . $uid);
        exit;
    }

    public function activate() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() === 'developer' || !$priv->can('user_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        $uid = $this->runData['route']['pathparts'][3] ?? '';
        $user = $this->runData['db']->select('s_entity', ['uid' => $uid, 's_type' => 'U'], false);
        if (count($user) != 1) {
            $this->runData['request']->setAlert('User not found.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/view');
            exit;
        }
        if ($user[0]['id'] == 1) {
            $this->runData['request']->setAlert('Activating this user is not allowed.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/view');
            exit;
        }
        $this->runData['db']->update('s_entity', ['livestatus' => '1'], ['uid' => $uid, 's_type' => 'U']);
        $this->runData['request']->setAlert('User activated successfully.', 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/viewone/' . $uid);
        exit;
    }

    private function buildLookupMap(string $table, array $ids, string $labelColumn): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $placeholder = ':id' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }

        $sql = "SELECT id, {$labelColumn} AS label FROM {$table} WHERE id IN (" . implode(',', $placeholders) . ")";
        $rows = $this->runData['db']->query($sql, $params);

        $map = [];
        foreach ($rows as $row) {
            if (!isset($row['id'])) {
                continue;
            }
            $map[(int)$row['id']] = $row['label'] ?? '';
        }
        return $map;
    }

    private function fetchUserWorkspaceRoles(int $userId): array {
        if ($userId <= 0) {
            return [];
        }
        $sql = "SELECT m.space_id,
                       s.s_name,
                       r.id AS role_id,
                       r.s_role_name,
                       r.s_scope,
                       m.s_scope_level,
                       m.s_ms_id,
                       ms.s_name AS ms_name
                FROM s_space_membership m
                LEFT JOIN s_role r ON r.id = m.s_role_id AND r.livestatus = '1'
                LEFT JOIN s_space s ON s.id = m.space_id
                LEFT JOIN s_ms ms ON ms.id = m.s_ms_id
                WHERE m.s_entity_id = :uid
                  AND m.livestatus = '1'
                ORDER BY s.s_name, r.s_role_name";
        $rows = $this->runData['db']->query($sql, [':uid' => $userId]);
        $grouped = [];
        foreach ($rows as $row) {
            $sid = (int)($row['space_id'] ?? 0);
            if (!isset($grouped[$sid])) {
                $grouped[$sid] = [
                    'id' => $sid,
                    'name' => $row['s_name'] ?? ('Space #' . $sid),
                    'roles' => [],
                ];
            }
            $scopeLevel = strtolower($row['s_scope_level'] ?? '');
            $grouped[$sid]['roles'][] = [
                'id' => (int)$row['role_id'],
                'name' => $row['s_role_name'] ?? ('Role #' . $row['role_id']),
                'scope_level' => $scopeLevel ?: 'workspace',
                'ms_id' => isset($row['s_ms_id']) ? (int)$row['s_ms_id'] : null,
                'ms_name' => $row['ms_name'] ?? null,
                'is_saas' => in_array($scopeLevel, ['workspace', 'ms'], true),
            ];
        }
        return array_values($grouped);
    }

    private function buildEntityHistory(array $entity): array {
        $createdBy = (int)($entity['createdby'] ?? 0);
        $updatedBy = (int)($entity['updatedby'] ?? 0);
        $actorMap = $this->buildLookupMap('s_entity', [$createdBy, $updatedBy], 's_name');

        return [
            'created' => [
                'timestamp' => $this->formatTimestamp($entity['createstamp'] ?? null),
                'actor' => $actorMap[$createdBy] ?? 'System',
            ],
            'updated' => [
                'timestamp' => $this->formatTimestamp($entity['updatestamp'] ?? null),
                'actor' => $actorMap[$updatedBy] ?? 'System',
            ],
        ];
    }

    private function buildWorkspaceRoleHistory(int $userId): array {
        if ($userId <= 0) {
            return [
                'timestamp' => '—',
                'actor' => 'System',
                'action' => 'No changes yet',
            ];
        }

        $sql = "SELECT m.createdby, m.createstamp, m.updatedby, m.updatestamp
                FROM s_space_membership m
                WHERE m.s_entity_id = :uid
                  AND m.s_role_id IS NOT NULL
                ORDER BY COALESCE(m.updatestamp, m.createstamp) DESC
                LIMIT 1";
        $rows = $this->runData['db']->query($sql, [':uid' => $userId]);
        if (empty($rows)) {
            return [
                'timestamp' => '—',
                'actor' => 'System',
                'action' => 'No changes yet',
            ];
        }

        $row = $rows[0];
        $actorId = (int)($row['updatedby'] ?? $row['createdby'] ?? 0);
        $actorMap = $this->buildLookupMap('s_entity', [$actorId], 's_name');
        $timestamp = $row['updatestamp'] ?? $row['createstamp'] ?? null;
        $action = !empty($row['updatestamp']) ? 'Last updated' : 'Last created';

        return [
            'timestamp' => $this->formatTimestamp($timestamp),
            'actor' => $actorMap[$actorId] ?? 'System',
            'action' => $action,
        ];
    }

    private function pruneExtraWorkspaceMemberships(int $userId, array $preferredRoles = [], ?string $batchId = null, array &$removedOut = []): int {
        if ($userId <= 0) {
            return 0;
        }
        $rows = $this->runData['db']->query(
            "SELECT id, s_entity_id, space_id, s_role_id, s_scope_level, s_ms_id, s_effective_from, s_effective_to,
                    createstamp, updatestamp
             FROM s_space_membership
             WHERE s_entity_id = :uid AND livestatus = '1' AND s_scope_level = 'workspace'
             ORDER BY space_id, COALESCE(updatestamp, createstamp) DESC, id DESC",
            [':uid' => $userId]
        );
        $bySpace = [];
        foreach ($rows as $row) {
            $spaceId = (int)($row['space_id'] ?? 0);
            if ($spaceId <= 0) {
                continue;
            }
            $bySpace[$spaceId][] = $row;
        }
        return $this->applyWorkspaceMembershipPrune(
            $this->previewExtraWorkspaceMemberships($userId, $preferredRoles),
            $batchId,
            $removedOut
        );
    }

    private function pruneExtraMsMemberships(int $userId, array $preferredRoles = [], ?string $batchId = null, array &$removedOut = []): int {
        if ($userId <= 0) {
            return 0;
        }
        return $this->applyMsMembershipPrune(
            $this->previewExtraMsMemberships($userId, $preferredRoles),
            $batchId,
            $removedOut
        );
    }

    private function previewExtraWorkspaceMemberships(int $userId, array $preferredRoles = []): array {
        $result = [
            'user_id' => $userId,
            'user_uid' => null,
            'user_name' => null,
            'user_identity' => null,
            'kept' => [],
            'removed' => [],
        ];
        if ($userId <= 0) {
            return $result;
        }
        $userRows = $this->runData['db']->select('s_entity', ['id' => $userId], true);
        if (!empty($userRows[0])) {
            $result['user_uid'] = $userRows[0]['uid'] ?? null;
            $result['user_name'] = $userRows[0]['s_name'] ?? null;
            $result['user_identity'] = $userRows[0]['s_identity'] ?? null;
        }
        $rows = $this->runData['db']->query(
            "SELECT id, space_id, s_role_id, s_scope_level, s_ms_id, createstamp, updatestamp
             FROM s_space_membership
             WHERE s_entity_id = :uid AND livestatus = '1' AND s_scope_level = 'workspace'
             ORDER BY space_id, COALESCE(updatestamp, createstamp) DESC, id DESC",
            [':uid' => $userId]
        );
        $bySpace = [];
        foreach ($rows as $row) {
            $spaceId = (int)($row['space_id'] ?? 0);
            if ($spaceId <= 0) {
                continue;
            }
            $bySpace[$spaceId][] = $row;
        }
        $spaceIds = array_keys($bySpace);
        $roleIds = [];
        foreach ($bySpace as $spaceId => $list) {
            foreach ($list as $row) {
                $rid = (int)($row['s_role_id'] ?? 0);
                if ($rid > 0) {
                    $roleIds[$rid] = true;
                }
            }
        }
        $spaceNames = $this->buildLookupMap('s_space', $spaceIds, 's_name');
        $roleNames = $this->buildLookupMap('s_role', array_keys($roleIds), 's_role_name');

        foreach ($bySpace as $spaceId => $list) {
            if (count($list) <= 1) {
                if (!empty($list[0])) {
                    $list[0]['space_name'] = $spaceNames[$spaceId] ?? null;
                    $list[0]['role_name'] = $roleNames[(int)($list[0]['s_role_id'] ?? 0)] ?? null;
                    $result['kept'][] = $list[0];
                }
                continue;
            }
            $preferredRole = isset($preferredRoles[$spaceId]) ? (int)$preferredRoles[$spaceId] : 0;
            $keepRow = null;
            if ($preferredRole > 0) {
                foreach ($list as $row) {
                    if ((int)($row['s_role_id'] ?? 0) === $preferredRole) {
                        $keepRow = $row;
                        break;
                    }
                }
            }
            if ($keepRow === null) {
                $keepRow = $list[0];
            }
            $keepRow['space_name'] = $spaceNames[$spaceId] ?? null;
            $keepRow['role_name'] = $roleNames[(int)($keepRow['s_role_id'] ?? 0)] ?? null;
            $result['kept'][] = $keepRow;
            foreach ($list as $row) {
                if ((int)$row['id'] === (int)$keepRow['id']) {
                    continue;
                }
                $row['space_name'] = $spaceNames[$spaceId] ?? null;
                $row['role_name'] = $roleNames[(int)($row['s_role_id'] ?? 0)] ?? null;
                $result['removed'][] = $row;
            }
        }
        return $result;
    }

    private function applyWorkspaceMembershipPrune(array $preview, ?string $batchId = null, array &$removedOut = []): int {
        $removed = 0;
        foreach ($preview['removed'] ?? [] as $row) {
            if (!empty($batchId)) {
                $removedOut[] = [
                    'id' => (int)$row['id'],
                    's_entity_id' => (int)($row['s_entity_id'] ?? 0),
                    'space_id' => (int)($row['space_id'] ?? 0),
                    's_role_id' => (int)($row['s_role_id'] ?? 0),
                    's_scope_level' => (string)($row['s_scope_level'] ?? 'workspace'),
                    's_ms_id' => isset($row['s_ms_id']) ? (int)$row['s_ms_id'] : null,
                    's_effective_from' => $row['s_effective_from'] ?? null,
                    's_effective_to' => $row['s_effective_to'] ?? null,
                    'livestatus' => '1',
                ];
            }
            $this->runData['db']->update('s_space_membership', [
                'livestatus' => '2',
                's_role_id' => null,
                's_scope_level' => 'workspace',
                's_ms_id' => null,
                's_effective_from' => null,
                's_effective_to' => null,
            ], ['id' => (int)$row['id']], ['updatedby' => $this->runData['entity']['id'] ?? null]);
            $removed++;
        }
        return $removed;
    }

    private function previewExtraMsMemberships(int $userId, array $preferredRoles = []): array {
        $result = [
            'user_id' => $userId,
            'user_uid' => null,
            'user_name' => null,
            'user_identity' => null,
            'kept' => [],
            'removed' => [],
        ];
        if ($userId <= 0) {
            return $result;
        }
        $userRows = $this->runData['db']->select('s_entity', ['id' => $userId], true);
        if (!empty($userRows[0])) {
            $result['user_uid'] = $userRows[0]['uid'] ?? null;
            $result['user_name'] = $userRows[0]['s_name'] ?? null;
            $result['user_identity'] = $userRows[0]['s_identity'] ?? null;
        }
        $rows = $this->runData['db']->query(
            "SELECT id, s_entity_id, space_id, s_role_id, s_scope_level, s_ms_id, s_effective_from, s_effective_to,
                    createstamp, updatestamp
             FROM s_space_membership
             WHERE s_entity_id = :uid AND livestatus = '1' AND s_scope_level = 'ms'
             ORDER BY space_id, s_ms_id, COALESCE(updatestamp, createstamp) DESC, id DESC",
            [':uid' => $userId]
        );
        $byMs = [];
        foreach ($rows as $row) {
            $spaceId = (int)($row['space_id'] ?? 0);
            $msId = (int)($row['s_ms_id'] ?? 0);
            if ($spaceId <= 0 || $msId <= 0) {
                continue;
            }
            $byMs[$spaceId][$msId][] = $row;
        }
        $spaceIds = array_keys($byMs);
        $roleIds = [];
        $msIds = [];
        foreach ($byMs as $spaceList) {
            foreach ($spaceList as $msId => $list) {
                $msIds[$msId] = true;
                foreach ($list as $row) {
                    $rid = (int)($row['s_role_id'] ?? 0);
                    if ($rid > 0) {
                        $roleIds[$rid] = true;
                    }
                }
            }
        }
        $spaceNames = $this->buildLookupMap('s_space', $spaceIds, 's_name');
        $roleNames = $this->buildLookupMap('s_role', array_keys($roleIds), 's_role_name');
        $msNames = $this->buildLookupMap('s_ms', array_keys($msIds), 's_name');

        foreach ($byMs as $spaceId => $msList) {
            foreach ($msList as $msId => $list) {
                if (count($list) <= 1) {
                    if (!empty($list[0])) {
                        $list[0]['space_name'] = $spaceNames[$spaceId] ?? null;
                        $list[0]['role_name'] = $roleNames[(int)($list[0]['s_role_id'] ?? 0)] ?? null;
                        $list[0]['ms_name'] = $msNames[(int)$msId] ?? null;
                        $result['kept'][] = $list[0];
                    }
                    continue;
                }
                $preferredRole = $preferredRoles[$spaceId][$msId] ?? 0;
                $keepRow = null;
                if ($preferredRole > 0) {
                    foreach ($list as $row) {
                        if ((int)($row['s_role_id'] ?? 0) === (int)$preferredRole) {
                            $keepRow = $row;
                            break;
                        }
                    }
                }
                if ($keepRow === null) {
                    $keepRow = $list[0];
                }
                $keepRow['space_name'] = $spaceNames[$spaceId] ?? null;
                $keepRow['role_name'] = $roleNames[(int)($keepRow['s_role_id'] ?? 0)] ?? null;
                $keepRow['ms_name'] = $msNames[(int)$msId] ?? null;
                $result['kept'][] = $keepRow;
                foreach ($list as $row) {
                    if ((int)$row['id'] === (int)$keepRow['id']) {
                        continue;
                    }
                    $row['space_name'] = $spaceNames[$spaceId] ?? null;
                    $row['role_name'] = $roleNames[(int)($row['s_role_id'] ?? 0)] ?? null;
                    $row['ms_name'] = $msNames[(int)$msId] ?? null;
                    $result['removed'][] = $row;
                }
            }
        }
        return $result;
    }

    private function applyMsMembershipPrune(array $preview, ?string $batchId = null, array &$removedOut = []): int {
        $removed = 0;
        foreach ($preview['removed'] ?? [] as $row) {
            if (!empty($batchId)) {
                $removedOut[] = [
                    'id' => (int)$row['id'],
                    's_entity_id' => (int)($row['s_entity_id'] ?? 0),
                    'space_id' => (int)($row['space_id'] ?? 0),
                    's_role_id' => (int)($row['s_role_id'] ?? 0),
                    's_scope_level' => (string)($row['s_scope_level'] ?? 'ms'),
                    's_ms_id' => isset($row['s_ms_id']) ? (int)$row['s_ms_id'] : null,
                    's_effective_from' => $row['s_effective_from'] ?? null,
                    's_effective_to' => $row['s_effective_to'] ?? null,
                    'livestatus' => '1',
                ];
            }
            $this->runData['db']->update('s_space_membership', [
                'livestatus' => '2',
                's_role_id' => null,
                's_scope_level' => 'ms',
                's_ms_id' => null,
                's_effective_from' => null,
                's_effective_to' => null,
            ], ['id' => (int)$row['id']], ['updatedby' => $this->runData['entity']['id'] ?? null]);
            $removed++;
        }
        return $removed;
    }

    private function exportNormalizeReport(array $report, string $format): void {
        $format = strtolower($format);
        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="normalize-report.json"');
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="normalize-report.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['user_id', 'user_uid', 'user_name', 'user_identity', 'action', 'space_id', 'space_name', 'role_id', 'role_name']);
            foreach ($report as $entry) {
                $userId = (int)($entry['user_id'] ?? 0);
                $userUid = (string)($entry['user_uid'] ?? '');
                $userName = (string)($entry['user_name'] ?? '');
                $userIdentity = (string)($entry['user_identity'] ?? '');
                foreach ($entry['kept'] ?? [] as $row) {
                    fputcsv($out, [
                        $userId,
                        $userUid,
                        $userName,
                        $userIdentity,
                        'kept',
                        (int)($row['space_id'] ?? 0),
                        (string)($row['space_name'] ?? ''),
                        (int)($row['s_role_id'] ?? 0),
                        (string)($row['role_name'] ?? ''),
                    ]);
                }
                foreach ($entry['removed'] ?? [] as $row) {
                    fputcsv($out, [
                        $userId,
                        $userUid,
                        $userName,
                        $userIdentity,
                        'removed',
                        (int)($row['space_id'] ?? 0),
                        (string)($row['space_name'] ?? ''),
                        (int)($row['s_role_id'] ?? 0),
                        (string)($row['role_name'] ?? ''),
                    ]);
                }
            }
            fclose($out);
            return;
        }
        throw new \InvalidArgumentException('Unsupported export format.');
    }

    private function getNormalizeStoreDir(): string {
        $baseDir = $this->runData['config']['dir']['data'] ?? '';
        $dir = rtrim($baseDir, '/') . '/temp/role-normalize';
        if ($baseDir !== '' && !is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function createNormalizeBatchId(): string {
        return date('YmdHis') . '-' . bin2hex(random_bytes(4));
    }

    private function storeNormalizeBatch(string $batchId, array $report, array $removed): void {
        $dir = $this->getNormalizeStoreDir();
        if ($dir === '' || !is_dir($dir)) {
            return;
        }
        $payload = [
            'batch_id' => $batchId,
            'timestamp' => date('c'),
            'actor_id' => (int)($this->runData['entity']['id'] ?? 0),
            'removed' => array_values($removed),
            'report' => $report,
        ];
        $path = $dir . '/' . $batchId . '.json';
        @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function loadNormalizeBatch(string $batchId): ?array {
        $dir = $this->getNormalizeStoreDir();
        if ($dir === '' || !is_dir($dir)) {
            return null;
        }
        $path = $dir . '/' . $batchId . '.json';
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        $decoded = json_decode($raw ?: '', true);
        return is_array($decoded) ? $decoded : null;
    }

    public function rollbackNormalize() {
        $priv = new PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage')) {
            throw new \Exception('Access denied.', 403);
        }
        if (strtoupper($this->runData['request']->method) !== 'POST') {
            throw new \Exception('Invalid request method.', 405);
        }
        $batchId = trim((string)($this->runData['request']->post['batch_id'] ?? ''));
        if ($batchId === '') {
            $this->runData['request']->setAlert('Missing normalization batch id.', 'danger');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/diagnostics');
            exit;
        }
        $payload = $this->loadNormalizeBatch($batchId);
        if (empty($payload['removed'])) {
            $this->runData['request']->setAlert('No rollback data found for this batch.', 'warning');
            header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/diagnostics');
            exit;
        }
        $restored = 0;
        foreach ($payload['removed'] as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $updates = [
                'livestatus' => '1',
                's_role_id' => (int)($row['s_role_id'] ?? 0),
                's_scope_level' => (string)($row['s_scope_level'] ?? 'workspace'),
                's_ms_id' => isset($row['s_ms_id']) ? (int)$row['s_ms_id'] : null,
                's_effective_from' => $row['s_effective_from'] ?? null,
                's_effective_to' => $row['s_effective_to'] ?? null,
            ];
            $this->runData['db']->update('s_space_membership', $updates, ['id' => $id], ['updatedby' => $this->runData['entity']['id'] ?? null]);
            $restored++;
        }
        $this->runData['request']->setAlert('Rollback completed for ' . $restored . ' membership record(s).', 'success');
        header('Location: ' . $this->runData['route']['rad_admin_url'] . '/user/diagnostics');
        exit;
    }

    private function extractPlatformRoleIds(array $user): array {
        $roleId = (int)($user['s_nonsaas_role_id'] ?? 0);
        return $roleId > 0 ? [$roleId] : [];
    }

    private function buildRoleDetails(array $roleIds): array {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static function ($id) {
            return $id > 0;
        })));
        if (empty($roleIds)) {
            return [];
        }

        $nameMap = $this->buildLookupMap('s_role', $roleIds, 's_role_name');
        $roles = [];
        foreach ($roleIds as $roleId) {
            $roles[] = [
                'id' => $roleId,
                'name' => $nameMap[$roleId] ?? ('Role #' . $roleId),
            ];
        }
        return $roles;
    }

    private function formatTimestamp(?string $value): string {
        if (empty($value)) {
            return '—';
        }
        $timezone = TimeHelper::resolveTimezone(
            $this->runData['entity']['timezone'] ?? null,
            $this->runData['config']['sys']['timezone_default'] ?? null
        );
        return TimeHelper::formatUtc($value, $timezone, 'd M Y, H:i') ?? '—';
    }

    private function formatRoles($assignedRoles) {
        $formattedRoles = [];
        foreach ($assignedRoles as $spaceId => $roleIds) {
            if ($spaceId == 'nonSaaS') {
                foreach ($roleIds as $roleId) {
                    $formattedRoles[] = '0:' . $roleId;
                }
            } else {
                $formattedRoles[] = $spaceId . ':' . implode(',', $roleIds);
            }
        }
        return implode(';', $formattedRoles);
    }

    private function parseRoles($rolesString) {
        $roles = explode(';', $rolesString);
        $parsedRoles = [];
        foreach ($roles as $role) {
            if (strpos($role, ':') !== false) {
                list($spaceId, $roleIds) = explode(':', $role);
                $parsedRoles[$spaceId] = explode(',', $roleIds);
            }
        }
        return $parsedRoles;
    }

    private function normalizeLoginMode(?string $mode): string {
        $mode = strtolower((string)$mode);
        return match ($mode) {
            'se' => 'self',
            'ba' => 'batoi',
            'gl' => 'google',
            'tw' => 'twitter',
            'application' => 'application',
            'api' => 'api',
            default => 'human',
        };
    }

    private function loginModeLabel(string $mode): string {
        return match ($mode) {
            'self' => 'Self',
            'batoi' => 'Batoi',
            'google' => 'Google',
            'twitter' => 'Twitter',
            'application' => 'Application',
            'api' => 'API',
            default => 'Human',
        };
    }

    private function resolveLoginModeInput($input): string {
        $mode = 'SE';
        if (is_array($input)) {
            $mode = (string)($input[0] ?? 'SE');
        } elseif ($input !== null) {
            $mode = (string)$input;
        }
        $mode = strtoupper(trim($mode));
        $allowed = ['SE', 'BA', 'GL', 'TW', 'API', 'APPLICATION'];
        return in_array($mode, $allowed, true) ? $mode : 'SE';
    }

    private function sanitizeEmail(string $value) {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : false;
    }

    private function isAdminMfaEnforced(): bool {
        $enforceAdmin = (bool)($this->runData['config']['sys']['enforce_admin_mfa'] ?? false);
        try {
            $rows = $this->runData['db']->select('s_config', ['s_config_handle' => 'mfa_settings'], true);
            if (!empty($rows[0]['s_config_value'])) {
                $decoded = json_decode($rows[0]['s_config_value'], true);
                if (is_array($decoded)) {
                    $settings = new \Core\Sys\MfaSettings($decoded);
                    $enforceAdmin = $settings->enforceAdmin();
                }
            }
        } catch (\Throwable $e) {
            // ignore and fall back to config
        }
        return $enforceAdmin;
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

    private function generatePassword(int $length = 12): string {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%&*?';
        $max = strlen($alphabet) - 1;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }
        return $password;
    }

    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

<?php
namespace RadAdmin;
use DateTime;
use Core\Sys\BranchService;
class Content{
    private $runData = [];
    private $db;
    private $errorHandler;
    private $branchService;
    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        $this->branchService = new BranchService(
            $this->db,
            $runData['config'] ?? [],
            $runData['entity'] ?? [],
            $runData['request'] ?? null
        );
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }
    public function view() {
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Manage content blocks, search by slug, and review publishing details.';
        }

        $perPageParam = (int)($this->runData['request']->get['per_page'] ?? 0);
        if ($this->isAllowedPerPage($perPageParam)) {
            $this->saveProfilePerPage($perPageParam);
        }
        $perPage = $this->isAllowedPerPage($perPageParam) ? $perPageParam : $this->getProfilePerPage(25);

        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        $this->runData['route']['h1'] = 'Content Blocks';
        $this->runData['route']['meta_title'] = 'Content Blocks';
        $this->runData['route']['backlink'] = $radAdminUrl . '/home/view';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Content Blocks' => '',
        ];

        $filters = [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
            'status' => trim((string)($this->runData['request']->get['status'] ?? '')),
            'type' => trim((string)($this->runData['request']->get['type'] ?? '')),
        ];

        $contentRows = $this->runData['db']->select('s_content', [], true);
        $stats = [
            'total' => count($contentRows),
            'active' => 0,
            'archived' => 0,
            'static' => 0,
            'journal' => 0,
            'common' => 0,
        ];

        $userIds = [];
        $msIds = [];
        foreach ($contentRows as $row) {
            if (!empty($row['createdby'])) {
                $userIds[] = (int)$row['createdby'];
            }
            if (!empty($row['updatedby'])) {
                $userIds[] = (int)$row['updatedby'];
            }
            if (!empty($row['s_ms_id'])) {
                $msIds[] = (int)$row['s_ms_id'];
            }
        }
        $userMap = $this->fetchUserNames($userIds);
        $msMap = $this->fetchMicroserviceMap($msIds);

        $role = (new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []))->role();
        $contentRows = array_values(array_filter($contentRows, function ($row) use ($role) {
            $msId = (int)($row['s_ms_id'] ?? 0);
            if ($msId === 0) {
                return true;
            }
            if ($role === 'system_admin') {
                return true;
            }
            return !\RadAdmin\VisibilityHelper::isRestrictedMs($msId, $this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        }));
        $stats['total'] = count($contentRows);

        foreach ($contentRows as &$row) {
            $row['created_name'] = $userMap[(int)($row['createdby'] ?? 0)] ?? 'System';
            $row['updated_name'] = $userMap[(int)($row['updatedby'] ?? 0)] ?? 'System';
            $row['ms_meta'] = $msMap[(int)($row['s_ms_id'] ?? 0)] ?? ['name' => '—', 'uid' => ''];
            $row['livestatus_slug'] = $row['livestatus'] === '1' ? 'active' : 'archived';
            $row['type_slug'] = strtolower($row['s_type'] ?? 'c');
            switch ($row['type_slug']) {
                case 'i':
                    $row['type_label'] = 'Static';
                    $stats['static']++;
                    break;
                case 'j':
                    $row['type_label'] = 'Journal';
                    $stats['journal']++;
                    break;
                default:
                    $row['type_label'] = 'Common';
                    $stats['common']++;
                    break;
            }
            if ($row['livestatus'] === '1') {
                $stats['active']++;
            } else {
                $stats['archived']++;
            }
            $row['formatted_updated'] = $row['updatestamp'] ? (new DateTime($row['updatestamp']))->format('d M Y, H:i') : '—';
            $row['search_blob'] = strtolower(trim(
                ($row['s_title'] ?? '') . ' ' .
                ($row['s_slug'] ?? '') . ' ' .
                ($row['s_content'] ?? '') . ' ' .
                $row['type_label'] . ' ' .
                ($row['ms_meta']['name'] ?? '')
            ));
        }
        unset($row);

        // Apply filters
        $contentRows = array_values(array_filter($contentRows, function ($row) use ($filters) {
            if ($filters['status'] !== '' && $row['livestatus_slug'] !== $filters['status']) {
                return false;
            }
            if ($filters['type'] !== '' && strtolower($row['s_type'] ?? '') !== strtolower($filters['type'])) {
                return false;
            }
            if ($filters['q'] !== '' && strpos($row['search_blob'], strtolower($filters['q'])) === false) {
                return false;
            }
            return true;
        }));

        $this->runData['data']['content'] = $contentRows;
        $this->runData['data']['content_stats'] = $stats;
        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['per_page_pref'] = $perPage;
        return $this->runData;
    }

    private function getProfilePerPage(int $fallback): int {
        $definition = $this->loadEntityDefinition();
        $prefs = $definition['profile_prefs'] ?? [];
        $perPage = (int)($prefs['per_page'] ?? 0);
        return $this->isAllowedPerPage($perPage) ? $perPage : $fallback;
    }

    private function saveProfilePerPage(int $perPage): void {
        if (!$this->isAllowedPerPage($perPage)) {
            return;
        }
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return;
        }
        $definition = $this->loadEntityDefinition();
        $prefs = $definition['profile_prefs'] ?? [];
        if (!is_array($prefs)) {
            $prefs = [];
        }
        $prefs['per_page'] = $perPage;
        $definition['profile_prefs'] = $prefs;
        $this->db->update('s_entity', [
            's_definition' => json_encode($definition, JSON_UNESCAPED_SLASHES),
        ], ['id' => $entityId]);
    }

    private function loadEntityDefinition(): array {
        $entityId = (int)($this->runData['entity']['id'] ?? 0);
        if ($entityId <= 0) {
            return [];
        }
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return [];
        }
        $raw = $rows[0]['s_definition'] ?? '';
        if (empty($raw)) {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function isAllowedPerPage(int $perPage): bool {
        return in_array($perPage, [10, 25, 50, 100, 200], true);
    }

    /**
     * add - Add a new content
     */
    public function add() {
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Add a new Content Block here.';
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        $this->runData['route']['h1'] = 'Add Content Block';
        $this->runData['route']['meta_title'] = 'Add Content Block';
        $this->runData['route']['backlink'] = $radAdminUrl . '/content/view';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Content Blocks' => $radAdminUrl . '/content/view',
            'Add' => '',
        ];
        $this->runData['data']['content_ms'] = $this->filterRestrictedMs($this->runData['db']->select('s_ms', ['livestatus'=>'1','s_type'=>'STA'], true));
        // If post parameters are set by form submission, then add the content
        if (isset($this->runData['request']->post['s_title'])) {
            // print '<pre>';print_r($this->runData['request']->post);print '</pre>';die('here');
            // Validate required fields
            $requiredFields = ['s_title', 's_content', 's_meta_title', 's_slug', 's_ms_id', 's_type'];
            foreach ($requiredFields as $field) {
                if (empty($this->runData['request']->post[$field])) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Please fill in all required fields.';
                    return $this->runData;
                }
            }

            // Validate s_group_id
            $contentMS = $this->filterRestrictedMs($this->runData['db']->select('s_ms', ['livestatus'=>'1','s_type'=>'STA'], true));
            if (empty($contentMS)) {
                $this->runData['route']['alert'] = 'danger';
        $this->runData['route']['alert_message'] = 'Please create a Content Microservicelet before adding content.';
                return $this->runData;
            }
            // print '<pre>';print_r($contentGroup);print '</pre>';die('here');
            $msIds = array_column($contentMS, 'id');
            // print '<pre>';print_r($groupIds);print '</pre>';die('here');
            if (!in_array($this->runData['request']->post['s_ms_id'], $msIds)) {
                $this->runData['route']['alert'] = 'danger';
        $this->runData['route']['alert_message'] = 'Invalid Content Microservicelet selected.';
                return $this->runData;
            }
            // print '<pre>';print_r($this->runData['request']->post['s_group_id']);print '</pre>';die('here');
            // slug should be unique
            $slugExists = $this->runData['db']->select('s_content', ['s_slug' => $this->runData['request']->post['s_slug']], true);
            if (count($slugExists) > 0) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Slug already exists. Please choose a different slug.';
                return $this->runData;
            }
            if ($this->runData['request']->post['s_definition'] == '') {
                $json_str = '{}';
            }
            else {
                // Validate the JSON
                $json_str = html_entity_decode($this->runData['request']->post['s_definition']);
                $json_str = stripslashes($json_str);
                $json = json_decode($json_str);
                // print '<pre>';print_r($this->runData['request']->post['s_service_definition']);print '</pre>';die('here');
                if (json_last_error() != JSON_ERROR_NONE) {
                    // Add alert and alert_message to runData - information to be displayed to the user
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Invalid JSON for Content Meta Definition.';
                }
            }
            // Add content to s_content table if there is no error from validation
            if ( $this->runData['route']['alert'] != 'danger') {
                // Add content to s_content table
                // If meta_title or meta_description string length is more that 250, then truncate it
                $metaTitle = (strlen($this->runData['request']->post['s_meta_title']) > 250) ? substr($this->runData['request']->post['s_meta_title'], 0, 250) : $this->runData['request']->post['s_meta_title'];
                $metaDescription = (strlen($this->runData['request']->post['s_meta_description']) > 250) ? substr($this->runData['request']->post['s_meta_description'], 0, 250) : $this->runData['request']->post['s_meta_description'];
                $newContentId = $this->runData['db']->insert('s_content', [
                    's_ms_id' => $this->runData['request']->post['s_ms_id'],
                    's_title' => $this->runData['request']->post['s_title'],
                    's_content' => $this->runData['request']->post['s_content'],
                    's_definition' => $json_str,
                    's_meta_title' => $metaTitle,
                    's_meta_description' => $metaDescription,
                    's_slug' => $this->runData['request']->post['s_slug'],
                    's_type' => $this->runData['request']->post['s_type'],
                ]);
            }
            // print '<pre>';print_r($newContentId);print '</pre>';die('After Insert');
            // Redirect to content view page
            $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/content/view';
            header("Location: {$redirectUrl}");
            exit;
        }
        // print '<pre>';print_r($this->runData['data']['content']);print '</pre>';die('here');
        $this->runData['route']['h1'] = 'Add Content Block';
        $this->runData['route']['meta_title'] = 'Add Content Block';
        // Select config parameters from s_config table
        // $this->runData['data']['configParams'] = $this->runData['db']->select('s_config', [], true);
        return $this->runData;
    }

    /**
     * edit - Edit a content
     */
    public function edit() {
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Edit Content Block here.';
        }
        $radAdminUrl = $this->runData['route']['rad_admin_url'];
        $this->runData['route']['h1'] = 'Edit Content Block';
        $this->runData['route']['meta_title'] = 'Edit Content Block';
        $this->runData['route']['backlink'] = $radAdminUrl . '/content/view';
        $this->runData['route']['breadcrumb'] = [
            'Home' => $radAdminUrl . '/home/view',
            'Content Blocks' => $radAdminUrl . '/content/view',
            'Edit' => '',
        ];
        $branch = $this->branchService->resolveEditorBranch();
        $this->runData['data']['branch'] = $branch;
        $this->runData['data']['branch_can_manage'] = $this->branchService->canUseBeta();
        $this->runData['data']['branch_can_merge'] = $this->branchService->canMerge();
        $this->runData['data']['content_ms'] = $this->filterRestrictedMs($this->runData['db']->select('s_ms', ['livestatus'=>'1','s_type'=>'STA'], true));
        // If post parameters are set by form submission, then update the content
        if (isset($this->runData['request']->post['s_title'])) {
            // print '<pre>';print_r($this->runData['request']->post);print '</pre>';die('here');
            // Validate required fields
            $requiredFields = ['s_title', 's_content', 's_meta_title', 's_slug', 's_ms_id', 's_type'];
            foreach ($requiredFields as $field) {
                if (empty($this->runData['request']->post[$field])) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Please fill in all required fields.';
                    return $this->runData;
                }
            }

            // Validate s_ms_id
            $contentMS = $this->filterRestrictedMs($this->runData['db']->select('s_ms', ['livestatus'=>'1','s_type'=>'STA'], true));
            if (empty($contentMS)) {
                $this->runData['route']['alert'] = 'danger';
        $this->runData['route']['alert_message'] = 'Please create a Content Microservicelet before adding content.';
                return $this->runData;
            }
            
            // print '<pre>';print_r($contentGroup);print '</pre>';die('here');
            $msIds = array_column($contentMS, 'id');
            if (!in_array($this->runData['request']->post['s_ms_id'], $msIds)) {
                $this->runData['route']['alert'] = 'danger';
        $this->runData['route']['alert_message'] = 'Invalid Content Microservicelet selected.';
                return $this->runData;
            }
            // slug should be unique - except for the current content
            $slugExists = $this->runData['db']->select('s_content', ['s_slug' => $this->runData['request']->post['s_slug']], true);
            if (count($slugExists) > 0) {
                if ($slugExists[0]['id'] != $this->runData['request']->post['id']) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Slug already exists. Please choose a different slug.';
                    return $this->runData;
                }
            }
            if ($this->runData['request']->post['s_definition'] == '') {
                $json_str = '{}';
            }
            else {
                // Validate the JSON
                $json_str = html_entity_decode($this->runData['request']->post['s_definition']);
                $json_str = stripslashes($json_str);
                $json = json_decode($json_str);
                // print '<pre>';print_r($this->runData['request']->post['s_service_definition']);print '</pre>';die('here');
                if (json_last_error() != JSON_ERROR_NONE) {
                    // Add alert and alert_message to runData - information to be displayed to the user
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'Invalid JSON for Content Meta Definition.';
                }
            }
            // Update content to s_content table if there is no error from validation
            if ( $this->runData['route']['alert'] != 'danger') {
                $metaTitle = (strlen($this->runData['request']->post['s_meta_title']) > 250) ? substr($this->runData['request']->post['s_meta_title'], 0, 250) : $this->runData['request']->post['s_meta_title'];
                $metaDescription = (strlen($this->runData['request']->post['s_meta_description']) > 250) ? substr($this->runData['request']->post['s_meta_description'], 0, 250) : $this->runData['request']->post['s_meta_description'];
                $contentId = (int)$this->runData['request']->post['id'];
                if ($branch === 'beta') {
                    if (!$this->branchService->hasContentBeta($contentId)) {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Create a beta branch before saving beta content.';
                        return $this->runData;
                    }
                    $contentRows = $this->runData['db']->select('s_content', ['id' => $contentId], true);
                    if (count($contentRows) !== 1) {
                        $this->runData['route']['alert'] = 'danger';
                        $this->runData['route']['alert_message'] = 'Content not found.';
                        return $this->runData;
                    }
                    $info = $this->decodeAdditionalInfo($contentRows[0]['s_additional_info'] ?? '');
                    $info['branch_beta'] = [
                        's_ms_id' => $this->runData['request']->post['s_ms_id'],
                        's_title' => $this->runData['request']->post['s_title'],
                        's_content' => $this->runData['request']->post['s_content'],
                        's_definition' => $json_str,
                        's_meta_title' => $metaTitle,
                        's_meta_description' => $metaDescription,
                        's_slug' => $this->runData['request']->post['s_slug'],
                        's_type' => $this->runData['request']->post['s_type'],
                    ];
                    $this->runData['db']->update('s_content', [
                        's_additional_info' => json_encode($info, JSON_UNESCAPED_SLASHES),
                    ], ['id' => $contentId]);
                    $this->runData['route']['alert'] = 'success';
                    $this->runData['route']['alert_message'] = 'Beta content saved.';
                    $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                    $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/content/edit/'.$this->runData['route']['pathparts'][3].'?branch=beta';
                    header("Location: {$redirectUrl}");
                    exit;
                }
                // Update live content
                $this->runData['db']->update('s_content', [
                    's_ms_id' => $this->runData['request']->post['s_ms_id'],
                    's_title' => $this->runData['request']->post['s_title'],
                    's_content' => $this->runData['request']->post['s_content'],
                    's_definition' => $json_str,
                    's_meta_title' => $metaTitle,
                    's_meta_description' => $metaDescription,
                    's_slug' => $this->runData['request']->post['s_slug'],
                    's_type' => $this->runData['request']->post['s_type']
                ], ['id' => $contentId]);
            }
            // print '<pre>';print_r($this->runData['route']);print '</pre>';die('After update');
            // Set alert
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'Content updated successfully.';
            // save alert into cookie using our function
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to content view page
            $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/content/view';
            header("Location: {$redirectUrl}");
            exit;
        }
        else {
            //If pathparts index 3 exists, get the content uid from it
            // print '<pre>';print_r($this->runData['route']['pathparts']);print '</pre>';die('here');
            // print '<pre>';print_r($this->runData['route']['pathparts'][3]);print '</pre>';die('pathparts 3');
            if (isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] != '')) {
                $contentUid = $this->runData['route']['pathparts'][3];
                // print '<pre>';print_r($contentUid);print '</pre>';die('pathparts 3 - reached');
                // Get the content from s_content table
                $this->runData['data']['content'] = $this->runData['db']->select('s_content', ['uid' => $contentUid], true);
                // print '<pre>';print_r($this->runData['data']['content']);print '</pre>';die('here');
                // If content is not found, then redirect to content view page
                if (empty($this->runData['data']['content'])) {
                    $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/content/view';
                    header("Location: {$redirectUrl}");
                    exit;
                }
                $contentId = (int)($this->runData['data']['content'][0]['id'] ?? 0);
                $this->runData['data']['branch_status'] = $contentId ? $this->branchService->getContentBranchStatus($contentId) : [];
                $this->runData['data']['branch_has_beta'] = $contentId ? $this->branchService->hasContentBeta($contentId) : false;
                try {
                    $this->runData['data']['branch_history'] = $contentId ? $this->runData['db']->query(
                        "SELECT * FROM s_branch
                         WHERE s_object_type = 'content' AND s_object_id = :cid
                         ORDER BY id DESC
                         LIMIT 10",
                        [':cid' => $contentId]
                    ) : [];
                } catch (\Throwable $e) {
                    $this->runData['data']['branch_history'] = [];
                }
                if ($branch === 'beta' && $this->runData['data']['branch_has_beta']) {
                    $beta = $this->extractBetaContent($this->runData['data']['content'][0]);
                    if (!empty($beta)) {
                        $this->runData['data']['content'][0] = $this->applyBetaContent($this->runData['data']['content'][0], $beta);
                    }
                } elseif ($branch === 'beta') {
                    $this->runData['data']['branch_missing'] = true;
                }
                $msId = (int)($this->runData['data']['content'][0]['s_ms_id'] ?? 0);
                if ($msId > 0 && $this->isRestrictedMs($msId)) {
                    $this->runData['route']['alert'] = 'danger';
                    $this->runData['route']['alert_message'] = 'You do not have access to this Content Microservicelet.';
                    $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                    $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/content/view';
                    header("Location: {$redirectUrl}");
                    exit;
                }
                // print '<pre>';print_r($this->runData['data']['content']);print '</pre>';die('here');
                $this->runData['route']['h1'] = 'Edit Content Block';
                $this->runData['route']['meta_title'] = 'Edit Content Block';
                $this->runData['data']['content_ms'] = $this->filterRestrictedMs($this->runData['db']->select('s_ms', ['livestatus'=>'1','s_type'=>'STA'], true));
                // Select config parameters from s_config table
                // $this->runData['data']['configParams'] = $this->runData['db']->select('s_config', [], true);
                return $this->runData;
            }
            else {
                // Add alert message
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Invalid content access.';
                // save alert into cookie using our function
                $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
                //If pathparts index 4 does not exist, then redirect to content view page
                $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/content/view';
                header("Location: {$redirectUrl}");
                exit;
            }
        }
    }

    public function branchcreate() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('controller_edit') || !$this->branchService->canUseBeta()) {
            throw new \Exception('Access denied.', 403);
        }
        $contentUid = $this->runData['route']['pathparts'][3] ?? '';
        if ($contentUid === '') {
            throw new \Exception('Content identifier missing.', 404);
        }
        $rows = $this->runData['db']->select('s_content', ['uid' => $contentUid], true);
        if (count($rows) !== 1) {
            throw new \Exception('Content not found.', 404);
        }
        $contentId = (int)$rows[0]['id'];
        $result = $this->branchService->createContentBeta($contentId);
        $this->runData['request']->setAlert($result['message'], $result['status'] ? 'success' : 'danger');
        $redirect = $this->runData['route']['rad_admin_url'] . '/content/edit/' . $contentUid . '?branch=beta';
        header("Location: {$redirect}");
        exit;
    }

    public function branchmerge() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('controller_edit') || !$this->branchService->canMerge()) {
            throw new \Exception('Access denied.', 403);
        }
        $contentUid = $this->runData['route']['pathparts'][3] ?? '';
        if ($contentUid === '') {
            throw new \Exception('Content identifier missing.', 404);
        }
        $rows = $this->runData['db']->select('s_content', ['uid' => $contentUid], true);
        if (count($rows) !== 1) {
            throw new \Exception('Content not found.', 404);
        }
        $contentId = (int)$rows[0]['id'];
        $result = $this->branchService->mergeContentBeta($contentId);
        $this->runData['request']->setAlert($result['message'], $result['status'] ? 'success' : 'danger');
        $redirect = $this->runData['route']['rad_admin_url'] . '/content/edit/' . $contentUid;
        header("Location: {$redirect}");
        exit;
    }

    public function branchdiscard() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('controller_edit') || !$this->branchService->canUseBeta()) {
            throw new \Exception('Access denied.', 403);
        }
        $contentUid = $this->runData['route']['pathparts'][3] ?? '';
        if ($contentUid === '') {
            throw new \Exception('Content identifier missing.', 404);
        }
        $rows = $this->runData['db']->select('s_content', ['uid' => $contentUid], true);
        if (count($rows) !== 1) {
            throw new \Exception('Content not found.', 404);
        }
        $contentId = (int)$rows[0]['id'];
        $result = $this->branchService->discardContentBeta($contentId);
        $this->runData['request']->setAlert($result['message'], $result['status'] ? 'success' : 'danger');
        $redirect = $this->runData['route']['rad_admin_url'] . '/content/edit/' . $contentUid;
        header("Location: {$redirect}");
        exit;
    }

    /**
     * archive - Archive a content
     */
    public function archive() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if ($priv->role() === 'developer' || !$priv->can('controller_edit')) {
            throw new \Exception('Access denied.', 403);
        }
        // check if content uid is set in pathparts index 3
        if (isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] != '')) {
            $contentUid = $this->runData['route']['pathparts'][3];
            // Get the content from s_content table
            $this->runData['data']['content'] = $this->runData['db']->select('s_content', ['uid' => $contentUid], true);
            // If content is not found, then redirect to content view page
            if (empty($this->runData['data']['content'])) {
                $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/content/view';
                header("Location: {$redirectUrl}");
                exit;
            }
            // print '<pre>';print_r($this->runData['data']['content']);print '</pre>';die('here');
            // Archive content in s_content table
            $this->runData['db']->update('s_content', ['livestatus' => '0'], ['uid' => $contentUid]);
            // Set alert
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'Content archived successfully.';
            // save alert into cookie using our function
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to content view page
            $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/content/view';
            header("Location: {$redirectUrl}");
            exit;
        }
        else {
            // Add alert message
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Invalid content access.';
            // save alert into cookie using our function
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            //If pathparts index 4 does not exist, then redirect to content view page
            $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/content/view';
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    /**
     * restore - Restore a content
     */
    public function restore() {
        // check if content uid is set in pathparts index 3
        if (isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] != '')) {
            $contentUid = $this->runData['route']['pathparts'][3];
            // Get the content from s_content table
            $this->runData['data']['content'] = $this->runData['db']->select('s_content', ['uid' => $contentUid], true);
            // If content is not found, then redirect to content view page
            if (empty($this->runData['data']['content'])) {
                $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/content/view';
                header("Location: {$redirectUrl}");
                exit;
            }
            // print '<pre>';print_r($this->runData['data']['content']);print '</pre>';die('here');
            // Restore content in s_content table
            $this->runData['db']->update('s_content', ['livestatus' => '1'], ['uid' => $contentUid]);
            // Set alert
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'Content restored successfully.';
            // save alert into cookie using our function
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // Redirect to content view page
            $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/content/view';
            header("Location: {$redirectUrl}");
            exit;
        }
        else {
            // Add alert message
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Invalid content access.';
            // save alert into cookie using our function
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            //If pathparts index 4 does not exist, then redirect to content view page
            $redirectUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/content/view';
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    private function fetchUserNames(array $ids): array {
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
        $rows = $this->runData['db']->query(
            'SELECT id, s_name FROM s_entity WHERE id IN (' . implode(',', $placeholders) . ')',
            $params
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row['s_name'] ?? 'Unknown';
        }
        return $map;
    }

    private function fetchMicroserviceMap(array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $placeholder = ':ms' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }
        $rows = $this->runData['db']->query(
            'SELECT id, s_name, uid FROM s_ms WHERE id IN (' . implode(',', $placeholders) . ')',
            $params
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = [
                'name' => $row['s_name'] ?? 'Unknown',
                'uid' => $row['uid'] ?? '',
            ];
        }
        return $map;
    }

    private function filterRestrictedMs(array $msList): array {
        $role = (new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []))->role();
        if ($role === 'system_admin') {
            return $msList;
        }
        return array_values(array_filter($msList, function ($ms) {
            $msId = (int)($ms['id'] ?? 0);
            if ($msId === 0) {
                return false;
            }
            return !\RadAdmin\VisibilityHelper::isRestrictedMs($msId, $this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        }));
    }

    private function isRestrictedMs(int $msId): bool {
        $role = (new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []))->role();
        if ($role === 'system_admin') {
            return false;
        }
        return \RadAdmin\VisibilityHelper::isRestrictedMs($msId, $this->runData['config'] ?? [], $this->runData['entity'] ?? []);
    }

    private function decodeAdditionalInfo($raw): array {
        if (!$raw) {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function extractBetaContent(array $contentRow): array {
        $info = $this->decodeAdditionalInfo($contentRow['s_additional_info'] ?? '');
        $beta = $info['branch_beta'] ?? [];
        return is_array($beta) ? $beta : [];
    }

    private function applyBetaContent(array $contentRow, array $beta): array {
        foreach ($beta as $key => $value) {
            if (array_key_exists($key, $contentRow)) {
                $contentRow[$key] = $value;
            }
        }
        return $contentRow;
    }
}

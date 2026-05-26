<?php
namespace Core\Sys;

class GenericController {
    private $db;
    private $view;
    private $session;
    private $errorHandler;
    private $permissionService;
    private $runData = [];
    private $routeIndex = [];
    private $cacheService;
    private $ipAccessService;

    public function __construct(array $runData, \Core\Sys\View $view, \Core\Sys\ErrorHandler $errorHandler) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->routeIndex = $runData['route']['pathparts'];
        $this->view = $view;
        $this->session = $runData['session'];
        $this->errorHandler = $errorHandler;
        $this->permissionService = $runData['permissionService'] ?? new PermissionService($this->db);
        $this->cacheService = new CacheService($runData['config']);
        $this->ipAccessService = new IpAccessService();
        // print '<pre>';print_r($this->runData);print '</pre>';die('Gen Controller Initiated.');
    }

    public function handle() {
        $this->checkIPRestriction();
        // get microservice details
        $pathparts = $this->routeIndex;
        $spaceSlug = null;
        $workspacePrefix = null;
        $msIndex = 0;
        $msName = $pathparts[0] ?? '';
        $msDetails = [];
        $directMsDetails = [];
        if (!empty($this->runData['config']['sys']['workspace_slug_prefix'])) {
            $workspacePrefix = trim((string)$this->runData['config']['sys']['workspace_slug_prefix'], "/ \t\n\r\0\x0B");
        }
        if ($workspacePrefix === '') {
            $workspacePrefix = null;
        }
        if ($workspacePrefix && ($pathparts[0] ?? '') === $workspacePrefix && isset($pathparts[2])) {
            $workspaceDyn = $this->db->select('s_ms', [
                'livestatus' => '1',
                's_name' => $pathparts[2],
                's_scope' => 'workspace',
                's_type' => 'DYN',
            ], true);
            if (count($workspaceDyn) === 1) {
                $spaceSlug = $pathparts[1] ?? null;
                $msName = $pathparts[2];
                $msIndex = 2;
                $msDetails = $workspaceDyn;
            }
        }
        if (empty($msDetails) && $msName !== '') {
            $directMsDetails = $this->db->select('s_ms', ['livestatus' => '1', 's_name' => $msName], true);
            if (count($directMsDetails) === 1) {
                $msDetails = $directMsDetails;
            }
        }
        if (empty($msDetails) && !$workspacePrefix && isset($pathparts[1])) {
            $workspaceDyn = $this->db->select('s_ms', [
                'livestatus' => '1',
                's_name' => $pathparts[1],
                's_scope' => 'workspace',
                's_type' => 'DYN',
            ], true);
            if (count($workspaceDyn) === 1) {
                $spaceSlug = $pathparts[0] ?? null;
                $msName = $pathparts[1];
                $msIndex = 1;
                $msDetails = $workspaceDyn;
            }
        }
        if (empty($msDetails)) {
            if ($workspacePrefix && ($pathparts[0] ?? '') !== $workspacePrefix && isset($pathparts[1])) {
                $legacyWorkspaceDyn = $this->db->select('s_ms', [
                    'livestatus' => '1',
                    's_name' => $pathparts[1],
                    's_scope' => 'workspace',
                    's_type' => 'DYN',
                ], true);
                if (count($legacyWorkspaceDyn) === 1) {
                    $this->errorHandler->handleException('Workspace routes must include the prefix: /' . $workspacePrefix . '/{space_name}/{ms_name}/...');
                }
            }
            $msDetails = $directMsDetails;
        }
        // print '<pre>';print_r($msDetails);print count($msDetails);print '<br/>';die('here');
        if(count($msDetails) == 1) {
            $this->runData['ms']['id'] = $msDetails[0]['id'];
            $this->runData['ms']['uid'] = $msDetails[0]['uid'];
            $this->runData['ms']['name'] = $msDetails[0]['s_name'];
            $this->runData['ms']['type'] = $msDetails[0]['s_type'];
            $this->runData['ms']['definition'] = ($msDetails[0]['s_definition'] == '') ? [] : json_decode($msDetails[0]['s_definition'], true);
            if (isset($this->runData['ms']['definition']['route_path']) && $this->runData['ms']['definition']['route_path'] == 'auto') {
                $this->runData['ms']['route_path'] = 'auto';
                // Get the default routename from the s_msroute table
                $defaultRouteDetails = $this->db->select('s_msroute', ['livestatus'=>'1','id' => $msDetails[0]['s_default_route_id'],'s_ms_id'=> $msDetails[0]['id'] ], true);
                if(count($defaultRouteDetails) != 1) {
                    $this->errorHandler->handleException('No default route found.');
                }
                $this->runData['ms']['default_route_name'] = $defaultRouteDetails[0]['s_name'];
            }
            else {
                $this->runData['ms']['route_path'] = 'manual';
            }
            $this->runData['ms']['scope'] = $msDetails[0]['s_scope'];
            $scope = strtolower($msDetails[0]['s_scope'] ?? '');
            $this->runData['ms']['access_scope'] = $scope === 'global' ? 'public' : 'private';
            $this->runData['ms']['access_role_ids'] = [];
            $this->runData['ms']['default_route_id'] = $msDetails[0]['s_default_route_id'];
            $this->runData['ms']['tpl_name'] = $msDetails[0]['s_tpl_name'];
        }
        else {
            print 'No or Multiple Microservicelets found';
            exit;
        }
        // print '<pre>';print_r($this->runData['ms']);print '</pre>';die('Before routetype');
        
        // get route details
        $routePartsAfterMs = array_slice($this->routeIndex, $msIndex + 1); // original segments after ms name
        $this->routeIndex = $routePartsAfterMs;
        $routeSegmentsUsed = 0;
        $serviceArgs = [];
        if (count($this->routeIndex) == 0) {
            // Get route details from default route id
            $routeDetails = $this->db->select('s_msroute', ['livestatus'=>'1','id' => $this->runData['ms']['default_route_id'],'s_ms_id'=> $this->runData['ms']['id'] ], true);
            if(count($routeDetails) != 1) {
                $this->errorHandler->handleException('No default route found.');
            }
            $routeSegmentsUsed = 0;
        }
        else {
            if ($this->runData['ms']['type'] == 'STA') {
                $routeSegmentsUsed = 0;
                if (isset($this->runData['ms']['definition']['degree']) && $this->runData['ms']['definition']['degree'] > 0) {
                    $degree = $this->runData['ms']['definition']['degree'];
                }
                else {
                    $degree = 1;
                }
                // form the $routeName from $this->routeIndex upto $degree parts
                $this->routeIndex = array_slice($this->routeIndex, 0, $degree);
                $routeSegmentsUsed = min(count($routePartsAfterMs), $degree);
                $routeName = implode('/', $this->routeIndex);
                // print '<pre>';print_r($routeName);print '</pre>';die('here');
                // If $routeName == $this->runData['ms']['default_route_name'], then redirect to base_url/[ms_name]
                if ( ( isset($this->runData['ms']['default_route_name']) ) && ($routeName == $this->runData['ms']['default_route_name']) ) {
                    $redirectUrl = $this->runData['config']['sys']['base_url'].'/'.$this->runData['ms']['name'];
                    header("Location: {$redirectUrl}");exit;
                }
                
                if ($this->runData['ms']['route_path'] == 'auto') {
                    $routeDetails[0]['id'] = 0;
                    $routeDetails[0]['uid'] = '0';
                    $routeDetails[0]['s_service_definition'] = '{}';
                }
                else {
                    $routeDetails = $this->db->select('s_msroute', ['livestatus'=>'1','s_name' => $routeName,'s_ms_id'=> $this->runData['ms']['id'] ], true);
                    if(count($routeDetails) != 1) {
                        $this->errorHandler->handleException('No static route found.');
                    }
                }
            }
            else if ($this->runData['ms']['type'] == 'DYN') {
                $routeName = $this->routeIndex[0] ?? null;
                if (!$routeName) {
                    $routeDetails = $this->db->select('s_msroute', [
                        'livestatus' => '1',
                        'id' => $this->runData['ms']['default_route_id'],
                        's_ms_id' => $this->runData['ms']['id']
                    ], true);
                    if (count($routeDetails) != 1) {
                        $this->errorHandler->handleException('No default route found.');
                    }
                    $routeName = $routeDetails[0]['s_name'] ?? '';
                    $routeSegmentsUsed = 0;
                    $serviceArgs = [];
                } else {
                    $routeSegmentsUsed = 1;
                    $serviceArgs = array_slice($routePartsAfterMs, 1);
                    $routeDetails = $this->db->select('s_msroute', ['livestatus'=>'1','s_name' => $routeName,'s_ms_id'=> $this->runData['ms']['id'] ], true);
                    if(count($routeDetails) != 1) {
                        $this->errorHandler->handleException('No Dynamic route found.');
                    }
                }
            }
            else if ($this->runData['ms']['type'] == 'UID') {
                $routeSegmentsUsed = 1;
                $routeUID = $this->routeIndex[0];
                $routeDetails = $this->db->select('s_msroute', ['livestatus'=>'1','uid' => $routeUID,'s_ms_id'=> $this->runData['ms']['id'] ], true);
                if(count($routeDetails) != 1) {
                    $this->errorHandler->handleException('No UID route found.');
                }
            }
            else if ($this->runData['ms']['type'] == 'ID') {
                $routeSegmentsUsed = 1;
                $routeID = $this->routeIndex[0];
                $routeDetails = $this->db->select('s_msroute', ['livestatus'=>'1','id' => $routeID,'s_ms_id'=> $this->runData['ms']['id'] ], true);
                if(count($routeDetails) != 1) {
                    $this->errorHandler->handleException('No ID route found.');
                }
        }
        else {
            $this->errorHandler->handleException('Invalid Microservicelet type.');
        }
    }
        $this->runData['route']['id'] = $routeDetails[0]['id'];
        // print '<pre>';print_r($this->runData['route']);print_r($this->runData['ms']);print '</pre>';die('here');
        $this->runData['route']['uid'] = $routeDetails[0]['uid'];
        $this->runData['route']['name'] = $routeDetails[0]['s_name'] ?? '';
        // print '<pre>';print_r($this->runData['route']);print '</pre>';die('here');
        if ($this->runData['route']['path'] != '') {
            $this->runData['route']['path_full'] = $this->runData['ms']['name'].'/'.$this->runData['route']['path'];
        }
        else {
            $this->runData['route']['path_full'] = $this->runData['ms']['name'];
        }
        $this->runData['route']['file_key'] = ($this->runData['ms']['type'] === 'DYN')
            ? ($routeDetails[0]['s_name'] ?? $this->runData['route']['id'])
            : (string)$this->runData['route']['id'];
        // Check if private and does not have session
        $scope = $this->runData['ms']['scope'] ?? 'platform';
        $this->runData['ms']['access_scope'] = $scope === 'global' ? 'public' : 'private';
        if ($this->runData['ms']['access_scope'] === 'private' && !$this->session->get('entity_id')) {
            $this->redirectToLogin();
        }
        $this->runData['route']['access_scope'] = $this->runData['ms']['access_scope'] === 'private' ? 'private' : 'public';
        // SaaS is now workspace-scoped only
        $isSaas = ($scope === 'workspace');
        $this->runData['route']['is_saas'] = $isSaas ? 'Y' : 'N';
        // Redirect default route paths to base only for non-SaaS microservicelets
        if (!$isSaas) {
            if (($this->runData['route']['path'] != '') && ($this->runData['route']['id'] == $this->runData['ms']['default_route_id'])) {
                $redirectUrl = $this->runData['config']['sys']['base_url'].'/'.$this->runData['ms']['name'];
                header("Location: {$redirectUrl}");exit;
            }
        }
        $this->runData['route']['access_role_ids'] = [];
        $route_definition = []; // Default to an empty array

        // Determine space slug position for SaaS microservicelets
        $spaceId = null;
        if ($isSaas) {
            if ($this->runData['ms']['type'] == 'DYN') {
                // Space slug already captured as 3rd segment.
            } elseif ($this->runData['ms']['type'] == 'UID' || $this->runData['ms']['type'] == 'ID') {
                $spaceSlug = $routePartsAfterMs[1] ?? null;
            } else { // STA and others
                if ($routeSegmentsUsed !== 1) {
                    $this->renderSpaceError('Workspace static routes must use single-segment paths to reserve the third segment for space slug.');
                }
                $spaceSlug = $routePartsAfterMs[$routeSegmentsUsed] ?? null;
            }

            if (empty($spaceSlug)) {
                $this->renderSpaceError('Workspace identifier (space slug/uid) is required for SaaS routes.');
            }

            // Resolve workspace by slug/uid (STA/DYN use slug; ID/UID use uid)
            $spaceLookup = $this->db->select(
                's_space',
                ($this->runData['ms']['type'] === 'UID' || $this->runData['ms']['type'] === 'ID')
                    ? ['uid' => $spaceSlug]
                    : ['s_slug' => $spaceSlug],
                true
            );
            if (empty($spaceLookup)) {
                $this->renderSpaceError('Workspace not found for the provided identifier.');
            }
            $spaceId = (int)$spaceLookup[0]['id'];
            $this->runData['route']['space_id'] = $spaceId;
            $this->runData['route']['space_uid'] = $spaceLookup[0]['uid'] ?? null;
            $this->runData['route']['space_slug'] = $spaceLookup[0]['s_slug'] ?? null;
        }

        // Permission enforcement for non-global microservicelets.
        if ($scope !== 'global') {
            $entityId = $this->session->get('entity_id');
            $routeId = (int)$this->runData['route']['id'];
            $msId = (int)$this->runData['ms']['id'];
            $routeHasBindings = $this->permissionService->hasBindings('route', $routeId);
            $msHasBindings = $this->permissionService->hasBindings('ms', $msId);
            // Non-global: bindings required. Routes inherit MS if they have none.
            if ($routeHasBindings) {
                if (!$this->permissionService->canAccess($entityId, 'route', $routeId, 'use', $spaceId, $msId)) {
                    $this->denyAccess();
                }
            } elseif ($msHasBindings) {
                if (!$this->permissionService->canAccess($entityId, 'ms', $msId, 'use', $spaceId, $msId)) {
                    $this->denyAccess();
                }
            } else {
                $this->denyAccess();
            }
        }

        if (!empty($routeDetails[0]['s_service_definition']) && is_string($routeDetails[0]['s_service_definition'])) {
            $decoded_json = json_decode($routeDetails[0]['s_service_definition'], true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                $route_definition = $decoded_json;
            } else {
                $route_definition = [];
            }
        }
        
        if (isset($route_definition['content_tpl'])) {
            $this->runData['route']['content_tpl'] = ($route_definition['content_tpl'] == 'Y') ? 'Y' : 'N';
        }
        else {
            $this->runData['route']['content_tpl'] = 'N';
        }
        // print '<pre>';print_r($route_definition);print '</pre>';die('here');
        /* Complete MS stype specific definition processing */
        if ($this->runData['ms']['type'] == 'STA') {
            // print '<pre>';print_r($this->runData['route']);print '</pre>';die('here');
            if ($this->runData['ms']['route_path'] == 'auto') {
                if ($this->runData['route']['path'] == '') {
                    // check if the ms definition has array index default_action
                    if (isset($this->runData['ms']['definition']['default_action'])) {
                        if ($this->runData['ms']['definition']['default_action'] == 'redirect') {
                            // redirect to base_url/definition['default_action_redirect_path'] if it exists, else redirect to base_url
                            if (isset($this->runData['ms']['definition']['default_action_redirect_path'])) {
                                $redirectUrl = $this->runData['config']['sys']['base_url'].'/'.$this->runData['ms']['definition']['default_action_redirect_path'];
                            }
                            else {
                                $redirectUrl = $this->runData['config']['sys']['base_url'];
                            }
                        }
                        else if ($this->runData['ms']['definition']['default_action'] == 'content') {
                            // get content details from s_content table
                            $this->getContent($this->runData['ms']['definition']['default_action_content_id'],'id');
                        }
                        else {
                            //
                        }
                    }
                    //
                }
                else {
                    $this->getContent($this->runData['route']['path'],'slug');
                }
            }
            else {
                if (isset($route_definition['content_id'])) {
                    $this->getContent($route_definition['content_id'],'id');
                }
            }
        }
        if ($this->runData['ms']['type'] == 'DYN') {
            // DYN routes are rendered from route.{name}.php only (no controller lookup).
        }
        $this->enforceIpAccessRules($spaceLookup[0] ?? null);
        // print('<pre>');print_r($this->runData['route']);print('</pre>');die('here');
        if ($this->runData['route']['access_scope'] == 'private') {
            $routeBindings = $this->permissionService->hasBindings('route', (int)$this->runData['route']['id']);
            $msBindings = $this->permissionService->hasBindings('ms', (int)$this->runData['ms']['id']);
            if ($routeBindings || $msBindings) {
                $this->enforcePermissionBindings($routeBindings, $msBindings);
            } else {
                // Private route without explicit bindings: require login but allow through.
                if (!$this->runData['entity']['is_logged_in']) {
                    $this->redirectToLogin();
                }
                $this->runData['entity']['route_access'] = 'Y';
                $this->runData['route']['access'] = 'Y';
            }

            if ($this->runData['route']['is_saas'] == 'Y') {
                $this->handleSpaceBinding($spaceSlug);
            }
        }
        // print '<pre>';print_r($this->runData);print '</pre>';die('here');
        $routeDir = $this->runData['config']['dir']['ms'].'/'.$this->runData['ms']['name'];
        $branchService = new \Core\Sys\BranchService(
            $this->db,
            $this->runData['config'] ?? [],
            $this->runData['entity'] ?? [],
            $this->runData['request'] ?? null
        );
        $branch = $branchService->resolveRuntimeBranch([
            'ms_name' => (string)($this->runData['ms']['name'] ?? ''),
            'route_key' => (string)($this->runData['route']['file_key'] ?? ''),
        ]);
        $this->runData['route']['branch'] = $branch;
        $routeLoadFile = $branchService->getRouteFilePath(
            $this->runData['ms']['name'],
            (string)$this->runData['route']['file_key'],
            'load',
            $branch,
            true
        );
        // print '<pre>';print_r($routeLoadFile);exit;
        $cacheVariant = '';
        $cacheTtl = 0;
        $useRouteCache = $this->shouldUseRouteCache($route_definition);
        if ($useRouteCache) {
            $cacheVariant = $this->buildRouteCacheVariant($spaceId);
            $cacheTtl = $this->resolveRouteCacheTtl($route_definition);
            $cacheHit = $this->cacheService->get($this->runData['ms']['name'], 'route', (string)$this->runData['route']['id'], $cacheVariant);
            if (!empty($cacheHit['hit'])) {
                $this->registerCacheActivity('hit', 'route');
                $this->registerCacheDebug('hit', $cacheVariant, $cacheHit, 'route', (string)$this->runData['route']['id']);
                echo $cacheHit['payload'] ?? '';
                return;
            }
            $this->registerCacheActivity('miss', 'route');
            $this->registerCacheDebug('miss', $cacheVariant, $cacheHit, 'route', (string)$this->runData['route']['id']);
        }
        if(file_exists($routeLoadFile)) {
            include_once($routeLoadFile);
        }

        // Call View
        // Ensure that view_status is set to 'render' if it does not exist
        if (!isset($this->runData['entity']['view_status'])) {
            $this->runData['entity']['view_status'] = 'render';
        }

        // Call View based on the view_status
        if ($this->runData['entity']['view_status'] == 'render') {
            if ($useRouteCache) {
                ob_start();
            }
            $this->attachDebugBlock();
            $this->view->render($this->runData);
            if ($useRouteCache) {
                $output = ob_get_clean();
                $meta = [
                    'uri' => $this->runData['request']->uri ?? '',
                    'space_id' => $spaceId,
                    'ms_id' => $this->runData['ms']['id'] ?? null,
                    'route_id' => $this->runData['route']['id'] ?? null,
                ];
                $this->cacheService->set(
                    $this->runData['ms']['name'],
                    'route',
                    (string)$this->runData['route']['id'],
                    $cacheVariant,
                    $output,
                    $cacheTtl,
                    $meta
                );
                echo $output;
            }
        } elseif ($this->runData['entity']['view_status'] == 'redirect') {
            // Ensure redirect_url is set and is a string to avoid potential issues
            if (isset($this->runData['entity']['redirect_url']) && is_string($this->runData['entity']['redirect_url'])) {
                $redirectUrl = $this->runData['entity']['redirect_url'];
                header("Location: {$redirectUrl}");
                exit;
            } else {
                // Handle the case where 'redirect_url' is not set or not a string
                // Log the error, throw an exception, or set a default redirection
                error_log('Redirect URL is not set or not a string');
                // You could redirect to a default page or show an error
                // For example: header("Location: /error-page");
                exit;
            }
        } else {
            exit;
        }
    }

    /**
     * Redirect to Login with Post Login URL
     */
    public function redirectToLogin() {
        $redirectUrlPostLogin = $this->runData['config']['sys']['base_url'].$this->runData['request']->uri;
        setcookie('redirect_url_post_login', $redirectUrlPostLogin, time() + (86400 * 30), "/");
        $loginUrl = $this->runData['config']['sys']['base_url'].'/login/localsession';
        header("Location: {$loginUrl}");
        exit;
    }

    /**
     * Get Content
     */
    public function getContent($contentID, $refPath) {
        $branchService = new \Core\Sys\BranchService(
            $this->db,
            $this->runData['config'] ?? [],
            $this->runData['entity'] ?? [],
            $this->runData['request'] ?? null
        );
        $branch = $branchService->resolveRuntimeBranch([
            'ms_name' => (string)($this->runData['ms']['name'] ?? ''),
            'content_id' => (int)$contentID,
        ]);
        $cacheVariant = json_encode([
            'ref' => $refPath,
            'space_id' => $this->runData['route']['space_id'] ?? 0,
            'slug' => $refPath === 'slug' ? (string)$contentID : null,
            'branch' => $branch,
        ], JSON_UNESCAPED_SLASHES);
        if ($this->cacheService->isEnabled() && $refPath === 'id') {
            $cacheHit = $this->cacheService->get($this->runData['ms']['name'], 'content', (string)$contentID, $cacheVariant);
            if (!empty($cacheHit['hit']) && is_array($cacheHit['payload'])) {
                $payload = $cacheHit['payload'];
                $this->runData['route']['content_id'] = $payload['id'] ?? $contentID;
                $this->runData['route']['content_title'] = $payload['s_title'] ?? '';
                $this->runData['route']['content'] = $payload['s_content'] ?? '';
                $this->runData['route']['meta_title'] = $payload['s_meta_title'] ?? '';
                $this->runData['route']['meta_description'] = $payload['s_meta_description'] ?? '';
                $this->registerCacheActivity('hit', 'content');
                $this->registerCacheDebug('hit', $cacheVariant, $cacheHit, 'content', (string)$contentID);
                return;
            }
            $this->registerCacheActivity('miss', 'content');
            $this->registerCacheDebug('miss', $cacheVariant, $cacheHit, 'content', (string)$contentID);
        }
        if ($refPath == 'slug') {
            $contentDetails = $this->db->select('s_content', ['livestatus'=>'1','s_slug' => $contentID ], false);
        }
        else if ($refPath == 'id') {
            $contentDetails = $this->db->select('s_content', ['livestatus'=>'1','id' => $contentID ], false);
        }
        else {
            $this->errorHandler->handleException('Invalid refPath for accessing Content.');
        }
        if(count($contentDetails) == 1) {
            if ($branch === 'beta') {
                $beta = $this->extractContentBranch($contentDetails[0]);
                if (!empty($beta)) {
                    $contentDetails[0] = $this->applyContentBranch($contentDetails[0], $beta);
                }
            }
            $resolvedContentId = $contentDetails[0]['id'] ?? $contentID;
            $this->runData['route']['content_id'] = $resolvedContentId;
            $this->runData['route']['content_title'] = $contentDetails[0]['s_title'];
            $this->runData['route']['content'] = $contentDetails[0]['s_content'];
            $this->runData['route']['meta_title'] = $contentDetails[0]['s_meta_title'];
            $this->runData['route']['meta_description'] = $contentDetails[0]['s_meta_description'];
            if ($this->cacheService->isEnabled()) {
                $cacheId = (string)$resolvedContentId;
                $payload = [
                    'id' => $resolvedContentId,
                    's_title' => $contentDetails[0]['s_title'],
                    's_content' => $contentDetails[0]['s_content'],
                    's_meta_title' => $contentDetails[0]['s_meta_title'],
                    's_meta_description' => $contentDetails[0]['s_meta_description'],
                ];
                $this->cacheService->set(
                    $this->runData['ms']['name'],
                    'content',
                    $cacheId,
                    $cacheVariant,
                    $payload,
                    $this->cacheService->defaultTtl('content'),
                    [
                        'space_id' => $this->runData['route']['space_id'] ?? 0,
                        'ms_id' => $this->runData['ms']['id'] ?? null,
                        'route_id' => $this->runData['route']['id'] ?? null,
                    ]
                );
            }
            // print '<pre>';print_r($this->runData['route']);print '</pre>';die('here');
        }
        // else {
        //     $this->errorHandler->handleException('Content not found.');
        // }
    }

    private function enforcePermissionBindings(bool $routeBindings, bool $msBindings): void {
        if (!$this->runData['entity']['is_logged_in']) {
            $this->redirectToLogin();
        }

        $entityId = $this->runData['entity']['id'] ?? null;
        if ($entityId === null) {
            $this->redirectToLogin();
        }

        if ($this->isSuperAdmin()) {
            $this->runData['entity']['route_access'] = 'Y';
            $this->runData['route']['access'] = 'Y';
            return;
        }

        $spaceId = $this->runData['route']['space_id'] ?? null;
        $msId = (int)$this->runData['ms']['id'];
        $objectType = $routeBindings ? 'route' : 'ms';
        $objectId = $routeBindings ? (int)$this->runData['route']['id'] : $msId;

        if (!$this->permissionService->canAccess($entityId, $objectType, $objectId, 'use', $spaceId, $msId)) {
            $this->errorHandler->handleException('Access Denied');
        }

        $this->runData['entity']['route_access'] = 'Y';
        $this->runData['route']['access'] = 'Y';
    }

    private function isSuperAdmin(): bool {
        $entity = $this->runData['entity'] ?? [];
        return !empty($entity['id']) && (int)$entity['id'] === 1;
    }

    private function extractContentBranch(array $contentRow): array {
        $infoRaw = $contentRow['s_additional_info'] ?? '';
        if (!$infoRaw) {
            return [];
        }
        $info = is_array($infoRaw) ? $infoRaw : json_decode((string)$infoRaw, true);
        if (!is_array($info)) {
            return [];
        }
        $beta = $info['branch_beta'] ?? [];
        return is_array($beta) ? $beta : [];
    }

    private function applyContentBranch(array $contentRow, array $beta): array {
        foreach ($beta as $key => $value) {
            if (array_key_exists($key, $contentRow)) {
                $contentRow[$key] = $value;
            }
        }
        return $contentRow;
    }

    private function handleSpaceBinding(?string $slug): void {
        $spaceId = (int)($this->runData['route']['space_id'] ?? 0);
        if ($spaceId <= 0) {
            if ($slug === null || $slug === '') {
                $this->renderSpaceError('Workspace identifier (space UID/slug) is required for SaaS routes.');
            }
            $spaceDetails = $this->db->select('s_space', ['livestatus' => '1', 'uid' => $slug], true);
            if (count($spaceDetails) !== 1) {
                $this->errorHandler->handleException('Space not found.');
            }
            $spaceId = (int)$spaceDetails[0]['id'];
        } else {
            $spaceDetails = $this->db->select('s_space', ['livestatus' => '1', 'id' => $spaceId], true);
            if (count($spaceDetails) !== 1) {
                $this->errorHandler->handleException('Space not found.');
            }
        }

        $this->runData['route']['space_id'] = $spaceId;
        $this->runData['route']['space_uid'] = $spaceDetails[0]['uid'] ?? ($this->runData['route']['space_uid'] ?? null);
        $this->runData['route']['space_name'] = $spaceDetails[0]['s_name'] ?? ($this->runData['route']['space_name'] ?? null);
        $this->runData['route']['space_slug'] = $spaceDetails[0]['s_slug'] ?? ($this->runData['route']['space_slug'] ?? null);

        if ($this->isSuperAdmin()) {
            // Superuser still needs a space UID, but bypasses membership checks.
            $this->runData['route']['space_binding'] = 'override';
            $roleSet = $this->permissionService->resolveRoleSet(
                (int)($this->runData['entity']['id'] ?? 0),
                $spaceId,
                (int)($this->runData['ms']['id'] ?? 0)
            );
            $this->runData['route']['space_role_id'] = $roleSet['workspace_role_id'] ?? null;
            $this->runData['route']['ms_role_id'] = null;
            return;
        }

        $entityId = $this->runData['entity']['id'] ?? null;
        if ($entityId === null) {
            $this->errorHandler->handleException('Access Denied');
        }

        $roleSet = $this->permissionService->resolveRoleSet($entityId, $spaceId, (int)($this->runData['ms']['id'] ?? 0));
        if (empty($roleSet['roles'])) {
            $this->errorHandler->handleException('Access Denied');
        }

        $this->runData['route']['space_binding'] = 'Y';
        $this->runData['route']['space_id'] = $spaceId;
        $this->runData['route']['space_uid'] = $spaceDetails[0]['uid'];
        $this->runData['route']['space_name'] = $spaceDetails[0]['s_name'];
        $this->runData['route']['space_role_id'] = $roleSet['workspace_role_id'] ?? null;
        $this->runData['route']['ms_role_id'] = null;
    }

    private function renderSpaceError(string $message): void {
        http_response_code(400);
        $this->runData['route']['error_status'] = 'error';
        $this->runData['route']['h1'] = 'Workspace Required';
        $this->runData['route']['meta_title'] = 'Workspace Required';
        $this->runData['route']['alert'] = 'danger';
        $this->runData['route']['alert_message'] = $message;
        $this->runData['route']['error_code'] = 400;
        $this->runData['route']['error_message'] = $message;
        $this->runData['route']['error_path'] = $this->runData['request']->uri ?? '';
        $this->runData['route']['path_full'] = 'error-pages/400';
        $this->runData['route']['url'] = $this->runData['config']['sys']['base_url'] . '/error-pages/400';
        $this->runData['ms']['tpl_name'] = 'error-page';
        $this->attachDebugBlock();
        $this->view->render($this->runData);
        exit;
    }

    /**
     * Check if the IP restriction to the application Y/N. If Y, check whether the client IP is in the allowed_ips list
     */
    private function checkIPRestriction() {
        // print '<pre>';print_r($this->runData['config']['sys']);print '</pre>';die();
        if ($this->runData['config']['sys']['ip_access_restrict'] == 'Y') {
            $allowedIPs = explode(',', $this->runData['config']['sys']['allowed_ips']);
            // the client IP should be the public IP of the user accessing the application
            $clientIP = $this->ipAccessService->getClientIp();
            // print '<pre>';print_r($clientIP);print '</pre>';
            // print '<pre>';print_r($allowedIPs);print '</pre>';die();
            if (!in_array($clientIP, $allowedIPs)) {
                // $this->errorHandler->handleException('IP not allowed');
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'IP not allowed to access the application.';
                $this->runData['route']['error_status'] = 'error';
                // print '<pre>';print_r($this->runData);print '</pre>';die('here');
                $this->view->render($this->runData);
                exit();
            }
        }
    }

    private function denyAccess(): void {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }

    private function enforceIpAccessRules(?array $spaceRow = null): void {
        if (strtoupper((string)($this->runData['ms']['type'] ?? '')) !== 'DYN') {
            return;
        }

        $entityId = (int)($this->runData['entity']['id'] ?? $this->session->get('entity_id') ?? 0);
        $clientIp = $this->ipAccessService->getClientIp();
        $msRule = $this->ipAccessService->extractRuleFromDefinition($this->runData['ms']['definition'] ?? []);
        $scope = strtolower((string)($this->runData['ms']['scope'] ?? 'platform'));

        if ($scope === 'platform') {
            $msResult = $this->ipAccessService->evaluate($msRule, $entityId, $clientIp);
            if (!$msResult['allowed']) {
                $this->renderIpAccessDenied(
                    'Microservicelet IP Restricted',
                    'This platform microservicelet is available only from approved IP addresses.'
                );
            }
            return;
        }

        if ($scope !== 'workspace') {
            return;
        }

        $spaceRule = $this->ipAccessService->extractRuleFromDefinition($spaceRow['s_definition'] ?? []);
        $spaceResult = $this->ipAccessService->evaluate($spaceRule, $entityId, $clientIp);
        if (!$spaceResult['allowed']) {
            $this->renderIpAccessDenied(
                'Workspace IP Restricted',
                'This workspace is available only from approved IP addresses.'
            );
        }
    }

    private function renderIpAccessDenied(string $title, string $message): void {
        http_response_code(403);
        $clientIp = $this->ipAccessService->getClientIp();
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $safeIp = htmlspecialchars($clientIp !== '' ? $clientIp : 'Unavailable', ENT_QUOTES, 'UTF-8');
        $baseUrl = htmlspecialchars($this->runData['config']['sys']['base_url'] ?? '/', ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . $safeTitle . '</title>';
        echo '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f5f7fb;color:#172033;margin:0}.wrap{max-width:720px;margin:64px auto;padding:0 20px}.card{background:#fff;border:1px solid #d9e1ec;border-radius:16px;padding:28px;box-shadow:0 12px 30px rgba(15,23,42,.08)}.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:12px;font-weight:600}.muted{color:#5b667a}.actions{margin-top:24px}.actions a{display:inline-block;padding:10px 14px;border-radius:10px;background:#0f172a;color:#fff;text-decoration:none}</style>';
        echo '</head><body><div class="wrap"><div class="card"><div class="badge">403 Forbidden</div><h1>' . $safeTitle . '</h1><p class="muted">' . $safeMessage . '</p><p class="muted"><strong>Your IP:</strong> ' . $safeIp . '</p><div class="actions"><a href="' . $baseUrl . '">Return Home</a></div></div></div></body></html>';
        exit;
    }

    private function shouldUseRouteCache(array $routeDefinition): bool {
        if (!$this->cacheService->isEnabled()) {
            return false;
        }
        $method = strtoupper((string)($this->runData['request']->method ?? ''));
        if ($method !== 'GET') {
            return false;
        }
        if (!empty($this->runData['request']->get['debug_block'])) {
            return false;
        }
        if (!empty($this->runData['request']->get['nocache'])) {
            return false;
        }
        if (($this->runData['route']['access_scope'] ?? '') !== 'public') {
            return false;
        }
        if (!empty($this->runData['entity']['is_logged_in'])) {
            return false;
        }
        $cacheConfig = $routeDefinition['cache'] ?? null;
        if (is_array($cacheConfig) && array_key_exists('enabled', $cacheConfig)) {
            $enabled = $cacheConfig['enabled'];
            if (is_string($enabled)) {
                $enabled = strtoupper($enabled) === 'Y';
            }
            if (!$enabled) {
                return false;
            }
        }
        return $this->resolveRouteCacheTtl($routeDefinition) > 0;
    }

    private function resolveRouteCacheTtl(array $routeDefinition): int {
        $cacheConfig = $routeDefinition['cache'] ?? null;
        if (is_array($cacheConfig) && isset($cacheConfig['ttl'])) {
            return max(0, (int)$cacheConfig['ttl']);
        }
        return $this->cacheService->defaultTtl('route');
    }

    private function buildRouteCacheVariant(?int $spaceId): string {
        $defaults = $this->cacheService->variantDefaults();
        $ignore = $this->cacheService->ignoredQueryParams();
        $query = $this->runData['request']->get ?? [];
        foreach ($ignore as $key) {
            unset($query[$key]);
        }
        if (!empty($query)) {
            ksort($query);
        }

        $uri = (string)($this->runData['request']->uri ?? '');
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;
        $segments = array_values(array_filter(explode('/', trim((string)$path, '/'))));
        $msName = $this->runData['ms']['name'] ?? '';
        if ($msName !== '' && !empty($segments) && $segments[0] === $msName) {
            array_shift($segments);
        }

        $variant = [];
        if (!empty($defaults['host'])) {
            $variant['host'] = $this->runData['request']->host ?? ($_SERVER['HTTP_HOST'] ?? '');
        }
        if (!empty($defaults['segments'])) {
            $variant['segments'] = $segments;
        }
        if (!empty($defaults['query'])) {
            $variant['query'] = $query;
        }
        if (!empty($defaults['space'])) {
            $variant['space_id'] = $spaceId ?? 0;
        }
        $variant['branch'] = $this->runData['route']['branch'] ?? 'live';
        return json_encode($variant, JSON_UNESCAPED_SLASHES);
    }

    private function registerCacheActivity(string $status, string $type): void {
        if (!empty($this->runData['activity'])) {
            return;
        }
        $label = sprintf('Cache %s (%s)', $status, $type);
        $this->runData['activity'] = [
            'activity_label' => $label,
            'activity_notify' => false,
            'activity_severity' => 'info',
            'activity_event' => 'cache_' . $type . '_' . $status,
            'space_id' => $this->runData['route']['space_id'] ?? 0,
            'ms_id' => $this->runData['ms']['id'] ?? null,
            'ms_name' => $this->runData['ms']['name'] ?? '',
            'route_id' => $this->runData['route']['id'] ?? null,
            'route_uid' => $this->runData['route']['uid'] ?? null,
            'route_name' => $this->runData['route']['name'] ?? '',
        ];
    }

    private function registerCacheDebug(string $status, string $variant, array $cacheData, string $type, ?string $id = null): void {
        if (!$this->shouldShowDebugBlock()) {
            return;
        }
        if (!isset($this->runData['debug'])) {
            $this->runData['debug'] = [];
        }
        if (!isset($this->runData['debug']['cache']) || !is_array($this->runData['debug']['cache'])) {
            $this->runData['debug']['cache'] = [];
        }
        $this->runData['debug']['cache'][] = [
            'status' => $status,
            'type' => $type,
            'ms' => $this->runData['ms']['name'] ?? '',
            'id' => $id ?? ($this->runData['route']['id'] ?? null),
            'variant' => $variant,
            'reason' => $cacheData['reason'] ?? null,
            'path' => $cacheData['path'] ?? null,
            'created_at' => $cacheData['created_at'] ?? null,
            'ttl' => $cacheData['ttl'] ?? null,
        ];
    }

    private function attachDebugBlock(): void {
        if (!$this->shouldShowDebugBlock()) {
            return;
        }

        $debug = $this->runData['debug'] ?? [];
        if (!is_array($debug)) {
            $debug = ['value' => $debug];
        }

        $checkpointStats = [];
        $start = null;
        $prev = null;
        $checkpoints = $debug['checkpoints'] ?? [];
        if (is_array($checkpoints)) {
            foreach ($checkpoints as $idx => $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $ts = $entry['ts'] ?? $entry['time'] ?? null;
                if ($ts === null) {
                    continue;
                }
                $ts = (float)$ts;
                if ($start === null) {
                    $start = $ts;
                }
                $label = $entry['label'] ?? ('Checkpoint ' . ($idx + 1));
                $delta = $prev !== null ? $ts - $prev : 0.0;
                $elapsed = $start !== null ? $ts - $start : 0.0;
                $checkpointStats[] = [
                    'label' => $label,
                    'ts' => $ts,
                    'delta_ms' => round($delta * 1000, 2),
                    'elapsed_ms' => round($elapsed * 1000, 2),
                ];
                $prev = $ts;
            }
        }

        $debug['generated_at'] = date('Y-m-d H:i:s');
        if (!empty($checkpointStats)) {
            $debug['checkpoint_stats'] = $checkpointStats;
        }

        $this->runData['route']['debug_block'] = [
            'generated_at' => date('Y-m-d H:i:s'),
            'request_uri' => $this->runData['request']->uri ?? '',
            'payload' => $debug,
        ];
    }

    private function shouldShowDebugBlock(): bool {
        $debugFlag = strtoupper((string)($this->runData['config']['sys']['dev_debug_flag']
            ?? $this->runData['config']['app']['dev_debug_flag']
            ?? 'N')) === 'Y';
        if (!$debugFlag) {
            return false;
        }
        $query = $this->runData['request']->get['debug_block'] ?? '';
        if ($query !== '1') {
            return false;
        }
        $entity = $this->runData['entity'] ?? [];
        if (empty($entity['is_logged_in'])) {
            return false;
        }
        $entityId = (int)($entity['id'] ?? 0);
        if ($entityId === 1) {
            return true;
        }
        $roleId = $entity['nonsaas_role_id'] ?? ($entity['s_nonsaas_role_id'] ?? ($entity['role_id'] ?? null));
        if (is_array($roleId)) {
            return in_array(1, $roleId, true);
        }
        return (int)$roleId === 1;
    }
}

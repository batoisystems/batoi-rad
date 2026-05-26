<?php
namespace RadAdmin;
use Core\Sys\TimeHelper;
class Theme{
    use AiAssistAware;
    private const MAX_TEMPLATE_VERSIONS = 20;
    private $runData = [];
    private $db;
    private $errorHandler;
    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }
    public function view(){
        if ( !is_dir($this->runData['config']['dir']['rad'] . '/theme/') ) {
            throw new \Exception('Invalid Theme Folder', 404);
        }
        // print '<pre>';print_r($configRow);print '</pre>';die('here');
        // add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'Here, you manage the theme UI templates.';
        $this->runData['route']['h1'] = 'Theme UI Templates';
        // print '<pre>';print_r($this->runData['request']);print '</pre>';die('here');
        return $this->runData;
    }

    public function viewone() {
        $this->resetOpcacheInDev();
        $template = $this->resolveTemplateNameFromRoute(3);
        if ($template === '') {
            throw new \Exception('Invalid Template File', 404);
        }

        $filePath = $this->getTemplateFilePath($template);
        if (!is_file($filePath)) {
            throw new \Exception('Template file not found', 404);
        }

        $stats = $this->collectTemplateStats($filePath);
        $usage = $this->fetchTemplateUsage($template);
        $history = $this->loadTemplateHistory($template);
        $versions = $this->loadTemplateVersions($template);
        if (empty($history)) {
            $history[] = [
                'action' => 'Template detected',
                'timestamp' => $stats['modified_unix'],
                'user' => 'Filesystem',
                'size' => $stats['size_bytes'],
                'size_human' => $stats['size_human'],
                'details' => 'Initial state captured from filesystem metadata.',
            ];
        }

        $this->runData['route']['h1'] = 'Template: <code>' . $template . '</code>';
        $this->runData['route']['meta_title'] = 'Theme Template - ' . $template;
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/theme/view';
        $this->runData['data']['template_detail'] = [
            'name' => $template,
            'file' => $template . '.tpl.php',
            'path' => $filePath,
            'code' => file_get_contents($filePath) ?: '',
            'stats' => $stats,
            'usage' => $usage,
            'history' => $history,
            'versions' => $versions,
            'available_templates' => array_values(array_filter($this->listThemeTemplates(), function ($name) use ($template) {
                return $name !== $template;
            })),
        ];

        return $this->runData;
    }

    public function add() {
        if ( !is_dir($this->runData['config']['dir']['rad'] . '/theme/') ) {
            throw new \Exception('Invalid Theme Folder', 404);
        }

        if (!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Create a new theme template file.';
        }
        $this->runData['route']['h1'] = 'Add Theme Template';
        $this->runData['route']['meta_title'] = 'Add Theme Template';
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/theme/view';

        $request = $this->runData['request'];
        if (strtoupper($request->method) === 'POST') {
            $rawName = trim($request->post['template_name'] ?? '');
            $content = $request->post['template_content'] ?? '';

            $safeName = $this->sanitizeTemplateName($rawName);
            if ($safeName === '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Template name must contain letters, numbers, or underscores.';
                return $this->runData;
            }

            $filePath = $this->runData['config']['dir']['theme'] . '/' . $safeName . '.tpl.php';
            if (file_exists($filePath)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'A template with this name already exists.';
                return $this->runData;
            }

            $templateBody = $content !== '' ? $content : $this->defaultTemplateStub($safeName);
            if (file_put_contents($filePath, $templateBody) === false) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Failed to create the template file.';
                return $this->runData;
            }

            $this->createTemplateVersion($safeName, $templateBody, [
                'note' => 'Template created',
            ]);
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'Template created successfully. Redirecting to editor...';
            $this->recordTemplateHistory($safeName, 'Template created', [
                'size' => strlen($templateBody),
            ]);
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/theme/edittemplate/' . $safeName;
            header("Location: {$redirectUrl}");
            exit;
        }

        return $this->runData;
    }

    public function duplicate() {
        $sourceTemplate = $this->resolveTemplateNameFromRoute(3);
        if ($sourceTemplate === '') {
            throw new \Exception('Invalid Template File', 404);
        }

        $sourcePath = $this->getTemplateFilePath($sourceTemplate);
        if (!is_file($sourcePath)) {
            throw new \Exception('Template file not found', 404);
        }

        $request = $this->runData['request'];
        if (strtoupper($request->method) === 'POST') {
            $rawName = trim($request->post['template_name'] ?? '');
            $safeName = $this->sanitizeTemplateName($rawName);
            if ($safeName === '') {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Template name must contain letters, numbers, or underscores.';
                return $this->prepareDuplicateContext($sourceTemplate, $sourcePath);
            }

            $targetPath = $this->getTemplateFilePath($safeName);
            if (file_exists($targetPath)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'A template with this name already exists.';
                return $this->prepareDuplicateContext($sourceTemplate, $sourcePath);
            }

            if (!@copy($sourcePath, $targetPath)) {
                $this->runData['route']['alert'] = 'danger';
                $this->runData['route']['alert_message'] = 'Failed to duplicate the template.';
                return $this->prepareDuplicateContext($sourceTemplate, $sourcePath);
            }

            $content = file_get_contents($targetPath) ?: '';
            $size = strlen($content);
            $this->createTemplateVersion($safeName, $content, [
                'note' => 'Duplicated from ' . $sourceTemplate,
            ]);
            $this->recordTemplateHistory($safeName, 'Template duplicated from ' . $sourceTemplate, [
                'size' => $size,
                'details' => 'Duplicated from ' . $sourceTemplate,
            ]);

            $this->runData['request']->setAlert('Template duplicated successfully.', 'success');
            $redirectUrl = $this->runData['route']['rad_admin_url'] . '/theme/viewone/' . $safeName;
            header("Location: {$redirectUrl}");
            exit;
        }

        return $this->prepareDuplicateContext($sourceTemplate, $sourcePath);
    }

    public function restoreversion() {
        $template = $this->resolveTemplateNameFromRoute(3);
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][4] ?? '');
        $redirect = $this->runData['route']['rad_admin_url'] . '/theme/viewone/' . $template;

        if ($template === '' || $versionId === '') {
            header("Location: {$redirect}");
            exit;
        }

        if (strtoupper($this->runData['request']->method) !== 'POST') {
            header("Location: {$redirect}");
            exit;
        }

        $versionPath = $this->getVersionFilePath($template, $versionId);
        if (!$versionPath) {
            $this->runData['request']->setAlert('Version not found.', 'danger');
            header("Location: {$redirect}");
            exit;
        }

        $content = file_get_contents($versionPath);
        $targetPath = $this->getTemplateFilePath($template);
        if (file_put_contents($targetPath, $content) === false) {
            $this->runData['request']->setAlert('Failed to restore template.', 'danger');
            header("Location: {$redirect}");
            exit;
        }

        $this->createTemplateVersion($template, $content, [
            'note' => 'Restored from version ' . $versionId,
        ]);
        $this->recordTemplateHistory($template, 'Template restored from version', [
            'size' => strlen($content),
            'details' => 'Restored from version ' . $versionId,
        ]);

        $this->runData['request']->setAlert('Template restored successfully.', 'success');
        header("Location: {$redirect}");
        exit;
    }

    public function downloadversion() {
        $template = $this->resolveTemplateNameFromRoute(3);
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][4] ?? '');
        if ($template === '' || $versionId === '') {
            throw new \Exception('Invalid version request', 404);
        }

        $versionPath = $this->getVersionFilePath($template, $versionId);
        if (!$versionPath) {
            throw new \Exception('Version file not found', 404);
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $template . '-' . $versionId . '.tpl.php"');
        readfile($versionPath);
        exit;
    }

    public function diffversion() {
        $template = $this->resolveTemplateNameFromRoute(3);
        $versionId = $this->sanitizeVersionId($this->runData['route']['pathparts'][4] ?? '');
        if ($template === '' || $versionId === '') {
            throw new \Exception('Invalid version request', 404);
        }

        $versionEntry = $this->getVersionEntry($template, $versionId);
        $versionPath = $this->getVersionFilePath($template, $versionId);
        $livePath = $this->getTemplateFilePath($template);
        if (!$versionEntry || !$versionPath || !is_file($livePath)) {
            throw new \Exception('Version not found', 404);
        }

        $versionLines = file($versionPath, FILE_IGNORE_NEW_LINES);
        $liveLines = file($livePath, FILE_IGNORE_NEW_LINES);
        $diff = $this->renderDiff($versionLines ?: [], $liveLines ?: []);

        $this->runData['route']['h1'] = 'Diff: ' . $template;
        $this->runData['route']['meta_title'] = 'Diff - ' . $template;
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/theme/viewone/' . $template;
        $this->runData['data']['diff'] = [
            'template' => $template,
            'version' => $versionEntry,
            'diff' => $diff,
        ];
        return $this->runData;
    }

    /**
     * Edit a Template File
     */
    public function edittemplate() {
        // check if the template file exists from the theme folder and with route id from the pathparts array 4th element
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Template File', 404);
        }
        $templateFile = $this->runData['route']['pathparts'][3];
        $templateFileName = explode('.', $templateFile)[0];
        $filePath = $this->getTemplateFilePath($templateFileName);
        if (!is_file($filePath)) {
            throw new \Exception('Invalid Template File', 404);
        }
        $this->runData['route']['h1'] = 'Template File - <code>'.$templateFile.'</code>';
        $this->runData['route']['meta_title'] = 'Template File - '.$templateFile;
        $this->runData['data']['templateFileName'] = $templateFileName;
        $this->runData["data"]["code_tpl"] = file_get_contents($filePath);
        $this->runData['route']['backlink'] = $this->runData['config']['sys']['base_url'].'/rad-admin/theme/view';
        return $this->runData;
    }

    /**
     * Save code for a template file
     */
    public function savetpl() {
        $data = json_decode(file_get_contents("php://input"), true);
    
        $response = [];
        header('Content-Type: application/json');
    
        if (!$data || !isset($data['type']) || !isset($data['content'])) {
            $response = ['message' => 'Invalid data provided'];
            echo json_encode($response);
            exit;
        }
    
        $type = $data['type'];
        $content = $data['content'];
        $templateName = $this->resolveTemplateNameFromRoute(4);
        if ($templateName === '') {
            $templateName = $this->resolveTemplateNameFromRoute(3);
        }
        if ($templateName === '') {
            $response = ['message' => 'Invalid template name'];
            echo json_encode($response);
            exit;
        }

        $file_path = $this->getTemplateFilePath($templateName);
        if (!is_file($file_path)) {
            $response = ['message' => 'Template does not exist'];
            echo json_encode($response);
            exit;
        }
    
        // Save the content to the file
        if ($file_path) {
            $expectedChecksum = trim((string)($data['expected_checksum'] ?? ''));
            $currentContent = (string)(file_get_contents($file_path) ?: '');
            if ($expectedChecksum !== '' && sha1($currentContent) !== $expectedChecksum) {
                http_response_code(409);
                echo json_encode([
                    'message' => 'The template changed since the patch was generated. Refresh context and regenerate the patch.',
                    'current_checksum' => sha1($currentContent),
                ], JSON_UNESCAPED_SLASHES);
                exit;
            }
            if (file_put_contents($file_path, $content) === false) {
                $response = ['message' => 'Failed to save the content'];
                echo json_encode($response);
                exit;
            }

            $createVersion = !empty($data['create_version']);
            $latestVersion = null;
            if ($createVersion) {
                $this->createTemplateVersion($templateName, $content, [
                    'note' => 'Manual Save & Version',
                    'channel' => $type,
                ]);
                $this->recordTemplateHistory($templateName, 'Template version created', [
                    'size' => strlen($content),
                    'channel' => $type,
                ]);
                $versions = $this->loadTemplateVersions($templateName, 1);
                $latestVersion = $versions[0] ?? null;
            }

            $response = [
                'message' => '',
                'checksum' => sha1($content),
                'latest_version' => $latestVersion,
            ];
            echo json_encode($response);
            exit;
        }
    }

    public function agentcontext() {
        header('Content-Type: application/json');
        if ($this->errorHandler && method_exists($this->errorHandler, 'setResponseMode')) {
            $this->errorHandler->setResponseMode('json');
        }
        try {
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $payload = $this->runData['request']->post ?? $this->runData['request']->get ?? [];
            }

            $template = $this->resolveTemplateNameFromRoute(3);
            $workspace = $this->resolveThemeWorkspaceContext($template);
            if ($workspace === null) {
                http_response_code(404);
                echo json_encode(['error' => 'Theme template workspace not found.'], JSON_UNESCAPED_SLASHES);
                return;
            }

            $task = trim((string)($payload['task'] ?? ''));
            $scope = trim((string)($payload['scope'] ?? 'template_only'));
            $relatedTemplates = $this->normalizeRelatedTemplateSelection($payload['related_templates'] ?? []);
            $relatedContext = $this->resolveThemeRelatedContext($template, $task, $scope, $relatedTemplates);

            echo json_encode([
                'context' => $this->buildThemeAgentContextPayload($workspace, $relatedContext),
            ], JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->logError('Theme agent context error: ' . $e->getMessage());
            }
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage() ?: 'Unable to load theme context.'], JSON_UNESCAPED_SLASHES);
        }
    }

    public function agentplan() {
        header('Content-Type: application/json');
        if ($this->errorHandler && method_exists($this->errorHandler, 'setResponseMode')) {
            $this->errorHandler->setResponseMode('json');
        }
        try {
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $payload = $this->runData['request']->post ?? [];
            }

            $task = trim((string)($payload['task'] ?? ''));
            $scope = trim((string)($payload['scope'] ?? 'template_only'));
            if ($task === '') {
                http_response_code(422);
                echo json_encode(['error' => 'Please describe what you want the agent to do.'], JSON_UNESCAPED_SLASHES);
                return;
            }

            $template = $this->resolveTemplateNameFromRoute(3);
            $workspace = $this->resolveThemeWorkspaceContext($template);
            if ($workspace === null) {
                http_response_code(404);
                echo json_encode(['error' => 'Theme template workspace not found.'], JSON_UNESCAPED_SLASHES);
                return;
            }

            $relatedTemplates = $this->normalizeRelatedTemplateSelection($payload['related_templates'] ?? []);
            $relatedContext = $this->resolveThemeRelatedContext($template, $task, $scope, $relatedTemplates);

            echo json_encode([
                'plan' => $this->buildThemeAgentPlan($task, $scope, $workspace, $relatedContext),
                'context' => $this->buildThemeAgentContextPayload($workspace, $relatedContext),
            ], JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            if ($this->errorHandler) {
                $this->errorHandler->logError('Theme agent plan error: ' . $e->getMessage());
            }
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage() ?: 'Unable to generate theme plan.'], JSON_UNESCAPED_SLASHES);
        }
    }

    public function agentpatch() {
        header('Content-Type: application/json');
        if ($this->errorHandler && method_exists($this->errorHandler, 'setResponseMode')) {
            $this->errorHandler->setResponseMode('json');
        }

        try {
            $this->traceThemeAgentPatch('agentpatch:start');
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $payload = $this->runData['request']->post ?? [];
            }

            $task = trim((string)($payload['task'] ?? ''));
            $scope = trim((string)($payload['scope'] ?? 'template_only'));
            if ($task === '') {
                http_response_code(422);
                echo json_encode(['error' => 'Please describe the requested template change first.'], JSON_UNESCAPED_SLASHES);
                return;
            }

            $template = $this->resolveTemplateNameFromRoute(3);
            $workspace = $this->resolveThemeWorkspaceContext($template);
            if ($workspace === null) {
                http_response_code(404);
                echo json_encode(['error' => 'Theme template workspace not found.'], JSON_UNESCAPED_SLASHES);
                return;
            }
            $this->traceThemeAgentPatch('agentpatch:workspace', [
                'template' => $template,
                'code_size' => strlen((string)($workspace['code'] ?? '')),
            ]);

            $relatedTemplates = $this->normalizeRelatedTemplateSelection($payload['related_templates'] ?? []);
            $relatedContext = $this->resolveThemeRelatedContext($template, $task, $scope, $relatedTemplates);
            $this->traceThemeAgentPatch('agentpatch:related_context', [
                'selected' => count($relatedContext['selected'] ?? []),
                'detected' => count($relatedContext['detected'] ?? []),
                'templates' => count($relatedContext['templates'] ?? []),
            ]);

            $proposal = $this->generateThemeAgentPatch($task, $scope, $workspace, $relatedContext);
            $this->traceThemeAgentPatch('agentpatch:proposal_ready', [
                'proposal_size' => strlen((string)($proposal['proposed_content'] ?? '')),
            ]);

            echo json_encode([
                'proposal' => $proposal,
                'context' => $this->buildThemeAgentContextPayload($workspace, $relatedContext),
            ], JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            $this->traceThemeAgentPatch('agentpatch:error', ['message' => $e->getMessage()]);
            if ($this->errorHandler) {
                $this->errorHandler->logError('Theme agent patch error: ' . $e->getMessage());
            }
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage() ?: 'Unable to generate template patch.'], JSON_UNESCAPED_SLASHES);
        }
    }
    
    /**
     * AI Assist code for Theme Templates
     */
    public function aiassist() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data || !isset($data['content'])) {
            echo json_encode(['error' => 'Invalid data provided']);
            return;
        }

        $service = $this->getAiAssistService('coding', 'full');
        $result = $service->suggest($data['content'], 'theme', [
            'template' => $this->runData['route']['pathparts'][3] ?? '',
        ]);

        echo json_encode($result);
    }
    /**
     * View asset browser
     */
    public function browseassets() {
        // check if the theme exists from the theme folder and with route id from the pathparts array 3rd element
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Theme', 404);
        }
        $theme = $this->runData['route']['pathparts'][3];
        if ( !is_dir($this->runData['config']['dir']['rad'] . '/theme/' . $theme) ) {
            throw new \Exception('Invalid Theme', 404);
        }
        // print '<pre>';print_r($configRow);print '</pre>';die('here');
        // add alert and alert_message to runData - information to be displayed to the user
        $this->runData['route']['alert'] = 'info';
        $this->runData['route']['alert_message'] = 'Here, you browse assets for the theme <code>'.$theme.'</code>.';
        $this->runData['route']['h1'] = 'Theme - <code>'.$theme.'</code> - Browse Assets';
        $this->runData['route']['meta_title'] = 'Theme '.$theme.' - Browse Assets';
        // print '<pre>';print_r($this->runData['request']);print '</pre>';die('here');
        $this->runData['data']['theme'] = $theme;
        // if there is pathparts index 4, then it is a subdirectory, assign it then to $this->runData['data']['innerAssetDirectory'] else the $this->runData['data']['innerAssetDirectory'] will be blank
        if ( isset($this->runData['route']['pathparts'][4]) && ($this->runData['route']['pathparts'][4] != '') ) {
            $this->runData['data']['innerAssetDirectory'] = $this->runData['route']['pathparts'][4];
        } else {
            $this->runData['data']['innerAssetDirectory'] = '';
        }
        $this->runData['route']['backlink'] = $this->runData['config']['sys']['base_url'].'/rad-admin/theme/viewone/'.$theme;
        return $this->runData;
    }

    /**
     * Fetch files and folders from the theme assets folder
     */
    public function fetchfiles() {
        try {
            if (!isset($this->runData['route']['pathparts'][3]) || $this->runData['route']['pathparts'][3] == '') {
                throw new \Exception('Invalid Theme', 404);
            }
            
            $theme = basename($this->runData['route']['pathparts'][3]);
            $target_dir = $this->runData['config']['dir']['rad'] . '/theme/' . $theme . '/assets/';
            // print '<pre>';print_r($target_dir);print '</pre>';die('here');
            if (!is_dir($target_dir)) {
                throw new \Exception('Invalid Theme', 404);
            }
            // print '<pre>';print_r($this->runData['route']['pathparts']);print '</pre>';die('here');
            if (isset($this->runData['route']['pathparts'][4]) && $this->runData['route']['pathparts'][4] != '') {
                // get the pathparts array and remove the first 4 elements
                $pathparts = $this->runData['route']['pathparts'];
                array_splice($pathparts, 0, 4);
                // implode the pathparts array to get the additional path
                $additional_path = implode('/', $pathparts);
                $target_dir .= $additional_path;
                // print '<pre>';print $additional_path;print '<br/>';print_r($target_dir);print '</pre>';die('here');
            }
            
            $files = [];
            $folder_base_url = $this->runData['config']['sys']['base_url'].'/rad-admin/theme/browseassets/'.$theme;
            $file_base_url = $this->runData['config']['sys']['base_url'].'/rad-admin/theme/editfile/'.$theme;
            
            // print '<pre>';print_r($target_dir);print '</pre>';die('here');

            if (is_dir($target_dir)) {
                $timezone = $this->resolveTimezone();
                foreach (new \DirectoryIterator($target_dir) as $file) {
                    if (!$file->isDot()) {
                        // ignore files starting with dot(.) and underscore(_)
                        if (preg_match('/^[._]/', $file->getFilename())) {
                            continue;
                        }
                        if ($file->isDir()) {
                            $file_type = "folder";
                            if ( isset($this->runData['route']['pathparts'][4]) && ($this->runData['route']['pathparts'][4] != '')) {
                                $assetInnerDirectory = $additional_path.'/'.$file->getFilename();
                                $link = $folder_base_url . '/' . $assetInnerDirectory;
                            } else {
                                $folder_name = $file->getFilename();
                                $link = $folder_base_url.'/'.$folder_name;
                            }
                        } else {
                            $file_type = strtolower($file->getExtension());
                            if ( isset($this->runData['route']['pathparts'][4]) && ($this->runData['route']['pathparts'][4] != '')) {
                                $assetInnerDirectory = $additional_path.'/'.$file->getFilename();
                                $link = $file_base_url . '/' .  $file->getFilename() . '/'. $assetInnerDirectory;
                            } else {
                                $link = $file_base_url.'/'.$file->getFilename();
                            }
                        }
                        $icon = $this->getFileIcon($file_type);
                        
                        $files[] = [
                            'icon' => $icon,
                            'name' => $file->getFilename(),
                            'link' => $link,
                            'lastUpdated' => TimeHelper::formatUtc($file->getMTime(), $timezone, 'Y-m-d H:i:s') ?? ''
                        ];
                    }
                }
            }
            header('Content-Type: application/json');
            if(empty($files)) {
                echo json_encode(['error' => 'No files found']);
            } else {
                echo json_encode($files);
            }
        } catch (\Exception $e) {
            header('Content-Type: application/json', true, $e->getCode());
            echo json_encode(['error' => $e->getMessage()]);
            // Add this for debugging.
            file_put_contents("php_error.log", $e->getMessage());
        }
    }

    /**
     * Upload files to the theme assets folder
     */
    public function uploadfiles() {
        // check if the theme exists from the theme folder and with route id from the pathparts array 3rd element
        if ( !isset($this->runData['route']['pathparts'][3]) && ($this->runData['route']['pathparts'][3] == '') ) {
            throw new \Exception('Invalid Theme', 404);
        }
        $theme = $this->runData['route']['pathparts'][3];
        if ( !is_dir($this->runData['config']['dir']['rad'] . '/theme/' . $theme) ) {
            throw new \Exception('Invalid Theme', 404);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (isset($_FILES['files'])) {
                    if (!isset($this->runData['route']['pathparts'][3]) || $this->runData['route']['pathparts'][3] == '') {
                        throw new \Exception('Invalid Theme', 404);
                    }
        
                    $target_dir = $this->runData['config']['dir']['rad'] . '/theme/' . $theme . '/assets/';
        
                    // Validate the theme directory
                    if (!is_dir($target_dir)) {
                        throw new \Exception('Invalid Theme', 404);
                    }
        
                    //if pathparts index 4 exists and not blank, then concatenate it to $redirect_dir
                    if ( isset($this->runData['route']['pathparts'][4]) && ($this->runData['route']['pathparts'][4] != '')) {
                        $target_dir .= $this->runData['route']['pathparts'][4];
                    }
        
                    // Create directory if it doesn't exist
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
        
                    // Move files
                    error_log("Target Directory: " . $target_dir);

                    // Rest of your code
                    foreach ($_FILES['files']['tmp_name'] as $index => $tmpName) {
                        $target_file = $target_dir . '/' . basename($_FILES["files"]["name"][$index]);
                        error_log("Trying to move " . $tmpName . " to " . $target_file);

                        if (move_uploaded_file($tmpName, $target_file)) {
                            error_log("Successfully moved " . $tmpName . " to " . $target_file);
                            echo "Successfully uploaded: " . $_FILES["files"]["name"][$index] . "\n";
                        } else {
                            throw new \Exception('Failed to move uploaded file.', 500);
                        }
                    }
                    
                    http_response_code(200);
                    echo "Success";
                } else {
                    throw new \Exception('No files uploaded.', 400);
                }
            } catch (\Exception $e) {
                error_log("Exception caught: " . $e->getMessage());
                http_response_code($e->getCode());
                echo $e->getMessage();
            }
        }        
    }

    private function resolveTemplateNameFromRoute(int $index): string {
        $raw = $this->runData['route']['pathparts'][$index] ?? '';
        if ($raw === '') {
            return '';
        }
        $raw = str_replace('.tpl.php', '', $raw);
        return $this->sanitizeTemplateName($raw);
    }

    private function getTemplateFilePath(string $template): string {
        return rtrim($this->runData['config']['dir']['theme'] ?? '', '/') . '/' . $template . '.tpl.php';
    }

    private function listThemeTemplates(): array {
        $dir = rtrim($this->runData['config']['dir']['theme'] ?? '', '/');
        if ($dir === '' || !is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.tpl.php') ?: [];
        $templates = [];
        foreach ($files as $file) {
            $templates[] = basename($file, '.tpl.php');
        }
        sort($templates);
        return $templates;
    }

    private function collectTemplateStats(string $filePath): array {
        $size = @filesize($filePath) ?: 0;
        $modified = @filemtime($filePath) ?: time();
        $created = @filectime($filePath) ?: $modified;
        $lines = $this->countTemplateLines($filePath);
        $preview = $this->buildTemplatePreview($filePath);
        $timezone = $this->resolveTimezone();

        return [
            'size_bytes' => $size,
            'size_human' => $this->formatBytes($size),
            'modified' => TimeHelper::formatUtc($modified, $timezone, 'M j, Y H:i') ?? '',
            'modified_unix' => $modified,
            'created' => TimeHelper::formatUtc($created, $timezone, 'M j, Y H:i') ?? '',
            'created_unix' => $created,
            'lines' => $lines,
            'checksum' => @sha1_file($filePath) ?: '',
            'preview' => $preview,
        ];
    }

    private function resolveTimezone(): string {
        return TimeHelper::resolveTimezone(
            $this->runData['entity']['timezone'] ?? null,
            $this->runData['config']['sys']['timezone_default'] ?? null
        );
    }

    private function formatBytes(int $bytes): string {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B','KB','MB','GB','TB'];
        $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }

    private function countTemplateLines(string $filePath): int {
        try {
            $file = new \SplFileObject($filePath, 'r');
            $file->seek(PHP_INT_MAX);
            return $file->key() + 1;
        } catch (\RuntimeException $e) {
            return 0;
        }
    }

    private function buildTemplatePreview(string $filePath, int $lines = 12): array {
        $preview = [];
        try {
            $file = new \SplFileObject($filePath, 'r');
            $count = 0;
            while (!$file->eof() && $count < $lines) {
                $line = rtrim((string)$file->fgets(), "\r\n");
                $preview[] = $line;
                $count++;
            }
        } catch (\RuntimeException $e) {
            return [];
        }
        return $preview;
    }

    private function fetchTemplateUsage(string $template): array {
        $rows = $this->db->select('s_ms', [
            'livestatus' => '1',
            's_tpl_name' => $template,
        ], true, ['s_name' => 'ASC']);

        $role = (new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []))->role();
        if ($role !== 'system_admin') {
            $rows = array_values(array_filter($rows, function ($row) {
                $msId = (int)($row['id'] ?? 0);
                return $msId === 0 || !\RadAdmin\VisibilityHelper::isRestrictedMs($msId, $this->runData['config'] ?? [], $this->runData['entity'] ?? []);
            }));
        }

        $usage = [];
        foreach ($rows as $row) {
            $usage[] = [
                'name' => $row['s_name'] ?? '',
                'description' => $row['s_description'] ?? '',
                'type' => $row['s_type'] ?? '',
                'uid' => $row['uid'] ?? '',
                'default_route_id' => $row['s_default_route_id'] ?? '',
                'updated' => $row['updatestamp'] ?? $row['createstamp'] ?? '',
            ];
        }
        return $usage;
    }

    private function loadTemplateHistory(string $template): array {
        $path = $this->getTemplateHistoryPath();
        if (!is_file($path)) {
            return [];
        }
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data[$template]) || !is_array($data[$template])) {
            return [];
        }
        $entries = $data[$template];
        usort($entries, function ($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });
        return array_slice($entries, 0, 10);
    }

    private function recordTemplateHistory(string $template, string $action, array $meta = []): void {
        if ($template === '') {
            return;
        }
        $path = $this->getTemplateHistoryPath();
        $this->ensureHistoryStorage(dirname($path));

        $data = [];
        if (is_file($path)) {
            $json = file_get_contents($path);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        if (!isset($data[$template]) || !is_array($data[$template])) {
            $data[$template] = [];
        }

        $entry = [
            'action' => $action,
            'timestamp' => time(),
            'user' => $this->getCurrentUserLabel(),
            'size' => $meta['size'] ?? null,
            'size_human' => isset($meta['size']) ? $this->formatBytes((int)$meta['size']) : null,
            'details' => $meta['details'] ?? ($meta['channel'] ?? ''),
        ];

        $data[$template][] = $entry;
        $data[$template] = array_slice($data[$template], -10);

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function getTemplateHistoryPath(): string {
        $logDir = rtrim($this->runData['config']['dir']['rad'] ?? $this->runData['config']['dir']['site'] ?? '', '/');
        return $logDir . '/log/theme-history.json';
    }

    private function ensureHistoryStorage(string $directory): void {
        if ($directory === '') {
            return;
        }
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }

    private function getCurrentUserLabel(): string {
        if (!isset($this->runData['entity']) || empty($this->runData['entity']['is_logged_in'])) {
            return 'RAD Admin';
        }
        if (!empty($this->runData['entity']['fullname'])) {
            return $this->runData['entity']['fullname'];
        }
        if (!empty($this->runData['entity']['username'])) {
            return $this->runData['entity']['username'];
        }
        return 'RAD Admin User';
    }

    private function sanitizeTemplateName(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]+/', '_', $value);
        return trim($value, '_-');
    }

    private function sanitizeVersionId(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]+/', '', $value);
        return $value;
    }

    private function resolveThemeWorkspaceContext(string $template): ?array {
        $template = $this->sanitizeTemplateName($template);
        if ($template === '') {
            return null;
        }
        $filePath = $this->getTemplateFilePath($template);
        if (!is_file($filePath)) {
            return null;
        }
        $code = (string)(file_get_contents($filePath) ?: '');
        return [
            'template' => $template,
            'file' => $template . '.tpl.php',
            'file_path' => $filePath,
            'code' => $code,
            'stats' => $this->collectTemplateStats($filePath),
            'usage' => $this->fetchTemplateUsage($template),
            'history' => array_slice($this->loadTemplateHistory($template), 0, 6),
            'versions' => $this->loadTemplateVersions($template, 6),
        ];
    }

    private function buildThemeAgentContextPayload(array $workspace, array $relatedContext = []): array {
        $stats = $workspace['stats'] ?? [];
        $usage = $workspace['usage'] ?? [];
        $versions = $workspace['versions'] ?? [];
        $history = $workspace['history'] ?? [];
        $code = (string)($workspace['code'] ?? '');

        return [
            'template' => [
                'name' => (string)($workspace['template'] ?? ''),
                'file' => (string)($workspace['file'] ?? ''),
                'path' => (string)($workspace['file_path'] ?? ''),
                'size_human' => (string)($stats['size_human'] ?? ''),
                'lines' => (int)($stats['lines'] ?? 0),
                'checksum' => (string)($stats['checksum'] ?? ''),
                'modified' => (string)($stats['modified'] ?? ''),
                'code_size' => strlen($code),
            ],
            'usage' => array_map(function ($row) {
                return [
                    'name' => (string)($row['name'] ?? ''),
                    'type' => (string)($row['type'] ?? ''),
                    'description' => (string)($row['description'] ?? ''),
                ];
            }, array_slice($usage, 0, 12)),
            'recent_versions' => array_map(function ($row) {
                return [
                    'id' => (string)($row['id'] ?? ''),
                    'timestamp' => (int)($row['timestamp'] ?? 0),
                    'user' => (string)($row['user'] ?? ''),
                    'note' => (string)($row['note'] ?? ''),
                ];
            }, $versions),
            'recent_history' => array_map(function ($row) {
                return [
                    'action' => (string)($row['action'] ?? ''),
                    'timestamp' => (int)($row['timestamp'] ?? 0),
                    'user' => (string)($row['user'] ?? ''),
                ];
            }, $history),
            'related_templates' => array_map(function ($row) {
                return [
                    'name' => (string)($row['name'] ?? ''),
                    'mode' => (string)($row['mode'] ?? 'selected'),
                    'reason' => (string)($row['reason'] ?? ''),
                    'size_human' => (string)($row['size_human'] ?? ''),
                    'lines' => (int)($row['lines'] ?? 0),
                    'path' => (string)($row['path'] ?? ''),
                ];
            }, $relatedContext['templates'] ?? []),
            'related_template_names' => array_values(array_map(function ($row) {
                return (string)($row['name'] ?? '');
            }, $relatedContext['templates'] ?? [])),
            'related_template_summary' => [
                'selected' => array_values($relatedContext['selected'] ?? []),
                'detected' => array_values($relatedContext['detected'] ?? []),
            ],
            'code_excerpt' => $this->summarizeThemeTemplateCode($code),
        ];
    }

    private function summarizeThemeTemplateCode(string $code): string {
        $code = trim($code);
        if ($code === '') {
            return '';
        }
        if (strlen($code) <= 1400) {
            return $code;
        }
        return substr($code, 0, 1400) . "\n\n<!-- truncated for agent context -->";
    }

    private function buildThemeAgentPlan(string $task, string $scope, array $workspace, array $relatedContext = []): array {
        $taskLower = strtolower($task);
        $template = (string)($workspace['template'] ?? '');
        $steps = [
            'Inspect the current template markup and identify the smallest safe section to change.',
            'Review the microservicelets that use this template so layout changes do not break their expectations.',
            'Draft the updated template markup and keep Bootstrap and existing theme conventions intact.',
            'Review the diff and create a version snapshot after approval.',
        ];
        $risks = [];
        $usageNames = array_values(array_filter(array_map(function ($row) {
            return trim((string)($row['name'] ?? ''));
        }, $workspace['usage'] ?? [])));

        if ($scope === 'template_usage' || str_contains($taskLower, 'layout') || str_contains($taskLower, 'header') || str_contains($taskLower, 'footer')) {
            $steps[1] = 'Review the microservicelets that use this template and check whether layout, slots, or shared sections will be affected.';
        }
        if ($scope === 'template_related' || !empty($relatedContext['templates'])) {
            $steps[1] = 'Review the attached related templates and align shared structure, declarations, and naming before patching the current file.';
        }
        if (str_contains($taskLower, 'responsive') || str_contains($taskLower, 'mobile')) {
            $risks[] = 'Template layout changes can introduce mobile regressions if grid and spacing assumptions shift.';
        }
        if (str_contains($taskLower, 'form')) {
            $risks[] = 'Form markup changes can break field names, validation messaging, or submit behavior.';
        }
        if (count($usageNames) > 1) {
            $risks[] = 'This template is shared across multiple microservicelets, so changes may affect more than one surface.';
        }
        if (!empty($relatedContext['templates'])) {
            $risks[] = 'Related templates are being used for context, so naming and structural assumptions should be checked before applying the patch.';
        }

        return [
            'objective' => $task,
            'scope' => $scope,
            'target' => [
                'template' => $template,
                'file' => (string)($workspace['file'] ?? ''),
            ],
            'summary' => sprintf(
                'Work on template `%s` and review downstream usage before changing the shared theme markup.',
                $template
            ),
            'steps' => $steps,
            'suggested_files' => [
                [
                    'path' => (string)($workspace['file_path'] ?? ''),
                    'role' => 'primary',
                    'reason' => 'Current theme template file',
                ],
            ],
            'related_microservicelets' => array_slice($usageNames, 0, 8),
            'related_templates' => array_values(array_map(function ($row) {
                return (string)($row['name'] ?? '');
            }, $relatedContext['templates'] ?? [])),
            'architecture_rules' => $this->getThemeAgentArchitectureGuidance(),
            'risks' => array_values(array_unique($risks)),
            'checks' => [
                'review template diff before apply',
                'confirm shared usage impact',
                'create version snapshot after successful change',
            ],
        ];
    }

    private function generateThemeAgentPatch(string $task, string $scope, array $workspace, array $relatedContext = []): array {
        $context = $this->buildThemeAgentContextPayload($workspace, $relatedContext);
        $currentContent = (string)($workspace['code'] ?? '');
        $template = (string)($workspace['template'] ?? '');
        $taskLower = strtolower($task);
        $isFullTemplateRequest = str_contains($taskLower, 'full template')
            || str_contains($taskLower, 'full html')
            || str_contains($taskLower, 'bootstrap 5 template')
            || str_contains($taskLower, 'modern')
            || str_contains($taskLower, 'default.tpl.php');
        $primaryTemplateContext = $isFullTemplateRequest
            ? $this->buildThemeTemplateContractSummary($currentContent)
            : $currentContent;

        $prompt = "Task:\n{$task}\n\n";
        $prompt .= "Scope: {$scope}\n";
        $prompt .= "Theme Template: {$template}\n\n";
        $prompt .= "RAD Theme Guidance:\n" . $this->getThemeAgentArchitecturePrompt() . "\n\n";
        $contextSummary = json_encode([
            'usage' => $context['usage'] ?? [],
            'recent_versions' => $context['recent_versions'] ?? [],
            'recent_history' => $context['recent_history'] ?? [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $prompt .= "Context Summary:\n" . $contextSummary;
        if (!empty($relatedContext['templates'])) {
            $prompt .= "\n\nRelated template context:\n";
            foreach (array_slice($relatedContext['templates'], 0, 2) as $relatedTemplate) {
                $prompt .= sprintf(
                    "=== %s (%s) ===\n%s\n\n",
                    $relatedTemplate['name'] ?? 'template',
                    $relatedTemplate['mode'] ?? 'selected',
                    $relatedTemplate['code_excerpt'] ?? ''
                );
            }
        }
        $prompt .= "\n\nCurrent template context:\n" . $primaryTemplateContext;
        $prompt .= "\n\nInstructions:\n";
        $prompt .= "- Return the full updated PHP/HTML template file only.\n";
        $prompt .= "- Do not include markdown fences or explanation.\n";
        $prompt .= "- Preserve existing PHP variables and output contracts unless the task explicitly requires changes.\n";
        $prompt .= "- Keep the markup compatible with the existing RAD admin/theme Bootstrap conventions.\n";
        $prompt .= "- Restrict the proposed change to the current template file.\n";
        if ($isFullTemplateRequest) {
            $prompt .= "- Generate a complete fresh template structure while preserving the required PHP declarations and output placeholders.\n";
        }
        if (!empty($relatedContext['templates'])) {
            $prompt .= "- You may read related templates for structure and conventions, but you must patch only the current template file.\n";
        }

        $fallbackPrompt = "Task:\n{$task}\n\n";
        $fallbackPrompt .= "Theme Template: {$template}\n\n";
        $fallbackPrompt .= "Current template context:\n" . $this->buildThemeTemplateContractSummary($currentContent);
        $fallbackPrompt .= "\n\nReturn the full updated PHP/HTML template file only. ";
        $fallbackPrompt .= "Do not include markdown fences, commentary, or explanations. ";
        $fallbackPrompt .= "Preserve existing PHP declarations and output contracts unless the task explicitly requires changes.";

        $fallbackUsed = false;
        try {
            $proposedContent = trim($this->requestThemePatchContent($prompt, $fallbackPrompt));
        } catch (\Throwable $e) {
            if (!$isFullTemplateRequest) {
                throw $e;
            }
            $fallbackUsed = true;
            $this->traceThemeAgentPatch('agentpatch:local_fallback', [
                'template' => $template,
                'message' => $e->getMessage(),
            ]);
            $proposedContent = $this->buildLocalBootstrapThemeTemplate($template, $currentContent, $task);
        }
        if ($proposedContent === '') {
            throw new \RuntimeException('AI did not return template content.');
        }
        if (!str_starts_with($proposedContent, '<?php') && str_contains($currentContent, '<?php')) {
            $proposedContent = "<?php\n" . ltrim($proposedContent);
        }

        $warnings = [];
        if (count($workspace['usage'] ?? []) > 1) {
            $warnings[] = 'This template is shared by multiple microservicelets, so applying the patch will affect all of them.';
        }
        if (!empty($relatedContext['templates'])) {
            $warnings[] = 'Related templates were included as context. The generated patch still modifies only the current template file.';
        }
        if ($fallbackUsed) {
            $warnings[] = 'AI was unavailable for this request, so a local Bootstrap 5 starter template was generated instead.';
        }

        return [
            'summary' => sprintf('Proposed theme template update for `%s` based on the requested task.', $template),
            'warnings' => $warnings,
            'file_path' => (string)($workspace['file_path'] ?? ''),
            'original_content' => $currentContent,
            'proposed_content' => $proposedContent,
            'base_checksum' => sha1($currentContent),
            'task' => $task,
        ];
    }

    private function buildLocalBootstrapThemeTemplate(string $template, string $currentContent, string $task): string {
        $title = ucwords(str_replace(['_', '-'], ' ', $template));
        $description = 'Modern Bootstrap 5 starter layout generated locally because the AI service was unavailable.';
        if (str_contains(strtolower($task), 'dashboard')) {
            $description = 'Modern Bootstrap 5 dashboard starter generated locally because the AI service was unavailable.';
        }

        $hasPrepart = str_contains($currentContent, '$prepart');
        $hasPagepart = str_contains($currentContent, '$pagepart');
        $hasPostpart = str_contains($currentContent, '$postpart');

        $segments = [];
        $segments[] = "<?php";
        $segments[] = "/**";
        $segments[] = " * Template: {$template}";
        $segments[] = " *";
        $segments[] = " * {$description}";
        $segments[] = " */";
        $segments[] = "?>";
        if ($hasPrepart) {
            $segments[] = "<?php echo \$prepart ?? ''; ?>";
        }
        $segments[] = "";
        $segments[] = "<div class=\"container-fluid py-4\">";
        $segments[] = "    <div class=\"row g-4 align-items-start\">";
        $segments[] = "        <div class=\"col-12\">";
        $segments[] = "            <div class=\"card border-0 shadow-sm overflow-hidden\">";
        $segments[] = "                <div class=\"card-body p-4 p-lg-5\">";
        $segments[] = "                    <div class=\"d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3\">";
        $segments[] = "                        <div>";
        $segments[] = "                            <p class=\"text-uppercase small fw-semibold text-primary mb-2\">Theme Template</p>";
        $segments[] = "                            <h1 class=\"h2 mb-2\">{$title}</h1>";
        $segments[] = "                            <p class=\"text-muted mb-0\">{$description}</p>";
        $segments[] = "                        </div>";
        $segments[] = "                        <div class=\"d-flex flex-wrap gap-2\">";
        $segments[] = "                            <button type=\"button\" class=\"btn btn-primary\">Primary Action</button>";
        $segments[] = "                            <button type=\"button\" class=\"btn btn-outline-secondary\">Secondary</button>";
        $segments[] = "                        </div>";
        $segments[] = "                    </div>";
        $segments[] = "                </div>";
        $segments[] = "            </div>";
        $segments[] = "        </div>";
        if ($hasPagepart) {
            $segments[] = "";
            $segments[] = "        <div class=\"col-12\">";
            $segments[] = "            <?php echo \$pagepart ?? ''; ?>";
            $segments[] = "        </div>";
        }
        $segments[] = "";
        $segments[] = "        <div class=\"col-lg-8\">";
        $segments[] = "            <div class=\"card border-0 shadow-sm h-100\">";
        $segments[] = "                <div class=\"card-header bg-white border-0 pt-4 px-4\">";
        $segments[] = "                    <h2 class=\"h5 mb-1\">Main Content</h2>";
        $segments[] = "                    <p class=\"text-muted small mb-0\">Replace this section with your primary template content.</p>";
        $segments[] = "                </div>";
        $segments[] = "                <div class=\"card-body px-4 pb-4\">";
        $segments[] = "                    <div class=\"table-responsive\">";
        $segments[] = "                        <table class=\"table table-striped align-middle mb-0\">";
        $segments[] = "                            <thead class=\"table-light\">";
        $segments[] = "                                <tr>";
        $segments[] = "                                    <th scope=\"col\">Column 1</th>";
        $segments[] = "                                    <th scope=\"col\">Column 2</th>";
        $segments[] = "                                    <th scope=\"col\">Column 3</th>";
        $segments[] = "                                </tr>";
        $segments[] = "                            </thead>";
        $segments[] = "                            <tbody>";
        $segments[] = "                                <?php for (\$i = 1; \$i <= 5; \$i++) : ?>";
        $segments[] = "                                    <tr>";
        $segments[] = "                                        <td>Row <?php echo \$i; ?> - Col 1</td>";
        $segments[] = "                                        <td>Row <?php echo \$i; ?> - Col 2</td>";
        $segments[] = "                                        <td>Row <?php echo \$i; ?> - Col 3</td>";
        $segments[] = "                                    </tr>";
        $segments[] = "                                <?php endfor; ?>";
        $segments[] = "                            </tbody>";
        $segments[] = "                        </table>";
        $segments[] = "                    </div>";
        $segments[] = "                </div>";
        $segments[] = "            </div>";
        $segments[] = "        </div>";
        $segments[] = "";
        $segments[] = "        <div class=\"col-lg-4\">";
        $segments[] = "            <div class=\"card border-0 shadow-sm mb-4\">";
        $segments[] = "                <div class=\"card-body p-4\">";
        $segments[] = "                    <h2 class=\"h6 mb-3\">Quick Summary</h2>";
        $segments[] = "                    <ul class=\"list-unstyled small text-muted mb-0\">";
        $segments[] = "                        <li class=\"mb-2\">Use this area for KPIs, metadata, or status.</li>";
        $segments[] = "                        <li class=\"mb-2\">Keep cards compact and readable on desktop and mobile.</li>";
        $segments[] = "                        <li>Preserve existing PHP placeholders where needed.</li>";
        $segments[] = "                    </ul>";
        $segments[] = "                </div>";
        $segments[] = "            </div>";
        $segments[] = "            <div class=\"card border-0 shadow-sm\">";
        $segments[] = "                <div class=\"card-body p-4\">";
        $segments[] = "                    <h2 class=\"h6 mb-3\">Next Steps</h2>";
        $segments[] = "                    <p class=\"small text-muted mb-0\">Replace these starter blocks with your real page modules, charts, forms, or content sections.</p>";
        $segments[] = "                </div>";
        $segments[] = "            </div>";
        $segments[] = "        </div>";
        $segments[] = "    </div>";
        $segments[] = "</div>";
        if ($hasPostpart) {
            $segments[] = "";
            $segments[] = "<?php echo \$postpart ?? ''; ?>";
        }

        return implode("\n", $segments) . "\n";
    }

    private function requestThemePatchContent(string $prompt, ?string $fallbackPrompt = null): string {
        $aiConfig = $this->runData['config']['ai'] ?? ($this->runData['config']['rad']['ai'] ?? []);
        $maxTokens = (int)($aiConfig['theme_agent_patch_max_tokens'] ?? $aiConfig['agent_patch_max_tokens'] ?? $aiConfig['patch_max_tokens'] ?? 1800);
        $timeout = (int)($aiConfig['theme_agent_patch_timeout'] ?? $aiConfig['agent_patch_timeout'] ?? 18);
        if ($maxTokens < 512) {
            $maxTokens = 512;
        }
        if ($timeout < 8) {
            $timeout = 8;
        }
        $attempts = [
            ['prompt' => $prompt, 'max_tokens' => $maxTokens],
        ];
        if ($fallbackPrompt !== null && trim($fallbackPrompt) !== '') {
            $attempts[] = ['prompt' => $fallbackPrompt, 'max_tokens' => max($maxTokens, 1600)];
        }

        $lastError = null;
        foreach ($attempts as $index => $attempt) {
            try {
                $this->traceThemeAgentPatch('agentpatch:ai_attempt_start', [
                    'attempt' => $index + 1,
                    'prompt_size' => strlen((string)$attempt['prompt']),
                    'max_tokens' => (int)$attempt['max_tokens'],
                    'timeout' => $timeout,
                ]);
                $response = $this->getAiAssistClient((int)$attempt['max_tokens'], $timeout, 'coding', 'full')->getSuggestion((string)$attempt['prompt']);
                $response = trim((string)$response);
                if ($response === '') {
                    $lastError = 'AI returned an empty response.';
                    $this->traceThemeAgentPatch('agentpatch:ai_attempt_empty', ['attempt' => $index + 1]);
                    if ($this->errorHandler) {
                        $this->errorHandler->logError('Theme patch AI request returned empty response on attempt ' . ($index + 1) . '.');
                    }
                    continue;
                }
                $response = preg_replace('/^```[a-zA-Z0-9]*\s*/', '', $response);
                $response = preg_replace('/```$/', '', $response);
                $this->traceThemeAgentPatch('agentpatch:ai_attempt_success', [
                    'attempt' => $index + 1,
                    'response_size' => strlen((string)$response),
                ]);
                return ltrim((string)$response);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $this->traceThemeAgentPatch('agentpatch:ai_attempt_error', [
                    'attempt' => $index + 1,
                    'message' => $e->getMessage(),
                ]);
                if ($this->errorHandler) {
                    $this->errorHandler->logError('Theme patch AI request failed on attempt ' . ($index + 1) . ': ' . $e->getMessage());
                }
            }
        }

        if ($lastError !== null && stripos($lastError, 'empty response') === false) {
            throw new \RuntimeException('AI service is currently unavailable for template patch generation.');
        }
        throw new \RuntimeException('AI returned an empty response.');
    }

    private function buildThemeTemplateContractSummary(string $content): string {
        $content = trim($content);
        if ($content === '') {
            return 'No current template content.';
        }

        $lines = preg_split("/\\r\\n|\\n|\\r/", $content) ?: [];
        $summary = [];
        $phpLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (str_contains($trimmed, '$prepart') || str_contains($trimmed, '$pagepart') || str_contains($trimmed, '$postpart')) {
                $phpLines[] = $trimmed;
                continue;
            }
            if (str_starts_with($trimmed, '<?php') || str_starts_with($trimmed, '?>')) {
                $phpLines[] = $trimmed;
                continue;
            }
            if (preg_match('/<(div|section|header|main|footer|table|thead|tbody|form|nav|article|aside)\\b/i', $trimmed)) {
                $summary[] = $trimmed;
            }
            if (count($summary) >= 12) {
                break;
            }
        }

        $parts = [];
        if (!empty($phpLines)) {
            $parts[] = "Required PHP declarations and placeholders:\n" . implode("\n", array_slice(array_unique($phpLines), 0, 10));
        }
        if (!empty($summary)) {
            $parts[] = "Current structural markers:\n" . implode("\n", $summary);
        }
        if (empty($parts)) {
            $parts[] = substr($content, 0, 1200);
        }

        return implode("\n\n", $parts);
    }

    private function traceThemeAgentPatch(string $stage, array $meta = []): void {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $stage;
        if (!empty($meta)) {
            $line .= ' ' . json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        $line .= PHP_EOL;
        @file_put_contents('/tmp/rad-theme-agentpatch-trace.log', $line, FILE_APPEND);
    }

    private function normalizeRelatedTemplateSelection($value): array {
        if (!is_array($value)) {
            if (is_string($value) && trim($value) !== '') {
                $value = [$value];
            } else {
                return [];
            }
        }
        $selected = [];
        foreach ($value as $item) {
            $name = $this->sanitizeTemplateName((string)$item);
            if ($name === '') {
                continue;
            }
            $selected[$name] = $name;
        }
        return array_values($selected);
    }

    private function resolveThemeRelatedContext(string $currentTemplate, string $task, string $scope, array $selectedTemplates = []): array {
        $available = $this->listThemeTemplates();
        $availableLookup = array_fill_keys($available, true);
        $selected = [];
        foreach ($selectedTemplates as $name) {
            if ($name !== $currentTemplate && isset($availableLookup[$name])) {
                $selected[$name] = $name;
            }
        }

        $detected = [];
        $shouldDetect = $scope === 'template_related' || !empty($selected) || trim($task) !== '';
        if ($shouldDetect) {
            foreach ($available as $name) {
                if ($name === $currentTemplate) {
                    continue;
                }
                if (preg_match('/(?<![a-z0-9_])' . preg_quote($name, '/') . '(?![a-z0-9_])/i', $task)) {
                    $detected[$name] = $name;
                }
            }
        }

        $resolvedTemplates = [];
        foreach (array_unique(array_merge(array_values($selected), array_values($detected))) as $name) {
            $filePath = $this->getTemplateFilePath($name);
            if (!is_file($filePath)) {
                continue;
            }
            $stats = $this->collectTemplateStats($filePath);
            $content = (string)(file_get_contents($filePath) ?: '');
            $resolvedTemplates[] = [
                'name' => $name,
                'path' => $filePath,
                'mode' => isset($selected[$name]) ? 'selected' : 'detected',
                'reason' => isset($selected[$name]) ? 'Attached in the workspace' : 'Detected from the prompt text',
                'size_human' => (string)($stats['size_human'] ?? ''),
                'lines' => (int)($stats['lines'] ?? 0),
                'code_excerpt' => $this->summarizeThemeTemplateCode($content),
            ];
        }

        return [
            'selected' => array_values($selected),
            'detected' => array_values(array_diff_key($detected, $selected)),
            'templates' => $resolvedTemplates,
        ];
    }

    private function getThemeAgentArchitectureGuidance(): array {
        return [
            'editable_files' => [
                'rad/theme/*.tpl.php',
            ],
            'repo_layout' => [
                'rad/theme/' => 'Theme templates',
                'public_html/assets/' => 'Public web assets',
                'rad/ms/{ms_name}/' => 'Microservicelet code that may render the template',
            ],
            'rules' => [
                'Keep changes inside the current theme template file unless explicitly required otherwise.',
                'Preserve template variables and PHP output contracts already used by consuming pages.',
                'When markup changes are shared, consider all microservicelets listed in template usage.',
                'Use existing Bootstrap/theme classes where possible instead of introducing unrelated patterns.',
            ],
        ];
    }

    private function getThemeAgentArchitecturePrompt(): string {
        return json_encode($this->getThemeAgentArchitectureGuidance(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function resetOpcacheInDev(): void {
        $debugFlag = strtoupper((string)($this->runData['config']['sys']['dev_debug_flag']
            ?? $this->runData['config']['app']['dev_debug_flag']
            ?? 'N'));
        if ($debugFlag !== 'Y') {
            return;
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function defaultTemplateStub(string $name): string {
        return <<<PHP
<?php
/**
 * Template: {$name}
 */
?>
<div class="container py-4">
    <h2 class="mb-3">{$name}</h2>
    <p class="text-muted">This is a newly created template. Replace this markup with your layout.</p>
</div>

PHP;
    }

    private function getTemplateVersionRoot(): string {
        $base = $this->runData['config']['dir']['rad'] ?? dirname(__DIR__, 2);
        return rtrim($base, '/') . '/data/versions/theme';
    }

    private function getTemplateVersionDir(string $template): string {
        return $this->getTemplateVersionRoot() . '/' . $template;
    }

    private function ensureDirectoryExists(string $path): void {
        if ($path === '') {
            return;
        }
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
    }

    private function createTemplateVersion(string $template, string $content, array $meta = []): void {
        if ($template === '') {
            return;
        }
        $dir = $this->getTemplateVersionDir($template);
        $this->ensureDirectoryExists($dir);
        $timestamp = time();
        $versionId = date('YmdHis', $timestamp) . '_' . substr(sha1($content . microtime(true)), 0, 8);
        $fileName = $versionId . '.tpl.php';
        @file_put_contents($dir . '/' . $fileName, $content);

        $entry = [
            'id' => $versionId,
            'timestamp' => $timestamp,
            'user' => $meta['user'] ?? $this->getCurrentUserLabel(),
            'size' => strlen($content),
            'size_human' => $this->formatBytes(strlen($content)),
            'checksum' => sha1($content),
            'note' => $meta['note'] ?? ($meta['channel'] ?? 'Saved via editor'),
            'file' => $fileName,
        ];

        $entries = $this->readTemplateManifest($template);
        $entries[] = $entry;
        usort($entries, function ($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });
        if (count($entries) > self::MAX_TEMPLATE_VERSIONS) {
            $excess = array_slice($entries, self::MAX_TEMPLATE_VERSIONS);
            foreach ($excess as $oldEntry) {
                $oldFile = $dir . '/' . ($oldEntry['file'] ?? '');
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
            $entries = array_slice($entries, 0, self::MAX_TEMPLATE_VERSIONS);
        }

        $this->writeTemplateManifest($template, $entries);
    }

    private function readTemplateManifest(string $template): array {
        $manifest = $this->getTemplateVersionDir($template) . '/manifest.json';
        if (!is_file($manifest)) {
            return [];
        }
        $json = file_get_contents($manifest);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function writeTemplateManifest(string $template, array $entries): void {
        $manifest = $this->getTemplateVersionDir($template) . '/manifest.json';
        $this->ensureDirectoryExists(dirname($manifest));
        file_put_contents($manifest, json_encode(array_values($entries), JSON_PRETTY_PRINT));
    }

    private function loadTemplateVersions(string $template, int $limit = 10): array {
        $entries = $this->readTemplateManifest($template);
        usort($entries, function ($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });
        return array_slice($entries, 0, $limit);
    }

    private function getVersionEntry(string $template, string $versionId): ?array {
        $entries = $this->readTemplateManifest($template);
        foreach ($entries as $entry) {
            if (($entry['id'] ?? '') === $versionId) {
                return $entry;
            }
        }
        return null;
    }

    private function getVersionFilePath(string $template, string $versionId): ?string {
        $dir = $this->getTemplateVersionDir($template);
        $path = $dir . '/' . $versionId . '.tpl.php';
        return is_file($path) ? $path : null;
    }

    /**
     * Find file bootstrap icons based on extension
     */
    private function getFileIcon($file_type) {
        switch($file_type) {
            case 'folder':
                return '<i class="bi bi-folder"></i>';
            case 'png':
            case 'jpg':
            case 'jpeg':
            case 'gif':
            case 'svg':
                return '<i class="bi bi-image"></i>';
            case 'pdf':
                return '<i class="bi bi-file-earmark-pdf"></i>';
            case 'doc':
            case 'docx':
                return '<i class="bi bi-file-earmark-word"></i>';
            case 'xls':
            case 'xlsx':
                return '<i class="bi bi-file-earmark-excel"></i>';
            case 'ppt':
            case 'pptx':
                return '<i class="bi bi-file-earmark-ppt"></i>';
            case 'zip':
            case 'rar':
            case 'tar':
            case 'gz':
                return '<i class="bi bi-file-earmark-zip"></i>';
            case 'txt':
                return '<i class="bi bi-file-earmark-text"></i>';
            default:
                return '<i class="bi bi-file-earmark"></i>';
        }
    }

    private function renderDiff(array $oldLines, array $newLines): array {
        $diff = [];
        $i = 0;
        $j = 0;
        $oldCount = count($oldLines);
        $newCount = count($newLines);

        while ($i < $oldCount || $j < $newCount) {
            $oldLine = $i < $oldCount ? $oldLines[$i] : null;
            $newLine = $j < $newCount ? $newLines[$j] : null;

            if ($oldLine !== null && $newLine !== null && $oldLine === $newLine) {
                $diff[] = [
                    'type' => 'equal',
                    'old_line' => $i + 1,
                    'new_line' => $j + 1,
                    'old' => $oldLine,
                    'new' => $newLine,
                ];
                $i++;
                $j++;
                continue;
            }

            if ($oldLine !== null && $j + 1 < $newCount && $oldLine === $newLines[$j + 1]) {
                $diff[] = [
                    'type' => 'insert',
                    'old_line' => null,
                    'new_line' => $j + 1,
                    'old' => '',
                    'new' => $newLine,
                ];
                $j++;
                continue;
            }

            if ($newLine !== null && $i + 1 < $oldCount && $newLine === $oldLines[$i + 1]) {
                $diff[] = [
                    'type' => 'delete',
                    'old_line' => $i + 1,
                    'new_line' => null,
                    'old' => $oldLine,
                    'new' => '',
                ];
                $i++;
                continue;
            }

            $diff[] = [
                'type' => 'replace',
                'old_line' => $oldLine !== null ? $i + 1 : null,
                'new_line' => $newLine !== null ? $j + 1 : null,
                'old' => $oldLine ?? '',
                'new' => $newLine ?? '',
            ];
            if ($oldLine !== null) {
                $i++;
            }
            if ($newLine !== null) {
                $j++;
            }
        }

        return $diff;
    }

    private function prepareDuplicateContext(string $sourceTemplate, string $sourcePath): array {
        $stats = $this->collectTemplateStats($sourcePath);
        $suggestedName = $this->suggestTemplateName($sourceTemplate);
        $this->runData['route']['h1'] = 'Duplicate Template: <code>' . $sourceTemplate . '</code>';
        $this->runData['route']['meta_title'] = 'Duplicate Template - ' . $sourceTemplate;
        $this->runData['route']['backlink'] = $this->runData['route']['rad_admin_url'] . '/theme/viewone/' . $sourceTemplate;
        $this->runData['data']['duplicate'] = [
            'source' => $sourceTemplate,
            'stats' => $stats,
            'suggested_name' => $suggestedName,
        ];
        return $this->runData;
    }

    private function suggestTemplateName(string $sourceTemplate): string {
        $counter = 1;
        $base = $sourceTemplate . '_copy';
        $candidate = $base;
        while (file_exists($this->getTemplateFilePath($candidate))) {
            $counter++;
            $candidate = $base . $counter;
            if ($counter > 50) {
                break;
            }
        }
        return $candidate;
    }
}

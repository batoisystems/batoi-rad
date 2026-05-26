<?php
namespace Core\Sys;

class View {
    private $runData = [];

    public function __construct() {
        $this->runData = [];
    }

    public function render(array $runData) {
        $this->runData = $runData;
        $this->ensureRunDataDefaults();

        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $responseType = $this->negotiateResponseType($accept);

        if ($responseType === 'json') {
            header('Content-Type: application/json');
            echo json_encode($this->runData);
            return;
        }
        if ($responseType === 'xml') {
            // convert $runData to XML and output
            return;
        }

        $isRadAdmin = ($this->runData['route']['pathparts'][0] ?? '') === 'rad-admin';
        if ($isRadAdmin) {
            $this->renderAdminHTML();
        } else {
            $this->renderHTML();
        }
    }

    public function renderJSON() {
        header('Content-Type: application/json');
        echo json_encode($this->runData['data'] ?? []);
    }

    /**
     * Render the HTML
     */
    private function renderHTML() {
        $baseUrl = $this->runData['config']['sys']['base_url'] = $this->resolveBaseUrl($this->runData['config']['sys']['base_url'] ?? '');
        $pathFull = ltrim($this->runData['route']['path_full'] ?? '', '/');
        $this->runData['route']['url'] = $pathFull === '' ? $baseUrl : rtrim($baseUrl, '/') . '/' . $pathFull;
        $this->runData['route']['assets_url'] = rtrim($baseUrl, '/') . '/assets';

        if( isset($this->runData['route']['error_status']) && $this->runData['route']['error_status'] == 'error' ) {
            $templateFile = $this->runData['config']['dir']['theme'] . '/error-page.tpl.php';
        } else {
            $tplName = $this->runData['ms']['tpl_name'] ?? 'default';
            $templateFile = rtrim($this->runData['config']['dir']['theme'], '/') . '/' . $tplName . '.tpl.php';
        }

        if(file_exists($templateFile)) {
            include $templateFile;
        } else {
            $this->renderMissingTemplateNotice('Theme template not found', $templateFile);
        }
    }

    /**
     * Render the admin HTML
     */
    private function renderAdminHTML() {
        $tplName = $this->runData['ms']['tpl_name'] ?? 'rad-admin';
        $adminDir = rtrim($this->runData['config']['dir']['admin'], '/');
        $templateFile = $adminDir . '/' . $tplName . '.tpl.php';

        if (!file_exists($templateFile)) {
            $defaultAdminTemplate = $adminDir . '/rad-admin.tpl.php';

            if ($tplName !== 'rad-admin' && file_exists($defaultAdminTemplate)) {
                $templateFile = $defaultAdminTemplate;
            } else {
                // fallback to theme directory for shared templates
                $themeDir = rtrim($this->runData['config']['dir']['theme'] ?? '', '/');
                $themeFallback = $themeDir ? $themeDir . '/' . $tplName . '.tpl.php' : '';

                if ($themeFallback && file_exists($themeFallback)) {
                    $templateFile = $themeFallback;
                } else {
                    $this->renderMissingTemplateNotice(
                        'RAD Admin template not found',
                        $templateFile,
                        $this->runData['config']['dir']['admin'] ?? ''
                    );
                    return;
                }
            }
        }

        include $templateFile;
    }

    /**
     * Include Prepart, pagepart and postpart files
     */
    private function includePart($parttype) {
        $msName = $this->runData['ms']['name'] ?? null;
        $routeId = $this->runData['route']['id'] ?? null;
        $branch = $this->runData['route']['branch'] ?? 'live';
        $branchService = new \Core\Sys\BranchService(
            $this->runData['db'],
            $this->runData['config'] ?? [],
            $this->runData['entity'] ?? [],
            $this->runData['request'] ?? null
        );

        if ($parttype == 'pre') {
            if ($msName && $routeId) {
                $routeKey = $this->runData['route']['file_key'] ?? (string)$routeId;
                $fileWithPath = $branchService->getRouteFilePath($msName, $routeKey, $parttype . 'part', $branch, true);
                if(file_exists($fileWithPath)) {
                    include $fileWithPath;
                }
            }
        }
        else if ($parttype == 'page') {
            if ( isset($this->runData['route']['content']) && ($this->runData['route']['content_tpl'] ?? '') == 'N' ) {
                if (isset($this->runData['route']['content'])) {
                    print $this->runData['route']['content'];
                }
            }
            else if ($msName && $routeId) {
                $routeKey = $this->runData['route']['file_key'] ?? (string)$routeId;
                $fileWithPath = $branchService->getRouteFilePath($msName, $routeKey, $parttype . 'part', $branch, true);
                if(file_exists($fileWithPath)) {
                    include $fileWithPath;
                }
            }
        }
        else if ($parttype == 'post') {
            if ($msName && $routeId) {
                $routeKey = $this->runData['route']['file_key'] ?? (string)$routeId;
                $fileWithPath = $branchService->getRouteFilePath($msName, $routeKey, $parttype . 'part', $branch, true);
                if(file_exists($fileWithPath)) {
                    include $fileWithPath;
                }
            }
        }
        else {
            throw new \Exception('Invalid part type', 404);
        }
    }

    private function ensureRunDataDefaults(): void {
        if (!isset($this->runData['route']) || !is_array($this->runData['route'])) {
            $this->runData['route'] = [];
        }
        $defaults = [
            'pathparts' => [],
            'path_full' => '',
            'meta_title' => '',
            'meta_description' => '',
            'h1' => '',
        ];
        foreach ($defaults as $key => $value) {
            if (!isset($this->runData['route'][$key])) {
                $this->runData['route'][$key] = $value;
            }
        }

        if (!isset($this->runData['config']) || !is_array($this->runData['config'])) {
            $this->runData['config'] = ['sys' => [], 'dir' => []];
        }
        if (!isset($this->runData['config']['sys'])) {
            $this->runData['config']['sys'] = [];
        }
        if (!isset($this->runData['config']['dir'])) {
            $this->runData['config']['dir'] = [];
        }
    }

    private function resolveBaseUrl(string $configured): string {
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $scheme = 'http';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0];
        } elseif (!empty($_SERVER['HTTP_X_REAL_PROTO'])) {
            $scheme = $_SERVER['HTTP_X_REAL_PROTO'];
        } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }

        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        if (strpos($host, ',') !== false) {
            $host = trim(explode(',', $host)[0]);
        }

        $port = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? ($_SERVER['SERVER_PORT'] ?? '');
        $port = is_string($port) ? trim($port) : $port;
        $includePort = $port && !in_array((int)$port, [80, 443], true) && strpos($host, ':') === false;
        $authority = $includePort ? "{$host}:{$port}" : $host;

        return strtolower($scheme) . '://' . $authority;
    }

    private function renderMissingTemplateNotice(string $title, string $templateFile, string $searchDir = ''): void {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeTemplate = htmlspecialchars($templateFile, ENT_QUOTES, 'UTF-8');
        $safeDir = htmlspecialchars($searchDir ?: dirname($templateFile), ENT_QUOTES, 'UTF-8');
        $message = "{$title}: {$templateFile}";
        error_log($message);

        http_response_code(500);
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$safeTitle}</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f8f9fa;margin:0;padding:40px;color:#212529;}
        .box{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:24px;max-width:600px;margin:40px auto;box-shadow:0 2px 6px rgba(0,0,0,0.08);}
        .box h1{font-size:1.5rem;margin-bottom:12px;}
        .box p{margin:0 0 8px;}
        .box code{color:#c7254e;background:#f9f2f4;padding:2px 4px;border-radius:4px;}
    </style>
</head>
<body>
    <div class="box">
        <h1>{$safeTitle}</h1>
        <p>The template <code>{$safeTemplate}</code> could not be found.</p>
        <p>Expected location: <code>{$safeDir}</code>.</p>
    </div>
</body>
</html>
HTML;
    }

    private function negotiateResponseType(string $accept): string {
        if ($accept === '') {
            return 'html';
        }

        if (strpos($accept, 'text/html') !== false || strpos($accept, '*/*') !== false) {
            return 'html';
        }

        if (strpos($accept, 'application/json') !== false) {
            return 'json';
        }

        if (strpos($accept, 'text/xml') !== false || strpos($accept, 'application/xml') !== false) {
            return 'xml';
        }

        return 'html';
    }
}

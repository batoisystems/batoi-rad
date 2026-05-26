<?php
namespace RadAdmin;

use Core\Sys\PrivilegeService;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class Observability {
    private array $runData = [];
    private $db;
    private PrivilegeService $priv;
    private array $msCache = [];
    private array $routeCache = [];
    private array $controllerCache = [];

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->priv = new PrivilegeService($runData['config'] ?? [], $runData['entity'] ?? []);
    }

    public function findcode() {
        if (!$this->priv->can('idm_view')) {
            throw new \Exception('Access denied.', 403);
        }

        $request = $this->runData['request'];
        $query = trim((string)($request->get['q'] ?? ''));
        $caseSensitive = !empty($request->get['cs']);

        $scopeParamsPresent = isset($request->get['scope_ms'])
            || isset($request->get['scope_theme'])
            || isset($request->get['scope_assets']);
        $scopes = [
            'ms' => $scopeParamsPresent ? !empty($request->get['scope_ms']) : true,
            'theme' => $scopeParamsPresent ? !empty($request->get['scope_theme']) : true,
            'assets' => $scopeParamsPresent ? !empty($request->get['scope_assets']) : true,
        ];

        $this->runData['route']['h1'] = 'Find Code';
        $this->runData['route']['meta_title'] = 'Find Code';
        $this->runData['route']['breadcrumb'] = [
            'Observability' => null,
            'Find Code' => '',
        ];

        $results = [];
        $stats = [
            'files_scanned' => 0,
            'matches' => 0,
            'files_with_matches' => 0,
            'duration_ms' => 0,
            'limit_reached' => false,
            'limits' => [
                'max_files' => 3000,
                'max_matches' => 2000,
                'max_file_bytes' => 1024 * 1024,
                'max_line_length' => 4000,
                'max_matches_per_file' => 200,
            ],
        ];

        if ($query !== '') {
            $start = microtime(true);
            $results = $this->searchCodebase($query, $caseSensitive, $scopes, $stats);
            $stats['files_with_matches'] = count($results);
            $stats['duration_ms'] = (int)round((microtime(true) - $start) * 1000);
        }

        $this->runData['data']['query'] = $query;
        $this->runData['data']['case_sensitive'] = $caseSensitive;
        $this->runData['data']['scopes'] = $scopes;
        $this->runData['data']['results'] = $results;
        $this->runData['data']['stats'] = $stats;

        if ($query === '') {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Search for any code string, variable, or tag across microservicelets, theme templates, and assets.';
        }

        return $this->runData;
    }

    private function searchCodebase(string $query, bool $caseSensitive, array $scopes, array &$stats): array {
        $roots = [];
        $msDir = rtrim($this->runData['config']['dir']['ms'] ?? '', '/');
        $themeDir = rtrim($this->runData['config']['dir']['theme'] ?? '', '/');
        $assetsDir = rtrim($this->runData['config']['dir']['assets'] ?? '', '/');

        if ($scopes['ms'] && $msDir !== '' && is_dir($msDir)) {
            $roots[] = ['root' => $msDir, 'kind' => 'ms'];
        }
        if ($scopes['theme'] && $themeDir !== '' && is_dir($themeDir)) {
            $roots[] = ['root' => $themeDir, 'kind' => 'theme'];
        }
        if ($scopes['assets'] && $assetsDir !== '' && is_dir($assetsDir)) {
            $roots[] = ['root' => $assetsDir, 'kind' => 'assets'];
        }

        $textExtensions = array_flip([
            'php', 'js', 'css', 'scss', 'less', 'html', 'htm', 'md', 'txt', 'json', 'xml', 'yml', 'yaml', 'svg'
        ]);
        $assetExtensions = array_flip(['css', 'js']);
        $skipDirs = ['node_modules', 'vendor', '.git', '.idea', '.vscode', 'cache', 'tmp', 'log', 'logs'];

        $results = [];
        $maxFiles = $stats['limits']['max_files'];
        $maxMatches = $stats['limits']['max_matches'];
        $maxFileBytes = $stats['limits']['max_file_bytes'];
        $maxLineLength = $stats['limits']['max_line_length'];
        $maxMatchesPerFile = $stats['limits']['max_matches_per_file'];

        foreach ($roots as $root) {
            if ($stats['limit_reached']) {
                break;
            }
            $rootPath = $root['root'];
            $kind = $root['kind'];

            $iterator = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS),
                    function ($current) use ($skipDirs) {
                        if ($current->isDir()) {
                            $name = $current->getFilename();
                            if ($name[0] === '.' || in_array($name, $skipDirs, true)) {
                                return false;
                            }
                        }
                        return true;
                    }
                )
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    continue;
                }
                if ($stats['files_scanned'] >= $maxFiles || $stats['matches'] >= $maxMatches) {
                    $stats['limit_reached'] = true;
                    break;
                }

                $filePath = $file->getPathname();
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if ($kind === 'assets') {
                    if (!isset($assetExtensions[$extension])) {
                        continue;
                    }
                } else if (!isset($textExtensions[$extension])) {
                    continue;
                }

                if ($file->getSize() > $maxFileBytes) {
                    continue;
                }

                $stats['files_scanned']++;
                $relative = ltrim(str_replace($rootPath, '', $filePath), '/');

                $matchesForFile = [];
                $lineNumber = 0;
                $handle = new \SplFileObject($filePath, 'r');
                while (!$handle->eof()) {
                    $line = $handle->fgets();
                    $lineNumber++;
                    if ($line === '') {
                        continue;
                    }
                    if (strlen($line) > $maxLineLength) {
                        $line = substr($line, 0, $maxLineLength);
                    }
                    $hasMatch = $caseSensitive
                        ? (strpos($line, $query) !== false)
                        : (stripos($line, $query) !== false);
                    if (!$hasMatch) {
                        continue;
                    }

                    $matchesForFile[] = [
                        'line' => $lineNumber,
                        'text' => rtrim($line, "\r\n"),
                    ];
                    $stats['matches']++;

                    if (count($matchesForFile) >= $maxMatchesPerFile || $stats['matches'] >= $maxMatches) {
                        break;
                    }
                }

                if (!empty($matchesForFile)) {
                    $meta = $this->buildFileMeta($filePath, $relative, $kind);
                    $results[] = [
                        'path' => $filePath,
                        'relative' => $relative,
                        'type' => $meta['type'],
                        'edit_url' => $meta['edit_url'],
                        'edit_label' => $meta['edit_label'],
                        'matches' => $matchesForFile,
                    ];
                }

                if ($stats['matches'] >= $maxMatches) {
                    $stats['limit_reached'] = true;
                    break;
                }
            }
        }

        return $results;
    }

    private function buildFileMeta(string $filePath, string $relative, string $kind): array {
        $radAdminUrl = rtrim($this->runData['route']['rad_admin_url'] ?? '', '/');
        $meta = [
            'type' => 'file',
            'edit_url' => null,
            'edit_label' => null,
        ];

        if ($kind === 'ms') {
            $segments = explode('/', $relative);
            $msName = $segments[0] ?? '';
            $baseName = basename($filePath);
            if (preg_match('/^route\\.([^.]+)\\./', $baseName, $match)) {
                $routeKey = $match[1];
                $meta['type'] = 'route';
                $meta['edit_url'] = $this->resolveRouteEditUrl($msName, $routeKey);
                $meta['edit_label'] = $meta['edit_url'] ? 'Edit route code' : null;
                return $meta;
            }
            if (preg_match('/^(.+)\\.cls\\.php$/', $baseName, $match)) {
                $controllerName = $match[1];
                $meta['type'] = 'controller';
                $meta['edit_url'] = $this->resolveControllerEditUrl($msName, $controllerName);
                $meta['edit_label'] = $meta['edit_url'] ? 'Edit controller code' : null;
                return $meta;
            }
            $meta['type'] = 'microservice-file';
            return $meta;
        }

        if ($kind === 'theme') {
            if (substr($filePath, -8) === '.tpl.php') {
                $template = basename($filePath, '.tpl.php');
                $meta['type'] = 'theme-template';
                $meta['edit_url'] = $radAdminUrl . '/theme/edittemplate/' . urlencode($template);
                $meta['edit_label'] = 'Edit template';
                return $meta;
            }
            $meta['type'] = 'theme-file';
            return $meta;
        }

        if ($kind === 'assets') {
            $meta['type'] = 'asset';
            $meta['edit_url'] = $radAdminUrl . '/uiassets/editfile?path=' . rawurlencode($relative);
            $meta['edit_label'] = 'Edit asset';
            return $meta;
        }

        return $meta;
    }

    private function resolveRouteEditUrl(string $msName, string $routeKey): ?string {
        if ($msName === '') {
            return null;
        }
        $msRow = $this->resolveMicroservice($msName);
        if (!$msRow) {
            return null;
        }
        $msId = (int)$msRow['id'];
        $msUid = $msRow['uid'] ?? '';
        if ($msUid === '') {
            return null;
        }

        $cacheKey = $msId . ':' . $routeKey;
        if (array_key_exists($cacheKey, $this->routeCache)) {
            $routeUid = $this->routeCache[$cacheKey];
            return $routeUid ? $this->runData['route']['rad_admin_url'] . '/route/code/' . $routeUid . '/' . $msUid : null;
        }

        $routeRows = [];
        if (ctype_digit($routeKey)) {
            $routeRows = $this->db->select('s_msroute', ['id' => (int)$routeKey, 's_ms_id' => $msId], true);
        } else {
            $routeRows = $this->db->select('s_msroute', ['s_name' => $routeKey, 's_ms_id' => $msId], true);
        }

        $routeUid = '';
        if (!empty($routeRows)) {
            $routeUid = $routeRows[0]['uid'] ?? (string)($routeRows[0]['id'] ?? '');
        }
        $this->routeCache[$cacheKey] = $routeUid ?: null;

        if ($routeUid === '') {
            return null;
        }
        return $this->runData['route']['rad_admin_url'] . '/route/code/' . $routeUid . '/' . $msUid;
    }

    private function resolveControllerEditUrl(string $msName, string $controllerName): ?string {
        if ($msName === '') {
            return null;
        }
        $msRow = $this->resolveMicroservice($msName);
        if (!$msRow) {
            return null;
        }
        $msId = (int)$msRow['id'];
        $msUid = $msRow['uid'] ?? '';
        if ($msUid === '') {
            return null;
        }

        $controllerSlug = strtolower($controllerName);
        $cacheKey = $msId . ':' . $controllerSlug;
        if (array_key_exists($cacheKey, $this->controllerCache)) {
            return $this->controllerCache[$cacheKey];
        }

        $controllerRows = $this->db->select('s_mscontroller', [
            's_ms_id' => $msId,
            's_name' => $controllerSlug,
        ], true);

        if (empty($controllerRows)) {
            $this->controllerCache[$cacheKey] = null;
            return null;
        }

        $url = $this->runData['route']['rad_admin_url'] . '/controller/code/' . $msUid . '/' . $controllerSlug;
        $this->controllerCache[$cacheKey] = $url;
        return $url;
    }

    private function resolveMicroservice(string $msName): ?array {
        if (isset($this->msCache[$msName])) {
            return $this->msCache[$msName];
        }
        $rows = $this->db->select('s_ms', ['s_name' => $msName], true);
        $this->msCache[$msName] = $rows[0] ?? null;
        return $this->msCache[$msName];
    }
}

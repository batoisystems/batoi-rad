<?php
namespace RadAdmin;

use Core\Sys\VendorLibraryService;
use Core\Sys\TimeHelper;
use RuntimeException;

class Vendor {
    private const SERVICE_TYPE_ID = '3';
    private $runData = [];
    private $db;
    private $errorHandler;
    private $service;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'];
    }

    private function getService(): VendorLibraryService {
        if (!$this->service instanceof VendorLibraryService) {
            $this->service = new VendorLibraryService($this->runData['config']);
        }
        return $this->service;
    }

    /**
     * Check whether a folder looks like a PHP package (composer.json or autoload.php present).
     */
    private function isPackageFolder(string $path): bool {
        return is_dir($path) && (is_file($path . '/composer.json') || is_file($path . '/autoload.php') || is_file($path . '/package.json'));
    }

    /**
     * Try to read composer.json "name" to propose a canonical handle.
     */
    private function getComposerHandle(string $path): ?string {
        $composerFile = $path . '/composer.json';
        if (!is_file($composerFile)) {
            return null;
        }
        $json = @file_get_contents($composerFile);
        if (!$json) {
            return null;
        }
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['name'])) {
            return null;
        }
        return $this->sanitizeHandle((string)$data['name']);
    }

    public function view() {
        $filters = [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
        ];

        $records = $this->db->select('s_vendor', ['s_service_type_id' => self::SERVICE_TYPE_ID], true, ['s_title' => 'ASC']);
        $service = $this->getService();
        $installed = 0;
        $missing = 0;
        $catalogByHandle = [];
        foreach ($records as &$record) {
            $fs = $service->describeFilesystem($record['s_handle'], $record);
            $record['filesystem'] = $fs;
            if ($fs['installed']) {
                $installed++;
            } else {
                $missing++;
            }
            $catalogByHandle[strtolower($record['s_handle'] ?? '')] = (int)$record['id'];
            if (!empty($record['s_install_path'])) {
                $catalogByHandle[strtolower(basename($record['s_install_path']))] = (int)$record['id'];
            }
        }
        unset($record);

        if ($filters['q'] !== '') {
            $needle = strtolower($filters['q']);
            $records = array_values(array_filter($records, function ($row) use ($needle) {
                $blob = strtolower(($row['s_title'] ?? '') . ' ' . ($row['s_summary'] ?? '') . ' ' . ($row['s_category'] ?? '') . ' ' . ($row['s_handle'] ?? ''));
                return strpos($blob, $needle) !== false;
            }));
        }

        // Filesystem scan for folders under rad/vendor
        $fsFolders = [];
        $uncataloged = [];
        $vendorRoot = rtrim($this->runData['config']['dir']['vendor'] ?? dirname(__DIR__, 2) . '/vendor', '/');
        if (is_dir($vendorRoot)) {
            $addFsEntry = function (string $name, string $path, ?string $suggestedHandle = null) use (&$fsFolders, &$uncataloged, $catalogByHandle) {
                $candidates = [];
                if (!empty($suggestedHandle)) {
                    $candidates[] = $suggestedHandle;
                }
                $candidates[] = $name;
                $candidates[] = basename($path);
                if ($suggestedHandle && str_contains($suggestedHandle, '/')) {
                    $parts = explode('/', $suggestedHandle);
                    $candidates[] = end($parts);
                }
                $catalogId = null;
                foreach ($candidates as $cand) {
                    $key = strtolower($cand);
                    if (isset($catalogByHandle[$key])) {
                        $catalogId = $catalogByHandle[$key];
                        break;
                    }
                }
                $entry = [
                    'name' => $name,
                    'path' => $path,
                    'suggested_handle' => $suggestedHandle ?: $name,
                    'last_modified' => $this->formatTimestamp(filemtime($path) ?: time()),
                    'catalog_id' => $catalogId,
                ];
                $fsFolders[] = $entry;
                if ($catalogId === null) {
                    $uncataloged[] = $entry;
                }
            };

            $entries = array_filter(scandir($vendorRoot) ?: [], fn($e) => $e !== '.' && $e !== '..' && $e !== '_packages');
            foreach ($entries as $entry) {
                $full = $vendorRoot . '/' . $entry;
                if (!is_dir($full)) {
                    continue;
                }

                // Pattern 1: rad/vendor/package
                if ($this->isPackageFolder($full)) {
                    $composerHandle = $this->getComposerHandle($full);
                    $suggested = $composerHandle ?: $entry;
                    $addFsEntry($entry, $full, $suggested);
                    continue;
                }

                // Pattern 2: rad/vendor/vendor/package
                $children = array_filter(scandir($full) ?: [], fn($c) => $c !== '.' && $c !== '..');
                foreach ($children as $child) {
                    $childPath = $full . '/' . $child;
                    if (!$this->isPackageFolder($childPath)) {
                        continue;
                    }
                    $composerHandle = $this->getComposerHandle($childPath);
                    $suggestedHandle = $composerHandle ?: ($entry . '/' . $child); // e.g., vendor/package
                    $addFsEntry($child, $childPath, $suggestedHandle);
                }
            }
        }

        $this->runData['data']['vendors'] = $records;
        $this->runData['data']['vendor_stats'] = [
            'total' => count($records),
            'installed' => $installed,
            'missing' => $missing,
            'uncataloged' => count($uncataloged),
        ];
        $this->runData['data']['filters'] = $filters;
        $this->runData['data']['vendor_root'] = $vendorRoot;
        $this->runData['data']['fs_folders'] = $fsFolders;
        $this->runData['data']['uncataloged'] = $uncataloged;
        $this->runData['route']['h1'] = 'Third-Party Libraries';
        $this->runData['route']['meta_title'] = 'Vendor Libraries';
        $this->runData['route']['breadcrumb'] = ['Libraries' => ''];
        $this->runData['route']['primary_action'] = [
            'label' => 'Add Library',
            'href' => $this->runData['route']['rad_admin_url'] . '/vendor/add',
        ];
        return $this->runData;
    }

    public function filesystem() {
        // Build catalog map to mark which folders are already catalogued.
        $records = $this->db->select('s_vendor', ['s_service_type_id' => self::SERVICE_TYPE_ID], true, ['s_title' => 'ASC']);
        $catalogByHandle = [];
        $catalogLookup = [];
        foreach ($records as $record) {
            $catalogByHandle[strtolower($record['s_handle'] ?? '')] = (int)$record['id'];
            if (!empty($record['s_install_path'])) {
                $catalogByHandle[strtolower(basename($record['s_install_path']))] = (int)$record['id'];
            }
            $catalogLookup[(int)$record['id']] = $record;
        }

        // Filesystem scan
        $fsFolders = [];
        $uncataloged = [];
        $vendorRoot = rtrim($this->runData['config']['dir']['vendor'] ?? dirname(__DIR__, 2) . '/vendor', '/');
        if (is_dir($vendorRoot)) {
            $addFsEntry = function (string $name, string $path, ?string $suggestedHandle = null) use (&$fsFolders, &$uncataloged, $catalogByHandle) {
                $candidates = [];
                if (!empty($suggestedHandle)) {
                    $candidates[] = $suggestedHandle;
                }
                $candidates[] = $name;
                $candidates[] = basename($path);
                if ($suggestedHandle && str_contains($suggestedHandle, '/')) {
                    $parts = explode('/', $suggestedHandle);
                    $candidates[] = end($parts);
                }
                $catalogId = null;
                foreach ($candidates as $cand) {
                    $key = strtolower($cand);
                    if (isset($catalogByHandle[$key])) {
                        $catalogId = $catalogByHandle[$key];
                        break;
                    }
                }
                $entry = [
                    'name' => $name,
                    'path' => $path,
                    'suggested_handle' => $suggestedHandle ?: $name,
                    'last_modified' => $this->formatTimestamp(filemtime($path) ?: time()),
                    'catalog_id' => $catalogId,
                ];
                $fsFolders[] = $entry;
                if ($catalogId === null) {
                    $uncataloged[] = $entry;
                }
            };

            $entries = array_filter(scandir($vendorRoot) ?: [], fn($e) => $e !== '.' && $e !== '..' && $e !== '_packages');
            foreach ($entries as $entry) {
                $full = $vendorRoot . '/' . $entry;
                if (!is_dir($full)) {
                    continue;
                }

                if ($this->isPackageFolder($full)) {
                    $composerHandle = $this->getComposerHandle($full);
                    $suggested = $composerHandle ?: $entry;
                    $addFsEntry($entry, $full, $suggested);
                    continue;
                }

                $children = array_filter(scandir($full) ?: [], fn($c) => $c !== '.' && $c !== '..');
                foreach ($children as $child) {
                    $childPath = $full . '/' . $child;
                    if (!$this->isPackageFolder($childPath)) {
                        continue;
                    }
                    $composerHandle = $this->getComposerHandle($childPath);
                    $suggestedHandle = $composerHandle ?: ($entry . '/' . $child);
                    $addFsEntry($child, $childPath, $suggestedHandle);
                }
            }
        }

        $this->runData['data']['fs_folders'] = $fsFolders;
        $this->runData['data']['uncataloged'] = $uncataloged;
        $this->runData['data']['vendor_root'] = $vendorRoot;
        $this->runData['data']['catalog_lookup'] = $catalogLookup;
        $this->runData['route']['h1'] = 'Filesystem Libraries';
        $this->runData['route']['meta_title'] = 'Filesystem Libraries';
        $this->runData['route']['breadcrumb'] = [
            'Libraries' => $this->runData['route']['rad_admin_url'] . '/vendor/view',
            'Filesystem' => '',
        ];
        $this->runData['route']['primary_action'] = [
            'label' => 'View Catalog',
            'href' => $this->runData['route']['rad_admin_url'] . '/vendor/view',
        ];
        return $this->runData;
    }

    public function detail() {
        $vendor = $this->locateVendor($this->runData['route']['pathparts'][3] ?? '');
        $service = $this->getService();
        $vendor['filesystem'] = $service->describeFilesystem($vendor['s_handle'], $vendor);
        $this->runData['data']['vendor'] = $vendor;
        $this->runData['data']['packages_dir'] = $service->packagesDirectory();
        $this->runData['route']['h1'] = $vendor['s_title'] ?? 'Library';
        $this->runData['route']['meta_title'] = 'Library · ' . ($vendor['s_title'] ?? $vendor['s_handle']);
        $this->runData['route']['breadcrumb'] = [
            'Libraries' => $this->runData['route']['rad_admin_url'] . '/vendor/view',
            $vendor['s_title'] ?? $vendor['s_handle'] => '',
        ];
        $this->runData['route']['primary_action'] = [
            'label' => 'Upload Package',
            'href' => '#install-library',
        ];
        return $this->runData;
    }

    public function add() {
        $this->runData['route']['h1'] = 'Add Library';
        $this->runData['route']['meta_title'] = 'Add Library';
        $this->runData['route']['breadcrumb'] = [
            'Libraries' => $this->runData['route']['rad_admin_url'] . '/vendor/view',
            'Add' => '',
        ];

        $prefill = [];
        $handleFromRequest = $this->runData['request']->get['handle'] ?? '';
        $pathFromRequest = $this->runData['request']->get['path'] ?? '';
        if (!empty($handleFromRequest)) {
            $sanitized = $this->sanitizeHandle($handleFromRequest);
            $prefill['s_handle'] = $sanitized;
            $prefill['s_install_path'] = $pathFromRequest ?: (($this->runData['config']['dir']['vendor'] ?? dirname(__DIR__, 2) . '/vendor') . '/' . str_replace('/', DIRECTORY_SEPARATOR, $sanitized));
            $prefill['s_title'] = ucwords(str_replace(['-', '_', '/'], ' ', $sanitized));
        } elseif (!empty($pathFromRequest)) {
            $prefill['s_install_path'] = $pathFromRequest;
        }
        if (!empty($prefill)) {
            $this->runData['data']['vendor_prefill'] = $prefill;
        }

        if (!empty($this->runData['request']->post['s_title'])) {
            $payload = $this->collectPayload();
            $existing = $this->db->select('s_vendor', ['s_handle' => $payload['s_handle']], true);
            if (!empty($existing)) {
                $this->setAlert('A library with this handle already exists.', 'danger');
            } else {
                $payload['s_service_type_id'] = self::SERVICE_TYPE_ID;
                $payload['s_install_path'] = $payload['s_install_path'] ?: $this->runData['config']['dir']['vendor'] . '/' . $payload['s_handle'];
                $this->db->insert('s_vendor', $payload);
                $this->setAlert('Library <code>' . htmlspecialchars($payload['s_title']) . '</code> added.', 'success', true);
                $this->redirect('/vendor/view');
            }
        }

        return $this->runData;
    }

    public function edit() {
        $vendor = $this->locateVendor($this->runData['route']['pathparts'][3] ?? '');
        $this->runData['route']['h1'] = 'Edit Library';
        $this->runData['route']['meta_title'] = 'Edit · ' . ($vendor['s_title'] ?? $vendor['s_handle']);
        $this->runData['route']['breadcrumb'] = [
            'Libraries' => $this->runData['route']['rad_admin_url'] . '/vendor/view',
            $vendor['s_title'] ?? $vendor['s_handle'] => $this->runData['route']['rad_admin_url'] . '/vendor/detail/' . $vendor['uid'],
            'Edit' => '',
        ];

        if (!empty($this->runData['request']->post['s_title'])) {
            $payload = $this->collectPayload($vendor['s_handle']);
            $this->db->update('s_vendor', $payload, ['id' => $vendor['id']]);
            $this->setAlert('Library updated successfully.', 'success', true);
            $this->redirect('/vendor/detail/' . $vendor['uid']);
        }

        $this->runData['data']['vendor'] = $vendor;
        return $this->runData;
    }

    public function install() {
        $vendor = $this->locateVendor($this->runData['route']['pathparts'][3] ?? '');
        $service = $this->getService();
        try {
            $result = null;
            if (!empty($_FILES['package_archive']) && ($_FILES['package_archive']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $archive = $service->storeUploadedArchive($_FILES['package_archive'], $vendor['s_handle']);
                $result = $service->installFromArchive($vendor['s_handle'], $archive, $vendor);
                $message = 'Library installed from uploaded package.';
            } else {
                $result = $service->linkExistingLibrary($vendor['s_handle'], $vendor);
                $message = 'Library folder detected. Metadata refreshed.';
            }

            $update = [
                's_install_path' => $vendor['s_install_path'] ?: $result['path'],
                's_version_installed' => $result['version'] ?? $vendor['s_version_installed'],
                's_last_scan' => date('Y-m-d H:i:s'),
                'livestatus' => '1',
            ];
            $this->db->update('s_vendor', $update, ['id' => $vendor['id']]);
            $this->setAlert($message, 'success', true);
        } catch (RuntimeException $e) {
            $this->setAlert($e->getMessage(), 'danger');
        } catch (\Throwable $e) {
            $this->errorHandler->handleException($e);
            $this->setAlert('Unable to install the library. Please check the logs.', 'danger');
        }
        $this->redirect('/vendor/detail/' . $vendor['uid']);
    }

    public function refresh() {
        $vendor = $this->locateVendor($this->runData['route']['pathparts'][3] ?? '');
        try {
            $result = $this->getService()->linkExistingLibrary($vendor['s_handle'], $vendor);
            $this->db->update('s_vendor', [
                's_version_installed' => $result['version'] ?? $vendor['s_version_installed'],
                's_last_scan' => date('Y-m-d H:i:s'),
            ], ['id' => $vendor['id']]);
            $this->setAlert('Library metadata refreshed from filesystem.', 'success', true);
        } catch (RuntimeException $e) {
            $this->setAlert($e->getMessage(), 'danger');
        }
        $this->redirect('/vendor/detail/' . $vendor['uid']);
    }

    public function sync() {
        $this->setAlert('Manifest sync is disabled. Manage libraries directly in Vendor Libraries.', 'info');
        $this->redirect('/vendor/view');
    }

    private function collectPayload(?string $currentHandle = null): array {
        $post = $this->runData['request']->post;
        $handle = $this->sanitizeHandle($post['s_handle'] ?? $post['s_title'] ?? '');
        if ($handle === '') {
            $handle = 'library-' . date('YmdHis');
        }
        if ($currentHandle) {
            $handle = $currentHandle;
        }
        return [
            's_title' => trim($post['s_title'] ?? ''),
            's_handle' => $handle,
            's_summary' => trim($post['s_summary'] ?? ''),
            's_category' => trim($post['s_category'] ?? ''),
            's_doc_url' => trim($post['s_doc_url'] ?? ''),
            's_source_url' => trim($post['s_source_url'] ?? ''),
            's_install_path' => trim($post['s_install_path'] ?? ''),
            's_version_available' => trim($post['s_version_available'] ?? ''),
            's_version_installed' => trim($post['s_version_installed'] ?? ''),
            's_usage_notes' => $post['s_usage_notes'] ?? '',
        ];
    }

    private function sanitizeHandle(string $handle): string {
        $handle = strtolower(trim($handle));
        // Allow vendor/package style handles; normalize other chars to dash.
        $handle = preg_replace('/[^a-z0-9_\\-\\/]+/', '-', $handle);
        // Collapse multiple dashes or slashes.
        $handle = preg_replace('/-{2,}/', '-', $handle);
        $handle = preg_replace('#/{2,}#', '/', $handle);
        return trim($handle, "-/");
    }

    private function locateVendor(string $reference): array {
        if ($reference === '') {
            throw new RuntimeException('Vendor reference missing.');
        }
        $criteria = ctype_digit($reference) ? ['id' => $reference] : ['uid' => $reference];
        $criteria['s_service_type_id'] = self::SERVICE_TYPE_ID;
        $rows = $this->db->select('s_vendor', $criteria, true);
        if (count($rows) !== 1 && !ctype_digit($reference)) {
            $rows = $this->db->select('s_vendor', [
                's_handle' => $reference,
                's_service_type_id' => self::SERVICE_TYPE_ID,
            ], true);
        }
        if (count($rows) !== 1) {
            throw new RuntimeException('Library not found.');
        }
        return $rows[0];
    }

    private function setAlert(string $message, string $type = 'info', bool $persist = false): void {
        $this->runData['route']['alert'] = $type;
        $this->runData['route']['alert_message'] = $message;
        if ($persist) {
            $this->runData['request']->setAlert($message, $type);
        }
    }

    private function redirect(string $path): void {
        $url = rtrim($this->runData['route']['rad_admin_url'], '/') . $path;
        header("Location: {$url}");
        exit;
    }

    private function formatTimestamp(int $timestamp): string {
        if ($timestamp <= 0) {
            return '';
        }
        $timezone = TimeHelper::resolveTimezone(
            $this->runData['entity']['timezone'] ?? null,
            $this->runData['config']['sys']['timezone_default'] ?? null
        );
        return TimeHelper::formatUtc($timestamp, $timezone, 'Y-m-d H:i') ?? '';
    }
}

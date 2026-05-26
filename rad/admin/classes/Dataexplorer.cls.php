<?php
namespace RadAdmin;

use Core\Sys\WorkspaceStorageService;

class Dataexplorer {
    private array $runData = [];
    private $db;
    private $storage;

    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->storage = new WorkspaceStorageService($runData['config']);
    }

    public function view() {
        $this->runData['route']['h1'] = 'Data Explorer';
        $this->runData['route']['meta_title'] = 'Workspace Data Explorer';
        $this->runData['route']['breadcrumb'] = [
            'Data Explorer' => null,
        ];

        $spaceUid = $this->runData['route']['pathparts'][3] ?? '';
        $filters = [
            'q' => trim((string)($this->runData['request']->get['q'] ?? '')),
        ];
        $spaces = $this->db->select('s_space', [], true, ['s_name' => 'ASC']);
        $decorated = [];
        $spaceLookup = [];
        foreach ($spaces as $space) {
            $uid = $space['uid'] ?: ('space-' . $space['id']);
            $stats = $this->storage->summarizeWorkspace($uid);
            $decorated[] = [
                'id' => $space['id'],
                'uid' => $uid,
                'name' => $space['s_name'],
                'description' => $space['s_description'] ?? '',
                'stats' => $stats,
            ];
            $index = array_key_last($decorated);
            $spaceLookup[strtolower($uid)] = $decorated[$index];
        }

        if ($filters['q'] !== '') {
            $needle = strtolower($filters['q']);
            $decorated = array_values(array_filter($decorated, function ($row) use ($needle) {
                $blob = strtolower(($row['name'] ?? '') . ' ' . ($row['uid'] ?? ''));
                return strpos($blob, $needle) !== false;
            }));
        }

        $selected = null;
        $files = [];
        if ($spaceUid !== '') {
            $key = strtolower($spaceUid);
            if (!isset($spaceLookup[$key])) {
                $this->runData['route']['alert'] = 'warning';
                $this->runData['route']['alert_message'] = 'Workspace not found.';
            } else {
                $selected = $spaceLookup[$key];
                $files = $this->filterDisplayFiles($this->storage->listFiles($selected['uid']));
            }
        }

        $this->runData['data']['spaces'] = $decorated;

        $legacyBuckets = $this->storage->listLegacyBuckets();
        $globalEntry = null;
        $filteredLegacy = [];
        foreach ($legacyBuckets as $entry) {
            if (($entry['name'] ?? '') === 'global') {
                $globalEntry = $entry;
                continue;
            }
            $filteredLegacy[] = $entry;
        }

        $this->runData['data']['selected_space'] = $selected;
        $this->runData['data']['workspace_files'] = $files;
        $this->runData['data']['legacy_entries'] = $filteredLegacy;
        $this->runData['data']['storage_base'] = $this->storage->getBasePath();
        $this->runData['data']['space_uid'] = $spaceUid;
        $this->runData['data']['global_bucket'] = $globalEntry ?? $this->buildDefaultGlobalEntry();
        $this->runData['data']['filters'] = $filters;

        return $this->runData;
    }

    private function buildDefaultGlobalEntry(): array {
        $path = $this->storage->globalPath();
        $size = 0;
        $modified = null;
        if (is_dir($path)) {
            $size = $this->directorySize($path);
            $modified = filemtime($path);
        }
        return [
            'label' => 'Non-SaaS / Global',
            'name' => 'global',
            'is_dir' => true,
            'size' => $size,
            'modified' => $modified,
            'relative' => 'global',
        ];
    }

    private function directorySize(string $path): int {
        if (!is_dir($path)) {
            return 0;
        }
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $bytes += $file->getSize();
            }
        }
        return $bytes;
    }

    private function filterDisplayFiles(array $files): array {
        return array_values(array_filter($files, static function ($file) {
            return ($file['name'] ?? '') !== '.DS_Store';
        }));
    }
}

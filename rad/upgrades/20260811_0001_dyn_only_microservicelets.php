<?php
declare(strict_types=1);

return [
    'id' => '20260811_0001_dyn_only_microservicelets',
    'description' => 'Restrict microservicelets to the DYN routing model.',
    'run' => static function (\Core\Sys\Database $db): void {
        $rows = $db->query("SELECT COUNT(*) AS legacy_count FROM s_ms WHERE s_type <> 'DYN'");
        $legacyCount = (int)($rows[0]['legacy_count'] ?? 0);
        if ($legacyCount > 0) {
            $examples = $db->query(
                "SELECT id, s_name, s_type FROM s_ms WHERE s_type <> 'DYN' ORDER BY id LIMIT 10"
            );
            $labels = array_map(static function (array $row): string {
                $name = trim((string)($row['s_name'] ?? ''));
                $label = $name !== '' ? $name : 'id=' . (string)($row['id'] ?? '?');
                return $label . ' (' . (string)($row['s_type'] ?? 'unknown') . ')';
            }, $examples);
            $exampleText = $labels === [] ? '' : ' Found: ' . implode(', ', $labels) . '.';
            throw new \RuntimeException(
                "DYN-only migration refused: {$legacyCount} non-DYN microservicelet record(s) remain. "
                . 'Remove or rebuild those records as DYN before retrying; legacy routing is not supported.'
                . $exampleText
            );
        }

        $db->query("ALTER TABLE s_ms MODIFY s_type ENUM('DYN') NOT NULL DEFAULT 'DYN'");
    },
    'rollback' => null,
];

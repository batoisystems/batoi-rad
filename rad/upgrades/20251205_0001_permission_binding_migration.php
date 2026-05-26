<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '20251205_0001_permission_binding_migration',
    'description' => 'Create permission bindings for non-SaaS roles on private microservices and routes.',
    'run' => function (Database $db, Logger $logger): void {
        $roles = $db->select('s_role', ['s_saas' => 'N', 'livestatus' => '1'], true);
        if (empty($roles)) {
            $logger->logSql('[permission-binding] No non-SaaS roles found; skipping.');
            return;
        }
        $roleIds = array_map(static function ($r) { return (int)$r['id']; }, $roles);

        $hasBinding = function (string $type, int $objectId, int $roleId) use ($db): bool {
            $rows = $db->query(
                "SELECT id FROM s_permission_binding WHERE s_object_type = :otype AND s_object_id = :oid AND s_role_id = :rid AND livestatus != '0' LIMIT 1",
                [':otype' => $type, ':oid' => $objectId, ':rid' => $roleId]
            );
            return !empty($rows);
        };

        $inserted = 0;

        // Microservices
        $msList = $db->select('s_ms', ['s_access_scope' => 'private', 'livestatus' => '1'], true);
        foreach ($msList as $ms) {
            $msId = (int)$ms['id'];
            foreach ($roleIds as $rid) {
                if ($hasBinding('ms', $msId, $rid)) {
                    continue;
                }
                $db->insert('s_permission_binding', [
                    's_object_type' => 'ms',
                    's_object_id' => $msId,
                    's_role_id' => $rid,
                    's_access_level' => 'use',
                ]);
                $inserted++;
            }
        }

        // Routes
        $routeList = $db->query(
            "SELECT r.* FROM s_msroute r
             INNER JOIN s_ms m ON m.id = r.s_ms_id
             WHERE m.s_access_scope = 'private' AND m.livestatus = '1' AND r.livestatus = '1'"
        );
        foreach ($routeList as $route) {
            $routeId = (int)$route['id'];
            foreach ($roleIds as $rid) {
                if ($hasBinding('route', $routeId, $rid)) {
                    continue;
                }
                $db->insert('s_permission_binding', [
                    's_object_type' => 'route',
                    's_object_id' => $routeId,
                    's_role_id' => $rid,
                    's_access_level' => 'use',
                ]);
                $inserted++;
            }
        }

        $logger->logSql("[permission-binding] Inserted {$inserted} bindings.");
    },
];

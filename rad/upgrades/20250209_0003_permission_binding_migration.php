<?php
use Core\Sys\Database;
use Core\Sys\Logger;

return [
    'id' => '20250209_0003_permission_binding_migration',
    'description' => 'Populate s_permission_binding with ms/route/nav scope references and ensure required schema.',
    'run' => function (Database $db, Logger $logger): void {
        $columns = $db->query("SHOW COLUMNS FROM `s_permission_binding`");
        if (empty($columns)) {
            throw new \RuntimeException('Table s_permission_binding is missing; please create it before running this upgrade.');
        }

        $validObjectTypes = ['ms', 'route', 'nav'];
        $validAccessLevels = ['view', 'use', 'admin'];

        $insertBinding = function (string $objectType, $objectId, $roleId, string $accessLevel) use ($db, $validObjectTypes, $validAccessLevels): bool {
            $roleId = trim((string)$roleId);
            if ($roleId === '' || !ctype_digit($roleId)) {
                return false;
            }

            if (!in_array($objectType, $validObjectTypes, true)) {
                return false;
            }

            if (!in_array($accessLevel, $validAccessLevels, true)) {
                return false;
            }

            $existing = $db->query(
                "SELECT id FROM s_permission_binding WHERE s_object_type = :otype AND s_object_id = :oid AND s_role_id = :rid LIMIT 1",
                [
                    ':otype' => $objectType,
                    ':oid' => $objectId,
                    ':rid' => $roleId,
                ]
            );

            if (!empty($existing)) {
                return false;
            }

            $db->query(
                "INSERT INTO s_permission_binding 
                    (uid, livestatus, versioncode, wf_status, space_id, createdby, createstamp, s_object_type, s_object_id, s_role_id, s_access_level)
                 VALUES
                    (:uid, '1', 1, 0, 0, NULL, NOW(), :otype, :oid, :rid, :access)",
                [
                    ':uid' => $db->generateUuidV4(),
                    ':otype' => $objectType,
                    ':oid' => $objectId,
                    ':rid' => $roleId,
                    ':access' => $accessLevel,
                ]
            );

            return true;
        };

        $parseRoles = function (?string $csv): array {
            if ($csv === null || trim($csv) === '') {
                return [];
            }
            $parts = array_map('trim', explode(',', $csv));
            return array_values(array_filter($parts, function ($role) {
                return $role !== '' && ctype_digit($role);
            }));
        };

        $msRows = $db->query("SELECT id, s_access_role_ids, s_scope FROM s_ms WHERE s_access_role_ids IS NOT NULL AND s_access_role_ids != ''");
        $routeRows = $db->query("SELECT id, s_access_role_ids FROM s_msroute WHERE s_access_role_ids IS NOT NULL AND s_access_role_ids != ''");
        $navRows = $db->query("SELECT id, s_access_role_ids FROM s_nav WHERE s_access_role_ids IS NOT NULL AND s_access_role_ids != ''");

        $msProcessed = 0;
        $routeProcessed = 0;
        $navProcessed = 0;
        $permissionsCreated = 0;

        $saasRoles = [];
        $nonSaasRoles = [];

        foreach ($msRows as $row) {
            $roles = $parseRoles($row['s_access_role_ids'] ?? '');
            if (empty($roles)) {
                continue;
            }
            $msProcessed++;
            foreach ($roles as $roleId) {
                if ($insertBinding('ms', $row['id'], $roleId, 'use')) {
                    $permissionsCreated++;
                }
                if (in_array($row['s_scope'] ?? 'platform', ['workspace','app','member_org'], true)) {
                    $saasRoles[$roleId] = true;
                } else {
                    $nonSaasRoles[$roleId] = true;
                }
            }
        }

        foreach ($routeRows as $row) {
            $roles = $parseRoles($row['s_access_role_ids'] ?? '');
            if (empty($roles)) {
                continue;
            }
            $routeProcessed++;
            foreach ($roles as $roleId) {
                if ($insertBinding('route', $row['id'], $roleId, 'use')) {
                    $permissionsCreated++;
                }
            }
        }

        foreach ($navRows as $row) {
            $roles = $parseRoles($row['s_access_role_ids'] ?? '');
            if (empty($roles)) {
                continue;
            }
            $navProcessed++;
            foreach ($roles as $roleId) {
                if ($insertBinding('nav', $row['id'], $roleId, 'view')) {
                    $permissionsCreated++;
                }
            }
        }

        $logger->logError(sprintf(
            '[UPGRADE] Permission binding results -> ms processed: %d, routes processed: %d, nav processed: %d, total bindings inserted: %d',
            $msProcessed,
            $routeProcessed,
            $navProcessed,
            $permissionsCreated
        ));

        if (!empty($saasRoles) || !empty($nonSaasRoles)) {
            $logger->logError(sprintf(
                '[UPGRADE] Manual review required (Step 5). SaaS roles needing app/workspace scope: %s | Platform roles: %s',
                implode(',', array_keys($saasRoles)),
                implode(',', array_keys($nonSaasRoles))
            ));
        } else {
            $logger->logError('[UPGRADE] Manual review required (Step 5) but no ms role bindings were detected.');
        }

        $logger->logError('[UPGRADE] Legacy field s_access_role_ids retained (deprecated).');
    },
    'rollback' => function (Database $db, Logger $logger, array $config = []): void {
        $db->query("DELETE FROM s_permission_binding WHERE s_object_type IN ('ms','route','nav')");
        $logger->logError('[ROLLBACK] Cleared s_permission_binding rows for ms/route/nav references. Review manually before re-running upgrades.');
    },
];

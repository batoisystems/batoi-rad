<?php
namespace Core\Sys;

use InvalidArgumentException;

/**
 * System-level Dot Phrase service.
 * Provides CRUD and resolve/usage helpers for snippets.
 */
class DotPhraseService {
    private $db;
    private $errorHandler;

    public function __construct(Database $db, ErrorHandler $errorHandler = null) {
        $this->db = $db;
        $this->errorHandler = $errorHandler;
    }

    /**
     * List dot phrases with optional filters.
     * $filters: scope, space_id, owner_id, is_public, livestatus, search (phrase/content)
     */
    public function list(array $filters = []): array {
        $sql = "SELECT dp.*, s.s_name AS space_name, e.s_name AS owner_name
                FROM s_dotphrase dp
                LEFT JOIN s_space s ON s.id = dp.space_id
                LEFT JOIN s_entity e ON e.id = dp.s_owner_id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['scope'])) {
            $sql .= " AND dp.s_scope = :scope";
            $params[':scope'] = $filters['scope'];
        }
        if (isset($filters['space_id']) && $filters['space_id'] !== '') {
            $sql .= " AND dp.space_id = :space_id";
            $params[':space_id'] = (int)$filters['space_id'];
        }
        if (isset($filters['owner_id']) && $filters['owner_id'] !== '') {
            $sql .= " AND dp.s_owner_id = :owner_id";
            $params[':owner_id'] = (int)$filters['owner_id'];
        }
        if (isset($filters['is_public']) && $filters['is_public'] !== '') {
            $sql .= " AND dp.s_is_public = :is_public";
            $params[':is_public'] = $filters['is_public'];
        }
        if (!empty($filters['livestatus'])) {
            $sql .= " AND dp.livestatus = :livestatus";
            $params[':livestatus'] = $filters['livestatus'];
        } else {
            $sql .= " AND dp.livestatus != '0'";
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (dp.s_phrase LIKE :q OR dp.s_content LIKE :q)";
            $params[':q'] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY dp.updatestamp DESC";
        return $this->db->query($sql, $params);
    }

    public function get($idOrUid): ?array {
        $field = is_numeric($idOrUid) ? 'id' : 'uid';
        $rows = $this->db->select('s_dotphrase', [$field => $idOrUid], true);
        return $rows[0] ?? null;
    }

    public function create(array $data, ?int $actorId = null): int {
        $payload = $this->normalizePayload($data, true);
        $spaceId = (int)($payload['space_id'] ?? 0);
        unset($payload['space_id']);
        $stateData = [
            'space_id' => $spaceId
        ];
        if ($actorId !== null) {
            $stateData['createdby'] = $actorId;
        }
        return (int)$this->db->insert('s_dotphrase', $payload, $stateData);
    }

    public function update($idOrUid, array $data, ?int $actorId = null): bool {
        $phrase = $this->get($idOrUid);
        if (!$phrase) {
            throw new InvalidArgumentException('Dot phrase not found');
        }
        $payload = $this->normalizePayload($data, false, $phrase);
        $payload['updatedby'] = $actorId;
        $payload['updatestamp'] = date('Y-m-d H:i:s');
        $field = is_numeric($idOrUid) ? 'id' : 'uid';
        return (bool)$this->db->update('s_dotphrase', $payload, [$field => $idOrUid]);
    }

    public function archive($idOrUid): bool {
        $field = is_numeric($idOrUid) ? 'id' : 'uid';
        return (bool)$this->db->update('s_dotphrase', ['livestatus' => '2'], [$field => $idOrUid]);
    }

    /**
     * Resolve a phrase for an entity/space/scope; returns matching row or null.
     * Priority: exact scope/space match first, then platform/global public.
     */
    public function resolve(string $phrase, ?int $entityId = null, ?int $spaceId = null, ?string $scope = null): ?array {
        $phrase = trim($phrase);
        if ($phrase === '') {
            return null;
        }
        $scope = $scope ?: 'platform';
        $isSaas = ($scope === 'workspace');
        if ($isSaas && (!$spaceId || $spaceId <= 0)) {
            return null;
        }

        $params = [
            ':phrase' => $phrase,
            ':scope' => $scope,
            ':space_id' => (int)($spaceId ?? 0),
        ];

        $sql = "SELECT * FROM s_dotphrase
                WHERE s_phrase = :phrase
                  AND livestatus = '1'
                  AND (
                        (s_scope = :scope AND space_id = :space_id)
                        OR (s_scope = 'platform' AND space_id = 0)
                      )";

        // ownership/public check
        if ($entityId) {
            $sql .= " AND (s_is_public = 'Y' OR s_owner_id IS NULL OR s_owner_id = :eid)";
            $params[':eid'] = (int)$entityId;
        } else {
            $sql .= " AND s_is_public = 'Y'";
        }

        $sql .= " ORDER BY (s_scope = :scope AND space_id = :space_id) DESC, s_scope ASC LIMIT 1";
        $rows = $this->db->query($sql, $params);
        return $rows[0] ?? null;
    }

    /**
     * Record usage of a phrase (optional).
     */
    public function recordUsage(int $dotphraseId, ?int $entityId = null, ?int $spaceId = null, ?string $context = null): void {
        if ($dotphraseId <= 0) {
            return;
        }
        $stateData = [
            'space_id' => (int)($spaceId ?? 0)
        ];
        if ($entityId !== null) {
            $stateData['createdby'] = $entityId;
        }
        $this->db->insert('s_dotphrase_usage', [
            'dotphrase_id' => $dotphraseId,
            'entity_id' => $entityId,
            's_context' => $context,
        ], $stateData);
    }

    private function normalizePayload(array $data, bool $isCreate, array $existing = []): array {
        $phrase = trim($data['s_phrase'] ?? ($existing['s_phrase'] ?? ''));
        $content = $data['s_content'] ?? ($existing['s_content'] ?? '');
        $scope = $data['s_scope'] ?? ($existing['s_scope'] ?? 'platform');
        $spaceId = (int)($data['space_id'] ?? ($existing['space_id'] ?? 0));
        $isPublic = $data['s_is_public'] ?? ($existing['s_is_public'] ?? 'N');
        $ownerId = $data['s_owner_id'] ?? ($existing['s_owner_id'] ?? null);

        if ($phrase === '' || $content === '') {
            throw new InvalidArgumentException('Phrase and content are required');
        }
        if (!in_array($scope, ['platform','workspace','app','member_org'], true)) {
            throw new InvalidArgumentException('Invalid scope');
        }
        if (in_array($scope, ['workspace','app','member_org'], true) && $spaceId <= 0) {
            throw new InvalidArgumentException('SaaS scopes require a space_id');
        }

        $payload = [
            's_phrase' => $phrase,
            's_content' => $content,
            's_scope' => $scope,
            'space_id' => $spaceId,
            's_is_public' => $isPublic === 'Y' ? 'Y' : 'N',
            's_description' => $data['s_description'] ?? ($existing['s_description'] ?? null),
            's_owner_id' => $ownerId !== '' ? $ownerId : null,
        ];
        if (isset($data['s_tags'])) {
            $payload['s_tags'] = $data['s_tags'];
        }
        return $payload;
    }
}

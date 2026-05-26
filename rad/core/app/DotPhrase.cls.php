<?php
namespace Core\App;

use Core\Sys\DotPhraseService;
use Core\Sys\Database;
use Core\Sys\ErrorHandler;

/**
 * App-facing Dot Phrase helper (wraps system service).
 */
class DotPhrase {
    private $service;

    public function __construct(Database $db, ErrorHandler $errorHandler = null) {
        $this->service = new DotPhraseService($db, $errorHandler);
    }

    /**
     * List dot phrases with optional filters.
     *
     * @param array $filters Field filters passed to service
     * @return array Phrase rows
     */
    public function list(array $filters = []): array {
        return $this->service->list($filters);
    }

    /**
     * Get a phrase by id or uid.
     *
     * @param int|string $idOrUid Identifier
     * @return array|null Phrase row or null
     */
    public function get($idOrUid): ?array {
        return $this->service->get($idOrUid);
    }

    /**
     * Create a phrase.
     *
     * @param array $data Required keys: s_name, s_content
     * @param int|null $actorId Optional actor id for audit fields
     * @return int Inserted id
     */
    public function create(array $data, ?int $actorId = null): int {
        return $this->service->create($data, $actorId);
    }

    /**
     * Update a phrase.
     *
     * @param int|string $idOrUid Identifier
     * @param array $data Fields to update
     * @param int|null $actorId Optional actor id for audit fields
     * @return bool True on success
     */
    public function update($idOrUid, array $data, ?int $actorId = null): bool {
        return $this->service->update($idOrUid, $data, $actorId);
    }

    /**
     * Archive a phrase.
     *
     * @param int|string $idOrUid Identifier
     * @return bool True on success
     */
    public function archive($idOrUid): bool {
        return $this->service->archive($idOrUid);
    }

    /**
     * Resolve a phrase to content based on scope/user.
     *
     * @param string $phrase Phrase text
     * @param int|null $entityId Optional user id
     * @param int|null $spaceId Optional workspace id
     * @param string|null $scope Optional scope
     * @return array|null Resolved phrase or null
     */
    public function resolve(string $phrase, ?int $entityId = null, ?int $spaceId = null, ?string $scope = null): ?array {
        return $this->service->resolve($phrase, $entityId, $spaceId, $scope);
    }

    /**
     * Record usage of a phrase for analytics.
     *
     * @param int $dotphraseId Phrase id
     * @param int|null $entityId Optional user id
     * @param int|null $spaceId Optional workspace id
     * @param string|null $context Optional usage context
     */
    public function recordUsage(int $dotphraseId, ?int $entityId = null, ?int $spaceId = null, ?string $context = null): void {
        $this->service->recordUsage($dotphraseId, $entityId, $spaceId, $context);
    }
}

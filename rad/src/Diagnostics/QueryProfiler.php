<?php
declare(strict_types=1);

namespace Batoi\Rad\Diagnostics;

final class QueryProfiler
{
    /** @var array<string, int> */
    private array $fingerprints = [];

    public function record(string $sql): void
    {
        $fingerprint = $this->fingerprint($sql);
        $this->fingerprints[$fingerprint] = ($this->fingerprints[$fingerprint] ?? 0) + 1;
    }

    public function queryCount(): int
    {
        return array_sum($this->fingerprints);
    }

    public function uniqueQueryCount(): int
    {
        return count($this->fingerprints);
    }

    public function duplicateQueryCount(): int
    {
        return $this->queryCount() - $this->uniqueQueryCount();
    }

    private function fingerprint(string $sql): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
        return hash('sha256', strtolower($normalized));
    }
}

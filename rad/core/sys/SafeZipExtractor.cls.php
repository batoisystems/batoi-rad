<?php
namespace Core\Sys;

use RuntimeException;
use ZipArchive;

final class SafeZipExtractor
{
    public static function extract(ZipArchive $zip, string $destination, int $maxEntries = 5000, int $maxBytes = 536870912): void
    {
        if (!is_dir($destination)) {
            throw new RuntimeException('Archive destination does not exist.');
        }
        if ($zip->numFiles > $maxEntries) {
            throw new RuntimeException('Archive contains too many entries.');
        }

        $policy = new SafePath($destination);
        $seen = [];
        $totalBytes = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = str_replace('\\', '/', (string)($stat['name'] ?? ''));
            $trimmed = rtrim($name, '/');
            if ($trimmed === '' || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:\//', $name)
                || str_contains($name, "\0") || in_array('..', explode('/', $trimmed), true)) {
                throw new RuntimeException('Archive contains an unsafe path.');
            }
            if ($policy->forWrite($trimmed) === null || isset($seen[$trimmed])) {
                throw new RuntimeException('Archive contains an unsafe or duplicate path.');
            }
            $seen[$trimmed] = true;

            $attributes = (int)($stat['external_attributes'] ?? 0);
            $unixType = ($attributes >> 16) & 0170000;
            if ($unixType === 0120000) {
                throw new RuntimeException('Archive contains a symbolic link.');
            }
            $totalBytes += max(0, (int)($stat['size'] ?? 0));
            if ($totalBytes > $maxBytes) {
                throw new RuntimeException('Archive exceeds the extraction size limit.');
            }
        }

        if (!$zip->extractTo($destination)) {
            throw new RuntimeException('Unable to extract archive contents.');
        }
    }
}

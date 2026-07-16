<?php
namespace Core\Sys;

final class SafePath
{
    private string $base;

    public function __construct(string $base)
    {
        $resolved = realpath($base);
        if ($resolved === false || !is_dir($resolved)) {
            throw new \InvalidArgumentException('Safe path base directory does not exist.');
        }
        $this->base = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function existing(string $relative): ?string
    {
        $candidate = $this->candidate($relative);
        if ($candidate === null) {
            return null;
        }
        $resolved = realpath($candidate);
        return $resolved !== false && $this->contains($resolved) ? $resolved : null;
    }

    public function forWrite(string $relative): ?string
    {
        $candidate = $this->candidate($relative);
        if ($candidate === null) {
            return null;
        }
        if (file_exists($candidate) || is_link($candidate)) {
            $resolved = realpath($candidate);
            return $resolved !== false && $this->contains($resolved) ? $resolved : null;
        }

        $parent = dirname($candidate);
        $suffix = [basename($candidate)];
        while (!file_exists($parent)) {
            $name = basename($parent);
            if ($name === '.' || $name === DIRECTORY_SEPARATOR || $name === '') {
                return null;
            }
            array_unshift($suffix, $name);
            $next = dirname($parent);
            if ($next === $parent) {
                return null;
            }
            $parent = $next;
        }
        $resolvedParent = realpath($parent);
        if ($resolvedParent === false || !$this->contains($resolvedParent)) {
            return null;
        }
        return $resolvedParent . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $suffix);
    }

    public function relative(string $absolute): ?string
    {
        $resolved = realpath($absolute);
        if ($resolved === false || !$this->contains($resolved)) {
            return null;
        }
        return ltrim(substr($resolved, strlen($this->base)), DIRECTORY_SEPARATOR);
    }

    private function candidate(string $relative): ?string
    {
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '://')) {
            return null;
        }
        $relative = str_replace('\\', '/', trim($relative));
        if ($relative === '' || $relative[0] === '/' || preg_match('/^[A-Za-z]:\//', $relative)) {
            return null;
        }
        $parts = explode('/', $relative);
        $safe = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return null;
            }
            $safe[] = $part;
        }
        if ($safe === []) {
            return null;
        }
        return $this->base . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $safe);
    }

    private function contains(string $path): bool
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        return $path === $this->base || str_starts_with($path, $this->base . DIRECTORY_SEPARATOR);
    }
}

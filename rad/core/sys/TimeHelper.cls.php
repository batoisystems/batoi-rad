<?php
namespace Core\Sys;

class TimeHelper {
    private static ?array $timezoneLookup = null;

    private static function getTimezoneLookup(): array {
        if (self::$timezoneLookup === null) {
            self::$timezoneLookup = array_flip(\DateTimeZone::listIdentifiers());
        }
        return self::$timezoneLookup;
    }

    public static function isValidTimezone(?string $timezone): bool {
        $timezone = trim((string)$timezone);
        if ($timezone === '') {
            return false;
        }
        return isset(self::getTimezoneLookup()[$timezone]);
    }

    public static function resolveTimezone(?string $timezone, ?string $fallback = null): string {
        if (self::isValidTimezone($timezone)) {
            return trim((string)$timezone);
        }
        if (self::isValidTimezone($fallback)) {
            return trim((string)$fallback);
        }
        return 'UTC';
    }

    public static function formatUtc($timestamp, string $timezone, string $format = 'M j, Y H:i'): ?string {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }
        if (is_numeric($timestamp)) {
            $timestamp = '@' . (int)$timestamp;
        }
        $timestamp = trim((string)$timestamp);
        try {
            $dt = new \DateTime($timestamp, new \DateTimeZone('UTC'));
            $resolved = self::resolveTimezone($timezone, 'UTC');
            $dt->setTimezone(new \DateTimeZone($resolved));
            return $dt->format($format);
        } catch (\Throwable $e) {
            return $timestamp;
        }
    }
}

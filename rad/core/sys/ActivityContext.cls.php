<?php
namespace Core\Sys;

class ActivityContext {
    private static array $context = [];

    public static function set(array $context): void {
        self::$context = $context;
    }

    public static function get(): array {
        return self::$context;
    }

    public static function clear(): void {
        self::$context = [];
    }

    public static function pull(): array {
        $context = self::$context;
        self::$context = [];
        return $context;
    }
}

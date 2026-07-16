<?php
declare(strict_types=1);

namespace Batoi\Rad\Http;

final class JsonResponse
{
    private const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    /** @param mixed $payload */
    public static function encode($payload): string
    {
        $json = json_encode($payload, self::FLAGS);
        if ($json === false) {
            throw new \RuntimeException('Unable to encode JSON response.');
        }

        return $json;
    }

    /** @param mixed $payload */
    public static function send($payload, int $status = 200): never
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8', true, $status);
        } else {
            http_response_code($status);
        }
        echo self::encode($payload);
        exit;
    }

    public static function error(string $message, int $status = 400): never
    {
        self::send(['error' => $message], $status);
    }
}

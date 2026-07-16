<?php
declare(strict_types=1);

namespace Batoi\Rad\Http;

final class JsonRequest
{
    /**
     * @param array<string, mixed> $fallback
     * @return array<string, mixed>
     */
    public static function decode(string $body, array $fallback = []): array
    {
        if (trim($body) === '') {
            return $fallback;
        }

        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \UnexpectedValueException('Invalid JSON payload.', 0, $exception);
        }

        if (!is_array($payload) || array_is_list($payload)) {
            throw new \UnexpectedValueException('JSON payload must be an object.');
        }

        return $payload;
    }
}

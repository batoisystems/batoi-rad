<?php
declare(strict_types=1);

namespace Batoi\Rad\Http;

final class CsrfToken
{
    /**
     * @param object $request Legacy RAD request object.
     * @param array<string, mixed> $server
     */
    public static function extract(object $request, array $server = []): string
    {
        $headers = array_change_key_case((array)($request->headers ?? []), CASE_LOWER);
        $candidates = [
            $headers['x-csrf-token'] ?? null,
            $server['HTTP_X_CSRF_TOKEN'] ?? null,
            $request->post['csrf_token'] ?? null,
            $request->get['csrf_token'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /** @param object $request Legacy RAD request object. */
    public static function isValid(object $request, array $server = []): bool
    {
        $token = self::extract($request, $server);
        return $token !== ''
            && method_exists($request, 'checkCSRFToken')
            && $request->checkCSRFToken($token) === true;
    }
}

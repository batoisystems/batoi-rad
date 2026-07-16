<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Batoi\Rad\Http\CsrfToken;
use Batoi\Rad\Http\JsonRequest;
use Batoi\Rad\Http\JsonResponse;

function assertHttpBoundary(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

assertHttpBoundary(JsonRequest::decode('', ['fallback' => true]) === ['fallback' => true], 'Empty bodies must use the supplied fallback.');
assertHttpBoundary(JsonRequest::decode('{"name":"RAD"}') === ['name' => 'RAD'], 'JSON objects must decode.');

foreach (['{', '[]', 'null'] as $invalid) {
    try {
        JsonRequest::decode($invalid);
        assertHttpBoundary(false, 'Invalid or non-object JSON must be rejected.');
    } catch (UnexpectedValueException) {
    }
}

assertHttpBoundary(JsonResponse::encode(['path' => '/rad/✓']) === '{"path":"/rad/✓"}', 'JSON responses must preserve slashes and Unicode.');

$request = new class {
    public array $headers = ['X-CSRF-Token' => 'header-token'];
    public array $post = ['csrf_token' => 'post-token'];
    public array $get = [];

    public function checkCSRFToken(string $token): bool
    {
        return $token === 'header-token';
    }
};

assertHttpBoundary(CsrfToken::extract($request) === 'header-token', 'The explicit CSRF header must take precedence.');
assertHttpBoundary(CsrfToken::isValid($request), 'Extracted CSRF tokens must be validated by the request object.');

echo "HTTP boundary tests passed.\n";

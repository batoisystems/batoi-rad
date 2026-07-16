<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/batoi/aif/autoload.php';

use Batoi\Aif\Contracts\HttpTransportInterface;
use Batoi\Aif\Providers\OpenAICompatibleProvider;
use Batoi\Aif\Rad\RadAifConfig;
use Batoi\Aif\Value\HttpResponse;
use Batoi\Aif\Value\InferenceRequest;

final class RecordingTransport implements HttpTransportInterface
{
    public array $request = [];

    public function postJson(string $url, array $headers, array $payload, int $timeoutSeconds = 30): HttpResponse
    {
        $this->request = compact('url', 'headers', 'payload', 'timeoutSeconds');
        return new HttpResponse(200, json_encode([
            'id' => 'test-response',
            'model' => 'test-model',
            'output_text' => 'AIF response',
        ], JSON_THROW_ON_ERROR));
    }
}

$transport = new RecordingTransport();
$provider = new OpenAICompatibleProvider(
    apiKey: 'test-key',
    defaultTextModel: 'test-model',
    transport: $transport,
    textEndpoint: 'https://aif.example.test/v1/responses',
);
$response = $provider->generateText(new InferenceRequest(
    input: 'Hello',
    metadata: [
        'messages' => [
            ['role' => 'system', 'content' => 'System'],
            ['role' => 'user', 'content' => 'Hello'],
        ],
        'max_tokens' => 128,
    ],
));

assertSame('AIF response', $response->output, 'AIF output');
assertSame('https://aif.example.test/v1/responses', $transport->request['url'] ?? null, 'AIF endpoint');
assertSame(128, $transport->request['payload']['max_output_tokens'] ?? null, 'AIF token limit');

$resolved = RadAifConfig::resolve([
    'ai' => [
        'api_key' => 'legacy-key',
        'endpoint' => 'https://aif.example.test/v1/responses',
        'model' => 'legacy-model',
    ],
], 'general', 'mini');
assertSame('legacy-key', $resolved['api_key'] ?? null, 'legacy configuration migration');

$removedCoreImplementations = [
    'core/sys/AiService.cls.php',
    'core/sys/AiProviderFactory.cls.php',
    'core/sys/OpenAiClient.cls.php',
    'core/sys/ClaudeClient.cls.php',
    'core/sys/GeminiClient.cls.php',
    'core/sys/CopilotClient.cls.php',
    'core/app/AiClient.cls.php',
    'admin/classes/Codexapi.cls.php',
];
foreach ($removedCoreImplementations as $relativePath) {
    if (is_file(dirname(__DIR__) . '/' . $relativePath)) {
        throw new RuntimeException('Forbidden RAD AI implementation remains: ' . $relativePath);
    }
}

echo "AIF boundary test passed.\n";

function assertSame(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ' mismatch.');
    }
}

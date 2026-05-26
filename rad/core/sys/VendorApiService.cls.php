<?php
namespace Core\Sys;

use RuntimeException;

class VendorApiService {
    public function execute(array $endpoint, array $payload): array {
        $definition = $endpoint['definition'] ?? [];
        $method = strtoupper($definition['method'] ?? 'POST');
        $url = $definition['url'] ?? '';
        if ($url === '') {
            throw new RuntimeException('Vendor endpoint missing URL.');
        }

        $body = $this->buildBody($definition, $payload);
        $headers = $this->buildHeaders($definition, $payload);

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int)($definition['timeout'] ?? 60),
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if (!empty($body)) {
            $options[CURLOPT_POSTFIELDS] = is_array($body) ? json_encode($body) : $body;
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('Vendor call failed: ' . $error);
        }

        $decoded = json_decode($response, true);
        return [
            'status' => $status,
            'body' => $response,
            'decoded' => $decoded,
        ];
    }

    private function buildBody(array $definition, array $payload) {
        if (isset($definition['body_template'])) {
            $template = $definition['body_template'];
            foreach ($payload as $key => $value) {
                if (is_scalar($value)) {
                    $template = str_replace('{{' . $key . '}}', (string)$value, $template);
                }
            }
            $decoded = json_decode($template, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $template;
        }
        return $payload;
    }

    private function buildHeaders(array $definition, array $payload): array {
        $headers = $definition['headers'] ?? [];
        foreach ($headers as &$header) {
            foreach ($payload as $key => $value) {
                if (is_scalar($value)) {
                    $header = str_replace('{{' . $key . '}}', (string)$value, $header);
                }
            }
        }
        unset($header);
        if (empty($headers)) {
            $headers = ['Content-Type: application/json'];
        }
        return $headers;
    }
}

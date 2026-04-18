<?php
require_once __DIR__ . '/../config/music.php';

class HttpJsonClient {
    public function getJson(string $url, array $headers = []): array {
        return $this->requestJson($url, 'GET', null, $headers);
    }

    public function postFormJson(string $url, array $data, array $headers = []): array {
        return $this->requestJson(
            $url,
            'POST',
            http_build_query($data),
            array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers)
        );
    }

    private function requestJson(string $url, string $method, ?string $content, array $headers): array {
        $allHeaders = array_merge([
            'Accept: application/json',
            'User-Agent: ' . MUSIC_USER_AGENT,
        ], $headers);

        $options = [
            'method' => $method,
            'timeout' => MUSIC_HTTP_TIMEOUT,
            'ignore_errors' => true,
            'header' => implode("\r\n", $allHeaders),
        ];
        if ($content !== null) {
            $options['content'] = $content;
        }

        $context = stream_context_create(['http' => $options]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

<?php
require_once __DIR__ . '/../config/music.php';

class HttpJsonClient {
    public function getJson(string $url, array $headers = []): array {
        $allHeaders = array_merge([
            'Accept: application/json',
            'User-Agent: ' . MUSIC_USER_AGENT,
        ], $headers);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => MUSIC_HTTP_TIMEOUT,
                'ignore_errors' => true,
                'header' => implode("\r\n", $allHeaders),
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

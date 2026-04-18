<?php
require_once __DIR__ . '/HttpJsonClient.php';
require_once __DIR__ . '/SimpleMusicCache.php';

class SpotifyService {
    private HttpJsonClient $http;
    private SimpleMusicCache $cache;

    public function __construct() {
        $this->http = new HttpJsonClient();
        $this->cache = new SimpleMusicCache();
    }

    public function isConfigured(): bool {
        return SPOTIFY_CLIENT_ID !== '' && SPOTIFY_CLIENT_SECRET !== '';
    }

    public function buildEmbed(string $track = '', string $artist = ''): array {
        $track = trim($track);
        $artist = trim($artist);

        if (!$this->isConfigured()) {
            return [
                'configured' => false,
                'found' => false,
                'type' => null,
                'title' => null,
                'spotify_url' => null,
                'embed_url' => null,
                'reason' => 'Spotify credentials not configured',
            ];
        }

        if ($track !== '' && $artist !== '') {
            $trackResult = $this->searchTrack($track, $artist);
            if ($trackResult !== null) {
                return $trackResult;
            }
        }

        if ($artist !== '') {
            $artistResult = $this->searchArtist($artist);
            if ($artistResult !== null) {
                return $artistResult;
            }
        }

        if ($track !== '') {
            $trackResult = $this->searchTrack($track, '');
            if ($trackResult !== null) {
                return $trackResult;
            }
        }

        return [
            'configured' => true,
            'found' => false,
            'type' => null,
            'title' => null,
            'spotify_url' => null,
            'embed_url' => null,
            'reason' => 'No Spotify result found',
        ];
    }

    private function getAccessToken(): ?string {
        if (!$this->isConfigured()) {
            return null;
        }

        $cached = $this->cache->remember('spotify_access_token', function () {
            $encoded = base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET);
            $response = $this->http->postFormJson(
                'https://accounts.spotify.com/api/token',
                ['grant_type' => 'client_credentials'],
                ['Authorization: Basic ' . $encoded]
            );

            if (!isset($response['access_token'])) {
                return [];
            }

            return [
                'access_token' => (string)$response['access_token'],
                'expires_in' => max(60, ((int)($response['expires_in'] ?? 3600)) - 120),
            ];
        }, 3300);

        return is_array($cached) && !empty($cached['access_token'])
            ? (string)$cached['access_token']
            : null;
    }

    private function searchTrack(string $track, string $artist): ?array {
        $query = trim($track . ' ' . $artist);
        $result = $this->search('track', $query);
        $item = $result['tracks']['items'][0] ?? null;
        if (!is_array($item) || empty($item['id'])) {
            return null;
        }

        return [
            'configured' => true,
            'found' => true,
            'type' => 'track',
            'title' => (string)($item['name'] ?? $track),
            'subtitle' => implode(', ', array_map(
                static fn(array $artistItem): string => (string)($artistItem['name'] ?? ''),
                array_filter($item['artists'] ?? [], 'is_array')
            )),
            'spotify_url' => (string)($item['external_urls']['spotify'] ?? ''),
            'embed_url' => 'https://open.spotify.com/embed/track/' . rawurlencode((string)$item['id']),
        ];
    }

    private function searchArtist(string $artist): ?array {
        $result = $this->search('artist', $artist);
        $item = $result['artists']['items'][0] ?? null;
        if (!is_array($item) || empty($item['id'])) {
            return null;
        }

        return [
            'configured' => true,
            'found' => true,
            'type' => 'artist',
            'title' => (string)($item['name'] ?? $artist),
            'subtitle' => 'Artist on Spotify',
            'spotify_url' => (string)($item['external_urls']['spotify'] ?? ''),
            'embed_url' => 'https://open.spotify.com/embed/artist/' . rawurlencode((string)$item['id']),
        ];
    }

    private function search(string $type, string $query): array {
        $query = trim($query);
        $token = $this->getAccessToken();
        if ($query === '' || $token === null) {
            return [];
        }

        return $this->cache->remember('spotify_search_' . $type . '_' . strtolower($query), function () use ($type, $query, $token) {
            $url = 'https://api.spotify.com/v1/search?' . http_build_query([
                'q' => $query,
                'type' => $type,
                'limit' => 1,
                'market' => SPOTIFY_MARKET,
            ]);

            return $this->http->getJson($url, [
                'Authorization: Bearer ' . $token,
            ]);
        }, 86400);
    }
}

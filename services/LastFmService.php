<?php
require_once __DIR__ . '/HttpJsonClient.php';
require_once __DIR__ . '/SimpleMusicCache.php';

class LastFmService {
    private HttpJsonClient $http;
    private SimpleMusicCache $cache;

    public function __construct() {
        $this->http = new HttpJsonClient();
        $this->cache = new SimpleMusicCache();
    }

    public function isConfigured(): bool {
        return LASTFM_API_KEY !== '';
    }

    public function enrichArtist(string $artistName): array {
        $artistName = trim($artistName);
        if ($artistName === '' || !$this->isConfigured()) {
            return [
                'configured' => $this->isConfigured(),
                'artist' => $artistName,
                'tags' => [],
                'similar_artists' => [],
                'top_tracks' => [],
            ];
        }

        return $this->cache->remember('lastfm_artist_' . strtolower($artistName), function () use ($artistName) {
            $info = $this->call('artist.getInfo', [
                'artist' => $artistName,
                'autocorrect' => 1,
            ]);
            $similar = $this->call('artist.getSimilar', [
                'artist' => $artistName,
                'autocorrect' => 1,
                'limit' => 8,
            ]);
            $topTracks = $this->call('artist.getTopTracks', [
                'artist' => $artistName,
                'autocorrect' => 1,
                'limit' => 8,
            ]);

            $tags = $info['artist']['tags']['tag'] ?? [];
            $similarArtists = $similar['similarartists']['artist'] ?? [];
            $tracks = $topTracks['toptracks']['track'] ?? [];

            return [
                'configured' => true,
                'artist' => $info['artist']['name'] ?? $artistName,
                'tags' => array_values(array_filter(array_map(
                    static fn(array $tag): string => (string)($tag['name'] ?? ''),
                    is_array($tags) ? $tags : []
                ))),
                'similar_artists' => array_values(array_filter(array_map(
                    static fn(array $artist): array => [
                        'name' => (string)($artist['name'] ?? ''),
                        'match' => isset($artist['match']) ? (float)$artist['match'] : null,
                        'url' => (string)($artist['url'] ?? ''),
                    ],
                    is_array($similarArtists) ? $similarArtists : []
                ), static fn(array $artist): bool => $artist['name'] !== '')),
                'top_tracks' => array_values(array_filter(array_map(
                    static fn(array $track): array => [
                        'title' => (string)($track['name'] ?? ''),
                        'url' => (string)($track['url'] ?? ''),
                    ],
                    is_array($tracks) ? $tracks : []
                ), static fn(array $track): bool => $track['title'] !== '')),
            ];
        }, 43200);
    }

    private function call(string $method, array $params): array {
        if (!$this->isConfigured()) {
            return [];
        }

        $params['method'] = $method;
        $params['api_key'] = LASTFM_API_KEY;
        $params['format'] = 'json';

        $url = 'https://ws.audioscrobbler.com/2.0/?' . http_build_query($params);
        return $this->http->getJson($url);
    }
}

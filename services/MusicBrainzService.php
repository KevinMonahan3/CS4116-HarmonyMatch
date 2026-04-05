<?php
require_once __DIR__ . '/HttpJsonClient.php';
require_once __DIR__ . '/SimpleMusicCache.php';

class MusicBrainzService {
    private HttpJsonClient $http;
    private SimpleMusicCache $cache;

    public function __construct() {
        $this->http = new HttpJsonClient();
        $this->cache = new SimpleMusicCache();
    }

    public function searchArtists(string $query, int $limit = 8): array {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $url = 'https://musicbrainz.org/ws/2/artist?fmt=json&limit=' . $limit
            . '&query=' . rawurlencode($query);

        $data = $this->cache->remember('mb_artist_' . strtolower($query) . '_' . $limit, function () use ($url) {
            return $this->http->getJson($url);
        }, 86400);

        $artists = $data['artists'] ?? [];
        $results = [];
        foreach ($artists as $artist) {
            if (!is_array($artist)) {
                continue;
            }

            $results[] = [
                'source' => 'musicbrainz',
                'mbid' => $artist['id'] ?? '',
                'name' => $artist['name'] ?? '',
                'sort_name' => $artist['sort-name'] ?? '',
                'country' => $artist['country'] ?? '',
                'disambiguation' => $artist['disambiguation'] ?? '',
                'score' => isset($artist['score']) ? (int)$artist['score'] : null,
                'tags' => array_values(array_map(
                    static fn(array $tag): string => (string)($tag['name'] ?? ''),
                    array_filter($artist['tags'] ?? [], 'is_array')
                )),
            ];
        }

        return $results;
    }

    public function searchRecordings(string $query, ?string $artist = null, int $limit = 8): array {
        $query = trim($query);
        $artist = trim((string)$artist);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $search = '"' . $query . '"';
        if ($artist !== '') {
            $search .= ' AND artist:"' . $artist . '"';
        }

        $url = 'https://musicbrainz.org/ws/2/recording?fmt=json&limit=' . $limit
            . '&query=' . rawurlencode($search);

        $data = $this->cache->remember(
            'mb_recording_' . strtolower($query . '|' . $artist) . '_' . $limit,
            function () use ($url) {
                return $this->http->getJson($url);
            },
            86400
        );

        $recordings = $data['recordings'] ?? [];
        $results = [];
        foreach ($recordings as $recording) {
            if (!is_array($recording)) {
                continue;
            }

            $credit = $recording['artist-credit'][0]['name'] ?? '';
            $results[] = [
                'source' => 'musicbrainz',
                'mbid' => $recording['id'] ?? '',
                'title' => $recording['title'] ?? '',
                'artist' => $credit,
                'length_ms' => isset($recording['length']) ? (int)$recording['length'] : null,
                'disambiguation' => $recording['disambiguation'] ?? '',
                'score' => isset($recording['score']) ? (int)$recording['score'] : null,
            ];
        }

        return $results;
    }
}

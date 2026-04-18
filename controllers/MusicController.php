<?php
require_once __DIR__ . '/../services/MusicBrainzService.php';
require_once __DIR__ . '/../services/LastFmService.php';
require_once __DIR__ . '/../services/SpotifyService.php';

class MusicController {
    private MusicBrainzService $musicBrainz;
    private LastFmService $lastFm;
    private SpotifyService $spotify;

    public function __construct() {
        $this->musicBrainz = new MusicBrainzService();
        $this->lastFm = new LastFmService();
        $this->spotify = new SpotifyService();
    }

    public function searchArtists(string $query): array {
        $query = trim($query);
        if ($query === '') {
            return ['success' => false, 'error' => 'Missing query'];
        }

        return [
            'success' => true,
            'results' => $this->musicBrainz->searchArtists($query),
        ];
    }

    public function searchTracks(string $query, string $artist = ''): array {
        $query = trim($query);
        if ($query === '') {
            return ['success' => false, 'error' => 'Missing query'];
        }

        return [
            'success' => true,
            'results' => $this->musicBrainz->searchRecordings($query, $artist),
        ];
    }

    public function enrichArtist(string $artist): array {
        $artist = trim($artist);
        if ($artist === '') {
            return ['success' => false, 'error' => 'Missing artist'];
        }

        return [
            'success' => true,
            'result' => $this->lastFm->enrichArtist($artist),
        ];
    }

    public function spotifyEmbed(string $track = '', string $artist = ''): array {
        $track = trim($track);
        $artist = trim($artist);
        if ($track === '' && $artist === '') {
            return ['success' => false, 'error' => 'Missing track or artist'];
        }

        return [
            'success' => true,
            'result' => $this->spotify->buildEmbed($track, $artist),
        ];
    }
}

<?php
require_once __DIR__ . '/../services/MusicBrainzService.php';
require_once __DIR__ . '/../services/LastFmService.php';

class MusicController {
    private MusicBrainzService $musicBrainz;
    private LastFmService $lastFm;

    public function __construct() {
        $this->musicBrainz = new MusicBrainzService();
        $this->lastFm = new LastFmService();
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
}

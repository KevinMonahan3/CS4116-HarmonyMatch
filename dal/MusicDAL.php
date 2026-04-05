<?php
require_once __DIR__ . '/../config/database.php';

class MusicDAL {
    private ?PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getAllGenres(): array {
        if (!$this->db) {
            return [];
        }

        return $this->db->query('SELECT genre_id AS id, name FROM genres ORDER BY name')->fetchAll();
    }

    public function getUserGenres(int $userId): array {
        if (!$this->db) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT g.genre_id AS id, g.name, ug.rank_weight
             FROM genres g
             JOIN user_genres ug ON ug.genre_id = g.genre_id
             WHERE ug.user_id = ?
             ORDER BY COALESCE(ug.rank_weight, 999), g.name'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function setUserGenres(int $userId, array $genreIds): void {
        if (!$this->db) {
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM user_genres WHERE user_id = ?');
        $stmt->execute([$userId]);

        $insert = $this->db->prepare(
            'INSERT INTO user_genres (user_id, genre_id, rank_weight, created_at) VALUES (?, ?, ?, NOW())'
        );

        $rank = 1;
        foreach ($genreIds as $genreId) {
            $insert->execute([$userId, (int)$genreId, $rank++]);
        }
    }

    public function getUserArtists(int $userId): array {
        if (!$this->db) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT a.artist_id AS id, a.name, ua.affinity_weight
             FROM artists a
             JOIN user_artists ua ON ua.artist_id = a.artist_id
             WHERE ua.user_id = ?
             ORDER BY COALESCE(ua.affinity_weight, 999), a.name'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function addUserArtist(int $userId, string $artistName): void {
        if (!$this->db) {
            return;
        }

        $artistName = trim($artistName);
        if ($artistName === '') {
            return;
        }

        $stmt = $this->db->prepare('INSERT IGNORE INTO artists (name) VALUES (?)');
        $stmt->execute([$artistName]);

        $stmt = $this->db->prepare('SELECT artist_id FROM artists WHERE name = ?');
        $stmt->execute([$artistName]);
        $artistId = $stmt->fetchColumn();

        if ($artistId === false) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO user_artists (user_id, artist_id, affinity_weight, created_at)
             VALUES (?, ?, NULL, NOW())
             ON DUPLICATE KEY UPDATE artist_id = VALUES(artist_id)'
        );
        $stmt->execute([$userId, (int)$artistId]);
    }

    public function replaceUserArtists(int $userId, array $artistNames): void {
        if (!$this->db) {
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM user_artists WHERE user_id = ?');
        $stmt->execute([$userId]);

        foreach ($artistNames as $artistName) {
            $this->addUserArtist($userId, (string)$artistName);
        }
    }

    public function getUserSongs(int $userId): array {
        if (!$this->db) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT s.song_id AS id, s.title, a.name AS artist, us.preference_rank
             FROM songs s
             JOIN artists a ON a.artist_id = s.artist_id
             JOIN user_songs us ON us.song_id = s.song_id
             WHERE us.user_id = ?
             ORDER BY COALESCE(us.preference_rank, 999), s.title'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function addUserSong(int $userId, string $title, string $artist): void {
        if (!$this->db) {
            return;
        }

        $title = trim($title);
        $artist = trim($artist);
        if ($title === '' || $artist === '') {
            return;
        }

        $stmt = $this->db->prepare('INSERT IGNORE INTO artists (name) VALUES (?)');
        $stmt->execute([$artist]);

        $stmt = $this->db->prepare('SELECT artist_id FROM artists WHERE name = ?');
        $stmt->execute([$artist]);
        $artistId = $stmt->fetchColumn();
        if ($artistId === false) {
            return;
        }

        $stmt = $this->db->prepare('INSERT IGNORE INTO songs (title, artist_id) VALUES (?, ?)');
        $stmt->execute([$title, (int)$artistId]);

        $stmt = $this->db->prepare('SELECT song_id FROM songs WHERE title = ? AND artist_id = ?');
        $stmt->execute([$title, (int)$artistId]);
        $songId = $stmt->fetchColumn();
        if ($songId === false) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO user_songs (user_id, song_id, preference_rank, created_at)
             VALUES (?, ?, NULL, NOW())
             ON DUPLICATE KEY UPDATE song_id = VALUES(song_id)'
        );
        $stmt->execute([$userId, (int)$songId]);
    }

    public function replaceUserSongs(int $userId, array $songs): void {
        if (!$this->db) {
            return;
        }

        $stmt = $this->db->prepare('DELETE FROM user_songs WHERE user_id = ?');
        $stmt->execute([$userId]);

        foreach ($songs as $song) {
            if (!is_array($song)) {
                continue;
            }

            $title = trim((string)($song['title'] ?? ''));
            $artist = trim((string)($song['artist'] ?? ''));
            if ($title === '' || $artist === '') {
                continue;
            }

            $this->addUserSong($userId, $title, $artist);
        }
    }
}

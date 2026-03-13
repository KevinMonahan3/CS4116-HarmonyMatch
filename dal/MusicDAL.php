<?php
require_once __DIR__ . '/../config/database.php';

class MusicDAL {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // --- Genres ---

    public function getAllGenres(): array {
        return $this->db->query('SELECT * FROM genres ORDER BY name')->fetchAll();
    }

    public function getUserGenres(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT g.* FROM genres g
             JOIN user_genres ug ON ug.genre_id = g.id
             WHERE ug.user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function setUserGenres(int $userId, array $genreIds): void {
        $stmt = $this->db->prepare('DELETE FROM user_genres WHERE user_id = ?');
        $stmt->execute([$userId]);
        $insert = $this->db->prepare('INSERT INTO user_genres (user_id, genre_id) VALUES (?, ?)');
        foreach ($genreIds as $genreId) {
            $insert->execute([$userId, $genreId]);
        }
    }

    // --- Artists ---

    public function getUserArtists(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT a.* FROM artists a
             JOIN user_artists ua ON ua.artist_id = a.id
             WHERE ua.user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function addUserArtist(int $userId, string $artistName): void {
        // Insert artist if not exists, then link to user
        $stmt = $this->db->prepare('INSERT IGNORE INTO artists (name) VALUES (?)');
        $stmt->execute([$artistName]);
        $stmt = $this->db->prepare('SELECT id FROM artists WHERE name = ?');
        $stmt->execute([$artistName]);
        $artist = $stmt->fetch();
        $stmt = $this->db->prepare('INSERT IGNORE INTO user_artists (user_id, artist_id) VALUES (?, ?)');
        $stmt->execute([$userId, $artist['id']]);
    }

    // --- Songs ---

    public function getUserSongs(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT s.* FROM songs s
             JOIN user_songs us ON us.song_id = s.id
             WHERE us.user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function addUserSong(int $userId, string $title, string $artist): void {
        $stmt = $this->db->prepare('INSERT IGNORE INTO songs (title, artist) VALUES (?, ?)');
        $stmt->execute([$title, $artist]);
        $stmt = $this->db->prepare('SELECT id FROM songs WHERE title = ? AND artist = ?');
        $stmt->execute([$title, $artist]);
        $song = $stmt->fetch();
        $stmt = $this->db->prepare('INSERT IGNORE INTO user_songs (user_id, song_id) VALUES (?, ?)');
        $stmt->execute([$userId, $song['id']]);
    }
}

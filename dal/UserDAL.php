<?php
require_once __DIR__ . '/../config/database.php';

class UserDAL {
    private ?PDO $db;
    public string $lastError = '';

    public function __construct() {
        $this->db = Database::getConnection();
    }

    private function baseUserSelect(): string {
        return
            'SELECT
                u.user_id AS id,
                u.user_id,
                u.email,
                u.password_hash,
                p.display_name AS name,
                p.display_name,
                p.bio,
                p.birth_year,
                p.visibility,
                COALESCE(
                    NULLIF(CONCAT_WS(", ", l.city, l.country), ""),
                    l.city,
                    l.country
                ) AS location,
                uph.photo_url AS profile_photo,
                (u.status = "active") AS is_active,
                (u.role = "admin") AS is_admin,
                EXISTS(SELECT 1 FROM user_genres ug WHERE ug.user_id = u.user_id) AS onboarding_complete,
                u.role,
                u.status,
                u.created_at,
                u.updated_at
             FROM users u
             LEFT JOIN profiles p ON p.user_id = u.user_id
             LEFT JOIN locations l ON l.location_id = p.location_id
             LEFT JOIN user_photos uph
                ON uph.user_id = u.user_id
               AND uph.is_primary = 1';
    }

    private function resolveGenderId(?string $gender): ?int {
        if (!$this->db || $gender === null || trim($gender) === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT gender_id FROM genders WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([trim($gender)]);
        $genderId = $stmt->fetchColumn();

        return $genderId === false ? null : (int)$genderId;
    }

    private function resolveLocationId(?string $location): ?int {
        if (!$this->db) {
            return null;
        }

        $location = trim((string)$location);
        if ($location === '') {
            return null;
        }

        $city = $location;
        $country = '';

        if (str_contains($location, ',')) {
            [$city, $country] = array_map('trim', explode(',', $location, 2));
        }

        $stmt = $this->db->prepare(
            'SELECT location_id
             FROM locations
             WHERE city = ? AND country = ?
             LIMIT 1'
        );
        $stmt->execute([$city, $country]);
        $existingId = $stmt->fetchColumn();
        if ($existingId !== false) {
            return (int)$existingId;
        }

        $insert = $this->db->prepare('INSERT INTO locations (city, country) VALUES (?, ?)');
        $insert->execute([$city, $country]);

        return (int)$this->db->lastInsertId();
    }

    public function searchLocations(string $query, int $limit = 8): array {
        if (!$this->db) {
            return [];
        }

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $search = '%' . $query . '%';

        $stmt = $this->db->prepare(
            'SELECT
                location_id AS id,
                city,
                country,
                COALESCE(NULLIF(CONCAT_WS(", ", city, country), ""), city, country) AS label
             FROM locations
             WHERE city LIKE ? OR country LIKE ?
             ORDER BY
                CASE WHEN city = ? THEN 0 WHEN city LIKE ? THEN 1 ELSE 2 END,
                city ASC,
                country ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $search, PDO::PARAM_STR);
        $stmt->bindValue(2, $search, PDO::PARAM_STR);
        $stmt->bindValue(3, $query, PDO::PARAM_STR);
        $stmt->bindValue(4, $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(5, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getUserById(int $id): array|false {
        if (!$this->db) {
            return false;
        }

        $stmt = $this->db->prepare($this->baseUserSelect() . ' WHERE u.user_id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch();
    }

    public function getUserByEmail(string $email): array|false {
        if (!$this->db) {
            return false;
        }

        $stmt = $this->db->prepare($this->baseUserSelect() . ' WHERE u.email = ?');
        $stmt->execute([$email]);

        return $stmt->fetch();
    }

    public function createUser(string $email, string $passwordHash, string $name, string $dob, string $gender): int {
        if (!$this->db) {
            return 0;
        }

        $birthYear = null;
        if ($dob !== '') {
            $timestamp = strtotime($dob);
            if ($timestamp !== false) {
                $birthYear = (int)date('Y', $timestamp);
            }
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO users (email, password_hash, role, status, created_at, updated_at)
                 VALUES (?, ?, "user", "active", NOW(), NOW())'
            );
            $stmt->execute([$email, $passwordHash]);
            $userId = (int)$this->db->lastInsertId();

            $stmt = $this->db->prepare(
                'INSERT INTO profiles (user_id, display_name, bio, birth_year, visibility, updated_at)
                 VALUES (?, ?, NULL, ?, "public", NOW())'
            );
            $stmt->execute([$userId, $name, $birthYear]);

            $genderId = $this->resolveGenderId($gender);
            $stmt = $this->db->prepare(
                'INSERT INTO user_preferences (user_id, gender_id, seeking_type, min_age_pref, max_age_pref)
                 VALUES (?, ?, NULL, NULL, NULL)'
            );
            $stmt->execute([$userId, $genderId]);

            $this->db->commit();

            return $userId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->lastError = $e->getMessage();
            error_log('createUser failed: ' . $e->getMessage());
            return 0;
        }
    }

    public function updateProfile(int $id, array $data): bool {
        if (!$this->db) {
            return false;
        }

        $current = $this->getUserById($id);
        if (!$current) {
            return false;
        }

        $name = array_key_exists('name', $data) ? trim((string)$data['name']) : (string)($current['name'] ?? '');
        $bio = array_key_exists('bio', $data) ? trim((string)$data['bio']) : (string)($current['bio'] ?? '');
        $birthYear = array_key_exists('dob', $data)
            ? $this->extractBirthYear((string)$data['dob'])
            : ($current['birth_year'] !== null ? (int)$current['birth_year'] : null);
        $locationId = array_key_exists('location', $data)
            ? $this->resolveLocationId((string)$data['location'])
            : $this->resolveLocationId((string)($current['location'] ?? ''));
        $genderId = array_key_exists('gender', $data)
            ? $this->resolveGenderId((string)$data['gender'])
            : null;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE profiles
                 SET display_name = ?, bio = ?, birth_year = ?, location_id = ?, updated_at = NOW()
                 WHERE user_id = ?'
            );
            $stmt->execute([$name, $bio !== '' ? $bio : null, $birthYear, $locationId, $id]);

            if (array_key_exists('gender', $data)) {
                $stmt = $this->db->prepare(
                    'INSERT INTO user_preferences (user_id, gender_id, seeking_type, min_age_pref, max_age_pref)
                     VALUES (?, ?, NULL, NULL, NULL)
                     ON DUPLICATE KEY UPDATE gender_id = VALUES(gender_id)'
                );
                $stmt->execute([$id, $genderId]);
            }

            if (array_key_exists('profile_photo', $data)) {
                $photo = trim((string)$data['profile_photo']);
                if ($photo === '') {
                    $stmt = $this->db->prepare('DELETE FROM user_photos WHERE user_id = ? AND is_primary = 1');
                    $stmt->execute([$id]);
                } else {
                    $stmt = $this->db->prepare(
                        'SELECT photo_id FROM user_photos WHERE user_id = ? AND is_primary = 1 LIMIT 1'
                    );
                    $stmt->execute([$id]);
                    $photoId = $stmt->fetchColumn();

                    if ($photoId !== false) {
                        $stmt = $this->db->prepare(
                            'UPDATE user_photos
                             SET photo_url = ?, display_order = 1
                             WHERE photo_id = ?'
                        );
                        $stmt->execute([$photo, (int)$photoId]);
                    } else {
                        $stmt = $this->db->prepare(
                            'INSERT INTO user_photos (user_id, photo_url, is_primary, display_order, created_at)
                             VALUES (?, ?, 1, 1, NOW())'
                        );
                        $stmt->execute([$id, $photo]);
                    }
                }
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function updateOnboardingComplete(int $id): bool {
        if (!$this->db) {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO user_preferences (user_id, gender_id, seeking_type, min_age_pref, max_age_pref)
             VALUES (?, NULL, NULL, NULL, NULL)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)'
        );

        return $stmt->execute([$id]);
    }

    private function extractBirthYear(string $dob): ?int {
        $dob = trim($dob);
        if ($dob === '') {
            return null;
        }

        $timestamp = strtotime($dob);
        if ($timestamp === false) {
            return null;
        }

        return (int)date('Y', $timestamp);
    }

    public function setUserActive(int $id, bool $active): bool {
        if (!$this->db) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ?');
        return $stmt->execute([$active ? 'active' : 'suspended', $id]);
    }

    public function getAllUsers(int $limit = 50, int $offset = 0): array {
        if (!$this->db) {
            return [];
        }

        $stmt = $this->db->prepare(
            $this->baseUserSelect() . '
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function searchUsers(array $filters): array {
        if (!$this->db) {
            return [];
        }

        $sql = $this->baseUserSelect() . '
             WHERE u.user_id != ?
               AND u.status = "active"
               AND NOT EXISTS (
                    SELECT 1
                    FROM blocks b
                    WHERE (b.blocker_user_id = ? AND b.blocked_user_id = u.user_id)
                       OR (b.blocker_user_id = u.user_id AND b.blocked_user_id = ?)
               )';
        $excludeId = (int)($filters['exclude_id'] ?? 0);
        $params = [$excludeId, $excludeId, $excludeId];

        $query = trim((string)($filters['query'] ?? ''));
        if ($query !== '') {
            $sql .= '
               AND (
                    p.display_name LIKE ?
                    OR l.city LIKE ?
                    OR l.country LIKE ?
               )';
            $like = '%' . $query . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $genreId = (int)($filters['genre_id'] ?? 0);
        if ($genreId > 0) {
            $sql .= '
               AND EXISTS (
                    SELECT 1
                    FROM user_genres ug
                    WHERE ug.user_id = u.user_id
                      AND ug.genre_id = ?
               )';
            $params[] = $genreId;
        }

        $sql .= '
             ORDER BY u.created_at DESC
             LIMIT 50';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}

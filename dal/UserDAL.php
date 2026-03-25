<?php
require_once __DIR__ . '/../config/database.php';

class UserDAL {
    private ?PDO $db;

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
                EXISTS(SELECT 1 FROM user_preferences pref WHERE pref.user_id = u.user_id) AS onboarding_complete,
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
        $locationId = array_key_exists('location', $data)
            ? $this->resolveLocationId((string)$data['location'])
            : $this->resolveLocationId((string)($current['location'] ?? ''));

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE profiles
                 SET display_name = ?, bio = ?, location_id = ?, updated_at = NOW()
                 WHERE user_id = ?'
            );
            $stmt->execute([$name, $bio !== '' ? $bio : null, $locationId, $id]);

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

        $stmt = $this->db->prepare(
            $this->baseUserSelect() . '
             WHERE u.user_id != ?
               AND u.status = "active"
             ORDER BY u.created_at DESC
             LIMIT 50'
        );
        $stmt->execute([(int)($filters['exclude_id'] ?? 0)]);

        return $stmt->fetchAll();
    }
}

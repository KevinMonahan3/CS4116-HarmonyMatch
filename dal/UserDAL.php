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
                l.city,
                l.country,
                uph.photo_url AS profile_photo,
                up.gender_id,
                g.name AS gender,
                COALESCE(up.seeking_type, "dating") AS seeking_type,
                up.min_age_pref,
                up.max_age_pref,
                COALESCE(up.desired_gender, "everyone") AS desired_gender,
                COALESCE(up.location_scope, "anywhere") AS location_scope,
                (u.status = "active") AS is_active,
                (u.role = "admin") AS is_admin,
                EXISTS(SELECT 1 FROM user_genres ug WHERE ug.user_id = u.user_id) AS onboarding_complete,
                u.role,
                u.status,
                u.created_at,
                u.updated_at
             FROM users u
             LEFT JOIN profiles p ON p.user_id = u.user_id
             LEFT JOIN user_preferences up ON up.user_id = u.user_id
             LEFT JOIN genders g ON g.gender_id = up.gender_id
             LEFT JOIN locations l ON l.location_id = p.location_id
             LEFT JOIN user_photos uph
                ON uph.user_id = u.user_id
               AND uph.is_primary = 1';
    }

    public function getAllGenders(): array {
        if (!$this->db) {
            return [];
        }

        return $this->db->query('SELECT gender_id AS id, name FROM genders ORDER BY gender_id')->fetchAll();
    }

    private function normalizeDesiredGender(?string $value): string {
        $value = strtolower(trim((string)$value));
        if (in_array($value, ['both', 'all'], true)) {
            $value = 'everyone';
        }

        $allowed = ['male', 'female', 'non_binary', 'other', 'everyone'];
        return in_array($value, $allowed, true) ? $value : 'everyone';
    }

    private function normalizeLocationScope(?string $value): string {
        $value = strtolower(trim((string)$value));
        $allowed = ['anywhere', 'same_country', 'same_city'];
        return in_array($value, $allowed, true) ? $value : 'anywhere';
    }

    private function normalizeSeekingType(?string $value): string {
        $value = strtolower(trim((string)$value));
        if (in_array($value, ['anything', 'open', 'all'], true)) {
            $value = 'open_to_anything';
        }

        $allowed = ['open_to_anything', 'friendship', 'dating', 'networking', 'music_buddy'];
        return in_array($value, $allowed, true) ? $value : 'dating';
    }

    private function normalizeAgePreference(mixed $value, int $fallback): int {
        $age = (int)$value;
        if ($age <= 0) {
            return $fallback;
        }

        return max(18, min(100, $age));
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

    public function getUserPhotos(int $userId): array {
        if (!$this->db) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT
                photo_id AS id,
                photo_url,
                is_primary,
                display_order,
                created_at
             FROM user_photos
             WHERE user_id = ?
             ORDER BY is_primary DESC, display_order ASC, photo_id ASC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function getUserPhotoCount(int $userId): int {
        if (!$this->db) {
            return 0;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM user_photos WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int)$stmt->fetchColumn();
    }

    public function addUserPhoto(int $userId, string $photoUrl): array|false {
        if (!$this->db) {
            return false;
        }

        $count = $this->getUserPhotoCount($userId);
        if ($count >= 10) {
            $this->lastError = 'Photo limit reached';
            return false;
        }

        $isPrimary = $count === 0 ? 1 : 0;
        $displayOrder = $count + 1;

        $stmt = $this->db->prepare(
            'INSERT INTO user_photos (user_id, photo_url, is_primary, display_order, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $photoUrl, $isPrimary, $displayOrder]);

        return [
            'id' => (int)$this->db->lastInsertId(),
            'photo_url' => $photoUrl,
            'is_primary' => $isPrimary,
            'display_order' => $displayOrder,
        ];
    }

    public function setPrimaryPhoto(int $userId, int $photoId): bool {
        if (!$this->db) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM user_photos WHERE user_id = ? AND photo_id = ?');
        $stmt->execute([$userId, $photoId]);
        if ((int)$stmt->fetchColumn() === 0) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('UPDATE user_photos SET is_primary = 0 WHERE user_id = ?');
            $stmt->execute([$userId]);

            $stmt = $this->db->prepare('UPDATE user_photos SET is_primary = 1, display_order = 1 WHERE user_id = ? AND photo_id = ?');
            $stmt->execute([$userId, $photoId]);

            $this->db->commit();
            return true;
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function deleteUserPhoto(int $userId, int $photoId): array|false {
        if (!$this->db) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT photo_url, is_primary FROM user_photos WHERE user_id = ? AND photo_id = ? LIMIT 1');
        $stmt->execute([$userId, $photoId]);
        $photo = $stmt->fetch();
        if (!$photo) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM user_photos WHERE user_id = ? AND photo_id = ?');
        $stmt->execute([$userId, $photoId]);

        if (!empty($photo['is_primary'])) {
            $stmt = $this->db->prepare(
                'UPDATE user_photos
                 SET is_primary = 1, display_order = 1
                 WHERE user_id = ?
                 ORDER BY display_order ASC, photo_id ASC
                 LIMIT 1'
            );
            $stmt->execute([$userId]);
        }

        return $photo;
    }

    public function getUserByEmail(string $email): array|false {
        if (!$this->db) {
            return false;
        }

        $stmt = $this->db->prepare($this->baseUserSelect() . ' WHERE u.email = ?');
        $stmt->execute([$email]);

        return $stmt->fetch();
    }

    private function ensurePasswordResetTable(): bool {
        if (!$this->db) {
            return false;
        }

        try {
            $this->db->exec(
                'CREATE TABLE IF NOT EXISTS password_reset_tokens (
                    reset_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_password_reset_user (user_id),
                    UNIQUE KEY uq_password_reset_token_hash (token_hash)
                )'
            );
            return true;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function createPasswordResetToken(int $userId, string $tokenHash, DateTimeInterface $expiresAt): bool {
        if (!$this->ensurePasswordResetTable()) {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at)
             VALUES (?, ?, ?, NOW())'
        );

        return $stmt->execute([$userId, $tokenHash, $expiresAt->format('Y-m-d H:i:s')]);
    }

    public function getValidPasswordReset(string $tokenHash): array|false {
        if (!$this->ensurePasswordResetTable()) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT prt.reset_id, prt.user_id, u.email
             FROM password_reset_tokens prt
             JOIN users u ON u.user_id = prt.user_id
             WHERE prt.token_hash = ?
               AND prt.used_at IS NULL
               AND prt.expires_at >= NOW()
               AND u.status = "active"
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);

        return $stmt->fetch();
    }

    public function resetPasswordWithToken(string $tokenHash, string $passwordHash): bool {
        if (!$this->ensurePasswordResetTable()) {
            return false;
        }

        $reset = $this->getValidPasswordReset($tokenHash);
        if (!$reset) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?');
            $stmt->execute([$passwordHash, (int)$reset['user_id']]);

            $stmt = $this->db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE reset_id = ?');
            $stmt->execute([(int)$reset['reset_id']]);

            $stmt = $this->db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
            $stmt->execute([(int)$reset['user_id']]);

            $this->db->commit();
            return true;
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
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
                'INSERT INTO user_preferences (user_id, gender_id, seeking_type, min_age_pref, max_age_pref, desired_gender, location_scope)
                 VALUES (?, ?, "dating", 18, 40, "everyone", "anywhere")'
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
            : ($current['gender_id'] !== null ? (int)$current['gender_id'] : null);
        $desiredGender = array_key_exists('desired_gender', $data)
            ? $this->normalizeDesiredGender((string)$data['desired_gender'])
            : (string)($current['desired_gender'] ?? 'everyone');
        $seekingType = array_key_exists('seeking_type', $data)
            ? $this->normalizeSeekingType((string)$data['seeking_type'])
            : (string)($current['seeking_type'] ?? 'dating');
        $locationScope = array_key_exists('location_scope', $data)
            ? $this->normalizeLocationScope((string)$data['location_scope'])
            : (string)($current['location_scope'] ?? 'anywhere');
        $minAgePref = array_key_exists('min_age_pref', $data)
            ? $this->normalizeAgePreference($data['min_age_pref'], 18)
            : (int)($current['min_age_pref'] ?? 18);
        $maxAgePref = array_key_exists('max_age_pref', $data)
            ? $this->normalizeAgePreference($data['max_age_pref'], 40)
            : (int)($current['max_age_pref'] ?? 40);
        if ($maxAgePref < $minAgePref) {
            $maxAgePref = $minAgePref;
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE profiles
                 SET display_name = ?, bio = ?, birth_year = ?, location_id = ?, updated_at = NOW()
                 WHERE user_id = ?'
            );
            $stmt->execute([$name, $bio !== '' ? $bio : null, $birthYear, $locationId, $id]);

            if (
                array_key_exists('gender', $data)
                || array_key_exists('desired_gender', $data)
                || array_key_exists('seeking_type', $data)
                || array_key_exists('min_age_pref', $data)
                || array_key_exists('max_age_pref', $data)
                || array_key_exists('location_scope', $data)
            ) {
                $stmt = $this->db->prepare(
                    'INSERT INTO user_preferences (user_id, gender_id, seeking_type, min_age_pref, max_age_pref, desired_gender, location_scope)
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        gender_id = VALUES(gender_id),
                        seeking_type = VALUES(seeking_type),
                        min_age_pref = VALUES(min_age_pref),
                        max_age_pref = VALUES(max_age_pref),
                        desired_gender = VALUES(desired_gender),
                        location_scope = VALUES(location_scope)'
                );
                $stmt->execute([$id, $genderId, $seekingType, $minAgePref, $maxAgePref, $desiredGender, $locationScope]);
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
            'INSERT INTO user_preferences (user_id, gender_id, seeking_type, min_age_pref, max_age_pref, desired_gender, location_scope)
             VALUES (?, NULL, "dating", 18, 40, "everyone", "anywhere")
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

    public function countAllUsers(): int {
        if (!$this->db) {
            return 0;
        }

        return (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function countActiveUsers(): int {
        if (!$this->db) {
            return 0;
        }

        return (int)$this->db->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn();
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

        $gender = trim((string)($filters['gender'] ?? ''));
        if ($gender !== '') {
            $sql .= ' AND g.name = ?';
            $params[] = $gender;
        }

        $sql .= '
             ORDER BY u.created_at DESC
             LIMIT 50';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}

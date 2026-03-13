<?php
require_once __DIR__ . '/../config/database.php';

class UserDAL {
    private ?PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getUserById(int $id): array|false {
        if (!$this->db) return false;
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getUserByEmail(string $email): array|false {
        if (!$this->db) return false;
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function createUser(string $email, string $passwordHash, string $name, string $dob, string $gender): int {
        $stmt = $this->db->prepare(
            'INSERT INTO users (email, password_hash, name, dob, gender, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$email, $passwordHash, $name, $dob, $gender]);
        return (int)$this->db->lastInsertId();
    }

    public function updateProfile(int $id, array $data): bool {
        $stmt = $this->db->prepare(
            'UPDATE users SET name = ?, bio = ?, location = ?, profile_photo = ?, updated_at = NOW()
             WHERE id = ?'
        );
        return $stmt->execute([$data['name'], $data['bio'], $data['location'], $data['profile_photo'], $id]);
    }

    public function updateOnboardingComplete(int $id): bool {
        $stmt = $this->db->prepare('UPDATE users SET onboarding_complete = 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function setUserActive(int $id, bool $active): bool {
        $stmt = $this->db->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        return $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function getAllUsers(int $limit = 50, int $offset = 0): array {
        $stmt = $this->db->prepare('SELECT id, email, name, gender, location, is_active, created_at FROM users LIMIT ? OFFSET ?');
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    public function searchUsers(array $filters): array {
        // TODO: Build dynamic query from filters (age range, gender, location)
        $stmt = $this->db->prepare(
            'SELECT id, name, dob, gender, location, profile_photo, bio FROM users
             WHERE is_active = 1 AND onboarding_complete = 1 AND id != ?
             LIMIT 50'
        );
        $stmt->execute([$filters['exclude_id']]);
        return $stmt->fetchAll();
    }
}

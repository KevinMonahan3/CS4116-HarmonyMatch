<?php
require_once __DIR__ . '/../config/database.php';

class MatchDAL {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // Record a like/skip action
    public function recordSwipe(int $fromUserId, int $toUserId, string $action): void {
        // action: 'like' | 'skip'
        $stmt = $this->db->prepare(
            'INSERT INTO swipes (from_user_id, to_user_id, action, created_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE action = ?, created_at = NOW()'
        );
        $stmt->execute([$fromUserId, $toUserId, $action, $action]);
    }

    // Check if a mutual like exists (i.e. a match)
    public function checkMatch(int $userA, int $userB): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM swipes
             WHERE from_user_id = ? AND to_user_id = ? AND action = "like"'
        );
        $stmt->execute([$userB, $userA]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // Create a confirmed match record
    public function createMatch(int $userA, int $userB): int {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO matches (user_a_id, user_b_id, matched_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([min($userA, $userB), max($userA, $userB)]);
        return (int)$this->db->lastInsertId();
    }

    // Get all confirmed matches for a user
    public function getMatchesForUser(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT m.id as match_id, m.matched_at,
                    u.id, u.name, u.profile_photo, u.location
             FROM matches m
             JOIN users u ON (u.id = IF(m.user_a_id = ?, m.user_b_id, m.user_a_id))
             WHERE m.user_a_id = ? OR m.user_b_id = ?
             ORDER BY m.matched_at DESC'
        );
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    // Get potential matches (users not yet swiped on)
    public function getPotentialMatches(int $userId, int $limit = 20): array {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.name, u.dob, u.gender, u.location, u.profile_photo, u.bio
             FROM users u
             WHERE u.id != ?
               AND u.is_active = 1
               AND u.onboarding_complete = 1
               AND u.id NOT IN (
                   SELECT to_user_id FROM swipes WHERE from_user_id = ?
               )
             LIMIT ?'
        );
        $stmt->execute([$userId, $userId, $limit]);
        return $stmt->fetchAll();
    }

    // Store computed compatibility score between two users
    public function saveCompatibilityScore(int $userA, int $userB, float $score): void {
        $stmt = $this->db->prepare(
            'INSERT INTO compatibility_scores (user_a_id, user_b_id, score, computed_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE score = ?, computed_at = NOW()'
        );
        $a = min($userA, $userB);
        $b = max($userA, $userB);
        $stmt->execute([$a, $b, $score, $score]);
    }

    public function getCompatibilityScore(int $userA, int $userB): float {
        $stmt = $this->db->prepare(
            'SELECT score FROM compatibility_scores WHERE user_a_id = ? AND user_b_id = ?'
        );
        $stmt->execute([min($userA, $userB), max($userA, $userB)]);
        return (float)($stmt->fetchColumn() ?: 0);
    }
}

<?php
require_once __DIR__ . '/../config/database.php';

class MatchDAL {
    private ?PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    private function userSummarySelect(): string {
        return
            'SELECT
                u.user_id AS id,
                p.display_name AS name,
                p.bio,
                p.birth_year,
                COALESCE(NULLIF(CONCAT_WS(", ", l.city, l.country), ""), l.city, l.country) AS location,
                uph.photo_url AS profile_photo
             FROM users u
             LEFT JOIN profiles p ON p.user_id = u.user_id
             LEFT JOIN locations l ON l.location_id = p.location_id
             LEFT JOIN user_photos uph
                ON uph.user_id = u.user_id
               AND uph.is_primary = 1';
    }

    public function recordSwipe(int $fromUserId, int $toUserId, string $action): void {
        if (!$this->db || $fromUserId === $toUserId) {
            return;
        }

        if ($action === 'like') {
            $stmt = $this->db->prepare(
                'INSERT INTO likes (actor_user_id, target_user_id, created_at)
                 SELECT ?, ?, NOW()
                 FROM DUAL
                 WHERE NOT EXISTS (
                     SELECT 1 FROM likes WHERE actor_user_id = ? AND target_user_id = ?
                 )'
            );
            $stmt->execute([$fromUserId, $toUserId, $fromUserId, $toUserId]);
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO blocks (blocker_user_id, blocked_user_id, reason_code, created_at)
             SELECT ?, ?, "skip", NOW()
             FROM DUAL
             WHERE NOT EXISTS (
                 SELECT 1 FROM blocks WHERE blocker_user_id = ? AND blocked_user_id = ?
             )'
        );
        $stmt->execute([$fromUserId, $toUserId, $fromUserId, $toUserId]);
    }

    public function checkMatch(int $userA, int $userB): bool {
        if (!$this->db) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM likes
             WHERE actor_user_id = ? AND target_user_id = ?'
        );
        $stmt->execute([$userB, $userA]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function resetSkips(int $userId): void {
        if (!$this->db) return;
        $stmt = $this->db->prepare(
            'DELETE FROM blocks WHERE blocker_user_id = ? AND reason_code = \'skip\''
            );
        $stmt->execute([$userId]);
    }

    public function createMatch(int $userA, int $userB): int {
        if (!$this->db) {
            return 0;
        }

        $a = min($userA, $userB);
        $b = max($userA, $userB);

        $stmt = $this->db->prepare(
            'SELECT match_id FROM matches WHERE user_a_id = ? AND user_b_id = ? LIMIT 1'
        );
        $stmt->execute([$a, $b]);
        $existingId = $stmt->fetchColumn();
        if ($existingId !== false) {
            return (int)$existingId;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO matches (user_a_id, user_b_id, status, matched_at, updated_at)
             VALUES (?, ?, "active", NOW(), NOW())'
        );
        $stmt->execute([$a, $b]);
        $matchId = (int)$this->db->lastInsertId();

        $stmt = $this->db->prepare(
            'INSERT INTO chats (match_id, created_at, last_message_at)
             VALUES (?, NOW(), NULL)'
        );
        $stmt->execute([$matchId]);

        return $matchId;
    }

    public function getMatchesForUser(int $userId): array {
        if (!$this->db) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT
                m.match_id,
                m.matched_at,
                other.id,
                other.name,
                other.profile_photo,
                other.location
             FROM matches m
             JOIN (' . $this->userSummarySelect() . ') AS other
               ON other.id = IF(m.user_a_id = ?, m.user_b_id, m.user_a_id)
             WHERE (m.user_a_id = ? OR m.user_b_id = ?)
               AND m.status = "active"
             ORDER BY m.matched_at DESC'
        );
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    public function getPotentialMatches(int $userId, int $limit = 20): array {
        if (!$this->db) {
            return [];
        }

        $stmt = $this->db->prepare(
            $this->userSummarySelect() . '
             WHERE u.user_id != ?
               AND u.status = "active"
               AND p.display_name IS NOT NULL
               AND NOT EXISTS (
                   SELECT 1 FROM likes lk
                   WHERE lk.actor_user_id = ? AND lk.target_user_id = u.user_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM blocks b
                   WHERE b.blocker_user_id = ? AND b.blocked_user_id = u.user_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM matches m
                   WHERE m.status = "active"
                     AND (
                         (m.user_a_id = ? AND m.user_b_id = u.user_id)
                         OR (m.user_b_id = ? AND m.user_a_id = u.user_id)
                     )
               )
             ORDER BY u.created_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->bindValue(3, $userId, PDO::PARAM_INT);
        $stmt->bindValue(4, $userId, PDO::PARAM_INT);
        $stmt->bindValue(5, $userId, PDO::PARAM_INT);
        $stmt->bindValue(6, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Returns users who have liked $userId but haven't matched with them yet.
     */
    public function getLikesReceived(int $userId): array {
        if (!$this->db) return [];

        $stmt = $this->db->prepare(
            $this->userSummarySelect() . '
             WHERE EXISTS (
                 SELECT 1 FROM likes lk
                 WHERE lk.actor_user_id = u.user_id AND lk.target_user_id = ?
             )
             AND NOT EXISTS (
                 SELECT 1 FROM matches m
                 WHERE m.status = "active"
                   AND (
                       (m.user_a_id = u.user_id AND m.user_b_id = ?)
                       OR (m.user_b_id = u.user_id AND m.user_a_id = ?)
                   )
             )
             AND u.status = "active"
             ORDER BY u.created_at DESC'
        );
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    public function saveCompatibilityScore(int $userA, int $userB, float $score): void {
        if (!$this->db) {
            return;
        }

        $a = min($userA, $userB);
        $b = max($userA, $userB);

        $stmt = $this->db->prepare(
            'INSERT INTO compatibility_scores
                (user_a_id, user_b_id, genre_overlap, artist_similarity, song_match, mood_similarity, final_score, computed_at)
             VALUES (?, ?, NULL, NULL, NULL, NULL, ?, NOW())
             ON DUPLICATE KEY UPDATE final_score = VALUES(final_score), computed_at = VALUES(computed_at)'
        );
        $stmt->execute([$a, $b, $score]);
    }

    public function getCompatibilityScore(int $userA, int $userB): float {
        if (!$this->db) {
            return 0.0;
        }

        $stmt = $this->db->prepare(
            'SELECT final_score FROM compatibility_scores WHERE user_a_id = ? AND user_b_id = ?'
        );
        $stmt->execute([min($userA, $userB), max($userA, $userB)]);
        return (float)($stmt->fetchColumn() ?: 0);
    }
}

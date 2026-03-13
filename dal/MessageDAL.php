<?php
require_once __DIR__ . '/../config/database.php';

class MessageDAL {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function sendMessage(int $fromUserId, int $toUserId, string $content): int {
        $stmt = $this->db->prepare(
            'INSERT INTO messages (from_user_id, to_user_id, content, sent_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$fromUserId, $toUserId, $content]);
        return (int)$this->db->lastInsertId();
    }

    public function getConversation(int $userA, int $userB, int $limit = 50): array {
        $stmt = $this->db->prepare(
            'SELECT m.*, u.name as sender_name, u.profile_photo as sender_photo
             FROM messages m
             JOIN users u ON u.id = m.from_user_id
             WHERE (m.from_user_id = ? AND m.to_user_id = ?)
                OR (m.from_user_id = ? AND m.to_user_id = ?)
             ORDER BY m.sent_at ASC
             LIMIT ?'
        );
        $stmt->execute([$userA, $userB, $userB, $userA, $limit]);
        return $stmt->fetchAll();
    }

    // Get all conversations (latest message per match) for inbox view
    public function getInboxForUser(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT m.*, u.name as other_name, u.profile_photo as other_photo
             FROM messages m
             JOIN users u ON u.id = IF(m.from_user_id = ?, m.to_user_id, m.from_user_id)
             WHERE m.id IN (
                 SELECT MAX(id) FROM messages
                 WHERE from_user_id = ? OR to_user_id = ?
                 GROUP BY LEAST(from_user_id, to_user_id), GREATEST(from_user_id, to_user_id)
             )
             ORDER BY m.sent_at DESC'
        );
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    public function markRead(int $fromUserId, int $toUserId): void {
        $stmt = $this->db->prepare(
            'UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ?'
        );
        $stmt->execute([$fromUserId, $toUserId]);
    }
}

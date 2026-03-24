<?php
require_once __DIR__ . '/../config/database.php';

class MessageDAL {
    private ?PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    private function getMatchIdBetweenUsers(int $userA, int $userB): ?int {
        if (!$this->db) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT match_id
             FROM matches
             WHERE user_a_id = ? AND user_b_id = ? AND status = "active"
             LIMIT 1'
        );
        $stmt->execute([min($userA, $userB), max($userA, $userB)]);
        $matchId = $stmt->fetchColumn();

        return $matchId === false ? null : (int)$matchId;
    }

    private function ensureChatId(int $userA, int $userB): ?int {
        if (!$this->db) {
            return null;
        }

        $matchId = $this->getMatchIdBetweenUsers($userA, $userB);
        if ($matchId === null) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT chat_id FROM chats WHERE match_id = ? LIMIT 1');
        $stmt->execute([$matchId]);
        $chatId = $stmt->fetchColumn();
        if ($chatId !== false) {
            return (int)$chatId;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO chats (match_id, created_at, last_message_at) VALUES (?, NOW(), NULL)'
        );
        $stmt->execute([$matchId]);

        return (int)$this->db->lastInsertId();
    }

    public function sendMessage(int $fromUserId, int $toUserId, string $content): int {
        if (!$this->db) {
            return 0;
        }

        $chatId = $this->ensureChatId($fromUserId, $toUserId);
        if ($chatId === null) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO messages (chat_id, sender_user_id, body, sent_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$chatId, $fromUserId, $content]);
        $messageId = (int)$this->db->lastInsertId();

        $stmt = $this->db->prepare('UPDATE chats SET last_message_at = NOW() WHERE chat_id = ?');
        $stmt->execute([$chatId]);

        return $messageId;
    }

    public function getConversation(int $userA, int $userB, int $limit = 50): array {
        if (!$this->db) {
            return [];
        }

        $chatId = $this->ensureChatId($userA, $userB);
        if ($chatId === null) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT
                msg.message_id AS id,
                msg.chat_id,
                msg.sender_user_id AS from_user_id,
                CASE
                    WHEN msg.sender_user_id = mt.user_a_id THEN mt.user_b_id
                    ELSE mt.user_a_id
                END AS to_user_id,
                msg.body AS content,
                msg.sent_at,
                msg.read_at,
                p.display_name AS sender_name,
                uph.photo_url AS sender_photo
             FROM messages msg
             JOIN chats c ON c.chat_id = msg.chat_id
             JOIN matches mt ON mt.match_id = c.match_id
             LEFT JOIN profiles p ON p.user_id = msg.sender_user_id
             LEFT JOIN user_photos uph
                ON uph.user_id = msg.sender_user_id
               AND uph.is_primary = 1
             WHERE msg.chat_id = ?
             ORDER BY msg.sent_at ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $chatId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getInboxForUser(int $userId): array {
        if (!$this->db) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT
                msg.message_id AS id,
                msg.sender_user_id AS from_user_id,
                CASE
                    WHEN msg.sender_user_id = mt.user_a_id THEN mt.user_b_id
                    ELSE mt.user_a_id
                END AS to_user_id,
                msg.body AS content,
                msg.sent_at,
                msg.read_at,
                other_profile.display_name AS other_name,
                other_photo.photo_url AS other_photo
             FROM chats c
             JOIN matches mt ON mt.match_id = c.match_id
             JOIN messages msg
               ON msg.chat_id = c.chat_id
              AND msg.message_id = (
                    SELECT MAX(m2.message_id)
                    FROM messages m2
                    WHERE m2.chat_id = c.chat_id
                )
             LEFT JOIN profiles other_profile
               ON other_profile.user_id = IF(mt.user_a_id = ?, mt.user_b_id, mt.user_a_id)
             LEFT JOIN user_photos other_photo
               ON other_photo.user_id = IF(mt.user_a_id = ?, mt.user_b_id, mt.user_a_id)
              AND other_photo.is_primary = 1
             WHERE mt.user_a_id = ? OR mt.user_b_id = ?
             ORDER BY msg.sent_at DESC'
        );
        $stmt->execute([$userId, $userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    public function markRead(int $fromUserId, int $toUserId): void {
        if (!$this->db) {
            return;
        }

        $chatId = $this->ensureChatId($fromUserId, $toUserId);
        if ($chatId === null) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE messages
             SET read_at = NOW()
             WHERE chat_id = ? AND sender_user_id = ? AND read_at IS NULL'
        );
        $stmt->execute([$chatId, $fromUserId]);
    }
}

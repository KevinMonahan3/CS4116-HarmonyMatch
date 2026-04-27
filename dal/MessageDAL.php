<?php
require_once __DIR__ . '/../config/database.php';

class MessageDAL {
    private ?PDO $db;
    public string $lastError = '';

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

    private function findChatIdByMatchId(int $matchId): ?int {
        if (!$this->db) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT chat_id FROM chats WHERE match_id = ? LIMIT 1');
        $stmt->execute([$matchId]);
        $chatId = $stmt->fetchColumn();

        return $chatId === false ? null : (int)$chatId;
    }

    private function usersCanInteract(int $userA, int $userB): bool {
        if (!$this->db || $userA <= 0 || $userB <= 0 || $userA === $userB) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM users u1
             JOIN users u2 ON u2.user_id = ?
             WHERE u1.user_id = ?
               AND u1.status = "active"
               AND u2.status = "active"
               AND NOT EXISTS (
                    SELECT 1
                    FROM blocks b
                    WHERE (b.blocker_user_id = ? AND b.blocked_user_id = ?)
                       OR (b.blocker_user_id = ? AND b.blocked_user_id = ?)
               )'
        );
        $stmt->execute([$userB, $userA, $userA, $userB, $userB, $userA]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function ensureChatId(int $userA, int $userB): ?int {
        if (!$this->db) {
            return null;
        }

        $matchId = $this->getMatchIdBetweenUsers($userA, $userB);
        if ($matchId === null) {
            return null;
        }

        $chatId = $this->findChatIdByMatchId($matchId);
        if ($chatId !== null) {
            return $chatId;
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

        $this->lastError = '';
        $content = trim($content);
        if ($content === '') {
            $this->lastError = 'Message cannot be empty.';
            return 0;
        }

        if (mb_strlen($content) > 1000) {
            $this->lastError = 'Message is too long.';
            return 0;
        }

        if ($this->containsPhoneNumber($content)) {
            $this->lastError = 'Phone numbers are not allowed in chat.';
            return 0;
        }

        if (!$this->usersCanInteract($fromUserId, $toUserId)) {
            $this->lastError = 'Users cannot message each other.';
            return 0;
        }

        $chatId = $this->ensureChatId($fromUserId, $toUserId);
        if ($chatId === null) {
            $this->lastError = 'Unable to send message until users are matched.';
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

        if (!$this->usersCanInteract($userA, $userB)) {
            return [];
        }

        $matchId = $this->getMatchIdBetweenUsers($userA, $userB);
        if ($matchId === null) {
            return [];
        }

        $chatId = $this->findChatIdByMatchId($matchId);
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
                msg.body AS content,
                msg.sent_at,
                msg.read_at,
                IF(mt.user_a_id = ?, mt.user_b_id, mt.user_a_id) AS other_user_id,
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
             JOIN users other_user
               ON other_user.user_id = IF(mt.user_a_id = ?, mt.user_b_id, mt.user_a_id)
             WHERE (mt.user_a_id = ? OR mt.user_b_id = ?)
               AND mt.status = "active"
               AND other_user.status = "active"
               AND NOT EXISTS (
                    SELECT 1
                    FROM blocks b
                    WHERE (b.blocker_user_id = ? AND b.blocked_user_id = IF(mt.user_a_id = ?, mt.user_b_id, mt.user_a_id))
                       OR (b.blocker_user_id = IF(mt.user_a_id = ?, mt.user_b_id, mt.user_a_id) AND b.blocked_user_id = ?)
               )
             ORDER BY msg.sent_at DESC'
        );
        $stmt->execute([
            $userId,
            $userId,
            $userId,
            $userId,
            $userId,
            $userId,
            $userId,
            $userId,
            $userId,
            $userId,
        ]);
        return $stmt->fetchAll();
    }

    public function markRead(int $fromUserId, int $toUserId): void {
        if (!$this->db) {
            return;
        }

        if (!$this->usersCanInteract($fromUserId, $toUserId)) {
            return;
        }

        $matchId = $this->getMatchIdBetweenUsers($fromUserId, $toUserId);
        if ($matchId === null) {
            return;
        }

        $chatId = $this->findChatIdByMatchId($matchId);
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

    private function containsPhoneNumber(string $content): bool {
        $digitsOnly = preg_replace('/\D/', '', $content) ?? '';
        if ($digitsOnly === '') {
            return false;
        }

        if (strlen($digitsOnly) >= 8) {
            return true;
        }

        return preg_match('/(?:\+\d[\d\s().-]{6,}\d)/', $content) === 1;
    }
}

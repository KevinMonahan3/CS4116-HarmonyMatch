<?php
require_once __DIR__ . '/../config/database.php';

class ReportDAL {
    private ?PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    private function normalizeStatus(string $status): string {
        return match ($status) {
            'pending' => 'open',
            'actioned' => 'resolved',
            default => $status,
        };
    }

    private function normalizeReason(string $reason): string {
        $reason = strtolower(trim($reason));
        $allowed = ['spam', 'abuse', 'fake_profile', 'harassment', 'other'];
        if (str_contains($reason, 'harass')) {
            return 'harassment';
        }
        if (str_contains($reason, 'spam')) {
            return 'spam';
        }
        if (str_contains($reason, 'fake')) {
            return 'fake_profile';
        }
        if (str_contains($reason, 'abuse')) {
            return 'abuse';
        }
        return in_array($reason, $allowed, true) ? $reason : 'other';
    }

    public function createReport(int $reporterId, int $reportedId, string $reason): int {
        if (!$this->db) {
            return 0;
        }

        $reason = trim($reason);
        $reasonCode = $this->normalizeReason($reason);
        $message = $reason !== '' && !in_array(strtolower($reason), ['spam', 'abuse', 'fake_profile', 'harassment', 'other'], true)
            ? $reason
            : null;

        $stmt = $this->db->prepare(
            'INSERT INTO reports (reporter_user_id, reported_user_id, reason_code, message, status, created_at)
             VALUES (?, ?, ?, ?, "open", NOW())'
        );
        $stmt->execute([$reporterId, $reportedId, $reasonCode, $message]);
        return (int)$this->db->lastInsertId();
    }

    public function getAllReports(string $status = 'pending', string $query = ''): array {
        if (!$this->db) {
            return [];
        }

        $sql = 'SELECT
                r.report_id AS id,
                r.report_id,
                r.reason_code AS reason,
                r.message,
                r.status,
                r.created_at,
                reporter.display_name AS reporter_name,
                reported.display_name AS reported_name,
                r.reporter_user_id,
                r.reported_user_id
             FROM reports r
             LEFT JOIN profiles reporter ON reporter.user_id = r.reporter_user_id
             LEFT JOIN profiles reported ON reported.user_id = r.reported_user_id
             WHERE 1 = 1';
        $params = [];

        $normalizedStatus = trim($status);
        if ($normalizedStatus !== '' && $normalizedStatus !== 'all') {
            $sql .= ' AND r.status = ?';
            $params[] = $this->normalizeStatus($normalizedStatus);
        }

        $query = trim($query);
        if ($query !== '') {
            $sql .= ' AND (
                reporter.display_name LIKE ?
                OR reported.display_name LIKE ?
                OR r.message LIKE ?
                OR r.reason_code LIKE ?
            )';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $sql .= ' ORDER BY r.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getReportById(int $id): array|false {
        if (!$this->db) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT
                r.report_id AS id,
                r.reporter_user_id,
                r.reported_user_id,
                r.reason_code AS reason,
                r.message,
                r.status,
                r.created_at
             FROM reports r
             WHERE r.report_id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch();
    }

    public function updateReportStatus(int $id, string $status, int $adminId): bool {
        if (!$this->db) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE reports
             SET status = ?, resolved_by_admin_id = ?, resolved_at = NOW()
             WHERE report_id = ?'
        );
        return $stmt->execute([$this->normalizeStatus($status), $adminId, $id]);
    }

    public function logAdminAction(int $adminId, string $action, string $target): void {
        if (!$this->db) {
            return;
        }

        [$targetType, $targetId] = array_pad(explode(':', $target, 2), 2, '0');

        $stmt = $this->db->prepare(
            'INSERT INTO admin_audit_logs
                (admin_user_id, action_type, target_type, target_id, metadata, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $adminId,
            $action,
            $targetType,
            (int)$targetId,
            json_encode(['target' => $target], JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function getAdminAuditLogs(int $limit = 20): array {
        if (!$this->db) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare(
            'SELECT
                l.audit_id,
                l.action_type,
                l.target_type,
                l.target_id,
                l.metadata,
                l.created_at,
                p.display_name AS admin_name
             FROM admin_audit_logs l
             LEFT JOIN profiles p ON p.user_id = l.admin_user_id
             ORDER BY l.created_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

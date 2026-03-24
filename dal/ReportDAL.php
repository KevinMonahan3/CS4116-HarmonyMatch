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
        return in_array($reason, $allowed, true) ? $reason : 'other';
    }

    public function createReport(int $reporterId, int $reportedId, string $reason): int {
        if (!$this->db) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO reports (reporter_user_id, reported_user_id, reason_code, message, status, created_at)
             VALUES (?, ?, ?, NULL, "open", NOW())'
        );
        $stmt->execute([$reporterId, $reportedId, $this->normalizeReason($reason)]);
        return (int)$this->db->lastInsertId();
    }

    public function getAllReports(string $status = 'pending'): array {
        if (!$this->db) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT
                r.report_id AS id,
                r.report_id,
                r.reason_code AS reason,
                r.message,
                r.status,
                r.created_at,
                reporter.display_name AS reporter_name,
                reported.display_name AS reported_name
             FROM reports r
             LEFT JOIN profiles reporter ON reporter.user_id = r.reporter_user_id
             LEFT JOIN profiles reported ON reported.user_id = r.reported_user_id
             WHERE r.status = ?
             ORDER BY r.created_at DESC'
        );
        $stmt->execute([$this->normalizeStatus($status)]);
        return $stmt->fetchAll();
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
}

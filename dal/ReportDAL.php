<?php
require_once __DIR__ . '/../config/database.php';

class ReportDAL {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function createReport(int $reporterId, int $reportedId, string $reason): int {
        $stmt = $this->db->prepare(
            'INSERT INTO reports (reporter_id, reported_id, reason, status, created_at)
             VALUES (?, ?, ?, "pending", NOW())'
        );
        $stmt->execute([$reporterId, $reportedId, $reason]);
        return (int)$this->db->lastInsertId();
    }

    public function getAllReports(string $status = 'pending'): array {
        $stmt = $this->db->prepare(
            'SELECT r.*,
                    u1.name as reporter_name,
                    u2.name as reported_name
             FROM reports r
             JOIN users u1 ON u1.id = r.reporter_id
             JOIN users u2 ON u2.id = r.reported_id
             WHERE r.status = ?
             ORDER BY r.created_at DESC'
        );
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }

    public function updateReportStatus(int $id, string $status, int $adminId): bool {
        $stmt = $this->db->prepare(
            'UPDATE reports SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$status, $adminId, $id]);
    }

    public function logAdminAction(int $adminId, string $action, string $target): void {
        $stmt = $this->db->prepare(
            'INSERT INTO admin_logs (admin_id, action, target, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$adminId, $action, $target]);
    }
}

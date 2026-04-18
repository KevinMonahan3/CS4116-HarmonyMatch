<?php
require_once __DIR__ . '/../dal/UserDAL.php';
require_once __DIR__ . '/../dal/ReportDAL.php';

class AdminController {
    private UserDAL $userDAL;
    private ReportDAL $reportDAL;

    public function __construct() {
        $this->userDAL  = new UserDAL();
        $this->reportDAL = new ReportDAL();
    }

    public function getAllUsers(): array {
        return $this->userDAL->getAllUsers();
    }

    public function suspendUser(int $adminId, int $targetId): array {
        if ($targetId <= 0 || $targetId === $adminId) {
            return ['success' => false, 'error' => 'Invalid target user'];
        }
        $this->userDAL->setUserActive($targetId, false);
        $this->reportDAL->logAdminAction($adminId, 'suspend', "user:$targetId");
        return ['success' => true];
    }

    public function reactivateUser(int $adminId, int $targetId): array {
        if ($targetId <= 0) {
            return ['success' => false, 'error' => 'Invalid target user'];
        }
        $this->userDAL->setUserActive($targetId, true);
        $this->reportDAL->logAdminAction($adminId, 'reactivate', "user:$targetId");
        return ['success' => true];
    }

    public function getPendingReports(string $status = 'pending', string $query = ''): array {
        return $this->reportDAL->getAllReports($status, $query);
    }

    public function resolveReport(int $adminId, int $reportId, string $resolution): array {
        if ($reportId <= 0) {
            return ['success' => false, 'error' => 'Invalid report'];
        }

        $report = $this->reportDAL->getReportById($reportId);
        if (!$report) {
            return ['success' => false, 'error' => 'Report not found'];
        }

        $this->reportDAL->updateReportStatus($reportId, $resolution, $adminId);
        if ($resolution === 'actioned' && !empty($report['reported_user_id'])) {
            $this->userDAL->setUserActive((int)$report['reported_user_id'], false);
            $this->reportDAL->logAdminAction($adminId, 'suspend', 'user:' . (int)$report['reported_user_id']);
        }
        $this->reportDAL->logAdminAction($adminId, 'resolve_report', "report:$reportId");
        return ['success' => true];
    }

    public function updateUserProfile(int $adminId, int $targetId, array $data): array {
        if ($targetId <= 0) {
            return ['success' => false, 'error' => 'Invalid target user'];
        }

        $allowed = array_intersect_key($data, array_flip(['name', 'bio', 'location', 'profile_photo', 'dob', 'gender']));
        if (!$this->userDAL->updateProfile($targetId, $allowed)) {
            return ['success' => false, 'error' => 'Unable to update profile'];
        }

        $this->reportDAL->logAdminAction($adminId, 'edit_profile', "user:$targetId");
        return ['success' => true];
    }

    public function getAuditLogs(int $limit = 20): array {
        return $this->reportDAL->getAdminAuditLogs($limit);
    }
}

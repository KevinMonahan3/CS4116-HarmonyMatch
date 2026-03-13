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
        $this->userDAL->setUserActive($targetId, false);
        $this->reportDAL->logAdminAction($adminId, 'suspend', "user:$targetId");
        return ['success' => true];
    }

    public function reactivateUser(int $adminId, int $targetId): array {
        $this->userDAL->setUserActive($targetId, true);
        $this->reportDAL->logAdminAction($adminId, 'reactivate', "user:$targetId");
        return ['success' => true];
    }

    public function getPendingReports(): array {
        return $this->reportDAL->getAllReports('pending');
    }

    public function resolveReport(int $adminId, int $reportId, string $resolution): array {
        // resolution: 'dismissed' | 'actioned'
        $this->reportDAL->updateReportStatus($reportId, $resolution, $adminId);
        $this->reportDAL->logAdminAction($adminId, 'resolve_report', "report:$reportId");
        return ['success' => true];
    }
}

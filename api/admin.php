<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/AdminController.php';

header('Content-Type: application/json');
AuthController::requireAdmin();

$adminId = (int)$_SESSION['user_id'];
$action  = $_REQUEST['action'] ?? '';
$ctrl    = new AdminController();

switch ($action) {
    case 'users':
        $page = max(1, (int)($_GET['page'] ?? $_POST['page'] ?? 1));
        $perPage = max(5, min(100, (int)($_GET['per_page'] ?? $_POST['per_page'] ?? 25)));
        echo json_encode($ctrl->getAllUsers($perPage, ($page - 1) * $perPage));
        break;

    case 'suspend':
        $targetId = (int)($_POST['user_id'] ?? 0);
        echo json_encode($ctrl->suspendUser($adminId, $targetId));
        break;

    case 'reactivate':
        $targetId = (int)($_POST['user_id'] ?? 0);
        echo json_encode($ctrl->reactivateUser($adminId, $targetId));
        break;

    case 'reports':
        echo json_encode($ctrl->getPendingReports(
            (string)($_GET['status'] ?? $_POST['status'] ?? 'pending'),
            (string)($_GET['query'] ?? $_POST['query'] ?? '')
        ));
        break;

    case 'audit_logs':
        echo json_encode($ctrl->getAuditLogs((int)($_GET['limit'] ?? $_POST['limit'] ?? 20)));
        break;

    case 'resolve_report':
        $reportId   = (int)($_POST['report_id'] ?? 0);
        $resolution = $_POST['resolution'] ?? 'dismissed';
        echo json_encode($ctrl->resolveReport($adminId, $reportId, $resolution));
        break;

    case 'update_profile':
        $targetId = (int)($_POST['user_id'] ?? 0);
        echo json_encode($ctrl->updateUserProfile($adminId, $targetId, $_POST));
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}

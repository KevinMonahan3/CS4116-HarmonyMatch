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
        echo json_encode($ctrl->getAllUsers());
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
        echo json_encode($ctrl->getPendingReports());
        break;

    case 'resolve_report':
        $reportId   = (int)($_POST['report_id'] ?? 0);
        $resolution = $_POST['resolution'] ?? 'dismissed';
        echo json_encode($ctrl->resolveReport($adminId, $reportId, $resolution));
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}

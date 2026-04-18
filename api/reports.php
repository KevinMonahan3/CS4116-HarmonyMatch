<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../dal/ReportDAL.php';
require_once __DIR__ . '/../dal/MatchDAL.php';

header('Content-Type: application/json');
AuthController::requireLogin();

$userId = (int)($_SESSION['user_id'] ?? 0);
$action = $_REQUEST['action'] ?? '';
$reportDAL = new ReportDAL();
$matchDAL = new MatchDAL();

switch ($action) {
    case 'report':
        $reportedId = (int)($_POST['reported_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? 'other'));

        if ($reportedId <= 0 || $reportedId === $userId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user to report']);
            break;
        }

        $reportId = $reportDAL->createReport($userId, $reportedId, $reason);
        if ($reportId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unable to submit report']);
            break;
        }

        echo json_encode(['success' => true, 'report_id' => $reportId]);
        break;

    case 'block':
        $blockedId = (int)($_POST['blocked_id'] ?? 0);

        if ($blockedId <= 0 || $blockedId === $userId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user to block']);
            break;
        }

        if (!$matchDAL->blockUser($userId, $blockedId, 'user_block')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unable to block user']);
            break;
        }

        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

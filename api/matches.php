<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/MatchController.php';

header('Content-Type: application/json');
AuthController::requireLogin();

$userId = (int)$_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';
$ctrl   = new MatchController();

switch ($action) {
    case 'dashboard':
        echo json_encode($ctrl->getDashboardMatches($userId));
        break;

    case 'swipe':
        $toUserId = (int)($_POST['to_user_id'] ?? 0);
        $swipe    = $_POST['action_type'] ?? 'skip'; // 'like' | 'skip'
        if (!$toUserId) { http_response_code(400); echo json_encode(['error' => 'Missing to_user_id']); break; }
        echo json_encode($ctrl->swipe($userId, $toUserId, $swipe));
        break;

    case 'my_matches':
        echo json_encode($ctrl->getConfirmedMatches($userId));
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}

<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../dal/MessageDAL.php';

header('Content-Type: application/json');
AuthController::requireLogin();

$userId = (int)$_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';
$dal    = new MessageDAL();

switch ($action) {
    case 'inbox':
        echo json_encode($dal->getInboxForUser($userId));
        break;

    case 'conversation':
        $withUserId = (int)($_GET['with'] ?? 0);
        if (!$withUserId) { http_response_code(400); echo json_encode(['error' => 'Missing with']); break; }
        $dal->markRead($withUserId, $userId);
        echo json_encode($dal->getConversation($userId, $withUserId));
        break;

    case 'send':
        $toUserId = (int)($_POST['to_user_id'] ?? 0);
        $content  = trim($_POST['content'] ?? '');
        if (!$toUserId || $content === '') { http_response_code(400); echo json_encode(['error' => 'Missing fields']); break; }
        $id = $dal->sendMessage($userId, $toUserId, htmlspecialchars($content));
        echo json_encode(['success' => true, 'message_id' => $id]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}

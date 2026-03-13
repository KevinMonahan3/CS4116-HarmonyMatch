<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/UserController.php';

header('Content-Type: application/json');
AuthController::requireLogin();

$userId = (int)$_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';
$ctrl   = new UserController();

switch ($action) {
    case 'profile':
        $targetId = (int)($_GET['id'] ?? $userId);
        echo json_encode($ctrl->getProfile($targetId));
        break;

    case 'update_profile':
        echo json_encode($ctrl->updateProfile($userId, $_POST));
        break;

    case 'onboarding_music':
        $genreIds = array_map('intval', $_POST['genres'] ?? []);
        $artists  = $_POST['artists'] ?? [];
        $songs    = $_POST['songs']   ?? [];
        echo json_encode($ctrl->saveOnboardingStep2($userId, $genreIds, $artists, $songs));
        break;

    case 'complete_onboarding':
        echo json_encode($ctrl->completeOnboarding($userId));
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}

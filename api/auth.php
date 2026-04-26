<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../controllers/AuthController.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$auth   = new AuthController();

switch ($action) {
    case 'login':
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        echo json_encode($auth->login($email, $password));
        break;

    case 'admin_login':
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        echo json_encode($auth->loginAdmin($email, $password));
        break;

    case 'register':
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $name     = trim($_POST['name'] ?? '');
        $dob      = $_POST['dob'] ?? '';
        $gender   = $_POST['gender'] ?? '';
        echo json_encode($auth->register($email, $password, $name, $dob, $gender));
        break;

    case 'request_password_reset':
        $email = trim($_POST['email'] ?? '');
        echo json_encode($auth->requestPasswordReset($email));
        break;

    case 'validate_reset_token':
        $token = trim($_GET['token'] ?? $_POST['token'] ?? '');
        echo json_encode($auth->validateResetToken($token));
        break;

    case 'reset_password':
        $token = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';
        echo json_encode($auth->resetPassword($token, $password, $confirm));
        break;

    case 'logout':
        $redirect = !empty($_SESSION['is_admin']) ? '/admin-login.php' : '/login.php';
        $auth->logout();
        header('Location: ' . $redirect);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}

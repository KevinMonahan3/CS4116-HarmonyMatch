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

    case 'register':
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $name     = trim($_POST['name'] ?? '');
        $dob      = $_POST['dob'] ?? '';
        $gender   = $_POST['gender'] ?? '';
        echo json_encode($auth->register($email, $password, $name, $dob, $gender));
        break;

    case 'logout':
        $auth->logout();
        header('Location: /login.php');
        exit;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}

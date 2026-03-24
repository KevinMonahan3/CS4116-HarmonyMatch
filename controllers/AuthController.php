<?php
require_once __DIR__ . '/../dal/UserDAL.php';

class AuthController {
    private UserDAL $userDAL;

    public function __construct() {
        $this->userDAL = new UserDAL();
    }

    public function register(string $email, string $password, string $name, string $dob, string $gender): array {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email address'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }
        if ($this->userDAL->getUserByEmail($email)) {
            return ['success' => false, 'error' => 'Email already registered'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $id = $this->userDAL->createUser($email, $hash, $name, $dob, $gender);
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Unable to create account'];
        }

        return ['success' => true, 'user_id' => $id];
    }

    public function login(string $email, string $password): array {
        $user = $this->userDAL->getUserByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid email or password'];
        }
        if (empty($user['is_active'])) {
            return ['success' => false, 'error' => 'Account suspended'];
        }

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'] ?? $user['email'];
        $_SESSION['is_admin']  = !empty($user['is_admin']);

        $redirect = !empty($user['onboarding_complete']) ? 'dashboard.php' : 'onboarding.php';
        return ['success' => true, 'redirect' => $redirect];
    }

    public function logout(): void {
        session_destroy();
    }

    // Call at the top of protected pages
    public static function requireLogin(): void {
        if (!Database::isEnabled()) return; // DB not connected yet, skip auth
        if (empty($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function requireAdmin(): void {
        if (!Database::isEnabled()) return; // DB not connected yet, skip auth
        self::requireLogin();
        if (empty($_SESSION['is_admin'])) {
            header('Location: /dashboard.php');
            exit;
        }
    }
}

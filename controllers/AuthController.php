<?php
require_once __DIR__ . '/../dal/UserDAL.php';

class AuthController {
    private UserDAL $userDAL;

    public function __construct() {
        $this->userDAL = new UserDAL();
    }

    public function register(string $email, string $password, string $name, string $dob, string $gender): array {
        $name = trim($name);
        $gender = trim($gender);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email address'];
        }
        if ($name === '') {
            return ['success' => false, 'error' => 'Name is required'];
        }
        $pwErrors = [];
        if (strlen($password) < 8)            $pwErrors[] = 'at least 8 characters';
        if (!preg_match('/[A-Z]/', $password)) $pwErrors[] = 'an uppercase letter';
        if (!preg_match('/[0-9]/', $password)) $pwErrors[] = 'a number';
        if (!preg_match('/[^A-Za-z0-9]/', $password)) $pwErrors[] = 'a special character';
        if (!empty($pwErrors)) {
            return ['success' => false, 'error' => 'Password is missing: ' . implode(', ', $pwErrors)];
        }
        if ($this->userDAL->getUserByEmail($email)) {
            return ['success' => false, 'error' => 'Email already registered'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $id = $this->userDAL->createUser($email, $hash, $name, $dob, $gender);
        if ($id <= 0) {
            return ['success' => false, 'error' => 'Unable to create account: ' . $this->userDAL->lastError];
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['user_name'] = $name;
        $_SESSION['is_admin'] = false;

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

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'] ?? $user['email'];
        $_SESSION['is_admin'] = !empty($user['is_admin']);

        $redirect = !empty($user['onboarding_complete']) ? 'dashboard.php' : 'onboarding.php';
        return ['success' => true, 'redirect' => $redirect];
    }

    public function loginAdmin(string $email, string $password): array {
        $user = $this->userDAL->getUserByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid admin email or password'];
        }
        if (empty($user['is_active'])) {
            return ['success' => false, 'error' => 'Account suspended'];
        }
        if (empty($user['is_admin'])) {
            return ['success' => false, 'error' => 'This account does not have admin access'];
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'] ?? $user['email'];
        $_SESSION['is_admin'] = true;

        return ['success' => true, 'redirect' => 'admin.php'];
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
        if (empty($_SESSION['user_id'])) {
            header('Location: /admin-login.php');
            exit;
        }
        if (empty($_SESSION['is_admin'])) {
            header('Location: /dashboard.php');
            exit;
        }
    }
}

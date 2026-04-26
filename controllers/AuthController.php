<?php
require_once __DIR__ . '/../dal/UserDAL.php';
require_once __DIR__ . '/../services/ResendMailer.php';

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
        if (!$this->isAdult($dob)) {
            return ['success' => false, 'error' => 'You must be at least 18 years old to register'];
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

    public function registerWithPhoto(string $email, string $password, string $name, string $dob, string $gender, array $file): array {
        $result = $this->register($email, $password, $name, $dob, $gender);
        if (empty($result['success']) || empty($result['user_id']) || empty($file)) {
            return $result;
        }

        require_once __DIR__ . '/UserController.php';
        $photoResult = (new UserController())->uploadPhoto((int)$result['user_id'], $file);
        if (empty($photoResult['success'])) {
            $result['photo_warning'] = $photoResult['error'] ?? 'Account created, but photo upload failed';
        }

        return $result;
    }

    private function isAdult(string $dob): bool {
        $dob = trim($dob);
        if ($dob === '') {
            return false;
        }

        $birthDate = date_create($dob);
        if ($birthDate === false) {
            return false;
        }

        $today = new DateTimeImmutable('today');
        $age = (int)$today->diff(DateTimeImmutable::createFromMutable($birthDate))->y;
        return $age >= 18;
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

    public function requestPasswordReset(string $email): array {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Enter a valid email address'];
        }

        $generic = ['success' => true, 'message' => 'If that email exists, a reset link has been sent.'];
        $user = $this->userDAL->getUserByEmail($email);
        if (!$user || empty($user['is_active'])) {
            return $generic;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = new DateTimeImmutable('+1 hour');
        if (!$this->userDAL->createPasswordResetToken((int)$user['id'], $tokenHash, $expiresAt)) {
            return ['success' => false, 'error' => 'Unable to create password reset link'];
        }

        $resetUrl = $this->buildBaseUrl() . '/reset-password.php?token=' . urlencode($token);
        $mailer = new ResendMailer();
        $sent = $mailer->sendPasswordReset($email, $resetUrl);

        if (!$sent) {
            error_log('HarmonyMatch password reset email failed for ' . $email . ': ' . $mailer->lastError);
            error_log('HarmonyMatch password reset link for ' . $email . ': ' . $resetUrl);
        }

        return $generic + ['mail_sent' => $sent, 'mailer' => $mailer->isConfigured() ? 'resend' : 'not_configured'];
    }

    public function validateResetToken(string $token): array {
        $token = trim($token);
        if ($token === '') {
            return ['success' => false, 'error' => 'Missing reset token'];
        }

        $reset = $this->userDAL->getValidPasswordReset(hash('sha256', $token));
        return $reset
            ? ['success' => true, 'email' => $reset['email']]
            : ['success' => false, 'error' => 'Reset link is invalid or expired'];
    }

    public function resetPassword(string $token, string $password, string $confirm): array {
        $token = trim($token);
        if ($token === '') {
            return ['success' => false, 'error' => 'Missing reset token'];
        }
        if ($password !== $confirm) {
            return ['success' => false, 'error' => 'Passwords do not match'];
        }

        $pwErrors = [];
        if (strlen($password) < 8)             $pwErrors[] = 'at least 8 characters';
        if (!preg_match('/[A-Z]/', $password)) $pwErrors[] = 'an uppercase letter';
        if (!preg_match('/[0-9]/', $password)) $pwErrors[] = 'a number';
        if (!preg_match('/[^A-Za-z0-9]/', $password)) $pwErrors[] = 'a special character';
        if (!empty($pwErrors)) {
            return ['success' => false, 'error' => 'Password is missing: ' . implode(', ', $pwErrors)];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        if (!$this->userDAL->resetPasswordWithToken(hash('sha256', $token), $hash)) {
            return ['success' => false, 'error' => 'Reset link is invalid or expired'];
        }

        return ['success' => true, 'redirect' => 'login.php'];
    }

    private function buildBaseUrl(): string {
        $configuredBaseUrl = trim((string)(getenv('APP_BASE_URL') ?: ''));
        if ($configuredBaseUrl !== '') {
            return rtrim($configuredBaseUrl, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto === 'https') {
            $scheme = 'https';
        }

        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return rtrim($scheme . '://' . preg_replace('/:(80|443)$/', '', $host), '/');
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

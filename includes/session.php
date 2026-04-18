<?php
function hm_is_https_request(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto === 'https') {
        return true;
    }

    return (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function hm_is_local_host(string $host): bool {
    $host = strtolower(trim($host));
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || str_ends_with($host, '.local');
}

function hm_host_without_default_port(string $host): string {
    return preg_replace('/:(80|443)$/', '', $host) ?? $host;
}

function hm_should_force_https(): bool {
    $configured = getenv('APP_FORCE_HTTPS');
    if ($configured === false || $configured === '') {
        return false;
    }

    return filter_var($configured, FILTER_VALIDATE_BOOL);
}

if (hm_should_force_https() && !hm_is_https_request()) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $host = hm_host_without_default_port((string)($_SERVER['HTTP_HOST'] ?? ''));
    header('Location: https://' . $host . $requestUri, true, 301);
    exit;
}

if (hm_is_https_request()) {
    ini_set('session.cookie_secure', '1');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$scheme = hm_is_https_request() ? 'https' : 'http';
$baseUrl = rtrim($scheme . '://' . hm_host_without_default_port((string)($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');

<?php
// Database configuration
// TODO: Update with Oracle credentials when available
define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

// Set to true once Oracle credentials are configured
define('DB_ENABLED', false);

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): ?PDO {
        if (!DB_ENABLED) return null;

        if (self::$instance === null) {
            // TODO: Switch to Oracle DSN when credentials are ready
            // $dsn = 'oci:dbname=//' . DB_HOST . '/' . DB_NAME;
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                return null;
            }
        }
        return self::$instance;
    }

    public static function isEnabled(): bool {
        return DB_ENABLED;
    }
}

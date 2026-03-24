<?php

$localConfig = [];
$localConfigPath = __DIR__ . '/db.local.php';
if (is_file($localConfigPath)) {
    $loaded = require $localConfigPath;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

function db_config_value(string $key, mixed $default = ''): mixed {
    global $localConfig;

    $envValue = getenv($key);
    if ($envValue !== false && $envValue !== '') {
        return $envValue;
    }

    return $localConfig[$key] ?? $default;
}

define('DB_DRIVER', (string)db_config_value('DB_DRIVER', 'mysql'));
define('DB_HOST', (string)db_config_value('DB_HOST', ''));
define('DB_PORT', (string)db_config_value('DB_PORT', DB_DRIVER === 'mysql' ? '3306' : ''));
define('DB_NAME', (string)db_config_value('DB_NAME', ''));
define('DB_USER', (string)db_config_value('DB_USER', ''));
define('DB_PASS', (string)db_config_value('DB_PASS', ''));
define('DB_CHARSET', (string)db_config_value('DB_CHARSET', 'utf8mb4'));
define('DB_ENABLED', filter_var(
    db_config_value('DB_ENABLED', DB_HOST !== '' && DB_NAME !== '' && DB_USER !== ''),
    FILTER_VALIDATE_BOOL
));

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): ?PDO {
        if (!DB_ENABLED) {
            return null;
        }

        if (self::$instance === null) {
            if (DB_DRIVER === 'oci') {
                $portSegment = DB_PORT !== '' ? ':' . DB_PORT : '';
                $dsn = 'oci:dbname=//' . DB_HOST . $portSegment . '/' . DB_NAME;
            } else {
                $portSegment = DB_PORT !== '' ? ';port=' . DB_PORT : '';
                $charsetSegment = DB_CHARSET !== '' ? ';charset=' . DB_CHARSET : '';
                $dsn = 'mysql:host=' . DB_HOST . $portSegment . ';dbname=' . DB_NAME . $charsetSegment;
            }

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException) {
                return null;
            }
        }

        return self::$instance;
    }

    public static function isEnabled(): bool {
        return DB_ENABLED;
    }
}

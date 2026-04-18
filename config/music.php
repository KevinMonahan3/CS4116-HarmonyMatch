<?php

$musicLocalConfig = [];
$candidatePaths = array_filter([
    getenv('MUSIC_CONFIG_PATH') ?: null,
    __DIR__ . '/music.local.php',
    '/etc/harmonymatch/music.local.php',
    '/var/www/harmonymatch-shared/music.local.php',
]);

foreach ($candidatePaths as $musicLocalConfigPath) {
    if (!is_file($musicLocalConfigPath)) {
        continue;
    }

    $loaded = require $musicLocalConfigPath;
    if (is_array($loaded)) {
        $musicLocalConfig = array_merge($musicLocalConfig, $loaded);
    }
}

function music_config_value(string $key, mixed $default = null): mixed {
    global $musicLocalConfig;

    $envValue = getenv($key);
    if ($envValue !== false && $envValue !== '') {
        return $envValue;
    }

    return $musicLocalConfig[$key] ?? $default;
}

define('LASTFM_API_KEY', (string)music_config_value('LASTFM_API_KEY', ''));
define('SPOTIFY_CLIENT_ID', (string)music_config_value('SPOTIFY_CLIENT_ID', ''));
define('SPOTIFY_CLIENT_SECRET', (string)music_config_value('SPOTIFY_CLIENT_SECRET', ''));
define('SPOTIFY_MARKET', (string)music_config_value('SPOTIFY_MARKET', 'IE'));
define('MUSIC_HTTP_TIMEOUT', (int)music_config_value('MUSIC_HTTP_TIMEOUT', 5));
define('MUSIC_CACHE_TTL', (int)music_config_value('MUSIC_CACHE_TTL', 86400));
define(
    'MUSIC_USER_AGENT',
    (string)music_config_value(
        'MUSIC_USER_AGENT',
        'HarmonyMatch/1.0 (student project contact: admin@harmonymatch.local)'
    )
);

<?php

$resendConfigValue = static function (string $key, mixed $default = ''): mixed {
    $envValue = getenv($key);
    if ($envValue !== false && $envValue !== '') {
        return $envValue;
    }

    $resendLocalConfig = [];
    $resendLocalConfigPath = __DIR__ . '/resend.local.php';
    if (is_file($resendLocalConfigPath)) {
        $loaded = require $resendLocalConfigPath;
        if (is_array($loaded)) {
            $resendLocalConfig = $loaded;
        }
    }

    return $resendLocalConfig[$key] ?? $default;
};

return [
    'api_key' => (string)$resendConfigValue('RESEND_API_KEY', ''),
    'from' => (string)$resendConfigValue('RESEND_FROM_EMAIL', 'HarmonyMatch <onboarding@resend.dev>'),
    'reply_to' => (string)$resendConfigValue('RESEND_REPLY_TO', ''),
    'timeout' => (int)$resendConfigValue('RESEND_HTTP_TIMEOUT', 10),
];

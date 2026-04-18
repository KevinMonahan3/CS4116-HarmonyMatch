<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$appRoot = realpath(__DIR__ . '/..');
if ($appRoot === false) {
    fwrite(STDERR, "Unable to resolve app root.\n");
    exit(1);
}

$baseUrl = 'http://127.0.0.1:8091';
$serverLog = sys_get_temp_dir() . '/harmonymatch_smoke_server.log';
$serverCommand = sprintf('php -S 127.0.0.1:8091 -t %s > %s 2>&1', escapeshellarg($appRoot), escapeshellarg($serverLog));
$process = proc_open($serverCommand, [], $pipes);
if (!is_resource($process)) {
    fwrite(STDERR, "Unable to start built-in PHP server.\n");
    exit(1);
}

usleep(1200000);

register_shutdown_function(static function () use ($process): void {
    if (is_resource($process)) {
        proc_terminate($process);
    }
});

function assertTrue(bool $condition, string $label, string $details = ''): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        if ($details !== '') {
            fwrite(STDERR, $details . "\n");
        }
        exit(1);
    }
    echo "PASS: {$label}\n";
}

function requestJson(string $method, string $url, array $data = [], string $cookie = ''): array {
    $headers = ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }

    $options = [
        'http' => [
            'method' => strtoupper($method),
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
            'timeout' => 10,
        ],
    ];

    if (strtoupper($method) === 'POST') {
        $options['http']['content'] = http_build_query($data);
    } elseif ($data !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($data);
    }

    $context = stream_context_create($options);
    $body = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $setCookie = '';
    foreach ($responseHeaders as $header) {
        if (stripos($header, 'Set-Cookie:') === 0) {
            $cookiePart = trim(substr($header, strlen('Set-Cookie:')));
            $setCookie = explode(';', $cookiePart, 2)[0];
            break;
        }
    }

    return [
        'body' => $body === false ? '' : $body,
        'json' => json_decode($body === false ? '' : $body, true),
        'cookie' => $setCookie,
        'headers' => $responseHeaders,
    ];
}

function createAdmin(string $email, string $password): void {
    $db = Database::getConnection();
    assertTrue($db !== null, 'database connection for smoke test');

    $stmt = $db->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        return;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO users (email, password_hash, role, status, created_at, updated_at)
             VALUES (?, ?, "admin", "active", NOW(), NOW())'
        );
        $stmt->execute([$email, password_hash($password, PASSWORD_BCRYPT)]);
        $adminId = (int)$db->lastInsertId();

        $stmt = $db->prepare(
            'INSERT INTO profiles (user_id, display_name, bio, birth_year, visibility, updated_at)
             VALUES (?, ?, ?, ?, "public", NOW())'
        );
        $stmt->execute([$adminId, 'Smoke Admin', 'Temporary admin for smoke tests.', 1995]);

        $stmt = $db->prepare(
            'INSERT INTO user_preferences (user_id, gender_id, seeking_type, min_age_pref, max_age_pref, updated_at)
             VALUES (?, NULL, "dating", 18, 40, NOW())'
        );
        $stmt->execute([$adminId]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function genreIds(array $names): string {
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT genre_id FROM genres WHERE name = ? LIMIT 1');
    $ids = [];
    foreach ($names as $name) {
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            $ids[] = (string)$id;
        }
    }
    return implode(',', $ids);
}

$suffix = (string)time();
$adminEmail = "admin.smoke.{$suffix}@harmonymatch.local";
$adminPassword = 'AdminPass1!';
createAdmin($adminEmail, $adminPassword);

$userPassword = 'SmokePass1!';
$userAEmail = "smoke.a.{$suffix}@harmonymatch.local";
$userBEmail = "smoke.b.{$suffix}@harmonymatch.local";

$userA = requestJson('POST', $baseUrl . '/api/auth.php?action=register', [
    'email' => $userAEmail,
    'password' => $userPassword,
    'name' => 'Smoke Alpha',
    'dob' => '2000-01-02',
    'gender' => 'male',
]);
assertTrue(($userA['json']['success'] ?? false) === true, 'registration user A', $userA['body']);
$cookieA = $userA['cookie'];
$userAId = (int)($userA['json']['user_id'] ?? 0);

$userB = requestJson('POST', $baseUrl . '/api/auth.php?action=register', [
    'email' => $userBEmail,
    'password' => $userPassword,
    'name' => 'Smoke Beta',
    'dob' => '1999-03-04',
    'gender' => 'female',
]);
assertTrue(($userB['json']['success'] ?? false) === true, 'registration user B', $userB['body']);
$cookieB = $userB['cookie'];
$userBId = (int)($userB['json']['user_id'] ?? 0);

requestJson('POST', $baseUrl . '/api/users.php?action=update_profile', [
    'name' => 'Smoke Alpha',
    'bio' => 'Indie listener for smoke testing.',
    'location' => 'Dublin, Ireland',
], $cookieA);
requestJson('POST', $baseUrl . '/api/users.php?action=update_profile', [
    'name' => 'Smoke Beta',
    'bio' => 'Pop listener for smoke testing.',
    'location' => 'Cork, Ireland',
], $cookieB);

$musicA = requestJson('POST', $baseUrl . '/api/users.php?action=onboarding_music', [
    'genres' => genreIds(['Indie Rock', 'Alternative Rock', 'Pop']),
    'artists' => ['Arctic Monkeys', 'The 1975'],
    'songs' => json_encode([
        ['title' => 'Do I Wanna Know?', 'artist' => 'Arctic Monkeys'],
        ['title' => 'About You', 'artist' => 'The 1975'],
    ], JSON_UNESCAPED_SLASHES),
], $cookieA);
assertTrue(($musicA['json']['success'] ?? false) === true, 'onboarding music user A', $musicA['body']);

$musicB = requestJson('POST', $baseUrl . '/api/users.php?action=onboarding_music', [
    'genres' => genreIds(['Pop', 'Dance', 'Electronic']),
    'artists' => ['Taylor Swift', 'Dua Lipa'],
    'songs' => json_encode([
        ['title' => 'Style', 'artist' => 'Taylor Swift'],
        ['title' => 'Levitating', 'artist' => 'Dua Lipa'],
    ], JSON_UNESCAPED_SLASHES),
], $cookieB);
assertTrue(($musicB['json']['success'] ?? false) === true, 'onboarding music user B', $musicB['body']);

assertTrue((requestJson('POST', $baseUrl . '/api/users.php?action=complete_onboarding', [], $cookieA)['json']['success'] ?? false) === true, 'complete onboarding user A');
assertTrue((requestJson('POST', $baseUrl . '/api/users.php?action=complete_onboarding', [], $cookieB)['json']['success'] ?? false) === true, 'complete onboarding user B');

$search = requestJson('GET', $baseUrl . '/api/users.php', [
    'action' => 'search',
    'query' => 'Smoke Beta',
    'min_age' => 18,
    'max_age' => 40,
    'min_compatibility' => 0,
], $cookieA);
assertTrue(str_contains($search['body'], 'Smoke Beta'), 'search returns other user', $search['body']);

$likeA = requestJson('POST', $baseUrl . '/api/matches.php', [
    'action' => 'swipe',
    'to_user_id' => $userBId,
    'action_type' => 'like',
], $cookieA);
assertTrue(($likeA['json']['is_match'] ?? null) === false, 'first like is pending', $likeA['body']);

$likeB = requestJson('POST', $baseUrl . '/api/matches.php', [
    'action' => 'swipe',
    'to_user_id' => $userAId,
    'action_type' => 'like',
], $cookieB);
assertTrue(($likeB['json']['is_match'] ?? null) === true, 'mutual like becomes match', $likeB['body']);

$messageOk = requestJson('POST', $baseUrl . '/api/messages.php', [
    'action' => 'send',
    'to_user_id' => $userBId,
    'content' => 'Hey from the smoke test',
], $cookieA);
assertTrue(($messageOk['json']['success'] ?? false) === true, 'matched users can message', $messageOk['body']);

$messagePhone = requestJson('POST', $baseUrl . '/api/messages.php', [
    'action' => 'send',
    'to_user_id' => $userBId,
    'content' => 'Call me on 0871234567',
], $cookieA);
assertTrue(str_contains($messagePhone['body'], 'Phone numbers are not allowed in chat'), 'phone number blocked in chat', $messagePhone['body']);

$block = requestJson('POST', $baseUrl . '/api/reports.php', [
    'action' => 'block',
    'blocked_id' => $userAId,
], $cookieB);
assertTrue(($block['json']['success'] ?? false) === true, 'block user', $block['body']);

$messageBlocked = requestJson('POST', $baseUrl . '/api/messages.php', [
    'action' => 'send',
    'to_user_id' => $userBId,
    'content' => 'You should not receive this',
], $cookieA);
assertTrue(str_contains($messageBlocked['body'], 'Users cannot message each other'), 'blocked users cannot message', $messageBlocked['body']);

$report = requestJson('POST', $baseUrl . '/api/reports.php', [
    'action' => 'report',
    'reported_id' => $userAId,
    'reason' => 'harassment in test flow',
], $cookieB);
assertTrue(($report['json']['success'] ?? false) === true, 'report flow submit', $report['body']);
$reportId = (int)($report['json']['report_id'] ?? 0);

$adminLogin = requestJson('POST', $baseUrl . '/api/auth.php?action=login', [
    'email' => $adminEmail,
    'password' => $adminPassword,
]);
assertTrue(($adminLogin['json']['success'] ?? false) === true, 'admin login', $adminLogin['body']);
$cookieAdmin = $adminLogin['cookie'];

$resolve = requestJson('POST', $baseUrl . '/api/admin.php', [
    'action' => 'resolve_report',
    'report_id' => $reportId,
    'resolution' => 'actioned',
], $cookieAdmin);
assertTrue(($resolve['json']['success'] ?? false) === true, 'admin resolves report and suspends user', $resolve['body']);

$suspendedLogin = requestJson('POST', $baseUrl . '/api/auth.php?action=login', [
    'email' => $userAEmail,
    'password' => $userPassword,
]);
assertTrue(str_contains($suspendedLogin['body'], 'Account suspended'), 'suspended user cannot log in', $suspendedLogin['body']);

echo "Smoke test completed successfully.\n";

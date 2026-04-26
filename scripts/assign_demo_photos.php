<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
if (!$db) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

$sourceArg = $argv[1] ?? 'avatars';
$sourceDir = realpath($sourceArg);
if ($sourceDir === false) {
    $sourceDir = realpath(__DIR__ . '/../' . $sourceArg);
}
if ($sourceDir === false) {
    $sourceDir = realpath(__DIR__ . '/../assets/img/uploads');
}
if ($sourceDir === false) {
    fwrite(STDERR, "Source folder not found. Pass a folder path, or add images to avatars/.\n");
    exit(1);
}

$uploadDir = realpath(__DIR__ . '/../assets/img/uploads');
if ($uploadDir === false) {
    $uploadDir = __DIR__ . '/../assets/img/uploads';
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        fwrite(STDERR, "Unable to create uploads directory.\n");
        exit(1);
    }
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
$files = array_values(array_filter(glob($sourceDir . '/*') ?: [], static function (string $path) use ($allowedExtensions): bool {
    if (!is_file($path)) {
        return false;
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($extension, $allowedExtensions, true);
}));

sort($files, SORT_NATURAL | SORT_FLAG_CASE);
$files = array_slice($files, 0, 10);

if (count($files) < 1) {
    fwrite(STDERR, "No images found in {$sourceDir}.\n");
    exit(1);
}

$users = $db->query(
    'SELECT u.user_id, p.display_name
     FROM users u
     LEFT JOIN profiles p ON p.user_id = u.user_id
     WHERE u.role = "user"
       AND u.status = "active"
     ORDER BY u.user_id
     LIMIT 10'
)->fetchAll();

if (count($users) < count($files)) {
    fwrite(STDERR, "Only found " . count($users) . " active users for " . count($files) . " images.\n");
    exit(1);
}

$deleteExisting = $db->prepare('DELETE FROM user_photos WHERE user_id = ?');
$insertPhoto = $db->prepare(
    'INSERT INTO user_photos (user_id, photo_url, is_primary, display_order, created_at)
     VALUES (?, ?, 1, 1, NOW())'
);

$assigned = 0;
$db->beginTransaction();
try {
    foreach ($files as $index => $sourcePath) {
        $user = $users[$index];
        $userId = (int)$user['user_id'];
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $targetName = 'demo-user-' . $userId . '.' . $extension;
        $targetPath = $uploadDir . '/' . $targetName;
        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException('Unable to copy ' . basename($sourcePath));
        }

        $deleteExisting->execute([$userId]);
        $insertPhoto->execute([$userId, '/assets/img/uploads/' . $targetName]);

        echo 'Assigned ' . basename($targetPath) . ' to user #' . $userId . ' ' . ($user['display_name'] ?? '') . PHP_EOL;
        $assigned++;
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "Failed assigning demo photos: {$e->getMessage()}\n");
    exit(1);
}

echo "Assigned {$assigned} demo photo(s).\n";

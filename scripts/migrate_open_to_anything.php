<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
if (!$db) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

$column = $db->query("SHOW COLUMNS FROM user_preferences LIKE 'seeking_type'")->fetch();
if (!$column) {
    fwrite(STDERR, "user_preferences.seeking_type column not found.\n");
    exit(1);
}

$type = (string)($column['Type'] ?? '');
if (str_contains($type, "'open_to_anything'")) {
    echo "seeking_type already supports open_to_anything.\n";
    exit(0);
}

$db->exec(
    "ALTER TABLE user_preferences
     MODIFY seeking_type ENUM('open_to_anything','friendship','dating','networking','music_buddy') DEFAULT 'dating'"
);

echo "Updated seeking_type enum with open_to_anything.\n";

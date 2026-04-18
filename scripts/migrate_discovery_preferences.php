<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
if (!$db) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

$columns = $db->query('SHOW COLUMNS FROM user_preferences')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('desired_gender', $columns, true)) {
    $db->exec(
        "ALTER TABLE user_preferences
         ADD COLUMN desired_gender ENUM('male','female','non_binary','other','everyone')
         NOT NULL DEFAULT 'everyone'
         AFTER max_age_pref"
    );
    echo "Added desired_gender.\n";
}

if (!in_array('location_scope', $columns, true)) {
    $db->exec(
        "ALTER TABLE user_preferences
         ADD COLUMN location_scope ENUM('anywhere','same_country','same_city')
         NOT NULL DEFAULT 'anywhere'
         AFTER desired_gender"
    );
    echo "Added location_scope.\n";
}

$db->exec("UPDATE user_preferences SET seeking_type = 'dating' WHERE seeking_type IS NULL");
$db->exec("UPDATE user_preferences SET min_age_pref = 18 WHERE min_age_pref IS NULL OR min_age_pref < 18");
$db->exec("UPDATE user_preferences SET max_age_pref = 40 WHERE max_age_pref IS NULL OR max_age_pref < min_age_pref");
$db->exec("UPDATE user_preferences SET desired_gender = 'everyone' WHERE desired_gender IS NULL");
$db->exec("UPDATE user_preferences SET location_scope = 'anywhere' WHERE location_scope IS NULL");

echo "Discovery preference migration complete.\n";

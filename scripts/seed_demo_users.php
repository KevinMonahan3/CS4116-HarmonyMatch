<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
if (!$db) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

$targetCount = 30;
$currentCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
if ($currentCount >= $targetCount) {
    echo "User count already meets target: {$currentCount}\n";
    exit(0);
}

$needed = $targetCount - $currentCount;
$genres = $db->query('SELECT genre_id FROM genres ORDER BY genre_id')->fetchAll(PDO::FETCH_COLUMN);
$locations = $db->query('SELECT location_id FROM locations ORDER BY location_id')->fetchAll(PDO::FETCH_COLUMN);
$genders = $db->query('SELECT gender_id FROM genders ORDER BY gender_id')->fetchAll(PDO::FETCH_COLUMN);

if (count($genres) < 3 || count($locations) < 1) {
    fwrite(STDERR, "Missing prerequisite genres or locations for seeding.\n");
    exit(1);
}

$firstNames = ['Jamie', 'Taylor', 'Morgan', 'Casey', 'Riley', 'Alex', 'Jordan', 'Avery', 'Quinn', 'Charlie'];
$lastNames = ['Byrne', 'Murphy', 'Kelly', 'Ryan', 'Walsh', 'Doyle', 'Keane', 'Foley', 'Hayes', 'Flynn'];
$bios = [
    'Always chasing the next perfect gig and a great coffee after.',
    'Playlist curator, vinyl browser, and unapologetic live-session fan.',
    'Looking for someone who will judge songs with me in the car.',
    'Into late-night walks, loud choruses, and discovering new artists.',
    'Happy when a bassline lands and the conversation keeps going.',
];

$insertUser = $db->prepare(
    'INSERT INTO users (email, password_hash, role, status, created_at, updated_at)
     VALUES (?, ?, "user", "active", NOW(), NOW())'
);
$insertProfile = $db->prepare(
    'INSERT INTO profiles (user_id, display_name, bio, birth_year, visibility, location_id, updated_at)
     VALUES (?, ?, ?, ?, "public", ?, NOW())'
);
$insertPreference = $db->prepare(
    'INSERT INTO user_preferences (user_id, gender_id, seeking_type, min_age_pref, max_age_pref, updated_at)
     VALUES (?, ?, "dating", 18, 40, NOW())'
);
$insertGenre = $db->prepare(
    'INSERT INTO user_genres (user_id, genre_id, rank_weight, created_at)
     VALUES (?, ?, ?, NOW())'
);

$passwordHash = password_hash('Harmony123!', PASSWORD_BCRYPT);
$created = 0;
$sequence = 1;

while ($created < $needed) {
    $first = $firstNames[array_rand($firstNames)];
    $last = $lastNames[array_rand($lastNames)];
    $displayName = "{$first} {$last}";
    $email = strtolower($first . '.' . $last . '.demo' . $sequence . '@harmonymatch.local');
    $sequence++;

    $existsStmt = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $existsStmt->execute([$email]);
    if ((int)$existsStmt->fetchColumn() > 0) {
        continue;
    }

    $birthYear = random_int(1994, 2005);
    $bio = $bios[array_rand($bios)];
    $locationId = (int)$locations[array_rand($locations)];
    $genderId = !empty($genders) ? (int)$genders[array_rand($genders)] : null;
    $genrePool = $genres;
    shuffle($genrePool);
    $selectedGenres = array_slice($genrePool, 0, 3);

    $db->beginTransaction();
    try {
        $insertUser->execute([$email, $passwordHash]);
        $userId = (int)$db->lastInsertId();

        $insertProfile->execute([$userId, $displayName, $bio, $birthYear, $locationId]);
        $insertPreference->execute([$userId, $genderId]);

        foreach ($selectedGenres as $index => $genreId) {
            $insertGenre->execute([$userId, (int)$genreId, $index + 1]);
        }

        $db->commit();
        $created++;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        fwrite(STDERR, "Failed creating {$email}: {$e->getMessage()}\n");
    }
}

echo "Created {$created} demo users. Current user count: " . ((int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn()) . "\n";
echo "Demo login password for seeded users: Harmony123!\n";

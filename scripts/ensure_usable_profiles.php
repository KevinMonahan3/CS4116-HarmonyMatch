<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../dal/MusicDAL.php';

$db = Database::getConnection();
if (!$db) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

$musicDAL = new MusicDAL();
$targetUsableProfiles = 30;

$bundles = [
    [
        'genres' => ['Indie Rock', 'Alternative Rock', 'Shoegaze'],
        'artists' => ['Arctic Monkeys', 'The 1975', 'Phoebe Bridgers'],
        'songs' => [
            ['title' => 'Do I Wanna Know?', 'artist' => 'Arctic Monkeys'],
            ['title' => 'About You', 'artist' => 'The 1975'],
            ['title' => 'Kyoto', 'artist' => 'Phoebe Bridgers'],
        ],
        'bio' => 'Always chasing a better live set and a better playlist.',
    ],
    [
        'genres' => ['Hip-Hop', 'R&B', 'Neo-Soul'],
        'artists' => ['Kendrick Lamar', 'SZA', 'Frank Ocean'],
        'songs' => [
            ['title' => 'Money Trees', 'artist' => 'Kendrick Lamar'],
            ['title' => 'Snooze', 'artist' => 'SZA'],
            ['title' => 'Lost', 'artist' => 'Frank Ocean'],
        ],
        'bio' => 'Late-night drives, sharp lyrics, and album deep-dives.',
    ],
    [
        'genres' => ['Pop', 'Dance', 'Electronic'],
        'artists' => ['Dua Lipa', 'Charli xcx', 'Taylor Swift'],
        'songs' => [
            ['title' => 'Levitating', 'artist' => 'Dua Lipa'],
            ['title' => '360', 'artist' => 'Charli xcx'],
            ['title' => 'Style', 'artist' => 'Taylor Swift'],
        ],
        'bio' => 'If the chorus hits, I am already adding it to the queue.',
    ],
    [
        'genres' => ['Electronic', 'House', 'Synthpop'],
        'artists' => ['Fred again..', 'Disclosure', 'Daft Punk'],
        'songs' => [
            ['title' => 'adore u', 'artist' => 'Fred again..'],
            ['title' => 'Latch', 'artist' => 'Disclosure'],
            ['title' => 'Instant Crush', 'artist' => 'Daft Punk'],
        ],
        'bio' => 'Festival energy with a soft spot for emotional synths.',
    ],
    [
        'genres' => ['Rock', 'Blues', 'Alternative'],
        'artists' => ['Fleetwood Mac', 'The Beatles', 'Led Zeppelin'],
        'songs' => [
            ['title' => 'Dreams', 'artist' => 'Fleetwood Mac'],
            ['title' => 'Something', 'artist' => 'The Beatles'],
            ['title' => 'Ramble On', 'artist' => 'Led Zeppelin'],
        ],
        'bio' => 'Old records, real instruments, and way too many favourites.',
    ],
];

$genreMap = [];
foreach ($db->query('SELECT genre_id, name FROM genres')->fetchAll(PDO::FETCH_ASSOC) as $genre) {
    $genreMap[strtolower($genre['name'])] = (int)$genre['genre_id'];
}

$locationIds = $db->query('SELECT location_id FROM locations ORDER BY location_id')->fetchAll(PDO::FETCH_COLUMN);
$genderIds = $db->query('SELECT gender_id FROM genders ORDER BY gender_id')->fetchAll(PDO::FETCH_COLUMN);

$usableUserIds = $db->query(
    'SELECT u.user_id
     FROM users u
     WHERE u.role = "user"
       AND EXISTS (SELECT 1 FROM user_genres ug WHERE ug.user_id = u.user_id)
       AND EXISTS (SELECT 1 FROM user_artists ua WHERE ua.user_id = u.user_id)
       AND EXISTS (SELECT 1 FROM user_songs us WHERE us.user_id = u.user_id)'
)->fetchAll(PDO::FETCH_COLUMN);

function pick_bundle(array $bundles, int $seed): array {
    return $bundles[$seed % count($bundles)];
}

function ensure_profile_basics(PDO $db, int $userId, array $bundle, array $locationIds, array $genderIds, int $seed): void {
    $profile = $db->prepare('SELECT user_id, bio, birth_year, location_id FROM profiles WHERE user_id = ? LIMIT 1');
    $profile->execute([$userId]);
    $existing = $profile->fetch(PDO::FETCH_ASSOC);

    $birthYear = 1994 + ($seed % 10);
    $locationId = !empty($locationIds) ? (int)$locationIds[$seed % count($locationIds)] : null;

    if ($existing) {
        $stmt = $db->prepare(
            'UPDATE profiles
             SET bio = COALESCE(NULLIF(bio, ""), ?),
                 birth_year = COALESCE(birth_year, ?),
                 location_id = COALESCE(location_id, ?),
                 updated_at = NOW()
             WHERE user_id = ?'
        );
        $stmt->execute([$bundle['bio'], $birthYear, $locationId, $userId]);
    } else {
        $stmt = $db->prepare(
            'INSERT INTO profiles (user_id, display_name, bio, birth_year, visibility, location_id, updated_at)
             VALUES (?, ?, ?, ?, "public", ?, NOW())'
        );
        $stmt->execute([$userId, 'Demo User ' . $userId, $bundle['bio'], $birthYear, $locationId]);
    }

    $preference = $db->prepare('SELECT user_id FROM user_preferences WHERE user_id = ? LIMIT 1');
    $preference->execute([$userId]);
    if (!$preference->fetchColumn()) {
        $genderId = !empty($genderIds) ? (int)$genderIds[$seed % count($genderIds)] : null;
        $stmt = $db->prepare(
            'INSERT INTO user_preferences (user_id, gender_id, seeking_type, min_age_pref, max_age_pref, updated_at)
             VALUES (?, ?, "dating", 18, 40, NOW())'
        );
        $stmt->execute([$userId, $genderId]);
    }
}

function ensure_music_for_user(PDO $db, MusicDAL $musicDAL, int $userId, array $bundle, array $genreMap): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM user_genres WHERE user_id = ?');
    $stmt->execute([$userId]);
    $genreCount = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM user_artists WHERE user_id = ?');
    $stmt->execute([$userId]);
    $artistCount = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM user_songs WHERE user_id = ?');
    $stmt->execute([$userId]);
    $songCount = (int)$stmt->fetchColumn();

    if ($genreCount === 0) {
        $genreIds = [];
        foreach ($bundle['genres'] as $genreName) {
            $genreId = $genreMap[strtolower($genreName)] ?? null;
            if ($genreId !== null) {
                $genreIds[] = $genreId;
            }
        }
        if (count($genreIds) >= 2) {
            $musicDAL->setUserGenres($userId, $genreIds);
        }
    }

    if ($artistCount === 0) {
        $musicDAL->replaceUserArtists($userId, $bundle['artists']);
    }

    if ($songCount === 0) {
        $musicDAL->replaceUserSongs($userId, $bundle['songs']);
    }

    $stmt = $db->prepare(
        'SELECT
            EXISTS (SELECT 1 FROM user_genres ug WHERE ug.user_id = ?) AS has_genres,
            EXISTS (SELECT 1 FROM user_artists ua WHERE ua.user_id = ?) AS has_artists,
            EXISTS (SELECT 1 FROM user_songs us WHERE us.user_id = ?) AS has_songs'
    );
    $stmt->execute([$userId, $userId, $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return !empty($result['has_genres']) && !empty($result['has_artists']) && !empty($result['has_songs']);
}

$users = $db->query('SELECT user_id FROM users WHERE role = "user" ORDER BY user_id')->fetchAll(PDO::FETCH_COLUMN);
$usable = [];
$seed = 0;

foreach ($users as $userId) {
    $bundle = pick_bundle($bundles, $seed++);
    ensure_profile_basics($db, (int)$userId, $bundle, $locationIds, $genderIds, $seed);
    if (ensure_music_for_user($db, $musicDAL, (int)$userId, $bundle, $genreMap)) {
        $usable[(int)$userId] = true;
    }
}

$currentUsable = count($usable);
$passwordHash = password_hash('Harmony123!', PASSWORD_BCRYPT);
$firstNames = ['Jamie', 'Taylor', 'Morgan', 'Casey', 'Riley', 'Alex', 'Jordan', 'Avery', 'Quinn', 'Charlie', 'Mia', 'Ella'];
$lastNames = ['Byrne', 'Murphy', 'Kelly', 'Ryan', 'Walsh', 'Doyle', 'Keane', 'Foley', 'Hayes', 'Flynn', 'Olsen', 'Carter'];

$sequence = 1;
$attempts = 0;
while ($currentUsable < $targetUsableProfiles && $attempts < 200) {
    $attempts++;
    $bundle = pick_bundle($bundles, $seed++);
    $first = $firstNames[$seed % count($firstNames)];
    $last = $lastNames[$seed % count($lastNames)];
    $email = strtolower($first . '.' . $last . '.usable' . $sequence . '@harmonymatch.local');
    $sequence++;

    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ((int)$stmt->fetchColumn() > 0) {
        continue;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO users (email, password_hash, role, status, created_at, updated_at)
             VALUES (?, ?, "user", "active", NOW(), NOW())'
        );
        $stmt->execute([$email, $passwordHash]);
        $userId = (int)$db->lastInsertId();

        $locationId = !empty($locationIds) ? (int)$locationIds[$seed % count($locationIds)] : null;
        $birthYear = 1994 + ($seed % 10);
        $displayName = $first . ' ' . $last;

        $stmt = $db->prepare(
            'INSERT INTO profiles (user_id, display_name, bio, birth_year, visibility, location_id, updated_at)
             VALUES (?, ?, ?, ?, "public", ?, NOW())'
        );
        $stmt->execute([$userId, $displayName, $bundle['bio'], $birthYear, $locationId]);

        $genderId = !empty($genderIds) ? (int)$genderIds[$seed % count($genderIds)] : null;
        $stmt = $db->prepare(
            'INSERT INTO user_preferences (user_id, gender_id, seeking_type, min_age_pref, max_age_pref, updated_at)
             VALUES (?, ?, "dating", 18, 40, NOW())'
        );
        $stmt->execute([$userId, $genderId]);

        $db->commit();

        ensure_music_for_user($db, $musicDAL, $userId, $bundle, $genreMap);
        $usable[$userId] = true;
        $currentUsable = count($usable);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        fwrite(STDERR, "Failed creating usable profile {$email}: {$e->getMessage()}\n");
    }
}

echo "Usable profiles with genres/artists/songs: {$currentUsable}\n";
echo "Total user profiles: " . (int)$db->query('SELECT COUNT(*) FROM users WHERE role = "user"')->fetchColumn() . "\n";
echo "Seeded demo password for newly created usable users: Harmony123!\n";
if ($currentUsable < $targetUsableProfiles) {
    fwrite(STDERR, "Warning: target usable profile count was not reached.\n");
}

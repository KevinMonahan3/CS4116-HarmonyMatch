<?php
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$genres = [
    'Alternative',
    'Alternative Rock',
    'Ambient',
    'Blues',
    'Classical',
    'Country',
    'Dance',
    'Drum and Bass',
    'Dubstep',
    'EDM',
    'Electronic',
    'Folk',
    'Funk',
    'Garage Rock',
    'Gospel',
    'Grime',
    'Hip-Hop',
    'House',
    'Indie',
    'Indie Rock',
    'Jazz',
    'K-Pop',
    'Latin',
    'Lo-fi',
    'Metal',
    'Neo-Soul',
    'Pop',
    'Post-Punk',
    'Punk',
    'R&B',
    'Rap',
    'Reggae',
    'Rock',
    'Shoegaze',
    'Singer-Songwriter',
    'Soul',
    'Techno',
    'Trap',
];

$stmt = $pdo->prepare('INSERT INTO genres (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)');

$inserted = 0;
foreach ($genres as $genre) {
    $stmt->execute([$genre]);
    $inserted++;
}

echo "Seeded or updated {$inserted} genres.\n";

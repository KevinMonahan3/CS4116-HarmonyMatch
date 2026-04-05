<?php
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$cities = [
    ['Dublin', 'Ireland'],
    ['Cork', 'Ireland'],
    ['Limerick', 'Ireland'],
    ['Galway', 'Ireland'],
    ['Waterford', 'Ireland'],
    ['Kilkenny', 'Ireland'],
    ['Sligo', 'Ireland'],
    ['Drogheda', 'Ireland'],
    ['Athlone', 'Ireland'],
    ['Belfast', 'United Kingdom'],
    ['Derry', 'United Kingdom'],
    ['London', 'United Kingdom'],
    ['Manchester', 'United Kingdom'],
    ['Liverpool', 'United Kingdom'],
    ['Birmingham', 'United Kingdom'],
    ['Edinburgh', 'United Kingdom'],
    ['Glasgow', 'United Kingdom'],
    ['Paris', 'France'],
    ['Berlin', 'Germany'],
    ['Amsterdam', 'Netherlands'],
    ['Madrid', 'Spain'],
    ['Barcelona', 'Spain'],
    ['Rome', 'Italy'],
    ['Milan', 'Italy'],
    ['Lisbon', 'Portugal'],
    ['Prague', 'Czech Republic'],
    ['Vienna', 'Austria'],
    ['Brussels', 'Belgium'],
    ['Copenhagen', 'Denmark'],
    ['Stockholm', 'Sweden'],
    ['Oslo', 'Norway'],
    ['Warsaw', 'Poland'],
    ['Budapest', 'Hungary'],
    ['New York', 'United States'],
    ['Los Angeles', 'United States'],
    ['Chicago', 'United States'],
    ['Toronto', 'Canada'],
    ['Sydney', 'Australia'],
    ['Melbourne', 'Australia'],
];

$select = $pdo->prepare('SELECT location_id FROM locations WHERE city = ? AND country = ? LIMIT 1');
$selectBlankCountry = $pdo->prepare('SELECT location_id FROM locations WHERE city = ? AND country = "" LIMIT 1');
$updateCountry = $pdo->prepare('UPDATE locations SET country = ? WHERE location_id = ?');
$insert = $pdo->prepare(
    'INSERT INTO locations (city, country, latitude, longitude) VALUES (?, ?, NULL, NULL)'
);

$added = 0;
$updated = 0;
foreach ($cities as [$city, $country]) {
    $select->execute([$city, $country]);
    if ($select->fetchColumn() !== false) {
        continue;
    }

    $selectBlankCountry->execute([$city]);
    $blankCountryId = $selectBlankCountry->fetchColumn();
    if ($blankCountryId !== false) {
        $updateCountry->execute([$country, (int)$blankCountryId]);
        $updated++;
        continue;
    }

    $insert->execute([$city, $country]);
    $added++;
}

echo "Added {$added} city rows, updated {$updated} existing rows.\n";

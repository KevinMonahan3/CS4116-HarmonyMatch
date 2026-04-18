<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/MusicController.php';

header('Content-Type: application/json');
AuthController::requireLogin();

$action = $_REQUEST['action'] ?? '';
$ctrl = new MusicController();

switch ($action) {
    case 'search_artist':
        echo json_encode($ctrl->searchArtists((string)($_GET['query'] ?? $_POST['query'] ?? '')));
        break;

    case 'search_track':
        echo json_encode($ctrl->searchTracks(
            (string)($_GET['query'] ?? $_POST['query'] ?? ''),
            (string)($_GET['artist'] ?? $_POST['artist'] ?? '')
        ));
        break;

    case 'artist_enrichment':
        echo json_encode($ctrl->enrichArtist((string)($_GET['artist'] ?? $_POST['artist'] ?? '')));
        break;

    case 'spotify_embed':
        echo json_encode($ctrl->spotifyEmbed(
            (string)($_GET['track'] ?? $_POST['track'] ?? ''),
            (string)($_GET['artist'] ?? $_POST['artist'] ?? '')
        ));
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

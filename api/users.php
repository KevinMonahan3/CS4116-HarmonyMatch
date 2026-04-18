<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../dal/MusicDAL.php';

header('Content-Type: application/json');
AuthController::requireLogin();

$userId = (int)$_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';
$ctrl   = new UserController();

switch ($action) {
    case 'genres':
        echo json_encode((new MusicDAL())->getAllGenres());
        break;

    case 'genders':
        echo json_encode($ctrl->getGenderOptions());
        break;

    case 'profile':
        $targetId = (int)($_GET['id'] ?? $userId);
        echo json_encode($ctrl->getProfile($targetId));
        break;

    case 'search_locations':
        echo json_encode($ctrl->searchLocations((string)($_GET['query'] ?? $_POST['query'] ?? '')));
        break;

    case 'search':
        echo json_encode($ctrl->search($userId, [
            'query' => (string)($_GET['query'] ?? $_POST['query'] ?? ''),
            'genre_id' => (int)($_GET['genre_id'] ?? $_POST['genre_id'] ?? 0),
            'gender' => (string)($_GET['gender'] ?? $_POST['gender'] ?? ''),
            'min_age' => (int)($_GET['min_age'] ?? $_POST['min_age'] ?? 18),
            'max_age' => (int)($_GET['max_age'] ?? $_POST['max_age'] ?? 60),
            'min_compatibility' => (float)($_GET['min_compatibility'] ?? $_POST['min_compatibility'] ?? 0),
        ]));
        break;

    case 'update_profile':
        echo json_encode($ctrl->updateProfile($userId, $_POST));
        break;

    case 'onboarding_music':
    case 'update_music':
        $genresInput = $_POST['genres'] ?? [];
        if (!is_array($genresInput)) {
            $genresInput = $genresInput === '' ? [] : explode(',', (string)$genresInput);
        }

        $artistsInput = $_POST['artists'] ?? [];
        if (!is_array($artistsInput)) {
            $artistsInput = $artistsInput === '' ? [] : explode(',', (string)$artistsInput);
        }

        $songsInput = $_POST['songs'] ?? [];
        if (is_string($songsInput)) {
            $decodedSongs = json_decode($songsInput, true);
            if (is_array($decodedSongs)) {
                $songsInput = $decodedSongs;
            } else {
                $songsInput = $songsInput === '' ? [] : explode(',', $songsInput);
            }
        }

        $genreIds = array_map('intval', $genresInput);
        $artists  = array_values(array_filter(array_map('trim', $artistsInput), fn($artist) => $artist !== ''));
        $songs    = is_array($songsInput) ? $songsInput : [];
        echo json_encode($ctrl->saveOnboardingStep2($userId, $genreIds, $artists, $songs));
        break;

    case 'complete_onboarding':
        echo json_encode($ctrl->completeOnboarding($userId));
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}

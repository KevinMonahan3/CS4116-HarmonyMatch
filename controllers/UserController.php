<?php
require_once __DIR__ . '/../dal/UserDAL.php';
require_once __DIR__ . '/../dal/MusicDAL.php';
require_once __DIR__ . '/MatchController.php';

class UserController {
    private UserDAL $userDAL;
    private MusicDAL $musicDAL;
    private MatchController $matchController;

    public function __construct() {
        $this->userDAL = new UserDAL();
        $this->musicDAL = new MusicDAL();
        $this->matchController = new MatchController();
    }

    public function getProfile(int $userId): array|false {
        $user = $this->userDAL->getUserById($userId);
        if (!$user) return false;

        $user['genres']  = $this->musicDAL->getUserGenres($userId);
        $user['artists'] = $this->musicDAL->getUserArtists($userId);
        $user['songs']   = $this->musicDAL->getUserSongs($userId);
        return $user;
    }

    public function getGenderOptions(): array {
        return $this->userDAL->getAllGenders();
    }

    public function searchLocations(string $query): array {
        $query = trim($query);
        if ($query === '') {
            return ['success' => false, 'error' => 'Missing query'];
        }

        return [
            'success' => true,
            'results' => $this->userDAL->searchLocations($query),
        ];
    }

    public function search(int $currentUserId, array $filters): array {
        $results = $this->userDAL->searchUsers([
            'exclude_id' => $currentUserId,
            'query' => trim((string)($filters['query'] ?? '')),
            'genre_id' => (int)($filters['genre_id'] ?? 0),
            'gender' => trim((string)($filters['gender'] ?? '')),
        ]);

        $minAge = max(18, (int)($filters['min_age'] ?? 18));
        $maxAge = max($minAge, min(100, (int)($filters['max_age'] ?? 100)));
        $minCompatibility = max(0, min(100, (float)($filters['min_compatibility'] ?? 0)));
        $currentYear = (int)date('Y');

        $filtered = [];
        foreach ($results as $user) {
            $birthYear = isset($user['birth_year']) ? (int)$user['birth_year'] : 0;
            $age = $birthYear > 0 ? $currentYear - $birthYear : null;

            if ($age !== null && ($age < $minAge || $age > $maxAge)) {
                continue;
            }

            $insights = $this->matchController->getCompatibilityInsights($currentUserId, (int)$user['id']);
            $compatibility = (float)$insights['score'];
            if ($compatibility < $minCompatibility) {
                continue;
            }
            if (!$this->matchController->isDiscoverEligible($currentUserId, $user)) {
                continue;
            }

            $user['age'] = $age;
            $user['compatibility'] = $compatibility;
            $user['match_reason'] = $insights['reason'];
            $user['shared_summary'] = $insights['summary'];
            $user['shared_genres'] = $insights['shared_genres'];
            $user['shared_artists'] = $insights['shared_artists'];
            $user['genres'] = $this->musicDAL->getUserGenres((int)$user['id']);
            $user['top_artist'] = $this->musicDAL->getUserArtists((int)$user['id'])[0]['name'] ?? null;
            $filtered[] = $user;
        }

        usort($filtered, static fn(array $a, array $b): int => $b['compatibility'] <=> $a['compatibility']);

        return $filtered;
    }

    public function updateProfile(int $userId, array $data): array {
        $allowed = [
            'name',
            'bio',
            'location',
            'profile_photo',
            'dob',
            'gender',
            'desired_gender',
            'seeking_type',
            'min_age_pref',
            'max_age_pref',
            'location_scope',
        ];
        $filtered = array_intersect_key($data, array_flip($allowed));

        if (!empty($filtered['name']) && strlen(trim((string)$filtered['name'])) > 80) {
            return ['success' => false, 'error' => 'Display name must be 80 characters or fewer'];
        }

        if (!empty($filtered['bio']) && strlen(trim((string)$filtered['bio'])) > 1000) {
            return ['success' => false, 'error' => 'Bio must be 1000 characters or fewer'];
        }

        if (!$this->userDAL->updateProfile($userId, $filtered)) {
            return ['success' => false, 'error' => 'Unable to save profile changes'];
        }

        if (!empty($filtered['name'])) {
            $_SESSION['user_name'] = trim((string)$filtered['name']);
        }

        return ['success' => true];
    }

    public function saveOnboardingStep2(int $userId, array $genreIds, array $artists, array $songs): array {
        $genreIds = array_values(array_unique(array_filter(array_map('intval', $genreIds))));
        if (count($genreIds) < 2) {
            return ['success' => false, 'error' => 'Please choose at least 2 genres'];
        }

        $artists = array_values(array_unique(array_filter(array_map(
            static fn($artist) => trim((string)$artist),
            $artists
        ))));

        $normalizedSongs = [];
        foreach ($songs as $song) {
            if (is_string($song)) {
                $parts = preg_split('/\s+[–-]\s+/', trim($song), 2);
                $normalizedSongs[] = [
                    'title' => trim((string)($parts[0] ?? '')),
                    'artist' => trim((string)($parts[1] ?? '')),
                ];
                continue;
            }

            if (is_array($song)) {
                $normalizedSongs[] = [
                    'title' => trim((string)($song['title'] ?? '')),
                    'artist' => trim((string)($song['artist'] ?? '')),
                ];
            }
        }

        $this->musicDAL->setUserGenres($userId, $genreIds);
        $this->musicDAL->replaceUserArtists($userId, $artists);
        $this->musicDAL->replaceUserSongs($userId, $normalizedSongs);

        return ['success' => true];
    }

    public function uploadPhoto(int $userId, array $file): array {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload failed'];
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowedMimes, true)) {
            return ['success' => false, 'error' => 'Only JPEG, PNG, WebP, or GIF images are allowed'];
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Image must be under 5 MB'];
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        };

        $uploadDir = __DIR__ . '/../assets/img/uploads/';
        $filename  = 'user_' . $userId . '.' . $ext;
        $destPath  = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'error' => 'Could not save image'];
        }

        $photoUrl = '/assets/img/uploads/' . $filename;
        $this->userDAL->updateProfile($userId, ['profile_photo' => $photoUrl]);

        return ['success' => true, 'photo_url' => $photoUrl];
    }

    public function completeOnboarding(int $userId): array {
        $this->userDAL->updateOnboardingComplete($userId);
        return ['success' => true, 'redirect' => 'dashboard.php'];
    }
}

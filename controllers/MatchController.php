<?php
require_once __DIR__ . '/../dal/MatchDAL.php';
require_once __DIR__ . '/../dal/MusicDAL.php';
require_once __DIR__ . '/../dal/UserDAL.php';

class MatchController {
    private MatchDAL $matchDAL;
    private MusicDAL $musicDAL;
    private UserDAL $userDAL;

    public function __construct() {
        $this->matchDAL = new MatchDAL();
        $this->musicDAL = new MusicDAL();
        $this->userDAL = new UserDAL();
    }

    public function swipe(int $fromUserId, int $toUserId, string $action): array {
        $this->matchDAL->recordSwipe($fromUserId, $toUserId, $action);

        $isMatch = false;
        if ($action === 'like' && $this->matchDAL->checkMatch($fromUserId, $toUserId)) {
            $this->matchDAL->createMatch($fromUserId, $toUserId);
            $isMatch = true;
        }
        return ['success' => true, 'is_match' => $isMatch];
    }

    public function resetSkips(int $userId): void {
        $this->matchDAL->resetSkips($userId);
    }
        

    public function getDashboardMatches(int $userId): array {
        $candidates = $this->matchDAL->getPotentialMatches($userId);
        $viewer = $this->userDAL->getUserById($userId);
        $viewerAge = $this->resolveAge($viewer);
        foreach ($candidates as &$candidate) {
            if (!$this->passesPreferenceGate($viewer, $viewerAge, $candidate)) {
                continue;
            }
            $components = $this->computeComponents($userId, $candidate['id']);
            $candidate['compatibility'] = round($components['final'] * 100, 1);
            $candidate['shared_summary'] = $this->buildSharedSummary($components);
            $candidate['match_reason'] = $this->buildMatchReason($components);
            $candidate['shared_genres'] = array_slice($components['shared_genres'], 0, 3);
            $candidate['shared_artists'] = array_slice($components['shared_artists'], 0, 3);
            $candidate['age'] = $this->resolveAge($candidate);
            $candidate['seeking_type'] = $candidate['seeking_type'] ?? 'dating';
            $this->matchDAL->saveCompatibilityScore(
                $userId,
                $candidate['id'],
                $components['final'],
                $components['genre'],
                $components['artist'],
                $components['song']
            );

            $artists = $this->musicDAL->getUserArtists($candidate['id']);
            $candidate['top_artist'] = $artists[0]['name'] ?? null;
        }
        unset($candidate);
        $candidates = array_values(array_filter($candidates, static fn(array $candidate): bool => isset($candidate['compatibility'])));
        // Sort by compatibility descending
        usort($candidates, fn($a, $b) => $b['compatibility'] <=> $a['compatibility']);
        return $candidates;
    }

    public function getConfirmedMatches(int $userId): array {
        return $this->matchDAL->getMatchesForUser($userId);
    }

    public function getLikesReceived(int $userId): array {
        $likers = $this->matchDAL->getLikesReceived($userId);
        foreach ($likers as &$liker) {
            $artists = $this->musicDAL->getUserArtists($liker['id']);
            $liker['top_artist'] = $artists[0]['name'] ?? null;
        }
        return $likers;
    }

    private function weightedOverlap(array $itemsA, array $itemsB, string $idKey, ?string $rankKey = null): float {
        $weightsA = $this->buildWeightMap($itemsA, $idKey, $rankKey);
        $weightsB = $this->buildWeightMap($itemsB, $idKey, $rankKey);

        if (empty($weightsA) && empty($weightsB)) {
            return 0.0;
        }

        $allIds = array_unique(array_merge(array_keys($weightsA), array_keys($weightsB)));
        $intersection = 0.0;
        $union = 0.0;

        foreach ($allIds as $id) {
            $a = $weightsA[$id] ?? 0.0;
            $b = $weightsB[$id] ?? 0.0;
            $intersection += min($a, $b);
            $union += max($a, $b);
        }

        return $union > 0 ? $intersection / $union : 0.0;
    }

    private function buildWeightMap(array $items, string $idKey, ?string $rankKey = null): array {
        $weights = [];
        $position = 1;

        foreach ($items as $item) {
            $id = (string)($item[$idKey] ?? '');
            if ($id === '') {
                continue;
            }

            $rank = $rankKey !== null && isset($item[$rankKey]) && $item[$rankKey] !== null
                ? max(1, (int)$item[$rankKey])
                : $position;

            $weights[$id] = 1 / $rank;
            $position++;
        }

        return $weights;
    }

    private function locationAffinity(int $userA, int $userB): float {
        $profileA = $this->userDAL->getUserById($userA);
        $profileB = $this->userDAL->getUserById($userB);

        $locationA = strtolower(trim((string)($profileA['location'] ?? '')));
        $locationB = strtolower(trim((string)($profileB['location'] ?? '')));
        if ($locationA === '' || $locationB === '') {
            return 0.0;
        }

        if ($locationA === $locationB) {
            return 1.0;
        }

        $countryA = trim((string)substr($locationA, strrpos($locationA, ',') !== false ? strrpos($locationA, ',') + 1 : 0));
        $countryB = trim((string)substr($locationB, strrpos($locationB, ',') !== false ? strrpos($locationB, ',') + 1 : 0));

        return ($countryA !== '' && $countryA === $countryB) ? 0.45 : 0.0;
    }

    private function sharedItems(array $itemsA, array $itemsB, string $idKey, string $labelKey): array {
        $labelsById = [];
        foreach ($itemsA as $item) {
            $id = (string)($item[$idKey] ?? '');
            $label = trim((string)($item[$labelKey] ?? ''));
            if ($id !== '' && $label !== '') {
                $labelsById[$id] = $label;
            }
        }

        $shared = [];
        foreach ($itemsB as $item) {
            $id = (string)($item[$idKey] ?? '');
            if ($id !== '' && isset($labelsById[$id])) {
                $shared[] = $labelsById[$id];
            }
        }

        return array_values(array_unique($shared));
    }

    private function buildSharedSummary(array $components): string {
        $parts = [];

        if (!empty($components['shared_genres'])) {
            $parts[] = 'Genres: ' . implode(', ', array_slice($components['shared_genres'], 0, 2));
        }
        if (!empty($components['shared_artists'])) {
            $parts[] = 'Artists: ' . implode(', ', array_slice($components['shared_artists'], 0, 2));
        }
        if (!empty($components['shared_songs'])) {
            $parts[] = 'Songs: ' . implode(', ', array_slice($components['shared_songs'], 0, 1));
        }

        return implode(' | ', $parts);
    }

    private function buildMatchReason(array $components): string {
        $reasons = [];
        if ($components['genre'] >= 0.35) {
            $reasons[] = 'strong genre overlap';
        }
        if ($components['artist'] >= 0.25) {
            $reasons[] = 'similar favourite artists';
        }
        if ($components['song'] >= 0.20) {
            $reasons[] = 'shared tracks';
        }
        if ($components['location'] >= 0.45) {
            $reasons[] = 'same country';
        }

        return $reasons !== [] ? ucfirst(implode(' + ', array_slice($reasons, 0, 2))) : 'Promising new music match';
    }

    /**
     * Compute weighted compatibility components between two users.
     * Returns an array with 'genre', 'artist', 'song', and 'final' keys (all 0.0–1.0).
     */
    private function computeComponents(int $userA, int $userB): array {
        $genresA  = $this->musicDAL->getUserGenres($userA);
        $genresB  = $this->musicDAL->getUserGenres($userB);
        $artistsA = $this->musicDAL->getUserArtists($userA);
        $artistsB = $this->musicDAL->getUserArtists($userB);
        $songsA   = $this->musicDAL->getUserSongs($userA);
        $songsB   = $this->musicDAL->getUserSongs($userB);

        $genre    = $this->weightedOverlap($genresA, $genresB, 'id', 'rank_weight');
        $artist   = $this->weightedOverlap($artistsA, $artistsB, 'id', 'affinity_weight');
        $song     = $this->weightedOverlap($songsA, $songsB, 'id', 'preference_rank');
        $location = $this->locationAffinity($userA, $userB);

        // Weights: genre 35 %, artist 35 %, song 20 %, location 10 %
        $final = (0.35 * $genre) + (0.35 * $artist) + (0.20 * $song) + (0.10 * $location);

        return [
            'genre'  => round($genre,  4),
            'artist' => round($artist, 4),
            'song'   => round($song,   4),
            'location' => round($location, 4),
            'shared_genres' => $this->sharedItems($genresA, $genresB, 'id', 'name'),
            'shared_artists' => $this->sharedItems($artistsA, $artistsB, 'id', 'name'),
            'shared_songs' => $this->sharedItems($songsA, $songsB, 'id', 'title'),
            'final'  => round($final,  4),
        ];
    }

    public function getCompatibilityInsights(int $userA, int $userB): array {
        $components = $this->computeComponents($userA, $userB);

        return [
            'score' => round($components['final'] * 100, 1),
            'summary' => $this->buildSharedSummary($components),
            'reason' => $this->buildMatchReason($components),
            'shared_genres' => array_slice($components['shared_genres'], 0, 3),
            'shared_artists' => array_slice($components['shared_artists'], 0, 3),
            'shared_songs' => array_slice($components['shared_songs'], 0, 2),
        ];
    }

    /**
     * Compute a 0–100 compatibility score based on shared music preferences.
     */
    public function computeCompatibility(int $userA, int $userB): float {
        return round($this->computeComponents($userA, $userB)['final'] * 100, 1);
    }

    public function isDiscoverEligible(int $viewerId, array $candidate): bool {
        $viewer = $this->userDAL->getUserById($viewerId);
        return $this->passesPreferenceGate($viewer, $this->resolveAge($viewer), $candidate);
    }

    private function passesPreferenceGate(array|false $viewer, ?int $viewerAge, array $candidate): bool {
        if (!$viewer) {
            return true;
        }

        $candidateFull = $this->userDAL->getUserById((int)$candidate['id']);
        if (!$candidateFull) {
            return false;
        }

        $candidateAge = $this->resolveAge($candidateFull);
        $viewerGender = (string)($viewer['gender'] ?? '');
        $candidateGender = (string)($candidateFull['gender'] ?? '');

        if (!$this->matchesDesiredGender((string)($viewer['desired_gender'] ?? 'everyone'), $candidateGender)) {
            return false;
        }
        if (!$this->matchesDesiredGender((string)($candidateFull['desired_gender'] ?? 'everyone'), $viewerGender)) {
            return false;
        }

        if (!$this->matchesAgePreference((int)($viewer['min_age_pref'] ?? 18), (int)($viewer['max_age_pref'] ?? 100), $candidateAge)) {
            return false;
        }
        if (!$this->matchesAgePreference((int)($candidateFull['min_age_pref'] ?? 18), (int)($candidateFull['max_age_pref'] ?? 100), $viewerAge)) {
            return false;
        }

        if (!$this->matchesLocationScope((string)($viewer['location_scope'] ?? 'anywhere'), (string)($viewer['location'] ?? ''), (string)($candidateFull['location'] ?? ''))) {
            return false;
        }
        if (!$this->matchesLocationScope((string)($candidateFull['location_scope'] ?? 'anywhere'), (string)($candidateFull['location'] ?? ''), (string)($viewer['location'] ?? ''))) {
            return false;
        }

        return true;
    }

    private function matchesDesiredGender(string $desiredGender, string $candidateGender): bool {
        $desiredGender = strtolower(trim($desiredGender));
        $candidateGender = strtolower(trim($candidateGender));

        if ($desiredGender === '' || $desiredGender === 'everyone') {
            return true;
        }

        return $candidateGender !== '' && $candidateGender === $desiredGender;
    }

    private function matchesAgePreference(int $minAge, int $maxAge, ?int $candidateAge): bool {
        if ($candidateAge === null) {
            return true;
        }

        $minAge = max(18, $minAge ?: 18);
        $maxAge = max($minAge, $maxAge ?: 100);
        return $candidateAge >= $minAge && $candidateAge <= $maxAge;
    }

    private function matchesLocationScope(string $scope, string $viewerLocation, string $candidateLocation): bool {
        $scope = strtolower(trim($scope));
        if ($scope === '' || $scope === 'anywhere') {
            return true;
        }

        [$viewerCity, $viewerCountry] = $this->splitLocation($viewerLocation);
        [$candidateCity, $candidateCountry] = $this->splitLocation($candidateLocation);

        if ($scope === 'same_city') {
            return $viewerCity !== '' && $viewerCity === $candidateCity;
        }

        if ($scope === 'same_country') {
            return $viewerCountry !== '' && $viewerCountry === $candidateCountry;
        }

        return true;
    }

    private function splitLocation(string $location): array {
        $parts = array_map('trim', explode(',', strtolower($location)));
        if (count($parts) >= 2) {
            return [$parts[0], $parts[count($parts) - 1]];
        }

        return [$parts[0] ?? '', $parts[0] ?? ''];
    }

    private function resolveAge(array|false $profile): ?int {
        if (!$profile) {
            return null;
        }

        $birthYear = (int)($profile['birth_year'] ?? 0);
        if ($birthYear <= 0) {
            return null;
        }

        return (int)date('Y') - $birthYear;
    }
}

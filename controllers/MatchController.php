<?php
require_once __DIR__ . '/../dal/MatchDAL.php';
require_once __DIR__ . '/../dal/MusicDAL.php';

class MatchController {
    private MatchDAL $matchDAL;
    private MusicDAL $musicDAL;

    public function __construct() {
        $this->matchDAL = new MatchDAL();
        $this->musicDAL = new MusicDAL();
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
        foreach ($candidates as &$candidate) {
            $components = $this->computeComponents($userId, $candidate['id']);
            $candidate['compatibility'] = round($components['final'] * 100, 1);
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

    /**
     * Jaccard similarity between two arrays: |A ∩ B| / |A ∪ B|
     * Returns 0.0–1.0 (returns 0.0 when both arrays are empty).
     */
    private function jaccardSimilarity(array $a, array $b): float {
        if (empty($a) && empty($b)) return 0.0;
        $intersection = count(array_intersect($a, $b));
        $union        = count(array_unique(array_merge($a, $b)));
        return $union === 0 ? 0.0 : $intersection / $union;
    }

    /**
     * Compute weighted compatibility components between two users.
     * Returns an array with 'genre', 'artist', 'song', and 'final' keys (all 0.0–1.0).
     */
    private function computeComponents(int $userA, int $userB): array {
        $genresA  = array_column($this->musicDAL->getUserGenres($userA),  'id');
        $genresB  = array_column($this->musicDAL->getUserGenres($userB),  'id');
        $artistsA = array_column($this->musicDAL->getUserArtists($userA), 'id');
        $artistsB = array_column($this->musicDAL->getUserArtists($userB), 'id');
        $songsA   = array_column($this->musicDAL->getUserSongs($userA),   'id');
        $songsB   = array_column($this->musicDAL->getUserSongs($userB),   'id');

        $genre  = $this->jaccardSimilarity($genresA,  $genresB);
        $artist = $this->jaccardSimilarity($artistsA, $artistsB);
        $song   = $this->jaccardSimilarity($songsA,   $songsB);

        // Weights: genre 40 %, artist 35 %, song 25 %
        $final = (0.40 * $genre) + (0.35 * $artist) + (0.25 * $song);

        return [
            'genre'  => round($genre,  4),
            'artist' => round($artist, 4),
            'song'   => round($song,   4),
            'final'  => round($final,  4),
        ];
    }

    /**
     * Compute a 0–100 compatibility score based on shared music preferences.
     */
    public function computeCompatibility(int $userA, int $userB): float {
        return round($this->computeComponents($userA, $userB)['final'] * 100, 1);
    }
}

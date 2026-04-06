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
            $score = $this->computeCompatibility($userId, $candidate['id']);
            $candidate['compatibility'] = $score;
            $this->matchDAL->saveCompatibilityScore($userId, $candidate['id'], $score);

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

    /**
     * Compute a 0-100 compatibility score based on shared music preferences.
     * TODO: Expand with weighted genre/artist/song/mood scoring.
     */
    public function computeCompatibility(int $userA, int $userB): float {
        $genresA = array_column($this->musicDAL->getUserGenres($userA), 'id');
        $genresB = array_column($this->musicDAL->getUserGenres($userB), 'id');

        $shared = count(array_intersect($genresA, $genresB));
        $total  = count(array_unique(array_merge($genresA, $genresB)));

        if ($total === 0) return 0.0;
        return round(($shared / $total) * 100, 1);
    }
}

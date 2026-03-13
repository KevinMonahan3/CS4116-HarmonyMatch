<?php
require_once __DIR__ . '/../dal/UserDAL.php';
require_once __DIR__ . '/../dal/MusicDAL.php';

class UserController {
    private UserDAL $userDAL;
    private MusicDAL $musicDAL;

    public function __construct() {
        $this->userDAL = new UserDAL();
        $this->musicDAL = new MusicDAL();
    }

    public function getProfile(int $userId): array|false {
        $user = $this->userDAL->getUserById($userId);
        if (!$user) return false;

        $user['genres']  = $this->musicDAL->getUserGenres($userId);
        $user['artists'] = $this->musicDAL->getUserArtists($userId);
        $user['songs']   = $this->musicDAL->getUserSongs($userId);
        return $user;
    }

    public function updateProfile(int $userId, array $data): array {
        $allowed = ['name', 'bio', 'location', 'profile_photo'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        $this->userDAL->updateProfile($userId, $filtered);
        return ['success' => true];
    }

    public function saveOnboardingStep2(int $userId, array $genreIds, array $artists, array $songs): array {
        $this->musicDAL->setUserGenres($userId, $genreIds);
        foreach ($artists as $artist) {
            $this->musicDAL->addUserArtist($userId, $artist);
        }
        foreach ($songs as $song) {
            $this->musicDAL->addUserSong($userId, $song['title'], $song['artist']);
        }
        return ['success' => true];
    }

    public function completeOnboarding(int $userId): array {
        $this->userDAL->updateOnboardingComplete($userId);
        return ['success' => true, 'redirect' => 'dashboard.php'];
    }
}

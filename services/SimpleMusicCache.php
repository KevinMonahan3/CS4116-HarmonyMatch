<?php
require_once __DIR__ . '/../config/music.php';

class SimpleMusicCache {
    private string $cacheDir;

    public function __construct() {
        $this->cacheDir = sys_get_temp_dir() . '/harmonymatch_music_cache';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
    }

    public function remember(string $key, callable $resolver, ?int $ttl = null): mixed {
        $ttl ??= MUSIC_CACHE_TTL;
        $path = $this->cachePath($key);

        if (is_file($path) && (time() - filemtime($path)) < $ttl) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                return json_decode($raw, true);
            }
        }

        $value = $resolver();
        @file_put_contents($path, json_encode($value));
        return $value;
    }

    private function cachePath(string $key): string {
        return $this->cacheDir . '/' . sha1($key) . '.json';
    }
}

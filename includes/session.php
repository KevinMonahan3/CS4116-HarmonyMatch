<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base URL — works on both localhost and InfinityFree
$baseUrl = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'], '/');

<?php
// Outputs the shared <head> block and navbar
// $pageTitle should be set before including this file
$pageTitle = $pageTitle ?? 'HarmonyMatch';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — HarmonyMatch</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<nav class="hm-navbar">
    <div class="navbar-brand">
        <div class="brand-logo">♪</div>
        <span class="brand-name">HarmonyMatch</span>
    </div>
    <?php if (!empty($_SESSION['user_id'])): ?>
    <div class="navbar-actions">
        <a href="<?= $baseUrl ?>/dashboard.php" class="btn-ghost"><i class="fas fa-home"></i></a>
        <a href="<?= $baseUrl ?>/chat.php" class="btn-ghost"><i class="fas fa-comment"></i></a>
        <a href="<?= $baseUrl ?>/profile-own.php" class="btn-ghost"><i class="fas fa-user"></i></a>
        <a href="<?= $baseUrl ?>/api/auth.php?action=logout" class="btn-ghost"><i class="fas fa-sign-out-alt"></i></a>
    </div>
    <?php endif; ?>
</nav>

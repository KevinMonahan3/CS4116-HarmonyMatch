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
    <a href="<?= $baseUrl ?>/dashboard.php" class="navbar-brand">
        <div class="brand-logo"><i class="fas fa-music"></i></div>
        <span class="brand-name">HarmonyMatch</span>
    </a>
    <?php if (!empty($_SESSION['user_id'])): ?>
    <div class="navbar-actions">
        <a href="<?= $baseUrl ?>/api/auth.php?action=logout" class="btn-ghost" title="Sign out">
            <i class="fas fa-sign-out-alt"></i>
            <span class="btn-ghost-label">Sign out</span>
        </a>
    </div>
    <?php endif; ?>
</nav>

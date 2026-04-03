<?php
/**
 * includes/header.php
 * Shared HTML <head> and opening body wrapper for all authenticated pages.
 * $pageTitle should be set in the calling page before including this.
 */
$pageTitle = $pageTitle ?? 'HarmonyMatch';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>HarmonyMatch — <?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>

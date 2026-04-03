<?php
/**
 * includes/footer.php
 * Closes the body and optionally loads a per-page JS module.
 * Set $extraScript = 'filename.js' in the calling page to load assets/js/filename.js.
 */
?>
<?php if (!empty($extraScript)): ?>
  <script src="/assets/js/<?= htmlspecialchars($extraScript) ?>"></script>
<?php endif; ?>
</body>
</html>

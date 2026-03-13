<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
AuthController::requireLogin();

$withUserId = (int)($_GET['with'] ?? 0);
$pageTitle  = 'Messages';
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">
    <aside class="hm-sidebar">
        <nav class="sidebar-nav">
            <a href="/dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Discover</span></a>
            <a href="/search.php" class="nav-item"><i class="fas fa-search"></i><span>Search</span></a>
            <a href="/chat.php" class="nav-item active"><i class="fas fa-comment"></i><span>Messages</span></a>
            <a href="/profile-own.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
        </nav>
    </aside>

    <main class="hm-main chat-layout">
        <!-- Inbox panel -->
        <div class="chat-inbox hm-card" id="chatInbox">
            <h3>Messages</h3>
            <div id="inboxList"><p style="color:var(--text-secondary);">Loading...</p></div>
        </div>

        <!-- Conversation panel -->
        <div class="chat-panel hm-card" id="chatPanel" <?= $withUserId ? '' : 'style="display:none"' ?>>
            <div class="chat-header" id="chatHeader"></div>
            <div class="chat-messages" id="chatMessages"></div>
            <form class="chat-input-row" id="sendForm">
                <input type="text" id="msgInput" class="form-input" placeholder="Type a message..." autocomplete="off">
                <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i></button>
            </form>
        </div>
    </main>
</div>

<script>
const CURRENT_USER_ID = <?= (int)$_SESSION['user_id'] ?>;
const OPEN_WITH = <?= $withUserId ?: 'null' ?>;
</script>
<?php $extraScript = 'chat.js'; include __DIR__ . '/includes/footer.php'; ?>

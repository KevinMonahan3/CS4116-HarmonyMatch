<?php
/**
 * chat.php
 * Messaging interface — inbox list on the left, active conversation on the right.
 * Optionally opens a specific conversation if ?with=<userId> is passed.
 */
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
      <a href="/dashboard.php"   class="nav-item"><i class="fas fa-home"></i><span>Discover</span></a>
      <a href="/search.php"      class="nav-item"><i class="fas fa-search"></i><span>Search</span></a>
      <a href="/chat.php"        class="nav-item active"><i class="fas fa-comment"></i><span>Messages</span></a>
      <a href="/profile-own.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
    </nav>
  </aside>

  <main class="hm-main chat-layout">

    <!-- ── Inbox panel ── -->
    <div class="chat-inbox hm-card" id="chatInbox">
      <h3>Messages</h3>

      <!--
        DB CONNECTION POINT — Inbox List
        ─────────────────────────────────────────────────────────
        Populated by chat.js via:
          fetch('/api/messages.php?action=inbox')

        In /api/messages.php (action=inbox):
          1. Verify session
          2. Call MessageController::getInbox($currentUserId)
             → MessageDAL::getInbox() runs:
                SELECT conversations with latest message per thread,
                JOIN users for name + photo,
                ORDER BY last_message_at DESC
          3. Return JSON: [{ user_id, name, photo, last_message, unread_count, timestamp }]

        chat.js renders each as an .inbox-item element.
        Clicking one opens the conversation panel and calls loadConversation(userId).
        ─────────────────────────────────────────────────────────
      -->
      <div id="inboxList">

        <!-- Static placeholder inbox items — remove once API is wired -->
        <div class="inbox-item active" onclick="loadConversation(99)">
          <div class="inbox-avatar">A</div>
          <div class="inbox-info">
            <div class="inbox-name">Alex M.</div>
            <div class="inbox-preview">I love that song too! 🎵</div>
          </div>
        </div>

        <div class="inbox-item" onclick="loadConversation(98)">
          <div class="inbox-avatar">S</div>
          <div class="inbox-info">
            <div class="inbox-name">Sam K.</div>
            <div class="inbox-preview">Have you heard the new album?</div>
          </div>
        </div>

        <div class="inbox-item" onclick="loadConversation(97)">
          <div class="inbox-avatar">J</div>
          <div class="inbox-info">
            <div class="inbox-name">Jordan L.</div>
            <div class="inbox-preview">What are you listening to rn?</div>
          </div>
        </div>

      </div><!-- /#inboxList -->
    </div>

    <!-- ── Conversation panel ── -->
    <div class="chat-panel hm-card" id="chatPanel" <?= $withUserId ? '' : 'style="display:none;"' ?>>

      <!--
        DB CONNECTION POINT — Chat Header
        Name + photo of the person you're talking to.
        Populated by chat.js after loadConversation(userId).
      -->
      <div class="chat-header" id="chatHeader">
        <!-- Populated by chat.js -->
        <span style="color:var(--text-muted);">Select a conversation</span>
      </div>

      <!--
        DB CONNECTION POINT — Message Thread
        ─────────────────────────────────────────────────────────
        Populated by chat.js via:
          fetch('/api/messages.php?action=thread&with=<userId>')

        In /api/messages.php (action=thread):
          1. Verify session
          2. Call MessageController::getThread($currentUserId, $withUserId)
             → MessageDAL::getThread() runs:
                SELECT id, sender_id, body, sent_at
                FROM messages
                WHERE (sender_id = :me AND receiver_id = :them)
                   OR (sender_id = :them AND receiver_id = :me)
                ORDER BY sent_at ASC
          3. Return JSON array of message objects
          4. Also mark incoming messages as read (MessageDAL::markRead())

        chat.js renders each as a .msg-bubble.sent or .msg-bubble.received element.
        ─────────────────────────────────────────────────────────
      -->
      <div class="chat-messages" id="chatMessages">
        <!-- Static placeholder messages — remove once API is wired -->
        <div>
          <div class="msg-bubble received">Hey! I saw you're into Indie too 🎸</div>
          <div class="msg-time">2:14 pm</div>
        </div>
        <div style="align-self:flex-end;text-align:right;">
          <div class="msg-bubble sent">Yes! Especially Arctic Monkeys 🎵</div>
          <div class="msg-time">2:15 pm</div>
        </div>
        <div>
          <div class="msg-bubble received">I love that song too! What else are you listening to?</div>
          <div class="msg-time">2:16 pm</div>
        </div>
      </div>

      <!--
        DB CONNECTION POINT — Send Message
        ─────────────────────────────────────────────────────────
        Form submit handled by chat.js via:
          fetch('/api/messages.php', { method:'POST', body: `action=send&to=<userId>&body=<text>` })

        In /api/messages.php (action=send):
          1. Verify session & that $toUserId exists and is not blocked
          2. Call MessageController::send($fromUserId, $toUserId, $body)
             → MessageDAL::insert() — INSERT INTO messages (sender_id, receiver_id, body, sent_at)
          3. Return JSON { success: true, message: { id, body, sent_at } }

        chat.js appends the new bubble immediately (optimistic UI) then confirms with the response.
        ─────────────────────────────────────────────────────────
      -->
      <form class="chat-input-row" id="sendForm">
        <input type="text" id="msgInput" class="form-input" placeholder="Type a message…" autocomplete="off">
        <button type="submit" class="btn-primary">
          <i class="fas fa-paper-plane"></i>
        </button>
      </form>

    </div><!-- /#chatPanel -->

  </main>
</div>

<script>
  const CURRENT_USER_ID = <?= (int)$_SESSION['user_id'] ?>;
  const OPEN_WITH       = <?= $withUserId ?: 'null' ?>;

  /* Placeholder — wire into chat.js once API is ready */
  function loadConversation(userId) {
    document.querySelectorAll('.inbox-item').forEach(el => el.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById('chatPanel').style.display = 'flex';
    document.getElementById('chatHeader').innerHTML =
      '<i class="fas fa-user-circle" style="color:var(--accent-purple-light);margin-right:8px;"></i> User #' + userId;
    // TODO: chat.js will call loadConversation(userId) to fetch & render the thread
  }

  document.getElementById('sendForm').addEventListener('submit', e => {
    e.preventDefault();
    const msg = document.getElementById('msgInput').value.trim();
    if (!msg) return;
    // TODO: wire to /api/messages.php?action=send in chat.js
    const bubble = document.createElement('div');
    bubble.style.alignSelf = 'flex-end';
    bubble.style.textAlign = 'right';
    bubble.innerHTML = `<div class="msg-bubble sent">${msg}</div>`;
    document.getElementById('chatMessages').appendChild(bubble);
    document.getElementById('msgInput').value = '';
    document.getElementById('chatMessages').scrollTop = 9999;
  });
</script>

<?php $extraScript = 'chat.js'; include __DIR__ . '/includes/footer.php'; ?>

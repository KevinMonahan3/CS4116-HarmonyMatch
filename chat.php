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
        <div class="inbox-item" onclick="loadConversation(99, this)">
          <div class="inbox-avatar">A</div>
          <div class="inbox-info">
            <div class="inbox-name">Alex M.</div>
            <div class="inbox-preview">I love that song too! 🎵</div>
          </div>
        </div>

        <div class="inbox-item" onclick="loadConversation(98, this)">
          <div class="inbox-avatar">S</div>
          <div class="inbox-info">
            <div class="inbox-name">Sam K.</div>
            <div class="inbox-preview">Have you heard the new album?</div>
          </div>
        </div>

        <div class="inbox-item" onclick="loadConversation(97, this)">
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
      <div class="chat-messages" id="chatMessages"></div>

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
  const CURRENT_USER_ID = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
  const OPEN_WITH       = <?= $withUserId ?: 'null' ?>;

  const FAKE_USERS = {
    99: { name: 'Alex M.',   initial: 'A' },
    98: { name: 'Sam K.',    initial: 'S' },
    97: { name: 'Jordan L.', initial: 'J' },
  };

  const FAKE_MESSAGES = {
    99: [
      { self: false, text: "Hey! I saw you're into Indie too 🎸",           time: '2:14 pm' },
      { self: true,  text: 'Yes! Especially Arctic Monkeys 🎵',             time: '2:15 pm' },
      { self: false, text: 'I love that song too! What else are you into?', time: '2:16 pm' },
    ],
    98: [
      { self: false, text: 'Have you heard the new Hozier album?',              time: '1:30 pm' },
      { self: true,  text: 'Not yet! Is it good?',                             time: '1:38 pm' },
      { self: false, text: "It's incredible, you need to listen immediately",   time: '1:40 pm' },
      { self: true,  text: 'Adding it to my queue now 🎧',                     time: '1:41 pm' },
      { self: false, text: 'Have you heard the new album?',                     time: '1:42 pm' },
    ],
    97: [
      { self: false, text: 'What are you listening to rn?',               time: 'Yesterday' },
      { self: true,  text: 'Been on a massive Fleetwood Mac kick lately', time: 'Yesterday' },
      { self: false, text: 'Classic taste 🙌 Dreams or Go Your Own Way?', time: 'Yesterday' },
    ],
  };

  function loadConversation(userId, el) {
    console.log('loadConversation called', userId);
    document.querySelectorAll('.inbox-item').forEach(item => item.classList.remove('active'));
    if (el) el.classList.add('active');

    const user = FAKE_USERS[userId];
    document.getElementById('chatHeader').innerHTML =
      `<div class="inbox-avatar" style="width:36px;height:36px;font-size:.9rem;margin-right:10px;">${user.initial}</div><strong>${user.name}</strong>`;

    const container = document.getElementById('chatMessages');
    container.innerHTML = (FAKE_MESSAGES[userId] ?? []).map(m => `
      <div style="display:flex;flex-direction:column;align-self:${m.self ? 'flex-end' : 'flex-start'};max-width:70%;">
        <div class="msg-bubble ${m.self ? 'sent' : 'received'}">${m.text}</div>
        <div class="msg-time" style="text-align:${m.self ? 'right' : 'left'}">${m.time}</div>
      </div>
    `).join('');
    container.scrollTop = container.scrollHeight;

    document.getElementById('chatPanel').style.display = '';
  }

  document.getElementById('sendForm').addEventListener('submit', e => {
    e.preventDefault();
    const input = document.getElementById('msgInput');
    const msg = input.value.trim();
    if (!msg) return;
    const container = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.style.display = 'flex';
    div.style.flexDirection = 'column';
    div.style.alignSelf = 'flex-end';
    div.style.maxWidth = '70%';
    div.innerHTML = `<div class="msg-bubble sent">${msg}</div><div class="msg-time" style="text-align:right">Just now</div>`;
    container.appendChild(div);
    input.value = '';
    container.scrollTop = container.scrollHeight;
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

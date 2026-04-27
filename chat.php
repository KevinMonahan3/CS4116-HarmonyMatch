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
      <a href="/likes.php"       class="nav-item"><i class="fas fa-heart"></i><span>Likes</span></a>
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
        <p id="inboxEmpty" style="color:var(--text-muted);font-size:13px;padding:12px;display:none;">No conversations yet.</p>
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
        <button class="chat-back-btn" onclick="showInbox()" aria-label="Back to inbox">
          <i class="fas fa-chevron-left"></i>
        </button>
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
        <input type="text" id="msgInput" class="form-input" placeholder="Type a message…" autocomplete="off" maxlength="1000">
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

  let activeUserId   = null;
  let pollTimer      = null;

  /* ── Helpers ── */
  function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function formatTime(sentAt) {
    if (!sentAt) return '';
    const d = new Date(sentAt.replace(' ', 'T'));
    return isNaN(d) ? sentAt : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function avatarHtml(name, photo, size = 40) {
    if (photo) return `<img src="${escHtml(photo)}" style="width:${size}px;height:${size}px;border-radius:50%;object-fit:cover;">`;
    const initial = (name || '?').charAt(0).toUpperCase();
    return `<div class="inbox-avatar" style="width:${size}px;height:${size}px;font-size:${size * 0.4}px;flex-shrink:0;">${initial}</div>`;
  }

  function containsPhoneNumber(content) {
    const digits = String(content ?? '').replace(/[^\d]/g, '');
    return digits.length >= 8 || /(?:\+\d[\d\s().-]{6,}\d)/.test(String(content ?? ''));
  }

  /* ── Inbox ── */
  async function loadInbox() {
    const list = document.getElementById('inboxList');
    const data = await apiGet('/api/messages.php?action=inbox');
    const items = Array.isArray(data) ? data : [];

    // Remove old inbox items (keep the empty notice)
    list.querySelectorAll('.inbox-item').forEach(el => el.remove());

    document.getElementById('inboxEmpty').style.display = items.length ? 'none' : 'block';

    items.forEach(item => {
      const otherId   = item.other_user_id;
      const otherName = item.other_name ?? 'Unknown';
      const div = document.createElement('div');
      div.className = 'inbox-item';
      if (otherId == activeUserId) div.classList.add('active');
      div.dataset.userId = otherId;
      div.innerHTML = `
        ${avatarHtml(otherName, item.other_photo, 40)}
        <div class="inbox-info">
          <div class="inbox-name">${escHtml(otherName)}</div>
          <div class="inbox-preview">${escHtml(item.content ?? '')}</div>
        </div>`;
      div.addEventListener('click', () => openConversation(otherId, otherName, item.other_photo));
      list.appendChild(div);
    });

    // If OPEN_WITH is set and not yet open, try to open from inbox data or fetch profile
    if (OPEN_WITH && activeUserId === null) {
      const found = items.find(item => {
        const oid = item.from_user_id == CURRENT_USER_ID ? item.to_user_id : item.from_user_id;
        return oid == OPEN_WITH;
      });
      if (found) {
        const oid = found.from_user_id == CURRENT_USER_ID ? found.to_user_id : found.from_user_id;
        openConversation(oid, found.other_name, found.other_photo);
      } else {
        // Fresh match — no messages yet; fetch the profile to get the name
        const profile = await apiGet(`/api/users.php?action=profile&id=${OPEN_WITH}`);
        openConversation(OPEN_WITH, profile?.name ?? 'New Match', profile?.profile_photo ?? null);
      }
    }
  }

  /* ── Open a conversation ── */
  function openConversation(userId, name, photo) {
    activeUserId = userId;

    document.querySelectorAll('.inbox-item').forEach(el => {
      el.classList.toggle('active', el.dataset.userId == userId);
    });

    document.getElementById('chatHeader').innerHTML =
      `<button class="chat-back-btn" onclick="showInbox()" aria-label="Back to inbox">
         <i class="fas fa-chevron-left"></i>
       </button>
       ${avatarHtml(name, photo, 36)}
       <strong style="margin-left:8px;">${escHtml(name)}</strong>
       <button class="btn-outline chat-report-btn" type="button" onclick="reportUser(${Number(userId)})" title="Report user">
         <i class="fas fa-flag"></i> Report
       </button>`;

    document.getElementById('chatPanel').style.display = '';

    // On mobile, switch from inbox view to conversation view
    if (window.innerWidth <= 600) {
      document.querySelector('.chat-layout').classList.add('mobile-panel-open');
    }

    loadMessages(userId);

    clearInterval(pollTimer);
    pollTimer = setInterval(() => loadMessages(userId), 5000);
  }

  /* ── Back to inbox (mobile) ── */
  function showInbox() {
    document.querySelector('.chat-layout').classList.remove('mobile-panel-open');
    // Clear inline display style so the CSS display:none rule takes over and hides the panel
    document.getElementById('chatPanel').style.display = '';
    activeUserId = null;
    clearInterval(pollTimer);
  }

  /* ── Load messages for active conversation ── */
  async function loadMessages(userId) {
    if (userId !== activeUserId) return;
    const msgs = await apiGet(`/api/messages.php?action=conversation&with=${userId}`);
    if (!Array.isArray(msgs)) return;

    const container = document.getElementById('chatMessages');
    const atBottom  = container.scrollHeight - container.scrollTop - container.clientHeight < 60;

    container.innerHTML = msgs.length
      ? msgs.map(m => {
          const self = m.from_user_id == CURRENT_USER_ID;
          return `
            <div style="display:flex;flex-direction:column;align-self:${self?'flex-end':'flex-start'};max-width:70%;">
              <div class="msg-bubble ${self?'sent':'received'}">${escHtml(m.content)}</div>
              <div class="msg-time" style="text-align:${self?'right':'left'}">${formatTime(m.sent_at)}</div>
            </div>`;
        }).join('')
      : `<p style="color:var(--text-muted);text-align:center;margin-top:24px;font-size:13px;">No messages yet. Say hi!</p>`;

    if (atBottom || msgs.length === 0) container.scrollTop = container.scrollHeight;
  }

  /* ── Send message ── */
  document.getElementById('sendForm').addEventListener('submit', async e => {
    e.preventDefault();
    const input = document.getElementById('msgInput');
    const content = input.value.trim();
    if (!content || !activeUserId) return;
    if (containsPhoneNumber(content)) {
      alert('Phone numbers are not allowed in chat. Please keep messages on HarmonyMatch.');
      return;
    }

    const response = await apiPost('/api/messages.php', { action: 'send', to_user_id: activeUserId, content });
    if (!response || response.error) {
      alert(response?.error ?? 'Unable to send message.');
      return;
    }

    input.value = '';

    // Refresh inbox preview and real messages
    loadInbox();
    loadMessages(activeUserId);
  });

  document.addEventListener('DOMContentLoaded', loadInbox);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

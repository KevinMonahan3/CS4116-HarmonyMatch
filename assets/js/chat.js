// chat.js — messaging page logic

let activeChatUserId = null;

document.addEventListener('DOMContentLoaded', async () => {
    await loadInbox();

    if (typeof OPEN_WITH === 'number' && OPEN_WITH) {
        openConversation(OPEN_WITH);
    }

    const sendForm = document.getElementById('sendForm');
    if (sendForm) {
        sendForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input   = document.getElementById('msgInput');
            const content = input.value.trim();
            if (!content || !activeChatUserId) return;
            input.value = '';
            await apiPost('/api/messages.php', { action: 'send', to_user_id: activeChatUserId, content });
            await loadConversation(activeChatUserId);
        });
    }
});

async function loadInbox() {
    const list = document.getElementById('inboxList');
    const data = await apiGet('/api/messages.php?action=inbox');
    if (!Array.isArray(data) || data.length === 0) {
        list.innerHTML = '<p style="color:var(--text-secondary);">No conversations yet.</p>';
        return;
    }
    list.innerHTML = data.map(m => `
        <div class="inbox-item" onclick="openConversation(${m.from_user_id === CURRENT_USER_ID ? m.to_user_id : m.from_user_id})">
            <div class="inbox-photo">
                ${m.other_photo ? `<img src="${m.other_photo}" alt="">` : `<div class="avatar-placeholder">${m.other_name.charAt(0)}</div>`}
            </div>
            <div class="inbox-info">
                <strong>${m.other_name}</strong>
                <p>${m.content.substring(0, 50)}${m.content.length > 50 ? '…' : ''}</p>
            </div>
        </div>
    `).join('');
}

async function openConversation(userId) {
    activeChatUserId = userId;
    const panel = document.getElementById('chatPanel');
    panel.style.display = '';
    await loadConversation(userId);
}

async function loadConversation(userId) {
    const messages = await apiGet(`/api/messages.php?action=conversation&with=${userId}`);
    const container = document.getElementById('chatMessages');
    if (!Array.isArray(messages)) return;
    container.innerHTML = messages.map(m => `
        <div class="msg-bubble ${m.from_user_id === CURRENT_USER_ID ? 'msg-sent' : 'msg-received'}">
            <p>${m.content}</p>
            <span class="msg-time">${m.sent_at}</span>
        </div>
    `).join('');
    container.scrollTop = container.scrollHeight;
}

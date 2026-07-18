// ================= FLOATING CHAT ASSISTANT (OpenRouter-backed) =================
// Self-contained, same pattern as the search-detail modal in dashboard.js:
// injects its own <style> and markup, so this one <script> tag is all any
// admin-side page needs to get the chat head + panel.
//
// Conversation history intentionally lives only in a JS variable (not
// localStorage/sessionStorage) — it can reference student/teacher data,
// so it's cleared on refresh rather than persisted on a shared machine.
(function () {
    const CHAT_ENDPOINT = 'assests/api/chatbot.php';
    const MAX_HISTORY_TURNS = 8; // sent to the server; server re-caps too

    let history = []; // [{role: 'user'|'assistant', content: string}]
    let isSending = false;

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    // Turn plain-text bot replies into safe HTML with line breaks preserved.
    function formatReply(text) {
        return escapeHtml(text).replace(/\n/g, '<br>');
    }

    function injectStyles() {
        if (document.getElementById('chatAssistantStyles')) return;
        const style = document.createElement('style');
        style.id = 'chatAssistantStyles';
        style.textContent = `
            .chat-fab {
                position: fixed;
                bottom: 24px;
                right: 24px;
                width: 58px;
                height: 58px;
                border-radius: 50%;
                border: none;
                background: var(--primary, #4f46e5);
                color: #fff;
                font-size: 22px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 8px 24px rgba(0,0,0,0.22);
                z-index: 9998;
                transition: transform .15s ease;
            }
            .chat-fab:hover { transform: scale(1.06); }
            .chat-fab.hidden-while-open { transform: scale(0); opacity: 0; pointer-events: none; }

            .chat-panel {
                position: fixed;
                bottom: 24px;
                right: 24px;
                width: 360px;
                max-width: 92vw;
                height: 520px;
                max-height: 75vh;
                background: var(--card-bg, #fff);
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.28);
                display: flex;
                flex-direction: column;
                overflow: hidden;
                z-index: 9999;
                transform: translateY(16px) scale(.97);
                opacity: 0;
                pointer-events: none;
                transition: all .18s ease;
            }
            .chat-panel.open {
                transform: translateY(0) scale(1);
                opacity: 1;
                pointer-events: auto;
            }
            .chat-panel-header {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 14px 16px;
                background: var(--primary, #4f46e5);
                color: #fff;
                flex-shrink: 0;
            }
            .chat-panel-title {
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 600;
                font-size: 14.5px;
            }
            .chat-panel-close {
                margin-left: auto;
                background: rgba(255,255,255,0.15);
                border: none;
                color: #fff;
                width: 28px;
                height: 28px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 12px;
            }
            .chat-panel-messages {
                flex: 1;
                overflow-y: auto;
                padding: 14px;
                display: flex;
                flex-direction: column;
                gap: 10px;
                background: var(--bg-light, #f8fafc);
            }
            .chat-msg {
                max-width: 84%;
                padding: 9px 12px;
                border-radius: 12px;
                font-size: 13.5px;
                line-height: 1.45;
                word-wrap: break-word;
            }
            .chat-msg-bot {
                align-self: flex-start;
                background: #eef1f6;
                color: var(--text-dark, #1f2937);
                border-bottom-left-radius: 4px;
            }
            .chat-msg-user {
                align-self: flex-end;
                background: var(--primary, #4f46e5);
                color: #fff;
                border-bottom-right-radius: 4px;
            }
            .chat-msg-error {
                align-self: flex-start;
                background: #fee2e2;
                color: #b91c1c;
                border-bottom-left-radius: 4px;
            }
            .chat-msg-typing {
                align-self: flex-start;
                background: #eef1f6;
                color: var(--text-muted, #6b7280);
                border-bottom-left-radius: 4px;
                font-style: italic;
            }
            .chat-panel-input-row {
                display: flex;
                gap: 8px;
                padding: 12px;
                border-top: 1px solid rgba(0,0,0,0.08);
                flex-shrink: 0;
                background: var(--card-bg, #fff);
            }
            .chat-panel-input-row input {
                flex: 1;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                padding: 9px 14px;
                font-size: 13.5px;
                outline: none;
            }
            .chat-panel-input-row input:focus {
                border-color: var(--primary, #4f46e5);
            }
            .chat-panel-input-row button {
                width: 38px;
                height: 38px;
                border-radius: 50%;
                border: none;
                background: var(--primary, #4f46e5);
                color: #fff;
                cursor: pointer;
                flex-shrink: 0;
            }
            .chat-panel-input-row button:disabled {
                opacity: .6;
                cursor: not-allowed;
            }
            @media (max-width: 480px) {
                .chat-panel { right: 12px; bottom: 12px; }
                .chat-fab { right: 16px; bottom: 16px; }
            }
        `;
        document.head.appendChild(style);
    }

    function injectMarkup() {
        if (document.getElementById('chatFabBtn')) return;

        const fab = document.createElement('button');
        fab.id = 'chatFabBtn';
        fab.className = 'chat-fab';
        fab.type = 'button';
        fab.title = 'Ask the IntelliLearn Assistant';
        fab.setAttribute('aria-label', 'Open chat assistant');
        fab.innerHTML = '<i class="fas fa-robot"></i>';
        document.body.appendChild(fab);

        const panel = document.createElement('div');
        panel.id = 'chatPanel';
        panel.className = 'chat-panel';
        panel.innerHTML = `
            <div class="chat-panel-header">
                <div class="chat-panel-title"><i class="fas fa-robot"></i><span>IntelliLearn Assistant</span></div>
                <button type="button" class="chat-panel-close" id="chatPanelClose" aria-label="Close chat"><i class="fas fa-times"></i></button>
            </div>
            <div class="chat-panel-messages" id="chatMessages">
                <div class="chat-msg chat-msg-bot">Hi! I can answer questions about students, teachers, courses, sections, and enrollments in the system. What would you like to know?</div>
            </div>
            <form class="chat-panel-input-row" id="chatForm">
                <input type="text" id="chatInput" placeholder="Ask about students, courses, teachers..." autocomplete="off" maxlength="1000">
                <button type="submit" id="chatSendBtn" aria-label="Send"><i class="fas fa-paper-plane"></i></button>
            </form>
        `;
        document.body.appendChild(panel);

        fab.addEventListener('click', openPanel);
        panel.querySelector('#chatPanelClose').addEventListener('click', closePanel);
        panel.querySelector('#chatForm').addEventListener('submit', onSubmit);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && panel.classList.contains('open')) closePanel();
        });
    }

    function openPanel() {
        document.getElementById('chatPanel').classList.add('open');
        document.getElementById('chatFabBtn').classList.add('hidden-while-open');
        setTimeout(() => document.getElementById('chatInput')?.focus(), 150);
    }

    function closePanel() {
        document.getElementById('chatPanel').classList.remove('open');
        document.getElementById('chatFabBtn').classList.remove('hidden-while-open');
    }

    function appendMessage(role, text) {
        const messages = document.getElementById('chatMessages');
        const bubble = document.createElement('div');
        bubble.className = 'chat-msg ' + (role === 'user' ? 'chat-msg-user' : role === 'error' ? 'chat-msg-error' : 'chat-msg-bot');
        bubble.innerHTML = formatReply(text);
        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;
        return bubble;
    }

    function appendTypingIndicator() {
        const messages = document.getElementById('chatMessages');
        const bubble = document.createElement('div');
        bubble.className = 'chat-msg chat-msg-typing';
        bubble.id = 'chatTypingIndicator';
        bubble.textContent = 'Assistant is typing...';
        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;
    }

    function removeTypingIndicator() {
        document.getElementById('chatTypingIndicator')?.remove();
    }

    async function onSubmit(e) {
        e.preventDefault();
        if (isSending) return;

        const input = document.getElementById('chatInput');
        const sendBtn = document.getElementById('chatSendBtn');
        const text = input.value.trim();
        if (text === '') return;

        appendMessage('user', text);
        input.value = '';
        isSending = true;
        input.disabled = true;
        sendBtn.disabled = true;
        appendTypingIndicator();

        try {
            const res = await fetch(CHAT_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: text,
                    history: history.slice(-MAX_HISTORY_TURNS),
                })
            });

            const data = await res.json();
            removeTypingIndicator();

            if (!res.ok || data.error) {
                appendMessage('error', data.error || 'Something went wrong. Please try again.');
            } else {
                appendMessage('bot', data.reply);
                history.push({ role: 'user', content: text });
                history.push({ role: 'assistant', content: data.reply });
            }
        } catch (err) {
            removeTypingIndicator();
            appendMessage('error', 'Could not reach the assistant. Please check your connection and try again.');
        } finally {
            isSending = false;
            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        }
    }

    function init() {
        injectStyles();
        injectMarkup();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
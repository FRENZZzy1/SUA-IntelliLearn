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

    const SUGGESTIONS = [
        'How many students are enrolled?',
        'List teachers in the Science department',
        'Show sections for Grade 10'
    ];

    let history = []; // [{role: 'user'|'assistant', content: string}]
    let isSending = false;
    let unreadCount = 0;

    // ---------- utilities ----------

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function formatTime(date) {
        return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    // Lightweight, dependency-free markdown → safe HTML.
    // Escapes everything first, then re-introduces a small set of
    // markdown constructs so LLM replies (lists, bold, code, links) render
    // cleanly instead of as raw asterisks and backticks.
    function renderMarkdown(raw) {
        const text = String(raw ?? '');

        // 1. Pull out fenced code blocks so nothing inside them gets
        //    reformatted, then escape + restore with a copy button.
        const codeBlocks = [];
        let working = text.replace(/```(\w*)\n?([\s\S]*?)```/g, (_, lang, code) => {
            codeBlocks.push({ lang: lang.trim(), code: code.replace(/\n$/, '') });
            return `\u0000CODEBLOCK${codeBlocks.length - 1}\u0000`;
        });

        working = escapeHtml(working);

        // 2. Inline formatting (order matters: bold before italic).
        working = working
            .replace(/`([^`\n]+)`/g, '<code>$1</code>')
            .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
            .replace(/__([^_\n]+)__/g, '<strong>$1</strong>')
            .replace(/(?<!\*)\*([^*\n]+)\*(?!\*)/g, '<em>$1</em>')
            .replace(/(?<!_)_([^_\n]+)_(?!_)/g, '<em>$1</em>')
            .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g,
                '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

        // 3. Block-level: headers, quotes, lists, paragraphs.
        const lines = working.split('\n');
        let html = '';
        let listBuffer = [];
        let listType = null; // 'ul' | 'ol'
        let paraBuffer = [];

        function flushList() {
            if (listBuffer.length) {
                html += `<${listType}>${listBuffer.map(li => `<li>${li}</li>`).join('')}</${listType}>`;
                listBuffer = [];
                listType = null;
            }
        }
        function flushPara() {
            if (paraBuffer.length) {
                html += `<p>${paraBuffer.join('<br>')}</p>`;
                paraBuffer = [];
            }
        }

        lines.forEach((line) => {
            const trimmed = line.trim();
            const headerMatch = trimmed.match(/^(#{1,3})\s+(.*)$/);
            const ulMatch = trimmed.match(/^[-*+]\s+(.*)$/);
            const olMatch = trimmed.match(/^\d+[.)]\s+(.*)$/);
            const quoteMatch = trimmed.match(/^&gt;\s?(.*)$/);

            if (trimmed === '') {
                flushList();
                flushPara();
            } else if (headerMatch) {
                flushList();
                flushPara();
                const level = headerMatch[1].length + 3; // h4-h6, stays modest in a chat bubble
                html += `<h${level}>${headerMatch[2]}</h${level}>`;
            } else if (ulMatch) {
                flushPara();
                if (listType !== 'ul') { flushList(); listType = 'ul'; }
                listBuffer.push(ulMatch[1]);
            } else if (olMatch) {
                flushPara();
                if (listType !== 'ol') { flushList(); listType = 'ol'; }
                listBuffer.push(olMatch[1]);
            } else if (quoteMatch) {
                flushList();
                flushPara();
                html += `<blockquote>${quoteMatch[1]}</blockquote>`;
            } else {
                flushList();
                paraBuffer.push(trimmed);
            }
        });
        flushList();
        flushPara();

        // 4. Restore code blocks with a copy button.
        html = html.replace(/\u0000CODEBLOCK(\d+)\u0000/g, (_, idx) => {
            const block = codeBlocks[idx];
            const escaped = escapeHtml(block.code);
            const langLabel = block.lang ? `<span class="code-lang">${escapeHtml(block.lang)}</span>` : '<span class="code-lang">code</span>';
            return `<div class="code-block">
                <div class="code-block-header">${langLabel}<button type="button" class="code-copy-btn" data-code="${encodeURIComponent(block.code)}"><i class="fas fa-copy"></i> Copy</button></div>
                <pre><code>${escaped}</code></pre>
            </div>`;
        });

        return html || `<p>${escapeHtml(text)}</p>`;
    }

    // ---------- styles ----------

    function injectStyles() {
        if (document.getElementById('chatAssistantStyles')) return;
        const style = document.createElement('style');
        style.id = 'chatAssistantStyles';
        style.textContent = `
            .chat-fab {
                position: fixed;
                bottom: 24px;
                right: 24px;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                border: none;
                background: linear-gradient(135deg, var(--primary, #4f46e5), var(--primary-accent, #7c6cf0));
                color: #fff;
                font-size: 23px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 10px 28px rgba(79, 70, 229, 0.38);
                z-index: 9998;
                transition: transform .2s cubic-bezier(.34,1.56,.64,1), box-shadow .2s ease, opacity .2s ease;
            }
            .chat-fab:hover { transform: scale(1.07); box-shadow: 0 14px 34px rgba(79, 70, 229, 0.46); }
            .chat-fab:active { transform: scale(0.96); }
            .chat-fab.hidden-while-open { transform: scale(0); opacity: 0; pointer-events: none; }
            .chat-fab-badge {
                position: absolute;
                top: -2px;
                right: -2px;
                min-width: 20px;
                height: 20px;
                padding: 0 5px;
                border-radius: 10px;
                background: #ef4444;
                color: #fff;
                font-size: 11px;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 2px solid #fff;
            }
            .chat-fab-badge.hidden { display: none; }

            .chat-panel {
                position: fixed;
                bottom: 24px;
                right: 24px;
                width: 380px;
                max-width: 92vw;
                height: 560px;
                max-height: 78vh;
                background: var(--card-bg, #fff);
                border-radius: 20px;
                box-shadow: 0 24px 64px rgba(15, 23, 42, 0.22);
                display: flex;
                flex-direction: column;
                overflow: hidden;
                z-index: 9999;
                transform: translateY(20px) scale(.96);
                opacity: 0;
                pointer-events: none;
                transition: transform .2s cubic-bezier(.34,1.2,.64,1), opacity .18s ease;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Inter, Helvetica, Arial, sans-serif;
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
                background: linear-gradient(135deg, var(--primary, #4f46e5), var(--primary-accent, #7c6cf0));
                color: #fff;
                flex-shrink: 0;
            }
            .chat-panel-avatar {
                width: 34px;
                height: 34px;
                border-radius: 50%;
                background: rgba(255,255,255,0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 15px;
                flex-shrink: 0;
                position: relative;
            }
            .chat-panel-avatar .status-dot {
                position: absolute;
                bottom: -1px;
                right: -1px;
                width: 9px;
                height: 9px;
                border-radius: 50%;
                background: #22c55e;
                border: 2px solid var(--primary, #4f46e5);
            }
            .chat-panel-title-block { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
            .chat-panel-title { font-weight: 600; font-size: 14.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .chat-panel-subtitle { font-size: 11.5px; opacity: .85; }
            .chat-panel-actions { margin-left: auto; display: flex; gap: 6px; flex-shrink: 0; }
            .chat-panel-icon-btn {
                background: rgba(255,255,255,0.15);
                border: none;
                color: #fff;
                width: 28px;
                height: 28px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background .15s ease;
            }
            .chat-panel-icon-btn:hover { background: rgba(255,255,255,0.28); }

            .chat-panel-messages {
                flex: 1;
                overflow-y: auto;
                padding: 16px 14px;
                display: flex;
                flex-direction: column;
                gap: 4px;
                background: var(--bg-light, #f7f8fb);
                scroll-behavior: smooth;
            }
            .chat-panel-messages::-webkit-scrollbar { width: 6px; }
            .chat-panel-messages::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.14); border-radius: 3px; }

            .chat-row { display: flex; gap: 8px; margin: 6px 0; animation: chatFadeIn .22s ease; }
            .chat-row.user { flex-direction: row-reverse; }
            @keyframes chatFadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

            .chat-row-avatar {
                width: 26px;
                height: 26px;
                border-radius: 50%;
                background: var(--primary, #4f46e5);
                color: #fff;
                font-size: 11.5px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                margin-top: 2px;
            }
            .chat-row.user .chat-row-avatar { background: #cbd5e1; color: #334155; }

            .chat-msg-group { display: flex; flex-direction: column; max-width: 78%; }
            .chat-row.user .chat-msg-group { align-items: flex-end; }

            .chat-msg {
                padding: 10px 13px;
                border-radius: 14px;
                font-size: 13.5px;
                line-height: 1.5;
                word-wrap: break-word;
            }
            .chat-msg-bot {
                background: #fff;
                color: var(--text-dark, #1f2937);
                border: 1px solid #e7e9f0;
                border-bottom-left-radius: 4px;
            }
            .chat-msg-user {
                background: linear-gradient(135deg, var(--primary, #4f46e5), var(--primary-accent, #7c6cf0));
                color: #fff;
                border-bottom-right-radius: 4px;
            }
            .chat-msg-error {
                background: #fef2f2;
                color: #b91c1c;
                border: 1px solid #fecaca;
                border-bottom-left-radius: 4px;
            }
            .chat-msg-error .retry-btn {
                margin-top: 6px;
                border: 1px solid #fca5a5;
                background: #fff;
                color: #b91c1c;
                font-size: 12px;
                font-weight: 600;
                padding: 4px 10px;
                border-radius: 8px;
                cursor: pointer;
            }
            .chat-msg-error .retry-btn:hover { background: #fef2f2; }

            .chat-msg p { margin: 0 0 8px; }
            .chat-msg p:last-child { margin-bottom: 0; }
            .chat-msg h4, .chat-msg h5, .chat-msg h6 { margin: 6px 0 4px; font-size: 1em; font-weight: 700; }
            .chat-msg ul, .chat-msg ol { margin: 4px 0 8px; padding-left: 20px; }
            .chat-msg li { margin-bottom: 3px; }
            .chat-msg blockquote {
                margin: 6px 0;
                padding: 4px 10px;
                border-left: 3px solid var(--primary, #4f46e5);
                background: rgba(79,70,229,0.06);
                color: var(--text-muted, #475569);
            }
            .chat-msg code {
                background: rgba(79,70,229,0.1);
                color: #4338ca;
                padding: 1px 5px;
                border-radius: 5px;
                font-size: 12px;
                font-family: 'SFMono-Regular', Consolas, monospace;
            }
            .chat-msg-user code { background: rgba(255,255,255,0.22); color: #fff; }
            .chat-msg a { color: var(--primary, #4f46e5); font-weight: 600; text-decoration: underline; }
            .chat-msg-user a { color: #fff; }

            .code-block {
                margin: 6px 0;
                border-radius: 10px;
                overflow: hidden;
                background: #1e1e2e;
                font-size: 12px;
            }
            .code-block-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 5px 10px;
                background: #14141f;
                color: #9ca3af;
            }
            .code-lang { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
            .code-copy-btn {
                background: none;
                border: none;
                color: #9ca3af;
                font-size: 11px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            .code-copy-btn:hover { color: #fff; }
            .code-block pre { margin: 0; padding: 10px; overflow-x: auto; }
            .code-block code { background: none; color: #e5e7eb; padding: 0; }

            .chat-msg-time {
                font-size: 10.5px;
                color: var(--text-muted, #94a3b8);
                margin-top: 3px;
                padding: 0 3px;
            }
            .chat-row.user .chat-msg-time { text-align: right; }

            .chat-typing-dots {
                display: flex;
                gap: 3px;
                padding: 4px 2px;
            }
            .chat-typing-dots span {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: #9aa1b3;
                animation: chatTypingBounce 1.2s infinite ease-in-out;
            }
            .chat-typing-dots span:nth-child(2) { animation-delay: .15s; }
            .chat-typing-dots span:nth-child(3) { animation-delay: .3s; }
            @keyframes chatTypingBounce {
                0%, 60%, 100% { transform: translateY(0); opacity: .5; }
                30% { transform: translateY(-4px); opacity: 1; }
            }

            .chat-suggestions {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                padding: 2px 2px 10px 34px;
            }
            .chat-suggestion-chip {
                border: 1px solid #e0e3ee;
                background: #fff;
                color: var(--primary, #4f46e5);
                font-size: 12px;
                font-weight: 500;
                padding: 6px 11px;
                border-radius: 16px;
                cursor: pointer;
                transition: background .15s ease, transform .1s ease;
            }
            .chat-suggestion-chip:hover { background: rgba(79,70,229,0.08); transform: translateY(-1px); }

            .chat-scroll-btn {
                position: absolute;
                bottom: 78px;
                right: 18px;
                background: var(--primary, #4f46e5);
                color: #fff;
                border: none;
                border-radius: 16px;
                padding: 6px 12px;
                font-size: 11.5px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 5px;
                cursor: pointer;
                box-shadow: 0 6px 16px rgba(0,0,0,0.2);
                opacity: 0;
                pointer-events: none;
                transition: opacity .15s ease, transform .15s ease;
                transform: translateY(6px);
            }
            .chat-scroll-btn.visible { opacity: 1; pointer-events: auto; transform: translateY(0); }

            .chat-panel-input-row {
                display: flex;
                align-items: flex-end;
                gap: 8px;
                padding: 10px 12px;
                border-top: 1px solid rgba(0,0,0,0.06);
                flex-shrink: 0;
                background: var(--card-bg, #fff);
            }
            .chat-panel-input-row textarea {
                flex: 1;
                resize: none;
                border: 1px solid #e2e8f0;
                border-radius: 18px;
                padding: 9px 14px;
                font-size: 13.5px;
                font-family: inherit;
                outline: none;
                max-height: 100px;
                line-height: 1.4;
                transition: border-color .15s ease;
            }
            .chat-panel-input-row textarea:focus { border-color: var(--primary, #4f46e5); }
            .chat-panel-input-row button {
                width: 38px;
                height: 38px;
                border-radius: 50%;
                border: none;
                background: var(--primary, #4f46e5);
                color: #fff;
                cursor: pointer;
                flex-shrink: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background .15s ease, transform .1s ease;
            }
            .chat-panel-input-row button:hover:not(:disabled) { background: var(--primary-accent, #6d5cd6); }
            .chat-panel-input-row button:active:not(:disabled) { transform: scale(0.94); }
            .chat-panel-input-row button:disabled {
                opacity: .5;
                cursor: not-allowed;
            }

            @media (max-width: 480px) {
                .chat-panel { right: 12px; bottom: 12px; width: calc(100vw - 24px); }
                .chat-fab { right: 16px; bottom: 16px; }
            }
        `;
        document.head.appendChild(style);
    }

    // ---------- markup ----------

    function injectMarkup() {
        if (document.getElementById('chatFabBtn')) return;

        const fab = document.createElement('button');
        fab.id = 'chatFabBtn';
        fab.className = 'chat-fab';
        fab.type = 'button';
        fab.title = 'Ask the IntelliLearn Assistant';
        fab.setAttribute('aria-label', 'Open chat assistant');
        fab.innerHTML = '<i class="fas fa-robot"></i><span class="chat-fab-badge hidden" id="chatFabBadge">0</span>';
        document.body.appendChild(fab);

        const panel = document.createElement('div');
        panel.id = 'chatPanel';
        panel.className = 'chat-panel';
        panel.innerHTML = `
            <div class="chat-panel-header">
                <div class="chat-panel-avatar"><i class="fas fa-robot"></i><span class="status-dot"></span></div>
                <div class="chat-panel-title-block">
                    <div class="chat-panel-title">IntelliLearn Assistant</div>
                    <div class="chat-panel-subtitle">Online · answers about students &amp; courses</div>
                </div>
                <div class="chat-panel-actions">
                    <button type="button" class="chat-panel-icon-btn" id="chatPanelClear" title="Clear conversation" aria-label="Clear conversation"><i class="fas fa-broom"></i></button>
                    <button type="button" class="chat-panel-icon-btn" id="chatPanelClose" aria-label="Close chat"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div style="position: relative; flex: 1; overflow: hidden; display: flex;">
                <div class="chat-panel-messages" id="chatMessages"></div>
                <button type="button" class="chat-scroll-btn" id="chatScrollBtn"><i class="fas fa-arrow-down"></i> New messages</button>
            </div>
            <form class="chat-panel-input-row" id="chatForm">
                <textarea id="chatInput" placeholder="Ask about students, courses, teachers..." rows="1" maxlength="1000"></textarea>
                <button type="submit" id="chatSendBtn" aria-label="Send"><i class="fas fa-paper-plane"></i></button>
            </form>
        `;
        document.body.appendChild(panel);

        renderWelcome();

        fab.addEventListener('click', openPanel);
        panel.querySelector('#chatPanelClose').addEventListener('click', closePanel);
        panel.querySelector('#chatPanelClear').addEventListener('click', clearConversation);
        panel.querySelector('#chatForm').addEventListener('submit', onSubmit);

        const input = panel.querySelector('#chatInput');
        input.addEventListener('input', () => autoGrow(input));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                panel.querySelector('#chatForm').requestSubmit();
            }
        });

        const messages = panel.querySelector('#chatMessages');
        const scrollBtn = panel.querySelector('#chatScrollBtn');
        messages.addEventListener('scroll', () => {
            const nearBottom = messages.scrollHeight - messages.scrollTop - messages.clientHeight < 60;
            if (nearBottom) scrollBtn.classList.remove('visible');
        });
        scrollBtn.addEventListener('click', () => scrollToBottom(true));

        // Delegate copy-button clicks for any rendered code blocks.
        messages.addEventListener('click', (e) => {
            const btn = e.target.closest('.code-copy-btn');
            if (!btn) return;
            const code = decodeURIComponent(btn.dataset.code || '');
            navigator.clipboard?.writeText(code).then(() => {
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied';
                setTimeout(() => { btn.innerHTML = original; }, 1500);
            }).catch(() => {});
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && panel.classList.contains('open')) closePanel();
        });
    }

    function autoGrow(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 100) + 'px';
    }

    function renderWelcome() {
        const messages = document.getElementById('chatMessages');
        messages.innerHTML = '';
        appendMessage('bot', 'Hi! I can answer questions about students, teachers, courses, sections, and enrollments in the system. What would you like to know?', { skipHistory: true });
        renderSuggestions();
    }

    function renderSuggestions() {
        const messages = document.getElementById('chatMessages');
        const wrap = document.createElement('div');
        wrap.className = 'chat-suggestions';
        wrap.id = 'chatSuggestions';
        SUGGESTIONS.forEach((s) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'chat-suggestion-chip';
            chip.textContent = s;
            chip.addEventListener('click', () => {
                document.getElementById('chatInput').value = s;
                document.getElementById('chatForm').requestSubmit();
            });
            wrap.appendChild(chip);
        });
        messages.appendChild(wrap);
    }

    function clearConversation() {
        history = [];
        renderWelcome();
    }

    function openPanel() {
        document.getElementById('chatPanel').classList.add('open');
        document.getElementById('chatFabBtn').classList.add('hidden-while-open');
        unreadCount = 0;
        updateBadge();
        setTimeout(() => document.getElementById('chatInput')?.focus(), 150);
        scrollToBottom(false);
    }

    function closePanel() {
        document.getElementById('chatPanel').classList.remove('open');
        document.getElementById('chatFabBtn').classList.remove('hidden-while-open');
    }

    function updateBadge() {
        const badge = document.getElementById('chatFabBadge');
        if (!badge) return;
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    function scrollToBottom(smooth) {
        const messages = document.getElementById('chatMessages');
        if (!messages) return;
        messages.scrollTo({ top: messages.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
        document.getElementById('chatScrollBtn')?.classList.remove('visible');
    }

    function appendMessage(role, text, opts = {}) {
        document.getElementById('chatSuggestions')?.remove();

        const messages = document.getElementById('chatMessages');
        const row = document.createElement('div');
        row.className = 'chat-row ' + (role === 'user' ? 'user' : '');

        const avatar = document.createElement('div');
        avatar.className = 'chat-row-avatar';
        avatar.innerHTML = role === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';

        const group = document.createElement('div');
        group.className = 'chat-msg-group';

        const bubble = document.createElement('div');
        bubble.className = 'chat-msg ' + (role === 'user' ? 'chat-msg-user' : role === 'error' ? 'chat-msg-error' : 'chat-msg-bot');

        if (role === 'error') {
            bubble.innerHTML = `<p>${escapeHtml(text)}</p>`;
            if (opts.onRetry) {
                const retryBtn = document.createElement('button');
                retryBtn.type = 'button';
                retryBtn.className = 'retry-btn';
                retryBtn.innerHTML = '<i class="fas fa-rotate-right"></i> Retry';
                retryBtn.addEventListener('click', opts.onRetry);
                bubble.appendChild(retryBtn);
            }
        } else if (role === 'bot') {
            bubble.innerHTML = renderMarkdown(text);
        } else {
            bubble.textContent = text;
        }

        const time = document.createElement('div');
        time.className = 'chat-msg-time';
        time.textContent = formatTime(new Date());

        group.appendChild(bubble);
        group.appendChild(time);
        row.appendChild(avatar);
        row.appendChild(group);
        messages.appendChild(row);

        const isOpen = document.getElementById('chatPanel').classList.contains('open');
        const nearBottom = messages.scrollHeight - messages.scrollTop - messages.clientHeight < 120;
        if (nearBottom || role === 'user') {
            scrollToBottom(true);
        } else {
            document.getElementById('chatScrollBtn')?.classList.add('visible');
        }
        if (!isOpen && role === 'bot') {
            unreadCount += 1;
            updateBadge();
        }

        return bubble;
    }

    function appendTypingIndicator() {
        const messages = document.getElementById('chatMessages');
        const row = document.createElement('div');
        row.className = 'chat-row';
        row.id = 'chatTypingRow';
        row.innerHTML = `
            <div class="chat-row-avatar"><i class="fas fa-robot"></i></div>
            <div class="chat-msg-group">
                <div class="chat-msg chat-msg-bot">
                    <div class="chat-typing-dots"><span></span><span></span><span></span></div>
                </div>
            </div>
        `;
        messages.appendChild(row);
        scrollToBottom(true);
    }

    function removeTypingIndicator() {
        document.getElementById('chatTypingRow')?.remove();
    }

    // ---------- send / receive ----------

    async function sendToServer(text) {
        return fetch(CHAT_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message: text,
                history: history.slice(-MAX_HISTORY_TURNS),
            })
        });
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
        autoGrow(input);
        await runRequest(text);
    }

    async function runRequest(text) {
        const input = document.getElementById('chatInput');
        const sendBtn = document.getElementById('chatSendBtn');

        isSending = true;
        input.disabled = true;
        sendBtn.disabled = true;
        appendTypingIndicator();

        try {
            const res = await sendToServer(text);
            const data = await res.json();
            removeTypingIndicator();

            if (!res.ok || data.error) {
                appendMessage('error', data.error || 'Something went wrong. Please try again.', {
                    onRetry: () => runRequest(text)
                });
            } else {
                appendMessage('bot', data.reply);
                history.push({ role: 'user', content: text });
                history.push({ role: 'assistant', content: data.reply });
            }
        } catch (err) {
            removeTypingIndicator();
            appendMessage('error', 'Could not reach the assistant. Please check your connection and try again.', {
                onRetry: () => runRequest(text)
            });
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
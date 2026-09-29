(function () {
    if (document.getElementById('teacherChatAssistant')) return;

    var ENDPOINT = '/SUA-INTELLILEARN/public/teacher/assets/api/teacher_chatbot.php';
    var MAX_HISTORY = 8;
    var SUGGESTIONS = [
        'How many students do I have?',
        'Who are my students?',
        'What assignments are due?',
        'Which students have missing work?'
    ];
    var history = [];
    var sending = false;

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function renderMarkdown(raw) {
        var text = esc(raw);
        text = text.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        var lines = text.split('\n');
        var html = '', list = [], inList = false, para = [];

        function flushList() {
            if (!inList) return;
            html += '<ul>' + list.map(function (x) { return '<li>' + x + '</li>'; }).join('') + '</ul>';
            list = []; inList = false;
        }
        function flushPara() {
            if (!para.length) return;
            html += '<p>' + para.join('<br>') + '</p>';
            para = [];
        }

        lines.forEach(function (line) {
            var t = line.trim();
            var bullet = t.match(/^[-*+]\s+(.*)$/);
            var numbered = t.match(/^\d+[.)]\s+(.*)$/);
            if (!t) { flushList(); flushPara(); }
            else if (bullet || numbered) {
                flushPara();
                if (!inList) inList = true;
                list.push((bullet || numbered)[1]);
            } else {
                flushList();
                para.push(t);
            }
        });
        flushList(); flushPara();
        return html || '<p>' + text + '</p>';
    }

    var style = document.createElement('style');
    style.textContent = [
        '#teacherChatAssistant *{box-sizing:border-box}',
        '.tca-fab{position:fixed;right:22px;bottom:22px;z-index:9998;width:58px;height:58px;border:0;border-radius:50%;background:linear-gradient(135deg,#1b4332,#2d6a4f);color:#fff;cursor:pointer;display:grid;place-items:center;box-shadow:0 12px 30px rgba(0,0,0,.25);font-size:22px;transition:.18s}',
        '.tca-fab:hover{transform:scale(1.06)}.tca-fab.open{transform:scale(0);pointer-events:none}',
        '.tca-panel{position:fixed;right:22px;bottom:22px;z-index:9999;width:min(400px,calc(100vw - 28px));height:min(600px,78vh);min-height:420px;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 24px 65px rgba(15,23,42,.28);display:flex;flex-direction:column;opacity:0;pointer-events:none;transform:translateY(16px) scale(.97);transition:.2s;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}',
        '.tca-panel.open{opacity:1;pointer-events:auto}.tca-head{padding:14px 15px;color:#fff;background:linear-gradient(135deg,#1b4332,#2d6a4f);display:flex;align-items:center;gap:10px;flex-shrink:0}',
        '.tca-avatar{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.18);display:grid;place-items:center;flex-shrink:0}.tca-title{font-size:14px;font-weight:700}.tca-subtitle{font-size:11px;opacity:.82;margin-top:2px}.tca-actions{margin-left:auto;display:flex;gap:5px}.tca-icon{width:30px;height:30px;border:0;border-radius:50%;background:rgba(255,255,255,.14);color:#fff;cursor:pointer}',
        '.tca-messages{flex:1;overflow-y:auto;padding:14px 12px;background:#f6f8f7;scroll-behavior:smooth}.tca-row{display:flex;gap:7px;margin:7px 0}.tca-row.user{flex-direction:row-reverse}.tca-row-avatar{width:27px;height:27px;border-radius:50%;flex:0 0 27px;display:grid;place-items:center;font-size:11px;background:#1b4332;color:#fff;margin-top:2px}.tca-row.user .tca-row-avatar{background:#d1d5db;color:#374151}',
        '.tca-group{max-width:80%}.tca-row.user .tca-group{text-align:right}.tca-msg{display:inline-block;text-align:left;padding:9px 12px;border-radius:14px;font-size:13.5px;line-height:1.5;word-break:break-word}.tca-bot{background:#fff;border:1px solid #e5e7eb;border-bottom-left-radius:4px;color:#1f2937}.tca-user{background:#1b4332;color:#fff;border-bottom-right-radius:4px}.tca-error{background:#fff1f2;color:#b91c1c;border:1px solid #fecdd3}.tca-msg p{margin:0 0 7px}.tca-msg p:last-child{margin-bottom:0}.tca-msg ul{margin:4px 0 5px;padding-left:20px}.tca-msg li{margin-bottom:3px}.tca-time{font-size:10px;color:#94a3b8;margin:3px 3px 0}',
        '.tca-suggestions{padding:3px 3px 10px 34px;display:flex;flex-wrap:wrap;gap:6px}.tca-chip{border:1px solid #dbe5df;background:#fff;color:#1b4332;border-radius:15px;padding:6px 10px;font-size:11.5px;cursor:pointer}',
        '.tca-input{display:flex;gap:7px;align-items:flex-end;padding:10px;border-top:1px solid #e5e7eb;background:#fff;flex-shrink:0}.tca-input textarea{flex:1;resize:none;min-width:0;max-height:105px;min-height:38px;border:1px solid #dbe1dd;border-radius:18px;padding:9px 12px;font:13.5px/1.4 inherit;outline:none}.tca-input textarea:focus{border-color:#2d6a4f}.tca-send{width:38px;height:38px;border:0;border-radius:50%;background:#1b4332;color:#fff;cursor:pointer;flex:0 0 38px}.tca-send:disabled{opacity:.45;cursor:not-allowed}',
        '.tca-typing{display:flex;gap:3px;padding:6px 3px}.tca-typing span{width:6px;height:6px;border-radius:50%;background:#94a3b8;animation:tcaBounce 1.1s infinite}.tca-typing span:nth-child(2){animation-delay:.15s}.tca-typing span:nth-child(3){animation-delay:.3s}@keyframes tcaBounce{0%,60%,100%{transform:translateY(0);opacity:.45}30%{transform:translateY(-4px);opacity:1}}',
        '.tca-disclaimer{font-size:9.5px;color:#94a3b8;padding:0 12px 7px;text-align:center;background:#fff}',
        '@media(max-width:600px){.tca-fab{right:14px;bottom:14px;width:54px;height:54px;font-size:20px}.tca-panel{right:0;bottom:0;width:100vw;max-width:none;height:100dvh;max-height:none;min-height:0;border-radius:0}.tca-messages{padding:12px 9px}.tca-group{max-width:88%}.tca-msg{font-size:13px}.tca-input{padding:9px;padding-bottom:max(9px,env(safe-area-inset-bottom))}.tca-input textarea{font-size:16px}}'
    ].join('');
    document.head.appendChild(style);

    var root = document.createElement('div');
    root.id = 'teacherChatAssistant';
    root.innerHTML =
        '<button class="tca-fab" id="tcaFab" aria-label="Open teacher assistant" title="Teacher Assistant"><i class="fas fa-robot"></i></button>' +
        '<section class="tca-panel" id="tcaPanel" role="dialog" aria-label="Teacher Assistant">' +
        '<header class="tca-head"><div class="tca-avatar"><i class="fas fa-robot"></i></div>' +
        '<div><div class="tca-title">IntelliLearn Teacher Assistant</div><div class="tca-subtitle">Your classes, students & learning data</div></div>' +
        '<div class="tca-actions"><button class="tca-icon" id="tcaClear" title="Clear conversation"><i class="fas fa-broom"></i></button><button class="tca-icon" id="tcaClose" title="Close"><i class="fas fa-times"></i></button></div></header>' +
        '<div class="tca-messages" id="tcaMessages"></div>' +
        '<div class="tca-input"><textarea id="tcaInput" rows="1" placeholder="Ask about your students or classes..."></textarea><button class="tca-send" id="tcaSend" aria-label="Send"><i class="fas fa-paper-plane"></i></button></div>' +
        '<div class="tca-disclaimer">Answers use your teacher-scoped IntelliLearn data.</div></section>';
    document.body.appendChild(root);

    var fab = document.getElementById('tcaFab');
    var panel = document.getElementById('tcaPanel');
    var messages = document.getElementById('tcaMessages');
    var input = document.getElementById('tcaInput');
    var send = document.getElementById('tcaSend');

    function now() { return new Date().toLocaleTimeString([], {hour:'numeric',minute:'2-digit'}); }
    function scrollBottom() { messages.scrollTop = messages.scrollHeight; }

    function addMessage(role, content, error) {
        var row = document.createElement('div');
        row.className = 'tca-row ' + role;
        var avatar = role === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';
        var rendered = role === 'assistant' && !error ? renderMarkdown(content) : esc(content).replace(/\n/g,'<br>');
        row.innerHTML = '<div class="tca-row-avatar">' + avatar + '</div><div class="tca-group"><div class="tca-msg ' +
            (error ? 'tca-error' : (role === 'user' ? 'tca-user' : 'tca-bot')) + '">' + rendered +
            '</div><div class="tca-time">' + now() + '</div></div>';
        messages.appendChild(row);
        scrollBottom();
    }

    function typing(show) {
        var el = document.getElementById('tcaTyping');
        if (show && !el) {
            el = document.createElement('div');
            el.id = 'tcaTyping';
            el.className = 'tca-row';
            el.innerHTML = '<div class="tca-row-avatar"><i class="fas fa-robot"></i></div><div class="tca-group"><div class="tca-msg tca-bot"><div class="tca-typing"><span></span><span></span><span></span></div></div></div>';
            messages.appendChild(el); scrollBottom();
        } else if (!show && el) el.remove();
    }

    function addSuggestions() {
        var wrap = document.createElement('div');
        wrap.className = 'tca-suggestions';
        SUGGESTIONS.forEach(function (suggestion) {
            var btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'tca-chip'; btn.textContent = suggestion;
            btn.addEventListener('click', function () { input.value = suggestion; sendMessage(); });
            wrap.appendChild(btn);
        });
        messages.appendChild(wrap);
    }

    function reset() {
        history = [];
        messages.innerHTML = '';
        addMessage('assistant', 'Hi! I can answer questions about your students, classes, assignments, quizzes, attendance, scores, and other teacher-scoped IntelliLearn data.');
        addSuggestions();
    }

    function openChat() {
        panel.classList.add('open'); fab.classList.add('open');
        setTimeout(function(){ input.focus(); },100);
    }
    function closeChat() { panel.classList.remove('open'); fab.classList.remove('open'); }

    async function sendMessage() {
        var message = input.value.trim();
        if (!message || sending) return;
        input.value = ''; input.style.height = 'auto';
        addMessage('user', message);
        history.push({role:'user',content:message});
        history = history.slice(-MAX_HISTORY);
        sending = true; send.disabled = true; typing(true);

        try {
            var response = await fetch(ENDPOINT, {
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json'},
                body:JSON.stringify({message:message,history:history})
            });
            var data = await response.json().catch(function(){return {};});
            typing(false);
            if (!response.ok || !data.reply) {
                addMessage('assistant', data.error || 'The assistant could not answer right now.', true);
                return;
            }
            addMessage('assistant', data.reply);
            history.push({role:'assistant',content:data.reply});
            history = history.slice(-MAX_HISTORY);
        } catch (error) {
            typing(false);
            addMessage('assistant', 'Connection error. Please check your XAMPP/server connection and try again.', true);
        } finally {
            sending = false; send.disabled = false; input.focus();
        }
    }

    fab.addEventListener('click', openChat);
    document.getElementById('tcaClose').addEventListener('click', closeChat);
    document.getElementById('tcaClear').addEventListener('click', reset);
    send.addEventListener('click', sendMessage);

    input.addEventListener('input', function () {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight,105) + 'px';
    });
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(); }
    });

    reset();
})();

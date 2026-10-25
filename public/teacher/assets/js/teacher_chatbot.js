(function () {
    if (document.getElementById('teacherChatAssistant')) return;

    var ENDPOINT = '/SUA-INTELLILEARN/public/teacher/assets/api/teacher_chatbot.php';
    var FAB_ICON = '/SUA-INTELLILEARN/public/admin/assests/images/icon_chat.png';
    var MAX_HISTORY = 8;
    var SUGGESTIONS = [
        'Who is at risk in my classes?',
        'What is due in the next 2 weeks?',
        'How do I take attendance?',
        'Ideas to help my struggling students'
    ];
    var history = [];
    var sending = false;

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function renderMarkdown(raw) {
        // Escape first (XSS-safe), then apply a small Markdown subset:
        // headings, bold, inline code, bullet/numbered lists, tables, paragraphs.
        var lines = String(raw == null ? '' : raw).replace(/\r/g, '').split('\n');
        var html = '', list = null, para = [], i = 0;

        function inline(t) {
            return esc(t)
                .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
                .replace(/`([^`\n]+)`/g, '<code>$1</code>');
        }
        function flushPara() {
            if (!para.length) return;
            html += '<p>' + para.map(inline).join('<br>') + '</p>';
            para = [];
        }
        function closeList() {
            if (list) { html += '</' + list + '>'; list = null; }
        }
        function cells(l) {
            return l.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map(function (c) { return c.trim(); });
        }
        var isRow = function (l) { return /^\s*\|.+\|\s*$/.test(l); };
        var isSep = function (l) { return /^\s*\|[\s:\-|]+\|\s*$/.test(l || ''); };

        while (i < lines.length) {
            var line = lines[i], t = line.trim(), m;

            if (isRow(line) && isSep(lines[i + 1])) {
                flushPara(); closeList();
                html += '<div class="tca-table-wrap"><table><thead><tr>' +
                    cells(line).map(function (c) { return '<th>' + inline(c) + '</th>'; }).join('') +
                    '</tr></thead><tbody>';
                i += 2;
                while (i < lines.length && isRow(lines[i])) {
                    html += '<tr>' + cells(lines[i]).map(function (c) { return '<td>' + inline(c) + '</td>'; }).join('') + '</tr>';
                    i++;
                }
                html += '</tbody></table></div>';
                continue;
            }

            if (!t) { flushPara(); closeList(); }
            else if ((m = t.match(/^#{1,4}\s+(.*)$/))) {
                flushPara(); closeList();
                html += '<h4>' + inline(m[1]) + '</h4>';
            }
            else if ((m = t.match(/^[-*+]\s+(.*)$/))) {
                flushPara();
                if (list !== 'ul') { closeList(); html += '<ul>'; list = 'ul'; }
                html += '<li>' + inline(m[1]) + '</li>';
            }
            else if ((m = t.match(/^\d+[.)]\s+(.*)$/))) {
                flushPara();
                if (list !== 'ol') { closeList(); html += '<ol>'; list = 'ol'; }
                html += '<li>' + inline(m[1]) + '</li>';
            }
            else { closeList(); para.push(t); }
            i++;
        }
        flushPara(); closeList();
        return html || '<p>' + esc(raw) + '</p>';
    }

    var style = document.createElement('style');
    style.textContent = [
        '#teacherChatAssistant *{box-sizing:border-box}',
        '.tca-fab{position:fixed;right:22px;bottom:22px;z-index:9998;width:60px;height:60px;border:0;border-radius:50%;background:transparent;padding:0;cursor:pointer;display:grid;place-items:center;box-shadow:0 10px 28px rgba(0,0,0,.25);animation:tcaFloat 3s ease-in-out infinite;transition:transform .2s cubic-bezier(.34,1.56,.64,1),box-shadow .2s ease,opacity .2s ease}',
        '@keyframes tcaFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}',
        '.tca-fab:hover{animation-play-state:paused;transform:scale(1.07);box-shadow:0 14px 34px rgba(0,0,0,.35)}',
        '.tca-fab:active{transform:scale(.96)}',
        '.tca-fab.open{transform:scale(0);opacity:0;pointer-events:none}',
        '.tca-fab img{width:100%;height:100%;object-fit:cover;border-radius:50%;pointer-events:none}',
        '.tca-panel{position:fixed;right:22px;bottom:22px;z-index:9999;width:min(400px,calc(100vw - 28px));height:min(600px,78vh);min-height:420px;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 24px 65px rgba(15,23,42,.28);display:flex;flex-direction:column;opacity:0;pointer-events:none;transform:translateY(16px) scale(.97);transition:.2s;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}',
        '.tca-panel.open{opacity:1;pointer-events:auto;transform:translateY(0) scale(1)}',
        '.tca-head{padding:14px 15px;color:#fff;background:linear-gradient(135deg,#1b4332,#2d6a4f);display:flex;align-items:center;gap:10px;flex-shrink:0}',
        '.tca-avatar{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.18);display:grid;place-items:center;flex-shrink:0;position:relative}.tca-avatar:after{content:"";position:absolute;right:-1px;bottom:-1px;width:9px;height:9px;border-radius:50%;background:#22c55e;border:2px solid #1b4332}',
        '.tca-title{font-size:14px;font-weight:700}.tca-subtitle{font-size:11px;opacity:.82;margin-top:2px}.tca-actions{margin-left:auto;display:flex;gap:5px}.tca-icon{width:30px;height:30px;border:0;border-radius:50%;background:rgba(255,255,255,.14);color:#fff;cursor:pointer}',
        '.tca-messages{flex:1;overflow-y:auto;padding:14px 12px;background:#f6f8f7;scroll-behavior:smooth}.tca-row{display:flex;gap:7px;margin:7px 0}.tca-row.user{flex-direction:row-reverse}.tca-row-avatar{width:27px;height:27px;border-radius:50%;flex:0 0 27px;display:grid;place-items:center;font-size:11px;background:#1b4332;color:#fff;margin-top:2px}.tca-row.user .tca-row-avatar{background:#d1d5db;color:#374151}',
        '.tca-group{max-width:88%}.tca-row.user .tca-group{text-align:right}.tca-msg{display:inline-block;text-align:left;padding:9px 12px;border-radius:14px;font-size:13.5px;line-height:1.5;word-break:break-word}.tca-bot{background:#fff;border:1px solid #e5e7eb;border-bottom-left-radius:4px;color:#1f2937}.tca-user{background:#1b4332;color:#fff;border-bottom-right-radius:4px}.tca-error{background:#fff1f2;color:#b91c1c;border:1px solid #fecdd3}',
        '.tca-msg p{margin:0 0 7px}.tca-msg p:last-child{margin-bottom:0}.tca-msg ul{margin:4px 0 5px;padding-left:20px}.tca-msg li{margin-bottom:3px}.tca-time{font-size:10px;color:#94a3b8;margin:3px 3px 0}',
        '.tca-msg h4{margin:9px 0 4px;font-size:13.5px;font-weight:700;color:#1b4332}.tca-msg h4:first-child{margin-top:0}.tca-msg ol{margin:4px 0 5px;padding-left:22px}.tca-msg code{background:#eef2ef;padding:1px 5px;border-radius:4px;font-size:12.5px}.tca-table-wrap{overflow-x:auto;margin:6px 0}.tca-msg table{border-collapse:collapse;font-size:12px;min-width:100%}.tca-msg th,.tca-msg td{border:1px solid #e1e7e3;padding:4px 8px;text-align:left;white-space:nowrap}.tca-msg th{background:#eef5f0;color:#1b4332}',
        '.tca-suggestions{padding:3px 3px 10px 34px;display:flex;flex-wrap:wrap;gap:6px}.tca-chip{border:1px solid #dbe5df;background:#fff;color:#1b4332;border-radius:15px;padding:6px 10px;font-size:11.5px;cursor:pointer;max-width:100%}',
        '.tca-input{display:flex;gap:7px;align-items:flex-end;padding:10px;border-top:1px solid #e5e7eb;background:#fff;flex-shrink:0}.tca-input textarea{flex:1;resize:none;min-width:0;max-height:105px;min-height:38px;border:1px solid #dbe1dd;border-radius:18px;padding:9px 12px;font:13.5px/1.4 inherit;outline:none}.tca-input textarea:focus{border-color:#2d6a4f}.tca-send{width:38px;height:38px;border:0;border-radius:50%;background:#1b4332;color:#fff;cursor:pointer;flex:0 0 38px}.tca-send:disabled{opacity:.45;cursor:not-allowed}',
        '.tca-typing{display:flex;gap:3px;padding:6px 3px}.tca-typing span{width:6px;height:6px;border-radius:50%;background:#94a3b8;animation:tcaBounce 1.1s infinite}.tca-typing span:nth-child(2){animation-delay:.15s}.tca-typing span:nth-child(3){animation-delay:.3s}@keyframes tcaBounce{0%,60%,100%{transform:translateY(0);opacity:.45}30%{transform:translateY(-4px);opacity:1}}',
        '.tca-disclaimer{font-size:9.5px;color:#94a3b8;padding:0 12px 7px;text-align:center;background:#fff}',
        '@media(max-width:700px){.tca-fab{right:14px;bottom:14px;width:56px;height:56px}.tca-panel{right:0;bottom:0;width:100%;height:100dvh;max-height:100dvh;min-height:0;border-radius:0}.tca-head{padding:12px 13px}.tca-title{font-size:13.5px}.tca-subtitle{font-size:10.5px}.tca-messages{padding:10px 8px}.tca-group{max-width:86%}.tca-msg{font-size:13px;padding:8px 10px}.tca-suggestions{padding-left:32px;padding-right:2px}.tca-chip{font-size:11px;padding:6px 9px}.tca-input{padding:8px;padding-bottom:max(8px,env(safe-area-inset-bottom))}.tca-input textarea{font-size:16px;max-height:96px}.tca-send{width:40px;height:40px;flex-basis:40px}}',
        '@media(max-width:380px){.tca-actions{gap:3px}.tca-icon{width:28px;height:28px}.tca-row-avatar{width:25px;height:25px;flex-basis:25px}.tca-group{max-width:88%}}',
        '.tca-panel{transition:transform .2s ease,opacity .2s ease,width .25s ease,height .25s ease,right .25s ease,bottom .25s ease,border-radius .25s ease}',
        '.tca-expanded #tcaExpand{display:none}',
        '.tca-panel.tca-expanded{right:0;bottom:0;width:100vw;height:100vh;height:100dvh;max-height:100vh;max-height:100dvh;border-radius:0}',
        '.tca-icon{width:32px;height:32px;display:grid;place-items:center;transition:background .15s}.tca-icon:hover{background:rgba(255,255,255,.28)}',
        '.tca-messages{overscroll-behavior:contain}',
        '.tca-expanded .tca-head{padding:14px max(20px,calc((100% - 900px)/2))}.tca-expanded .tca-messages{padding:28px max(20px,calc((100% - 820px)/2))}.tca-expanded .tca-msg{font-size:15px}.tca-expanded .tca-group{max-width:75%}',
        '.tca-input{align-items:flex-end;gap:8px;margin:6px 14px 0;padding:6px 6px 6px 18px;border:1px solid #dfe5e1;border-radius:26px;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:border-color .15s,box-shadow .15s}',
        '.tca-input:focus-within{border-color:#2d6a4f;box-shadow:0 0 0 4px rgba(45,106,79,.14)}',
        '.tca-input textarea{border:0;border-radius:0;background:transparent;padding:9px 0;min-height:22px;max-height:160px;font-family:inherit;font-size:14.5px;line-height:1.45;color:#1f2937}',
        '.tca-input textarea:focus{border:0}',
        '.tca-send{display:grid;place-items:center;transition:background .15s,transform .1s}.tca-send:active:not(:disabled){transform:scale(.94)}',
        '.tca-input:not(.has-text) .tca-send:not(:disabled){background:#cbd5d0;cursor:default}',
        '.tca-disclaimer{padding:7px 12px 10px;font-size:11px}',
        '.tca-expanded .tca-input{width:min(820px,calc(100% - 40px));margin:6px auto 0}',
        '@media(max-width:700px){.tca-icon[data-expand]{display:none}.tca-input{margin:6px 10px 0}.tca-input textarea{font-size:16px}}'
    ].join('');
    document.head.appendChild(style);

    var root = document.createElement('div');
    root.id = 'teacherChatAssistant';
    root.innerHTML =
        '<button class="tca-fab" id="tcaFab" aria-label="Open teacher assistant" title="Teacher Assistant"><img src="' + FAB_ICON + '" alt="Teacher Assistant"></button>' +
        '<section class="tca-panel" id="tcaPanel" role="dialog" aria-label="Teacher Assistant">' +
        '<header class="tca-head"><div class="tca-avatar"><i class="fas fa-robot"></i></div>' +
        '<div><div class="tca-title">IntelliLearn Teacher Assistant</div><div class="tca-subtitle">Your classes, students & learning data</div></div>' +
        '<div class="tca-actions"><button class="tca-icon" id="tcaClear" title="Clear conversation"><i class="fas fa-broom"></i></button><button class="tca-icon" id="tcaExpand" data-expand title="Full screen" aria-label="Enter full screen"><i class="fas fa-expand"></i></button><button class="tca-icon" id="tcaClose" title="Minimize" aria-label="Minimize"><i class="fas fa-minus"></i></button></div></header>' +
        '<div class="tca-messages" id="tcaMessages"></div>' +
        '<div class="tca-input"><textarea id="tcaInput" rows="1" aria-label="Message"></textarea><button class="tca-send" id="tcaSend" aria-label="Send"><i class="fas fa-paper-plane"></i></button></div>' +
        '<div class="tca-disclaimer">Answers use your IntelliLearn class data. AI can make mistakes.</div></section>';
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
        addMessage('assistant', 'Hi! I can report on your classes, students, attendance, assignments and quizzes, explain how to use IntelliLearn, and suggest ways to support your students. What would you like to know?');
        addSuggestions();
    }

    function openChat() {
        panel.classList.add('open'); fab.classList.add('open');
        setTimeout(function(){ input.focus(); },100);
    }
    function closeChat() { panel.classList.remove('open'); fab.classList.remove('open'); }

    // Minimize: from fullscreen go back to the small panel; from the small panel collapse to the floating icon.
    function minimizeChat() {
        if (panel.classList.contains('tca-expanded')) toggleExpand(); else closeChat();
    }

    function syncComposer() {
        input.parentNode.classList.toggle('has-text', input.value.trim().length > 0);
    }

    function toggleExpand() {
        panel.classList.toggle('tca-expanded');
        scrollBottom();
        input.focus();
    }

    async function sendMessage() {
        var message = input.value.trim();
        if (!message || sending) return;
        input.value = ''; input.style.height = 'auto'; syncComposer();
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
    document.getElementById('tcaClose').addEventListener('click', minimizeChat);
    document.getElementById('tcaExpand').addEventListener('click', toggleExpand);
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || !panel.classList.contains('open')) return;
        minimizeChat();
    });
    document.getElementById('tcaClear').addEventListener('click', reset);
    send.addEventListener('click', sendMessage);

    input.addEventListener('input', function () {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight,160) + 'px';
        syncComposer();
    });
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(); }
    });

    reset();
})();
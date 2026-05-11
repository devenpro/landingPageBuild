// site/public/assets/js/chat-widget.js — public chatbot widget (Phase 13).
//
// Loaded by site/layout.php only when GUA_AI_CHAT_ENABLED=1. Renders a
// floating bubble bottom-right; clicking opens a panel with a message
// list, input, and send button. Conversation lives in localStorage so
// it survives page reloads on the same site; "Reset" clears it.
//
// The server is stateless: each turn we POST the entire transcript to
// /api/chat.php. Server-side persistence (ai_chat_messages) is purely
// for admin analytics and is opt-in via AI_CHAT_PERSIST.
//
// We don't send a CSRF token because the endpoint is public; per-IP
// and global token caps in core/lib/ai/ratelimit.php are the abuse
// protection.

(function () {
    'use strict';

    const SESSION_KEY = 'gua_chat_session_id';
    const HISTORY_KEY = 'gua_chat_history';
    const MAX_TURNS   = 30; // server caps at 40; we cap earlier so the user can keep chatting

    // ----- Storage helpers --------------------------------------------------

    function uuid() {
        if (window.crypto && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        // Fallback for older browsers — not cryptographically strong, fine
        // for an opaque session token.
        return 'gua-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    }

    function getSessionId() {
        let id = '';
        try { id = localStorage.getItem(SESSION_KEY) || ''; } catch (_) { /* private mode */ }
        if (!id) {
            id = uuid().replace(/[^A-Za-z0-9_-]/g, '_');
            try { localStorage.setItem(SESSION_KEY, id); } catch (_) { /* swallow */ }
        }
        return id;
    }

    function loadHistory() {
        try {
            const raw = localStorage.getItem(HISTORY_KEY);
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (_) { return []; }
    }

    function saveHistory(history) {
        try { localStorage.setItem(HISTORY_KEY, JSON.stringify(history)); } catch (_) { /* swallow */ }
    }

    function resetConversation() {
        try {
            localStorage.removeItem(HISTORY_KEY);
            localStorage.removeItem(SESSION_KEY);
        } catch (_) { /* swallow */ }
    }

    // ----- DOM --------------------------------------------------------------

    function el(tag, props, ...children) {
        const e = document.createElement(tag);
        if (props) for (const k of Object.keys(props)) {
            if (k === 'class') e.className = props[k];
            else if (k === 'html') e.innerHTML = props[k];
            else if (k.startsWith('on') && typeof props[k] === 'function') e.addEventListener(k.slice(2), props[k]);
            else e.setAttribute(k, props[k]);
        }
        for (const c of children) {
            if (c == null) continue;
            e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
        }
        return e;
    }

    // ----- Widget -----------------------------------------------------------

    const messagesArea = el('div', { class: 'gua-chat__messages', role: 'log', 'aria-live': 'polite' });
    const input = el('textarea', {
        class: 'gua-chat__input',
        rows: '1',
        placeholder: 'Ask a question…',
        'aria-label': 'Your message',
        maxlength: '4000',
    });
    const sendBtn = el('button', { type: 'button', class: 'gua-chat__send', 'aria-label': 'Send' }, '➤');
    const resetBtn = el('button', { type: 'button', class: 'gua-chat__reset', 'aria-label': 'Reset conversation' }, 'Reset');

    const panel = el('div', { class: 'gua-chat__panel', role: 'dialog', 'aria-label': 'Chat assistant' },
        el('header', { class: 'gua-chat__header' },
            el('span', { class: 'gua-chat__title' }, 'Chat with us'),
            resetBtn,
            el('button', { type: 'button', class: 'gua-chat__close', 'aria-label': 'Close',
                onclick: () => closePanel() }, '×')
        ),
        messagesArea,
        el('form', { class: 'gua-chat__form',
            onsubmit: (e) => { e.preventDefault(); send(); }
        }, input, sendBtn)
    );

    const bubble = el('button', {
        type: 'button', class: 'gua-chat__bubble', 'aria-label': 'Open chat',
        onclick: () => togglePanel()
    }, el('span', { class: 'gua-chat__bubble-icon' }, '💬'));

    const root = el('div', { class: 'gua-chat', id: 'gua-chat' }, bubble, panel);

    function appendMessage(role, content, opts) {
        opts = opts || {};
        const msg = el('div', { class: 'gua-chat__msg gua-chat__msg--' + role },
            el('div', { class: 'gua-chat__bubble-msg' }, content)
        );
        if (opts.transient) msg.dataset.transient = '1';
        messagesArea.appendChild(msg);
        messagesArea.scrollTop = messagesArea.scrollHeight;
        return msg;
    }

    function clearTransient() {
        messagesArea.querySelectorAll('[data-transient="1"]').forEach((n) => n.remove());
    }

    function renderHistory() {
        messagesArea.innerHTML = '';
        const history = loadHistory();
        if (history.length === 0) {
            appendMessage('assistant', "Hi! I'm here to help with questions about this site. What would you like to know?");
        } else {
            for (const m of history) appendMessage(m.role, m.content);
        }
    }

    function openPanel()  { panel.classList.add('gua-chat__panel--open');  bubble.setAttribute('aria-expanded', 'true');  setTimeout(() => input.focus(), 50); }
    function closePanel() { panel.classList.remove('gua-chat__panel--open'); bubble.setAttribute('aria-expanded', 'false'); bubble.focus(); }
    function togglePanel() {
        if (panel.classList.contains('gua-chat__panel--open')) closePanel();
        else openPanel();
    }

    // Escape closes the panel when it's open and returns focus to the bubble.
    // Listening on document so it fires regardless of what's focused inside
    // the panel (the textarea swallows keydowns otherwise).
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && panel.classList.contains('gua-chat__panel--open')) {
            closePanel();
        }
    });

    async function send() {
        const text = input.value.trim();
        if (!text) return;
        input.value = '';
        input.style.height = 'auto';

        const history = loadHistory();
        history.push({ role: 'user', content: text });
        if (history.length > MAX_TURNS * 2) {
            // Drop the oldest pair if the visitor pushes past our soft cap.
            history.splice(0, 2);
        }
        saveHistory(history);
        appendMessage('user', text);

        sendBtn.disabled = true;
        const typing = appendMessage('assistant', '…', { transient: true });
        typing.classList.add('gua-chat__msg--typing');

        try {
            const res = await fetch('/api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    session_id: getSessionId(),
                    messages: history,
                }),
            });
            const body = await res.json().catch(() => null);
            clearTransient();
            if (res.ok && body && body.ok) {
                history.push({ role: 'assistant', content: body.reply || '' });
                saveHistory(history);
                appendMessage('assistant', body.reply || '(empty reply)');
            } else {
                let msg = (body && body.error) || ('Error ' + res.status);
                if (res.status === 429) msg = "We've hit the chat rate limit. Try again in a moment.";
                else if (res.status === 404) msg = 'Chat is currently disabled on this site.';
                else msg = "Sorry — I couldn't reach the assistant. " + msg;
                appendMessage('assistant', msg);
            }
        } catch (err) {
            clearTransient();
            appendMessage('assistant', "Network error: " + (err.message || err));
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    }

    // Auto-grow textarea up to ~5 lines.
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });

    resetBtn.addEventListener('click', () => {
        if (!confirm('Clear this conversation?')) return;
        resetConversation();
        renderHistory();
    });

    // Mount
    function mount() {
        document.body.appendChild(root);
        renderHistory();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
    else mount();
})();

/**
 * Bleikøya Chat Widget
 *
 * Vanilla JS chat interface with SSE streaming from the FastAPI agent.
 * Uses marked.js for Markdown rendering of assistant responses.
 */
(function () {
    'use strict';

    // Configuration injected by WordPress page template (or empty for local dev)
    var config = window.AGENT_CONFIG || {};
    var BASE_URL = config.baseUrl || '';
    var AUTH_TOKEN = config.token || '';

    var messages = []; // Conversation history sent to the API

    var form = document.getElementById('form');
    var input = document.getElementById('input');
    var messagesEl = document.getElementById('messages');

    marked.setOptions({ breaks: true, gfm: true });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var text = input.value.trim();
        if (!text) return;

        appendMessage('user', text);
        messages.push({ role: 'user', content: text });
        input.value = '';
        setInputEnabled(false);
        autoScroll = true;
        showThinking();

        var bubbleEl = null;
        var streamedText = '';
        var fullText = '';
        var needNewBubble = true;

        try {
            var headers = { 'Content-Type': 'application/json' };
            if (AUTH_TOKEN) {
                headers['Authorization'] = 'Bearer ' + AUTH_TOKEN;
            }

            var response = await fetch(BASE_URL + '/chat', {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({ messages: messages }),
            });

            if (!response.ok) {
                throw new Error('Server svarte med ' + response.status);
            }

            var reader = response.body.getReader();
            var decoder = new TextDecoder();
            var buffer = '';

            while (true) {
                var result = await reader.read();
                if (result.done) break;

                buffer += decoder.decode(result.value, { stream: true });

                var lines = buffer.split('\n');
                buffer = lines.pop(); // Keep incomplete line

                var eventType = null;
                for (var i = 0; i < lines.length; i++) {
                    var line = lines[i];
                    if (line.startsWith('event: ')) {
                        eventType = line.substring(7);
                    } else if (line.startsWith('data: ') && eventType) {
                        var data = JSON.parse(line.substring(6));

                        if (eventType === 'text') {
                            if (needNewBubble) {
                                hideThinking();
                                hideSearching();
                                bubbleEl = appendMessage('assistant', '');
                                streamedText = '';
                                needNewBubble = false;
                            }
                            streamedText += data.text;
                            fullText += data.text;
                            bubbleEl.innerHTML = marked.parse(streamedText);
                            scrollToBottom();
                        } else if (eventType === 'tool_start') {
                            hideThinking();
                            showSearching(data.tool, data.input);
                        } else if (eventType === 'tool_done') {
                            // Don't hide indicators yet — keep them visible
                            // until next text arrives. Just mark that a new
                            // bubble should be created for the next text.
                            needNewBubble = true;
                        } else if (eventType === 'error') {
                            hideThinking();
                            hideSearching();
                            if (!bubbleEl) {
                                bubbleEl = appendMessage('assistant', '');
                            }
                            bubbleEl.textContent = data.error;
                            bubbleEl.classList.add('chat__bubble--error');
                        }
                        eventType = null;
                    } else if (line === '') {
                        eventType = null;
                    }
                }
            }

            hideThinking();
            hideSearching();
            if (fullText) {
                messages.push({ role: 'assistant', content: fullText });
            }
        } catch (err) {
            console.error('Chat error:', err);
            hideThinking();
            hideSearching();
            if (!bubbleEl) {
                bubbleEl = appendMessage('assistant', '');
            }
            bubbleEl.textContent = 'Beklager, noe gikk galt. Er agentserveren startet?';
            bubbleEl.classList.add('chat__bubble--error');
        }

        setInputEnabled(true);
        input.focus();
    });

    function appendMessage(role, text) {
        var wrapper = document.createElement('div');
        wrapper.className = 'chat__message chat__message--' + role;
        var bubble = document.createElement('div');
        bubble.className = 'chat__bubble';
        if (role === 'user') {
            bubble.textContent = text;
        } else {
            bubble.innerHTML = text ? marked.parse(text) : '';
        }
        wrapper.appendChild(bubble);
        messagesEl.appendChild(wrapper);
        scrollToBottom();
        return bubble;
    }

    function describeToolUse(toolName, input) {
        switch (toolName) {
            case 'mcp__wp__search':
                return 'Søker på nettsiden' + (input.query ? ': \u00ab' + input.query + '\u00bb' : '');
            case 'mcp__wp__get_post':
                return 'Leser innlegg #' + (input.post_id || '');
            case 'mcp__wp__drive_search':
                return 'Søker i dokumentarkivet' + (input.query ? ': \u00ab' + input.query + '\u00bb' : '');
            case 'mcp__wp__drive_read_doc':
                return 'Leser dokument fra arkivet';
            default:
                return 'Arbeider';
        }
    }

    function showThinking() {
        var el = document.createElement('div');
        el.className = 'chat__thinking';
        el.textContent = 'Tenker\u2026';
        messagesEl.appendChild(el);
        scrollToBottom();
    }

    function hideThinking() {
        messagesEl.querySelectorAll('.chat__thinking').forEach(function (el) {
            el.remove();
        });
    }

    function showSearching(toolName, input) {
        var el = document.createElement('div');
        el.className = 'chat__searching';
        el.textContent = describeToolUse(toolName, input || {}) + '\u2026';
        messagesEl.appendChild(el);
        scrollToBottom();
    }

    function hideSearching() {
        messagesEl.querySelectorAll('.chat__searching').forEach(function (el) {
            el.remove();
        });
    }

    // Auto-scroll during active response. Disabled if user scrolls up manually.
    var autoScroll = true;
    var lastScrollY = window.scrollY;

    window.addEventListener('scroll', function () {
        // If user scrolled up during a response, stop auto-scrolling
        if (window.scrollY < lastScrollY - 30) {
            autoScroll = false;
        }
        lastScrollY = window.scrollY;
    }, { passive: true });

    function scrollToBottom() {
        if (!autoScroll) return;
        var lastChild = messagesEl.lastElementChild;
        if (!lastChild) return;
        var rect = lastChild.getBoundingClientRect();
        var targetY = window.scrollY + rect.bottom - window.innerHeight + 100;
        if (targetY > window.scrollY) {
            window.scrollTo({ top: targetY, behavior: 'smooth' });
        }
    }

    function setInputEnabled(enabled) {
        input.disabled = !enabled;
        form.querySelector('.chat__send').disabled = !enabled;
    }
})();

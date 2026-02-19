/**
 * Bleikøya Chat Widget
 *
 * Vanilla JS chat interface with SSE streaming from the FastAPI agent.
 * Uses marked.js for Markdown rendering of assistant responses.
 */
(function () {
    'use strict';

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

        var bubbleEl = appendMessage('assistant', '');
        var streamedText = '';

        try {
            var response = await fetch('/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
                            streamedText += data.text;
                            bubbleEl.innerHTML = marked.parse(streamedText);
                            scrollToBottom();
                        } else if (eventType === 'tool_start') {
                            showSearching();
                        } else if (eventType === 'tool_done') {
                            hideSearching();
                        } else if (eventType === 'error') {
                            hideSearching();
                            bubbleEl.textContent = data.error;
                            bubbleEl.classList.add('chat__bubble--error');
                        }
                        eventType = null;
                    } else if (line === '') {
                        eventType = null;
                    }
                }
            }

            hideSearching();
            if (streamedText) {
                messages.push({ role: 'assistant', content: streamedText });
            }
        } catch (err) {
            console.error('Chat error:', err);
            hideSearching();
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

    function showSearching() {
        if (messagesEl.querySelector('.chat__searching')) return;
        var el = document.createElement('div');
        el.className = 'chat__searching';
        el.textContent = 'Søker på nettsiden\u2026';
        messagesEl.appendChild(el);
        scrollToBottom();
    }

    function hideSearching() {
        messagesEl.querySelectorAll('.chat__searching').forEach(function (el) {
            el.remove();
        });
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function setInputEnabled(enabled) {
        input.disabled = !enabled;
        form.querySelector('.chat__send').disabled = !enabled;
    }
})();

const appendMessage = (container, emptyState, message) => {
    if (!container || !message?.id) {
        return;
    }

    if (container.querySelector(`[data-message-id="${message.id}"]`)) {
        return;
    }

    emptyState?.classList.add('hidden');

    const article = document.createElement('article');
    article.dataset.messageId = message.id;
    article.className = 'rounded border border-slate-200 bg-white px-3 py-2';

    const meta = document.createElement('div');
    meta.className = 'flex items-center justify-between gap-3 text-xs text-slate-500';

    const nickname = document.createElement('span');
    nickname.className = 'font-medium text-slate-800';
    nickname.textContent = message.nickname || 'Guest';

    const time = document.createElement('time');
    time.dateTime = message.created_at || '';
    time.textContent = message.created_at
        ? new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : '';

    const content = document.createElement('p');
    content.className = 'mt-1 whitespace-pre-wrap break-words text-sm text-slate-800';
    content.textContent = message.content || '';

    meta.append(nickname, time);
    article.append(meta, content);
    container.append(article);
    container.scrollTop = container.scrollHeight;
};

const appendSystemMessage = (container, text) => {
    if (!container || !text) {
        return;
    }

    const paragraph = document.createElement('p');
    paragraph.className = 'text-center text-xs text-slate-500';
    paragraph.textContent = text;
    container.append(paragraph);
    container.scrollTop = container.scrollHeight;
};

const setStatus = (element, text, tone = 'slate') => {
    if (!element) {
        return;
    }

    element.textContent = text;
    element.dataset.tone = tone;
};

const initLiveChat = () => {
    const root = document.querySelector('[data-live-chat]');

    if (!root) {
        return;
    }

    const roomId = root.dataset.roomId;
    const messagesUrl = root.dataset.messagesUrl;
    const channelName = `live-room.${roomId}`;
    const messages = root.querySelector('[data-chat-messages]');
    const emptyState = root.querySelector('[data-chat-empty]');
    const form = root.querySelector('[data-chat-form]');
    const nicknameInput = root.querySelector('[data-chat-nickname]');
    const contentInput = root.querySelector('[data-chat-content]');
    const submitButton = root.querySelector('[data-chat-submit]');
    const error = root.querySelector('[data-chat-error]');
    const status = root.querySelector('[data-chat-status]');
    const onlineCount = root.querySelector('[data-chat-online-count]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    let fallbackSubscribed = false;

    const subscribeToPublicChannel = () => {
        if (fallbackSubscribed || !window.Echo) {
            return;
        }

        fallbackSubscribed = true;
        window.Echo.channel(channelName)
            .listen('.MessageSent', (event) => appendMessage(messages, emptyState, event));
    };

    if (window.Echo) {
        try {
            window.Echo.join(channelName)
                .here((users) => {
                    onlineCount.textContent = users.length;
                    setStatus(status, 'Presence connected', 'emerald');
                })
                .joining((user) => {
                    onlineCount.textContent = Number(onlineCount.textContent || 0) + 1;
                    appendSystemMessage(messages, `${user.name || 'Viewer'} joined`);
                })
                .leaving((user) => {
                    onlineCount.textContent = Math.max(Number(onlineCount.textContent || 1) - 1, 0);
                    appendSystemMessage(messages, `${user.name || 'Viewer'} left`);
                })
                .error(() => {
                    setStatus(status, 'Presence unavailable; using public chat fallback', 'amber');
                    subscribeToPublicChannel();
                })
                .listen('.MessageSent', (event) => appendMessage(messages, emptyState, event));
        } catch (error) {
            setStatus(status, 'Presence unavailable; using public chat fallback', 'amber');
            subscribeToPublicChannel();
        }
    } else {
        setStatus(status, 'Echo is not loaded', 'red');
    }

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const content = contentInput.value.trim();

        if (!content) {
            return;
        }

        error?.classList.add('hidden');
        submitButton.disabled = true;

        try {
            const response = await fetch(messagesUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    nickname: nicknameInput?.value?.trim() || null,
                    content,
                }),
            });

            if (!response.ok) {
                throw new Error('Message could not be sent.');
            }

            contentInput.value = '';
            contentInput.focus();
        } catch (sendError) {
            if (error) {
                error.textContent = sendError.message;
                error.classList.remove('hidden');
            }
        } finally {
            submitButton.disabled = false;
        }
    });
};

document.addEventListener('DOMContentLoaded', initLiveChat);

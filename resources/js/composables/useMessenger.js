import axios from 'axios';
import { nextTick, ref } from 'vue';

export function useMessenger({ scrollTarget = null } = {}) {
    const conversations = ref([]);
    const colleagues = ref([]);
    const messages = ref([]);
    const activeConversation = ref(null);
    const activeConversationId = ref(null);
    const unreadCount = ref(0);
    const conversationsLoading = ref(false);
    const colleaguesLoading = ref(false);
    const threadLoading = ref(false);
    const sending = ref(false);
    const error = ref('');

    function syncActiveConversationFromList() {
        if (activeConversationId.value === null) {
            return;
        }

        activeConversation.value = conversations.value.find(
            (conversation) => Number(conversation.id) === Number(activeConversationId.value),
        ) ?? activeConversation.value;
    }

    async function loadConversations({ background = false, channel = null } = {}) {
        if (!background) {
            conversationsLoading.value = true;
        }

        try {
            const params = {};
            if (channel) {
                params.channel = channel;
            }

            const { data } = await axios.get(route('messenger.conversations.index'), {
                headers: { Accept: 'application/json' },
                params,
            });
            conversations.value = data.conversations ?? [];
            unreadCount.value = data.unread_count ?? 0;
            syncActiveConversationFromList();
        } finally {
            if (!background) {
                conversationsLoading.value = false;
            }
        }
    }

    async function loadCounterpartyContacts() {
        try {
            const { data } = await axios.get(route('messenger.counterparty-contacts'), {
                headers: { Accept: 'application/json' },
            });

            return data.contacts ?? [];
        } catch {
            return [];
        }
    }

    async function loadCounterpartyOrders(conversationId) {
        const { data } = await axios.get(route('messenger.conversations.counterparty-orders', {
            conversation: conversationId,
        }), {
            headers: { Accept: 'application/json' },
        });

        return data.orders ?? [];
    }

    async function openCounterparty(payload) {
        error.value = '';

        try {
            const { data } = await axios.post(route('messenger.conversations.open-counterparty'), payload, {
                headers: { Accept: 'application/json' },
            });

            await loadConversations();

            if (data.conversation) {
                await selectConversation(data.conversation);
            }
        } catch (exception) {
            const message = exception.response?.data?.message
                ?? exception.response?.data?.errors?.contractor_id?.[0]
                ?? exception.response?.data?.errors?.external_party?.[0];
            error.value = typeof message === 'string' ? message : 'Не удалось открыть чат с контрагентом.';
            throw exception;
        }
    }

    async function loadColleagues() {
        colleaguesLoading.value = true;

        try {
            const { data } = await axios.get(route('messenger.colleagues'), {
                headers: { Accept: 'application/json' },
            });
            colleagues.value = data.users ?? [];
        } finally {
            colleaguesLoading.value = false;
        }
    }

    async function loadThread(conversationId, { background = false } = {}) {
        if (!background) {
            threadLoading.value = true;
            messages.value = [];
        }

        try {
            const { data } = await axios.get(route('messenger.conversations.messages', { conversation: conversationId }), {
                headers: { Accept: 'application/json' },
            });
            messages.value = data.messages ?? [];
            if (!background) {
                await loadConversations({ background: true });
            }
            scrollToBottom();
        } finally {
            if (!background) {
                threadLoading.value = false;
            }
        }
    }

    async function refreshThread({ background = true } = {}) {
        if (activeConversationId.value === null) {
            return;
        }

        const lastMessageId = messages.value.length > 0
            ? Number(messages.value[messages.value.length - 1]?.id ?? 0)
            : 0;

        if (lastMessageId <= 0) {
            await loadThread(activeConversationId.value, { background });

            return;
        }

        try {
            const { data } = await axios.get(route('messenger.conversations.messages', {
                conversation: activeConversationId.value,
            }), {
                headers: { Accept: 'application/json' },
                params: {
                    after_id: lastMessageId,
                },
            });

            const incoming = data.messages ?? [];
            if (incoming.length === 0) {
                return;
            }

            const existingIds = new Set(messages.value.map((message) => Number(message.id)));
            const merged = [...messages.value];

            for (const message of incoming) {
                if (!existingIds.has(Number(message.id))) {
                    merged.push(message);
                }
            }

            messages.value = merged;
            await loadConversations({ background: true });
            scrollToBottom();
        } catch {
            if (!background) {
                await loadThread(activeConversationId.value, { background: false });
            }
        }
    }

    async function selectConversation(conversation) {
        error.value = '';
        activeConversation.value = conversation;
        activeConversationId.value = Number(conversation.id);
        await loadThread(Number(conversation.id));
    }

    async function openDirect(user) {
        error.value = '';

        try {
            const { data } = await axios.post(route('messenger.conversations.open'), {
                user_id: user.id,
            }, {
                headers: { Accept: 'application/json' },
            });

            await loadConversations();

            if (data.conversation) {
                await selectConversation(data.conversation);
            }
        } catch (exception) {
            const message = exception.response?.data?.message ?? exception.response?.data?.errors?.user_id?.[0];
            error.value = typeof message === 'string' ? message : 'Не удалось открыть чат.';
            throw exception;
        }
    }

    async function createGroup(title, userIds) {
        error.value = '';

        try {
            const { data } = await axios.post(route('messenger.conversations.groups.store'), {
                title,
                user_ids: userIds,
            }, {
                headers: { Accept: 'application/json' },
            });

            await loadConversations();

            if (data.conversation) {
                await selectConversation(data.conversation);
            }

            return data.conversation ?? null;
        } catch (exception) {
            const message = exception.response?.data?.message
                ?? exception.response?.data?.errors?.title?.[0]
                ?? exception.response?.data?.errors?.user_ids?.[0];
            error.value = typeof message === 'string' ? message : 'Не удалось создать группу.';
            throw exception;
        }
    }

    async function sendMessage(body, payload = {}) {
        const text = String(body ?? '').trim();
        if (!activeConversation.value || text === '') {
            return null;
        }

        sending.value = true;
        error.value = '';

        try {
            const { data } = await axios.post(route('messenger.conversations.messages.store', {
                conversation: activeConversation.value.id,
            }), {
                body: text,
                ...payload,
            }, {
                headers: { Accept: 'application/json' },
            });

            if (data.message) {
                messages.value = [...messages.value, data.message];
            }

            await loadConversations();
            scrollToBottom();

            return data.message ?? null;
        } catch (exception) {
            const message = exception.response?.data?.message ?? exception.response?.data?.errors?.body?.[0];
            error.value = typeof message === 'string' ? message : 'Не удалось отправить сообщение.';
            throw exception;
        } finally {
            sending.value = false;
        }
    }

    async function reloadAll() {
        await Promise.all([loadConversations(), loadColleagues()]);

        if (activeConversation.value) {
            await selectConversation(activeConversation.value);
        }
    }

    function clearActiveConversation() {
        activeConversation.value = null;
        activeConversationId.value = null;
        messages.value = [];
        error.value = '';
    }

    function scrollToBottom() {
        nextTick(() => {
            const target = typeof scrollTarget === 'function' ? scrollTarget() : scrollTarget?.value;
            if (target) {
                target.scrollTop = target.scrollHeight;
            }
        });
    }

    return {
        conversations,
        colleagues,
        messages,
        activeConversation,
        activeConversationId,
        unreadCount,
        conversationsLoading,
        colleaguesLoading,
        threadLoading,
        sending,
        error,
        loadConversations,
        loadColleagues,
        loadCounterpartyContacts,
        loadCounterpartyOrders,
        loadThread,
        refreshThread,
        selectConversation,
        openDirect,
        openCounterparty,
        createGroup,
        sendMessage,
        reloadAll,
        clearActiveConversation,
        scrollToBottom,
    };
}

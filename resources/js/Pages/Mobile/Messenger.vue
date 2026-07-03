<template>
    <div class="flex h-screen min-h-screen flex-col bg-zinc-950 text-zinc-50">
        <header class="flex shrink-0 items-center justify-between border-b border-white/10 px-4 py-3">
            <div>
                <h1 class="text-lg font-semibold">Автоальянс Чат</h1>
                <p class="text-xs text-zinc-400">{{ unreadCount > 0 ? `Непрочитано: ${unreadCount}` : 'Все сообщения прочитаны' }}</p>
            </div>
            <button
                type="button"
                class="rounded-xl border border-white/10 px-3 py-2 text-sm font-medium text-zinc-100"
                @click="reloadAll"
            >
                Обновить
            </button>
        </header>

        <main class="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-[20rem_minmax(0,1fr)]">
            <aside class="flex min-h-0 flex-col border-b border-white/10 md:border-b-0 md:border-r">
                <div class="shrink-0 space-y-2 border-b border-white/10 p-3">
                    <input
                        v-model="colleagueSearch"
                        class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-zinc-50 outline-none placeholder:text-zinc-500"
                        placeholder="Найти коллегу"
                    />
                    <div v-if="filteredColleagues.length" class="flex gap-2 overflow-x-auto pb-1">
                        <button
                            v-for="user in filteredColleagues"
                            :key="user.id"
                            type="button"
                            class="shrink-0 rounded-full border border-sky-400/30 bg-sky-500/10 px-3 py-1.5 text-xs font-medium text-sky-100"
                            @click="openDirect(user)"
                        >
                            {{ user.name }}
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <button
                        v-for="conversation in conversations"
                        :key="conversation.id"
                        type="button"
                        class="flex w-full items-start gap-3 border-b border-white/5 px-4 py-3 text-left transition hover:bg-white/5"
                        :class="activeConversationId === conversation.id ? 'bg-sky-500/15' : ''"
                        @click="selectConversation(conversation)"
                    >
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-sky-500/20 text-sm font-bold text-sky-100">
                            {{ conversationTitle(conversation).slice(0, 1).toUpperCase() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-semibold text-zinc-50">{{ conversationTitle(conversation) }}</span>
                                <span
                                    v-if="conversation.unread_count > 0"
                                    class="ml-auto rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold text-white"
                                >
                                    {{ conversation.unread_count }}
                                </span>
                            </div>
                            <p class="mt-1 truncate text-xs text-zinc-400">
                                {{ conversation.last_message?.body ?? 'Сообщений пока нет' }}
                            </p>
                        </div>
                    </button>

                    <div v-if="!loadingConversations && conversations.length === 0" class="p-6 text-center text-sm text-zinc-500">
                        Диалогов пока нет. Выберите коллегу сверху.
                    </div>
                </div>
            </aside>

            <section class="flex min-h-0 flex-col">
                <div v-if="activeConversation" class="flex shrink-0 items-center border-b border-white/10 px-4 py-3">
                    <div>
                        <div class="font-semibold">{{ conversationTitle(activeConversation) }}</div>
                        <div class="text-xs text-zinc-400">
                            {{ activeConversation.type === 'group' ? `${activeConversation.member_count} участников` : 'Личный чат' }}
                        </div>
                    </div>
                </div>

                <div ref="messagesPanel" class="min-h-0 flex-1 space-y-2 overflow-y-auto p-4">
                    <div v-if="!activeConversation" class="flex h-full items-center justify-center text-center text-sm text-zinc-500">
                        Выберите диалог или начните чат с коллегой.
                    </div>

                    <template v-else>
                        <div
                            v-for="message in messages"
                            :key="message.id"
                            class="flex"
                            :class="message.user_id === currentUserId ? 'justify-end' : 'justify-start'"
                        >
                            <div
                                class="max-w-[82%] rounded-2xl px-3 py-2 text-sm"
                                :class="message.user_id === currentUserId ? 'bg-sky-600 text-white' : 'bg-white/10 text-zinc-100'"
                            >
                                <div v-if="message.user_id !== currentUserId" class="mb-1 text-[11px] font-semibold text-sky-200">
                                    {{ message.author_name ?? 'Пользователь' }}
                                </div>
                                <p class="whitespace-pre-wrap break-words">{{ message.body }}</p>
                                <div class="mt-1 text-right text-[10px] opacity-70">{{ formatTime(message.created_at) }}</div>
                            </div>
                        </div>
                    </template>
                </div>

                <form class="flex shrink-0 gap-2 border-t border-white/10 p-3" @submit.prevent="sendMessage">
                    <textarea
                        v-model="messageBody"
                        rows="1"
                        class="min-h-11 flex-1 resize-none rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-zinc-50 outline-none placeholder:text-zinc-500"
                        :disabled="!activeConversation || sending"
                        placeholder="Сообщение"
                        @keydown.enter.exact.prevent="sendMessage"
                    />
                    <button
                        type="submit"
                        class="rounded-2xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="!activeConversation || sending || messageBody.trim() === ''"
                    >
                        Отпр.
                    </button>
                </form>
            </section>
        </main>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const conversations = ref([]);
const colleagues = ref([]);
const messages = ref([]);
const activeConversation = ref(null);
const activeConversationId = ref(null);
const messageBody = ref('');
const colleagueSearch = ref('');
const unreadCount = ref(0);
const loadingConversations = ref(false);
const sending = ref(false);
const messagesPanel = ref(null);

const filteredColleagues = computed(() => {
    const needle = colleagueSearch.value.trim().toLowerCase();
    const source = needle === ''
        ? colleagues.value.slice(0, 12)
        : colleagues.value.filter((user) => String(user.name ?? '').toLowerCase().includes(needle)).slice(0, 12);

    return source;
});

function conversationTitle(conversation) {
    if (!conversation) {
        return '';
    }

    if (conversation.type === 'group') {
        return conversation.title ?? 'Группа';
    }

    return conversation.other_user?.name ?? 'Личный чат';
}

function formatTime(value) {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

async function loadConversations() {
    loadingConversations.value = true;

    try {
        const { data } = await axios.get(route('messenger.conversations.index'), {
            headers: { Accept: 'application/json' },
        });
        conversations.value = data.conversations ?? [];
        unreadCount.value = data.unread_count ?? 0;

        if (activeConversationId.value !== null) {
            activeConversation.value = conversations.value.find((item) => Number(item.id) === Number(activeConversationId.value)) ?? activeConversation.value;
        }
    } finally {
        loadingConversations.value = false;
    }
}

async function loadColleagues() {
    const { data } = await axios.get(route('messenger.colleagues'), {
        headers: { Accept: 'application/json' },
    });
    colleagues.value = data.users ?? [];
}

async function selectConversation(conversation) {
    activeConversation.value = conversation;
    activeConversationId.value = Number(conversation.id);

    const { data } = await axios.get(route('messenger.conversations.messages', { conversation: conversation.id }), {
        headers: { Accept: 'application/json' },
    });
    messages.value = data.messages ?? [];
    await loadConversations();
    scrollToBottom();
}

async function openDirect(user) {
    const { data } = await axios.post(route('messenger.conversations.open'), {
        user_id: user.id,
    }, {
        headers: { Accept: 'application/json' },
    });

    await loadConversations();

    if (data.conversation) {
        await selectConversation(data.conversation);
    }
}

async function sendMessage() {
    const body = messageBody.value.trim();
    if (!activeConversation.value || body === '') {
        return;
    }

    sending.value = true;

    try {
        const { data } = await axios.post(route('messenger.conversations.messages.store', { conversation: activeConversation.value.id }), {
            body,
        }, {
            headers: { Accept: 'application/json' },
        });
        messages.value = [...messages.value, data.message];
        messageBody.value = '';
        await loadConversations();
        scrollToBottom();
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

function scrollToBottom() {
    nextTick(() => {
        const panel = messagesPanel.value;
        if (panel) {
            panel.scrollTop = panel.scrollHeight;
        }
    });
}

onMounted(() => {
    reloadAll();
});
</script>

<template>
    <div class="flex h-[100dvh] min-h-screen flex-col bg-zinc-950 text-zinc-50">
        <template v-if="screen === 'list'">
            <header class="shrink-0 border-b border-white/10 px-4 pb-3 pt-[calc(0.85rem+env(safe-area-inset-top,0px))]">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-semibold">Автоальянс Чат</h1>
                        <p class="text-xs text-zinc-400">{{ unreadCount > 0 ? `Непрочитано: ${unreadCount}` : 'Все сообщения прочитаны' }}</p>
                    </div>
                    <button
                        type="button"
                        class="border border-white/10 px-3 py-2 text-sm font-medium text-zinc-100"
                        @click="reloadAll"
                    >
                        Обновить
                    </button>
                </div>
                <input
                    v-model="search"
                    class="mt-3 w-full border border-white/10 bg-white/5 px-4 py-3 text-base text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                    placeholder="Поиск чатов и коллег"
                />
            </header>

            <main class="min-h-0 flex-1 overflow-y-auto">
                <section v-if="filteredColleagues.length" class="border-b border-white/10 py-2">
                    <div class="px-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Коллеги</div>
                    <button
                        v-for="user in filteredColleagues"
                        :key="`user-${user.id}`"
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-3 text-left active:bg-white/10"
                        @click="openUserThread(user)"
                    >
                        <AvatarBubble :label="user.name" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-zinc-50">{{ user.name }}</div>
                            <div class="truncate text-xs text-zinc-500">{{ user.email ?? 'Открыть личный чат' }}</div>
                        </div>
                    </button>
                </section>

                <section>
                    <div v-if="conversationsLoading" class="p-6 text-center text-sm text-zinc-500">Загрузка чатов…</div>
                    <button
                        v-for="conversation in filteredConversations"
                        v-else
                        :key="`conversation-${conversation.id}`"
                        type="button"
                        class="flex w-full items-center gap-3 border-b border-white/5 px-4 py-3 text-left active:bg-white/10"
                        @click="openConversationThread(conversation)"
                    >
                        <AvatarBubble :label="conversationTitle(conversation)" :group="conversation.type === 'group'" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-semibold text-zinc-50">{{ conversationTitle(conversation) }}</span>
                                <span class="ml-auto shrink-0 text-[10px] text-zinc-500">{{ formatShortTime(conversation.updated_at) }}</span>
                            </div>
                            <div class="mt-1 flex items-center gap-2">
                                <p class="min-w-0 flex-1 truncate text-xs text-zinc-400">
                                    {{ conversationPreview(conversation) }}
                                </p>
                                <span
                                    v-if="conversation.unread_count > 0"
                                    class="flex h-5 min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-sky-600 px-1 text-[10px] font-bold text-white"
                                >
                                    {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
                                </span>
                            </div>
                        </div>
                    </button>

                    <div v-if="!conversationsLoading && filteredConversations.length === 0" class="p-8 text-center text-sm text-zinc-500">
                        {{ search.trim() ? 'Ничего не найдено.' : 'Диалогов пока нет. Найдите коллегу сверху.' }}
                    </div>
                </section>
            </main>
        </template>

        <template v-else>
            <header class="flex shrink-0 items-center gap-3 border-b border-white/10 px-3 pb-3 pt-[calc(0.75rem+env(safe-area-inset-top,0px))]">
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-2xl text-zinc-200 active:bg-white/10"
                    aria-label="Назад"
                    @click="backToList"
                >
                    ‹
                </button>
                <AvatarBubble :label="conversationTitle(activeConversation)" :group="activeConversation?.type === 'group'" />
                <div class="min-w-0 flex-1">
                    <div class="truncate text-base font-semibold">{{ conversationTitle(activeConversation) }}</div>
                    <div class="truncate text-xs text-zinc-400">
                        {{ activeConversation?.type === 'group' ? `${activeConversation.member_count} участников` : 'Личный чат' }}
                    </div>
                </div>
            </header>

            <main ref="messagesPanel" class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 py-4">
                <div v-if="threadLoading" class="py-8 text-center text-sm text-zinc-500">Загрузка сообщений…</div>
                <div
                    v-for="message in messages"
                    v-else
                    :key="message.id"
                    class="flex"
                    :class="message.user_id === currentUserId ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[84%] rounded-2xl px-3 py-2 text-sm shadow-sm"
                        :class="message.user_id === currentUserId ? 'rounded-br-md bg-sky-600 text-white' : 'rounded-bl-md bg-white/10 text-zinc-100'"
                    >
                        <div v-if="message.user_id !== currentUserId" class="mb-1 text-[11px] font-semibold text-sky-200">
                            {{ message.author_name ?? 'Пользователь' }}
                        </div>
                        <p class="whitespace-pre-wrap break-words">{{ message.body }}</p>
                        <div class="mt-1 text-right text-[10px] opacity-70">{{ formatMessageTime(message.created_at) }}</div>
                    </div>
                </div>
            </main>

            <form class="flex shrink-0 gap-2 border-t border-white/10 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))]" @submit.prevent="submitMessage">
                <textarea
                    v-model="messageBody"
                    rows="1"
                    class="min-h-11 flex-1 resize-none rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                    :disabled="!activeConversation || sending"
                    placeholder="Сообщение"
                    @keydown.enter.exact.prevent="submitMessage"
                />
                <button
                    type="submit"
                    class="rounded-2xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    :disabled="!activeConversation || sending || messageBody.trim() === ''"
                >
                    Отпр.
                </button>
            </form>
        </template>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useMessenger } from '@/composables/useMessenger.js';

const AvatarBubble = defineComponent({
    props: {
        label: { type: String, default: '' },
        group: { type: Boolean, default: false },
    },
    setup(props) {
        return () => h('div', {
            class: [
                'flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-sky-100',
                props.group ? 'bg-violet-600/40' : 'bg-sky-600/35',
            ],
        }, props.group ? 'Г' : String(props.label || 'Ч').slice(0, 1).toUpperCase());
    },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const messagesPanel = ref(null);
const screen = ref('list');
const search = ref('');
const messageBody = ref('');

const {
    conversations,
    colleagues,
    messages,
    activeConversation,
    unreadCount,
    conversationsLoading,
    threadLoading,
    sending,
    loadColleagues,
    reloadAll,
    selectConversation,
    openDirect,
    sendMessage,
    clearActiveConversation,
} = useMessenger({ scrollTarget: messagesPanel });

const filteredConversations = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (needle === '') {
        return conversations.value;
    }

    return conversations.value.filter((conversation) =>
        conversationTitle(conversation).toLowerCase().includes(needle)
        || conversationPreview(conversation).toLowerCase().includes(needle),
    );
});

const filteredColleagues = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (needle === '') {
        return colleagues.value.slice(0, 8);
    }

    return colleagues.value
        .filter((user) => `${user.name ?? ''} ${user.email ?? ''}`.toLowerCase().includes(needle))
        .slice(0, 12);
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

function conversationPreview(conversation) {
    const body = conversation?.last_message?.body;
    if (!body) {
        return 'Сообщений пока нет';
    }

    return String(body).replace(/\s+/g, ' ');
}

function formatShortTime(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function formatMessageTime(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

async function openConversationThread(conversation) {
    await selectConversation(conversation);
    screen.value = 'thread';
}

async function openUserThread(user) {
    await openDirect(user);
    screen.value = 'thread';
}

function backToList() {
    screen.value = 'list';
    messageBody.value = '';
    clearActiveConversation();
    reloadAll();
}

async function submitMessage() {
    await sendMessage(messageBody.value);
    messageBody.value = '';
}

onMounted(() => {
    reloadAll();
    loadColleagues();
});
</script>

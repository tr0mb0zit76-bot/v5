<template>
    <div class="flex h-[100dvh] min-h-screen flex-col bg-zinc-950 text-zinc-50">
        <template v-if="screen === 'thread'">
            <header class="flex shrink-0 items-center gap-3 border-b border-white/10 px-3 pb-3 pt-[calc(0.75rem+env(safe-area-inset-top,0px))]">
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-zinc-200 active:bg-white/10"
                    aria-label="Назад"
                    @click="backToChats"
                >
                    <ArrowLeft class="h-5 w-5" />
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
                <button
                    type="button"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/10 text-zinc-300 active:bg-white/10"
                    title="Действия с CRM"
                >
                    <Plus class="h-5 w-5" />
                </button>
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

        <template v-else>
            <header class="shrink-0 border-b border-white/10 px-4 pb-3 pt-[calc(0.85rem+env(safe-area-inset-top,0px))]">
                <div class="flex items-center gap-2">
                    <div class="relative min-w-0 flex-1">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" />
                        <input
                            v-model="search"
                            class="w-full rounded-2xl border border-white/10 bg-white/5 py-3 pl-10 pr-4 text-base text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                            :placeholder="activeTab === 'chats' ? 'Поиск чатов и коллег' : `Поиск: ${activeTabLabel}`"
                        />
                    </div>
                    <button
                        v-if="activeTab === 'chats'"
                        type="button"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/10 text-zinc-200 active:bg-white/10"
                        title="Новая группа"
                        @click="showGroupComposer = !showGroupComposer"
                    >
                        <Users class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/10 text-zinc-200 active:bg-white/10"
                        title="Обновить"
                        @click="reloadAll"
                    >
                        <RefreshCw class="h-4 w-4" />
                    </button>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-y-auto pb-2">
                <section v-if="activeTab === 'chats'" class="min-h-full">
                    <form
                        v-if="showGroupComposer"
                        class="border-b border-white/10 bg-white/[0.03] p-4"
                        @submit.prevent="submitGroup"
                    >
                        <div class="text-sm font-semibold text-zinc-100">Новая группа</div>
                        <input
                            v-model="groupTitle"
                            class="mt-3 w-full rounded-2xl border border-white/10 bg-zinc-900 px-4 py-3 text-sm text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                            placeholder="Название группы"
                        />
                        <div class="mt-3 max-h-44 space-y-1 overflow-y-auto">
                            <label
                                v-for="user in groupCandidates"
                                :key="`group-${user.id}`"
                                class="flex items-center gap-3 rounded-2xl px-2 py-2 active:bg-white/10"
                            >
                                <input v-model="groupMemberIds" type="checkbox" class="rounded border-zinc-600 bg-zinc-900" :value="user.id" />
                                <AvatarBubble :label="user.name" small />
                                <span class="min-w-0 flex-1 truncate text-sm">{{ user.name }}</span>
                            </label>
                        </div>
                        <p v-if="messengerError" class="mt-2 text-xs text-rose-300">{{ messengerError }}</p>
                        <button
                            type="submit"
                            class="mt-3 w-full rounded-2xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            :disabled="groupCreating || groupTitle.trim() === '' || groupMemberIds.length === 0"
                        >
                            {{ groupCreating ? 'Создание…' : 'Создать группу' }}
                        </button>
                    </form>

                    <section v-if="filteredColleagues.length" class="border-b border-white/10 py-2">
                        <div class="px-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Коллеги</div>
                        <div
                            v-for="user in filteredColleagues"
                            :key="`user-${user.id}`"
                            class="flex w-full items-center gap-3 px-4 py-3 active:bg-white/10"
                        >
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 items-center gap-3 text-left"
                                @click="openUserThread(user)"
                            >
                                <AvatarBubble :label="user.name" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-zinc-50">{{ user.name }}</div>
                                    <div class="truncate text-xs text-zinc-500">{{ contactSubtitle(user) }}</div>
                                </div>
                            </button>
                            <a
                                v-if="normalizedPhone(user.phone)"
                                :href="`tel:${normalizedPhone(user.phone)}`"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/10 text-sky-200 active:bg-white/10"
                                aria-label="Позвонить"
                            >
                                <Phone class="h-4 w-4" />
                            </a>
                        </div>
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
                </section>

                <PlaceholderTab
                    v-else
                    :tab="activeTab"
                    :title="activeTabLabel"
                />
            </main>

            <nav class="grid shrink-0 grid-cols-4 border-t border-white/10 bg-zinc-950/95 px-2 pb-[calc(0.5rem+env(safe-area-inset-bottom,0px))] pt-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="relative flex flex-col items-center gap-1 rounded-2xl px-2 py-2 text-[11px]"
                    :class="activeTab === tab.key ? 'bg-white/10 text-sky-200' : 'text-zinc-500 active:bg-white/5'"
                    @click="selectTab(tab.key)"
                >
                    <component :is="tab.icon" class="h-5 w-5" />
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="tab.key === 'chats' && unreadCount > 0"
                        class="absolute right-4 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-sky-600 px-1 text-[9px] font-bold text-white"
                    >
                        {{ unreadCount > 99 ? '99+' : unreadCount }}
                    </span>
                </button>
            </nav>
        </template>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ArrowLeft, CheckSquare, FileText, MessageCircle, Package, Phone, Plus, RefreshCw, Search, Users } from 'lucide-vue-next';
import { useMessenger } from '@/composables/useMessenger.js';
import { useMessengerPolling } from '@/composables/useMessengerPolling.js';
import { registerMobilePushIfAvailable } from '@/support/mobilePush.js';

const AvatarBubble = defineComponent({
    props: {
        label: { type: String, default: '' },
        group: { type: Boolean, default: false },
        small: { type: Boolean, default: false },
    },
    setup(props) {
        return () => h('div', {
            class: [
                'flex shrink-0 items-center justify-center rounded-full text-sm font-bold text-sky-100',
                props.small ? 'h-8 w-8 text-xs' : 'h-11 w-11',
                props.group ? 'bg-violet-600/40' : 'bg-sky-600/35',
            ],
        }, props.group ? 'Г' : String(props.label || 'Ч').slice(0, 1).toUpperCase());
    },
});

const PlaceholderTab = defineComponent({
    props: {
        tab: { type: String, required: true },
        title: { type: String, required: true },
    },
    setup(props) {
        const descriptions = {
            documents: 'Здесь появится мобильный inbox документов: добавить файл, выбрать заказ и слот, посмотреть требующие действия документы.',
            orders: 'Здесь будут карточки заказов без гридов: маршрут, контрагенты, документы и быстрый переход в связанный чат.',
            tasks: 'Здесь будут мои задачи, просроченные задачи и быстрые действия по связанным заказам и лидам.',
        };

        return () => h('section', { class: 'p-4' }, [
            h('div', { class: 'rounded-3xl border border-white/10 bg-white/[0.04] p-5' }, [
                h('div', { class: 'text-lg font-semibold text-zinc-50' }, props.title),
                h('p', { class: 'mt-2 text-sm leading-6 text-zinc-400' }, descriptions[props.tab] ?? 'Раздел будет собран карточками без мобильных гридов.'),
                h('div', { class: 'mt-4 rounded-2xl border border-dashed border-white/10 px-4 py-3 text-xs text-zinc-500' }, 'Каркас вкладки готов. Логика модуля будет добавляться следующими шагами.'),
            ]),
        ]);
    },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const messagesPanel = ref(null);
const screen = ref('list');
const activeTab = ref('chats');
const search = ref('');
const messageBody = ref('');
const showGroupComposer = ref(false);
const groupTitle = ref('');
const groupMemberIds = ref([]);
const groupCreating = ref(false);

const tabs = [
    { key: 'chats', label: 'Чаты', icon: MessageCircle },
    { key: 'documents', label: 'Документы', icon: FileText },
    { key: 'orders', label: 'Заказы', icon: Package },
    { key: 'tasks', label: 'Задачи', icon: CheckSquare },
];

const messenger = useMessenger({ scrollTarget: messagesPanel });

useMessengerPolling(messenger);

const {
    conversations,
    colleagues,
    messages,
    activeConversation,
    unreadCount,
    conversationsLoading,
    threadLoading,
    sending,
    error: messengerError,
    loadColleagues,
    reloadAll,
    selectConversation,
    openDirect,
    createGroup,
    sendMessage,
    clearActiveConversation,
} = messenger;

const activeTabLabel = computed(() => tabs.find((tab) => tab.key === activeTab.value)?.label ?? 'Раздел');

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
        .filter((user) => `${user.name ?? ''} ${user.phone ?? ''} ${user.email ?? ''}`.toLowerCase().includes(needle))
        .slice(0, 12);
});

const groupCandidates = computed(() => colleagues.value.slice(0, 50));

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

function backToChats() {
    screen.value = 'list';
    activeTab.value = 'chats';
    messageBody.value = '';
    clearActiveConversation();
    reloadAll();
}

function selectTab(tab) {
    activeTab.value = tab;
    search.value = '';
    showGroupComposer.value = false;
}

async function submitMessage() {
    await sendMessage(messageBody.value);
    messageBody.value = '';
}

async function submitGroup() {
    groupCreating.value = true;

    try {
        await createGroup(groupTitle.value.trim(), groupMemberIds.value);
        groupTitle.value = '';
        groupMemberIds.value = [];
        showGroupComposer.value = false;
        screen.value = 'thread';
    } catch {
        return;
    } finally {
        groupCreating.value = false;
    }
}

function normalizedPhone(phone) {
    const text = String(phone ?? '').trim();
    if (text === '') {
        return '';
    }

    return text.replace(/[^\d+]/g, '');
}

function contactSubtitle(user) {
    return user.phone || user.email || 'Открыть личный чат';
}

onMounted(() => {
    reloadAll();
    loadColleagues();
    registerMobilePushIfAvailable();
});
</script>

<template>
    <div class="w-full rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <Teleport to="body">
            <div
                v-if="chatPanelOpen"
                class="fixed inset-x-0 top-0 z-[90] flex flex-col bg-zinc-950/50 dark:bg-zinc-950/70"
                :style="{ bottom: 'calc(7.5rem + env(safe-area-inset-bottom, 0px))' }"
                @click.self="closeChatPanel"
            >
                <div
                    class="mx-auto mt-auto flex h-[min(52vh,480px)] w-full max-w-4xl flex-col overflow-hidden rounded-t-3xl border border-b-0 border-zinc-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900"
                    @click.stop
                >
                    <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Чаты</div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="rounded-xl border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                @click="toggleColleaguePicker"
                            >
                                {{ showColleaguePicker ? 'Скрыть' : 'Новый чат' }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-xl border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                @click="toggleGroupForm"
                            >
                                <Users class="h-3.5 w-3.5" />
                                {{ showGroupForm ? 'Скрыть' : 'Группа' }}
                            </button>
                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-xl border border-zinc-200 text-zinc-500 hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                                aria-label="Закрыть"
                                @click="closeChatPanel"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div v-if="showColleaguePicker" class="max-h-40 overflow-y-auto border-b border-zinc-100 px-3 py-2 dark:border-zinc-800">
                        <div v-if="colleaguesLoading" class="py-4 text-center text-xs text-zinc-500">Загрузка…</div>
                        <button
                            v-for="u in colleagues"
                            v-else
                            :key="u.id"
                            type="button"
                            class="flex w-full items-center rounded-xl px-3 py-2 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            @click="openWithUser(u)"
                        >
                            {{ u.name }}
                        </button>
                    </div>

                    <div v-if="showGroupForm" class="border-b border-zinc-100 px-3 py-3 dark:border-zinc-800">
                        <div v-if="colleaguesLoading" class="py-4 text-center text-xs text-zinc-500">Загрузка…</div>
                        <div v-else class="space-y-3">
                            <input
                                v-model="groupTitle"
                                type="text"
                                maxlength="255"
                                placeholder="Название группы"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm outline-none placeholder:text-zinc-400 focus:border-sky-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                            >
                            <div class="max-h-32 space-y-1 overflow-y-auto text-sm">
                                <label
                                    v-for="u in colleagues"
                                    :key="'g-' + u.id"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                >
                                    <input v-model="groupMemberIds" type="checkbox" class="rounded border-zinc-300" :value="u.id">
                                    <span>{{ u.name }}</span>
                                </label>
                            </div>
                            <button
                                type="button"
                                class="w-full rounded-xl bg-zinc-900 px-3 py-2 text-xs font-medium text-white disabled:opacity-40 dark:bg-white dark:text-zinc-900"
                                :disabled="groupCreating || !groupTitle.trim() || groupMemberIds.length === 0"
                                @click="createGroup"
                            >
                                {{ groupCreating ? 'Создание…' : 'Создать группу' }}
                            </button>
                        </div>
                    </div>

                    <div class="flex min-h-0 flex-1">
                        <div class="w-[38%] max-w-[220px] shrink-0 overflow-y-auto border-r border-zinc-100 dark:border-zinc-800">
                            <div v-if="conversationsLoading" class="p-4 text-center text-xs text-zinc-500">…</div>
                            <button
                                v-for="c in conversations"
                                v-else
                                :key="c.id"
                                type="button"
                                class="flex w-full flex-col gap-0.5 border-b border-zinc-50 px-3 py-2.5 text-left text-xs hover:bg-zinc-50 dark:border-zinc-800/80 dark:hover:bg-zinc-800/60"
                                :class="Number(activeConversationId) === Number(c.id) ? 'bg-zinc-100 dark:bg-zinc-800' : ''"
                                @click="selectConversation(c)"
                            >
                                <div class="flex items-center justify-between gap-1">
                                    <span class="truncate font-medium text-zinc-900 dark:text-zinc-100">
                                        <span v-if="c.type === 'group'" class="inline-flex items-center gap-1">
                                            <Users class="h-3 w-3 shrink-0 opacity-70" />
                                            {{ c.title || 'Группа' }}
                                        </span>
                                        <span v-else>{{ c.other_user?.name || 'Чат' }}</span>
                                    </span>
                                    <span
                                        v-if="c.unread_count > 0"
                                        class="flex h-5 min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white"
                                    >
                                        {{ c.unread_count > 99 ? '99+' : c.unread_count }}
                                    </span>
                                </div>
                            </button>
                            <div v-if="!conversationsLoading && conversations.length === 0" class="p-4 text-center text-xs text-zinc-500">
                                Нет диалогов. Нажмите «Новый чат».
                            </div>
                        </div>

                        <div ref="threadRef" class="min-w-0 flex-1 overflow-y-auto bg-zinc-50/80 p-3 dark:bg-zinc-950/50">
                            <div v-if="!activeConversationId" class="flex h-full items-center justify-center text-center text-sm text-zinc-500">
                                Выберите диалог слева или создайте новый.
                            </div>
                            <div v-else-if="threadLoading" class="py-8 text-center text-xs text-zinc-500">Загрузка сообщений…</div>
                            <div v-else class="space-y-3">
                                <div
                                    v-if="activeConversation?.type === 'group'"
                                    class="rounded-xl border border-zinc-200 bg-white/90 px-3 py-2 text-xs dark:border-zinc-700 dark:bg-zinc-900/90"
                                >
                                    <div class="font-medium text-zinc-800 dark:text-zinc-100">
                                        {{ activeConversation.title || 'Группа' }}
                                    </div>
                                    <div v-if="activeConversation.member_count" class="text-zinc-500 dark:text-zinc-400">
                                        {{ activeConversation.member_count }} участников
                                    </div>
                                </div>
                                <div
                                    v-for="m in threadMessages"
                                    :key="m.id"
                                    class="flex"
                                    :class="m.user_id === currentUserId ? 'justify-end' : 'justify-start'"
                                >
                                    <div
                                        class="max-w-[85%] rounded-2xl px-3 py-2 text-sm shadow-sm"
                                        :class="m.user_id === currentUserId
                                            ? 'border border-sky-200/80 bg-sky-100/90 text-zinc-800 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 dark:shadow-none'
                                            : 'border border-zinc-200 bg-white text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100'"
                                    >
                                        <div v-if="m.user_id !== currentUserId" class="mb-0.5 text-[10px] font-medium text-zinc-500 dark:text-zinc-400">
                                            {{ m.author_name }}
                                        </div>
                                        <div class="whitespace-pre-wrap break-words">
                                            <template v-for="(part, pi) in messageParts(m.body)" :key="`${m.id}-${pi}`">
                                                <a
                                                    v-if="part.type === 'link'"
                                                    :href="part.href"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="break-all underline"
                                                    :class="m.user_id === currentUserId
                                                        ? 'text-sky-700 dark:text-sky-300'
                                                        : 'text-sky-600 dark:text-sky-400'"
                                                    @click.stop
                                                >{{ part.text }}</a>
                                                <span v-else>{{ part.text }}</span>
                                            </template>
                                        </div>
                                        <div
                                            class="mt-1 text-[10px] opacity-70"
                                        >
                                            {{ formatMsgTime(m.created_at) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <div
            v-if="showActions"
            class="border-b border-zinc-200 px-3 py-3 dark:border-zinc-800"
        >
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tile in quickTiles"
                    :key="tile.key"
                    type="button"
                    class="group flex h-14 w-14 shrink-0 flex-col items-center justify-center gap-0.5 rounded-2xl border border-zinc-200 bg-zinc-50 text-zinc-700 transition hover:border-zinc-400 hover:bg-white dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:border-zinc-500 dark:hover:bg-zinc-900"
                    :title="tile.label"
                    @click="goQuick(tile)"
                >
                    <component :is="tile.icon" class="h-5 w-5 shrink-0 text-zinc-600 dark:text-zinc-300" />
                    <span class="max-w-[3.25rem] truncate px-0.5 text-center text-[9px] font-medium leading-tight text-zinc-500 dark:text-zinc-400">{{ tile.short }}</span>
                </button>
            </div>
        </div>

        <div class="flex items-end gap-2 p-2">
            <button
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-zinc-200 text-zinc-600 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                :class="showActions ? 'border-zinc-900 bg-zinc-100 text-zinc-900 dark:border-zinc-400 dark:bg-zinc-800 dark:text-zinc-100' : ''"
                :title="showActions ? 'Скрыть быстрые действия' : 'Быстрые действия'"
                @click="showActions = !showActions"
            >
                <Sparkles class="h-5 w-5" />
            </button>

            <button
                type="button"
                class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-zinc-200 text-zinc-600 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                :class="chatPanelOpen ? 'border-sky-600 bg-sky-50 text-sky-900 dark:border-sky-500 dark:bg-sky-950/40 dark:text-sky-100' : ''"
                title="Чаты"
                @click="toggleChatPanel"
            >
                <MessageCircle class="h-5 w-5" />
                <span
                    v-if="messengerUnread > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold leading-none text-white"
                >
                    {{ messengerUnread > 99 ? '99+' : messengerUnread }}
                </span>
            </button>

            <div class="min-w-0 flex-1">
                <div class="flex items-end gap-2 rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                    <textarea
                        ref="textareaRef"
                        v-model="message"
                        rows="1"
                        class="w-full resize-none bg-transparent text-sm outline-none placeholder:text-zinc-400 dark:placeholder:text-zinc-500"
                        :placeholder="inputPlaceholder"
                        @keydown="handleKeydown"
                        @input="autosize"
                    />

                    <label
                        v-if="!isChatInputMode"
                        class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-xl hover:bg-zinc-200/70 dark:hover:bg-zinc-700/70"
                    >
                        <Paperclip class="h-4 w-4" />
                        <input type="file" class="hidden" multiple @change="handleFiles">
                    </label>

                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-zinc-900 text-white disabled:opacity-40 dark:bg-white dark:text-zinc-900"
                        :disabled="isDisabled"
                        @click="submit"
                    >
                        <SendHorizontal class="h-4 w-4" />
                    </button>
                </div>

                <p v-if="messengerSendError" class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ messengerSendError }}</p>
                <div v-if="attachedFiles.length && !isChatInputMode" class="mt-2 flex flex-wrap gap-2">
                    <div
                        v-for="file in attachedFiles"
                        :key="file.name + file.size"
                        class="inline-flex items-center gap-2 rounded-full bg-zinc-100 px-3 py-1 text-xs dark:bg-zinc-800"
                    >
                        <Paperclip class="h-3.5 w-3.5" />
                        <span class="max-w-[180px] truncate">{{ file.name }}</span>
                        <button type="button" @click="removeFile(file)">×</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    ClipboardList,
    MessageCircle,
    Package,
    Paperclip,
    Receipt,
    ScrollText,
    SendHorizontal,
    Sparkles,
    Target,
    Users,
    X,
} from 'lucide-vue-next';

const emit = defineEmits(['submit']);

const page = usePage();
const message = ref('');
const attachedFiles = ref([]);
const showActions = ref(false);
const textareaRef = ref(null);
const threadRef = ref(null);

const chatPanelOpen = ref(false);
const showColleaguePicker = ref(false);
const showGroupForm = ref(false);
const groupTitle = ref('');
const groupMemberIds = ref([]);
const groupCreating = ref(false);
const conversations = ref([]);
const conversationsLoading = ref(false);
const colleagues = ref([]);
const colleaguesLoading = ref(false);
const activeConversationId = ref(null);
const activeConversation = ref(null);
const threadMessages = ref([]);
const threadLoading = ref(false);
const messengerUnread = ref(0);
const messengerSendError = ref('');

let pollUnreadTimer = null;

function conversationRouteParams(id) {
    return { conversation: id };
}

const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const isChatInputMode = computed(() => chatPanelOpen.value && activeConversationId.value !== null);

const inputPlaceholder = computed(() => {
    if (chatPanelOpen.value && activeConversationId.value === null) {
        return 'Сначала выберите диалог слева или «Новый чат»…';
    }
    const conv = activeConversation.value;
    if (isChatInputMode.value && conv) {
        if (conv.type === 'group') {
            const t = (conv.title || '').trim();
            return t ? `Сообщение в группу «${t}»…` : 'Сообщение в группу…';
        }
        if (conv.other_user?.name) {
            return `Сообщение для ${conv.other_user.name}…`;
        }
    }
    if (isChatInputMode.value) {
        return 'Сообщение…';
    }
    return 'Напишите команду, вопрос или задачу для ИИ…';
});

const isDisabled = computed(() => {
    if (chatPanelOpen.value && activeConversationId.value === null) {
        return true;
    }
    if (isChatInputMode.value) {
        return !message.value.trim();
    }
    return !message.value.trim() && attachedFiles.value.length === 0;
});

const quickTiles = [
    {
        key: 'lead',
        label: 'Создать лид',
        short: 'Лид',
        icon: Target,
        visit: () => route('leads.create'),
    },
    {
        key: 'order',
        label: 'Создать заказ',
        short: 'Заказ',
        icon: Package,
        visit: () => route('orders.create'),
    },
    {
        key: 'contractor',
        label: 'Создать контрагента',
        short: 'Контрагент',
        icon: Users,
        visit: () => route('contractors.create'),
    },
    {
        key: 'task',
        label: 'Создать задачу',
        short: 'Задача',
        icon: ClipboardList,
        visit: () => route('tasks.index', { create: 1 }),
    },
    {
        key: 'invoice',
        label: 'Создать счёт',
        short: 'Счёт',
        icon: Receipt,
        visit: () => route('finance.index', { section: 'documents', new_document: 'invoice' }),
    },
    {
        key: 'upd',
        label: 'Создать УПД',
        short: 'УПД',
        icon: ScrollText,
        visit: () => route('finance.index', { section: 'documents', new_document: 'upd' }),
    },
];

async function fetchMessengerUnread() {
    try {
        const { data } = await axios.get(route('messenger.unread-count'), {
            headers: { Accept: 'application/json' },
        });
        messengerUnread.value = data.unread_count ?? 0;
    } catch {
        /* ignore */
    }
}

function syncActiveConversationFromList() {
    const id = activeConversationId.value;
    if (id === null) {
        return;
    }
    const found = conversations.value.find((x) => Number(x.id) === Number(id));
    if (found) {
        activeConversation.value = found;
    }
}

async function loadConversations() {
    conversationsLoading.value = true;
    try {
        const { data } = await axios.get(route('messenger.conversations.index'), {
            headers: { Accept: 'application/json' },
        });
        conversations.value = data.conversations ?? [];
        messengerUnread.value = data.unread_count ?? 0;
        syncActiveConversationFromList();
    } finally {
        conversationsLoading.value = false;
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

async function selectConversation(c) {
    messengerSendError.value = '';
    activeConversation.value = c;
    activeConversationId.value = Number(c.id);
    showColleaguePicker.value = false;
    showGroupForm.value = false;
    await loadThread(Number(c.id));
}

async function openWithUser(u) {
    messengerSendError.value = '';
    try {
        const { data } = await axios.post(
            route('messenger.conversations.open'),
            { user_id: u.id },
            { headers: { Accept: 'application/json' } },
        );
        showColleaguePicker.value = false;
        await loadConversations();
        if (data.conversation) {
            const conv = data.conversation;
            activeConversation.value = conv;
            activeConversationId.value = Number(conv.id);
            await loadThread(Number(conv.id));
        }
    } catch (error) {
        const msg = error.response?.data?.message ?? error.response?.data?.errors?.user_id?.[0];
        messengerSendError.value = typeof msg === 'string' ? msg : 'Не удалось открыть чат. Обновите страницу и попробуйте снова.';
    }
}

async function loadThread(conversationId) {
    threadLoading.value = true;
    threadMessages.value = [];
    try {
        const { data } = await axios.get(
            route('messenger.conversations.messages', conversationRouteParams(conversationId)),
            { headers: { Accept: 'application/json' } },
        );
        threadMessages.value = data.messages ?? [];
        await nextTick();
        scrollThreadToEnd();
        await loadConversations();
    } finally {
        threadLoading.value = false;
    }
}

function scrollThreadToEnd() {
    const el = threadRef.value;
    if (el) {
        el.scrollTop = el.scrollHeight;
    }
}

async function sendChatMessage() {
    const text = message.value.trim();
    const cid = activeConversationId.value;
    if (!text || cid === null) {
        return;
    }

    messengerSendError.value = '';
    try {
        const { data } = await axios.post(
            route('messenger.conversations.messages.store', conversationRouteParams(cid)),
            { body: text },
            { headers: { Accept: 'application/json' } },
        );
        if (data.message) {
            threadMessages.value = [...threadMessages.value, data.message];
        }
        message.value = '';
        nextTick(() => {
            if (textareaRef.value) {
                textareaRef.value.style.height = 'auto';
            }
            scrollThreadToEnd();
        });
        await fetchMessengerUnread();
        await loadConversations();
    } catch (error) {
        const msg = error.response?.data?.message ?? error.response?.data?.errors?.body?.[0];
        messengerSendError.value = typeof msg === 'string' ? msg : 'Не удалось отправить сообщение.';
    }
}

function toggleColleaguePicker() {
    showColleaguePicker.value = !showColleaguePicker.value;
    if (showColleaguePicker.value) {
        showGroupForm.value = false;
        if (colleagues.value.length === 0) {
            loadColleagues();
        }
    }
}

function toggleGroupForm() {
    showGroupForm.value = !showGroupForm.value;
    if (showGroupForm.value) {
        showColleaguePicker.value = false;
        groupTitle.value = '';
        groupMemberIds.value = [];
        if (colleagues.value.length === 0) {
            loadColleagues();
        }
    }
}

async function createGroup() {
    const title = groupTitle.value.trim();
    if (!title || groupMemberIds.value.length === 0) {
        return;
    }
    messengerSendError.value = '';
    groupCreating.value = true;
    try {
        const { data } = await axios.post(
            route('messenger.conversations.groups.store'),
            { title, user_ids: groupMemberIds.value },
            { headers: { Accept: 'application/json' } },
        );
        showGroupForm.value = false;
        groupTitle.value = '';
        groupMemberIds.value = [];
        await loadConversations();
        if (data.conversation) {
            const conv = data.conversation;
            activeConversation.value = conv;
            activeConversationId.value = Number(conv.id);
            await loadThread(Number(conv.id));
        }
    } catch (error) {
        const msg = error.response?.data?.message ?? error.response?.data?.errors?.user_ids?.[0] ?? error.response?.data?.errors?.title?.[0];
        messengerSendError.value = typeof msg === 'string' ? msg : 'Не удалось создать группу.';
    } finally {
        groupCreating.value = false;
    }
}

function toggleChatPanel() {
    messengerSendError.value = '';
    chatPanelOpen.value = !chatPanelOpen.value;
    if (chatPanelOpen.value) {
        loadConversations();
        fetchMessengerUnread();
    } else {
        showColleaguePicker.value = false;
        showGroupForm.value = false;
    }
}

function closeChatPanel() {
    messengerSendError.value = '';
    chatPanelOpen.value = false;
    showColleaguePicker.value = false;
    showGroupForm.value = false;
}

function autosize() {
    const el = textareaRef.value;
    if (!el) {
        return;
    }

    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 160)}px`;
}

function handleFiles(event) {
    const files = Array.from(event.target.files || []);
    attachedFiles.value = [...attachedFiles.value, ...files];
    event.target.value = '';
}

function removeFile(fileToRemove) {
    attachedFiles.value = attachedFiles.value.filter(
        (file) => !(file.name === fileToRemove.name && file.size === fileToRemove.size),
    );
}

async function submit() {
    if (isDisabled.value) {
        return;
    }

    if (chatPanelOpen.value && activeConversationId.value === null) {
        messengerSendError.value = 'Выберите диалог слева или создайте новый чат.';
        return;
    }

    if (isChatInputMode.value) {
        await sendChatMessage();
        return;
    }

    emit('submit', {
        message: message.value,
        files: attachedFiles.value,
    });

    message.value = '';
    attachedFiles.value = [];

    nextTick(() => {
        if (textareaRef.value) {
            textareaRef.value.style.height = 'auto';
        }
    });
}

function handleKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        submit();
    }
}

function goQuick(tile) {
    showActions.value = false;
    router.visit(tile.visit());
}

/**
 * Разбивает текст на фрагменты с кликабельными https-ссылками (удобно вставлять URL из кабинета / финансов).
 */
function messageParts(body) {
    if (!body) {
        return [];
    }
    const re = /(https?:\/\/[^\s<]+)/gi;
    const parts = [];
    let lastIndex = 0;
    let m = re.exec(body);
    while (m !== null) {
        if (m.index > lastIndex) {
            parts.push({ type: 'text', text: body.slice(lastIndex, m.index) });
        }
        let raw = m[1];
        let href = raw.replace(/[.,;:!?)]+$/u, '');
        parts.push({ type: 'link', text: raw, href });
        lastIndex = m.index + raw.length;
        m = re.exec(body);
    }
    if (lastIndex < body.length) {
        parts.push({ type: 'text', text: body.slice(lastIndex) });
    }
    return parts.length > 0 ? parts : [{ type: 'text', text: body }];
}

function formatMsgTime(iso) {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return '';
    }
    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
}

watch(chatPanelOpen, (open) => {
    if (open) {
        document.body.classList.add('overflow-hidden');
    } else {
        document.body.classList.remove('overflow-hidden');
    }
});

onMounted(() => {
    fetchMessengerUnread();
    pollUnreadTimer = window.setInterval(fetchMessengerUnread, 60000);
});

onUnmounted(() => {
    document.body.classList.remove('overflow-hidden');
    if (pollUnreadTimer) {
        window.clearInterval(pollUnreadTimer);
    }
});
</script>

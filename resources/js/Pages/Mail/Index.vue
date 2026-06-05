<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <CrmPageHeader
            lead="Входящие и исходящие письма по лидам и заказам. Ответы уходят с вашего адреса."
            title="Почта"
        />

        <div class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(240px,300px),minmax(0,1fr)]">
            <aside :class="`${crmPanel} flex min-h-0 flex-col p-3`">
                <div v-if="mailView.can_view_all_mailboxes" class="mb-4 border-b border-zinc-200 pb-3 dark:border-zinc-800">
                    <h2 class="mb-2 text-sm font-semibold text-zinc-900 dark:text-zinc-50">Ящики</h2>
                    <div class="max-h-44 space-y-1 overflow-y-auto pr-1">
                        <Link
                            :href="mailboxIndexUrl(null)"
                            preserve-scroll
                            :class="mailboxFolderClass(null)"
                        >
                            <span>Все</span>
                            <span class="text-xs text-zinc-500">{{ mailView.total_thread_count }}</span>
                        </Link>
                        <Link
                            v-for="owner in mailView.owners"
                            :key="owner.user_id"
                            :href="mailboxIndexUrl(owner.user_id)"
                            preserve-scroll
                            :class="mailboxFolderClass(owner.user_id)"
                            :title="owner.full_name"
                        >
                            <span class="truncate">{{ owner.label }}</span>
                            <span class="shrink-0 text-xs text-zinc-500">{{ owner.thread_count }}</span>
                        </Link>
                        <Link
                            v-if="mailView.unassigned_thread_count > 0"
                            :href="mailboxIndexUrl(0)"
                            preserve-scroll
                            :class="mailboxFolderClass(0)"
                        >
                            <span>Без владельца</span>
                            <span class="text-xs text-zinc-500">{{ mailView.unassigned_thread_count }}</span>
                        </Link>
                    </div>
                </div>

                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Цепочки</h2>
                    <Link :href="mailboxIndexUrl(mailView.selected_mailbox_user_id)" :class="crmBtnSecondary" preserve-scroll>Новое</Link>
                </div>
                <div v-if="threads.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">Писем пока нет.</div>
                <div v-else class="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                    <Link
                        v-for="thread in threads"
                        :key="thread.id"
                        :href="threadShowUrl(thread.id)"
                        preserve-scroll
                        :class="threadLinkClass(thread.id)"
                    >
                        <div class="truncate font-medium text-zinc-900 dark:text-zinc-50">{{ thread.subject }}</div>
                        <div class="mt-1 flex flex-wrap gap-x-2 text-xs text-zinc-500 dark:text-zinc-400">
                            <span
                                v-if="mailView.can_view_all_mailboxes && mailView.selected_mailbox_user_id === null && thread.mailbox_owner_label"
                                class="rounded bg-zinc-100 px-1.5 py-0.5 dark:bg-zinc-800"
                            >
                                {{ thread.mailbox_owner_label }}
                            </span>
                            <span v-if="thread.lead_number">Лид {{ thread.lead_number }}</span>
                            <span v-if="thread.order_number">Заказ {{ thread.order_number }}</span>
                            <span v-if="thread.contractor_name">{{ thread.contractor_name }}</span>
                        </div>
                        <div v-if="thread.last_message_at" class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                            {{ formatWhen(thread.last_message_at) }}
                        </div>
                        <p v-if="thread.preview" class="mt-2 line-clamp-2 text-xs text-zinc-600 dark:text-zinc-300">
                            {{ thread.preview }}
                        </p>
                    </Link>
                </div>
            </aside>

            <section class="flex min-h-0 flex-col gap-4">
                <template v-if="selectedThread">
                    <div :class="`${crmPanel} space-y-2 p-4`">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ selectedThread.subject }}</h2>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                            <span v-if="selectedThread.mailbox_owner_name">
                                Ящик: {{ selectedThread.mailbox_owner_name }}
                                <span v-if="selectedThread.mailbox_owner_email">({{ selectedThread.mailbox_owner_email }})</span>
                            </span>
                            <span v-if="selectedThread.lead_number">
                                Лид {{ selectedThread.lead_number }}
                                <span v-if="selectedThread.lead_title">— {{ selectedThread.lead_title }}</span>
                            </span>
                            <span v-if="selectedThread.order_number">Заказ {{ selectedThread.order_number }}</span>
                            <span v-if="selectedThread.contractor_name">{{ selectedThread.contractor_name }}</span>
                        </div>
                    </div>

                    <div :class="`${crmPanel} min-h-0 flex-1 space-y-3 overflow-y-auto p-4`">
                        <article
                            v-for="message in messages"
                            :key="message.id"
                            class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800"
                            :class="message.direction === 'outbound' ? 'bg-zinc-50/80 dark:bg-zinc-900/40' : ''"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">
                                        {{ message.from_email || '—' }}
                                        <span class="ml-2 text-xs font-normal text-zinc-500 dark:text-zinc-400">
                                            {{ directionLabel(message.direction) }}
                                        </span>
                                    </div>
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        Кому: {{ formatRecipients(message.to_emails) }}
                                        <span v-if="message.cc_emails?.length"> · CC: {{ formatRecipients(message.cc_emails) }}</span>
                                    </div>
                                    <div v-if="message.sent_at" class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                        {{ formatWhen(message.sent_at) }}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    :class="importanceButtonClass(message.is_important)"
                                    :title="message.is_important ? 'Снять отметку «важно»' : 'Пометить как важное'"
                                    @click="toggleImportance(message)"
                                >
                                    <Star class="h-4 w-4" :class="message.is_important ? 'fill-current' : ''" />
                                </button>
                            </div>
                            <div class="mt-3 space-y-2">
                                <div v-if="message.body_html" class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        :class="messageViewMode[message.id] === 'html' ? crmBtnSecondary : crmBtnSecondaryOutline"
                                        @click="setMessageViewMode(message.id, 'html')"
                                    >
                                        Форматированный
                                    </button>
                                    <button
                                        type="button"
                                        :class="messageViewMode[message.id] !== 'html' ? crmBtnSecondary : crmBtnSecondaryOutline"
                                        @click="setMessageViewMode(message.id, 'text')"
                                    >
                                        Текст
                                    </button>
                                </div>
                                <div
                                    v-if="message.body_html && messageViewMode[message.id] === 'html'"
                                    class="prose prose-sm max-w-none text-zinc-700 dark:prose-invert dark:text-zinc-200"
                                    v-html="message.body_html"
                                />
                                <pre
                                    v-else
                                    class="whitespace-pre-wrap font-sans text-sm text-zinc-700 dark:text-zinc-200"
                                >{{ message.body_text }}</pre>
                            </div>
                            <ul
                                v-if="message.attachments?.length"
                                class="mt-3 space-y-1 text-xs text-zinc-600 dark:text-zinc-300"
                            >
                                <li v-for="(file, fileIndex) in message.attachments" :key="`${message.id}-att-${fileIndex}`" class="flex items-center gap-1.5">
                                    <Paperclip class="h-3.5 w-3.5 shrink-0" />
                                    <a
                                        v-if="file.download_url"
                                        :href="file.download_url"
                                        class="text-indigo-600 hover:underline dark:text-indigo-400"
                                    >
                                        {{ file.name }}
                                    </a>
                                    <span v-else>{{ file.name }}</span>
                                    <span v-if="file.file_size" class="text-zinc-400">({{ formatFileSize(file.file_size) }})</span>
                                </li>
                            </ul>
                        </article>
                    </div>

                    <form :class="`${crmPanel} space-y-3 p-4`" @submit.prevent="submitReply">
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Ответить</h3>
                        <div>
                            <label :class="crmLabel">Кому (через запятую)</label>
                            <input v-model="replyForm.to_raw" type="text" :class="crmFieldFluid" />
                        </div>
                        <div>
                            <label :class="crmLabel">Копия (необязательно)</label>
                            <input v-model="replyForm.cc_raw" type="text" :class="crmFieldFluid" placeholder="cc@example.com" />
                        </div>
                        <div>
                            <label :class="crmLabel">Текст</label>
                            <textarea v-model="replyForm.body" rows="5" :class="crmFieldFluid" />
                        </div>
                        <div>
                            <label :class="crmLabel">Вложения</label>
                            <input
                                type="file"
                                multiple
                                :class="crmFieldFluid"
                                @change="onReplyAttachmentsSelected"
                            />
                            <p v-if="attachmentLimits.hint" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ attachmentLimits.hint }}</p>
                            <ul v-if="replyForm.attachments.length" class="mt-2 space-y-1 text-xs text-zinc-600 dark:text-zinc-300">
                                <li v-for="(file, index) in replyForm.attachments" :key="`reply-att-${index}`">
                                    {{ file.name }}
                                </li>
                            </ul>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">От: {{ fromEmail }}</p>
                        <button type="submit" :class="crmBtnPrimary" :disabled="replyForm.processing">Отправить ответ</button>
                    </form>
                </template>

                <form v-else :class="`${crmPanel} space-y-3 p-4`" @submit.prevent="submitSend">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Новое письмо</h2>
                    <div>
                        <label :class="crmLabel">Лид (необязательно)</label>
                        <select v-model="sendForm.lead_id" :class="crmFieldFluid">
                            <option :value="null">Без привязки</option>
                            <option v-for="lead in leads" :key="lead.id" :value="lead.id">
                                {{ lead.number }} — {{ lead.title }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label :class="crmLabel">Заказ (необязательно)</label>
                        <select v-model="sendForm.order_id" :class="crmFieldFluid">
                            <option :value="null">Без привязки</option>
                            <option v-for="order in orders" :key="order.id" :value="order.id">
                                {{ order.order_number }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label :class="crmLabel">Кому (через запятую)</label>
                        <input v-model="sendForm.to_raw" type="text" :class="crmFieldFluid" placeholder="client@example.com" />
                    </div>
                    <div>
                        <label :class="crmLabel">Копия (необязательно)</label>
                        <input v-model="sendForm.cc_raw" type="text" :class="crmFieldFluid" placeholder="cc@example.com" />
                    </div>
                    <div>
                        <label :class="crmLabel">Тема</label>
                        <input v-model="sendForm.subject" type="text" :class="crmFieldFluid" />
                    </div>
                    <div>
                        <label :class="crmLabel">Текст</label>
                        <textarea v-model="sendForm.body" rows="8" :class="crmFieldFluid" />
                    </div>
                    <div>
                        <label :class="crmLabel">Вложения</label>
                        <input
                            type="file"
                            multiple
                            :class="crmFieldFluid"
                            @change="onSendAttachmentsSelected"
                        />
                        <p v-if="attachmentLimits.hint" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ attachmentLimits.hint }}</p>
                        <ul v-if="sendForm.attachments.length" class="mt-2 space-y-1 text-xs text-zinc-600 dark:text-zinc-300">
                            <li v-for="(file, index) in sendForm.attachments" :key="`send-att-${index}`">
                                {{ file.name }}
                            </li>
                        </ul>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">От: {{ fromEmail }}</p>
                    <button type="submit" :class="crmBtnPrimary" :disabled="sendForm.processing">Отправить</button>
                </form>
            </section>
        </div>
    </div>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { Paperclip, Star } from 'lucide-vue-next';
import { onMounted, reactive, watch } from 'vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnPrimary, crmBtnSecondary, crmBtnSecondaryOutline, crmFieldFluid, crmLabel, crmPanel } from '@/support/crmUi.js';

defineOptions({ layout: (h, page) => h(CrmLayout, { activeKey: 'mail' }, () => page) });

const props = defineProps({
    threads: {
        type: Array,
        default: () => [],
    },
    selectedThread: {
        type: Object,
        default: null,
    },
    messages: {
        type: Array,
        default: () => [],
    },
    leads: {
        type: Array,
        default: () => [],
    },
    orders: {
        type: Array,
        default: () => [],
    },
    fromEmail: {
        type: String,
        default: '',
    },
    replyDefaults: {
        type: Object,
        default: null,
    },
    composeDefaults: {
        type: Object,
        default: null,
    },
    attachmentLimits: {
        type: Object,
        default: () => ({
            hint: '',
            max_files: 5,
            max_file_kb: 10240,
        }),
    },
    mailView: {
        type: Object,
        default: () => ({
            can_view_all_mailboxes: false,
            selected_mailbox_user_id: null,
            owners: [],
            unassigned_thread_count: 0,
            total_thread_count: 0,
        }),
    },
});

const sendForm = useForm({
    lead_id: null,
    order_id: null,
    to_raw: '',
    cc_raw: '',
    subject: '',
    body: '',
    attachments: [],
});

const replyForm = useForm({
    to_raw: '',
    cc_raw: '',
    body: '',
    attachments: [],
});

const messageViewMode = reactive({});

function applyComposeDefaults(defaults) {
    if (!defaults) {
        return;
    }

    sendForm.order_id = defaults.order_id ?? null;
    sendForm.to_raw = (defaults.to ?? []).join(', ');
    sendForm.subject = defaults.subject ?? '';
}

watch(
    () => props.replyDefaults,
    (defaults) => {
        if (!defaults) {
            return;
        }

        replyForm.to_raw = (defaults.to ?? []).join(', ');
        replyForm.cc_raw = '';
        replyForm.body = '';
    },
    { immediate: true },
);

watch(
    () => props.composeDefaults,
    (defaults) => {
        applyComposeDefaults(defaults);
    },
    { immediate: true },
);

onMounted(() => {
    if (typeof window === 'undefined' || props.composeDefaults) {
        return;
    }

    const orderId = new URL(window.location.href).searchParams.get('order_id');

    if (!orderId) {
        return;
    }

    sendForm.order_id = Number.parseInt(orderId, 10) || null;
});

function mailboxIndexUrl(mailboxUserId) {
    const params = {};

    if (props.composeDefaults?.order_id) {
        params.order_id = props.composeDefaults.order_id;
    }

    if (mailboxUserId !== null && mailboxUserId !== undefined) {
        params.mailbox = mailboxUserId;
    }

    return route('mail.index', params);
}

function threadShowUrl(threadId) {
    const params = {};

    if (props.mailView.can_view_all_mailboxes && props.mailView.selected_mailbox_user_id !== null) {
        params.mailbox = props.mailView.selected_mailbox_user_id;
    }

    return route('mail.threads.show', { mailThread: threadId, ...params });
}

function mailboxFolderClass(mailboxUserId) {
    const active = props.mailView.selected_mailbox_user_id === mailboxUserId
        || (mailboxUserId === null && props.mailView.selected_mailbox_user_id === null);

    return [
        'flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-sm transition',
        active
            ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300'
            : 'text-zinc-700 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-900',
    ];
}

function setMessageViewMode(messageId, mode) {
    messageViewMode[messageId] = mode;
}

function parseEmails(raw) {
    return String(raw ?? '')
        .split(/[,;]/)
        .map((value) => value.trim())
        .filter(Boolean);
}

function pickAttachmentFiles(event) {
    const files = Array.from(event.target?.files ?? []);
    const maxFiles = Math.max(1, Number(props.attachmentLimits.max_files) || 5);

    return files.slice(0, maxFiles);
}

function onSendAttachmentsSelected(event) {
    sendForm.attachments = pickAttachmentFiles(event);
}

function onReplyAttachmentsSelected(event) {
    replyForm.attachments = pickAttachmentFiles(event);
}

function submitSend() {
    sendForm
        .transform((data) => ({
            lead_id: data.lead_id,
            order_id: data.order_id,
            to: parseEmails(data.to_raw),
            cc: parseEmails(data.cc_raw),
            subject: data.subject,
            body: data.body,
            attachments: data.attachments,
        }))
        .post(route('mail.send'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                sendForm.reset('to_raw', 'cc_raw', 'subject', 'body', 'attachments');
            },
        });
}

function submitReply() {
    if (!props.selectedThread) {
        return;
    }

    replyForm
        .transform((data) => ({
            to: parseEmails(data.to_raw),
            cc: parseEmails(data.cc_raw),
            body: data.body,
            attachments: data.attachments,
        }))
        .post(route('mail.threads.reply', props.selectedThread.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                replyForm.reset('body', 'attachments');
            },
        });
}

function formatFileSize(bytes) {
    const value = Number(bytes);

    if (!Number.isFinite(value) || value <= 0) {
        return '';
    }

    if (value < 1024) {
        return `${value} Б`;
    }

    if (value < 1024 * 1024) {
        return `${(value / 1024).toFixed(1)} КиБ`;
    }

    return `${(value / 1024 / 1024).toFixed(1)} МиБ`;
}

function toggleImportance(message) {
    router.patch(
        route('mail.messages.importance', message.id),
        { is_important: !message.is_important },
        { preserveScroll: true },
    );
}

function threadLinkClass(threadId) {
    const active = props.selectedThread?.id === threadId;

    return [
        'block rounded-xl border p-3 text-sm transition',
        active
            ? 'border-indigo-300 bg-indigo-50/80 dark:border-indigo-700 dark:bg-indigo-950/40'
            : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700',
    ];
}

function importanceButtonClass(isImportant) {
    return [
        crmBtnSecondary,
        'shrink-0 p-2',
        isImportant ? 'text-amber-500' : 'text-zinc-400',
    ];
}

function directionLabel(direction) {
    return direction === 'outbound' ? 'исходящее' : 'входящее';
}

function formatRecipients(emails) {
    if (!Array.isArray(emails) || emails.length === 0) {
        return '—';
    }

    return emails.join(', ');
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString('ru-RU');
}
</script>

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

            <section class="flex min-h-0 flex-1 flex-col gap-2 overflow-hidden">
                <template v-if="selectedThread">
                    <div :class="`${crmPanel} shrink-0 space-y-3 p-4`">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ selectedThread.subject }}</h2>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                            <span v-if="selectedThread.mailbox_owner_name">
                                Ящик: {{ selectedThread.mailbox_owner_name }}
                                <span v-if="selectedThread.mailbox_owner_email">({{ selectedThread.mailbox_owner_email }})</span>
                            </span>
                            <Link
                                v-if="selectedThread.lead_id"
                                :href="route('leads.show', selectedThread.lead_id)"
                                class="text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                Лид {{ selectedThread.lead_number }}
                                <span v-if="selectedThread.lead_title">— {{ selectedThread.lead_title }}</span>
                            </Link>
                            <Link
                                v-if="selectedThread.order_id"
                                :href="route('orders.edit', selectedThread.order_id)"
                                class="text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                Заказ {{ selectedThread.order_number }}
                            </Link>
                            <span v-if="selectedThread.contractor_name">{{ selectedThread.contractor_name }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                :class="threadActionButtonClass(showLinkPanel)"
                                @click="toggleLinkPanel"
                            >
                                <Link2 class="h-4 w-4" />
                                Привязка
                            </button>
                            <button
                                type="button"
                                :class="threadActionButtonClass(showReplyForm)"
                                @click="toggleReplyForm"
                            >
                                <Reply class="h-4 w-4" />
                                Ответить
                            </button>
                            <template v-if="mailAiEnabled">
                                <button
                                    type="button"
                                    :class="threadActionButtonClass(showAiPanel)"
                                    :disabled="aiBusy"
                                    @click="toggleAiPanel"
                                >
                                    AI
                                </button>
                            </template>
                            <button
                                type="button"
                                :class="`${crmBtnSecondaryOutline} ml-auto`"
                                @click="deleteThread"
                            >
                                <Trash2 class="h-4 w-4" />
                                Удалить
                            </button>
                        </div>

                        <div
                            v-if="showAiPanel && mailAiEnabled"
                            class="space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-800"
                        >
                            <div class="flex flex-wrap gap-2">
                                <button type="button" :class="crmBtnSecondary" :disabled="aiBusy" @click="runAiAction('summarize')">
                                    Резюме
                                </button>
                                <button type="button" :class="crmBtnSecondary" :disabled="aiBusy" @click="runAiAction('draft')">
                                    Черновик
                                </button>
                                <button
                                    v-if="selectedThread.lead_id"
                                    type="button"
                                    :class="crmBtnSecondary"
                                    :disabled="aiBusy"
                                    @click="runAiAction('next_step')"
                                >
                                    Следующий шаг
                                </button>
                            </div>
                            <div v-if="aiError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                                {{ aiError }}
                            </div>
                            <div v-if="aiResult" class="space-y-2 rounded-xl border border-zinc-200 bg-zinc-50/80 p-3 text-sm dark:border-zinc-800 dark:bg-zinc-900/40">
                                <div v-if="aiResult.summary" class="whitespace-pre-wrap text-zinc-800 dark:text-zinc-100">{{ aiResult.summary }}</div>
                                <ul v-if="aiResult.key_points?.length" class="list-disc space-y-1 pl-5 text-zinc-700 dark:text-zinc-200">
                                    <li v-for="(point, index) in aiResult.key_points" :key="`point-${index}`">{{ point }}</li>
                                </ul>
                                <div v-if="aiResult.subject" class="font-medium text-zinc-900 dark:text-zinc-50">{{ aiResult.subject }}</div>
                                <div v-if="aiResult.body" class="whitespace-pre-wrap text-zinc-800 dark:text-zinc-100">{{ aiResult.body }}</div>
                                <div v-if="aiResult.next_step" class="space-y-1">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-50">{{ aiResult.next_step }}</div>
                                    <div v-if="aiResult.rationale" class="text-zinc-600 dark:text-zinc-300">{{ aiResult.rationale }}</div>
                                </div>
                                <div v-if="aiSuggestionKey" class="flex flex-wrap gap-2 border-t border-zinc-200 pt-2 dark:border-zinc-700">
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">Полезно?</span>
                                    <button type="button" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400" @click="submitAiFeedback('helpful')">
                                        Да
                                    </button>
                                    <button type="button" class="text-xs text-zinc-600 hover:underline dark:text-zinc-300" @click="submitAiFeedback('not_helpful')">
                                        Нет
                                    </button>
                                </div>
                            </div>
                        </div>

                        <form
                            v-if="showLinkPanel"
                            class="space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-800"
                            @submit.prevent="submitLinks"
                        >
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                Укажите лид или заказ, если система не определила их автоматически.
                            </p>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <MailLinkPicker
                                    v-model="linkForm.lead_id"
                                    type="lead"
                                    :seeds="linkLeadSeeds"
                                    label="Лид"
                                    placeholder="Номер или название лида"
                                />
                                <MailLinkPicker
                                    v-model="linkForm.order_id"
                                    type="order"
                                    :seeds="linkOrderSeeds"
                                    label="Заказ"
                                    placeholder="Номер заказа"
                                />
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" :class="crmBtnSecondary" :disabled="linkForm.processing">
                                    Сохранить привязку
                                </button>
                                <button
                                    v-if="threadHasLinks"
                                    type="button"
                                    :class="crmBtnSecondaryOutline"
                                    @click="showLinkPanel = false"
                                >
                                    Свернуть
                                </button>
                            </div>
                        </form>
                    </div>

                    <div :class="`${crmPanel} flex min-h-0 flex-1 flex-col overflow-hidden`">
                        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
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
                                        :class="messageShowsHtml(message) ? crmBtnSecondary : crmBtnSecondaryOutline"
                                        @click="setMessageViewMode(message.id, 'html')"
                                    >
                                        Форматированный
                                    </button>
                                    <button
                                        type="button"
                                        :class="!messageShowsHtml(message) ? crmBtnSecondary : crmBtnSecondaryOutline"
                                        @click="setMessageViewMode(message.id, 'text')"
                                    >
                                        Текст
                                    </button>
                                </div>
                                <div
                                    v-if="message.body_html && messageShowsHtml(message)"
                                    class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950"
                                >
                                    <iframe
                                        :srcdoc="mailHtmlSrcdoc(message.body_html)"
                                        class="block min-h-[120px] w-full border-0"
                                        sandbox="allow-popups allow-popups-to-escape-sandbox"
                                        :title="`Письмо от ${message.from_email || 'отправителя'}`"
                                        @load="resizeMailIframe"
                                    />
                                </div>
                                <pre
                                    v-else
                                    class="whitespace-pre-wrap font-sans text-sm text-zinc-700 dark:text-zinc-200"
                                >{{ message.body_text }}</pre>
                            </div>
                            <ul
                                v-if="message.attachments?.length"
                                class="mt-3 space-y-1 text-xs text-zinc-600 dark:text-zinc-300"
                            >
                                <li v-for="(file, fileIndex) in message.attachments" :key="`${message.id}-att-${fileIndex}`" class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <Paperclip class="h-3.5 w-3.5 shrink-0" />
                                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ file.name }}</span>
                                    <span v-if="file.file_size" class="text-zinc-400">({{ formatFileSize(file.file_size) }})</span>
                                    <button
                                        v-if="file.preview_url"
                                        type="button"
                                        class="text-indigo-600 hover:underline dark:text-indigo-400"
                                        @click="openAttachmentPreview(file)"
                                    >
                                        Просмотр
                                    </button>
                                    <a
                                        v-if="file.download_url"
                                        :href="file.download_url"
                                        class="text-zinc-500 hover:underline dark:text-zinc-400"
                                    >
                                        Скачать
                                    </a>
                                </li>
                            </ul>
                        </article>
                        </div>
                    </div>

                    <form
                        v-if="showReplyForm"
                        :class="`${crmPanel} max-h-[min(42vh,22rem)] shrink-0 space-y-3 overflow-y-auto p-4`"
                        @submit.prevent="submitReply"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Ответить</h3>
                            <button type="button" :class="crmBtnSecondaryOutline" @click="showReplyForm = false">
                                Свернуть
                            </button>
                        </div>
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
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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

        <div
            v-if="attachmentPreview"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="`Предпросмотр: ${attachmentPreview.name}`"
            @click.self="closeAttachmentPreview"
        >
            <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ attachmentPreview.name }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Предпросмотр в CRM</div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <a
                            v-if="attachmentPreview.download_url"
                            :href="attachmentPreview.download_url"
                            class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                        >
                            Скачать
                        </a>
                        <button
                            type="button"
                            class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                            @click="closeAttachmentPreview"
                        >
                            Закрыть
                        </button>
                    </div>
                </div>
                <div class="min-h-0 flex-1 overflow-auto bg-zinc-100 p-2 dark:bg-zinc-900">
                    <iframe
                        v-if="attachmentPreview.preview_kind !== 'image'"
                        :src="attachmentPreview.preview_url"
                        class="mx-auto block h-[min(75vh,900px)] w-full max-w-4xl rounded-lg border border-zinc-200 bg-white dark:border-zinc-800"
                        title="Предпросмотр вложения"
                    />
                    <img
                        v-else
                        :src="attachmentPreview.preview_url"
                        :alt="attachmentPreview.name"
                        class="mx-auto block max-h-[75vh] max-w-full rounded-lg border border-zinc-200 bg-white dark:border-zinc-800"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Link2, Paperclip, Reply, Star, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import MailLinkPicker from '@/Components/Mail/MailLinkPicker.vue';
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

const linkForm = useForm({
    lead_id: null,
    order_id: null,
});

const messageViewMode = reactive({});
const showLinkPanel = ref(false);
const showReplyForm = ref(false);
const showAiPanel = ref(false);
const attachmentPreview = ref(null);
const aiBusy = ref(false);
const aiError = ref('');
const aiResult = ref(null);
const aiSuggestionKey = ref('');
const page = usePage();

const mailAiEnabled = computed(() => Boolean(page.props.crm_features?.commercial_mail_ai?.enabled));

const threadHasLinks = computed(() => Boolean(props.selectedThread?.lead_id || props.selectedThread?.order_id));

const linkLeadSeeds = computed(() => {
    const seeds = [...props.leads];

    if (props.selectedThread?.lead_id && !seeds.some((lead) => lead.id === props.selectedThread.lead_id)) {
        seeds.unshift({
            id: props.selectedThread.lead_id,
            number: props.selectedThread.lead_number,
            title: props.selectedThread.lead_title,
        });
    }

    return seeds;
});

const linkOrderSeeds = computed(() => {
    const seeds = [...props.orders];

    if (props.selectedThread?.order_id && !seeds.some((order) => order.id === props.selectedThread.order_id)) {
        seeds.unshift({
            id: props.selectedThread.order_id,
            order_number: props.selectedThread.order_number,
        });
    }

    return seeds;
});

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

watch(
    () => props.selectedThread,
    (thread) => {
        if (!thread) {
            showLinkPanel.value = false;
            showReplyForm.value = false;
            showAiPanel.value = false;
            aiResult.value = null;
            aiSuggestionKey.value = '';
            aiError.value = '';

            return;
        }

        linkForm.lead_id = thread.lead_id ?? null;
        linkForm.order_id = thread.order_id ?? null;
        showLinkPanel.value = !thread.lead_id && !thread.order_id;
        showReplyForm.value = false;
        showAiPanel.value = false;
        aiResult.value = null;
        aiSuggestionKey.value = '';
        aiError.value = '';
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

function threadActionButtonClass(active) {
    return active ? crmBtnSecondary : crmBtnSecondaryOutline;
}

function toggleLinkPanel() {
    showLinkPanel.value = !showLinkPanel.value;

    if (showLinkPanel.value) {
        showReplyForm.value = false;
        showAiPanel.value = false;
    }
}

function toggleReplyForm() {
    showReplyForm.value = !showReplyForm.value;

    if (showReplyForm.value) {
        showLinkPanel.value = false;
        showAiPanel.value = false;
    }
}

function toggleAiPanel() {
    showAiPanel.value = !showAiPanel.value;

    if (showAiPanel.value) {
        showLinkPanel.value = false;
        showReplyForm.value = false;
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function runAiAction(action) {
    if (!props.selectedThread || aiBusy.value) {
        return;
    }

    aiBusy.value = true;
    aiError.value = '';
    aiResult.value = null;
    aiSuggestionKey.value = '';

    const routeName = action === 'summarize'
        ? 'mail.threads.ai.summarize'
        : action === 'draft'
            ? 'mail.threads.ai.draft-reply'
            : 'mail.threads.ai.next-step';

    try {
        const response = await fetch(route(routeName, props.selectedThread.id), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || Object.values(data.errors ?? {})?.flat()?.[0] || 'Не удалось получить подсказку');
        }

        aiResult.value = data;
        aiSuggestionKey.value = data.suggestion_key ?? '';

        if (action === 'draft' && data.body) {
            showReplyForm.value = true;
            replyForm.body = data.body;
        }
    } catch (error) {
        aiError.value = error?.message || 'Ошибка AI-подсказки';
    } finally {
        aiBusy.value = false;
    }
}

async function submitAiFeedback(rating) {
    if (!aiSuggestionKey.value || aiBusy.value) {
        return;
    }

    aiBusy.value = true;

    try {
        const response = await fetch(route('mail.ai.feedback'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                suggestion_key: aiSuggestionKey.value,
                rating,
            }),
        });

        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.message || 'Не удалось сохранить отзыв');
        }
    } catch (error) {
        aiError.value = error?.message || 'Не удалось сохранить отзыв';
    } finally {
        aiBusy.value = false;
    }
}

function submitLinks() {
    if (!props.selectedThread) {
        return;
    }

    linkForm.patch(route('mail.threads.links', props.selectedThread.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (linkForm.lead_id || linkForm.order_id) {
                showLinkPanel.value = false;
            }
        },
    });
}

function deleteThread() {
    if (!props.selectedThread) {
        return;
    }

    if (!window.confirm('Удалить цепочку писем из CRM? В почтовом ящике письма останутся.')) {
        return;
    }

    const params = {};

    if (props.mailView.selected_mailbox_user_id !== null && props.mailView.selected_mailbox_user_id !== undefined) {
        params.mailbox = props.mailView.selected_mailbox_user_id;
    }

    router.delete(route('mail.threads.destroy', props.selectedThread.id), {
        data: params,
    });
}

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

function messageShowsHtml(message) {
    return Boolean(message.body_html) && messageViewMode[message.id] !== 'text';
}

function mailHtmlSrcdoc(html) {
    return `<!DOCTYPE html><html><head><meta charset="utf-8"><base target="_blank"><style>body{font-family:system-ui,-apple-system,sans-serif;font-size:14px;line-height:1.5;margin:12px;color:#334155;word-break:break-word;background:#fff;}img{max-width:100%;height:auto;}a{color:#4f46e5;}table{max-width:100%;}</style></head><body>${html}</body></html>`;
}

function resizeMailIframe(event) {
    const iframe = event.target;

    if (!iframe?.contentDocument?.body) {
        return;
    }

    iframe.style.height = `${Math.max(120, iframe.contentDocument.body.scrollHeight + 16)}px`;
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

function openAttachmentPreview(file) {
    if (!file?.preview_url) {
        return;
    }

    attachmentPreview.value = file;
}

function closeAttachmentPreview() {
    attachmentPreview.value = null;
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

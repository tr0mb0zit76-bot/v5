<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { crmBtnSecondary } from '@/support/crmUi.js';

const props = defineProps({
    order: { type: Object, default: null },
    isOrderFormEditable: { type: Boolean, default: true },
    epdIntegration: { type: Object, default: null },
    epdPreview: { type: Object, default: null },
    documentEdoAcknowledgements: { type: Array, default: () => [] },
    canEditDocumentEdoAcknowledgements: { type: Boolean, default: false },
});

const DOCUMENTS = [
    {
        key: 'etrn',
        title: 'ЭТрН',
        subtitle: 'Электронная транспортная накладная — болванка в 1С и предпросмотр титулов',
        routeName: 'orders.one-c.etrn.store',
        party: 'carrier',
        documentType: 'etrn',
        required: true,
    },
    {
        key: 'expedition_receipt',
        title: 'Экспедиторская расписка',
        subtitle: 'Электронная ЭР — необязательна для закрытия сделки, передаётся через ЭПД',
        routeName: 'orders.one-c.expedition-receipt.store',
        party: 'customer',
        documentType: 'expedition_receipt',
        required: false,
    },
];

const openByKey = reactive({
    etrn: true,
    expedition_receipt: false,
});

const integration = ref(cloneIntegration(props.epdIntegration));
const busyByKey = reactive({ etrn: false, expedition_receipt: false });
const errorByKey = reactive({ etrn: '', expedition_receipt: '' });
const ackBusy = reactive({});
const ackError = reactive({});
const ackDraft = reactive({});

watch(
    () => props.epdIntegration,
    (value) => {
        integration.value = cloneIntegration(value);
    },
);

watch(
    () => [props.documentEdoAcknowledgements, props.order?.id],
    () => {
        for (const doc of DOCUMENTS) {
            syncAckDraft(doc);
        }
    },
    { immediate: true, deep: true },
);

const panels = computed(() => DOCUMENTS.map((doc) => {
    const state = integration.value?.[doc.key] ?? null;
    const preview = props.epdPreview?.[doc.key] ?? null;
    const missing = Array.isArray(preview?.missing_required_fields)
        ? preview.missing_required_fields
        : [];
    const titles = Array.isArray(preview?.titles) ? preview.titles : [];

    return {
        ...doc,
        state,
        preview,
        missing,
        titles,
        open: Boolean(openByKey[doc.key]),
        busy: Boolean(busyByKey[doc.key]),
        error: errorByKey[doc.key] || '',
        ack: ackDraft[doc.key] ?? emptyAck(),
    };
}));

function cloneIntegration(value) {
    if (!value || typeof value !== 'object') {
        return { etrn: null, expedition_receipt: null };
    }

    return {
        etrn: value.etrn ? { ...value.etrn } : null,
        expedition_receipt: value.expedition_receipt ? { ...value.expedition_receipt } : null,
    };
}

function emptyAck() {
    return {
        received_via_edo: false,
        document_number: '',
        document_date: '',
    };
}

function findAck(doc) {
    return (Array.isArray(props.documentEdoAcknowledgements) ? props.documentEdoAcknowledgements : []).find((row) => (
        row?.party === doc.party
        && row?.document_type === doc.documentType
        && String(row?.slot_key ?? '') === String(doc.documentType)
    )) ?? null;
}

function syncAckDraft(doc) {
    const existing = findAck(doc);
    ackDraft[doc.key] = {
        received_via_edo: Boolean(existing?.received_via_edo),
        document_number: existing?.document_number ?? '',
        document_date: existing?.document_date ?? '',
    };
}

function togglePanel(key) {
    openByKey[key] = !openByKey[key];
}

function fieldDisplay(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

async function pushStub(doc) {
    if (!props.order?.id || busyByKey[doc.key] || !doc.state?.can_push) {
        return;
    }

    busyByKey[doc.key] = true;
    errorByKey[doc.key] = '';

    try {
        const response = await window.axios.post(route(doc.routeName, props.order.id), {});
        if (response.data?.epd) {
            integration.value = cloneIntegration(response.data.epd);
        } else if (response.data?.document) {
            integration.value = {
                ...integration.value,
                [doc.key]: {
                    ...(integration.value?.[doc.key] ?? {}),
                    document: response.data.document,
                    action: response.data.action,
                },
            };
        }
    } catch (error) {
        errorByKey[doc.key] = error?.response?.data?.errors?.one_c?.[0]
            || error?.response?.data?.message
            || `Не удалось синхронизировать «${doc.title}» с 1С.`;
    } finally {
        busyByKey[doc.key] = false;
    }
}

async function saveAck(doc) {
    if (!props.order?.id || !props.canEditDocumentEdoAcknowledgements) {
        return;
    }

    const draft = ackDraft[doc.key] ?? emptyAck();
    if (draft.received_via_edo && !String(draft.document_number ?? '').trim()) {
        ackError[doc.key] = 'Укажите номер документа для отметки «Отправлен».';
        return;
    }

    ackBusy[doc.key] = true;
    ackError[doc.key] = '';

    try {
        await window.axios.patch(
            route('documents.orders.edo-acknowledgement', props.order.id),
            {
                party: doc.party,
                document_type: doc.documentType,
                slot_key: doc.documentType,
                contractor_id: null,
                received_via_edo: Boolean(draft.received_via_edo),
                document_number: draft.received_via_edo ? String(draft.document_number ?? '').trim() : null,
                document_date: draft.received_via_edo && draft.document_date ? draft.document_date : null,
            },
        );

        router.reload({
            only: ['requiredDocumentChecklist', 'documentEdoAcknowledgements', 'epdPreview'],
            preserveScroll: true,
        });
    } catch (error) {
        ackError[doc.key] = error?.response?.data?.message
            || error?.response?.data?.errors?.document_number?.[0]
            || 'Не удалось сохранить отметку.';
    } finally {
        ackBusy[doc.key] = false;
    }
}

function onAckToggle(doc, event) {
    if (!ackDraft[doc.key]) {
        syncAckDraft(doc);
    }
    ackDraft[doc.key].received_via_edo = Boolean(event.target.checked);
    ackError[doc.key] = '';
    if (!ackDraft[doc.key].received_via_edo) {
        saveAck(doc);
    }
}
</script>

<template>
    <div class="space-y-4">
        <div>
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">ЭПД</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Предпросмотр титулов и создание болванок в 1С. Отправка оператору ЭПД — из 1С (кнопка там).
            </p>
        </div>

        <section
            v-for="panel in panels"
            :key="panel.key"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900/40"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50"
                @click="togglePanel(panel.key)"
            >
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ panel.title }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :class="panel.required
                                ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100'
                                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                        >
                            {{ panel.required ? 'обязательный' : 'необязательный' }}
                        </span>
                        <span
                            v-if="panel.ack.received_via_edo"
                            class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
                        >
                            отправлен
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ panel.subtitle }}</p>
                </div>
                <component
                    :is="panel.open ? ChevronDown : ChevronRight"
                    class="h-5 w-5 shrink-0 text-slate-400"
                />
            </button>

            <div
                v-show="panel.open"
                class="space-y-4 border-t border-slate-200 px-4 py-4 dark:border-slate-700"
            >
                <div
                    v-if="panel.state?.enabled && (panel.state?.can_create || panel.state?.document)"
                    class="space-y-2 rounded-xl border border-sky-200 bg-sky-50/50 p-3 dark:border-sky-900/50 dark:bg-sky-950/20"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-sky-950 dark:text-sky-100">1С · {{ panel.title }}</div>
                            <p class="mt-0.5 text-xs text-sky-900/80 dark:text-sky-200/80">
                                Болванка для кнопки отправки оператору в 1С. Проведённую из CRM не меняем.
                            </p>
                        </div>
                        <button
                            v-if="panel.state?.can_create"
                            type="button"
                            :class="crmBtnSecondary"
                            :disabled="!order?.id || panel.busy || !isOrderFormEditable || !panel.state?.can_push"
                            @click="pushStub(panel)"
                        >
                            {{ panel.busy ? 'Синхронизация…' : (panel.state?.button_label || `Создать ${panel.title} в 1С`) }}
                        </button>
                    </div>
                    <p
                        v-if="panel.state?.hint"
                        class="text-xs"
                        :class="panel.state?.action === 'blocked_posted' ? 'text-amber-800 dark:text-amber-200' : 'text-sky-900/80 dark:text-sky-200/80'"
                    >
                        {{ panel.state.hint }}
                    </p>
                    <p
                        v-if="panel.state?.document?.status === 'created'"
                        class="text-xs text-sky-900 dark:text-sky-100"
                    >
                        Связь:
                        № {{ panel.state.document.external_number || '—' }}
                        · ref {{ panel.state.document.external_ref || '—' }}
                        <span v-if="panel.state.posted"> · проведена</span>
                        <span v-else-if="panel.state.stale"> · есть изменения в CRM</span>
                    </p>
                    <p
                        v-else-if="panel.state?.document?.status === 'failed'"
                        class="text-xs text-rose-700 dark:text-rose-300"
                    >
                        Ошибка: {{ panel.state.document.last_error || 'неизвестно' }}
                    </p>
                    <p v-if="panel.error" class="text-xs text-rose-700 dark:text-rose-300">{{ panel.error }}</p>
                </div>

                <div
                    v-if="canEditDocumentEdoAcknowledgements || panel.ack.received_via_edo"
                    class="rounded-xl border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-800/40"
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-800 dark:text-slate-100">
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                :checked="panel.ack.received_via_edo"
                                :disabled="!canEditDocumentEdoAcknowledgements || ackBusy[panel.key]"
                                @change="onAckToggle(panel, $event)"
                            >
                            Отправлен (ЭПД)
                        </label>
                        <template v-if="panel.ack.received_via_edo">
                            <input
                                v-model="ackDraft[panel.key].document_number"
                                type="text"
                                placeholder="Номер"
                                class="rounded-lg border border-slate-300 px-2 py-1 text-sm dark:border-slate-600 dark:bg-slate-900"
                                :disabled="!canEditDocumentEdoAcknowledgements || ackBusy[panel.key]"
                            >
                            <input
                                v-model="ackDraft[panel.key].document_date"
                                type="date"
                                class="rounded-lg border border-slate-300 px-2 py-1 text-sm dark:border-slate-600 dark:bg-slate-900"
                                :disabled="!canEditDocumentEdoAcknowledgements || ackBusy[panel.key]"
                            >
                            <button
                                v-if="canEditDocumentEdoAcknowledgements"
                                type="button"
                                :class="crmBtnSecondary"
                                :disabled="ackBusy[panel.key]"
                                @click="saveAck(panel)"
                            >
                                {{ ackBusy[panel.key] ? 'Сохранение…' : 'Сохранить' }}
                            </button>
                        </template>
                    </div>
                    <p v-if="ackError[panel.key]" class="mt-2 text-xs text-rose-600">{{ ackError[panel.key] }}</p>
                </div>

                <div
                    v-if="panel.missing.length > 0"
                    class="rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    Не хватает для полного титула:
                    {{ panel.missing.join(', ') }}
                </div>

                <div class="space-y-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Предпросмотр титулов
                    </h3>
                    <div
                        v-for="title in panel.titles"
                        :key="`${panel.key}-${title.code}`"
                        class="rounded-xl border border-slate-200 dark:border-slate-700"
                    >
                        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-800 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-100">
                            {{ title.label }}
                        </div>
                        <dl class="grid gap-2 p-3 sm:grid-cols-2">
                            <div
                                v-for="field in title.fields"
                                :key="`${title.code}-${field.key}`"
                                class="rounded-lg px-2 py-1.5"
                                :class="field.filled
                                    ? 'bg-white dark:bg-transparent'
                                    : 'bg-rose-50/80 dark:bg-rose-950/20'"
                            >
                                <dt class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                    {{ field.label }}
                                </dt>
                                <dd
                                    class="mt-0.5 text-sm"
                                    :class="field.filled
                                        ? 'text-slate-900 dark:text-slate-100'
                                        : 'text-rose-700 dark:text-rose-300'"
                                >
                                    {{ fieldDisplay(field.value) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <p
                        v-if="panel.titles.length === 0"
                        class="text-sm text-slate-500 dark:text-slate-400"
                    >
                        Нет данных для предпросмотра. Сохраните заказ с маршрутом и сторонами.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>

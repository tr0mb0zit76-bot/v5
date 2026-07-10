<script setup>
import { computed, reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ExternalLink, Trash2 } from 'lucide-vue-next';
import axios from 'axios';
import { expandClosingRowsForEdo, edoAcknowledgementToggleLabel, rowHasClosingEdoControls } from '@/support/orderDocumentClosingEdoRows.js';
import { buildRegistryTableRows } from '@/support/orderDocumentRegistryRows.js';
import { attachTrackReceivedToRegistryRows } from '@/support/orderTrackingDates.js';
import {
    documentTypeDisplayLabel,
    isTransportDocumentType,
    TRANSPORT_SUBTYPE_OPTIONS,
    withTransportSubtypeOptions,
} from '@/support/orderDocumentTypes.js';

const props = defineProps({
    signedDocuments: { type: Array, default: () => [] },
    requiredDocumentRules: { type: Array, default: () => [] },
    requiredDocumentChecklist: { type: Array, default: () => [] },
    documentTypeOptions: { type: Array, default: () => [] },
    edoAcknowledgements: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: true },
    canEditEdo: { type: Boolean, default: false },
    deletingId: { type: [Number, null], default: null },
    order: { type: Object, default: null },
    orderId: { type: [Number, null], default: null },
    clientPaymentSchedule: { type: Object, default: () => ({}) },
    contractorsCosts: { type: Array, default: () => [] },
    performers: { type: Array, default: () => [] },
});

const emit = defineEmits(['delete', 'update:field', 'edo-updated']);

const resolvedOrderId = computed(() => props.order?.id ?? props.orderId ?? null);

const trackingContext = computed(() => ({
    clientPaymentSchedule: props.clientPaymentSchedule,
    contractorsCosts: props.contractorsCosts,
    order: props.order,
    performers: props.performers,
}));

const rows = computed(() => {
    const registryRows = buildRegistryTableRows(
        props.signedDocuments,
        props.requiredDocumentRules,
        props.requiredDocumentChecklist,
        props.documentTypeOptions,
    );

    const expandedRows = expandClosingRowsForEdo(
        registryRows,
        props.signedDocuments,
        props.edoAcknowledgements,
    );

    return attachTrackReceivedToRegistryRows(
        expandedRows,
        trackingContext.value,
        props.requiredDocumentRules,
    );
});

const showReceivedDateColumn = computed(() => rows.value.some((row) => row.track_field));
const showEdoColumn = computed(() => rows.value.some((row) => rowHasClosingEdoControls(row) || row.slot_kind?.endsWith('_closing')));

const localReceivedDates = reactive({});
const savingTrackFields = reactive({});
const trackSaveErrors = reactive({});
const localEdoState = reactive({});
const savingEdoKeys = reactive({});
const edoSaveErrors = reactive({});

function edoRowKey(row) {
    return [
        row.party,
        row.type,
        row.slot_key ?? '',
        row.contractor_id ?? 0,
    ].join('|');
}

watch(
    rows,
    (nextRows) => {
        const activeFields = new Set();
        const activeEdoKeys = new Set();

        for (const row of nextRows) {
            if (row.track_field) {
                activeFields.add(row.track_field);
                localReceivedDates[row.track_field] = row.received_date ?? '';
                delete trackSaveErrors[row.track_field];
            }

            if (rowHasClosingEdoControls(row)) {
                const key = edoRowKey(row);
                activeEdoKeys.add(key);
                localEdoState[key] = {
                    received_via_edo: Boolean(row.edo_acknowledgement?.received_via_edo),
                    document_number: row.edo_acknowledgement?.document_number ?? row.number ?? '',
                    document_date: row.edo_acknowledgement?.document_date ?? row.document_date ?? '',
                };
                delete edoSaveErrors[key];
            }
        }

        for (const field of Object.keys(localReceivedDates)) {
            if (!activeFields.has(field)) {
                delete localReceivedDates[field];
                delete savingTrackFields[field];
                delete trackSaveErrors[field];
            }
        }

        for (const key of Object.keys(localEdoState)) {
            if (!activeEdoKeys.has(key)) {
                delete localEdoState[key];
                delete savingEdoKeys[key];
                delete edoSaveErrors[key];
            }
        }
    },
    { immediate: true, deep: true },
);

const typeLabelByValue = computed(() => {
    const map = new Map();

    (props.documentTypeOptions || []).forEach((opt) => {
        map.set(opt.value, opt.label);
    });

    return map;
});

const attachTypeOptions = computed(() => withTransportSubtypeOptions(props.documentTypeOptions || []));

function findPerformerContractorName(contractorId, performers) {
    const targetId = Number(contractorId ?? 0);
    if (targetId <= 0) {
        return null;
    }

    for (const performer of performers ?? []) {
        if (Number(performer?.contractor_id ?? 0) === targetId && performer?.contractor_name) {
            return String(performer.contractor_name).trim();
        }

        for (const slot of performer?.split_carriers ?? []) {
            if (Number(slot?.contractor_id ?? 0) === targetId && slot?.contractor_name) {
                return String(slot.contractor_name).trim();
            }
        }
    }

    return null;
}

function findContractorCostName(contractorId, contractorsCosts) {
    const targetId = Number(contractorId ?? 0);
    if (targetId <= 0) {
        return null;
    }

    const match = (contractorsCosts ?? []).find((row) => Number(row?.contractor_id ?? 0) === targetId);

    return match?.contractor_name ? String(match.contractor_name).trim() : null;
}

function partyLabel(row) {
    const explicit = String(row.counterparty_label ?? '').trim();
    if (explicit !== '') {
        return explicit;
    }

    if (row.party === 'customer') {
        const clientName = props.order?.client?.name ?? props.order?.client_snapshot?.name;
        return clientName ? String(clientName).trim() : 'Заказчик';
    }

    if (row.party === 'carrier') {
        const contractorId = Number(row.contractor_id ?? 0);
        const performerName = findPerformerContractorName(contractorId, props.performers);
        if (performerName) {
            return performerName;
        }

        const carrierName = props.order?.carrier?.name;
        if (carrierName) {
            return String(carrierName).trim();
        }

        return 'Перевозчик';
    }

    if (row.party === 'contractor') {
        const contractorId = Number(row.contractor_id ?? 0);
        return findContractorCostName(contractorId, props.contractorsCosts)
            ?? findPerformerContractorName(contractorId, props.performers)
            ?? 'Подрядчик';
    }

    return partyLabelFromParty(row.party);
}

function partyLabelFromParty(party) {
    if (party === 'customer') {
        return 'Заказчик';
    }

    if (party === 'carrier') {
        return 'Перевозчик';
    }

    if (party === 'contractor') {
        return 'Подрядчик';
    }

    return 'Внутренний';
}

function displayTypeLabel(row) {
    if (row.type_label) {
        return row.type_label;
    }

    return documentTypeDisplayLabel(row.type, typeLabelByValue.value);
}

function typeOptionsForRow(row) {
    if (isTransportDocumentType(row.type)) {
        return TRANSPORT_SUBTYPE_OPTIONS;
    }

    return attachTypeOptions.value;
}

function onFieldChange(doc, field, value) {
    emit('update:field', { id: doc.id, field, value });
}

function onReceivedDateInput(row, event) {
    localReceivedDates[row.track_field] = event.target.value;
}

function onReceivedDateBlur(row) {
    if (!props.canEdit || !resolvedOrderId.value || !row.track_field) {
        return;
    }

    const nextValue = localReceivedDates[row.track_field] === ''
        ? null
        : localReceivedDates[row.track_field];
    const currentValue = row.received_date === '' || row.received_date == null
        ? null
        : String(row.received_date).slice(0, 10);

    if (nextValue === currentValue) {
        return;
    }

    savingTrackFields[row.track_field] = true;
    delete trackSaveErrors[row.track_field];

    router.patch(route('orders.inline-update', resolvedOrderId.value), {
        field: row.track_field,
        value: nextValue,
        wizard_context: true,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['order'],
        onError: (errors) => {
            trackSaveErrors[row.track_field] = errors?.field ?? errors?.value ?? 'Не удалось сохранить дату.';
            localReceivedDates[row.track_field] = row.received_date ?? '';
        },
        onFinish: () => {
            savingTrackFields[row.track_field] = false;
        },
    });
}

function canEditEdoForRow(row) {
    return props.canEditEdo
        && resolvedOrderId.value
        && rowHasClosingEdoControls(row)
        && !row.uploaded_file_preview_url;
}

function edoToggleLabel(row) {
    return edoAcknowledgementToggleLabel(row.party);
}

function isMandatoryChecklistRow(row) {
    return row.requirement_key != null && String(row.requirement_key) !== '';
}

function checklistCellClass(row) {
    if (!isMandatoryChecklistRow(row)) {
        return '';
    }

    if (row.checklist_completed) {
        return 'bg-emerald-50/90 dark:bg-emerald-950/35';
    }

    return 'bg-rose-50/90 dark:bg-rose-950/35';
}

function checklistCheckboxClass(row) {
    const base = 'h-4 w-4 rounded focus:ring-2 focus:ring-offset-0 disabled:cursor-default';

    if (!isMandatoryChecklistRow(row)) {
        return `${base} border-zinc-300 text-zinc-400 dark:border-zinc-600`;
    }

    if (row.checklist_completed) {
        return `${base} border-emerald-500 bg-emerald-100 text-emerald-700 focus:ring-emerald-400 dark:border-emerald-400 dark:bg-emerald-950/60 dark:text-emerald-300`;
    }

    return `${base} border-rose-400 bg-rose-100 text-rose-600 focus:ring-rose-400 dark:border-rose-500 dark:bg-rose-950/60 dark:text-rose-300`;
}

function checklistStatusTitle(row) {
    if (!isMandatoryChecklistRow(row)) {
        return 'Дополнительный документ';
    }

    if (row.checklist_completed) {
        return `${row.requirement_label ?? 'Обязательный документ'} — выполнено`;
    }

    return `${row.requirement_label ?? 'Обязательный документ'} — не хватает документа`;
}

async function saveEdoRow(row) {
    const key = edoRowKey(row);
    const state = localEdoState[key];

    if (!canEditEdoForRow(row) || !state) {
        return;
    }

    savingEdoKeys[key] = true;
    delete edoSaveErrors[key];

    try {
        const response = await axios.patch(
            route('documents.orders.edo-acknowledgement', resolvedOrderId.value),
            {
                party: row.party,
                document_type: row.type,
                slot_key: row.slot_key ?? '',
                contractor_id: row.contractor_id ?? null,
                received_via_edo: Boolean(state.received_via_edo),
                document_number: state.received_via_edo ? String(state.document_number ?? '').trim() : null,
                document_date: state.received_via_edo && state.document_date ? state.document_date : null,
            },
        );

        emit('edo-updated', response.data ?? {});
    } catch (error) {
        edoSaveErrors[key] = error?.response?.data?.message
            ?? error?.response?.data?.errors?.document_number?.[0]
            ?? error?.response?.data?.errors?.received_via_edo?.[0]
            ?? 'Не удалось сохранить отметку ЭДО.';
    } finally {
        savingEdoKeys[key] = false;
    }
}

function onEdoToggle(row, event) {
    const key = edoRowKey(row);
    if (!localEdoState[key]) {
        return;
    }

    localEdoState[key].received_via_edo = event.target.checked;
    delete edoSaveErrors[key];

    if (!event.target.checked) {
        saveEdoRow(row);
    }
}

function onEdoFieldBlur(row) {
    const key = edoRowKey(row);
    const state = localEdoState[key];

    if (!state?.received_via_edo) {
        return;
    }

    if (String(state.document_number ?? '').trim() === '') {
        edoSaveErrors[key] = 'Укажите номер документа для отметки ЭДО.';

        return;
    }

    saveEdoRow(row);
}
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
            <thead class="bg-zinc-50/80 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                <tr>
                    <th class="w-10 px-2 py-2.5 text-center" title="Обязательный документ: красный — не хватает, зелёный — выполнено">
                        ✓
                    </th>
                    <th class="min-w-[180px] max-w-[240px] px-3 py-2.5">Сторона</th>
                    <th class="w-[96px] min-w-[96px] px-3 py-2.5">Тип</th>
                    <th class="min-w-[120px] px-3 py-2.5">Номер</th>
                    <th class="min-w-[130px] px-3 py-2.5">Дата документа</th>
                    <th v-if="showReceivedDateColumn" class="min-w-[130px] px-3 py-2.5">Дата получения</th>
                    <th v-if="showEdoColumn" class="min-w-[180px] px-3 py-2.5">ЭДО</th>
                    <th class="min-w-[140px] px-3 py-2.5">Файл</th>
                    <th v-if="canEdit" class="px-3 py-2.5 text-right"> </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-950">
                <tr
                    v-for="row in rows"
                    :key="`registry-row-${row.id ?? row._localKey}`"
                    :class="row.is_placeholder ? 'bg-zinc-50/80 dark:bg-zinc-900/30' : ''"
                >
                    <td
                        class="px-2 py-2.5 text-center align-middle"
                        :class="checklistCellClass(row)"
                    >
                        <input
                            type="checkbox"
                            :class="checklistCheckboxClass(row)"
                            :checked="row.checklist_completed"
                            disabled
                            :title="checklistStatusTitle(row)"
                        >
                    </td>
                    <td class="max-w-[240px] px-3 py-2.5 whitespace-normal text-zinc-700 dark:text-zinc-300">
                        {{ partyLabel(row) }}
                    </td>
                    <td class="w-[96px] min-w-[96px] px-3 py-2.5 text-zinc-700 dark:text-zinc-300">
                        <select
                            v-if="canEdit && row.id && !row.is_placeholder && !row.is_closing_edo_row"
                            :value="row.type"
                            class="w-full min-w-[140px] rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            @change="onFieldChange(row, 'type', $event.target.value)"
                        >
                            <option v-for="opt in typeOptionsForRow(row)" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <span v-else>{{ displayTypeLabel(row) }}</span>
                    </td>
                    <td class="px-3 py-2.5 text-zinc-500 dark:text-zinc-400">
                        <template v-if="canEditEdoForRow(row) && localEdoState[edoRowKey(row)]?.received_via_edo">
                            <input
                                v-model="localEdoState[edoRowKey(row)].document_number"
                                type="text"
                                class="w-full min-w-[100px] rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                placeholder="№ документа"
                                :disabled="savingEdoKeys[edoRowKey(row)]"
                                @blur="onEdoFieldBlur(row)"
                            >
                        </template>
                        <input
                            v-else-if="canEdit && row.id && !row.is_placeholder && !row.is_closing_edo_row"
                            :value="row.number ?? ''"
                            type="text"
                            class="w-full min-w-[100px] rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            @change="onFieldChange(row, 'number', $event.target.value)"
                        >
                        <span v-else>{{ row.is_placeholder && !localEdoState[edoRowKey(row)]?.received_via_edo ? '—' : (row.number || localEdoState[edoRowKey(row)]?.document_number || '—') }}</span>
                    </td>
                    <td class="px-3 py-2.5 text-zinc-500 dark:text-zinc-400">
                        <template v-if="canEditEdoForRow(row) && localEdoState[edoRowKey(row)]?.received_via_edo">
                            <input
                                v-model="localEdoState[edoRowKey(row)].document_date"
                                type="date"
                                class="rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="savingEdoKeys[edoRowKey(row)]"
                                @blur="onEdoFieldBlur(row)"
                            >
                        </template>
                        <input
                            v-else-if="canEdit && row.id && !row.is_placeholder && !row.is_closing_edo_row"
                            :value="row.document_date ?? ''"
                            type="date"
                            class="rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            @change="onFieldChange(row, 'document_date', $event.target.value)"
                        >
                        <span v-else>{{ row.is_placeholder && !localEdoState[edoRowKey(row)]?.received_via_edo ? '—' : (row.document_date || localEdoState[edoRowKey(row)]?.document_date || '—') }}</span>
                    </td>
                    <td v-if="showReceivedDateColumn" class="px-3 py-2.5 text-zinc-500 dark:text-zinc-400">
                        <template v-if="row.track_field">
                            <input
                                v-if="canEdit && resolvedOrderId"
                                :value="localReceivedDates[row.track_field] ?? ''"
                                type="date"
                                class="rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                :disabled="savingTrackFields[row.track_field]"
                                title="Для расчёта плановой даты оплаты"
                                @input="onReceivedDateInput(row, $event)"
                                @blur="onReceivedDateBlur(row)"
                            >
                            <span v-else-if="!resolvedOrderId" class="text-xs text-zinc-500">Сохраните заказ</span>
                            <span v-else>{{ localReceivedDates[row.track_field] || '—' }}</span>
                            <p v-if="trackSaveErrors[row.track_field]" class="mt-1 text-xs text-rose-600">
                                {{ trackSaveErrors[row.track_field] }}
                            </p>
                        </template>
                        <span v-else>—</span>
                    </td>
                    <td v-if="showEdoColumn" class="px-3 py-2.5 text-zinc-500 dark:text-zinc-400">
                        <template v-if="rowHasClosingEdoControls(row)">
                            <label v-if="canEditEdoForRow(row)" class="inline-flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 dark:border-zinc-600"
                                    :checked="localEdoState[edoRowKey(row)]?.received_via_edo"
                                    :disabled="savingEdoKeys[edoRowKey(row)]"
                                    @change="onEdoToggle(row, $event)"
                                >
                                <span class="text-xs">{{ edoToggleLabel(row) }}</span>
                            </label>
                            <span
                                v-else-if="row.uploaded_file_preview_url"
                                class="text-xs text-zinc-400"
                                title="Для прикреплённого скана ЭДО не требуется"
                            >
                                скан
                            </span>
                            <span
                                v-else-if="localEdoState[edoRowKey(row)]?.received_via_edo || row.edo_acknowledgement?.received_via_edo"
                                class="text-xs font-medium text-emerald-700 dark:text-emerald-300"
                            >
                                {{ edoToggleLabel(row) }}
                            </span>
                            <span v-else class="text-xs text-zinc-400">—</span>
                            <p v-if="edoSaveErrors[edoRowKey(row)]" class="mt-1 text-xs text-rose-600">
                                {{ edoSaveErrors[edoRowKey(row)] }}
                            </p>
                        </template>
                        <span v-else class="text-xs text-zinc-400">—</span>
                    </td>
                    <td class="max-w-[200px] px-3 py-2.5 text-zinc-500 dark:text-zinc-400">
                        <a
                            v-if="row.uploaded_file_preview_url"
                            :href="row.uploaded_file_preview_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex max-w-full items-center gap-1 truncate text-sky-700 underline dark:text-sky-300"
                        >
                            <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                            <span class="truncate">{{ row.original_name || 'Открыть' }}</span>
                        </a>
                        <span v-else>—</span>
                    </td>
                    <td v-if="canEdit" class="px-3 py-2.5 text-right">
                        <button
                            v-if="row.id && !row.is_placeholder"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                            :disabled="deletingId === row.id"
                            @click="emit('delete', row)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            Удалить
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

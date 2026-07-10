<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import * as ps from '../../../support/orderPaymentScheduleUi.js';
import {
    crmBtnSecondary,
    crmField,
    crmFieldFluid,
    crmPanel,
    crmSegmented,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
} from '@/support/crmUi.js';

const props = defineProps({
    /** Объект графика (мутируется по месту, как в мастере). */
    schedule: { type: Object, required: true },
    totalAmount: { type: [Number, String], default: 0 },
    currency: { type: String, default: 'RUB' },
    routePoints: { type: Array, default: () => [] },
    orderDate: { type: String, default: '' },
    editableSummary: { type: Boolean, default: false },
});

const summaryText = defineModel('summaryText', { type: String, default: '' });

const summaryEditedManually = ref(false);
/** 'single' | 'multiple' */
const installmentLayoutMode = ref('single');
const isSyncing = ref(false);

const autoSummary = computed(() =>
    ps.paymentScheduleSummaryHuman(props.schedule, Number(props.totalAmount || 0), props.currency, props.routePoints, props.orderDate),
);

const canAddInstallment = computed(() => (props.schedule.installments?.length ?? 0) < ps.MAX_INSTALLMENTS);

function syncManualSummaryFlagFromText() {
    const current = String(summaryText.value ?? '').trim();
    const auto = String(autoSummary.value ?? '').trim();

    summaryEditedManually.value = current !== '' && current !== auto;
}

function pushAutoSummary(force = false) {
    if (!props.editableSummary) {
        return;
    }

    const val = autoSummary.value;
    if (force || !summaryEditedManually.value) {
        if (summaryText.value !== val) {
            summaryText.value = val;
        }
    }
}

function onSummaryTextInput() {
    summaryEditedManually.value = true;
}

function applyScheduleShape() {
    ps.applyInstallmentScheduleInPlace(props.schedule);
    installmentLayoutMode.value = (props.schedule.installments?.length ?? 0) > 1 ? 'multiple' : 'single';
}

function syncAmountsAndSummary() {
    if (isSyncing.value) {
        return;
    }

    isSyncing.value = true;
    try {
        ps.syncInstallmentAmountsFromPercents(props.schedule, Number(props.totalAmount || 0));
        pushAutoSummary(false);
    } finally {
        isSyncing.value = false;
    }
}

onMounted(() => {
    applyScheduleShape();
    syncManualSummaryFlagFromText();

    if (isSyncing.value) {
        return;
    }

    isSyncing.value = true;
    try {
        ps.syncInstallmentAmountsFromPercents(props.schedule, Number(props.totalAmount || 0));
        pushAutoSummary(false);
    } finally {
        isSyncing.value = false;
    }
});

watch(summaryText, () => {
    syncManualSummaryFlagFromText();
});

watch(
    () => props.schedule,
    (schedule, previousSchedule) => {
        if (schedule !== previousSchedule) {
            applyScheduleShape();
            syncAmountsAndSummary();
        }
    },
);

watch(autoSummary, () => {
    pushAutoSummary(false);
});

watch(() => Number(props.totalAmount || 0), () => {
    syncAmountsAndSummary();
});

watch(
    () => [props.orderDate, props.routePoints],
    () => {
        pushAutoSummary(false);
    },
    { deep: true },
);

function setInstallmentLayoutMode(mode) {
    summaryEditedManually.value = false;
    installmentLayoutMode.value = mode;

    if (mode === 'single') {
        ps.ensureInstallmentSchedule(props.schedule);
        props.schedule.installments = [ps.blankInstallmentRow({ percent: 100 })];
    } else if ((props.schedule.installments?.length ?? 0) < 2) {
        ps.addInstallmentRow(props.schedule);
    }

    ps.syncInstallmentAmountsFromPercents(props.schedule, Number(props.totalAmount || 0));
    pushAutoSummary(true);
}

function addInstallment() {
    summaryEditedManually.value = false;
    installmentLayoutMode.value = 'multiple';
    ps.addInstallmentRow(props.schedule);
    ps.syncInstallmentAmountsFromPercents(props.schedule, Number(props.totalAmount || 0));
    pushAutoSummary(true);
}

function removeInstallment(index) {
    summaryEditedManually.value = false;
    ps.removeInstallmentRow(props.schedule, index);
    if ((props.schedule.installments?.length ?? 0) <= 1) {
        installmentLayoutMode.value = 'single';
    }
    ps.syncInstallmentAmountsFromPercents(props.schedule, Number(props.totalAmount || 0));
    pushAutoSummary(true);
}

function onInstallmentPercentInput(index) {
    ps.rebalanceInstallmentPercents(props.schedule, index);
    ps.syncInstallmentAmountsFromPercents(props.schedule, Number(props.totalAmount || 0));
}

function onInstallmentAmountInput(index) {
    ps.rebalanceInstallmentAmounts(props.schedule, index, Number(props.totalAmount || 0));
}
</script>

<template>
    <div :class="`${crmPanel} space-y-2.5 p-3`">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Сроки и условия оплаты</div>
        </div>

        <div :class="crmSegmented">
            <button
                type="button"
                :class="installmentLayoutMode === 'single' ? crmSegmentedBtnActive : crmSegmentedBtn"
                class="text-xs"
                @click="setInstallmentLayoutMode('single')"
            >
                Один транш
            </button>
            <button
                type="button"
                :class="installmentLayoutMode === 'multiple' ? crmSegmentedBtnActive : crmSegmentedBtn"
                class="text-xs"
                @click="setInstallmentLayoutMode('multiple')"
            >
                Несколько траншей
            </button>
        </div>

        <div class="space-y-2">
            <div
                v-for="(inst, instIndex) in schedule.installments"
                :key="'inst-' + instIndex"
                class="rounded-lg border border-zinc-200 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-950"
            >
                <div class="mb-1.5 flex items-center justify-between gap-2">
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Транш {{ instIndex + 1 }}</div>
                    <button
                        v-if="schedule.installments.length > 1"
                        type="button"
                        class="text-[10px] text-red-600 hover:underline dark:text-red-400"
                        @click="removeInstallment(instIndex)"
                    >
                        Удалить
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-x-1.5 gap-y-1 sm:grid-cols-3 xl:grid-cols-6 xl:items-end">
                    <div class="min-w-0 space-y-0.5">
                        <label class="block text-[10px] font-medium leading-tight text-zinc-600 dark:text-zinc-400">%, доля</label>
                        <input
                            v-model.number="inst.percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            :class="`${crmField} h-7 min-w-0 px-1 py-0.5 text-center text-[11px] tabular-nums`"
                            :disabled="schedule.installments.length === 1"
                            @input="onInstallmentPercentInput(instIndex)"
                        />
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <label class="block text-[10px] font-medium leading-tight text-zinc-600 dark:text-zinc-400">Сумма</label>
                        <input
                            v-model.number="inst.amount"
                            type="number"
                            min="0"
                            step="0.01"
                            :class="`${crmField} h-7 min-w-0 px-1 py-0.5 text-center text-[11px] tabular-nums`"
                            :disabled="schedule.installments.length === 1"
                            @change="onInstallmentAmountInput(instIndex)"
                        />
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <label class="block text-[10px] font-medium leading-tight text-zinc-600 dark:text-zinc-400">Сдвиг, дн</label>
                        <input
                            v-model.number="inst.offset_days"
                            type="number"
                            min="-730"
                            max="730"
                            step="1"
                            :class="`${crmField} h-7 min-w-0 px-1 py-0.5 text-center text-[11px] tabular-nums`"
                        />
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <label class="block text-[10px] font-medium leading-tight text-zinc-600 dark:text-zinc-400">Тип дней</label>
                        <select v-model="inst.offset_unit" :class="`${crmField} h-7 min-w-0 px-1 text-[10px]`">
                            <option value="calendar_days">кал.</option>
                            <option value="bank_days">банк.</option>
                        </select>
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <label class="block text-[10px] font-medium leading-tight text-zinc-600 dark:text-zinc-400">Якорь</label>
                        <select v-model="inst.anchor" :class="`${crmField} h-7 min-w-0 px-1 text-[10px]`">
                            <option v-for="opt in ps.PAYMENT_ANCHOR_OPTIONS" :key="`anchor-${opt.value}`" :value="opt.value">
                                {{ opt.shortLabel || opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <label class="block text-[10px] font-medium leading-tight text-zinc-600 dark:text-zinc-400">Событие</label>
                        <select v-model="inst.basis" :class="`${crmField} h-7 min-w-0 px-1 text-[10px]`">
                            <option v-for="option in ps.PAYMENT_BASIS_OPTIONS" :key="`i-${option.value}`" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="installmentLayoutMode === 'multiple'" class="flex flex-wrap gap-2">
            <button type="button" :class="crmBtnSecondary" :disabled="!canAddInstallment" @click="addInstallment">
                Добавить транш
            </button>
            <span v-if="!canAddInstallment" class="self-center text-xs text-zinc-500 dark:text-zinc-400">Не более {{ ps.MAX_INSTALLMENTS }} траншей</span>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-2.5 dark:border-zinc-700 dark:bg-zinc-950">
            <div class="text-[10px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Сводка для договора и печати</div>
            <textarea
                v-if="editableSummary"
                v-model="summaryText"
                rows="2"
                :maxlength="ps.PAYMENT_TERMS_SUMMARY_MAX_LENGTH"
                class="mt-1 w-full resize-y rounded-md border border-zinc-200 bg-white px-2 py-1.5 text-xs leading-snug text-zinc-800 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-100"
                @input="onSummaryTextInput"
            />
            <textarea
                v-else
                readonly
                tabindex="-1"
                rows="2"
                :value="autoSummary"
                class="mt-1 w-full resize-none cursor-default rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1.5 text-xs leading-snug text-zinc-800 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
            />
        </div>
    </div>
</template>

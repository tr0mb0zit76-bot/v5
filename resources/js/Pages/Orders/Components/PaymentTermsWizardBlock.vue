<script setup>
import { computed, ref, watch } from 'vue';
import * as ps from '../../../support/orderPaymentScheduleUi.js';

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

const termsMode = ref('standard');
/** 'single' | 'pair' — число траншей в режиме «подробно». */
const installmentPairMode = ref('pair');

const autoSummary = computed(() =>
    ps.paymentScheduleSummaryHuman(props.schedule, Number(props.totalAmount || 0), props.currency, props.routePoints, props.orderDate),
);

/** Подставляем автосводку в строку при любом изменении графика; первый прогон не затираем текст с сервера. */
watch(autoSummary, (val, oldVal) => {
    if (!props.editableSummary) {
        return;
    }
    if (oldVal === undefined) {
        if (!String(summaryText.value ?? '').trim()) {
            summaryText.value = val;
        }
        return;
    }
    summaryText.value = val;
});

function syncFromPropsSchedule() {
    if (ps.usesInstallments(props.schedule)) {
        termsMode.value = 'detailed';
        installmentPairMode.value = props.schedule.installments.length >= 2 ? 'pair' : 'single';
    } else {
        termsMode.value = 'standard';
    }
}

watch(
    () => props.schedule,
    () => {
        syncFromPropsSchedule();
    },
    { deep: true, immediate: true },
);

watch(
    () => [Number(props.totalAmount || 0), props.schedule, props.routePoints, props.orderDate],
    () => {
        if (ps.usesInstallments(props.schedule)) {
            ps.syncInstallmentAmountsFromPercents(props.schedule, Number(props.totalAmount || 0));
        }
    },
    { deep: true },
);

function setTermsMode(mode) {
    if (mode === 'standard') {
        termsMode.value = 'standard';
        ps.applyStandardScheduleShape(props.schedule);
        return;
    }
    if (termsMode.value !== 'detailed') {
        termsMode.value = 'detailed';
        applyDetailed();
    }
}

function setInstallmentPairMode(mode) {
    installmentPairMode.value = mode;
    if (termsMode.value === 'detailed') {
        applyDetailed();
    }
}

function applyDetailed() {
    ps.applyDetailedScheduleShape(props.schedule, installmentPairMode.value === 'pair');
    ps.syncInstallmentAmountsFromPercents(props.schedule, Number(props.totalAmount || 0));
}

function onInstallmentPercentInput(index) {
    const sch = props.schedule;
    if (!ps.usesInstallments(sch) || !Array.isArray(sch.installments) || sch.installments.length < 2) {
        ps.syncInstallmentAmountsFromPercents(sch, Number(props.totalAmount || 0));
        return;
    }
    if (index === 0) {
        const p1 = Math.min(100, Math.max(0, Number(sch.installments[0].percent || 0)));
        sch.installments[0].percent = p1;
        sch.installments[1].percent = Math.round((100 - p1) * 100) / 100;
    } else {
        const p2 = Math.min(100, Math.max(0, Number(sch.installments[1].percent || 0)));
        sch.installments[1].percent = p2;
        sch.installments[0].percent = Math.round((100 - p2) * 100) / 100;
    }
    ps.syncInstallmentAmountsFromPercents(sch, Number(props.totalAmount || 0));
}

function onInstallmentAmountInput(index) {
    const sch = props.schedule;
    if (!ps.usesInstallments(sch) || !Array.isArray(sch.installments) || sch.installments.length < 2) {
        ps.syncInstallmentAmountsFromPercents(sch, Number(props.totalAmount || 0));
        return;
    }
    const total = Number(props.totalAmount || 0);
    if (!total || total <= 0) {
        ps.syncInstallmentAmountsFromPercents(sch, total);
        return;
    }
    const amt = Math.min(total, Math.max(0, Number(sch.installments[index].amount || 0)));
    sch.installments[index].amount = Math.round(amt * 100) / 100;
    const other = index === 0 ? 1 : 0;
    sch.installments[other].amount = Math.round((total - sch.installments[index].amount) * 100) / 100;
    sch.installments[index].percent = Math.round((100 * sch.installments[index].amount) / total * 100) / 100;
    sch.installments[other].percent = Math.round((100 * sch.installments[other].amount) / total * 100) / 100;
}
</script>

<template>
    <div class="space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Сроки и условия оплаты</div>
        </div>

        <div class="flex flex-wrap gap-2 rounded-xl border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
            <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-xs font-medium"
                :class="termsMode === 'standard' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                @click="setTermsMode('standard')"
            >
                Условия стандарт
            </button>
            <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-xs font-medium"
                :class="termsMode === 'detailed' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                @click="setTermsMode('detailed')"
            >
                Условия подробно
            </button>
        </div>

        <div v-if="termsMode === 'detailed'" class="flex flex-wrap gap-2 rounded-xl border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
            <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-xs font-medium"
                :class="installmentPairMode === 'single' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                @click="setInstallmentPairMode('single')"
            >
                Один транш
            </button>
            <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-xs font-medium"
                :class="installmentPairMode === 'pair' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                @click="setInstallmentPairMode('pair')"
            >
                Два транша
            </button>
        </div>

        <div v-if="termsMode === 'standard'" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs text-zinc-500 dark:text-zinc-400">Сроки в календарных днях после выбранного базиса</div>
                <label class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap text-sm text-zinc-900 dark:text-zinc-100">
                    <input v-model="schedule.has_prepayment" type="checkbox" class="h-3.5 w-3.5 shrink-0 rounded border-zinc-300" />
                    Доля предоплаты
                </label>
            </div>
            <div v-if="!schedule.has_prepayment" class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(4.5rem,5.5rem)_minmax(0,1fr)] sm:items-end">
                <div class="min-w-0 space-y-1.5">
                    <label class="block text-sm font-medium leading-snug text-zinc-900 dark:text-zinc-100">Срок, дней</label>
                    <input
                        v-model="schedule.postpayment_days"
                        type="number"
                        min="0"
                        step="1"
                        class="w-full rounded-xl border border-zinc-200 bg-white px-2 py-2 text-center text-sm tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                    />
                </div>
                <div class="min-w-0 space-y-1.5">
                    <label class="block text-sm font-medium leading-snug text-zinc-900 dark:text-zinc-100">Оплата по</label>
                    <select v-model="schedule.postpayment_mode" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                        <option v-for="option in ps.PAYMENT_BASIS_OPTIONS" :key="`${option.value}-std`" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
            </div>
            <div v-else class="grid grid-cols-1 gap-4 xl:grid-cols-2 xl:items-end">
                <div class="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-3 md:items-end">
                    <div class="min-w-0 space-y-1.5">
                        <label class="block text-sm font-medium leading-snug text-zinc-900 dark:text-zinc-100">Предоплата, %</label>
                        <input
                            v-model="schedule.prepayment_ratio"
                            type="number"
                            min="1"
                            max="99"
                            step="1"
                            class="w-full rounded-xl border border-zinc-200 bg-white px-2 py-2 text-center text-sm tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                        />
                    </div>
                    <div class="min-w-0 space-y-1.5">
                        <label class="block text-sm font-medium leading-snug text-zinc-900 dark:text-zinc-100">Срок, дней</label>
                        <input
                            v-model="schedule.prepayment_days"
                            type="number"
                            min="0"
                            step="1"
                            class="w-full rounded-xl border border-zinc-200 bg-white px-2 py-2 text-center text-sm tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                        />
                    </div>
                    <div class="min-w-0 space-y-1.5">
                        <label class="block text-sm font-medium leading-snug text-zinc-900 dark:text-zinc-100">Оплата по</label>
                        <select v-model="schedule.prepayment_mode" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                            <option v-for="option in ps.PAYMENT_BASIS_OPTIONS" :key="`${option.value}-pre`" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="grid min-w-0 grid-cols-1 gap-3 border-t border-zinc-200 pt-3 md:grid-cols-3 md:items-end xl:border-l xl:border-t-0 xl:pl-4 xl:pt-0 dark:border-zinc-600">
                    <div class="min-w-0 space-y-1.5">
                        <label class="block text-sm font-medium leading-snug text-zinc-900 dark:text-zinc-100">Постоплата, %</label>
                        <input
                            :value="100 - Number(schedule.prepayment_ratio || 0)"
                            type="number"
                            disabled
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-100 px-2 py-2 text-center text-sm tabular-nums dark:border-zinc-700 dark:bg-zinc-800"
                        />
                    </div>
                    <div class="min-w-0 space-y-1.5">
                        <label class="block text-sm font-medium leading-snug text-zinc-900 dark:text-zinc-100">Срок, дней</label>
                        <input
                            v-model="schedule.postpayment_days"
                            type="number"
                            min="0"
                            step="1"
                            class="w-full rounded-xl border border-zinc-200 bg-white px-2 py-2 text-center text-sm tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                        />
                    </div>
                    <div class="min-w-0 space-y-1.5">
                        <label class="block text-sm font-medium leading-snug text-zinc-900 dark:text-zinc-100">Оплата по</label>
                        <select v-model="schedule.postpayment_mode" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                            <option v-for="option in ps.PAYMENT_BASIS_OPTIONS" :key="`${option.value}-post`" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="space-y-2">
            <div
                v-for="(inst, instIndex) in schedule.installments"
                :key="'inst-' + instIndex"
                class="rounded-lg border border-zinc-200 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-950"
            >
                <div class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Транш {{ instIndex + 1 }}</div>
                <div class="grid grid-cols-2 gap-x-1.5 gap-y-1 sm:grid-cols-3 xl:grid-cols-6 xl:items-end">
                    <div class="min-w-0 space-y-0.5">
                        <label class="block text-[10px] font-medium leading-tight text-zinc-600 dark:text-zinc-400">%, доля</label>
                        <input
                            v-model.number="inst.percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            class="h-7 w-full min-w-0 rounded-md border border-zinc-200 bg-white px-1 py-0.5 text-center text-[11px] tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
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
                            class="h-7 w-full min-w-0 rounded-md border border-zinc-200 bg-white px-1 py-0.5 text-center text-[11px] tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                            :disabled="schedule.installments.length === 1"
                            @change="onInstallmentAmountInput(instIndex)"
                        />
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <label class="block text-[10px] font-medium leading-tight text-zinc-600 dark:text-zinc-400">Сдвиг</label>
                        <input
                            v-model.number="inst.offset_days"
                            type="number"
                            min="-730"
                            max="730"
                            step="1"
                            class="h-7 w-full min-w-0 rounded-md border border-zinc-200 bg-white px-1 py-0.5 text-center text-[11px] tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                        />
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <label class="block text-[10px] font-medium leading-tight text-zinc-600 dark:text-zinc-400">Дни</label>
                        <select v-model="inst.offset_unit" class="h-7 w-full min-w-0 rounded-md border border-zinc-200 bg-white px-1 text-[10px] dark:border-zinc-700 dark:bg-zinc-950">
                            <option value="calendar_days">кал.</option>
                            <option value="bank_days">банк.</option>
                        </select>
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <label class="block text-[10px] font-medium leading-tight text-zinc-600 dark:text-zinc-400">Якорь</label>
                        <select v-model="inst.anchor" class="h-7 w-full min-w-0 rounded-md border border-zinc-200 bg-white px-1 text-[10px] dark:border-zinc-700 dark:bg-zinc-950">
                            <option v-for="opt in ps.PAYMENT_ANCHOR_OPTIONS" :key="`anchor-${opt.value}`" :value="opt.value">
                                {{ opt.shortLabel || opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <label class="block text-[10px] font-medium leading-tight text-zinc-600 dark:text-zinc-400">Базис</label>
                        <select v-model="inst.basis" class="h-7 w-full min-w-0 rounded-md border border-zinc-200 bg-white px-1 text-[10px] dark:border-zinc-700 dark:bg-zinc-950">
                            <option v-for="option in ps.PAYMENT_BASIS_OPTIONS" :key="`i-${option.value}`" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-2.5 dark:border-zinc-700 dark:bg-zinc-950">
            <div class="text-[10px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Сводка для договора и печати</div>
            <textarea
                v-if="editableSummary"
                v-model="summaryText"
                rows="2"
                maxlength="255"
                class="mt-1 w-full resize-y rounded-md border border-zinc-200 bg-white px-2 py-1.5 text-xs leading-snug text-zinc-800 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-100"
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

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Minus, Plus, RefreshCw } from 'lucide-vue-next';
import {
    blankGoodsLine,
    blankServiceLine,
    calculateLeadPrecalculation,
    formatPrecalculationMoney,
    freightAllocationForLine,
    freightDistributionOptions,
    mergeServiceLinesFromPerformers,
    precalculationStatusOptions,
    searchLeadPrecalculationTnVed,
    stageLabel,
} from '@/support/leadWizardPrecalculation.js';
import { crmBtnSecondary, crmFieldFluid, crmLabel } from '@/support/crmUi.js';

const precalculation = defineModel('precalculation', { type: Object, required: true });

const props = defineProps({
    leadId: { type: [Number, null], default: null },
    performers: { type: Array, default: () => [] },
    importCostMeta: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['apply-finance']);

const calculating = ref(false);
const calculateError = ref('');
let calculateTimer = null;

const tnVedState = ref({});
const tnVedTimers = {};

const currencies = computed(() => props.importCostMeta?.currencies ?? []);
const disclaimer = computed(() => props.importCostMeta?.disclaimer ?? '');
const computedTotals = computed(() => precalculation.value?.computed ?? null);
const goodsLines = computed({
    get: () => precalculation.value?.goods_lines ?? [],
    set: (lines) => {
        precalculation.value = {
            ...precalculation.value,
            goods_lines: lines,
        };
    },
});
const serviceLines = computed({
    get: () => precalculation.value?.service_lines ?? [],
    set: (lines) => {
        precalculation.value = {
            ...precalculation.value,
            service_lines: lines,
        };
    },
});
const freight = computed({
    get: () => precalculation.value?.freight ?? { to_border_total: 0, after_border_total: 0, distribution_basis: 'invoice_rub' },
    set: (value) => {
        precalculation.value = {
            ...precalculation.value,
            freight: value,
        };
    },
});

function updateFreight(patch) {
    freight.value = { ...freight.value, ...patch };
    scheduleCalculate();
}

function updateStatus(status) {
    precalculation.value = {
        ...precalculation.value,
        status,
    };
}

function openPrecalculationDocument(format = 'html') {
    if (!props.leadId) {
        return;
    }

    const url = route('leads.precalculation.document', {
        lead: props.leadId,
        format,
        preview: 1,
    });

    window.open(url, '_blank', 'noopener,noreferrer');
}

function defaultCurrencyCode() {
    return currencies.value[0]?.code ?? 'USD';
}

function addGoodsLine() {
    goodsLines.value = [...goodsLines.value, blankGoodsLine(defaultCurrencyCode())];
    scheduleCalculate();
}

function removeGoodsLine(index) {
    goodsLines.value = goodsLines.value.filter((_, lineIndex) => lineIndex !== index);
    scheduleCalculate();
}

function addServiceLine(kind = 'other') {
    serviceLines.value = [...serviceLines.value, blankServiceLine(kind)];
    scheduleCalculate();
}

function removeServiceLine(index) {
    serviceLines.value = serviceLines.value.filter((_, lineIndex) => lineIndex !== index);
    scheduleCalculate();
}

function pullLogisticsFromLegs() {
    serviceLines.value = mergeServiceLinesFromPerformers(serviceLines.value, props.performers);
    scheduleCalculate();
}

function applyTotalsToFinance() {
    const grandTotal = computedTotals.value?.grand_total;
    if (grandTotal === null || grandTotal === undefined) {
        return;
    }

    emit('apply-finance', {
        target_price: grandTotal,
        calculated_cost: computedTotals.value?.services_total ?? null,
    });
}

function updateGoodsLine(index, patch) {
    const next = [...goodsLines.value];
    next[index] = { ...next[index], ...patch };
    goodsLines.value = next;
    scheduleCalculate();
}

function updateServiceLine(index, patch) {
    const next = [...serviceLines.value];
    next[index] = { ...next[index], ...patch };
    serviceLines.value = next;
    scheduleCalculate();
}

function scheduleCalculate() {
    clearTimeout(calculateTimer);
    calculateTimer = window.setTimeout(runCalculate, 500);
}

async function runCalculate() {
    if (goodsLines.value.length === 0 && serviceLines.value.length === 0) {
        precalculation.value = {
            ...precalculation.value,
            computed: null,
        };

        return;
    }

    calculating.value = true;
    calculateError.value = '';

    try {
        const result = await calculateLeadPrecalculation(precalculation.value);
        precalculation.value = result;
    } catch (error) {
        calculateError.value = error instanceof Error ? error.message : 'Не удалось выполнить расчёт.';
    } finally {
        calculating.value = false;
    }
}

function queueTnVedSearch(lineIndex) {
    clearTimeout(tnVedTimers[lineIndex]);
    const query = String(goodsLines.value[lineIndex]?.tn_ved_code ?? '').trim();

    if (query.length < 2) {
        tnVedState.value = {
            ...tnVedState.value,
            [lineIndex]: { open: false, loading: false, items: [] },
        };

        return;
    }

    tnVedState.value = {
        ...tnVedState.value,
        [lineIndex]: { open: true, loading: true, items: [] },
    };

    tnVedTimers[lineIndex] = window.setTimeout(async () => {
        try {
            const items = await searchLeadPrecalculationTnVed(query);
            tnVedState.value = {
                ...tnVedState.value,
                [lineIndex]: { open: true, loading: false, items },
            };
        } catch {
            tnVedState.value = {
                ...tnVedState.value,
                [lineIndex]: { open: true, loading: false, items: [] },
            };
        }
    }, 300);
}

function selectTnVed(lineIndex, item) {
    updateGoodsLine(lineIndex, {
        tn_ved_code: item.code,
        tn_ved_label: item.label,
    });

    tnVedState.value = {
        ...tnVedState.value,
        [lineIndex]: { open: false, loading: false, items: [] },
    };
}

function lineTotal(lineId) {
    const rows = computedTotals.value?.goods_lines ?? [];
    const row = rows.find((item) => item.line_id === lineId);

    return row?.summary?.total_landed ?? null;
}

function onDocumentClick(event) {
    if (!(event.target instanceof Element)) {
        return;
    }

    if (!event.target.closest('[data-precalc-tn-ved]')) {
        tnVedState.value = Object.fromEntries(
            Object.entries(tnVedState.value).map(([key, state]) => [key, { ...state, open: false }]),
        );
    }
}

watch(
    () => [goodsLines.value.length, serviceLines.value.length],
    () => scheduleCalculate(),
);

onMounted(() => {
    document.addEventListener('click', onDocumentClick);

    if (goodsLines.value.length === 0 && serviceLines.value.length === 0) {
        return;
    }

    scheduleCalculate();
});

onUnmounted(() => {
    clearTimeout(calculateTimer);
    Object.values(tnVedTimers).forEach((timer) => clearTimeout(timer));
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold">Предрасчёт</h3>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Коммерческий ориентир: товары по ТН ВЭД и услуги (логистика с плеч). Не заменяет таможенную декларацию.
                </p>
                <p v-if="disclaimer" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ disclaimer }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-if="leadId"
                    type="button"
                    :class="crmBtnSecondary"
                    @click="openPrecalculationDocument('html')"
                >
                    Предпросмотр HTML
                </button>
                <button
                    v-if="leadId"
                    type="button"
                    :class="crmBtnSecondary"
                    @click="openPrecalculationDocument('pdf')"
                >
                    PDF
                </button>
                <button type="button" :class="crmBtnSecondary" :disabled="calculating" @click="runCalculate">
                    <RefreshCw class="h-4 w-4" :class="calculating ? 'animate-spin' : ''" />
                    Пересчитать
                </button>
                <button
                    type="button"
                    :class="crmBtnSecondary"
                    :disabled="!computedTotals?.grand_total"
                    @click="applyTotalsToFinance"
                >
                    Подставить в финансы
                </button>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-[12rem_minmax(0,1fr)] md:items-end">
            <div class="space-y-2">
                <label :class="crmLabel">Статус</label>
                <select
                    :value="precalculation.status"
                    :class="crmFieldFluid"
                    @change="updateStatus($event.target.value)"
                >
                    <option v-for="option in precalculationStatusOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>
        </div>

        <section class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-zinc-500">Фрахт на отправление</h4>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Общая сумма распределяется по товарным строкам автоматически при пересчёте.
            </p>
            <div class="grid gap-3 md:grid-cols-3">
                <div class="space-y-2">
                    <label :class="crmLabel">До границы, ₽</label>
                    <input
                        :value="freight.to_border_total"
                        type="number"
                        min="0"
                        :class="crmFieldFluid"
                        @input="updateFreight({ to_border_total: Number($event.target.value) || 0 })"
                    >
                </div>
                <div class="space-y-2">
                    <label :class="crmLabel">После выпуска, ₽</label>
                    <input
                        :value="freight.after_border_total"
                        type="number"
                        min="0"
                        :class="crmFieldFluid"
                        @input="updateFreight({ after_border_total: Number($event.target.value) || 0 })"
                    >
                </div>
                <div class="space-y-2">
                    <label :class="crmLabel">Распределение</label>
                    <select
                        :value="freight.distribution_basis"
                        :class="crmFieldFluid"
                        @change="updateFreight({ distribution_basis: $event.target.value })"
                    >
                        <option v-for="option in freightDistributionOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="text-xs uppercase tracking-wide text-zinc-500">Товары + таможня</div>
                <div class="mt-1 text-xl font-semibold">{{ formatPrecalculationMoney(computedTotals?.goods_total) }}</div>
            </div>
            <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="text-xs uppercase tracking-wide text-zinc-500">Услуги</div>
                <div class="mt-1 text-xl font-semibold">{{ formatPrecalculationMoney(computedTotals?.services_total) }}</div>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-900 dark:bg-emerald-950/20">
                <div class="text-xs uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Итого клиенту</div>
                <div class="mt-1 text-xl font-semibold text-emerald-800 dark:text-emerald-200">
                    {{ formatPrecalculationMoney(computedTotals?.grand_total) }}
                </div>
            </div>
        </div>

        <p v-if="calculateError" class="text-sm text-rose-600">{{ calculateError }}</p>
        <ul v-if="computedTotals?.warnings?.length" class="space-y-1 text-sm text-amber-700 dark:text-amber-300">
            <li v-for="warning in computedTotals.warnings" :key="warning">• {{ warning }}</li>
        </ul>

        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-zinc-500">Товарные строки</h4>
                <button type="button" :class="crmBtnSecondary" @click="addGoodsLine">
                    <Plus class="h-4 w-4" />
                    Добавить товар
                </button>
            </div>

            <p v-if="goodsLines.length === 0" class="rounded-2xl border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                Добавьте строки с кодом ТН ВЭД и инвойсом — расчёт пошлины, НДС и утильсбора возьмём из модуля «Растаможка».
            </p>

            <article
                v-for="(line, index) in goodsLines"
                :key="line.id"
                class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="font-medium">Строка {{ index + 1 }}</div>
                        <div v-if="lineTotal(line.id) !== null" class="text-sm text-zinc-500">
                            Итого по строке: {{ formatPrecalculationMoney(lineTotal(line.id)) }}
                        </div>
                        <div
                            v-if="freightAllocationForLine(computedTotals, line.id)"
                            class="text-xs text-zinc-500 dark:text-zinc-400"
                        >
                            Фрахт: до границы {{ formatPrecalculationMoney(freightAllocationForLine(computedTotals, line.id)?.freight_to_border) }},
                            после выпуска {{ formatPrecalculationMoney(freightAllocationForLine(computedTotals, line.id)?.freight_after_border) }}
                        </div>
                    </div>
                    <button type="button" class="rounded-lg border border-zinc-200 p-2 text-zinc-500 hover:bg-zinc-50 dark:border-zinc-700" @click="removeGoodsLine(index)">
                        <Minus class="h-4 w-4" />
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-2">
                        <label :class="crmLabel">Описание</label>
                        <input
                            :value="line.description"
                            type="text"
                            :class="crmFieldFluid"
                            placeholder="Погрузчик, запчасти…"
                            @input="updateGoodsLine(index, { description: $event.target.value })"
                        >
                    </div>
                    <div class="space-y-2" data-precalc-tn-ved>
                        <label :class="crmLabel">Код ТН ВЭД</label>
                        <input
                            :value="line.tn_ved_code"
                            type="text"
                            :class="crmFieldFluid"
                            placeholder="8429.51"
                            @focus="queueTnVedSearch(index)"
                            @input="updateGoodsLine(index, { tn_ved_code: $event.target.value }); queueTnVedSearch(index)"
                        >
                        <div
                            v-if="tnVedState[index]?.open"
                            class="max-h-48 overflow-auto rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <p v-if="tnVedState[index]?.loading" class="px-4 py-3 text-sm text-zinc-500">Ищем коды…</p>
                            <button
                                v-for="item in tnVedState[index]?.items ?? []"
                                :key="item.code"
                                type="button"
                                class="flex w-full flex-col items-start border-b border-zinc-100 px-4 py-2 text-left text-sm last:border-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800"
                                @click="selectTnVed(index, item)"
                            >
                                <span class="font-medium">{{ item.code_display }} · {{ item.label }}</span>
                            </button>
                        </div>
                        <p v-if="line.tn_ved_label" class="text-xs text-zinc-500">{{ line.tn_ved_label }}</p>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-4">
                    <div class="space-y-2">
                        <label :class="crmLabel">Кол-во</label>
                        <input
                            :value="line.quantity"
                            type="number"
                            min="1"
                            :class="crmFieldFluid"
                            @input="updateGoodsLine(index, { quantity: Number($event.target.value) || 1 })"
                        >
                    </div>
                    <div class="space-y-2">
                        <label :class="crmLabel">Инвойс</label>
                        <input
                            :value="line.invoice_amount ?? ''"
                            type="number"
                            min="0"
                            step="0.01"
                            :class="crmFieldFluid"
                            @input="updateGoodsLine(index, { invoice_amount: $event.target.value === '' ? null : Number($event.target.value) })"
                        >
                    </div>
                    <div class="space-y-2">
                        <label :class="crmLabel">Валюта</label>
                        <select
                            :value="line.currency"
                            :class="crmFieldFluid"
                            @change="updateGoodsLine(index, { currency: $event.target.value })"
                        >
                            <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                                {{ currency.label }}
                            </option>
                        </select>
                    </div>
                    <div v-if="line.currency !== 'RUB'" class="space-y-2">
                        <label :class="crmLabel">Курс к ₽</label>
                        <input
                            :value="line.exchange_rate ?? ''"
                            type="number"
                            min="0"
                            step="0.0001"
                            :class="crmFieldFluid"
                            @input="updateGoodsLine(index, { exchange_rate: $event.target.value === '' ? null : Number($event.target.value) })"
                        >
                    </div>
                    <div class="space-y-2">
                        <label :class="crmLabel">Вес, кг</label>
                        <input
                            :value="line.weight_kg ?? ''"
                            type="number"
                            min="0"
                            step="0.01"
                            :class="crmFieldFluid"
                            @input="updateGoodsLine(index, { weight_kg: $event.target.value === '' ? null : Number($event.target.value) })"
                        >
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-2">
                        <label :class="crmLabel">Прочие расходы строки, ₽</label>
                        <input
                            :value="line.other_costs"
                            type="number"
                            min="0"
                            :class="crmFieldFluid"
                            @input="updateGoodsLine(index, { other_costs: Number($event.target.value) || 0 })"
                        >
                    </div>
                    <div class="space-y-2">
                        <label :class="crmLabel">Возраст техники, лет</label>
                        <input
                            :value="line.vehicle_age_years"
                            type="number"
                            min="0"
                            :class="crmFieldFluid"
                            @input="updateGoodsLine(index, { vehicle_age_years: Number($event.target.value) || 0 })"
                        >
                    </div>
                </div>
            </article>
        </section>

        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-zinc-500">Услуги</h4>
                <div class="flex flex-wrap gap-2">
                    <button type="button" :class="crmBtnSecondary" @click="pullLogisticsFromLegs">
                        С плеч маршрута
                    </button>
                    <button type="button" :class="crmBtnSecondary" @click="addServiceLine('logistics')">
                        <Plus class="h-4 w-4" />
                        Логистика
                    </button>
                    <button type="button" :class="crmBtnSecondary" @click="addServiceLine('other')">
                        <Plus class="h-4 w-4" />
                        Прочее
                    </button>
                </div>
            </div>

            <p v-if="serviceLines.length === 0" class="rounded-2xl border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                Добавьте логистику вручную или подтяните ориентиры ставок с вкладки «Маршрут».
            </p>

            <div
                v-for="(line, index) in serviceLines"
                :key="line.id"
                class="grid gap-3 rounded-2xl border border-zinc-200 p-4 md:grid-cols-[minmax(0,1fr)_10rem_10rem_auto] md:items-end dark:border-zinc-800"
            >
                <div class="space-y-2">
                    <label :class="crmLabel">Название</label>
                    <input
                        :value="line.title"
                        type="text"
                        :class="crmFieldFluid"
                        @input="updateServiceLine(index, { title: $event.target.value })"
                    >
                    <p v-if="line.stage" class="text-xs text-zinc-500">{{ stageLabel(line.stage) }}</p>
                </div>
                <div class="space-y-2">
                    <label :class="crmLabel">Сумма, ₽</label>
                    <input
                        :value="line.amount ?? ''"
                        type="number"
                        min="0"
                        :class="crmFieldFluid"
                        @input="updateServiceLine(index, { amount: $event.target.value === '' ? null : Number($event.target.value) })"
                    >
                </div>
                <div class="space-y-2">
                    <label :class="crmLabel">Тип</label>
                    <select
                        :value="line.kind"
                        :class="crmFieldFluid"
                        @change="updateServiceLine(index, { kind: $event.target.value })"
                    >
                        <option value="logistics">Логистика</option>
                        <option value="other">Прочее</option>
                    </select>
                </div>
                <button type="button" class="rounded-lg border border-zinc-200 p-2 text-zinc-500 hover:bg-zinc-50 dark:border-zinc-700" @click="removeServiceLine(index)">
                    <Minus class="h-4 w-4" />
                </button>
            </div>
        </section>
    </div>
</template>

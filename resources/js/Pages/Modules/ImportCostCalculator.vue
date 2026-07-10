<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <CrmPageHeader
            lead="Инвойс + код ТН ВЭД → полная стоимость с доставкой, пошлиной, НДС и утильсбором (самоходная техника)."
            title="Растаможка"
        />

        <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
            {{ disclaimer }}
        </p>
        <p v-if="referenceMetaLine" class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
            {{ referenceMetaLine }}
        </p>

        <div class="grid min-h-0 gap-4 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
            <section :class="`${crmPanel} space-y-5 p-5`">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Инвойсная стоимость</span>
                        <input
                            v-model="form.invoice_amount"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="100000"
                            @input="scheduleRecalculate"
                        >
                    </label>

                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Валюта инвойса</span>
                        <select
                            v-model="form.currency"
                            :class="crmFieldFluid"
                            @change="scheduleRecalculate"
                        >
                            <option
                                v-for="currency in currencies"
                                :key="currency.code"
                                :value="currency.code"
                            >
                                {{ currency.label }}
                            </option>
                        </select>
                    </label>
                </div>

                <label v-if="form.currency !== 'RUB'" :class="crmFilterField">
                    <span :class="crmLabelCompact">Курс к рублю</span>
                    <input
                        v-model="form.exchange_rate"
                        type="text"
                        inputmode="decimal"
                        autocomplete="off"
                        :class="crmFieldFluid"
                        placeholder="92.50"
                        @input="scheduleRecalculate"
                    >
                </label>

                <div class="space-y-2" data-tn-ved-picker>
                    <label :class="crmLabelCompact">Код ТН ВЭД</label>
                    <input
                        v-model="tnVedSearch"
                        type="text"
                        :class="crmFieldFluid"
                        placeholder="8429.52 или «погрузчик»"
                        @focus="tnVedDropdownOpen = true"
                        @input="onTnVedSearchInput"
                    >
                    <div
                        v-if="tnVedDropdownOpen && tnVedSearch.trim().length >= 2"
                        class="max-h-56 overflow-auto rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <p v-if="tnVedSearchLoading" class="px-4 py-3 text-sm text-zinc-500">Ищем коды…</p>
                        <p v-else-if="tnVedSearchResults.length === 0" class="px-4 py-3 text-sm text-zinc-500">Ничего не найдено</p>
                        <button
                            v-for="item in tnVedSearchResults"
                            :key="item.code"
                            type="button"
                            class="flex w-full flex-col items-start gap-0.5 border-b border-zinc-100 px-4 py-2.5 text-left text-sm last:border-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800"
                            @click="selectTnVed(item)"
                        >
                            <span class="font-medium">{{ item.code_display }} · {{ item.label }}</span>
                            <span class="text-xs text-zinc-500">
                                пошлина {{ item.duty_percent }}% · НДС {{ item.vat_percent }}%
                                <template v-if="item.duty_source_label"> · {{ item.duty_source_label }}</template>
                                <template v-if="item.requires_utilization_fee"> · утильсбор</template>
                            </span>
                            <span v-if="item.is_coarse" class="text-xs text-amber-600 dark:text-amber-400">
                                Укрупнённый код — уточните до 10 знаков
                            </span>
                        </button>
                    </div>
                    <p v-else-if="tnVedDropdownOpen" class="text-xs text-zinc-500">
                        Введите код или название (минимум 2 символа)
                    </p>
                    <p v-if="selectedTnVed" class="text-xs text-zinc-600 dark:text-zinc-300">
                        Выбрано: <span class="font-medium">{{ selectedTnVed.code_display }}</span> — {{ selectedTnVed.label }}
                        <template v-if="selectedTnVed.duty_source_label">
                            · ставка: {{ selectedTnVed.duty_source_label }}
                        </template>
                    </p>
                    <p v-if="selectedTnVed?.is_coarse" class="text-xs text-amber-700 dark:text-amber-300">
                        Выбран укрупнённый код ТН ВЭД — для точного расчёта уточните 10-значный код.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Доставка до границы, ₽</span>
                        <input
                            v-model="form.freight_to_border"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="0"
                            @input="scheduleRecalculate"
                        >
                    </label>

                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Доставка после выпуска, ₽</span>
                        <input
                            v-model="form.freight_after_border"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="0"
                            @input="scheduleRecalculate"
                        >
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Возраст техники, лет</span>
                        <input
                            v-model="form.vehicle_age_years"
                            type="number"
                            min="0"
                            max="50"
                            :class="crmFieldFluid"
                            @input="scheduleRecalculate"
                        >
                    </label>

                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Прочие расходы, ₽</span>
                        <input
                            v-model="form.other_costs"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :class="crmFieldFluid"
                            placeholder="брокер, СВХ"
                            @input="scheduleRecalculate"
                        >
                    </label>
                </div>

                <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <input
                        v-model="form.include_utilization_fee"
                        type="checkbox"
                        class="rounded border-zinc-300 dark:border-zinc-600"
                        @change="scheduleRecalculate"
                    >
                    Учитывать утильсбор
                </label>

                <details class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-800">
                    <summary class="cursor-pointer font-medium text-zinc-700 dark:text-zinc-300">
                        Переопределить ставки
                    </summary>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label :class="crmFilterField">
                            <span :class="crmLabelCompact">Пошлина, %</span>
                            <input
                                v-model="form.duty_percent_override"
                                type="text"
                                inputmode="decimal"
                                :class="crmFieldFluid"
                                :placeholder="selectedTnVed ? String(selectedTnVed.duty_percent) : 'авто'"
                                @input="scheduleRecalculate"
                            >
                        </label>
                        <label :class="crmFilterField">
                            <span :class="crmLabelCompact">НДС, %</span>
                            <input
                                v-model="form.vat_percent_override"
                                type="text"
                                inputmode="decimal"
                                :class="crmFieldFluid"
                                :placeholder="selectedTnVed ? String(selectedTnVed.vat_percent) : String(defaultVatPercent)"
                                @input="scheduleRecalculate"
                            >
                        </label>
                    </div>
                </details>
            </section>

            <section :class="`${crmPanel} space-y-4 p-5`">
                <h3 :class="crmSectionTitle">Итоговая стоимость</h3>

                <div v-if="loading" class="text-sm text-zinc-500">Считаем…</div>

                <div
                    v-else-if="result?.error"
                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-100"
                >
                    {{ result.error }}
                </div>

                <div
                    v-else-if="result?.warning"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    {{ result.warning }}
                </div>

                <template v-else-if="result?.summary">
                    <div class="text-3xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
                        {{ formatMoney(result.summary.total_landed) }}
                    </div>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Таможенная стоимость: {{ formatMoney(result.summary.customs_value) }}
                    </p>

                    <div class="space-y-2 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-800">
                        <div
                            v-for="row in result.breakdown"
                            :key="row.key"
                            class="flex items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <div class="text-zinc-700 dark:text-zinc-300">{{ row.label }}</div>
                                <div v-if="row.meta" class="text-xs text-zinc-500">{{ row.meta }}</div>
                            </div>
                            <span class="shrink-0 font-medium tabular-nums">{{ formatMoney(row.amount) }}</span>
                        </div>
                    </div>

                    <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                        {{ result.summary.disclaimer }}
                    </p>
                </template>

                <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">
                    Введите инвойс и выберите код ТН ВЭД.
                </p>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmFieldFluid,
    crmFilterField,
    crmLabelCompact,
    crmPanel,
    crmSectionTitle,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'modules', activeSubKey: 'modules-import-cost' }, () => page),
});

const props = defineProps({
    currencies: { type: Array, default: () => [] },
    disclaimer: { type: String, default: '' },
    defaultVatPercent: { type: Number, default: 22 },
    referenceMeta: { type: Object, default: () => ({}) },
});

const form = reactive({
    invoice_amount: '',
    currency: 'USD',
    exchange_rate: '',
    tn_ved_code: '',
    freight_to_border: '0',
    freight_after_border: '0',
    other_costs: '0',
    vehicle_age_years: '0',
    include_utilization_fee: true,
    duty_percent_override: '',
    vat_percent_override: '',
});

const tnVedSearch = ref('');
const tnVedDropdownOpen = ref(false);
const tnVedSearchResults = ref([]);
const tnVedSearchLoading = ref(false);
const selectedTnVed = ref(null);
const loading = ref(false);
const result = ref(null);
let debounceTimer = null;
let tnVedSearchTimer = null;

const referenceMetaLine = computed(() => {
    const alta = props.referenceMeta?.alta?.synced_at;
    const eec = props.referenceMeta?.eec?.synced_at;
    const kodtnved = props.referenceMeta?.kodtnved?.synced_at;
    const pp = props.referenceMeta?.pp1291?.synced_at;
    const ppFrom = props.referenceMeta?.pp1291?.effective_from;

    const parts = [];
    if (alta) {
        parts.push(`Alta: ${formatSyncDate(alta)}`);
    }
    if (eec) {
        parts.push(`ЕЭК: ${formatSyncDate(eec)}`);
    }
    if (kodtnved) {
        parts.push(`kodtnved.ru: ${formatSyncDate(kodtnved)}`);
    }
    if (pp) {
        parts.push(`ПП № 1291: ${formatSyncDate(pp)}${ppFrom ? ` (ред. ${ppFrom})` : ''}`);
    }

    return parts.length > 0 ? `Справочники: ${parts.join(' · ')}` : 'Справочники не синхронизированы — выполните php artisan import-cost:sync-references';
});

function formatSyncDate(iso) {
    if (!iso) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso));
    } catch {
        return iso;
    }
}


function onTnVedSearchInput() {
    tnVedDropdownOpen.value = true;
    form.tn_ved_code = '';
    selectedTnVed.value = null;
    scheduleTnVedSearch();
    scheduleRecalculate();
}

function scheduleTnVedSearch() {
    clearTimeout(tnVedSearchTimer);
    tnVedSearchTimer = setTimeout(searchTnVedCodes, 300);
}

async function searchTnVedCodes() {
    const query = tnVedSearch.value.trim();

    if (query.length < 2) {
        tnVedSearchResults.value = [];
        tnVedSearchLoading.value = false;

        return;
    }

    tnVedSearchLoading.value = true;

    try {
        const url = new URL(route('modules.import-cost.tn-ved.search'), window.location.origin);
        url.searchParams.set('q', query);

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            tnVedSearchResults.value = [];

            return;
        }

        const payload = await response.json();
        tnVedSearchResults.value = Array.isArray(payload.items) ? payload.items : [];
    } catch {
        tnVedSearchResults.value = [];
    } finally {
        tnVedSearchLoading.value = false;
    }
}

function selectTnVed(item) {
    form.tn_ved_code = item.code;
    selectedTnVed.value = item;
    tnVedSearch.value = `${item.code_display} · ${item.label}`;
    tnVedDropdownOpen.value = false;
    scheduleRecalculate();
}

function parseAmount(value) {
    const trimmed = String(value ?? '').trim().replace(/\s+/g, '').replace(',', '.');

    if (trimmed === '') {
        return null;
    }

    const numeric = Number(trimmed);

    if (!Number.isFinite(numeric) || numeric < 0) {
        return null;
    }

    return numeric;
}

function scheduleRecalculate() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(recalculate, 400);
}

async function recalculate() {
    const invoiceAmount = parseAmount(form.invoice_amount);

    if (!invoiceAmount || !form.tn_ved_code) {
        result.value = null;

        return;
    }

    loading.value = true;

    try {
        const payload = {
            invoice_amount: invoiceAmount,
            currency: form.currency,
            tn_ved_code: form.tn_ved_code,
            freight_to_border: parseAmount(form.freight_to_border) ?? 0,
            freight_after_border: parseAmount(form.freight_after_border) ?? 0,
            other_costs: parseAmount(form.other_costs) ?? 0,
            vehicle_age_years: Number.parseInt(String(form.vehicle_age_years || '0'), 10) || 0,
            include_utilization_fee: Boolean(form.include_utilization_fee),
        };

        if (form.currency !== 'RUB') {
            const rate = parseAmount(form.exchange_rate);
            if (rate === null) {
                result.value = { warning: 'Укажите курс валюты к рублю.' };
                loading.value = false;

                return;
            }
            payload.exchange_rate = rate;
        }

        const dutyOverride = parseAmount(form.duty_percent_override);
        if (dutyOverride !== null) {
            payload.duty_percent_override = dutyOverride;
        }

        const vatOverride = parseAmount(form.vat_percent_override);
        if (vatOverride !== null) {
            payload.vat_percent_override = vatOverride;
        }

        const response = await fetch(route('modules.import-cost.calculate'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            result.value = { error: 'Не удалось выполнить расчёт.' };

            return;
        }

        result.value = await response.json();
    } catch {
        result.value = { error: 'Не удалось выполнить расчёт.' };
    } finally {
        loading.value = false;
    }
}

function formatMoney(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }

    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 2,
    }).format(Number(value));
}

function onDocumentClick(event) {
    if (!(event.target instanceof Element)) {
        return;
    }

    if (!event.target.closest('[data-tn-ved-picker]')) {
        tnVedDropdownOpen.value = false;
    }
}

onMounted(() => {
    if (props.currencies.length > 0 && !props.currencies.some((c) => c.code === form.currency)) {
        form.currency = props.currencies[0].code;
    }

    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    clearTimeout(debounceTimer);
    clearTimeout(tnVedSearchTimer);
    document.removeEventListener('click', onDocumentClick);
});
</script>

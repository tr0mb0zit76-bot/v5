<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden">
        <div class="shrink-0 space-y-1">
            <Link
                href="/finance?section=cashflow&cashflow_tab=reconcile"
                class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            >
                <ArrowLeft class="h-4 w-4" />
                К разносу выписки
            </Link>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 space-y-0.5">
                    <h1 :class="crmPageTitle">Разнесение выписки</h1>
                    <p :class="crmPageLead">
                        {{ importData.file_name }} · {{ importData.bank_account?.bank_name }}
                        · {{ formatDate(importData.period_from) }} — {{ formatDate(importData.period_to) }}
                        · разнесено {{ importData.lines_allocated }} / {{ importData.lines_count }}
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-rose-200 px-3 py-1.5 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-300 dark:hover:bg-rose-950/30"
                    @click="deleteImport"
                >
                    Удалить выписку
                </button>
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap gap-2">
            <button
                v-for="filter in lineFilters"
                :key="filter.key"
                type="button"
                :class="lineFilter === filter.key ? crmSegmentedBtnActive : crmSegmentedBtn"
                @click="lineFilter = filter.key"
            >
                {{ filter.label }}
                <span v-if="filter.count > 0" class="ml-1 tabular-nums text-zinc-500">({{ filter.count }})</span>
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            <article
                v-for="line in filteredLines"
                :key="line.id"
                class="px-3 py-2.5"
                :class="line.status === 'allocated' ? 'bg-emerald-50/40 dark:bg-emerald-950/15' : ''"
            >
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="shrink-0 text-xs tabular-nums text-zinc-500">{{ formatDate(line.operation_date) }}</span>
                    <span
                        class="shrink-0 text-sm font-semibold tabular-nums"
                        :class="line.direction === 'in' ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                    >
                        {{ line.direction === 'in' ? '+' : '−' }}{{ formatMoney(line.amount) }}
                    </span>
                    <p
                        class="min-w-0 flex-1 truncate text-xs text-zinc-600 dark:text-zinc-400"
                        :title="line.description"
                    >
                        {{ line.description }}
                    </p>
                    <span
                        v-if="line.status === 'allocated'"
                        class="shrink-0 rounded border border-emerald-200 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-emerald-700 dark:border-emerald-800 dark:text-emerald-300"
                    >
                        Разнесено
                    </span>
                    <span
                        v-else-if="line.needs_manual_selection"
                        class="shrink-0 rounded border border-amber-200 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-800 dark:border-amber-800 dark:text-amber-300"
                    >
                        Выбор
                    </span>
                    <span
                        v-if="line.match_notes"
                        class="hidden shrink-0 text-[10px] text-amber-700 sm:inline dark:text-amber-300"
                    >
                        {{ line.match_notes }}
                        <span v-if="line.match_confidence">({{ line.match_confidence }}%)</span>
                    </span>
                </div>

                <p
                    v-if="line.match_notes"
                    class="mt-0.5 truncate text-[10px] text-amber-700 sm:hidden dark:text-amber-300"
                >
                    {{ line.match_notes }}
                    <span v-if="line.match_confidence">({{ line.match_confidence }}%)</span>
                </p>

                <div
                    v-if="line.status === 'allocated'"
                    class="mt-1.5 flex flex-wrap items-center justify-between gap-2 text-xs"
                >
                    <p class="min-w-0 truncate text-zinc-600 dark:text-zinc-300">
                        <template v-if="line.allocation_summary">
                            <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ allocationSummaryInline(line.allocation_summary) }}</span>
                            <span v-if="line.allocation_summary.splits?.length" class="block text-zinc-500">
                                <span
                                    v-for="split in line.allocation_summary.splits"
                                    :key="split.id"
                                    class="mr-2"
                                >
                                    #{{ split.payment_schedule_id }} · {{ formatMoney(split.amount) }}
                                </span>
                            </span>
                            <span v-if="line.allocation_summary.allocated_by_name" class="text-zinc-500">
                                · {{ line.allocation_summary.allocated_by_name }}
                            </span>
                        </template>
                        <template v-else>Операция разнесена</template>
                    </p>
                    <button
                        type="button"
                        class="shrink-0 rounded border border-zinc-200 px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        @click="deallocateLine(line)"
                    >
                        Исправить
                    </button>
                </div>

                <form
                    v-if="line.status !== 'allocated'"
                    class="mt-1.5 space-y-1.5"
                    @submit.prevent="allocateLine(line)"
                >
                    <div class="flex flex-wrap items-center gap-1.5">
                        <select
                            v-model="allocationForms[line.id].allocation_type"
                            class="rounded border border-zinc-300 bg-white px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-950"
                            @change="onAllocationTypeChange(line)"
                        >
                            <option value="operational">Операционный</option>
                            <option value="payroll">ФОТ</option>
                            <option value="category">Статья</option>
                        </select>

                        <template v-if="allocationForms[line.id].allocation_type === 'operational'">
                            <label class="flex items-center gap-1 text-[10px] text-zinc-600 dark:text-zinc-300">
                                <input
                                    v-model="splitModes[line.id]"
                                    type="checkbox"
                                    class="rounded border-zinc-300"
                                >
                                Несколько заявок
                            </label>

                            <template v-if="splitModes[line.id]">
                                <div
                                    v-for="(row, index) in splitAllocations[line.id]"
                                    :key="`${line.id}-split-${index}`"
                                    class="flex w-full flex-wrap items-center gap-1.5"
                                >
                                    <input
                                        v-model.number="row.payment_schedule_id"
                                        type="number"
                                        placeholder="ID графика"
                                        class="w-24 rounded border border-zinc-300 bg-white px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-950"
                                    >
                                    <input
                                        v-model.number="row.amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        placeholder="Сумма"
                                        class="w-28 rounded border border-zinc-300 bg-white px-2 py-1 text-xs tabular-nums dark:border-zinc-600 dark:bg-zinc-950"
                                    >
                                    <button
                                        v-if="splitAllocations[line.id].length > 2"
                                        type="button"
                                        class="rounded border border-zinc-200 px-2 py-1 text-xs text-zinc-600"
                                        @click="removeSplitRow(line, index)"
                                    >
                                        ×
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="rounded border border-zinc-200 px-2 py-1 text-xs text-zinc-700 dark:border-zinc-700 dark:text-zinc-300"
                                    @click="addSplitRow(line)"
                                >
                                    + строка
                                </button>
                                <span class="text-[10px] tabular-nums text-zinc-500">
                                    Σ {{ formatMoney(splitTotal(line)) }} / {{ formatMoney(line.amount) }}
                                </span>
                            </template>

                            <template v-else>
                            <input
                                v-model="searchQueries[line.id]"
                                type="search"
                                placeholder="Перевозчик / заявка"
                                class="min-w-[7rem] flex-1 rounded border border-zinc-300 bg-white px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-950"
                                @input="debouncedSearch(line)"
                            >
                            <button
                                type="button"
                                class="rounded border border-zinc-200 px-2 py-1 text-xs text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                :disabled="searchLoading[line.id]"
                                @click="searchCandidates(line)"
                            >
                                {{ searchLoading[line.id] ? '…' : 'Найти' }}
                            </button>
                            <select
                                v-if="displayCandidates(line).length > 0"
                                v-model="allocationForms[line.id].payment_schedule_id"
                                class="min-w-[10rem] max-w-full flex-[2] rounded border border-zinc-300 bg-white px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-950"
                            >
                                <option disabled value="">Строка графика</option>
                                <option
                                    v-for="candidate in displayCandidates(line)"
                                    :key="candidate.payment_schedule_id"
                                    :value="candidate.payment_schedule_id"
                                >
                                    {{ candidateOptionLabel(candidate) }}
                                </option>
                            </select>
                            <input
                                v-else
                                v-model.number="allocationForms[line.id].payment_schedule_id"
                                type="number"
                                placeholder="ID графика"
                                class="w-24 rounded border border-zinc-300 bg-white px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-950"
                            >
                            </template>
                        </template>

                        <template v-else-if="allocationForms[line.id].allocation_type === 'payroll'">
                            <select
                                v-model="allocationForms[line.id].category_id"
                                class="min-w-[8rem] flex-1 rounded border border-zinc-300 bg-white px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-950"
                            >
                                <option v-for="category in categoriesForType('payroll')" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                            <input
                                v-model.number="allocationForms[line.id].user_id"
                                type="number"
                                placeholder="ID сотр."
                                class="w-20 rounded border border-zinc-300 bg-white px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-950"
                            >
                        </template>

                        <template v-else>
                            <select
                                v-model="allocationForms[line.id].category_id"
                                class="min-w-[10rem] flex-1 rounded border border-zinc-300 bg-white px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-950"
                            >
                                <option v-for="category in categoriesForType('category')" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </template>

                        <button
                            type="submit"
                            class="shrink-0 rounded bg-sky-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-sky-700 disabled:opacity-50 dark:bg-sky-500 dark:hover:bg-sky-600"
                            :disabled="!canSubmit(line)"
                        >
                            Разнести
                        </button>
                    </div>

                    <p
                        v-if="displayCandidates(line).length > 0 && line.match_confidence < 70"
                        class="rounded border border-sky-200 bg-sky-50 px-2 py-1 text-[11px] text-sky-900 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-200"
                    >
                        Возможные совпадения — выберите строку графика или подтвердите предложенный вариант.
                    </p>

                    <p
                        v-if="allocationForms[line.id].allocation_type === 'operational' && displayCandidates(line).length === 0 && !searchLoading[line.id]"
                        class="truncate text-[10px] text-zinc-500 dark:text-zinc-400"
                    >
                        Совпадений нет — уточните поиск или укажите ID строки графика.
                    </p>
                </form>
            </article>

            <p v-if="filteredLines.length === 0" class="px-3 py-6 text-center text-sm text-zinc-500">
                Нет операций для выбранного фильтра.
            </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmPageLead,
    crmPageTitle,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) =>
        h(CrmLayout, { activeKey: 'finance', activeSubKey: 'finance-management-accounting', mainFill: true }, () => page),
});

const props = defineProps({
    import: { type: Object, required: true },
    lines: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ line_filter: null }) },
});

const importData = props.import;

function resolveInitialFilter() {
    const requested = props.filters?.line_filter;
    if (requested && ['pending', 'allocated', 'all'].includes(requested)) {
        return requested;
    }

    const pendingCount = props.lines.filter((line) => line.status !== 'allocated').length;
    const allocatedCount = props.lines.filter((line) => line.status === 'allocated').length;

    if (pendingCount === 0 && allocatedCount > 0) {
        return 'allocated';
    }

    return 'pending';
}

const lineFilter = ref(resolveInitialFilter());
const allocationForms = reactive({});
const splitModes = reactive({});
const splitAllocations = reactive({});
const searchQueries = reactive({});
const searchResults = reactive({});
const searchLoading = reactive({});
const debounceTimers = {};

const COST_CATEGORY_CODES = ['operational_carrier_out', 'cost_own_fleet'];

function defaultAllocationType(line) {
    if (line.match_type === 'operational' || line.direction === 'in' || line.direction === 'out') {
        return 'operational';
    }

    if (line.match_type === 'payroll') {
        return 'payroll';
    }

    const candidates = Array.isArray(line.operational_candidates) ? line.operational_candidates : [];

    if (candidates.length > 0) {
        return 'operational';
    }

    if (
        line.direction === 'in'
        && (line.needs_manual_selection || line.contractor_search_hint || line.suggested_payment_schedule)
    ) {
        return 'operational';
    }

    return 'category';
}

for (const line of props.lines) {
    const candidates = Array.isArray(line.operational_candidates) ? line.operational_candidates : [];
    const ambiguous = candidates.length > 1;
    const defaultScheduleId = ambiguous
        ? ''
        : (candidates[0]?.payment_schedule_id
            ?? line.suggested_payment_schedule?.id
            ?? '');

    allocationForms[line.id] = {
        allocation_type: defaultAllocationType(line),
        category_id: line.suggested_category?.id ?? props.categories[0]?.id ?? null,
        payment_schedule_id: defaultScheduleId,
        user_id: line.suggested_user?.id ?? null,
    };

    searchQueries[line.id] = line.contractor_search_hint ?? '';
    searchResults[line.id] = candidates;
    searchLoading[line.id] = false;
    splitModes[line.id] = false;
    splitAllocations[line.id] = [
        { payment_schedule_id: defaultScheduleId || null, amount: null },
        { payment_schedule_id: null, amount: null },
    ];
}

onMounted(() => {
    for (const line of props.lines) {
        if (line.status === 'allocated') {
            continue;
        }

        if (line.contractor_search_hint || line.needs_manual_selection || line.direction === 'in') {
            searchCandidates(line);
        }
    }
});

const lineFilters = computed(() => [
    { key: 'pending', label: 'Ожидают', count: props.lines.filter((line) => line.status !== 'allocated').length },
    { key: 'allocated', label: 'Разнесено', count: props.lines.filter((line) => line.status === 'allocated').length },
    { key: 'all', label: 'Все', count: props.lines.length },
]);

const filteredLines = computed(() => {
    const filter = lineFilter.value;

    if (filter === 'allocated') {
        return props.lines.filter((line) => line.status === 'allocated');
    }

    if (filter === 'all') {
        return props.lines;
    }

    return props.lines.filter((line) => line.status !== 'allocated');
});

function categoriesForType(allocationType) {
    if (allocationType === 'payroll') {
        return props.categories.filter((category) => String(category.kind ?? '').includes('payroll'));
    }

    return props.categories.filter((category) => !COST_CATEGORY_CODES.includes(category.code));
}

function displayCandidates(line) {
    const searched = searchResults[line.id];

    if (Array.isArray(searched) && searched.length > 0) {
        return searched;
    }

    return Array.isArray(line.operational_candidates) ? line.operational_candidates : [];
}

function onAllocationTypeChange(line) {
    if (allocationForms[line.id].allocation_type === 'operational' && !searchQueries[line.id]) {
        searchQueries[line.id] = line.contractor_search_hint ?? '';
        searchCandidates(line);
    }
}

function debouncedSearch(line) {
    clearTimeout(debounceTimers[line.id]);
    debounceTimers[line.id] = setTimeout(() => searchCandidates(line), 350);
}

async function searchCandidates(line) {
    searchLoading[line.id] = true;

    try {
        const params = new URLSearchParams();

        if (searchQueries[line.id]) {
            params.set('search', searchQueries[line.id]);
        }

        const response = await window.axios.get(
            `/finance/management-accounting/lines/${line.id}/operational-candidates?${params.toString()}`,
        );

        searchResults[line.id] = response.data?.candidates ?? [];

        if (
            searchResults[line.id].length === 1
            && !allocationForms[line.id].payment_schedule_id
        ) {
            allocationForms[line.id].payment_schedule_id = searchResults[line.id][0].payment_schedule_id;
        }
    } catch {
        searchResults[line.id] = line.operational_candidates ?? [];
    } finally {
        searchLoading[line.id] = false;
    }
}

function canSubmit(line) {
    const form = allocationForms[line.id];

    if (form.allocation_type === 'operational' && splitModes[line.id]) {
        const rows = splitAllocations[line.id] || [];
        const validRows = rows.filter((row) => row.payment_schedule_id && row.amount > 0);

        return validRows.length >= 2 && Math.abs(splitTotal(line) - Number(line.amount)) < 0.02;
    }

    if (form.allocation_type === 'operational') {
        return Boolean(form.payment_schedule_id);
    }

    if (form.allocation_type === 'payroll') {
        return Boolean(form.category_id && form.user_id);
    }

    return Boolean(form.category_id);
}

function candidateOptionLabel(candidate) {
    const order = candidate.order_number || `#${candidate.order_id}`;
    const plan = candidate.planned_date ? formatDate(candidate.planned_date) : '—';
    const due = formatMoney(candidate.amount_due ?? candidate.amount);
    const lineAmount = candidate.amount !== undefined && candidate.amount_due !== undefined
        && Math.abs(Number(candidate.amount_due) - Number(candidate.amount)) > 0.01
        ? ` (строка ${formatMoney(candidate.amount)})`
        : '';
    const contractor = candidate.contractor_label && candidate.contractor_label !== '—'
        ? `${candidate.contractor_label} · `
        : '';
    const reason = candidate.match_reason_label
        ? ` · ${candidate.match_reason_label}`
        : '';

    const slot = candidate.slot_label ? `${candidate.slot_label} · ` : '';

    return `${contractor}${slot}${order} · к оплате ${due}${lineAmount} · ${plan} · #${candidate.payment_schedule_id}${reason}`;
}

function allocateLine(line) {
    const form = allocationForms[line.id];

    if (form.allocation_type === 'operational' && splitModes[line.id]) {
        router.post(`/finance/management-accounting/lines/${line.id}/allocate`, {
            allocation_type: 'operational',
            allocations: (splitAllocations[line.id] || [])
                .filter((row) => row.payment_schedule_id && row.amount > 0)
                .map((row) => ({
                    payment_schedule_id: row.payment_schedule_id,
                    amount: row.amount,
                })),
        }, {
            preserveScroll: true,
        });

        return;
    }

    router.post(`/finance/management-accounting/lines/${line.id}/allocate`, form, {
        preserveScroll: true,
    });
}

function addSplitRow(line) {
    splitAllocations[line.id].push({ payment_schedule_id: null, amount: null });
}

function removeSplitRow(line, index) {
    splitAllocations[line.id].splice(index, 1);
}

function splitTotal(line) {
    return (splitAllocations[line.id] || []).reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
}

function deallocateLine(line) {
    if (!window.confirm('Отменить разнесение и выбрать другой вариант? Связанная оплата в графике будет снята.')) {
        return;
    }

    router.post(`/finance/management-accounting/lines/${line.id}/deallocate`, {}, {
        preserveScroll: true,
    });
}

function deleteImport() {
    const allocated = props.importData.lines_allocated ?? 0;

    const message = allocated > 0
        ? `Удалить выписку «${props.importData.file_name}» целиком?\n\nБудут отменены все ${allocated} разнесений и удалены все операции из файла. Это действие нельзя отменить.`
        : `Удалить выписку «${props.importData.file_name}» и все её операции? Это действие нельзя отменить.`;

    if (!window.confirm(message)) {
        return;
    }

    router.delete(`/finance/management-accounting/imports/${props.importData.id}`);
}

function allocationSummaryInline(summary) {
    if (summary.match_type === 'operational' || summary.match_type === 'operational_split') {
        const order = summary.order?.order_number;
        const schedule = summary.payment_schedule;
        const parts = ['Операционный'];

        if (order) {
            parts.push(order);
        }

        if (schedule) {
            parts.push(`#${schedule.id} · ${formatMoney(schedule.amount)}`);
        }

        if (summary.category?.name) {
            parts.push(summary.category.name);
        }

        return parts.join(' · ');
    }

    if (summary.match_type === 'payroll') {
        const user = summary.user?.name;

        return user ? `ФОТ · ${user}` : 'ФОТ';
    }

    return summary.category?.name ?? 'Статья расхоов';
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('ru-RU');
}
</script>

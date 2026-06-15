<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <div class="space-y-1">
            <Link
                href="/finance?section=cashflow&cashflow_tab=reconcile"
                class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            >
                <ArrowLeft class="h-4 w-4" />
                К разносу выписки
            </Link>
            <h1 :class="crmPageTitle">Разнесение выписки</h1>
            <p :class="crmPageLead">
                {{ importData.file_name }} · {{ importData.bank_account?.bank_name }}
                · {{ formatDate(importData.period_from) }} — {{ formatDate(importData.period_to) }}
                · разнесено {{ importData.lines_allocated }} / {{ importData.lines_count }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
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

        <div class="space-y-3">
            <article
                v-for="line in filteredLines"
                :key="line.id"
                :class="`${crmPanel} space-y-3 p-4 ${line.status === 'allocated' ? 'border-emerald-200 dark:border-emerald-900/50' : ''}`"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <div class="text-sm text-zinc-500">{{ formatDate(line.operation_date) }}</div>
                        <div class="font-medium" :class="line.direction === 'in' ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'">
                            {{ line.direction === 'in' ? '+' : '−' }}{{ formatMoney(line.amount) }}
                        </div>
                        <p class="text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ line.description }}</p>
                    </div>
                    <div
                        v-if="line.status === 'allocated'"
                        class="rounded border border-emerald-200 px-2 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-800 dark:text-emerald-300"
                    >
                        Разнесено
                    </div>
                    <div
                        v-else-if="line.needs_manual_selection"
                        class="rounded border border-amber-200 px-2 py-1 text-xs font-medium text-amber-800 dark:border-amber-800 dark:text-amber-300"
                    >
                        Нужен выбор
                    </div>
                </div>

                <div
                    v-if="line.status === 'allocated' && line.allocation_summary"
                    class="space-y-3 rounded-lg border border-emerald-200 bg-emerald-50/60 p-3 text-sm dark:border-emerald-900/40 dark:bg-emerald-950/20"
                >
                    <div class="font-medium text-zinc-800 dark:text-zinc-100">
                        {{ allocationSummaryTitle(line.allocation_summary) }}
                    </div>
                    <div v-if="line.allocation_summary.order" class="text-zinc-600 dark:text-zinc-300">
                        Заявка: <strong>{{ line.allocation_summary.order.order_number }}</strong>
                        <span v-if="line.allocation_summary.payment_schedule">
                            · график #{{ line.allocation_summary.payment_schedule.id }}
                            · {{ formatMoney(line.allocation_summary.payment_schedule.amount) }}
                        </span>
                    </div>
                    <div v-if="line.allocation_summary.category" class="text-zinc-600 dark:text-zinc-300">
                        Статья: {{ line.allocation_summary.category.name }}
                    </div>
                    <div v-if="line.allocation_summary.user" class="text-zinc-600 dark:text-zinc-300">
                        Сотрудник: {{ line.allocation_summary.user.name }}
                    </div>
                    <div v-if="line.allocation_summary.allocated_by_name" class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ line.allocation_summary.allocated_by_name }}
                        <span v-if="line.allocation_summary.allocated_at">
                            · {{ formatDateTime(line.allocation_summary.allocated_at) }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            :class="crmBtnPrimary"
                            @click="deallocateLine(line)"
                        >
                            Исправить разнесение
                        </button>
                    </div>
                </div>

                <div v-if="line.match_notes" class="text-xs text-amber-700 dark:text-amber-300">
                    {{ line.match_notes }}
                    <span v-if="line.match_confidence">({{ line.match_confidence }}%)</span>
                </div>

                <form
                    v-if="line.status !== 'allocated'"
                    class="space-y-3 rounded-lg border border-zinc-200 bg-zinc-50/80 p-3 dark:border-zinc-800 dark:bg-zinc-900/30"
                    @submit.prevent="allocateLine(line)"
                >
                    <div class="grid gap-2 md:grid-cols-4">
                        <select
                            v-model="allocationForms[line.id].allocation_type"
                            class="rounded-lg border border-zinc-300 px-2 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                            @change="onAllocationTypeChange(line)"
                        >
                            <option value="operational">Операционный / себестоимость</option>
                            <option value="payroll">ФОТ</option>
                            <option value="category">Статья расходов</option>
                        </select>
                        <select
                            v-if="allocationForms[line.id].allocation_type === 'category' || allocationForms[line.id].allocation_type === 'payroll'"
                            v-model="allocationForms[line.id].category_id"
                            class="rounded-lg border border-zinc-300 px-2 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                            <option v-for="category in categoriesForType(allocationForms[line.id].allocation_type)" :key="category.id" :value="category.id">
                                {{ category.name }}
                            </option>
                        </select>
                        <input
                            v-if="allocationForms[line.id].allocation_type === 'payroll'"
                            v-model.number="allocationForms[line.id].user_id"
                            type="number"
                            placeholder="ID сотрудника"
                            class="rounded-lg border border-zinc-300 px-2 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                    </div>

                    <div v-if="allocationForms[line.id].allocation_type === 'operational'" class="space-y-2">
                        <label class="block space-y-1 text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Поиск перевозчика / заявки в графике</span>
                            <div class="flex flex-wrap gap-2">
                                <input
                                    v-model="searchQueries[line.id]"
                                    type="search"
                                    placeholder="Например: Тандем, АС-2506-0201"
                                    class="min-w-[14rem] flex-1 rounded-lg border border-zinc-300 px-2 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                                    @input="debouncedSearch(line)"
                                >
                                <button
                                    type="button"
                                    :class="crmBtnNeutral"
                                    :disabled="searchLoading[line.id]"
                                    @click="searchCandidates(line)"
                                >
                                    {{ searchLoading[line.id] ? 'Поиск…' : 'Найти' }}
                                </button>
                            </div>
                        </label>

                        <div v-if="displayCandidates(line).length > 0" class="space-y-2">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                Выберите строку графика, куда разнести платёж:
                            </p>
                            <label
                                v-for="candidate in displayCandidates(line)"
                                :key="candidate.payment_schedule_id"
                                class="flex cursor-pointer gap-3 rounded-lg border px-3 py-2 text-sm transition"
                                :class="Number(allocationForms[line.id].payment_schedule_id) === Number(candidate.payment_schedule_id)
                                    ? 'border-sky-400 bg-sky-50 dark:border-sky-700 dark:bg-sky-950/30'
                                    : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600'"
                            >
                                <input
                                    v-model="allocationForms[line.id].payment_schedule_id"
                                    type="radio"
                                    class="mt-1"
                                    :value="candidate.payment_schedule_id"
                                >
                                <span class="min-w-0 flex-1">
                                    <span class="font-medium">{{ candidateOptionLabel(candidate) }}</span>
                                    <span
                                        v-if="candidate.match_reason_label"
                                        class="ml-2 text-xs text-zinc-500"
                                    >
                                        · {{ candidate.match_reason_label }}
                                    </span>
                                </span>
                            </label>
                        </div>

                        <p v-else class="text-xs text-zinc-500 dark:text-zinc-400">
                            Совпадений не найдено. Уточните название перевозчика или введите номер заявки.
                        </p>

                        <input
                            v-if="displayCandidates(line).length === 0"
                            v-model.number="allocationForms[line.id].payment_schedule_id"
                            type="number"
                            placeholder="Или укажите ID строки графика вручную"
                            class="w-full rounded-lg border border-zinc-300 px-2 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :class="crmBtnPrimary"
                            :disabled="!canSubmit(line)"
                        >
                            Подтвердить разнесение
                        </button>
                    </div>
                </form>
            </article>

            <p v-if="filteredLines.length === 0" class="py-8 text-center text-sm text-zinc-500">
                Нет операций для выбранного фильтра.
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmBtnNeutral,
    crmBtnPrimary,
    crmPageLead,
    crmPageTitle,
    crmPanel,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) =>
        h(CrmLayout, { activeKey: 'finance', activeSubKey: 'finance-management-accounting' }, () => page),
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
const searchQueries = reactive({});
const searchResults = reactive({});
const searchLoading = reactive({});
const debounceTimers = {};

const COST_CATEGORY_CODES = ['operational_carrier_out', 'cost_own_fleet'];

for (const line of props.lines) {
    const candidates = Array.isArray(line.operational_candidates) ? line.operational_candidates : [];
    const ambiguous = candidates.length > 1;
    const defaultScheduleId = ambiguous
        ? ''
        : (candidates[0]?.payment_schedule_id
            ?? line.suggested_payment_schedule?.id
            ?? '');

    allocationForms[line.id] = {
        allocation_type: line.match_type === 'operational' || line.direction === 'out'
            ? 'operational'
            : (line.match_type === 'payroll' ? 'payroll' : 'category'),
        category_id: line.suggested_category?.id ?? props.categories[0]?.id ?? null,
        payment_schedule_id: defaultScheduleId,
        user_id: line.suggested_user?.id ?? null,
    };

    searchQueries[line.id] = line.contractor_search_hint ?? '';
    searchResults[line.id] = candidates;
    searchLoading[line.id] = false;
}

onMounted(() => {
    for (const line of props.lines) {
        if (line.status === 'allocated') {
            continue;
        }

        if (line.contractor_search_hint || line.needs_manual_selection) {
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
    const plan = candidate.planned_date ? formatDate(candidate.planned_date) : 'без даты';
    const amount = formatMoney(candidate.amount);
    const contractor = candidate.contractor_label && candidate.contractor_label !== '—'
        ? `${candidate.contractor_label} · `
        : '';

    return `${contractor}${order} · ${amount} · план ${plan} · график #${candidate.payment_schedule_id}`;
}

function allocateLine(line) {
    router.post(`/finance/management-accounting/lines/${line.id}/allocate`, allocationForms[line.id], {
        preserveScroll: true,
    });
}

function deallocateLine(line) {
    if (!window.confirm('Отменить разнесение и выбрать другой вариант? Связанная оплата в графике будет снята.')) {
        return;
    }

    router.post(`/finance/management-accounting/lines/${line.id}/deallocate`, {}, {
        preserveScroll: true,
    });
}

function allocationSummaryTitle(summary) {
    if (summary.match_type === 'operational') {
        return 'Операционный платёж (себестоимость)';
    }

    if (summary.match_type === 'payroll') {
        return 'ФОТ';
    }

    return 'Статья расходов';
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('ru-RU');
}

function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('ru-RU');
}
</script>

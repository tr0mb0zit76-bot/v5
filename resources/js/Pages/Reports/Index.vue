<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto">
        <section :class="`${crmPanel} p-5`">
            <div>
                <h1 :class="crmPageTitle">Отчёты</h1>
            </div>

            <div class="mt-4 flex flex-wrap gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-700">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    :class="crmTabButtonClasses(tab === t.key)"
                    @click="switchTab(t.key)"
                >
                    {{ t.label }}
                </button>
            </div>

            <div
                class="mt-3 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="min-w-0 flex-1 space-y-2">
                    <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                        {{ glossaryBlock }}
                    </p>
                    <div v-if="usesPartyFilter" class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            :class="filterForm.party === 'customer' ? crmPillActive : crmPill"
                            @click="switchParty('customer')"
                        >
                            Заказчики
                        </button>
                        <button
                            type="button"
                            :class="filterForm.party === 'carrier' ? crmPillActive : crmPill"
                            @click="switchParty('carrier')"
                        >
                            Перевозчики
                        </button>
                    </div>
                    <div v-if="tab === 'managers'" class="flex flex-wrap gap-2">
                        <button
                            v-for="mode in managerModes"
                            :key="mode.key"
                            type="button"
                            :class="filterForm.managers_mode === mode.key ? crmPillActive : crmPill"
                            @click="switchManagersMode(mode.key)"
                        >
                            {{ mode.label }}
                        </button>
                    </div>
                </div>

                <form
                    v-if="usesDateRange"
                    class="crm-filter-bar shrink-0"
                    @submit.prevent="applyFilters"
                >
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">С</span>
                        <input
                            v-model="filterForm.date_from"
                            type="date"
                            :class="crmField"
                            :disabled="datesDisabled"
                        >
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">По</span>
                        <input
                            v-model="filterForm.date_to"
                            type="date"
                            :class="crmField"
                            :disabled="datesDisabled"
                        >
                    </label>
                    <button type="submit" :class="crmBtnPrimary">
                        Применить
                    </button>
                </form>

                <form
                    v-else-if="tab === 'lead-process'"
                    class="crm-filter-bar shrink-0"
                    @submit.prevent="applyLeadProcessFilters"
                >
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Порог «долго на этапе», дн.</span>
                        <input
                            v-model.number="filterForm.stuck_days"
                            type="number"
                            min="1"
                            max="365"
                            class="w-28"
                            :class="crmField"
                        >
                    </label>
                    <button type="submit" :class="crmBtnPrimary">
                        Применить
                    </button>
                </form>
            </div>

            <div
                v-if="tab === 'managers' && team_report"
                class="mt-4 space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"
            >
                <p
                    v-if="filterForm.managers_mode === 'snapshot'"
                    class="text-xs text-zinc-500 dark:text-zinc-400"
                >
                    Даты периода для снимка не используются.
                </p>
                <p
                    v-else-if="filterForm.managers_mode === 'compare' && team_report.compare_meta"
                    class="text-xs text-zinc-500 dark:text-zinc-400"
                >
                    Сравнение с {{ team_report.compare_meta.prev_from }} — {{ team_report.compare_meta.prev_to }}.
                </p>

                <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap">
                    <label :class="crmFilterField" class="min-w-[12rem]">
                        <span :class="crmLabelCompact">Подразделение</span>
                        <select v-model="filterForm.department_id" :class="crmField">
                            <option value="">Все в зоне видимости</option>
                            <option
                                v-for="dept in team_report.department_options || []"
                                :key="dept.id"
                                :value="String(dept.id)"
                            >
                                {{ dept.name }}
                            </option>
                        </select>
                    </label>

                    <label :class="`${crmFilterField} min-w-[14rem] flex-1`">
                        <span :class="crmLabelCompact">Менеджеры</span>
                        <select
                            v-model="filterForm.user_ids"
                            multiple
                            size="4"
                            :class="`${crmField} min-h-[5.5rem]`"
                        >
                            <option
                                v-for="manager in team_report.manager_options || []"
                                :key="manager.id"
                                :value="String(manager.id)"
                            >
                                {{ manager.name }}
                            </option>
                        </select>
                        <span class="text-[11px] text-zinc-500 dark:text-zinc-400">Ctrl/⌘ — несколько. Пусто = все в scope.</span>
                    </label>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="group in metricGroups"
                        :key="group.key"
                        type="button"
                        :class="selectedMetricGroups.includes(group.key) ? crmPillActive : crmPill"
                        @click="toggleMetricGroup(group.key)"
                    >
                        {{ group.label }}
                    </button>
                    <button type="button" :class="crmBtnPrimary" @click="applyFilters">
                        Обновить отчёт
                    </button>
                </div>
            </div>
        </section>

        <section v-if="tab === 'abc'" :class="`${crmPanel} overflow-x-auto`">
            <table class="min-w-full text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/50 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Класс</th>
                        <th class="px-4 py-3">{{ partyContractorLabel }}</th>
                        <th class="px-4 py-3 text-right">Заказов</th>
                        <th class="px-4 py-3 text-right">{{ partyAmountLabel }}</th>
                        <th class="px-4 py-3 text-right">Доля, %</th>
                        <th class="px-4 py-3 text-right">Накопл., %</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in abc.rows" :key="row.contractor_id" class="border-b border-zinc-100 dark:border-zinc-800">
                        <td class="px-4 py-2">
                            <span
                                class="inline-flex min-w-[1.5rem] items-center justify-center rounded border px-1.5 py-0.5 text-xs font-bold"
                                :class="abcBadgeClass(row.abc_class)"
                            >{{ row.abc_class }}</span>
                        </td>
                        <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100">{{ row.contractor_name }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ row.orders_count }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(row.revenue) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-600 dark:text-zinc-400">{{ row.share_percent.toFixed(2) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-600 dark:text-zinc-400">{{ row.cumulative_share_percent.toFixed(2) }}</td>
                    </tr>
                    <tr v-if="!abc.rows.length" class="text-zinc-500 dark:text-zinc-400">
                        <td colspan="6" class="px-4 py-6 text-center text-sm">Нет данных за период.</td>
                    </tr>
                </tbody>
                <tfoot v-if="abc.rows.length" class="border-t border-zinc-200 bg-zinc-50 font-semibold dark:border-zinc-700 dark:bg-zinc-950/40">
                    <tr>
                        <td colspan="3" class="px-4 py-2 text-zinc-700 dark:text-zinc-300">Итого</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(abc.total_revenue) }}</td>
                        <td colspan="2" class="px-4 py-2 text-right text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ abc.total_orders }} заказов</td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <section v-else-if="tab === 'xyz'" :class="`${crmPanel} overflow-x-auto`">
            <table class="min-w-full text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/50 dark:text-zinc-400">
                    <tr>
                        <th class="sticky left-0 z-10 bg-zinc-50 px-4 py-3 dark:bg-zinc-950/50">XYZ</th>
                        <th class="sticky left-12 z-10 bg-zinc-50 px-4 py-3 dark:bg-zinc-950/50">{{ partyContractorLabel }}</th>
                        <th v-for="m in xyz.months" :key="m" class="px-2 py-3 text-right">{{ m }}</th>
                        <th class="px-4 py-3 text-right">μ</th>
                        <th class="px-4 py-3 text-right">σ</th>
                        <th class="px-4 py-3 text-right">CV</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in xyz.rows" :key="row.contractor_id" class="border-b border-zinc-100 dark:border-zinc-800">
                        <td class="sticky left-0 z-10 bg-white px-4 py-2 dark:bg-zinc-900">
                            <span
                                class="inline-flex min-w-[1.5rem] items-center justify-center rounded border px-1.5 py-0.5 text-xs font-bold"
                                :class="xyzBadgeClass(row.xyz_class)"
                            >{{ row.xyz_class }}</span>
                        </td>
                        <td class="sticky left-12 z-10 bg-white px-4 py-2 font-medium text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">{{ row.contractor_name }}</td>
                        <td v-for="(v, idx) in row.monthly_revenues" :key="idx" class="px-2 py-2 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ formatMoney(v) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(row.mean) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatMoney(row.std_dev) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-zinc-600 dark:text-zinc-400">{{ row.cv === null ? '—' : row.cv.toFixed(3) }}</td>
                    </tr>
                    <tr v-if="!xyz.rows.length" class="text-zinc-500 dark:text-zinc-400">
                        <td :colspan="4 + (xyz.months?.length || 0)" class="px-4 py-6 text-center text-sm">Нет данных за выбранные месяцы.</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-else-if="tab === 'lead-process'" :class="`${crmPanel} overflow-x-auto`">
            <table class="min-w-full text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/50 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Лид</th>
                        <th class="px-4 py-3">Причина</th>
                        <th class="px-4 py-3">Процесс</th>
                        <th class="px-4 py-3">Этап</th>
                        <th class="px-4 py-3">Ответственный</th>
                        <th class="px-4 py-3 text-right">На этапе с</th>
                        <th class="px-4 py-3 text-right">Дней на этапе</th>
                        <th class="px-4 py-3 text-right">Срок этапа</th>
                        <th class="px-4 py-3 text-right">Просрочка, дн.</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in lead_process.rows"
                        :key="row.lead_id"
                        class="cursor-pointer border-b border-zinc-100 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50"
                        @click="openLead(row.lead_id)"
                    >
                        <td class="px-4 py-2">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ row.lead_number }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ row.lead_title }}</div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="label in row.issue_labels"
                                    :key="label"
                                    class="inline-flex rounded border px-1.5 py-0.5 text-[10px] font-medium"
                                    :class="issueLabelClass(label)"
                                >{{ label }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2">{{ row.process_name || '—' }}</td>
                        <td class="px-4 py-2">{{ row.stage_name || '—' }}</td>
                        <td class="px-4 py-2">{{ row.responsible_name || '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatDateTime(row.stage_entered_at) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ row.days_on_stage ?? '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatDateTime(row.stage_due_at) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-rose-600 dark:text-rose-400">{{ row.days_overdue ?? '—' }}</td>
                    </tr>
                    <tr v-if="!lead_process.rows.length" class="text-zinc-500 dark:text-zinc-400">
                        <td colspan="9" class="px-4 py-6 text-center text-sm">
                            {{ has_leads_access ? `Нет лидов с проблемой на этапе (порог «долго на этапе»: ${lead_process.stuck_days ?? filterForm.stuck_days} дн.).` : 'Нет доступа к разделу «Лиды».' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-else :class="`${crmPanel} overflow-x-auto`">
            <table class="min-w-full text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950/50 dark:text-zinc-400">
                    <tr>
                        <th class="sticky left-0 z-10 bg-zinc-50 px-4 py-3 dark:bg-zinc-950/50">Менеджер</th>
                        <th
                            v-for="col in teamColumns"
                            :key="col.key"
                            class="px-3 py-3 text-right"
                            :title="col.group"
                        >
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in teamRows"
                        :key="row.manager_id"
                        class="border-b border-zinc-100 dark:border-zinc-800"
                    >
                        <td class="sticky left-0 z-10 bg-white px-4 py-2 font-medium text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
                            {{ row.manager_name }}
                        </td>
                        <td
                            v-for="col in teamColumns"
                            :key="`${row.manager_id}-${col.key}`"
                            class="px-3 py-2 text-right tabular-nums text-zinc-700 dark:text-zinc-300"
                            :class="col.drilldown ? 'cursor-pointer hover:bg-sky-50 dark:hover:bg-sky-950/30' : ''"
                            @click="col.drilldown && openDrillDown(row, col)"
                        >
                            <template v-if="isCompareMode">
                                <div>{{ formatMetric(metricCell(row, col.key)?.value, col.format) }}</div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                    было {{ formatMetric(metricCell(row, col.key)?.prev_value, col.format) }}
                                    <span :class="deltaClass(metricCell(row, col.key)?.delta_pct)">
                                        {{ formatDeltaPct(metricCell(row, col.key)?.delta_pct) }}
                                    </span>
                                </div>
                            </template>
                            <template v-else>
                                {{ formatMetric(row.metrics?.[col.key], col.format) }}
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!teamRows.length" class="text-zinc-500 dark:text-zinc-400">
                        <td :colspan="1 + teamColumns.length" class="px-4 py-6 text-center text-sm">
                            Нет менеджеров в выбранном scope.
                        </td>
                    </tr>
                </tbody>
                <tfoot
                    v-if="teamRows.length && team_report?.totals"
                    class="border-t border-zinc-200 bg-zinc-50 font-semibold dark:border-zinc-700 dark:bg-zinc-950/40"
                >
                    <tr>
                        <td class="sticky left-0 z-10 bg-zinc-50 px-4 py-2 dark:bg-zinc-950/40">Итого</td>
                        <td
                            v-for="col in teamColumns"
                            :key="`total-${col.key}`"
                            class="px-3 py-2 text-right tabular-nums"
                        >
                            <template v-if="isCompareMode">
                                <div>{{ formatMetric(team_report.totals[col.key]?.value, col.format) }}</div>
                                <div class="text-[11px] font-normal text-zinc-500 dark:text-zinc-400">
                                    было {{ formatMetric(team_report.totals[col.key]?.prev_value, col.format) }}
                                    <span :class="deltaClass(team_report.totals[col.key]?.delta_pct)">
                                        {{ formatDeltaPct(team_report.totals[col.key]?.delta_pct) }}
                                    </span>
                                </div>
                            </template>
                            <template v-else>
                                {{ formatMetric(team_report.totals[col.key], col.format) }}
                            </template>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <Modal :show="drillDown.open" max-width="3xl" @close="closeDrillDown">
            <div class="space-y-3 p-5">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ drillDown.label || 'Расшифровка' }}
                    </h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ drillDown.manager_name }}
                        <span v-if="drillDown.loading"> · загружаем…</span>
                        <span v-else> · {{ drillDown.items.length }} записей</span>
                    </p>
                    <p v-if="drillDown.error" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ drillDown.error }}</p>
                </div>
                <div class="max-h-[24rem] overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-zinc-50 text-left text-xs uppercase text-zinc-500 dark:bg-zinc-950 dark:text-zinc-400">
                            <tr>
                                <th class="px-3 py-2">Номер</th>
                                <th class="px-3 py-2">Название</th>
                                <th class="px-3 py-2">Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in drillDown.items"
                                :key="`${drillDown.entity}-${item.id}`"
                                class="cursor-pointer border-t border-zinc-100 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/40"
                                @click="openDrillItem(item)"
                            >
                                <td class="px-3 py-2 font-medium text-sky-700 dark:text-sky-300">{{ item.number || item.id }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ item.title || '—' }}</td>
                                <td class="px-3 py-2 text-zinc-500 dark:text-zinc-400">{{ item.status || '—' }}</td>
                            </tr>
                            <tr v-if="!drillDown.loading && !drillDown.items.length">
                                <td colspan="3" class="px-3 py-6 text-center text-zinc-500">Нет записей</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end">
                    <button type="button" :class="crmBtnPrimary" @click="closeDrillDown">Закрыть</button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import Modal from '@/Components/Modal.vue';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';
import {
    crmBtnPrimary,
    crmField,
    crmFilterBar,
    crmFilterField,
    crmLabelCompact,
    crmPageTitle,
    crmPanel,
    crmPill,
    crmPillActive,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'reports', activeSubKey: 'reports-overview' }, () => page),
});

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    tab: { type: String, default: 'abc' },
    order_scope: { type: String, default: 'own' },
    leads_scope: { type: String, default: 'own' },
    has_leads_access: { type: Boolean, default: false },
    lead_process: { type: Object, default: () => ({ rows: [], stuck_days: 3 }) },
    abc: { type: Object, default: () => ({ rows: [], total_revenue: 0, total_orders: 0 }) },
    xyz: { type: Object, default: () => ({ rows: [], months: [] }) },
    managers: { type: Array, default: () => [] },
    team_report: { type: Object, default: null },
    glossary: { type: Object, default: () => ({}) },
});

const DEFAULT_STUCK_DAYS = 3;
const METRICS_STORAGE_PREFIX = 'reports_managers_metrics_';

const filterForm = reactive({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    party: props.filters.party === 'carrier' ? 'carrier' : 'customer',
    stuck_days: props.filters.stuck_days ?? props.lead_process?.stuck_days ?? DEFAULT_STUCK_DAYS,
    managers_mode: props.filters.managers_mode || 'period',
    department_id: props.filters.department_id ? String(props.filters.department_id) : '',
    user_ids: (props.filters.user_ids || []).map((id) => String(id)),
    metrics: [...(props.filters.metrics || [])],
});

const selectedMetricGroups = reactive([]);

const drillDown = reactive({
    open: false,
    loading: false,
    error: '',
    label: '',
    manager_name: '',
    entity: '',
    items: [],
});

const tabs = [
    { key: 'abc', label: 'ABC' },
    { key: 'xyz', label: 'XYZ' },
    { key: 'managers', label: 'Менеджеры' },
    { key: 'lead-process', label: 'Этапы лидов' },
];

const managerModes = [
    { key: 'period', label: 'Результаты периода' },
    { key: 'snapshot', label: 'Воронка сейчас' },
    { key: 'compare', label: 'Период к периоду' },
];

const usesDateRange = computed(() => ['abc', 'xyz', 'managers'].includes(props.tab));
const datesDisabled = computed(() => props.tab === 'managers' && filterForm.managers_mode === 'snapshot');
const usesPartyFilter = computed(() => ['abc', 'xyz'].includes(props.tab));
const isCarrierParty = computed(() => filterForm.party === 'carrier');
const partyContractorLabel = computed(() => (isCarrierParty.value ? 'Перевозчик' : 'Клиент'));
const partyAmountLabel = computed(() => (isCarrierParty.value ? 'Сумма ставок' : 'Выручка'));
const isCompareMode = computed(() => filterForm.managers_mode === 'compare');
const teamColumns = computed(() => props.team_report?.columns || []);
const teamRows = computed(() => props.team_report?.rows || []);
const metricGroups = computed(() => props.team_report?.metric_catalog?.groups || []);

const glossaryBlock = computed(() => {
    if (props.tab === 'xyz') {
        return isCarrierParty.value ? props.glossary.xyz_carrier : props.glossary.xyz_customer;
    }
    if (props.tab === 'managers') {
        return props.team_report?.glossary || props.glossary.managers;
    }
    if (props.tab === 'lead-process') {
        return props.glossary.lead_process;
    }

    return isCarrierParty.value ? props.glossary.abc_carrier : props.glossary.abc_customer;
});

function syncMetricGroupsFromFilters() {
    selectedMetricGroups.splice(0, selectedMetricGroups.length);
    const groups = metricGroups.value.map((g) => g.key);
    const metrics = filterForm.metrics || [];
    if (metrics.length === 0) {
        groups.forEach((key) => selectedMetricGroups.push(key));

        return;
    }

    groups.forEach((key) => {
        if (metrics.includes(key)) {
            selectedMetricGroups.push(key);
        }
    });

    if (selectedMetricGroups.length === 0 && groups.length) {
        groups.forEach((key) => selectedMetricGroups.push(key));
    }
}

watch(
    () => [props.tab, props.team_report?.mode, props.filters?.metrics],
    () => {
        if (props.tab === 'managers') {
            syncMetricGroupsFromFilters();
        }
    },
    { immediate: true },
);

function formatMoney(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function formatMetric(value, format) {
    if (value === null || value === undefined) {
        return '—';
    }

    if (format === 'money') {
        return formatMoney(value);
    }

    if (format === 'percent') {
        return `${Number(value).toFixed(1)}%`;
    }

    return new Intl.NumberFormat('ru-RU').format(Number(value || 0));
}

function metricCell(row, key) {
    const cell = row?.metrics?.[key];

    return cell && typeof cell === 'object' ? cell : null;
}

function formatDeltaPct(value) {
    if (value === null || value === undefined) {
        return 'н/д';
    }

    const sign = value > 0 ? '+' : '';

    return `(${sign}${Number(value).toFixed(1)}%)`;
}

function deltaClass(value) {
    if (value === null || value === undefined) {
        return 'text-zinc-400';
    }
    if (value > 0) {
        return 'text-emerald-600 dark:text-emerald-400';
    }
    if (value < 0) {
        return 'text-rose-600 dark:text-rose-400';
    }

    return 'text-zinc-500';
}

function abcBadgeClass(cls) {
    if (cls === 'A') {
        return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200';
    }
    if (cls === 'B') {
        return 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200';
    }

    return 'border-zinc-300 bg-zinc-100 text-zinc-800 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200';
}

function xyzBadgeClass(cls) {
    if (cls === 'X') {
        return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200';
    }
    if (cls === 'Y') {
        return 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200';
    }
    if (cls === 'Z') {
        return 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200';
    }

    return 'border-zinc-300 bg-zinc-100 text-zinc-600 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300';
}

function issueLabelClass(label) {
    if (label === 'Срок этапа') {
        return 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200';
    }

    return 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200';
}

function normalizeStuckDays(value) {
    const days = Number.parseInt(String(value ?? DEFAULT_STUCK_DAYS), 10);

    if (Number.isNaN(days)) {
        return DEFAULT_STUCK_DAYS;
    }

    return Math.min(365, Math.max(1, days));
}

function toggleMetricGroup(key) {
    const index = selectedMetricGroups.indexOf(key);
    if (index >= 0) {
        if (selectedMetricGroups.length === 1) {
            return;
        }
        selectedMetricGroups.splice(index, 1);
    } else {
        selectedMetricGroups.push(key);
    }

    try {
        localStorage.setItem(
            `${METRICS_STORAGE_PREFIX}${filterForm.managers_mode}`,
            JSON.stringify([...selectedMetricGroups]),
        );
    } catch {
        // ignore storage errors
    }
}

function loadStoredMetricGroups(mode) {
    try {
        const raw = localStorage.getItem(`${METRICS_STORAGE_PREFIX}${mode}`);
        if (!raw) {
            return null;
        }
        const parsed = JSON.parse(raw);

        return Array.isArray(parsed) ? parsed.map(String) : null;
    } catch {
        return null;
    }
}

function reportQueryParams(tab) {
    const params = {
        tab,
    };

    if (['abc', 'xyz', 'managers'].includes(tab)) {
        params.date_from = filterForm.date_from;
        params.date_to = filterForm.date_to;
    }

    if (['abc', 'xyz'].includes(tab)) {
        params.party = filterForm.party === 'carrier' ? 'carrier' : 'customer';
    }

    if (tab === 'lead-process') {
        params.stuck_days = normalizeStuckDays(filterForm.stuck_days);
    }

    if (tab === 'managers') {
        params.managers_mode = filterForm.managers_mode;
        if (filterForm.department_id) {
            params.department_id = Number(filterForm.department_id);
        }
        if (filterForm.user_ids?.length) {
            params.user_ids = filterForm.user_ids.map((id) => Number(id));
        }
        const groups = selectedMetricGroups.length
            ? [...selectedMetricGroups]
            : (loadStoredMetricGroups(filterForm.managers_mode) || []);
        if (groups.length) {
            params.metrics = groups;
        }
    }

    return params;
}

function visitReports(params, only = null) {
    const options = {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    };

    if (only) {
        options.only = only;
    }

    router.get(route('reports.index'), params, options);
}

function applyFilters() {
    const only = props.tab === 'managers'
        ? ['team_report', 'filters', 'glossary']
        : null;

    visitReports(reportQueryParams(props.tab), only);
}

function applyLeadProcessFilters() {
    filterForm.stuck_days = normalizeStuckDays(filterForm.stuck_days);

    visitReports(reportQueryParams('lead-process'), ['lead_process', 'filters', 'glossary']);
}

function switchTab(key) {
    visitReports(reportQueryParams(key));
}

function switchParty(party) {
    if (filterForm.party === party) {
        return;
    }

    filterForm.party = party;
    visitReports(reportQueryParams(props.tab));
}

function switchManagersMode(mode) {
    if (filterForm.managers_mode === mode) {
        return;
    }

    filterForm.managers_mode = mode;
    const stored = loadStoredMetricGroups(mode);
    selectedMetricGroups.splice(0, selectedMetricGroups.length);
    if (stored?.length) {
        stored.forEach((key) => selectedMetricGroups.push(key));
    }

    visitReports(reportQueryParams('managers'), ['team_report', 'filters', 'glossary']);
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function openLead(leadId) {
    if (!leadId) {
        return;
    }

    router.get(route('leads.show', leadId));
}

async function openDrillDown(row, col) {
    drillDown.open = true;
    drillDown.loading = true;
    drillDown.error = '';
    drillDown.label = col.label;
    drillDown.manager_name = row.manager_name;
    drillDown.entity = '';
    drillDown.items = [];

    try {
        const params = new URLSearchParams({
            managers_mode: filterForm.managers_mode,
            metric_key: col.key,
            manager_id: String(row.manager_id),
            date_from: filterForm.date_from,
            date_to: filterForm.date_to,
        });
        const response = await fetch(`${route('reports.managers.drill-down')}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(response.status === 403 ? 'Нет доступа' : 'Не удалось загрузить расшифровку');
        }

        const payload = await response.json();
        drillDown.label = payload.label || col.label;
        drillDown.manager_name = payload.manager_name || row.manager_name;
        drillDown.entity = payload.entity || '';
        drillDown.items = Array.isArray(payload.items) ? payload.items : [];
    } catch (error) {
        drillDown.error = error?.message || 'Ошибка загрузки';
    } finally {
        drillDown.loading = false;
    }
}

function closeDrillDown() {
    drillDown.open = false;
}

function openDrillItem(item) {
    if (!item?.href) {
        return;
    }

    router.visit(item.href);
}
</script>

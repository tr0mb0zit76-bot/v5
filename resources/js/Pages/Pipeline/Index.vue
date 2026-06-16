<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-hidden">
        <CrmPageHeader
            :lead="headerLead"
            title="Сквозной pipeline"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        :class="view === 'orders' ? crmBtnPrimary : crmBtnSecondaryOutline"
                        @click="switchView('orders')"
                    >
                        Заказы
                    </button>
                    <button
                        v-if="hasLeadsAccess"
                        type="button"
                        :class="view === 'leads' ? crmBtnPrimary : crmBtnSecondaryOutline"
                        @click="switchView('leads')"
                    >
                        Лиды
                    </button>
                    <select
                        v-if="view === 'leads' && processes.length > 1"
                        :value="leadProcessSlug"
                        class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                        @change="switchLeadProcess($event.target.value)"
                    >
                        <option
                            v-for="process in processes"
                            :key="process.slug"
                            :value="process.slug"
                        >
                            {{ process.name }}
                        </option>
                    </select>
                </div>
            </template>
        </CrmPageHeader>

        <p
            v-if="error"
            class="shrink-0 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200"
        >
            {{ error }}
        </p>

        <section
            v-if="kpi"
            class="crm-panel grid shrink-0 grid-cols-1 gap-3 p-4 sm:grid-cols-3"
        >
            <div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    Среднее лид → заказ · {{ kpiPeriodLabel }}
                </div>
                <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ formatDays(kpi.avg_lead_to_order_days) }}
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ kpi.linked_leads_count }} заказов с лидом
                </div>
            </div>
            <div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    Среднее лид → закрыт · {{ kpiPeriodLabel }}
                </div>
                <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ formatDays(kpi.avg_lead_to_closed_days) }}
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ kpi.closed_with_lead_count }} закрытых с лидом
                </div>
            </div>
            <div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    Просрочка графика оплат
                </div>
                <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ formatPercent(kpi.overdue_payments_percent) }}
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ kpi.orders_with_overdue_payments }} / {{ kpi.active_orders_count }} активных заказов
                </div>
            </div>
        </section>

        <div :class="[crmGridPanel, 'flex flex-col']">
            <div class="flex min-h-0 flex-1 overflow-x-auto overflow-y-hidden p-4">
                <div class="flex h-full min-h-0 gap-4 pb-2">
                    <div
                        v-for="column in columns"
                        :key="column.key"
                        class="flex h-full min-h-0 w-72 shrink-0 flex-col rounded-xl border border-zinc-200 bg-zinc-50 p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                        :class="{ 'ring-2 ring-sky-500/60': dragOverColumn === column.key }"
                        @dragover.prevent="onColumnDragOver(column)"
                        @dragleave="onColumnDragLeave(column.key)"
                        @drop.prevent="onColumnDrop(column)"
                    >
                        <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                            <span class="normal-case tracking-normal">{{ column.label }}</span>
                            <span>{{ column.cards?.length ?? 0 }}</span>
                        </div>

                        <div class="mt-3 min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                            <article
                                v-for="card in column.cards"
                                :key="`${card.type}-${card.id}`"
                                class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-950"
                                :class="leadDragEnabled ? 'cursor-grab' : ''"
                                :draggable="leadDragEnabled && card.type === 'lead'"
                                @dragstart="(event) => onCardDragStart(event, card)"
                                @dragend="onCardDragEnd"
                            >
                                <div class="text-xs font-semibold text-zinc-900 dark:text-zinc-50">
                                    {{ cardTitle(card) }}
                                </div>
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ cardSubtitle(card) }}
                                </div>
                                <ul
                                    v-if="card.blockers?.length"
                                    class="mt-2 space-y-1 text-[11px] text-amber-800 dark:text-amber-200"
                                >
                                    <li v-for="(blocker, index) in card.blockers" :key="index">
                                        {{ blocker }}
                                    </li>
                                </ul>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <Link
                                        :href="card.edit_url"
                                        class="text-[11px] font-semibold uppercase tracking-wide text-sky-700 underline dark:text-sky-300"
                                    >
                                        Открыть
                                    </Link>
                                    <form
                                        v-if="card.type === 'order' && canMarkAccountingHandoff && column.key === 'closed' && !card.accounting_handoff_at"
                                        :action="route('pipeline.orders.accounting-handoff', card.id)"
                                        method="post"
                                        class="inline"
                                    >
                                        <input type="hidden" name="_token" :value="csrfToken">
                                        <button
                                            type="submit"
                                            class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700 underline dark:text-emerald-300"
                                        >
                                            Принято бухгалтерией
                                        </button>
                                    </form>
                                </div>
                            </article>

                            <div
                                v-if="!column.cards?.length"
                                class="rounded-xl border border-dashed border-zinc-300 px-3 py-6 text-center text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
                            >
                                Пусто
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnPrimary, crmBtnSecondaryOutline, crmGridPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'planning', activeSubKey: 'pipeline', mainFill: true }, () => page),
});

const props = defineProps({
    view: { type: String, default: 'orders' },
    columns: { type: Array, default: () => [] },
    lead_process_slug: { type: String, default: 'transport-intake' },
    lead_process_name: { type: String, default: '' },
    processes: { type: Array, default: () => [] },
    can_mark_accounting_handoff: { type: Boolean, default: false },
    can_advance_lead_stage: { type: Boolean, default: false },
    kpi: { type: Object, default: null },
    error: { type: String, default: '' },
});

const page = usePage();
const csrfToken = computed(() => page.props.csrf_token ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '');
const hasLeadsAccess = computed(() => {
    const areas = page.props.auth?.user?.role?.visibility_areas ?? [];

    return areas.includes('leads') || page.props.auth?.user?.is_admin;
});

const headerLead = computed(() => {
    if (props.view === 'leads') {
        return `Лиды: ${props.lead_process_name || 'бизнес-процесс'}. Перетаскивание меняет этап БП.`;
    }

    return 'Заказы по стадиям до закрытия и приёмки бухгалтерией. Статусы вычисляются из фактов (маршрут, доки, оплаты).';
});

const leadProcessSlug = computed(() => props.lead_process_slug);
const leadDragEnabled = computed(() => props.view === 'leads' && props.can_advance_lead_stage);
const canMarkAccountingHandoff = computed(() => props.can_mark_accounting_handoff);

const kpiPeriodLabel = computed(() => {
    const months = Number(props.kpi?.period_months ?? 12);

    return months === 12 ? '12 мес.' : `${months} мес.`;
});

function formatDays(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    const days = Number(value);

    if (Number.isNaN(days)) {
        return '—';
    }

    return `${days.toLocaleString('ru-RU', { maximumFractionDigits: 1 })} дн.`;
}

function formatPercent(value) {
    const numeric = Number(value ?? 0);

    return `${numeric.toLocaleString('ru-RU', { maximumFractionDigits: 1 })}%`;
}

const draggedCard = ref(null);
const dragOverColumn = ref('');

const advanceForm = useForm({
    stage_id: '',
    close_outcome_primary_flag: '',
    close_outcome_note: '',
});

function switchView(nextView) {
    router.get(route('pipeline.index'), nextView === 'leads'
        ? { view: 'leads', lead_process: leadProcessSlug.value || 'transport-intake' }
        : { view: 'orders' }, { preserveState: false });
}

function switchLeadProcess(slug) {
    router.get(route('pipeline.index'), { view: 'leads', lead_process: slug }, { preserveState: false });
}

function cardTitle(card) {
    if (card.type === 'order') {
        return card.order_number || `Заказ #${card.id}`;
    }

    return card.number || card.title || `Лид #${card.id}`;
}

function cardSubtitle(card) {
    if (card.type === 'order') {
        return [card.customer_name, card.route_label, card.manager_name].filter(Boolean).join(' · ');
    }

    return [card.customer_name, card.responsible_name, card.stage_name].filter(Boolean).join(' · ');
}

function onCardDragStart(event, card) {
    if (!leadDragEnabled.value || card.type !== 'lead') {
        return;
    }

    draggedCard.value = card;
    event.dataTransfer.effectAllowed = 'move';
}

function onCardDragEnd() {
    draggedCard.value = null;
    dragOverColumn.value = '';
}

function onColumnDragOver(column) {
    if (!leadDragEnabled.value || !draggedCard.value || column.stage_id == null) {
        return;
    }

    dragOverColumn.value = column.key;
}

function onColumnDragLeave(columnKey) {
    if (dragOverColumn.value === columnKey) {
        dragOverColumn.value = '';
    }
}

function onColumnDrop(column) {
    dragOverColumn.value = '';

    const card = draggedCard.value;
    draggedCard.value = null;

    if (!card || card.type !== 'lead' || column.stage_id == null) {
        return;
    }

    if (Number(card.stage_id) === Number(column.stage_id)) {
        return;
    }

    advanceForm.stage_id = column.stage_id;
    advanceForm.patch(route('leads.process-stage', card.id), {
        preserveScroll: true,
        onSuccess: () => {
            advanceForm.reset();
        },
    });
}
</script>

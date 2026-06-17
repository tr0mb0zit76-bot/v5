<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <CrmPageHeader
            lead="Заказы «в пути»: отметки местоположения и комментарии по дням от самой ранней погрузки до плановой выгрузки."
            title="Диспозиция"
        />

        <section
            v-if="kpi"
            class="crm-panel grid grid-cols-1 gap-3 p-4 sm:grid-cols-3"
        >
            <div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">Оба слота · {{ kpiDateLabel }}</div>
                <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ formatPercent(kpi.both_slots_fill_percent) }}
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ kpi.both_slots_filled_count }} / {{ kpi.orders_in_progress }} заказов
                </div>
            </div>
            <div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">Средняя задержка внесения</div>
                <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ formatDelayMinutes(kpi.avg_delay_minutes) }}
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ kpi.delayed_entries_count }} ячеек после 10:00 / 16:00
                </div>
            </div>
            <div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">Утро / вечер</div>
                <div class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ formatPercent(kpi.morning_fill_percent) }} · {{ formatPercent(kpi.evening_fill_percent) }}
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Заполнение местоположения по слотам
                </div>
            </div>
        </section>

        <div :class="crmGridPanel">
            <DispositionGrid
                :dates="dates"
                :rows="rows"
                :today="today"
                :user-id="userId"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import DispositionGrid from '@/Components/Disposition/DispositionGrid.vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmGridPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'planning', activeSubKey: 'disposition', mainFill: true }, () => page),
});

const props = defineProps({
    dates: { type: Array, default: () => [] },
    today: { type: String, default: '' },
    rows: { type: Array, default: () => [] },
    status_filter: { type: String, default: 'in_progress' },
    kpi: { type: Object, default: null },
});

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');

const kpiDateLabel = computed(() => {
    const raw = props.kpi?.date ?? props.today;
    if (!raw) {
        return 'сегодня';
    }

    const parts = String(raw).split('-');
    if (parts.length !== 3) {
        return raw;
    }

    return `${parts[2]}.${parts[1]}.${parts[0]}`;
});

function formatPercent(value) {
    const numeric = Number(value ?? 0);

    return `${numeric.toLocaleString('ru-RU', { maximumFractionDigits: 1 })}%`;
}

function formatDelayMinutes(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    const minutes = Number(value);
    if (Number.isNaN(minutes)) {
        return '—';
    }

    if (minutes < 60) {
        return `${Math.round(minutes)} мин`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = Math.round(minutes % 60);

    return rest > 0 ? `${hours} ч ${rest} мин` : `${hours} ч`;
}
</script>

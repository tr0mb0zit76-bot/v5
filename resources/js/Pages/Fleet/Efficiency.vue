<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <CrmPageHeader
            lead="План/факт по рейсам собственного парка."
            title="Эффективность"
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Рейсов всего</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.trip_count }}</p>
                <p class="mt-1 text-xs text-zinc-500">Завершено: {{ summary.completed_count }}</p>
            </article>
            <article class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Факт, ₽</p>
                <p class="mt-2 text-2xl font-semibold">{{ formatMoney(summary.total_actual_cost) }}</p>
            </article>
            <article class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Факт, км</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.total_actual_km.toLocaleString('ru-RU') }}</p>
            </article>
            <article class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <p class="text-xs uppercase tracking-wide text-zinc-500">₽ / км</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.rub_per_km != null ? formatMoney(summary.rub_per_km) : '—' }}</p>
                <p v-if="summary.own_fleet_order_share_percent != null" class="mt-1 text-xs text-zinc-500">
                    Доля заказов с «Собственным парком»: {{ summary.own_fleet_order_share_percent }}%
                </p>
            </article>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'own-fleet', activeSubKey: 'fleet-efficiency' }, () => page),
});

const page = usePage();
const summary = computed(() => page.props.summary ?? {
    trip_count: 0,
    completed_count: 0,
    total_actual_cost: 0,
    total_actual_km: 0,
    rub_per_km: null,
    own_fleet_order_share_percent: null,
});

function formatMoney(value) {
    return `${Number(value).toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ₽`;
}
</script>

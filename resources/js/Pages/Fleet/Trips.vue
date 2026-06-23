<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <CrmPageHeader
            lead="Рейсы собственного парка, связь с заказами и фактические затраты."
            title="Рейсы"
        />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,28rem)]">
            <section :class="crmGridPanel">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-left text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-700">
                                <th class="px-3 py-2">Заказ</th>
                                <th class="px-3 py-2">Плечо</th>
                                <th class="px-3 py-2">Статус</th>
                                <th class="px-3 py-2">ТС / водитель</th>
                                <th class="px-3 py-2 text-right">План</th>
                                <th class="px-3 py-2 text-right">Факт</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="trip in trips"
                                :key="trip.id"
                                class="cursor-pointer border-b border-zinc-100 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900/60"
                                :class="selectedTrip?.id === trip.id ? 'bg-sky-50/70 dark:bg-sky-950/20' : ''"
                                @click="openTrip(trip.id)"
                            >
                                <td class="px-3 py-2 font-medium">{{ trip.order_number || `#${trip.order_id}` }}</td>
                                <td class="px-3 py-2">
                                    {{ stageLabel(trip.order_leg_stage) }}
                                    <span v-if="trip.carrier_slot"> · {{ splitCarrierSlotLabel(trip.carrier_slot) }}</span>
                                </td>
                                <td class="px-3 py-2">{{ statusLabel(trip.status) }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                    <div>{{ trip.vehicle_label || '—' }}</div>
                                    <div class="text-xs">{{ trip.driver_name || '—' }}</div>
                                </td>
                                <td class="px-3 py-2 text-right">{{ formatMoney(trip.estimated_cost) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatMoney(trip.total_cost) }}</td>
                            </tr>
                            <tr v-if="trips.length === 0">
                                <td colspan="6" class="px-3 py-8 text-center text-zinc-500">Рейсов пока нет. Они создаются при сохранении заказа с перевозчиком «Собственный парк».</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="selectedTrip" class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div>
                    <h2 class="text-base font-semibold">Рейс #{{ selectedTrip.id }}</h2>
                    <p class="text-xs text-zinc-500">Заказ {{ selectedTrip.order_number || selectedTrip.order_id }} · {{ stageLabel(selectedTrip.order_leg_stage) }}</p>
                </div>

                <form class="space-y-4" @submit.prevent="saveTrip">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-500">Статус</label>
                            <select v-model="tripForm.status" :class="crmFieldFluid" :disabled="selectedTrip.status === 'completed'">
                                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-500">План, км</label>
                            <input v-model.number="tripForm.planned_km" type="number" min="0" :class="crmFieldFluid" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-500">Факт, км</label>
                            <input v-model.number="tripForm.actual_km" type="number" min="0" :class="crmFieldFluid" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-500">Примерная стоимость</label>
                            <input v-model="tripForm.estimated_cost" type="number" min="0" step="0.01" :class="crmFieldFluid" :disabled="selectedTrip.status === 'completed'" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold">Строки затрат</h3>
                            <button type="button" class="text-xs text-sky-600 hover:underline" @click="addCostLine">+ Строка</button>
                        </div>
                        <div
                            v-for="(line, index) in tripForm.cost_lines"
                            :key="`cost-${index}`"
                            class="grid gap-2 rounded-xl border border-zinc-100 p-3 dark:border-zinc-800 sm:grid-cols-12"
                        >
                            <select v-model="line.cost_category" :class="[crmFieldFluid, 'sm:col-span-4']">
                                <option v-for="option in costCategoryOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <input v-model="line.amount" type="number" min="0" step="0.01" placeholder="Сумма" :class="[crmFieldFluid, 'sm:col-span-3']" />
                            <input v-model="line.comment" type="text" placeholder="Комментарий" :class="[crmFieldFluid, 'sm:col-span-4']" />
                            <button type="button" class="text-xs text-rose-600 sm:col-span-1" @click="removeCostLine(index)">×</button>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" :class="crmBtnPrimary" :disabled="tripForm.processing">Сохранить</button>
                        <button
                            v-if="selectedTrip.status !== 'completed'"
                            type="button"
                            :class="crmBtnCreate"
                            :disabled="completeForm.processing"
                            @click="completeTrip"
                        >
                            Закрыть рейс
                        </button>
                        <Link
                            v-if="selectedTrip.order_id"
                            :href="route('orders.edit', selectedTrip.order_id)"
                            class="rounded-xl border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        >
                            Открыть заказ
                        </Link>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnCreate, crmBtnPrimary, crmFieldFluid, crmGridPanel } from '@/support/crmUi.js';
import { splitCarrierSlotLabel } from '@/support/orderPerformers.js';
import { stageLabel } from '@/support/orderPrintFormSlots.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'own-fleet', activeSubKey: 'fleet-trips' }, () => page),
});

const page = usePage();
const trips = computed(() => page.props.trips ?? []);
const selectedTrip = computed(() => page.props.selectedTrip ?? null);
const statusOptions = computed(() => page.props.statusOptions ?? []);
const costCategoryOptions = computed(() => page.props.costCategoryOptions ?? []);

const tripForm = useForm({
    status: 'planned',
    planned_km: null,
    actual_km: null,
    estimated_cost: null,
    cost_lines: [],
});

const completeForm = useForm({});

watch(selectedTrip, (trip) => {
    if (!trip) {
        return;
    }

    tripForm.defaults({
        status: trip.status ?? 'planned',
        planned_km: trip.planned_km,
        actual_km: trip.actual_km,
        estimated_cost: trip.estimated_cost,
        cost_lines: Array.isArray(trip.cost_lines) ? trip.cost_lines.map((line) => ({ ...line })) : [],
    });
    tripForm.reset();
}, { immediate: true });

function statusLabel(code) {
    return statusOptions.value.find((option) => option.value === code)?.label ?? code;
}

function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return `${Number(value).toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ₽`;
}

function openTrip(id) {
    router.visit(route('fleet.trips.show', id), { preserveScroll: true });
}

function blankCostLine() {
    return {
        cost_category: costCategoryOptions.value[0]?.value ?? 'other',
        amount: null,
        currency: 'RUB',
        comment: '',
    };
}

function addCostLine() {
    tripForm.cost_lines.push(blankCostLine());
}

function removeCostLine(index) {
    tripForm.cost_lines.splice(index, 1);
}

function saveTrip() {
    if (!selectedTrip.value?.id) {
        return;
    }

    tripForm.patch(route('fleet.trips.update', selectedTrip.value.id), {
        preserveScroll: true,
    });
}

function completeTrip() {
    if (!selectedTrip.value?.id) {
        return;
    }

    completeForm.transform(() => ({ actual_km: tripForm.actual_km }))
        .post(route('fleet.trips.complete', selectedTrip.value.id), {
            preserveScroll: true,
        });
}
</script>

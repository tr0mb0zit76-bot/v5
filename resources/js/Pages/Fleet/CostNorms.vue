<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <CrmPageHeader
            lead="Нормы для калькулятора «Сколько стоит?»: топливо в ₽/л + расход л/100 км → ₽/км автоматически."
            title="Нормы себестоимости"
        />

        <form class="space-y-4" @submit.prevent="submit">
            <section :class="`${crmPanel} space-y-4 p-5`">
                <h2 :class="crmSectionTitle">Китай (до границы)</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Цена топлива, ₽/л</span>
                        <input v-model="form.cn.fuel_price_rub_per_liter" type="number" min="0" step="0.01" :class="crmFieldFluid">
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Расход, л/100 км</span>
                        <input v-model="form.cn.fuel_consumption_l_per_100km" type="number" min="0" step="0.01" :class="crmFieldFluid">
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Топливо, ₽/км</span>
                        <input :value="formatPerKm(cnFuelPerKm)" type="text" readonly :class="crmFieldFluid">
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Труд водителя, ₽/км</span>
                        <input v-model="form.cn.driver_rub_per_km" type="number" min="0" step="0.01" :class="crmFieldFluid">
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Прочее, ₽/км</span>
                        <input v-model="form.cn.other_rub_per_km" type="number" min="0" step="0.01" :class="crmFieldFluid">
                    </label>
                </div>
            </section>

            <section :class="`${crmPanel} space-y-4 p-5`">
                <h2 :class="crmSectionTitle">РФ (от границы)</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Цена топлива, ₽/л</span>
                        <input v-model="form.ru.fuel_price_rub_per_liter" type="number" min="0" step="0.01" :class="crmFieldFluid">
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Расход, л/100 км</span>
                        <input v-model="form.ru.fuel_consumption_l_per_100km" type="number" min="0" step="0.01" :class="crmFieldFluid">
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Топливо, ₽/км</span>
                        <input :value="formatPerKm(ruFuelPerKm)" type="text" readonly :class="crmFieldFluid">
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Труд водителя, ₽/км</span>
                        <input v-model="form.ru.driver_rub_per_km" type="number" min="0" step="0.01" :class="crmFieldFluid">
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Прочее, ₽/км</span>
                        <input v-model="form.ru.other_rub_per_km" type="number" min="0" step="0.01" :class="crmFieldFluid">
                    </label>
                </div>
            </section>

            <section :class="`${crmPanel} space-y-4 p-5`">
                <h2 :class="crmSectionTitle">На весь рейс и наценка</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Амортизация, ₽/км</span>
                        <input v-model="form.depreciation_rub_per_km" type="number" min="0" step="0.01" :class="crmFieldFluid">
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Наценка, %</span>
                        <input v-model="form.margin_percent" type="number" min="0" step="0.01" :class="crmFieldFluid">
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Надбавка, ₽</span>
                        <input v-model="form.margin_absolute_rub" type="number" min="0" step="0.01" :class="crmFieldFluid">
                    </label>
                </div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Цена клиенту = себестоимость × (1 + наценка%) + надбавка ₽.
                    Позже появится кнопка «Обновить» из факта рейсов.
                </p>
            </section>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" :class="crmBtnPrimary" :disabled="form.processing">
                    {{ form.processing ? 'Сохраняем…' : 'Сохранить нормы' }}
                </button>
                <p v-if="form.recentlySuccessful" class="text-sm text-emerald-700 dark:text-emerald-300">
                    Сохранено.
                </p>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmBtnPrimary,
    crmFieldFluid,
    crmFilterField,
    crmLabelCompact,
    crmPanel,
    crmSectionTitle,
} from '@/support/crmUi.js';

const props = defineProps({
    norms: {
        type: Object,
        required: true,
    },
});

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'own-fleet', activeSubKey: 'fleet-cost-norms' }, () => page),
});

const form = useForm({
    cn: {
        fuel_price_rub_per_liter: props.norms.cn?.fuel_price_rub_per_liter ?? 0,
        fuel_consumption_l_per_100km: props.norms.cn?.fuel_consumption_l_per_100km ?? 0,
        driver_rub_per_km: props.norms.cn?.driver_rub_per_km ?? 0,
        other_rub_per_km: props.norms.cn?.other_rub_per_km ?? 0,
    },
    ru: {
        fuel_price_rub_per_liter: props.norms.ru?.fuel_price_rub_per_liter ?? 0,
        fuel_consumption_l_per_100km: props.norms.ru?.fuel_consumption_l_per_100km ?? 0,
        driver_rub_per_km: props.norms.ru?.driver_rub_per_km ?? 0,
        other_rub_per_km: props.norms.ru?.other_rub_per_km ?? 0,
    },
    depreciation_rub_per_km: props.norms.depreciation_rub_per_km ?? 0,
    margin_percent: props.norms.margin_percent ?? 0,
    margin_absolute_rub: props.norms.margin_absolute_rub ?? 0,
});

const cnFuelPerKm = computed(() => fuelPerKm(
    form.cn.fuel_price_rub_per_liter,
    form.cn.fuel_consumption_l_per_100km,
));

const ruFuelPerKm = computed(() => fuelPerKm(
    form.ru.fuel_price_rub_per_liter,
    form.ru.fuel_consumption_l_per_100km,
));

function fuelPerKm(price, consumption) {
    const p = Number(price) || 0;
    const c = Number(consumption) || 0;

    return Math.round((c / 100) * p * 10000) / 10000;
}

function formatPerKm(value) {
    return `${Number(value).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 4 })} ₽/км`;
}

function submit() {
    form.put(route('fleet.cost-norms.update'), {
        preserveScroll: true,
    });
}
</script>

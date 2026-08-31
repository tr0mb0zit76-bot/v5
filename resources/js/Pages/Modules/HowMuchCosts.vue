<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <CrmPageHeader
            lead="Км до границы (Китай) и от границы (РФ) → себестоимость по нормам парка → цена клиенту."
            title="Сколько стоит?"
        />

        <p v-if="!normsConfigured" class="text-sm text-amber-800 dark:text-amber-200">
            Справочник норм ещё не создан (нужна миграция). Обратитесь к администратору.
        </p>

        <div v-else class="grid min-h-0 gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <section :class="`${crmPanel} space-y-5 p-5`">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Км до границы (Китай)</span>
                        <input
                            v-model="form.km_to_border"
                            type="number"
                            min="0"
                            step="1"
                            :class="crmFieldFluid"
                            @input="scheduleRecalculate"
                        >
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Км от границы (РФ)</span>
                        <input
                            v-model="form.km_from_border"
                            type="number"
                            min="0"
                            step="1"
                            :class="crmFieldFluid"
                            @input="scheduleRecalculate"
                        >
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Наценка, % (override)</span>
                        <input
                            v-model="form.margin_percent"
                            type="number"
                            min="0"
                            step="0.01"
                            :class="crmFieldFluid"
                            :placeholder="String(norms?.margin_percent ?? 0)"
                            @input="scheduleRecalculate"
                        >
                    </label>
                    <label :class="crmFilterField">
                        <span :class="crmLabelCompact">Надбавка, ₽ (override)</span>
                        <input
                            v-model="form.margin_absolute_rub"
                            type="number"
                            min="0"
                            step="0.01"
                            :class="crmFieldFluid"
                            :placeholder="String(norms?.margin_absolute_rub ?? 0)"
                            @input="scheduleRecalculate"
                        >
                    </label>
                </div>

                <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                    Пустые override берут значения из справочника норм собственного парка.
                    Сейчас: топливо CN {{ formatPerKm(norms?.cn?.fuel_cost_rub_per_km) }},
                    RU {{ formatPerKm(norms?.ru?.fuel_cost_rub_per_km) }};
                    наценка {{ norms?.margin_percent ?? 0 }}% + {{ formatMoney(norms?.margin_absolute_rub ?? 0) }}.
                </p>

                <button type="button" :class="crmBtnPrimary" :disabled="calculating" @click="recalculate">
                    {{ calculating ? 'Считаем…' : 'Рассчитать' }}
                </button>
                <p v-if="errorMessage" class="text-sm text-rose-600 dark:text-rose-300">{{ errorMessage }}</p>
            </section>

            <section :class="`${crmPanel} space-y-4 p-5`">
                <h2 :class="crmSectionTitle">Результат</h2>
                <template v-if="result">
                    <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-900/50 dark:bg-sky-950/30">
                        <p class="text-xs uppercase tracking-wide text-sky-700 dark:text-sky-300">Цена клиенту</p>
                        <p class="mt-2 text-3xl font-semibold text-sky-950 dark:text-sky-50">
                            {{ formatMoney(result.totals.customer_price) }}
                        </p>
                        <p class="mt-1 text-sm text-sky-800 dark:text-sky-200">
                            Себестоимость {{ formatMoney(result.totals.cost_price) }}
                            · наценка {{ formatMoney(result.totals.margin_from_percent_rub) }}
                            · надбавка {{ formatMoney(result.totals.margin_absolute_rub) }}
                        </p>
                    </div>

                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        <li
                            v-for="row in result.breakdown"
                            :key="row.label"
                            class="flex items-center justify-between gap-3 py-2 text-sm"
                        >
                            <span class="text-zinc-600 dark:text-zinc-300">{{ row.label }}</span>
                            <span class="font-medium tabular-nums">{{ formatMoney(row.amount) }}</span>
                        </li>
                    </ul>
                </template>
                <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">
                    Укажите километраж и нажмите «Рассчитать».
                </p>
            </section>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
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

defineProps({
    norms: {
        type: Object,
        default: null,
    },
    normsConfigured: {
        type: Boolean,
        default: false,
    },
});

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'modules', activeSubKey: 'modules-how-much-costs' }, () => page),
});

const form = reactive({
    km_to_border: '',
    km_from_border: '',
    margin_percent: '',
    margin_absolute_rub: '',
});

const result = ref(null);
const calculating = ref(false);
const errorMessage = ref('');
let recalculateTimer = null;

function scheduleRecalculate() {
    window.clearTimeout(recalculateTimer);
    recalculateTimer = window.setTimeout(() => {
        recalculate();
    }, 350);
}

async function recalculate() {
    if (form.km_to_border === '' && form.km_from_border === '') {
        result.value = null;

        return;
    }

    calculating.value = true;
    errorMessage.value = '';

    try {
        const payload = {
            km_to_border: Number(form.km_to_border) || 0,
            km_from_border: Number(form.km_from_border) || 0,
        };

        if (form.margin_percent !== '') {
            payload.margin_percent = Number(form.margin_percent);
        }
        if (form.margin_absolute_rub !== '') {
            payload.margin_absolute_rub = Number(form.margin_absolute_rub);
        }

        const response = await fetch(route('modules.how-much-costs.calculate'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        if (! response.ok) {
            const data = await response.json().catch(() => ({}));
            errorMessage.value = data.message || 'Не удалось рассчитать.';
            result.value = null;

            return;
        }

        result.value = await response.json();
    } catch {
        errorMessage.value = 'Ошибка сети при расчёте.';
        result.value = null;
    } finally {
        calculating.value = false;
    }
}

function formatMoney(value) {
    return `${Number(value || 0).toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ₽`;
}

function formatPerKm(value) {
    if (value == null) {
        return '—';
    }

    return `${Number(value).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 4 })} ₽/км`;
}
</script>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    formatPrecalculationMoney,
    freightDistributionOptions,
    precalculationStatusOptions,
} from '@/support/leadWizardPrecalculation.js';
import { crmBtnSecondary } from '@/support/crmUi.js';

const props = defineProps({
    snapshot: { type: Object, required: true },
    orderId: { type: Number, required: true },
    importCostMeta: { type: Object, default: () => ({}) },
});

const precalculation = computed(() => props.snapshot?.precalculation ?? {});
const computedTotals = computed(() => precalculation.value?.computed ?? null);

const statusLabel = computed(() => {
    const status = precalculation.value?.status ?? 'draft';

    return precalculationStatusOptions.find((option) => option.value === status)?.label ?? status;
});

const freightBasisLabel = computed(() => {
    const basis = precalculation.value?.freight?.distribution_basis ?? 'invoice_rub';

    return freightDistributionOptions.find((option) => option.value === basis)?.label ?? basis;
});

const snapshotAtLabel = computed(() => {
    const raw = props.snapshot?.snapshot_at;

    if (!raw) {
        return null;
    }

    return new Date(raw).toLocaleString('ru-RU');
});

const disclaimer = computed(() => props.importCostMeta?.disclaimer ?? '');

function lineTotal(lineId) {
    const rows = computedTotals.value?.goods_lines ?? [];
    const row = rows.find((item) => item.line_id === lineId);

    return row?.summary?.total_landed ?? null;
}

function openSnapshotDocument(format = 'html') {
    const url = route('orders.lead-precalculation-snapshot.document', {
        order: props.orderId,
        format,
    });

    window.open(url, '_blank', 'noopener');
}
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold">Предрасчёт с лида</h3>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Снимок на момент конвертации. Только просмотр — правки в карточке исходного лида.
                </p>
                <p v-if="snapshotAtLabel" class="mt-2 text-xs text-zinc-500">
                    Сохранён: {{ snapshotAtLabel }}
                </p>
                <p v-if="disclaimer" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ disclaimer }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" :class="crmBtnSecondary" @click="openSnapshotDocument('html')">
                    Предпросмотр HTML
                </button>
                <button type="button" :class="crmBtnSecondary" @click="openSnapshotDocument('pdf')">
                    PDF
                </button>
                <Link
                    v-if="snapshot?.lead_id"
                    :href="route('leads.show', snapshot.lead_id)"
                    :class="crmBtnSecondary"
                >
                    Открыть лид
                </Link>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-800 dark:bg-zinc-900/40">
            <span class="text-zinc-500">Статус на лиде:</span>
            <span class="ml-2 font-medium text-zinc-900 dark:text-zinc-100">{{ statusLabel }}</span>
        </div>

        <section
            v-if="precalculation.freight"
            class="space-y-2 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800"
        >
            <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-zinc-500">Фрахт на отправление</h4>
            <div class="grid gap-2 text-sm md:grid-cols-3">
                <div>
                    <span class="text-zinc-500">До границы:</span>
                    {{ formatPrecalculationMoney(precalculation.freight.to_border_total) }}
                </div>
                <div>
                    <span class="text-zinc-500">После выпуска:</span>
                    {{ formatPrecalculationMoney(precalculation.freight.after_border_total) }}
                </div>
                <div>
                    <span class="text-zinc-500">Распределение:</span>
                    {{ freightBasisLabel }}
                </div>
            </div>
        </section>

        <section v-if="precalculation.goods_lines?.length" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-zinc-500">Товары</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-zinc-500">
                        <tr>
                            <th class="pb-2 pr-3">#</th>
                            <th class="pb-2 pr-3">Описание</th>
                            <th class="pb-2 pr-3">ТН ВЭД</th>
                            <th class="pb-2 pr-3 text-right">Инвойс</th>
                            <th class="pb-2 text-right">Итого, ₽</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(line, index) in precalculation.goods_lines"
                            :key="line.id ?? `goods-${index}`"
                            class="border-t border-zinc-100 dark:border-zinc-800"
                        >
                            <td class="py-2 pr-3">{{ index + 1 }}</td>
                            <td class="py-2 pr-3">{{ line.description || '—' }}</td>
                            <td class="py-2 pr-3">{{ line.tn_ved_code || '—' }}</td>
                            <td class="py-2 pr-3 text-right">
                                {{ line.invoice_amount ?? '—' }} {{ line.currency ?? '' }}
                            </td>
                            <td class="py-2 text-right font-medium">{{ formatPrecalculationMoney(lineTotal(line.id)) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="precalculation.service_lines?.length" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-zinc-500">Услуги</h4>
            <ul class="space-y-2 text-sm">
                <li
                    v-for="(line, index) in precalculation.service_lines"
                    :key="line.id ?? `service-${index}`"
                    class="flex items-center justify-between gap-3 border-t border-zinc-100 pt-2 first:border-t-0 first:pt-0 dark:border-zinc-800"
                >
                    <span>{{ line.title || 'Услуга' }}</span>
                    <span class="font-medium">{{ formatPrecalculationMoney(line.amount) }}</span>
                </li>
            </ul>
        </section>

        <section
            v-if="computedTotals"
            class="ml-auto max-w-md space-y-2 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-sm"
        >
            <div class="flex justify-between gap-3">
                <span class="text-zinc-500">Товары + таможня</span>
                <span>{{ formatPrecalculationMoney(computedTotals.goods_total) }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-zinc-500">Услуги</span>
                <span>{{ formatPrecalculationMoney(computedTotals.services_total) }}</span>
            </div>
            <div class="flex justify-between gap-3 border-t border-emerald-500/20 pt-2 text-base font-semibold text-emerald-800 dark:text-emerald-200">
                <span>Итого клиенту</span>
                <span>{{ formatPrecalculationMoney(computedTotals.grand_total) }}</span>
            </div>
        </section>
    </div>
</template>

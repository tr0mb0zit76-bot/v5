<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <CrmPageHeader
            title="Акт сверки"
            lead="Сводка по заказам и оплатам с контрагентом: начислено по гриду «Заказы» и фактически оплачено по «Графику оплат»."
        />

        <nav class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <Link
                :href="route('finance.index')"
                class="text-zinc-600 underline decoration-zinc-300 underline-offset-2 transition hover:text-zinc-900 dark:text-zinc-400 dark:decoration-zinc-600 dark:hover:text-zinc-100"
            >
                ← К финансам
            </Link>
        </nav>

        <section :class="`${crmPanel} space-y-4 p-5`">
            <form class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_auto]" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Контрагент</label>
                    <select v-model="form.contractor_id" :class="crmFieldFluid" required>
                        <option value="">Выберите контрагента</option>
                        <option v-for="option in contractorOptions" :key="option.id" :value="String(option.id)">
                            {{ option.label }}<template v-if="option.inn"> · ИНН {{ option.inn }}</template>
                        </option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Период с</label>
                    <input v-model="form.date_from" type="date" :class="crmFieldFluid" />
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Период по</label>
                    <input v-model="form.date_to" type="date" :class="crmFieldFluid" />
                </div>
                <div class="flex items-end">
                    <button type="submit" :class="crmBtnPrimary" :disabled="form.processing">
                        Сформировать
                    </button>
                </div>
            </form>

            <p v-if="!report?.ledger_available" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
                Журнал оплат ещё не создан — выполните миграции и команду
                <code class="text-xs">php artisan payment-schedules:backfill-payment-events</code>.
            </p>
        </section>

        <template v-if="report">
            <section :class="`${crmPanel} space-y-3 p-5`">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ report.contractor.name }}
                        </h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            <span v-if="report.contractor.inn">ИНН {{ report.contractor.inn }} · </span>
                            Период:
                            {{ formatDate(report.period.from) || '…' }}
                            —
                            {{ formatDate(report.period.to) || '…' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" :class="crmBtnNeutral" class="gap-1.5 text-xs" @click="printReport">
                            <Printer class="h-4 w-4" />
                            Печать
                        </button>
                        <button type="button" :class="crmBtnNeutral" class="gap-1.5 text-xs" @click="downloadCsv">
                            <Download class="h-4 w-4" />
                            CSV
                        </button>
                    </div>
                </div>
            </section>

            <ReconciliationSection
                :section="report.as_customer"
                empty-text="За период нет заказов, где контрагент выступает заказчиком."
            />

            <ReconciliationSection
                :section="report.as_carrier"
                empty-text="За период нет заказов с услугами этого перевозчика."
            />
        </template>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { Download, Printer } from 'lucide-vue-next';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import ReconciliationSection from '@/Components/Finance/ReconciliationSection.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnNeutral, crmBtnPrimary, crmFieldFluid, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'finance', activeSubKey: 'finance-reconciliation' }, () => page),
});

const props = defineProps({
    contractorOptions: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    report: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    contractor_id: props.filters.contractor_id ? String(props.filters.contractor_id) : '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

function submit() {
    form.post(route('finance.reconciliation.store'), {
        preserveScroll: true,
    });
}

function formatDate(value) {
    if (!value) {
        return '';
    }

    const parts = String(value).slice(0, 10).split('-');

    if (parts.length !== 3) {
        return String(value);
    }

    return `${parts[2]}.${parts[1]}.${parts[0]}`;
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

function buildCsvLines() {
    if (!props.report) {
        return [];
    }

    const lines = [
        `Акт сверки;${props.report.contractor.name};${formatDate(props.report.period.from)};${formatDate(props.report.period.to)}`,
        '',
    ];

    for (const section of [props.report.as_customer, props.report.as_carrier]) {
        lines.push(section.title);
        lines.push('Заказ;Дата;Начислено;Оплачено;Остаток');

        for (const row of section.rows) {
            lines.push(
                [
                    row.order_number,
                    formatDate(row.order_date),
                    row.accrued,
                    row.paid,
                    row.balance,
                ].join(';'),
            );
        }

        lines.push(
            `Итого;;${section.totals.accrued};${section.totals.paid};${section.totals.balance}`,
        );
        lines.push('');
    }

    return lines;
}

function downloadCsv() {
    const lines = buildCsvLines();
    const blob = new Blob(['\uFEFF', lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `akt-sverki-${props.report.contractor.id}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

function printReport() {
    const report = props.report;
    const sectionsHtml = [report.as_customer, report.as_carrier]
        .map((section) => {
            const rows = section.rows
                .map(
                    (row) => `
                    <tr>
                        <td>${row.order_number}</td>
                        <td>${formatDate(row.order_date)}</td>
                        <td>${formatMoney(row.accrued)}</td>
                        <td>${formatMoney(row.paid)}</td>
                        <td>${formatMoney(row.balance)}</td>
                    </tr>
                `,
                )
                .join('');

            return `
                <h2>${section.title}</h2>
                <p>${section.description}</p>
                <table>
                    <thead>
                        <tr>
                            <th>Заказ</th>
                            <th>Дата</th>
                            <th>Начислено</th>
                            <th>Оплачено</th>
                            <th>Остаток</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">Итого</th>
                            <th>${formatMoney(section.totals.accrued)}</th>
                            <th>${formatMoney(section.totals.paid)}</th>
                            <th>${formatMoney(section.totals.balance)}</th>
                        </tr>
                    </tfoot>
                </table>
            `;
        })
        .join('');

    const printWindow = window.open('', '_blank', 'noopener,noreferrer');

    if (!printWindow) {
        window.alert('Разрешите всплывающие окна для печати.');

        return;
    }

    printWindow.document.write(`
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="utf-8" />
            <title>Акт сверки — ${report.contractor.name}</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; padding: 16px; }
                h1 { font-size: 18px; margin-bottom: 8px; }
                h2 { font-size: 14px; margin: 20px 0 8px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
                th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
                th { background: #f4f4f5; }
            </style>
        </head>
        <body>
            <h1>Акт сверки (сводка)</h1>
            <p><strong>${report.contractor.name}</strong>${report.contractor.inn ? ` · ИНН ${report.contractor.inn}` : ''}</p>
            <p>Период: ${formatDate(report.period.from) || '…'} — ${formatDate(report.period.to) || '…'}</p>
            ${sectionsHtml}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}
</script>

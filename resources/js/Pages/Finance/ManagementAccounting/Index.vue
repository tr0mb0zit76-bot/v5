<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <Link
                    href="/finance"
                    class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                >
                    <ArrowLeft class="h-4 w-4" />
                    К обзору финансов
                </Link>
                <h1 :class="crmPageTitle">Управленческий учёт</h1>
                <p :class="crmPageLead">
                    Банковские выписки, операционные платежи по заявкам, ФОТ и прочие статьи расходов.
                </p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section :class="`${crmPanel} space-y-4 p-5`">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    Загрузка выписки
                </h2>
                <form class="space-y-3" @submit.prevent="submitImport">
                    <label class="block space-y-1 text-sm">
                        <span class="text-zinc-600 dark:text-zinc-300">Счёт</span>
                        <select
                            v-model="importForm.bank_account_id"
                            class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-950"
                            required
                        >
                            <option value="" disabled>Выберите счёт</option>
                            <option v-for="account in bank_accounts" :key="account.id" :value="account.id">
                                {{ account.bank_name }} · {{ account.account_mask }} ({{ account.currency }})
                            </option>
                        </select>
                    </label>
                    <label class="block space-y-1 text-sm">
                        <span class="text-zinc-600 dark:text-zinc-300">Файл XLSX (Сбер «Реестр банковских документов»)</span>
                        <input
                            type="file"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            class="w-full text-sm"
                            @change="onFileChange"
                        >
                    </label>
                    <button
                        type="submit"
                        :disabled="importForm.processing"
                        class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50 dark:bg-zinc-100 dark:text-zinc-900"
                    >
                        Загрузить и разнести
                    </button>
                </form>
            </section>

            <section :class="`${crmPanel} space-y-4 p-5`">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    ФОТ (полупериод {{ current_payroll_half.half === 1 ? '1–15' : '16–конец' }})
                </h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Период {{ formatDate(current_payroll_half.period_start) }} — {{ formatDate(current_payroll_half.period_end) }},
                    выплата {{ formatDate(current_payroll_half.payment_date) }}
                </p>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs uppercase text-zinc-500">Начислено</div>
                        <div class="mt-1 text-lg font-semibold tabular-nums">{{ formatMoney(current_payroll_half.accrued_total) }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs uppercase text-zinc-500">Выплачено</div>
                        <div class="mt-1 text-lg font-semibold tabular-nums">{{ formatMoney(current_payroll_half.paid_total) }}</div>
                    </div>
                </div>
            </section>
        </div>

        <section :class="`${crmPanel} p-5`">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Импорты</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs uppercase text-zinc-500 dark:border-zinc-700">
                            <th class="px-2 py-2">Файл</th>
                            <th class="px-2 py-2">Счёт</th>
                            <th class="px-2 py-2">Период</th>
                            <th class="px-2 py-2">Строки</th>
                            <th class="px-2 py-2">Суммы</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in imports"
                            :key="item.id"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td class="px-2 py-2">{{ item.file_name }}</td>
                            <td class="px-2 py-2">{{ item.bank_account?.bank_name }} {{ item.bank_account?.account_mask }}</td>
                            <td class="px-2 py-2">{{ formatDate(item.period_from) }} — {{ formatDate(item.period_to) }}</td>
                            <td class="px-2 py-2 tabular-nums">{{ item.lines_allocated }} / {{ item.lines_count }}</td>
                            <td class="px-2 py-2 tabular-nums">
                                +{{ formatMoney(item.total_in) }} / −{{ formatMoney(item.total_out) }}
                            </td>
                            <td class="px-2 py-2 text-right">
                                <Link
                                    :href="`/finance/management-accounting/imports/${item.id}`"
                                    class="font-medium text-sky-700 hover:underline dark:text-sky-300"
                                >
                                    Разнести
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="imports.length === 0">
                            <td colspan="6" class="px-2 py-6 text-center text-zinc-500">Импортов пока нет</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section :class="`${crmPanel} p-5`">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Справочник статей</h2>
            <div class="mt-3 space-y-2">
                <div
                    v-for="category in categories"
                    :key="category.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700"
                >
                    <div>
                        <div class="font-medium">{{ category.name }}</div>
                        <div class="text-xs text-zinc-500">{{ category.code }}</div>
                    </div>
                    <input
                        v-if="!category.is_system"
                        :value="category.name"
                        type="text"
                        class="rounded border border-zinc-300 px-2 py-1 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        @change="updateCategory(category, $event.target.value)"
                    >
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmPageLead, crmPageTitle, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) =>
        h(CrmLayout, { activeKey: 'finance', activeSubKey: 'finance-management-accounting' }, () => page),
});

const props = defineProps({
    bank_accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    imports: { type: Array, default: () => [] },
    payroll_halves: { type: Array, default: () => [] },
    current_payroll_half: { type: Object, default: () => ({}) },
});

const importForm = useForm({
    bank_account_id: props.bank_accounts[0]?.id ?? '',
    statement_file: null,
});

function onFileChange(event) {
    importForm.statement_file = event.target.files?.[0] ?? null;
}

function submitImport() {
    importForm.post('/finance/management-accounting/imports', {
        forceFormData: true,
    });
}

function updateCategory(category, name) {
    router.patch(`/finance/management-accounting/categories/${category.id}`, { name }, { preserveScroll: true });
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
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden">
        <!-- Загрузка — одна служебная строка под чертой вкладок, не конкурирует с мостом -->
        <form
            class="flex shrink-0 flex-wrap items-end gap-3 border-b border-zinc-200 pb-3 dark:border-zinc-700"
            @submit.prevent="submitImport"
        >
            <label class="min-w-[12rem] flex-1 space-y-1 text-sm sm:max-w-sm">
                <span :class="crmLabel">Счёт</span>
                <select v-model="importForm.bank_account_id" :class="crmFieldFluid">
                    <option :value="null">Из файла / сводная</option>
                    <option v-for="account in bank_accounts" :key="account.id" :value="Number(account.id)">
                        {{ account.bank_name }} · {{ account.account_mask }} ({{ account.currency }})
                    </option>
                </select>
            </label>
            <label class="min-w-[10rem] space-y-1 text-sm">
                <span :class="crmLabel">Файл XLSX</span>
                <input
                    type="file"
                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    class="block w-full max-w-xs text-sm text-zinc-700 file:mr-2 file:rounded file:border-0 file:bg-zinc-100 file:px-2.5 file:py-1.5 file:text-xs file:font-medium file:text-zinc-800 hover:file:bg-zinc-200 dark:text-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-100"
                    @change="onFileChange"
                >
            </label>
            <button type="submit" :disabled="importForm.processing" :class="crmBtnPrimary">
                Загрузить
            </button>
            <p class="w-full text-[11px] leading-4 text-zinc-500 dark:text-zinc-400 sm:ml-auto sm:w-auto sm:max-w-xs sm:pb-1 sm:text-right">
                Повтор того же файла не создаёт дубликат — откроется существующая выписка. Ночные/дневные импорты из 1С появляются в списке ниже (ответственный «Система»).
            </p>
        </form>

        <section :class="`${crmPanel} flex min-h-0 flex-1 flex-col overflow-hidden p-0`">
            <div class="shrink-0 border-b border-zinc-200 px-4 py-2.5 dark:border-zinc-800">
                <h2 :class="crmSectionTitle">Импорты</h2>
            </div>
            <div class="min-h-0 flex-1 overflow-auto overscroll-y-contain">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-white dark:bg-zinc-900">
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wide text-zinc-500 dark:border-zinc-700">
                            <th class="whitespace-nowrap px-4 py-2.5">Файл</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Счёт</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Период</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Строки</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Суммы</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Кто загрузил</th>
                            <th class="whitespace-nowrap px-4 py-2.5 text-right">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in imports"
                            :key="item.id"
                            class="border-b border-zinc-100 last:border-b-0 dark:border-zinc-800"
                        >
                            <td class="max-w-[18rem] truncate px-4 py-2.5 font-medium text-zinc-900 dark:text-zinc-100" :title="item.file_name">
                                {{ item.file_name }}
                            </td>
                            <td class="max-w-[16rem] truncate px-4 py-2.5 text-zinc-700 dark:text-zinc-300">
                                <template v-if="item.bank_account">
                                    {{ item.bank_account.bank_name }} {{ item.bank_account.account_mask }}
                                </template>
                                <span v-else class="text-zinc-500">—</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-zinc-700 dark:text-zinc-300">
                                {{ formatDate(item.period_from) }} — {{ formatDate(item.period_to) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-zinc-700 dark:text-zinc-300">
                                {{ item.lines_allocated }} / {{ item.lines_count }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-zinc-700 dark:text-zinc-300">
                                +{{ formatMoney(item.total_in) }} / −{{ formatMoney(item.total_out) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600 dark:text-zinc-300">
                                {{ item.importer_name ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <Link
                                        v-if="item.pending_lines > 0"
                                        :href="importHref(item.id, 'pending')"
                                        :class="crmBtnNeutral"
                                    >
                                        Разнести
                                    </Link>
                                    <Link
                                        v-if="item.has_allocated_lines"
                                        :href="importHref(item.id, 'allocated')"
                                        :class="crmBtnPrimary"
                                    >
                                        Исправить
                                    </Link>
                                    <Link
                                        v-if="item.pending_lines === 0 && !item.has_allocated_lines"
                                        :href="importHref(item.id, 'all')"
                                        class="text-sm font-medium text-sky-700 hover:underline dark:text-sky-300"
                                    >
                                        Открыть
                                    </Link>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200 px-3 py-1.5 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                        @click="deleteImport(item)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="imports.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-zinc-500">Импортов пока нет</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    crmBtnNeutral,
    crmBtnPrimary,
    crmFieldFluid,
    crmLabel,
    crmPanel,
    crmSectionTitle,
} from '@/support/crmUi.js';

const props = defineProps({
    imports: { type: Array, default: () => [] },
    bank_accounts: { type: Array, default: () => [] },
    default_bank_account_id: { type: [Number, String], default: null },
});

const importForm = useForm({
    bank_account_id: props.default_bank_account_id ? Number(props.default_bank_account_id) : null,
    statement_file: null,
});

function importHref(importId, filter) {
    return `/finance/management-accounting/imports/${importId}?filter=${filter}`;
}

function onFileChange(event) {
    importForm.statement_file = event.target.files?.[0] ?? null;
}

function submitImport() {
    importForm
        .transform((data) => ({
            ...data,
            bank_account_id: data.bank_account_id || null,
        }))
        .post('/finance/management-accounting/imports', {
            forceFormData: true,
        });
}

function deleteImport(item) {
    const allocated = Number(item.lines_allocated) || 0;

    const message = allocated > 0
        ? `Удалить выписку «${item.file_name}»?\n\nБудут отменены все ${allocated} разнесений и удалены все операции. Это действие нельзя отменить.`
        : `Удалить выписку «${item.file_name}» и все её операции? Это действие нельзя отменить.`;

    if (!window.confirm(message)) {
        return;
    }

    router.delete(`/finance/management-accounting/imports/${item.id}`);
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

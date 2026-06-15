<template>
    <div class="space-y-4">
        <section :class="`${crmPanel} space-y-4 p-5`">
            <h2 :class="crmSectionTitle">Загрузка выписки</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Реестр банковских документов (XLSX). Повторная загрузка того же файла не создаст дубликат — откроется существующая выписка.
            </p>
            <form class="space-y-3" @submit.prevent="submitImport">
                <label class="block space-y-1 text-sm">
                    <span :class="crmLabel">Счёт (необязательно)</span>
                    <select v-model="importForm.bank_account_id" :class="crmFieldFluid">
                        <option :value="null">Определить из файла / сводная</option>
                        <option v-for="account in bank_accounts" :key="account.id" :value="Number(account.id)">
                            {{ account.bank_name }} · {{ account.account_mask }} ({{ account.currency }})
                        </option>
                    </select>
                </label>
                <label class="block space-y-1 text-sm">
                    <span :class="crmLabel">Файл XLSX</span>
                    <input
                        type="file"
                        accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        class="w-full text-sm"
                        @change="onFileChange"
                    >
                </label>
                <button type="submit" :disabled="importForm.processing" :class="crmBtnPrimary">
                    Загрузить выписку
                </button>
            </form>
        </section>

        <section :class="`${crmPanel} p-5`">
            <h2 :class="crmSectionTitle">Импорты</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs uppercase text-zinc-500 dark:border-zinc-700">
                            <th class="px-2 py-2">Файл</th>
                            <th class="px-2 py-2">Счёт</th>
                            <th class="px-2 py-2">Период</th>
                            <th class="px-2 py-2">Строки</th>
                            <th class="px-2 py-2">Суммы</th>
                            <th class="px-2 py-2">Кто загрузил</th>
                            <th class="px-2 py-2 text-right">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in imports"
                            :key="item.id"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td class="px-2 py-2">{{ item.file_name }}</td>
                            <td class="px-2 py-2">
                                <template v-if="item.bank_account">
                                    {{ item.bank_account.bank_name }} {{ item.bank_account.account_mask }}
                                </template>
                                <span v-else class="text-zinc-500">—</span>
                            </td>
                            <td class="px-2 py-2">{{ formatDate(item.period_from) }} — {{ formatDate(item.period_to) }}</td>
                            <td class="px-2 py-2 tabular-nums">{{ item.lines_allocated }} / {{ item.lines_count }}</td>
                            <td class="px-2 py-2 tabular-nums">
                                +{{ formatMoney(item.total_in) }} / −{{ formatMoney(item.total_out) }}
                            </td>
                            <td class="px-2 py-2 text-zinc-600 dark:text-zinc-300">
                                {{ item.importer_name ?? '—' }}
                            </td>
                            <td class="px-2 py-2">
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
                            <td colspan="7" class="px-2 py-6 text-center text-zinc-500">Импортов пока нет</td>
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

<template>
    <div>
        <button
            v-if="canManage"
            type="button"
            :class="crmBtnPrimary"
            @click="open = true"
        >
            Добавить операцию
        </button>

        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="open = false"
        >
            <form
                :class="`${crmPanel} w-full max-w-lg space-y-4 p-5`"
                @submit.prevent="submit"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Ручная операция</h2>
                    <button type="button" class="text-sm text-zinc-500 hover:text-zinc-800" @click="open = false">
                        Закрыть
                    </button>
                </div>

                <div :class="crmModalFieldsWrap">
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                        <label :class="crmModalFieldLabel">Счёт</label>
                        <select
                            v-model="form.bank_account_id"
                            required
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                            <option disabled value="">Выберите счёт</option>
                            <option v-for="account in bankAccounts" :key="account.id" :value="account.id">
                                {{ account.bank_name }} · {{ account.account_mask || account.currency }}
                            </option>
                        </select>
                    </div>
                    <div :class="crmModalFieldRow">
                        <label :class="crmModalFieldLabel">Дата</label>
                        <input
                            v-model="form.operation_date"
                            type="date"
                            required
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                    </div>
                    <div :class="crmModalFieldRow">
                        <label :class="crmModalFieldLabel">Направление</label>
                        <select
                            v-model="form.direction"
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                            <option value="in">Приход</option>
                            <option value="out">Расход</option>
                        </select>
                    </div>
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                        <label :class="crmModalFieldLabel">Сумма</label>
                        <input
                            v-model.number="form.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm tabular-nums dark:border-zinc-600 dark:bg-zinc-950"
                        >
                    </div>
                    <div :class="`${crmModalFieldRow} crm-modal-field-row--wide`">
                        <label :class="crmModalFieldLabel">Разнесение</label>
                        <select
                            v-model="form.allocation_type"
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                            <option value="category">Статья</option>
                            <option value="operational">Операционный</option>
                            <option value="payroll">ФОТ</option>
                        </select>
                    </div>
                    <div v-if="form.allocation_type === 'category'" :class="`${crmModalFieldRow} crm-modal-field-row--full`">
                        <label :class="crmModalFieldLabel">Статья</label>
                        <select
                            v-model="form.category_id"
                            required
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                {{ category.name }}
                            </option>
                        </select>
                    </div>
                    <div v-if="form.allocation_type === 'operational'" :class="crmModalFieldRow">
                        <label :class="crmModalFieldLabel">ID графика</label>
                        <input
                            v-model.number="form.payment_schedule_id"
                            type="number"
                            min="1"
                            required
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                    </div>
                    <div v-if="form.allocation_type === 'payroll'" :class="crmModalFieldRow">
                        <label :class="crmModalFieldLabel">ID сотрудника</label>
                        <input
                            v-model.number="form.user_id"
                            type="number"
                            min="1"
                            required
                            class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                        >
                    </div>
                </div>

                <label class="block w-full space-y-1">
                    <span :class="crmModalFieldLabel">Описание</span>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        required
                        maxlength="2000"
                        class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                    />
                </label>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="crmBtnNeutral" @click="open = false">Отмена</button>
                    <button type="submit" :class="crmBtnPrimary" :disabled="form.processing">
                        {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                    </button>
                </div>
            </form>
        </div>

        <div v-if="recentEntries.length > 0" class="mt-4">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Последние ручные операции</h3>
            <ul class="mt-2 divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                <li
                    v-for="entry in recentEntries"
                    :key="entry.id"
                    class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-xs"
                >
                    <span class="text-zinc-500">{{ formatDate(entry.operation_date) }}</span>
                    <span
                        class="font-semibold tabular-nums"
                        :class="entry.direction === 'in' ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                    >
                        {{ entry.direction === 'in' ? '+' : '−' }}{{ formatMoney(entry.amount) }}
                    </span>
                    <span class="min-w-0 flex-1 truncate text-zinc-600 dark:text-zinc-300">{{ entry.description }}</span>
                    <span class="text-zinc-500">{{ entry.category_name || entry.status }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { crmBtnNeutral, crmBtnPrimary, crmModalFieldLabel, crmModalFieldRow, crmModalFieldsWrap, crmPanel } from '@/support/crmUi.js';

const props = defineProps({
    bankAccounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    recentEntries: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const open = ref(false);

const form = useForm({
    bank_account_id: props.bankAccounts[0]?.id ?? '',
    operation_date: new Date().toISOString().slice(0, 10),
    direction: 'out',
    amount: '',
    currency: 'RUB',
    description: '',
    allocation_type: 'category',
    category_id: props.categories[0]?.id ?? null,
    payment_schedule_id: null,
    user_id: null,
});

function submit() {
    form.post(route('finance.management-accounting.manual-entries.store'), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset('amount', 'description');
        },
    });
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('ru-RU');
}
</script>

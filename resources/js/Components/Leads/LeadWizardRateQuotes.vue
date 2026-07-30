<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { crmBtnPrimary, crmBtnSecondary, crmFieldFluid, crmLabel } from '@/support/crmUi.js';

const props = defineProps({
    leadId: { type: [Number, String], default: null },
    quotes: { type: Array, default: () => [] },
    currencyOptions: { type: Array, default: () => [] },
    paymentFormOptions: { type: Array, default: () => [] },
});

const selectingId = ref(null);

const form = useForm({
    carrier_name: '',
    rate: '',
    currency: 'RUB',
    payment_form: '',
    valid_until: '',
    source: 'manual',
    comment: '',
});

const sourceOptions = [
    { value: 'manual', label: 'Вручную' },
    { value: 'phone', label: 'Телефон' },
    { value: 'ati', label: 'АТИ' },
    { value: 'load_board', label: 'Биржа' },
    { value: 'other', label: 'Другое' },
];

const statusLabels = {
    received: 'Получена',
    selected: 'Выбрана',
    rejected: 'Отклонена',
    expired: 'Истекла',
};

const canManage = computed(() => props.leadId != null && props.leadId !== '');

function formatMoney(value, currency = 'RUB') {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return `${new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(value))} ${currency}`;
}

function statusLabel(status) {
    return statusLabels[status] || status;
}

function submitQuote() {
    if (!canManage.value) {
        return;
    }

    form.post(route('leads.rate-quotes.store', props.leadId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('carrier_name', 'rate', 'payment_form', 'valid_until', 'comment');
            form.source = 'manual';
            form.currency = 'RUB';
        },
    });
}

function selectQuote(quote) {
    if (!canManage.value || quote.status === 'selected') {
        return;
    }

    selectingId.value = quote.id;
    router.post(route('leads.rate-quotes.select', [props.leadId, quote.id]), {}, {
        preserveScroll: true,
        onFinish: () => {
            selectingId.value = null;
        },
    });
}
</script>

<template>
    <section class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
        <div>
            <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-zinc-500 dark:text-zinc-400">
                Котировки перевозчиков
            </h4>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Несколько ставок на один запрос. «Выбрать» подставит ставку в цену перевозчика и в исполнителя.
            </p>
        </div>

        <p v-if="!canManage" class="text-sm text-zinc-500">
            Сохраните лид, чтобы добавлять котировки.
        </p>

        <template v-else>
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-800">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/40">
                        <tr>
                            <th class="px-3 py-2 font-medium">Перевозчик</th>
                            <th class="px-3 py-2 font-medium">Ставка</th>
                            <th class="px-3 py-2 font-medium">Источник</th>
                            <th class="px-3 py-2 font-medium">Статус</th>
                            <th class="px-3 py-2 font-medium" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr v-if="quotes.length === 0">
                            <td colspan="5" class="px-3 py-4 text-zinc-500">Пока нет котировок.</td>
                        </tr>
                        <tr v-for="quote in quotes" :key="quote.id">
                            <td class="px-3 py-2">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ quote.carrier_label }}</div>
                                <div v-if="quote.valid_until" class="text-xs text-zinc-500">до {{ quote.valid_until }}</div>
                            </td>
                            <td class="px-3 py-2 tabular-nums">{{ formatMoney(quote.rate, quote.currency) }}</td>
                            <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ quote.source }}</td>
                            <td class="px-3 py-2">{{ statusLabel(quote.status) }}</td>
                            <td class="px-3 py-2 text-right">
                                <button
                                    v-if="quote.status !== 'selected'"
                                    type="button"
                                    :class="crmBtnSecondary"
                                    :disabled="selectingId === quote.id"
                                    @click="selectQuote(quote)"
                                >
                                    {{ selectingId === quote.id ? '…' : 'Выбрать' }}
                                </button>
                                <span v-else class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Выбрана</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" @submit.prevent="submitQuote">
                <div class="space-y-2 md:col-span-2 xl:col-span-1">
                    <label :class="crmLabel">Перевозчик (название)</label>
                    <input v-model="form.carrier_name" type="text" :class="crmFieldFluid" placeholder="ООО Транс" />
                </div>
                <div class="space-y-2">
                    <label :class="crmLabel">Ставка</label>
                    <input v-model="form.rate" type="number" min="0" step="0.01" required :class="crmFieldFluid" />
                    <p v-if="form.errors.rate" class="text-xs text-rose-600">{{ form.errors.rate }}</p>
                </div>
                <div class="space-y-2">
                    <label :class="crmLabel">Валюта</label>
                    <select v-model="form.currency" :class="crmFieldFluid">
                        <option v-for="option in currencyOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label :class="crmLabel">Форма оплаты</label>
                    <select v-model="form.payment_form" :class="crmFieldFluid">
                        <option value="">Не указано</option>
                        <option v-for="option in paymentFormOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label :class="crmLabel">Действует до</label>
                    <input v-model="form.valid_until" type="date" :class="crmFieldFluid" />
                </div>
                <div class="space-y-2">
                    <label :class="crmLabel">Источник</label>
                    <select v-model="form.source" :class="crmFieldFluid">
                        <option v-for="option in sourceOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="space-y-2 md:col-span-2 xl:col-span-3">
                    <label :class="crmLabel">Комментарий</label>
                    <input v-model="form.comment" type="text" :class="crmFieldFluid" placeholder="Условия, контакты…" />
                </div>
                <div class="md:col-span-2 xl:col-span-3">
                    <button type="submit" :class="crmBtnPrimary" :disabled="form.processing">
                        {{ form.processing ? 'Сохранение…' : 'Добавить котировку' }}
                    </button>
                </div>
            </form>
        </template>
    </section>
</template>

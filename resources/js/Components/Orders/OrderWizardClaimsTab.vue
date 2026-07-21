<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Претензии</h3>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Инциденты по рейсу: простой, порча, срыв срока, спор по ставке. Для Юрика и учёта.
                </p>
            </div>
            <button
                type="button"
                :class="crmBtnPrimary"
                @click="showForm = !showForm"
            >
                {{ showForm ? 'Скрыть форму' : 'Новая претензия' }}
            </button>
        </div>

        <form
            v-if="showForm"
            class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"
            @submit.prevent="submitCreate"
        >
            <div class="grid gap-3 md:grid-cols-3">
                <div class="space-y-1">
                    <label :class="crmLabel">Сторона</label>
                    <select v-model="form.party" :class="crmFieldFluid" required>
                        <option
                            v-for="option in partyOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label :class="crmLabel">Тип</label>
                    <select v-model="form.type" :class="crmFieldFluid" required>
                        <option
                            v-for="option in typeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label :class="crmLabel">Сумма риска</label>
                    <input v-model="form.amount_risk" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                </div>
            </div>
            <div class="space-y-1">
                <label :class="crmLabel">Тема</label>
                <input v-model="form.title" type="text" :class="crmFieldFluid" required maxlength="255" />
            </div>
            <div class="space-y-1">
                <label :class="crmLabel">Описание</label>
                <textarea v-model="form.description" rows="3" :class="crmFieldFluid" />
            </div>
            <p v-if="form.errors.title || form.errors.party" class="text-sm text-rose-600">
                {{ form.errors.title || form.errors.party }}
            </p>
            <div class="flex justify-end gap-2">
                <button type="button" :class="crmBtnSecondary" @click="showForm = false">Отмена</button>
                <button type="submit" :class="crmBtnPrimary" :disabled="form.processing">Создать</button>
            </div>
        </form>

        <div
            v-if="!claims.length"
            class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700"
        >
            Пока нет претензий по этому заказу.
        </div>

        <ul v-else class="space-y-2">
            <li
                v-for="claim in claims"
                :key="claim.id"
                class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ claim.number }} · {{ claim.title }}
                        </div>
                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ claim.party_label }} · {{ claim.type_label }}
                            <span v-if="claim.amount_risk != null">
                                · {{ formatMoney(claim.amount_risk, claim.currency) }}
                            </span>
                        </div>
                    </div>
                    <select
                        class="rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                        :value="claim.status"
                        @change="updateStatus(claim, $event.target.value)"
                    >
                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <p
                    v-if="claim.description"
                    class="mt-2 text-sm text-zinc-600 dark:text-zinc-300"
                >
                    {{ claim.description }}
                </p>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { crmBtnPrimary, crmBtnSecondary, crmFieldFluid, crmLabel } from '@/support/crmUi.js';

const props = defineProps({
    orderId: { type: Number, required: true },
    claims: { type: Array, default: () => [] },
    partyOptions: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
});

const showForm = ref(false);

const form = useForm({
    party: props.partyOptions[0]?.value ?? 'customer',
    type: props.typeOptions[0]?.value ?? 'late',
    status: 'open',
    title: '',
    description: '',
    amount_risk: '',
    currency: 'RUB',
});

function submitCreate() {
    form.post(route('orders.claims.store', props.orderId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title', 'description', 'amount_risk');
            showForm.value = false;
        },
    });
}

function updateStatus(claim, status) {
    if (!status || status === claim.status) {
        return;
    }

    useForm({ status }).patch(route('orders.claims.update', [props.orderId, claim.id]), {
        preserveScroll: true,
    });
}

function formatMoney(amount, currency) {
    const value = Number(amount);

    if (Number.isNaN(value)) {
        return '—';
    }

    return `${value.toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ${currency || 'RUB'}`;
}
</script>

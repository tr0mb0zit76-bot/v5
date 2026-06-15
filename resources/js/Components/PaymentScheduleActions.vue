<template>
    <div class="flex items-center gap-1.5">
        <!-- Кнопка "Зафиксировать платеж" (иконка плюс) -->
        <button
            v-if="canRecordPayment && canRecordMorePayments"
            @click="openRecordPaymentModal"
            class="inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 p-1.5 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/60"
            title="Зафиксировать платеж (можно частично)"
        >
            <Plus class="h-3.5 w-3.5" />
        </button>

        <!-- Кнопка "Показать частичные платежи" (иконка списка) -->
        <button
            v-if="payment.has_partial_payments || payment.is_partial || Number(payment.paid_amount || 0) > 0"
            @click="togglePartialPayments"
            class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-50 p-1.5 text-blue-700 hover:bg-blue-100 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-300 dark:hover:bg-blue-950/60"
            :title="showPartialPayments ? 'Скрыть частичные платежи' : 'Показать частичные платежи'"
        >
            <List class="h-3.5 w-3.5" />
        </button>

        <!-- Кнопка "Отменить" (иконка крестик) -->
        <button
            v-if="canCancelPaymentRow && payment.status !== 'cancelled' && payment.status !== 'paid'"
            @click="cancelPayment"
            class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 p-1.5 text-rose-700 hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950/60"
            title="Отменить платеж"
        >
            <X class="h-3.5 w-3.5" />
        </button>

        <!-- Кнопка "Восстановить" (иконка восстановления) -->
        <button
            v-if="canCancelPaymentRow && payment.status === 'cancelled'"
            @click="restorePayment"
            class="inline-flex items-center justify-center rounded-xl border border-amber-200 bg-amber-50 p-1.5 text-amber-700 hover:bg-amber-100 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-950/60"
            title="Восстановить платеж"
        >
            <RotateCcw class="h-3.5 w-3.5" />
        </button>

        <!-- Прогресс оплаты -->
        <div v-if="payment.payment_progress > 0 && payment.payment_progress < 100" class="ml-1">
            <div class="flex items-center gap-1.5">
                <div class="h-1.5 w-12 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div
                        class="h-full bg-emerald-500"
                        :style="{ width: payment.payment_progress + '%' }"
                    ></div>
                </div>
                <span class="text-xs text-zinc-600 dark:text-zinc-400">
                    {{ payment.payment_progress.toFixed(0) }}%
                </span>
            </div>
        </div>

        <!-- Модальное окно для фиксации платежа -->
        <Teleport to="body">
            <div
                v-if="showRecordPaymentModal"
                class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
                role="dialog"
                aria-modal="true"
                @click.self="showRecordPaymentModal = false"
            >
                <div class="max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto rounded-lg bg-white shadow-xl dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                        Зафиксировать платеж
                    </h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ payment.payment_type === 'incoming' ? 'Поступление от клиента' : 'Оплата перевозчику' }}
                    </p>
                </div>

                <form @submit.prevent="recordPayment" class="space-y-4 px-6 py-4">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Сумма платежа
                        </label>
                        <div class="mt-1">
                            <input
                                v-model.number="paymentData.paid_amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :max="remainingToPay"
                                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500"
                                placeholder="Введите сумму"
                                required
                            />
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                Остаток к оплате: {{ formatMoney(remainingToPay) }}. Можно внести частичную оплату.
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Дата фактической оплаты
                        </label>
                        <input
                            v-model="paymentData.payment_date"
                            type="date"
                            class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Способ оплаты
                        </label>
                        <select
                            v-model="paymentData.payment_method"
                            class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500"
                            required
                        >
                            <option value="">Выберите способ оплаты</option>
                            <option value="bank_transfer">Банковский перевод</option>
                            <option value="cash">Наличные</option>
                            <option value="card">Карта</option>
                            <option value="electronic">Электронный платеж</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Номер транзакции/документа
                        </label>
                        <input
                            v-model="paymentData.transaction_reference"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500"
                            placeholder="Необязательно, но желательно"
                        />
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            Номер платёжного поручения, чека и т.д. — по возможности указывайте для сверки.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Примечание
                        </label>
                        <textarea
                            v-model="paymentData.notes"
                            rows="2"
                            class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500"
                            placeholder="Дополнительная информация"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                        <button
                            type="button"
                            @click="showRecordPaymentModal = false"
                            class="rounded-xl border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        >
                            Отмена
                        </button>
                        <button
                            type="submit"
                            :disabled="processing"
                            :class="crmBtnCreate"
                        >
                            {{ processing ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </form>
                </div>
            </div>
        </Teleport>

        <!-- История оплат и частичные платежи -->
        <div v-if="showPartialPayments" class="mt-3 w-full">
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900/50">
                <h4 class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    История оплат
                </h4>
                <div v-if="paymentEvents.length === 0 && partialPayments.length === 0" class="text-xs text-zinc-500 dark:text-zinc-400">
                    Нет зафиксированных оплат.
                </div>
                <div v-if="paymentEvents.length > 0" class="mb-3 space-y-2">
                    <div
                        v-for="event in paymentEvents"
                        :key="event.id"
                        class="rounded border border-zinc-200 bg-white p-2 text-sm dark:border-zinc-700 dark:bg-zinc-800"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ formatMoney(event.amount) }}
                                </span>
                                <span class="ml-2 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ event.payment_date }}
                                </span>
                                <span v-if="event.payment_method" class="ml-2 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ event.payment_method }}
                                </span>
                                <div v-if="event.transaction_reference" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    № {{ event.transaction_reference }}
                                </div>
                                <div v-if="event.is_management_allocation" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                    Из разнесения выписки — отмена в разделе «Разнесение выписки».
                                </div>
                            </div>
                            <button
                                v-if="canVoidPaymentEvents && !event.is_management_allocation"
                                type="button"
                                class="shrink-0 rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                @click="voidPaymentEvent(event)"
                            >
                                Отменить
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="partialPayments.length > 0" class="space-y-2">
                    <div
                        v-for="partial in partialPayments"
                        :key="partial.id"
                        class="rounded border border-zinc-200 bg-white p-2 text-sm dark:border-zinc-700 dark:bg-zinc-800"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ formatMoney(partial.amount) }}
                                </span>
                                <span class="ml-2 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ partial.actual_date }}
                                </span>
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ partial.payment_method }}
                            </div>
                        </div>
                        <div v-if="partial.transaction_reference" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            № {{ partial.transaction_reference }}
                        </div>
                        <div v-if="partial.notes" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ partial.notes }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Plus, List, X, RotateCcw } from 'lucide-vue-next';
import { crmBtnCreate } from '@/support/crmUi.js';

const props = defineProps({
    payment: {
        type: Object,
        required: true,
    },
    canRecordPayment: {
        type: Boolean,
        default: true,
    },
    canCancelPaymentRow: {
        type: Boolean,
        default: true,
    },
});

const showRecordPaymentModal = ref(false);
const showPartialPayments = ref(false);
const processing = ref(false);
const partialPayments = ref([]);
const paymentEvents = ref([]);
const canVoidPaymentEvents = ref(false);

const remainingToPay = computed(() => {
    const remaining = Number(props.payment.remaining_amount ?? 0);
    const amount = Number(props.payment.amount ?? 0);

    if (remaining > 0) {
        return remaining;
    }

    return amount > 0 ? amount : 0;
});

const canRecordMorePayments = computed(() => {
    if (props.payment.is_partial) {
        return false;
    }

    if (props.payment.status === 'cancelled' || props.payment.status === 'paid') {
        return false;
    }

    return remainingToPay.value > 0;
});

const paymentData = ref(createPaymentFormState());

function createPaymentFormState() {
    return {
        paid_amount: remainingToPay.value,
        payment_date: new Date().toISOString().split('T')[0],
        payment_method: '',
        transaction_reference: '',
        notes: '',
    };
}

function openRecordPaymentModal() {
    paymentData.value = createPaymentFormState();
    showRecordPaymentModal.value = true;
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

function togglePartialPayments() {
    showPartialPayments.value = !showPartialPayments.value;
    if (showPartialPayments.value) {
        loadPartialPayments();
        loadPaymentEvents();
    }
}

async function loadPaymentEvents() {
    if (!props.payment.id) {
        return;
    }

    try {
        const response = await fetch(`/payment-schedules/${props.payment.id}/payment-events`);
        if (response.ok) {
            const data = await response.json();
            paymentEvents.value = data.payment_events || [];
            canVoidPaymentEvents.value = Boolean(data.can_void_payment_events);
        }
    } catch (error) {
        console.error('Ошибка загрузки истории оплат:', error);
    }
}

async function voidPaymentEvent(event) {
    if (!window.confirm(`Отменить оплату ${formatMoney(event.amount)}?`)) {
        return;
    }

    try {
        await axios.post(`/payment-schedules/payment-events/${event.id}/void`, {});
        await loadPaymentEvents();
        await loadPartialPayments();
        router.reload({ only: ['cashFlowJournal', 'cash_flow_stats', 'todays_cash_flow'] });
    } catch (error) {
        const message = error?.response?.data?.message || 'Ошибка при отмене оплаты.';
        window.alert(message);
    }
}

async function loadPartialPayments() {
    if (!props.payment.id) return;

    try {
        const response = await fetch(`/payment-schedules/${props.payment.id}/partial-payments`);
        if (response.ok) {
            const data = await response.json();
            partialPayments.value = data.partial_payments || [];
        }
    } catch (error) {
        console.error('Ошибка загрузки частичных платежей:', error);
    }
}

async function recordPayment() {
    const paidAmount = Number(paymentData.value.paid_amount);

    if (!Number.isFinite(paidAmount) || paidAmount <= 0) {
        window.alert('Укажите сумму платежа больше 0.');

        return;
    }

    if (paidAmount > remainingToPay.value + 0.009) {
        window.alert(`Сумма не может превышать остаток (${formatMoney(remainingToPay.value)}).`);

        return;
    }

    processing.value = true;

    try {
        await axios.post(`/payment-schedules/${props.payment.id}/record-payment`, {
            paid_amount: paidAmount,
            payment_date: paymentData.value.payment_date,
            payment_method: paymentData.value.payment_method,
            transaction_reference: paymentData.value.transaction_reference?.trim() || null,
            notes: paymentData.value.notes?.trim() || null,
        });

        showRecordPaymentModal.value = false;
        router.reload({ only: ['cashFlowJournal', 'cash_flow_stats', 'todays_cash_flow'] });
    } catch (error) {
        const message = error?.response?.data?.message
            || Object.values(error?.response?.data?.errors || {}).flat().join('\n')
            || 'Ошибка при сохранении платежа. Проверьте введённые данные.';
        window.alert(message);
    } finally {
        processing.value = false;
    }
}

async function cancelPayment() {
    if (!confirm('Вы уверены, что хотите отменить этот платеж?')) return;

    try {
        await router.post(`/payment-schedules/${props.payment.id}/cancel`, {}, {
            preserveScroll: true,
            only: ['cashFlowJournal', 'cash_flow_stats', 'todays_cash_flow'],
        });
    } catch (error) {
        console.error('Ошибка при отмене платежа:', error);
        alert('Ошибка при отмене платежа.');
    }
}

async function restorePayment() {
    try {
        await router.post(`/payment-schedules/${props.payment.id}/restore`, {}, {
            preserveScroll: true,
            only: ['cashFlowJournal', 'cash_flow_stats', 'todays_cash_flow'],
        });
    } catch (error) {
        console.error('Ошибка при восстановлении платежа:', error);
        alert('Ошибка при восстановлении платежа.');
    }
}

onMounted(() => {
    if (props.payment.has_partial_payments || props.payment.is_partial || Number(props.payment.paid_amount || 0) > 0) {
        loadPartialPayments();
        loadPaymentEvents();
    }
});
</script>

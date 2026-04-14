<template>
    <div class="flex items-center gap-1.5">
        <!-- Кнопка "Зафиксировать платеж" (иконка плюс) -->
        <button
            v-if="!payment.is_partial && payment.status !== 'paid' && payment.status !== 'cancelled'"
            @click="showRecordPaymentModal = true"
            class="inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 p-1.5 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/60"
            title="Зафиксировать платеж"
        >
            <Plus class="h-3.5 w-3.5" />
        </button>

        <!-- Кнопка "Показать частичные платежи" (иконка списка) -->
        <button
            v-if="payment.has_partial_payments || payment.is_partial"
            @click="togglePartialPayments"
            class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-50 p-1.5 text-blue-700 hover:bg-blue-100 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-300 dark:hover:bg-blue-950/60"
            :title="showPartialPayments ? 'Скрыть частичные платежи' : 'Показать частичные платежи'"
        >
            <List class="h-3.5 w-3.5" />
        </button>

        <!-- Кнопка "Отменить" (иконка крестик) -->
        <button
            v-if="payment.status !== 'cancelled' && payment.status !== 'paid'"
            @click="cancelPayment"
            class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 p-1.5 text-rose-700 hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950/60"
            title="Отменить платеж"
        >
            <X class="h-3.5 w-3.5" />
        </button>

        <!-- Кнопка "Восстановить" (иконка восстановления) -->
        <button
            v-if="payment.status === 'cancelled'"
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
                                type="number"
                                step="0.01"
                                v-model="paymentData.amount"
                                :max="payment.remaining_amount || payment.amount"
                                class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500"
                                placeholder="Введите сумму"
                                required
                            />
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                Остаток к оплате: {{ formatMoney(payment.remaining_amount || payment.amount) }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Дата фактической оплаты
                        </label>
                        <input
                            type="date"
                            v-model="paymentData.actual_date"
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
                            type="text"
                            v-model="paymentData.transaction_reference"
                            class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500"
                            placeholder="Номер платежного поручения, чека и т.д."
                        />
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
                            class="rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50 dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        >
                            {{ processing ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </form>
                </div>
            </div>
        </Teleport>

        <!-- Список частичных платежей -->
        <div v-if="showPartialPayments && partialPayments.length > 0" class="mt-3 w-full">
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900/50">
                <h4 class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Частичные платежи
                </h4>
                <div class="space-y-2">
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
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Plus, List, X, RotateCcw } from 'lucide-vue-next';

const props = defineProps({
    payment: {
        type: Object,
        required: true,
    },
});

const showRecordPaymentModal = ref(false);
const showPartialPayments = ref(false);
const processing = ref(false);
const partialPayments = ref([]);

const paymentData = ref({
    amount: props.payment.remaining_amount || props.payment.amount,
    actual_date: new Date().toISOString().split('T')[0],
    payment_method: '',
    transaction_reference: '',
    notes: '',
});

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

function togglePartialPayments() {
    showPartialPayments.value = !showPartialPayments.value;
    if (showPartialPayments.value && partialPayments.value.length === 0) {
        loadPartialPayments();
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
    processing.value = true;

    try {
        await router.post(`/payment-schedules/${props.payment.id}/record-payment`, paymentData.value, {
            onSuccess: () => {
                showRecordPaymentModal.value = false;
                // Перезагружаем страницу для обновления данных
                router.reload({ only: ['cashFlowJournal'] });
            },
            onError: (errors) => {
                console.error('Ошибка при сохранении платежа:', errors);
                alert('Ошибка при сохранении платежа. Проверьте введенные данные.');
            },
        });
    } catch (error) {
        console.error('Ошибка при сохранении платежа:', error);
        alert('Ошибка при сохранении платежа.');
    } finally {
        processing.value = false;
    }
}

async function cancelPayment() {
    if (!confirm('Вы уверены, что хотите отменить этот платеж?')) return;

    try {
        await router.post(`/payment-schedules/${props.payment.id}/cancel`, {}, {
            onSuccess: () => {
                router.reload({ only: ['cashFlowJournal'] });
            },
        });
    } catch (error) {
        console.error('Ошибка при отмене платежа:', error);
        alert('Ошибка при отмене платежа.');
    }
}

async function restorePayment() {
    try {
        await router.post(`/payment-schedules/${props.payment.id}/restore`, {}, {
            onSuccess: () => {
                router.reload({ only: ['cashFlowJournal'] });
            },
        });
    } catch (error) {
        console.error('Ошибка при восстановлении платежа:', error);
        alert('Ошибка при восстановлении платежа.');
    }
}

onMounted(() => {
    if (props.payment.has_partial_payments || props.payment.is_partial) {
        loadPartialPayments();
    }
});
</script>

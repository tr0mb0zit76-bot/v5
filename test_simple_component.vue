<template>
    <div class="flex items-center gap-2">
        <!-- Простая кнопка для тестирования -->
        <button class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white">
            Тест
        </button>
        
        <!-- Отображение данных платежа -->
        <div class="text-xs text-zinc-500">
            ID: {{ payment.id }}, Статус: {{ payment.status }}, Сумма: {{ payment.amount }}
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    payment: {
        type: Object,
        required: true,
    },
});

console.log('PaymentScheduleActions component loaded');
console.log('Payment data:', props.payment);
</script>
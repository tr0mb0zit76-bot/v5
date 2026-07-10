<script setup>
defineProps({
    isEditing: { type: Boolean, default: false },
    isOrderFormEditable: { type: Boolean, default: true },
    canViewOrderDocuments: { type: Boolean, default: false },
    canEditFinancialFields: { type: Boolean, default: true },
    saveAttempted: { type: Boolean, default: false },
    coreValidationIssues: { type: Array, default: () => [] },
});
</script>

<template>
    <p
        v-if="isEditing && !isOrderFormEditable && !canViewOrderDocuments"
        class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
        role="status"
    >
        Редактирование заказа недоступно: все печатные заявки по заказу доведены до финального PDF. Данные можно просматривать; изменения не сохраняются.
    </p>
    <p
        v-else-if="isEditing && !isOrderFormEditable && canViewOrderDocuments"
        class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
        role="status"
    >
        Редактирование заказа недоступно: все печатные заявки по заказу доведены до финального PDF. Документы доступны на вкладке «Документы».
    </p>
    <p
        v-else-if="isEditing && isOrderFormEditable && !canEditFinancialFields"
        class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
        role="status"
    >
        Стоимость перевозки и финансовые условия недоступны для изменения, пока рейс в статусе «Выполняется». Остальные поля заказа можно редактировать.
    </p>
    <p v-if="saveAttempted && coreValidationIssues.length > 0" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200">
        Не удалось сохранить: заполните {{ coreValidationIssues.join(', ') }}.
    </p>
</template>

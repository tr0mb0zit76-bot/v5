<script setup>
import { computed } from 'vue';
import OrderBasicTermsEditor from '@/Components/Orders/OrderBasicTermsEditor.vue';

const props = defineProps({
    order: { type: Object, default: null },
    basicTerms: { type: Object, default: null },
    canPromoteBasicTerms: { type: Boolean, default: false },
    isOrderFormEditable: { type: Boolean, default: true },
    showCustomer: { type: Boolean, default: false },
    showCarrier: { type: Boolean, default: false },
});

const basicTermsDraft = defineModel('basicTermsDraft', {
    type: Object,
    default: () => ({ dirty: false, customer_basic_terms: undefined, carrier_basic_terms: undefined }),
});

const hasAnySection = computed(() => props.showCustomer || props.showCarrier);

function onCustomerItems(items) {
    basicTermsDraft.value.dirty = true;
    basicTermsDraft.value.customer_basic_terms = items;
}

function onCarrierItems(items) {
    basicTermsDraft.value.dirty = true;
    basicTermsDraft.value.carrier_basic_terms = items;
}
</script>

<template>
    <div class="space-y-4">
        <div>
            <h2 class="text-base font-semibold">Нормы заявки</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Базовые условия для выбранных на вкладке «Документы» печатных форм с соответствующими плейсхолдерами.
            </p>
        </div>

        <div
            v-if="!hasAnySection"
            class="rounded-2xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
        >
            Выберите на вкладке «Документы» шаблон с плейсхолдерами базовых условий — тогда здесь можно будет их отредактировать.
        </div>

        <template v-else-if="basicTerms">
            <OrderBasicTermsEditor
                v-if="showCustomer"
                party="customer"
                label="Заказчик"
                :meta="basicTerms.customer"
                :order-id="order?.id ?? null"
                :editable="isOrderFormEditable"
                :can-promote="canPromoteBasicTerms"
                @update:items="onCustomerItems"
            />
            <OrderBasicTermsEditor
                v-if="showCarrier"
                party="carrier"
                label="Перевозчик"
                :meta="basicTerms.carrier"
                :order-id="order?.id ?? null"
                :editable="isOrderFormEditable"
                :can-promote="canPromoteBasicTerms"
                @update:items="onCarrierItems"
            />
        </template>
    </div>
</template>

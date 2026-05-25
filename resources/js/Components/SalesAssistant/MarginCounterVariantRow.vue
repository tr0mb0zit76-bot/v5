<template>
    <div class="text-sm">
        <div class="font-medium text-zinc-700 dark:text-zinc-300">{{ label }}</div>
        <div class="mt-1 grid gap-1 text-zinc-600 dark:text-zinc-400">
            <div>
                Без НДС:
                <span class="font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(variants.without_vat) }}</span>
            </div>
            <div>
                С НДС<span v-if="variants.vat_label"> ({{ variants.vat_label }})</span>:
                <span class="font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ formatMoney(variants.with_vat) }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    variants: { type: Object, required: true },
});

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 2 }).format(Number(value ?? 0));
}
</script>

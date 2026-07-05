<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    orderId: { type: Number, required: true },
    contractorId: { type: [Number, String, null], default: null },
    externalParty: { type: String, required: true },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: 'Написать в Traklo' },
});

const loading = ref(false);
const error = ref('');

const canOpen = computed(() => !props.disabled && props.orderId > 0 && Number(props.contractorId) > 0);

function messengerUrl() {
    const params = new URLSearchParams({
        counterparty_contractor_id: String(Number(props.contractorId)),
        counterparty_party: props.externalParty,
        order_id: String(props.orderId),
    });

    return `${route('mobile.messenger.app')}?${params.toString()}`;
}

async function openChat() {
    if (!canOpen.value || loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const response = await fetch(route('messenger.conversations.open-counterparty'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                contractor_id: Number(props.contractorId),
                external_party: props.externalParty,
                order_id: props.orderId,
            }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            error.value = data.message
                ?? Object.values(data.errors ?? {}).flat().join(' ')
                ?? 'Не удалось открыть чат. Пригласите контакт в Traklo.';

            return;
        }

        const conversationId = Number(data.conversation?.id ?? 0);
        const url = conversationId > 0
            ? `${route('mobile.messenger.app')}?conversation_id=${conversationId}`
            : messengerUrl();

        window.open(url, '_blank', 'noopener');
    } catch {
        error.value = 'Ошибка сети. Попробуйте ещё раз.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="inline-flex flex-col items-end gap-1">
        <button
            type="button"
            class="rounded-lg border border-emerald-200 px-2 py-1 text-[11px] font-medium text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
            :disabled="!canOpen || loading"
            :title="!orderId ? 'Сначала сохраните заказ' : (!contractorId ? 'Выберите контрагента' : 'Открыть чат с контактом в Traklo')"
            @click="openChat"
        >
            {{ loading ? '…' : label }}
        </button>
        <p v-if="error" class="max-w-[14rem] text-right text-[10px] text-rose-500">{{ error }}</p>
    </div>
</template>

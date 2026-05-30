<script setup>
import { computed, ref } from 'vue';
import { toStageKey } from '@/support/orderStageKey.js';

const props = defineProps({
    orderId: { type: Number, required: true },
    stage: { type: String, required: true },
    contractorId: { type: [Number, String, null], default: null },
    carrierSlot: { type: Number, default: 1 },
    disabled: { type: Boolean, default: false },
});

const modalOpen = ref(false);
const loading = ref(false);
const error = ref('');
const inviteUrl = ref('');
const linkValidityHint = ref('');
const copied = ref(false);

const canInvite = computed(() => !props.disabled && props.orderId > 0 && Number(props.contractorId) > 0);

function formatErrorMessage(data) {
    if (data?.errors && typeof data.errors === 'object') {
        return Object.values(data.errors).flat().join(' ');
    }

    return data?.message ?? 'Не удалось создать ссылку.';
}

async function createInvite() {
    if (!canInvite.value || loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';
    copied.value = false;

    try {
        const response = await fetch(route('orders.portal-invites.carrier.store', props.orderId), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                contractor_id: Number(props.contractorId),
                stage: toStageKey(props.stage),
                carrier_slot: Number(props.carrierSlot) > 0 ? Number(props.carrierSlot) : 1,
            }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            error.value = formatErrorMessage(data);
            return;
        }

        inviteUrl.value = data.url ?? '';
        linkValidityHint.value = data.link_validity_hint ?? 'Ссылка действует до проставления фактической даты выгрузки по заказу.';
        modalOpen.value = true;
    } catch {
        error.value = 'Ошибка сети. Попробуйте ещё раз.';
    } finally {
        loading.value = false;
    }
}

async function copyLink() {
    if (!inviteUrl.value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(inviteUrl.value);
        copied.value = true;
    } catch {
        copied.value = false;
    }
}

function mailtoLink() {
    if (!inviteUrl.value) {
        return '';
    }

    const subject = encodeURIComponent('Ссылка для заполнения данных по перевозке');
    const body = encodeURIComponent(`Здравствуйте!\n\nЗаполните данные по ТС и водителю по ссылке:\n${inviteUrl.value}\n`);

    return `mailto:?subject=${subject}&body=${body}`;
}
</script>

<template>
    <div class="inline-flex flex-col items-end gap-1">
        <button
            type="button"
            class="rounded-lg border border-sky-200 px-2 py-1 text-[11px] font-medium text-sky-700 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-sky-900 dark:text-sky-300 dark:hover:bg-sky-950/40"
            :disabled="!canInvite || loading"
            :title="!orderId ? 'Сначала сохраните заказ' : (!contractorId ? 'Выберите перевозчика' : 'Ссылка для перевозчика')"
            @click="createInvite"
        >
            {{ loading ? '…' : 'Ссылка перевозчику' }}
        </button>
        <p v-if="error" class="max-w-[12rem] text-right text-[10px] text-rose-500">{{ error }}</p>
    </div>

    <Teleport to="body">
        <div
            v-if="modalOpen"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 px-4"
            @click.self="modalOpen = false"
        >
            <div class="w-full max-w-lg rounded-2xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Ссылка для перевозчика</h3>
                <p class="mt-1 text-sm text-zinc-500">Отправьте ссылку перевозчику — он заполнит ТС и водителя без входа в CRM.</p>
                <p v-if="linkValidityHint" class="mt-2 text-xs text-zinc-500">{{ linkValidityHint }}</p>

                <input
                    :value="inviteUrl"
                    type="text"
                    readonly
                    class="mt-4 w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                    @focus="$event.target.select()"
                />

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-xl bg-zinc-900 px-3 py-2 text-sm text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900"
                        @click="copyLink"
                    >
                        {{ copied ? 'Скопировано' : 'Копировать' }}
                    </button>
                    <a
                        v-if="inviteUrl"
                        :href="mailtoLink()"
                        class="rounded-xl border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    >
                        Письмо
                    </a>
                    <button
                        type="button"
                        class="ml-auto rounded-xl px-3 py-2 text-sm text-zinc-500 hover:text-zinc-800"
                        @click="modalOpen = false"
                    >
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

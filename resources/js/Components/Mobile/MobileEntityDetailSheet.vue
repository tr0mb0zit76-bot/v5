<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end bg-black/60 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))]"
        @click.self="$emit('close')"
    >
        <div class="max-h-[78dvh] w-full overflow-hidden rounded-3xl border border-white/10 bg-zinc-900 shadow-xl">
            <div class="border-b border-white/10 px-4 py-3">
                <div class="flex items-start gap-2">
                    <component :is="iconForKind(entity?.kind)" class="mt-0.5 h-5 w-5 shrink-0 text-sky-300" />
                    <div class="min-w-0 flex-1">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-sky-200">
                            {{ entityKindLabel(entity?.kind) }}
                        </div>
                        <div class="mt-0.5 text-base font-semibold text-zinc-50">{{ entity?.label }}</div>
                        <div v-if="entity?.subtitle" class="mt-1 text-xs text-zinc-400">{{ entity.subtitle }}</div>
                    </div>
                </div>
            </div>

            <div class="max-h-[46dvh] overflow-y-auto px-4 py-3">
                <div v-if="loading" class="py-6 text-center text-sm text-zinc-500">Загрузка…</div>

                <template v-else-if="entity?.kind === 'order' && orderSummary">
                    <div class="space-y-3 text-sm text-zinc-300">
                        <div v-if="orderSummary.order?.status">
                            <span class="text-zinc-500">Статус:</span> {{ orderSummary.order.status }}
                        </div>
                        <div v-if="orderSummary.order?.customer_name">
                            <span class="text-zinc-500">Заказчик:</span> {{ orderSummary.order.customer_name }}
                        </div>
                        <div v-if="orderSummary.order?.carrier_name">
                            <span class="text-zinc-500">Перевозчик:</span> {{ orderSummary.order.carrier_name }}
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Документы</div>
                            <div class="mt-1 text-sm text-zinc-100">
                                {{ orderSummary.documents?.completed_count ?? 0 }} / {{ orderSummary.documents?.total_count ?? 0 }} закрыто
                            </div>
                            <ul v-if="orderSummary.documents?.pending?.length" class="mt-2 space-y-1 text-xs text-amber-100">
                                <li v-for="(item, index) in orderSummary.documents.pending" :key="`pending-${index}`">
                                    · {{ item.label }}
                                </li>
                            </ul>
                            <div v-else-if="(orderSummary.documents?.pending_count ?? 0) === 0" class="mt-2 text-xs text-emerald-300">
                                Все обязательные слоты закрыты
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else-if="entity?.meta?.length">
                    <div v-for="(row, index) in entity.meta" :key="`meta-${index}`" class="py-1 text-sm text-zinc-300">
                        <span class="text-zinc-500">{{ row.label }}:</span> {{ row.value }}
                    </div>
                </template>
            </div>

            <div class="space-y-2 border-t border-white/10 p-3">
                <a
                    v-if="entity?.url"
                    :href="entity.url"
                    class="flex w-full items-center justify-center rounded-2xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white active:bg-sky-500"
                >
                    Открыть в CRM
                </a>
                <button
                    v-if="entity?.kind === 'order'"
                    type="button"
                    class="w-full rounded-2xl border border-white/10 px-4 py-3 text-sm font-medium text-zinc-100 active:bg-white/10"
                    @click="$emit('upload-document', entity.id)"
                >
                    Прикрепить документ
                </button>
                <button
                    v-if="entity?.kind === 'task' && entity?.responsibleId && entity.responsibleId !== currentUserId"
                    type="button"
                    class="w-full rounded-2xl border border-white/10 px-4 py-3 text-sm font-medium text-zinc-100 active:bg-white/10"
                    @click="$emit('message-responsible', { userId: entity.responsibleId, name: entity.responsibleName })"
                >
                    Написать ответственному
                </button>
                <button
                    v-if="entity?.orderUrl"
                    type="button"
                    class="w-full rounded-2xl border border-white/10 px-4 py-3 text-sm font-medium text-zinc-100 active:bg-white/10"
                    @click="$emit('share', { url: entity.orderUrl, label: entity.orderLabel ?? 'Заказ' })"
                >
                    Отправить заказ в чат
                </button>
                <button
                    v-if="entity?.leadUrl"
                    type="button"
                    class="w-full rounded-2xl border border-white/10 px-4 py-3 text-sm font-medium text-zinc-100 active:bg-white/10"
                    @click="$emit('share', { url: entity.leadUrl, label: entity.leadLabel ?? 'Лид' })"
                >
                    Отправить лид в чат
                </button>
                <button
                    type="button"
                    class="w-full rounded-2xl border border-white/10 px-4 py-3 text-sm font-medium text-zinc-100 active:bg-white/10"
                    @click="$emit('share', { url: entity?.url, label: entity?.label })"
                >
                    Отправить в чат
                </button>
                <button
                    type="button"
                    class="w-full rounded-2xl px-4 py-3 text-sm text-zinc-400 active:bg-white/10"
                    @click="$emit('close')"
                >
                    Закрыть
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { CheckSquare, FileText, Package, UserRound, Users } from 'lucide-vue-next';
import { entityKindLabel } from '@/support/mobileMessageLinks.js';

defineProps({
    open: { type: Boolean, default: false },
    entity: { type: Object, default: null },
    orderSummary: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    currentUserId: { type: Number, default: null },
});

defineEmits(['close', 'share', 'upload-document', 'message-responsible']);

function iconForKind(kind) {
    if (kind === 'order') {
        return Package;
    }

    if (kind === 'lead') {
        return Users;
    }

    if (kind === 'contractor') {
        return UserRound;
    }

    if (kind === 'task') {
        return CheckSquare;
    }

    return FileText;
}
</script>

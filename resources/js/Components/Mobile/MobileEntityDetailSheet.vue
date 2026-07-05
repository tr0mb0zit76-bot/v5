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

                <template v-else-if="entity?.kind === 'lead' && entitySummary?.lead">
                    <div class="space-y-3 text-sm text-zinc-300">
                        <div v-if="entitySummary.lead.status_label">
                            <span class="text-zinc-500">Статус:</span> {{ entitySummary.lead.status_label }}
                        </div>
                        <div v-if="entitySummary.lead.responsible_name">
                            <span class="text-zinc-500">Ответственный:</span> {{ entitySummary.lead.responsible_name }}
                        </div>
                        <div v-else-if="entitySummary.lead.source === 'traklo_public_request'" class="text-xs text-amber-200">
                            Заявка ещё не назначена — правки сохраняются как черновик.
                        </div>

                        <template v-if="entitySummary.lead.editable">
                            <label class="block text-xs text-zinc-500">
                                Откуда
                                <input
                                    v-model="leadDraft.loading_location"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border border-white/10 bg-zinc-950 px-3 py-2 text-sm text-zinc-50 outline-none focus:border-sky-500"
                                />
                            </label>
                            <label class="block text-xs text-zinc-500">
                                Куда
                                <input
                                    v-model="leadDraft.unloading_location"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border border-white/10 bg-zinc-950 px-3 py-2 text-sm text-zinc-50 outline-none focus:border-sky-500"
                                />
                            </label>
                            <label class="block text-xs text-zinc-500">
                                Груз
                                <input
                                    v-model="leadDraft.cargo"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border border-white/10 bg-zinc-950 px-3 py-2 text-sm text-zinc-50 outline-none focus:border-sky-500"
                                />
                            </label>
                            <label class="block text-xs text-zinc-500">
                                Телефон
                                <input
                                    v-model="leadDraft.phone"
                                    type="tel"
                                    class="mt-1 w-full rounded-xl border border-white/10 bg-zinc-950 px-3 py-2 text-sm text-zinc-50 outline-none focus:border-sky-500"
                                />
                            </label>
                            <label class="block text-xs text-zinc-500">
                                Контакт
                                <input
                                    v-model="leadDraft.contact_name"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border border-white/10 bg-zinc-950 px-3 py-2 text-sm text-zinc-50 outline-none focus:border-sky-500"
                                />
                            </label>
                            <label class="block text-xs text-zinc-500">
                                Компания
                                <input
                                    v-model="leadDraft.company_name"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border border-white/10 bg-zinc-950 px-3 py-2 text-sm text-zinc-50 outline-none focus:border-sky-500"
                                />
                            </label>
                        </template>

                        <template v-else>
                            <div v-if="entitySummary.lead.loading_location || entitySummary.lead.unloading_location">
                                <span class="text-zinc-500">Маршрут:</span>
                                {{ entitySummary.lead.loading_location || '—' }} → {{ entitySummary.lead.unloading_location || '—' }}
                            </div>
                            <div v-if="entitySummary.lead.cargo">
                                <span class="text-zinc-500">Груз:</span> {{ entitySummary.lead.cargo }}
                            </div>
                            <div v-if="entitySummary.lead.phone">
                                <span class="text-zinc-500">Телефон:</span> {{ entitySummary.lead.phone }}
                            </div>
                            <div v-if="entitySummary.lead.contact_name">
                                <span class="text-zinc-500">Контакт:</span> {{ entitySummary.lead.contact_name }}
                            </div>
                            <div v-if="entitySummary.lead.company_name">
                                <span class="text-zinc-500">Компания:</span> {{ entitySummary.lead.company_name }}
                            </div>
                        </template>

                        <div v-if="entitySummary.lead.raw_text" class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Исходный текст</div>
                            <p class="mt-2 whitespace-pre-wrap text-xs leading-5 text-zinc-300">{{ entitySummary.lead.raw_text }}</p>
                        </div>
                    </div>
                </template>

                <template v-else-if="entity?.kind === 'contractor' && entitySummary?.contractor">
                    <div class="space-y-3 text-sm text-zinc-300">
                        <div v-if="entitySummary.contractor.inn">
                            <span class="text-zinc-500">ИНН:</span> {{ entitySummary.contractor.inn }}
                        </div>
                        <div v-if="entitySummary.contractor.phone">
                            <span class="text-zinc-500">Телефон:</span> {{ entitySummary.contractor.phone }}
                        </div>
                        <div v-if="entitySummary.contractor.contact_person">
                            <span class="text-zinc-500">Контакт:</span> {{ entitySummary.contractor.contact_person }}
                            <span v-if="entitySummary.contractor.contact_person_phone"> · {{ entitySummary.contractor.contact_person_phone }}</span>
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
                <button
                    v-if="entity?.kind === 'lead' && entitySummary?.lead?.editable"
                    type="button"
                    class="flex w-full items-center justify-center rounded-2xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50 active:bg-sky-500"
                    :disabled="leadSaving"
                    @click="saveLeadDraft"
                >
                    {{ leadSaving ? 'Сохраняем…' : 'Сохранить' }}
                </button>
                <p v-if="leadSaveError" class="text-center text-xs text-rose-300">{{ leadSaveError }}</p>
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
import { reactive, ref, watch } from 'vue';
import { entityKindLabel } from '@/support/mobileMessageLinks.js';

const props = defineProps({
    open: { type: Boolean, default: false },
    entity: { type: Object, default: null },
    orderSummary: { type: Object, default: null },
    entitySummary: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    currentUserId: { type: Number, default: null },
    leadSaving: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'share', 'upload-document', 'message-responsible', 'save-lead-draft']);

const leadDraft = reactive({
    loading_location: '',
    unloading_location: '',
    cargo: '',
    phone: '',
    contact_name: '',
    company_name: '',
});

const leadSaveError = ref('');

watch(
    () => props.entitySummary?.lead,
    (lead) => {
        leadSaveError.value = '';

        if (! lead) {
            return;
        }

        leadDraft.loading_location = lead.loading_location ?? '';
        leadDraft.unloading_location = lead.unloading_location ?? '';
        leadDraft.cargo = lead.cargo ?? '';
        leadDraft.phone = lead.phone ?? '';
        leadDraft.contact_name = lead.contact_name ?? '';
        leadDraft.company_name = lead.company_name ?? '';
    },
    { immediate: true },
);

function saveLeadDraft() {
    if (! props.entity?.id) {
        return;
    }

    leadSaveError.value = '';
    emit('save-lead-draft', {
        leadId: props.entity.id,
        payload: {
            loading_location: leadDraft.loading_location.trim() || null,
            unloading_location: leadDraft.unloading_location.trim() || null,
            cargo: leadDraft.cargo.trim() || null,
            phone: leadDraft.phone.trim() || null,
            contact_name: leadDraft.contact_name.trim() || null,
            company_name: leadDraft.company_name.trim() || null,
        },
    });
}

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

defineExpose({ setLeadSaveError: (message) => { leadSaveError.value = message; } });
</script>

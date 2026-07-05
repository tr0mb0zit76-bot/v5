<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, Copy, Link2, Plus, Save, Star, UserCircle, X } from 'lucide-vue-next';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmFieldFluid,
} from '@/support/crmUi.js';

const props = defineProps({
    contractorId: {
        type: Number,
        required: true,
    },
    portrait: {
        type: Object,
        required: true,
    },
    contacts: {
        type: Array,
        default: () => [],
    },
    contractorType: {
        type: String,
        default: null,
    },
    portraitOptions: {
        type: Object,
        required: true,
    },
    interactions: {
        type: Array,
        default: () => [],
    },
    insightDrafts: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['portrait-updated', 'open-communications', 'record-interaction']);

const portraitForm = useForm({
    communication_style: props.portrait.communication_style ?? 'unknown',
    price_sensitivity: props.portrait.price_sensitivity ?? 'unknown',
    preferred_channel: props.portrait.preferred_channel ?? 'unknown',
    decision_cadence: props.portrait.decision_cadence ?? 'unknown',
    relationship_trust: props.portrait.relationship_trust ?? 'unknown',
    success_criteria: props.portrait.success_criteria ?? '',
    typical_objections: [...(props.portrait.typical_objections ?? [])],
    internal_notes: props.portrait.internal_notes ?? '',
});

watch(
    () => props.portrait,
    (value) => {
        portraitForm.defaults({
            communication_style: value.communication_style ?? 'unknown',
            price_sensitivity: value.price_sensitivity ?? 'unknown',
            preferred_channel: value.preferred_channel ?? 'unknown',
            decision_cadence: value.decision_cadence ?? 'unknown',
            relationship_trust: value.relationship_trust ?? 'unknown',
            success_criteria: value.success_criteria ?? '',
            typical_objections: [...(value.typical_objections ?? [])],
            internal_notes: value.internal_notes ?? '',
        });
        portraitForm.reset();
    },
    { deep: true },
);

const coveragePct = computed(() => Number(props.portrait.coverage_pct ?? 0));
const missingSlots = computed(() => props.portrait.missing_slots ?? []);
const recentInteractions = computed(() => (props.interactions ?? []).slice(0, 5));
const pendingInsightDrafts = ref([...(props.insightDrafts ?? [])]);
const insightDraftBusyId = ref(null);
const trakloBusyContactId = ref(null);
const trakloInviteUrl = ref('');
const trakloInviteMessage = ref('');
const needsPartyChoice = computed(() => ['both', 'contractor'].includes(String(props.contractorType ?? '').toLowerCase()));
const inviteParty = ref('carrier');

const setTrakloPrimary = async (contact) => {
    if (!contact?.id) {
        return;
    }

    trakloBusyContactId.value = contact.id;
    trakloInviteMessage.value = '';

    try {
        await axios.post(route('contractors.contacts.traklo.primary', [props.contractorId, contact.id]));
        router.reload({ only: ['selectedContractor'], preserveScroll: true });
    } catch (error) {
        trakloInviteMessage.value = error?.response?.data?.message ?? 'Не удалось отметить основной контакт.';
    } finally {
        trakloBusyContactId.value = null;
    }
};

const inviteToTraklo = async (contact) => {
    if (!contact?.id) {
        return;
    }

    trakloBusyContactId.value = contact.id;
    trakloInviteMessage.value = '';
    trakloInviteUrl.value = '';

    try {
        const payload = needsPartyChoice.value ? { external_party: inviteParty.value } : {};
        const { data } = await axios.post(route('contractors.contacts.traklo.invite', [props.contractorId, contact.id]), payload);
        trakloInviteUrl.value = data.url ?? '';
        trakloInviteMessage.value = data.created ? 'Ссылка создана. Отправьте её контакту.' : 'Новая ссылка для существующего пользователя.';
    } catch (error) {
        const errors = error?.response?.data?.errors ?? {};
        trakloInviteMessage.value =
            errors.email?.[0] ?? errors.external_party?.[0] ?? error?.response?.data?.message ?? 'Не удалось создать приглашение.';
    } finally {
        trakloBusyContactId.value = null;
    }
};

const copyInviteUrl = async () => {
    if (!trakloInviteUrl.value || !navigator?.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(trakloInviteUrl.value);
    trakloInviteMessage.value = 'Ссылка скопирована в буфер обмена.';
};

watch(
    () => props.insightDrafts,
    (value) => {
        pendingInsightDrafts.value = [...(value ?? [])];
    },
    { deep: true },
);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function reviewInsightDraft(draftId, action) {
    if (insightDraftBusyId.value !== null) {
        return;
    }

    insightDraftBusyId.value = draftId;

    try {
        const routeName = action === 'accept'
            ? 'contractors.insight-drafts.accept'
            : 'contractors.insight-drafts.reject';

        const response = await fetch(route(routeName, [props.contractorId, draftId]), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Не удалось обработать предложение');
        }

        pendingInsightDrafts.value = pendingInsightDrafts.value.filter((item) => item.id !== draftId);
        router.reload({ only: ['selectedContractor'], preserveScroll: true });
        emit('portrait-updated');
    } catch (error) {
        window.alert(error?.message || 'Ошибка обработки предложения');
    } finally {
        insightDraftBusyId.value = null;
    }
}

function formatContactedAt(value) {
    if (!value) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

const objectionInput = ref('');

function addObjectionTag() {
    const tag = objectionInput.value.trim();
    if (!tag || portraitForm.typical_objections.includes(tag)) {
        return;
    }

    portraitForm.typical_objections.push(tag);
    objectionInput.value = '';
}

function removeObjectionTag(tag) {
    portraitForm.typical_objections = portraitForm.typical_objections.filter((item) => item !== tag);
}

function savePortrait() {
    portraitForm.patch(route('contractors.portrait.update', props.contractorId), {
        preserveScroll: true,
        onSuccess: () => emit('portrait-updated'),
    });
}
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <UserCircle class="h-5 w-5 text-sky-600" />
                    <div>
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Портрет клиента</div>
                        <div class="text-xs text-zinc-500">Структурированная памятка; история общения — на вкладке «Коммуникации»</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Полнота</div>
                    <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ coveragePct }}%</div>
                </div>
            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                <div
                    class="h-full rounded-full bg-sky-500 transition-all"
                    :style="{ width: `${coveragePct}%` }"
                />
            </div>

            <p v-if="missingSlots.length" class="mt-3 text-sm text-amber-700 dark:text-amber-300">
                Не хватает: {{ missingSlots.join(' · ') }}
            </p>

            <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                Ассистент собирает контекст переписки и звонков из журнала
                <button type="button" class="font-medium text-sky-700 underline underline-offset-2 dark:text-sky-300" @click="emit('open-communications')">
                    «Коммуникации»
                </button>.
            </p>
        </div>

        <div
            v-if="pendingInsightDrafts.length"
            class="rounded-xl border border-amber-200 bg-amber-50/80 p-4 dark:border-amber-900/60 dark:bg-amber-950/20"
        >
            <div class="text-sm font-semibold text-amber-900 dark:text-amber-100">Предложения из переписки</div>
            <p class="mt-1 text-xs text-amber-800/80 dark:text-amber-200/80">
                ИИ предложил факты — примите только то, что согласуется с вашим знанием о клиенте.
            </p>
            <ul class="mt-3 space-y-2">
                <li
                    v-for="draft in pendingInsightDrafts"
                    :key="draft.id"
                    class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-amber-200/80 bg-white px-3 py-2 dark:border-amber-900/40 dark:bg-zinc-900"
                >
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ draft.field_label }}</div>
                        <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ draft.proposed_display }}</div>
                        <div v-if="draft.confidence !== null" class="mt-1 text-xs text-zinc-500">
                            Уверенность: {{ Math.round(draft.confidence * 100) }}%
                        </div>
                        <a
                            v-if="draft.source_url"
                            :href="draft.source_url"
                            class="mt-1 inline-block text-xs font-medium text-sky-700 hover:underline dark:text-sky-300"
                        >
                            Источник: {{ draft.source_label ?? 'открыть' }}
                        </a>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button
                            type="button"
                            :class="crmBtnCreate"
                            :disabled="insightDraftBusyId === draft.id"
                            @click="reviewInsightDraft(draft.id, 'accept')"
                        >
                            <Check class="h-4 w-4" />
                            Принять
                        </button>
                        <button
                            type="button"
                            :class="crmBtnNeutral"
                            :disabled="insightDraftBusyId === draft.id"
                            @click="reviewInsightDraft(draft.id, 'reject')"
                        >
                            <X class="h-4 w-4" />
                            Отклонить
                        </button>
                    </div>
                </li>
            </ul>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <form class="space-y-4 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900" @submit.prevent="savePortrait">
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Как с ними работать</div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label v-for="field in [
                        { key: 'communication_style', label: 'Стиль общения', options: portraitOptions.communication_style },
                        { key: 'price_sensitivity', label: 'Чувствительность к цене', options: portraitOptions.price_sensitivity },
                        { key: 'preferred_channel', label: 'Предпочитаемый канал', options: portraitOptions.preferred_channel },
                        { key: 'decision_cadence', label: 'Скорость решений', options: portraitOptions.decision_cadence },
                        { key: 'relationship_trust', label: 'Доверие', options: portraitOptions.relationship_trust },
                    ]" :key="field.key" class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">{{ field.label }}</span>
                        <select v-model="portraitForm[field.key]" :class="`mt-2 ${crmFieldFluid}`">
                            <option v-for="option in field.options" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                </div>

                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Что для них успех перевозки</span>
                    <textarea v-model="portraitForm.success_criteria" rows="3" :class="`mt-2 ${crmFieldFluid}`" />
                </label>

                <div>
                    <span class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Типичные возражения</span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="tag in portraitForm.typical_objections"
                            :key="tag"
                            class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-3 py-1 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                        >
                            {{ tag }}
                            <button type="button" class="text-zinc-400 hover:text-rose-500" @click="removeObjectionTag(tag)">×</button>
                        </span>
                    </div>
                    <div class="mt-2 flex gap-2">
                        <input v-model="objectionInput" type="text" :class="crmFieldFluid" placeholder="Добавить тег" @keydown.enter.prevent="addObjectionTag" />
                        <button type="button" :class="crmBtnNeutral" @click="addObjectionTag">Добавить</button>
                    </div>
                </div>

                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Внутренняя памятка</span>
                    <textarea v-model="portraitForm.internal_notes" rows="3" :class="`mt-2 ${crmFieldFluid}`" />
                </label>

                <button type="submit" :class="crmBtnCreate" :disabled="portraitForm.processing">
                    <Save class="h-4 w-4" />
                    Сохранить портрет
                </button>
            </form>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Traklo · доступ контакта</div>
                <p class="mt-1 text-xs text-zinc-500">
                    Отметьте основной контакт и выдайте ссылку для входа в мобильное приложение.
                </p>
                <div v-if="needsPartyChoice" class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-zinc-500">Сторона:</span>
                    <select v-model="inviteParty" class="rounded-lg border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                        <option value="carrier">Перевозчик</option>
                        <option value="customer">Заказчик</option>
                    </select>
                </div>
                <ul v-if="contacts.length" class="mt-3 space-y-2 text-sm">
                    <li
                        v-for="contact in contacts"
                        :key="contact.id ?? contact.full_name"
                        class="rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/40"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ contact.full_name || 'Без имени' }}
                                    <span
                                        v-if="contact.is_traklo_primary"
                                        class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                    >
                                        Traklo
                                    </span>
                                </div>
                                <div class="text-zinc-500">{{ contact.email || 'Без email' }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    :class="crmBtnNeutral"
                                    :disabled="trakloBusyContactId === contact.id || contact.is_traklo_primary"
                                    @click="setTrakloPrimary(contact)"
                                >
                                    <Star class="h-4 w-4" />
                                    Основной
                                </button>
                                <button
                                    type="button"
                                    :class="crmBtnCreate"
                                    :disabled="trakloBusyContactId === contact.id || !contact.email"
                                    @click="inviteToTraklo(contact)"
                                >
                                    <Link2 class="h-4 w-4" />
                                    Пригласить
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-zinc-500">Контакты пока не заполнены — добавьте их на вкладке «Контакты».</p>
                <div v-if="trakloInviteUrl" class="mt-3 flex flex-wrap items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                    <span class="break-all">{{ trakloInviteUrl }}</span>
                    <button type="button" :class="crmBtnNeutral" @click="copyInviteUrl">
                        <Copy class="h-4 w-4" />
                        Копировать
                    </button>
                </div>
                <p v-if="trakloInviteMessage" class="mt-2 text-xs text-zinc-500">{{ trakloInviteMessage }}</p>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Карта людей</div>
                <ul v-if="contacts.length" class="mt-3 space-y-2 text-sm">
                    <li v-for="contact in contacts" :key="`map-${contact.id ?? contact.full_name}`" class="rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/40">
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ contact.full_name || 'Без имени' }}</div>
                        <div class="text-zinc-500">
                            {{ contact.role_in_deal_label || 'Роль не указана' }}
                            <span v-if="contact.communication_notes"> · {{ contact.communication_notes }}</span>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-zinc-500">Контакты пока не заполнены — добавьте их на вкладке «Контакты».</p>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Последние контакты</div>
                <button type="button" :class="crmBtnNeutral" @click="emit('record-interaction')">
                    <Plus class="h-4 w-4" />
                    Зафиксировать итог
                </button>
            </div>
            <ul v-if="recentInteractions.length" class="mt-3 space-y-2 text-sm">
                <li
                    v-for="interaction in recentInteractions"
                    :key="interaction.id ?? `${interaction.contacted_at}-${interaction.summary}`"
                    class="rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/40"
                >
                    <div class="font-medium text-zinc-900 dark:text-zinc-100">
                        {{ formatContactedAt(interaction.contacted_at) }}
                        · {{ interaction.channel || '—' }}
                        <span v-if="interaction.contact_name"> · {{ interaction.contact_name }}</span>
                    </div>
                    <div class="mt-1 text-zinc-600 dark:text-zinc-300">
                        {{ interaction.summary || interaction.subject || 'Без описания' }}
                    </div>
                    <div v-if="interaction.outcome_label || (interaction.objection_tags?.length ?? 0) > 0" class="mt-1 text-xs text-zinc-500">
                        <span v-if="interaction.outcome_label">{{ interaction.outcome_label }}</span>
                        <span v-if="interaction.objection_tags?.length"> · {{ interaction.objection_tags.join(', ') }}</span>
                    </div>
                </li>
            </ul>
            <p v-else class="mt-3 text-sm text-zinc-500">
                Итогов контактов пока нет.
                <button type="button" class="font-medium text-sky-700 underline underline-offset-2 dark:text-sky-300" @click="emit('record-interaction')">
                    Зафиксировать первый
                </button>
            </p>
        </div>
    </div>
</template>

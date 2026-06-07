<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Save, UserCircle } from 'lucide-vue-next';
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
    portraitOptions: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['portrait-updated', 'open-communications']);

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
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Карта людей</div>
                <ul v-if="contacts.length" class="mt-3 space-y-2 text-sm">
                    <li v-for="contact in contacts" :key="contact.id ?? contact.full_name" class="rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/40">
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
    </div>
</template>

<template>
    <div class="mx-auto max-w-3xl min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0">
        <div class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Скрипт</div>
            <h1 class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ session.script_title }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Версия {{ session.version_number }} · сессия #{{ session.id }}</p>
        </div>

        <div
            v-if="session.completed_at"
            class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
        >
            Сессия завершена
            <span v-if="session.outcome">· исход: {{ session.outcome }}</span>
        </div>

        <div v-else-if="currentNode" class="space-y-4 border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ kindLabel(currentNode.kind) }}</div>
            <div class="whitespace-pre-wrap text-base text-zinc-900 dark:text-zinc-50">{{ currentNode.body }}</div>
            <p v-if="currentNode.hint" class="rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                <span class="font-medium">Подсказка:</span> {{ currentNode.hint }}
            </p>

            <div v-if="!mustComplete && outgoingTransitions.length > 0" class="flex flex-col gap-2 pt-2">
                <button
                    v-for="(t, idx) in outgoingTransitions"
                    :key="`${t.transition_id}-${idx}`"
                    type="button"
                    class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-left text-sm font-medium text-zinc-900 transition hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:hover:bg-zinc-800"
                    @click="advance(t.sales_script_reaction_class_id)"
                >
                    {{ t.label }}
                </button>
            </div>
        </div>

        <div
            v-if="!session.completed_at && mustComplete"
            class="space-y-4 border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
        >
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Зафиксируйте исход</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Эти данные используются для отчётов по воронке и обучения подсказок.</p>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Исход</label>
                <select v-model="completeForm.outcome" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <option value="" disabled>Выберите</option>
                    <option v-for="opt in outcomeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Главное возражение (необязательно)</label>
                <select v-model="completeForm.primary_reaction_class_id" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <option :value="null">—</option>
                    <option v-for="rc in reactionClasses" :key="rc.id" :value="rc.id">{{ rc.label }}</option>
                </select>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Комментарий</label>
                <textarea
                    v-model="completeForm.notes"
                    rows="3"
                    class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                    placeholder="Кратко: что договорились, что мешало"
                />
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900"
                :disabled="!completeForm.outcome"
                @click="submitComplete"
            >
                Сохранить и выйти
            </button>
        </div>

        <div v-if="eventTrail.length > 0" class="border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Ход сессии</h3>
            <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-zinc-600 dark:text-zinc-300">
                <li v-for="ev in eventTrail" :key="ev.id">{{ ev.label }}</li>
            </ol>
        </div>

        <div class="flex gap-3">
            <Link
                :href="route('scripts.index')"
                class="text-sm font-medium text-zinc-600 underline hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            >
                ← К списку сценариев
            </Link>
        </div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'scripts' }, () => page),
});

const props = defineProps({
    session: { type: Object, required: true },
    currentNode: { type: Object, default: null },
    outgoingTransitions: { type: Array, default: () => [] },
    mustComplete: { type: Boolean, default: false },
    eventTrail: { type: Array, default: () => [] },
    outcomeOptions: { type: Array, default: () => [] },
    reactionClasses: { type: Array, default: () => [] },
});

const completeForm = reactive({
    outcome: '',
    primary_reaction_class_id: null,
    notes: '',
});

function kindLabel(kind) {
    const map = { say: 'Что сказать', ask: 'Вопрос', branch: 'Ветвление по реакции клиента' };
    return map[kind] || kind;
}

function advance(reactionClassId) {
    router.post(route('scripts.sessions.advance', props.session.id), {
        sales_script_reaction_class_id: reactionClassId,
    });
}

function submitComplete() {
    router.post(route('scripts.sessions.complete', props.session.id), {
        outcome: completeForm.outcome,
        primary_reaction_class_id: completeForm.primary_reaction_class_id,
        notes: completeForm.notes || null,
    });
}
</script>

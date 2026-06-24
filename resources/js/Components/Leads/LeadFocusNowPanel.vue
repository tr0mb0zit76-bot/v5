<template>
    <div
        v-if="brief"
        class="mt-3 space-y-3 rounded-lg border p-3"
        :class="panelClass"
    >
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="space-y-1">
                <div class="text-xs font-semibold uppercase tracking-wide" :class="titleClass">
                    Сейчас
                </div>
                <p v-if="brief.context?.bp_stage_goal" class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    {{ brief.context.bp_stage_goal }}
                </p>
                <p class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">
                    {{ brief.summary_ru }}
                </p>
            </div>
            <span
                v-if="healthLabel"
                class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium"
                :class="healthBadgeClass"
            >
                {{ healthLabel }}
            </span>
        </div>

        <ul v-if="brief.actions_now?.length" class="space-y-1.5">
            <li
                v-for="action in brief.actions_now"
                :key="`${action.code}-${action.priority}`"
                class="flex items-start gap-2 text-sm"
            >
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-[11px] font-semibold text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                    {{ action.priority }}
                </span>
                <button
                    v-if="action.tab || action.kind === 'next_step'"
                    type="button"
                    class="text-left font-medium text-sky-700 underline-offset-2 hover:underline dark:text-sky-300"
                    @click="onActionClick(action)"
                >
                    {{ action.label }}
                </button>
                <span v-else class="text-zinc-800 dark:text-zinc-200">{{ action.label }}</span>
            </li>
        </ul>

        <p
            v-else-if="brief.health === 'ready_to_advance'"
            class="text-sm font-medium text-emerald-800 dark:text-emerald-200"
        >
            Данные по этапу собраны — можно переходить дальше.
        </p>

        <div v-if="brief.risks?.length" class="flex flex-wrap gap-1.5">
            <span
                v-for="risk in brief.risks"
                :key="risk.code"
                class="inline-flex rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
            >
                {{ risk.label }}
            </span>
        </div>

        <div v-if="brief.positives?.length" class="flex flex-wrap gap-1.5">
            <span
                v-for="positive in brief.positives"
                :key="positive.code"
                class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100"
            >
                {{ positive.label }}
            </span>
        </div>

        <details
            v-if="hasPlaybookDetails"
            class="group rounded-lg border border-zinc-200 bg-white/80 dark:border-zinc-700 dark:bg-zinc-950/50"
        >
            <summary class="cursor-pointer select-none px-3 py-2 text-xs font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                Подробнее по этапу
            </summary>
            <div class="space-y-2 border-t border-zinc-100 px-3 py-2 dark:border-zinc-800">
                <div v-if="processProgress?.current_stage_playbook" class="rounded-lg border border-zinc-100 bg-zinc-50/80 p-2 dark:border-zinc-800 dark:bg-zinc-900/40">
                    <CrmMarkdownView :model-value="processProgress.current_stage_playbook" compact />
                </div>
                <div v-if="processProgress?.current_stage_success_criteria">
                    <CrmMarkdownView :model-value="processProgress.current_stage_success_criteria" compact />
                </div>
                <div v-if="processProgress?.current_stage_sales_script">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg border border-emerald-400 bg-white px-3 py-1.5 text-xs font-medium text-emerald-900 hover:bg-emerald-50 dark:border-emerald-700 dark:bg-zinc-900 dark:text-emerald-100"
                        @click="startSalesScript(processProgress.current_stage_sales_script.version_id)"
                    >
                        Скрипт «{{ processProgress.current_stage_sales_script.title }}»
                    </button>
                </div>
            </div>
        </details>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import CrmMarkdownView from '@/Components/Crm/CrmMarkdownView.vue';

const props = defineProps({
    brief: { type: Object, default: null },
    processProgress: { type: Object, default: null },
});

const emit = defineEmits(['navigate-tab', 'focus-action']);

const healthLabels = {
    stuck: 'Требует внимания',
    on_track: 'В работе',
    ready_to_advance: 'Готов к переходу',
    terminal: 'Закрыт',
};

const healthLabel = computed(() => healthLabels[props.brief?.health] ?? null);

const panelClass = computed(() => {
    const health = props.brief?.health;

    if (health === 'stuck') {
        return 'border-amber-200 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-950/20';
    }

    if (health === 'ready_to_advance') {
        return 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/20';
    }

    return 'border-zinc-200 bg-white/80 dark:border-zinc-700 dark:bg-zinc-950/40';
});

const titleClass = computed(() => {
    const health = props.brief?.health;

    if (health === 'stuck') {
        return 'text-amber-800 dark:text-amber-200';
    }

    if (health === 'ready_to_advance') {
        return 'text-emerald-800 dark:text-emerald-200';
    }

    return 'text-zinc-600 dark:text-zinc-400';
});

const healthBadgeClass = computed(() => {
    const health = props.brief?.health;

    if (health === 'stuck') {
        return 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-100';
    }

    if (health === 'ready_to_advance') {
        return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-100';
    }

    if (health === 'terminal') {
        return 'bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
    }

    return 'bg-sky-100 text-sky-900 dark:bg-sky-950/60 dark:text-sky-100';
});

const hasPlaybookDetails = computed(() => Boolean(
    props.processProgress?.current_stage_playbook
    || props.processProgress?.current_stage_success_criteria
    || props.processProgress?.current_stage_sales_script,
));

function onActionClick(action) {
    emit('focus-action', {
        tab: action.tab ?? 'main',
        kind: action.kind ?? null,
        code: action.code ?? null,
    });
    emit('navigate-tab', action.tab ?? 'main');
}

function startSalesScript(versionId) {
    router.post(route('scripts.sessions.store'), {
        sales_script_version_id: versionId,
    });
}
</script>

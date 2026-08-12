<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    enabled: {
        type: Boolean,
        default: true,
    },
    /**
     * compact — чип в ряд с вкладками (чердак).
     * default — прежняя карточка (если где-то ещё нужна).
     */
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'compact'].includes(value),
    },
});

const loading = ref(false);
const expanded = ref(false);
const verdict = ref(null);
const error = ref('');

const hasPending = computed(() =>
    (verdict.value?.companies ?? []).some((company) => Number(company.pending_count) > 0),
);

const statusTone = computed(() => {
    const status = verdict.value?.status;
    if (status === 'ok' && hasPending.value) {
        return 'amber';
    }
    if (status === 'ok') {
        return 'emerald';
    }
    if (status === 'error') {
        return 'rose';
    }
    if (status === 'attention') {
        return 'amber';
    }

    return 'zinc';
});

const statusClass = computed(() => {
    const tone = statusTone.value;
    if (props.variant === 'compact') {
        return {
            amber: 'border-amber-200 bg-amber-50/80 text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100',
            emerald: 'border-emerald-200 bg-emerald-50/80 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100',
            rose: 'border-rose-200 bg-rose-50/80 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100',
            zinc: 'border-zinc-200 bg-white text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100',
        }[tone];
    }

    return {
        amber: 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100',
        emerald: 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100',
        rose: 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100',
        zinc: 'border-zinc-200 bg-zinc-50 text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100',
    }[tone];
});

const summaryShort = computed(() => {
    if (error.value) {
        return error.value;
    }
    if (! verdict.value) {
        return 'Связь CRM ↔ 1С';
    }

    return verdict.value.summary_ru || 'Вердикт получен';
});

async function runCheck() {
    if (!props.enabled || loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.post(route('finance.management-accounting.bridge-check'));
        verdict.value = data;
        expanded.value = data.status !== 'ok' || (data.companies ?? []).some((c) => Number(c.pending_count) > 0);
    } catch (e) {
        error.value = e?.response?.data?.message || e?.message || 'Не удалось проверить мост 1С.';
        verdict.value = null;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div
        v-if="enabled"
        class="rounded-lg border text-sm"
        :class="[
            statusClass,
            variant === 'compact' ? 'min-w-0 max-w-xl' : 'w-full px-3 py-2',
        ]"
    >
        <div
            class="flex flex-wrap items-center gap-2"
            :class="variant === 'compact' ? 'px-2.5 py-1.5' : 'justify-between'"
        >
            <div class="min-w-0 flex-1 space-y-0.5">
                <div
                    class="flex flex-wrap items-center gap-x-2 gap-y-0.5"
                    :class="variant === 'compact' ? '' : 'flex-col items-start'"
                >
                    <span class="shrink-0 text-[11px] font-semibold uppercase tracking-wide opacity-70">
                        Мост 1С
                    </span>
                    <p
                        class="min-w-0 font-medium"
                        :class="variant === 'compact' ? 'truncate text-xs sm:text-sm' : 'text-sm'"
                        :title="summaryShort"
                    >
                        {{ summaryShort }}
                    </p>
                </div>
                <p
                    v-if="variant !== 'compact' && verdict?.task_created"
                    class="text-xs opacity-90"
                >
                    Задача {{ verdict.task_created.number }}: {{ verdict.task_created.title }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button
                    v-if="verdict"
                    type="button"
                    class="text-xs underline opacity-80 hover:opacity-100"
                    @click="expanded = !expanded"
                >
                    {{ expanded ? 'Скрыть' : 'Детали' }}
                </button>
                <button
                    type="button"
                    class="rounded bg-zinc-900 px-2.5 py-1 text-xs font-medium text-white hover:bg-zinc-800 disabled:opacity-50 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                    :disabled="loading"
                    @click="runCheck"
                >
                    {{ loading ? '…' : 'Проверить' }}
                </button>
            </div>
        </div>

        <ul
            v-if="expanded && verdict?.companies?.length"
            class="space-y-1 border-t border-black/10 text-xs dark:border-white/10"
            :class="variant === 'compact' ? 'mx-2.5 mb-2 mt-1 pt-2' : 'mt-2 px-0 pt-2'"
        >
            <li v-for="company in verdict.companies" :key="company.code">
                <span class="font-medium">{{ company.label }}</span>
                — pending {{ company.pending_count }},
                docs {{ company.docs_gap_count }},
                odata {{ company.odata_ok ? 'ok' : 'fail' }}
                <span v-if="company.issues?.length">: {{ company.issues.join('; ') }}</span>
            </li>
            <li v-if="verdict?.task_created" class="opacity-90">
                Задача {{ verdict.task_created.number }}: {{ verdict.task_created.title }}
            </li>
        </ul>
    </div>
</template>

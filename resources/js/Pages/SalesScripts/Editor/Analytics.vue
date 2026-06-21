<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto">
        <section :class="`${crmPanel} space-y-4 p-6`">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div :class="crmPageEyebrow">Версия {{ payload.version.version_number }}</div>
                    <h1 :class="crmPageTitle">Аналитика сценария</h1>
                    <p :class="`${crmPageLead} mt-2`">{{ payload.script.title }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <select v-model="selectedDays" :class="crmFieldFluid" class="max-w-[140px]" @change="reloadReport">
                        <option :value="30">30 дней</option>
                        <option :value="60">60 дней</option>
                        <option :value="90">90 дней</option>
                    </select>
                    <a
                        :href="exportUrl"
                        :class="crmBtnSecondary"
                    >
                        CSV
                    </a>
                    <Link :href="route('scripts.editor.versions.show', payload.version.id)" :class="crmBtnSecondary">
                        К редактору
                    </Link>
                </div>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Порог подсказок в Play: N ≥ {{ report.min_sample_size }}. Успех = progress / quote_sent / won.
            </p>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section :class="`${crmPanel} space-y-3 p-5`">
                <h2 :class="crmSectionTitle">Топ реакций клиента</h2>
                <div v-if="report.top_reactions.length === 0" class="text-sm text-zinc-500">Нет данных за период.</div>
                <ul v-else class="space-y-2 text-sm">
                    <li
                        v-for="row in report.top_reactions"
                        :key="row.reaction_class_id"
                        class="rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-700"
                    >
                        <div class="font-medium">{{ row.reaction_label }}</div>
                        <div class="mt-1 text-xs text-zinc-500">
                            {{ row.transition_count }} переходов · успех {{ row.success_rate_pct }}% · lost {{ row.lost_rate_pct }}%
                        </div>
                    </li>
                </ul>
            </section>

            <section :class="`${crmPanel} space-y-3 p-5`">
                <h2 :class="crmSectionTitle">Узлы с низкой долей успеха</h2>
                <div v-if="report.drop_off_nodes.length === 0" class="text-sm text-zinc-500">Недостаточно данных (N &lt; порога).</div>
                <ul v-else class="space-y-2 text-sm">
                    <li
                        v-for="row in report.drop_off_nodes"
                        :key="row.node_id"
                        class="rounded-xl border border-amber-200 bg-amber-50/50 px-3 py-2 dark:border-amber-900/50 dark:bg-amber-950/20"
                    >
                        <div class="font-medium">{{ row.node_key || `node#${row.node_id}` }}</div>
                        <div class="mt-1 text-xs text-amber-900/80 dark:text-amber-100/80">
                            успех {{ row.success_rate_pct }}% · lost {{ row.lost_rate_pct }}%
                            <span v-if="row.worst_reaction_label"> · частая проблема: {{ row.worst_reaction_label }}</span>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <section :class="`${crmPanel} overflow-x-auto p-5`">
            <h2 :class="crmSectionTitle">Матрица узел × реакция</h2>
            <table class="min-w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-2 py-2">Узел</th>
                        <th class="px-2 py-2">Реакция</th>
                        <th class="px-2 py-2">N</th>
                        <th class="px-2 py-2">Успех</th>
                        <th class="px-2 py-2">Lost</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in report.reaction_matrix"
                        :key="`${row.node_id}-${row.reaction_class_id}`"
                        class="border-b border-zinc-100 dark:border-zinc-800"
                    >
                        <td class="px-2 py-2 font-mono text-xs">{{ row.node_key }}</td>
                        <td class="px-2 py-2">{{ row.reaction_label }}</td>
                        <td class="px-2 py-2">{{ row.transition_count }}</td>
                        <td class="px-2 py-2">{{ row.success_rate_pct }}%</td>
                        <td class="px-2 py-2">{{ row.lost_rate_pct }}%</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmBtnSecondary,
    crmFieldFluid,
    crmPageEyebrow,
    crmPageLead,
    crmPageTitle,
    crmPanel,
    crmSectionTitle,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'scripts' }, () => page),
});

const props = defineProps({
    payload: { type: Object, required: true },
    report: { type: Object, required: true },
    days: { type: Number, default: 30 },
});

const selectedDays = ref(props.days);

const exportUrl = computed(() => route('scripts.editor.versions.analytics.export', {
    sales_script_version: props.payload.version.id,
    days: selectedDays.value,
}));

function reloadReport() {
    router.get(route('scripts.editor.versions.analytics', props.payload.version.id), {
        days: selectedDays.value,
    }, { preserveState: true, preserveScroll: true });
}
</script>

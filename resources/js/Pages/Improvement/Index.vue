<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    crmBtnCreate,
    crmBtnDangerMuted,
    crmBtnSecondary,
    crmField,
    crmLabel,
    crmPageLead,
    crmPageTitle,
    crmPanel,
} from '@/support/crmUi';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';

const props = defineProps({
    tab: { type: String, default: 'signals' },
    feature_enabled: { type: Boolean, default: true },
    tables_ready: { type: Boolean, default: false },
    signals: { type: Array, default: () => [] },
    hypotheses: { type: Array, default: () => [] },
    experiments: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    managers: { type: Array, default: () => [] },
    script_nodes: { type: Array, default: () => [] },
});

defineOptions({ layout: CrmLayout });

const tabs = [
    { key: 'signals', label: 'Сигналы' },
    { key: 'hypotheses', label: 'Гипотезы' },
    { key: 'experiments', label: 'Эксперименты' },
    { key: 'history', label: 'История' },
];

const activeTab = ref(props.tab);

watch(
    () => props.tab,
    (value) => {
        activeTab.value = value;
    },
);

function switchTab(key) {
    activeTab.value = key;
    router.get(route('improvement.index'), { tab: key }, { preserveState: true, replace: true });
}

function collectSignals() {
    router.post(route('improvement.signals.collect'), {}, { preserveScroll: true });
}

function runPipeline() {
    router.post(route('improvement.pipeline.run'), {}, { preserveScroll: true });
}

function dismissSignal(id) {
    router.post(route('improvement.signals.dismiss', id), {}, { preserveScroll: true });
}

function acceptHypothesis(id) {
    router.post(route('improvement.hypotheses.accept', id), {}, { preserveScroll: true });
}

function rejectHypothesis(id) {
    router.post(route('improvement.hypotheses.reject', id), {}, { preserveScroll: true });
}

const experimentForm = useForm({
    hypothesis_id: null,
    name: '',
    starts_on: '',
    ends_on: '',
    assignment_mode: 'leads',
    variant_a_label: 'Контроль (как сейчас)',
    variant_b_label: '',
    pool_user_ids: [],
    variant_a_user_ids: [],
    variant_b_user_ids: [],
    metric_key: 'win_rate',
});

const completeForm = useForm({
    experiment_id: null,
    verdict: 'adopt_b',
    verdict_note: '',
});

const acceptedHypotheses = computed(() =>
    props.hypotheses.filter((h) => h.status === 'accepted'),
);

function openCreateExperiment(hypothesis) {
    experimentForm.hypothesis_id = hypothesis.id;
    experimentForm.name = `A/B: ${hypothesis.text.slice(0, 60)}`;
    experimentForm.variant_b_label = hypothesis.text;
    experimentForm.starts_on = new Date().toISOString().slice(0, 10);
    const end = new Date();
    end.setDate(end.getDate() + 14);
    experimentForm.ends_on = end.toISOString().slice(0, 10);
}

function submitExperiment() {
    if (!experimentForm.hypothesis_id) {
        return;
    }
    experimentForm.post(route('improvement.experiments.store', experimentForm.hypothesis_id), {
        preserveScroll: true,
        onSuccess: () => {
            experimentForm.reset();
            experimentForm.hypothesis_id = null;
            switchTab('experiments');
        },
    });
}

function startExperiment(id) {
    router.post(route('improvement.experiments.start', id), {}, { preserveScroll: true });
}

function cancelExperiment(id) {
    router.post(route('improvement.experiments.cancel', id), {}, { preserveScroll: true });
}

function openComplete(experiment) {
    completeForm.experiment_id = experiment.id;
    completeForm.verdict = 'adopt_b';
    completeForm.verdict_note = '';
}

function submitComplete() {
    if (!completeForm.experiment_id) {
        return;
    }
    completeForm.post(route('improvement.experiments.complete', completeForm.experiment_id), {
        preserveScroll: true,
        onSuccess: () => {
            completeForm.reset();
            completeForm.experiment_id = null;
            switchTab('history');
        },
    });
}

const domainFilter = ref('all');
const domainLabel = {
    sales: 'Продажи',
    documents: 'Документы/оплаты',
    fleet: 'Флот',
    finance: 'УУ',
};

const filteredSignals = computed(() => {
    if (domainFilter.value === 'all') {
        return props.signals;
    }
    return props.signals.filter((s) => s.domain === domainFilter.value);
});

const applyScriptForm = useForm({
    adoption_id: null,
    sales_script_node_id: '',
});

function openApplyScript(adoption) {
    applyScriptForm.adoption_id = adoption.id;
}

function submitApplyScript() {
    if (!applyScriptForm.adoption_id || !applyScriptForm.sales_script_node_id) {
        return;
    }
    applyScriptForm.post(route('improvement.adoptions.apply-script-node', applyScriptForm.adoption_id), {
        preserveScroll: true,
        onSuccess: () => {
            applyScriptForm.adoption_id = null;
            applyScriptForm.sales_script_node_id = '';
        },
    });
}

const categoryLabel = {
    price: 'Цена',
    script: 'Скрипт',
    channel: 'Канал',
    process: 'Процесс',
};

const severityClass = {
    info: 'text-zinc-600 dark:text-zinc-300',
    warn: 'text-amber-700 dark:text-amber-300',
    critical: 'text-rose-700 dark:text-rose-300',
};

const verdictLabel = {
    adopt_b: 'Внедрить B',
    keep_a: 'Оставить A',
    inconclusive: 'Неясно',
};
</script>

<template>
    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto">
        <Head title="Улучшения" />

        <section :class="`${crmPanel} p-5`">
            <h1 :class="crmPageTitle">Контур улучшений</h1>
            <p :class="`${crmPageLead} mt-1`">
                Мультидоменные сигналы → гипотезы → A/B → закрепление. Внедрение в скрипт — только HITL (variant B).
            </p>

            <div
                v-if="! feature_enabled || ! tables_ready"
                class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200"
            >
                <template v-if="! feature_enabled">Модуль выключен (CRM_FEATURE_IMPROVEMENT_LOOP).</template>
                <template v-else>Выполните миграции: php artisan migrate</template>
            </div>

            <div class="mt-4 flex flex-wrap gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-700">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    :class="crmTabButtonClasses(activeTab === t.key)"
                    @click="switchTab(t.key)"
                >
                    {{ t.label }}
                </button>
            </div>

            <!-- Signals -->
            <div v-if="activeTab === 'signals'" class="mt-4 space-y-3">
                <div class="flex flex-wrap gap-2">
                    <button type="button" :class="crmBtnSecondary" @click="collectSignals">Собрать сигналы</button>
                    <select
                        v-model="domainFilter"
                        class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                    >
                        <option value="all">Все домены</option>
                        <option value="sales">Продажи</option>
                        <option value="documents">Документы/оплаты</option>
                        <option value="fleet">Флот</option>
                        <option value="finance">УУ</option>
                    </select>
                </div>
                <div
                    v-if="filteredSignals.length === 0"
                    class="text-sm text-zinc-500"
                >
                    Сигналов пока нет. Нажмите «Собрать» или дождитесь cron.
                </div>
                <article
                    v-for="signal in filteredSignals"
                    :key="signal.id"
                    class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-zinc-500">
                                {{ domainLabel[signal.domain] || signal.domain }} · {{ signal.kind }} · {{ signal.status }}
                            </div>
                            <h2 class="mt-1 text-base font-semibold" :class="severityClass[signal.severity] || ''">
                                {{ signal.title }}
                            </h2>
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ signal.period_from }} — {{ signal.period_to }}
                            </p>
                        </div>
                        <button
                            v-if="signal.status === 'open'"
                            type="button"
                            :class="crmBtnDangerMuted"
                            @click="dismissSignal(signal.id)"
                        >
                            Скрыть
                        </button>
                    </div>
                </article>
            </div>

            <!-- Hypotheses -->
            <div v-else-if="activeTab === 'hypotheses'" class="mt-4 space-y-3">
                <div class="flex flex-wrap gap-2">
                    <button type="button" :class="crmBtnSecondary" @click="runPipeline">Запустить пайплайн гипотез</button>
                </div>
                <div v-if="hypotheses.length === 0" class="text-sm text-zinc-500">
                    Гипотез нет. Нужны lost-лиды с причинами и доступный LLM.
                </div>
                <article
                    v-for="h in hypotheses"
                    :key="h.id"
                    class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs text-zinc-500">
                                {{ categoryLabel[h.category] || h.category }}
                                · score {{ h.score ?? '—' }}
                                · ICE {{ h.impact }}/{{ h.confidence }}/{{ h.ease }}
                                · {{ h.status }}
                            </div>
                            <h2 class="mt-1 text-base font-medium">{{ h.text }}</h2>
                            <p v-if="h.short_reason" class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ h.short_reason }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <template v-if="h.status === 'draft'">
                                <button type="button" :class="crmBtnCreate" @click="acceptHypothesis(h.id)">Принять</button>
                                <button type="button" :class="crmBtnDangerMuted" @click="rejectHypothesis(h.id)">Отклонить</button>
                            </template>
                            <button
                                v-if="h.status === 'accepted'"
                                type="button"
                                :class="crmBtnSecondary"
                                @click="openCreateExperiment(h); switchTab('experiments')"
                            >
                                В эксперимент
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Experiments -->
            <div v-else-if="activeTab === 'experiments'" class="mt-4 space-y-4">
                <div
                    v-if="experimentForm.hypothesis_id || acceptedHypotheses.length"
                    :class="`${crmPanel} space-y-3 border border-dashed border-zinc-300 p-4 dark:border-zinc-600`"
                >
                    <h3 class="text-sm font-semibold">Новый эксперимент (ручной A/B)</h3>
                    <div v-if="! experimentForm.hypothesis_id" class="space-y-2">
                        <label :class="crmLabel">Гипотеза</label>
                        <select
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            @change="openCreateExperiment(acceptedHypotheses.find((h) => h.id === Number($event.target.value)))"
                        >
                            <option value="">Выберите принятую гипотезу…</option>
                            <option v-for="h in acceptedHypotheses" :key="h.id" :value="h.id">
                                #{{ h.id }} · {{ h.text.slice(0, 80) }}
                            </option>
                        </select>
                    </div>
                    <template v-if="experimentForm.hypothesis_id">
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label :class="crmLabel">Название</label>
                                <input v-model="experimentForm.name" :class="crmField" type="text" class="mt-1 w-full">
                            </div>
                            <div>
                                <label :class="crmLabel">Режим назначения</label>
                                <select
                                    v-model="experimentForm.assignment_mode"
                                    class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                >
                                    <option value="leads">Рандомизация лидов (L4)</option>
                                    <option value="managers">По менеджерам (L2)</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label :class="crmLabel">С</label>
                                    <input v-model="experimentForm.starts_on" :class="crmField" type="date" class="mt-1 w-full">
                                </div>
                                <div>
                                    <label :class="crmLabel">По</label>
                                    <input v-model="experimentForm.ends_on" :class="crmField" type="date" class="mt-1 w-full">
                                </div>
                            </div>
                            <div v-if="experimentForm.assignment_mode === 'leads'" class="md:col-span-2">
                                <label :class="crmLabel">Пул менеджеров (лиды случайно → A/B)</label>
                                <select
                                    v-model="experimentForm.pool_user_ids"
                                    multiple
                                    class="mt-1 h-28 w-full rounded-xl border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                >
                                    <option v-for="m in managers" :key="`p-${m.id}`" :value="m.id">{{ m.name }}</option>
                                </select>
                            </div>
                            <template v-else>
                                <div>
                                    <label :class="crmLabel">Вариант A — менеджеры</label>
                                    <select
                                        v-model="experimentForm.variant_a_user_ids"
                                        multiple
                                        class="mt-1 h-28 w-full rounded-xl border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                    >
                                        <option v-for="m in managers" :key="`a-${m.id}`" :value="m.id">{{ m.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label :class="crmLabel">Вариант B — менеджеры</label>
                                    <select
                                        v-model="experimentForm.variant_b_user_ids"
                                        multiple
                                        class="mt-1 h-28 w-full rounded-xl border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                    >
                                        <option v-for="m in managers" :key="`b-${m.id}`" :value="m.id">{{ m.name }}</option>
                                    </select>
                                </div>
                            </template>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" :class="crmBtnCreate" :disabled="experimentForm.processing" @click="submitExperiment">
                                Создать
                            </button>
                            <button
                                type="button"
                                :class="crmBtnSecondary"
                                @click="experimentForm.hypothesis_id = null"
                            >
                                Отмена
                            </button>
                        </div>
                    </template>
                </div>

                <div v-if="experiments.length === 0" class="text-sm text-zinc-500">Экспериментов пока нет.</div>

                <article
                    v-for="exp in experiments"
                    :key="exp.id"
                    class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"
                >
                    <div class="text-xs text-zinc-500">#{{ exp.id }} · {{ exp.status }} · {{ exp.assignment_mode || 'managers' }} · {{ exp.metric_key }}</div>
                    <h2 class="mt-1 font-medium">{{ exp.name }}</h2>
                    <p v-if="exp.hypothesis" class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        Гипотеза: {{ exp.hypothesis.text }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-500">
                        {{ exp.starts_on }} — {{ exp.ends_on }}
                        <span v-if="exp.assignment_mode === 'leads'"> · назначений: {{ exp.assignments_count }}</span>
                    </p>
                    <div
                        v-if="exp.live_snapshot?.stats || exp.result_snapshot?.stats"
                        class="mt-2 rounded-lg bg-zinc-50 p-3 text-xs dark:bg-zinc-900/50"
                    >
                        <template v-if="(exp.live_snapshot || exp.result_snapshot).stats">
                            <div>
                                A: {{ (exp.live_snapshot || exp.result_snapshot).variant_a?.win_rate_pct }}%
                                (n={{ (exp.live_snapshot || exp.result_snapshot).variant_a?.closed }})
                                · B: {{ (exp.live_snapshot || exp.result_snapshot).variant_b?.win_rate_pct }}%
                                (n={{ (exp.live_snapshot || exp.result_snapshot).variant_b?.closed }})
                            </div>
                            <div class="mt-1">
                                Δ {{ (exp.live_snapshot || exp.result_snapshot).stats.diff_pp }} п.п.
                                · p={{ (exp.live_snapshot || exp.result_snapshot).stats.p_value ?? '—' }}
                                · значимо: {{ (exp.live_snapshot || exp.result_snapshot).stats.significant ? 'да' : 'нет' }}
                                · нужно ≥{{ (exp.live_snapshot || exp.result_snapshot).stats.required_n_per_arm }} на руку
                            </div>
                            <div
                                v-if="(exp.live_snapshot || exp.result_snapshot).stats.early_stop_suggested"
                                class="mt-1 font-medium text-emerald-700 dark:text-emerald-300"
                            >
                                Подсказка early-stop: можно завершать (HITL). Рекомендация:
                                {{ (exp.live_snapshot || exp.result_snapshot).stats.recommendation }}
                            </div>
                        </template>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-if="exp.status === 'planned'"
                            type="button"
                            :class="crmBtnCreate"
                            @click="startExperiment(exp.id)"
                        >
                            Запустить
                        </button>
                        <button
                            v-if="exp.status === 'running'"
                            type="button"
                            :class="crmBtnSecondary"
                            @click="openComplete(exp)"
                        >
                            Завершить
                        </button>
                        <button
                            v-if="exp.status === 'planned' || exp.status === 'running'"
                            type="button"
                            :class="crmBtnDangerMuted"
                            @click="cancelExperiment(exp.id)"
                        >
                            Отменить
                        </button>
                    </div>

                    <div
                        v-if="completeForm.experiment_id === exp.id"
                        class="mt-3 space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
                    >
                        <label :class="crmLabel">Вердикт</label>
                        <select v-model="completeForm.verdict" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                            <option value="adopt_b">Внедрить B</option>
                            <option value="keep_a">Оставить A</option>
                            <option value="inconclusive">Неясно</option>
                        </select>
                        <label :class="crmLabel">Комментарий</label>
                        <textarea v-model="completeForm.verdict_note" rows="3" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                        <button type="button" :class="crmBtnCreate" :disabled="completeForm.processing" @click="submitComplete">
                            Сохранить вердикт
                        </button>
                    </div>
                </article>
            </div>

            <!-- History -->
            <div v-else class="mt-4 space-y-3">
                <div v-if="history.length === 0" class="text-sm text-zinc-500">Истории вердиктов пока нет.</div>
                <article
                    v-for="item in history"
                    :key="`h-${item.id}`"
                    class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"
                >
                    <div class="text-xs text-zinc-500">
                        {{ verdictLabel[item.verdict] || item.verdict }} · {{ item.decided_at }}
                    </div>
                    <h2 class="mt-1 font-medium">{{ item.name }}</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ item.hypothesis_text }}</p>
                    <p class="mt-1 text-sm">{{ item.verdict_note }}</p>
                    <p v-if="item.result_snapshot" class="mt-2 text-xs text-zinc-500">
                        A win rate: {{ item.result_snapshot.variant_a?.win_rate_pct ?? '—' }}%
                        · B win rate: {{ item.result_snapshot.variant_b?.win_rate_pct ?? '—' }}%
                    </p>
                    <p v-if="item.adoption" class="mt-2 text-sm text-emerald-700 dark:text-emerald-300">
                        Закреплено: {{ item.adoption.summary }}
                        <span v-if="item.adoption.target_type === 'task'"> (задача #{{ item.adoption.target_id }})</span>
                    </p>
                    <div
                        v-if="item.adoption?.meta?.proposed_body_variant_b && ! item.adoption?.meta?.script_applied && script_nodes.length"
                        class="mt-3 space-y-2 rounded-lg border border-dashed border-zinc-300 p-3 dark:border-zinc-600"
                    >
                        <div class="text-xs font-medium text-zinc-600 dark:text-zinc-300">L5: внедрить в узел скрипта (variant B)</div>
                        <select
                            v-model="applyScriptForm.sales_script_node_id"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                            @focus="openApplyScript(item.adoption)"
                        >
                            <option value="">Выберите узел…</option>
                            <option
                                v-for="n in script_nodes.filter((n) => !item.adoption.meta.sales_script_version_id || n.version_id === item.adoption.meta.sales_script_version_id)"
                                :key="n.id"
                                :value="n.id"
                            >
                                {{ n.label }}
                            </option>
                        </select>
                        <button
                            type="button"
                            :class="crmBtnCreate"
                            :disabled="applyScriptForm.processing || ! applyScriptForm.sales_script_node_id"
                            @click="openApplyScript(item.adoption); submitApplyScript()"
                        >
                            Записать variant B + включить A/B
                        </button>
                    </div>
                    <p
                        v-else-if="item.adoption?.meta?.script_applied"
                        class="mt-2 text-xs text-emerald-700 dark:text-emerald-300"
                    >
                        Уже применено к узлу #{{ item.adoption.meta.applied_node_id }}
                    </p>
                </article>
            </div>
        </section>
    </div>
</template>

<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            title="Бизнес-процессы"
            lead="Конструктор воронок и playbook для менеджеров: цель этапа, чек-листы, SLA и аналитика для улучшения процесса."
        />

        <div class="flex flex-wrap items-center gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                :class="activeTab === tab.id ? crmSegmentedBtnActive : crmSegmentedBtn"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
            </button>
            <div v-if="activeTab === 'health'" class="ml-auto flex items-center gap-2 text-sm">
                <label class="text-zinc-500 dark:text-zinc-400">Период</label>
                <select v-model.number="lookbackDays" class="rounded-lg border border-zinc-200 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950" @change="reloadHealth">
                    <option :value="30">30 дней</option>
                    <option :value="90">90 дней</option>
                    <option :value="180">180 дней</option>
                </select>
            </div>
        </div>

        <div v-if="activeTab === 'health'" class="space-y-4">
            <section v-if="health.recommendations?.length" :class="`${crmPanel} space-y-3 p-4`">
                <h2 :class="crmSectionTitle">Рекомендации коуча</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    На основе SLA, времени на этапах и заполненности playbook за последние {{ health.lookback_days }} дн.
                </p>
                <ul class="space-y-2">
                    <li
                        v-for="(item, index) in health.recommendations"
                        :key="`${item.process_id}-${index}`"
                        class="rounded-lg border px-3 py-2 text-sm"
                        :class="recommendationClass(item.severity)"
                    >
                        <span class="font-medium">{{ item.process_name }}:</span>
                        {{ item.message }}
                    </li>
                </ul>
            </section>

            <section v-if="stageIssues.rows?.length" :class="`${crmPanel} space-y-3 p-4`">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 :class="crmSectionTitle">Сейчас требуют внимания</h2>
                    <Link :href="route('reports.index', { tab: 'lead-process' })" class="text-sm text-sky-600 hover:underline dark:text-sky-400">
                        Полный отчёт →
                    </Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-zinc-500">
                            <tr>
                                <th class="px-2 py-1">Лид</th>
                                <th class="px-2 py-1">Процесс / этап</th>
                                <th class="px-2 py-1">Проблема</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in stageIssues.rows.slice(0, 8)" :key="row.lead_id" class="border-t border-zinc-100 dark:border-zinc-800">
                                <td class="px-2 py-2">{{ row.lead_number }}</td>
                                <td class="px-2 py-2">{{ row.process_name }} · {{ row.stage_name }}</td>
                                <td class="px-2 py-2">{{ (row.issue_labels || []).join(', ') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div v-for="process in health.processes" :key="process.id" :class="`${crmPanel} space-y-4 p-4`">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">{{ process.name }}</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Win-rate за период:
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ process.win_rate_percent ?? '—' }}%</span>
                            ({{ process.won_in_period }} / {{ process.closed_in_period }} закрытых)
                        </p>
                    </div>
                    <button type="button" :class="crmBtnNeutral" class="text-xs" @click="selectProcessForEdit(process.id)">
                        Редактировать playbook
                    </button>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="stage in process.stages"
                        :key="stage.id"
                        class="grid gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-800 md:grid-cols-[minmax(0,1fr)_repeat(4,minmax(4rem,auto))]"
                    >
                        <div>
                            <div class="font-medium">{{ stage.sequence }}. {{ stage.name }}</div>
                            <div v-if="!stage.has_playbook && !stage.is_terminal" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                Нет инструкции для менеджера
                            </div>
                        </div>
                        <div class="text-center text-xs">
                            <div class="text-zinc-500">Сейчас</div>
                            <div class="text-lg font-semibold">{{ stage.active_leads }}</div>
                        </div>
                        <div class="text-center text-xs">
                            <div class="text-zinc-500">Ср. дней</div>
                            <div class="text-lg font-semibold">{{ stage.avg_days_on_stage ?? '—' }}</div>
                            <div v-if="stage.duration_days_norm" class="text-zinc-400">норма {{ stage.duration_days_norm }}</div>
                        </div>
                        <div class="text-center text-xs">
                            <div class="text-zinc-500">Дальше</div>
                            <div class="text-lg font-semibold">{{ stage.conversion_to_next_percent ?? '—' }}<span v-if="stage.conversion_to_next_percent != null">%</span></div>
                        </div>
                        <div class="text-center text-xs">
                            <div class="text-zinc-500">SLA</div>
                            <div class="text-lg font-semibold" :class="stage.sla_breaches_in_period > 0 ? 'text-rose-600' : ''">
                                {{ stage.sla_breaches_in_period }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="!health.processes?.length" class="text-sm text-zinc-500 dark:text-zinc-400">
                Нет активных процессов для аналитики.
            </p>
        </div>

        <div v-else class="grid min-h-0 grid-cols-1 gap-4 xl:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
            <aside :class="`${crmPanel} space-y-3 p-4`">
                <form class="space-y-2 border border-dashed border-zinc-300 p-3 dark:border-zinc-700" @submit.prevent="submitNewProcess">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Новый процесс</div>
                    <input v-model="newProcessForm.name" type="text" placeholder="Название" :class="crmFieldFluid" required />
                    <button type="submit" :class="`${crmBtnCreate} w-full justify-center`" :disabled="newProcessForm.processing">
                        Добавить
                    </button>
                </form>

                <button
                    v-for="process in processes"
                    :key="process.id"
                    type="button"
                    :class="[selectedProcessId === process.id ? crmListItemActive : crmListItemIdle, 'justify-between']"
                    @click="selectProcess(process.id)"
                >
                    <div class="space-y-1 text-left">
                        <div class="text-sm font-medium">{{ process.name }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ process.stages.length }} этапов</div>
                        <div v-if="!process.is_active" class="text-xs text-amber-600">Неактивен</div>
                    </div>
                </button>
            </aside>

            <section v-if="selectedProcess" :class="`${crmPanel} flex min-h-0 flex-col gap-4 p-4`">
                <form class="grid gap-3 md:grid-cols-2" @submit.prevent="saveProcess">
                    <div class="space-y-1 md:col-span-2">
                        <label :class="crmLabel">Название процесса</label>
                        <input v-model="processForm.name" type="text" :class="crmFieldFluid" required />
                    </div>
                    <div class="space-y-1 md:col-span-2">
                        <label :class="crmLabel">Описание воронки (для менеджеров)</label>
                        <CrmMarkdownEditor v-model="processForm.description" placeholder="Зачем этот процесс, когда его применять…" compact />
                    </div>
                    <div class="space-y-1">
                        <label :class="crmLabel">Порядок</label>
                        <input v-model.number="processForm.sort_order" type="number" min="0" :class="crmFieldFluid" />
                    </div>
                    <div class="flex items-end gap-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input v-model="processForm.is_active" type="checkbox" class="rounded border-zinc-300" />
                            Активен
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2 md:col-span-2">
                        <button type="submit" :class="crmBtnCreate" :disabled="processForm.processing">Сохранить процесс</button>
                        <button type="button" :class="crmBtnDangerMuted" @click="deleteProcess">Удалить процесс</button>
                    </div>
                </form>

                <div class="space-y-3">
                    <h2 :class="crmSectionTitle">Этапы и playbook</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Методика: <strong>цель</strong> → <strong>действия (чек-лист)</strong> → <strong>критерии готовности</strong> → SLA и автозадача.
                    </p>

                    <form class="space-y-3 rounded-lg border border-dashed border-zinc-300 p-3 dark:border-zinc-700" @submit.prevent="submitStage">
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-sm font-medium">{{ editingStageId ? 'Редактирование этапа' : 'Новый этап' }}</div>
                            <button v-if="editingStageId" type="button" class="text-xs text-zinc-500 hover:underline" @click="resetStageForm">Отмена</button>
                        </div>

                        <div class="grid gap-2 md:grid-cols-6">
                            <input v-model="stageForm.name" type="text" placeholder="Название этапа" :class="`${crmFieldFluid} md:col-span-2`" required />
                            <input v-model.number="stageForm.sequence" type="number" min="1" placeholder="Порядок" :class="crmFieldFluid" />
                            <input v-model.number="stageForm.duration_days" type="number" min="0" max="365" placeholder="Норматив, дней" :class="crmFieldFluid" title="Норматив SLA этапа" />
                            <label class="inline-flex items-center gap-2 text-sm md:col-span-2">
                                <input v-model="stageForm.is_terminal" type="checkbox" class="rounded border-zinc-300" />
                                Финальный этап
                            </label>
                            <select v-if="stageForm.is_terminal" v-model="stageForm.terminal_outcome" :class="crmFieldFluid">
                                <option :value="null">Исход</option>
                                <option value="won">Выигран</option>
                                <option value="lost">Проигран</option>
                                <option value="neutral">Нейтрально</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label :class="crmLabel">Цель этапа (одна фраза)</label>
                            <input v-model="stageForm.stage_goal" type="text" :class="crmFieldFluid" placeholder="Например: получить полные параметры перевозки" maxlength="500" />
                        </div>

                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label :class="crmLabel">Playbook — что делать</label>
                                <div class="flex flex-wrap gap-1">
                                    <button type="button" class="rounded border border-zinc-200 px-2 py-0.5 text-xs dark:border-zinc-700" @click="applyPlaybookTemplate">
                                        Шаблон
                                    </button>
                                    <button
                                        v-for="ph in playbookPlaceholders"
                                        :key="ph.token"
                                        type="button"
                                        class="rounded border border-zinc-200 px-2 py-0.5 text-xs dark:border-zinc-700"
                                        :title="ph.hint"
                                        @click="insertPlaceholder(ph.token)"
                                    >
                                        {{ ph.label }}
                                    </button>
                                </div>
                            </div>
                            <CrmMarkdownEditor ref="playbookEditorRef" v-model="stageForm.description" placeholder="Чек-лист действий менеджера…" />
                        </div>

                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label :class="crmLabel">Критерии готовности (Definition of Done)</label>
                                <button type="button" class="rounded border border-zinc-200 px-2 py-0.5 text-xs dark:border-zinc-700" @click="applySuccessTemplate">
                                    Шаблон
                                </button>
                            </div>
                            <CrmMarkdownEditor v-model="stageForm.success_criteria" placeholder="Когда этап можно считать завершённым…" compact />
                        </div>

                        <div class="space-y-1">
                            <label :class="crmLabel">Сценарий продаж (скрипт)</label>
                            <select v-model="stageForm.sales_script_id" :class="crmFieldFluid">
                                <option :value="null">— без привязки —</option>
                                <option v-for="script in salesScriptOptions" :key="script.id" :value="script.id">
                                    {{ script.title }}
                                </option>
                            </select>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                Менеджер увидит кнопку «Открыть скрипт» на карточке лида на этом этапе.
                            </p>
                        </div>

                        <details class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                            <summary class="cursor-pointer text-sm font-medium">Напоминания (nudges)</summary>
                            <div class="mt-3 space-y-3">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Cron <code class="text-[11px]">commercial:process-nudges</code> создаёт задачи по включённым триггерам. Пустой список — дефолты из config.
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <label
                                        v-for="option in nudgeTypeOptions"
                                        :key="option.value"
                                        class="inline-flex items-center gap-2 rounded border border-zinc-200 px-2 py-1 text-xs dark:border-zinc-700"
                                        :title="option.description"
                                    >
                                        <input
                                            type="checkbox"
                                            class="rounded border-zinc-300"
                                            :checked="stageForm.nudge_triggers.includes(option.value)"
                                            @change="toggleNudgeTrigger(option.value)"
                                        />
                                        {{ option.label }}
                                    </label>
                                </div>
                                <div class="grid gap-2 md:grid-cols-2">
                                    <input
                                        v-model.number="stageForm.no_reply_nudge_days"
                                        type="number"
                                        min="1"
                                        max="90"
                                        placeholder="Дней без ответа на КП"
                                        :class="crmFieldFluid"
                                    />
                                    <input
                                        v-model.number="stageForm.ledger_idle_nudge_days"
                                        type="number"
                                        min="1"
                                        max="90"
                                        placeholder="Дней без событий в ленте"
                                        :class="crmFieldFluid"
                                    />
                                </div>
                            </div>
                        </details>

                        <details class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                            <summary class="cursor-pointer text-sm font-medium">Автозадача при входе на этап</summary>
                            <div class="mt-3 space-y-2">
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input v-model="stageForm.auto_create_task" type="checkbox" class="rounded border-zinc-300" />
                                    Создавать задачу
                                </label>
                                <input v-model="stageForm.task_title_template" type="text" placeholder="Шаблон: {stage_name} — {lead_number}" :class="crmFieldFluid" :disabled="!stageForm.auto_create_task" />
                                <CrmMarkdownEditor v-model="stageForm.task_description_template" placeholder="Описание задачи…" compact :class="!stageForm.auto_create_task ? 'opacity-50 pointer-events-none' : ''" />
                                <div class="grid gap-2 md:grid-cols-2">
                                    <input v-model.number="stageForm.task_due_days_offset" type="number" min="0" max="365" placeholder="Срок, дней" :class="crmFieldFluid" :disabled="!stageForm.auto_create_task" />
                                    <select v-model="stageForm.task_priority" :class="crmFieldFluid" :disabled="!stageForm.auto_create_task">
                                        <option value="low">Низкий</option>
                                        <option value="medium">Средний</option>
                                        <option value="high">Высокий</option>
                                        <option value="critical">Срочный</option>
                                    </select>
                                </div>
                            </div>
                        </details>

                        <button type="submit" :class="`${crmBtnCreate} w-full justify-center md:w-auto`">
                            {{ editingStageId ? 'Сохранить этап' : 'Добавить этап' }}
                        </button>
                    </form>

                    <div class="space-y-2">
                        <div
                            v-for="stage in selectedProcess.stages"
                            :key="stage.id"
                            class="flex flex-wrap items-center justify-between gap-3 border border-zinc-200 px-3 py-3 dark:border-zinc-800"
                        >
                            <div class="min-w-0 space-y-1">
                                <div class="font-medium">{{ stage.sequence }}. {{ stage.name }}</div>
                                <div v-if="stage.stage_goal" class="text-sm text-zinc-600 dark:text-zinc-300">{{ stage.stage_goal }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ stage.duration_days }} дн.
                                    <span v-if="stage.is_terminal"> · {{ terminalLabels[stage.terminal_outcome] ?? 'Финал' }}</span>
                                    <span v-if="stage.description || stage.success_criteria"> · playbook</span>
                                    <span v-if="stage.sales_script_title"> · скрипт: {{ stage.sales_script_title }}</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" :class="crmBtnNeutral" class="px-3 py-1.5 text-xs" @click="editStage(stage)">Изменить</button>
                                <button type="button" :class="crmBtnDangerMuted" class="px-3 py-1.5 text-xs" @click="deleteStage(stage.id)">Удалить</button>
                            </div>
                        </div>
                        <p v-if="selectedProcess.stages.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">Этапы ещё не добавлены.</p>
                    </div>
                </div>
            </section>

            <section v-else :class="`${crmPanel} flex items-center justify-center p-8 text-sm text-zinc-500 dark:text-zinc-400`">
                Выберите бизнес-процесс слева или создайте новый.
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmMarkdownEditor from '@/Components/Crm/CrmMarkdownEditor.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmBtnCreate,
    crmBtnDangerMuted,
    crmBtnNeutral,
    crmFieldFluid,
    crmLabel,
    crmListItemActive,
    crmListItemIdle,
    crmPanel,
    crmSectionTitle,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'administration', activeLeafKey: 'business-processes' }, () => page),
});

const props = defineProps({
    processes: { type: Array, default: () => [] },
    playbook_placeholders: { type: Array, default: () => [] },
    playbook_templates: { type: Object, default: () => ({}) },
    health: { type: Object, default: () => ({ processes: [], recommendations: [], lookback_days: 90 }) },
    stage_issues: { type: Object, default: () => ({ rows: [] }) },
    sales_script_options: { type: Array, default: () => [] },
    nudge_type_options: { type: Array, default: () => [] },
    lookback_days: { type: Number, default: 90 },
});

const salesScriptOptions = computed(() => props.sales_script_options ?? []);
const nudgeTypeOptions = computed(() => props.nudge_type_options ?? []);

const tabs = [
    { id: 'builder', label: 'Конструктор' },
    { id: 'health', label: 'Здоровье воронки' },
];

const activeTab = ref('builder');
const lookbackDays = ref(props.lookback_days);
const playbookEditorRef = ref(null);
const selectedProcessId = ref(props.processes[0]?.id ?? null);

const playbookPlaceholders = computed(() => props.playbook_placeholders ?? []);

const selectedProcess = computed(() => props.processes.find((process) => process.id === selectedProcessId.value) ?? null);

const terminalLabels = { won: 'Выигран', lost: 'Проигран', neutral: 'Нейтрально' };

const newProcessForm = useForm({ name: '', description: '', is_active: true, sort_order: 0 });
const processForm = useForm({ name: '', description: '', is_active: true, sort_order: 0 });
const editingStageId = ref(null);
const stageForm = useForm({
    name: '',
    description: '',
    stage_goal: '',
    success_criteria: '',
    sales_script_id: null,
    sequence: null,
    duration_days: 0,
    is_terminal: false,
    terminal_outcome: null,
    auto_create_task: false,
    task_title_template: '',
    task_description_template: '',
    task_due_days_offset: 0,
    task_priority: 'medium',
    no_reply_nudge_days: null,
    nudge_triggers: [],
    ledger_idle_nudge_days: null,
});

watch(selectedProcess, (process) => {
    if (!process) {
        return;
    }

    processForm.name = process.name;
    processForm.description = process.description ?? '';
    processForm.is_active = process.is_active;
    processForm.sort_order = process.sort_order ?? 0;
}, { immediate: true });

function recommendationClass(severity) {
    if (severity === 'focus') {
        return 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-100';
    }
    if (severity === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100';
    }

    return 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100';
}

function selectProcess(id) {
    selectedProcessId.value = id;
    activeTab.value = 'builder';
}

function selectProcessForEdit(id) {
    selectProcess(id);
}

function reloadHealth() {
    router.get(route('settings.business-processes.index'), { lookback_days: lookbackDays.value }, {
        preserveScroll: true,
        preserveState: true,
        only: ['health', 'stage_issues', 'lookback_days'],
    });
}

function submitNewProcess() {
    newProcessForm.post(route('settings.business-processes.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newProcessForm.reset();
            newProcessForm.is_active = true;
        },
    });
}

function saveProcess() {
    if (!selectedProcess.value) {
        return;
    }

    processForm.patch(route('settings.business-processes.update', selectedProcess.value.id), { preserveScroll: true });
}

function deleteProcess() {
    if (!selectedProcess.value || !window.confirm('Удалить бизнес-процесс?')) {
        return;
    }

    router.delete(route('settings.business-processes.destroy', selectedProcess.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedProcessId.value = props.processes[0]?.id ?? null;
        },
    });
}

function resetStageForm() {
    editingStageId.value = null;
    stageForm.reset();
    stageForm.duration_days = 0;
    stageForm.is_terminal = false;
    stageForm.terminal_outcome = null;
    stageForm.auto_create_task = false;
    stageForm.task_due_days_offset = 0;
    stageForm.task_priority = 'medium';
    stageForm.no_reply_nudge_days = null;
    stageForm.nudge_triggers = [];
    stageForm.ledger_idle_nudge_days = null;
}

function editStage(stage) {
    editingStageId.value = stage.id;
    stageForm.name = stage.name;
    stageForm.description = stage.description ?? '';
    stageForm.stage_goal = stage.stage_goal ?? '';
    stageForm.success_criteria = stage.success_criteria ?? '';
    stageForm.sales_script_id = stage.sales_script_id ?? null;
    stageForm.sequence = stage.sequence;
    stageForm.duration_days = stage.duration_days ?? 0;
    stageForm.is_terminal = Boolean(stage.is_terminal);
    stageForm.terminal_outcome = stage.terminal_outcome;
    stageForm.auto_create_task = Boolean(stage.auto_create_task);
    stageForm.task_title_template = stage.task_title_template ?? '';
    stageForm.task_description_template = stage.task_description_template ?? '';
    stageForm.task_due_days_offset = stage.task_due_days_offset ?? 0;
    stageForm.task_priority = stage.task_priority ?? 'medium';
    stageForm.no_reply_nudge_days = stage.no_reply_nudge_days ?? null;
    stageForm.nudge_triggers = [...(stage.nudge_triggers ?? [])];
    stageForm.ledger_idle_nudge_days = stage.ledger_idle_nudge_days ?? null;
}

function submitStage() {
    if (!selectedProcess.value) {
        return;
    }

    const onSuccess = () => resetStageForm();

    if (editingStageId.value) {
        stageForm.patch(route('settings.business-processes.stages.update', [selectedProcess.value.id, editingStageId.value]), {
            preserveScroll: true,
            onSuccess,
        });

        return;
    }

    stageForm.post(route('settings.business-processes.stages.store', selectedProcess.value.id), {
        preserveScroll: true,
        onSuccess,
    });
}

function deleteStage(stageId) {
    if (!selectedProcess.value || !window.confirm('Удалить этап?')) {
        return;
    }

    router.delete(route('settings.business-processes.stages.destroy', [selectedProcess.value.id, stageId]), {
        preserveScroll: true,
    });
}

function toggleNudgeTrigger(value) {
    const triggers = new Set(stageForm.nudge_triggers ?? []);

    if (triggers.has(value)) {
        triggers.delete(value);
    } else {
        triggers.add(value);
    }

    stageForm.nudge_triggers = [...triggers];
}

function applyPlaybookTemplate() {
    const name = stageForm.name || 'этап';
    stageForm.description = (props.playbook_templates?.stage ?? '').replace('Название этапа', name);
}

function applySuccessTemplate() {
    stageForm.success_criteria = props.playbook_templates?.success_criteria ?? '';
}

function insertPlaceholder(token) {
    playbookEditorRef.value?.insertText?.(token);
}
</script>

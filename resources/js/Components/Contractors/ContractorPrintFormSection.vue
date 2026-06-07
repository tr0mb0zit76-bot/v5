<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, ExternalLink, Plus, Trash2 } from 'lucide-vue-next';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmFieldFluid,
    crmPanel,
} from '@/support/crmUi.js';

const props = defineProps({
    contractorId: {
        type: Number,
        required: true,
    },
    editor: {
        type: Object,
        required: true,
    },
});

const activeParty = ref(props.editor.active_party ?? 'customer');
let rowKey = 0;

function rowsForParty(party) {
    const side = party === 'carrier' ? 'carrier' : 'customer';

    return props.editor?.[side]?.rows ?? [];
}

function mapRows(rows) {
    return (rows ?? []).map((row) => ({
        key: `term-${rowKey += 1}`,
        body: String(row?.body ?? ''),
    }));
}

const form = useForm({
    party: activeParty.value,
    items: mapRows(rowsForParty(activeParty.value)),
    manager_notes: '',
    yurik_summary: '',
});

const resolveForm = useForm({
    action: 'approve',
    reason: '',
    notes: '',
});

const currentPartyRows = computed(() => {
    const side = activeParty.value === 'carrier' ? 'carrier' : 'customer';

    return props.editor?.[side]?.rows ?? [];
});

const currentPartyMode = computed(() => {
    const side = activeParty.value === 'carrier' ? 'carrier' : 'customer';

    return props.editor?.[side]?.mode ?? { label: 'Стандартная', mode: 'internal_standard' };
});

const placeholderHelp = computed(() => {
    const help = props.editor?.placeholder_help?.[activeParty.value] ?? {};

    return {
        anchor: help.anchor ?? 'cp_basic_terms_row_text',
        macros: Array.isArray(help.macros) ? help.macros : [],
    };
});

const pendingChange = computed(() => props.editor?.pending_change ?? null);
const canDirectManage = computed(() => Boolean(props.editor?.can_direct_manage));
const canApprove = computed(() => Boolean(props.editor?.can_approve));
const externalTemplates = computed(() => props.editor?.external_templates ?? []);
const isPendingForActiveParty = computed(() => pendingChange.value?.party === activeParty.value && pendingChange.value?.status === 'pending_approval');

watch(
    () => props.editor,
    (editor) => {
        activeParty.value = editor?.active_party ?? activeParty.value;
        syncFormFromEditor();
    },
    { deep: true },
);

watch(activeParty, () => {
    syncFormFromEditor();
});

function syncFormFromEditor() {
    form.party = activeParty.value;
    form.items = mapRows(rowsForParty(activeParty.value));
    form.clearErrors();
}

function switchParty(party) {
    activeParty.value = party;
}

function addRow() {
    form.items.push({ key: `term-${rowKey += 1}`, body: '' });
}

function removeRow(index) {
    form.items.splice(index, 1);
}

function moveRow(index, direction) {
    const target = index + direction;

    if (target < 0 || target >= form.items.length) {
        return;
    }

    const copy = [...form.items];
    const [row] = copy.splice(index, 1);
    copy.splice(target, 0, row);
    form.items = copy;
}

function exportItems() {
    return form.items
        .map((row) => String(row.body ?? '').trim())
        .filter((body) => body !== '');
}

function saveDirectly() {
    form.party = activeParty.value;
    form.transform((data) => ({
        party: data.party,
        items: exportItems(),
    })).put(route('contractors.print-form.basic-terms.update', props.contractorId), {
        preserveScroll: true,
    });
}

function submitForApproval() {
    form.party = activeParty.value;
    form.transform((data) => ({
        party: data.party,
        items: exportItems(),
        manager_notes: data.manager_notes || null,
        yurik_summary: data.yurik_summary || null,
    })).post(route('contractors.print-form.changes.submit', props.contractorId), {
        preserveScroll: true,
    });
}

function resolvePending(action) {
    if (!pendingChange.value?.id) {
        return;
    }

    resolveForm.action = action;

    if (action === 'reject' && !String(resolveForm.reason ?? '').trim()) {
        window.alert('Укажите причину отклонения.');

        return;
    }

    resolveForm.post(route('contractors.print-form.changes.resolve', [props.contractorId, pendingChange.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            resolveForm.reset('reason', 'notes');
        },
    });
}

defineExpose({
    focusSection: () => nextTick(() => {
        document.getElementById('contractor-print-form-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }),
});
</script>

<template>
    <section
        id="contractor-print-form-section"
        :class="`${crmPanel} space-y-4 p-4`"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Печатные формы и базовые условия</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    Индивидуальные пункты для договоров-заявок этого контрагента. Стороны cp/dp не смешиваются.
                </div>
            </div>
            <div class="rounded-full border border-zinc-200 px-3 py-1 text-xs font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">
                {{ currentPartyMode.label }}
            </div>
        </div>

        <div
            v-if="pendingChange"
            class="rounded-xl border px-4 py-3 text-sm"
            :class="pendingChange.status === 'pending_approval'
                ? 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100'
                : 'border-zinc-200 bg-zinc-50 text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-200'"
        >
            <div class="font-medium">{{ pendingChange.status_label }} ({{ pendingChange.party_label }})</div>
            <div v-if="pendingChange.submitted_by_name" class="mt-1 text-xs opacity-80">
                Отправил: {{ pendingChange.submitted_by_name }}
            </div>
            <div v-if="pendingChange.manager_notes" class="mt-2 whitespace-pre-wrap text-xs">{{ pendingChange.manager_notes }}</div>
            <div v-if="pendingChange.yurik_summary" class="mt-2 whitespace-pre-wrap text-xs">
                <span class="font-medium">Заключение Юрика:</span> {{ pendingChange.yurik_summary }}
            </div>
            <div v-if="pendingChange.rejection_reason" class="mt-2 text-xs text-rose-700 dark:text-rose-300">
                {{ pendingChange.rejection_reason }}
            </div>

            <div v-if="canApprove && pendingChange.status === 'pending_approval'" class="mt-3 flex flex-wrap gap-2">
                <button type="button" :class="crmBtnCreate" @click="resolvePending('approve')">
                    Утвердить и сохранить
                </button>
                <button type="button" :class="crmBtnNeutral" @click="resolvePending('needs_counterparty')">
                    На согласование с контрагентом
                </button>
                <input
                    v-model="resolveForm.reason"
                    type="text"
                    placeholder="Причина отклонения"
                    :class="`${crmFieldFluid} max-w-xs text-sm`"
                />
                <button type="button" class="rounded-lg border border-rose-200 px-3 py-1.5 text-sm text-rose-700 dark:border-rose-900 dark:text-rose-300" @click="resolvePending('reject')">
                    Отклонить
                </button>
            </div>
        </div>

        <div v-if="externalTemplates.length" class="rounded-xl border border-violet-200 bg-violet-50/60 px-4 py-3 text-sm dark:border-violet-900/50 dark:bg-violet-950/20">
            <div class="font-medium text-violet-950 dark:text-violet-100">Внешние DOCX-шаблоны</div>
            <ul class="mt-2 space-y-1 text-xs text-violet-900 dark:text-violet-200">
                <li v-for="template in externalTemplates" :key="template.id">
                    {{ template.name }} <span class="opacity-70">({{ template.code }})</span>
                </li>
            </ul>
            <Link
                :href="route('settings.templates.index')"
                class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-violet-800 underline underline-offset-2 dark:text-violet-200"
            >
                Настройки шаблонов
                <ExternalLink class="h-3 w-3" />
            </Link>
        </div>

        <div v-if="!editor.enabled" class="text-sm text-zinc-500">
            Таблица базовых условий недоступна — выполните миграции.
        </div>

        <template v-else>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="option in editor.party_options ?? []"
                    :key="option.value"
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs font-medium"
                    :class="activeParty === option.value
                        ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                        : 'border-zinc-200 text-zinc-700 dark:border-zinc-700 dark:text-zinc-300'"
                    @click="switchParty(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(240px,300px)]">
                <div class="space-y-2">
                    <div
                        v-for="(row, index) in form.items"
                        :key="row.key"
                        class="flex items-start gap-2 rounded-lg border border-zinc-200 bg-white px-2 py-1.5 dark:border-zinc-700 dark:bg-zinc-900/40"
                    >
                        <span class="mt-2 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-medium dark:bg-zinc-800">{{ index + 1 }}</span>
                        <textarea
                            v-model="row.body"
                            rows="2"
                            :disabled="isPendingForActiveParty"
                            :class="`${crmFieldFluid} min-h-[2.5rem] flex-1 resize-y py-1.5 text-sm`"
                            placeholder="Текст пункта базовых условий"
                        />
                        <div v-if="!isPendingForActiveParty" class="flex shrink-0 gap-0.5 pt-1">
                            <button type="button" class="rounded border border-zinc-200 p-1 dark:border-zinc-700" :disabled="index === 0" @click="moveRow(index, -1)">
                                <ChevronUp class="h-3.5 w-3.5" />
                            </button>
                            <button type="button" class="rounded border border-zinc-200 p-1 dark:border-zinc-700" :disabled="index === form.items.length - 1" @click="moveRow(index, 1)">
                                <ChevronDown class="h-3.5 w-3.5" />
                            </button>
                            <button type="button" class="rounded border border-rose-200 p-1 text-rose-600 dark:border-rose-900" @click="removeRow(index)">
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <div v-if="form.items.length === 0" class="rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-center text-xs text-zinc-500 dark:border-zinc-700">
                        Пункты не заданы — при печати используются общие условия CRM.
                    </div>

                    <div v-if="!canDirectManage && !isPendingForActiveParty" class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                        <label class="block text-sm font-medium">Комментарий для руководителя</label>
                        <textarea v-model="form.manager_notes" rows="2" :class="`${crmFieldFluid} text-sm`" placeholder="Что изменилось и зачем" />
                        <label class="block text-sm font-medium">Заключение Юрика (опционально)</label>
                        <textarea v-model="form.yurik_summary" rows="3" :class="`${crmFieldFluid} text-sm`" placeholder="Краткий вывод ассистента" />
                    </div>

                    <div v-if="!isPendingForActiveParty" class="flex flex-wrap gap-2 pt-1">
                        <button type="button" :class="crmBtnNeutral" @click="addRow">
                            <Plus class="h-4 w-4" />
                            Пункт
                        </button>
                        <button
                            v-if="canDirectManage"
                            type="button"
                            :class="crmBtnCreate"
                            :disabled="form.processing"
                            @click="saveDirectly"
                        >
                            Сохранить в карточку
                        </button>
                        <button
                            v-else
                            type="button"
                            :class="crmBtnCreate"
                            :disabled="form.processing"
                            @click="submitForApproval"
                        >
                            Отправить на согласование
                        </button>
                    </div>
                </div>

                <aside class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 text-xs dark:border-zinc-700 dark:bg-zinc-900/50">
                    <div class="font-medium">Плейсхолдеры DOCX</div>
                    <p class="mt-2 text-zinc-600 dark:text-zinc-300">
                        Якорь cloneRow:
                        <code class="rounded bg-zinc-200 px-1 dark:bg-zinc-800">{{ placeholderHelp.anchor }}</code>
                    </p>
                    <ul class="mt-2 space-y-1 font-mono text-[11px]">
                        <li v-for="macro in placeholderHelp.macros" :key="macro">${{ '{' }}{{ macro }}{{ '}' }}</li>
                    </ul>
                </aside>
            </div>
        </template>
    </section>
</template>

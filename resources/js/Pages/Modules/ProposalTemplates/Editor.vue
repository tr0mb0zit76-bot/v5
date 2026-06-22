<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <CrmPageHeader
            :title="template ? `Редактор: ${template.name}` : 'Новый HTML-шаблон КП'"
            lead="Визуальный конструктор на GrapesJS: блоки, колонки, стили. Переменные — {lead.number}, {counterparty.name}. Preview и PDF — на лиде."
        >
            <template #actions>
                <Link :href="route('modules.proposal-templates.index')" :class="crmBtnSecondary">
                    К списку
                </Link>
            </template>
        </CrmPageHeader>

        <form class="flex min-h-0 flex-1 flex-col gap-4" @submit.prevent="submit">
            <div class="grid gap-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800 md:grid-cols-2 xl:grid-cols-4">
                <div class="space-y-1">
                    <label :class="crmLabel">Название</label>
                    <input v-model="form.name" type="text" :class="crmFieldFluid" required />
                </div>
                <div class="space-y-1">
                    <label :class="crmLabel">Slug</label>
                    <input v-model="form.slug" type="text" :class="crmFieldFluid" placeholder="auto-from-name" />
                </div>
                <label class="inline-flex items-end gap-2 pb-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-zinc-300" />
                    Активен
                </label>
                <div class="flex flex-wrap items-end gap-2">
                    <div class="min-w-[140px] flex-1 space-y-1">
                        <label :class="crmLabel">Preview на лиде</label>
                        <select v-model="previewLeadId" :class="crmFieldFluid">
                            <option value="">Выберите лид</option>
                            <option v-for="lead in previewLeads" :key="lead.id" :value="String(lead.id)">
                                {{ lead.label }}
                            </option>
                        </select>
                    </div>
                    <button
                        type="button"
                        :class="crmBtnSecondary"
                        :disabled="!canPreviewOnLead"
                        @click="openLeadPreview"
                    >
                        Preview
                    </button>
                </div>
            </div>

            <div class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[280px,minmax(0,1fr)]">
                <div class="flex max-h-[720px] flex-col gap-3 overflow-hidden rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="text-sm font-semibold">Переменные</div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Нажмите, чтобы вставить плейсхолдер в выбранный блок на холсте.
                    </p>
                    <input v-model="variableFilter" type="search" :class="crmFieldFluid" placeholder="Поиск переменной" />
                    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto">
                        <button
                            v-for="variable in filteredVariables"
                            :key="variable.path"
                            type="button"
                            class="block w-full rounded-xl border border-zinc-200 px-3 py-2 text-left text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                            @click="insertVariable(variable.path)"
                        >
                            <div class="font-mono text-emerald-700 dark:text-emerald-300">{ {{ variable.path }} }</div>
                            <div class="mt-1 text-zinc-500">{{ variable.label }}</div>
                            <div v-if="variable.group_name" class="mt-0.5 text-[10px] uppercase tracking-wide text-zinc-400">
                                {{ variable.group_name }}
                            </div>
                        </button>
                    </div>
                </div>

                <ProposalGrapesEditor
                    ref="grapesEditorRef"
                    :html-body="initialHtmlBody"
                    :css-inline="initialCssInline"
                />
            </div>

            <div class="flex justify-end gap-2">
                <button type="submit" :class="crmBtnPrimary" :disabled="form.processing">
                    {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import ProposalGrapesEditor from '@/Components/ProposalTemplates/ProposalGrapesEditor.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnPrimary, crmBtnSecondary, crmFieldFluid, crmLabel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'modules' }, () => page),
});

const props = defineProps({
    template: {
        type: Object,
        default: null,
    },
    variables: {
        type: Array,
        default: () => [],
    },
    previewLeads: {
        type: Array,
        default: () => [],
    },
});

const grapesEditorRef = ref(null);
const variableFilter = ref('');
const previewLeadId = ref('');

const defaultHtmlBody = '<table style="width:100%;max-width:600px;margin:0 auto;"><tr><td style="padding:24px;font-family:Arial,sans-serif;"><h1 style="margin:0 0 16px;">Коммерческое предложение</h1><p style="margin:0 0 12px;">Уважаемый {counterparty.contact_person}!</p><p style="margin:0 0 12px;">Маршрут: {route.loading_first_city} → {route.unloading_last_city}</p><p style="margin:0;">Ставка: {offer.price} {offer.currency}</p></td></tr></table>';
const defaultCssInline = 'body{margin:0;padding:0;background:#f4f4f5;}';

const initialHtmlBody = props.template?.html_body ?? defaultHtmlBody;
const initialCssInline = props.template?.css_inline ?? defaultCssInline;

const form = useForm({
    name: props.template?.name ?? '',
    slug: props.template?.slug ?? '',
    is_active: props.template?.is_active ?? true,
    html_body: initialHtmlBody,
    css_inline: initialCssInline,
    visibility: props.template?.visibility ?? 'workspace',
});

const filteredVariables = computed(() => {
    const query = variableFilter.value.trim().toLowerCase();
    if (!query) {
        return props.variables;
    }

    return props.variables.filter((variable) =>
        `${variable.path} ${variable.label} ${variable.group_name ?? ''}`.toLowerCase().includes(query),
    );
});

const canPreviewOnLead = computed(() => Boolean(props.template?.id && previewLeadId.value));

function insertVariable(path) {
    grapesEditorRef.value?.insertVariable(path);
}

function openLeadPreview() {
    if (!canPreviewOnLead.value) {
        return;
    }

    const url = route('modules.proposal-templates.preview', {
        proposalHtmlTemplate: props.template.id,
        lead: Number(previewLeadId.value),
    });

    window.open(url, '_blank', 'noopener,noreferrer');
}

function submit() {
    const exported = grapesEditorRef.value?.syncFromEditor();
    if (exported) {
        form.html_body = exported.html_body;
        form.css_inline = exported.css_inline;
    }

    if (props.template?.id) {
        form.patch(route('modules.proposal-templates.update', props.template.id), { preserveScroll: true });
        return;
    }

    form.post(route('modules.proposal-templates.store'));
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <CrmPageHeader
            :title="template ? `Редактор: ${template.name}` : 'Новый HTML-шаблон КП'"
            title-class="crm-page-title--sm"
        >
            <template #actions>
                <Link :href="route('modules.proposal-templates.index')" :class="toolbarBtnSecondary">
                    К списку
                </Link>
                <button
                    type="button"
                    :class="toolbarBtnSecondary"
                    :disabled="!canPreviewOnLead"
                    @click="openLeadPreview"
                >
                    Preview
                </button>
                <button
                    type="submit"
                    form="proposal-template-form"
                    :class="toolbarBtnPrimary"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                </button>
            </template>
        </CrmPageHeader>

        <form id="proposal-template-form" class="flex min-h-0 flex-1 flex-col gap-2" @submit.prevent="submit">
            <div class="flex flex-wrap items-center gap-2">
                <input
                    v-model="form.name"
                    type="text"
                    :class="toolbarFieldClass"
                    class="min-w-[9rem] max-w-xs flex-1"
                    placeholder="Название"
                    required
                />
                <input
                    v-model="form.slug"
                    type="text"
                    :class="toolbarFieldClass"
                    class="w-28 shrink-0"
                    placeholder="Slug"
                />
                <label class="inline-flex shrink-0 items-center gap-1.5 text-xs text-zinc-600 dark:text-zinc-300">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" />
                    Активен
                </label>
                <select v-model="previewLeadId" :class="toolbarFieldClass" class="min-w-[8rem] max-w-[12rem] shrink-0">
                    <option value="">Лид для preview</option>
                    <option v-for="lead in previewLeads" :key="lead.id" :value="String(lead.id)">
                        {{ lead.label }}
                    </option>
                </select>
            </div>

            <div class="grid min-h-0 flex-1 gap-2 xl:grid-cols-[220px,minmax(0,1fr)]">
                <aside class="flex max-h-none min-h-0 flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950 xl:max-h-none">
                    <div class="flex items-center justify-between gap-2 border-b border-zinc-100 bg-zinc-50/80 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900/70">
                        <div class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">CRM-переменные</div>
                        <span class="rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                            {{ filteredVariables.length }}
                        </span>
                    </div>
                    <div class="px-3 py-2">
                        <input v-model="variableFilter" type="search" :class="compactFieldClass" placeholder="Поиск" />
                    </div>
                    <div class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 pb-3">
                        <button
                            v-for="variable in filteredVariables"
                            :key="variable.path"
                            type="button"
                            class="group flex w-full items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-left transition hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-zinc-700 dark:bg-zinc-950 dark:hover:border-emerald-800 dark:hover:bg-emerald-950/30"
                            :title="variable.label"
                            @click="insertVariable(variable.path)"
                        >
                            <span class="truncate font-mono text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">{ {{ variable.path }} }</span>
                            <span
                                v-if="variable.group_name"
                                class="shrink-0 rounded bg-zinc-100 px-1.5 py-0.5 text-[9px] uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"
                            >
                                {{ variable.group_name }}
                            </span>
                        </button>
                    </div>
                </aside>

                <ProposalGrapesEditor
                    ref="grapesEditorRef"
                    :html-body="initialHtmlBody"
                    :css-inline="initialCssInline"
                />
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
import { crmBtnPrimary, crmBtnSecondary, crmFieldFluid } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'modules' }, () => page),
});

const toolbarFieldClass = `${crmFieldFluid} !h-8 !py-1 !text-xs`;
const toolbarBtnPrimary = `${crmBtnPrimary} !px-3 !py-1.5 !text-xs`;
const toolbarBtnSecondary = `${crmBtnSecondary} !px-3 !py-1.5 !text-xs`;
const compactFieldClass = toolbarFieldClass;

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

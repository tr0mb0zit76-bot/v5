<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4">
        <CrmPageHeader
            :title="template ? `Редактор: ${template.name}` : 'Новый HTML-шаблон КП'"
            lead="Используйте переменные вида {lead.number} или {counterparty.name}. Preview и PDF формируются на лиде."
        >
            <template #actions>
                <Link :href="route('modules.proposal-templates.index')" :class="crmBtnSecondary">
                    К списку
                </Link>
            </template>
        </CrmPageHeader>

        <form class="grid gap-4 xl:grid-cols-[1.2fr,0.8fr]" @submit.prevent="submit">
            <div class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-1">
                        <label :class="crmLabel">Название</label>
                        <input v-model="form.name" type="text" :class="crmFieldFluid" required />
                    </div>
                    <div class="space-y-1">
                        <label :class="crmLabel">Slug</label>
                        <input v-model="form.slug" type="text" :class="crmFieldFluid" placeholder="auto-from-name" />
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-zinc-300" />
                    Активен
                </label>

                <div class="space-y-1">
                    <label :class="crmLabel">HTML-тело</label>
                    <textarea
                        ref="htmlBodyRef"
                        v-model="form.html_body"
                        rows="16"
                        :class="crmFieldFluid"
                        placeholder="<h1>Коммерческое предложение</h1><p>Клиент: {counterparty.name}</p>"
                    />
                </div>

                <div class="space-y-1">
                    <label :class="crmLabel">Inline CSS</label>
                    <textarea
                        v-model="form.css_inline"
                        rows="6"
                        :class="crmFieldFluid"
                        placeholder="body { font-family: Arial, sans-serif; }"
                    />
                </div>

                <div class="flex justify-end gap-2">
                    <button type="submit" :class="crmBtnPrimary" :disabled="form.processing">
                        {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                    </button>
                </div>
            </div>

            <div class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="text-sm font-semibold">Переменные</div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Нажмите, чтобы вставить плейсхолдер в HTML-тело.
                </p>
                <input v-model="variableFilter" type="search" :class="crmFieldFluid" placeholder="Поиск переменной" />
                <div class="max-h-[520px] space-y-2 overflow-y-auto">
                    <button
                        v-for="variable in filteredVariables"
                        :key="variable.path"
                        type="button"
                        class="block w-full rounded-xl border border-zinc-200 px-3 py-2 text-left text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                        @click="insertVariable(variable.path)"
                    >
                        <div class="font-mono text-emerald-700 dark:text-emerald-300">{ {{ variable.path }} }</div>
                        <div class="mt-1 text-zinc-500">{{ variable.label }}</div>
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
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
});

const htmlBodyRef = ref(null);
const variableFilter = ref('');

const form = useForm({
    name: props.template?.name ?? '',
    slug: props.template?.slug ?? '',
    is_active: props.template?.is_active ?? true,
    html_body: props.template?.html_body ?? '<h1>Коммерческое предложение</h1>\n<p>Уважаемый {counterparty.contact_person}!</p>\n<p>Маршрут: {route.loading_first_city} → {route.unloading_last_city}</p>\n<p>Ставка: {offer.price} {offer.currency}</p>',
    css_inline: props.template?.css_inline ?? 'body{font-family:Arial,sans-serif;padding:24px;color:#111}',
    visibility: props.template?.visibility ?? 'workspace',
});

const filteredVariables = computed(() => {
    const query = variableFilter.value.trim().toLowerCase();
    if (!query) {
        return props.variables;
    }

    return props.variables.filter((variable) =>
        `${variable.path} ${variable.label}`.toLowerCase().includes(query),
    );
});

function insertVariable(path) {
    const token = `{${path}}`;
    const textarea = htmlBodyRef.value;
    if (!textarea) {
        form.html_body = `${form.html_body}${token}`;
        return;
    }

    const start = textarea.selectionStart ?? form.html_body.length;
    const end = textarea.selectionEnd ?? start;
    form.html_body = `${form.html_body.slice(0, start)}${token}${form.html_body.slice(end)}`;

    nextTick(() => {
        textarea.focus();
        const caret = start + token.length;
        textarea.setSelectionRange(caret, caret);
    });
}

function submit() {
    if (props.template?.id) {
        form.patch(route('modules.proposal-templates.update', props.template.id), { preserveScroll: true });
        return;
    }

    form.post(route('modules.proposal-templates.store'));
}
</script>

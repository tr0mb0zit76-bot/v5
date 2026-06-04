<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            lead="Шаблон номера заявки для каждой своей компании. Мастер заказа подставляет следующий номер автоматически; менеджер может изменить его вручную."
            title="Автонумератор заявок"
        />

        <p v-if="flashSuccess" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100">
            {{ flashSuccess }}
        </p>

        <div class="grid min-h-0 grid-cols-1 gap-4 xl:grid-cols-[minmax(0,280px)_minmax(0,1fr)]">
            <aside :class="`${crmPanel} flex flex-col gap-2 p-4`">
                <button
                    type="button"
                    class="rounded-xl border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    @click="startCreate"
                >
                    + Новое правило
                </button>
                <button
                    v-for="rule in rules"
                    :key="rule.id"
                    type="button"
                    :class="[
                        'rounded-xl border px-3 py-2 text-left text-sm',
                        editingId === rule.id
                            ? 'border-zinc-900 bg-zinc-100 dark:border-zinc-100 dark:bg-zinc-800'
                            : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900',
                    ]"
                    @click="editRule(rule)"
                >
                    <div class="font-semibold">{{ rule.cipher }}</div>
                    <div class="text-xs text-zinc-500">{{ rule.own_company_name }}</div>
                </button>
            </aside>

            <section :class="`${crmPanel} space-y-4 p-4`">
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Шифр правила</label>
                            <input v-model="form.cipher" type="text" :class="crmFieldFluid" placeholder="exwill_main" />
                            <p class="text-xs text-zinc-500">Латиница, цифры, дефис. Используется в API и отчётах.</p>
                            <p v-if="form.errors.cipher" class="text-xs text-rose-500">{{ form.errors.cipher }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Своя компания</label>
                            <select v-model="form.own_company_id" :class="crmFieldFluid">
                                <option :value="null">Выберите компанию</option>
                                <option v-for="company in ownCompanies" :key="company.id" :value="company.id">
                                    {{ company.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.own_company_id" class="text-xs text-rose-500">{{ form.errors.own_company_id }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Разделитель частей</label>
                        <input v-model="form.separator" type="text" maxlength="3" :class="crmFieldFluid" placeholder="-" />
                    </div>

                    <SegmentField
                        label="Префикс"
                        :type-model="form.prefix_type"
                        :value-model="form.prefix_value"
                        :options="segmentTypeOptions"
                        @update:type-model="form.prefix_type = $event"
                        @update:value-model="form.prefix_value = $event"
                    />
                    <SegmentField
                        label="Тело"
                        :type-model="form.body_type"
                        :value-model="form.body_value"
                        :options="segmentTypeOptions"
                        @update:type-model="form.body_type = $event"
                        @update:value-model="form.body_value = $event"
                    />
                    <SegmentField
                        label="Суффикс"
                        :type-model="form.suffix_type"
                        :value-model="form.suffix_value"
                        :options="segmentTypeOptions"
                        @update:type-model="form.suffix_type = $event"
                        @update:value-model="form.suffix_value = $event"
                    />

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Сброс счётчика</label>
                            <select v-model="form.sequence_scope" :class="crmFieldFluid">
                                <option v-for="opt in sequenceScopeOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Длина номера (нули слева)</label>
                            <input v-model.number="form.sequence_pad" type="number" min="0" max="8" :class="crmFieldFluid" />
                            <p class="text-xs text-zinc-500">0 — без дополнения (1, 2, 3…). 4 — 0001, 0002…</p>
                        </div>
                    </div>

                    <div
                        v-if="previewSample"
                        class="rounded-xl border border-sky-200 bg-sky-50/80 px-4 py-3 text-sm text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/20 dark:text-sky-100"
                    >
                        <div>Пример: <span class="font-mono font-semibold">{{ previewSample }}</span></div>
                        <div v-if="previewSampleNext" class="mt-1 text-xs text-sky-800 dark:text-sky-200">
                            Следующий: <span class="font-mono">{{ previewSampleNext }}</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="submit"
                            class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Сохранение…' : (editingId ? 'Сохранить' : 'Создать правило') }}
                        </button>
                        <button
                            v-if="editingId"
                            type="button"
                            class="rounded-xl border border-rose-300 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-200"
                            @click="destroyRule"
                        >
                            Удалить
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>

<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import SegmentField from '@/Pages/Settings/System/Components/OrderNumberingSegmentField.vue';
import { crmFieldFluid, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, {
        activeKey: 'settings',
        activeSubKey: 'system',
        activeLeafKey: 'order-numbering',
    }, () => page),
});

const props = defineProps({
    rules: { type: Array, default: () => [] },
    ownCompanies: { type: Array, default: () => [] },
    segmentTypeOptions: { type: Array, default: () => [] },
    sequenceScopeOptions: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? '');
const editingId = ref(null);
const previewSample = ref('');
const previewSampleNext = ref('');

const blankForm = () => ({
    cipher: '',
    own_company_id: null,
    separator: '-',
    prefix_type: 'sequence',
    prefix_value: '',
    body_type: 'text',
    body_value: '',
    suffix_type: 'month',
    suffix_value: '',
    sequence_pad: 0,
    sequence_scope: 'month',
});

const form = useForm(blankForm());

function startCreate() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    Object.assign(form, blankForm());
}

function editRule(rule) {
    editingId.value = rule.id;
    form.clearErrors();
    form.cipher = rule.cipher;
    form.own_company_id = rule.own_company_id;
    form.separator = rule.separator;
    form.prefix_type = rule.prefix_type;
    form.prefix_value = rule.prefix_value ?? '';
    form.body_type = rule.body_type;
    form.body_value = rule.body_value ?? '';
    form.suffix_type = rule.suffix_type;
    form.suffix_value = rule.suffix_value ?? '';
    form.sequence_pad = rule.sequence_pad;
    form.sequence_scope = rule.sequence_scope;
}

function submit() {
    if (editingId.value) {
        form.patch(route('settings.system.order-numbering.update', { orderNumberingRule: editingId.value }), {
            preserveScroll: true,
        });

        return;
    }

    form.post(route('settings.system.order-numbering.store'), {
        preserveScroll: true,
        onSuccess: () => {
            startCreate();
        },
    });
}

function destroyRule() {
    if (!editingId.value || !window.confirm('Удалить это правило автонумерации?')) {
        return;
    }

    router.delete(route('settings.system.order-numbering.destroy', { orderNumberingRule: editingId.value }), {
        preserveScroll: true,
        onSuccess: () => startCreate(),
    });
}

let previewTimer = null;

async function refreshPreview() {
    try {
        const { data } = await axios.post(route('settings.system.order-numbering.preview'), {
            separator: form.separator,
            prefix_type: form.prefix_type,
            prefix_value: form.prefix_value,
            body_type: form.body_type,
            body_value: form.body_value,
            suffix_type: form.suffix_type,
            suffix_value: form.suffix_value,
            sequence_pad: form.sequence_pad,
            sequence_scope: form.sequence_scope,
        });
        previewSample.value = data?.sample ?? '';
        previewSampleNext.value = data?.sample_next ?? '';
    } catch {
        previewSample.value = '';
        previewSampleNext.value = '';
    }
}

watch(
    () => [
        form.separator,
        form.prefix_type,
        form.prefix_value,
        form.body_type,
        form.body_value,
        form.suffix_type,
        form.suffix_value,
        form.sequence_pad,
        form.sequence_scope,
    ],
    () => {
        window.clearTimeout(previewTimer);
        previewTimer = window.setTimeout(() => {
            void refreshPreview();
        }, 300);
    },
    { immediate: true },
);

if (props.rules.length > 0) {
    editRule(props.rules[0]);
}
</script>

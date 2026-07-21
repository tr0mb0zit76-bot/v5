<script setup>
import { computed, ref } from 'vue';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmFieldFluid,
    crmPanel,
} from '@/support/crmUi.js';
import ContractorAsyncSearchSelect from '@/Components/Crm/ContractorAsyncSearchSelect.vue';

const props = defineProps({
    partyOptions: {
        type: Array,
        default: () => [],
    },
    ownCompanyOptions: {
        type: Array,
        default: () => [],
    },
});

const party = ref('customer');
const contractorId = ref(null);
const contractorLabel = ref('');
const ownCompanyId = ref(props.ownCompanyOptions[0]?.id ?? null);
const sourceFile = ref(null);
const fileInput = ref(null);

const analyzing = ref(false);
const applying = ref(false);
const errorMessage = ref('');
const draftToken = ref(null);
const originalFilename = ref('');
const existingPlaceholders = ref([]);
const proposals = ref([]);

const enabledCount = computed(() => proposals.value.filter((row) => row.enabled).length);

const partyHint = computed(() => {
    if (party.value === 'customer') {
        return 'Форма заказчика: своя компания → lp_ (в тексте часто «Перевозчик»), контрагент → cp_ («Заказчик»).';
    }
    if (party.value === 'carrier') {
        return 'Форма перевозчика: своя компания → lp_, контрагент-перевозчик → dp_.';
    }

    return 'Внутренняя форма: подстановка по реквизитам выбранных сторон.';
});

function onFileChange(event) {
    const file = event.target.files?.[0] ?? null;
    sourceFile.value = file;
    draftToken.value = null;
    proposals.value = [];
    existingPlaceholders.value = [];
    errorMessage.value = '';
}

function csrfHeaders() {
    return {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

async function analyze() {
    errorMessage.value = '';
    if (!sourceFile.value) {
        errorMessage.value = 'Выберите DOCX-файл.';
        return;
    }

    analyzing.value = true;
    try {
        const body = new FormData();
        body.append('source_file', sourceFile.value);
        body.append('party', party.value);
        if (contractorId.value) {
            body.append('contractor_id', String(contractorId.value));
        }
        if (ownCompanyId.value) {
            body.append('own_company_id', String(ownCompanyId.value));
        }

        const response = await fetch(route('settings.templates.draft-converter.analyze'), {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body,
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            errorMessage.value = payload.message
                || payload.errors?.source_file?.[0]
                || 'Не удалось разобрать черновик.';
            return;
        }

        draftToken.value = payload.draft_token;
        originalFilename.value = payload.original_filename ?? '';
        existingPlaceholders.value = payload.existing_placeholders ?? [];
        proposals.value = (payload.proposals ?? []).map((row) => ({
            ...row,
            enabled: row.enabled !== false,
        }));
    } catch (error) {
        errorMessage.value = error?.message || 'Ошибка сети при анализе.';
    } finally {
        analyzing.value = false;
    }
}

async function applyAndDownload() {
    errorMessage.value = '';
    if (!draftToken.value) {
        errorMessage.value = 'Сначала выполните анализ.';
        return;
    }

    applying.value = true;
    try {
        const body = new FormData();
        body.append('draft_token', draftToken.value);
        body.append(
            'download_filename',
            (originalFilename.value || 'shablon').replace(/\.docx$/i, '') + '_CRM.docx',
        );

        const enabled = proposals.value.filter((row) => row.enabled);
        enabled.forEach((row, index) => {
            body.append(`replacements[${index}][find]`, row.find);
            body.append(`replacements[${index}][replace]`, row.replace);
            body.append(`replacements[${index}][enabled]`, '1');
        });

        if (enabled.length === 0) {
            errorMessage.value = 'Включите хотя бы одну замену, либо скачайте исходник без изменений через повторную загрузку.';
            return;
        }

        const response = await fetch(route('settings.templates.draft-converter.apply'), {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body,
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            errorMessage.value = payload.message || 'Не удалось применить замены.';
            return;
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = (originalFilename.value || 'shablon').replace(/\.docx$/i, '') + '_CRM.docx';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    } catch (error) {
        errorMessage.value = error?.message || 'Ошибка сети при скачивании.';
    } finally {
        applying.value = false;
    }
}

function confidenceClass(level) {
    if (level === 'high') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300';
    }
    if (level === 'medium') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300';
    }

    return 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
}

function confidenceLabel(level) {
    if (level === 'high') {
        return 'высокая';
    }
    if (level === 'medium') {
        return 'средняя';
    }

    return 'низкая';
}
</script>

<template>
    <section :class="`${crmPanel} flex min-h-0 flex-1 flex-col gap-4 overflow-hidden p-4`">
        <div class="shrink-0 space-y-1">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Черновик → шаблон</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Загрузите заполненный DOCX клиента, укажите сторону и контрагента — система предложит замены на плейсхолдеры.
                Проверьте список и скачайте готовый файл для загрузки во вкладку «DOCX-шаблоны».
            </p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ partyHint }}</p>
        </div>

        <div class="grid shrink-0 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="block text-sm">
                <span class="mb-1 block text-zinc-600 dark:text-zinc-300">Сторона шаблона</span>
                <select v-model="party" :class="crmFieldFluid">
                    <option
                        v-for="option in partyOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>

            <label class="block text-sm">
                <span class="mb-1 block text-zinc-600 dark:text-zinc-300">Своя компания</span>
                <select v-model="ownCompanyId" :class="crmFieldFluid">
                    <option :value="null">Авто (первая)</option>
                    <option
                        v-for="company in ownCompanyOptions"
                        :key="company.id"
                        :value="company.id"
                    >
                        {{ company.name }}
                    </option>
                </select>
            </label>

            <label class="block text-sm">
                <span class="mb-1 block text-zinc-600 dark:text-zinc-300">Контрагент формы</span>
                <ContractorAsyncSearchSelect
                    v-model="contractorId"
                    :selected-label="contractorLabel"
                    :search-type="party === 'carrier' ? 'carrier' : (party === 'customer' ? 'customer' : '')"
                    clear-label="Не выбран"
                    placeholder="Поиск по названию или ИНН"
                    @select="(option) => { contractorLabel = option.name; }"
                    @clear="() => { contractorLabel = ''; }"
                />
            </label>

            <label class="block text-sm">
                <span class="mb-1 block text-zinc-600 dark:text-zinc-300">DOCX-черновик</span>
                <input
                    ref="fileInput"
                    type="file"
                    accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                    class="block w-full text-sm text-zinc-700 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:text-zinc-200 dark:file:bg-zinc-800"
                    @change="onFileChange"
                >
            </label>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <button
                type="button"
                :class="crmBtnCreate"
                :disabled="analyzing || !sourceFile"
                @click="analyze"
            >
                {{ analyzing ? 'Анализ…' : 'Разобрать черновик' }}
            </button>
            <button
                type="button"
                :class="crmBtnNeutral"
                :disabled="applying || !draftToken || enabledCount === 0"
                @click="applyAndDownload"
            >
                {{ applying ? 'Сборка…' : `Скачать шаблон (${enabledCount})` }}
            </button>
        </div>

        <div
            v-if="errorMessage"
            class="shrink-0 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-100"
            role="alert"
        >
            {{ errorMessage }}
        </div>

        <div
            v-if="existingPlaceholders.length"
            class="shrink-0 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/40"
        >
            <div class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-500">Уже есть в файле</div>
            <div class="flex flex-wrap gap-1.5">
                <span
                    v-for="name in existingPlaceholders"
                    :key="name"
                    class="rounded-full bg-white px-2.5 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                >
                    ${'{' + name + '}'}
                </span>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-auto">
            <table
                v-if="proposals.length"
                class="min-w-full border-collapse text-sm"
            >
                <thead class="sticky top-0 z-10 bg-zinc-100 dark:bg-zinc-800">
                    <tr class="text-left text-zinc-600 dark:text-zinc-200">
                        <th class="border-b border-zinc-200 px-3 py-2 font-medium dark:border-zinc-700">Вкл</th>
                        <th class="border-b border-zinc-200 px-3 py-2 font-medium dark:border-zinc-700">Найти</th>
                        <th class="border-b border-zinc-200 px-3 py-2 font-medium dark:border-zinc-700">Плейсхолдер</th>
                        <th class="border-b border-zinc-200 px-3 py-2 font-medium dark:border-zinc-700">Уверенность</th>
                        <th class="border-b border-zinc-200 px-3 py-2 font-medium dark:border-zinc-700">Почему</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in proposals"
                        :key="row.id"
                        class="border-b border-zinc-100 align-top dark:border-zinc-800"
                    >
                        <td class="px-3 py-2">
                            <input v-model="row.enabled" type="checkbox" class="h-4 w-4">
                        </td>
                        <td class="max-w-xs px-3 py-2 break-words font-mono text-xs text-zinc-700 dark:text-zinc-300">
                            {{ row.find.startsWith('@@nth:') ? row.find.replace(/^@@nth:\d+@@/, '(2-е вхождение) ') : row.find }}
                        </td>
                        <td class="px-3 py-2">
                            <input
                                v-model="row.replace"
                                type="text"
                                :class="`${crmFieldFluid} font-mono text-xs`"
                            >
                            <div class="mt-1 font-mono text-[11px] text-zinc-500">{{ row.path }}</div>
                        </td>
                        <td class="px-3 py-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="confidenceClass(row.confidence)"
                            >
                                {{ confidenceLabel(row.confidence) }}
                            </span>
                        </td>
                        <td class="max-w-md px-3 py-2 text-xs text-zinc-600 dark:text-zinc-300">
                            {{ row.reason }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <p
                v-else-if="draftToken"
                class="text-sm text-zinc-500"
            >
                Предложений нет: в файле не нашлись реквизиты выбранных сторон и известные опечатки.
                Можно вручную дописать плейсхолдеры в Word и загрузить как внешний шаблон.
            </p>
        </div>
    </section>
</template>

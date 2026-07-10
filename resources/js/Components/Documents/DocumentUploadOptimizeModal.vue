<script setup>
import { computed, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import {
    crmBtnNeutral,
    crmBtnPrimary,
    crmBtnSecondary,
    crmModalFormBody,
    crmModalFormShell,
} from '@/support/crmUi.js';
import {
    fileFromOptimizedPdfBase64,
    formatFileSizeHuman,
    optimizeDocumentPdf,
} from '@/support/documentOptimizeClient.js';
import { formatDocumentRegistryError } from '@/support/documentRegistryClient.js';
import { computeDocumentUploadBudget } from '@/support/documentUploadClientCheck.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    /** @type {import('vue').PropType<{ file: File, limits: object, budget: object }|null>} */
    state: { type: Object, default: null },
});

const emit = defineEmits(['accept', 'cancel']);

const modalVisible = computed(() => Boolean(props.show && props.state?.file instanceof File));

const phase = ref('offer');
const loading = ref(false);
const error = ref('');
const result = ref(null);
const previewUrl = ref('');
const optimizedFile = ref(null);
const optimizedBudget = ref(null);

const fileName = computed(() => props.state?.file?.name ?? 'document.pdf');
const originalBytes = computed(() => Number(props.state?.file?.size) || 0);
const limitBytes = computed(() => Number(props.state?.budget?.maxBytes) || 0);
const estimatedPages = computed(() => Math.max(1, Number(props.state?.budget?.pages) || 1));
const kbPerPage = computed(() => {
    const bpp = Number(props.state?.limits?.bytes_per_page) || 600 * 1024;

    return Math.round(bpp / 1024);
});
const limitExplanation = computed(() => {
    const bytes = limitBytes.value;
    if (bytes <= 0) {
        return 'не удалось оценить (обновите страницу)';
    }

    return `~${estimatedPages.value} стр. × ${kbPerPage.value} КиБ ≈ ${formatFileSizeHuman(bytes)}`;
});

watch(
    () => props.show,
    (visible) => {
        if (visible) {
            resetState();
        } else {
            revokePreview();
        }
    },
);

function resetState() {
    phase.value = 'offer';
    loading.value = false;
    error.value = '';
    result.value = null;
    optimizedFile.value = null;
    optimizedBudget.value = null;
    revokePreview();
}

function revokePreview() {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = '';
    }
}

async function runOptimize() {
    if (!props.state?.file) {
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const payload = await optimizeDocumentPdf(props.state.file);
        result.value = payload;

        const file = fileFromOptimizedPdfBase64(payload.pdf_base64, props.state.file.name);
        optimizedFile.value = file;
        optimizedBudget.value = await computeDocumentUploadBudget(file, props.state.limits);

        revokePreview();
        previewUrl.value = URL.createObjectURL(file);
        phase.value = 'preview';
    } catch (err) {
        error.value = formatDocumentRegistryError(err);
        phase.value = 'error';
    } finally {
        loading.value = false;
    }
}

function acceptOptimized() {
    if (!optimizedFile.value) {
        return;
    }

    emit('accept', optimizedFile.value);
}

function cancelFlow() {
    emit('cancel');
}

const withinBudgetAfter = computed(() => {
    if (result.value?.within_budget === true) {
        return true;
    }

    if (optimizedFile.value && optimizedBudget.value) {
        return optimizedFile.value.size <= optimizedBudget.value.maxBytes;
    }

    return false;
});
</script>

<template>
    <Modal :show="modalVisible" max-width="3xl" @close="cancelFlow">
        <section :class="crmModalFormShell">
            <CrmModalHeader
                eyebrow="Подготовка файла"
                title="Файл превышает допустимый размер"
                @close="cancelFlow"
            />

            <div :class="`${crmModalFormBody} space-y-4 px-6 pb-6 pt-2`">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ fileName }}</span>
                    — {{ formatFileSizeHuman(originalBytes) }}.
                    Лимит для загрузки: {{ limitExplanation }}.
                </p>
                <p
                    v-if="originalBytes === 0"
                    class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    Файл пустой или браузер не передал размер. Выберите PDF заново.
                </p>

                <div v-if="phase === 'offer'" class="space-y-4">
                    <p class="text-sm text-zinc-700 dark:text-zinc-300">
                        CRM может сжать PDF на сервере (без изменения текста). После оптимизации вы увидите предпросмотр и решите, сохранять ли файл.
                    </p>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" :class="crmBtnNeutral" @click="cancelFlow">
                            Отмена — подготовлю сам
                        </button>
                        <button
                            type="button"
                            :class="crmBtnPrimary"
                            :disabled="loading"
                            @click="runOptimize"
                        >
                            {{ loading ? 'Оптимизация…' : 'Оптимизировать PDF' }}
                        </button>
                    </div>
                </div>

                <div v-else-if="phase === 'preview' && optimizedFile" class="space-y-4">
                    <div class="grid gap-2 text-sm sm:grid-cols-2">
                        <div class="rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                            <div class="text-xs text-zinc-500">Было</div>
                            <div class="font-medium">{{ formatFileSizeHuman(originalBytes) }}</div>
                        </div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 px-3 py-2 dark:border-emerald-900 dark:bg-emerald-950/30">
                            <div class="text-xs text-emerald-800 dark:text-emerald-200">Стало</div>
                            <div class="font-medium text-emerald-950 dark:text-emerald-100">
                                {{ formatFileSizeHuman(optimizedFile.size) }}
                                <span v-if="result?.method" class="text-xs font-normal text-emerald-800/80">({{ result.method }})</span>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="!withinBudgetAfter"
                        class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                    >
                        Даже после оптимизации файл может не пройти лимит сервера. Проверьте предпросмотр; при необходимости отмените и уменьшите документ вручную.
                    </p>

                    <ul v-if="result?.warnings?.length" class="list-inside list-disc text-xs text-amber-800 dark:text-amber-200">
                        <li v-for="(warning, index) in result.warnings" :key="index">{{ warning }}</li>
                    </ul>

                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <iframe
                            v-if="previewUrl"
                            :src="previewUrl"
                            class="h-[min(420px,50vh)] w-full bg-zinc-100 dark:bg-zinc-900"
                            title="Предпросмотр оптимизированного PDF"
                        />
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" :class="crmBtnNeutral" @click="cancelFlow">
                            Отмена
                        </button>
                        <button type="button" :class="crmBtnSecondary" @click="phase = 'offer'; error = ''">
                            Повторить оптимизацию
                        </button>
                        <button type="button" :class="crmBtnPrimary" @click="acceptOptimized">
                            Использовать этот файл
                        </button>
                    </div>
                </div>

                <div v-else-if="phase === 'error'" class="space-y-4">
                    <p class="text-sm text-rose-700 dark:text-rose-300">{{ error }}</p>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" :class="crmBtnNeutral" @click="cancelFlow">
                            Отмена — подготовлю сам
                        </button>
                        <button type="button" :class="crmBtnSecondary" :disabled="loading" @click="runOptimize">
                            Повторить
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </Modal>
</template>

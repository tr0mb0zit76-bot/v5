<script setup>
import { computed, ref, watch } from 'vue';
import {
    fileFromOptimizedPdfBase64,
    formatFileSizeHuman,
    optimizeDocumentPdf,
} from '@/support/documentOptimizeClient.js';
import { formatDocumentRegistryError } from '@/support/documentRegistryClient.js';
import { computeDocumentUploadBudget } from '@/support/documentUploadClientCheck.js';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** @type {import('vue').PropType<{ file: File, limits: object, budget: object }|null>} */
    state: { type: Object, default: null },
});

const emit = defineEmits(['accept', 'cancel']);

const phase = ref('offer');
const loading = ref(false);
const error = ref('');
const result = ref(null);
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
        return 'не удалось оценить';
    }

    return `~${estimatedPages.value} стр. × ${kbPerPage.value} КиБ ≈ ${formatFileSizeHuman(bytes)}`;
});

const withinBudgetAfter = computed(() => {
    if (result.value?.within_budget === true) {
        return true;
    }

    if (optimizedFile.value && optimizedBudget.value) {
        return optimizedFile.value.size <= optimizedBudget.value.maxBytes;
    }

    return false;
});

watch(
    () => props.open,
    (visible) => {
        if (visible) {
            phase.value = 'offer';
            loading.value = false;
            error.value = '';
            result.value = null;
            optimizedFile.value = null;
            optimizedBudget.value = null;
        }
    },
);

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
</script>

<template>
    <div
        v-if="open && state?.file"
        class="absolute inset-0 z-40 flex flex-col justify-end bg-black/70"
        @click.self="cancelFlow"
    >
        <div class="max-h-[78dvh] overflow-y-auto rounded-t-3xl border border-white/10 bg-zinc-900 p-4 pb-[calc(1rem+env(safe-area-inset-bottom,0px))]">
            <div class="mb-3 text-sm font-semibold text-zinc-100">Файл слишком большой</div>
            <p class="text-sm text-zinc-400">
                <span class="font-medium text-zinc-200">{{ fileName }}</span>
                — {{ formatFileSizeHuman(originalBytes) }}.
                Лимит: {{ limitExplanation }}.
            </p>

            <div v-if="phase === 'offer'" class="mt-4 space-y-3">
                <p class="text-sm text-zinc-300">
                    CRM может сжать PDF на сервере без изменения текста. После оптимизации файл можно загрузить в заказ.
                </p>
                <div class="flex flex-col gap-2">
                    <button
                        type="button"
                        class="rounded-2xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white active:bg-sky-500 disabled:opacity-50"
                        :disabled="loading"
                        @click="runOptimize"
                    >
                        {{ loading ? 'Оптимизация…' : 'Оптимизировать PDF' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border border-white/10 px-4 py-3 text-sm text-zinc-300 active:bg-white/10"
                        @click="cancelFlow"
                    >
                        Отмена — подготовлю сам
                    </button>
                </div>
            </div>

            <div v-else-if="phase === 'preview' && optimizedFile" class="mt-4 space-y-3">
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-2xl border border-white/10 px-3 py-2">
                        <div class="text-xs text-zinc-500">Было</div>
                        <div class="font-medium text-zinc-100">{{ formatFileSizeHuman(originalBytes) }}</div>
                    </div>
                    <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2">
                        <div class="text-xs text-emerald-200">Стало</div>
                        <div class="font-medium text-emerald-100">
                            {{ formatFileSizeHuman(optimizedFile.size) }}
                        </div>
                    </div>
                </div>
                <p
                    v-if="!withinBudgetAfter"
                    class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-100"
                >
                    Даже после оптимизации файл может не пройти лимит. Попробуйте загрузить или уменьшите документ вручную.
                </p>
                <ul v-if="result?.warnings?.length" class="list-inside list-disc text-xs text-amber-200">
                    <li v-for="(warning, index) in result.warnings" :key="index">{{ warning }}</li>
                </ul>
                <div class="flex flex-col gap-2">
                    <button
                        type="button"
                        class="rounded-2xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white active:bg-sky-500"
                        @click="acceptOptimized"
                    >
                        Использовать этот файл
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border border-white/10 px-4 py-3 text-sm text-zinc-300 active:bg-white/10"
                        @click="cancelFlow"
                    >
                        Отмена
                    </button>
                </div>
            </div>

            <div v-else-if="phase === 'error'" class="mt-4 space-y-3">
                <p class="text-sm text-rose-300">{{ error }}</p>
                <div class="flex flex-col gap-2">
                    <button
                        type="button"
                        class="rounded-2xl border border-white/10 px-4 py-3 text-sm text-zinc-300 active:bg-white/10"
                        :disabled="loading"
                        @click="runOptimize"
                    >
                        Повторить
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl px-4 py-3 text-sm text-zinc-500 active:bg-white/10"
                        @click="cancelFlow"
                    >
                        Отмена
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

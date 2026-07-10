<script setup>
defineProps({
    intakeLoading: { type: Boolean, default: false },
    intakeSelectedFile: { type: Object, default: null },
    intakeError: { type: String, default: '' },
    intakePreview: { type: Object, default: null },
});

defineEmits(['file-selected', 'extract', 'apply']);
</script>

<template>
    <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50/80 px-4 py-3 dark:border-sky-900/50 dark:bg-sky-950/20">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="text-sm font-semibold text-sky-950 dark:text-sky-100">Заполнить из заявки заказчика</div>
                <p class="mt-1 text-xs text-sky-900/80 dark:text-sky-200/80">PDF или DOCX с текстовым слоем. Данные попадут в форму — проверьте перед сохранением.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <input
                    type="file"
                    accept=".pdf,.docx,image/jpeg,image/png,image/webp"
                    class="max-w-xs text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-medium dark:file:bg-zinc-800"
                    @change="$emit('file-selected', $event)"
                >
                <button
                    type="button"
                    class="rounded-lg bg-sky-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-800 disabled:opacity-60"
                    :disabled="intakeLoading || !intakeSelectedFile"
                    @click="$emit('extract')"
                >
                    {{ intakeLoading ? 'Распознавание…' : 'Распознать' }}
                </button>
            </div>
        </div>
        <p v-if="intakeError" class="mt-2 text-xs text-rose-700 dark:text-rose-300">{{ intakeError }}</p>
        <div v-if="intakePreview" class="mt-3 space-y-2 rounded-lg border border-sky-200/80 bg-white/70 p-3 dark:border-sky-900/40 dark:bg-zinc-900/40">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-xs font-medium text-zinc-700 dark:text-zinc-200">
                    Черновик #{{ intakePreview.draft_id }}
                    <span v-if="intakePreview.confidence != null"> · уверенность {{ Math.round(intakePreview.confidence * 100) }}%</span>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
                    @click="$emit('apply')"
                >
                    Применить к форме
                </button>
            </div>
            <ul v-if="intakePreview.preview?.length" class="grid gap-1 text-xs text-zinc-700 dark:text-zinc-300 sm:grid-cols-2">
                <li v-for="row in intakePreview.preview" :key="row.label">
                    <span class="text-zinc-500">{{ row.label }}:</span> {{ row.value }}
                </li>
            </ul>
            <ul v-if="intakePreview.warnings?.length" class="text-xs text-amber-800 dark:text-amber-200">
                <li v-for="(warning, index) in intakePreview.warnings" :key="index">{{ warning }}</li>
            </ul>
        </div>
    </div>
</template>

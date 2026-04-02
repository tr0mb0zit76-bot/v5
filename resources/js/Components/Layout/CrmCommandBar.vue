<template>
    <div class="w-full rounded-3xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
        <div class="flex items-end gap-2 p-2">
            <button
                type="button"
                class="h-11 w-11 shrink-0 rounded-2xl border border-zinc-200 dark:border-zinc-700 flex items-center justify-center hover:bg-zinc-100 dark:hover:bg-zinc-800"
                @click="showActions = true"
            >
                <Sparkles class="h-5 w-5" />
            </button>

            <div class="flex-1 min-w-0">
                <div class="flex items-end gap-2 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2">
                    <textarea
                        ref="textareaRef"
                        v-model="message"
                        rows="1"
                        class="w-full resize-none bg-transparent outline-none text-sm placeholder:text-zinc-400 dark:placeholder:text-zinc-500"
                        placeholder="Напишите команду, вопрос или задачу для ИИ..."
                        @keydown="handleKeydown"
                        @input="autosize"
                    />

                    <label
                        class="h-9 w-9 shrink-0 rounded-xl flex items-center justify-center cursor-pointer hover:bg-zinc-200/70 dark:hover:bg-zinc-700/70"
                    >
                        <Paperclip class="h-4 w-4" />
                        <input type="file" class="hidden" multiple @change="handleFiles" />
                    </label>

                    <button
                        type="button"
                        class="h-9 w-9 shrink-0 rounded-xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 flex items-center justify-center disabled:opacity-40"
                        :disabled="isDisabled"
                        @click="submit"
                    >
                        <SendHorizontal class="h-4 w-4" />
                    </button>
                </div>

                <div v-if="attachedFiles.length" class="mt-2 flex flex-wrap gap-2">
                    <div
                        v-for="file in attachedFiles"
                        :key="file.name + file.size"
                        class="inline-flex items-center gap-2 rounded-full bg-zinc-100 dark:bg-zinc-800 px-3 py-1 text-xs"
                    >
                        <Paperclip class="h-3.5 w-3.5" />
                        <span class="max-w-[180px] truncate">{{ file.name }}</span>
                        <button type="button" @click="removeFile(file)">×</button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showActions"
            class="border-t border-zinc-200 dark:border-zinc-800 px-2 py-2"
        >
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                <button
                    v-for="action in actions"
                    :key="action.key"
                    type="button"
                    class="rounded-2xl border border-zinc-200 dark:border-zinc-700 px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                    @click="pickAction(action)"
                >
                    <div class="font-medium text-sm">{{ action.title }}</div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ action.description }}</div>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';
import { Paperclip, SendHorizontal, Sparkles } from 'lucide-vue-next';

const emit = defineEmits(['submit']);

const message = ref('');
const attachedFiles = ref([]);
const showActions = ref(false);
const textareaRef = ref(null);

const actions = [
    {
        key: 'create-order',
        title: 'Создать заказ',
        description: 'Сформировать заказ из текстового описания',
        prompt: 'Создай заказ: ',
    },
    {
        key: 'low-margin-report',
        title: 'Низкая маржа',
        description: 'Показать заказы с низкой маржой',
        prompt: 'Покажи заказы с маржой ниже ',
    },
    {
        key: 'contract-protocol',
        title: 'Протокол разногласий',
        description: 'Подготовить проект документа по договору',
        prompt: 'Подготовь протокол разногласий по договору: ',
    },
];

const isDisabled = computed(() => {
    return !message.value.trim() && attachedFiles.value.length === 0;
});

function autosize() {
    const el = textareaRef.value;
    if (!el) return;

    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 160)}px`;
}

function handleFiles(event) {
    const files = Array.from(event.target.files || []);
    attachedFiles.value = [...attachedFiles.value, ...files];
    event.target.value = '';
}

function removeFile(fileToRemove) {
    attachedFiles.value = attachedFiles.value.filter(
        (file) => !(file.name === fileToRemove.name && file.size === fileToRemove.size)
    );
}

function submit() {
    if (isDisabled.value) return;

    emit('submit', {
        message: message.value,
        files: attachedFiles.value,
    });

    message.value = '';
    attachedFiles.value = [];

    nextTick(() => {
        if (textareaRef.value) {
            textareaRef.value.style.height = 'auto';
        }
    });
}

function handleKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        submit();
    }
}

function pickAction(action) {
    message.value = action.prompt;
    showActions.value = false;

    nextTick(() => {
        textareaRef.value?.focus();
        autosize();
    });
}
</script>
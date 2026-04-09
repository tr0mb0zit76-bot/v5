<template>
    <div class="w-full rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div
            v-if="showActions"
            class="border-b border-zinc-200 px-3 py-3 dark:border-zinc-800"
        >
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tile in quickTiles"
                    :key="tile.key"
                    type="button"
                    class="group flex h-14 w-14 shrink-0 flex-col items-center justify-center gap-0.5 rounded-2xl border border-zinc-200 bg-zinc-50 text-zinc-700 transition hover:border-zinc-400 hover:bg-white dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:border-zinc-500 dark:hover:bg-zinc-900"
                    :title="tile.label"
                    @click="goQuick(tile)"
                >
                    <component :is="tile.icon" class="h-5 w-5 shrink-0 text-zinc-600 dark:text-zinc-300" />
                    <span class="max-w-[3.25rem] truncate px-0.5 text-center text-[9px] font-medium leading-tight text-zinc-500 dark:text-zinc-400">{{ tile.short }}</span>
                </button>
            </div>
        </div>

        <div class="flex items-end gap-2 p-2">
            <button
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-zinc-200 text-zinc-600 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                :class="showActions ? 'border-zinc-900 bg-zinc-100 text-zinc-900 dark:border-zinc-400 dark:bg-zinc-800 dark:text-zinc-100' : ''"
                :title="showActions ? 'Скрыть быстрые действия' : 'Быстрые действия'"
                @click="showActions = !showActions"
            >
                <Sparkles class="h-5 w-5" />
            </button>

            <div class="min-w-0 flex-1">
                <div class="flex items-end gap-2 rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                    <textarea
                        ref="textareaRef"
                        v-model="message"
                        rows="1"
                        class="w-full resize-none bg-transparent text-sm outline-none placeholder:text-zinc-400 dark:placeholder:text-zinc-500"
                        placeholder="Напишите команду, вопрос или задачу для ИИ..."
                        @keydown="handleKeydown"
                        @input="autosize"
                    />

                    <label
                        class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-xl hover:bg-zinc-200/70 dark:hover:bg-zinc-700/70"
                    >
                        <Paperclip class="h-4 w-4" />
                        <input type="file" class="hidden" multiple @change="handleFiles">
                    </label>

                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-zinc-900 text-white disabled:opacity-40 dark:bg-white dark:text-zinc-900"
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
                        class="inline-flex items-center gap-2 rounded-full bg-zinc-100 px-3 py-1 text-xs dark:bg-zinc-800"
                    >
                        <Paperclip class="h-3.5 w-3.5" />
                        <span class="max-w-[180px] truncate">{{ file.name }}</span>
                        <button type="button" @click="removeFile(file)">×</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    ClipboardList,
    Package,
    Paperclip,
    Receipt,
    ScrollText,
    SendHorizontal,
    Sparkles,
    Target,
    Users,
} from 'lucide-vue-next';

const emit = defineEmits(['submit']);

const message = ref('');
const attachedFiles = ref([]);
const showActions = ref(false);
const textareaRef = ref(null);

const quickTiles = [
    {
        key: 'lead',
        label: 'Создать лид',
        short: 'Лид',
        icon: Target,
        visit: () => route('leads.create'),
    },
    {
        key: 'order',
        label: 'Создать заказ',
        short: 'Заказ',
        icon: Package,
        visit: () => route('orders.create'),
    },
    {
        key: 'contractor',
        label: 'Создать контрагента',
        short: 'Контрагент',
        icon: Users,
        visit: () => route('contractors.create'),
    },
    {
        key: 'task',
        label: 'Создать задачу',
        short: 'Задача',
        icon: ClipboardList,
        visit: () => route('tasks.index', { create: 1 }),
    },
    {
        key: 'invoice',
        label: 'Создать счёт',
        short: 'Счёт',
        icon: Receipt,
        visit: () => route('finance.index', { section: 'documents', new_document: 'invoice' }),
    },
    {
        key: 'upd',
        label: 'Создать УПД',
        short: 'УПД',
        icon: ScrollText,
        visit: () => route('finance.index', { section: 'documents', new_document: 'upd' }),
    },
];

const isDisabled = computed(() => {
    return !message.value.trim() && attachedFiles.value.length === 0;
});

function autosize() {
    const el = textareaRef.value;
    if (!el) {
        return;
    }

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
        (file) => !(file.name === fileToRemove.name && file.size === fileToRemove.size),
    );
}

function submit() {
    if (isDisabled.value) {
        return;
    }

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

function goQuick(tile) {
    showActions.value = false;
    router.visit(tile.visit());
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-x-0 top-0 z-[120] flex flex-col bg-zinc-950/40 dark:bg-zinc-950/60"
            :style="{ bottom: 'calc(5.5rem + env(safe-area-inset-bottom, 0px))' }"
            @click.self="$emit('close')"
        >
            <div
                class="mx-auto mt-auto flex flex-col rounded-t-3xl border border-b-0 border-zinc-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900"
                :style="panelStyle"
                @click.stop
            >
                <div class="relative shrink-0 select-none">
                    <div class="flex justify-center py-2">
                        <div class="h-1 w-12 rounded-full bg-zinc-300 dark:bg-zinc-600" />
                    </div>
                    <button
                        type="button"
                        class="absolute right-2 top-1.5 flex h-7 w-7 cursor-nesw-resize items-center justify-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                        title="Потяните для изменения размера (до ¾ ширины и ⅔ высоты экрана). Двойной щелчок — сброс."
                        aria-label="Изменить размер окна"
                        @mousedown.stop.prevent="startResize"
                        @dblclick.stop.prevent="resetPanelSize"
                    >
                        <Scaling class="h-4 w-4" />
                    </button>
                </div>
                <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <Sparkles class="h-4 w-4 text-sky-600 dark:text-sky-400" />
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">ИИ-ассистент CRM</span>
                    </div>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-xl border border-zinc-200 text-zinc-500 hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                        aria-label="Закрыть"
                        @click="$emit('close')"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div ref="threadRef" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-3">
                    <p
                        v-if="messages.length === 0 && !loading"
                        class="text-sm text-zinc-500 dark:text-zinc-400"
                    >
                        Задайте вопрос по заказам, задачам, диспозиции, инструкциям из Книги продаж или контрагентам.
                    </p>

                    <div
                        v-for="(item, index) in messages"
                        :key="item.turnId ?? `msg-${index}`"
                        class="flex"
                        :class="item.role === 'user' ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[92%] rounded-2xl px-3 py-2 text-sm leading-relaxed"
                            :class="item.role === 'user'
                                ? 'whitespace-pre-wrap bg-sky-600 text-white'
                                : 'border border-zinc-200 bg-zinc-50 text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100'"
                        >
                            <div
                                v-if="item.role === 'assistant'"
                                class="agent-markdown text-sm leading-relaxed"
                                v-html="renderMarkdown(item.content)"
                            />
                            <p v-else class="whitespace-pre-wrap">{{ item.content }}</p>

                            <div
                                v-if="item.role === 'assistant' && item.turnId && !loading"
                                class="mt-2 flex flex-wrap items-center gap-1 border-t border-zinc-200/80 pt-2 dark:border-zinc-600/80"
                            >
                                <span class="mr-1 text-[10px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Ответ полезен?
                                </span>
                                <button
                                    type="button"
                                    class="rounded-lg border px-2 py-1 text-[11px] font-medium transition disabled:opacity-50"
                                    :class="item.feedback === 'helpful'
                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
                                        : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-200'"
                                    :disabled="feedbackBusyTurnId === item.turnId"
                                    @click="$emit('feedback', { turnId: item.turnId, rating: 'helpful' })"
                                >
                                    Да
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border px-2 py-1 text-[11px] font-medium transition disabled:opacity-50"
                                    :class="item.feedback === 'not_helpful'
                                        ? 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200'
                                        : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-200'"
                                    :disabled="feedbackBusyTurnId === item.turnId"
                                    @click="$emit('feedback', { turnId: item.turnId, rating: 'not_helpful' })"
                                >
                                    Нет
                                </button>
                                <span
                                    v-if="item.feedback"
                                    class="text-[10px] text-zinc-500 dark:text-zinc-400"
                                >
                                    Спасибо
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="loading" class="flex justify-start">
                        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                            Думаю…
                        </div>
                    </div>
                </div>

                <div class="border-t border-zinc-200 px-4 py-2 dark:border-zinc-800">
                    <p v-if="error" class="text-xs text-rose-600 dark:text-rose-400">{{ error }}</p>
                    <p v-else-if="metaLabel" class="text-xs text-zinc-500 dark:text-zinc-400">{{ metaLabel }}</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Scaling, Sparkles, X } from 'lucide-vue-next';
import { usePersistedPanelSize } from '@/composables/usePersistedPanelSize.js';
import { renderAgentMarkdown } from '@/support/renderAgentMarkdown.js';

const props = defineProps({
    open: { type: Boolean, default: false },
    messages: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    channel: { type: String, default: '' },
    toolRounds: { type: Number, default: 0 },
    feedbackBusyTurnId: { type: String, default: '' },
});

defineEmits(['close', 'feedback']);

const threadRef = ref(null);

const { panelStyle, startResize, resetPanelSize } = usePersistedPanelSize({
    storageKey: 'crm_agent_panel_size_v1',
    minWidth: 320,
    minHeight: 240,
    maxWidthRatio: 0.75,
    maxHeightRatio: 2 / 3,
    defaultWidth: (maxW) => Math.min(768, maxW),
    defaultHeight: (maxH) => Math.min(720, maxH),
});

function renderMarkdown(content) {
    return renderAgentMarkdown(content);
}

const metaLabel = computed(() => {
    if (props.loading || props.error) {
        return '';
    }

    const parts = [];
    if (props.channel === 'local_only') {
        parts.push('Локальный режим (без внешней модели)');
    } else if (props.channel === 'external_large') {
        parts.push('DeepSeek');
    }

    if (props.toolRounds > 0) {
        parts.push(`инструментов: ${props.toolRounds}`);
    }

    return parts.join(' · ');
});

watch(
    () => [props.messages.length, props.loading, props.open],
    async () => {
        if (!props.open) {
            return;
        }

        await nextTick();
        const el = threadRef.value;
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    },
);
</script>

<style scoped>
.agent-markdown :deep(p) {
    margin: 0.35rem 0;
}

.agent-markdown :deep(p:first-child) {
    margin-top: 0;
}

.agent-markdown :deep(p:last-child) {
    margin-bottom: 0;
}

.agent-markdown :deep(ul),
.agent-markdown :deep(ol) {
    margin: 0.35rem 0;
    padding-left: 1.25rem;
}

.agent-markdown :deep(table) {
    margin: 0.5rem 0;
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
}

.agent-markdown :deep(th),
.agent-markdown :deep(td) {
    border: 1px solid rgb(212 212 216);
    padding: 0.35rem 0.5rem;
    text-align: left;
    vertical-align: top;
}

:global(.dark) .agent-markdown :deep(th),
:global(.dark) .agent-markdown :deep(td) {
    border-color: rgb(63 63 70);
}

.agent-markdown :deep(th) {
    background: rgb(244 244 245);
    font-weight: 600;
}

:global(.dark) .agent-markdown :deep(th) {
    background: rgb(39 39 42);
}

.agent-markdown :deep(strong) {
    font-weight: 600;
}

.agent-markdown :deep(code) {
    font-size: 0.75rem;
}
</style>

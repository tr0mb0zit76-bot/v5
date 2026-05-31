<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-x-0 top-0 z-[120] flex flex-col bg-zinc-950/40 dark:bg-zinc-950/60"
            :style="{ bottom: 'calc(7.5rem + env(safe-area-inset-bottom, 0px))' }"
            @click.self="$emit('close')"
        >
            <div
                class="mx-auto mt-auto flex h-[min(56vh,520px)] w-full max-w-3xl flex-col rounded-t-3xl border border-b-0 border-zinc-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900"
                @click.stop
            >
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
                        Задайте вопрос по заказам, задачам, диспозиции или контрагентам. Ассистент использует те же данные CRM, что и инструменты для Cursor.
                    </p>

                    <div
                        v-for="(item, index) in messages"
                        :key="index"
                        class="flex"
                        :class="item.role === 'user' ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[92%] whitespace-pre-wrap rounded-2xl px-3 py-2 text-sm leading-relaxed"
                            :class="item.role === 'user'
                                ? 'bg-sky-600 text-white'
                                : 'border border-zinc-200 bg-zinc-50 text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100'"
                        >
                            {{ item.content }}
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
import { Sparkles, X } from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
    messages: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    channel: { type: String, default: '' },
    toolRounds: { type: Number, default: 0 },
});

defineEmits(['close']);

const threadRef = ref(null);

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

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end bg-black/60 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))]"
        @click.self="$emit('close')"
    >
        <div class="max-h-[70dvh] w-full overflow-hidden rounded-3xl border border-white/10 bg-zinc-900 shadow-xl">
            <div class="border-b border-white/10 px-4 py-3">
                <div class="text-sm font-semibold text-zinc-50">Отправить в чат</div>
                <div v-if="shareLabel" class="mt-1 truncate text-xs text-zinc-400">{{ shareLabel }}</div>
            </div>

            <div class="max-h-[52dvh] overflow-y-auto py-2">
                <div v-if="conversations.length" class="px-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                    Диалоги
                </div>
                <button
                    v-for="conversation in conversations"
                    :key="`share-conversation-${conversation.id}`"
                    type="button"
                    class="flex w-full items-center gap-3 px-4 py-3 text-left active:bg-white/10"
                    @click="$emit('pick-conversation', conversation)"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-600/35 text-sm font-bold text-sky-100">
                        {{ conversationTitle(conversation).slice(0, 1).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-zinc-50">{{ conversationTitle(conversation) }}</div>
                        <div class="truncate text-xs text-zinc-500">{{ conversationPreview(conversation) }}</div>
                    </div>
                </button>

                <div v-if="colleagues.length" class="mt-2 px-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                    Коллеги
                </div>
                <button
                    v-for="user in colleagues"
                    :key="`share-colleague-${user.id}`"
                    type="button"
                    class="flex w-full items-center gap-3 px-4 py-3 text-left active:bg-white/10"
                    @click="$emit('pick-colleague', user)"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-600/35 text-sm font-bold text-sky-100">
                        {{ String(user.name ?? '?').slice(0, 1).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-zinc-50">{{ user.name }}</div>
                        <div class="truncate text-xs text-zinc-500">{{ user.phone || user.email || 'Личный чат' }}</div>
                    </div>
                </button>

                <div
                    v-if="conversations.length === 0 && colleagues.length === 0"
                    class="px-4 py-8 text-center text-sm text-zinc-500"
                >
                    Нет доступных чатов. Найдите коллегу на вкладке «Чаты».
                </div>
            </div>

            <div class="border-t border-white/10 p-3">
                <button
                    type="button"
                    class="w-full rounded-2xl border border-white/10 px-4 py-3 text-sm font-medium text-zinc-200 active:bg-white/10"
                    @click="$emit('close')"
                >
                    Отмена
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    open: { type: Boolean, default: false },
    shareLabel: { type: String, default: '' },
    conversations: { type: Array, default: () => [] },
    colleagues: { type: Array, default: () => [] },
    conversationTitle: { type: Function, required: true },
    conversationPreview: { type: Function, required: true },
});

defineEmits(['close', 'pick-conversation', 'pick-colleague']);
</script>

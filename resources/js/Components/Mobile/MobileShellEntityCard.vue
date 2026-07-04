<template>
    <div
        :id="elementId || undefined"
        class="rounded-3xl border"
        :class="[cardClass, highlightClass]"
    >
        <div class="flex items-start gap-2 p-4" :class="clickable ? 'active:bg-white/10' : ''">
            <button
                type="button"
                class="min-w-0 flex-1 text-left"
                @click="$emit('open')"
            >
                <slot />
            </button>
            <button
                v-if="shareable"
                type="button"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/10 text-sky-200 active:bg-white/10"
                title="Отправить в чат"
                @click.stop="$emit('share')"
            >
                <Share2 class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>

<script setup>
import { Share2 } from 'lucide-vue-next';

defineProps({
    elementId: { type: String, default: undefined },
    cardClass: { type: String, default: 'border-white/10 bg-white/[0.04]' },
    highlightClass: { type: String, default: '' },
    shareable: { type: Boolean, default: true },
    clickable: { type: Boolean, default: true },
});

defineEmits(['share', 'open']);
</script>

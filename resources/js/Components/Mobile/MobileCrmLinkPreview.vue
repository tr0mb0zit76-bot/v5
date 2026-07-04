<template>
    <a
        :href="url"
        class="block rounded-xl border border-white/15 bg-black/25 px-3 py-2.5 transition active:bg-black/40"
        :class="compact ? 'text-[11px]' : 'text-xs'"
    >
        <div class="flex items-start gap-2">
            <component :is="iconForKind(displayPreview.kind)" class="mt-0.5 h-4 w-4 shrink-0 text-sky-300" />
            <div class="min-w-0 flex-1">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-sky-200/90">
                    {{ displayPreview.label }}
                </div>
                <div class="mt-0.5 font-semibold text-zinc-50">{{ displayPreview.title }}</div>
                <div v-if="displayPreview.subtitle" class="mt-0.5 text-zinc-400">{{ displayPreview.subtitle }}</div>
            </div>
        </div>
    </a>
</template>

<script setup>
import axios from 'axios';
import { CheckSquare, FileText, Package, UserRound, Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { previewForCrmUrl } from '@/support/mobileMessageLinks.js';

const props = defineProps({
    url: { type: String, required: true },
    preview: { type: Object, default: null },
    compact: { type: Boolean, default: true },
});

const enrichedPreview = ref(null);

const displayPreview = computed(() => enrichedPreview.value ?? props.preview ?? {
    kind: 'document',
    label: 'Ссылка',
    title: props.url,
    subtitle: null,
});

onMounted(async () => {
    const fallback = props.preview ?? previewForCrmUrl(props.url);
    if (!fallback) {
        return;
    }

    enrichedPreview.value = fallback;

    try {
        const { data } = await axios.get(route('mobile.shell.link-preview'), {
            headers: { Accept: 'application/json' },
            params: { url: props.url },
        });

        if (data.preview) {
            enrichedPreview.value = data.preview;
        }
    } catch {
        // keep fallback preview
    }
});

function iconForKind(kind) {
    if (kind === 'order') {
        return Package;
    }

    if (kind === 'lead') {
        return Users;
    }

    if (kind === 'contractor') {
        return UserRound;
    }

    if (kind === 'task') {
        return CheckSquare;
    }

    return FileText;
}
</script>

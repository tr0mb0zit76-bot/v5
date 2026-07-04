<template>
    <a
        :href="url"
        class="block rounded-xl border border-white/15 bg-black/25 px-3 py-2.5 transition active:bg-black/40"
        :class="compact ? 'text-[11px]' : 'text-xs'"
    >
        <div class="flex items-start gap-2">
            <component :is="iconForKind(preview.kind)" class="mt-0.5 h-4 w-4 shrink-0 text-sky-300" />
            <div class="min-w-0 flex-1">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-sky-200/90">
                    {{ preview.label }}
                </div>
                <div class="mt-0.5 font-semibold text-zinc-50">{{ preview.title }}</div>
                <div v-if="preview.subtitle" class="mt-0.5 text-zinc-400">{{ preview.subtitle }}</div>
            </div>
        </div>
    </a>
</template>

<script setup>
import { CheckSquare, FileText, Package, UserRound, Users } from 'lucide-vue-next';

defineProps({
    url: { type: String, required: true },
    preview: { type: Object, required: true },
    compact: { type: Boolean, default: true },
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

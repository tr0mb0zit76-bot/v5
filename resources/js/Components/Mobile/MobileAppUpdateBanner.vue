<script setup>
import { X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { checkMobileAppUpdate } from '@/support/mobileAppUpdate.js';

const update = ref(null);
const dismissed = ref(false);

onMounted(async () => {
    try {
        update.value = await checkMobileAppUpdate();
    } catch {
        update.value = null;
    }
});

function dismiss() {
    if (update.value?.required === true) {
        return;
    }

    dismissed.value = true;
}
</script>

<template>
    <div
        v-if="update && !dismissed"
        class="fixed inset-x-3 top-[calc(0.75rem+env(safe-area-inset-top,0px))] z-[70] rounded-3xl border border-sky-400/30 bg-zinc-950/95 p-4 text-zinc-50 shadow-2xl shadow-black/40 backdrop-blur"
    >
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-sky-500/15 text-lg font-bold text-sky-200">
                T
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold">
                    Доступно обновление Traklo {{ update.latest_version_name }}
                </div>
                <p class="mt-1 text-xs leading-5 text-zinc-400">
                    {{ update.changelog || 'Скачайте новую APK-версию и установите поверх текущей.' }}
                </p>
                <div class="mt-3 flex items-center gap-2">
                    <a
                        :href="update.apk_url"
                        class="rounded-2xl bg-sky-600 px-4 py-2 text-xs font-semibold text-white active:bg-sky-500"
                    >
                        Скачать APK
                    </a>
                    <span v-if="update.required" class="text-[11px] font-medium text-amber-200">
                        Обновление обязательно
                    </span>
                    <button
                        v-else
                        type="button"
                        class="px-3 py-2 text-xs text-zinc-500 active:text-zinc-300"
                        @click="dismiss"
                    >
                        Позже
                    </button>
                </div>
            </div>
            <button
                v-if="!update.required"
                type="button"
                class="rounded-full p-1 text-zinc-500 active:bg-white/10 active:text-zinc-300"
                aria-label="Скрыть"
                @click="dismiss"
            >
                <X class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>

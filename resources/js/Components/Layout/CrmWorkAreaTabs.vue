<template>
    <div
        v-if="tabs.length > 0"
        class="crm-work-area-tabs mb-1 flex min-h-0 shrink-0 items-center gap-1 overflow-x-auto border-b border-zinc-200 pb-1 dark:border-zinc-800"
        role="tablist"
        aria-label="Рабочие вкладки"
    >
        <div
            v-for="tab in tabs"
            :key="tab.id"
            class="group flex max-w-[12rem] shrink-0 items-center rounded-lg border text-xs transition-colors"
            :class="tab.id === activeId
                ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                : 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-500'"
            role="presentation"
        >
            <button
                type="button"
                role="tab"
                class="min-w-0 flex-1 truncate px-2.5 py-1.5 text-left font-medium"
                :aria-selected="tab.id === activeId"
                :title="tab.title"
                @click="activateTab(tab.id)"
            >
                {{ tab.title }}
            </button>
            <button
                type="button"
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-r-lg opacity-70 transition hover:opacity-100"
                :class="tab.id === activeId
                    ? 'hover:bg-white/15 dark:hover:bg-zinc-900/10'
                    : 'hover:bg-zinc-100 dark:hover:bg-zinc-800'"
                :title="`Закрыть «${tab.title}»`"
                @click.stop="closeTab(tab.id)"
            >
                <X class="h-3.5 w-3.5" />
            </button>
        </div>
    </div>
</template>

<script setup>
import { X } from 'lucide-vue-next';
import { useCrmWorkArea } from '@/support/crmWorkArea.js';

const { tabs, activeId, activateTab, closeTab } = useCrmWorkArea();
</script>

<template>
    <div class="space-y-6">
        <section class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Модуль</div>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Скрипты продаж</h1>
            <p class="mt-2 max-w-3xl text-sm text-zinc-500 dark:text-zinc-400">
                Сценарии диалогов для звонков и переписок. Выберите сценарий и пройдите шаги; в конце зафиксируйте исход — это основа для статистики и подсказок команде.
            </p>
            <p
                v-if="page.props.flash?.message"
                class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ page.props.flash.message }}
            </p>
            <p v-if="page.props.can_manage_sales_scripts" class="mt-4">
                <Link
                    :href="route('scripts.editor.index')"
                    class="text-sm font-medium text-zinc-800 underline-offset-4 hover:underline dark:text-zinc-200"
                >
                    Редактор сценариев (версии и шаги)
                </Link>
            </p>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="script in scripts"
                :key="script.id"
                class="flex flex-col border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
            >
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ script.title }}</h2>
                <p v-if="script.description" class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ script.description }}</p>
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                    <span v-if="script.channel" class="rounded-full border border-zinc-200 px-2 py-0.5 dark:border-zinc-700">{{ script.channel }}</span>
                    <span
                        v-for="tag in script.tags || []"
                        :key="tag"
                        class="rounded-full border border-zinc-200 px-2 py-0.5 dark:border-zinc-700"
                    >
                        {{ tag }}
                    </span>
                </div>
                <div class="mt-4 flex flex-1 flex-col justify-end">
                    <button
                        v-if="script.active_version"
                        type="button"
                        class="inline-flex w-full items-center justify-center rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        @click="startSession(script.active_version.id)"
                    >
                        Начать сессию
                    </button>
                    <p v-else class="text-sm text-amber-700 dark:text-amber-300">Нет опубликованной версии сценария.</p>
                </div>
            </article>
        </section>

        <p v-if="scripts.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
            Сценарии пока не добавлены. Администратор может загрузить демо: <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">php artisan db:seed --class=SalesScriptsDemoSeeder</code>
        </p>
    </div>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'scripts' }, () => page),
});

const props = defineProps({
    scripts: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

function startSession(versionId) {
    router.post(route('scripts.sessions.store'), {
        sales_script_version_id: versionId,
    });
}
</script>

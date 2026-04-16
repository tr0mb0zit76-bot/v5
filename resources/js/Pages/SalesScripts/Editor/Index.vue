<template>
    <div class="min-h-0 flex-1 space-y-8 overflow-y-auto lg:min-h-0">
        <section class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Редактор</div>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Сценарии продаж</h1>
            <p class="mt-2 max-w-3xl text-sm text-zinc-500 dark:text-zinc-400">
                Создавайте версии, шаги и переходы. Опубликованная версия доступна команде на странице «Скрипты».
            </p>
            <p
                v-if="page.props.flash?.message"
                class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ page.props.flash.message }}
            </p>
            <div class="mt-4">
                <Link
                    :href="route('scripts.index')"
                    class="text-sm font-medium text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-300"
                >
                    ← К прохождению сценариев
                </Link>
            </div>
        </section>

        <section class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Новый сценарий</h2>
            <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="submitNewScript">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Название</label>
                    <input
                        v-model="newScript.title"
                        type="text"
                        required
                        class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                    />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Описание</label>
                    <textarea
                        v-model="newScript.description"
                        rows="2"
                        class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Канал</label>
                    <input
                        v-model="newScript.channel"
                        type="text"
                        class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                    />
                </div>
                <div class="flex items-end">
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                    >
                        Создать
                    </button>
                </div>
            </form>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Все сценарии</h2>
            <div v-for="script in scripts" :key="script.id" class="border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1 space-y-2">
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ script.title }}</h3>
                        <p v-if="script.description" class="text-sm text-zinc-500 dark:text-zinc-400">{{ script.description }}</p>
                        <div class="flex flex-wrap gap-2 text-xs text-zinc-500">
                            <span v-if="script.channel" class="rounded-full border border-zinc-200 px-2 py-0.5 dark:border-zinc-700">{{ script.channel }}</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-800 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-200 dark:hover:bg-rose-950/40"
                        @click="destroyScript(script.id)"
                    >
                        Удалить сценарий
                    </button>
                </div>

                <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Версии</div>
                    <ul class="mt-2 space-y-2">
                        <li
                            v-for="v in script.versions"
                            :key="v.id"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-900/50"
                        >
                            <div class="text-sm text-zinc-800 dark:text-zinc-200">
                                v{{ v.version_number }}
                                <span v-if="v.is_active && v.published_at" class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                                    активна
                                </span>
                                <span v-else class="ml-2 text-xs text-zinc-500">черновик / неактивна</span>
                            </div>
                            <Link
                                :href="route('scripts.editor.versions.show', v.id)"
                                class="text-sm font-medium text-zinc-900 underline-offset-4 hover:underline dark:text-zinc-50"
                            >
                                Открыть редактор
                            </Link>
                        </li>
                    </ul>
                    <form class="mt-3 flex flex-wrap items-end gap-2" @submit.prevent="addVersion(script)">
                        <div>
                            <label class="block text-xs text-zinc-500">Копировать из</label>
                            <select
                                v-model="versionDuplicate[script.id]"
                                class="mt-1 rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <option :value="null">Пустая версия</option>
                                <option v-for="v in script.versions" :key="v.id" :value="v.id">v{{ v.version_number }}</option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                        >
                            Добавить версию
                        </button>
                    </form>
                </div>
            </div>
            <p v-if="scripts.length === 0" class="text-sm text-zinc-500">Сценариев пока нет — создайте первый выше.</p>
        </section>
    </div>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-scripts' }, () => page),
});

const props = defineProps({
    scripts: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const newScript = reactive({
    title: '',
    description: '',
    channel: '',
});

const versionDuplicate = reactive({});

function submitNewScript() {
    router.post(route('scripts.editor.scripts.store'), { ...newScript });
}

function addVersion(script) {
    const dup = versionDuplicate[script.id];
    router.post(route('scripts.editor.scripts.versions.store', script.id), {
        duplicate_from_version_id: dup || null,
    });
}

function destroyScript(scriptId) {
    if (!window.confirm('Удалить сценарий и все версии?')) {
        return;
    }
    router.delete(route('scripts.editor.scripts.destroy', scriptId));
}
</script>

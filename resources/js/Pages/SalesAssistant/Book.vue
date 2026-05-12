<template>
    <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[320px,1fr]">
        <aside class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Книга продаж</h1>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Пространство в стиле Notion: вложенные страницы, импорт markdown и визуальный редактор.</p>

            <form v-if="canWrite" class="mt-4 space-y-2 border-t border-zinc-100 pt-4 dark:border-zinc-800" @submit.prevent="createArticle">
                <input
                    v-model="createForm.title"
                    type="text"
                    required
                    placeholder="Новый заголовок страницы"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                />
                <select
                    v-model="createForm.parent_id"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <option :value="null">Без родителя</option>
                    <option v-for="option in articleOptions" :key="option.id" :value="option.id">
                        {{ option.title }}
                    </option>
                </select>
                <button
                    type="submit"
                    :disabled="createForm.processing"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-zinc-50 dark:text-zinc-900"
                >
                    Создать страницу
                </button>
            </form>

            <form v-if="canWrite" class="mt-3 space-y-2 border-t border-zinc-100 pt-4 dark:border-zinc-800" @submit.prevent="importMarkdown">
                <input
                    type="file"
                    accept=".md,.markdown,.txt,text/markdown,text/plain"
                    @change="onFileChange"
                    class="block w-full text-xs text-zinc-600 file:mr-2 file:rounded-md file:border-0 file:bg-zinc-100 file:px-2 file:py-1 file:text-xs file:font-medium dark:text-zinc-300 dark:file:bg-zinc-800"
                />
                <select
                    v-model="importForm.parent_id"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <option :value="null">Импорт в корень</option>
                    <option v-for="option in articleOptions" :key="`import-${option.id}`" :value="option.id">
                        {{ option.title }}
                    </option>
                </select>
                <button
                    type="submit"
                    :disabled="importForm.processing"
                    class="inline-flex w-full items-center justify-center rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-700 dark:hover:bg-zinc-900"
                >
                    Импорт .md
                </button>
            </form>

            <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <p v-if="flatArticles.length === 0" class="text-sm text-zinc-500">Пока нет страниц.</p>
                <button
                    v-for="entry in flatArticles"
                    :key="entry.id"
                    type="button"
                    :style="{ paddingLeft: `${entry.depth * 14 + 10}px` }"
                    class="mb-1 flex w-full items-center rounded-lg px-2 py-1.5 text-left text-sm transition"
                    :class="selectedArticle?.id === entry.id
                        ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                        : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800'"
                    @click="openArticle(entry.id)"
                >
                    {{ entry.title }}
                </button>
            </div>
        </aside>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <p
                v-if="page.props.flash?.message"
                class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ page.props.flash.message }}
            </p>

            <template v-if="selectedArticle">
                <form class="space-y-3" @submit.prevent="saveArticle">
                    <input
                        v-model="editForm.title"
                        type="text"
                        required
                        placeholder="Заголовок страницы"
                        class="w-full border-0 border-b border-zinc-200 bg-transparent px-0 py-2 text-3xl font-semibold text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:ring-0 dark:border-zinc-700 dark:text-zinc-100"
                    />

                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                        <span>Родитель:</span>
                        <select
                            v-model="editForm.parent_id"
                            class="rounded-md border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <option :value="null">Корень</option>
                            <option v-for="option in parentOptionsForEdit" :key="`edit-${option.id}`" :value="option.id">
                                {{ option.title }}
                            </option>
                        </select>
                        <span v-if="selectedArticle.updated_at">Обновлено: {{ formatDate(selectedArticle.updated_at) }}</span>
                    </div>

                    <TiptapEditor
                        v-model="editForm.html_content"
                        :upload-url="route('sales-assistant.book.assets.upload')"
                        :editable="canWrite"
                        placeholder="Начните писать... Нажмите Enter для нового блока. Можно вставлять ссылки, изображения, файлы и чек-листы."
                    />

                    <div v-if="canWrite" class="flex flex-wrap gap-2">
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            :class="crmBtnCreate"
                        >
                            Сохранить
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-200 dark:hover:bg-rose-950/40"
                            @click="destroyArticle"
                        >
                            Удалить
                        </button>
                    </div>
                </form>
            </template>

            <div v-else class="flex h-[420px] flex-col items-center justify-center rounded-lg border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-700">
                <p class="text-base font-medium text-zinc-700 dark:text-zinc-200">Пустая книга</p>
                <p class="mt-1 text-sm text-zinc-500">Создайте первую страницу и начните писать сразу.</p>
                <button
                    type="button"
                    class="mt-4 rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900"
                    @click="createUntitled"
                >
                    Создать первую страницу
                </button>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import TiptapEditor from '@/Components/SalesBook/TiptapEditor.vue';
import { crmBtnCreate } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-book' }, () => page),
});

const props = defineProps({
    articlesTree: {
        type: Array,
        default: () => [],
    },
    articleOptions: {
        type: Array,
        default: () => [],
    },
    selectedArticle: {
        type: Object,
        default: null,
    },
    capabilities: {
        type: Object,
        default: () => ({ can_read: false, can_comment: false, can_write: false }),
    },
});

const page = usePage();

const createForm = useForm({
    title: '',
    parent_id: null,
});

const importForm = useForm({
    file: null,
    parent_id: null,
});

const editForm = useForm({
    title: '',
    html_content: '<p></p>',
    parent_id: null,
});

const flatArticles = computed(() => flattenTree(props.articlesTree));
const parentOptionsForEdit = computed(() => props.articleOptions.filter((item) => item.id !== props.selectedArticle?.id));

watch(
    () => props.selectedArticle,
    (value) => {
        editForm.defaults({
            title: value?.title ?? '',
            html_content: value?.html_content ?? '<p></p>',
            parent_id: value?.parent_id ?? null,
        });
        editForm.reset();
    },
    { immediate: true },
);

function flattenTree(nodes, depth = 0) {
    return nodes.flatMap((node) => {
        const current = { id: node.id, title: node.title, depth };
        const children = flattenTree(node.children ?? [], depth + 1);

        return [current, ...children];
    });
}

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString();
}

function openArticle(articleId) {
    router.get(route('sales-assistant.book'), { article_id: articleId }, { preserveState: false, replace: true });
}

function createArticle() {
    createForm.post(route('sales-assistant.book.articles.store'));
}

function createUntitled() {
    createForm.title = 'Без названия';
    createForm.parent_id = null;
    createArticle();
}

function onFileChange(event) {
    importForm.file = event.target.files?.[0] ?? null;
}

function importMarkdown() {
    importForm.post(route('sales-assistant.book.import'), {
        forceFormData: true,
    });
}

function saveArticle() {
    if (!props.selectedArticle) {
        return;
    }

    editForm.patch(route('sales-assistant.book.articles.update', props.selectedArticle.id));
}

function destroyArticle() {
    if (!props.selectedArticle) {
        return;
    }

    if (!window.confirm('Удалить эту страницу?')) {
        return;
    }

    router.delete(route('sales-assistant.book.articles.destroy', props.selectedArticle.id));
}

const canWrite = computed(() => Boolean(props.capabilities?.can_write));
</script>

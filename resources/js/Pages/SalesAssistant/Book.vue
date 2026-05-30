<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="grid min-h-0 flex-1 gap-4 overflow-hidden grid-rows-[minmax(0,38vh)_minmax(0,1fr)] lg:grid-cols-[320px,minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)]">
        <aside :class="`${crmPanel} flex min-h-0 flex-col overflow-hidden p-4`">
            <div class="shrink-0">
                <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Книга продаж</h1>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Пространство в стиле Notion: вложенные страницы, импорт markdown и визуальный редактор.</p>
            </div>

            <form v-if="canWrite" class="mt-4 shrink-0 space-y-2 border-t border-zinc-100 pt-4 dark:border-zinc-800" @submit.prevent="createArticle">
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
                    <option value="">Без родителя</option>
                    <option v-for="option in indentedArticleOptions" :key="option.id" :value="String(option.id)">
                        {{ option.label }}
                    </option>
                </select>
                <button
                    type="submit"
                    :disabled="createForm.processing"
                    :class="`${crmBtnPrimary} w-full disabled:cursor-not-allowed disabled:opacity-60`"
                >
                    Создать страницу
                </button>
            </form>

            <form v-if="canWrite" class="mt-3 shrink-0 space-y-2 border-t border-zinc-100 pt-4 dark:border-zinc-800" @submit.prevent="importMarkdown">
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
                    <option value="">Импорт в корень</option>
                    <option v-for="option in indentedArticleOptions" :key="`import-${option.id}`" :value="String(option.id)">
                        {{ option.label }}
                    </option>
                </select>
                <button
                    type="submit"
                    :disabled="importForm.processing"
                    :class="`${crmBtnNeutral} w-full justify-center disabled:cursor-not-allowed disabled:opacity-60`"
                >
                    Импорт .md
                </button>
            </form>

            <div class="mt-4 min-h-0 flex-1 overflow-y-auto border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <p v-if="articlesTree.length === 0" class="text-sm text-zinc-500">Пока нет страниц.</p>
                <SalesBookTreeNav
                    v-else
                    :tree="articlesTree"
                    :article-options="articleOptions"
                    :selected-id="selectedArticle?.id ?? null"
                    :can-write="canWrite"
                    @select="openArticle"
                    @move="moveArticle"
                />
            </div>
        </aside>

        <section :class="`${crmPanel} flex min-h-0 flex-col overflow-hidden p-5`">
            <p
                v-if="page.props.flash?.message"
                class="mb-4 shrink-0 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ page.props.flash.message }}
            </p>

            <template v-if="selectedArticle">
                <form v-if="canWrite" class="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden" @submit.prevent="saveArticle">
                    <div class="flex shrink-0 items-start gap-2">
                        <input
                            v-model="editForm.title"
                            type="text"
                            required
                            placeholder="Заголовок страницы"
                            class="min-w-0 flex-1 border-0 border-b border-zinc-200 bg-transparent px-0 py-2 text-3xl font-semibold text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:ring-0 dark:border-zinc-700 dark:text-zinc-100"
                        />
                        <button
                            type="button"
                            :class="`${crmBtnNeutral} shrink-0 px-3 py-2 text-xs`"
                            :title="copyLinkFeedback ? 'Скопировано' : 'Копировать ссылку на страницу'"
                            @click="copyArticleLink"
                        >
                            {{ copyLinkFeedback ? 'Скопировано' : 'Ссылка' }}
                        </button>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2 text-xs text-zinc-500">
                        <span>Родитель:</span>
                        <select
                            v-model="editForm.parent_id"
                            class="rounded-md border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <option value="">Корень</option>
                            <option v-for="option in parentOptionsForEdit" :key="`edit-${option.id}`" :value="String(option.id)">
                                {{ option.label }}
                            </option>
                        </select>
                        <span v-if="selectedArticle.updated_at">Обновлено: {{ formatDate(selectedArticle.updated_at) }}</span>
                    </div>

                    <TiptapEditor
                        ref="editEditorRef"
                        :key="selectedArticle.id"
                        class="min-h-0 flex-1"
                        :model-value="editForm.markdown_content"
                        :upload-url="route('sales-assistant.book.assets.upload')"
                        @update:model-value="onEditorUpdate"
                        :editable="true"
                        placeholder="Начните писать... Можно вставлять файлы и скриншоты через Ctrl+V из Проводника, ссылки, файлы и чек-листы."
                    />

                    <div class="flex shrink-0 flex-wrap gap-2">
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

                <div v-else class="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden">
                    <div class="flex shrink-0 items-start gap-2">
                        <h2 class="min-w-0 flex-1 border-0 border-b border-zinc-200 px-0 py-2 text-3xl font-semibold text-zinc-900 dark:border-zinc-700 dark:text-zinc-100">
                            {{ selectedArticle.title }}
                        </h2>
                        <button
                            type="button"
                            :class="`${crmBtnNeutral} shrink-0 px-3 py-2 text-xs`"
                            :title="copyLinkFeedback ? 'Скопировано' : 'Копировать ссылку на страницу'"
                            @click="copyArticleLink"
                        >
                            {{ copyLinkFeedback ? 'Скопировано' : 'Ссылка' }}
                        </button>
                    </div>

                    <div v-if="selectedArticle.updated_at" class="shrink-0 text-xs text-zinc-500">
                        Обновлено: {{ formatDate(selectedArticle.updated_at) }}
                    </div>

                    <TiptapEditor
                        :key="readonlyEditorKey"
                        class="min-h-0 flex-1"
                        :model-value="selectedArticle.markdown_content"
                        :upload-url="route('sales-assistant.book.assets.upload')"
                        :editable="false"
                        placeholder=""
                    />
                </div>
            </template>

            <div v-else class="flex h-[420px] flex-col items-center justify-center rounded-lg border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-700">
                <p class="text-base font-medium text-zinc-700 dark:text-zinc-200">Пустая книга</p>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ canWrite ? 'Создайте первую страницу и начните писать сразу.' : 'Страницы пока не добавлены.' }}
                </p>
                <button
                    v-if="canWrite"
                    type="button"
                    :class="crmBtnPrimary"
                    class="mt-4"
                    @click="createUntitled"
                >
                    Создать первую страницу
                </button>
            </div>
        </section>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import TiptapEditor from '@/Components/SalesBook/TiptapEditor.vue';
import SalesBookTreeNav from '@/Components/SalesBook/SalesBookTreeNav.vue';
import { crmBtnCreate, crmBtnNeutral, crmBtnPrimary, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-book', mainFill: true }, () => page),
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
    parent_id: '',
});

const importForm = useForm({
    file: null,
    parent_id: '',
});

const editForm = useForm({
    title: '',
    markdown_content: '',
    parent_id: '',
});

const contentDirty = ref(false);
const copyLinkFeedback = ref(false);
const editEditorRef = ref(null);

const readonlyEditorKey = computed(() => {
    if (!props.selectedArticle) {
        return 'readonly-empty';
    }

    return `readonly-${props.selectedArticle.id}-${props.selectedArticle.updated_at ?? 'none'}`;
});

const flatArticles = computed(() => flattenTree(props.articlesTree));
const indentedArticleOptions = computed(() => flatArticles.value.map((entry) => ({
    id: entry.id,
    label: `${'\u00A0'.repeat(entry.depth * 2)}${entry.depth > 0 ? '↳ ' : ''}${entry.title}`,
})));

const parentOptionsForEdit = computed(() => {
    if (!props.selectedArticle) {
        return indentedArticleOptions.value;
    }

    const blockedIds = new Set([
        props.selectedArticle.id,
        ...collectDescendantIds(props.selectedArticle.id, props.articleOptions),
    ]);

    return indentedArticleOptions.value.filter((option) => !blockedIds.has(option.id));
});

watch(
    () => props.selectedArticle,
    (value, oldValue) => {
        if (!value) {
            return;
        }

        const articleChanged = value.id !== oldValue?.id;
        const serverMarkdownChanged = value.markdown_content !== oldValue?.markdown_content;

        editForm.defaults({
            title: value.title ?? '',
            markdown_content: value.markdown_content ?? '',
            parent_id: value.parent_id ? String(value.parent_id) : '',
        });

        if (articleChanged || serverMarkdownChanged) {
            contentDirty.value = false;
            editForm.reset();
        } else {
            editForm.title = value.title ?? '';
            editForm.parent_id = value.parent_id ? String(value.parent_id) : '';
        }
    },
    { immediate: true },
);

function onEditorUpdate(markdown) {
    editForm.markdown_content = markdown;
    contentDirty.value = true;
}

function flattenTree(nodes, depth = 0) {
    return nodes.flatMap((node) => {
        const current = {
            id: node.id,
            title: node.title,
            depth,
            parent_id: node.parent_id ?? null,
            sort_order: node.sort_order ?? 0,
        };
        const children = flattenTree(node.children ?? [], depth + 1);

        return [current, ...children];
    });
}

function collectDescendantIds(articleId, options) {
    const childrenByParent = new Map();

    options.forEach((option) => {
        if (option.parent_id === null || option.parent_id === undefined) {
            return;
        }

        const parentId = Number(option.parent_id);
        const current = childrenByParent.get(parentId) ?? [];
        current.push(Number(option.id));
        childrenByParent.set(parentId, current);
    });

    const descendants = [];
    const queue = [...(childrenByParent.get(Number(articleId)) ?? [])];

    while (queue.length > 0) {
        const childId = queue.shift();
        descendants.push(childId);
        queue.push(...(childrenByParent.get(childId) ?? []));
    }

    return descendants;
}

function normalizeParentId(value) {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    return Number(value);
}

function withNormalizedParent(form) {
    return form.transform((data) => ({
        ...data,
        parent_id: normalizeParentId(data.parent_id),
    }));
}

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString();
}

function openArticle(articleId) {
    router.get(route('sales-assistant.book'), { article_id: articleId }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['selectedArticle', 'articlesTree', 'articleOptions'],
    });
}

async function copyArticleLink() {
    if (!props.selectedArticle) {
        return;
    }

    const url = route('sales-assistant.book', { article_id: props.selectedArticle.id });

    try {
        await navigator.clipboard.writeText(url);
        copyLinkFeedback.value = true;
        window.setTimeout(() => {
            copyLinkFeedback.value = false;
        }, 2000);
    } catch {
        window.prompt('Скопируйте ссылку', url);
    }
}

function createArticle() {
    withNormalizedParent(createForm).post(route('sales-assistant.book.articles.store'));
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
    withNormalizedParent(importForm).post(route('sales-assistant.book.import'), {
        forceFormData: true,
    });
}

function saveArticle() {
    if (!props.selectedArticle) {
        return;
    }

    const markdownContent = editEditorRef.value?.getMarkdown?.() ?? editForm.markdown_content;

    router.patch(route('sales-assistant.book.articles.update', props.selectedArticle.id), {
        title: editForm.title,
        parent_id: normalizeParentId(editForm.parent_id),
        markdown_content: markdownContent,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            contentDirty.value = false;
        },
    });
}

function moveArticle(payload) {
    router.patch(route('sales-assistant.book.articles.move', payload.id), {
        parent_id: payload.parent_id,
        sort_order: payload.sort_order,
    }, {
        preserveScroll: true,
        only: ['articlesTree', 'articleOptions', 'selectedArticle'],
    });
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

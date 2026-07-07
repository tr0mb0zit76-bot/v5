<template>
    <div
        class="flex min-h-0 flex-col rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
        :class="editable ? 'flex-1 overflow-hidden' : ''"
        v-bind="$attrs"
    >
        <div
            v-if="editable"
            class="z-10 flex shrink-0 flex-wrap gap-1 border-b border-zinc-200 bg-white p-2 dark:border-zinc-800 dark:bg-zinc-950"
            role="toolbar"
            aria-label="Форматирование текста"
        >
            <button v-for="item in toolbarItems" :key="item.key" type="button" :title="item.title ?? item.label" :class="buttonClass(item.active?.() ?? false)" @click="item.action">
                {{ item.label }}
            </button>
            <button type="button" :class="buttonClass(false)" @click="setLink">Ссылка</button>
            <button type="button" title="Загрузить файл или картинку" :class="buttonClass(false)" @click="triggerFileUpload">📎</button>
            <select
                class="h-7 rounded-md border border-zinc-200 bg-white px-2 pr-7 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                value=""
                title="Вставить готовый блок"
                @change="insertQuickBlock($event.target.value); $event.target.value = ''"
            >
                <option value="">Блок</option>
                <option value="callout">Заметка</option>
                <option value="checklist">Чек-лист</option>
                <option value="divider">Разделитель</option>
                <option value="reply-script">Скрипт ответа</option>
                <option value="objection">Возражение</option>
                <option value="mini-offer">Мини-КП</option>
                <option value="next-step">Следующий шаг</option>
                <option value="control-questions">Контрольные вопросы</option>
            </select>

            <span class="mx-1 self-stretch w-px bg-zinc-200 dark:bg-zinc-700" aria-hidden="true" />

            <label
                class="self-center text-xs font-medium text-zinc-500 dark:text-zinc-400"
                for="sales-book-text-color"
            >
                Цвет
            </label>
            <select
                id="sales-book-text-color"
                class="h-7 rounded-md border border-zinc-200 bg-white px-2 pr-7 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                :value="activeTextColorValue"
                @change="onTextColorChange($event.target.value)"
            >
                <option value="">Авто</option>
                <option v-for="option in textColorOptions" :key="`text-${option.value}`" :value="option.value">
                    {{ option.label }}
                </option>
            </select>

            <span class="mx-1 self-stretch w-px bg-zinc-200 dark:bg-zinc-700" aria-hidden="true" />

            <label
                class="self-center text-xs font-medium text-zinc-500 dark:text-zinc-400"
                for="sales-book-highlight"
            >
                Маркер
            </label>
            <select
                id="sales-book-highlight"
                class="h-7 rounded-md border border-zinc-200 bg-white px-2 pr-7 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                :value="activeHighlightValue"
                @change="onHighlightChange($event.target.value)"
            >
                <option value="">Нет</option>
                <option v-for="option in highlightOptions" :key="`mark-${option.value}`" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
        </div>

        <div
            v-if="editable && tableToolbarItems.length > 0"
            class="z-10 flex shrink-0 flex-wrap gap-1 border-b border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-800 dark:bg-zinc-900/60"
            role="toolbar"
            aria-label="Таблица"
        >
            <span class="self-center px-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">Таблица:</span>
            <button v-for="item in tableToolbarItems" :key="item.key" type="button" :title="item.title ?? item.label" :class="buttonClass(false)" @click="item.action">
                {{ item.label }}
            </button>
        </div>

        <div
            class="min-h-0"
            :class="editable ? 'flex-1 overflow-y-auto overscroll-contain' : 'cursor-default'"
        >
            <EditorContent :editor="editor" class="tiptap-body px-4 py-3" />
            <div
                v-if="childPageLinks.length > 0"
                class="tiptap-body px-4 pb-3"
                data-sales-book-child-links
                @click="handleEditorClick"
            >
                <div class="sales-book-editor">
                    <ul>
                        <li v-for="child in childPageLinks" :key="child.id">
                            <p>
                                <a :href="child.url">{{ child.title }}</a>
                            </p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <input ref="fileInput" type="file" class="hidden" @change="uploadAndInsert($event, false)" />
    </div>
</template>

<script setup>
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { crmSegmentedBtn, crmSegmentedBtnActive } from '@/support/crmUi.js';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Image from '@tiptap/extension-image';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';
import Underline from '@tiptap/extension-underline';
import { TextStyle } from '@tiptap/extension-text-style';
import { Color } from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import { TableKit } from '@tiptap/extension-table';
import { Markdown } from '@tiptap/markdown';
import { SalesBookOrderedList } from '@/Components/SalesBook/SalesBookOrderedList.js';
import { SalesBookMermaidCodeBlock } from '@/Components/SalesBook/SalesBookMermaidCodeBlock.js';
import { SalesBookLink } from '@/Components/SalesBook/salesBookLinkExtension.js';
import { prepareSalesBookMarkdown } from '@/Components/SalesBook/salesBookMarkdownTables.js';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Начните писать...',
    },
    uploadUrl: {
        type: String,
        required: true,
    },
    editable: {
        type: Boolean,
        default: true,
    },
    childPageLinks: {
        type: Array,
        default: () => [],
    },
});

defineOptions({
    inheritAttrs: false,
});

const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);
const isApplyingExternalContent = ref(false);
const isEditorBootstrapping = ref(true);
const toolbarRevision = ref(0);

const textColorOptions = [
    { value: '#18181b', label: 'Черный' },
    { value: '#dc2626', label: 'Красный' },
    { value: '#ea580c', label: 'Оранжевый' },
    { value: '#ca8a04', label: 'Желтый' },
    { value: '#16a34a', label: 'Зеленый' },
    { value: '#2563eb', label: 'Синий' },
    { value: '#9333ea', label: 'Фиолетовый' },
];
const highlightOptions = [
    { value: '#fef08a', label: 'Желтый' },
    { value: '#bbf7d0', label: 'Зеленый' },
    { value: '#bfdbfe', label: 'Синий' },
    { value: '#fbcfe8', label: 'Розовый' },
    { value: '#fed7aa', label: 'Оранжевый' },
    { value: '#e9d5ff', label: 'Фиолетовый' },
];

function setEditorContent(value) {
    if (!editor.value?.markdown) {
        return;
    }

    isApplyingExternalContent.value = true;

    try {
        const markdown = prepareSalesBookMarkdown(value || '');
        const document = editor.value.markdown.parse(markdown);
        editor.value.commands.setContent(document, { emitUpdate: false });
    } catch (error) {
        console.error('SalesBook editor failed to load markdown content', error);
    }

    nextTick(() => {
        isApplyingExternalContent.value = false;
    });
}

function syncEditorContent(value, { force = false } = {}) {
    if (!editor.value?.markdown) {
        return;
    }

    const incoming = prepareSalesBookMarkdown(value || '');

    if (!force) {
        const current = prepareSalesBookMarkdown(editor.value.getMarkdown());

        if (incoming === current) {
            return;
        }
    }

    setEditorContent(incoming);
}

function finishEditorBootstrap() {
    nextTick(() => {
        syncEditorContent(props.modelValue, { force: true });
        nextTick(() => {
            isEditorBootstrapping.value = false;
        });
    });
}

const editor = useEditor({
    content: '',
    contentType: 'markdown',
    editable: props.editable,
    onCreate: () => {
        finishEditorBootstrap();
    },
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2, 3] },
            orderedList: false,
            codeBlock: false,
            link: false,
            underline: false,
        }),
        SalesBookMermaidCodeBlock,
        SalesBookOrderedList,
        SalesBookLink,
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
        Image,
        TaskList,
        TaskItem.configure({ nested: true }),
        Underline,
        TextStyle,
        Color,
        Highlight.configure({ multicolor: true }),
        TableKit.configure({
            table: {
                resizable: true,
                HTMLAttributes: {
                    class: 'sales-book-table',
                },
            },
        }),
        Markdown.configure({
            markedOptions: {
                gfm: true,
            },
        }),
    ],
    editorProps: {
        attributes: {
            class: 'sales-book-editor focus:outline-none',
        },
        handlePaste: (_view, event) => handleClipboardPaste(event),
        handleClick: (_view, _pos, event) => handleEditorClick(event),
    },
    onUpdate: ({ editor: instance }) => {
        if (isApplyingExternalContent.value || isEditorBootstrapping.value) {
            return;
        }

        emit('update:modelValue', instance.getMarkdown());
    },
});

watch(
    () => props.modelValue,
    (value) => {
        syncEditorContent(value);
    },
);

watch(
    () => props.editable,
    (value) => {
        if (!editor.value) {
            return;
        }

        editor.value.setEditable(value);
    },
);

watch(
    editor,
    (instance, previousInstance) => {
        previousInstance?.off('selectionUpdate', bumpToolbarRevision);
        previousInstance?.off('transaction', bumpToolbarRevision);

        if (!instance) {
            return;
        }

        isEditorBootstrapping.value = true;
        instance.setEditable(props.editable);
        instance.on('selectionUpdate', bumpToolbarRevision);
        instance.on('transaction', bumpToolbarRevision);
        finishEditorBootstrap();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

defineExpose({
    getMarkdown: () => editor.value?.getMarkdown() ?? '',
    insertMarkdown,
});

const activeTextColorValue = computed(() => {
    toolbarRevision.value;

    const color = editor.value?.getAttributes('textStyle').color;

    return textColorOptions.some((option) => option.value === color) ? color : '';
});

const activeHighlightValue = computed(() => {
    toolbarRevision.value;

    const active = highlightOptions.find((option) => editor.value?.isActive('highlight', { color: option.value }));

    return active?.value ?? '';
});

const toolbarItems = computed(() => {
    toolbarRevision.value;

    if (!editor.value || !props.editable) {
        return [];
    }

    return [
        { key: 'p', label: 'P', active: () => editor.value.isActive('paragraph'), action: () => editor.value.chain().focus().setParagraph().run() },
        { key: 'h1', label: 'H1', active: () => editor.value.isActive('heading', { level: 1 }), action: () => editor.value.chain().focus().toggleHeading({ level: 1 }).run() },
        { key: 'h2', label: 'H2', active: () => editor.value.isActive('heading', { level: 2 }), action: () => editor.value.chain().focus().toggleHeading({ level: 2 }).run() },
        { key: 'bold', label: 'B', active: () => editor.value.isActive('bold'), action: () => editor.value.chain().focus().toggleBold().run() },
        { key: 'italic', label: 'I', active: () => editor.value.isActive('italic'), action: () => editor.value.chain().focus().toggleItalic().run() },
        { key: 'underline', label: 'U', active: () => editor.value.isActive('underline'), action: () => editor.value.chain().focus().toggleUnderline().run() },
        { key: 'bullet', label: '•', title: 'Маркированный список', active: () => editor.value.isActive('bulletList'), action: () => toggleListForSelection('bulletList') },
        { key: 'ordered', label: '1.', title: 'Нумерованный список', active: () => isOrderedListActive('1'), action: () => applyOrderedList('1') },
        { key: 'ordered-alpha', label: 'a.', title: 'Буквенный список', active: () => isOrderedListActive('a'), action: () => applyOrderedList('a') },
        { key: 'task', label: 'Todo', active: () => editor.value.isActive('taskList'), action: () => editor.value.chain().focus().toggleTaskList().run() },
        { key: 'quote', label: 'Quote', active: () => editor.value.isActive('blockquote'), action: () => editor.value.chain().focus().toggleBlockquote().run() },
        { key: 'code', label: '</>', active: () => editor.value.isActive('codeBlock'), action: () => editor.value.chain().focus().toggleCodeBlock().run() },
        {
            key: 'mermaid',
            label: 'Mermaid',
            title: 'Диаграмма Mermaid',
            active: () => editor.value.isActive('codeBlock', { language: 'mermaid' }),
            action: () => editor.value.chain().focus().toggleCodeBlock({ language: 'mermaid' }).run(),
        },
        { key: 'table', label: 'Tbl', title: 'Вставить таблицу', active: () => editor.value.isActive('table'), action: () => insertTableWithPrompt() },
    ];
});

const tableToolbarItems = computed(() => {
    if (!editor.value || !props.editable || !editor.value.isActive('table')) {
        return [];
    }

    return [
        { key: 'add-row-before', label: '+ряд ↑', title: 'Добавить строку выше', action: () => editor.value.chain().focus().addRowBefore().run() },
        { key: 'add-row-after', label: '+ряд ↓', title: 'Добавить строку ниже', action: () => editor.value.chain().focus().addRowAfter().run() },
        { key: 'delete-row', label: '−ряд', title: 'Удалить строку', action: () => editor.value.chain().focus().deleteRow().run() },
        { key: 'add-col-before', label: '+кол ←', title: 'Добавить столбец слева', action: () => editor.value.chain().focus().addColumnBefore().run() },
        { key: 'add-col-after', label: '+кол →', title: 'Добавить столбец справа', action: () => editor.value.chain().focus().addColumnAfter().run() },
        { key: 'delete-col', label: '−кол', title: 'Удалить столбец', action: () => editor.value.chain().focus().deleteColumn().run() },
        { key: 'toggle-header', label: 'Hdr', title: 'Переключить строку-шапку', action: () => editor.value.chain().focus().toggleHeaderRow().run() },
        { key: 'delete-table', label: 'Удалить', title: 'Удалить таблицу', action: () => editor.value.chain().focus().deleteTable().run() },
    ];
});

function insertTableWithPrompt() {
    if (!editor.value || !props.editable) {
        return;
    }

    const rowsRaw = window.prompt('Сколько строк (включая шапку)?', '3');
    if (rowsRaw === null) {
        return;
    }

    const colsRaw = window.prompt('Сколько столбцов?', '3');
    if (colsRaw === null) {
        return;
    }

    const rows = Math.min(30, Math.max(2, Number.parseInt(rowsRaw, 10) || 3));
    const cols = Math.min(12, Math.max(1, Number.parseInt(colsRaw, 10) || 3));

    editor.value.chain().focus().insertTable({ rows, cols, withHeaderRow: true }).run();
}

function insertMarkdown(markdown) {
    if (!editor.value || !props.editable || typeof markdown !== 'string' || markdown.trim() === '') {
        return;
    }

    editor.value
        .chain()
        .focus()
        .insertContent(markdown, { contentType: 'markdown' })
        .run();
}

function insertQuickBlock(type) {
    const blocks = {
        callout: '\n\n> **Важно:** краткая подсказка для менеджера.\n',
        checklist: '\n\n- [ ] Первый шаг\n- [ ] Второй шаг\n- [ ] Проверить результат\n',
        divider: '\n\n---\n',
        'reply-script': '\n\n### Скрипт ответа\n\n**Ситуация:** \n\n**Что сказать клиенту:**\n> \n\n**Цель ответа:** \n',
        objection: '\n\n### Возражение\n\n**Клиент говорит:** “...”\n\n**Не спорим:** \n\n**Уточняем:** \n\n**Отвечаем:** \n\n**Следующий вопрос:** \n',
        'mini-offer': '\n\n### Мини-КП\n\n**Задача клиента:** \n\n**Наше решение:** \n\n**Что входит:**\n- \n\n**Почему это безопасно:** \n\n**Следующий шаг:** \n',
        'next-step': '\n\n### Следующий шаг\n\n- **Кто делает:** \n- **Что делает:** \n- **До когда:** \n- **Какой результат фиксируем:** \n',
        'control-questions': '\n\n### Контрольные вопросы\n\n1. \n2. \n3. \n',
    };

    insertMarkdown(blocks[type] ?? '');
}

function toggleListForSelection(listType) {
    if (!editor.value || !props.editable) {
        return;
    }

    splitHardBreaksInSelection();

    if (listType === 'bulletList') {
        editor.value.chain().focus().toggleBulletList().run();

        return;
    }
}

function isOrderedListActive(type) {
    if (!editor.value?.isActive('orderedList')) {
        return false;
    }

    const currentType = editor.value.getAttributes('orderedList').type ?? '1';

    return currentType === type;
}

function applyOrderedList(type) {
    if (!editor.value || !props.editable) {
        return;
    }

    splitHardBreaksInSelection();

    const attrType = type === '1' ? null : type;
    const normalizedCurrent = editor.value.getAttributes('orderedList').type ?? '1';

    if (editor.value.isActive('orderedList')) {
        if (normalizedCurrent === type) {
            editor.value.chain().focus().toggleOrderedList().run();

            return;
        }

        editor.value.chain().focus().updateAttributes('orderedList', { type: attrType }).run();

        return;
    }

    editor.value.chain().focus().toggleOrderedList().updateAttributes('orderedList', { type: attrType }).run();
}

/**
 * Shift+Enter даёт <br> внутри одного абзаца — список на таком выделении выглядит как отступ без маркеров.
 * Разбиваем на отдельные абзацы, чтобы каждая строка стала пунктом списка.
 */
function splitHardBreaksInSelection() {
    const instance = editor.value;
    if (!instance) {
        return;
    }

    const { state } = instance;
    const { from, to, empty } = state.selection;

    if (empty || from === to) {
        return;
    }

    const $from = state.doc.resolve(from);
    const $to = state.doc.resolve(to);

    if ($from.parent !== $to.parent || $from.parent.type.name !== 'paragraph') {
        return;
    }

    const hardBreakPositions = [];

    $from.parent.forEach((node, offset) => {
        if (node.type.name !== 'hardBreak') {
            return;
        }

        const position = $from.start() + offset + node.nodeSize;

        if (position > from && position < to) {
            hardBreakPositions.push(position);
        }
    });

    if (hardBreakPositions.length === 0) {
        return;
    }

    const chain = instance.chain().focus();

    [...hardBreakPositions].reverse().forEach((position) => {
        chain.setTextSelection({ from: position, to: position }).splitBlock();
    });

    chain.setTextSelection({ from, to }).run();
}

function bumpToolbarRevision() {
    toolbarRevision.value += 1;
}

function buttonClass(active) {
    return active
        ? `${crmSegmentedBtnActive} px-2 py-1 text-xs`
        : `${crmSegmentedBtn} px-2 py-1 text-xs`;
}

function applyTextColor(color) {
    if (!editor.value || !props.editable || !color) {
        return;
    }

    editor.value.chain().focus().setColor(color).run();
}

function clearTextColor() {
    if (!editor.value || !props.editable) {
        return;
    }

    editor.value.chain().focus().unsetColor().run();
}

function onTextColorChange(color) {
    if (color === '') {
        clearTextColor();

        return;
    }

    applyTextColor(color);
}

function applyHighlight(color) {
    if (!editor.value || !props.editable || !color) {
        return;
    }

    if (editor.value.isActive('highlight', { color })) {
        editor.value.chain().focus().unsetHighlight().run();

        return;
    }

    editor.value.chain().focus().setHighlight({ color }).run();
}

function clearHighlight() {
    if (!editor.value || !props.editable) {
        return;
    }

    editor.value.chain().focus().unsetHighlight().run();
}

function onHighlightChange(color) {
    if (color === '') {
        clearHighlight();

        return;
    }

    applyHighlight(color);
}

function extractBookArticleId(href) {
    if (!href) {
        return null;
    }

    const match = href.match(/article_id=(\d+)/);

    return match ? Number(match[1]) : null;
}

function handleEditorClick(event) {
    const target = event.target;

    if (!(target instanceof Element)) {
        return false;
    }

    const anchor = target.closest('a[href]');

    if (!anchor) {
        return false;
    }

    const href = anchor.getAttribute('href')?.trim() ?? '';

    if (href === '') {
        return false;
    }

    const articleId = extractBookArticleId(href);

    if (articleId !== null) {
        event.preventDefault();
        router.get(route('sales-assistant.book'), { article_id: articleId }, { preserveState: false });

        return true;
    }

    event.preventDefault();

    if (href.startsWith('mailto:') || href.startsWith('tel:')) {
        window.location.href = href;

        return true;
    }

    let url = href;

    try {
        url = new URL(href, window.location.origin).href;
    } catch {
        /* оставляем как есть */
    }

    window.open(url, '_blank', 'noopener,noreferrer');

    return true;
}

function setLink() {
    if (!editor.value || !props.editable) {
        return;
    }

    const previousUrl = editor.value.getAttributes('link').href;
    const url = window.prompt('URL ссылки', previousUrl || 'https://');

    if (url === null) {
        return;
    }

    if (url.trim() === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

function triggerFileUpload() {
    if (!props.editable) {
        return;
    }

    fileInput.value?.click();
}

async function uploadFile(file, { asImage = false } = {}) {
    if (!file || !editor.value || !props.editable) {
        return;
    }

    const uploadableFile = file.name
        ? file
        : new File([file], `screenshot-${Date.now()}.png`, { type: file.type || 'image/png' });

    const formData = new FormData();
    formData.append('file', uploadableFile);

    try {
        const response = await axios.post(props.uploadUrl, formData);
        const { url, name, is_image: isImage } = response.data;

        if (asImage || isImage) {
            editor.value.chain().focus().setImage({ src: url, alt: name || 'image' }).run();

            return;
        }

        editor.value
            .chain()
            .focus()
            .insertContent(`[${name || url}](${url})`, { contentType: 'markdown' })
            .run();
    } catch (error) {
        console.error('Upload failed', error);
        window.alert('Не удалось загрузить файл.');
    }
}

function collectFilesFromClipboard(event) {
    const clipboardData = event.clipboardData;

    if (!clipboardData) {
        return [];
    }

    const files = [];
    const seen = new Set();

    const addFile = (file) => {
        if (!file || seen.has(file)) {
            return;
        }

        seen.add(file);
        files.push(file);
    };

    Array.from(clipboardData.files ?? []).forEach(addFile);

    if (files.length > 0) {
        return files;
    }

    Array.from(clipboardData.items ?? []).forEach((item) => {
        if (item.kind !== 'file') {
            return;
        }

        addFile(item.getAsFile());
    });

    return files;
}

function handleClipboardPaste(event) {
    if (!props.editable || !editor.value) {
        return false;
    }

    const files = collectFilesFromClipboard(event);

    if (files.length === 0) {
        return false;
    }

    event.preventDefault();

    files.forEach((file) => {
        uploadFile(file, { asImage: file.type.startsWith('image/') });
    });

    return true;
}

async function uploadAndInsert(event, shouldInsertAsImage) {
    const file = event.target.files?.[0] ?? null;
    event.target.value = '';

    await uploadFile(file, { asImage: shouldInsertAsImage });
}
</script>

<style scoped>
:deep(.tiptap-body .ProseMirror p.is-editor-empty:first-child::before) {
    color: rgb(113 113 122);
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}

:deep(.tiptap-body .ProseMirror) {
    min-height: 8rem;
}

:deep(.tiptap-body .ProseMirror[contenteditable='false']) {
    cursor: default;
    user-select: text;
}

:deep(.tiptap-body .ProseMirror:focus) {
    outline: none;
}

:deep(.tiptap-body .sales-book-editor h1) {
    font-size: 1.875rem;
    font-weight: 700;
    line-height: 1.25;
    margin: 1.25rem 0 0.75rem;
}

:deep(.tiptap-body .sales-book-editor h2) {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.3;
    margin: 1rem 0 0.5rem;
}

:deep(.tiptap-body .sales-book-editor h3) {
    font-size: 1.25rem;
    font-weight: 600;
    line-height: 1.35;
    margin: 0.875rem 0 0.5rem;
}

:deep(.tiptap-body .sales-book-editor p) {
    margin: 0.5rem 0;
}

:deep(.tiptap-body .sales-book-editor ul:not([data-type='taskList'])),
:deep(.tiptap-body .sales-book-editor ol) {
    margin: 0.5rem 0;
    padding-left: 1.5rem;
}

:deep(.tiptap-body .sales-book-editor ul:not([data-type='taskList'])) {
    list-style-type: disc;
}

:deep(.tiptap-body .sales-book-editor ol) {
    list-style-type: decimal;
}

:deep(.tiptap-body .sales-book-editor ol[type='a']) {
    list-style-type: lower-alpha;
}

:deep(.tiptap-body .sales-book-editor ol[type='A']) {
    list-style-type: upper-alpha;
}

:deep(.tiptap-body .sales-book-editor li) {
    display: list-item;
    margin: 0.25rem 0;
}

:deep(.tiptap-body .sales-book-editor li > p) {
    margin: 0;
}

:deep(.tiptap-body .sales-book-editor ol ol),
:deep(.tiptap-body .sales-book-editor ul ul),
:deep(.tiptap-body .sales-book-editor ol ul),
:deep(.tiptap-body .sales-book-editor ul ol) {
    margin-top: 0.25rem;
    margin-bottom: 0.25rem;
}

:deep(.tiptap-body .sales-book-editor blockquote) {
    border-left: 3px solid rgb(212 212 216);
    color: rgb(82 82 91);
    margin: 0.75rem 0;
    padding-left: 1rem;
}

:deep(.dark .tiptap-body .sales-book-editor blockquote) {
    border-left-color: rgb(82 82 91);
    color: rgb(161 161 170);
}

:deep(.tiptap-body .sales-book-editor pre) {
    background: rgb(244 244 245);
    border: 1px solid rgb(228 228 231);
    border-radius: 0.5rem;
    color: rgb(24 24 27);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.875rem;
    line-height: 1.5;
    margin: 0.75rem 0;
    overflow-x: auto;
    padding: 0.75rem 1rem;
}

:deep(.tiptap-body .sales-book-editor pre code) {
    background: transparent;
    color: inherit;
    font: inherit;
    padding: 0;
    white-space: pre-wrap;
}

:deep(.tiptap-body .sales-book-editor :not(pre) > code) {
    background: rgb(244 244 245);
    border-radius: 0.25rem;
    color: rgb(24 24 27);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.875em;
    padding: 0.125rem 0.375rem;
}

:deep(.dark .tiptap-body .sales-book-editor pre) {
    background: rgb(39 39 42);
    border-color: rgb(63 63 70);
    color: rgb(244 244 245);
}

:deep(.dark .tiptap-body .sales-book-editor :not(pre) > code) {
    background: rgb(39 39 42);
    color: rgb(244 244 245);
}

:deep(.tiptap-body .sales-book-editor .sales-book-code-block.is-mermaid) {
    margin: 0.75rem 0;
}

:deep(.tiptap-body .sales-book-editor .mermaid-block-label) {
    color: rgb(113 113 122);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    margin-bottom: 0.375rem;
    text-transform: uppercase;
}

:deep(.dark .tiptap-body .sales-book-editor .mermaid-block-label) {
    color: rgb(161 161 170);
}

:deep(.tiptap-body .sales-book-editor .mermaid-source) {
    background: rgb(244 244 245);
    border: 1px solid rgb(228 228 231);
    border-radius: 0.5rem;
    color: rgb(24 24 27);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.875rem;
    line-height: 1.5;
    margin: 0 0 0.5rem;
    overflow-x: auto;
    padding: 0.75rem 1rem;
}

:deep(.dark .tiptap-body .sales-book-editor .mermaid-source) {
    background: rgb(39 39 42);
    border-color: rgb(63 63 70);
    color: rgb(244 244 245);
}

:deep(.tiptap-body .sales-book-editor .mermaid-diagram) {
    background: rgb(250 250 250);
    border: 1px solid rgb(228 228 231);
    border-radius: 0.5rem;
    overflow-x: auto;
    padding: 0.75rem 1rem;
}

:deep(.tiptap-body .sales-book-editor .mermaid-diagram-readonly) {
    background: transparent;
    border: 0;
    padding: 0;
}

:deep(.dark .tiptap-body .sales-book-editor .mermaid-diagram) {
    background: rgb(24 24 27);
    border-color: rgb(63 63 70);
}

:deep(.dark .tiptap-body .sales-book-editor .mermaid-diagram-readonly) {
    background: transparent;
    border: 0;
}

:deep(.tiptap-body .sales-book-editor .mermaid-diagram svg) {
    display: block;
    height: auto;
    max-width: 100%;
}

:deep(.tiptap-body .sales-book-editor .mermaid-error) {
    color: rgb(220 38 38);
    font-size: 0.875rem;
    margin: 0;
}

:deep(.dark .tiptap-body .sales-book-editor .mermaid-error) {
    color: rgb(248 113 113);
}

:deep(.tiptap-body .sales-book-editor .sales-book-code-block:not(.is-mermaid) pre) {
    background: rgb(244 244 245);
    border: 1px solid rgb(228 228 231);
    border-radius: 0.5rem;
    color: rgb(24 24 27);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.875rem;
    line-height: 1.5;
    margin: 0.75rem 0;
    overflow-x: auto;
    padding: 0.75rem 1rem;
}

:deep(.dark .tiptap-body .sales-book-editor .sales-book-code-block:not(.is-mermaid) pre) {
    background: rgb(39 39 42);
    border-color: rgb(63 63 70);
    color: rgb(244 244 245);
}

:deep(.tiptap-body .sales-book-editor table) {
    border-collapse: collapse;
    margin: 0.75rem 0;
    table-layout: auto;
    width: 100%;
}

:deep(.tiptap-body .sales-book-editor .tableWrapper) {
    margin: 0.75rem 0;
    overflow-x: auto;
}

:deep(.tiptap-body .sales-book-editor .column-resize-handle) {
    background-color: rgb(59 130 246);
    bottom: -2px;
    pointer-events: none;
    position: absolute;
    right: -2px;
    top: 0;
    width: 4px;
}

:deep(.tiptap-body .sales-book-editor .selectedCell::after) {
    background: rgb(59 130 246 / 0.12);
    content: '';
    inset: 0;
    pointer-events: none;
    position: absolute;
    z-index: 2;
}

:deep(.tiptap-body .sales-book-editor th),
:deep(.tiptap-body .sales-book-editor td) {
    border: 1px solid rgb(212 212 216);
    min-width: 4rem;
    padding: 0.375rem 0.625rem;
    vertical-align: top;
}

:deep(.tiptap-body .sales-book-editor th) {
    background: rgb(244 244 245);
    font-weight: 600;
    text-align: left;
}

:deep(.tiptap-body .sales-book-editor td p),
:deep(.tiptap-body .sales-book-editor th p) {
    margin: 0;
}

:deep(.dark .tiptap-body .sales-book-editor th),
:deep(.dark .tiptap-body .sales-book-editor td) {
    border-color: rgb(63 63 70);
}

:deep(.dark .tiptap-body .sales-book-editor th) {
    background: rgb(39 39 42);
}

:deep(.tiptap-body .sales-book-editor ul[data-type='taskList']) {
    list-style: none;
    margin: 0.5rem 0;
    padding-left: 0;
}

:deep(.tiptap-body .sales-book-editor ul[data-type='taskList'] li) {
    align-items: flex-start;
    display: flex;
    gap: 0.5rem;
    list-style: none;
    margin: 0.25rem 0;
}

:deep(.tiptap-body .sales-book-editor ul[data-type='taskList'] li > label) {
    flex-shrink: 0;
    margin-top: 0.2rem;
}

:deep(.tiptap-body .sales-book-editor ul[data-type='taskList'] li > div) {
    flex: 1;
}

:deep(.tiptap-body .sales-book-editor img) {
    border-radius: 0.5rem;
    margin: 0.75rem 0;
    max-width: 100%;
}

:deep(.tiptap-body .sales-book-editor mark) {
    border-radius: 0.125rem;
    padding: 0.05em 0.125em;
}

:deep(.tiptap-body .sales-book-editor a) {
    color: rgb(37 99 235);
    cursor: pointer;
    text-decoration: underline;
}

:deep([data-sales-book-child-links] a) {
    color: rgb(37 99 235);
    cursor: pointer;
    text-decoration: underline;
}

:deep(.tiptap-body .sales-book-editor a:hover) {
    color: rgb(29 78 216);
}

:deep([data-sales-book-child-links] a:hover) {
    color: rgb(29 78 216);
}

:deep(.tiptap-body .ProseMirror[contenteditable='false'] a) {
    cursor: pointer;
}

:deep(.dark .tiptap-body .sales-book-editor a) {
    color: rgb(96 165 250);
}

:deep(.dark [data-sales-book-child-links] a) {
    color: rgb(96 165 250);
}

:deep(.dark .tiptap-body .sales-book-editor a:hover) {
    color: rgb(147 197 253);
}

:deep(.dark [data-sales-book-child-links] a:hover) {
    color: rgb(147 197 253);
}
</style>

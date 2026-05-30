<template>
    <div
        class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
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
            <button type="button" :class="buttonClass(false)" @click="triggerImageUpload">Картинка</button>
            <button type="button" :class="buttonClass(false)" @click="triggerFileUpload">Файл</button>
        </div>

        <div
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
            :class="editable ? '' : 'cursor-default'"
        >
            <EditorContent :editor="editor" class="tiptap-body px-4 py-3" />
        </div>

        <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="uploadAndInsert($event, true)" />
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
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Image from '@tiptap/extension-image';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';
import Underline from '@tiptap/extension-underline';
import { TableKit } from '@tiptap/extension-table';
import { Markdown } from '@tiptap/markdown';
import { SalesBookOrderedList } from '@/Components/SalesBook/SalesBookOrderedList.js';

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
});

defineOptions({
    inheritAttrs: false,
});

const emit = defineEmits(['update:modelValue']);

const imageInput = ref(null);
const fileInput = ref(null);
const isApplyingExternalContent = ref(false);

function setEditorContent(value) {
    if (!editor.value) {
        return;
    }

    isApplyingExternalContent.value = true;

    editor.value.commands.setContent(value || '', false, { contentType: 'markdown' });

    nextTick(() => {
        isApplyingExternalContent.value = false;
    });
}

const editor = useEditor({
    content: props.modelValue || '',
    contentType: 'markdown',
    editable: props.editable,
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2, 3] },
            orderedList: false,
        }),
        SalesBookOrderedList,
        Link.configure({
            openOnClick: false,
            autolink: true,
            protocols: ['http', 'https', 'mailto'],
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
        Image,
        TaskList,
        TaskItem.configure({ nested: true }),
        Underline,
        TableKit.configure({
            table: {
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
        if (isApplyingExternalContent.value) {
            return;
        }

        emit('update:modelValue', instance.getMarkdown());
    },
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value) {
            return;
        }

        const incoming = value || '';
        const current = editor.value.getMarkdown();

        if (incoming !== current) {
            setEditorContent(incoming);
        }
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
    (instance) => {
        instance?.setEditable(props.editable);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

defineExpose({
    getMarkdown: () => editor.value?.getMarkdown() ?? '',
});

const toolbarItems = computed(() => {
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
        { key: 'table', label: 'Tbl', title: 'Вставить таблицу', active: () => editor.value.isActive('table'), action: () => editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
    ];
});

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

function buttonClass(active) {
    return active
        ? `${crmSegmentedBtnActive} px-2 py-1 text-xs`
        : `${crmSegmentedBtn} px-2 py-1 text-xs`;
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

    const articleId = extractBookArticleId(anchor.getAttribute('href'));

    if (!articleId) {
        return false;
    }

    event.preventDefault();
    router.get(route('sales-assistant.book'), { article_id: articleId }, { preserveState: false });

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

function triggerImageUpload() {
    if (!props.editable) {
        return;
    }

    imageInput.value?.click();
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
            .insertContent(`[${name || url}](${url})`, false, { contentType: 'markdown' })
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

:deep(.tiptap-body .sales-book-editor table) {
    border-collapse: collapse;
    margin: 0.75rem 0;
    table-layout: auto;
    width: 100%;
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

:deep(.tiptap-body .sales-book-editor a) {
    color: rgb(37 99 235);
    text-decoration: underline;
}

:deep(.dark .tiptap-body .sales-book-editor a) {
    color: rgb(96 165 250);
}
</style>

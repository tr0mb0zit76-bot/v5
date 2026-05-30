<template>
    <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
        <div class="flex flex-wrap gap-1 border-b border-zinc-200 p-2 dark:border-zinc-800">
            <button v-for="item in toolbarItems" :key="item.key" type="button" :class="buttonClass(item.active?.() ?? false)" @click="item.action">
                {{ item.label }}
            </button>
            <button type="button" :class="buttonClass(false)" @click="setLink">Ссылка</button>
            <button type="button" :class="buttonClass(false)" @click="triggerImageUpload">Картинка</button>
            <button type="button" :class="buttonClass(false)" @click="triggerFileUpload">Файл</button>
        </div>

        <EditorContent :editor="editor" class="tiptap-body min-h-[360px] px-4 py-3" />

        <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="uploadAndInsert($event, true)" />
        <input ref="fileInput" type="file" class="hidden" @change="uploadAndInsert($event, false)" />
    </div>
</template>

<script setup>
import axios from 'axios';
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
import { Markdown } from '@tiptap/markdown';

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

const emit = defineEmits(['update:modelValue']);

const imageInput = ref(null);
const fileInput = ref(null);
const isApplyingExternalContent = ref(false);

function looksLikeHtml(value) {
    const trimmed = (value || '').trim();

    return trimmed !== '' && /<[a-z][^>]*>/i.test(trimmed);
}

function setEditorContent(value) {
    if (!editor.value) {
        return;
    }

    const incoming = value || '';

    isApplyingExternalContent.value = true;

    if (looksLikeHtml(incoming)) {
        editor.value.commands.setContent(incoming, false);
    } else {
        editor.value.commands.setContent(incoming, false, { contentType: 'markdown' });
    }

    nextTick(() => {
        isApplyingExternalContent.value = false;
    });
}

const editor = useEditor({
    content: props.modelValue || '',
    contentType: looksLikeHtml(props.modelValue) ? undefined : 'markdown',
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2, 3] },
        }),
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
        Markdown,
    ],
    editorProps: {
        attributes: {
            class: 'sales-book-editor focus:outline-none',
        },
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
        const current = instanceContentForCompare();

        if (incoming !== current) {
            setEditorContent(incoming);
        }
    },
);

function instanceContentForCompare() {
    if (!editor.value) {
        return '';
    }

    if (looksLikeHtml(props.modelValue)) {
        return editor.value.getHTML();
    }

    return editor.value.getMarkdown();
}

watch(
    () => props.editable,
    (value) => {
        if (!editor.value) {
            return;
        }

        editor.value.setEditable(value);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

const toolbarItems = computed(() => {
    if (!editor.value) {
        return [];
    }

    return [
        { key: 'p', label: 'P', active: () => editor.value.isActive('paragraph'), action: () => editor.value.chain().focus().setParagraph().run() },
        { key: 'h1', label: 'H1', active: () => editor.value.isActive('heading', { level: 1 }), action: () => editor.value.chain().focus().toggleHeading({ level: 1 }).run() },
        { key: 'h2', label: 'H2', active: () => editor.value.isActive('heading', { level: 2 }), action: () => editor.value.chain().focus().toggleHeading({ level: 2 }).run() },
        { key: 'bold', label: 'B', active: () => editor.value.isActive('bold'), action: () => editor.value.chain().focus().toggleBold().run() },
        { key: 'italic', label: 'I', active: () => editor.value.isActive('italic'), action: () => editor.value.chain().focus().toggleItalic().run() },
        { key: 'underline', label: 'U', active: () => editor.value.isActive('underline'), action: () => editor.value.chain().focus().toggleUnderline().run() },
        { key: 'bullet', label: '• List', active: () => editor.value.isActive('bulletList'), action: () => toggleListForSelection('bulletList') },
        { key: 'ordered', label: '1. List', active: () => editor.value.isActive('orderedList'), action: () => toggleListForSelection('orderedList') },
        { key: 'task', label: 'Todo', active: () => editor.value.isActive('taskList'), action: () => editor.value.chain().focus().toggleTaskList().run() },
        { key: 'quote', label: 'Quote', active: () => editor.value.isActive('blockquote'), action: () => editor.value.chain().focus().toggleBlockquote().run() },
        { key: 'code', label: '</>', active: () => editor.value.isActive('codeBlock'), action: () => editor.value.chain().focus().toggleCodeBlock().run() },
    ];
});

function toggleListForSelection(listType) {
    if (!editor.value) {
        return;
    }

    splitHardBreaksInSelection();

    const chain = editor.value.chain().focus();

    if (listType === 'bulletList') {
        chain.toggleBulletList().run();

        return;
    }

    chain.toggleOrderedList().run();
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

async function uploadAndInsert(event, shouldInsertAsImage) {
    const file = event.target.files?.[0] ?? null;
    event.target.value = '';

    if (!file || !editor.value) {
        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await axios.post(props.uploadUrl, formData);
        const { url, name, is_image: isImage } = response.data;

        if (shouldInsertAsImage || isImage) {
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
    min-height: 320px;
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
    border-radius: 0.5rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.875rem;
    margin: 0.75rem 0;
    overflow-x: auto;
    padding: 0.75rem 1rem;
}

:deep(.dark .tiptap-body .sales-book-editor pre) {
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

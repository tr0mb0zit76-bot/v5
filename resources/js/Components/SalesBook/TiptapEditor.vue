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
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Image from '@tiptap/extension-image';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';
import Underline from '@tiptap/extension-underline';

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

const editor = useEditor({
    content: props.modelValue || '<p></p>',
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
    ],
    editorProps: {
        attributes: {
            class: 'prose max-w-none focus:outline-none dark:prose-invert',
        },
    },
    onUpdate: ({ editor: instance }) => {
        emit('update:modelValue', instance.getHTML());
    },
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value) {
            return;
        }

        const incoming = value || '<p></p>';
        if (incoming !== editor.value.getHTML()) {
            editor.value.commands.setContent(incoming, false);
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
        { key: 'bullet', label: '• List', active: () => editor.value.isActive('bulletList'), action: () => editor.value.chain().focus().toggleBulletList().run() },
        { key: 'ordered', label: '1. List', active: () => editor.value.isActive('orderedList'), action: () => editor.value.chain().focus().toggleOrderedList().run() },
        { key: 'task', label: 'Todo', active: () => editor.value.isActive('taskList'), action: () => editor.value.chain().focus().toggleTaskList().run() },
        { key: 'quote', label: 'Quote', active: () => editor.value.isActive('blockquote'), action: () => editor.value.chain().focus().toggleBlockquote().run() },
        { key: 'code', label: '</>', active: () => editor.value.isActive('codeBlock'), action: () => editor.value.chain().focus().toggleCodeBlock().run() },
    ];
});

function buttonClass(active) {
    return active
        ? 'rounded-md bg-zinc-900 px-2 py-1 text-xs font-medium text-white dark:bg-zinc-100 dark:text-zinc-900'
        : 'rounded-md border border-zinc-300 px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800';
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
            .insertContent(`<p><a href="${url}" target="_blank" rel="noopener noreferrer">${name || url}</a></p>`)
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
</style>

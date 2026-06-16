<template>
    <div
        class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950"
        :class="compact ? 'min-h-[8rem]' : 'min-h-[12rem]'"
    >
        <div
            v-if="editable"
            class="flex shrink-0 flex-wrap gap-1 border-b border-zinc-200 bg-zinc-50 p-1.5 dark:border-zinc-800 dark:bg-zinc-900/60"
            role="toolbar"
            aria-label="Форматирование"
        >
            <button
                v-for="item in toolbarItems"
                :key="item.key"
                type="button"
                :title="item.title"
                :class="buttonClass(item.active?.() ?? false)"
                @click="item.action"
            >
                {{ item.label }}
            </button>
            <button type="button" :class="buttonClass(false)" title="Ссылка" @click="setLink">Ссылка</button>
            <slot name="toolbar-extra" />
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <EditorContent :editor="editor" class="crm-markdown-editor px-3 py-2" />
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import { Markdown } from '@tiptap/markdown';
import { crmSegmentedBtn, crmSegmentedBtnActive } from '@/support/crmUi.js';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Опишите действия на этапе…',
    },
    editable: {
        type: Boolean,
        default: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const isApplyingExternalContent = ref(false);
const isEditorBootstrapping = ref(true);
const toolbarRevision = ref(0);

function bumpToolbarRevision() {
    toolbarRevision.value += 1;
}

function buttonClass(active) {
    return active ? crmSegmentedBtnActive : crmSegmentedBtn;
}

function normalizeMarkdown(value) {
    return String(value ?? '').replace(/\r\n/g, '\n').trim();
}

function setEditorContent(value) {
    if (!editor.value?.markdown) {
        return;
    }

    isApplyingExternalContent.value = true;

    try {
        const markdown = normalizeMarkdown(value);
        const document = markdown === ''
            ? editor.value.markdown.parse('')
            : editor.value.markdown.parse(markdown);
        editor.value.commands.setContent(document, { emitUpdate: false });
    } catch (error) {
        console.error('CrmMarkdownEditor failed to load content', error);
    }

    nextTick(() => {
        isApplyingExternalContent.value = false;
    });
}

function syncEditorContent(value, { force = false } = {}) {
    if (!editor.value?.markdown) {
        return;
    }

    const incoming = normalizeMarkdown(value);

    if (!force) {
        const current = normalizeMarkdown(editor.value.getMarkdown());
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
    onCreate: () => finishEditorBootstrap(),
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            link: false,
            underline: false,
        }),
        Link.configure({
            openOnClick: !props.editable,
            HTMLAttributes: { class: 'crm-markdown-link' },
        }),
        Placeholder.configure({ placeholder: props.placeholder }),
        TaskList,
        TaskItem.configure({ nested: true }),
        Underline,
        Markdown.configure({
            markedOptions: { gfm: true },
        }),
    ],
    editorProps: {
        attributes: {
            class: 'crm-markdown-prose focus:outline-none min-h-[6rem]',
        },
    },
    onUpdate: ({ editor: instance }) => {
        if (isApplyingExternalContent.value || isEditorBootstrapping.value) {
            return;
        }

        emit('update:modelValue', instance.getMarkdown());
    },
});

watch(() => props.modelValue, (value) => syncEditorContent(value));

watch(
    () => props.editable,
    (value) => editor.value?.setEditable(value),
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
    insertText: (text) => {
        editor.value?.chain().focus().insertContent(text).run();
    },
});

const toolbarItems = computed(() => {
    toolbarRevision.value;

    if (!editor.value) {
        return [];
    }

    return [
        { key: 'bold', label: 'B', title: 'Жирный', active: () => editor.value.isActive('bold'), action: () => editor.value.chain().focus().toggleBold().run() },
        { key: 'italic', label: 'I', title: 'Курсив', active: () => editor.value.isActive('italic'), action: () => editor.value.chain().focus().toggleItalic().run() },
        { key: 'underline', label: 'U', title: 'Подчёркнутый', active: () => editor.value.isActive('underline'), action: () => editor.value.chain().focus().toggleUnderline().run() },
        { key: 'h2', label: 'H2', title: 'Заголовок', active: () => editor.value.isActive('heading', { level: 2 }), action: () => editor.value.chain().focus().toggleHeading({ level: 2 }).run() },
        { key: 'bullet', label: '•', title: 'Список', active: () => editor.value.isActive('bulletList'), action: () => editor.value.chain().focus().toggleBulletList().run() },
        { key: 'ordered', label: '1.', title: 'Нумерация', active: () => editor.value.isActive('orderedList'), action: () => editor.value.chain().focus().toggleOrderedList().run() },
        { key: 'task', label: '☑', title: 'Чек-лист', active: () => editor.value.isActive('taskList'), action: () => editor.value.chain().focus().toggleTaskList().run() },
        { key: 'quote', label: '❝', title: 'Цитата', active: () => editor.value.isActive('blockquote'), action: () => editor.value.chain().focus().toggleBlockquote().run() },
    ];
});

function setLink() {
    if (!editor.value) {
        return;
    }

    const previous = editor.value.getAttributes('link').href ?? '';
    const url = window.prompt('URL ссылки', previous);
    if (url === null) {
        return;
    }

    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}
</script>

<style scoped>
:deep(.crm-markdown-prose .ProseMirror p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    color: rgb(161 161 170);
    pointer-events: none;
    height: 0;
}

:deep(.crm-markdown-prose .ProseMirror) {
    min-height: 6rem;
}

:deep(.crm-markdown-prose h2) {
    font-size: 1rem;
    font-weight: 600;
    margin: 0.75rem 0 0.35rem;
}

:deep(.crm-markdown-prose h3) {
    font-size: 0.875rem;
    font-weight: 600;
    margin: 0.5rem 0 0.25rem;
}

:deep(.crm-markdown-prose p) {
    margin: 0.35rem 0;
    font-size: 0.875rem;
    line-height: 1.5;
}

:deep(.crm-markdown-prose ul),
:deep(.crm-markdown-prose ol) {
    margin: 0.35rem 0;
    padding-left: 1.25rem;
    font-size: 0.875rem;
}

:deep(.crm-markdown-prose ul[data-type='taskList']) {
    list-style: none;
    padding-left: 0;
}

:deep(.crm-markdown-prose ul[data-type='taskList'] li) {
    display: flex;
    gap: 0.5rem;
    align-items: flex-start;
}

:deep(.crm-markdown-prose blockquote) {
    border-left: 3px solid rgb(212 212 216);
    padding-left: 0.75rem;
    color: rgb(82 82 91);
    margin: 0.5rem 0;
}

:deep(.crm-markdown-prose a.crm-markdown-link) {
    color: rgb(37 99 235);
    text-decoration: underline;
}
</style>

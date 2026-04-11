<template>
    <div class="tiptap-editor">
        <!-- Панель инструментов -->
        <div v-if="editor" class="mb-4 flex flex-wrap items-center gap-1 rounded-2xl border border-zinc-200 bg-white p-2 dark:border-zinc-800 dark:bg-zinc-900">
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                :class="editor.isActive('bold') ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                @click="editor.chain().focus().toggleBold().run()"
                title="Жирный (Ctrl+B)"
            >
                <Bold class="h-4 w-4" />
            </button>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                :class="editor.isActive('italic') ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                @click="editor.chain().focus().toggleItalic().run()"
                title="Курсив (Ctrl+I)"
            >
                <Italic class="h-4 w-4" />
            </button>
            <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-700" />
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                :class="editor.isActive('heading', { level: 1 }) ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
                title="Заголовок 1"
            >
                <Heading1 class="h-4 w-4" />
            </button>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                :class="editor.isActive('heading', { level: 2 }) ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                title="Заголовок 2"
            >
                <Heading2 class="h-4 w-4" />
            </button>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                :class="editor.isActive('heading', { level: 3 }) ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                title="Заголовок 3"
            >
                <Heading3 class="h-4 w-4" />
            </button>
            <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-700" />
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                :class="editor.isActive('bulletList') ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                @click="editor.chain().focus().toggleBulletList().run()"
                title="Маркированный список"
            >
                <List class="h-4 w-4" />
            </button>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                :class="editor.isActive('orderedList') ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                @click="editor.chain().focus().toggleOrderedList().run()"
                title="Нумерованный список"
            >
                <ListOrdered class="h-4 w-4" />
            </button>
            <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-700" />
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                :class="editor.isActive('code') ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                @click="editor.chain().focus().toggleCode().run()"
                title="Код (inline)"
            >
                <Code class="h-4 w-4" />
            </button>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                :class="editor.isActive('codeBlock') ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                @click="editor.chain().focus().toggleCodeBlock().run()"
                title="Блок кода"
            >
                <Code2 class="h-4 w-4" />
            </button>
            <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-700" />
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                @click="editor.chain().focus().undo().run()"
                title="Отменить (Ctrl+Z)"
            >
                <Undo class="h-4 w-4" />
            </button>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                @click="editor.chain().focus().redo().run()"
                title="Повторить (Ctrl+Y)"
            >
                <Redo class="h-4 w-4" />
            </button>
        </div>

        <!-- Редактор -->
        <div class="min-h-[300px] rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <editor-content :editor="editor" class="prose prose-zinc max-w-none dark:prose-invert focus:outline-none" />
        </div>

        <!-- Статус -->
        <div class="mt-2 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
            <div>
                <span v-if="isSaving" class="inline-flex items-center gap-1">
                    <Loader2 class="h-3 w-3 animate-spin" />
                    Сохранение...
                </span>
                <span v-else-if="lastSaved" class="inline-flex items-center gap-1">
                    <Check class="h-3 w-3 text-emerald-500" />
                    Сохранено {{ formatTime(lastSaved) }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                    @click="togglePreview"
                >
                    <Eye v-if="!showPreview" class="h-3 w-3" />
                    <EyeOff v-else class="h-3 w-3" />
                    {{ showPreview ? 'Редактировать' : 'Предпросмотр' }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                    @click="exportMarkdown"
                >
                    <Download class="h-3 w-3" />
                    Экспорт
                </button>
            </div>
        </div>

        <!-- Предпросмотр Markdown -->
        <div
            v-if="showPreview"
            class="mt-4 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Предпросмотр Markdown</div>
            <pre class="max-h-[200px] overflow-auto rounded-lg bg-zinc-50 p-3 text-xs text-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">{{ markdownPreview }}</pre>
        </div>
    </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Code from '@tiptap/extension-code';
import CodeBlock from '@tiptap/extension-code-block';
import {
    Bold,
    Check,
    Code2,
    Download,
    Eye,
    EyeOff,
    Heading1,
    Heading2,
    Heading3,
    Italic,
    List,
    ListOrdered,
    Loader2,
    Redo,
    Undo,
} from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'Начните писать здесь...',
                        },
                    ],
                },
            ],
        }),
    },
    placeholder: {
        type: String,
        default: 'Начните писать здесь...',
    },
});

const emit = defineEmits(['update:modelValue', 'save']);

const showPreview = ref(false);
const isSaving = ref(false);
const lastSaved = ref(null);
const markdownPreview = ref('');

// Конвертер JSON в Markdown (упрощенный)
function jsonToMarkdown(json) {
    if (!json || !json.content) return '';
    
    let markdown = '';
    
    function processNode(node) {
        if (node.type === 'text') {
            let text = node.text || '';
            if (node.marks) {
                node.marks.forEach(mark => {
                    if (mark.type === 'bold') text = `**${text}**`;
                    if (mark.type === 'italic') text = `*${text}*`;
                    if (mark.type === 'code') text = `\`${text}\``;
                });
            }
            return text;
        }
        
        if (node.type === 'paragraph') {
            const content = (node.content || []).map(processNode).join('');
            return content + '\n\n';
        }
        
        if (node.type === 'heading') {
            const level = node.attrs?.level || 1;
            const content = (node.content || []).map(processNode).join('');
            return '#'.repeat(level) + ' ' + content + '\n\n';
        }
        
        if (node.type === 'bulletList') {
            const items = (node.content || []).map(item => {
                const itemContent = (item.content || []).map(processNode).join('');
                return `- ${itemContent.trim()}`;
            }).join('\n');
            return items + '\n\n';
        }
        
        if (node.type === 'orderedList') {
            const items = (node.content || []).map((item, index) => {
                const itemContent = (item.content || []).map(processNode).join('');
                return `${index + 1}. ${itemContent.trim()}`;
            }).join('\n');
            return items + '\n\n';
        }
        
        if (node.type === 'codeBlock') {
            const content = (node.content || []).map(processNode).join('');
            return '```\n' + content + '\n```\n\n';
        }
        
        if (node.content) {
            return (node.content || []).map(processNode).join('');
        }
        
        return '';
    }
    
    json.content.forEach(node => {
        markdown += processNode(node);
    });
    
    return markdown.trim();
}

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Code,
        CodeBlock,
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-zinc max-w-none dark:prose-invert focus:outline-none min-h-[250px]',
            spellcheck: 'false',
        },
    },
    onUpdate: ({ editor }) => {
        const json = editor.getJSON();
        emit('update:modelValue', json);
        markdownPreview.value = jsonToMarkdown(json);
        
        // Автосохранение
        debouncedSave(json);
    },
});

// Автосохранение с задержкой
let saveTimeout = null;
function debouncedSave(content) {
    if (saveTimeout) clearTimeout(saveTimeout);
    
    isSaving.value = true;
    saveTimeout = setTimeout(() => {
        emit('save', content);
        isSaving.value = false;
        lastSaved.value = new Date();
    }, 1000);
}

function formatTime(date) {
    if (!date) return '';
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'только что';
    if (diff < 3600000) return `${Math.floor(diff / 60000)} мин назад`;
    return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function togglePreview() {
    showPreview.value = !showPreview.value;
    if (showPreview.value && editor.value) {
        markdownPreview.value = jsonToMarkdown(editor.value.getJSON());
    }
}

function exportMarkdown() {
    if (!editor.value) return;
    
    const content = jsonToMarkdown(editor.value.getJSON());
    const blob = new Blob([content], { type: 'text/markdown' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `document-${new Date().toISOString().slice(0, 10)}.md`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

onMounted(() => {
    if (editor.value) {
        markdownPreview.value = jsonToMarkdown(editor.value.getJSON());
    }
});

onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy();
    }
    if (saveTimeout) {
        clearTimeout(saveTimeout);
    }
});

watch(() => props.modelValue, (newValue) => {
    if (editor.value && JSON.stringify(editor.value.getJSON()) !== JSON.stringify(newValue)) {
        editor.value.commands.setContent(newValue);
    }
});
</script>

<style scoped>
.tiptap-editor :deep(.ProseMirror) {
    min-height: 250px;
    outline: none;
}

.tiptap-editor :deep(.ProseMirror p.is-editor-empty:first-child::before) {
    color: #a1a1aa;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}

.tiptap-editor :deep(.ProseMirror-focused) {
    outline: none;
}

.tiptap-editor :deep(.ProseMirror h1) {
    font-size: 1.875rem;
    font-weight: 700;
    line-height: 1.2;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

.tiptap-editor :deep(.ProseMirror h2) {
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1.3;
    margin-top: 1.25rem;
    margin-bottom: 0.75rem;
}

.tiptap-editor :deep(.ProseMirror h3) {
    font-size: 1.25rem;
    font-weight: 600;
    line-height: 1.4;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

.tiptap-editor :deep(.ProseMirror ul),
.tiptap-editor :deep(.ProseMirror ol) {
    padding-left: 1.5rem;
    margin: 0.75rem 0;
}

.tiptap-editor :deep(.ProseMirror li) {
    margin: 0.25rem 0;
}

.tiptap-editor :deep(.ProseMirror code) {
    background-color: #f4f4f5;
    border-radius: 0.25rem;
    padding: 0.125rem 0.375rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.875em;
}

.tiptap-editor :deep(.ProseMirror pre) {
    background-color: #18181b;
    color: #f4f4f5;
    border-radius: 0.5rem;
    padding: 1rem;
    margin: 1rem 0;
    overflow-x: auto;
}

.tiptap-editor :deep(.ProseMirror pre code) {
    background-color: transparent;
    padding: 0;
    font-size: 0.875rem;
}

.dark .tiptap-editor :deep(.ProseMirror code) {
    background-color: #27272a;
}

.dark .tiptap-editor :deep(.ProseMirror pre) {
    background-color: #09090b;
}
</style>

<template>
    <div class="notion-editor">
        <!-- Блоковый редактор -->
        <div class="space-y-4">
            <!-- Панель инструментов (плавающая) -->
            <div
                v-if="showToolbar && editor"
                ref="toolbarRef"
                class="fixed z-50 flex items-center gap-1 rounded-xl border border-zinc-200 bg-white/95 p-2 shadow-xl backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/95"
                :style="toolbarStyle"
            >
                <!-- Форматирование текста -->
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                        :class="editor.isActive('bold') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="editor.chain().focus().toggleBold().run()"
                        title="Жирный (Ctrl+B)"
                    >
                        <Bold class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                        :class="editor.isActive('italic') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="editor.chain().focus().toggleItalic().run()"
                        title="Курсив (Ctrl+I)"
                    >
                        <Italic class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                        :class="editor.isActive('underline') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="editor.chain().focus().toggleUnderline().run()"
                        title="Подчеркивание (Ctrl+U)"
                    >
                        <Underline class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                        :class="editor.isActive('strike') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="editor.chain().focus().toggleStrike().run()"
                        title="Зачеркнутый (Ctrl+Shift+S)"
                    >
                        <Strikethrough class="h-4 w-4" />
                    </button>
                </div>

                <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-700" />

                <!-- Заголовки -->
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                        :class="editor.isActive('heading', { level: 1 }) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
                        title="Заголовок 1"
                    >
                        <Heading1 class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                        :class="editor.isActive('heading', { level: 2 }) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                        title="Заголовок 2"
                    >
                        <Heading2 class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                        :class="editor.isActive('heading', { level: 3 }) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                        title="Заголовок 3"
                    >
                        <Heading3 class="h-4 w-4" />
                    </button>
                </div>

                <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-700" />

                <!-- Списки и выравнивание -->
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                        :class="editor.isActive('bulletList') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="editor.chain().focus().toggleBulletList().run()"
                        title="Маркированный список"
                    >
                        <List class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                        :class="editor.isActive('orderedList') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="editor.chain().focus().toggleOrderedList().run()"
                        title="Нумерованный список"
                    >
                        <ListOrdered class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                        :class="editor.isActive('taskList') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="editor.chain().focus().toggleTaskList().run()"
                        title="Список задач"
                    >
                        <CheckSquare class="h-4 w-4" />
                    </button>
                </div>

                <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-700" />

                <!-- Дополнительные функции -->
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                        :class="editor.isActive('blockquote') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="editor.chain().focus().toggleBlockquote().run()"
                        title="Цитата"
                    >
                        <Quote class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                        :class="editor.isActive('codeBlock') ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                        @click="editor.chain().focus().toggleCodeBlock().run()"
                        title="Блок кода"
                    >
                        <Code class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        @click="insertHorizontalRule"
                        title="Разделитель"
                    >
                        <Minus class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Контейнер редактора -->
            <div
                ref="editorRef"
                class="min-h-[500px] rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"
                @click="focusEditor"
            >
                <!-- Плейсхолдер -->
                <div
                    v-if="!editor || editor.isEmpty"
                    class="pointer-events-none absolute top-6 left-6 text-zinc-400 dark:text-zinc-500"
                >
                    Начните печатать или нажмите '/' для команд...
                </div>

                <!-- Редактор -->
                <div ref="editorElement" class="prose prose-zinc max-w-none dark:prose-invert">
                    <editor-content :editor="editor" />
                </div>
            </div>

            <!-- Панель добавления блоков (по нажатию /) -->
            <div
                v-if="showBlockMenu"
                ref="blockMenuRef"
                class="absolute z-50 w-64 rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                :style="blockMenuStyle"
            >
                <div class="p-2">
                    <div class="mb-2 px-3 py-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                        Блоки контента
                    </div>
                    <div class="space-y-1">
                        <button
                            v-for="block in blockTypes"
                            :key="block.type"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                            @click="insertBlock(block.type)"
                        >
                            <component :is="block.icon" class="h-4 w-4 text-zinc-500 dark:text-zinc-400" />
                            <span>{{ block.label }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Статус сохранения -->
        <div class="mt-4 flex items-center justify-between">
            <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                <div v-if="saving" class="flex items-center gap-1.5">
                    <div class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></div>
                    <span>Сохранение...</span>
                </div>
                <div v-else-if="lastSaved" class="flex items-center gap-1.5">
                    <Check class="h-3 w-3 text-emerald-500" />
                    <span>Сохранено {{ formatTimeAgo(lastSaved) }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    @click="handlePreview"
                >
                    <Eye class="h-4 w-4" />
                    Предпросмотр
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                    @click="handleSave"
                    :disabled="saving"
                >
                    <Save class="h-4 w-4" />
                    {{ saving ? 'Сохранение...' : 'Сохранить' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Strike from '@tiptap/extension-strike';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';
import Placeholder from '@tiptap/extension-placeholder';
import {
    Bold,
    Italic,
    Underline as UnderlineIcon,
    Strikethrough,
    Heading1,
    Heading2,
    Heading3,
    List,
    ListOrdered,
    CheckSquare,
    Quote,
    Code,
    Minus,
    Eye,
    Save,
    Check,
} from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Начните печатать или нажмите "/" для команд...',
    },
    autosave: {
        type: Boolean,
        default: true,
    },
    autosaveDelay: {
        type: Number,
        default: 2000,
    },
});

const emit = defineEmits(['update:modelValue', 'save', 'preview']);

const editorRef = ref(null);
const editorElement = ref(null);
const toolbarRef = ref(null);
const blockMenuRef = ref(null);

const editor = useEditor({
    extensions: [
        StarterKit,
        Underline,
        Strike,
        TaskList,
        TaskItem.configure({
            nested: true,
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
    ],
    content: props.modelValue,
    editorProps: {
        attributes: {
            class: 'outline-none min-h-[400px]',
        },
        handleKeyDown: (view, event) => {
            // Обработка нажатия / для меню блоков
            if (event.key === '/' && !showBlockMenu.value) {
                event.preventDefault();
                showBlockMenu.value = true;
                return true;
            }
            
            // Закрытие меню блоков при Escape
            if (event.key === 'Escape' && showBlockMenu.value) {
                showBlockMenu.value = false;
                return true;
            }
            
            return false;
        },
    },
    onUpdate: ({ editor }) => {
        const html = editor.getHTML();
        emit('update:modelValue', html);
        
        if (props.autosave) {
            debouncedSave();
        }
    },
    onSelectionUpdate: ({ editor }) => {
        updateToolbarPosition();
    },
});

const showToolbar = ref(false);
const toolbarStyle = ref({});
const showBlockMenu = ref(false);
const blockMenuStyle = ref({});
const saving = ref(false);
const lastSaved = ref(null);

const blockTypes = [
    { type: 'heading1', label: 'Заголовок 1', icon: Heading1 },
    { type: 'heading2', label: 'Заголовок 2', icon: Heading2 },
    { type: 'heading3', label: 'Заголовок 3', icon: Heading3 },
    { type: 'bulletList', label: 'Маркированный список', icon: List },
    { type: 'orderedList', label: 'Нумерованный список', icon: ListOrdered },
    { type: 'taskList', label: 'Список задач', icon: CheckSquare },
    { type: 'blockquote', label: 'Цитата', icon: Quote },
    { type: 'codeBlock', label: 'Блок кода', icon: Code },
    { type: 'horizontalRule', label: 'Разделитель', icon: Minus },
];

let saveTimeout = null;

// Дебаунс для автосохранения
const debouncedSave = () => {
    if (saveTimeout) {
        clearTimeout(saveTimeout);
    }
    
    saveTimeout = setTimeout(() => {
        handleSave();
    }, props.autosaveDelay);
};

// Обновление позиции тулбара
const updateToolbarPosition = () => {
    if (!editor.value || !editorRef.value) return;
    
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    
    const range = selection.getRangeAt(0);
    const rect = range.getBoundingClientRect();
    
    if (rect.width === 0 && rect.height === 0) {
        showToolbar.value = false;
        return;
    }
    
    showToolbar.value = true;
    
    nextTick(() => {
        if (!toolbarRef.value) return;
        
        const toolbarHeight = toolbarRef.value.offsetHeight;
        const top = rect.top - toolbarHeight - 10;
        const left = rect.left;
        
        toolbarStyle.value = {
            top: `${Math.max(10, top)}px`,
            left: `${Math.max(10, left)}px`,
        };
    });
};

// Обновление позиции меню блоков
const updateBlockMenuPosition = () => {
    if (!editor.value || !editorRef.value) return;
    
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    
    const range = selection.getRangeAt(0);
    const rect = range.getBoundingClientRect();
    
    nextTick(() => {
        if (!blockMenuRef.value) return;
        
        const menuHeight = blockMenuRef.value.offsetHeight;
        const top = rect.top + rect.height + 5;
        const left = rect.left;
        
        blockMenuStyle.value = {
            top: `${top}px`,
            left: `${left}px`,
        };
    });
};

// Вставка блока
const insertBlock = (type) => {
    if (!editor.value) return;
    
    editor.value.chain().focus();
    
    switch (type) {
        case 'heading1':
            editor.value.chain().focus().toggleHeading({ level: 1 }).run();
            break;
        case 'heading2':
            editor.value.chain().focus().toggleHeading({ level: 2 }).run();
            break;
        case 'heading3':
            editor.value.chain().focus().toggleHeading({ level: 3 }).run();
            break;
        case 'bulletList':
            editor.value.chain().focus().toggleBulletList().run();
            break;
        case 'orderedList':
            editor.value.chain().focus().toggleOrderedList().run();
            break;
        case 'taskList':
            editor.value.chain().focus().toggleTaskList().run();
            break;
        case 'blockquote':
            editor.value.chain().focus().toggleBlockquote().run();
            break;
        case 'codeBlock':
            editor.value.chain().focus().toggleCodeBlock().run();
            break;
        case 'horizontalRule':
            editor.value.chain().focus().setHorizontalRule().run();
            break;
    }
    
    showBlockMenu.value = false;
};

// Вставка горизонтальной линии
const insertHorizontalRule = () => {
    if (!editor.value) return;
    editor.value.chain().focus().setHorizontalRule().run();
};

// Фокус на редактор
const focusEditor = () => {
    if (editor.value) {
        editor.value.chain().focus().run();
    }
};

// Сохранение
const handleSave = async () => {
    if (!editor.value || saving.value) return;
    
    saving.value = true;
    
    try {
        const content = editor.value.getHTML();
        emit('save', content);
        
        lastSaved.value = new Date();
    } catch (error) {
        console.error('Ошибка сохранения:', error);
    } finally {
        saving.value = false;
    }
};

// Предпросмотр
const handlePreview = () => {
    if (!editor.value) return;
    const content = editor.value.getHTML();
    emit('preview', content);
};

// Форматирование времени
const formatTimeAgo = (date) => {
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    
    if (minutes < 1) return 'только что';
    if (minutes === 1) return 'минуту назад';
    if (minutes < 5) return `${minutes} минуты назад`;
    if (minutes < 60) return `${minutes} минут назад`;
    
    const hours = Math.floor(minutes / 60);
    if (hours === 1) return 'час назад';
    if (hours < 5) return `${hours} часа назад`;
    if (hours < 24) return `${hours} часов назад`;
    
    const days = Math.floor(hours / 24);
    if (days === 1) return 'вчера';
    if (days < 5) return `${days} дня назад`;
    return `${days} дней назад`;
};

// Обработчики событий
const handleClickOutside = (event) => {
    if (showBlockMenu.value && blockMenuRef.value && !blockMenuRef.value.contains(event.target)) {
        showBlockMenu.value = false;
    }
    
    if (showToolbar.value && toolbarRef.value && !toolbarRef.value.contains(event.target)) {
        // Не скрываем тулбар при клике вне его, так как он должен оставаться видимым при выделении
    }
};

// Наблюдение за изменениями значения
watch(() => props.modelValue, (newValue) => {
    if (editor.value && editor.value.getHTML() !== newValue) {
        editor.value.commands.setContent(newValue, false);
    }
});

// Жизненный цикл
onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    
    // Инициализация позиции меню блоков при показе
    watch(showBlockMenu, (value) => {
        if (value) {
            nextTick(() => {
                updateBlockMenuPosition();
            });
        }
    });
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    
    if (saveTimeout) {
        clearTimeout(saveTimeout);
    }
    
    if (editor.value) {
        editor.value.destroy();
    }
});
</script>

<style scoped>
.notion-editor :deep(.ProseMirror) {
    @apply outline-none min-h-[400px];
}

.notion-editor :deep(.ProseMirror p) {
    @apply my-3;
}

.notion-editor :deep(.ProseMirror h1) {
    @apply text-3xl font-bold mt-8 mb-4;
}

.notion-editor :deep(.ProseMirror h2) {
    @apply text-2xl font-bold mt-6 mb-3;
}

.notion-editor :deep(.ProseMirror h3) {
    @apply text-xl font-bold mt-5 mb-2;
}

.notion-editor :deep(.ProseMirror ul) {
    @apply list-disc pl-6 my-3;
}

.notion-editor :deep(.ProseMirror ol) {
    @apply list-decimal pl-6 my-3;
}

.notion-editor :deep(.ProseMirror blockquote) {
    @apply border-l-4 border-zinc-300 pl-4 italic my-4 dark:border-zinc-600;
}

.notion-editor :deep(.ProseMirror code) {
    @apply bg-zinc-100 text-zinc-800 px-1 py-0.5 rounded text-sm dark:bg-zinc-800 dark:text-zinc-200;
}

.notion-editor :deep(.ProseMirror pre) {
    @apply bg-zinc-900 text-zinc-100 p-4 rounded-lg my-4 overflow-x-auto;
}

.notion-editor :deep(.ProseMirror hr) {
    @apply my-8 border-t border-zinc-300 dark:border-zinc-700;
}

.notion-editor :deep(.ProseMirror .task-item) {
    @apply flex items-start gap-2 my-2;
}

.notion-editor :deep(.ProseMirror .task-item input[type="checkbox"]) {
    @apply mt-1;
}

.notion-editor :deep(.ProseMirror .task-item label) {
    @apply flex-1;
}

.notion-editor :deep(.ProseMirror:focus) {
    @apply outline-none;
}
</style>

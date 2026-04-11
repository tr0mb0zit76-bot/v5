<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    Создание новой страницы
                </h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Добавьте новую страницу в книгу "{{ book.title }}"
                </p>
            </div>
            <Link
                :href="route('sales-book.books.show', book)"
                class="inline-flex items-center gap-2 rounded-2xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
            >
                <ArrowLeft class="h-4 w-4" />
                Назад к книге
            </Link>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <form @submit.prevent="submit">
                <div class="space-y-6">
                    <!-- Основная информация -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Основная информация
                        </h2>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <!-- Название -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    Название страницы *
                                </label>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    :class="{ 'border-red-500': form.errors.title }"
                                    placeholder="Введите название страницы"
                                    @input="generateSlug"
                                />
                                <p v-if="form.errors.title" class="text-sm text-red-500">
                                    {{ form.errors.title }}
                                </p>
                            </div>

                            <!-- Slug -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    URL-адрес (slug) *
                                </label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <span class="text-sm text-zinc-500">/sales-book/{{ book.slug }}/</span>
                                    </div>
                                    <input
                                        v-model="form.slug"
                                        type="text"
                                        class="w-full rounded-2xl border border-zinc-200 bg-white pl-48 pr-4 py-2.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        :class="{ 'border-red-500': form.errors.slug }"
                                        placeholder="nazvanie-stranicy"
                                    />
                                </div>
                                <p v-if="form.errors.slug" class="text-sm text-red-500">
                                    {{ form.errors.slug }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Уникальный идентификатор для URL
                                </p>
                            </div>
                        </div>

                        <!-- Родительская страница -->
                        <div class="mt-4 space-y-2">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Родительская страница
                            </label>
                            <select
                                v-model="form.parent_id"
                                class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                :class="{ 'border-red-500': form.errors.parent_id }"
                            >
                                <option :value="null">Без родительской страницы (корневой уровень)</option>
                                <option
                                    v-for="parentPage in parentPages"
                                    :key="parentPage.id"
                                    :value="parentPage.id"
                                >
                                    {{ parentPage.title }}
                                </option>
                            </select>
                            <p v-if="form.errors.parent_id" class="text-sm text-red-500">
                                {{ form.errors.parent_id }}
                            </p>
                        </div>

                        <!-- Краткое описание -->
                        <div class="mt-4 space-y-2">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Краткое описание
                            </label>
                            <textarea
                                v-model="form.excerpt"
                                rows="2"
                                class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                :class="{ 'border-red-500': form.errors.excerpt }"
                                placeholder="Краткое описание страницы (до 500 символов)"
                                maxlength="500"
                            />
                            <p v-if="form.errors.excerpt" class="text-sm text-red-500">
                                {{ form.errors.excerpt }}
                            </p>
                            <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                                <span>Оставьте пустым для автоматической генерации из контента</span>
                                <span>{{ form.excerpt?.length || 0 }}/500</span>
                            </div>
                        </div>
                    </div>

                    <!-- Контент -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Содержание страницы
                        </h2>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Контент *
                            </label>
                            <TipTapEditor
                                v-model="form.content"
                                :placeholder="'Начните писать содержание страницы здесь...'"
                                @save="handleEditorSave"
                            />
                            <input
                                v-model="form.raw_markdown"
                                type="hidden"
                            />
                            <p v-if="form.errors.content" class="text-sm text-red-500">
                                {{ form.errors.content }}
                            </p>
                        </div>
                    </div>

                    <!-- Статус и порядок -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Настройки публикации
                        </h2>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <!-- Статус -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    Статус *
                                </label>
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <input
                                            id="status-draft"
                                            v-model="form.status"
                                            type="radio"
                                            value="draft"
                                            class="h-4 w-4 border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:focus:ring-zinc-100"
                                        />
                                        <label
                                            for="status-draft"
                                            class="ml-2 flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300"
                                        >
                                            <span class="inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
                                            Черновик
                                        </label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            id="status-published"
                                            v-model="form.status"
                                            type="radio"
                                            value="published"
                                            class="h-4 w-4 border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:focus:ring-zinc-100"
                                        />
                                        <label
                                            for="status-published"
                                            class="ml-2 flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300"
                                        >
                                            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                            Опубликовано
                                        </label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            id="status-archived"
                                            v-model="form.status"
                                            type="radio"
                                            value="archived"
                                            class="h-4 w-4 border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:focus:ring-zinc-100"
                                        />
                                        <label
                                            for="status-archived"
                                            class="ml-2 flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300"
                                        >
                                            <span class="inline-flex h-2 w-2 rounded-full bg-zinc-500"></span>
                                            В архиве
                                        </label>
                                    </div>
                                </div>
                                <p v-if="form.errors.status" class="text-sm text-red-500">
                                    {{ form.errors.status }}
                                </p>
                            </div>

                            <!-- Порядок -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    Порядковый номер
                                </label>
                                <input
                                    v-model="form.order_index"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    :class="{ 'border-red-500': form.errors.order_index }"
                                    placeholder="0"
                                />
                                <p v-if="form.errors.order_index" class="text-sm text-red-500">
                                    {{ form.errors.order_index }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Определяет порядок отображения страниц
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Кнопки -->
                <div class="mt-8 flex items-center justify-between border-t border-zinc-200 pt-6 dark:border-zinc-800">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">
                        Поля, отмеченные *, обязательны для заполнения
                    </div>
                    <div class="flex items-center gap-3">
                        <Link
                            :href="route('sales-book.books.show', book)"
                            class="rounded-2xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        >
                            Отмена
                        </Link>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-2xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                            :disabled="form.processing"
                        >
                            <Plus v-if="!form.processing" class="h-4 w-4" />
                            <Loader2 v-else class="h-4 w-4 animate-spin" />
                            {{ form.processing ? 'Создание...' : 'Создать страницу' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, Plus } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import TipTapEditor from '@/Components/SalesBook/TipTapEditor.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-book' }, () => page),
});

const props = defineProps({
    book: {
        type: Object,
        required: true,
    },
    parentPages: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    title: '',
    slug: '',
    content: {
        type: 'doc',
        content: [
            {
                type: 'paragraph',
                content: [
                    {
                        type: 'text',
                        text: 'Начните писать содержание страницы здесь...',
                    },
                ],
            },
        ],
    },
    raw_markdown: '',
    excerpt: '',
    parent_id: null,
    order_index: 0,
    depth: 0,
    status: 'draft',
    published_at: null,
});

function generateSlug() {
    if (!form.slug || form.slug === form.previousSlug) {
        const slug = form.title
            .toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/--+/g, '-')
            .trim();
        
        form.slug = slug;
        form.previousSlug = slug;
    }
}

function handleEditorSave(content) {
    // Сохраняем JSON контент
    form.content = content;
    
    // Генерируем raw_markdown из контента (упрощенная версия)
    // В реальном приложении нужно использовать конвертер JSON->Markdown
    if (content.content && content.content[0] && content.content[0].content) {
        const text = content.content[0].content
            .filter(node => node.type === 'text')
            .map(node => node.text)
            .join(' ');
        
        form.raw_markdown = text.substring(0, 500);
        
        // Автоматически генерируем excerpt, если не заполнен
        if (!form.excerpt && text.length > 0) {
            form.excerpt = text.substring(0, 200) + (text.length > 200 ? '...' : '');
        }
    }
}

function submit() {
    form.post(route('sales-book.books.pages.store', props.book), {
        onSuccess: () => {
            form.reset();
        },
    });
}
</script>
<template>
    <div class="flex min-h-screen bg-white dark:bg-zinc-950">
        <!-- Боковая панель навигации (Notion-style) -->
        <aside class="hidden lg:flex lg:w-64 xl:w-80 flex-col border-r border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950">
            <!-- Заголовок боковой панели -->
            <div class="border-b border-zinc-200 dark:border-zinc-800 p-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-100 to-purple-100 text-blue-600 dark:from-blue-900/30 dark:to-purple-900/30 dark:text-blue-400">
                        <BookOpen class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ book.title }}
                        </h2>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                            {{ book.pages_count || 0 }} страниц
                        </p>
                    </div>
                </div>
            </div>

            <!-- Навигация по страницам -->
            <div class="flex-1 overflow-y-auto p-4">
                <div class="space-y-1">
                    <!-- Кнопка создания новой страницы -->
                    <Link
                        :href="route('sales-book.books.pages.create', book)"
                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                    >
                        <Plus class="h-4 w-4" />
                        Новая страница
                    </Link>

                    <!-- Поиск в навигации -->
                    <div class="relative mb-4">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-zinc-400" />
                        <input
                            v-model="navSearch"
                            type="text"
                            placeholder="Поиск страниц..."
                            class="w-full rounded-lg border border-zinc-200 bg-white py-2 pl-9 pr-3 text-xs outline-none transition-all focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
                        />
                    </div>

                    <!-- Древовидная структура страниц -->
                    <div class="space-y-0.5">
                        <div
                            v-for="page in filteredPages"
                            :key="page.id"
                            class="group relative"
                            :style="{ marginLeft: `${(page.depth || 0) * 16}px` }"
                        >
                            <!-- Акцентная линия для активной страницы -->
                            <div
                                v-if="isPageActive(page)"
                                class="absolute -left-4 top-0 h-full w-0.5 bg-blue-500"
                            />

                            <!-- Элемент страницы -->
                            <div
                                class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors"
                                :class="isPageActive(page)
                                    ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'
                                    : 'text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                            >
                                <!-- Иконка страницы -->
                                <FileText class="h-4 w-4 flex-shrink-0" />

                                <!-- Заголовок страницы -->
                                <Link
                                    :href="route('sales-book.pages.show', page)"
                                    class="min-w-0 flex-1 truncate"
                                >
                                    {{ page.title }}
                                </Link>

                                <!-- Действия (появляются при наведении) -->
                                <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                    <Link
                                        :href="route('sales-book.pages.edit', page)"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded text-zinc-500 transition hover:bg-white hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-700"
                                        title="Редактировать"
                                    >
                                        <Pencil class="h-3 w-3" />
                                    </Link>
                                    <button
                                        type="button"
                                        @click.stop="deletePage(page)"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded text-zinc-500 transition hover:bg-red-50 hover:text-red-600 dark:text-zinc-400 dark:hover:bg-red-950/50 dark:hover:text-red-400"
                                        title="Удалить"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>

                            <!-- Дочерние страницы (рекурсивно) -->
                            <div v-if="page.children && page.children.length > 0" class="ml-4">
                                <div
                                    v-for="child in page.children"
                                    :key="child.id"
                                    class="group relative"
                                    :style="{ marginLeft: `${(child.depth || 0) * 16}px` }"
                                >
                                    <!-- Акцентная линия для активной дочерней страницы -->
                                    <div
                                        v-if="isPageActive(child)"
                                        class="absolute -left-4 top-0 h-full w-0.5 bg-blue-500"
                                    />

                                    <!-- Элемент дочерней страницы -->
                                    <div
                                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors"
                                        :class="isPageActive(child)
                                            ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'
                                            : 'text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                                    >
                                        <FileText class="h-3.5 w-3.5 flex-shrink-0" />
                                        <Link
                                            :href="route('sales-book.pages.show', child)"
                                            class="min-w-0 flex-1 truncate"
                                        >
                                            {{ child.title }}
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Пустое состояние -->
                    <div
                        v-if="filteredPages.length === 0"
                        class="rounded-lg border border-dashed border-zinc-300 p-4 text-center dark:border-zinc-700"
                    >
                        <FileText class="mx-auto h-8 w-8 text-zinc-400 dark:text-zinc-600" />
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ navSearch ? 'Страницы не найдены' : 'Нет страниц' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Информация о книге -->
            <div class="border-t border-zinc-200 dark:border-zinc-800 p-4">
                <div class="space-y-2 text-xs text-zinc-500 dark:text-zinc-400">
                    <div class="flex items-center justify-between">
                        <span>Статус:</span>
                        <span :class="statusTextClasses[book.status]">
                            {{ statusLabels[book.status] }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Создана:</span>
                        <span>{{ formatDateShort(book.created_at) }}</span>
                    </div>
                    <div v-if="book.updated_at !== book.created_at" class="flex items-center justify-between">
                        <span>Обновлена:</span>
                        <span>{{ formatDateShort(book.updated_at) }}</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Основной контент -->
        <main class="flex-1 overflow-y-auto">
            <div class="mx-auto max-w-4xl p-6 lg:p-8">
                <!-- Заголовок и действия -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-3">
                                <div
                                    v-if="book.cover_image"
                                    class="h-16 w-16 overflow-hidden rounded-2xl"
                                >
                                    <img
                                        :src="book.cover_image"
                                        :alt="book.title"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                                <div class="h-16 w-16 overflow-hidden rounded-2xl bg-gradient-to-br from-blue-100 to-purple-100 dark:from-blue-900/30 dark:to-purple-900/30" v-else>
                                    <div class="flex h-full w-full items-center justify-center text-blue-600 dark:text-blue-400">
                                        <BookOpen class="h-8 w-8" />
                                    </div>
                                </div>
                                <div>
                                    <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">
                                        {{ book.title }}
                                    </h1>
                                    <div class="mt-2 flex items-center gap-4 text-sm text-zinc-600 dark:text-zinc-400">
                                        <span class="flex items-center gap-1.5">
                                            <User class="h-4 w-4" />
                                            {{ book.author?.name || 'Неизвестный автор' }}
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <Calendar class="h-4 w-4" />
                                            {{ formatDate(book.created_at) }}
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <FileText class="h-4 w-4" />
                                            {{ book.pages_count || 0 }} страниц
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <p v-if="book.description" class="mt-4 text-base text-zinc-700 dark:text-zinc-300 leading-relaxed">
                                {{ book.description }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link
                                :href="route('sales-book.index')"
                                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            >
                                <ArrowLeft class="h-4 w-4" />
                                Назад
                            </Link>
                            <Link
                                :href="route('sales-book.books.edit', book)"
                                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            >
                                <Pencil class="h-4 w-4" />
                                Редактировать
                            </Link>
                        </div>
                    </div>

                    <!-- Статус и категории -->
                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium"
                            :class="statusClasses[book.status]"
                        >
                            <span class="inline-flex h-2 w-2 rounded-full" :class="statusDotClasses[book.status]"></span>
                            {{ statusLabels[book.status] }}
                        </span>

                        <span
                            v-for="category in book.categories"
                            :key="category.id"
                            class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300"
                        >
                            <Tag class="h-3 w-3" />
                            {{ category.name }}
                        </span>
                    </div>
                </div>

                <!-- Контент книги -->
                <div class="space-y-8">
                    <!-- Приветственный блок -->
                    <div class="rounded-2xl border border-zinc-200 bg-gradient-to-br from-blue-50 to-purple-50 p-8 dark:border-zinc-800 dark:from-blue-950/20 dark:to-purple-950/20">
                        <div class="max-w-2xl">
                            <h2 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                                Добро пожаловать в книгу продаж
                            </h2>
                            <p class="mt-3 text-zinc-700 dark:text-zinc-300">
                                Это ваша база знаний для команды продаж. Здесь вы можете хранить документацию, инструкции, шаблоны и полезные материалы.
                            </p>
                            <div class="mt-6 flex flex-wrap gap-3">
                                <Link
                                    :href="route('sales-book.books.pages.create', book)"
                                    class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                                >
                                    <Plus class="h-4 w-4" />
                                    Создать страницу
                                </Link>
                                <Link
                                    :href="route('sales-book.books.edit', book)"
                                    class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                >
                                    <Pencil class="h-4 w-4" />
                                    Настроить книгу
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Быстрые действия -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            :href="route('sales-book.books.pages.create', book)"
                            class="group rounded-2xl border border-zinc-200 bg-white p-6 transition-all hover:border-zinc-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700"
                        >
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-100 to-purple-100 text-blue-600 dark:from-blue-900/30 dark:to-purple-900/30 dark:text-blue-400">
                                <Plus class="h-6 w-6" />
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                Новая страница
                            </h3>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                Создайте новую страницу с документацией или инструкцией
                            </p>
                        </Link>

                        <Link
                            :href="route('sales-book.categories.index')"
                            class="group rounded-2xl border border-zinc-200 bg-white p-6 transition-all hover:border-zinc-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700"
                        >
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-green-100 to-emerald-100 text-green-600 dark:from-green-900/30 dark:to-emerald-900/30 dark:text-green-400">
                                <Tag class="h-6 w-6" />
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                Категории
                            </h3>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                Управляйте категориями для организации контента
                            </p>
                        </Link>

                        <div class="group rounded-2xl border border-zinc-200 bg-white p-6 transition-all hover:border-zinc-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-amber-100 to-orange-100 text-amber-600 dark:from-amber-900/30 dark:to-orange-900/30 dark:text-amber-400">
                                <Users class="h-6 w-6" />
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                Совместная работа
                            </h3>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                Пригласите коллег для совместной работы над книгой
                            </p>
                        </div>
                    </div>

                    <!-- Недавние страницы -->
                    <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                Недавние страницы
                            </h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                Страницы, которые были недавно обновлены
                            </p>
                        </div>
                        <div class="p-6">
                            <div v-if="recentPages && recentPages.length > 0" class="space-y-3">
                                <div
                                    v-for="page in recentPages"
                                    :key="page.id"
                                    class="group flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 transition hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                            <FileText class="h-5 w-5" />
                                        </div>
                                        <div>
                                            <Link
                                                :href="route('sales-book.pages.show', page)"
                                                class="font-medium text-zinc-900 transition hover:text-zinc-700 dark:text-zinc-100 dark:hover:text-zinc-300"
                                            >
                                                {{ page.title }}
                                            </Link>
                                            <div class="mt-1 flex items-center gap-3 text-xs text-zinc-500 dark:text-zinc-500">
                                                <span class="flex items-center gap-1">
                                                    <User class="h-3 w-3" />
                                                    {{ page.author?.name || 'Неизвестный автор' }}
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <Calendar class="h-3 w-3" />
                                                    {{ formatDateShort(page.updated_at) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <Link
                                        :href="route('sales-book.pages.edit', page)"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                        title="Редактировать"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </div>
                            </div>
                            <div v-else class="py-8 text-center">
                                <FileText class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" />
                                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                                    Нет недавних страниц
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    Calendar,
    FileText,
    Pencil,
    Plus,
    Search,
    Tag,
    Trash2,
    User,
    Users,
} from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-book' }, () => page),
});

const props = defineProps({
    book: {
        type: Object,
        required: true,
    },
    pages: {
        type: Array,
        default: () => [],
    },
});

const navSearch = ref('');

// Статусы книги
const statusLabels = {
    draft: 'Черновик',
    published: 'Опубликовано',
    archived: 'В архиве',
};

const statusClasses = {
    draft: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400',
    published: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400',
    archived: 'bg-zinc-50 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-400',
};

const statusDotClasses = {
    draft: 'bg-amber-500',
    published: 'bg-emerald-500',
    archived: 'bg-zinc-500',
};

const statusTextClasses = {
    draft: 'text-amber-600 dark:text-amber-400',
    published: 'text-emerald-600 dark:text-emerald-400',
    archived: 'text-zinc-600 dark:text-zinc-400',
};

// Фильтрация страниц для навигации
const filteredPages = computed(() => {
    if (!navSearch.value) {
        return props.pages;
    }
    
    const searchLower = navSearch.value.toLowerCase();
    return props.pages.filter(page => 
        page.title.toLowerCase().includes(searchLower) ||
        (page.excerpt && page.excerpt.toLowerCase().includes(searchLower))
    );
});

// Получение недавних страниц (последние 5 обновленных)
const recentPages = computed(() => {
    return [...props.pages]
        .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
        .slice(0, 5);
});

// Проверка активной страницы (для подсветки в навигации)
function isPageActive(page) {
    // В реальном приложении здесь была бы проверка текущего URL
    // Для демо просто возвращаем false
    return false;
}

// Форматирование даты
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

// Форматирование даты (краткий вариант)
function formatDateShort(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) {
        return 'Сегодня';
    } else if (diffDays === 1) {
        return 'Вчера';
    } else if (diffDays < 7) {
        return `${diffDays} дня назад`;
    } else {
        return date.toLocaleDateString('ru-RU', {
            day: 'numeric',
            month: 'short',
        });
    }
}

// Удаление страницы
function deletePage(page) {
    if (confirm(`Вы уверены, что хотите удалить страницу "${page.title}"?`)) {
        useForm({}).delete(route('sales-book.pages.destroy', page));
    }
}

// Удаление книги
function deleteBook() {
    if (confirm(`Вы уверены, что хотите удалить книгу "${props.book.title}"?`)) {
        useForm({}).delete(route('sales-book.books.destroy', props.book));
    }
}
</script>

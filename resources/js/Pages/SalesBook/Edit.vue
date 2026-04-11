<template>
    <div class="flex min-h-screen bg-white dark:bg-zinc-950">
        <!-- Боковая панель навигации -->
        <aside class="hidden lg:flex lg:w-64 xl:w-80 flex-col border-r border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950">
            <!-- Заголовок боковой панели -->
            <div class="border-b border-zinc-200 dark:border-zinc-800 p-6">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('sales-book.books.show', book)"
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-100 to-purple-100 text-blue-600 dark:from-blue-900/30 dark:to-purple-900/30 dark:text-blue-400"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ book?.title || 'Книга' }}
                        </h2>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                            Назад к книге
                        </p>
                    </div>
                </div>
            </div>

            <!-- Навигация по страницам -->
            <div class="flex-1 overflow-y-auto p-4">
                <div class="space-y-1">
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
                        <span>Страниц:</span>
                        <span>{{ book?.pages_count || 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Автор:</span>
                        <span>{{ book?.author?.name || 'Неизвестно' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Создана:</span>
                        <span>{{ formatDateShort(book?.created_at) }}</span>
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
                            <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">
                                Редактирование книги
                            </h1>
                            <p class="mt-2 text-base text-zinc-600 dark:text-zinc-400">
                                Измените информацию о книге продаж
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link
                                :href="route('sales-book.books.show', book)"
                                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            >
                                <ArrowLeft class="h-4 w-4" />
                                Отмена
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Форма редактирования -->
                <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Основная информация
                        </h2>
                    </div>
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <!-- Название -->
                            <div class="mb-6">
                                <label class="mb-2 block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    Название книги
                                </label>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    class="w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm outline-none transition-all focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    placeholder="Введите название книги"
                                />
                                <p v-if="form.errors.title" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.title }}
                                </p>
                            </div>

                            <!-- Описание -->
                            <div class="mb-6">
                                <label class="mb-2 block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    Описание
                                </label>
                                <textarea
                                    v-model="form.description"
                                    rows="4"
                                    class="w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm outline-none transition-all focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    placeholder="Опишите назначение этой книги"
                                />
                                <p v-if="form.errors.description" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.description }}
                                </p>
                            </div>

                            <!-- Категория -->
                            <div class="mb-6">
                                <label class="mb-2 block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    Категория
                                </label>
                                <select
                                    v-model="form.category_id"
                                    class="w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm outline-none transition-all focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
                                >
                                    <option value="">Без категории</option>
                                    <option
                                        v-for="category in categories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.category_id" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.category_id }}
                                </p>
                            </div>

                            <!-- Статус -->
                            <div class="mb-8">
                                <label class="mb-2 block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    Статус
                                </label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="form.status"
                                            type="radio"
                                            value="draft"
                                            class="h-4 w-4 border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800"
                                        />
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300">Черновик</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="form.status"
                                            type="radio"
                                            value="published"
                                            class="h-4 w-4 border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800"
                                        />
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300">Опубликовано</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="form.status"
                                            type="radio"
                                            value="archived"
                                            class="h-4 w-4 border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800"
                                        />
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300">В архиве</span>
                                    </label>
                                </div>
                                <p v-if="form.errors.status" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.status }}
                                </p>
                            </div>

                            <!-- Действия -->
                            <div class="flex items-center justify-end gap-3 border-t border-zinc-200 pt-6 dark:border-zinc-800">
                                <Link
                                    :href="route('sales-book.books.show', book)"
                                    class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                >
                                    Отмена
                                </Link>
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                                    :disabled="form.processing"
                                >
                                    <Save class="h-4 w-4" />
                                    {{ form.processing ? 'Сохранение...' : 'Сохранить изменения' }}
                                </button>
                            </div>
                        </form>
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
    FileText,
    Save,
    Search,
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
    categories: {
        type: Array,
        default: () => [],
    },
});

const navSearch = ref('');

const form = useForm({
    title: props.book.title,
    description: props.book.description,
    category_id: props.book.category_id,
    status: props.book.status,
});

const submit = () => {
    form.put(route('sales-book.books.update', props.book));
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

// Проверка активной страницы
const isPageActive = (page) => {
    return false; // В режиме редактирования книги нет активной страницы
};

// Форматирование даты (краткий вариант)
const formatDateShort = (dateString) => {
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
};
</script>

<style scoped>
/* Стили для формы */
input:focus, textarea:focus, select:focus {
    @apply ring-2 ring-blue-500/20;
}
</style>
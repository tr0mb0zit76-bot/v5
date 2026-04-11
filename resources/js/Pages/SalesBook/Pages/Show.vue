<template>
    <div class="flex min-h-screen bg-white dark:bg-zinc-950">
        <!-- Боковая панель навигации -->
        <aside class="hidden lg:flex lg:w-64 xl:w-80 flex-col border-r border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950">
            <!-- Заголовок боковой панели -->
            <div class="border-b border-zinc-200 dark:border-zinc-800 p-6">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('sales-book.books.show', page.book)"
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-100 to-purple-100 text-blue-600 dark:from-blue-900/30 dark:to-purple-900/30 dark:text-blue-400"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ page.book?.title || 'Книга' }}
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
                            v-for="bookPage in filteredPages"
                            :key="bookPage.id"
                            class="group relative"
                            :style="{ marginLeft: `${(bookPage.depth || 0) * 16}px` }"
                        >
                            <!-- Акцентная линия для активной страницы -->
                            <div
                                v-if="isPageActive(bookPage)"
                                class="absolute -left-4 top-0 h-full w-0.5 bg-blue-500"
                            />

                            <!-- Элемент страницы -->
                            <div
                                class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors"
                                :class="isPageActive(bookPage)
                                    ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'
                                    : 'text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100'"
                            >
                                <!-- Иконка страницы -->
                                <FileText class="h-4 w-4 flex-shrink-0" />

                                <!-- Заголовок страницы -->
                                <Link
                                    :href="route('sales-book.pages.show', bookPage)"
                                    class="min-w-0 flex-1 truncate"
                                >
                                    {{ bookPage.title }}
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

            <!-- Информация о странице -->
            <div class="border-t border-zinc-200 dark:border-zinc-800 p-4">
                <div class="space-y-2 text-xs text-zinc-500 dark:text-zinc-400">
                    <div class="flex items-center justify-between">
                        <span>Статус:</span>
                        <span :class="pageStatusTextClasses[page.status]">
                            {{ pageStatusLabels[page.status] }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Автор:</span>
                        <span>{{ page.author?.name || 'Неизвестно' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Создана:</span>
                        <span>{{ formatDateShort(page.created_at) }}</span>
                    </div>
                    <div v-if="page.updated_at !== page.created_at" class="flex items-center justify-between">
                        <span>Обновлена:</span>
                        <span>{{ formatDateShort(page.updated_at) }}</span>
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
                                <div class="h-16 w-16 overflow-hidden rounded-2xl bg-gradient-to-br from-blue-100 to-purple-100 dark:from-blue-900/30 dark:to-purple-900/30">
                                    <div class="flex h-full w-full items-center justify-center text-blue-600 dark:text-blue-400">
                                        <FileText class="h-8 w-8" />
                                    </div>
                                </div>
                                <div>
                                    <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">
                                        {{ page.title }}
                                    </h1>
                                    <div class="mt-2 flex items-center gap-4 text-sm text-zinc-600 dark:text-zinc-400">
                                        <span class="flex items-center gap-1.5">
                                            <User class="h-4 w-4" />
                                            {{ page.author?.name || 'Неизвестный автор' }}
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <Calendar class="h-4 w-4" />
                                            {{ formatDate(page.created_at) }}
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <Eye class="h-4 w-4" />
                                            {{ page.views_count || 0 }} просмотров
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <p v-if="page.excerpt" class="mt-4 text-base text-zinc-700 dark:text-zinc-300 leading-relaxed">
                                {{ page.excerpt }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link
                                :href="route('sales-book.books.show', page.book)"
                                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            >
                                <ArrowLeft class="h-4 w-4" />
                                К книге
                            </Link>
                            <Link
                                :href="route('sales-book.pages.edit', page)"
                                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            >
                                <Pencil class="h-4 w-4" />
                                Редактировать
                            </Link>
                        </div>
                    </div>

                    <!-- Статус и теги -->
                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium"
                            :class="pageStatusClasses[page.status]"
                        >
                            <span class="inline-flex h-2 w-2 rounded-full" :class="pageStatusDotClasses[page.status]"></span>
                            {{ pageStatusLabels[page.status] }}
                        </span>

                        <span
                            v-for="tag in page.tags"
                            :key="tag.id"
                            class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300"
                        >
                            <Tag class="h-3 w-3" />
                            {{ tag.name }}
                        </span>
                    </div>
                </div>

                <!-- Контент страницы -->
                <div class="mb-12">
                    <div class="prose prose-zinc max-w-none dark:prose-invert">
                        <div v-html="page.content"></div>
                    </div>
                </div>

                <!-- Комментарии -->
                <div class="mb-12">
                    <CommentsSection
                        :comments="page.comments || []"
                        :current-user="currentUser"
                        @add-comment="handleAddComment"
                        @edit-comment="handleEditComment"
                        @delete-comment="handleDeleteComment"
                        @add-reply="handleAddReply"
                        @toggle-like="handleToggleLike"
                    />
                </div>

                <!-- История изменений -->
                <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            История изменений
                        </h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            Последние изменения этой страницы
                        </p>
                    </div>
                    <div class="p-6">
                        <div v-if="page.versions && page.versions.length > 0" class="space-y-4">
                            <div
                                v-for="version in page.versions.slice(0, 3)"
                                :key="version.id"
                                class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950"
                            >
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-100 to-purple-100 text-blue-600 dark:from-blue-900/30 dark:to-purple-900/30 dark:text-blue-400">
                                    <User class="h-5 w-5" />
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ version.author?.name || 'Аноним' }}
                                        </h4>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ formatTimeAgo(version.created_at) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ version.description || 'Изменения внесены' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-8 text-center">
                            <History class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" />
                            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                                Нет истории изменений
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    Eye,
    FileText,
    History,
    Pencil,
    Search,
    Tag,
    User,
} from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CommentsSection from '@/Components/SalesBook/CommentsSection.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-book' }, () => page),
});

const props = defineProps({
    page: {
        type: Object,
        required: true,
    },
    pages: {
        type: Array,
        default: () => [],
    },
    currentUser: {
        type: Object,
        default: () => ({}),
    },
});

const navSearch = ref('');

// Статусы страницы
const pageStatusLabels = {
    draft: 'Черновик',
    published: 'Опубликовано',
    archived: 'В архиве',
};

const pageStatusClasses = {
    draft: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400',
    published: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400',
    archived: 'bg-zinc-50 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-400',
};

const pageStatusDotClasses = {
    draft: 'bg-amber-500',
    published: 'bg-emerald-500',
    archived: 'bg-zinc-500',
};

const pageStatusTextClasses = {
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
    return props.pages.filter(bookPage => 
        bookPage.title.toLowerCase().includes(searchLower) ||
        (bookPage.excerpt && bookPage.excerpt.toLowerCase().includes(searchLower))
    );
});

// Проверка активной страницы
const isPageActive = (bookPage) => {
    return bookPage.id === props.page.id;
};

// Форматирование даты
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
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

// Форматирование времени для истории
const formatTimeAgo = (dateString) => {
    if (!dateString) return '';
    
    const date = new Date(dateString);
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

// Обработчики комментариев
const handleAddComment = async (content) => {
    console.log('Добавление комментария:', content);
    // Здесь будет запрос к API
};

const handleEditComment = async ({ id, content }) => {
    console.log('Редактирование комментария:', id, content);
    // Здесь будет запрос к API
};

const handleDeleteComment = async (commentId) => {
    console.log('Удаление комментария:', commentId);
    // Здесь будет запрос к API
};

const handleAddReply = async ({ commentId, content }) => {
    console.log('Добавление ответа:', commentId, content);
    // Здесь будет запрос к API
};

const handleToggleLike = async (commentId) => {
    console.log('Лайк комментария:', commentId);
    // Здесь будет запрос к API
};
</script>

<style scoped>
/* Стили для контента страницы */
.prose :deep(h1) {
    @apply text-3xl font-bold mt-8 mb-4;
}

.prose :deep(h2) {
    @apply text-2xl font-bold mt-6 mb-3;
}

.prose :deep(h3) {
    @apply text-xl font-bold mt-5 mb-2;
}

.prose :deep(p) {
    @apply my-4 leading-relaxed;
}

.prose :deep(ul) {
    @apply list-disc pl-6 my-4;
}

.prose :deep(ol) {
    @apply list-decimal pl-6 my-4;
}

.prose :deep(blockquote) {
    @apply border-l-4 border-zinc-300 pl-4 italic my-6 dark:border-zinc-600;
}

.prose :deep(code) {
    @apply bg-zinc-100 text-zinc-800 px-1 py-0.5 rounded text-sm dark:bg-zinc-800 dark:text-zinc-200;
}

.prose :deep(pre) {
    @apply bg-zinc-900 text-zinc-100 p-4 rounded-lg my-6 overflow-x-auto;
}

.prose :deep(a) {
    @apply text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline;
}

.prose :deep(img) {
    @apply rounded-lg my-6 max-w-full;
}

.prose :deep(table) {
    @apply w-full border-collapse my-6;
}

.prose :deep(th) {
    @apply border border-zinc-300 px-4 py-2 text-left font-semibold dark:border-zinc-700;
}

.prose :deep(td) {
    @apply border border-zinc-300 px-4 py-2 dark:border-zinc-700;
}
</style>

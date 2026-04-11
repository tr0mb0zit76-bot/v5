<template>
    <div class="space-y-8">
        <!-- Заголовок и действия -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">
                    Книга продаж
                </h1>
                <p class="mt-2 text-base text-zinc-600 dark:text-zinc-400">
                    База знаний компании в стиле Notion. Документация, инструкции и полезные материалы для команды продаж.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('sales-book.categories.index')"
                    class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"
                >
                    <Tag class="h-4 w-4" />
                    Категории
                </Link>
                <Link
                    :href="route('sales-book.books.create')"
                    class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                >
                    <Plus class="h-4 w-4" />
                    Новая книга
                </Link>
            </div>
        </div>

        <!-- Поиск и фильтры в стиле Notion -->
        <div class="sticky top-0 z-10 bg-white/80 backdrop-blur-sm dark:bg-zinc-950/80 pt-2 pb-4">
            <div class="flex flex-col gap-4">
                <!-- Поиск -->
                <div class="relative max-w-2xl">
                    <Search class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Поиск по книгам, страницам, содержанию..."
                        class="w-full rounded-xl border border-zinc-200 bg-white py-3 pl-12 pr-4 text-sm outline-none transition-all focus:border-zinc-900 focus:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
                        @input="debouncedSearch"
                    />
                </div>

                <!-- Быстрые фильтры -->
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="filter in quickFilters"
                        :key="filter.key"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-medium transition-all hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        :class="activeFilter === filter.key ? 'border-zinc-900 bg-zinc-100 text-zinc-900 dark:border-zinc-50 dark:bg-zinc-800 dark:text-zinc-100' : ''"
                        @click="toggleFilter(filter.key)"
                    >
                        <component :is="filter.icon" class="h-4 w-4" />
                        {{ filter.label }}
                    </button>
                    
                    <select
                        v-model="filterCategory"
                        class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm outline-none transition-all focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
                    >
                        <option value="">Все категории</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Сетка книг в стиле Notion -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div
                v-for="book in filteredBooks"
                :key="book.id"
                class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white transition-all duration-300 hover:border-zinc-300 hover:shadow-xl dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700"
            >
                <!-- Акцентная полоса слева -->
                <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-blue-500 to-purple-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                
                <Link :href="route('sales-book.books.show', book.id)" class="block">
                    <div class="p-6">
                        <!-- Заголовок и иконка -->
                        <div class="mb-4 flex items-start justify-between">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-100 to-purple-100 text-blue-600 dark:from-blue-900/30 dark:to-purple-900/30 dark:text-blue-400">
                                <BookOpen class="h-7 w-7" />
                            </div>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium"
                                :class="statusClasses[book.status]"
                            >
                                <span class="inline-flex h-2 w-2 rounded-full" :class="statusDotClasses[book.status]"></span>
                                {{ statusLabels[book.status] }}
                            </span>
                        </div>

                        <!-- Заголовок книги -->
                        <h3 class="mb-3 text-xl font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">
                            {{ book.title }}
                        </h3>

                        <!-- Описание -->
                        <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400 line-clamp-3">
                            {{ book.description || 'Добавьте описание для этой книги...' }}
                        </p>

                        <!-- Мета-информация -->
                        <div class="space-y-3">
                            <!-- Автор и дата -->
                            <div class="flex items-center justify-between text-sm text-zinc-500 dark:text-zinc-400">
                                <div class="flex items-center gap-2">
                                    <User class="h-4 w-4" />
                                    <span class="truncate">{{ book.author?.name || 'Автор' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Calendar class="h-4 w-4" />
                                    <span>{{ formatDate(book.updated_at) }}</span>
                                </div>
                            </div>

                            <!-- Статистика -->
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                                        <FileText class="h-4 w-4" />
                                        <span>{{ book.pages_count || 0 }} стр.</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400" v-if="book.categories?.length">
                                        <Tag class="h-4 w-4" />
                                        <span>{{ book.categories.length }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                                    <Eye class="h-4 w-4" />
                                    <span>{{ book.views_count || 0 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </Link>

                <!-- Действия (появляются при наведении) -->
                <div class="absolute right-3 top-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <div class="flex items-center gap-1 rounded-xl bg-white/90 backdrop-blur-sm p-1 shadow-lg dark:bg-zinc-800/90">
                        <Link
                            :href="route('sales-book.books.edit', book.id)"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-700"
                        >
                            <Pencil class="h-3.5 w-3.5" />
                        </Link>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            @click.stop="handleDelete(book)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Пустое состояние -->
        <div
            v-if="filteredBooks.length === 0"
            class="rounded-2xl border border-dashed border-zinc-300 bg-white p-12 text-center dark:border-zinc-700 dark:bg-zinc-900"
        >
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-blue-100 to-purple-100 dark:from-blue-900/30 dark:to-purple-900/30">
                <BookOpen class="h-8 w-8 text-blue-600 dark:text-blue-400" />
            </div>
            <h3 class="mt-6 text-xl font-semibold text-zinc-900 dark:text-zinc-100">Нет книг</h3>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                {{ search || filterCategory || activeFilter ? 'Попробуйте изменить параметры поиска' : 'Создайте первую книгу продаж' }}
            </p>
            <Link
                v-if="!search && !filterCategory && !activeFilter"
                :href="route('sales-book.books.create')"
                class="mt-6 inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
            >
                <Plus class="h-4 w-4" />
                Создать книгу
            </Link>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash-es';
import {
    BookOpen,
    Calendar,
    Eye,
    FileText,
    Pencil,
    Plus,
    Search,
    Tag,
    Trash2,
    User,
} from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-book' }, () => page),
});

const props = defineProps({
    books: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
});

const search = ref('');
const filterCategory = ref('');
const activeFilter = ref('');
const quickFilters = ref([
    { key: 'recent', label: 'Недавние', icon: Calendar },
    { key: 'popular', label: 'Популярные', icon: Eye },
    { key: 'draft', label: 'Черновики', icon: FileText },
    { key: 'published', label: 'Опубликованные', icon: BookOpen },
]);

const statusLabels = {
    draft: 'Черновик',
    published: 'Опубликовано',
    archived: 'В архиве',
};

const statusClasses = {
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    published: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    archived: 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-300',
};

const statusDotClasses = {
    draft: 'bg-amber-500',
    published: 'bg-emerald-500',
    archived: 'bg-zinc-500',
};

const filteredBooks = computed(() => {
    return props.books.filter(book => {
        // Поиск
        if (search.value) {
            const searchLower = search.value.toLowerCase();
            const matchesSearch = 
                book.title.toLowerCase().includes(searchLower) ||
                (book.description && book.description.toLowerCase().includes(searchLower)) ||
                (book.author?.name && book.author.name.toLowerCase().includes(searchLower));
            
            if (!matchesSearch) return false;
        }

        // Фильтр по категории
        if (filterCategory.value && book.categories) {
            const hasCategory = book.categories.some(cat => cat.id == filterCategory.value);
            if (!hasCategory) return false;
        }

        // Быстрые фильтры
        if (activeFilter.value) {
            switch (activeFilter.value) {
                case 'recent':
                    // Фильтр по дате (последние 7 дней)
                    const weekAgo = new Date();
                    weekAgo.setDate(weekAgo.getDate() - 7);
                    if (new Date(book.updated_at) < weekAgo) return false;
                    break;
                case 'popular':
                    if (!book.views_count || book.views_count < 10) return false;
                    break;
                case 'draft':
                    if (book.status !== 'draft') return false;
                    break;
                case 'published':
                    if (book.status !== 'published') return false;
                    break;
            }
        }

        return true;
    });
});

const debouncedSearch = debounce(() => {
    // В реальном приложении здесь был бы запрос к API
    // Для демо просто фильтруем локально
}, 300);

function toggleFilter(filterKey) {
    activeFilter.value = activeFilter.value === filterKey ? '' : filterKey;
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function handleDelete(book) {
    if (confirm(`Удалить книгу "${book.title}"?`)) {
        router.delete(route('sales-book.books.destroy', book.id), {
            onSuccess: () => {
                // Inertia автоматически обновит пропсы
            },
        });
    }
}

onMounted(() => {
    // Инициализация
});
</script>

<style scoped>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
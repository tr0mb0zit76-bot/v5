<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    Создание новой книги
                </h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Заполните информацию о новой книге продаж
                </p>
            </div>
            <Link
                :href="route('sales-book.index')"
                class="inline-flex items-center gap-2 rounded-2xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
            >
                <ArrowLeft class="h-4 w-4" />
                Назад к списку
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
                                    Название книги *
                                </label>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    :class="{ 'border-red-500': form.errors.title }"
                                    placeholder="Введите название книги"
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
                                        <span class="text-sm text-zinc-500">/sales-book/</span>
                                    </div>
                                    <input
                                        v-model="form.slug"
                                        type="text"
                                        class="w-full rounded-2xl border border-zinc-200 bg-white pl-28 pr-4 py-2.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                        :class="{ 'border-red-500': form.errors.slug }"
                                        placeholder="nazvanie-knigi"
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

                        <!-- Описание -->
                        <div class="mt-4 space-y-2">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Описание
                            </label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                :class="{ 'border-red-500': form.errors.description }"
                                placeholder="Краткое описание книги"
                            />
                            <p v-if="form.errors.description" class="text-sm text-red-500">
                                {{ form.errors.description }}
                            </p>
                        </div>
                    </div>

                    <!-- Категории и статус -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Дополнительные настройки
                        </h2>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <!-- Категории -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    Категории
                                </label>
                                <div class="space-y-2">
                                    <div
                                        v-for="category in categories"
                                        :key="category.id"
                                        class="flex items-center"
                                    >
                                        <input
                                            :id="`category-${category.id}`"
                                            v-model="form.category_ids"
                                            type="checkbox"
                                            :value="category.id"
                                            class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:focus:ring-zinc-100"
                                        />
                                        <label
                                            :for="`category-${category.id}`"
                                            class="ml-2 text-sm text-zinc-700 dark:text-zinc-300"
                                        >
                                            {{ category.name }}
                                        </label>
                                    </div>
                                </div>
                                <p v-if="form.errors.category_ids" class="text-sm text-red-500">
                                    {{ form.errors.category_ids }}
                                </p>
                            </div>

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
                        </div>
                    </div>

                    <!-- Обложка -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Обложка книги
                        </h2>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                URL обложки
                            </label>
                            <input
                                v-model="form.cover_image"
                                type="text"
                                class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                :class="{ 'border-red-500': form.errors.cover_image }"
                                placeholder="https://example.com/cover.jpg"
                            />
                            <p v-if="form.errors.cover_image" class="text-sm text-red-500">
                                {{ form.errors.cover_image }}
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                Оставьте пустым, чтобы использовать стандартную обложку
                            </p>
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
                            :href="route('sales-book.index')"
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
                            {{ form.processing ? 'Создание...' : 'Создать книгу' }}
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

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-book' }, () => page),
});

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    title: '',
    slug: '',
    description: '',
    cover_image: '',
    status: 'draft',
    category_ids: [],
    order_index: 0,
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

function submit() {
    form.post(route('sales-book.books.store'), {
        onSuccess: () => {
            form.reset();
        },
    });
}
</script>
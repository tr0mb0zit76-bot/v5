<template>
    <div class="comments-section">
        <!-- Заголовок -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                Комментарии
                <span v-if="comments.length > 0" class="ml-2 text-sm font-normal text-zinc-500 dark:text-zinc-400">
                    ({{ comments.length }})
                </span>
            </h3>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Обсудите эту страницу с коллегами
            </p>
        </div>

        <!-- Форма добавления комментария -->
        <div class="mb-8 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-start gap-3">
                <!-- Аватар пользователя -->
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-100 to-purple-100 text-blue-600 dark:from-blue-900/30 dark:to-purple-900/30 dark:text-blue-400">
                    <span class="text-sm font-medium">
                        {{ currentUser?.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </span>
                </div>
                
                <!-- Форма -->
                <div class="flex-1">
                    <textarea
                        v-model="newComment"
                        ref="commentInput"
                        placeholder="Напишите комментарий..."
                        class="w-full rounded-xl border border-zinc-200 bg-white p-4 text-sm outline-none transition-all focus:border-zinc-900 focus:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
                        rows="3"
                        @keydown.ctrl.enter="addComment"
                    />
                    
                    <!-- Действия -->
                    <div class="mt-4 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="flex items-center gap-1">
                                <Info class="h-3 w-3" />
                                Нажмите Ctrl+Enter для отправки
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                @click="newComment = ''"
                                :disabled="!newComment.trim()"
                            >
                                Очистить
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                                @click="addComment"
                                :disabled="!newComment.trim() || addingComment"
                            >
                                <Send class="h-4 w-4" />
                                {{ addingComment ? 'Отправка...' : 'Отправить' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Список комментариев -->
        <div class="space-y-6">
            <!-- Комментарий -->
            <div
                v-for="comment in comments"
                :key="comment.id"
                class="group rounded-2xl border border-zinc-200 bg-white p-6 transition-all hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700"
            >
                <div class="flex items-start justify-between">
                    <!-- Автор и информация -->
                    <div class="flex items-start gap-3">
                        <!-- Аватар -->
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-100 to-purple-100 text-blue-600 dark:from-blue-900/30 dark:to-purple-900/30 dark:text-blue-400">
                            <span class="text-sm font-medium">
                                {{ comment.author?.name?.charAt(0)?.toUpperCase() || 'U' }}
                            </span>
                        </div>
                        
                        <!-- Контент -->
                        <div class="flex-1">
                            <!-- Заголовок -->
                            <div class="flex items-center gap-2">
                                <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ comment.author?.name || 'Аноним' }}
                                </h4>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ formatTimeAgo(comment.created_at) }}
                                </span>
                                
                                <!-- Бейдж автора -->
                                <span
                                    v-if="comment.author?.id === currentUser?.id"
                                    class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
                                >
                                    <User class="h-3 w-3" />
                                    Вы
                                </span>
                            </div>
                            
                            <!-- Текст комментария -->
                            <div class="mt-2 text-sm text-zinc-700 dark:text-zinc-300 prose prose-zinc max-w-none dark:prose-invert">
                                <div v-html="comment.content"></div>
                            </div>
                            
                            <!-- Действия -->
                            <div class="mt-4 flex items-center gap-4">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 text-xs text-zinc-500 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                                    @click="toggleLike(comment)"
                                >
                                    <Heart class="h-3.5 w-3.5" :class="comment.is_liked ? 'fill-red-500 text-red-500' : ''" />
                                    <span>{{ comment.likes_count || 0 }}</span>
                                </button>
                                
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 text-xs text-zinc-500 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                                    @click="toggleReply(comment)"
                                >
                                    <MessageSquare class="h-3.5 w-3.5" />
                                    <span>Ответить</span>
                                </button>
                                
                                <!-- Действия автора -->
                                <div
                                    v-if="comment.author?.id === currentUser?.id"
                                    class="ml-auto flex items-center gap-2 opacity-0 transition-opacity group-hover:opacity-100"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                        @click="editComment(comment)"
                                        title="Редактировать"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-red-50 hover:text-red-600 dark:text-zinc-400 dark:hover:bg-red-950/50 dark:hover:text-red-400"
                                        @click="deleteComment(comment)"
                                        title="Удалить"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Форма ответа -->
                            <div
                                v-if="replyingTo === comment.id"
                                class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-100 to-purple-100 text-blue-600 dark:from-blue-900/30 dark:to-purple-900/30 dark:text-blue-400">
                                        <span class="text-xs font-medium">
                                            {{ currentUser?.name?.charAt(0)?.toUpperCase() || 'U' }}
                                        </span>
                                    </div>
                                    <div class="flex-1">
                                        <textarea
                                            v-model="replyContent"
                                            placeholder="Напишите ответ..."
                                            class="w-full rounded-lg border border-zinc-200 bg-white p-3 text-sm outline-none transition-all focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-50"
                                            rows="2"
                                            @keydown.ctrl.enter="addReply(comment)"
                                        />
                                        <div class="mt-2 flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                                @click="replyingTo = null"
                                            >
                                                Отмена
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                                                @click="addReply(comment)"
                                                :disabled="!replyContent.trim() || addingReply"
                                            >
                                                <Send class="h-3 w-3" />
                                                {{ addingReply ? 'Отправка...' : 'Ответить' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ответы -->
                            <div
                                v-if="comment.replies && comment.replies.length > 0"
                                class="mt-4 space-y-4 border-l-2 border-zinc-200 pl-4 dark:border-zinc-800"
                            >
                                <div
                                    v-for="reply in comment.replies"
                                    :key="reply.id"
                                    class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950"
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-green-100 to-emerald-100 text-green-600 dark:from-green-900/30 dark:to-emerald-900/30 dark:text-green-400">
                                            <span class="text-xs font-medium">
                                                {{ reply.author?.name?.charAt(0)?.toUpperCase() || 'U' }}
                                            </span>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <h5 class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                                                    {{ reply.author?.name || 'Аноним' }}
                                                </h5>
                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ formatTimeAgo(reply.created_at) }}
                                                </span>
                                            </div>
                                            <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                                {{ reply.content }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Пустое состояние -->
            <div
                v-if="comments.length === 0"
                class="rounded-2xl border border-dashed border-zinc-300 bg-white p-8 text-center dark:border-zinc-700 dark:bg-zinc-900"
            >
                <MessageSquare class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" />
                <h4 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                    Нет комментариев
                </h4>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    Будьте первым, кто оставит комментарий
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import {
    Heart,
    Info,
    MessageSquare,
    Pencil,
    Send,
    Trash2,
    User,
} from 'lucide-vue-next';

const props = defineProps({
    comments: {
        type: Array,
        default: () => [],
    },
    currentUser: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits([
    'add-comment',
    'edit-comment',
    'delete-comment',
    'add-reply',
    'toggle-like',
]);

const newComment = ref('');
const replyContent = ref('');
const replyingTo = ref(null);
const addingComment = ref(false);
const addingReply = ref(false);
const commentInput = ref(null);

// Добавление комментария
const addComment = async () => {
    if (!newComment.value.trim() || addingComment.value) return;
    
    addingComment.value = true;
    
    try {
        await emit('add-comment', newComment.value.trim());
        newComment.value = '';
        
        // Фокус на поле ввода
        if (commentInput.value) {
            commentInput.value.focus();
        }
    } catch (error) {
        console.error('Ошибка добавления комментария:', error);
    } finally {
        addingComment.value = false;
    }
};

// Редактирование комментария
const editComment = (comment) => {
    const newContent = prompt('Редактировать комментарий:', comment.content);
    if (newContent !== null && newContent.trim() !== comment.content) {
        emit('edit-comment', { id: comment.id, content: newContent.trim() });
    }
};

// Удаление комментария
const deleteComment = (comment) => {
    if (confirm('Удалить этот комментарий?')) {
        emit('delete-comment', comment.id);
    }
};

// Ответ на комментарий
const toggleReply = (comment) => {
    if (replyingTo.value === comment.id) {
        replyingTo.value = null;
        replyContent.value = '';
    } else {
        replyingTo.value = comment.id;
        replyContent.value = '';
    }
};

const addReply = async (comment) => {
    if (!replyContent.value.trim() || addingReply.value) return;
    
    addingReply.value = true;
    
    try {
        await emit('add-reply', {
            commentId: comment.id,
            content: replyContent.value.trim(),
        });
        
        replyContent.value = '';
        replyingTo.value = null;
    } catch (error) {
        console.error('Ошибка добавления ответа:', error);
    } finally {
        addingReply.value = false;
    }
};

// Лайк комментария
const toggleLike = (comment) => {
    emit('toggle-like', comment.id);
};

// Форматирование времени
const formatTimeAgo = (dateString) => {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    
    if (minutes < 1) return 'только что';
    if (minutes === 1) return 'минуту назад';
    if (minutes < 5) return `${minutes} минуты назад`;
    if (minutes < 60) return `${minutes} минут назад`;
    
    if (hours === 1) return 'час назад';
    if (hours < 5) return `${hours} часа назад`;
    if (hours < 24) return `${hours} часов назад`;
    
    if (days === 1) return 'вчера';
    if (days < 5) return `${days} дня назад`;
    if (days < 30) return `${days} дней назад`;
    
    const months = Math.floor(days / 30);
    if (months === 1) return 'месяц назад';
    if (months < 5) return `${months} месяца назад`;
    if (months < 12) return `${months} месяцев назад`;
    
    const years = Math.floor(months / 12);
    if (years === 1) return 'год назад';
    return `${years} лет назад`;
};
</script>

<style scoped>
.comments-section :deep(.prose) {
    @apply max-w-none;
}

.comments-section :deep(.prose p) {
    @apply my-1;
}

.comments-section :deep(.prose a) {
    @apply text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300;
}

.comments-section :deep(.prose code) {
    @apply bg-zinc-100 text-zinc-800 px-1 py-0.5 rounded text-sm dark:bg-zinc-800 dark:text-zinc-200;
}

.comments-section :deep(.prose pre) {
    @apply bg-zinc-900 text-zinc-100 p-3 rounded-lg my-2 overflow-x-auto;
}

.comments-section :deep(.prose blockquote) {
    @apply border-l-4 border-zinc-300 pl-3 italic my-2 dark:border-zinc-600;
}
</style>

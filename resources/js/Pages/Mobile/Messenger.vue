<template>
    <div class="relative flex h-[100dvh] min-h-screen flex-col bg-zinc-950 text-zinc-50">
        <template v-if="screen === 'thread'">
            <div class="relative flex min-h-0 flex-1 flex-col">
            <header class="flex shrink-0 items-center gap-3 border-b border-white/10 px-3 pb-3 pt-[calc(0.75rem+env(safe-area-inset-top,0px))]">
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-zinc-200 active:bg-white/10"
                    aria-label="Назад"
                    @click="backToChats"
                >
                    <ArrowLeft class="h-5 w-5" />
                </button>
                <AvatarBubble :label="conversationTitle(activeConversation)" :group="activeConversation?.type === 'group'" />
                <div class="min-w-0 flex-1">
                    <div class="truncate text-base font-semibold">{{ conversationTitle(activeConversation) }}</div>
                    <div class="truncate text-xs text-zinc-400">
                        {{ threadSubtitle(activeConversation) }}
                    </div>
                </div>
            </header>

            <main ref="messagesPanel" class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 py-4">
                <div v-if="threadLoading" class="py-8 text-center text-sm text-zinc-500">Загрузка сообщений…</div>
                <div
                    v-for="message in messages"
                    v-else
                    :key="message.id"
                    class="flex gap-2"
                    :class="message.user_id === currentUserId ? 'justify-end' : 'justify-start'"
                >
                    <AvatarBubble
                        v-if="message.user_id !== currentUserId"
                        :label="message.author_name ?? '?'"
                        small
                    />
                    <div
                        class="max-w-[84%] rounded-2xl px-3 py-2 text-sm shadow-sm"
                        :class="message.user_id === currentUserId ? 'rounded-br-md bg-sky-600 text-white' : 'rounded-bl-md bg-white/10 text-zinc-100'"
                    >
                        <div
                            v-if="shouldShowMessageAuthor(message)"
                            class="mb-1 text-[11px] font-semibold text-sky-300"
                        >
                            {{ message.author_name ?? 'Пользователь' }}
                        </div>
                        <p class="whitespace-pre-wrap break-words">
                            <template v-for="(segment, segmentIndex) in splitMessageSegments(message.body)" :key="`${message.id}-${segmentIndex}`">
                                <template v-if="segment.type === 'url'">
                                    <div
                                        v-if="previewForCrmUrl(segment.value)"
                                        class="mb-2 rounded-xl border border-white/15 bg-black/20 px-2 py-1.5 text-[11px]"
                                    >
                                        <div class="font-semibold uppercase tracking-wide text-sky-200">
                                            {{ previewForCrmUrl(segment.value).label }}
                                        </div>
                                        <a :href="segment.value" class="mt-1 block break-all underline opacity-90">{{ segment.value }}</a>
                                    </div>
                                    <a v-else :href="segment.value" class="break-all underline">{{ segment.value }}</a>
                                </template>
                                <span v-else>{{ segment.value }}</span>
                            </template>
                        </p>
                        <div class="mt-1 text-right text-[10px] opacity-70">{{ formatMessageTime(message.created_at) }}</div>
                    </div>
                </div>
            </main>

            <form class="flex shrink-0 gap-2 border-t border-white/10 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))]" @submit.prevent="submitMessage">
                <button
                    type="button"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/10 text-zinc-300 active:bg-white/10"
                    title="Действия с CRM"
                    @click="toggleThreadActions"
                >
                    <Plus class="h-5 w-5" />
                </button>
                <textarea
                    v-model="messageBody"
                    rows="1"
                    class="min-h-11 flex-1 resize-none rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                    :disabled="!activeConversation || sending"
                    placeholder="Сообщение"
                    @keydown.enter.exact.prevent="submitMessage"
                />
                <button
                    type="submit"
                    class="rounded-2xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    :disabled="!activeConversation || sending || messageBody.trim() === ''"
                >
                    Отпр.
                </button>
            </form>

            <div
                v-if="showThreadMenu"
                class="absolute inset-0 z-30 flex flex-col justify-end bg-black/60"
                @click.self="showThreadMenu = false"
            >
                <div class="rounded-t-3xl border border-white/10 bg-zinc-900 p-3">
                    <button
                        type="button"
                        class="flex w-full rounded-2xl px-4 py-3 text-left text-sm font-medium text-zinc-100 active:bg-white/10"
                        @click="openEntityPicker"
                    >
                        Ссылка на заказ, лид, контрагента…
                    </button>
                    <button
                        type="button"
                        class="mt-2 flex w-full rounded-2xl px-4 py-3 text-left text-sm font-medium text-zinc-100 active:bg-white/10"
                        @click="openUploadWizard"
                    >
                        Прикрепить файл к заказу
                    </button>
                </div>
            </div>

            <MobileEntityPicker
                :open="showEntityPicker"
                @close="showEntityPicker = false"
                @select="insertEntityChip"
            />
            </div>
        </template>

        <template v-else>
            <header class="shrink-0 border-b border-white/10 px-4 pb-3 pt-[calc(0.85rem+env(safe-area-inset-top,0px))]">
                <div class="flex items-center gap-2">
                    <div class="relative min-w-0 flex-1">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" />
                        <input
                            v-model="search"
                            class="w-full rounded-2xl border border-white/10 bg-white/5 py-3 pl-10 pr-4 text-base text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                            :placeholder="activeTab === 'chats' ? 'Поиск чатов и коллег' : `Поиск: ${activeTabLabel}`"
                        />
                    </div>
                    <button
                        v-if="activeTab === 'chats'"
                        type="button"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/10 text-zinc-200 active:bg-white/10"
                        title="Новая группа"
                        @click="showGroupComposer = !showGroupComposer"
                    >
                        <Users class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/10 text-zinc-200 active:bg-white/10"
                        title="Обновить"
                        @click="refreshActiveTab"
                    >
                        <RefreshCw class="h-4 w-4" />
                    </button>
                </div>
            </header>

            <main
                ref="listPanel"
                class="min-h-0 flex-1 overflow-y-auto pb-2"
                @touchstart.passive="onTouchStart"
                @touchmove.passive="onTouchMove"
                @touchend="onTouchEnd"
            >
                <div
                    v-if="pullReady || pullRefreshing"
                    class="sticky top-0 z-10 bg-zinc-950/90 py-2 text-center text-[11px] font-medium backdrop-blur-sm"
                    :class="pullRefreshing ? 'text-sky-300' : 'text-zinc-500'"
                >
                    {{ pullRefreshing ? 'Обновление…' : 'Отпустите для обновления' }}
                </div>
                <section v-if="activeTab === 'chats'" class="min-h-full">
                    <form
                        v-if="showGroupComposer"
                        class="border-b border-white/10 bg-white/[0.03] p-4"
                        @submit.prevent="submitGroup"
                    >
                        <div class="text-sm font-semibold text-zinc-100">Новая группа</div>
                        <input
                            v-model="groupTitle"
                            class="mt-3 w-full rounded-2xl border border-white/10 bg-zinc-900 px-4 py-3 text-sm text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                            placeholder="Название группы"
                        />
                        <div class="mt-3 max-h-44 space-y-1 overflow-y-auto">
                            <label
                                v-for="user in groupCandidates"
                                :key="`group-${user.id}`"
                                class="flex items-center gap-3 rounded-2xl px-2 py-2 active:bg-white/10"
                            >
                                <input v-model="groupMemberIds" type="checkbox" class="rounded border-zinc-600 bg-zinc-900" :value="user.id" />
                                <AvatarBubble :label="user.name" small />
                                <span class="min-w-0 flex-1 truncate text-sm">{{ user.name }}</span>
                            </label>
                        </div>
                        <p v-if="messengerError" class="mt-2 text-xs text-rose-300">{{ messengerError }}</p>
                        <button
                            type="submit"
                            class="mt-3 w-full rounded-2xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            :disabled="groupCreating || groupTitle.trim() === '' || groupMemberIds.length === 0"
                        >
                            {{ groupCreating ? 'Создание…' : 'Создать группу' }}
                        </button>
                    </form>

                    <section v-if="filteredColleagues.length" class="border-b border-white/10 py-2">
                        <div class="px-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Коллеги</div>
                        <div
                            v-for="user in filteredColleagues"
                            :key="`user-${user.id}`"
                            class="flex w-full items-center gap-3 px-4 py-3 active:bg-white/10"
                        >
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 items-center gap-3 text-left"
                                @click="openUserThread(user)"
                            >
                                <AvatarBubble :label="user.name" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-zinc-50">{{ user.name }}</div>
                                    <div class="truncate text-xs text-zinc-500">{{ contactSubtitle(user) }}</div>
                                </div>
                            </button>
                            <span
                                v-if="colleagueUnreadCount(user) > 0"
                                class="flex h-5 min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-sky-600 px-1 text-[10px] font-bold text-white"
                            >
                                {{ colleagueUnreadCount(user) > 99 ? '99+' : colleagueUnreadCount(user) }}
                            </span>
                            <a
                                v-if="normalizedPhone(user.phone)"
                                :href="`tel:${normalizedPhone(user.phone)}`"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/10 text-sky-200 active:bg-white/10"
                                aria-label="Позвонить"
                            >
                                <Phone class="h-4 w-4" />
                            </a>
                        </div>
                    </section>

                    <section>
                        <div v-if="conversationsLoading" class="p-6 text-center text-sm text-zinc-500">Загрузка чатов…</div>
                        <button
                            v-for="conversation in filteredConversations"
                            v-else
                            :key="`conversation-${conversation.id}`"
                            type="button"
                            class="flex w-full items-center gap-3 border-b border-white/5 px-4 py-3 text-left active:bg-white/10"
                            @click="openConversationThread(conversation)"
                        >
                            <AvatarBubble :label="conversationTitle(conversation)" :group="conversation.type === 'group'" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-sm font-semibold text-zinc-50">{{ conversationTitle(conversation) }}</span>
                                    <span class="ml-auto shrink-0 text-[10px] text-zinc-500">{{ formatShortTime(conversation.updated_at) }}</span>
                                </div>
                                <div class="mt-1 flex items-center gap-2">
                                    <p class="min-w-0 flex-1 truncate text-xs text-zinc-400">
                                        {{ conversationPreview(conversation) }}
                                    </p>
                                    <span
                                        v-if="conversation.unread_count > 0"
                                        class="flex h-5 min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-sky-600 px-1 text-[10px] font-bold text-white"
                                    >
                                        {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
                                    </span>
                                </div>
                            </div>
                        </button>

                        <div v-if="!conversationsLoading && filteredConversations.length === 0" class="p-8 text-center text-sm text-zinc-500">
                            {{ search.trim() ? 'Ничего не найдено.' : 'Диалогов пока нет. Найдите коллегу сверху.' }}
                        </div>
                    </section>
                </section>

                <section v-else-if="activeTab === 'documents'" class="space-y-4 p-4">
                    <button
                        type="button"
                        class="w-full rounded-3xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white active:bg-sky-500"
                        @click="openUploadWizard"
                    >
                        Добавить документ с телефона
                    </button>
                    <div v-if="documentsLoading" class="py-8 text-center text-sm text-zinc-500">Загрузка документов…</div>
                    <template v-else>
                        <div v-if="attentionDocuments.length" class="space-y-2">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-300">Требуют внимания</div>
                            <div
                                v-for="item in attentionDocuments"
                                :key="`attention-${item.order_id}`"
                                :id="`mobile-attention-order-${item.order_id}`"
                                class="rounded-3xl border border-amber-500/20 bg-amber-500/10 active:bg-amber-500/15"
                                :class="highlightCardClass('attention-order', item.order_id)"
                            >
                                <div class="flex items-start gap-2 p-4">
                                    <a :href="item.url" class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold text-zinc-50">{{ item.order_number }}</div>
                                        <div class="mt-1 text-xs text-zinc-400">{{ item.customer_name || 'Заказ' }}</div>
                                        <div class="mt-2 text-xs text-amber-100">
                                            {{ item.pending_count }} незакрытых слотов
                                            <span v-if="item.pending_labels?.length"> · {{ item.pending_labels.join(', ') }}</span>
                                        </div>
                                    </a>
                                    <button
                                        type="button"
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/10 text-sky-200 active:bg-white/10"
                                        title="Отправить в чат"
                                        @click="beginShareToChat({ url: item.url, label: item.order_number })"
                                    >
                                        <Share2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Последние документы</div>
                            <div
                                v-for="doc in filteredRecentDocuments"
                                :key="`doc-${doc.id}`"
                                :id="`mobile-document-${doc.id}`"
                                class="rounded-3xl border border-white/10 bg-white/[0.04]"
                                :class="highlightCardClass('document', doc.id)"
                            >
                                <div class="flex items-start gap-2 p-4 active:bg-white/10">
                                    <a :href="doc.url" class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold text-zinc-50">{{ doc.label }}</div>
                                        <div class="mt-1 truncate text-xs text-zinc-500">{{ doc.url }}</div>
                                    </a>
                                    <button
                                        type="button"
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/10 text-sky-200 active:bg-white/10"
                                        title="Отправить в чат"
                                        @click="beginShareToChat({ url: doc.url, label: doc.label })"
                                    >
                                        <Share2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div v-if="filteredRecentDocuments.length === 0" class="rounded-3xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-zinc-500">
                                {{ search.trim() ? 'Ничего не найдено.' : 'Документов пока нет.' }}
                            </div>
                        </div>
                    </template>
                </section>

                <section v-else-if="activeTab === 'orders'" class="space-y-3 p-4">
                    <div v-if="ordersLoading" class="py-8 text-center text-sm text-zinc-500">Загрузка заказов…</div>
                    <template v-else>
                        <div
                            v-for="order in filteredOrders"
                            :key="`order-${order.id}`"
                            :id="`mobile-order-${order.id}`"
                            class="rounded-3xl border border-white/10 bg-white/[0.04]"
                            :class="highlightCardClass('order', order.id)"
                        >
                            <div class="flex items-start gap-2 p-4 active:bg-white/10">
                                <a :href="order.url" class="min-w-0 flex-1">
                                    <div class="flex items-start gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-semibold text-zinc-50">{{ order.order_number }}</div>
                                            <div class="mt-1 text-xs text-zinc-400">{{ order.customer_name || 'Заказчик не указан' }}</div>
                                            <div v-if="order.carrier_name" class="mt-1 text-xs text-zinc-500">Перевозчик: {{ order.carrier_name }}</div>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-white/10 px-2 py-1 text-[10px] uppercase tracking-wide text-zinc-300">
                                            {{ order.status || '—' }}
                                        </span>
                                    </div>
                                    <div v-if="order.loading_date || order.unloading_date" class="mt-3 text-xs text-zinc-500">
                                        {{ formatOrderRoute(order) }}
                                    </div>
                                </a>
                                <button
                                    type="button"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/10 text-sky-200 active:bg-white/10"
                                    title="Отправить в чат"
                                    @click="beginShareToChat({ url: order.url, label: order.order_number })"
                                >
                                    <Share2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div v-if="filteredOrders.length === 0" class="rounded-3xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-zinc-500">
                            {{ search.trim() ? 'Ничего не найдено.' : 'Активных заказов пока нет.' }}
                        </div>
                    </template>
                </section>

                <section v-else-if="activeTab === 'tasks'" class="space-y-3 p-4">
                    <div v-if="tasksLoading" class="py-8 text-center text-sm text-zinc-500">Загрузка задач…</div>
                    <template v-else>
                        <div
                            v-for="task in filteredTasks"
                            :key="`task-${task.id}`"
                            :id="`mobile-task-${task.id}`"
                            class="rounded-3xl border p-4 active:opacity-90"
                            :class="[
                                task.is_overdue || task.sla_breached ? 'border-rose-500/30 bg-rose-500/10' : 'border-white/10 bg-white/[0.04]',
                                highlightCardClass('task', task.id),
                            ]"
                        >
                            <div class="flex items-start gap-2">
                                <a :href="task.url" class="min-w-0 flex-1">
                                    <div class="flex items-start gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-semibold text-zinc-50">{{ task.number }} · {{ task.title }}</div>
                                            <div class="mt-1 text-xs text-zinc-400">{{ task.status_label }}</div>
                                            <div v-if="task.lead_number || task.contractor_name" class="mt-1 text-xs text-zinc-500">
                                                <span v-if="task.lead_number">Лид {{ task.lead_number }}</span>
                                                <span v-if="task.contractor_name">{{ task.lead_number ? ' · ' : '' }}{{ task.contractor_name }}</span>
                                            </div>
                                        </div>
                                        <span
                                            v-if="task.is_overdue || task.sla_breached"
                                            class="shrink-0 rounded-full bg-rose-500/20 px-2 py-1 text-[10px] font-semibold uppercase text-rose-200"
                                        >
                                            Просрочена
                                        </span>
                                    </div>
                                    <div v-if="task.due_at" class="mt-3 text-xs text-zinc-500">Срок: {{ formatShortDate(task.due_at) }}</div>
                                </a>
                                <button
                                    type="button"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/10 text-sky-200 active:bg-white/10"
                                    title="Отправить в чат"
                                    @click="beginShareToChat({ url: task.url, label: `${task.number} · ${task.title}` })"
                                >
                                    <Share2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div v-if="filteredTasks.length === 0" class="rounded-3xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-zinc-500">
                            {{ search.trim() ? 'Ничего не найдено.' : 'Открытых задач нет.' }}
                        </div>
                    </template>
                </section>
            </main>

            <nav class="grid shrink-0 grid-cols-4 border-t border-white/10 bg-zinc-950/95 px-2 pb-[calc(0.5rem+env(safe-area-inset-bottom,0px))] pt-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="relative flex flex-col items-center gap-1 rounded-2xl px-2 py-2 text-[11px]"
                    :class="activeTab === tab.key ? 'bg-white/10 text-sky-200' : 'text-zinc-500 active:bg-white/5'"
                    @click="selectTab(tab.key)"
                >
                    <component :is="tab.icon" class="h-5 w-5" />
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="tab.key === 'chats' && unreadCount > 0"
                        class="absolute right-4 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-sky-600 px-1 text-[9px] font-bold text-white"
                    >
                        {{ unreadCount > 99 ? '99+' : unreadCount }}
                    </span>
                    <span
                        v-if="tab.key === 'tasks' && overdueTaskCount > 0"
                        class="absolute right-4 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-bold text-white"
                    >
                        {{ overdueTaskCount > 99 ? '99+' : overdueTaskCount }}
                    </span>
                </button>
            </nav>

        </template>

        <MobileShareToChatPicker
            :open="showSharePicker"
            :share-label="pendingShare?.label ?? ''"
            :conversations="conversations"
            :colleagues="colleagues"
            :conversation-title="conversationTitle"
            :conversation-preview="conversationPreview"
            @close="closeSharePicker"
            @pick-conversation="shareToConversation"
            @pick-colleague="shareToColleague"
        />

        <MobileDocumentUploadWizard
            :open="showUploadWizard"
            @close="showUploadWizard = false"
            @uploaded="handleDocumentUploaded"
        />

        <MobileEntityPicker
            v-if="screen !== 'thread'"
            :open="showEntityPicker"
            @close="showEntityPicker = false"
            @select="insertEntityChip"
        />
    </div>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ArrowLeft, CheckSquare, FileText, MessageCircle, Package, Phone, Plus, RefreshCw, Search, Share2, Users } from 'lucide-vue-next';
import { useMessenger } from '@/composables/useMessenger.js';
import { useMessengerPolling } from '@/composables/useMessengerPolling.js';
import { useMobileShell } from '@/composables/useMobileShell.js';
import { usePullToRefresh } from '@/composables/usePullToRefresh.js';
import MobileDocumentUploadWizard from '@/Components/Mobile/MobileDocumentUploadWizard.vue';
import MobileEntityPicker from '@/Components/Mobile/MobileEntityPicker.vue';
import MobileShareToChatPicker from '@/Components/Mobile/MobileShareToChatPicker.vue';
import { previewForCrmUrl, splitMessageSegments } from '@/support/mobileMessageLinks.js';
import { buildDirectUnreadByUserId, formatConversationPreview } from '@/support/messengerConversationText.js';
import { registerMobilePushIfAvailable } from '@/support/mobilePush.js';

const AvatarBubble = defineComponent({
    props: {
        label: { type: String, default: '' },
        group: { type: Boolean, default: false },
        small: { type: Boolean, default: false },
    },
    setup(props) {
        return () => h('div', {
            class: [
                'flex shrink-0 items-center justify-center rounded-full text-sm font-bold text-sky-100',
                props.small ? 'h-8 w-8 text-xs' : 'h-11 w-11',
                props.group ? 'bg-violet-600/40' : 'bg-sky-600/35',
            ],
        }, props.group ? 'Г' : String(props.label || 'Ч').slice(0, 1).toUpperCase());
    },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const messagesPanel = ref(null);
const listPanel = ref(null);
const screen = ref('list');
const activeTab = ref('chats');
const search = ref('');
const messageBody = ref('');
const showGroupComposer = ref(false);
const groupTitle = ref('');
const groupMemberIds = ref([]);
const groupCreating = ref(false);
const showThreadMenu = ref(false);
const showEntityPicker = ref(false);
const showUploadWizard = ref(false);
const showSharePicker = ref(false);
const pendingShare = ref(null);
const highlightTarget = ref(null);

const { pullReady, refreshing: pullRefreshing, onTouchStart, onTouchMove, onTouchEnd } = usePullToRefresh(
    () => listPanel.value,
    () => refreshActiveTab(),
);

const tabs = [
    { key: 'chats', label: 'Чаты', icon: MessageCircle },
    { key: 'documents', label: 'Документы', icon: FileText },
    { key: 'orders', label: 'Заказы', icon: Package },
    { key: 'tasks', label: 'Задачи', icon: CheckSquare },
];

const messenger = useMessenger({ scrollTarget: messagesPanel });

useMessengerPolling(messenger);

const {
    conversations,
    colleagues,
    messages,
    activeConversation,
    unreadCount,
    conversationsLoading,
    threadLoading,
    sending,
    error: messengerError,
    loadConversations,
    loadColleagues,
    reloadAll,
    selectConversation,
    openDirect,
    createGroup,
    sendMessage,
    clearActiveConversation,
} = messenger;

const {
    tasks,
    orders,
    recentDocuments,
    attentionDocuments,
    overdueTaskCount,
    tasksLoading,
    ordersLoading,
    documentsLoading,
    shellError,
    loadTab,
    loadDocuments,
    loadOrders,
} = useMobileShell();

function beginShareToChat(payload) {
    pendingShare.value = payload;
    showSharePicker.value = true;
}

function closeSharePicker() {
    showSharePicker.value = false;
    pendingShare.value = null;
}

async function shareToConversation(conversation) {
    const share = pendingShare.value;
    closeSharePicker();
    await openConversationThread(conversation);

    if (share?.url) {
        insertUrlIntoComposer(share.url);
    }
}

async function shareToColleague(user) {
    const share = pendingShare.value;
    closeSharePicker();
    await openUserThread(user);

    if (share?.url) {
        insertUrlIntoComposer(share.url);
    }
}

function highlightCardClass(type, id) {
    if (!highlightTarget.value) {
        return '';
    }

    return highlightTarget.value.type === type && Number(highlightTarget.value.id) === Number(id)
        ? 'ring-2 ring-sky-400 ring-offset-2 ring-offset-zinc-950'
        : '';
}

let highlightTimer = null;

async function applyShellHighlight(detail) {
    const orderId = Number(detail.orderId ?? 0);

    if (orderId <= 0) {
        return;
    }

    const highlightType = detail.highlightType ?? (detail.tab === 'documents' ? 'attention-order' : 'order');
    highlightTarget.value = { type: highlightType, id: orderId };

    if (detail.tab === 'documents') {
        await loadDocuments(search.value);
    } else if (detail.tab === 'orders') {
        await loadOrders(search.value);
    }

    await nextTick();

    const elementId = highlightType === 'attention-order'
        ? `mobile-attention-order-${orderId}`
        : `mobile-order-${orderId}`;

    document.getElementById(elementId)?.scrollIntoView({ behavior: 'smooth', block: 'center' });

    clearTimeout(highlightTimer);
    highlightTimer = setTimeout(() => {
        highlightTarget.value = null;
    }, 4500);
}

function insertUrlIntoComposer(url) {
    const current = messageBody.value;
    const separator = current && !current.endsWith('\n') && !current.endsWith(' ') ? ' ' : '';

    messageBody.value = `${current}${separator}${url} `;
}

function insertEntityChip(entity) {
    if (!entity?.url) {
        return;
    }

    insertUrlIntoComposer(entity.url);
    showEntityPicker.value = false;
    showThreadMenu.value = false;
}

function openEntityPicker() {
    showThreadMenu.value = false;
    showEntityPicker.value = true;
}

function openUploadWizard() {
    showThreadMenu.value = false;
    showUploadWizard.value = true;
}

function toggleThreadActions() {
    showThreadMenu.value = !showThreadMenu.value;
}

async function handleDocumentUploaded(document) {
    if (document?.url && screen.value === 'thread') {
        insertUrlIntoComposer(document.url);
    }

    if (activeTab.value === 'documents' || screen.value !== 'thread') {
        await loadDocuments(search.value);
    }
}

const activeTabLabel = computed(() => tabs.find((tab) => tab.key === activeTab.value)?.label ?? 'Раздел');

const directUnreadByUserId = computed(() => buildDirectUnreadByUserId(conversations.value));

const filteredConversations = computed(() => {
    const colleagueIds = new Set(filteredColleagues.value.map((user) => Number(user.id)));
    const needle = search.value.trim().toLowerCase();

    return conversations.value
        .filter((conversation) => {
            if (conversation.type === 'direct') {
                const otherUserId = Number(conversation.other_user?.id ?? 0);

                if (otherUserId > 0 && colleagueIds.has(otherUserId)) {
                    return false;
                }
            }

            return true;
        })
        .filter((conversation) => {
            if (needle === '') {
                return true;
            }

            return conversationTitle(conversation).toLowerCase().includes(needle)
                || conversationPreview(conversation).toLowerCase().includes(needle);
        });
});

const filteredColleagues = computed(() => {
    const needle = search.value.trim().toLowerCase();
    let list = colleagues.value;

    if (needle !== '') {
        return list
            .filter((user) => `${user.name ?? ''} ${user.phone ?? ''} ${user.email ?? ''}`.toLowerCase().includes(needle))
            .slice(0, 12);
    }

    return [...list]
        .sort((left, right) => {
            const unreadLeft = directUnreadByUserId.value.get(Number(left.id)) ?? 0;
            const unreadRight = directUnreadByUserId.value.get(Number(right.id)) ?? 0;

            if (unreadLeft !== unreadRight) {
                return unreadRight - unreadLeft;
            }

            return String(left.name ?? '').localeCompare(String(right.name ?? ''), 'ru');
        })
        .slice(0, 8);
});

function colleagueUnreadCount(user) {
    return directUnreadByUserId.value.get(Number(user?.id)) ?? 0;
}

const groupCandidates = computed(() => colleagues.value.slice(0, 50));

const filteredTasks = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (needle === '') {
        return tasks.value;
    }

    return tasks.value.filter((task) =>
        `${task.number ?? ''} ${task.title ?? ''} ${task.status_label ?? ''}`.toLowerCase().includes(needle),
    );
});

const filteredOrders = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (needle === '') {
        return orders.value;
    }

    return orders.value.filter((order) =>
        `${order.order_number ?? ''} ${order.customer_name ?? ''} ${order.carrier_name ?? ''}`.toLowerCase().includes(needle),
    );
});

const filteredRecentDocuments = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (needle === '') {
        return recentDocuments.value;
    }

    return recentDocuments.value.filter((doc) =>
        `${doc.label ?? ''} ${doc.url ?? ''}`.toLowerCase().includes(needle),
    );
});

function conversationTitle(conversation) {
    if (!conversation) {
        return '';
    }

    if (conversation.type === 'group') {
        return conversation.title ?? 'Группа';
    }

    return conversation.other_user?.name ?? 'Личный чат';
}

function conversationPreview(conversation) {
    return formatConversationPreview(conversation, currentUserId.value);
}

function threadSubtitle(conversation) {
    if (!conversation) {
        return '';
    }

    if (conversation.type === 'group') {
        return `${conversation.member_count} участников`;
    }

    return 'Личное сообщение';
}

function shouldShowMessageAuthor(message) {
    return Number(message?.user_id) !== Number(currentUserId.value);
}

function formatShortTime(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function formatMessageTime(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function formatShortDate(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
    }).format(date);
}

function formatOrderRoute(order) {
    const loading = order.loading_date ? formatShortDate(order.loading_date) : null;
    const unloading = order.unloading_date ? formatShortDate(order.unloading_date) : null;

    if (loading && unloading) {
        return `${loading} → ${unloading}`;
    }

    return loading || unloading || '';
}

async function openConversationThread(conversation) {
    await selectConversation(conversation);
    screen.value = 'thread';
}

async function openUserThread(user) {
    await openDirect(user);
    screen.value = 'thread';
}

function backToChats() {
    screen.value = 'list';
    activeTab.value = 'chats';
    messageBody.value = '';
    clearActiveConversation();
    reloadAll();
}

function selectTab(tab) {
    activeTab.value = tab;
    search.value = '';
    showGroupComposer.value = false;

    if (tab !== 'chats') {
        loadTab(tab);
    }
}

async function refreshActiveTab() {
    if (activeTab.value === 'chats') {
        await Promise.all([reloadAll(), loadColleagues()]);

        return;
    }

    await loadTab(activeTab.value, search.value);
}

async function submitMessage() {
    await sendMessage(messageBody.value);
    messageBody.value = '';
}

async function submitGroup() {
    groupCreating.value = true;

    try {
        await createGroup(groupTitle.value.trim(), groupMemberIds.value);
        groupTitle.value = '';
        groupMemberIds.value = [];
        showGroupComposer.value = false;
        screen.value = 'thread';
    } catch {
        return;
    } finally {
        groupCreating.value = false;
    }
}

function normalizedPhone(phone) {
    const text = String(phone ?? '').trim();
    if (text === '') {
        return '';
    }

    return text.replace(/[^\d+]/g, '');
}

function contactSubtitle(user) {
    return user.phone || user.email || 'Открыть личный чат';
}

async function openConversationById(conversationId) {
    if (!conversationId) {
        return;
    }

    let conversation = conversations.value.find((item) => Number(item.id) === Number(conversationId));

    if (!conversation) {
        await loadConversations();
        conversation = conversations.value.find((item) => Number(item.id) === Number(conversationId));
    }

    if (conversation) {
        await openConversationThread(conversation);
    }
}

async function handleMobileNavigate(event) {
    const detail = event.detail ?? {};

    if (detail.tab) {
        selectTab(detail.tab);
        screen.value = 'list';
    }

    if (detail.conversationId) {
        await openConversationById(Number(detail.conversationId));

        return;
    }

    if (detail.orderId) {
        await applyShellHighlight(detail);

        return;
    }

    if (typeof detail.actionUrl === 'string' && detail.actionUrl !== '') {
        const actionUrl = detail.actionUrl;
        const url = actionUrl.startsWith('http')
            ? actionUrl
            : `${window.location.origin}${actionUrl.startsWith('/') ? actionUrl : `/${actionUrl}`}`;

        window.location.href = url;
    }
}

function handlePushReceived() {
    if (screen.value === 'thread') {
        return;
    }

    if (activeTab.value === 'chats') {
        reloadAll();
        loadColleagues();

        return;
    }

    loadTab(activeTab.value, search.value);
}

let shellSearchTimer = null;

watch([activeTab, search], ([tab, needle]) => {
    if (tab === 'chats') {
        return;
    }

    clearTimeout(shellSearchTimer);
    shellSearchTimer = setTimeout(() => {
        loadTab(tab, needle);
    }, 300);
});

onMounted(() => {
    reloadAll();
    loadColleagues();
    registerMobilePushIfAvailable({ enabled: page.props.mobile_push_enabled === true });
    window.addEventListener('crm-mobile-navigate', handleMobileNavigate);
    window.addEventListener('crm-mobile-push-received', handlePushReceived);
});

onUnmounted(() => {
    window.removeEventListener('crm-mobile-navigate', handleMobileNavigate);
    window.removeEventListener('crm-mobile-push-received', handlePushReceived);
    clearTimeout(highlightTimer);
});
</script>

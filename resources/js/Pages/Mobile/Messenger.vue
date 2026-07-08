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
                <div
                    v-if="activeConversation?.channel === 'counterparty' && counterpartyOrders.length"
                    class="sticky top-0 z-10 -mx-1 mb-2 flex gap-2 overflow-x-auto pb-1"
                >
                    <button
                        v-for="order in counterpartyOrders"
                        :key="`cp-order-${order.id}`"
                        type="button"
                        class="shrink-0 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] text-zinc-200 active:bg-white/10"
                        @click="messageBody = `${messageBody.trim() ? `${messageBody.trim()}\n` : ''}Заказ ${order.order_number}`"
                    >
                        {{ order.order_number || `#${order.id}` }}
                    </button>
                </div>
                <div v-if="threadLoading" class="py-8 text-center text-sm text-zinc-500">Загрузка сообщений…</div>
                <template v-for="message in messages" v-else :key="message.id">
                    <div
                        v-if="isSystemMessage(message)"
                        class="py-1 text-center text-[11px] text-zinc-500"
                    >
                        {{ message.body }}
                    </div>
                    <div
                        v-else
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
                                    <MobileCrmLinkPreview
                                        v-if="previewForCrmUrl(segment.value)"
                                        :url="segment.value"
                                        :preview="previewForCrmUrl(segment.value)"
                                        class="mb-2"
                                    />
                                    <a v-else :href="segment.value" class="break-all underline">{{ segment.value }}</a>
                                </template>
                                <span v-else>{{ segment.value }}</span>
                            </template>
                        </p>
                        <div class="mt-1 text-right text-[10px] opacity-70">{{ formatMessageTime(message.created_at) }}</div>
                    </div>
                    </div>
                </template>
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
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-sky-600 text-white disabled:opacity-50"
                    aria-label="Отправить"
                    title="Отправить"
                    :disabled="!activeConversation || sending || messageBody.trim() === ''"
                >
                    <Send class="h-5 w-5" />
                </button>
            </form>

            <div
                v-if="showThreadMenu"
                class="absolute inset-0 z-30 flex flex-col justify-end bg-black/60"
                @click.self="showThreadMenu = false"
            >
                <div class="rounded-t-3xl border border-white/10 bg-zinc-900 p-3">
                    <button
                        v-if="!isExternalUser"
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
                    <button
                        v-if="!isExternalUser"
                        type="button"
                        class="mt-2 flex w-full flex-col rounded-2xl px-4 py-3 text-left text-sm font-medium text-zinc-100 active:bg-white/10"
                        @click="insertTransportRequestLink"
                    >
                        <span>Ссылка на заявку на перевозку</span>
                        <span class="mt-1 text-xs font-normal text-zinc-400">Контрагент заполнит форму, в CRM появится лид</span>
                    </button>
                    <a
                        v-if="threadColleaguePhone"
                        :href="`tel:${threadColleaguePhone}`"
                        class="mt-2 flex w-full rounded-2xl px-4 py-3 text-left text-sm font-medium text-zinc-100 active:bg-white/10"
                        @click="showThreadMenu = false"
                    >
                        Позвонить
                    </a>
                </div>
            </div>

            <MobileEntityPicker
                v-if="!isExternalUser"
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
                            class="w-full rounded-2xl border border-white/10 bg-white/5 py-3 pl-10 pr-11 text-base text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                            :placeholder="activeTab === 'chats' ? 'Поиск чатов и коллег' : `Поиск: ${activeTabLabel}`"
                        />
                        <button
                            v-if="search.trim()"
                            type="button"
                            class="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-zinc-400 active:bg-white/10 active:text-zinc-100"
                            aria-label="Очистить поиск"
                            @click="search = ''"
                        >
                            <X class="h-4 w-4" />
                        </button>
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
                <section v-if="activeTab !== 'chats' && search.trim()" class="space-y-2 px-4 pt-2">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Поиск по CRM</div>
                    <div v-if="unifiedSearchLoading" class="py-4 text-center text-sm text-zinc-500">Ищем…</div>
                    <button
                        v-for="entity in unifiedSearchResults"
                        v-else
                        :key="`search-${entity.kind}-${entity.id}`"
                        type="button"
                        class="flex w-full flex-col rounded-3xl border border-white/10 bg-white/[0.04] px-4 py-3 text-left active:bg-white/10"
                        @click="openEntityChipDetail(entity)"
                    >
                        <span class="text-[10px] uppercase tracking-wide text-sky-200">{{ entityKindLabel(entity.kind) }}</span>
                        <span class="mt-1 text-sm font-semibold text-zinc-50">{{ entity.label }}</span>
                        <span v-if="entity.subtitle" class="mt-0.5 truncate text-xs text-zinc-500">{{ entity.subtitle }}</span>
                    </button>
                    <div v-if="!unifiedSearchLoading && unifiedSearchResults.length === 0" class="rounded-3xl border border-dashed border-white/10 px-4 py-6 text-center text-sm text-zinc-500">
                        По CRM ничего не найдено.
                    </div>
                </section>

                <section v-if="activeTab !== 'chats' && !search.trim() && mobileRecents.length" class="space-y-2 px-4 pt-2">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Недавно открывали</div>
                    <button
                        v-for="item in mobileRecents"
                        :key="`recent-${item.kind}-${item.id}`"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-3xl border border-white/10 bg-white/[0.04] px-4 py-3 text-left active:bg-white/10"
                        @click="openRecentDetail(item)"
                    >
                        <span class="text-[10px] uppercase tracking-wide text-sky-200">{{ entityKindLabel(item.kind) }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-50">{{ item.label }}</span>
                    </button>
                </section>

                <section v-if="activeTab === 'chats'" class="min-h-full">
                    <div
                        v-if="chatChannelOptions.length > 1"
                        class="flex gap-2 border-b border-white/10 px-4 py-2"
                    >
                        <button
                            v-for="option in chatChannelOptions"
                            :key="option.key"
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="chatChannelFilter === option.key ? 'bg-sky-600 text-white' : 'bg-white/5 text-zinc-400'"
                            @click="chatChannelFilter = option.key"
                        >
                            {{ option.label }}
                        </button>
                    </div>

                    <form
                        v-if="showGroupComposer && !isExternalUser"
                        class="flex max-h-[calc(100dvh-10.5rem)] flex-col border-b border-white/10 bg-white/[0.03] p-4"
                        @submit.prevent="submitGroup"
                    >
                        <div class="shrink-0 text-sm font-semibold text-zinc-100">Новая группа</div>
                        <input
                            v-model="groupTitle"
                            class="mt-3 w-full shrink-0 rounded-2xl border border-white/10 bg-zinc-900 px-4 py-3 text-sm text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                            placeholder="Название группы"
                        />
                        <div class="mt-3 min-h-0 flex-1 space-y-1 overflow-y-auto pb-2">
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
                        <p v-if="messengerError" class="mt-2 shrink-0 text-xs text-rose-300">{{ messengerError }}</p>
                        <button
                            type="submit"
                            class="mt-3 w-full shrink-0 rounded-2xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            :disabled="groupCreating || groupTitle.trim() === '' || groupMemberIds.length === 0"
                        >
                            {{ groupCreating ? 'Создание…' : 'Создать группу' }}
                        </button>
                    </form>

                    <section v-if="!isExternalUser && chatChannelFilter !== 'internal' && counterpartyContacts.length" class="border-b border-white/10 py-2">
                        <div class="px-4 pb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Контрагенты</div>
                        <button
                            v-for="contact in counterpartyContacts"
                            :key="`cp-${contact.conversation_id}`"
                            type="button"
                            class="flex w-full items-center gap-3 px-4 py-3 text-left active:bg-white/10"
                            @click="openConversationThread({ id: contact.conversation_id, channel: 'counterparty' })"
                        >
                            <AvatarBubble :label="contact.contractor_name ?? contact.name" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-semibold text-zinc-50">{{ contact.contractor_name ?? contact.name }}</div>
                                <div class="truncate text-xs text-zinc-500">{{ contact.name }}</div>
                            </div>
                        </button>
                    </section>

                    <section v-if="filteredColleagues.length && chatChannelFilter !== 'counterparty'" class="border-b border-white/10 py-2">
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
                        class="flex w-full items-center justify-center gap-2 rounded-3xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white active:bg-sky-500"
                        @click="openUploadWizard"
                    >
                        <Upload class="h-4 w-4" />
                        Добавить документ с телефона
                    </button>

                    <template v-if="isExternalUser">
                        <div v-if="documentsLoading" class="py-8 text-center text-sm text-zinc-500">Загрузка документов…</div>
                        <template v-else>
                            <div v-if="attentionDocuments.length" class="space-y-2">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-300">Требуют внимания</div>
                                <MobileShellEntityCard
                                    v-for="item in attentionDocuments"
                                    :key="`attention-${item.order_id}`"
                                    :element-id="`mobile-attention-order-${item.order_id}`"
                                    card-class="border-amber-500/20 bg-amber-500/10"
                                    :highlight-class="highlightCardClass('attention-order', item.order_id)"
                                    @open="openAttentionDetail(item)"
                                    @share="beginShareToChat({ url: item.url, label: item.order_number })"
                                >
                                    <div class="text-sm font-semibold text-zinc-50">{{ item.order_number }}</div>
                                    <div class="mt-1 text-xs text-zinc-400">{{ item.customer_name || 'Заказ' }}</div>
                                    <div class="mt-2 text-xs text-amber-100">
                                        {{ item.pending_count }} незакрытых слотов
                                        <span v-if="item.pending_labels?.length"> · {{ item.pending_labels.join(', ') }}</span>
                                    </div>
                                </MobileShellEntityCard>
                            </div>

                            <div class="space-y-2">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Последние документы</div>
                                <MobileShellEntityCard
                                    v-for="doc in filteredRecentDocuments"
                                    :key="`doc-${doc.id}`"
                                    :element-id="`mobile-document-${doc.id}`"
                                    :highlight-class="highlightCardClass('document', doc.id)"
                                    @open="openDocumentDetail(doc)"
                                    @share="beginShareToChat({ url: doc.url, label: doc.label })"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] uppercase tracking-wide text-sky-200">Документ</span>
                                    </div>
                                    <div class="mt-1 text-sm font-semibold text-zinc-50">{{ doc.label }}</div>
                                    <div v-if="doc.order_id" class="mt-1 text-xs text-zinc-500">Заказ #{{ doc.order_id }}</div>
                                </MobileShellEntityCard>
                                <div v-if="filteredRecentDocuments.length === 0" class="rounded-3xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-zinc-500">
                                    {{ search.trim() ? 'Ничего не найдено.' : 'Документов пока нет.' }}
                                </div>
                            </div>
                        </template>
                    </template>

                    <template v-else>
                        <div
                            v-if="documentsDrillLevel !== 'contractors'"
                            class="flex items-center gap-2"
                        >
                            <button
                                type="button"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-zinc-200 active:bg-white/10"
                                aria-label="Назад"
                                @click="documentsDrillBack"
                            >
                                <ArrowLeft class="h-4 w-4" />
                            </button>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-semibold text-zinc-50">{{ documentsDrillTitle }}</div>
                                <div v-if="documentsDrillSubtitle" class="truncate text-xs text-zinc-500">{{ documentsDrillSubtitle }}</div>
                            </div>
                        </div>

                        <div v-if="documentsPanelLoading" class="py-8 text-center text-sm text-zinc-500">Загрузка документов…</div>

                        <template v-else-if="documentsDrillLevel === 'contractors'">
                            <div v-if="attentionDocuments.length" class="space-y-2">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-300">Требуют внимания</div>
                                <MobileShellEntityCard
                                    v-for="item in attentionDocuments"
                                    :key="`attention-${item.order_id}`"
                                    :element-id="`mobile-attention-order-${item.order_id}`"
                                    card-class="border-amber-500/20 bg-amber-500/10"
                                    :highlight-class="highlightCardClass('attention-order', item.order_id)"
                                    @open="openAttentionOrderDrillDown(item)"
                                    @share="beginShareToChat({ url: item.url, label: item.order_number })"
                                >
                                    <div class="text-sm font-semibold text-zinc-50">{{ item.order_number }}</div>
                                    <div class="mt-1 text-xs text-zinc-400">{{ item.customer_name || 'Заказ' }}</div>
                                    <div class="mt-2 text-xs text-amber-100">
                                        {{ item.pending_count }} незакрытых слотов
                                        <span v-if="item.pending_labels?.length"> · {{ item.pending_labels.join(', ') }}</span>
                                    </div>
                                </MobileShellEntityCard>
                            </div>

                            <div class="space-y-2">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Контрагенты</div>
                                <MobileShellEntityCard
                                    v-for="contractor in documentContractors"
                                    :key="`doc-contractor-${contractor.id}`"
                                    @open="openDocumentContractor(contractor)"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-zinc-50">{{ contractor.name }}</div>
                                            <div v-if="contractor.inn" class="mt-1 text-xs text-zinc-500">ИНН {{ contractor.inn }}</div>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-white/10 px-2 py-1 text-[10px] text-zinc-300">
                                            {{ contractor.orders_count }} зак.
                                        </span>
                                    </div>
                                </MobileShellEntityCard>
                                <div v-if="documentContractors.length === 0" class="rounded-3xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-zinc-500">
                                    {{ search.trim() ? 'Контрагенты не найдены.' : 'Нет заказов с контрагентами.' }}
                                </div>
                            </div>
                        </template>

                        <template v-else-if="documentsDrillLevel === 'orders'">
                            <MobileShellEntityCard
                                v-for="order in documentContractorOrders"
                                :key="`doc-order-${order.id}`"
                                :element-id="`mobile-doc-order-${order.id}`"
                                :highlight-class="highlightCardClass('attention-order', order.id)"
                                @open="openDocumentOrder(order)"
                                @share="beginShareToChat({ url: order.documents_url, label: order.order_number })"
                            >
                                <div class="text-sm font-semibold text-zinc-50">{{ order.order_number }}</div>
                                <div class="mt-1 text-xs text-zinc-400">{{ order.customer_name || 'Заказчик не указан' }}</div>
                                <div v-if="order.carrier_name" class="mt-1 text-xs text-zinc-500">Перевозчик: {{ order.carrier_name }}</div>
                                <div
                                    v-if="order.documents_total_count > 0"
                                    class="mt-2 text-xs"
                                    :class="order.documents_pending_count > 0 ? 'text-amber-200' : 'text-emerald-300'"
                                >
                                    Документы: {{ order.documents_total_count - order.documents_pending_count }}/{{ order.documents_total_count }}
                                    <span v-if="order.documents_pending_labels?.length"> · {{ order.documents_pending_labels.join(', ') }}</span>
                                </div>
                            </MobileShellEntityCard>
                            <div v-if="documentContractorOrders.length === 0" class="rounded-3xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-zinc-500">
                                {{ search.trim() ? 'Заказы не найдены.' : 'У контрагента нет активных заказов.' }}
                            </div>
                        </template>

                        <template v-else-if="documentsDrillLevel === 'order' && orderDocumentChecklist">
                            <div class="rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-zinc-300">
                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Чек-лист документов</div>
                                <div class="mt-1 text-zinc-100">
                                    {{ orderDocumentChecklist.documents?.completed_count ?? 0 }} / {{ orderDocumentChecklist.documents?.total_count ?? 0 }} закрыто
                                </div>
                            </div>

                            <div class="space-y-2">
                                <button
                                    v-for="slot in filteredOrderDocumentSlots"
                                    :key="`slot-${slot.key}`"
                                    type="button"
                                    class="w-full rounded-3xl border px-4 py-3 text-left active:bg-white/5"
                                    :class="slot.completed ? 'border-emerald-500/20 bg-emerald-500/10' : 'border-amber-500/20 bg-amber-500/10'"
                                    @click="openDocumentSlot(slot)"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-zinc-50">{{ slot.label }}</div>
                                            <div v-if="slot.document?.original_name" class="mt-1 truncate text-xs text-zinc-400">
                                                {{ slot.document.original_name }}
                                            </div>
                                            <div v-else class="mt-1 text-xs text-amber-100">Слот не закрыт</div>
                                        </div>
                                        <span
                                            class="shrink-0 rounded-full px-2 py-0.5 text-[10px] uppercase tracking-wide"
                                            :class="slot.completed ? 'bg-emerald-500/20 text-emerald-200' : 'bg-amber-500/20 text-amber-100'"
                                        >
                                            {{ slot.completed ? 'Готово' : 'Ждёт' }}
                                        </span>
                                    </div>
                                </button>
                            </div>

                            <button
                                type="button"
                                class="flex w-full items-center justify-center gap-2 rounded-3xl border border-white/10 px-4 py-3 text-sm font-medium text-zinc-100 active:bg-white/10"
                                @click="openUploadWizardForOrder(orderDocumentChecklist.order.id)"
                            >
                                <Upload class="h-4 w-4" />
                                Загрузить документ
                            </button>
                        </template>
                    </template>
                </section>

                <section v-else-if="activeTab === 'orders'" class="space-y-3 p-4">
                    <div v-if="ordersLoading" class="py-8 text-center text-sm text-zinc-500">Загрузка заказов…</div>
                    <template v-else>
                        <MobileShellEntityCard
                            v-for="order in filteredOrders"
                            :key="`order-${order.id}`"
                            :element-id="`mobile-order-${order.id}`"
                            :highlight-class="highlightCardClass('order', order.id)"
                            @open="openOrderDetail(order)"
                            @share="beginShareToChat({ url: order.url, label: order.order_number })"
                        >
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] uppercase tracking-wide text-sky-200">Заказ</span>
                                    </div>
                                    <div class="mt-1 text-sm font-semibold text-zinc-50">{{ order.order_number }}</div>
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
                            <div
                                v-if="order.documents_total_count > 0"
                                class="mt-2 text-xs"
                                :class="order.documents_pending_count > 0 ? 'text-amber-200' : 'text-emerald-300'"
                            >
                                Документы: {{ order.documents_total_count - order.documents_pending_count }}/{{ order.documents_total_count }}
                                <span v-if="order.documents_pending_labels?.length"> · {{ order.documents_pending_labels.join(', ') }}</span>
                            </div>
                        </MobileShellEntityCard>
                        <div v-if="filteredOrders.length === 0" class="rounded-3xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-zinc-500">
                            {{ search.trim() ? 'Ничего не найдено.' : 'Активных заказов пока нет.' }}
                        </div>
                    </template>
                </section>

                <section v-else-if="activeTab === 'leads'" class="space-y-4 p-4">
                    <button
                        v-if="canUseLeadIntake"
                        type="button"
                        class="flex w-full items-center justify-between gap-3 rounded-3xl border border-sky-500/20 bg-sky-500/10 px-4 py-3 text-left active:bg-sky-500/15"
                        @click="openMessageLeadIntake"
                    >
                        <span>
                            <span class="block text-sm font-semibold text-zinc-50">Создать лид из текста</span>
                            <span class="mt-0.5 block text-xs text-zinc-400">Вставьте сообщение клиента из WhatsApp, Telegram или SMS</span>
                        </span>
                        <Plus class="h-5 w-5 shrink-0 text-sky-200" />
                    </button>

                    <div v-if="trakloLeadsLoading" class="py-8 text-center text-sm text-zinc-500">Загрузка лидов…</div>
                    <template v-else>
                        <div class="space-y-2">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Входящие заявки</div>
                            <div
                                v-for="lead in filteredTrakloLeads"
                                :key="`traklo-lead-${lead.id}`"
                                class="rounded-3xl border border-sky-400/20 bg-sky-400/10 p-3"
                            >
                                <button type="button" class="block w-full text-left" @click="openTrakloLeadDetail(lead)">
                                    <div class="flex items-start gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] uppercase tracking-wide text-sky-100">Лид</span>
                                                <span class="truncate text-[10px] text-zinc-400">{{ lead.status_label }}</span>
                                            </div>
                                            <div class="mt-1 truncate text-sm font-semibold text-zinc-50">{{ lead.number }} · {{ lead.title || 'Заявка на перевозку' }}</div>
                                            <div class="mt-1 truncate text-xs text-zinc-400">
                                                {{ lead.company_name || lead.contact_name || 'Контакт не указан' }}
                                            </div>
                                            <div v-if="lead.loading_location || lead.unloading_location" class="mt-2 text-xs text-sky-100">
                                                {{ lead.loading_location || '—' }} → {{ lead.unloading_location || '—' }}
                                            </div>
                                            <div v-if="lead.cargo" class="mt-1 truncate text-xs text-zinc-500">{{ lead.cargo }}</div>
                                        </div>
                                        <span class="shrink-0 text-[10px] text-zinc-500">{{ formatShortDate(lead.created_at) }}</span>
                                    </div>
                                </button>
                                <div class="mt-3 flex gap-2">
                                    <button
                                        type="button"
                                        class="flex-1 rounded-2xl bg-white/10 px-3 py-2 text-xs font-semibold text-zinc-100 active:bg-white/15"
                                        @click="beginShareToChat({ url: lead.url, label: lead.number })"
                                    >
                                        В чат
                                    </button>
                                    <a
                                        v-if="normalizedPhone(lead.phone)"
                                        :href="`tel:${normalizedPhone(lead.phone)}`"
                                        class="rounded-2xl border border-white/10 px-3 py-2 text-xs font-semibold text-sky-100 active:bg-white/10"
                                    >
                                        Позвонить
                                    </a>
                                </div>
                            </div>
                            <div v-if="filteredTrakloLeads.length === 0" class="rounded-3xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-zinc-500">
                                {{ search.trim() ? 'Ничего не найдено.' : 'Входящих заявок пока нет.' }}
                            </div>
                        </div>
                    </template>
                </section>

                <section v-else-if="activeTab === 'tasks'" class="space-y-3 p-4">
                    <div v-if="tasksLoading" class="py-8 text-center text-sm text-zinc-500">Загрузка задач…</div>
                    <template v-else>
                        <MobileShellEntityCard
                            v-for="task in filteredTasks"
                            :key="`task-${task.id}`"
                            :element-id="`mobile-task-${task.id}`"
                            :card-class="task.is_overdue || task.sla_breached ? 'border-rose-500/30 bg-rose-500/10' : 'border-white/10 bg-white/[0.04]'"
                            :highlight-class="highlightCardClass('task', task.id)"
                            @open="openTaskDetail(task)"
                            @share="beginShareToChat({ url: task.url, label: `${task.number} · ${task.title}` })"
                        >
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] uppercase tracking-wide text-sky-200">Задача</span>
                                    </div>
                                    <div class="mt-1 text-sm font-semibold text-zinc-50">{{ task.number }} · {{ task.title }}</div>
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
                        </MobileShellEntityCard>
                        <div v-if="filteredTasks.length === 0" class="rounded-3xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-zinc-500">
                            {{ search.trim() ? 'Ничего не найдено.' : 'Открытых задач нет.' }}
                        </div>
                    </template>
                </section>
            </main>

            <nav
                class="grid shrink-0 border-t border-white/10 bg-zinc-950/95 px-1 pb-[calc(0.5rem+env(safe-area-inset-bottom,0px))] pt-2"
                :class="visibleTabs.length >= 5 ? 'grid-cols-5' : 'grid-cols-4'"
            >
                <button
                    v-for="tab in visibleTabs"
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
                    <span
                        v-if="tab.key === 'leads' && trakloLeadsCount > 0"
                        class="absolute right-2 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-sky-600 px-1 text-[9px] font-bold text-white"
                    >
                        {{ trakloLeadsCount > 99 ? '99+' : trakloLeadsCount }}
                    </span>
                    <span
                        v-if="tab.key === 'documents' && documentsAttentionCount > 0"
                        class="absolute right-4 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[9px] font-bold text-white"
                    >
                        {{ documentsAttentionCount > 99 ? '99+' : documentsAttentionCount }}
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
            :preset-order-id="uploadPresetOrderId"
            @close="closeUploadWizard"
            @uploaded="handleDocumentUploaded"
        />

        <MobileEntityDetailSheet
            ref="detailSheetRef"
            :open="showDetailSheet"
            :entity="detailEntity"
            :order-summary="detailOrderSummary"
            :entity-summary="detailEntitySummary"
            :loading="detailLoading"
            :current-user-id="currentUserId"
            :lead-saving="leadDraftSaving"
            @close="closeDetailSheet"
            @share="beginShareFromDetail"
            @message-responsible="openTaskResponsibleChat"
            @upload-document="openUploadWizardForOrder"
            @save-lead-draft="handleSaveLeadDraft"
        />

        <MobileEntityPicker
            v-if="screen !== 'thread'"
            :open="showEntityPicker"
            @close="showEntityPicker = false"
            @select="insertEntityChip"
        />
        <div
            v-if="showMessageLeadIntake"
            class="absolute inset-0 z-40 flex flex-col justify-end bg-black/60"
            @click.self="closeMessageLeadIntake"
        >
            <form class="rounded-t-3xl border border-white/10 bg-zinc-900 p-4" @submit.prevent="submitMessageLeadIntake">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-base font-semibold text-zinc-50">Лид из сообщения</div>
                        <div class="mt-1 text-xs leading-5 text-zinc-400">
                            Вставьте текст клиента. Traklo создаст лид и попробует распознать маршрут, груз и телефон.
                        </div>
                    </div>
                    <button type="button" class="rounded-full p-2 text-zinc-400 active:bg-white/10" @click="closeMessageLeadIntake">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <textarea
                    v-model="messageLeadText"
                    class="mt-4 h-40 w-full resize-none rounded-2xl border border-white/10 bg-zinc-950 px-4 py-3 text-sm text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                    placeholder="Например: прошу рассчитать стоимость перевозки из Смоленска в Москву, груз паллеты 3 тонны, тел +7..."
                />
                <p v-if="messageLeadError" class="mt-2 text-xs text-rose-300">{{ messageLeadError }}</p>
                <button
                    type="submit"
                    class="mt-3 w-full rounded-2xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    :disabled="messageLeadCreating || messageLeadText.trim().length < 10"
                >
                    {{ messageLeadCreating ? 'Создаём лид…' : 'Создать лид' }}
                </button>
            </form>
        </div>
        <MobileAppUpdateBanner />
    </div>
</template>

<script setup>
import axios from 'axios';
import { computed, defineComponent, h, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ArrowLeft, CheckSquare, FileText, Inbox, MessageCircle, Package, Phone, Plus, Search, Send, Upload, Users, X } from 'lucide-vue-next';
import { useMessenger } from '@/composables/useMessenger.js';
import { useMessengerPolling } from '@/composables/useMessengerPolling.js';
import { useMobileShell } from '@/composables/useMobileShell.js';
import { usePullToRefresh } from '@/composables/usePullToRefresh.js';
import MobileAppUpdateBanner from '@/Components/Mobile/MobileAppUpdateBanner.vue';
import MobileCrmLinkPreview from '@/Components/Mobile/MobileCrmLinkPreview.vue';
import MobileDocumentUploadWizard from '@/Components/Mobile/MobileDocumentUploadWizard.vue';
import MobileEntityDetailSheet from '@/Components/Mobile/MobileEntityDetailSheet.vue';
import MobileEntityPicker from '@/Components/Mobile/MobileEntityPicker.vue';
import MobileShareToChatPicker from '@/Components/Mobile/MobileShareToChatPicker.vue';
import MobileShellEntityCard from '@/Components/Mobile/MobileShellEntityCard.vue';
import { entityKindLabel, previewForCrmUrl, splitMessageSegments } from '@/support/mobileMessageLinks.js';
import { buildDirectUnreadByUserId, formatConversationPreview } from '@/support/messengerConversationText.js';
import { registerMobilePushIfAvailable } from '@/support/mobilePush.js';
import { pushMobileRecent, readMobileRecents } from '@/support/mobileShellRecents.js';

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
const isExternalUser = computed(() => Boolean(page.props.auth?.user?.is_external));
const canUseLeadIntake = computed(() => {
    if (isExternalUser.value) {
        return false;
    }

    const areas = page.props.auth?.user?.role?.visibility_areas;

    return Array.isArray(areas) && areas.includes('leads');
});
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
const showMessageLeadIntake = ref(false);
const pendingShare = ref(null);
const highlightTarget = ref(null);
const showDetailSheet = ref(false);
const detailEntity = ref(null);
const detailOrderSummary = ref(null);
const detailEntitySummary = ref(null);
const detailLoading = ref(false);
const uploadPresetOrderId = ref(null);
const unifiedSearchResults = ref([]);
const unifiedSearchLoading = ref(false);
const mobileRecents = ref(readMobileRecents());
const messageLeadText = ref('');
const messageLeadCreating = ref(false);
const messageLeadError = ref('');
const chatChannelFilter = ref('all');
const counterpartyContacts = ref([]);
const counterpartyOrders = ref([]);
const counterpartyOrdersLoading = ref(false);

const chatChannelOptions = computed(() => {
    if (isExternalUser.value) {
        return [{ key: 'counterparty', label: 'Контрагент' }];
    }

    return [
        { key: 'all', label: 'Все' },
        { key: 'internal', label: 'Команда' },
        { key: 'counterparty', label: 'Контрагенты' },
    ];
});

const { pullReady, refreshing: pullRefreshing, onTouchStart, onTouchMove, onTouchEnd } = usePullToRefresh(
    () => listPanel.value,
    () => refreshActiveTab(),
);

const allTabs = [
    { key: 'chats', label: 'Чаты', icon: MessageCircle },
    { key: 'leads', label: 'Лиды', icon: Inbox, requiresLeads: true },
    { key: 'documents', label: 'Документы', icon: FileText },
    { key: 'orders', label: 'Заказы', icon: Package },
    { key: 'tasks', label: 'Задачи', icon: CheckSquare },
];

const visibleTabs = computed(() => {
    if (isExternalUser.value) {
        return allTabs.filter((tab) => ['chats', 'orders', 'documents'].includes(tab.key));
    }

    return allTabs.filter((tab) => !tab.requiresLeads || canUseLeadIntake.value);
});

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
    loadCounterpartyContacts,
    loadCounterpartyOrders,
    reloadAll,
    selectConversation,
    openDirect,
    openCounterparty,
    createGroup,
    sendMessage,
    clearActiveConversation,
} = messenger;

const {
    tasks,
    orders,
    recentDocuments,
    attentionDocuments,
    documentContractors,
    documentContractorOrders,
    orderDocumentChecklist,
    trakloLeads,
    overdueTaskCount,
    tasksLoading,
    ordersLoading,
    documentsLoading,
    documentContractorsLoading,
    documentContractorOrdersLoading,
    orderDocumentChecklistLoading,
    trakloLeadsLoading,
    shellError,
    loadTab,
    loadDocuments,
    loadDocumentContractors,
    loadDocumentContractorOrders,
    loadOrderDocumentChecklist,
    loadOrders,
    loadTrakloLeads,
    loadOrderSummary,
    loadLeadSummary,
    loadContractorSummary,
    saveLeadDraft,
    searchEntities,
} = useMobileShell();

const documentsDrillLevel = ref('contractors');
const selectedDocumentContractor = ref(null);
const selectedDocumentOrder = ref(null);

function resetDocumentsDrillDown() {
    documentsDrillLevel.value = 'contractors';
    selectedDocumentContractor.value = null;
    selectedDocumentOrder.value = null;
    orderDocumentChecklist.value = null;
    documentContractorOrders.value = [];
}

const documentsDrillTitle = computed(() => {
    if (documentsDrillLevel.value === 'orders') {
        return selectedDocumentContractor.value?.name ?? 'Заказы';
    }

    if (documentsDrillLevel.value === 'order') {
        return orderDocumentChecklist.value?.order?.order_number
            ?? selectedDocumentOrder.value?.order_number
            ?? 'Документы заказа';
    }

    return '';
});

const documentsDrillSubtitle = computed(() => {
    if (documentsDrillLevel.value === 'orders' && selectedDocumentContractor.value?.inn) {
        return `ИНН ${selectedDocumentContractor.value.inn}`;
    }

    if (documentsDrillLevel.value === 'order') {
        return orderDocumentChecklist.value?.order?.customer_name ?? null;
    }

    return null;
});

const documentsPanelLoading = computed(() => {
    if (documentsDrillLevel.value === 'contractors') {
        return documentsLoading.value || documentContractorsLoading.value;
    }

    if (documentsDrillLevel.value === 'orders') {
        return documentContractorOrdersLoading.value;
    }

    if (documentsDrillLevel.value === 'order') {
        return orderDocumentChecklistLoading.value;
    }

    return false;
});

const filteredOrderDocumentSlots = computed(() => {
    const slots = orderDocumentChecklist.value?.slots ?? [];
    const needle = search.value.trim().toLowerCase();

    if (needle === '') {
        return slots;
    }

    return slots.filter((slot) =>
        `${slot.label ?? ''} ${slot.document?.original_name ?? ''}`.toLowerCase().includes(needle),
    );
});

async function refreshDocumentsPanel() {
    await loadDocuments(search.value);

    if (isExternalUser.value) {
        return;
    }

    if (documentsDrillLevel.value === 'contractors') {
        await loadDocumentContractors(search.value);

        return;
    }

    if (documentsDrillLevel.value === 'orders' && selectedDocumentContractor.value?.id) {
        await loadDocumentContractorOrders(selectedDocumentContractor.value.id, search.value);

        return;
    }

    if (documentsDrillLevel.value === 'order' && selectedDocumentOrder.value?.id) {
        await loadOrderDocumentChecklist(selectedDocumentOrder.value.id);
    }
}

function documentsDrillBack() {
    search.value = '';

    if (documentsDrillLevel.value === 'order') {
        documentsDrillLevel.value = 'orders';
        selectedDocumentOrder.value = null;
        orderDocumentChecklist.value = null;
        loadDocumentContractorOrders(selectedDocumentContractor.value?.id, '');

        return;
    }

    if (documentsDrillLevel.value === 'orders') {
        documentsDrillLevel.value = 'contractors';
        selectedDocumentContractor.value = null;
        documentContractorOrders.value = [];
        loadDocumentContractors('');
    }
}

async function openDocumentContractor(contractor) {
    selectedDocumentContractor.value = contractor;
    documentsDrillLevel.value = 'orders';
    search.value = '';
    await loadDocumentContractorOrders(contractor.id, '');
}

async function openDocumentOrder(order) {
    selectedDocumentOrder.value = order;
    documentsDrillLevel.value = 'order';
    search.value = '';
    await loadOrderDocumentChecklist(order.id);
}

async function openAttentionOrderDrillDown(item) {
    if (item.customer_id) {
        selectedDocumentContractor.value = {
            id: item.customer_id,
            name: item.customer_name ?? 'Контрагент',
        };
        documentsDrillLevel.value = 'order';
    } else {
        documentsDrillLevel.value = 'order';
        selectedDocumentContractor.value = null;
    }

    selectedDocumentOrder.value = {
        id: item.order_id,
        order_number: item.order_number,
    };
    search.value = '';
    await loadOrderDocumentChecklist(item.order_id);
}

function openDocumentSlot(slot) {
    const orderId = orderDocumentChecklist.value?.order?.id;
    const url = orderDocumentChecklist.value?.urls?.documents
        ?? (orderId ? route('orders.edit', orderId) + '?tab=documents' : null);

    if (!url) {
        return;
    }

    if (slot.completed && slot.document?.url) {
        window.open(slot.document.url, '_blank', 'noopener');

        return;
    }

    if (orderId) {
        openUploadWizardForOrder(orderId);
    }
}

const leadDraftSaving = ref(false);
const detailSheetRef = ref(null);

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
        await refreshDocumentsPanel();
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
    uploadPresetOrderId.value = null;
    showThreadMenu.value = false;
    showUploadWizard.value = true;
}

function insertTransportRequestLink() {
    const url = route('public.transport-request.create');
    const current = messageBody.value.trim();
    const text = [
        'Можете оставить заявку на перевозку здесь:',
        url,
        '',
        'Укажите маршрут, груз и контакты — менеджер увидит заявку в CRM и вернётся с расчётом.',
    ].join('\n');

    messageBody.value = current ? `${current}\n\n${text}` : text;
    showThreadMenu.value = false;
}

function openMessageLeadIntake() {
    showThreadMenu.value = false;
    messageLeadError.value = '';
    showMessageLeadIntake.value = true;
}

function closeMessageLeadIntake() {
    if (messageLeadCreating.value) {
        return;
    }

    showMessageLeadIntake.value = false;
    messageLeadError.value = '';
}

async function submitMessageLeadIntake() {
    const text = messageLeadText.value.trim();
    if (text.length < 10) {
        messageLeadError.value = 'Вставьте сообщение клиента.';

        return;
    }

    messageLeadCreating.value = true;
    messageLeadError.value = '';

    try {
        const { data } = await axios.post(route('mobile.shell.leads.from-text'), {
            message: text,
        }, {
            headers: { Accept: 'application/json' },
        });

        messageLeadText.value = '';
        showMessageLeadIntake.value = false;
        await loadTrakloLeads(search.value);

        if (data?.lead) {
            openTrakloLeadDetail({
                ...data.lead,
                status_label: 'Новый',
                company_name: null,
                contact_name: null,
                phone: data.parsed?.phone ?? null,
                cargo: data.parsed?.cargo ?? null,
                created_at: new Date().toISOString(),
            });
        }
    } catch (exception) {
        messageLeadError.value = exception.response?.data?.message ?? 'Не удалось создать лид из текста.';
    } finally {
        messageLeadCreating.value = false;
    }
}

function openUploadWizardForOrder(orderId) {
    uploadPresetOrderId.value = Number(orderId);
    showDetailSheet.value = false;
    showUploadWizard.value = true;
}

function closeUploadWizard() {
    showUploadWizard.value = false;
    uploadPresetOrderId.value = null;
}

function closeDetailSheet() {
    showDetailSheet.value = false;
    detailEntity.value = null;
    detailOrderSummary.value = null;
    detailEntitySummary.value = null;
    detailLoading.value = false;
}

function rememberRecent(entity) {
    pushMobileRecent(entity);
    mobileRecents.value = readMobileRecents();
}

async function openOrderDetail(order) {
    rememberRecent({
        kind: 'order',
        id: order.id,
        label: order.order_number,
        subtitle: order.customer_name,
        url: order.url,
    });

    detailEntity.value = {
        kind: 'order',
        id: order.id,
        label: order.order_number,
        subtitle: order.customer_name,
        url: order.url,
    };
    detailOrderSummary.value = null;
    showDetailSheet.value = true;
    detailLoading.value = true;

    try {
        detailOrderSummary.value = await loadOrderSummary(order.id);

        if (detailOrderSummary.value?.urls?.order) {
            detailEntity.value.url = detailOrderSummary.value.urls.order;
        }
    } finally {
        detailLoading.value = false;
    }
}

function openAttentionDetail(item) {
    openOrderDetail({
        id: item.order_id,
        order_number: item.order_number,
        customer_name: item.customer_name,
        url: item.url,
    });
}

function openDocumentDetail(doc) {
    rememberRecent({
        kind: 'document',
        id: doc.id,
        label: doc.label,
        subtitle: doc.order_id ? `Заказ #${doc.order_id}` : null,
        url: doc.url,
    });

    detailEntity.value = {
        kind: 'document',
        id: doc.id,
        label: doc.label,
        subtitle: doc.order_id ? `Заказ #${doc.order_id}` : null,
        url: doc.url,
    };
    detailOrderSummary.value = null;
    showDetailSheet.value = true;
}

function openTaskDetail(task) {
    rememberRecent({
        kind: 'task',
        id: task.id,
        label: `${task.number} · ${task.title}`,
        subtitle: task.status_label,
        url: task.url,
    });

    detailEntity.value = {
        kind: 'task',
        id: task.id,
        label: `${task.number} · ${task.title}`,
        subtitle: task.status_label,
        url: task.url,
        orderUrl: task.order_url,
        orderLabel: task.order_id ? `Заказ #${task.order_id}` : null,
        leadUrl: task.lead_url,
        leadLabel: task.lead_number ? `Лид ${task.lead_number}` : null,
        meta: [
            task.responsible_name ? { label: 'Ответственный', value: task.responsible_name } : null,
            task.due_at ? { label: 'Срок', value: formatShortDate(task.due_at) } : null,
            task.contractor_name ? { label: 'Контрагент', value: task.contractor_name } : null,
        ].filter(Boolean),
        responsibleId: task.responsible_id ?? null,
        responsibleName: task.responsible_name ?? null,
    };
    detailOrderSummary.value = null;
    showDetailSheet.value = true;
}

function openTrakloLeadDetail(lead) {
    rememberRecent({
        kind: 'lead',
        id: lead.id,
        label: lead.number,
        subtitle: lead.title || lead.company_name || lead.contact_name,
        url: lead.url,
    });

    detailEntity.value = {
        kind: 'lead',
        id: lead.id,
        label: lead.number,
        subtitle: lead.title || lead.company_name || lead.contact_name,
        url: lead.url,
        meta: [
            lead.status_label ? { label: 'Статус', value: lead.status_label } : null,
            lead.company_name ? { label: 'Компания', value: lead.company_name } : null,
            lead.contact_name ? { label: 'Контакт', value: lead.contact_name } : null,
            lead.phone ? { label: 'Телефон', value: lead.phone } : null,
            lead.cargo ? { label: 'Груз', value: lead.cargo } : null,
        ].filter(Boolean),
    };
    detailOrderSummary.value = null;
    detailEntitySummary.value = null;
    showDetailSheet.value = true;
    loadEntitySummary('lead', lead.id);
}

function openEntityChipDetail(entity) {
    rememberRecent({
        kind: entity.kind,
        id: entity.id,
        label: entity.label,
        subtitle: entity.subtitle,
        url: entity.url,
    });

    if (entity.kind === 'order') {
        openOrderDetail({
            id: entity.id,
            order_number: entity.label,
            customer_name: entity.subtitle,
            url: entity.url,
        });

        return;
    }

    detailEntity.value = {
        kind: entity.kind,
        id: entity.id,
        label: entity.label,
        subtitle: entity.subtitle,
        url: entity.url,
    };
    detailOrderSummary.value = null;
    detailEntitySummary.value = null;
    showDetailSheet.value = true;

    if (entity.kind === 'lead' || entity.kind === 'contractor') {
        loadEntitySummary(entity.kind, entity.id);
    }
}

async function loadEntitySummary(kind, id) {
    detailLoading.value = true;

    try {
        if (kind === 'lead') {
            detailEntitySummary.value = await loadLeadSummary(id);

            if (detailEntitySummary.value?.lead?.number) {
                detailEntity.value.label = detailEntitySummary.value.lead.number;
            }

            if (detailEntitySummary.value?.lead?.title) {
                detailEntity.value.subtitle = detailEntitySummary.value.lead.title;
            }
        }

        if (kind === 'contractor') {
            detailEntitySummary.value = await loadContractorSummary(id);

            if (detailEntitySummary.value?.urls?.contractor) {
                detailEntity.value.url = detailEntitySummary.value.urls.contractor;
            }

            if (detailEntitySummary.value?.contractor?.name) {
                detailEntity.value.label = detailEntitySummary.value.contractor.name;
            }
        }
    } finally {
        detailLoading.value = false;
    }
}

async function handleSaveLeadDraft({ leadId, payload }) {
    leadDraftSaving.value = true;

    try {
        detailEntitySummary.value = await saveLeadDraft(leadId, payload);
        await loadTrakloLeads(search.value);
    } catch (exception) {
        detailSheetRef.value?.setLeadSaveError?.(
            exception.response?.data?.message ?? 'Не удалось сохранить лид.',
        );
    } finally {
        leadDraftSaving.value = false;
    }
}

function openRecentDetail(item) {
    if (item.kind === 'order') {
        openOrderDetail({
            id: item.id,
            order_number: item.label,
            customer_name: item.subtitle,
            url: item.url,
        });

        return;
    }

    if (item.kind === 'task') {
        const task = tasks.value.find((row) => Number(row.id) === Number(item.id));

        if (task) {
            openTaskDetail(task);

            return;
        }
    }

    detailEntity.value = { ...item };
    detailOrderSummary.value = null;
    showDetailSheet.value = true;
}

function beginShareFromDetail(payload) {
    closeDetailSheet();
    beginShareToChat(payload);
}

async function openTaskResponsibleChat(payload) {
    const userId = Number(payload?.userId);
    if (!userId || userId === currentUserId.value) {
        return;
    }

    closeDetailSheet();

    const colleague = colleagues.value.find((row) => Number(row.id) === userId);
    const user = colleague ?? {
        id: userId,
        name: payload?.name ?? 'Коллега',
    };

    activeTab.value = 'chats';
    await openUserThread(user);
}

function toggleThreadActions() {
    showThreadMenu.value = !showThreadMenu.value;
}

async function handleDocumentUploaded(document) {
    if (document?.url) {
        if (screen.value === 'thread') {
            const prefix = messageBody.value.trim();
            const text = prefix ? `${prefix} ${document.url}` : document.url;
            await sendMessage(text);
            messageBody.value = '';
        } else {
            beginShareToChat({
                url: document.url,
                label: document.label ?? 'Документ',
            });
        }
    }

    if (activeTab.value === 'documents' || screen.value !== 'thread') {
        await refreshDocumentsPanel();
    }

    if (activeTab.value === 'orders') {
        await loadOrders(search.value);
    }
}

const activeTabLabel = computed(() => allTabs.find((tab) => tab.key === activeTab.value)?.label ?? 'Раздел');

const trakloLeadsCount = computed(() => trakloLeads.value.length);

const directUnreadByUserId = computed(() => buildDirectUnreadByUserId(conversations.value));

const filteredConversations = computed(() => {
    const colleagueIds = new Set(filteredColleagues.value.map((user) => Number(user.id)));
    const needle = search.value.trim().toLowerCase();

    return conversations.value
        .filter((conversation) => {
            if (chatChannelFilter.value === 'all') {
                return true;
            }

            const channel = conversation.channel ?? 'internal';

            return channel === chatChannelFilter.value;
        })
        .filter((conversation) => {
            if (conversation.type === 'direct') {
                const otherUserId = Number(conversation.other_user?.id ?? 0);

                if (otherUserId > 0 && colleagueIds.has(otherUserId) && (conversation.channel ?? 'internal') === 'internal') {
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

const filteredTrakloLeads = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (needle === '') {
        return trakloLeads.value;
    }

    return trakloLeads.value.filter((lead) =>
        [
            lead.number,
            lead.title,
            lead.company_name,
            lead.contact_name,
            lead.phone,
            lead.cargo,
            lead.loading_location,
            lead.unloading_location,
        ].join(' ').toLowerCase().includes(needle),
    );
});

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

const documentsAttentionCount = computed(() => attentionDocuments.value.length);

const threadColleaguePhone = computed(() => {
    if (activeConversation.value?.type !== 'direct') {
        return null;
    }

    const other = activeConversation.value.other_user;
    if (!other?.id) {
        return null;
    }

    const colleague = colleagues.value.find((row) => Number(row.id) === Number(other.id));

    return normalizedPhone(colleague?.phone ?? other.phone);
});

function conversationTitle(conversation) {
    if (!conversation) {
        return '';
    }

    if (conversation.type === 'group') {
        return conversation.title ?? 'Группа';
    }

    if (conversation.channel === 'counterparty') {
        return conversation.contractor_name ?? conversation.other_user?.name ?? 'Контрагент';
    }

    return conversation.other_user?.name ?? 'Личный чат';
}

function threadSubtitle(conversation) {
    if (!conversation) {
        return '';
    }

    if (conversation.type === 'group') {
        return `${conversation.member_count} участников`;
    }

    if (conversation.channel === 'counterparty') {
        const party = conversation.external_party === 'carrier' ? 'Перевозчик' : 'Заказчик';

        return `${party} · ${conversation.other_user?.name ?? 'контакт'}`;
    }

    return 'Личное сообщение';
}

function isSystemMessage(message) {
    return message?.message_type === 'system';
}

async function refreshCounterpartyOrders() {
    if (!activeConversation.value || activeConversation.value.channel !== 'counterparty') {
        counterpartyOrders.value = [];

        return;
    }

    counterpartyOrdersLoading.value = true;

    try {
        counterpartyOrders.value = await loadCounterpartyOrders(activeConversation.value.id);
    } finally {
        counterpartyOrdersLoading.value = false;
    }
}

async function openConversationThread(conversation) {
    await selectConversation(conversation);
    screen.value = 'thread';
    await refreshCounterpartyOrders();
}

function conversationPreview(conversation) {
    return formatConversationPreview(conversation, currentUserId.value);
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

    if (tab !== 'documents') {
        resetDocumentsDrillDown();
    }

    if (tab === 'documents' && !isExternalUser.value) {
        refreshDocumentsPanel();

        return;
    }

    loadTab(tab);
}

async function refreshActiveTab() {
    if (activeTab.value === 'chats') {
        await Promise.all([reloadAll(), loadColleagues()]);

        if (!isExternalUser.value) {
            counterpartyContacts.value = await loadCounterpartyContacts();
        }

        return;
    }

    if (activeTab.value === 'documents') {
        await refreshDocumentsPanel();

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

async function openCounterpartyFromQuery() {
    if (typeof window === 'undefined') {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const conversationId = Number(params.get('conversation_id') ?? 0);

    if (conversationId > 0) {
        activeTab.value = 'chats';
        await openConversationById(conversationId);

        return;
    }

    if (isExternalUser.value) {
        return;
    }

    const contractorId = Number(params.get('counterparty_contractor_id') ?? 0);
    const externalParty = params.get('counterparty_party');
    const orderId = Number(params.get('order_id') ?? 0);

    if (contractorId <= 0 || !externalParty) {
        return;
    }

    try {
        await openCounterparty({
            contractor_id: contractorId,
            external_party: externalParty,
            order_id: orderId > 0 ? orderId : undefined,
        });
        activeTab.value = 'chats';
        screen.value = 'thread';
        await refreshCounterpartyOrders();
    } catch {
        return;
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

        if (canUseLeadIntake.value) {
            loadTrakloLeads();
        }

        return;
    }

    loadTab(activeTab.value, search.value);
}

let shellSearchTimer = null;

watch([activeTab, search], ([tab, needle]) => {
    if (tab === 'chats') {
        unifiedSearchResults.value = [];

        return;
    }

    clearTimeout(shellSearchTimer);
    shellSearchTimer = setTimeout(async () => {
        if (tab === 'documents') {
            await refreshDocumentsPanel();

            if (needle.trim() === '') {
                unifiedSearchResults.value = [];
                mobileRecents.value = readMobileRecents();

                return;
            }

            if (documentsDrillLevel.value === 'contractors' || isExternalUser.value) {
                unifiedSearchLoading.value = true;

                try {
                    unifiedSearchResults.value = await searchEntities(needle);
                } finally {
                    unifiedSearchLoading.value = false;
                }
            } else {
                unifiedSearchResults.value = [];
            }

            return;
        }

        await loadTab(tab, needle);

        if (needle.trim() === '') {
            unifiedSearchResults.value = [];
            mobileRecents.value = readMobileRecents();

            return;
        }

        unifiedSearchLoading.value = true;

        try {
            unifiedSearchResults.value = await searchEntities(needle);
        } finally {
            unifiedSearchLoading.value = false;
        }
    }, 300);
});

onMounted(async () => {
    if (isExternalUser.value) {
        chatChannelFilter.value = 'counterparty';
    }

    await reloadAll();
    loadColleagues();

    if (!isExternalUser.value) {
        loadCounterpartyContacts().then((contacts) => {
            counterpartyContacts.value = contacts;
        });
    }

    await openCounterpartyFromQuery();

    if (canUseLeadIntake.value) {
        loadTrakloLeads();
    }

    mobileRecents.value = readMobileRecents();
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

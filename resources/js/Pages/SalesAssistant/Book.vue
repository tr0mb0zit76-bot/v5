<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="grid min-h-0 flex-1 gap-3 overflow-hidden grid-rows-[minmax(0,28vh)_minmax(0,1fr)] lg:grid-cols-[minmax(220px,260px),minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)]">
        <aside :class="`${crmPanel} flex min-h-0 flex-col overflow-hidden p-3`">
            <h1 class="shrink-0 text-sm font-semibold text-zinc-900 dark:text-zinc-50">Книга продаж</h1>

            <form v-if="canWrite" class="mt-3 shrink-0 space-y-2 border-t border-zinc-100 pt-3 dark:border-zinc-800" @submit.prevent="createArticle">
                <input
                    v-model="createForm.title"
                    type="text"
                    required
                    placeholder="Новый заголовок страницы"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                />
                <select
                    v-model="createForm.parent_id"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 pr-8 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <option value="">Без родителя</option>
                    <option v-for="option in indentedArticleOptions" :key="option.id" :value="String(option.id)">
                        {{ option.label }}
                    </option>
                </select>
                <button
                    type="submit"
                    :disabled="createForm.processing"
                    :class="`${crmBtnPrimary} w-full disabled:cursor-not-allowed disabled:opacity-60`"
                >
                    Создать страницу
                </button>
            </form>

            <form v-if="canWrite" class="mt-3 shrink-0 space-y-2 border-t border-zinc-100 pt-3 dark:border-zinc-800" @submit.prevent="importMarkdown">
                <input
                    type="file"
                    accept=".md,.markdown,.txt,text/markdown,text/plain"
                    @change="onFileChange"
                    class="block w-full text-xs text-zinc-600 file:mr-2 file:rounded-md file:border-0 file:bg-zinc-100 file:px-2 file:py-1 file:text-xs file:font-medium dark:text-zinc-300 dark:file:bg-zinc-800"
                />
                <select
                    v-model="importForm.parent_id"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 pr-8 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <option value="">Импорт в корень</option>
                    <option v-for="option in indentedArticleOptions" :key="`import-${option.id}`" :value="String(option.id)">
                        {{ option.label }}
                    </option>
                </select>
                <button
                    type="submit"
                    :disabled="importForm.processing"
                    :class="`${crmBtnNeutral} w-full justify-center disabled:cursor-not-allowed disabled:opacity-60`"
                >
                    Импорт .md
                </button>
            </form>

            <div class="mt-3 shrink-0 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                <label class="block text-[11px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400" for="sales-book-view">
                    Представление
                </label>
                <select
                    id="sales-book-view"
                    class="mt-1 w-full rounded-lg border border-zinc-200 bg-white px-2 py-1.5 pr-8 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                    :value="activeBookView.slug"
                    @change="openBookView($event.target.value)"
                >
                    <option
                        v-for="view in bookViews"
                        :key="view.slug"
                        :value="view.slug"
                    >
                        {{ view.label }}
                    </option>
                </select>
                <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">{{ activeBookView.description }}</p>
            </div>

            <div class="mt-3 shrink-0 space-y-2 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                <input
                    v-model="sidebarSearch"
                    type="search"
                    placeholder="Найти материал..."
                    class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                />
                <div class="grid grid-cols-1 gap-1">
                    <select
                        v-for="definition in sidebarFilterDefinitions"
                        :key="`sidebar-filter-${definition.key}`"
                        v-model="sidebarFilters[definition.key]"
                        class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1.5 pr-8 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                    >
                        <option value="">{{ definition.label }}: все</option>
                        <option v-for="option in definition.options" :key="`${definition.key}-${option.value}`" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <button
                    v-if="sidebarFiltersActive"
                    type="button"
                    class="text-xs font-medium text-sky-700 hover:text-sky-800 dark:text-sky-300 dark:hover:text-sky-200"
                    @click="resetSidebarFilters"
                >
                    Сбросить поиск и фильтры
                </button>
            </div>

            <div class="mt-3 min-h-0 flex-1 overflow-y-auto border-t border-zinc-100 pt-3 dark:border-zinc-800">
                <p v-if="articlesTree.length === 0" class="text-sm text-zinc-500">Пока нет страниц.</p>
                <SalesBookTreeNav
                    v-else-if="activeBookView.layout === 'tree' && !sidebarFiltersActive"
                    :tree="articlesTree"
                    :article-options="articleOptions"
                    :selected-id="selectedArticle?.id ?? null"
                    :can-write="canWrite"
                    @select="openArticle"
                    @move="moveArticle"
                />
                <div v-else-if="activeBookView.layout === 'stage'" class="space-y-3">
                    <p v-if="filteredBookViewRows.length === 0" class="text-sm text-zinc-500">Нет материалов по текущим условиям.</p>
                    <div v-for="group in groupedRowsByStage" :key="group.value" class="space-y-1">
                        <p class="px-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                            {{ group.label }}
                        </p>
                        <button
                            v-for="row in group.rows"
                            :key="`stage-row-${row.id}`"
                            type="button"
                            class="block w-full rounded-lg px-2 py-1.5 text-left text-xs transition"
                            :class="row.id === selectedArticle?.id
                                ? 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-100'
                                : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800'"
                            @click="openArticle(row.id)"
                        >
                            <span class="font-medium">{{ row.title }}</span>
                            <span v-if="row.property_labels?.audience_role" class="mt-0.5 block text-[11px] text-zinc-500">
                                {{ row.property_labels.audience_role }}
                            </span>
                        </button>
                    </div>
                </div>
                <div v-else class="space-y-1">
                    <p v-if="filteredNavigationRows.length === 0" class="text-sm text-zinc-500">Нет материалов по текущим условиям.</p>
                    <button
                        v-for="row in filteredNavigationRows"
                        :key="`nav-row-${row.id}`"
                        type="button"
                        class="block w-full rounded-lg px-2 py-1.5 text-left text-xs transition"
                        :class="row.id === selectedArticle?.id
                            ? 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-100'
                            : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800'"
                        @click="openArticle(row.id)"
                    >
                        <span class="font-medium">{{ row.title }}</span>
                        <span class="mt-0.5 block truncate text-[11px] text-zinc-500">
                            {{ propertyLabelForRow(row, 'audience_role') || 'Без роли' }}
                            <template v-if="propertyLabelForRow(row, 'sales_stage')"> · {{ propertyLabelForRow(row, 'sales_stage') }}</template>
                        </span>
                        <span v-if="row.excerpt" class="mt-1 block text-[11px] leading-snug text-zinc-500 dark:text-zinc-400">
                            {{ row.excerpt }}
                        </span>
                    </button>
                </div>
            </div>
        </aside>

        <section :class="`${crmPanel} flex min-h-0 flex-col overflow-hidden p-5`">
            <p
                v-if="page.props.flash?.message"
                class="mb-4 shrink-0 rounded-lg border px-3 py-2 text-sm"
                :class="page.props.flash?.type === 'error'
                    ? 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-100'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'"
                role="alert"
            >
                {{ page.props.flash.message }}
            </p>

            <template v-if="selectedArticle">
                <form v-if="canWrite && !readerPreview" class="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden" @submit.prevent="saveArticle">
                    <div class="relative min-h-24 shrink-0 overflow-hidden rounded-xl border border-zinc-200 bg-gradient-to-r from-sky-100 via-indigo-100 to-amber-100 py-3 dark:border-zinc-800 dark:from-sky-950 dark:via-indigo-950 dark:to-amber-950 md:min-h-28">
                        <img
                            v-if="selectedArticle.cover_image_url"
                            :src="selectedArticle.cover_image_url"
                            alt=""
                            class="absolute inset-0 h-full w-full object-cover"
                        />
                        <div v-else class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(14,165,233,0.32),transparent_30%),linear-gradient(120deg,rgba(59,130,246,0.18),rgba(245,158,11,0.22))]" />
                        <div
                            v-if="selectedArticle.cover_image_url"
                            class="pointer-events-none absolute inset-0 bg-gradient-to-r from-white/75 via-white/40 to-transparent dark:from-zinc-950/80 dark:via-zinc-950/45 dark:to-transparent"
                            aria-hidden="true"
                        />

                        <div class="relative flex items-center justify-between gap-2 px-3 md:gap-3 md:px-4">
                            <input
                                v-model="editForm.title"
                                type="text"
                                required
                                placeholder="Заголовок страницы"
                                :class="articleBannerTitleInputClass"
                            />
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <button
                                    type="button"
                                    :class="`${crmBtnNeutral} px-3 py-1.5 text-xs`"
                                    :title="copyLinkFeedback ? 'Скопировано' : 'Копировать ссылку на страницу'"
                                    @click="copyArticleLink"
                                >
                                    {{ copyLinkFeedback ? 'Скопировано' : 'Ссылка' }}
                                </button>
                                <input
                                    ref="coverInputRef"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="hidden"
                                    @change="uploadCover"
                                />
                                <div class="flex flex-col items-stretch gap-0.5">
                                    <button
                                        type="button"
                                        :class="articleBannerCoverButtonClass"
                                        :disabled="coverUploading"
                                        @click="coverInputRef?.click()"
                                    >
                                        {{ selectedArticle.cover_image_url ? 'Заменить' : 'Обложка' }}
                                    </button>
                                    <button
                                        v-if="selectedArticle.cover_image_url"
                                        type="button"
                                        :class="`${articleBannerCoverButtonClass} text-rose-700 dark:text-rose-200`"
                                        :disabled="coverUploading"
                                        @click="destroyCover"
                                    >
                                        Убрать
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-if="coverError" class="shrink-0 text-xs text-rose-600 dark:text-rose-300">{{ coverError }}</p>
                    <p v-else class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">Рекомендуемая обложка: широкое изображение с пропорцией 8–10:1.</p>

                    <div class="flex shrink-0 flex-wrap items-center gap-2 text-xs text-zinc-500">
                        <span>Родитель:</span>
                        <select
                            v-model="editForm.parent_id"
                            class="rounded-md border border-zinc-200 bg-white px-2 py-1 pr-8 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <option value="">Корень</option>
                            <option v-for="option in parentOptionsForEdit" :key="`edit-${option.id}`" :value="String(option.id)">
                                {{ option.label }}
                            </option>
                        </select>
                        <span>Статус:</span>
                        <select
                            v-model="editForm.status"
                            class="rounded-md border border-zinc-200 bg-white px-2 py-1 pr-8 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <option v-for="option in articleStatusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <span>Теги:</span>
                        <input
                            v-model="editForm.tags_text"
                            type="text"
                            placeholder="через запятую"
                            class="min-w-[12rem] rounded-md border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                        />
                        <template v-for="definition in propertyDefinitions" :key="definition.key">
                            <span>{{ definition.label }}:</span>
                            <select
                                v-model="editForm.properties[definition.key]"
                                class="rounded-md border border-zinc-200 bg-white px-2 py-1 pr-8 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <option value="">Не задано</option>
                                <option v-for="option in definition.options" :key="`${definition.key}-${option.value}`" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </template>
                        <template v-if="collectionInsertViews.length > 0">
                            <span>Подборка:</span>
                            <select
                                v-model="collectionInsertForm.view_slug"
                                class="rounded-md border border-zinc-200 bg-white px-2 py-1 pr-8 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <option v-for="view in collectionInsertViews" :key="`collection-view-${view.slug}`" :value="view.slug">
                                    {{ view.label }}
                                </option>
                            </select>
                            <input
                                v-model="collectionInsertForm.title"
                                type="text"
                                placeholder="Заголовок подборки"
                                class="w-36 rounded-md border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                            />
                            <input
                                v-model.number="collectionInsertForm.limit"
                                type="number"
                                min="1"
                                max="30"
                                class="w-16 rounded-md border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                                title="Сколько материалов показать"
                            />
                            <select
                                v-model="collectionInsertForm.layout"
                                class="rounded-md border border-zinc-200 bg-white px-2 py-1 pr-8 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <option value="list">Список</option>
                                <option value="cards">Карточки</option>
                                <option value="grouped">Группы</option>
                            </select>
                            <select
                                v-for="definition in sidebarFilterDefinitions"
                                :key="`collection-filter-${definition.key}`"
                                v-model="collectionInsertForm.filters[definition.key]"
                                class="rounded-md border border-zinc-200 bg-white px-2 py-1 pr-8 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <option value="">{{ definition.label }}: все</option>
                                <option v-for="option in definition.options" :key="`collection-${definition.key}-${option.value}`" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <button
                                type="button"
                                :class="`${crmBtnNeutral} px-2 py-1 text-xs`"
                                @click="insertEmbeddedCollection"
                            >
                                Вставить
                            </button>
                        </template>
                        <span v-if="selectedArticle.updated_at">Обновлено: {{ formatDate(selectedArticle.updated_at) }}</span>
                    </div>

                    <div v-if="selectedArticleTags.length > 0" class="flex shrink-0 flex-wrap gap-1">
                        <span
                            v-for="tag in selectedArticleTags"
                            :key="`tag-${tag}`"
                            class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                        >
                            {{ tag }}
                        </span>
                    </div>

                    <div
                        v-if="selectedArticleQuiz"
                        class="flex shrink-0 flex-wrap items-center justify-between gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-100"
                    >
                        <span>На странице настроен интерактивный тест: {{ selectedArticleQuiz.questions.length }} вопросов.</span>
                        <button
                            type="button"
                            class="rounded-md bg-sky-600 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-sky-700 dark:bg-sky-700 dark:hover:bg-sky-600"
                            @click="readerPreview = true"
                        >
                            Предпросмотр и тест
                        </button>
                    </div>

                    <TiptapEditor
                        ref="editEditorRef"
                        :key="editEditorKey"
                        class="min-h-0 flex-1"
                        :model-value="editorMarkdown"
                        :child-page-links="directChildPages"
                        :upload-url="route('sales-assistant.book.assets.upload')"
                        @update:model-value="onEditorUpdate"
                        :editable="true"
                        placeholder="Начните писать... Можно вставлять файлы и скриншоты через Ctrl+V из Проводника, ссылки, файлы и чек-листы."
                    />

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            @click="readerPreview = true"
                        >
                            Предпросмотр
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            :class="crmBtnCreate"
                        >
                            Сохранить
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-200 dark:hover:bg-rose-950/40"
                            @click="destroyArticle"
                        >
                            Удалить
                        </button>
                    </div>
                </form>

                <div v-else class="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden">
                    <div
                        v-if="canWrite && readerPreview"
                        class="flex shrink-0 items-center justify-between gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs text-indigo-900 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-100"
                    >
                        <span>Режим предпросмотра — так страницу видят сотрудники.</span>
                        <button
                            type="button"
                            class="rounded-md border border-indigo-300 bg-white px-2.5 py-1 text-[11px] font-medium text-indigo-800 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-zinc-900 dark:text-indigo-100 dark:hover:bg-indigo-950"
                            @click="readerPreview = false"
                        >
                            Вернуться к редактированию
                        </button>
                    </div>

                    <div class="relative min-h-24 shrink-0 overflow-hidden rounded-xl border border-zinc-200 bg-gradient-to-r from-sky-100 via-indigo-100 to-amber-100 py-3 dark:border-zinc-800 dark:from-sky-950 dark:via-indigo-950 dark:to-amber-950 md:min-h-28">
                        <img
                            v-if="selectedArticle.cover_image_url"
                            :src="selectedArticle.cover_image_url"
                            alt=""
                            class="absolute inset-0 h-full w-full object-cover"
                        />
                        <div v-else class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(14,165,233,0.32),transparent_30%),linear-gradient(120deg,rgba(59,130,246,0.18),rgba(245,158,11,0.22))]" />
                        <div
                            v-if="selectedArticle.cover_image_url"
                            class="pointer-events-none absolute inset-0 bg-gradient-to-r from-white/75 via-white/40 to-transparent dark:from-zinc-950/80 dark:via-zinc-950/45 dark:to-transparent"
                            aria-hidden="true"
                        />

                        <div class="relative flex items-center justify-between gap-2 px-3 md:gap-3 md:px-4">
                            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                <h2 :class="articleBannerHeadingClass">
                                    {{ selectedArticle.title }}
                                </h2>
                                <span
                                    v-if="selectedArticle.status === 'draft'"
                                    class="shrink-0 rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                                >
                                    Черновик
                                </span>
                            </div>
                            <button
                                type="button"
                                :class="`${crmBtnNeutral} shrink-0 px-3 py-1.5 text-xs`"
                                :title="copyLinkFeedback ? 'Скопировано' : 'Копировать ссылку на страницу'"
                                @click="copyArticleLink"
                            >
                                {{ copyLinkFeedback ? 'Скопировано' : 'Ссылка' }}
                            </button>
                        </div>
                    </div>

                    <SalesBookArticleFeedbackBar
                        v-if="canComment"
                        :article-id="selectedArticle.id"
                        :summary="articleFeedbackSummary"
                        :busy="feedbackForm.processing"
                        @rate="submitArticleFeedback"
                    />

                    <div v-if="selectedArticle.updated_at" class="shrink-0 text-xs text-zinc-500">
                        Обновлено: {{ formatDate(selectedArticle.updated_at) }}
                    </div>

                    <div v-if="selectedArticleTags.length > 0" class="flex shrink-0 flex-wrap gap-1">
                        <span
                            v-for="tag in selectedArticleTags"
                            :key="`readonly-tag-${tag}`"
                            class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                        >
                            {{ tag }}
                        </span>
                    </div>

                    <div v-if="selectedArticlePropertyBadges.length > 0" class="flex shrink-0 flex-wrap gap-1">
                        <span
                            v-for="badge in selectedArticlePropertyBadges"
                            :key="`readonly-property-${badge.key}`"
                            class="rounded-full border border-sky-100 bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-700 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200"
                        >
                            {{ badge.label }}: {{ badge.value }}
                        </span>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain">
                        <TiptapEditor
                            :key="readonlyEditorKey"
                            :model-value="selectedArticle.markdown_content_display"
                            :upload-url="route('sales-assistant.book.assets.upload')"
                            :editable="false"
                            placeholder=""
                        />

                        <div v-if="selectedArticleEmbeddedCollections.length > 0" class="mt-4 space-y-3">
                            <section
                                v-for="collection in selectedArticleEmbeddedCollections"
                                :key="collection.block_id"
                                class="rounded-xl border border-sky-100 bg-sky-50/60 p-3 dark:border-sky-900 dark:bg-sky-950/20"
                            >
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-sky-950 dark:text-sky-100">
                                            {{ collection.title }}
                                        </h3>
                                        <p class="text-xs text-sky-700/80 dark:text-sky-200/75">
                                            Автоподборка · {{ collection.rows.length }} материалов
                                        </p>
                                    </div>
                                    <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-medium text-sky-700 dark:bg-zinc-900 dark:text-sky-200">
                                        {{ collection.view_slug }}
                                    </span>
                                </div>

                                <div v-if="collection.rows.length === 0" class="rounded-lg border border-dashed border-sky-200 bg-white/70 px-3 py-2 text-xs text-sky-800 dark:border-sky-900 dark:bg-zinc-950/50 dark:text-sky-200">
                                    В этой подборке пока нет материалов.
                                </div>
                                <div v-else class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                    <button
                                        v-for="row in collection.rows"
                                        :key="`embedded-${collection.block_id}-${row.id}`"
                                        type="button"
                                        class="rounded-lg border border-white bg-white px-3 py-2 text-left text-xs shadow-sm transition hover:border-sky-200 hover:bg-sky-50 dark:border-zinc-800 dark:bg-zinc-950 dark:hover:border-sky-900 dark:hover:bg-sky-950/40"
                                        @click="openArticle(row.id)"
                                    >
                                        <span class="block truncate font-semibold text-zinc-900 dark:text-zinc-50">
                                            {{ row.title }}
                                        </span>
                                        <span class="mt-1 block truncate text-[11px] text-zinc-500">
                                            {{ row.property_labels?.audience_role || 'Без роли' }}
                                            <template v-if="row.property_labels?.sales_stage"> · {{ row.property_labels.sales_stage }}</template>
                                        </span>
                                    </button>
                                </div>
                            </section>
                        </div>

                        <SalesBookQuiz
                            v-if="selectedArticleQuiz"
                            :key="`quiz-${selectedArticle.id}`"
                            class="mt-4"
                            :article-id="selectedArticle.id"
                            :quiz="selectedArticleQuiz"
                        />
                    </div>
                </div>
            </template>

            <div v-else class="flex h-[420px] flex-col items-center justify-center rounded-lg border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-700">
                <p class="text-base font-medium text-zinc-700 dark:text-zinc-200">Пустая книга</p>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ canWrite ? 'Создайте первую страницу и начните писать сразу.' : 'Страницы пока не добавлены.' }}
                </p>
                <button
                    v-if="canWrite"
                    type="button"
                    :class="crmBtnPrimary"
                    class="mt-4"
                    @click="createUntitled"
                >
                    Создать первую страницу
                </button>
            </div>
        </section>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import TiptapEditor from '@/Components/SalesBook/TiptapEditor.vue';
import SalesBookArticleFeedbackBar from '@/Components/SalesBook/SalesBookArticleFeedbackBar.vue';
import SalesBookQuiz from '@/Components/SalesBook/SalesBookQuiz.vue';
import SalesBookTreeNav from '@/Components/SalesBook/SalesBookTreeNav.vue';
import { crmBtnCreate, crmBtnNeutral, crmBtnPrimary, crmPanel } from '@/support/crmUi.js';

const articleBannerTitleInputClass = 'min-w-0 flex-1 border-0 bg-transparent py-0.5 text-xl font-semibold leading-tight text-zinc-900 placeholder:text-zinc-600 [text-shadow:0_1px_0_rgba(255,255,255,0.9)] focus:outline-none focus:ring-0 dark:text-zinc-50 dark:placeholder:text-zinc-400 dark:[text-shadow:0_1px_3px_rgba(0,0,0,0.85)] md:text-2xl';

const articleBannerHeadingClass = 'min-w-0 flex-1 break-words text-xl font-semibold leading-tight text-zinc-900 [text-shadow:0_1px_0_rgba(255,255,255,0.9)] dark:text-zinc-50 dark:[text-shadow:0_1px_3px_rgba(0,0,0,0.85)] md:text-2xl';

const articleBannerCoverButtonClass = 'rounded-md bg-white/90 px-2 py-0.5 text-[10px] font-medium leading-tight text-zinc-700 shadow-sm backdrop-blur hover:bg-white dark:bg-zinc-900/90 dark:text-zinc-200 dark:hover:bg-zinc-900';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-book', mainFill: true, showFlashBanner: false }, () => page),
});

const props = defineProps({
    articlesTree: {
        type: Array,
        default: () => [],
    },
    articleOptions: {
        type: Array,
        default: () => [],
    },
    bookViews: {
        type: Array,
        default: () => [],
    },
    activeBookView: {
        type: Object,
        default: () => ({ slug: 'tree', label: 'Дерево', layout: 'tree', filters: {} }),
    },
    bookViewRows: {
        type: Array,
        default: () => [],
    },
    bookSearch: {
        type: Object,
        default: () => ({ query: '', filters: {}, active: false }),
    },
    salesBookPropertyCatalog: {
        type: Array,
        default: () => [],
    },
    selectedArticle: {
        type: Object,
        default: null,
    },
    directChildPages: {
        type: Array,
        default: () => [],
    },
    capabilities: {
        type: Object,
        default: () => ({ can_read: false, can_comment: false, can_write: false }),
    },
    articleFeedbackSummary: {
        type: Object,
        default: null,
    },
    articleStatusOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const createForm = useForm({
    title: '',
    parent_id: '',
});

const importForm = useForm({
    file: null,
    parent_id: '',
});

const editForm = useForm({
    title: '',
    markdown_content: '',
    parent_id: '',
    status: 'published',
    tags_text: '',
    properties: {},
});

const feedbackForm = useForm({
    rating: '',
    comment: '',
});

const contentDirty = ref(false);
const copyLinkFeedback = ref(false);
const editEditorRef = ref(null);
const coverInputRef = ref(null);
const coverUploading = ref(false);
const coverError = ref('');
const readerPreview = ref(false);
const sidebarSearch = ref(props.bookSearch?.query ?? '');
const sidebarFilters = ref({
    audience_role: props.bookSearch?.filters?.audience_role ?? '',
    sales_stage: props.bookSearch?.filters?.sales_stage ?? '',
    product_area: props.bookSearch?.filters?.product_area ?? '',
});
let sidebarSearchTimer = null;
const bookPartialReloadProps = [
    'selectedArticle',
    'articlesTree',
    'articleOptions',
    'bookViews',
    'activeBookView',
    'bookViewRows',
    'bookSearch',
    'salesBookPropertyCatalog',
    'directChildPages',
    'articleFeedbackSummary',
];
const collectionInsertForm = ref({
    title: '',
    view_slug: 'manager-materials',
    limit: 8,
    layout: 'list',
    filters: {
        audience_role: '',
        sales_stage: '',
        product_area: '',
    },
});

const readonlyEditorKey = computed(() => {
    if (!props.selectedArticle) {
        return 'readonly-empty';
    }

    return `readonly-${props.selectedArticle.id}-${props.selectedArticle.updated_at ?? 'none'}`;
});

const editEditorKey = computed(() => {
    if (!props.selectedArticle) {
        return 'edit-empty';
    }

    return `edit-${props.selectedArticle.id}-${props.selectedArticle.updated_at ?? 'none'}`;
});

const editorMarkdown = computed(() => {
    if (contentDirty.value) {
        return editForm.markdown_content;
    }

    return props.selectedArticle?.markdown_content ?? '';
});

const flatArticles = computed(() => flattenTree(props.articlesTree));
const indentedArticleOptions = computed(() => flatArticles.value.map((entry) => ({
    id: entry.id,
    label: `${'\u00A0'.repeat(entry.depth * 2)}${entry.depth > 0 ? '↳ ' : ''}${entry.title}`,
})));

const parentOptionsForEdit = computed(() => {
    if (!props.selectedArticle) {
        return indentedArticleOptions.value;
    }

    const blockedIds = new Set([
        props.selectedArticle.id,
        ...collectDescendantIds(props.selectedArticle.id, props.articleOptions),
    ]);

    return indentedArticleOptions.value.filter((option) => !blockedIds.has(option.id));
});

const selectedArticleTags = computed(() => normalizeTags(props.selectedArticle?.tags ?? []));
const selectedArticleQuiz = computed(() => props.selectedArticle?.quiz ?? null);
const selectedArticleEmbeddedCollections = computed(() => props.selectedArticle?.embedded_collections ?? []);
const propertyDefinitions = computed(() => props.salesBookPropertyCatalog ?? []);
const collectionInsertViews = computed(() => props.bookViews.filter((view) => view.slug !== 'tree'));
const sidebarFilterDefinitions = computed(() => propertyDefinitions.value.filter((definition) => [
    'audience_role',
    'sales_stage',
    'product_area',
].includes(definition.key)));
const selectedArticlePropertyBadges = computed(() => propertyDefinitions.value
    .map((definition) => {
        const value = props.selectedArticle?.properties?.[definition.key] ?? '';
        const option = definition.options?.find((candidate) => candidate.value === value);

        return option ? { key: definition.key, label: definition.label, value: option.label } : null;
    })
    .filter(Boolean));
const groupedRowsByStage = computed(() => {
    const stageDefinition = propertyDefinitions.value.find((definition) => definition.key === 'sales_stage');
    const labels = new Map((stageDefinition?.options ?? []).map((option) => [option.value, option.label]));
    const groups = new Map();

    props.bookViewRows.forEach((row) => {
        const value = row.properties?.sales_stage || 'without-stage';
        const label = labels.get(value) ?? 'Без этапа';
        const group = groups.get(value) ?? { value, label, rows: [] };
        group.rows.push(row);
        groups.set(value, group);
    });

    return Array.from(groups.values());
});
const sidebarFiltersActive = computed(() => {
    if (sidebarSearch.value.trim() !== '') {
        return true;
    }

    return Object.values(sidebarFilters.value).some((value) => value !== '');
});
const navigationRows = computed(() => {
    if (props.activeBookView.layout === 'tree' && !sidebarFiltersActive.value) {
        return props.articleOptions.map((article) => ({
            id: article.id,
            title: article.title,
            tags: normalizeTags(article.tags ?? []),
            properties: article.properties ?? {},
        }));
    }

    return props.bookViewRows;
});
const filteredBookViewRows = computed(() => props.bookViewRows);
const filteredNavigationRows = computed(() => navigationRows.value);

watch(
    () => props.selectedArticle,
    (value, oldValue) => {
        if (!value) {
            return;
        }

        const articleChanged = value.id !== oldValue?.id;
        const serverMarkdownChanged = value.markdown_content !== oldValue?.markdown_content;

        editForm.defaults({
            title: value.title ?? '',
            markdown_content: value.markdown_content ?? '',
            parent_id: value.parent_id ? String(value.parent_id) : '',
            status: value.status ?? 'published',
            tags_text: formatTags(value.tags ?? []),
            properties: normalizeProperties(value.properties ?? {}),
        });

        if (articleChanged || serverMarkdownChanged) {
            contentDirty.value = false;
            editForm.reset();
        } else {
            editForm.title = value.title ?? '';
            editForm.parent_id = value.parent_id ? String(value.parent_id) : '';
            editForm.status = value.status ?? 'published';
            editForm.tags_text = formatTags(value.tags ?? []);
            editForm.properties = normalizeProperties(value.properties ?? {});
        }

        if (articleChanged) {
            readerPreview.value = false;
        }
    },
    { immediate: true },
);

watch(
    [sidebarSearch, sidebarFilters],
    () => {
        if (sidebarSearchTimer) {
            window.clearTimeout(sidebarSearchTimer);
        }

        sidebarSearchTimer = window.setTimeout(() => {
            applyBackendSearch();
        }, 350);
    },
    { deep: true },
);

onBeforeUnmount(() => {
    if (sidebarSearchTimer) {
        window.clearTimeout(sidebarSearchTimer);
    }
});

function onEditorUpdate(markdown) {
    const serverMarkdown = props.selectedArticle?.markdown_content ?? '';

    if (!contentDirty.value && markdown.trim() === '' && serverMarkdown.trim() !== '') {
        return;
    }

    editForm.markdown_content = markdown;
    contentDirty.value = true;
}

function flattenTree(nodes, depth = 0) {
    return nodes.flatMap((node) => {
        const current = {
            id: node.id,
            title: node.title,
            depth,
            parent_id: node.parent_id ?? null,
            sort_order: node.sort_order ?? 0,
        };
        const children = flattenTree(node.children ?? [], depth + 1);

        return [current, ...children];
    });
}

function collectDescendantIds(articleId, options) {
    const childrenByParent = new Map();

    options.forEach((option) => {
        if (option.parent_id === null || option.parent_id === undefined) {
            return;
        }

        const parentId = Number(option.parent_id);
        const current = childrenByParent.get(parentId) ?? [];
        current.push(Number(option.id));
        childrenByParent.set(parentId, current);
    });

    const descendants = [];
    const queue = [...(childrenByParent.get(Number(articleId)) ?? [])];

    while (queue.length > 0) {
        const childId = queue.shift();
        descendants.push(childId);
        queue.push(...(childrenByParent.get(childId) ?? []));
    }

    return descendants;
}

function normalizeParentId(value) {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    return Number(value);
}

function withNormalizedParent(form) {
    return form.transform((data) => ({
        ...data,
        parent_id: normalizeParentId(data.parent_id),
        tags: parseTags(data.tags_text ?? ''),
    }));
}

function parseTags(value) {
    if (typeof value !== 'string') {
        return [];
    }

    return normalizeTags(value.split(','));
}

function normalizeTags(tags) {
    if (!Array.isArray(tags)) {
        return [];
    }

    const seen = new Set();

    return tags
        .map((tag) => String(tag ?? '').trim())
        .filter((tag) => tag.length > 0)
        .map((tag) => tag.slice(0, 50))
        .filter((tag) => {
            const key = tag.toLocaleLowerCase('ru-RU');

            if (seen.has(key)) {
                return false;
            }

            seen.add(key);

            return true;
        })
        .slice(0, 20);
}

function normalizeProperties(properties) {
    const source = properties && typeof properties === 'object' ? properties : {};

    return Object.fromEntries(propertyDefinitions.value.map((definition) => [
        definition.key,
        source[definition.key] ?? '',
    ]));
}

function propertyLabelForRow(row, key) {
    const directLabel = row.property_labels?.[key];
    if (directLabel) {
        return directLabel;
    }

    const value = row.properties?.[key] ?? '';
    const definition = propertyDefinitions.value.find((candidate) => candidate.key === key);
    const option = definition?.options?.find((candidate) => candidate.value === value);

    return option?.label ?? '';
}

function resetSidebarFilters() {
    sidebarSearch.value = '';
    sidebarFilters.value = {
        audience_role: '',
        sales_stage: '',
        product_area: '',
    };
}

function cleanSidebarFilters() {
    return Object.fromEntries(Object.entries(sidebarFilters.value)
        .filter(([, value]) => value !== ''));
}

function bookSearchParams(overrides = {}) {
    const filters = cleanSidebarFilters();
    const query = sidebarSearch.value.trim();

    return {
        view: overrides.viewSlug ?? props.activeBookView.slug,
        article_id: overrides.articleId ?? props.selectedArticle?.id ?? undefined,
        q: query !== '' ? query : undefined,
        filters: Object.keys(filters).length > 0 ? filters : undefined,
    };
}

function applyBackendSearch() {
    router.get(route('sales-assistant.book'), bookSearchParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: bookPartialReloadProps,
    });
}

function formatTags(tags) {
    return normalizeTags(tags).join(', ');
}

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString();
}

function insertEmbeddedCollection() {
    const view = collectionInsertViews.value.find((candidate) => candidate.slug === collectionInsertForm.value.view_slug)
        ?? collectionInsertViews.value[0];

    if (!view) {
        return;
    }

    const filters = Object.fromEntries(Object.entries(collectionInsertForm.value.filters)
        .filter(([, value]) => value !== ''));
    const limit = Math.min(30, Math.max(1, Number(collectionInsertForm.value.limit) || 8));
    const payload = {
        title: collectionInsertForm.value.title.trim() || view.label,
        view_slug: view.slug,
        limit,
        layout: collectionInsertForm.value.layout || (view.layout === 'stage' ? 'grouped' : 'list'),
    };

    if (Object.keys(filters).length > 0) {
        payload.filters = filters;
    }

    const directive = `\n\n\`\`\`sales-book-view\n${JSON.stringify(payload, null, 2)}\n\`\`\`\n`;

    if (editEditorRef.value?.insertMarkdown) {
        editEditorRef.value.insertMarkdown(directive);
        contentDirty.value = true;

        return;
    }

    editForm.markdown_content = `${editForm.markdown_content ?? ''}${directive}`;
    contentDirty.value = true;
}

function openArticle(articleId) {
    router.get(route('sales-assistant.book'), bookSearchParams({ articleId }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: bookPartialReloadProps,
    });
}

function openBookView(viewSlug) {
    router.get(route('sales-assistant.book'), bookSearchParams({
        viewSlug,
        articleId: props.selectedArticle?.id ?? undefined,
    }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: bookPartialReloadProps,
    });
}

function uploadCover(event) {
    const file = event.target.files?.[0] ?? null;

    if (!file || !props.selectedArticle?.id) {
        return;
    }

    coverError.value = '';
    coverUploading.value = true;

    router.post(route('sales-assistant.book.articles.cover.upload', props.selectedArticle.id), {
        file,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => {
            coverError.value = errors?.file ?? 'Не удалось загрузить обложку.';
        },
        onFinish: () => {
            coverUploading.value = false;

            if (event.target) {
                event.target.value = '';
            }
        },
    });
}

function destroyCover() {
    if (!props.selectedArticle?.id) {
        return;
    }

    coverError.value = '';
    coverUploading.value = true;

    router.delete(route('sales-assistant.book.articles.cover.destroy', props.selectedArticle.id), {
        preserveScroll: true,
        onFinish: () => {
            coverUploading.value = false;
        },
    });
}

async function copyArticleLink() {
    if (!props.selectedArticle) {
        return;
    }

    const url = route('sales-assistant.book', { article_id: props.selectedArticle.id });

    try {
        await navigator.clipboard.writeText(url);
        copyLinkFeedback.value = true;
        window.setTimeout(() => {
            copyLinkFeedback.value = false;
        }, 2000);
    } catch {
        window.prompt('Скопируйте ссылку', url);
    }
}

function createArticle() {
    withNormalizedParent(createForm).post(route('sales-assistant.book.articles.store'));
}

function createUntitled() {
    createForm.title = 'Без названия';
    createForm.parent_id = null;
    createArticle();
}

function onFileChange(event) {
    importForm.file = event.target.files?.[0] ?? null;
}

function importMarkdown() {
    withNormalizedParent(importForm).post(route('sales-assistant.book.import'), {
        forceFormData: true,
    });
}

function saveArticle() {
    if (!props.selectedArticle) {
        return;
    }

    const markdownContent = editEditorRef.value?.getMarkdown?.() ?? editForm.markdown_content;

    router.patch(route('sales-assistant.book.articles.update', props.selectedArticle.id), {
        title: editForm.title,
        parent_id: normalizeParentId(editForm.parent_id),
        status: editForm.status,
        tags: parseTags(editForm.tags_text),
        properties: editForm.properties,
        content_format: props.selectedArticle.content_format ?? 'markdown',
        markdown_content: markdownContent,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            contentDirty.value = false;
        },
    });
}

function moveArticle(payload) {
    router.patch(route('sales-assistant.book.articles.move', payload.id), {
        parent_id: payload.parent_id,
        sort_order: payload.sort_order,
    }, {
        preserveScroll: true,
        only: ['articlesTree', 'articleOptions', 'selectedArticle'],
    });
}

function destroyArticle() {
    if (!props.selectedArticle) {
        return;
    }

    if (!window.confirm('Удалить эту страницу?')) {
        return;
    }

    router.delete(route('sales-assistant.book.articles.destroy', props.selectedArticle.id));
}

const canWrite = computed(() => Boolean(props.capabilities?.can_write));
const canComment = computed(() => Boolean(props.capabilities?.can_comment));

function submitArticleFeedback(payload) {
    if (!props.selectedArticle?.id) {
        return;
    }

    feedbackForm.rating = payload.rating;
    feedbackForm.comment = payload.comment ?? '';
    feedbackForm.post(route('sales-assistant.book.articles.feedback', props.selectedArticle.id), {
        preserveScroll: true,
        onSuccess: () => {
            feedbackForm.reset();
        },
    });
}
</script>

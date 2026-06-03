<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="grid min-h-0 flex-1 gap-4 overflow-hidden grid-rows-[minmax(0,38vh)_minmax(0,1fr)] lg:grid-cols-[320px,minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)]">
        <aside :class="`${crmPanel} flex min-h-0 flex-col overflow-hidden p-4`">
            <div class="shrink-0">
                <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Книга продаж</h1>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Пространство в стиле Notion: вложенные страницы, импорт markdown и визуальный редактор.</p>
            </div>

            <section
                v-if="canWrite && feedbackProblemArticles.length > 0"
                class="mt-4 shrink-0 rounded-xl border border-amber-200 bg-amber-50/80 p-3 dark:border-amber-900/70 dark:bg-amber-950/30"
            >
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-100">
                        Требует правки
                    </h2>
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-900 dark:bg-amber-900/60 dark:text-amber-100">
                        {{ feedbackProblemArticles.length }}
                    </span>
                </div>
                <div class="mt-2 space-y-1.5">
                    <button
                        v-for="article in feedbackProblemArticles"
                        :key="`problem-${article.id}`"
                        type="button"
                        class="w-full rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-amber-100/80 dark:hover:bg-amber-900/40"
                        @click="openArticle(article.id)"
                    >
                        <span class="block truncate text-xs font-medium text-zinc-900 dark:text-zinc-100">
                            {{ article.title }}
                        </span>
                        <span class="mt-0.5 block text-[11px] text-amber-800 dark:text-amber-200">
                            Негативных: {{ article.negative }}
                            <template v-if="article.unclear"> · непонятно {{ article.unclear }}</template>
                            <template v-if="article.outdated"> · устарело {{ article.outdated }}</template>
                            <template v-if="article.command_bar"> · из AI {{ article.command_bar }}</template>
                        </span>
                    </button>
                </div>
            </section>

            <section
                v-if="canWrite && qualityInsights"
                class="mt-3 shrink-0 rounded-xl border border-zinc-200 bg-white/80 p-3 dark:border-zinc-800 dark:bg-zinc-900/70"
            >
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-200">
                        Качество базы
                    </h2>
                    <span class="text-[11px] text-zinc-500 dark:text-zinc-400">30 дней</span>
                </div>

                <div class="mt-2 grid grid-cols-3 gap-1.5 text-center">
                    <div class="rounded-lg bg-zinc-50 px-2 py-1.5 dark:bg-zinc-800/70">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ qualityInsights.summary?.published_articles ?? 0 }}</div>
                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400">опубл.</div>
                    </div>
                    <div class="rounded-lg bg-amber-50 px-2 py-1.5 dark:bg-amber-950/40">
                        <div class="text-sm font-semibold text-amber-900 dark:text-amber-100">{{ qualityInsights.summary?.draft_articles ?? 0 }}</div>
                        <div class="text-[10px] text-amber-700 dark:text-amber-200">черн.</div>
                    </div>
                    <div class="rounded-lg bg-rose-50 px-2 py-1.5 dark:bg-rose-950/40">
                        <div class="text-sm font-semibold text-rose-900 dark:text-rose-100">{{ negativeFeedbackTotal }}</div>
                        <div class="text-[10px] text-rose-700 dark:text-rose-200">сигналов</div>
                    </div>
                </div>

                <div v-if="qualityInsights.hints?.length" class="mt-2 space-y-1">
                    <p
                        v-for="hint in qualityInsights.hints"
                        :key="hint"
                        class="rounded-lg bg-zinc-50 px-2 py-1.5 text-[11px] text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-300"
                    >
                        {{ hint }}
                    </p>
                </div>

                <div v-if="qualityInsights.recent_feedback?.length" class="mt-2 space-y-1.5">
                    <p class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Последние замечания</p>
                    <button
                        v-for="item in qualityInsights.recent_feedback"
                        :key="`feedback-${item.id}`"
                        type="button"
                        class="w-full rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        @click="openArticle(item.article_id)"
                    >
                        <span class="block truncate text-xs font-medium text-zinc-800 dark:text-zinc-100">{{ item.article_title }}</span>
                        <span class="mt-0.5 block truncate text-[11px] text-zinc-500 dark:text-zinc-400">
                            {{ item.rating_label }} · {{ item.source_label }}<template v-if="item.comment"> · {{ item.comment }}</template>
                        </span>
                    </button>
                </div>
            </section>

            <section
                v-if="canViewQuizAnalytics && quizInsights"
                class="mt-3 shrink-0 rounded-xl border border-sky-200 bg-sky-50/70 p-3 dark:border-sky-900/60 dark:bg-sky-950/25"
            >
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-sky-900 dark:text-sky-100">
                        Статистика тестов
                    </h2>
                    <span class="text-[11px] text-sky-700 dark:text-sky-300">30 дней</span>
                </div>

                <div class="mt-2 grid grid-cols-3 gap-1.5 text-center">
                    <div class="rounded-lg bg-white/80 px-2 py-1.5 dark:bg-zinc-900/70">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ quizInsights.summary?.attempts ?? 0 }}</div>
                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400">попыток</div>
                    </div>
                    <div class="rounded-lg bg-white/80 px-2 py-1.5 dark:bg-zinc-900/70">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ quizInsights.summary?.unique_users ?? 0 }}</div>
                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400">людей</div>
                    </div>
                    <div class="rounded-lg bg-white/80 px-2 py-1.5 dark:bg-zinc-900/70">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ quizAvgPercentLabel }}</div>
                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400">ср. %</div>
                    </div>
                </div>

                <div v-if="quizInsights.by_user?.length" class="mt-2 space-y-1.5">
                    <p class="text-[11px] font-medium text-sky-800 dark:text-sky-200">По сотрудникам</p>
                    <div
                        v-for="row in quizInsights.by_user"
                        :key="`quiz-user-${row.user_id}`"
                        class="rounded-lg bg-white/80 px-2 py-1.5 dark:bg-zinc-900/70"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-xs font-medium text-zinc-900 dark:text-zinc-100">{{ row.name }}</span>
                            <span class="shrink-0 text-[11px] text-zinc-600 dark:text-zinc-300">{{ row.avg_percent }}%</span>
                        </div>
                        <p class="mt-0.5 text-[11px] text-zinc-500 dark:text-zinc-400">
                            {{ row.attempts }} попыток · лучший {{ row.best_score }}/{{ row.best_total }}
                        </p>
                    </div>
                </div>

                <div v-if="quizInsights.recent_attempts?.length" class="mt-2 space-y-1.5">
                    <p class="text-[11px] font-medium text-sky-800 dark:text-sky-200">Последние прохождения</p>
                    <button
                        v-for="item in quizInsights.recent_attempts"
                        :key="`quiz-attempt-${item.id}`"
                        type="button"
                        class="w-full rounded-lg bg-white/80 px-2 py-1.5 text-left transition-colors hover:bg-white dark:bg-zinc-900/70 dark:hover:bg-zinc-800"
                        @click="openArticle(item.article_id)"
                    >
                        <span class="block truncate text-xs font-medium text-zinc-800 dark:text-zinc-100">{{ item.user_name }}</span>
                        <span class="mt-0.5 block truncate text-[11px] text-zinc-500 dark:text-zinc-400">
                            {{ item.article_title }} · {{ item.score }}/{{ item.total_questions }} ({{ item.percent }}%)
                        </span>
                    </button>
                </div>

                <Link
                    :href="route('sales-assistant.book.quiz-analytics')"
                    class="mt-2 inline-block text-[11px] font-medium text-sky-800 underline-offset-4 hover:underline dark:text-sky-200"
                >
                    Полный отчёт →
                </Link>
            </section>

            <form v-if="canWrite" class="mt-4 shrink-0 space-y-2 border-t border-zinc-100 pt-4 dark:border-zinc-800" @submit.prevent="createArticle">
                <input
                    v-model="createForm.title"
                    type="text"
                    required
                    placeholder="Новый заголовок страницы"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                />
                <input
                    v-model="createForm.tags_text"
                    type="text"
                    placeholder="Теги через запятую"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                />
                <select
                    v-model="createForm.parent_id"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
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

            <form v-if="canWrite" class="mt-3 shrink-0 space-y-2 border-t border-zinc-100 pt-4 dark:border-zinc-800" @submit.prevent="importMarkdown">
                <input
                    type="file"
                    accept=".md,.markdown,.txt,text/markdown,text/plain"
                    @change="onFileChange"
                    class="block w-full text-xs text-zinc-600 file:mr-2 file:rounded-md file:border-0 file:bg-zinc-100 file:px-2 file:py-1 file:text-xs file:font-medium dark:text-zinc-300 dark:file:bg-zinc-800"
                />
                <select
                    v-model="importForm.parent_id"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <option value="">Импорт в корень</option>
                    <option v-for="option in indentedArticleOptions" :key="`import-${option.id}`" :value="String(option.id)">
                        {{ option.label }}
                    </option>
                </select>
                <input
                    v-model="importForm.tags_text"
                    type="text"
                    placeholder="Теги импортируемой статьи"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                />
                <button
                    type="submit"
                    :disabled="importForm.processing"
                    :class="`${crmBtnNeutral} w-full justify-center disabled:cursor-not-allowed disabled:opacity-60`"
                >
                    Импорт .md
                </button>
            </form>

            <div class="mt-4 min-h-0 flex-1 overflow-y-auto border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <p v-if="articlesTree.length === 0" class="text-sm text-zinc-500">Пока нет страниц.</p>
                <SalesBookTreeNav
                    v-else
                    :tree="articlesTree"
                    :article-options="articleOptions"
                    :selected-id="selectedArticle?.id ?? null"
                    :can-write="canWrite"
                    @select="openArticle"
                    @move="moveArticle"
                />
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
                    <div class="relative h-20 shrink-0 overflow-hidden rounded-xl border border-zinc-200 bg-gradient-to-r from-sky-100 via-indigo-100 to-amber-100 dark:border-zinc-800 dark:from-sky-950 dark:via-indigo-950 dark:to-amber-950 md:h-24">
                        <img
                            v-if="selectedArticle.cover_image_url"
                            :src="selectedArticle.cover_image_url"
                            alt=""
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="h-full w-full bg-[radial-gradient(circle_at_20%_20%,rgba(14,165,233,0.32),transparent_30%),linear-gradient(120deg,rgba(59,130,246,0.18),rgba(245,158,11,0.22))]" />

                        <div class="absolute bottom-2 right-2 flex gap-2">
                            <input
                                ref="coverInputRef"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="uploadCover"
                            />
                            <button
                                type="button"
                                class="rounded-lg bg-white/90 px-2.5 py-1 text-xs font-medium text-zinc-700 shadow-sm backdrop-blur hover:bg-white dark:bg-zinc-900/90 dark:text-zinc-200 dark:hover:bg-zinc-900"
                                :disabled="coverUploading"
                                @click="coverInputRef?.click()"
                            >
                                {{ selectedArticle.cover_image_url ? 'Заменить обложку' : 'Загрузить обложку' }}
                            </button>
                            <button
                                v-if="selectedArticle.cover_image_url"
                                type="button"
                                class="rounded-lg bg-white/90 px-2.5 py-1 text-xs font-medium text-rose-700 shadow-sm backdrop-blur hover:bg-white dark:bg-zinc-900/90 dark:text-rose-200 dark:hover:bg-zinc-900"
                                :disabled="coverUploading"
                                @click="destroyCover"
                            >
                                Убрать
                            </button>
                        </div>
                    </div>
                    <p v-if="coverError" class="shrink-0 text-xs text-rose-600 dark:text-rose-300">{{ coverError }}</p>
                    <p v-else class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">Рекомендуемая обложка: широкое изображение с пропорцией 8–10:1.</p>

                    <div class="flex shrink-0 items-start gap-2">
                        <input
                            v-model="editForm.title"
                            type="text"
                            required
                            placeholder="Заголовок страницы"
                            class="min-w-0 flex-1 border-0 border-b border-zinc-200 bg-transparent px-0 py-2 text-3xl font-semibold text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:ring-0 dark:border-zinc-700 dark:text-zinc-100"
                        />
                        <button
                            type="button"
                            :class="`${crmBtnNeutral} shrink-0 px-3 py-2 text-xs`"
                            :title="copyLinkFeedback ? 'Скопировано' : 'Копировать ссылку на страницу'"
                            @click="copyArticleLink"
                        >
                            {{ copyLinkFeedback ? 'Скопировано' : 'Ссылка' }}
                        </button>
                    </div>

                    <SalesBookArticleFeedbackBar
                        v-if="canComment"
                        :article-id="selectedArticle.id"
                        :summary="articleFeedbackSummary"
                        :busy="feedbackForm.processing"
                        @rate="submitArticleFeedback"
                    />

                    <div class="flex shrink-0 flex-wrap items-center gap-2 text-xs text-zinc-500">
                        <span>Родитель:</span>
                        <select
                            v-model="editForm.parent_id"
                            class="rounded-md border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <option value="">Корень</option>
                            <option v-for="option in parentOptionsForEdit" :key="`edit-${option.id}`" :value="String(option.id)">
                                {{ option.label }}
                            </option>
                        </select>
                        <span>Статус:</span>
                        <select
                            v-model="editForm.status"
                            class="rounded-md border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900"
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

                    <div class="h-20 shrink-0 overflow-hidden rounded-xl border border-zinc-200 bg-gradient-to-r from-sky-100 via-indigo-100 to-amber-100 dark:border-zinc-800 dark:from-sky-950 dark:via-indigo-950 dark:to-amber-950 md:h-24">
                        <img
                            v-if="selectedArticle.cover_image_url"
                            :src="selectedArticle.cover_image_url"
                            alt=""
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="h-full w-full bg-[radial-gradient(circle_at_20%_20%,rgba(14,165,233,0.32),transparent_30%),linear-gradient(120deg,rgba(59,130,246,0.18),rgba(245,158,11,0.22))]" />
                    </div>

                    <div class="flex shrink-0 items-start gap-2">
                        <h2 class="min-w-0 flex-1 border-0 border-b border-zinc-200 px-0 py-2 text-3xl font-semibold text-zinc-900 dark:border-zinc-700 dark:text-zinc-100">
                            {{ selectedArticle.title }}
                        </h2>
                        <span
                            v-if="selectedArticle.status === 'draft'"
                            class="mt-2 shrink-0 rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                        >
                            Черновик
                        </span>
                        <button
                            type="button"
                            :class="`${crmBtnNeutral} shrink-0 px-3 py-2 text-xs`"
                            :title="copyLinkFeedback ? 'Скопировано' : 'Копировать ссылку на страницу'"
                            @click="copyArticleLink"
                        >
                            {{ copyLinkFeedback ? 'Скопировано' : 'Ссылка' }}
                        </button>
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

                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <TiptapEditor
                            :key="readonlyEditorKey"
                            class="min-h-[12rem]"
                            :model-value="selectedArticle.markdown_content_display"
                            :upload-url="route('sales-assistant.book.assets.upload')"
                            :editable="false"
                            placeholder=""
                        />

                        <SalesBookQuiz
                            v-if="selectedArticleQuiz"
                            :key="`quiz-${selectedArticle.id}`"
                            class="mt-4"
                            :article-id="selectedArticle.id"
                            :quiz="selectedArticleQuiz"
                            @attempt-recorded="reloadQuizInsights"
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
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import TiptapEditor from '@/Components/SalesBook/TiptapEditor.vue';
import SalesBookArticleFeedbackBar from '@/Components/SalesBook/SalesBookArticleFeedbackBar.vue';
import SalesBookQuiz from '@/Components/SalesBook/SalesBookQuiz.vue';
import SalesBookTreeNav from '@/Components/SalesBook/SalesBookTreeNav.vue';
import { crmBtnCreate, crmBtnNeutral, crmBtnPrimary, crmPanel } from '@/support/crmUi.js';

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
    feedbackProblemArticles: {
        type: Array,
        default: () => [],
    },
    qualityInsights: {
        type: Object,
        default: null,
    },
    quizInsights: {
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
    tags_text: '',
});

const importForm = useForm({
    file: null,
    parent_id: '',
    tags_text: '',
});

const editForm = useForm({
    title: '',
    markdown_content: '',
    parent_id: '',
    status: 'published',
    tags_text: '',
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
const negativeFeedbackTotal = computed(() => (
    Number(props.qualityInsights?.summary?.unclear ?? 0)
    + Number(props.qualityInsights?.summary?.outdated ?? 0)
));

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
        });

        if (articleChanged || serverMarkdownChanged) {
            contentDirty.value = false;
            editForm.reset();
        } else {
            editForm.title = value.title ?? '';
            editForm.parent_id = value.parent_id ? String(value.parent_id) : '';
            editForm.status = value.status ?? 'published';
            editForm.tags_text = formatTags(value.tags ?? []);
        }

        if (articleChanged) {
            readerPreview.value = false;
        }
    },
    { immediate: true },
);

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
        tags: parseTags(data.tags_text),
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

function formatTags(tags) {
    return normalizeTags(tags).join(', ');
}

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString();
}

function openArticle(articleId) {
    router.get(route('sales-assistant.book'), { article_id: articleId }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['selectedArticle', 'articlesTree', 'articleOptions', 'directChildPages', 'articleFeedbackSummary', 'feedbackProblemArticles', 'qualityInsights', 'quizInsights'],
    });
}

function reloadQuizInsights() {
    if (!canViewQuizAnalytics.value) {
        return;
    }

    router.reload({
        only: ['quizInsights'],
        preserveScroll: true,
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
const canViewQuizAnalytics = computed(() => Boolean(props.capabilities?.can_view_quiz_analytics));

const quizAvgPercentLabel = computed(() => {
    const value = props.quizInsights?.summary?.avg_percent;

    if (value === null || value === undefined) {
        return '—';
    }

    return `${value}%`;
});

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

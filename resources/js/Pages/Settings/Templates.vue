<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden lg:min-h-0">
        <CrmPageHeader
            lead="Жесткие системные формы и внешние DOCX-шаблоны заказчиков и перевозчиков."
            title="Шаблоны"
            :title-class="crmPageTitleSm"
        >
            <template #actions>
                <button
                    v-if="pageTab === 'templates'"
                    type="button"
                    :class="crmBtnNeutral"
                    @click="openCreateModal"
                >
                    <Plus class="h-4 w-4" />
                    Добавить шаблон
                </button>
            </template>
        </CrmPageHeader>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                :class="pageTab === 'templates' ? crmPillActive : crmPill"
                @click="switchPageTab('templates')"
            >
                DOCX-шаблоны
            </button>
            <button
                type="button"
                :class="pageTab === 'basic-terms' ? crmPillActive : crmPill"
                @click="switchPageTab('basic-terms')"
            >
                Базовые условия для договоров-заявок
            </button>
        </div>

        <template v-if="pageTab === 'templates'">
        <div
            v-if="!documentPreview.pdf_preview_available"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
            role="status"
        >
            {{ documentPreview.hint }}
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            <section :class="`${crmStatCard} p-4`">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Всего шаблонов</div>
                <div class="mt-2 text-2xl font-semibold">{{ templates.length }}</div>
            </section>
            <section :class="`${crmStatCard} p-4`">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">DOCX контрагентов</div>
                <div class="mt-2 text-2xl font-semibold">{{ externalTemplateCount }}</div>
            </section>
            <section :class="`${crmStatCard} p-4`">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">По умолчанию</div>
                <div class="mt-2 text-2xl font-semibold">{{ defaultTemplateCount }}</div>
            </section>
        </div>

        <div :class="`${crmPanel} min-h-0 flex-1 overflow-hidden`">
            <div class="h-full overflow-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-zinc-100 dark:bg-zinc-800">
                        <tr class="text-left text-zinc-600 dark:text-zinc-200">
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Код</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Шаблон</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Сущность</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Тип</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Назначение</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Источник</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Статус</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Плейсхолдеры</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="template in templates"
                            :key="template.id"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td class="px-3 py-3 font-mono text-xs text-zinc-500">{{ template.code }}</td>
                            <td class="px-3 py-3">
                                <div class="font-medium">{{ template.name }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ documentGroupLabel(template.document_group) }}
                                    <span v-if="template.original_filename">• {{ template.original_filename }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-3">{{ entityTypeLabel(template.entity_type) }}</td>
                            <td class="px-3 py-3">
                                <div>{{ documentTypeLabel(template.document_type) }}</div>
                                <div class="text-xs text-zinc-500">{{ partyLabel(template.party) }}</div>
                            </td>
                            <td class="px-3 py-3">
                                <div v-if="template.own_company_name" class="font-medium">{{ template.own_company_name }}</div>
                                <div v-else class="text-zinc-500">Любая своя компания</div>
                                <div class="text-xs text-zinc-500">{{ transportScopeLabel(template.transport_scope) }}</div>
                                <div v-if="template.source_type === 'external_docx' && template.contractor_name" class="text-xs text-zinc-500">{{ template.contractor_name }}</div>
                                <div v-if="template.is_default" class="text-xs text-emerald-600 dark:text-emerald-300">По умолчанию</div>
                            </td>
                            <td class="px-3 py-3">
                                <div>{{ sourceTypeLabel(template.source_type) }}</div>
                                <div class="text-xs text-zinc-500">{{ template.has_source_file ? 'Файл загружен' : 'Без файла' }}</div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="template.is_active
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                            : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'"
                                    >
                                        {{ template.is_active ? 'Активен' : 'Выключен' }}
                                    </span>
                                    <span
                                        class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                                    >
                                        v{{ template.version }}
                                    </span>
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="pipelineStatusClass(template.pipeline_status)"
                                    >
                                        {{ pipelineStatusLabel(template.pipeline_status) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div v-if="template.variables.length > 0" class="flex max-w-md flex-wrap gap-1.5">
                                    <span
                                        v-for="variable in template.variables.slice(0, 4)"
                                        :key="variable"
                                        class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                                    >
                                        {{ variable }}
                                    </span>
                                    <span
                                        v-if="template.variables.length > 4"
                                        class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"
                                    >
                                        +{{ template.variables.length - 4 }}
                                    </span>
                                </div>
                                <div v-else class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Не обнаружены
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="template.has_source_file"
                                        type="button"
                                        class="rounded-lg border border-emerald-200 p-2 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                                        @click="previewTemplateFromRow(template)"
                                    >
                                        <FileText class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-zinc-200 p-2 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                        @click="openEditModal(template)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200 p-2 text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                        @click="removeTemplate(template)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="templates.length === 0">
                            <td colspan="9" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                Шаблоны пока не добавлены. Начни с DOCX-формы контрагента или системного шаблона.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        </template>

        <section
            v-else-if="pageTab === 'basic-terms'"
            :class="`${crmPanel} flex min-h-0 flex-1 flex-col gap-4 overflow-hidden p-4`"
        >
            <div
                v-if="!basicTermsEditor.enabled"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
            >
                Таблица базовых условий ещё не создана. Выполните миграции базы данных.
            </div>

            <template v-else>
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in basicTermsEditor.partyOptions"
                            :key="option.value"
                            type="button"
                            :class="basicTermsParty === option.value ? crmPillActive : crmPill"
                            @click="switchBasicTermsParty(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                        Общих пунктов: {{ basicTermsEditor.globalRowCounts?.[basicTermsParty] ?? 0 }}
                    </div>
                </div>

                <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(280px,360px)]">
                    <div class="flex min-h-0 flex-col gap-3 overflow-hidden">
                        <div class="grid shrink-0 gap-2 sm:grid-cols-[minmax(0,1fr)] sm:items-end">
                            <label class="block text-sm">
                                <span class="mb-1 block text-zinc-600 dark:text-zinc-300">Область редактирования</span>
                                <select
                                    v-model="basicTermsContractorId"
                                    :class="crmFieldFluid"
                                    @change="reloadBasicTermsScope"
                                >
                                    <option :value="null">Общие условия (по умолчанию для всех)</option>
                                    <option
                                        v-for="contractor in contractorOptions"
                                        :key="contractor.id"
                                        :value="contractor.id"
                                    >
                                        {{ contractor.name }}
                                    </option>
                                </select>
                            </label>
                        </div>

                        <p class="shrink-0 text-xs leading-snug text-zinc-500 dark:text-zinc-400">
                            <template v-if="basicTermsContractorId">
                                Условия для выбранного контрагента перекрывают общие при печати договора-заявки с его участием.
                                Пустой список — контрагент использует общие условия.
                            </template>
                            <template v-else>
                                Эти пункты подставляются в DOCX для всех заказчиков или перевозчиков, если нет переопределения на контрагента или в заказе.
                            </template>
                        </p>

                        <div class="min-h-0 flex-1 space-y-1.5 overflow-y-auto overscroll-contain pr-1">
                            <div
                                v-for="(row, index) in basicTermsForm.items"
                                :key="row.key"
                                class="flex items-start gap-2 rounded-lg border border-zinc-200 bg-white px-2 py-1.5 dark:border-zinc-700 dark:bg-zinc-900/40"
                            >
                                <span
                                    class="mt-1.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                                    aria-hidden="true"
                                >
                                    {{ index + 1 }}
                                </span>
                                <textarea
                                    v-model="row.body"
                                    rows="2"
                                    :class="`${crmFieldFluid} min-h-[2.5rem] flex-1 resize-y py-1.5 text-sm leading-snug`"
                                    placeholder="Текст пункта базовых условий"
                                />
                                <div class="flex shrink-0 items-center gap-0.5 pt-0.5">
                                    <button
                                        type="button"
                                        class="rounded-md border border-zinc-200 p-1 hover:bg-zinc-50 disabled:opacity-40 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                        :disabled="index === 0"
                                        :title="'Выше'"
                                        @click="moveBasicTermRow(index, -1)"
                                    >
                                        <ChevronUp class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-zinc-200 p-1 hover:bg-zinc-50 disabled:opacity-40 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                        :disabled="index === basicTermsForm.items.length - 1"
                                        :title="'Ниже'"
                                        @click="moveBasicTermRow(index, 1)"
                                    >
                                        <ChevronDown class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-rose-200 p-1 text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                        title="Удалить"
                                        @click="removeBasicTermRow(index)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="basicTermsForm.items.length === 0"
                                class="rounded-lg border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
                            >
                                Пункты не заданы. Добавьте первый пункт или сохраните пустой список, чтобы использовать только общие условия.
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2 border-t border-zinc-200 bg-white pt-3 dark:border-zinc-800 dark:bg-zinc-900">
                            <button
                                type="button"
                                :class="crmBtnCreate"
                                :disabled="basicTermsForm.processing"
                                @click="saveBasicTerms"
                            >
                                Сохранить условия
                            </button>
                            <button type="button" :class="crmBtnNeutral" @click="addBasicTermRow">
                                <Plus class="h-4 w-4" />
                                Пункт
                            </button>
                            <span v-if="basicTermsForm.processing" class="text-sm text-zinc-500">Сохранение…</span>
                            <span v-if="basicTermsForm.hasErrors" class="text-sm text-rose-600 dark:text-rose-300">
                                Проверьте заполнение пунктов.
                            </span>
                        </div>
                    </div>

                    <aside class="min-h-0 overflow-y-auto overscroll-contain rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 lg:max-h-full">
                        <div class="font-medium">Плейсхолдеры в DOCX</div>
                        <p class="mt-2 text-zinc-600 dark:text-zinc-300">
                            В таблице шаблона укажите строку с макросами PhpWord cloneRow. Якорь — поле с суффиксом
                            <code class="rounded bg-zinc-200 px-1 dark:bg-zinc-800">{{ activeBasicTermsPlaceholderHelp.anchor }}</code>.
                        </p>
                        <ul class="mt-3 space-y-1 font-mono text-xs text-zinc-700 dark:text-zinc-200">
                            <li v-for="macro in activeBasicTermsPlaceholderHelp.macros" :key="macro">
                                ${ {{ macro }} }
                            </li>
                        </ul>
                        <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
                            При печати: сначала условия из заказа (если заданы), иначе для контрагента, иначе общие.
                            Сохранение набора для контрагента доступно здесь; правки в конкретной заявке — отдельным этапом.
                        </p>
                    </aside>
                </div>
            </template>
        </section>

        <Teleport to="body">
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="closeModal"
            >
                <section :class="`${crmModalPanel} ${crmModalFormShell} w-full max-w-5xl shadow-2xl`">
                    <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <div>
                            <div class="text-lg font-semibold">
                                {{ editingTemplate === null ? 'Новый шаблон' : 'Редактирование шаблона' }}
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                Назначение шаблона по умолчанию или на конкретного контрагента.
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            @click="closeModal"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="flex shrink-0 gap-1 border-b border-zinc-200 px-5 pt-2 dark:border-zinc-800">
                        <button
                            type="button"
                            class="rounded-t-lg px-3 py-2 text-sm font-medium"
                            :class="templateModalTab === 'main' ? crmPillActive : crmPill"
                            @click="templateModalTab = 'main'"
                        >
                            Основное
                        </button>
                        <button
                            type="button"
                            class="rounded-t-lg px-3 py-2 text-sm font-medium"
                            :class="templateModalTab === 'placeholders' ? crmPillActive : crmPill"
                            @click="templateModalTab = 'placeholders'"
                        >
                            Плейсхолдеры
                        </button>
                    </div>

                    <form class="flex min-h-0 flex-1 flex-col overflow-hidden" @submit.prevent="submit">
                        <div v-show="templateModalTab === 'main'" class="grid min-h-0 flex-1 grid-cols-1 gap-6 overflow-y-auto px-5 py-5 lg:grid-cols-2">
                            <div class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Код</label>
                                        <input v-model="form.code" type="text" :class="crmFieldFluid" placeholder="customer_contract_request" />
                                        <div v-if="form.errors.code" class="text-sm text-rose-600">{{ form.errors.code }}</div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Название</label>
                                        <input v-model="form.name" type="text" :class="crmFieldFluid" placeholder="Договор-заявка заказчика" />
                                        <div v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</div>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Сущность</label>
                                        <select v-model="form.entity_type" :class="crmFieldFluid">
                                            <option v-for="option in entityTypeOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Источник</label>
                                        <select v-model="form.source_type" :class="crmFieldFluid">
                                            <option v-for="option in sourceTypeOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Тип документа</label>
                                        <select v-model="form.document_type" :class="crmFieldFluid">
                                            <option v-for="option in documentTypeOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Группа документа</label>
                                        <select v-model="form.document_group" :class="crmFieldFluid">
                                            <option v-for="option in documentGroupOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Сторона</label>
                                        <select v-model="form.party" :class="crmFieldFluid">
                                            <option v-for="option in partyOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div v-if="form.source_type === 'external_docx'" class="space-y-2">
                                        <label class="text-sm font-medium">Контрагент</label>
                                        <select v-model="form.contractor_id" :class="crmFieldFluid">
                                            <option :value="null">Выберите контрагента</option>
                                            <option v-for="option in contractorOptions" :key="option.id" :value="option.id">
                                                {{ option.name }}
                                            </option>
                                        </select>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Обязательно для DOCX контрагента — шаблон применяется только к этому контрагенту.
                                        </div>
                                        <div v-if="form.errors.contractor_id" class="text-sm text-rose-600">{{ form.errors.contractor_id }}</div>
                                    </div>
                                </div>

                                <div v-if="form.entity_type === 'order'" class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Своя компания</label>
                                        <select v-model="form.own_company_id" :class="crmFieldFluid">
                                            <option :value="null">Любая</option>
                                            <option v-for="option in ownCompanyOptions" :key="option.id" :value="option.id">
                                                {{ option.name }}
                                            </option>
                                        </select>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Если указана — шаблон предлагается только для заказов с этой «нашей» компанией.
                                        </div>
                                        <div v-if="form.errors.own_company_id" class="text-sm text-rose-600">{{ form.errors.own_company_id }}</div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Тип перевозки</label>
                                        <select v-model="form.transport_scope" :class="crmFieldFluid">
                                            <option v-for="option in transportScopeOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Для внутрироссийских и международных заказов можно задать разные формы по умолчанию.
                                        </div>
                                        <div v-if="form.errors.transport_scope" class="text-sm text-rose-600">{{ form.errors.transport_scope }}</div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Исходный DOCX</label>
                                    <input
                                        ref="sourceDocxFileInputRef"
                                        type="file"
                                        accept=".docx"
                                        class="field file:mr-3 file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm dark:file:bg-zinc-800"
                                        @change="onFileChange"
                                    />
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        Для системного шаблона файл можно не загружать сейчас. Для DOCX контрагента файл обязателен.
                                    </div>
                                    <div v-if="editingTemplate?.original_filename" class="text-xs text-zinc-500 dark:text-zinc-400">
                                        Текущий файл: {{ editingTemplate.original_filename }}
                                    </div>
                                    <div v-if="form.progress" class="text-xs text-zinc-500 dark:text-zinc-400">
                                        Загрузка: {{ form.progress.percentage }}%
                                    </div>
                                    <div v-if="form.errors.source_file" class="text-sm text-rose-600">{{ form.errors.source_file }}</div>
                                </div>

                                <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="mb-3 text-sm font-medium">Подпись и печать для DOCX</div>
                                    <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">
                                        Это <span class="font-medium">отдельные поля картинок</span> (PhpWord
                                        <span class="font-mono">setImageValue</span>), не текстовые плейсхолдеры из блока ниже. В DOCX должны быть макросы в том же виде, что задаёте здесь, например
                                        <span class="font-mono">${signature}</span> и
                                        <span class="font-mono">${stamp}</span> или
                                        <span class="font-mono">&#123;&#123;stamp&#125;&#125;</span> — оба стиля макросов обрабатываются. После выбора файла нажмите «Сохранить» внизу окна.
                                        Для фона под печатью лучше <span class="font-medium">PNG с прозрачностью</span> — в итоговом PDF через LibreOffice/Gotenberg она обычно сохраняется лучше, чем в HTML-предпросмотре.
                                    </p>
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Плейсхолдер подписи</label>
                                            <input v-model="form.internal_signature_placeholder" type="text" class="field font-mono text-sm" autocomplete="off" />
                                            <div v-if="form.errors.internal_signature_placeholder" class="text-sm text-rose-600">
                                                {{ form.errors.internal_signature_placeholder }}
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Плейсхолдер печати</label>
                                            <input v-model="form.internal_stamp_placeholder" type="text" class="field font-mono text-sm" autocomplete="off" />
                                            <div v-if="form.errors.internal_stamp_placeholder" class="text-sm text-rose-600">
                                                {{ form.errors.internal_stamp_placeholder }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                                        <div class="space-y-2 rounded-xl border border-zinc-100 p-3 dark:border-zinc-800">
                                            <label class="text-sm font-medium">Файл подписи</label>
                                            <input
                                                type="file"
                                                name="signature_image_file"
                                                accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                                                class="field file:mr-3 file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm dark:file:bg-zinc-800"
                                                @change="onSignatureImageFileChange"
                                            />
                                            <div v-if="form.errors.signature_image_file" class="text-sm text-rose-600">{{ form.errors.signature_image_file }}</div>
                                            <img
                                                v-if="editingTemplate?.signature_image_preview_url"
                                                :src="editingTemplate.signature_image_preview_url"
                                                alt="Подпись"
                                                class="mt-2 max-h-20 rounded border border-zinc-200 object-contain dark:border-zinc-700"
                                            />
                                        </div>
                                        <div class="space-y-2 rounded-xl border border-zinc-100 p-3 dark:border-zinc-800">
                                            <label class="text-sm font-medium">Файл печати</label>
                                            <input
                                                type="file"
                                                name="stamp_image_file"
                                                accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                                                class="field file:mr-3 file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm dark:file:bg-zinc-800"
                                                @change="onStampImageFileChange"
                                            />
                                            <div v-if="form.errors.stamp_image_file" class="text-sm text-rose-600">{{ form.errors.stamp_image_file }}</div>
                                            <img
                                                v-if="editingTemplate?.stamp_image_preview_url"
                                                :src="editingTemplate.stamp_image_preview_url"
                                                alt="Печать"
                                                class="mt-2 max-h-20 rounded border border-zinc-200 object-contain dark:border-zinc-700"
                                            />
                                        </div>
                                    </div>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Подпись: ширина, мм</label>
                                            <input v-model.number="form.signature_image_width_mm" type="number" min="5" max="200" step="0.1" :class="crmFieldFluid" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Подпись: высота, мм</label>
                                            <input v-model.number="form.signature_image_height_mm" type="number" min="5" max="200" step="0.1" :class="crmFieldFluid" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Печать: ширина, мм</label>
                                            <input v-model.number="form.stamp_image_width_mm" type="number" min="5" max="200" step="0.1" :class="crmFieldFluid" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Печать: высота, мм</label>
                                            <input v-model.number="form.stamp_image_height_mm" type="number" min="5" max="200" step="0.1" :class="crmFieldFluid" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Подпись: сдвиг X, мм</label>
                                            <input v-model.number="form.signature_image_offset_x_mm" type="number" min="-200" max="200" step="0.1" :class="crmFieldFluid" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Подпись: сдвиг Y, мм</label>
                                            <input v-model.number="form.signature_image_offset_y_mm" type="number" min="-200" max="200" step="0.1" :class="crmFieldFluid" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Печать: сдвиг X, мм</label>
                                            <input v-model.number="form.stamp_image_offset_x_mm" type="number" min="-200" max="200" step="0.1" :class="crmFieldFluid" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Печать: сдвиг Y, мм</label>
                                            <input v-model.number="form.stamp_image_offset_y_mm" type="number" min="-200" max="200" step="0.1" :class="crmFieldFluid" />
                                        </div>
                                    </div>

                                    <label class="mt-3 flex items-start gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                                        <input v-model="form.apply_crm_overlay_offsets" type="checkbox" class="mt-1 rounded border-zinc-300" />
                                        <div>
                                            <div class="text-sm font-medium">Смещения и привязка к странице из CRM</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                Если выключено, подпись и печать вставляются только в местах плейсхолдеров DOCX (размеры ниже сохраняются; сдвиги из CRM к файлу не применяются).
                                            </div>
                                        </div>
                                    </label>

                                    <div
                                        v-if="editingTemplate !== null && editingTemplate.has_source_file"
                                        class="mt-4 space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800"
                                    >
                                        <div class="text-sm font-medium">Тестовая генерация DOCX</div>
                                    <div v-if="form.entity_type === 'order'" class="space-y-3">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">ID заказа</label>
                                            <input v-model="previewOrderId" type="number" min="1" :class="crmFieldFluid" placeholder="Например, 125" />
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/60"
                                                @click="previewOrderDraft"
                                            >
                                                <FileText class="h-4 w-4" />
                                                Предпросмотр в браузере
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                                @click="downloadOrderDraft"
                                            >
                                                Скачать DOCX
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else-if="form.entity_type === 'lead'" class="space-y-3">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">ID лида</label>
                                            <input v-model="previewLeadId" type="number" min="1" :class="crmFieldFluid" placeholder="Например, 18" />
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/60"
                                                @click="previewLeadDraft"
                                            >
                                                <FileText class="h-4 w-4" />
                                                Предпросмотр в браузере
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                                @click="downloadLeadDraft"
                                            >
                                                Скачать DOCX
                                            </button>
                                        </div>
                                    </div>
                                    </div>
                                    <p v-else-if="editingTemplate !== null && !editingTemplate.has_source_file" class="mt-3 text-xs text-amber-800 dark:text-amber-200">
                                        Сохраните шаблон с загруженным DOCX — здесь появятся кнопки предпросмотра и скачивания.
                                    </p>
                                    <p v-else class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                                        После первого сохранения с файлом DOCX откроется тестовая генерация по ID заказа или лида.
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="mb-3 text-sm font-medium">Назначение</div>
                                    <div class="space-y-3">
                                        <label class="flex items-start gap-3">
                                            <input v-model="form.is_default" type="checkbox" class="mt-1 rounded border-zinc-300" />
                                            <div>
                                                <div class="text-sm font-medium">Шаблон по умолчанию</div>
                                                <div class="text-xs text-zinc-500">
                                                    Для комбинации: тип документа, сторона печати (заказчик или перевозчик),
                                                    своя компания и тип перевозки. У заказчика и перевозчика — отдельные дефолты.
                                                    При нескольких «наших» компаниях для дефолта обязательно укажите «Свою компанию» выше.
                                                </div>
                                                <p
                                                    v-if="form.is_default && ownCompanyOptions.length > 1 && !form.own_company_id"
                                                    class="text-xs text-amber-700 dark:text-amber-300"
                                                >
                                                    Выберите свою компанию — иначе дефолт одной компании заменит дефолт другой.
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="mb-3 text-sm font-medium">Подпись и статус</div>
                                    <div class="space-y-3">
                                        <label class="flex items-start gap-3">
                                            <input v-model="form.requires_internal_signature" type="checkbox" class="mt-1 rounded border-zinc-300" />
                                            <div>
                                                <div class="text-sm font-medium">Нужна внутренняя подпись</div>
                                                <div class="text-xs text-zinc-500">В шаблоне будет включён контур внутренней подписи и печати.</div>
                                            </div>
                                        </label>
                                        <label class="flex items-start gap-3">
                                            <input v-model="form.requires_counterparty_signature" type="checkbox" class="mt-1 rounded border-zinc-300" />
                                            <div>
                                                <div class="text-sm font-medium">Нужна подпись контрагента</div>
                                                <div class="text-xs text-zinc-500">Шаблон участвует в двустороннем согласовании.</div>
                                            </div>
                                        </label>
                                        <label class="flex items-start gap-3">
                                            <input v-model="form.is_active" type="checkbox" class="mt-1 rounded border-zinc-300" />
                                            <div>
                                                <div class="text-sm font-medium">Шаблон активен</div>
                                                <div class="text-xs text-zinc-500">Неактивные шаблоны не предлагаются для генерации.</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div class="border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                                    Следующий этап после этого экрана: разбор плейсхолдеров, генерация DOCX, внедрение подписи и печати, финальная конвертация в PDF.
                                </div>
                            </div>
                        </div>

                        <div
                            v-show="templateModalTab === 'placeholders'"
                            class="min-h-0 flex-1 overflow-y-auto px-5 py-5"
                        >
                            <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="mb-3 text-sm font-medium">Сопоставление плейсхолдеров (текст и данные)</div>
                                <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">
                                    Подстановки заказа/лида. Плейсхолдеры подписи и печати — в блоке «Основное» → «Подпись и печать для DOCX».
                                </p>
                                    <div v-if="form.variable_mappings.length > 0" class="grid gap-4 md:grid-cols-2">
                                    <div
                                        v-for="(mapping, index) in form.variable_mappings"
                                        :key="`${index}-${mapping.placeholder}`"
                                        class="grid gap-3 rounded-2xl border border-zinc-200 p-3 dark:border-zinc-800"
                                    >
                                        <div>
                                            <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Плейсхолдер</div>
                                            <div class="mt-1 font-mono text-sm break-all">{{ mapping.placeholder }}</div>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Поле источника</label>
                                            <select v-model="form.variable_mappings[index].source_path" :class="crmFieldFluid">
                                                <option value="">Не сопоставлено</option>
                                                <option v-for="option in activeVariableOptions" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-zinc-500 dark:text-zinc-400">
                                    Сначала загрузи DOCX или открой шаблон, в котором уже обнаружены плейсхолдеры.
                                </div>
                                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                                    В списке — итоговое сопоставление (сохранённое в БД + автоправила для типовых имён). Пустое поле не перезаписывает legacy-алиасы при генерации. Нажмите «Сохранить», чтобы зафиксировать явные привязки в БД.
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center justify-end gap-3 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <button
                                type="button"
                                :class="crmBtnNeutral"
                                @click="closeModal"
                            >
                                Отмена
                            </button>
                            <button
                                type="submit"
                                :class="crmBtnCreate"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { FileText, ChevronDown, ChevronUp, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import { shouldHideFromVariableMapping } from '@/support/printFormClonePlaceholders.js';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmFieldFluid,
    crmModalFormShell,
    crmModalPanel,
    crmPageTitleSm,
    crmPanel,
    crmPill,
    crmPillActive,
    crmStatCard,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'configuration', activeLeafKey: 'templates', mainFill: true }, () => page),
});

const props = defineProps({
    pageTab: {
        type: String,
        default: 'templates',
    },
    templates: {
        type: Array,
        default: () => [],
    },
    contractorOptions: {
        type: Array,
        default: () => [],
    },
    ownCompanyOptions: {
        type: Array,
        default: () => [],
    },
    transportScopeOptions: {
        type: Array,
        default: () => [],
    },
    entityTypeOptions: {
        type: Array,
        default: () => [],
    },
    documentTypeOptions: {
        type: Array,
        default: () => [],
    },
    documentGroupOptions: {
        type: Array,
        default: () => [],
    },
    partyOptions: {
        type: Array,
        default: () => [],
    },
    sourceTypeOptions: {
        type: Array,
        default: () => [],
    },
    orderVariableOptions: {
        type: Array,
        default: () => [],
    },
    leadVariableOptions: {
        type: Array,
        default: () => [],
    },
    documentPreview: {
        type: Object,
        default: () => ({
            driver: 'html',
            gotenberg_url_configured: false,
            pdf_preview_available: false,
            hint: '',
        }),
    },
    basicTermsEditor: {
        type: Object,
        default: () => ({
            enabled: false,
            activeParty: 'customer',
            activeContractorId: null,
            rows: [],
            partyOptions: [],
            placeholderHelp: {},
            globalRowCounts: {},
        }),
    },
});

let basicTermRowKey = 0;

function mapBasicTermsRows(rows) {
    return (rows ?? []).map((row) => ({
        key: `term-${basicTermRowKey += 1}`,
        body: String(row?.body ?? ''),
    }));
}

const pageTab = computed(() => (props.pageTab === 'basic-terms' ? 'basic-terms' : 'templates'));
const basicTermsParty = ref(props.basicTermsEditor.activeParty ?? 'customer');
const basicTermsContractorId = ref(props.basicTermsEditor.activeContractorId ?? null);

const basicTermsForm = useForm({
    party: basicTermsParty.value,
    contractor_id: basicTermsContractorId.value,
    items: mapBasicTermsRows(props.basicTermsEditor.rows),
});

const activeBasicTermsPlaceholderHelp = computed(() => {
    const help = props.basicTermsEditor.placeholderHelp?.[basicTermsParty.value] ?? {};

    return {
        anchor: help.anchor ?? 'cp_basic_terms_row_text',
        macros: Array.isArray(help.macros) ? help.macros : [],
    };
});

function basicTermsQueryParams(overrides = {}) {
    return {
        tab: 'basic-terms',
        party: overrides.party ?? basicTermsParty.value,
        contractor_id: overrides.contractor_id ?? basicTermsContractorId.value ?? undefined,
    };
}

function syncBasicTermsFormFromProps(editor) {
    basicTermsParty.value = editor?.activeParty ?? 'customer';
    basicTermsContractorId.value = editor?.activeContractorId ?? null;
    basicTermsForm.party = basicTermsParty.value;
    basicTermsForm.contractor_id = basicTermsContractorId.value;
    basicTermsForm.items = mapBasicTermsRows(editor?.rows);
    basicTermsForm.clearErrors();
}

watch(
    () => props.basicTermsEditor,
    (editor) => {
        syncBasicTermsFormFromProps(editor);
    },
    { deep: true },
);

function switchPageTab(tab) {
    if (tab === pageTab.value) {
        return;
    }

    router.get(
        route('settings.templates.index'),
        tab === 'basic-terms' ? basicTermsQueryParams() : {},
        { preserveState: true, preserveScroll: true },
    );
}

function switchBasicTermsParty(party) {
    if (party === basicTermsParty.value) {
        return;
    }

    router.get(route('settings.templates.index'), basicTermsQueryParams({ party }), {
        preserveState: true,
        preserveScroll: true,
        only: ['basicTermsEditor', 'pageTab'],
    });
}

function reloadBasicTermsScope() {
    router.get(
        route('settings.templates.index'),
        basicTermsQueryParams({ contractor_id: basicTermsContractorId.value ?? undefined }),
        {
            preserveState: true,
            preserveScroll: true,
            only: ['basicTermsEditor', 'pageTab'],
        },
    );
}

function addBasicTermRow() {
    basicTermsForm.items.push({
        key: `term-${basicTermRowKey += 1}`,
        body: '',
    });
}

function removeBasicTermRow(index) {
    basicTermsForm.items.splice(index, 1);
}

function moveBasicTermRow(index, direction) {
    const target = index + direction;

    if (target < 0 || target >= basicTermsForm.items.length) {
        return;
    }

    const items = [...basicTermsForm.items];
    const [row] = items.splice(index, 1);
    items.splice(target, 0, row);
    basicTermsForm.items = items;
}

function saveBasicTerms() {
    basicTermsForm.party = basicTermsParty.value;
    basicTermsForm.contractor_id = basicTermsContractorId.value;
    basicTermsForm.transform((data) => ({
        party: data.party,
        contractor_id: data.contractor_id,
        items: (data.items ?? []).map((row) => String(row.body ?? '').trim()),
    }));
    basicTermsForm.put(route('settings.templates.basic-terms.update'), {
        preserveScroll: true,
    });
}

const showModal = ref(false);
const editingTemplate = ref(null);
const sourceDocxFileInputRef = ref(null);
const templateModalTab = ref('main');
const previewOrderId = ref('');
const previewLeadId = ref('');

const form = useForm({
    code: '',
    name: '',
    entity_type: props.entityTypeOptions[0]?.value ?? 'order',
    document_type: props.documentTypeOptions[0]?.value ?? 'contract_request',
    document_group: props.documentGroupOptions[0]?.value ?? 'contractual',
    party: props.partyOptions[0]?.value ?? 'internal',
    source_type: props.sourceTypeOptions[0]?.value ?? 'system',
    contractor_id: null,
    own_company_id: null,
    transport_scope: props.transportScopeOptions[0]?.value ?? 'any',
    is_default: false,
    requires_internal_signature: true,
    requires_counterparty_signature: false,
    is_active: true,
    source_file: null,
    variable_mappings: [],
    internal_signature_placeholder: 'signature',
    internal_stamp_placeholder: 'stamp',
    signature_image_width_mm: 42,
    signature_image_height_mm: 18,
    signature_image_offset_x_mm: 0,
    signature_image_offset_y_mm: 0,
    stamp_image_width_mm: 30,
    stamp_image_height_mm: 30,
    stamp_image_offset_x_mm: 0,
    stamp_image_offset_y_mm: 0,
    apply_crm_overlay_offsets: true,
    signature_image_file: null,
    stamp_image_file: null,
});

const externalTemplateCount = computed(() => props.templates.filter((template) => template.source_type === 'external_docx').length);
const defaultTemplateCount = computed(() => props.templates.filter((template) => template.is_default).length);
const activeVariableOptions = computed(() => (form.entity_type === 'lead' ? props.leadVariableOptions : props.orderVariableOptions));

watch(() => form.source_type, (sourceType) => {
    if (sourceType !== 'external_docx') {
        form.contractor_id = null;
    }
});

function resetForm() {
    form.reset();
    form.clearErrors();
    form.code = '';
    form.name = '';
    form.entity_type = props.entityTypeOptions[0]?.value ?? 'order';
    form.document_type = props.documentTypeOptions[0]?.value ?? 'contract_request';
    form.document_group = props.documentGroupOptions[0]?.value ?? 'contractual';
    form.party = props.partyOptions[0]?.value ?? 'internal';
    form.source_type = props.sourceTypeOptions[0]?.value ?? 'system';
    form.contractor_id = null;
    form.own_company_id = null;
    form.transport_scope = props.transportScopeOptions[0]?.value ?? 'any';
    form.is_default = false;
    form.requires_internal_signature = true;
    form.requires_counterparty_signature = false;
    form.is_active = true;
    form.source_file = null;
    form.variable_mappings = [];
    form.internal_signature_placeholder = 'signature';
    form.internal_stamp_placeholder = 'stamp';
    form.signature_image_width_mm = 42;
    form.signature_image_height_mm = 18;
    form.signature_image_offset_x_mm = 0;
    form.signature_image_offset_y_mm = 0;
    form.stamp_image_width_mm = 30;
    form.stamp_image_height_mm = 30;
    form.stamp_image_offset_x_mm = 0;
    form.stamp_image_offset_y_mm = 0;
    form.apply_crm_overlay_offsets = true;
    form.signature_image_file = null;
    form.stamp_image_file = null;
    previewOrderId.value = '';
    previewLeadId.value = '';
}

function openCreateModal() {
    editingTemplate.value = null;
    resetForm();
    templateModalTab.value = 'main';
    showModal.value = true;
}

function openEditModal(template) {
    editingTemplate.value = template;
    form.clearErrors();
    form.code = template.code;
    form.name = template.name;
    form.entity_type = template.entity_type;
    form.document_type = template.document_type;
    form.document_group = template.document_group;
    form.party = template.party;
    form.source_type = template.source_type;
    form.contractor_id = template.contractor_id ?? null;
    form.own_company_id = template.own_company_id ?? null;
    form.transport_scope = template.transport_scope ?? props.transportScopeOptions[0]?.value ?? 'any';
    form.is_default = Boolean(template.is_default);
    form.requires_internal_signature = Boolean(template.requires_internal_signature);
    form.requires_counterparty_signature = Boolean(template.requires_counterparty_signature);
    form.is_active = Boolean(template.is_active);
    form.source_file = null;
    form.variable_mappings = buildVariableMappings(template);
    form.internal_signature_placeholder = template.internal_signature_placeholder ?? 'signature';
    form.internal_stamp_placeholder = template.internal_stamp_placeholder ?? 'stamp';
    form.signature_image_width_mm = Number(template.signature_image_width_mm ?? 42);
    form.signature_image_height_mm = Number(template.signature_image_height_mm ?? 18);
    form.signature_image_offset_x_mm = Number(template.signature_image_offset_x_mm ?? 0);
    form.signature_image_offset_y_mm = Number(template.signature_image_offset_y_mm ?? 0);
    form.stamp_image_width_mm = Number(template.stamp_image_width_mm ?? 30);
    form.stamp_image_height_mm = Number(template.stamp_image_height_mm ?? 30);
    form.stamp_image_offset_x_mm = Number(template.stamp_image_offset_x_mm ?? 0);
    form.stamp_image_offset_y_mm = Number(template.stamp_image_offset_y_mm ?? 0);
    form.apply_crm_overlay_offsets = template.apply_crm_overlay_offsets !== false;
    form.signature_image_file = null;
    form.stamp_image_file = null;
    previewOrderId.value = '';
    previewLeadId.value = '';
    templateModalTab.value = 'main';
    showModal.value = true;
}

function draftPreviewCacheBust(templateRef) {
    const stamp = Date.now();
    const t = templateRef;
    if (t?.updated_at) {
        const parsed = Date.parse(t.updated_at);
        if (!Number.isNaN(parsed)) {
            return `${parsed}-${stamp}`;
        }
    }

    return stamp;
}

function previewTemplateFromRow(template) {
    if (!template?.has_source_file) {
        window.alert('У шаблона нет загруженного DOCX-файла.');
        return;
    }

    if (template.entity_type === 'order') {
        const orderId = String(window.prompt('ID заказа для предпросмотра', String(previewOrderId.value || '')) || '').trim();
        if (orderId === '') {
            return;
        }
        previewOrderId.value = orderId;
        window.open(
            route('settings.templates.generate-order-draft', {
                printFormTemplate: template.id,
                order_id: orderId,
                preview: 1,
                preview_mode: 'browser',
                cb: draftPreviewCacheBust(template),
            }),
            '_blank'
        );
        return;
    }

    if (template.entity_type === 'lead') {
        const leadId = String(window.prompt('ID лида для предпросмотра', String(previewLeadId.value || '')) || '').trim();
        if (leadId === '') {
            return;
        }
        previewLeadId.value = leadId;
        window.open(
            route('settings.templates.generate-lead-draft', {
                printFormTemplate: template.id,
                lead_id: leadId,
                preview: 1,
                preview_mode: 'browser',
                cb: draftPreviewCacheBust(template),
            }),
            '_blank'
        );
        return;
    }

    window.alert('Для этого типа шаблона быстрый предпросмотр пока не настроен.');
}

function closeModal() {
    showModal.value = false;
    editingTemplate.value = null;
    resetForm();
}

function onFileChange(event) {
    form.source_file = event.target.files?.[0] ?? null;
}

function onSignatureImageFileChange(event) {
    form.signature_image_file = event.target.files?.[0] ?? null;
}

function onStampImageFileChange(event) {
    form.stamp_image_file = event.target.files?.[0] ?? null;
}

function labelFor(options, value) {
    return options.find((option) => option.value === value)?.label ?? value;
}

function entityTypeLabel(value) {
    return labelFor(props.entityTypeOptions, value);
}

function documentTypeLabel(value) {
    return labelFor(props.documentTypeOptions, value);
}

function documentGroupLabel(value) {
    return labelFor(props.documentGroupOptions, value);
}

function partyLabel(value) {
    return labelFor(props.partyOptions, value);
}

function transportScopeLabel(value) {
    return labelFor(props.transportScopeOptions, value);
}

function sourceTypeLabel(value) {
    return labelFor(props.sourceTypeOptions, value);
}

function imageOverlayPlaceholderSet(template) {
    const names = [
        template?.internal_signature_placeholder,
        template?.internal_stamp_placeholder,
        'signature',
        'stamp',
    ];

    return new Set(
        names
            .map((s) => String(s ?? '').trim())
            .filter((s) => s !== ''),
    );
}

function imageOverlayPlaceholderSetFromForm() {
    const names = [
        form.internal_signature_placeholder,
        form.internal_stamp_placeholder,
        'signature',
        'stamp',
    ];

    return new Set(
        names
            .map((s) => String(s ?? '').trim())
            .filter((s) => s !== ''),
    );
}

function buildVariableMappings(template) {
    const currentMapping = template.variable_mapping || {};
    const skipImages = imageOverlayPlaceholderSet(template);

    return (template.variables || [])
        .filter((placeholder) => !skipImages.has(placeholder) && !shouldHideFromVariableMapping(placeholder))
        .map((placeholder) => ({
            placeholder,
            source_path: currentMapping[placeholder] || '',
        }));
}

/**
 * После замены DOCX на сервере обновляются settings.variables; объект editingTemplate
 * мог остаться ссылкой на старую строку из props — тогда модалка показывает старые плейсхолдеры.
 */
function resyncEditingTemplateFromTemplatesList(templatesList) {
    if (!showModal.value || editingTemplate.value === null || !Array.isArray(templatesList)) {
        return;
    }
    const id = editingTemplate.value.id;
    const fresh = templatesList.find((t) => t.id === id);
    if (!fresh) {
        return;
    }
    const cur = editingTemplate.value;
    const varsEqual = JSON.stringify(cur.variables ?? []) === JSON.stringify(fresh.variables ?? []);
    const metaEqual = cur.updated_at === fresh.updated_at && cur.version === fresh.version && cur.original_filename === fresh.original_filename;
    if (varsEqual && metaEqual) {
        return;
    }
    editingTemplate.value = fresh;
    form.variable_mappings = buildVariableMappings(fresh);
    form.source_file = null;
    if (sourceDocxFileInputRef.value) {
        sourceDocxFileInputRef.value.value = '';
    }
}

watch(
    () => props.templates,
    (templatesList) => {
        resyncEditingTemplateFromTemplatesList(templatesList);
    },
    { deep: true },
);

watch(
    () => [form.internal_signature_placeholder, form.internal_stamp_placeholder],
    () => {
        const skip = imageOverlayPlaceholderSetFromForm();
        form.variable_mappings = form.variable_mappings.filter((row) => !skip.has(row.placeholder));
    },
);

function pipelineStatusLabel(value) {
    return {
        draft: 'Черновик',
        uploaded: 'Загружен',
        uploaded_without_placeholders: 'Без плейсхолдеров',
        placeholders_ready: 'Плейсхолдеры готовы',
    }[value] ?? value;
}

function pipelineStatusClass(value) {
    return {
        draft: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
        uploaded: 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
        uploaded_without_placeholders: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
        placeholders_ready: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
    }[value] ?? 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300';
}

function submit() {
    const withOverlayCheckbox = (data) => ({
        ...data,
        // multipart FormData omits unchecked boxes — always send 0/1 so CRM смещения вкл/выкл сохраняются
        apply_crm_overlay_offsets: data.apply_crm_overlay_offsets === true || data.apply_crm_overlay_offsets === '1' || data.apply_crm_overlay_offsets === 1 ? '1' : '0',
    });

    if (editingTemplate.value === null) {
        form
            .transform((data) => withOverlayCheckbox(data))
            .post(route('settings.templates.store'), {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
            });
        return;
    }

    form
        .transform((data) => ({
            ...withOverlayCheckbox(data),
            _method: 'patch',
        }))
        .post(route('settings.templates.update', editingTemplate.value.id), {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: async () => {
                form.transform((data) => data);
                await nextTick();
                resyncEditingTemplateFromTemplatesList(props.templates);
            },
            onError: () => {
                form.transform((data) => data);
            },
        });
}

function removeTemplate(template) {
    if (!window.confirm(`Удалить шаблон ${template.name}?`)) {
        return;
    }

    router.delete(route('settings.templates.destroy', template.id), {
        preserveScroll: true,
    });
}

function previewOrderDraft() {
    if (editingTemplate.value === null) {
        return;
    }

    const orderId = String(previewOrderId.value || '').trim();

    if (orderId === '') {
        window.alert('Укажи ID заказа для тестовой генерации.');
        return;
    }

    window.open(
        route('settings.templates.generate-order-draft', {
            printFormTemplate: editingTemplate.value.id,
            order_id: orderId,
            preview: 1,
            preview_mode: 'browser',
            cb: draftPreviewCacheBust(editingTemplate.value),
        }),
        '_blank'
    );
}

function downloadOrderDraft() {
    if (editingTemplate.value === null) {
        return;
    }

    const orderId = String(previewOrderId.value || '').trim();

    if (orderId === '') {
        window.alert('Укажи ID заказа для тестовой генерации.');
        return;
    }

    window.location.href = route('settings.templates.generate-order-draft', {
        printFormTemplate: editingTemplate.value.id,
        order_id: orderId,
        cb: draftPreviewCacheBust(editingTemplate.value),
    });
}

function previewLeadDraft() {
    if (editingTemplate.value === null) {
        return;
    }

    const leadId = String(previewLeadId.value || '').trim();

    if (leadId === '') {
        window.alert('Укажи ID лида для тестовой генерации.');
        return;
    }

    window.open(
        route('settings.templates.generate-lead-draft', {
            printFormTemplate: editingTemplate.value.id,
            lead_id: leadId,
            preview: 1,
            preview_mode: 'browser',
            cb: draftPreviewCacheBust(editingTemplate.value),
        }),
        '_blank'
    );
}

function downloadLeadDraft() {
    if (editingTemplate.value === null) {
        return;
    }

    const leadId = String(previewLeadId.value || '').trim();

    if (leadId === '') {
        window.alert('Укажи ID лида для тестовой генерации.');
        return;
    }

    window.location.href = route('settings.templates.generate-lead-draft', {
        printFormTemplate: editingTemplate.value.id,
        lead_id: leadId,
        cb: draftPreviewCacheBust(editingTemplate.value),
    });
}
</script>


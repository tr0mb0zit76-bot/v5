<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto lg:min-h-0">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Шаблоны</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Жесткие системные формы и внешние DOCX-шаблоны заказчиков и перевозчиков.
                </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                @click="openCreateModal"
            >
                <Plus class="h-4 w-4" />
                Добавить шаблон
            </button>
        </div>

        <div
            v-if="!documentPreview.pdf_preview_available"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
            role="status"
        >
            {{ documentPreview.hint }}
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            <section class="border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Всего шаблонов</div>
                <div class="mt-2 text-2xl font-semibold">{{ templates.length }}</div>
            </section>
            <section class="border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">DOCX контрагентов</div>
                <div class="mt-2 text-2xl font-semibold">{{ externalTemplateCount }}</div>
            </section>
            <section class="border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">По умолчанию</div>
                <div class="mt-2 text-2xl font-semibold">{{ defaultTemplateCount }}</div>
            </section>
        </div>

        <div class="min-h-0 flex-1 overflow-hidden border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
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
                                <div v-if="template.contractor_name" class="font-medium">{{ template.contractor_name }}</div>
                                <div v-else class="text-zinc-500">Без контрагента</div>
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

        <Teleport to="body">
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="closeModal"
            >
                <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden border border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
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

                    <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                        <div class="grid min-h-0 flex-1 grid-cols-1 gap-6 overflow-y-auto px-5 py-5 lg:grid-cols-2">
                            <div class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Код</label>
                                        <input v-model="form.code" type="text" class="field" placeholder="customer_contract_request" />
                                        <div v-if="form.errors.code" class="text-sm text-rose-600">{{ form.errors.code }}</div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Название</label>
                                        <input v-model="form.name" type="text" class="field" placeholder="Договор-заявка заказчика" />
                                        <div v-if="form.errors.name" class="text-sm text-rose-600">{{ form.errors.name }}</div>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Сущность</label>
                                        <select v-model="form.entity_type" class="field">
                                            <option v-for="option in entityTypeOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Источник</label>
                                        <select v-model="form.source_type" class="field">
                                            <option v-for="option in sourceTypeOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Тип документа</label>
                                        <select v-model="form.document_type" class="field">
                                            <option v-for="option in documentTypeOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Группа документа</label>
                                        <select v-model="form.document_group" class="field">
                                            <option v-for="option in documentGroupOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Сторона</label>
                                        <select v-model="form.party" class="field">
                                            <option v-for="option in partyOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Контрагент</label>
                                        <select v-model="form.contractor_id" class="field">
                                            <option :value="null">Без привязки</option>
                                            <option v-for="option in contractorOptions" :key="option.id" :value="option.id">
                                                {{ option.name }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.contractor_id" class="text-sm text-rose-600">{{ form.errors.contractor_id }}</div>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Исходный DOCX</label>
                                    <input
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
                                        <span class="font-mono">${internal_signature_image}</span> и
                                        <span class="font-mono">${internal_stamp_image}</span> или
                                        <span class="font-mono">&#123;&#123;internal_stamp_image&#125;&#125;</span> — оба стиля макросов обрабатываются. После выбора файла нажмите «Сохранить» внизу окна.
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
                                            <input v-model.number="form.signature_image_width_mm" type="number" min="5" max="200" step="0.1" class="field" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Подпись: высота, мм</label>
                                            <input v-model.number="form.signature_image_height_mm" type="number" min="5" max="200" step="0.1" class="field" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Печать: ширина, мм</label>
                                            <input v-model.number="form.stamp_image_width_mm" type="number" min="5" max="200" step="0.1" class="field" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Печать: высота, мм</label>
                                            <input v-model.number="form.stamp_image_height_mm" type="number" min="5" max="200" step="0.1" class="field" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Подпись: сдвиг X, мм</label>
                                            <input v-model.number="form.signature_image_offset_x_mm" type="number" min="-200" max="200" step="0.1" class="field" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Подпись: сдвиг Y, мм</label>
                                            <input v-model.number="form.signature_image_offset_y_mm" type="number" min="-200" max="200" step="0.1" class="field" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Печать: сдвиг X, мм</label>
                                            <input v-model.number="form.stamp_image_offset_x_mm" type="number" min="-200" max="200" step="0.1" class="field" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Печать: сдвиг Y, мм</label>
                                            <input v-model.number="form.stamp_image_offset_y_mm" type="number" min="-200" max="200" step="0.1" class="field" />
                                        </div>
                                    </div>

                                    <label class="mt-3 flex items-start gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                                        <input v-model="form.apply_crm_overlay_offsets" type="checkbox" class="mt-1 rounded border-zinc-300" />
                                        <div>
                                            <div class="text-sm font-medium">Смещения и привязка к странице из CRM</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                Если выключено, подпись и печать вставляются только в местах плейсхолдеров DOCX (размеры ниже сохраняются; сдвиги и перетаскивание на предпросмотре не применяются к файлу).
                                            </div>
                                        </div>
                                    </label>

                                    <div
                                        v-if="editingTemplate !== null && editingTemplate.has_source_file"
                                        class="mt-4 space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800"
                                    >
                                        <div class="text-sm font-medium">Тестовая генерация DOCX</div>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                            «Предпросмотр» открывает документ в браузере. «Скачать DOCX» — файл на диск. «Печать и подпись на предпросмотре» — при включённых смещениях из CRM: перетаскивание поверх PDF; при выключенных — готовый вид как при печати (нужен Gotenberg, см. предупреждение вверху страницы).
                                        </p>
                                    <div v-if="form.entity_type === 'order'" class="space-y-3">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">ID заказа</label>
                                            <input v-model="previewOrderId" type="number" min="1" class="field" placeholder="Например, 125" />
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
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-medium text-sky-800 hover:bg-sky-100 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200 dark:hover:bg-sky-950/60"
                                                @click="openOrderOverlayPreview"
                                            >
                                                <Move class="h-4 w-4" />
                                                Печать и подпись на предпросмотре
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else-if="form.entity_type === 'lead'" class="space-y-3">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">ID лида</label>
                                            <input v-model="previewLeadId" type="number" min="1" class="field" placeholder="Например, 18" />
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
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-medium text-sky-800 hover:bg-sky-100 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200 dark:hover:bg-sky-950/60"
                                                @click="openLeadOverlayPreview"
                                            >
                                                <Move class="h-4 w-4" />
                                                Печать и подпись на предпросмотре
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
                                                <div class="text-xs text-zinc-500">Используется, если для контрагента не задана отдельная форма.</div>
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

                                <div class="border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="mb-3 text-sm font-medium">Сопоставление плейсхолдеров (текст и данные)</div>
                                    <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">
                                        Сюда попадают только подстановки значений заказа/лида. Плейсхолдеры подписи и печати настраиваются в блоке «Подпись и печать для DOCX» и здесь не дублируются.
                                    </p>
                                    <div v-if="form.variable_mappings.length > 0" class="space-y-3">
                                        <div
                                            v-for="(mapping, index) in form.variable_mappings"
                                            :key="mapping.placeholder"
                                            class="grid gap-3 rounded-2xl border border-zinc-200 p-3 dark:border-zinc-800"
                                        >
                                            <div>
                                                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Плейсхолдер</div>
                                                <div class="mt-1 font-mono text-sm">{{ mapping.placeholder }}</div>
                                            </div>
                                            <div class="space-y-2">
                                                <label class="text-sm font-medium">Поле источника</label>
                                                <select v-model="form.variable_mappings[index].source_path" class="field">
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
                                        Для шаблонов заказа в списке уже показано итоговое сопоставление: явно сохранённое в БД плюс автоматические правила для типовых имён плейсхолдеров (как при генерации DOCX). Для лидов без явного маппинга по умолчанию подставляется путь с тем же именем, что и плейсхолдер.
                                    </p>
                                </div>

                                <div class="border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                                    Следующий этап после этого экрана: разбор плейсхолдеров, генерация DOCX, внедрение подписи и печати, финальная конвертация в PDF.
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <button
                                type="button"
                                class="rounded-xl border border-zinc-200 px-4 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                @click="closeModal"
                            >
                                Отмена
                            </button>
                            <button
                                type="submit"
                                class="rounded-xl bg-zinc-900 px-4 py-2 text-sm text-white hover:bg-zinc-800 disabled:opacity-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { FileText, Move, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'configuration', activeLeafKey: 'templates' }, () => page),
});

const props = defineProps({
    templates: {
        type: Array,
        default: () => [],
    },
    contractorOptions: {
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
});

const showModal = ref(false);
const editingTemplate = ref(null);
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
    is_default: false,
    requires_internal_signature: true,
    requires_counterparty_signature: false,
    is_active: true,
    source_file: null,
    variable_mappings: [],
    internal_signature_placeholder: 'internal_signature_image',
    internal_stamp_placeholder: 'internal_stamp_image',
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
    form.is_default = false;
    form.requires_internal_signature = true;
    form.requires_counterparty_signature = false;
    form.is_active = true;
    form.source_file = null;
    form.variable_mappings = [];
    form.internal_signature_placeholder = 'internal_signature_image';
    form.internal_stamp_placeholder = 'internal_stamp_image';
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
    form.is_default = Boolean(template.is_default);
    form.requires_internal_signature = Boolean(template.requires_internal_signature);
    form.requires_counterparty_signature = Boolean(template.requires_counterparty_signature);
    form.is_active = Boolean(template.is_active);
    form.source_file = null;
    form.variable_mappings = buildVariableMappings(template);
    form.internal_signature_placeholder = template.internal_signature_placeholder ?? 'internal_signature_image';
    form.internal_stamp_placeholder = template.internal_stamp_placeholder ?? 'internal_stamp_image';
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

function sourceTypeLabel(value) {
    return labelFor(props.sourceTypeOptions, value);
}

function imageOverlayPlaceholderSet(template) {
    const names = [
        template?.internal_signature_placeholder,
        template?.internal_stamp_placeholder,
        'internal_signature_image',
        'internal_stamp_image',
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
        'internal_signature_image',
        'internal_stamp_image',
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
        .filter((placeholder) => !skipImages.has(placeholder))
        .map((placeholder) => ({
            placeholder,
            source_path: currentMapping[placeholder] || '',
        }));
}

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
    if (editingTemplate.value === null) {
        form.post(route('settings.templates.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
        return;
    }

    form
        .transform((data) => ({
            ...data,
            _method: 'patch',
        }))
        .post(route('settings.templates.update', editingTemplate.value.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.transform((data) => data);
                closeModal();
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

function openOrderOverlayPreview() {
    if (editingTemplate.value === null) {
        return;
    }

    const orderId = String(previewOrderId.value || '').trim();

    if (orderId === '') {
        window.alert('Укажи ID заказа — по нему строится фон предпросмотра без печати/подписи, поверх которого перетаскиваются изображения.');
        return;
    }

    router.visit(
        route('settings.templates.preview-order-overlay', {
            printFormTemplate: editingTemplate.value.id,
            order_id: orderId,
            cb: Date.now(),
        })
    );
}

function openLeadOverlayPreview() {
    if (editingTemplate.value === null) {
        return;
    }

    const leadId = String(previewLeadId.value || '').trim();

    if (leadId === '') {
        window.alert('Укажи ID лида — по нему строится фон предпросмотра без печати/подписи.');
        return;
    }

    router.visit(
        route('settings.templates.preview-lead-overlay', {
            printFormTemplate: editingTemplate.value.id,
            lead_id: leadId,
            cb: Date.now(),
        })
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

<style scoped>
.field {
    @apply w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none transition-colors focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50;
}
</style>

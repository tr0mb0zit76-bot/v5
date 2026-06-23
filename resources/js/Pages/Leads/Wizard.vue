<template>
    <div :class="crmWizardShell">
        <div :class="crmWizardHeader">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    :class="crmWizardBack"
                    title="К реестру"
                    @click="goBack"
                >
                    <X class="h-5 w-5" />
                    <span class="sr-only">К реестру</span>
                </button>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                        {{ selectedLeadId ? 'Карточка лида' : 'Новый лид' }}
                    </div>
                    <h1 class="mt-1 truncate text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ selectedLeadId ? form.number || 'Лид' : 'Добавление' }}
                    </h1>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" :class="crmBtnPrimary" :disabled="!selectedLeadId || !form.counterparty_id" @click="convertLead"><ArrowRightLeft class="h-4 w-4" />Конвертировать в заказ</button>
                <button type="button" :class="crmBtnCreate" @click="submit"><Save class="h-4 w-4" />Сохранить</button>
            </div>
        </div>

        <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-800">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex items-center gap-2 text-sm transition-colors"
                    :class="crmTabButtonClasses(activeTab === tab.key)"
                    @click="activeTab = tab.key"
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <div :class="crmWizardBody">
            <div v-if="activeTab === 'main'" class="space-y-5">
                <div
                    v-if="followUpPrompt"
                    class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100"
                >
                    <p class="font-medium">Лид закрыт без сделки</p>
                    <p class="mt-1 text-sky-900/90 dark:text-sky-100/90">
                        <template v-if="followUpPrompt.cancelled_tasks > 0">
                            Отменено задач по этому лиду: {{ followUpPrompt.cancelled_tasks }}.
                        </template>
                        <template v-else>
                            Открытых задач по лиду не было.
                        </template>
                        Клиент может вернуться — создайте задачу на поддержание контакта.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" :class="crmBtnPrimary" @click="prefillFollowUpTask">
                            Создать: «{{ followUpPrompt.suggested_title }}»
                        </button>
                        <button type="button" :class="crmBtnSecondary" @click="followUpPrompt = null">
                            Понятно
                        </button>
                    </div>
                </div>

                <LeadProcessPanel
                    v-if="businessProcessesEnabled"
                    v-model:advance-stage-id="advanceStageId"
                    v-model:close-outcome-primary-flag="processStageForm.close_outcome_primary_flag"
                    v-model:close-outcome-note="processStageForm.close_outcome_note"
                    :selected-lead-id="selectedLeadId"
                    :business-processes="businessProcesses"
                    :business-process-id="form.business_process_id"
                    :process-progress="processProgress"
                    :processing="processStageForm.processing"
                    :lost-close-outcome-options="lostCloseOutcomeOptions"
                    :won-close-outcome-options="wonCloseOutcomeOptions"
                    :close-outcome-error="processStageForm.errors.close_outcome_primary_flag"
                    @update:business-process-id="form.business_process_id = $event"
                    @advance="submitProcessStage"
                />
                <div
                    v-if="counterpartyPortraitIncomplete"
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    Портрет клиента неполный ({{ props.selectedLead?.counterparty_portrait_coverage_pct }}%).
                    <Link
                        :href="route('contractors.show', { contractor: form.counterparty_id, tab: 'portrait' })"
                        class="ml-1 font-medium underline underline-offset-2"
                    >
                        Заполнить портрет контрагента
                    </Link>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-2">
                        <label :class="crmLabel">Статус</label>
                        <select v-model="form.status" :class="crmFieldFluid">
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label :class="crmLabel">Источник</label>
                        <select v-model="form.source" :class="crmFieldFluid">
                            <option value="">Не выбрано</option>
                            <option v-for="option in sourceOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label :class="crmLabel">Ответственный</label>
                        <select v-model.number="form.responsible_id" :class="crmFieldFluid" :disabled="!canAssignResponsible">
                            <option v-for="user in responsibleUsers" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                        <p v-if="!selectedLeadId && canAssignResponsible" class="text-xs text-zinc-500 dark:text-zinc-400">
                            По умолчанию — вы; при необходимости можно назначить коллегу.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <label :class="crmLabel">Плановая отгрузка</label>
                        <input v-model="form.planned_shipping_date" type="date" :class="crmFieldFluid" />
                    </div>
                </div>

                <div class="space-y-2">
                    <label :class="crmLabel">Тема лида</label>
                    <input v-model="form.title" type="text" :class="crmFieldFluid" />
                </div>

                <div class="space-y-2">
                    <label :class="crmLabel">Описание</label>
                    <textarea v-model="form.description" rows="4" :class="crmFieldFluid" />
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <label :class="crmLabel">Контрагент</label>
                            <button
                                type="button"
                                class="shrink-0 rounded-xl border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                @mousedown.prevent
                                @click.stop="openLeadCounterpartyModal"
                            >
                                Новый контрагент
                            </button>
                        </div>
                        <div class="relative">
                            <input
                                v-model="counterpartySearch"
                                type="text"
                                :class="crmFieldFluid"
                                placeholder="Поиск по названию, ИНН, телефону, email"
                                @focus="showCounterpartyResults = true"
                                @blur="hideCounterpartyResultsWithDelay"
                            />
                            <button
                                v-if="form.counterparty_id"
                                type="button"
                                class="absolute right-2 top-2 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100"
                                @click="clearCounterparty"
                            >
                                <X class="h-4 w-4" />
                            </button>
                            <div
                                v-if="showCounterpartyResults && (combinedCounterpartyResults.length > 0 || counterpartySearch.trim().length >= MIN_CONTRACTOR_QUERY_LENGTH)"
                                class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <div v-if="isSearchingCounterparties" class="px-3 py-2 text-center text-xs text-zinc-500">Поиск…</div>
                                <button
                                    v-for="contractor in combinedCounterpartyResults"
                                    :key="contractor.id"
                                    type="button"
                                    class="flex w-full flex-col items-start px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                    @click="selectCounterparty(contractor)"
                                >
                                    <span class="text-sm font-medium">{{ contractor.name }}</span>
                                    <span class="text-xs text-zinc-500">{{ contractor.inn || 'Без ИНН' }}</span>
                                </button>
                                <button
                                    v-if="counterpartySearch.trim().length >= MIN_CONTRACTOR_QUERY_LENGTH && combinedCounterpartyResults.length === 0 && !isSearchingCounterparties"
                                    type="button"
                                    class="w-full border-t border-zinc-100 px-3 py-2 text-left text-sm font-medium text-sky-700 hover:bg-sky-50 dark:border-zinc-800 dark:text-sky-300 dark:hover:bg-sky-950/30"
                                    @mousedown.prevent="openLeadCounterpartyModal"
                                >
                                    Не найдено — создать «{{ counterpartySearch.trim() }}»
                                </button>
                            </div>
                        </div>
                        <p v-if="selectedCounterparty" class="text-xs text-zinc-500">
                            Выбран: {{ selectedCounterparty.name }}{{ selectedCounterparty.inn ? `, ИНН ${selectedCounterparty.inn}` : '' }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <label :class="crmLabel">Тип перевозки</label>
                        <select v-model="form.transport_type" :class="crmFieldFluid">
                            <option value="">Не выбрано</option>
                            <option v-for="option in transportTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label :class="crmLabel">Цена клиента</label>
                        <input v-model="form.target_price" type="number" min="0" step="0.01" :class="crmFieldFluid" />
                    </div>
                    <div class="space-y-2">
                        <label :class="crmLabel">Валюта</label>
                        <select v-model="form.target_currency" :class="crmFieldFluid">
                            <option v-for="option in currencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label :class="crmLabel">Следующий контакт</label>
                        <input v-model="form.next_contact_at" type="datetime-local" :class="crmFieldFluid" />
                    </div>
                </div>

                <LeadCloseOutcomeFields
                    v-if="showLegacyCloseOutcomeFields"
                    v-model:primary-flag="form.close_outcome_primary_flag"
                    v-model:note="form.close_outcome_note"
                    :terminal-outcome="form.status === 'won' ? 'won' : 'lost'"
                    :lost-options="lostCloseOutcomeOptions"
                    :won-options="wonCloseOutcomeOptions"
                    :error="form.errors.close_outcome_primary_flag || form.errors.close_outcome_note"
                    :input-class="crmFieldFluid"
                />

                <section v-if="selectedLeadId" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div>
                        <h3 class="text-base font-semibold">Контекстные документы</h3>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Пакинги, инвойсы и прочие файлы для работы с лидом. Не попадают в реестр «Документы».
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            ref="attachmentInput"
                            type="file"
                            class="max-w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-800 dark:text-zinc-300 dark:file:bg-zinc-800 dark:file:text-zinc-100"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp,.txt"
                            @change="onAttachmentSelected"
                        />
                        <button
                            type="button"
                            :class="crmBtnSecondary"
                            :disabled="!attachmentFile || attachmentForm.processing"
                            @click="addAttachment"
                        >
                            {{ attachmentForm.processing ? 'Загрузка…' : 'Загрузить' }}
                        </button>
                    </div>
                    <div v-if="leadAttachments.length" class="space-y-2">
                        <div
                            v-for="file in leadAttachments"
                            :key="file.id"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700"
                        >
                            <div class="min-w-0">
                                <a :href="file.download_url" class="font-medium text-zinc-900 underline-offset-2 hover:underline dark:text-zinc-100">
                                    {{ file.original_name }}
                                </a>
                                <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ formatAttachmentMeta(file) }}
                                </div>
                            </div>
                            <button type="button" :class="crmBtnSecondary" @click="deleteAttachment(file)">Удалить</button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">Файлов пока нет.</p>
                </section>

                <div class="grid gap-4 xl:grid-cols-4">
                    <div class="space-y-2"><label :class="crmLabel">Потребность</label><input v-model="form.qualification.need" type="text" :class="crmFieldFluid" /></div>
                    <div class="space-y-2"><label :class="crmLabel">Срок</label><input v-model="form.qualification.timeline" type="text" :class="crmFieldFluid" /></div>
                    <div class="space-y-2"><label :class="crmLabel">ЛПР</label><input v-model="form.qualification.authority" type="text" :class="crmFieldFluid" /></div>
                    <div class="space-y-2"><label :class="crmLabel">Бюджет</label><input v-model="form.qualification.budget" type="text" :class="crmFieldFluid" /></div>
                </div>

                <div
                    v-if="selectedLeadId && form.counterparty_id && hasQualificationForPortraitMerge"
                    class="flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-800 dark:bg-zinc-950/40"
                >
                    <p class="min-w-0 flex-1 text-zinc-600 dark:text-zinc-300">
                        Заполненная квалификация может пополнить портрет контрагента без перезаписи уже известных полей.
                    </p>
                    <button
                        type="button"
                        :class="crmBtnSecondary"
                        :disabled="portraitMergeProcessing"
                        @click="mergePortraitFromQualification"
                    >
                        {{ portraitMergeProcessing ? 'Перенос…' : 'Перенести в портрет' }}
                    </button>
                </div>
                <p v-if="portraitMergeMessage" class="text-sm text-emerald-700 dark:text-emerald-300">{{ portraitMergeMessage }}</p>
                <p v-if="portraitMergeError" class="text-sm text-rose-600 dark:text-rose-300">{{ portraitMergeError }}</p>

                <section v-if="selectedLeadId" class="space-y-4 border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold">Следующий шаг</h3>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Лид без следующего шага не должен зависать в воронке.</p>
                        </div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">Открытых задач: {{ openTasks.length }}</div>
                    </div>

                    <div v-if="canUseLeadTasks" class="grid gap-3 xl:grid-cols-[minmax(0,1.4fr),220px,220px,160px]">
                        <input v-model="nextStepForm.title" type="text" :class="crmFieldFluid" placeholder="Например: перезвонить клиенту после расчёта" />
                        <input v-model="nextStepForm.due_at" type="datetime-local" :class="crmFieldFluid" />
                        <select v-model="nextStepForm.responsible_id" :class="crmFieldFluid" :disabled="!canAssignResponsible">
                            <option v-for="user in responsibleUsers" :key="`next-step-${user.id}`" :value="user.id">{{ user.name }}</option>
                        </select>
                        <button type="button" :class="`${crmBtnSecondary} justify-center`" :disabled="nextStepForm.processing || !nextStepForm.title" @click="createNextStep">Создать шаг</button>
                    </div>

                    <div v-else class="text-sm text-zinc-500 dark:text-zinc-400">
                        Модуль задач недоступен для вашей роли.
                    </div>

                    <div class="space-y-2">
                        <div v-for="task in openTasks" :key="task.id" class="flex flex-wrap items-center justify-between gap-3 border border-zinc-200 px-3 py-3 text-sm dark:border-zinc-800">
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ task.title }}</div>
                                <div class="mt-1 text-xs uppercase tracking-[0.18em] text-zinc-500 dark:text-zinc-400">
                                    {{ task.status_label }} · {{ task.responsible_name || '—' }} · {{ formatDateTime(task.due_at) }}
                                </div>
                            </div>
                            <button type="button" :class="crmBtnSecondary" @click="openTask(task.id)">Открыть задачи</button>
                        </div>
                        <div v-if="openTasks.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">Открытых следующих шагов пока нет.</div>
                    </div>
                </section>

                <section v-else class="border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    Сохраните лид, чтобы назначить следующий шаг через модуль задач.
                </section>
            </div>

            <LeadWizardRouteTab
                v-else-if="activeTab === 'route'"
                v-model:route-points="form.route_points"
            />

            <LeadWizardCargoTab
                v-else-if="activeTab === 'cargo'"
                v-model:cargo-items="form.cargo_items"
                :cargo-type-options="cargoTypeOptions"
                :package-type-options="packageTypeOptions"
                :loading-type-options="loadingTypeOptions"
                :truck-body-type-options="truckBodyTypeOptions"
                :trailer-type-options="trailerTypeOptions"
                :cargo-title-suggestions="cargoTitleSuggestions"
            />

            <div v-else-if="activeTab === 'activities'" class="space-y-4">
                <div class="flex items-center justify-between gap-3"><div><h3 class="text-base font-semibold">Коммуникации</h3><p class="text-sm text-zinc-500 dark:text-zinc-400">История контактов и единая лента событий.</p></div><button type="button" :class="crmBtnSecondary" @click="addActivity"><Plus class="h-4 w-4" />Добавить активность</button></div>
                <ActivityTimeline v-if="selectedLeadId" ref="activityTimelineRef" :lead-id="selectedLeadId" />
                <div v-for="(activity, index) in form.activities" :key="`activity-${index}`" class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <select v-model="activity.type" :class="crmFieldFluid"><option value="call">Звонок</option><option value="email">Email</option><option value="meeting">Встреча</option><option value="note">Заметка</option></select>
                        <input v-model="activity.subject" type="text" :class="crmFieldFluid" placeholder="Тема" />
                        <input v-model="activity.next_action_at" type="datetime-local" :class="crmFieldFluid" />
                        <button type="button" :class="`${crmBtnDangerMuted} inline-flex h-11 w-11 shrink-0 items-center justify-center`" @click="removeActivity(index)"><Trash2 class="h-4 w-4" /></button>
                    </div>
                    <textarea v-model="activity.content" rows="3" :class="crmFieldFluid" placeholder="Комментарий" />
                </div>
            </div>

            <LeadWizardCommercialTab
                v-else
                v-model:selected-template-id="selectedTemplateId"
                v-model:selected-html-template-id="selectedHtmlTemplateId"
                :lead-id="selectedLeadId"
                :offers="form.offers"
                :orders="form.orders"
                :print-form-template-options="printFormTemplateOptions"
                :proposal-html-template-options="proposalHtmlTemplateOptions"
                @send-offer="openSendOfferModal"
            />
        </div>

        <div v-if="selectedLeadId" class="flex items-center justify-between gap-4 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Удаление используется для чистки воронки.</div>
            <button type="button" :class="crmBtnDangerMuted" @click="destroyLead"><Trash2 class="h-4 w-4" />Удалить</button>
        </div>

        <div
            v-show="showCounterpartyModal"
            class="fixed inset-0 z-[200] flex items-center justify-center bg-black/40 p-4"
            @click.self="closeLeadCounterpartyModal"
        >
            <div :class="`${crmModalPanel} w-full max-w-xl p-5 shadow-2xl`" @click.stop>
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Новый контрагент</div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                            Запись появится в справочнике контрагентов и будет выбрана в этом лиде.
                        </div>
                    </div>
                    <button type="button" class="rounded-xl p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="closeLeadCounterpartyModal">×</button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <input
                        ref="counterpartyNameInput"
                        v-model="counterpartyForm.name"
                        type="text"
                        placeholder="Название"
                        :class="`${crmFieldFluid} md:col-span-2`"
                    />
                    <input v-model="counterpartyForm.inn" type="text" placeholder="ИНН" :class="crmFieldFluid" />
                    <input v-model="counterpartyForm.kpp" type="text" placeholder="КПП" :class="crmFieldFluid" />
                    <input v-model="counterpartyForm.address" type="text" placeholder="Адрес" :class="`${crmFieldFluid} md:col-span-2`" />
                    <input v-model="counterpartyForm.phone" type="text" placeholder="Телефон" :class="crmFieldFluid" />
                    <input v-model="counterpartyForm.email" type="email" placeholder="Email" :class="crmFieldFluid" />
                    <input v-model="counterpartyForm.contact_person" type="text" placeholder="Контактное лицо" :class="`${crmFieldFluid} md:col-span-2`" />
                </div>
                <p v-if="inlineContractorError" class="mt-2 text-xs text-rose-600 dark:text-rose-400">{{ inlineContractorError }}</p>

                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" :class="crmBtnSecondary" @click="closeLeadCounterpartyModal">Отмена</button>
                    <button
                        type="button"
                        :class="crmBtnPrimary"
                        :disabled="inlineContractorSaving"
                        @click="createInlineLeadCounterparty"
                    >
                        {{ inlineContractorSaving ? 'Создание…' : 'Создать' }}
                    </button>
                </div>
            </div>
        </div>
        <div
            v-show="showSendOfferModal"
            class="fixed inset-0 z-[200] flex items-center justify-center bg-black/40 p-4"
            @click.self="closeSendOfferModal"
        >
            <form :class="`${crmModalPanel} w-full max-w-lg space-y-3 p-5 shadow-2xl`" @submit.prevent="submitSendOffer">
                <div class="text-lg font-semibold">Отправить КП по e-mail</div>
                <input v-model="sendOfferForm.to_raw" type="text" :class="crmFieldFluid" placeholder="Кому (через запятую)" />
                <input v-model="sendOfferForm.subject" type="text" :class="crmFieldFluid" placeholder="Тема" />
                <textarea v-model="sendOfferForm.body" rows="5" :class="crmFieldFluid" placeholder="Текст письма" />
                <div class="flex justify-end gap-2">
                    <button type="button" :class="crmBtnSecondary" @click="closeSendOfferModal">Отмена</button>
                    <button type="submit" :class="crmBtnPrimary" :disabled="sendOfferForm.processing">Отправить</button>
                </div>
            </form>
        </div>
</div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRightLeft, ClipboardList, FileText, History, MapPinned, Package, Plus, Save, Trash2, X } from 'lucide-vue-next';
import ActivityTimeline from '@/Components/CommercialIntelligence/ActivityTimeline.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import LeadCloseOutcomeFields from '@/Components/Leads/LeadCloseOutcomeFields.vue';
import LeadProcessPanel from '@/Components/Leads/LeadProcessPanel.vue';
import LeadWizardCargoTab from '@/Components/Leads/LeadWizardCargoTab.vue';
import LeadWizardCommercialTab from '@/Components/Leads/LeadWizardCommercialTab.vue';
import LeadWizardRouteTab from '@/Components/Leads/LeadWizardRouteTab.vue';
import {
    ensureContractorPartyAutofill,
    isCompleteContractorInn,
    normalizedContractorInn,
} from '@/support/contractorPartyAutofill.js';
import { warnIfDocumentExceedsBudget } from '@/support/documentUploadClientCheck.js';
import { normalizeLeadCargoItems } from '@/support/leadWizardCargo.js';
import { defaultLeadRoutePoints, normalizeLeadRoutePoints } from '@/support/leadWizardRoute.js';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';
import {
    crmBtnCreate,
    crmBtnDangerMuted,
    crmBtnPrimary,
    crmBtnSecondary,
    crmFieldFluid,
    crmLabel,
    crmWizardBack,
    crmWizardBody,
    crmWizardHeader,
    crmWizardShell,
    crmModalPanel,
} from '@/support/crmUi.js';

defineOptions({ layout: (h, page) => h(CrmLayout, { activeKey: 'leads' }, () => page) });

const props = defineProps({
    selectedLead: Object,
    leadTemplate: {
        type: Object,
        default: null,
    },
    isCreating: Boolean,
    embedded: {
        type: Boolean,
        default: false,
    },
    contractors: Array,
    responsibleUsers: Array,
    statusOptions: Array,
    sourceOptions: Array,
    transportTypeOptions: Array,
    currencyOptions: Array,
    printFormTemplateOptions: Array,
    proposalHtmlTemplateOptions: {
        type: Array,
        default: () => [],
    },
    currentUserId: Number,
    canAssignResponsible: Boolean,
    canUseLeadTasks: Boolean,
    businessProcessesEnabled: Boolean,
    businessProcesses: {
        type: Array,
        default: () => [],
    },
    lostCloseOutcomeOptions: {
        type: Array,
        default: () => [],
    },
    wonCloseOutcomeOptions: {
        type: Array,
        default: () => [],
    },
    cargoTypeOptions: {
        type: Array,
        default: () => [],
    },
    packageTypeOptions: {
        type: Array,
        default: () => [],
    },
    loadingTypeOptions: {
        type: Array,
        default: () => [],
    },
    truckBodyTypeOptions: {
        type: Array,
        default: () => [],
    },
    trailerTypeOptions: {
        type: Array,
        default: () => [],
    },
    cargoTitleSuggestions: {
        type: Array,
        default: () => [],
    },
});

const dictionaryProps = computed(() => ({
    cargoTypeOptions: props.cargoTypeOptions,
    packageTypeOptions: props.packageTypeOptions,
    loadingTypeOptions: props.loadingTypeOptions,
    truckBodyTypeOptions: props.truckBodyTypeOptions,
    trailerTypeOptions: props.trailerTypeOptions,
}));

const emit = defineEmits(['close']);

const activeTab = ref('main');
const selectedTemplateId = ref('');
const selectedHtmlTemplateId = ref('');
const contractors = ref([...props.contractors]);
const tabs = [
    { key: 'main', label: 'Основное', icon: ClipboardList },
    { key: 'route', label: 'Маршрут', icon: MapPinned },
    { key: 'cargo', label: 'Груз', icon: Package },
    { key: 'activities', label: 'Коммуникации', icon: History },
    { key: 'commercial', label: 'Коммерческое', icon: FileText },
];

function defaultResponsibleId() {
    const currentUserId = Number(props.currentUserId);
    if (Number.isFinite(currentUserId) && currentUserId > 0) {
        return currentUserId;
    }

    const fallbackUserId = Number(props.responsibleUsers?.[0]?.id);

    return Number.isFinite(fallbackUserId) && fallbackUserId > 0 ? fallbackUserId : null;
}

function blankForm() {
    return {
        number: '',
        status: 'new',
        source: '',
        counterparty_id: null,
        responsible_id: defaultResponsibleId(),
        title: '',
        description: '',
        transport_type: '',
        loading_location: '',
        unloading_location: '',
        planned_shipping_date: '',
        target_price: null,
        target_currency: 'RUB',
        calculated_cost: null,
        expected_margin: null,
        next_contact_at: '',
        lost_reason: '',
        close_outcome_primary_flag: '',
        close_outcome_note: '',
        qualification: { need: '', timeline: '', authority: '', budget: '' },
        route_points: defaultLeadRoutePoints(),
        cargo_items: normalizeLeadCargoItems([], dictionaryProps.value),
        activities: [],
        offers: [],
        orders: [],
        tasks: [],
        attachments: [],
        business_process_id: props.businessProcesses?.[0]?.id ?? null,
        process_progress: null,
        link_task_id: null,
    };
}

function leadToForm(lead) {
    if (!lead) {
        return blankForm();
    }

    return {
        ...blankForm(),
        ...lead,
        qualification: {
            need: lead.qualification?.need ?? '',
            timeline: lead.qualification?.timeline ?? '',
            authority: lead.qualification?.authority ?? '',
            budget: lead.qualification?.budget ?? '',
        },
        close_outcome_primary_flag: lead.close_outcome_primary_flag ?? '',
        close_outcome_note: lead.lost_reason ?? '',
        route_points: normalizeLeadRoutePoints(lead.route_points),
        cargo_items: normalizeLeadCargoItems(lead.cargo_items, dictionaryProps.value),
        activities: lead.activities ?? [],
        offers: lead.offers ?? [],
        orders: lead.orders ?? [],
        tasks: lead.tasks ?? [],
        attachments: lead.attachments ?? [],
    };
}

const page = usePage();
const followUpPrompt = ref(null);

watch(
    () => page.props.flash?.lead_follow_up,
    (value) => {
        if (value) {
            followUpPrompt.value = value;
        }
    },
    { immediate: true },
);

const initialLeadPayload = computed(() => props.selectedLead ?? props.leadTemplate ?? null);

const form = useForm(leadToForm(initialLeadPayload.value));
const advanceStageId = ref('');
const processStageForm = useForm({
    stage_id: null,
    close_outcome_primary_flag: '',
    close_outcome_note: '',
});
const nextStepForm = useForm({
    title: '',
    description: '',
    due_at: '',
    responsible_id: defaultResponsibleId(),
    priority: 'high',
});

watch(() => [props.selectedLead, props.leadTemplate], () => {
    const payload = leadToForm(initialLeadPayload.value);
    form.defaults(payload);
    form.reset();
    Object.entries(payload).forEach(([key, value]) => { form[key] = value; });
    activeTab.value = 'main';
    selectedTemplateId.value = props.printFormTemplateOptions?.[0]?.id ? String(props.printFormTemplateOptions[0].id) : '';
    nextStepForm.reset();
    nextStepForm.responsible_id = payload.responsible_id ?? defaultResponsibleId();
    nextStepForm.priority = 'high';
    advanceStageId.value = '';
}, { immediate: true });

const selectedLeadId = computed(() => props.selectedLead?.id ?? null);
const processProgress = computed(() => form.process_progress ?? props.selectedLead?.process_progress ?? null);
const showLegacyCloseOutcomeFields = computed(() => {
    if (processProgress.value) {
        return false;
    }

    return form.status === 'lost' || form.status === 'won';
});
const leadAttachments = computed(() => form.attachments ?? props.selectedLead?.attachments ?? []);
const attachmentInput = ref(null);
const attachmentFile = ref(null);
const attachmentForm = useForm({ file: null });
const businessProcessesEnabled = computed(() => Boolean(props.businessProcessesEnabled));
const selectedCounterparty = computed(() => contractors.value.find((contractor) => Number(contractor.id) === Number(form.counterparty_id)) ?? null);
const counterpartyPortraitIncomplete = computed(() => {
    const coverage = props.selectedLead?.counterparty_portrait_coverage_pct;

    return form.counterparty_id && coverage !== null && coverage !== undefined && Number(coverage) < 50;
});
const hasQualificationForPortraitMerge = computed(() => {
    const q = form.qualification ?? {};

    return ['need', 'timeline', 'authority', 'budget'].some((key) => String(q[key] ?? '').trim() !== '');
});
const portraitMergeProcessing = ref(false);
const portraitMergeMessage = ref('');
const portraitMergeError = ref('');
const canAssignResponsible = computed(() => Boolean(props.canAssignResponsible));
const canUseLeadTasks = computed(() => Boolean(props.canUseLeadTasks));
const openTasks = computed(() => (form.tasks ?? []).filter((task) => !['done', 'cancelled'].includes(task.status)));

const MIN_CONTRACTOR_QUERY_LENGTH = 2;
const counterpartySearch = ref('');
const showCounterpartyResults = ref(false);
const isSearchingCounterparties = ref(false);
const serverCounterpartyResults = ref([]);
const counterpartySearchTimer = ref(null);
const counterpartyAbortController = ref(null);
const counterpartyFetchSeq = ref(0);

const showCounterpartyModal = ref(false);
const counterpartyNameInput = ref(null);
const inlineContractorSaving = ref(false);
const inlineContractorError = ref('');
const leadCounterpartyInnLookupTimer = ref(null);
const leadCounterpartyLastAutofilledInn = ref('');

const counterpartyForm = useForm({
    name: '',
    inn: '',
    kpp: '',
    address: '',
    phone: '',
    email: '',
    contact_person: '',
    type: 'customer',
});

const filteredCounterparties = computed(() => {
    const query = counterpartySearch.value.trim().toLowerCase();
    const source = contractors.value.filter((contractor) => contractor.type === 'customer' || contractor.type === 'both');

    if (query === '' || query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        return source.slice(0, 50);
    }

    return source
        .filter((contractor) => [contractor.name, contractor.full_name, contractor.inn, contractor.phone, contractor.email]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)))
        .slice(0, 50);
});

const combinedCounterpartyResults = computed(() => {
    return [...serverCounterpartyResults.value, ...filteredCounterparties.value]
        .filter((contractor, index, array) => array.findIndex((item) => Number(item.id) === Number(contractor.id)) === index)
        .slice(0, 50);
});

watch(() => form.counterparty_id, (counterpartyId) => {
    const selected = contractors.value.find((contractor) => Number(contractor.id) === Number(counterpartyId));
    counterpartySearch.value = selected?.name ?? '';
}, { immediate: true });

watch(counterpartySearch, (newQuery) => {
    clearTimeout(counterpartySearchTimer.value);

    const trimmed = newQuery.trim();
    if (trimmed.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        counterpartyAbortController.value?.abort();
        counterpartyFetchSeq.value += 1;
        serverCounterpartyResults.value = [];
        isSearchingCounterparties.value = false;
        return;
    }

    counterpartySearchTimer.value = setTimeout(async () => {
        await searchCounterparties(trimmed);
    }, 550);
});

function goBack() {
    if (props.embedded) {
        emit('close');

        return;
    }

    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    router.get(route('leads.index'));
}

function ensureCounterpartyInLocalList(contractor) {
    if (!contractor?.id) {
        return;
    }

    if (contractors.value.some((row) => Number(row.id) === Number(contractor.id))) {
        return;
    }

    contractors.value.unshift({ ...contractor });
}

async function searchCounterparties(query) {
    if (query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
        serverCounterpartyResults.value = [];
        return;
    }

    counterpartyAbortController.value?.abort();
    const ac = new AbortController();
    counterpartyAbortController.value = ac;
    const seq = (counterpartyFetchSeq.value += 1);
    isSearchingCounterparties.value = true;

    try {
        const response = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(query)}&type=customer&limit=100`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include',
            signal: ac.signal,
        });

        if (!response.ok) {
            throw new Error(`Search failed with status ${response.status}`);
        }

        const data = await response.json();
        if (seq !== counterpartyFetchSeq.value) {
            return;
        }

        serverCounterpartyResults.value = data.contractors || [];
    } catch (error) {
        if (error?.name !== 'AbortError' && seq === counterpartyFetchSeq.value) {
            serverCounterpartyResults.value = [];
        }
    } finally {
        if (seq === counterpartyFetchSeq.value) {
            isSearchingCounterparties.value = false;
        }
    }
}

function selectCounterparty(contractor) {
    ensureCounterpartyInLocalList(contractor);
    form.counterparty_id = Number(contractor.id);
    counterpartySearch.value = contractor.name;
    showCounterpartyResults.value = false;
}

function clearCounterparty() {
    form.counterparty_id = null;
    counterpartySearch.value = '';
    showCounterpartyResults.value = false;
}

function hideCounterpartyResultsWithDelay() {
    setTimeout(() => {
        showCounterpartyResults.value = false;
    }, 150);
}

async function lookupLeadCounterpartyByInn() {
    const normalizedInn = normalizedContractorInn(counterpartyForm.inn);
    if (!isCompleteContractorInn(normalizedInn)) {
        return;
    }

    if (normalizedInn === leadCounterpartyLastAutofilledInn.value && String(counterpartyForm.name ?? '').trim() !== '') {
        return;
    }

    counterpartyForm.inn = normalizedInn;
    const filled = await ensureContractorPartyAutofill(counterpartyForm, { force: normalizedInn !== leadCounterpartyLastAutofilledInn.value });
    if (filled) {
        leadCounterpartyLastAutofilledInn.value = normalizedInn;
    }
}

watch(() => counterpartyForm.inn, (inn) => {
    clearTimeout(leadCounterpartyInnLookupTimer.value);

    const normalizedInn = normalizedContractorInn(inn);
    if (isCompleteContractorInn(normalizedInn) && counterpartyForm.inn !== normalizedInn) {
        counterpartyForm.inn = normalizedInn;
    }

    if (!isCompleteContractorInn(normalizedInn)) {
        leadCounterpartyLastAutofilledInn.value = '';

        return;
    }

    if (normalizedInn === leadCounterpartyLastAutofilledInn.value && String(counterpartyForm.name ?? '').trim() !== '') {
        return;
    }

    leadCounterpartyInnLookupTimer.value = window.setTimeout(() => {
        lookupLeadCounterpartyByInn();
    }, 500);
});

async function openLeadCounterpartyModal() {
    inlineContractorError.value = '';
    counterpartyForm.clearErrors();
    counterpartyForm.reset();
    counterpartyForm.type = 'customer';
    const searchTrimmed = counterpartySearch.value.trim();
    if (isCompleteContractorInn(searchTrimmed)) {
        counterpartyForm.inn = normalizedContractorInn(searchTrimmed);
        counterpartyForm.name = '';
    } else {
        counterpartyForm.name = searchTrimmed;
    }
    leadCounterpartyLastAutofilledInn.value = '';
    showCounterpartyModal.value = true;
    showCounterpartyResults.value = false;
    await nextTick();
    if (isCompleteContractorInn(counterpartyForm.inn)) {
        await lookupLeadCounterpartyByInn();
    } else {
        counterpartyNameInput.value?.focus?.();
    }
}

function closeLeadCounterpartyModal() {
    showCounterpartyModal.value = false;
    inlineContractorError.value = '';
    counterpartyForm.clearErrors();
}

async function createInlineLeadCounterparty() {
    inlineContractorError.value = '';
    counterpartyForm.clearErrors();
    inlineContractorSaving.value = true;

    try {
        if (isCompleteContractorInn(counterpartyForm.inn) && !String(counterpartyForm.name ?? '').trim()) {
            const filled = await ensureContractorPartyAutofill(counterpartyForm);
            if (!filled) {
                inlineContractorError.value = 'Не удалось получить данные по ИНН. Укажите название вручную.';
                return;
            }
        }

        if (!String(counterpartyForm.name ?? '').trim()) {
            inlineContractorError.value = 'Укажите название контрагента или корректный ИНН.';
            return;
        }

        const response = await fetch(route('leads.contractors.store'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(counterpartyForm.data()),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 422 && payload.errors) {
                const first = Object.values(payload.errors).flat()[0];
                inlineContractorError.value = first || 'Проверьте введённые данные.';
            } else {
                inlineContractorError.value = payload.message || 'Не удалось создать контрагента.';
            }

            return;
        }

        const contractor = payload.contractor;
        if (contractor?.id) {
            ensureCounterpartyInLocalList(contractor);
            selectCounterparty(contractor);
        }

        counterpartyForm.reset();
        counterpartyForm.type = 'customer';
        closeLeadCounterpartyModal();
    } catch (e) {
        inlineContractorError.value = 'Ошибка сети при создании контрагента.';
        console.error(e);
    } finally {
        inlineContractorSaving.value = false;
    }
}

async function mergePortraitFromQualification() {
    if (!selectedLeadId.value || !form.counterparty_id) {
        return;
    }

    portraitMergeProcessing.value = true;
    portraitMergeMessage.value = '';
    portraitMergeError.value = '';

    try {
        const response = await fetch(route('leads.portrait-merge', selectedLeadId.value), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ qualification: form.qualification }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            portraitMergeError.value = payload.message || 'Не удалось перенести данные в портрет.';

            return;
        }

        portraitMergeMessage.value = payload.message || 'Данные перенесены в портрет контрагента.';
        router.reload({ only: ['selectedLead'], preserveScroll: true });
    } catch (error) {
        portraitMergeError.value = 'Ошибка сети при переносе в портрет.';
        console.error(error);
    } finally {
        portraitMergeProcessing.value = false;
    }
}

function addActivity() { form.activities.push({ type: 'note', subject: '', content: '', next_action_at: '' }); }
function removeActivity(index) { form.activities.splice(index, 1); }
function openTask(taskId) { router.get(route('tasks.index'), taskId ? { task: taskId } : {}); }

async function onAttachmentSelected(event) {
    const files = event.target?.files;
    const picked = files && files[0] ? files[0] : null;
    if (picked) {
        await warnIfDocumentExceedsBudget(picked, page.props.document_upload_limits ?? {});
    }
    attachmentFile.value = picked;
    attachmentForm.file = attachmentFile.value;
}

function addAttachment() {
    if (!selectedLeadId.value || !attachmentFile.value) {
        return;
    }

    attachmentForm.post(route('leads.attachments.store', selectedLeadId.value), {
        preserveScroll: true,
        only: ['selectedLead'],
        forceFormData: true,
        onSuccess: () => {
            attachmentForm.reset();
            attachmentFile.value = null;
            if (attachmentInput.value) {
                attachmentInput.value.value = '';
            }
        },
    });
}

function deleteAttachment(file) {
    if (!selectedLeadId.value || !file?.id) {
        return;
    }

    router.delete(route('leads.attachments.destroy', [selectedLeadId.value, file.id]), {
        preserveScroll: true,
        only: ['selectedLead'],
    });
}

function formatAttachmentMeta(file) {
    const parts = [];
    if (file.uploaded_by) {
        parts.push(file.uploaded_by);
    }
    if (file.created_at) {
        const date = new Date(file.created_at);
        if (!Number.isNaN(date.getTime())) {
            parts.push(new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(date));
        }
    }
    if (file.size_bytes) {
        const kb = Math.round(Number(file.size_bytes) / 1024);
        parts.push(kb > 1024 ? `${(kb / 1024).toFixed(1)} МБ` : `${kb} КБ`);
    }

    return parts.length ? parts.join(' · ') : '—';
}

function submitProcessStage() {
    if (!selectedLeadId.value || !advanceStageId.value) {
        return;
    }

    processStageForm.stage_id = advanceStageId.value;
    processStageForm.patch(route('leads.process-stage', selectedLeadId.value), {
        preserveScroll: true,
        onSuccess: () => {
            advanceStageId.value = '';
            processStageForm.close_outcome_primary_flag = '';
            processStageForm.close_outcome_note = '';
        },
    });
}

function submit() {
    const payload = {
        ...form.data(),
        offers: undefined,
        orders: undefined,
        tasks: undefined,
        attachments: undefined,
        process_progress: undefined,
    };

    if (selectedLeadId.value) {
        router.patch(route('leads.update', selectedLeadId.value), payload);
        return;
    }

    router.post(route('leads.store'), payload);
}

const showSendOfferModal = ref(false);
const sendOfferTarget = ref(null);
const activityTimelineRef = ref(null);
const sendOfferForm = useForm({
    to_raw: '',
    subject: '',
    body: '',
});

function openSendOfferModal(offer) {
    sendOfferTarget.value = offer;
    const counterparty = contractors.value.find((c) => c.id === form.counterparty_id);
    const emails = [counterparty?.contact_person_email, counterparty?.email].filter(Boolean);
    sendOfferForm.to_raw = [...new Set(emails)].join(', ');
    sendOfferForm.subject = `Коммерческое предложение ${offer.number || ''}`.trim();
    sendOfferForm.body = `Добрый день!\n\nНаправляем коммерческое предложение по перевозке «${form.title}».`;
    showSendOfferModal.value = true;
}

function closeSendOfferModal() {
    showSendOfferModal.value = false;
    sendOfferTarget.value = null;
}

function submitSendOffer() {
    if (!selectedLeadId.value || !sendOfferTarget.value) {
        return;
    }

    const to = sendOfferForm.to_raw
        .split(/[,;]/)
        .map((s) => s.trim())
        .filter(Boolean);

    sendOfferForm
        .transform((data) => ({ ...data, to }))
        .post(route('leads.offers.send-email', [selectedLeadId.value, sendOfferTarget.value.id]), {
            preserveScroll: true,
            onSuccess: () => {
                closeSendOfferModal();
                activityTimelineRef.value?.reload?.();
            },
        });
}
function convertLead() { if (selectedLeadId.value) router.post(route('leads.convert', selectedLeadId.value), {}); }
function destroyLead() { if (selectedLeadId.value) router.delete(route('leads.destroy', selectedLeadId.value)); }
function prefillFollowUpTask() {
    if (!followUpPrompt.value) {
        return;
    }

    nextStepForm.title = followUpPrompt.value.suggested_title || 'Узнать новости у клиента';
    followUpPrompt.value = null;
}

function createNextStep() {
    if (!selectedLeadId.value) {
        return;
    }

    nextStepForm.post(route('leads.next-step.store', selectedLeadId.value), {
        preserveScroll: true,
        onSuccess: () => {
            nextStepForm.reset();
            nextStepForm.responsible_id = form.responsible_id;
            nextStepForm.priority = 'high';
        },
    });
}
function formatDateTime(value) {
    if (!value) { return '—'; }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) { return value; }
    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(date);
}
</script>


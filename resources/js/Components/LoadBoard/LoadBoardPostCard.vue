<template>
    <article :class="`${crmPanel} overflow-hidden`">
        <div class="border-b border-zinc-200 px-5 pt-5 dark:border-zinc-800">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="border px-2 py-1 text-xs font-semibold uppercase tracking-wide" :class="statusClass(post.status)">
                            {{ statusLabels[post.status] ?? post.status }}
                        </span>
                        <span class="border px-2 py-1 text-xs font-semibold uppercase tracking-wide" :class="priorityClass(post.priority)">
                            {{ priorityLabels[post.priority] ?? post.priority }}
                        </span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">#{{ post.id }}</span>
                    </div>
                    <h2 class="mt-2 text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ post.title }}</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ routeSummary(post) }}</p>
                    <Link
                        v-if="!fullPage"
                        :href="route('load-board.cases.show', post.id)"
                        class="mt-2 inline-flex text-sm font-medium text-indigo-700 hover:underline dark:text-indigo-300"
                    >
                        Открыть страницу кейса
                    </Link>
                </div>
                <div class="text-right text-xs text-zinc-500 dark:text-zinc-400">
                    <div>Продавец: {{ post.seller?.name ?? '—' }}</div>
                    <div>Закупщик: {{ post.buyer?.name ?? 'не назначен' }}</div>
                    <div v-if="post.accepted_at">Принял: {{ post.accepter?.name ?? '—' }}</div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    v-for="tab in detailTabs"
                    :key="tab.key"
                    type="button"
                    :class="crmTabButtonClasses(activeTab === tab.key)"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                    <span v-if="tab.key === 'offers'" class="opacity-70">({{ post.offers?.length ?? 0 }})</span>
                </button>
            </div>
        </div>

        <div class="p-5">
            <div v-if="activeTab === 'overview'" class="space-y-4">
                <div
                    v-if="advisorState?.risk_factors?.length"
                    class="border p-3 text-sm"
                    :class="riskBannerClass(advisorState.risk_level)"
                >
                    <div class="text-xs font-semibold uppercase tracking-wide">Риск срыва · {{ riskLevelLabel(advisorState.risk_level) }}</div>
                    <p class="mt-1">{{ advisorState.summary }}</p>
                    <ul v-if="advisorState.risk_factors.length > 1" class="mt-2 list-disc space-y-0.5 pl-5 text-xs opacity-90">
                        <li v-for="factor in advisorState.risk_factors" :key="factor.code">{{ factor.label }}</li>
                    </ul>
                </div>

                <dl class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">Даты</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ dateRange(post) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">Груз</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ cargoSummary(post) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">Ставка клиента</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ money(post.customer_rate, post.customer_rate_currency) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500">Целевая себестоимость</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ money(post.target_carrier_rate, post.customer_rate_currency) }}</dd>
                    </div>
                </dl>

                <div
                    v-if="post.procurement_case"
                    class="space-y-3 border border-indigo-200 bg-indigo-50/50 p-4 text-sm dark:border-indigo-900/50 dark:bg-indigo-950/20"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">
                            Кейс закупки #{{ post.procurement_case.id }}
                        </div>
                        <span class="text-xs text-indigo-600 dark:text-indigo-200">{{ statusLabels[post.procurement_case.status] ?? post.procurement_case.status }}</span>
                    </div>
                    <dl class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-zinc-500">Владелец сделки</dt>
                            <dd class="mt-1">{{ post.procurement_case.order_owner?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-zinc-500">Диспетчер</dt>
                            <dd class="mt-1">{{ post.procurement_case.dispatcher?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-zinc-500">Закупщик</dt>
                            <dd class="mt-1">{{ post.procurement_case.buyer?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-zinc-500">Юрлицо закупки</dt>
                            <dd class="mt-1">{{ post.procurement_case.buying_own_company?.name ?? '—' }}</dd>
                        </div>
                    </dl>
                    <div v-if="procurementLinkedOrders.length || procurementLinkedLeads.length" class="space-y-2">
                        <div v-if="procurementLinkedOrders.length" class="space-y-1">
                            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Заказы</div>
                            <ul class="space-y-1">
                                <li v-for="order in procurementLinkedOrders" :key="`pc-order-${order.id}`">
                                    <Link :href="route('orders.edit', order.id)" class="text-indigo-700 hover:underline dark:text-indigo-300">
                                        {{ order.order_number || `#${order.id}` }}
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        <div v-if="procurementLinkedLeads.length" class="space-y-1">
                            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Лиды</div>
                            <ul class="space-y-1">
                                <li v-for="lead in procurementLinkedLeads" :key="`pc-lead-${lead.id}`">
                                    <Link :href="route('leads.show', lead.id)" class="text-indigo-700 hover:underline dark:text-indigo-300">
                                        {{ lead.number || lead.title || `#${lead.id}` }}
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div v-if="!isClosed(post)" class="flex flex-wrap items-end gap-2">
                        <label class="flex flex-col gap-1 text-xs font-medium text-zinc-500">
                            <span>Привязать заказ</span>
                            <select v-model="linkOrderId" class="border border-zinc-200 bg-white px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                <option value="">Выберите</option>
                                <option v-for="order in orderOptions" :key="`link-order-${order.id}`" :value="order.id">
                                    {{ order.order_number || `#${order.id}` }}
                                </option>
                            </select>
                        </label>
                        <button type="button" :class="crmBtnNeutral" :disabled="!linkOrderId" @click="attachProcurementLink('order', linkOrderId)">
                            Добавить заказ
                        </button>
                        <label class="flex flex-col gap-1 text-xs font-medium text-zinc-500">
                            <span>Привязать лид</span>
                            <select v-model="linkLeadId" class="border border-zinc-200 bg-white px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                <option value="">Выберите</option>
                                <option v-for="lead in leadOptions" :key="`link-lead-${lead.id}`" :value="lead.id">
                                    {{ lead.number || lead.title || `#${lead.id}` }}
                                </option>
                            </select>
                        </label>
                        <button type="button" :class="crmBtnNeutral" :disabled="!linkLeadId" @click="attachProcurementLink('lead', linkLeadId)">
                            Добавить лид
                        </button>
                    </div>
                </div>

                <div class="grid gap-3 text-sm md:grid-cols-2">
                    <div v-if="post.requirements" class="border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900/40">
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Требования</div>
                        <p class="mt-1 whitespace-pre-wrap text-zinc-800 dark:text-zinc-200">{{ post.requirements }}</p>
                    </div>
                    <div v-if="post.seller_comment" class="border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900/40">
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Комментарий продавца</div>
                        <p class="mt-1 whitespace-pre-wrap text-zinc-800 dark:text-zinc-200">{{ post.seller_comment }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <label v-if="!isClosed(post)" class="flex items-center gap-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                        <span>Закупщик</span>
                        <select
                            :value="post.buyer_id ?? ''"
                            class="border border-zinc-200 bg-white px-2 py-1.5 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                            @change="assignBuyer($event.target.value)"
                        >
                            <option value="">Не назначен</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                    </label>
                    <button v-if="!isClosed(post) && post.buyer_id !== currentUserId" type="button" :class="crmBtnNeutral" @click="takePost">Взять в работу</button>
                    <button v-if="post.buyer_id === currentUserId && !isClosed(post)" type="button" :class="crmBtnNeutral" @click="releasePost">Вернуть в общий список</button>
                    <button v-if="!isClosed(post)" type="button" :class="crmBtnNeutral" @click="openOfferForm">Добавить оффер</button>
                    <button v-if="!isClosed(post)" type="button" :class="crmBtnCreate" @click="setStatus('closed')">Закрыть</button>
                    <button v-if="!isClosed(post)" type="button" :class="crmBtnNeutral" @click="setStatus('no_options')">Без вариантов</button>
                    <button v-if="!isClosed(post)" type="button" :class="crmBtnDangerMuted" @click="setStatus('cancelled')">Отменить</button>
                </div>
            </div>

            <div v-else-if="activeTab === 'offers'" class="space-y-4">
                <div v-if="advisorLoading" class="text-sm text-zinc-500 dark:text-zinc-400">Советник анализирует офферы и коридор…</div>
                <div
                    v-else-if="advisorState"
                    class="space-y-3 border border-violet-200 bg-violet-50/70 p-3 text-sm text-violet-950 dark:border-violet-900/50 dark:bg-violet-950/20 dark:text-violet-100"
                >
                    <div class="text-xs font-semibold uppercase tracking-wide text-violet-700 dark:text-violet-300">AI-советник</div>
                    <p>{{ advisorState.summary }}</p>
                </div>

                <div v-if="advisorLoading && !rateInsights" class="text-sm text-zinc-500 dark:text-zinc-400">Считаем коридор ставок по похожим грузам…</div>
                <div
                    v-else-if="rateInsights?.available"
                    class="border border-emerald-200 bg-emerald-50/70 p-3 text-sm text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/20 dark:text-emerald-100"
                >
                    <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                        Коридор по маршруту (n={{ rateInsights.sample_size }})
                    </div>
                    <p class="mt-1">
                        Перевозчик: {{ money(rateInsights.carrier_rate.min, rateInsights.carrier_rate.currency) }}
                        – {{ money(rateInsights.carrier_rate.max, rateInsights.carrier_rate.currency) }},
                        медиана ~ {{ money(rateInsights.carrier_rate.avg, rateInsights.carrier_rate.currency) }}
                    </p>
                    <p v-if="rateInsights.margin_pct.avg !== null" class="mt-1 text-xs opacity-80">
                        Валовая маржа по истории: {{ rateInsights.margin_pct.min }}% – {{ rateInsights.margin_pct.max }}%
                        (средняя {{ rateInsights.margin_pct.avg }}%, без вычета KPI)
                    </p>
                </div>
                <div v-else class="border border-dashed border-zinc-200 p-3 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    Пока мало данных по этому коридору — коридор появится после нескольких офферов.
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Сравнение офферов
                    </h3>
                    <button v-if="!isClosed(post)" type="button" :class="crmBtnCreate" @click="openOfferForm">
                        + Оффер
                    </button>
                </div>

                <form
                    v-if="showOfferForm"
                    class="space-y-3 border border-sky-200 bg-sky-50 p-3 dark:border-sky-900/60 dark:bg-sky-950/30"
                    @submit.prevent="submitOffer"
                >
                    <div class="grid gap-2 md:grid-cols-2">
                        <select v-model="offerForm.carrier_id" :class="crmFieldFluid">
                            <option :value="null">Перевозчик не указан</option>
                            <option v-for="contractor in contractors" :key="contractor.id" :value="contractor.id">{{ contractor.name }}</option>
                        </select>
                        <select v-model="offerForm.source" :class="crmFieldFluid">
                            <option v-for="(label, value) in offerSourceOptions" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input v-model="offerForm.carrier_rate" type="number" min="0" step="0.01" :class="crmFieldFluid" placeholder="Ставка перевозчика" />
                        <input v-model="offerForm.carrier_rate_currency" maxlength="3" :class="crmFieldFluid" placeholder="RUB" />
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input v-model="offerForm.payment_form" :class="crmFieldFluid" placeholder="Форма оплаты" />
                        <input v-model="offerForm.available_date" type="date" :class="crmFieldFluid" />
                    </div>
                    <input v-model="offerForm.carrier_contact" :class="crmFieldFluid" placeholder="Контакт перевозчика" />
                    <textarea v-model="offerForm.conditions" rows="2" :class="crmFieldFluid" placeholder="Условия" />
                    <textarea v-model="offerForm.comment" rows="2" :class="crmFieldFluid" placeholder="Комментарий закупщика" />
                    <InputError :message="offerForm.errors.carrier_rate" />
                    <div class="flex justify-end gap-2">
                        <button type="button" :class="crmBtnNeutral" @click="showOfferForm = false">Отмена</button>
                        <button type="submit" :class="crmBtnCreate" :disabled="offerForm.processing">Сохранить оффер</button>
                    </div>
                </form>

                <div v-if="sortedOffers.length === 0" class="border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    Офферов пока нет. Добавьте ответы подрядчиков из CRM, ATI (вручную), звонков или переписки.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-left text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-700">
                                <th class="px-2 py-2">Перевозчик</th>
                                <th class="px-2 py-2">Ставка</th>
                                <th class="px-2 py-2">Маржа</th>
                                <th class="px-2 py-2">Источник</th>
                                <th class="px-2 py-2">Статус</th>
                                <th class="px-2 py-2">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="offer in sortedOffers"
                                :key="offer.id"
                                class="border-b border-zinc-100 align-top dark:border-zinc-800"
                                :class="offer.status === 'selected' || offer.status === 'approved' ? 'bg-sky-50/60 dark:bg-sky-950/20' : ''"
                            >
                                <td class="px-2 py-3">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-50">{{ offer.carrier?.name ?? '—' }}</div>
                                    <div v-if="offerRank(offer.id)" class="mt-0.5 text-xs font-medium text-violet-700 dark:text-violet-300">
                                        Оценка {{ offerRank(offer.id).score }}/100 · {{ offerRank(offer.id).reasons?.[0] }}
                                    </div>
                                    <div v-if="offer.carrier_contact" class="mt-0.5 text-xs text-zinc-500">{{ offer.carrier_contact }}</div>
                                    <div v-if="offer.conditions" class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">{{ offer.conditions }}</div>
                                </td>
                                <td class="px-2 py-3 whitespace-nowrap">
                                    {{ money(offer.carrier_rate, offer.carrier_rate_currency) }}
                                    <div v-if="offer.payment_form" class="text-xs text-zinc-500">{{ offer.payment_form }}</div>
                                </td>
                                <td class="px-2 py-3 whitespace-nowrap">
                                    <template v-if="offer.margin_abs !== null && offer.margin_abs !== undefined">
                                        <span :class="offer.margin_abs >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'">
                                            {{ money(offer.margin_abs, offer.carrier_rate_currency) }}
                                        </span>
                                        <div v-if="offer.margin_pct !== null" class="text-xs text-zinc-500">{{ offer.margin_pct }}%</div>
                                    </template>
                                    <span v-else class="text-zinc-400">—</span>
                                </td>
                                <td class="px-2 py-3 text-xs text-zinc-600 dark:text-zinc-300">{{ offer.source_label ?? '—' }}</td>
                                <td class="px-2 py-3 text-xs uppercase tracking-wide" :class="offerStatusClass(offer.status)">
                                    {{ offerStatusLabel(offer.status) }}
                                </td>
                                <td class="px-2 py-3">
                                    <div class="flex flex-col gap-1">
                                        <button
                                            v-if="!['selected', 'approved'].includes(offer.status) && !isClosed(post)"
                                            type="button"
                                            class="text-left text-sm font-medium text-sky-700 hover:underline dark:text-sky-300"
                                            @click="selectOffer(offer)"
                                        >
                                            Выбрать
                                        </button>
                                        <button
                                            v-if="post.status === 'seller_review' && offer.status === 'selected'"
                                            type="button"
                                            class="text-left text-sm font-medium text-emerald-700 hover:underline dark:text-emerald-300"
                                            @click="approveOffer(offer)"
                                        >
                                            Принять
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else-if="activeTab === 'pool'" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Пул перевозчиков ({{ carrierPoolState.total ?? 0 }})
                    </h3>
                    <button
                        v-if="!isClosed(post)"
                        type="button"
                        :class="crmBtnCreate"
                        @click="openCandidateForm"
                    >
                        + Кандидат
                    </button>
                </div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Кандидат — ориентир из звонка, ATI или переписки без полного оффера. Офферы из вкладки «Офферы» попадают в пул автоматически.
                </p>

                <form
                    v-if="showCandidateForm"
                    class="space-y-3 border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-900/50 dark:bg-amber-950/20"
                    @submit.prevent="submitCandidate"
                >
                    <div class="grid gap-2 md:grid-cols-2">
                        <select v-model="candidateForm.carrier_id" :class="crmFieldFluid">
                            <option :value="null">Перевозчик из справочника</option>
                            <option v-for="contractor in contractors" :key="`pool-${contractor.id}`" :value="contractor.id">{{ contractor.name }}</option>
                        </select>
                        <select v-model="candidateForm.source" :class="crmFieldFluid">
                            <option v-for="(label, value) in offerSourceOptions" :key="`pool-source-${value}`" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <input
                        v-model="candidateForm.carrier_name"
                        type="text"
                        :class="crmFieldFluid"
                        placeholder="Название перевозчика (если нет в справочнике)"
                    >
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input v-model="candidateForm.carrier_rate" type="number" min="0" step="0.01" :class="crmFieldFluid" placeholder="Ориентир ставки (необязательно)" />
                        <input v-model="candidateForm.carrier_rate_currency" maxlength="3" :class="crmFieldFluid" placeholder="RUB" />
                    </div>
                    <input v-model="candidateForm.carrier_contact" :class="crmFieldFluid" placeholder="Контакт" />
                    <textarea v-model="candidateForm.conditions" rows="2" :class="crmFieldFluid" placeholder="Условия" />
                    <textarea v-model="candidateForm.comment" rows="2" :class="crmFieldFluid" placeholder="Комментарий" />
                    <InputError :message="candidateForm.errors.carrier_id" />
                    <InputError :message="candidateForm.errors.source" />
                    <div class="flex justify-end gap-2">
                        <button type="button" :class="crmBtnNeutral" @click="showCandidateForm = false">Отмена</button>
                        <button type="submit" :class="crmBtnCreate" :disabled="candidateForm.processing">Добавить в пул</button>
                    </div>
                </form>
                <div v-if="carrierPoolState.sources_summary?.length" class="flex flex-wrap gap-2 text-xs">
                    <span
                        v-for="row in carrierPoolState.sources_summary"
                        :key="row.source"
                        class="border border-zinc-200 px-2 py-1 dark:border-zinc-700"
                    >
                        {{ row.label }}: {{ row.count }}
                    </span>
                </div>
                <div v-if="!carrierPoolState.entries?.length" class="border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    Пул пуст. Добавьте офферы из CRM, ATI, звонков или переписки — они появятся здесь с дедупом по перевозчику и источнику.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-left text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-700">
                                <th class="px-2 py-2">Перевозчик</th>
                                <th class="px-2 py-2">Ставка</th>
                                <th class="px-2 py-2">Источник</th>
                                <th class="px-2 py-2">Статус</th>
                                <th class="px-2 py-2">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="entry in carrierPoolState.entries"
                                :key="entry.pool_key"
                                class="border-b border-zinc-100 dark:border-zinc-800"
                            >
                                <td class="px-2 py-3">
                                    <div class="font-medium">{{ entry.carrier_name ?? '—' }}</div>
                                    <div v-if="entry.carrier_contact" class="text-xs text-zinc-500">{{ entry.carrier_contact }}</div>
                                </td>
                                <td class="px-2 py-3 whitespace-nowrap">
                                    <template v-if="entry.carrier_rate !== null && entry.carrier_rate !== undefined">
                                        {{ money(entry.carrier_rate, entry.carrier_rate_currency) }}
                                    </template>
                                    <span v-else class="text-zinc-400">—</span>
                                </td>
                                <td class="px-2 py-3 text-xs">{{ entry.source_label }}</td>
                                <td class="px-2 py-3 text-xs uppercase">{{ poolStatusLabel(entry) }}</td>
                                <td class="px-2 py-3">
                                    <div class="flex flex-col gap-1">
                                        <button
                                            v-if="entry.kind === 'candidate' && !isClosed(post)"
                                            type="button"
                                            class="text-left text-sm font-medium text-sky-700 hover:underline dark:text-sky-300"
                                            @click="promoteCandidateToOffer(entry)"
                                        >
                                            В оффер
                                        </button>
                                        <button
                                            v-if="entry.kind === 'candidate' && entry.candidate_id && !isClosed(post)"
                                            type="button"
                                            class="text-left text-sm font-medium text-rose-700 hover:underline dark:text-rose-300"
                                            @click="removeCandidate(entry.candidate_id)"
                                        >
                                            Удалить
                                        </button>
                                        <span v-else-if="entry.kind === 'offer'" class="text-xs text-zinc-400">оффер</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else-if="activeTab === 'ati'" class="space-y-4">
                <div v-if="atiSummary(post)" class="border border-sky-100 bg-sky-50/70 p-3 text-sm text-sky-900 dark:border-sky-900/50 dark:bg-sky-950/20 dark:text-sky-100">
                    <div class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Справочники ATI</div>
                    <p class="mt-1">{{ atiSummary(post) }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button v-if="!isClosed(post)" type="button" :class="crmBtnNeutral" @click="prepareAti">Подготовить к ATI</button>
                </div>

                <div v-if="atiPreviewForPost" class="space-y-3 border border-indigo-200 bg-indigo-50/70 p-3 text-sm text-indigo-950 dark:border-indigo-900/60 dark:bg-indigo-950/20 dark:text-indigo-100">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Подготовка к ATI</div>
                            <p class="mt-1 font-medium">
                                {{ atiPreviewForPost.ready ? 'Готов к ручной публикации на ATI' : 'Нужно заполнить обязательные поля' }}
                            </p>
                        </div>
                        <span
                            class="border px-2 py-1 text-xs font-semibold uppercase tracking-wide"
                            :class="atiPreviewForPost.ready
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'
                                : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200'"
                        >
                            {{ atiPreviewForPost.ready ? 'ready' : 'draft' }}
                        </span>
                    </div>
                    <div v-if="atiPreviewForPost.missing?.length" class="space-y-1">
                        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Обязательные поля</div>
                        <ul class="list-disc space-y-0.5 pl-5">
                            <li v-for="item in atiPreviewForPost.missing" :key="item.field">{{ item.label }}</li>
                        </ul>
                    </div>
                    <div v-if="atiPreviewForPost.warnings?.length" class="space-y-1">
                        <div class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Рекомендации</div>
                        <ul class="list-disc space-y-0.5 pl-5">
                            <li v-for="item in atiPreviewForPost.warnings" :key="item.field">{{ item.label }}</li>
                        </ul>
                    </div>
                    <details class="border border-indigo-200 bg-white/70 p-2 dark:border-indigo-900/60 dark:bg-zinc-950/60">
                        <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Payload для ATI</summary>
                        <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap break-words text-xs text-zinc-800 dark:text-zinc-100">{{ atiPayloadJson(atiPreviewForPost) }}</pre>
                    </details>
                </div>
                <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">
                    Нажмите «Подготовить к ATI», чтобы проверить готовность и скопировать payload для ручной публикации.
                </p>
            </div>
        </div>
    </article>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import InputError from '@/Components/InputError.vue';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';
import {
    crmBtnCreate,
    crmBtnDangerMuted,
    crmBtnNeutral,
    crmFieldFluid,
    crmPanel,
} from '@/support/crmUi.js';
import { dictionarySelectionLabel } from '@/support/wizardDictionaryHelpers.js';

const props = defineProps({
    post: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    contractors: { type: Array, default: () => [] },
    statusLabels: { type: Object, default: () => ({}) },
    priorityLabels: { type: Object, default: () => ({}) },
    offerSourceOptions: { type: Object, default: () => ({}) },
    currentUserId: { type: [Number, String], default: null },
    atiPreview: { type: Object, default: null },
    orderOptions: { type: Array, default: () => [] },
    leadOptions: { type: Array, default: () => [] },
    advisor: { type: Object, default: null },
    carrierPool: { type: Object, default: null },
    fullPage: { type: Boolean, default: false },
});

const linkOrderId = ref('');
const linkLeadId = ref('');

const procurementLinkedOrders = computed(() => {
    const rows = props.post.procurement_case?.linked_orders;

    return Array.isArray(rows) ? rows : [];
});

const procurementLinkedLeads = computed(() => {
    const rows = props.post.procurement_case?.linked_leads;

    return Array.isArray(rows) ? rows : [];
});

const activeTab = ref(props.fullPage ? 'offers' : 'overview');
const showOfferForm = ref(false);
const showCandidateForm = ref(false);
const rateInsights = ref(null);
const advisorPayload = ref(null);
const advisorLoading = ref(false);

const advisorState = computed(() => props.advisor ?? advisorPayload.value);
const carrierPoolState = computed(() => {
    if (props.carrierPool && Object.keys(props.carrierPool).length > 0) {
        return props.carrierPool;
    }

    return advisorState.value?.carrier_pool ?? { total: 0, entries: [], sources_summary: [] };
});

const detailTabs = [
    { key: 'overview', label: 'Обзор' },
    { key: 'offers', label: 'Офферы' },
    { key: 'pool', label: 'Пул' },
    { key: 'ati', label: 'ATI' },
];

const offerForm = useForm({
    carrier_id: null,
    source: 'internal_crm',
    carrier_rate: '',
    carrier_rate_currency: 'RUB',
    payment_form: '',
    available_date: '',
    carrier_contact: '',
    conditions: '',
    comment: '',
});

const candidateForm = useForm({
    carrier_id: null,
    carrier_name: '',
    source: 'phone',
    carrier_rate: '',
    carrier_rate_currency: 'RUB',
    carrier_contact: '',
    conditions: '',
    comment: '',
});

const sortedOffers = computed(() => {
    const offers = Array.isArray(props.post.offers) ? [...props.post.offers] : [];
    const ranked = advisorState.value?.ranked_offers;

    if (Array.isArray(ranked) && ranked.length > 0) {
        const order = new Map(ranked.map((row, index) => [Number(row.offer_id), index]));

        return offers.sort((left, right) => {
            const leftIndex = order.get(Number(left.id)) ?? Number.MAX_SAFE_INTEGER;
            const rightIndex = order.get(Number(right.id)) ?? Number.MAX_SAFE_INTEGER;

            if (leftIndex !== rightIndex) {
                return leftIndex - rightIndex;
            }

            return Number(left.carrier_rate ?? Number.MAX_SAFE_INTEGER) - Number(right.carrier_rate ?? Number.MAX_SAFE_INTEGER);
        });
    }

    return offers.sort((left, right) => {
        const leftRate = Number(left.carrier_rate ?? Number.MAX_SAFE_INTEGER);
        const rightRate = Number(right.carrier_rate ?? Number.MAX_SAFE_INTEGER);

        return leftRate - rightRate;
    });
});

const offerRankMap = computed(() => {
    const map = new Map();
    const ranked = advisorState.value?.ranked_offers ?? [];

    ranked.forEach((row) => {
        map.set(Number(row.offer_id), row);
    });

    return map;
});

const atiPreviewForPost = computed(() => (
    Number(props.atiPreview?.post_id) === Number(props.post.id) ? props.atiPreview : null
));

watch(
    () => props.post.id,
    () => {
        activeTab.value = props.fullPage ? 'offers' : 'overview';
        showOfferForm.value = false;
        showCandidateForm.value = false;
        rateInsights.value = null;
        advisorPayload.value = null;
    },
);

watch(
    () => [props.post.id, activeTab.value, props.advisor],
    async ([postId, tab]) => {
        if (props.advisor) {
            rateInsights.value = props.advisor.corridor_insights ?? null;
            advisorPayload.value = props.advisor;

            return;
        }

        if (!['offers', 'overview', 'pool'].includes(tab) || !postId) {
            return;
        }

        advisorLoading.value = true;

        try {
            const response = await axios.get(route('load-board.advisor', postId));
            advisorPayload.value = response.data?.advisor ?? null;
            rateInsights.value = response.data?.advisor?.corridor_insights ?? null;
        } catch {
            advisorPayload.value = null;
            rateInsights.value = null;
        } finally {
            advisorLoading.value = false;
        }
    },
    { immediate: true },
);

function offerRank(offerId) {
    return offerRankMap.value.get(Number(offerId)) ?? null;
}

function riskLevelLabel(level) {
    return {
        low: 'низкий',
        medium: 'средний',
        high: 'высокий',
    }[level] ?? level;
}

function riskBannerClass(level) {
    if (level === 'high') {
        return 'border-rose-200 bg-rose-50 text-rose-950 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-100';
    }

    if (level === 'medium') {
        return 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-100';
    }

    return 'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/20 dark:text-emerald-100';
}

function isClosed(post) {
    return ['closed', 'cancelled', 'no_options'].includes(post.status);
}

function openOfferForm() {
    activeTab.value = 'offers';
    showOfferForm.value = true;
    showCandidateForm.value = false;
}

function openCandidateForm() {
    activeTab.value = 'pool';
    showCandidateForm.value = true;
    showOfferForm.value = false;
}

function submitCandidate() {
    candidateForm.post(route('load-board.carrier-pool.candidates.store', props.post.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCandidateForm.value = false;
            candidateForm.reset();
            candidateForm.source = 'phone';
            candidateForm.carrier_rate_currency = 'RUB';
            advisorPayload.value = null;
        },
    });
}

function removeCandidate(candidateId) {
    router.delete(route('load-board.carrier-pool.candidates.destroy', {
        post: props.post.id,
        candidate: candidateId,
    }), {
        preserveScroll: true,
        onSuccess: () => {
            advisorPayload.value = null;
        },
    });
}

function promoteCandidateToOffer(entry) {
    activeTab.value = 'offers';
    showOfferForm.value = true;
    showCandidateForm.value = false;
    offerForm.carrier_id = entry.carrier_id ?? null;
    offerForm.source = entry.source ?? 'phone';
    offerForm.carrier_rate = entry.carrier_rate ?? '';
    offerForm.carrier_rate_currency = entry.carrier_rate_currency ?? 'RUB';
    offerForm.carrier_contact = entry.carrier_contact ?? '';
    offerForm.conditions = entry.conditions ?? '';
    offerForm.comment = entry.comment ?? '';
}

function poolStatusLabel(entry) {
    if (entry.kind === 'candidate') {
        return 'кандидат';
    }

    return offerStatusLabel(entry.status);
}

function submitOffer() {
    offerForm.post(route('load-board.offers.store', props.post.id), {
        preserveScroll: true,
        onSuccess: () => {
            showOfferForm.value = false;
            offerForm.reset();
            offerForm.source = 'internal_crm';
        },
    });
}

function takePost() {
    router.post(route('load-board.take', props.post.id), {}, { preserveScroll: true });
}

function releasePost() {
    router.post(route('load-board.release', props.post.id), {}, { preserveScroll: true });
}

function assignBuyer(buyerId) {
    router.patch(route('load-board.buyer.update', props.post.id), {
        buyer_id: buyerId === '' ? null : Number(buyerId),
    }, { preserveScroll: true });
}

function attachProcurementLink(type, rawId) {
    const id = Number(rawId);

    if (!id) {
        return;
    }

    router.patch(route('load-board.procurement-case.links.attach', props.post.id), {
        type,
        id,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            if (type === 'order') {
                linkOrderId.value = '';
            } else {
                linkLeadId.value = '';
            }
        },
    });
}

function prepareAti() {
    activeTab.value = 'ati';
    router.post(route('load-board.ati.prepare', props.post.id), {}, { preserveScroll: true });
}

function selectOffer(offer) {
    router.post(route('load-board.offers.select', { post: props.post.id, offer: offer.id }), {}, { preserveScroll: true });
}

function approveOffer(offer) {
    router.post(route('load-board.offers.approve', { post: props.post.id, offer: offer.id }), {}, { preserveScroll: true });
}

function setStatus(status) {
    router.patch(route('load-board.status.update', props.post.id), { status }, { preserveScroll: true });
}

function routeSummary(post) {
    const parts = [post.loading_location, post.unloading_location].filter(Boolean);

    return parts.length ? parts.join(' → ') : 'Маршрут не указан';
}

function dateRange(post) {
    const loading = formatDate(post.loading_date);
    const unloading = formatDate(post.unloading_date);

    if (loading === '—' && unloading === '—') {
        return '—';
    }

    return `${loading} → ${unloading}`;
}

function cargoSummary(post) {
    const parts = [
        post.cargo_name,
        post.cargo_weight ? `${post.cargo_weight} т` : null,
        post.cargo_volume ? `${post.cargo_volume} м³` : null,
        post.transport_type,
    ].filter(Boolean);

    return parts.length ? parts.join(' · ') : '—';
}

function atiSummary(post) {
    const parts = [
        post.ati_cargo_name ? `ATI: ${post.ati_cargo_name}` : null,
        post.cargo_type_label,
        post.pack_type_label,
        dictionarySelectionLabel(post.loading_type_items),
        dictionarySelectionLabel(post.truck_body_type_items),
        dictionarySelectionLabel(post.trailer_type_items),
        post.package_count ? `${post.package_count} мест` : null,
        post.hs_code ? `ТН ВЭД ${post.hs_code}` : null,
    ].filter((part) => part && part !== 'Выберите');

    return parts.join(' · ');
}

function atiPayloadJson(preview) {
    return JSON.stringify(preview?.payload ?? {}, null, 2);
}

function offerStatusLabel(status) {
    const labels = {
        proposed: 'предложен',
        selected: 'выбран',
        approved: 'принят',
        rejected: 'отклонён',
    };

    return labels[status] ?? status;
}

function offerStatusClass(status) {
    const classes = {
        selected: 'text-violet-700 dark:text-violet-300',
        approved: 'text-emerald-700 dark:text-emerald-300',
        rejected: 'text-rose-600 dark:text-rose-300',
        proposed: 'text-zinc-500',
    };

    return classes[status] ?? 'text-zinc-500';
}

function money(value, currency = 'RUB') {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const numeric = Number(value);
    if (Number.isNaN(numeric)) {
        return `${value} ${currency}`;
    }

    return `${numeric.toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ${currency || 'RUB'}`;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const parts = String(value).split('-');
    if (parts.length !== 3) {
        return value;
    }

    return `${parts[2]}.${parts[1]}.${parts[0]}`;
}

function statusClass(status) {
    const map = {
        new: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200',
        in_work: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
        has_offers: 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-200',
        seller_review: 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-200',
        closed: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200',
        no_options: 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200',
        cancelled: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200',
    };

    return map[status] ?? map.new;
}

function priorityClass(priority) {
    const map = {
        low: 'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300',
        normal: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200',
        high: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
        urgent: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200',
    };

    return map[priority] ?? map.normal;
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            :title="pageTitle"
            :lead="pageDescription"
        />

        <nav v-if="isFinanceModule" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <Link
                    :href="route('finance.index')"
                    class="text-zinc-600 underline decoration-zinc-300 underline-offset-2 transition hover:text-zinc-900 dark:text-zinc-400 dark:decoration-zinc-600 dark:hover:text-zinc-100"
                >
                    ← К финансам
                </Link>
                <span class="text-zinc-300 dark:text-zinc-600" aria-hidden="true">·</span>
                <Link
                    :href="route('settings.motivation.salary')"
                    class="text-zinc-600 underline decoration-zinc-300 underline-offset-2 transition hover:text-zinc-900 dark:text-zinc-400 dark:decoration-zinc-600 dark:hover:text-zinc-100"
                >
                    Персональные условия и коэффициенты
                </Link>
        </nav>
        <nav v-else class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <Link
                    :href="route('settings.motivation.index')"
                    class="text-zinc-600 underline decoration-zinc-300 underline-offset-2 transition hover:text-zinc-900 dark:text-zinc-400 dark:decoration-zinc-600 dark:hover:text-zinc-100"
                >
                    ← Мотивация
                </Link>
                <span class="text-zinc-300 dark:text-zinc-600" aria-hidden="true">·</span>
                <Link
                    :href="route('finance.salary.index')"
                    class="text-zinc-600 underline decoration-zinc-300 underline-offset-2 transition hover:text-zinc-900 dark:text-zinc-400 dark:decoration-zinc-600 dark:hover:text-zinc-100"
                >
                    Зарплата: периоды и выплаты
                </Link>
        </nav>

        <section v-if="isFinanceModule" :class="`${crmPanel} p-5`">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold">Зарплатные периоды</h2>
                        <span
                            v-if="selectedPeriod"
                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                            :class="periodStatusClass(selectedPeriod.status)"
                        >
                            {{ periodStatusLabel(selectedPeriod.status) }}
                        </span>
                    </div>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Начисление = delta / 2; «к выплате» — по фактическим оплатам заказчиков в рамках периода.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model="selectedSalaryUserId"
                        :class="crmFieldFluid"
                        @change="selectSalaryPeriod"
                    >
                        <option :value="null">Все сотрудники</option>
                        <option v-for="employee in employees" :key="`salary-user-${employee.id}`" :value="employee.id">
                            {{ employee.name }}
                        </option>
                    </select>
                    <select
                        v-model="selectedSalaryPeriodId"
                        :class="crmFieldFluid"
                        @change="selectSalaryPeriod"
                    >
                        <option :value="null">Выберите период</option>
                        <option v-for="period in salaryPeriods" :key="period.id" :value="period.id">
                            {{ salaryPeriodLabel(period) }}
                        </option>
                    </select>
                </div>
            </div>

            <p
                v-if="salaryPeriodsPrunedCount"
                class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200"
            >
                Удалено лишних черновиков периодов: {{ salaryPeriodsPrunedCount }}.
            </p>

            <p
                v-if="salaryPeriods.length === 0"
                class="mb-4 rounded-lg border border-dashed border-zinc-300 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-600 dark:bg-zinc-950/40 dark:text-zinc-400"
            >
                Пока нет периодов. Укажите даты и нажмите «Создать и рассчитать период» — начисления подтянутся из грида по заказам.
            </p>

            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-[repeat(4,minmax(0,1fr))]">
                <input v-model="createPeriodForm.period_start" type="date" :class="crmFieldFluid">
                <input v-model="createPeriodForm.period_end" type="date" :class="crmFieldFluid">
                <select v-model="createPeriodForm.period_type" :class="crmFieldFluid">
                    <option value="h1">1-15 (H1)</option>
                    <option value="h2">16-last (H2)</option>
                </select>
                <button
                    type="button"
                    :class="crmBtnPrimary"
                    class="disabled:opacity-60"
                    :disabled="createPeriodForm.processing"
                    @click="storeSalaryPeriod"
                >
                    Создать и рассчитать период
                </button>
            </div>

            <div v-if="selectedSalaryPeriodId !== null" class="mb-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    :class="`${crmBtnNeutral} px-3 py-1.5 text-xs disabled:cursor-not-allowed disabled:opacity-50`"
                    :disabled="!selectedSalaryPeriodId || selectedPeriod?.status !== 'draft'"
                    @click="recalculateSalaryPeriod"
                >
                    Пересчитать
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                    :disabled="!selectedSalaryPeriodId || selectedPeriod?.status !== 'draft'"
                    @click="approveSalaryPeriod"
                >
                    Утвердить
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs text-amber-700 hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-900 dark:text-amber-300 dark:hover:bg-amber-950/30"
                    :disabled="!selectedSalaryPeriodId || selectedPeriod?.status !== 'approved'"
                    @click="closeSalaryPeriod"
                >
                    Закрыть
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-900 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-100 dark:hover:bg-amber-950"
                    :disabled="selectedSalaryPeriodId !== null && selectedPeriod?.status === 'closed'"
                    @click="openAdvancePayoutModal"
                >
                    Выдача аванса
                </button>
                <button
                    v-if="selectedPeriod?.can_delete"
                    type="button"
                    class="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30"
                    @click="destroySalaryPeriod"
                >
                    Удалить период
                </button>
            </div>

            <div class="overflow-auto border border-zinc-200 dark:border-zinc-800">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-zinc-50 dark:bg-zinc-950/60">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Сотрудник</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Начислено</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">К выплате</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Выплачено</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Авансы</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Аванс к зачёту</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Остаток</th>
                            <th class="px-3 py-2 text-right font-medium text-zinc-600 dark:text-zinc-300">Выплата</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        <template v-for="row in salaryPeriodUsers" :key="row.user_id">
                            <tr
                                class="cursor-pointer bg-white transition hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-800/70"
                                @click="toggleEmployeeDetails(row.user_id)"
                            >
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-5 w-5 items-center justify-center border border-zinc-200 text-[10px] dark:border-zinc-700">
                                            {{ isEmployeeExpanded(row.user_id) ? '−' : '+' }}
                                        </span>
                                        <span>{{ row.user_name }}</span>
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">
                                            {{ employeeOrderRows(row.user_id).length }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2">{{ money(row.accrued_total) }}</td>
                                <td class="px-3 py-2">{{ money(row.payable_total) }}</td>
                                <td class="px-3 py-2">{{ money(row.paid_total) }}</td>
                                <td class="px-3 py-2">{{ money(row.advance_total) }}</td>
                                <td class="px-3 py-2">{{ money(row.advance_balance) }}</td>
                                <td class="px-3 py-2">{{ money(row.payable_left) }}</td>
                                <td class="px-3 py-2" @click.stop>
                                    <div class="flex justify-end gap-2">
                                        <input
                                            v-model.number="payoutDrafts[row.user_id]"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="w-28 border border-zinc-300 px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950"
                                            :disabled="!selectedSalaryPeriodId || selectedPeriod?.status === 'closed' || selectedPeriod?.status === 'draft'"
                                        >
                                        <button
                                            type="button"
                                            class="rounded border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                            :disabled="!selectedSalaryPeriodId || selectedPeriod?.status === 'closed' || selectedPeriod?.status === 'draft'"
                                            @click="storeSalaryPayout(row.user_id, 'salary')"
                                        >
                                            Провести
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded border border-amber-200 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-900 dark:text-amber-300 dark:hover:bg-amber-950/30"
                                            :disabled="!selectedSalaryPeriodId || selectedPeriod?.status === 'closed'"
                                            @click="storeSalaryPayout(row.user_id, 'advance')"
                                        >
                                            Аванс
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="isEmployeeExpanded(row.user_id)">
                                <td colspan="8" class="bg-zinc-50 px-3 py-3 dark:bg-zinc-950/50">
                                    <div v-if="employeeOrderRows(row.user_id).length > 0" class="overflow-auto border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                        <table class="min-w-full divide-y divide-zinc-200 text-xs dark:divide-zinc-800">
                                            <thead class="bg-zinc-50 dark:bg-zinc-950/60">
                                                <tr>
                                                    <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Заказ</th>
                                                    <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Начислено</th>
                                                    <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">К выплате в периоде</th>
                                                    <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Выплачено в периоде</th>
                                                    <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Выплачено всего</th>
                                                    <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Остаток всего</th>
                                                    <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Оплата заказчика</th>
                                                    <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-300">Расчёт</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                                <tr v-for="detailRow in employeeOrderRows(row.user_id)" :key="detailRow.accrual_id">
                                                    <td class="px-3 py-2">{{ detailRow.order_number || `#${detailRow.order_id}` }}</td>
                                                    <td class="px-3 py-2">{{ money(detailRow.accrued_salary) }}</td>
                                                    <td class="px-3 py-2">{{ money(detailRow.payable_in_period) }}</td>
                                                    <td class="px-3 py-2">{{ money(detailRow.paid_in_period) }}</td>
                                                    <td class="px-3 py-2">{{ money(detailRow.paid_total) }}</td>
                                                    <td class="px-3 py-2">{{ money(detailRow.unpaid_total) }}</td>
                                                    <td class="px-3 py-2">
                                                        <span :class="detailRow.customer_fully_paid ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'">
                                                            <template v-if="detailRow.customer_fully_paid">
                                                                {{ money(detailRow.customer_paid_amount) }} / {{ money(detailRow.customer_rate) }}
                                                            </template>
                                                            <template v-else-if="Number(detailRow.customer_paid_amount) > 0">
                                                                Оплачено {{ formatPercent(detailRow.customer_payment_percent) }} ({{ money(detailRow.customer_paid_amount) }} / {{ money(detailRow.customer_rate) }})
                                                            </template>
                                                            <template v-else>
                                                                {{ money(detailRow.customer_paid_amount) }} / {{ money(detailRow.customer_rate) }}
                                                            </template>
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2">{{ calculationModeLabel(detailRow.calculation_mode) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div v-else class="border border-dashed border-zinc-300 bg-white px-4 py-3 text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                                        По сотруднику нет заказных начислений в выбранном периоде.
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="salaryPeriodUsers.length === 0">
                            <td colspan="8" class="px-3 py-4 text-center text-xs text-zinc-500 dark:text-zinc-400">
                                <span v-if="!selectedSalaryPeriodId">Выберите период или создайте новый.</span>
                                <span v-else>Нет строк начислений за этот период — проверьте заказы в гриде и нажмите «Пересчитать».</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Teleport to="body">
                <div
                    v-if="advancePayoutModalOpen"
                    class="fixed inset-0 z-[80] flex items-center justify-center bg-zinc-950/50 p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="advance-payout-modal-title"
                    @click.self="closeAdvancePayoutModal"
                >
                    <div
                        class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <h3 id="advance-payout-modal-title" class="text-base font-semibold text-zinc-900 dark:text-zinc-50">
                            Выдача аванса
                        </h3>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <template v-if="selectedSalaryPeriodId">
                                Сумма войдёт в «Выплачено» и «Авансы» по выбранному периоду и зачтётся при последующих выплатах зарплаты.
                                <span v-if="selectedPeriod" class="block pt-1">
                                    Период: {{ salaryPeriodLabel(selectedPeriod) }}.
                                </span>
                            </template>
                            <template v-else>
                                Аванс без привязки к периоду: запись появится в учёте и будет автоматически зачтена при пересчёте подходящих зарплатных периодов (по дате выдачи и начислениям по заказам).
                            </template>
                        </p>
                        <div class="mt-4 space-y-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-300">Сотрудник</label>
                                <select
                                    v-model="advancePayoutForm.user_id"
                                    class="w-full border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                                >
                                    <option value="">Выберите…</option>
                                    <option v-for="employee in employees" :key="`adv-emp-${employee.id}`" :value="String(employee.id)">
                                        {{ employee.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-300">Сумма</label>
                                <input
                                    v-model="advancePayoutForm.amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="w-full border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                                    placeholder="0,00"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-300">Дата выдачи</label>
                                <input
                                    v-model="advancePayoutForm.payout_date"
                                    type="date"
                                    class="w-full border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-300">Комментарий (необязательно)</label>
                                <input
                                    v-model="advancePayoutForm.comment"
                                    type="text"
                                    maxlength="500"
                                    class="w-full border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                                    placeholder="Например: аванс на карту"
                                >
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-600 dark:hover:bg-zinc-800"
                                :disabled="advancePayoutSubmitting"
                                @click="closeAdvancePayoutModal"
                            >
                                Отмена
                            </button>
                            <button
                                type="button"
                                class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-60 dark:bg-amber-700 dark:hover:bg-amber-600"
                                :disabled="advancePayoutSubmitting"
                                @click="submitAdvancePayoutModal"
                            >
                                Провести аванс
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

        </section>

        <div v-if="!isFinanceModule" class="grid min-h-0 grid-cols-1 gap-3 xl:grid-cols-[minmax(360px,0.42fr)_minmax(0,0.58fr)]">
            <section :class="`${crmPanel} p-5`">
                <div class="mb-4 space-y-1">
                    <h2 :class="crmSectionTitle">Новое условие</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Добавьте персональные параметры для сотрудника.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <label class="space-y-1">
                        <span class="text-sm font-medium">Сотрудник</span>
                        <select
                            v-model="createSalaryForm.manager_id"
                            :class="crmFieldFluid"
                        >
                            <option value="">Выберите сотрудника</option>
                            <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                                {{ employee.name }}<span v-if="employee.role_name"> · {{ employee.role_name }}</span>
                            </option>
                        </select>
                    </label>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <label class="space-y-1">
                            <span class="text-sm font-medium">Оклад</span>
                            <input
                                v-model.number="createSalaryForm.base_salary"
                                type="number"
                                min="0"
                                :class="crmFieldFluid"
                            >
                        </label>

                        <label class="space-y-1">
                            <span class="text-sm font-medium">Бонус, %</span>
                            <input
                                v-model.number="createSalaryForm.bonus_percent"
                                type="number"
                                min="0"
                                max="100"
                                :class="crmFieldFluid"
                            >
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <label class="space-y-1">
                            <span class="text-sm font-medium">Действует с</span>
                            <input
                                v-model="createSalaryForm.effective_from"
                                type="date"
                                :class="crmFieldFluid"
                            >
                        </label>

                        <label class="space-y-1">
                            <span class="text-sm font-medium">Действует до</span>
                            <input
                                v-model="createSalaryForm.effective_to"
                                type="date"
                                :class="crmFieldFluid"
                            >
                        </label>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="createSalaryForm.is_active" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-zinc-400">
                        <span>Запись активна</span>
                    </label>

                    <div class="flex justify-end">
                        <button
                            type="button"
                            :class="crmBtnPrimary"
                            class="disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="createSalaryForm.processing"
                            @click="storeSalaryCoefficient"
                        >
                            Добавить условие
                        </button>
                    </div>
                </div>
            </section>

            <section :class="`${crmPanel} min-h-0 overflow-auto`">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-zinc-50 dark:bg-zinc-950/60">
                        <tr>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Сотрудник</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Оклад</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Бонус</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">С</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">По</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Статус</th>
                            <th class="px-3 py-3 text-right font-medium text-zinc-600 dark:text-zinc-300">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        <tr v-for="coefficient in salaryDrafts" :key="coefficient.id">
                            <td class="px-3 py-2">
                                <select
                                    v-model="coefficient.manager_id"
                                    class="w-full min-w-[180px] border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                >
                                    <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                                        {{ employee.name }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input
                                    v-model.number="coefficient.base_salary"
                                    type="number"
                                    min="0"
                                    class="w-28 border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model.number="coefficient.bonus_percent"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="w-20 border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                    >
                                    <span class="text-zinc-500 dark:text-zinc-400">%</span>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <input
                                    v-model="coefficient.effective_from"
                                    type="date"
                                    class="border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <input
                                    v-model="coefficient.effective_to"
                                    type="date"
                                    class="border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <label class="inline-flex items-center gap-2 text-xs">
                                    <input v-model="coefficient.is_active" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-zinc-400">
                                    <span>{{ coefficient.is_active ? 'Активно' : 'Выключено' }}</span>
                                </label>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        :class="crmBtnCreate"
                                        class="px-3 py-1.5 text-xs"
                                        @click="saveSalaryCoefficient(coefficient)"
                                    >
                                        Сохранить
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-600 transition-colors hover:bg-red-50 dark:border-red-900/40 dark:text-red-400 dark:hover:bg-red-950/30"
                                        @click="deleteSalaryCoefficient(coefficient.id)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="salaryDrafts.length === 0">
                            <td colspan="7" class="px-3 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                Персональные коэффициенты ещё не заданы — добавьте запись слева.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnCreate, crmBtnNeutral, crmBtnPrimary, crmFieldFluid, crmPanel, crmSectionTitle } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => {
        const moduleScope = page.props.salary_module === 'finance' ? 'finance' : 'settings';

        return h(
            CrmLayout,
            moduleScope === 'finance'
                ? { activeKey: 'finance', activeSubKey: 'finance-salary' }
                : { activeKey: 'settings', activeSubKey: 'motivation', activeLeafKey: 'salary-settings' },
            () => page
        );
    },
});

const props = defineProps({
    employees: {
        type: Array,
        default: () => [],
    },
    salaryCoefficients: {
        type: Array,
        default: () => [],
    },
    salaryPeriods: {
        type: Array,
        default: () => [],
    },
    activeSalaryPeriodId: {
        type: Number,
        default: null,
    },
    activeSalaryUserId: {
        type: Number,
        default: null,
    },
    salaryPeriodUsers: {
        type: Array,
        default: () => [],
    },
    salaryPeriodOrderRows: {
        type: Array,
        default: () => [],
    },
    salary_module: {
        type: String,
        default: 'settings',
    },
    salaryPeriodsPrunedCount: {
        type: Number,
        default: null,
    },
});

const selectedSalaryPeriodId = ref(props.activeSalaryPeriodId ?? null);
const selectedSalaryUserId = ref(props.activeSalaryUserId ?? null);
const expandedEmployeeIds = ref([]);
const payoutDrafts = reactive({});

const advancePayoutModalOpen = ref(false);
const advancePayoutSubmitting = ref(false);
const advancePayoutForm = reactive({
    user_id: '',
    amount: '',
    payout_date: new Date().toISOString().slice(0, 10),
    comment: '',
});

const createSalaryForm = useForm({
    manager_id: '',
    base_salary: 0,
    bonus_percent: 0,
    effective_from: '',
    effective_to: '',
    is_active: true,
});

function todayIsoDate() {
    return new Date().toISOString().slice(0, 10);
}

function canonicalHalfMonthDates(periodType, periodStartIso) {
    if (!periodStartIso) {
        return null;
    }

    const match = String(periodStartIso).match(/^(\d{4})-(\d{2})/);
    if (!match) {
        return null;
    }

    const year = Number(match[1]);
    const month = Number(match[2]);
    const monthPad = String(month).padStart(2, '0');

    if (periodType === 'h1') {
        return {
            period_start: `${year}-${monthPad}-01`,
            period_end: `${year}-${monthPad}-15`,
        };
    }

    const lastDay = new Date(year, month, 0).getDate();

    return {
        period_start: `${year}-${monthPad}-16`,
        period_end: `${year}-${monthPad}-${String(lastDay).padStart(2, '0')}`,
    };
}

const initialPeriodDates = canonicalHalfMonthDates('h1', todayIsoDate()) ?? {
    period_start: '',
    period_end: '',
};

const createPeriodForm = useForm({
    period_start: initialPeriodDates.period_start,
    period_end: initialPeriodDates.period_end,
    period_type: 'h1',
    notes: '',
});

watch(
    () => [createPeriodForm.period_type, createPeriodForm.period_start],
    () => {
        const normalized = canonicalHalfMonthDates(createPeriodForm.period_type, createPeriodForm.period_start);
        if (!normalized) {
            return;
        }

        createPeriodForm.period_start = normalized.period_start;
        createPeriodForm.period_end = normalized.period_end;
    },
);

const salaryDrafts = reactive(props.salaryCoefficients.map((row) => ({
    ...row,
})));
const isFinanceModule = props.salary_module === 'finance';
const pageTitle = isFinanceModule ? 'Зарплата' : 'Персональные условия';
const pageDescription = isFinanceModule
    ? 'Периоды начислений по гриду, суммы к выплате по оплатам заказов и учёт фактических выплат.'
    : 'Оклад, бонус и срок действия персональных коэффициентов. Периоды и выплаты ведутся в разделе «Финансы → Зарплата».';

const selectedPeriod = computed(() => {
    if (selectedSalaryPeriodId.value === null) {
        return null;
    }

    return props.salaryPeriods.find((p) => p.id === selectedSalaryPeriodId.value) ?? null;
});

const salaryPeriodOrderRowsByUser = computed(() => {
    return props.salaryPeriodOrderRows.reduce((groups, row) => {
        const key = Number(row.user_id);
        groups[key] = groups[key] || [];
        groups[key].push(row);

        return groups;
    }, {});
});

function formatSalaryPeriodDate(value) {
    if (!value) {
        return '';
    }

    const parts = String(value).split('-');
    if (parts.length !== 3) {
        return String(value);
    }

    const [year, month, day] = parts;

    return `${day.padStart(2, '0')}.${month.padStart(2, '0')}.${year}`;
}

function salaryPeriodLabel(period) {
    if (!period) {
        return '';
    }

    return `${formatSalaryPeriodDate(period.period_start)} — ${formatSalaryPeriodDate(period.period_end)} (${period.period_type.toUpperCase()})`;
}

function periodStatusLabel(status) {
    const labels = {
        draft: 'Черновик',
        approved: 'Утверждён',
        closed: 'Закрыт',
    };

    return labels[status] ?? status;
}

function periodStatusClass(status) {
    const classes = {
        draft: 'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
        approved: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300',
        closed: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200',
    };

    return classes[status] ?? 'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300';
}

const routes = {
    salaryIndex: props.salary_module === 'finance' ? 'finance.salary.index' : 'settings.motivation.salary',
    periodsStore: props.salary_module === 'finance' ? 'finance.salary.periods.store' : 'settings.motivation.salary.periods.store',
    periodsRecalculate: props.salary_module === 'finance' ? 'finance.salary.periods.recalculate' : 'settings.motivation.salary.periods.recalculate',
    periodsApprove: props.salary_module === 'finance' ? 'finance.salary.periods.approve' : 'settings.motivation.salary.periods.approve',
    periodsClose: props.salary_module === 'finance' ? 'finance.salary.periods.close' : 'settings.motivation.salary.periods.close',
    periodsDestroy: props.salary_module === 'finance' ? 'finance.salary.periods.destroy' : '',
    periodsPayoutStore: props.salary_module === 'finance' ? 'finance.salary.periods.payouts.store' : 'settings.motivation.salary.periods.payouts.store',
    advancePayoutUnscopedStore: props.salary_module === 'finance' ? 'finance.salary.advance-payouts.store' : '',
    coefficientsStore: props.salary_module === 'finance' ? 'finance.salary.coefficients.store' : 'settings.motivation.salary.store',
    coefficientsUpdate: props.salary_module === 'finance' ? 'finance.salary.coefficients.update' : 'settings.motivation.salary.update',
    coefficientsDelete: props.salary_module === 'finance' ? 'finance.salary.coefficients.destroy' : 'settings.motivation.salary.destroy',
};

function storeSalaryCoefficient() {
    createSalaryForm.post(route(routes.coefficientsStore), {
        preserveScroll: true,
        onSuccess: () => {
            createSalaryForm.reset();
            createSalaryForm.is_active = true;
        },
    });
}

function saveSalaryCoefficient(coefficient) {
    router.patch(route(routes.coefficientsUpdate, coefficient.id), {
        manager_id: coefficient.manager_id,
        base_salary: coefficient.base_salary,
        bonus_percent: coefficient.bonus_percent,
        effective_from: coefficient.effective_from,
        effective_to: coefficient.effective_to,
        is_active: coefficient.is_active,
    }, {
        preserveScroll: true,
    });
}

function deleteSalaryCoefficient(id) {
    if (!window.confirm('Удалить это условие мотивации?')) {
        return;
    }

    router.delete(route(routes.coefficientsDelete, id), {
        preserveScroll: true,
    });
}

function selectSalaryPeriod() {
    router.get(route(routes.salaryIndex), {
        salary_period_id: selectedSalaryPeriodId.value,
        salary_user_id: selectedSalaryUserId.value,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function storeSalaryPeriod() {
    createPeriodForm.post(route(routes.periodsStore), {
        preserveScroll: true,
    });
}

function recalculateSalaryPeriod() {
    if (!selectedSalaryPeriodId.value) return;
    router.post(route(routes.periodsRecalculate, selectedSalaryPeriodId.value), {}, { preserveScroll: true });
}

function approveSalaryPeriod() {
    if (!selectedSalaryPeriodId.value) return;
    router.post(route(routes.periodsApprove, selectedSalaryPeriodId.value), {}, { preserveScroll: true });
}

function closeSalaryPeriod() {
    if (!selectedSalaryPeriodId.value) return;
    router.post(route(routes.periodsClose, selectedSalaryPeriodId.value), {}, { preserveScroll: true });
}

function destroySalaryPeriod() {
    if (!selectedSalaryPeriodId.value || !routes.periodsDestroy) {
        return;
    }

    if (!window.confirm('Удалить выбранный зарплатный период? Действие необратимо.')) {
        return;
    }

    router.delete(route(routes.periodsDestroy, selectedSalaryPeriodId.value), {
        preserveScroll: true,
        onSuccess: () => {
            selectedSalaryPeriodId.value = null;
        },
    });
}

function isEmployeeExpanded(userId) {
    return expandedEmployeeIds.value.includes(Number(userId));
}

function toggleEmployeeDetails(userId) {
    const normalizedUserId = Number(userId);

    if (isEmployeeExpanded(normalizedUserId)) {
        expandedEmployeeIds.value = expandedEmployeeIds.value.filter((id) => id !== normalizedUserId);
        return;
    }

    expandedEmployeeIds.value = [...expandedEmployeeIds.value, normalizedUserId];
}

function employeeOrderRows(userId) {
    return salaryPeriodOrderRowsByUser.value[Number(userId)] || [];
}

function storeSalaryPayout(userId, type = 'salary') {
    if (!selectedSalaryPeriodId.value) return;
    const amount = Number(payoutDrafts[userId] || 0);
    if (!Number.isFinite(amount) || amount <= 0) {
        window.alert('Укажите сумму выплаты больше 0.');
        return;
    }

    router.post(route(routes.periodsPayoutStore, selectedSalaryPeriodId.value), {
        user_id: userId,
        amount,
        payout_date: new Date().toISOString().slice(0, 10),
        type,
        comment: null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            payoutDrafts[userId] = null;
        },
    });
}

function openAdvancePayoutModal() {
    if (selectedSalaryPeriodId.value !== null && selectedPeriod.value?.status === 'closed') {
        return;
    }

    advancePayoutForm.user_id = selectedSalaryUserId.value != null ? String(selectedSalaryUserId.value) : '';
    advancePayoutForm.amount = '';
    advancePayoutForm.payout_date = new Date().toISOString().slice(0, 10);
    advancePayoutForm.comment = '';
    advancePayoutModalOpen.value = true;
}

function closeAdvancePayoutModal() {
    if (advancePayoutSubmitting.value) {
        return;
    }
    advancePayoutModalOpen.value = false;
}

function submitAdvancePayoutModal() {
    const userId = Number(advancePayoutForm.user_id);
    const amount = Number(advancePayoutForm.amount);

    if (!userId || !Number.isFinite(amount) || amount <= 0) {
        window.alert('Выберите сотрудника и укажите сумму больше 0.');
        return;
    }

    advancePayoutSubmitting.value = true;

    const payload = {
        user_id: userId,
        amount,
        payout_date: advancePayoutForm.payout_date,
        type: 'advance',
        comment: advancePayoutForm.comment.trim() !== '' ? advancePayoutForm.comment.trim() : null,
    };

    const finish = {
        preserveScroll: true,
        onFinish: () => {
            advancePayoutSubmitting.value = false;
        },
        onSuccess: () => {
            advancePayoutModalOpen.value = false;
        },
    };

    if (selectedSalaryPeriodId.value) {
        router.post(route(routes.periodsPayoutStore, selectedSalaryPeriodId.value), payload, finish);
    } else if (routes.advancePayoutUnscopedStore) {
        router.post(route(routes.advancePayoutUnscopedStore), {
            user_id: userId,
            amount,
            payout_date: advancePayoutForm.payout_date,
            comment: payload.comment,
        }, finish);
    } else {
        advancePayoutSubmitting.value = false;
        window.alert('Выдача аванса без периода доступна только в разделе «Финансы → Зарплата».');
    }
}

function money(value) {
    return Number(value || 0).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatPercent(value) {
    return Number(value || 0).toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 1 });
}

function calculationModeLabel(value) {
    const labels = {
        kpi: 'KPI',
        base_plus_margin_percent: 'Оклад + %',
    };

    return labels[value] ?? value ?? 'KPI';
}
</script>

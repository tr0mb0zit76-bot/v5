<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            :lead="`Всего: ${users.length} · Активных: ${activeUsers.length} · Неактивных: ${inactiveUsers.length}`"
            title="Пользователи"
            :title-class="crmPageTitleSm"
        >
            <template #actions>
                <button
                    type="button"
                    :class="crmBtnCreate"
                    @click="openCreateModal"
                >
                    <Plus class="h-4 w-4" />
                    Добавить пользователя
                </button>
            </template>
        </CrmPageHeader>

        <div class="flex items-center gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="px-3 py-1.5 text-sm transition-colors"
                :class="crmTabButtonClasses(activeTab === tab.key)"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <div :class="crmGridPanel">
            <div class="h-full overflow-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-zinc-100 dark:bg-zinc-800">
                        <tr class="text-left text-zinc-600 dark:text-zinc-200">
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Имя</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Email</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Телефон</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Роль</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Подразделение</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Подпись</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Статус</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Создан</th>
                            <th class="border-b border-zinc-200 px-3 py-3 font-medium dark:border-zinc-700">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in displayedUsers"
                            :key="user.id"
                            class="border-b border-zinc-100 dark:border-zinc-800"
                        >
                            <td class="px-3 py-3 font-medium">{{ user.name }}</td>
                            <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ user.email }}</td>
                            <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ user.phone || '—' }}</td>
                            <td class="px-3 py-3">
                                <span class="inline-flex max-w-xs flex-wrap gap-1">
                                    <span
                                        v-for="role in (user.roles?.length ? user.roles : (user.role ? [user.role] : []))"
                                        :key="`user-${user.id}-role-${role.id}`"
                                        class="inline-flex rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium dark:bg-zinc-800"
                                    >
                                        {{ role.display_name || role.name }}
                                    </span>
                                    <span v-if="!user.roles?.length && !user.role" class="text-xs text-zinc-500">Без роли</span>
                                </span>
                            </td>
                            <td class="px-3 py-3 text-xs text-zinc-600 dark:text-zinc-300">
                                {{ user.departments_label || '—' }}
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="user.has_signing_authority
                                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                        : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'"
                                >
                                    {{ user.signing_own_companies_label || (user.has_signing_authority ? 'Есть' : 'Нет') }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="user.is_active
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'"
                                >
                                    {{ user.is_active ? 'Активен' : 'Неактивен' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ formatDate(user.created_at) }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-zinc-200 p-2 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                        @click="openEditModal(user)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="user.id !== currentUserId"
                                        type="button"
                                        class="rounded-lg border border-zinc-200 p-2 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                        @click="toggleActive(user)"
                                    >
                                        <Power class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="user.id !== currentUserId"
                                        type="button"
                                        class="rounded-lg border border-rose-200 p-2 text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                        @click="removeUser(user)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="displayedUsers.length === 0">
                            <td colspan="8" class="px-3 py-12 text-center text-zinc-500">
                                Пользователи в этой вкладке не найдены.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showModal" max-width="5xl" @close="closeModal">
            <section :class="crmModalFormShell">
                <CrmModalHeader
                    eyebrow="Пользователи"
                    :title="editingUser === null ? 'Новый пользователь' : 'Редактирование пользователя'"
                    @close="closeModal"
                />

                <form :class="`${crmModalFormBody} space-y-4 px-6 py-5`" @submit.prevent="submit">
                    <div :class="crmModalFieldsWrap">
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide flex-wrap`">
                            <label :class="crmModalFieldLabel">Имя</label>
                            <input
                                v-model="form.name"
                                type="text"
                                :class="crmFieldFluid"
                            />
                            <div v-if="form.errors.name" class="w-full text-sm text-rose-600">{{ form.errors.name }}</div>
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide flex-wrap`">
                            <label :class="crmModalFieldLabel">Email</label>
                            <input
                                v-model="form.email"
                                type="email"
                                :class="crmFieldFluid"
                            />
                            <div v-if="form.errors.email" class="w-full text-sm text-rose-600">{{ form.errors.email }}</div>
                        </div>
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide flex-wrap`">
                            <label :class="crmModalFieldLabel">Телефон</label>
                            <input
                                v-model="form.phone"
                                type="tel"
                                autocomplete="tel"
                                :class="crmFieldFluid"
                            />
                            <div v-if="form.errors.phone" class="w-full text-sm text-rose-600">{{ form.errors.phone }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label :class="crmModalFieldLabel">Роли</label>
                            <div class="mt-1 max-h-44 space-y-2 overflow-y-auto rounded-xl border border-zinc-200 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-950">
                                <label
                                    v-for="role in roles"
                                    :key="`user-role-${role.id}`"
                                    class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 text-sm text-zinc-800 transition hover:bg-zinc-50 dark:text-zinc-100 dark:hover:bg-zinc-900"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-600 dark:bg-zinc-950"
                                        :checked="isRoleSelected(role.id)"
                                        @change="toggleRole(role.id)"
                                    >
                                    <span>{{ role.display_name || role.name }}</span>
                                </label>
                            </div>
                            <div v-if="form.errors.role_ids" class="mt-1 text-sm text-rose-600">{{ form.errors.role_ids }}</div>
                            <div v-if="form.errors.role_id" class="mt-1 text-sm text-rose-600">{{ form.errors.role_id }}</div>
                        </div>

                        <label class="flex items-center gap-3 pt-2 text-sm text-zinc-700 dark:text-zinc-200 md:col-span-2">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                :class="crmCheckbox"
                            />
                            Активный пользователь
                        </label>
                    </div>

                    <div v-if="departments.length > 0" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label :class="crmModalFieldLabel">Подразделение</label>
                            <select
                                v-model="form.primary_department_id"
                                :class="`mt-1 ${crmFieldFluid}`"
                            >
                                <option :value="null">Не выбрано</option>
                                <option
                                    v-for="department in departments"
                                    :key="`dept-primary-${department.id}`"
                                    :value="department.id"
                                >
                                    {{ department.name }}
                                </option>
                            </select>
                            <div v-if="form.errors.primary_department_id" class="mt-1 text-sm text-rose-600">
                                {{ form.errors.primary_department_id }}
                            </div>
                        </div>

                        <div v-if="showApprovalDepartmentsField">
                            <label :class="crmModalFieldLabel">Согласования</label>
                            <div class="mt-1 max-h-44 space-y-2 overflow-y-auto rounded-xl border border-zinc-200 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-950">
                                <label
                                    v-for="department in departments"
                                    :key="`dept-approval-${department.id}`"
                                    class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 text-sm text-zinc-800 transition hover:bg-zinc-50 dark:text-zinc-100 dark:hover:bg-zinc-900"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-600 dark:bg-zinc-950"
                                        :checked="isApprovalDepartmentSelected(department.id)"
                                        @change="toggleApprovalDepartment(department.id)"
                                    >
                                    <span>{{ department.name }}</span>
                                </label>
                            </div>
                            <div v-if="form.errors.approval_department_ids" class="mt-1 text-sm text-rose-600">
                                {{ form.errors.approval_department_ids }}
                            </div>
                        </div>
                    </div>

                    <label class="flex items-start gap-3 rounded-xl border border-zinc-200 px-3 py-3 text-sm dark:border-zinc-800">
                        <input
                            v-model="form.has_signing_authority"
                            type="checkbox"
                            class="mt-1 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-600 dark:bg-zinc-950"
                        />
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-50">Имеет право подписи (согласование заявок)</div>
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                Не зависит от роли: при включении пользователь видит в заказе кнопки «Подписать» / «Отказать» для документов по шаблону, отправленных на согласование.
                            </div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border border-zinc-200 px-3 py-3 text-sm dark:border-zinc-800">
                        <input
                            v-model="form.belongs_to_management"
                            type="checkbox"
                            class="mt-1 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-600 dark:bg-zinc-950"
                        />
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-50">Относится к управлению</div>
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                Доступ к модулю «Бюджетирование» и управленческому планированию. Не зависит от роли.
                            </div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border border-zinc-200 px-3 py-3 text-sm dark:border-zinc-800">
                        <input
                            v-model="form.can_management_accounting"
                            type="checkbox"
                            class="mt-1 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-600 dark:bg-zinc-950"
                        />
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-50">Управленческий учёт</div>
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                Доступ к модулю «Управленческий учёт» в разделе «Финансы»: выписки, разнесение, ФОТ.
                            </div>
                        </div>
                    </label>

                    <label
                        v-if="showSeesCompanyDashboardField"
                        class="flex items-start gap-3 rounded-xl border border-zinc-200 px-3 py-3 text-sm dark:border-zinc-800"
                    >
                        <input
                            v-model="form.sees_company_dashboard"
                            type="checkbox"
                            class="mt-1 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-600 dark:bg-zinc-950"
                        />
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-50">Дашборд: долги по всей компании</div>
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                По умолчанию руководитель видит возвраты и плитки только по своему подразделению. Включите, чтобы показывать картину по всей компании.
                            </div>
                        </div>
                    </label>

                    <div
                        v-if="form.has_signing_authority && ownCompanies.length > 0"
                        class="rounded-xl border border-amber-200/80 bg-amber-50/50 px-3 py-3 dark:border-amber-900/50 dark:bg-amber-950/20"
                    >
                        <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                            Наши компании для подписи
                        </label>
                        <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">
                            Если ничего не выбрано — пользователь может подписывать заявки по <strong class="font-medium">всем</strong> нашим компаниям. Отметьте компании, чтобы ограничить доступ.
                        </p>
                        <div class="mt-3 max-h-44 space-y-2 overflow-y-auto rounded-xl border border-zinc-200 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-950">
                            <label
                                v-for="company in ownCompanies"
                                :key="`sign-co-${company.id}`"
                                class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 text-sm text-zinc-800 transition hover:bg-zinc-50 dark:text-zinc-100 dark:hover:bg-zinc-900"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-600 dark:bg-zinc-950"
                                    :checked="isSigningCompanySelected(company.id)"
                                    @change="toggleSigningCompany(company.id)"
                                >
                                <span>{{ company.name }}</span>
                            </label>
                        </div>
                        <div v-if="form.errors.signing_own_company_ids" class="mt-1 text-sm text-rose-600">
                            {{ form.errors.signing_own_company_ids }}
                        </div>
                    </div>

                    <div
                        v-if="editingUser !== null"
                        class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-sm dark:border-zinc-800 dark:bg-zinc-950"
                    >
                        <div class="font-medium text-zinc-900 dark:text-zinc-50">
                            <template v-if="editingUser.has_password">
                                Пароль задан
                                <span class="ml-1.5 select-none tracking-[0.35em] text-zinc-500 dark:text-zinc-400" aria-hidden="true">••••••••</span>
                            </template>
                            <template v-else>
                                <span class="text-amber-800 dark:text-amber-200">Пароль ещё не задан</span>
                            </template>
                        </div>
                        <p class="mt-1 text-xs leading-snug text-zinc-500 dark:text-zinc-400">
                            Текущий пароль по соображениям безопасности не хранится и не показывается. Поля ниже — только для
                            <strong class="font-medium text-zinc-600 dark:text-zinc-300">нового</strong>
                            пароля; кнопка с глазом переключает видимость вводимого текста (если поле пустое, показывать нечего).
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                                {{ editingUser === null ? 'Пароль' : 'Новый пароль' }}
                            </label>
                            <div class="relative mt-2">
                                <input
                                    v-model="form.password"
                                    :type="passwordVisible ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    class="w-full rounded-xl border border-zinc-200 bg-white py-2 pl-3 pr-10 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50"
                                />
                                <button
                                    type="button"
                                    class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                    :disabled="editingUser !== null && form.password.length === 0"
                                    :aria-pressed="passwordVisible"
                                    :title="passwordFieldToggleTitle"
                                    aria-label="Показать или скрыть пароль"
                                    @click="passwordVisible = !passwordVisible"
                                >
                                    <EyeOff v-if="passwordVisible" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <div v-if="form.errors.password" class="mt-1 text-sm text-rose-600">{{ form.errors.password }}</div>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Подтверждение пароля</label>
                            <div class="relative mt-2">
                                <input
                                    v-model="form.password_confirmation"
                                    :type="passwordConfirmVisible ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    class="w-full rounded-xl border border-zinc-200 bg-white py-2 pl-3 pr-10 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50"
                                />
                                <button
                                    type="button"
                                    class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                    :disabled="editingUser !== null && form.password_confirmation.length === 0"
                                    :aria-pressed="passwordConfirmVisible"
                                    :title="passwordConfirmFieldToggleTitle"
                                    aria-label="Показать или скрыть подтверждение пароля"
                                    @click="passwordConfirmVisible = !passwordConfirmVisible"
                                >
                                    <EyeOff v-if="passwordConfirmVisible" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-3 dark:border-zinc-800 dark:bg-zinc-950">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50">Почта (IMAP reg.ru)</div>
                                <p class="mt-1 text-xs leading-snug text-zinc-500 dark:text-zinc-400">
                                    Логин ящика — адрес email выше. Пароль хранится зашифрованно для фоновой синхронизации переписки в CRM.
                                </p>
                            </div>
                            <label class="inline-flex shrink-0 items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                                <input v-model="form.mail_sync_enabled" type="checkbox" :class="crmCheckbox" />
                                Синхронизировать
                            </label>
                        </div>

                        <div
                            v-if="editingUser !== null"
                            class="mt-3 text-sm text-zinc-700 dark:text-zinc-200"
                        >
                            <template v-if="editingUser.has_mail_imap_password">
                                Пароль почты задан
                                <span class="ml-1.5 select-none tracking-[0.35em] text-zinc-500 dark:text-zinc-400" aria-hidden="true">••••••••</span>
                            </template>
                            <template v-else>
                                <span class="text-amber-800 dark:text-amber-200">Пароль почты не задан</span>
                            </template>
                            <p
                                v-if="editingUser.mail_last_sync_error"
                                class="mt-1 text-xs text-rose-600 dark:text-rose-300"
                            >
                                Последняя ошибка sync: {{ editingUser.mail_last_sync_error }}
                            </p>
                        </div>

                        <div class="mt-3">
                            <label class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                                {{ editingUser === null ? 'Пароль почты' : 'Новый пароль почты' }}
                            </label>
                            <div class="relative mt-2 max-w-md">
                                <input
                                    v-model="form.mail_password"
                                    :type="mailPasswordVisible ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    class="w-full rounded-xl border border-zinc-200 bg-white py-2 pl-3 pr-10 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:border-zinc-50"
                                />
                                <button
                                    type="button"
                                    class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                    :disabled="editingUser !== null && form.mail_password.length === 0"
                                    :aria-pressed="mailPasswordVisible"
                                    :title="mailPasswordFieldToggleTitle"
                                    aria-label="Показать или скрыть пароль почты"
                                    @click="mailPasswordVisible = !mailPasswordVisible"
                                >
                                    <EyeOff v-if="mailPasswordVisible" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <div v-if="form.errors.mail_password" class="mt-1 text-sm text-rose-600">{{ form.errors.mail_password }}</div>
                            <div v-if="form.errors.mail_sync_enabled" class="mt-1 text-sm text-rose-600">{{ form.errors.mail_sync_enabled }}</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
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
        </Modal>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff, Pencil, Plus, Power, Trash2 } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import Modal from '@/Components/Modal.vue';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmCheckbox,
    crmFieldFluid,
    crmGridPanel,
    crmModalFieldLabel,
    crmModalFieldRow,
    crmModalFieldsWrap,
    crmModalFormBody,
    crmModalFormShell,
    crmPageTitleSm,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'users' }, () => page),
});

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => [],
    },
    ownCompanies: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const showModal = ref(false);
const editingUser = ref(null);
const activeTab = ref('active');
const passwordVisible = ref(false);
const passwordConfirmVisible = ref(false);
const mailPasswordVisible = ref(false);

const activeUsers = computed(() => props.users.filter((user) => user.is_active));
const inactiveUsers = computed(() => props.users.filter((user) => !user.is_active));
const displayedUsers = computed(() => activeTab.value === 'active' ? activeUsers.value : inactiveUsers.value);

const showApprovalDepartmentsField = computed(() => {
    const selectedRoles = props.roles.filter((role) => form.role_ids.includes(Number(role.id)));
    const hasSupervisorRole = selectedRoles.some((role) => role.name === 'supervisor' || role.name === 'admin');

    return hasSupervisorRole || form.belongs_to_management;
});

const showSeesCompanyDashboardField = computed(() => {
    const selectedRoles = props.roles.filter((role) => form.role_ids.includes(Number(role.id)));

    return selectedRoles.some((role) => role.name === 'supervisor');
});

const passwordFieldToggleTitle = computed(() => {
    if (editingUser.value !== null && form.password.length === 0) {
        return 'Сначала введите новый пароль — нечего показывать';
    }

    return passwordVisible.value ? 'Скрыть вводимый пароль' : 'Показать вводимый пароль';
});

const passwordConfirmFieldToggleTitle = computed(() => {
    if (editingUser.value !== null && form.password_confirmation.length === 0) {
        return 'Сначала введите подтверждение — нечего показывать';
    }

    return passwordConfirmVisible.value ? 'Скрыть подтверждение' : 'Показать подтверждение';
});

const mailPasswordFieldToggleTitle = computed(() => {
    if (editingUser.value !== null && form.mail_password.length === 0) {
        return 'Сначала введите пароль почты — нечего показывать';
    }

    return mailPasswordVisible.value ? 'Скрыть пароль почты' : 'Показать пароль почты';
});

const tabs = computed(() => [
    { key: 'active', label: `Активные (${activeUsers.value.length})` },
    { key: 'inactive', label: `Неактивные (${inactiveUsers.value.length})` },
]);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    role_id: null,
    role_ids: [],
    is_active: true,
    has_signing_authority: false,
    belongs_to_management: false,
    can_management_accounting: false,
    sees_company_dashboard: false,
    signing_own_company_ids: [],
    primary_department_id: null,
    approval_department_ids: [],
    password: '',
    password_confirmation: '',
    mail_password: '',
    mail_sync_enabled: true,
});

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('ru-RU');
}

function resetPasswordVisibility() {
    passwordVisible.value = false;
    passwordConfirmVisible.value = false;
    mailPasswordVisible.value = false;
}

function resetForm() {
    form.reset();
    form.clearErrors();
    form.name = '';
    form.email = '';
    form.phone = '';
    form.role_id = null;
    form.role_ids = [];
    form.is_active = true;
    form.has_signing_authority = false;
    form.belongs_to_management = false;
    form.can_management_accounting = false;
    form.sees_company_dashboard = false;
    form.signing_own_company_ids = [];
    form.primary_department_id = null;
    form.approval_department_ids = [];
    form.password = '';
    form.password_confirmation = '';
    form.mail_password = '';
    form.mail_sync_enabled = true;
    resetPasswordVisibility();
}

function openCreateModal() {
    editingUser.value = null;
    resetForm();
    showModal.value = true;
}

function openEditModal(user) {
    editingUser.value = user;
    resetPasswordVisibility();
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.phone = user.phone ?? '';
    form.role_id = user.role_id;
    form.role_ids = Array.isArray(user.role_ids) && user.role_ids.length > 0
        ? user.role_ids.map((id) => Number(id))
        : (user.role_id ? [Number(user.role_id)] : []);
    form.is_active = user.is_active;
    form.has_signing_authority = Boolean(user.has_signing_authority);
    form.belongs_to_management = Boolean(user.belongs_to_management);
    form.can_management_accounting = Boolean(user.can_management_accounting);
    form.sees_company_dashboard = Boolean(user.sees_company_dashboard);
    form.signing_own_company_ids = Array.isArray(user.signing_own_company_ids)
        ? user.signing_own_company_ids.map((id) => Number(id))
        : [];
    form.primary_department_id = user.primary_department_id ? Number(user.primary_department_id) : null;
    form.approval_department_ids = Array.isArray(user.approval_department_ids)
        ? user.approval_department_ids.map((id) => Number(id))
        : [];
    form.password = '';
    form.password_confirmation = '';
    form.mail_password = '';
    form.mail_sync_enabled = user.mail_sync_enabled !== false;
    showModal.value = true;
}

watch(() => form.has_signing_authority, (enabled) => {
    if (!enabled) {
        form.signing_own_company_ids = [];
    }
});

watch(showApprovalDepartmentsField, (visible) => {
    if (!visible) {
        form.approval_department_ids = [];
    }
});

function isApprovalDepartmentSelected(departmentId) {
    const id = Number(departmentId);

    return form.approval_department_ids.some((selectedId) => Number(selectedId) === id);
}

function toggleApprovalDepartment(departmentId) {
    const id = Number(departmentId);
    const ids = form.approval_department_ids.map((selectedId) => Number(selectedId));

    if (ids.includes(id)) {
        form.approval_department_ids = ids.filter((selectedId) => selectedId !== id);
    } else {
        form.approval_department_ids = [...ids, id];
    }
}

function isSigningCompanySelected(companyId) {
    const id = Number(companyId);

    return form.signing_own_company_ids.some((selectedId) => Number(selectedId) === id);
}

function toggleSigningCompany(companyId) {
    const id = Number(companyId);
    const ids = form.signing_own_company_ids.map((selectedId) => Number(selectedId));

    if (ids.includes(id)) {
        form.signing_own_company_ids = ids.filter((selectedId) => selectedId !== id);

        return;
    }

    form.signing_own_company_ids = [...ids, id];
}

function isRoleSelected(roleId) {
    const id = Number(roleId);

    return form.role_ids.some((selectedId) => Number(selectedId) === id);
}

function toggleRole(roleId) {
    const id = Number(roleId);
    const ids = form.role_ids.map((selectedId) => Number(selectedId));

    if (ids.includes(id)) {
        form.role_ids = ids.filter((selectedId) => selectedId !== id);
    } else {
        form.role_ids = [...ids, id];
    }

    form.role_id = form.role_ids[0] ?? null;
}

watch(() => [...form.role_ids], (roleIds) => {
    if (editingUser.value !== null) {
        return;
    }

    const selectedRoles = props.roles.filter((role) => roleIds.includes(Number(role.id)));
    form.has_signing_authority = selectedRoles.some((role) => Boolean(role.default_has_signing_authority));
}, { deep: true });

function closeModal() {
    showModal.value = false;
    editingUser.value = null;
    resetForm();
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (editingUser.value === null) {
        form.post(route('users.store'), options);
        return;
    }

    form.patch(route('users.update', editingUser.value.id), options);
}

function buildUpdatePayload(user, overrides = {}) {
    return {
        name: user.name,
        email: user.email,
        phone: user.phone ?? '',
        role_id: user.role_id,
        role_ids: Array.isArray(user.role_ids) && user.role_ids.length > 0
            ? [...user.role_ids]
            : (user.role_id ? [user.role_id] : []),
        is_active: user.is_active,
        has_signing_authority: user.has_signing_authority,
        belongs_to_management: user.belongs_to_management,
        can_management_accounting: user.can_management_accounting,
        sees_company_dashboard: user.sees_company_dashboard,
        signing_own_company_ids: Array.isArray(user.signing_own_company_ids)
            ? [...user.signing_own_company_ids]
            : [],
        primary_department_id: user.primary_department_id ?? null,
        approval_department_ids: Array.isArray(user.approval_department_ids)
            ? [...user.approval_department_ids]
            : [],
        password: '',
        password_confirmation: '',
        mail_password: '',
        mail_sync_enabled: user.mail_sync_enabled !== false,
        ...overrides,
    };
}

function toggleActive(user) {
    router.patch(route('users.update', user.id), buildUpdatePayload(user, {
        is_active: !user.is_active,
    }), {
        preserveScroll: true,
    });
}

function removeUser(user) {
    if (!window.confirm(`Удалить пользователя ${user.name}?`)) {
        return;
    }

    router.delete(route('users.destroy', user.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="space-y-8">
        <!-- Заголовок и описание -->
        <div class="space-y-4">
            <div>
                <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">
                    Модули CRM
                </h1>
                <p class="mt-2 text-base text-zinc-600 dark:text-zinc-400">
                    Все доступные инструменты и разделы системы для эффективной работы
                </p>
            </div>
        </div>

        <!-- Сетка модулей -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <!-- Модуль "Заказы" -->
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white transition-all duration-300 hover:border-zinc-300 hover:shadow-xl dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-blue-500 to-purple-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                
                <Link href="/orders" class="block">
                    <div class="p-6">
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-100 to-purple-100 text-blue-600 dark:from-blue-900/30 dark:to-purple-900/30 dark:text-blue-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">
                            Заказы
                        </h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            Управление заявками, отслеживание статусов, работа с маршрутами и документами
                        </p>
                    </div>
                </Link>
            </div>

            <!-- Модуль "Контрагенты" -->
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white transition-all duration-300 hover:border-zinc-300 hover:shadow-xl dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-emerald-500 to-green-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                
                <Link href="/contractors" class="block">
                    <div class="p-6">
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-100 to-green-100 text-emerald-600 dark:from-emerald-900/30 dark:to-green-900/30 dark:text-emerald-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">
                            Контрагенты
                        </h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            База клиентов и перевозчиков, реквизиты, история взаимодействий и документы
                        </p>
                    </div>
                </Link>
            </div>

            <!-- Модуль "Финансы" -->
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white transition-all duration-300 hover:border-zinc-300 hover:shadow-xl dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-amber-500 to-orange-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                
                <Link href="/finance" class="block">
                    <div class="p-6">
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-100 to-orange-100 text-amber-600 dark:from-amber-900/30 dark:to-orange-900/30 dark:text-amber-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">
                            Финансы
                        </h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            Графики оплат, счета, зарплата, отчёты и финансовые показатели
                        </p>
                    </div>
                </Link>
            </div>

            <!-- Модуль "Помощник продавца" -->
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white transition-all duration-300 hover:border-zinc-300 hover:shadow-xl dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-rose-500 to-pink-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                
                <Link :href="route('sales-assistant.index')" class="block">
                    <div class="p-6">
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-100 to-pink-100 text-rose-600 dark:from-rose-900/30 dark:to-pink-900/30 dark:text-rose-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">
                            Помощник продавца
                        </h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            Скрипты продаж, книга знаний, тренажёр для отработки навыков менеджеров
                        </p>
                    </div>
                </Link>
            </div>

            <!-- Модуль "Задачи" -->
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white transition-all duration-300 hover:border-zinc-300 hover:shadow-xl dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-violet-500 to-indigo-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                
                <Link href="/tasks" class="block">
                    <div class="p-6">
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-100 to-indigo-100 text-violet-600 dark:from-violet-900/30 dark:to-indigo-900/30 dark:text-violet-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">
                            Задачи
                        </h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            Планирование, контроль выполнения, чек-листы и совместная работа над проектами
                        </p>
                    </div>
                </Link>
            </div>

            <!-- Модуль "Активности" -->
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white transition-all duration-300 hover:border-zinc-300 hover:shadow-xl dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-cyan-500 to-teal-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                
                <Link href="/activities" class="block">
                    <div class="p-6">
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-100 to-teal-100 text-cyan-600 dark:from-cyan-900/30 dark:to-teal-900/30 dark:text-cyan-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">
                            Активности
                        </h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            История действий, лог событий, отслеживание изменений и активность пользователей
                        </p>
                    </div>
                </Link>
            </div>

            <!-- Модуль "Отчёты" -->
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white transition-all duration-300 hover:border-zinc-300 hover:shadow-xl dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-lime-500 to-emerald-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                
                <Link href="/reports" class="block">
                    <div class="p-6">
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-lime-100 to-emerald-100 text-lime-600 dark:from-lime-900/30 dark:to-emerald-900/30 dark:text-lime-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">
                            Отчёты
                        </h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            Аналитика, статистика, KPI, дашборды и визуализация бизнес-показателей
                        </p>
                    </div>
                </Link>
            </div>

            <!-- Модуль "Настройки" -->
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white transition-all duration-300 hover:border-zinc-300 hover:shadow-xl dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-zinc-500 to-gray-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                
                <Link href="/settings" class="block">
                    <div class="p-6">
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-zinc-100 to-gray-100 text-zinc-600 dark:from-zinc-900/30 dark:to-gray-900/30 dark:text-zinc-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h3 class="mb-3 text-xl font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">
                            Настройки
                        </h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            Пользователи, роли, таблицы, справочники, шаблоны и системные параметры
                        </p>
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'modules' }, () => page),
});
</script>

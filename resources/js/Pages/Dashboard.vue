<template>
    <div class="space-y-6">
        <div v-if="isMobileStandalone" class="space-y-5">
            <section class="rounded-[28px] bg-zinc-900 px-5 py-6 text-white shadow-sm dark:bg-zinc-50 dark:text-zinc-900">
                <div class="text-xs uppercase tracking-[0.22em] text-white/60 dark:text-zinc-500">Мобильное приложение</div>
                <h1 class="mt-3 text-2xl font-semibold">Главный экран CRM</h1>
                <p class="mt-2 max-w-sm text-sm text-white/70 dark:text-zinc-600">
                    Быстрый доступ к заказам, базе контрагентов, отчётам и рабочим действиям без desktop-интерфейса.
                </p>
            </section>

            <section class="grid grid-cols-2 gap-3">
                <Link
                    href="/orders/create"
                    class="rounded-[24px] border border-zinc-200 bg-white p-4 text-left shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700 dark:hover:bg-zinc-800"
                >
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-zinc-900 text-white dark:bg-zinc-50 dark:text-zinc-900">
                        <SquarePen class="h-5 w-5" />
                    </div>
                    <div class="mt-4 text-sm font-semibold">Новый заказ</div>
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Запустить мастер и оформить заявку в дороге.</div>
                </Link>

                <Link
                    href="/contractors/create"
                    class="rounded-[24px] border border-zinc-200 bg-white p-4 text-left shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700 dark:hover:bg-zinc-800"
                >
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-50">
                        <Building2 class="h-5 w-5" />
                    </div>
                    <div class="mt-4 text-sm font-semibold">Новый контрагент</div>
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Быстро завести карточку клиента или перевозчика.</div>
                </Link>
            </section>

            <section class="space-y-3">
                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Что можно делать в приложении</div>

                <div class="space-y-3">
                    <Link
                        v-for="item in mobileSections"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-start gap-4 rounded-[24px] border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700 dark:hover:bg-zinc-800"
                    >
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-50">
                            <component :is="item.icon" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ item.title }}</div>
                            <div class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ item.description }}</div>
                        </div>
                    </Link>
                </div>
            </section>

            <section class="rounded-[24px] border border-dashed border-zinc-300 bg-white px-4 py-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                        <Bot class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">AI-строка</div>
                        <div class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                            Когда рабочий AI-контур будет подключён, сюда добавим быстрый сценарий общения и постановки задач прямо из приложения.
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div v-else>
            <div>
                <h1 class="text-2xl font-semibold">Панель</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Добро пожаловать в CRM.
                </p>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-3xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Заказы</div>
                    <div class="mt-2 text-3xl font-semibold">24</div>
                </div>

                <div class="rounded-3xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Контрагенты</div>
                    <div class="mt-2 text-3xl font-semibold">128</div>
                </div>

                <div class="rounded-3xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">Документы</div>
                    <div class="mt-2 text-3xl font-semibold">61</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { BarChart3, Bot, Building2, FileText, Package, SquarePen } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'dashboard' }, () => page),
});

const isMobileStandalone = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(max-width: 1023px)').matches
        && (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true);
});

const mobileSections = [
    {
        href: '/orders',
        title: 'Заказы',
        description: 'Открыть мобильный реестр заказов и перейти к текущим сделкам.',
        icon: Package,
    },
    {
        href: '/contractors',
        title: 'Контрагенты',
        description: 'Поиск по базе, открытие карточек и быстрый доступ к реквизитам.',
        icon: Building2,
    },
    {
        href: '/reports',
        title: 'Отчёты и статистика',
        description: 'Ключевые показатели и сводки без перегруженных desktop-таблиц.',
        icon: BarChart3,
    },
    {
        href: '/documents',
        title: 'Счета и документы',
        description: 'Следующий шаг мобильного контура: выставление и сопровождение документов.',
        icon: FileText,
    },
];
</script>

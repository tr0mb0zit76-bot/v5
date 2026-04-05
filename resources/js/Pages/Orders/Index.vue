<template>
    <div class="flex h-full min-h-0 flex-col gap-2">
        <div v-if="isMobileStandalone" class="space-y-4 pb-24">
            <section class="rounded-[28px] bg-zinc-900 px-5 py-6 text-white shadow-sm dark:bg-zinc-50 dark:text-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-[0.22em] text-white/60 dark:text-zinc-500">Мобильный реестр</div>
                        <h1 class="mt-3 text-2xl font-semibold">Заказы</h1>
                        <p class="mt-2 text-sm text-white/70 dark:text-zinc-600">Все активные сделки в компактном мобильном формате.</p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-11 items-center gap-2 rounded-2xl bg-white px-4 text-sm font-medium text-zinc-900 transition hover:bg-zinc-200 dark:bg-zinc-900 dark:text-zinc-50 dark:hover:bg-zinc-800"
                        @click="openCreateOrder"
                    >
                        <Plus class="h-4 w-4" />
                        Новый
                    </button>
                </div>
            </section>

            <section class="space-y-3 rounded-[24px] border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        v-model="mobileSearch"
                        type="text"
                        placeholder="Поиск по номеру, клиенту, маршруту"
                        class="w-full rounded-2xl border border-zinc-300 bg-white py-3 pl-10 pr-4 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                    />
                </div>

                <div class="flex items-center justify-between gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                    <span>Найдено: {{ mobileRows.length }}</span>
                    <span>Всего: {{ rows.length }}</span>
                </div>
            </section>

            <section class="space-y-3">
                <button
                    v-for="row in mobileRows"
                    :key="row.id"
                    type="button"
                    class="w-full rounded-[24px] border border-zinc-200 bg-white p-4 text-left shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700 dark:hover:bg-zinc-800"
                    @click="handleRowDblClick(row)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                                {{ row.order_number || `Заказ #${row.id}` }}
                            </div>
                            <div class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">
                                {{ row.customer_name || 'Клиент не указан' }}
                            </div>
                        </div>

                        <span
                            class="shrink-0 rounded-full px-2 py-1 text-[11px] font-medium"
                            :class="mobileStatusClass(row.status_text)"
                        >
                            {{ row.status_text || 'Без статуса' }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs text-zinc-600 dark:text-zinc-300">
                        <div>
                            <div class="text-zinc-400 dark:text-zinc-500">Маршрут</div>
                            <div class="mt-1 line-clamp-2">
                                {{ row.loading_point || '—' }} → {{ row.unloading_point || '—' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-zinc-400 dark:text-zinc-500">Груз</div>
                            <div class="mt-1 line-clamp-2">{{ row.cargo_description || 'Не указан' }}</div>
                        </div>
                        <div>
                            <div class="text-zinc-400 dark:text-zinc-500">Дата</div>
                            <div class="mt-1">{{ formatDate(row.order_date) }}</div>
                        </div>
                        <div>
                            <div class="text-zinc-400 dark:text-zinc-500">Ставка клиента</div>
                            <div class="mt-1">{{ formatMoney(row.customer_rate) }}</div>
                        </div>
                    </div>
                </button>

                <div
                    v-if="mobileRows.length === 0"
                    class="rounded-[24px] border border-dashed border-zinc-300 bg-white px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400"
                >
                    По текущему запросу заказы не найдены.
                </div>
            </section>

            <div class="sticky bottom-0 z-20 -mx-1 pt-2">
                <div class="rounded-[26px] border border-zinc-200/80 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-zinc-800/80 dark:bg-zinc-950/95">
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs uppercase tracking-[0.2em] text-zinc-400 dark:text-zinc-500">Быстрое действие</div>
                            <div class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ mobileRows.length === rows.length ? `Все заказы: ${rows.length}` : `Найдено: ${mobileRows.length} из ${rows.length}` }}
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-12 shrink-0 items-center gap-2 rounded-2xl bg-zinc-900 px-4 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                            @click="openCreateOrder"
                        >
                            <Plus class="h-4 w-4" />
                            Новый заказ
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <template v-else>
            <div class="flex items-start justify-between gap-4">
                <div class="shrink-0">
                    <h1 class="text-2xl font-semibold">Заказы</h1>
                    <p class="text-sm text-zinc-500">Всего заказов: {{ rows.length }}</p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    @click="openCreateOrder"
                >
                    <Plus class="h-4 w-4" />
                    Добавить
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-hidden">
                <OrdersGrid
                    :rows="rows"
                    :available-columns="availableColumns"
                    :role-key="roleKey"
                    :role-columns-config="roleColumnsConfig"
                    :user-id="userId"
                    :editable="true"
                    @cell-save="handleCellSave"
                    @row-dblclick="handleRowDblClick"
                    @row-delete="handleRowDelete"
                />
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import OrdersGrid from '@/Components/Orders/OrdersGrid.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'orders' }, () => page),
});

const page = usePage();
const mobileSearch = ref('');

const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const roleKey = computed(() => page.props.roleKey ?? page.props.auth?.user?.role?.name ?? 'manager');
const roleColumnsConfig = computed(() => page.props.auth?.user?.role?.columns_config ?? {});
const availableColumns = computed(() => page.props.orderColumns ?? []);
const rows = computed(() => page.props.rows ?? []);
const isMobileStandalone = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(max-width: 1023px)').matches
        && (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true);
});

const mobileRows = computed(() => {
    const query = mobileSearch.value.trim().toLowerCase();

    if (query === '') {
        return rows.value;
    }

    return rows.value.filter((row) => {
        return [
            row.order_number,
            row.customer_name,
            row.carrier_name,
            row.loading_point,
            row.unloading_point,
            row.cargo_description,
            row.status_text,
        ].filter(Boolean).some((value) => String(value).toLowerCase().includes(query));
    });
});

const handleCellSave = (event) => {
    if (!event?.row?.id || !event?.field) {
        return;
    }

    router.patch(route('orders.inline-update', event.row.id), {
        field: event.field,
        value: event.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['rows'],
    });
};

const handleRowDblClick = (row) => {
    if (row?.id) {
        router.get(route('orders.edit', row.id), {}, { preserveScroll: true });
    }
};

const openCreateOrder = () => {
    router.get(route('orders.create'), {}, { preserveScroll: true });
};

const handleRowDelete = (row) => {
    if (!row?.id) {
        return;
    }

    if (!window.confirm(`Удалить заказ ${row.order_number || `#${row.id}`}?`)) {
        return;
    }

    router.delete(route('orders.destroy', row.id), {
        preserveScroll: true,
    });
};

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('ru-RU');
};

const formatMoney = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return `${new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(value))} ₽`;
};

const mobileStatusClass = (status) => {
    const normalizedStatus = String(status ?? '').toLowerCase();

    if (['new', 'новый', 'draft'].some((item) => normalizedStatus.includes(item))) {
        return 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300';
    }

    if (['progress', 'в работе', 'loading', 'loaded'].some((item) => normalizedStatus.includes(item))) {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300';
    }

    if (['done', 'completed', 'закрыт', 'заверш'].some((item) => normalizedStatus.includes(item))) {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
    }

    return 'bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200';
};
</script>

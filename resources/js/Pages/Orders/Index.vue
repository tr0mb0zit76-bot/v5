<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <div v-if="isMobileStandalone" class="space-y-4 pb-24">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">Заказы</h1>

                    <button
                        type="button"
                        class="inline-flex h-11 items-center gap-2 rounded-2xl border border-emerald-200/90 bg-emerald-50 px-4 text-sm font-medium text-emerald-900 shadow-sm transition hover:bg-emerald-100 dark:border-emerald-800/70 dark:bg-emerald-950/50 dark:text-emerald-50 dark:hover:bg-emerald-900/45"
                        @click="openCreateOrder"
                    >
                        <Plus class="h-4 w-4" />
                        Новый
                    </button>
            </div>

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

                <div class="-mx-1 flex gap-2 overflow-x-auto pb-1 pt-1">
                    <button
                        v-for="opt in mobileSortOptions"
                        :key="opt.value"
                        type="button"
                        class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="mobileSort === opt.value
                            ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900'
                            : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300 dark:hover:border-zinc-600'"
                        @click="mobileSort = opt.value"
                    >
                        {{ opt.label }}
                    </button>
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

            <div v-if="false" class="sticky bottom-0 z-20 -mx-1 pt-2">
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
            <CrmPageHeader
                :lead="ordersPageLead"
                title="Заказы"
            >
                <template #actions>
                    <button
                        type="button"
                        :class="crmBtnCreate"
                        @click="openCreateOrder"
                    >
                        <Plus class="h-4 w-4" />
                        Добавить
                    </button>
                </template>
            </CrmPageHeader>

            <div :class="crmGridPanel">
                <OrdersGrid
                    :rows="displayedRows"
                    :available-columns="availableColumns"
                    :role-key="roleKey"
                    :role-columns-config="roleColumnsConfig"
                    :inline-editable-fields="orderInlineEditableFields"
                    :user-id="userId"
                    :payment-form-select-options="paymentFormOptions"
                    :editable="true"
                    :can-use-load-board="canUseLoadBoard"
                    @cell-save="handleCellSave"
                    @row-dblclick="handleRowDblClick"
                    @row-delete="handleRowDelete"
                    @create-request="openCreateOrder"
                    @create-from-request="openCreateOrderFrom"
                    @open-order-documents="handleOpenOrderDocuments"
                    @publish-load-board="openLoadBoardFromOrder"
                />
            </div>
        </template>

        <OrderDocumentsModal
            :show="orderDocumentsModal.show"
            :order-id="orderDocumentsModal.orderId"
            :order-number="orderDocumentsModal.orderNumber"
            @close="closeOrderDocumentsModal"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';

const MOBILE_SORT_OPTIONS = [
    { value: 'id_desc', label: 'Сначала новые' },
    { value: 'id_asc', label: 'Сначала старые' },
    { value: 'number_desc', label: 'Номер ↓' },
    { value: 'number_asc', label: 'Номер ↑' },
    { value: 'date_desc', label: 'Дата ↓' },
    { value: 'date_asc', label: 'Дата ↑' },
];
import { router, usePage } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnCreate, crmGridPanel } from '@/support/crmUi.js';
import OrdersGrid from '@/Components/Orders/OrdersGrid.vue';
import OrderDocumentsModal from '@/Components/Orders/OrderDocumentsModal.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'orders', mainFill: true }, () => page),
});

const page = usePage();
const mobileSearch = ref('');
const mobileSort = ref('id_desc');
const mobileSortOptions = MOBILE_SORT_OPTIONS;

const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const roleKey = computed(() => page.props.roleKey ?? page.props.auth?.user?.role?.name ?? 'manager');
const roleColumnsConfig = computed(() => page.props.auth?.user?.role?.columns_config ?? {});
const canUseLoadBoard = computed(() => {
    const role = page.props.auth?.user?.role ?? {};
    const areas = role.visibility_areas ?? [];

    return Boolean(role.is_admin) || role.name === 'admin' || areas.includes('load_board');
});
const availableColumns = computed(() => page.props.orderColumns ?? []);
const orderInlineEditableFields = computed(() => page.props.orderInlineEditableFields ?? []);
const rows = computed(() => page.props.rows ?? []);
const orderDateFrom = ref(null);
const orderDateTo = ref(null);

const hasOrderPeriodFilter = computed(() => Boolean(orderDateFrom.value && orderDateTo.value));

const displayedRows = computed(() => {
    if (!hasOrderPeriodFilter.value) {
        return rows.value;
    }

    return rows.value.filter((row) => {
        const date = row.order_date ? String(row.order_date).slice(0, 10) : '';

        if (date === '') {
            return false;
        }

        return date >= orderDateFrom.value && date <= orderDateTo.value;
    });
});

const ordersPageLead = computed(() => {
    if (hasOrderPeriodFilter.value) {
        return `За период ${orderDateFrom.value} — ${orderDateTo.value}: ${displayedRows.value.length} из ${rows.value.length}`;
    }

    return `Всего заказов: ${rows.value.length}`;
});

const paymentFormOptions = computed(() => page.props.paymentFormOptions ?? []);

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    const from = url.searchParams.get('order_date_from');
    const to = url.searchParams.get('order_date_to');

    if (!from || !to) {
        return;
    }

    orderDateFrom.value = from;
    orderDateTo.value = to;
    url.searchParams.delete('order_date_from');
    url.searchParams.delete('order_date_to');
    window.history.replaceState({}, '', url.pathname + url.search);
});
const isMobileStandalone = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(max-width: 1023px)').matches
        && (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true);
});

function sortMobileRows(list) {
    const out = [...list];
    const cmpNum = (a, b) => Number(a) - Number(b);
    const cmpStr = (a, b) => String(a ?? '').localeCompare(String(b ?? ''), 'ru', { numeric: true, sensitivity: 'base' });
    const dateVal = (r) => {
        if (! r?.order_date) {
            return 0;
        }
        const t = new Date(r.order_date).getTime();

        return Number.isFinite(t) ? t : 0;
    };

    switch (mobileSort.value) {
        case 'id_asc':
            out.sort((a, b) => cmpNum(a.id, b.id));
            break;
        case 'number_desc':
            out.sort((a, b) => -cmpStr(a.order_number, b.order_number));
            break;
        case 'number_asc':
            out.sort((a, b) => cmpStr(a.order_number, b.order_number));
            break;
        case 'date_desc':
            out.sort((a, b) => dateVal(b) - dateVal(a));
            break;
        case 'date_asc':
            out.sort((a, b) => dateVal(a) - dateVal(b));
            break;
        case 'id_desc':
        default:
            out.sort((a, b) => cmpNum(b.id, a.id));
    }

    return out;
}

const mobileRows = computed(() => {
    const query = mobileSearch.value.trim().toLowerCase();

    const filtered = query === ''
        ? displayedRows.value
        : displayedRows.value.filter((row) => {
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

    return sortMobileRows(filtered);
});

const handleCellSave = (event) => {
    if (!event?.row?.id || !event?.field) {
        return;
    }

    router.post(route('orders.inline-update', event.row.id), {
        _method: 'patch',
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

const orderDocumentsModal = reactive({
    show: false,
    orderId: null,
    orderNumber: '',
});

const handleOpenOrderDocuments = (row) => {
    if (!row?.id) {
        return;
    }

    orderDocumentsModal.show = true;
    orderDocumentsModal.orderId = row.id;
    orderDocumentsModal.orderNumber = row.order_number || '';
};

const closeOrderDocumentsModal = () => {
    orderDocumentsModal.show = false;
};

const openCreateOrder = () => {
    router.get(route('orders.create'), {}, { preserveScroll: true });
};

const openCreateOrderFrom = (row) => {
    if (!row?.id) {
        return;
    }

    router.get(route('orders.create', { from: row.id }), {}, { preserveScroll: true });
};

const openLoadBoardFromOrder = (row) => {
    if (!row?.id) {
        return;
    }

    router.get(route('load-board.index', { from_order: row.id }), {}, { preserveScroll: true });
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
        onError: (errors) => {
            const message = errors?.message
                ?? (typeof errors === 'object' ? Object.values(errors).flat().find(Boolean) : null);

            if (message && typeof window !== 'undefined') {
                window.alert(message);
            }
        },
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

onMounted(() => {
    router.reload({
        only: ['rows'],
        preserveScroll: true,
        preserveState: false,
    });
});
</script>

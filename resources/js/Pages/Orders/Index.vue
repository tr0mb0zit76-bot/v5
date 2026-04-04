<template>
    <div class="flex h-full min-h-0 flex-col gap-2">
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
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import OrdersGrid from '@/Components/Orders/OrdersGrid.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'orders' }, () => page),
});

const page = usePage();

const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
const roleKey = computed(() => page.props.roleKey ?? page.props.auth?.user?.role?.name ?? 'manager');
const roleColumnsConfig = computed(() => page.props.auth?.user?.role?.columns_config ?? {});
const availableColumns = computed(() => page.props.orderColumns ?? []);
const rows = computed(() => page.props.rows ?? []);

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
        router.get(route('orders.edit', row.id));
    }
};

const openCreateOrder = () => {
    router.get(route('orders.create'));
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
</script>

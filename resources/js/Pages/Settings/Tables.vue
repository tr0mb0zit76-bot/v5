<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div class="shrink-0">
            <h1 class="text-2xl font-semibold">Управление таблицей</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Здесь задаётся разрешённый набор колонок для ролей в таблицах заказов, лидов и контрагентов. Пользователь видит и настраивает только те поля, которые разрешены его роли.
            </p>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 xl:grid-cols-[280px_minmax(0,1fr)]">
            <section class="min-h-0 overflow-hidden border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-4 py-3 text-sm font-medium dark:border-zinc-800">
                    Роли
                </div>
                <div class="flex max-h-full flex-col overflow-y-auto p-2">
                    <button
                        v-for="role in roles"
                        :key="role.id"
                        type="button"
                        class="flex items-center justify-between gap-3 px-3 py-3 text-left transition-colors"
                        :class="selectedRoleId === role.id
                            ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'
                            : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/70'"
                        @click="selectRole(role.id)"
                    >
                        <div class="font-medium">{{ role.display_name || role.name }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ allowedCount(role.id) }}/{{ activeColumns.length }}
                        </div>
                    </button>
                </div>
            </section>

            <section class="min-h-0 overflow-hidden border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <div>
                        <div class="font-medium">
                            {{ selectedRole?.display_name || selectedRole?.name || 'Роль не выбрана' }}
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            Разрешённые колонки и порядок для таблицы «{{ tableDefinitions.find((table) => table.key === selectedTableKey)?.label || 'Таблица' }}»
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                            @click="resetSelectedRole"
                        >
                            Сбросить
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-zinc-900 px-4 py-2 text-sm text-white hover:bg-zinc-800 disabled:opacity-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                            :disabled="!selectedRole || form.processing"
                            @click="saveSelectedRole"
                        >
                            {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <button
                        v-for="table in tableDefinitions"
                        :key="table.key"
                        type="button"
                        class="rounded-xl border px-3 py-2 text-sm transition-colors"
                        :class="selectedTableKey === table.key
                            ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900'
                            : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800'"
                        @click="selectTable(table.key)"
                    >
                        {{ table.label }}
                    </button>
                </div>

                <div v-if="selectedColumns.length" class="max-h-full overflow-y-auto p-4">
                    <div class="space-y-5">
                        <section v-for="group in groupedSelectedColumns" :key="group.key" class="space-y-2">
                            <div class="sticky top-0 z-10 border-b border-zinc-200 bg-white px-1 py-2 text-sm font-semibold text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">
                                {{ group.label }}
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="item in group.columns"
                                    :key="item.column.colId"
                                    class="grid grid-cols-1 gap-3 border border-zinc-200 px-4 py-3 dark:border-zinc-800 lg:grid-cols-[minmax(0,1fr)_110px_140px_92px]"
                                >
                                    <div class="min-w-0">
                                        <div class="font-medium">{{ findColumnLabel(item.column.colId) }}</div>
                                    </div>

                                    <label class="flex items-center gap-2 text-sm">
                                        <input
                                            :checked="!item.column.hide"
                                            type="checkbox"
                                            class="rounded border-zinc-300"
                                            @change="toggleVisibility(item.index)"
                                        />
                                        Доступна роли
                                    </label>

                                    <label class="flex items-center gap-2 text-sm">
                                        <span class="text-zinc-500 dark:text-zinc-400">Ширина</span>
                                        <input
                                            :value="item.column.width"
                                            type="number"
                                            min="60"
                                            max="500"
                                            class="w-24 border border-zinc-300 bg-white px-2 py-1 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                                            @input="updateWidth(item.index, $event)"
                                        />
                                    </label>

                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-zinc-200 px-2 py-1 text-sm hover:bg-zinc-50 disabled:opacity-40 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                            :disabled="item.index === 0"
                                            @click="moveColumn(item.index, -1)"
                                        >
                                            ↑
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-zinc-200 px-2 py-1 text-sm hover:bg-zinc-50 disabled:opacity-40 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                            :disabled="item.index === selectedColumns.length - 1"
                                            @click="moveColumn(item.index, 1)"
                                        >
                                            ↓
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <div v-else class="flex h-full items-center justify-center p-8 text-sm text-zinc-500 dark:text-zinc-400">
                    Выберите роль слева.
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'configuration', activeLeafKey: 'table-presets' }, () => page),
});

const props = defineProps({
    roles: {
        type: Array,
        default: () => [],
    },
    orderColumns: {
        type: Array,
        default: () => [],
    },
    leadColumns: {
        type: Array,
        default: () => [],
    },
    contractorColumns: {
        type: Array,
        default: () => [],
    },
});

const tableDefinitions = [
    { key: 'orders', label: 'Заказы' },
    { key: 'leads', label: 'Лиды' },
    { key: 'contractors', label: 'Контрагенты' },
];

const groupDefinitions = [
    { key: 'identity', label: 'Идентификация' },
    { key: 'routing', label: 'Маршрут и груз' },
    { key: 'participants', label: 'Участники' },
    { key: 'finance', label: 'Финансы' },
    { key: 'status', label: 'Статусы' },
    { key: 'documents', label: 'Документы' },
    { key: 'tracking', label: 'Трекинг и заявки' },
    { key: 'contacts', label: 'Контакты' },
    { key: 'system', label: 'Системные поля' },
];

const groupMap = {
    id: 'identity',
    order_number: 'identity',
    company_code: 'identity',
    manager_id: 'identity',
    manager_name: 'identity',
    site_id: 'identity',
    loading_point: 'routing',
    unloading_point: 'routing',
    loading_date: 'routing',
    unloading_date: 'routing',
    order_date: 'routing',
    cargo_description: 'routing',
    customer_id: 'participants',
    customer_name: 'participants',
    customer_payment_form: 'participants',
    customer_payment_term: 'participants',
    carrier_id: 'participants',
    carrier_name: 'participants',
    carrier_payment_form: 'participants',
    carrier_payment_term: 'participants',
    driver_id: 'participants',
    customer_rate: 'finance',
    carrier_rate: 'finance',
    additional_expenses: 'finance',
    insurance: 'finance',
    bonus: 'finance',
    delta: 'finance',
    kpi_percent: 'finance',
    salary_accrued: 'finance',
    salary_paid: 'finance',
    status: 'status',
    manual_status: 'status',
    status_text: 'status',
    status_updated_by: 'status',
    status_updated_at: 'status',
    is_active: 'status',
    invoice_number: 'documents',
    upd_number: 'documents',
    waybill_number: 'documents',
    upd_carrier_number: 'documents',
    upd_carrier_date: 'documents',
    track_number_customer: 'tracking',
    track_sent_date_customer: 'tracking',
    track_received_date_customer: 'tracking',
    track_number_carrier: 'tracking',
    track_sent_date_carrier: 'tracking',
    track_received_date_carrier: 'tracking',
    order_customer_number: 'tracking',
    order_customer_date: 'tracking',
    order_carrier_number: 'tracking',
    order_carrier_date: 'tracking',
    customer_contact_name: 'contacts',
    customer_contact_phone: 'contacts',
    customer_contact_email: 'contacts',
    carrier_contact_name: 'contacts',
    carrier_contact_phone: 'contacts',
    carrier_contact_email: 'contacts',
    ai_draft_id: 'system',
    ai_confidence: 'system',
    ai_metadata: 'system',
    ati_response: 'system',
    ati_load_id: 'system',
    ati_published_at: 'system',
    created_by: 'system',
    updated_by: 'system',
    metadata: 'system',
    payment_statuses: 'system',
    created_at: 'system',
    updated_at: 'system',
};

const leadGroupMap = {
    number: 'identity',
    title: 'identity',
    source: 'identity',
    status: 'status',
    counterparty_name: 'participants',
    responsible_name: 'participants',
    planned_shipping_date: 'routing',
    target_price: 'finance',
    target_currency: 'finance',
    has_offer: 'documents',
    created_at: 'system',
};

const contractorGroupMap = {
    name: 'identity',
    status_text: 'status',
    type_label: 'identity',
    activity_types_label: 'participants',
    inn: 'identity',
    primary_contact: 'contacts',
    phone: 'contacts',
    email: 'contacts',
    contacts_count: 'contacts',
    orders_count: 'tracking',
    current_debt: 'finance',
    is_verified: 'status',
    is_own_company: 'status',
};

const selectedRoleId = ref(props.roles[0]?.id ?? null);
const selectedTableKey = ref('orders');
const draftColumnsByRole = ref(Object.fromEntries(
    props.roles.map((role) => [
        role.id,
        {
            orders: (role.columns_config?.orders ?? []).map((column) => ({ ...column })),
            leads: (role.columns_config?.leads ?? []).map((column) => ({ ...column })),
            contractors: (role.columns_config?.contractors ?? []).map((column) => ({ ...column })),
        },
    ]),
));

const form = useForm({
    table: 'orders',
    columns: [],
});

const selectedRole = computed(() => props.roles.find((role) => role.id === selectedRoleId.value) ?? null);
const selectedColumns = computed(() => draftColumnsByRole.value[selectedRoleId.value]?.[selectedTableKey.value] ?? []);
const activeColumns = computed(() => ({
    orders: props.orderColumns,
    leads: props.leadColumns,
    contractors: props.contractorColumns,
}[selectedTableKey.value] ?? []));

const groupedSelectedColumns = computed(() => {
    const currentGroupMap = {
        orders: groupMap,
        leads: leadGroupMap,
        contractors: contractorGroupMap,
    }[selectedTableKey.value] ?? {};

    const grouped = new Map(groupDefinitions.map((group) => [group.key, {
        key: group.key,
        label: group.label,
        columns: [],
    }]));

    selectedColumns.value.forEach((column, index) => {
        const groupKey = currentGroupMap[column.colId] ?? 'system';
        grouped.get(groupKey)?.columns.push({ column, index });
    });

    return groupDefinitions
        .map((group) => grouped.get(group.key))
        .filter((group) => group && group.columns.length > 0);
});

function selectRole(roleId) {
    selectedRoleId.value = roleId;
}

function selectTable(tableKey) {
    selectedTableKey.value = tableKey;
}

function allowedCount(roleId) {
    return (draftColumnsByRole.value[roleId]?.[selectedTableKey.value] ?? []).filter((column) => !column.hide).length;
}

function findColumnLabel(field) {
    return activeColumns.value.find((column) => column.field === field)?.label ?? field;
}

function patchSelectedColumns(callback) {
    if (!selectedRoleId.value) {
        return;
    }

    const nextColumns = [...(draftColumnsByRole.value[selectedRoleId.value]?.[selectedTableKey.value] ?? [])];
    callback(nextColumns);
    draftColumnsByRole.value = {
        ...draftColumnsByRole.value,
        [selectedRoleId.value]: {
            ...(draftColumnsByRole.value[selectedRoleId.value] ?? {}),
            [selectedTableKey.value]: nextColumns.map((column, index) => ({
                ...column,
                order: index,
            })),
        },
    };
}

function toggleVisibility(index) {
    patchSelectedColumns((columns) => {
        columns[index] = {
            ...columns[index],
            hide: !columns[index].hide,
        };
    });
}

function updateWidth(index, event) {
    const width = Number.parseInt(event.target.value, 10);

    patchSelectedColumns((columns) => {
        columns[index] = {
            ...columns[index],
            width: Number.isNaN(width) ? columns[index].width : Math.min(500, Math.max(60, width)),
        };
    });
}

function moveColumn(index, direction) {
    const targetIndex = index + direction;

    patchSelectedColumns((columns) => {
        if (targetIndex < 0 || targetIndex >= columns.length) {
            return;
        }

        const [moved] = columns.splice(index, 1);
        columns.splice(targetIndex, 0, moved);
    });
}

function resetSelectedRole() {
    if (!selectedRole.value) {
        return;
    }

    draftColumnsByRole.value = {
        ...draftColumnsByRole.value,
        [selectedRole.value.id]: {
            ...(draftColumnsByRole.value[selectedRole.value.id] ?? {}),
            [selectedTableKey.value]: (selectedRole.value.columns_config?.[selectedTableKey.value] ?? []).map((column) => ({ ...column })),
        },
    };
}

function saveSelectedRole() {
    if (!selectedRole.value) {
        return;
    }

    form.table = selectedTableKey.value;
    form.columns = selectedColumns.value.map((column, index) => ({
        colId: column.colId,
        hide: Boolean(column.hide),
        width: column.width,
        order: index,
    }));

    form.patch(route('settings.tables.update', selectedRole.value.id), {
        preserveScroll: true,
    });
}
</script>

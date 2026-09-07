<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CrmPageHeader from '@/Components/CrmPageHeader.vue';
import Modal from '@/Components/Modal.vue';
import CrmModalHeader from '@/Components/CrmModalHeader.vue';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmBtnSecondary,
    crmFieldFluid,
    crmGridPanel,
    crmModalFieldLabel,
    crmModalFieldRow,
    crmModalFieldsWrap,
    crmModalFormBody,
    crmModalFormShell,
    crmPill,
    crmPillActive,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'epd', mainFill: true }, () => page),
});

const props = defineProps({
    rows: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({ type: 'all', unlinked: false, q: '' }),
    },
    typeOptions: { type: Array, default: () => [] },
    canSync: { type: Boolean, default: false },
    lastSyncedAt: { type: String, default: null },
});

const localFilters = reactive({
    type: props.filters?.type || 'all',
    unlinked: Boolean(props.filters?.unlinked),
    q: props.filters?.q || '',
});

watch(
    () => props.filters,
    (value) => {
        localFilters.type = value?.type || 'all';
        localFilters.unlinked = Boolean(value?.unlinked);
        localFilters.q = value?.q || '';
    },
    { deep: true },
);

const syncBusy = ref(false);
const syncMessage = ref('');
const linkModal = reactive({
    show: false,
    entry: null,
    q: '',
    orders: [],
    busy: false,
    error: '',
});

const unlinkedCount = computed(() => props.rows.filter((row) => !row.order_id).length);

function applyFilters() {
    router.get(route('epd.index'), {
        type: localFilters.type === 'all' ? undefined : localFilters.type,
        unlinked: localFilters.unlinked ? 1 : undefined,
        q: localFilters.q || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

async function syncNow() {
    if (!props.canSync || syncBusy.value) {
        return;
    }
    syncBusy.value = true;
    syncMessage.value = '';
    try {
        const { data } = await window.axios.post(route('epd.sync'));
        syncMessage.value = data?.stats?.skipped
            ? `Пропуск: ${data.stats.reason || 'skipped'}`
            : `Обновлено: ${data?.stats?.upserted ?? 0} (ошибок: ${data?.stats?.errors ?? 0})`;
        router.reload({ only: ['rows', 'lastSyncedAt'], preserveScroll: true });
    } catch (error) {
        syncMessage.value = error?.response?.data?.message || 'Не удалось синхронизировать реестр ЭПД.';
    } finally {
        syncBusy.value = false;
    }
}

function openLinkModal(entry) {
    linkModal.show = true;
    linkModal.entry = entry;
    linkModal.q = '';
    linkModal.orders = [];
    linkModal.busy = false;
    linkModal.error = '';
}

function closeLinkModal() {
    linkModal.show = false;
    linkModal.entry = null;
}

async function searchOrders() {
    const q = String(linkModal.q || '').trim();
    if (q === '') {
        linkModal.orders = [];
        return;
    }
    linkModal.busy = true;
    linkModal.error = '';
    try {
        const { data } = await window.axios.get(route('epd.orders.search'), { params: { q } });
        linkModal.orders = Array.isArray(data?.orders) ? data.orders : [];
    } catch (error) {
        linkModal.error = error?.response?.data?.message || 'Не удалось найти заказы.';
    } finally {
        linkModal.busy = false;
    }
}

async function linkOrder(order) {
    if (!linkModal.entry?.id || !order?.id || linkModal.busy) {
        return;
    }
    linkModal.busy = true;
    linkModal.error = '';
    try {
        await window.axios.post(route('epd.link', linkModal.entry.id), { order_id: order.id });
        closeLinkModal();
        router.reload({ only: ['rows'], preserveScroll: true });
    } catch (error) {
        linkModal.error = error?.response?.data?.errors?.order_id?.[0]
            || error?.response?.data?.message
            || 'Не удалось связать с заказом.';
    } finally {
        linkModal.busy = false;
    }
}

async function unlinkEntry(entry) {
    if (!entry?.id || !entry.order_id) {
        return;
    }
    if (!window.confirm(`Отвязать документ от заказа ${entry.order_number || entry.order_id}?`)) {
        return;
    }
    try {
        await window.axios.post(route('epd.unlink', entry.id));
        router.reload({ only: ['rows'], preserveScroll: true });
    } catch (error) {
        window.alert(error?.response?.data?.message || 'Не удалось отвязать.');
    }
}

function formatDate(value) {
    if (!value) {
        return '—';
    }
    const raw = String(value).slice(0, 10);
    const [y, m, d] = raw.split('-');
    if (!y || !m || !d) {
        return raw;
    }
    return `${d}.${m}.${y}`;
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <CrmPageHeader
            lead="Зеркало РеестрЭПД из 1С: поручения, расписки, ЭТрН и заказы-заявки. Свяжите документ с заказом CRM — связь появится на вкладке «ЭПД» в мастере."
            title="ЭПД"
        >
            <template #actions>
                <button
                    type="button"
                    :class="crmBtnSecondary"
                    :disabled="!canSync || syncBusy"
                    @click="syncNow"
                >
                    {{ syncBusy ? 'Синхронизация…' : 'Обновить из 1С' }}
                </button>
            </template>
        </CrmPageHeader>

        <p v-if="syncMessage" class="text-sm text-slate-600 dark:text-slate-300">{{ syncMessage }}</p>
        <p v-if="lastSyncedAt" class="text-xs text-slate-500">
            Последняя синхронизация строки: {{ lastSyncedAt }}
        </p>

        <div class="flex flex-wrap items-end gap-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Тип</span>
                <select v-model="localFilters.type" :class="crmFieldFluid" class="min-w-[12rem]" @change="applyFilters">
                    <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Поиск</span>
                <input
                    v-model="localFilters.q"
                    type="search"
                    :class="crmFieldFluid"
                    class="min-w-[14rem]"
                    placeholder="номер, ИНН, название…"
                    @keydown.enter.prevent="applyFilters"
                >
            </label>
            <button type="button" :class="crmBtnNeutral" @click="applyFilters">Найти</button>
            <button
                type="button"
                :class="localFilters.unlinked ? crmPillActive : crmPill"
                @click="localFilters.unlinked = !localFilters.unlinked; applyFilters()"
            >
                Без заказа ({{ unlinkedCount }})
            </button>
        </div>

        <div :class="`${crmGridPanel} overflow-auto`">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/60">
                    <tr>
                        <th class="px-3 py-2">Тип</th>
                        <th class="px-3 py-2">№ ЭПД</th>
                        <th class="px-3 py-2">Дата</th>
                        <th class="px-3 py-2">Шаг</th>
                        <th class="px-3 py-2">ГО</th>
                        <th class="px-3 py-2">Перевозчик</th>
                        <th class="px-3 py-2">Заказ CRM</th>
                        <th class="px-3 py-2">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                        <td class="px-3 py-2 font-medium text-slate-900 dark:text-slate-100">
                            {{ row.document_type_label }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ row.epd_number || row.ib_number || '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ formatDate(row.epd_date) }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center gap-1">
                                {{ row.current_step || '—' }}
                                <span
                                    v-if="row.current_step_done"
                                    class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
                                >ok</span>
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <div>{{ row.shipper_name || '—' }}</div>
                            <div v-if="row.shipper_inn" class="text-xs text-slate-500">{{ row.shipper_inn }}</div>
                        </td>
                        <td class="px-3 py-2">
                            <div>{{ row.carrier_name || '—' }}</div>
                            <div v-if="row.carrier_inn" class="text-xs text-slate-500">{{ row.carrier_inn }}</div>
                        </td>
                        <td class="px-3 py-2">
                            <Link
                                v-if="row.order_id"
                                :href="route('orders.edit', row.order_id)"
                                class="font-medium text-sky-700 hover:underline dark:text-sky-300"
                            >
                                {{ row.order_number || `#${row.order_id}` }}
                            </Link>
                            <span v-else class="text-slate-400">не связан</span>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <button
                                v-if="!row.order_id"
                                type="button"
                                :class="crmBtnCreate"
                                class="!px-2 !py-1 text-xs"
                                @click="openLinkModal(row)"
                            >
                                Связать
                            </button>
                            <button
                                v-else
                                type="button"
                                :class="crmBtnNeutral"
                                class="!px-2 !py-1 text-xs"
                                @click="unlinkEntry(row)"
                            >
                                Отвязать
                            </button>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="8" class="px-3 py-8 text-center text-slate-500">
                            Пока пусто. Нажмите «Обновить из 1С» или дождитесь hourly sync.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="linkModal.show" max-width="lg" @close="closeLinkModal">
            <section :class="crmModalFormShell">
                <CrmModalHeader
                    eyebrow="Связь с заказом"
                    :title="linkModal.entry ? `${linkModal.entry.document_type_label} №${linkModal.entry.epd_number || linkModal.entry.ib_number || '—'}` : 'Связать'"
                    @close="closeLinkModal"
                />
                <div :class="`${crmModalFormBody} space-y-4 px-6 pb-6 pt-2`">
                    <div :class="crmModalFieldsWrap">
                        <div :class="`${crmModalFieldRow} crm-modal-field-row--wide flex-wrap`">
                            <label :class="crmModalFieldLabel">Номер заказа</label>
                            <input
                                v-model="linkModal.q"
                                type="search"
                                :class="crmFieldFluid"
                                placeholder="АС-…"
                                @keydown.enter.prevent="searchOrders"
                            >
                            <button type="button" :class="crmBtnSecondary" :disabled="linkModal.busy" @click="searchOrders">
                                Найти
                            </button>
                        </div>
                    </div>
                    <p v-if="linkModal.error" class="text-sm text-rose-600">{{ linkModal.error }}</p>
                    <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                        <li
                            v-for="order in linkModal.orders"
                            :key="order.id"
                            class="flex items-center justify-between gap-3 px-3 py-2"
                        >
                            <div>
                                <div class="font-medium">{{ order.order_number || `#${order.id}` }}</div>
                                <div class="text-xs text-slate-500">{{ order.customer_name || '—' }} · {{ formatDate(order.order_date) }}</div>
                            </div>
                            <button type="button" :class="crmBtnCreate" class="!px-2 !py-1 text-xs" :disabled="linkModal.busy" @click="linkOrder(order)">
                                Выбрать
                            </button>
                        </li>
                        <li v-if="linkModal.orders.length === 0" class="px-3 py-4 text-center text-sm text-slate-500">
                            Введите номер и нажмите «Найти».
                        </li>
                    </ul>
                </div>
            </section>
        </Modal>
    </div>
</template>

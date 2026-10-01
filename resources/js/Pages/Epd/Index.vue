<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import EpdGrid from '@/Components/Epd/EpdGrid.vue';
import Modal from '@/Components/Modal.vue';
import CrmModalHeader from '@/Components/Crm/CrmModalHeader.vue';
import {
    crmBtnCreate,
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
        default: () => ({ type: 'all', unlinked: false, q: '', order_id: null }),
    },
    typeOptions: { type: Array, default: () => [] },
    canSync: { type: Boolean, default: false },
    lastSyncedAt: { type: String, default: null },
    epdPilot: { type: Object, default: null },
});

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');

const localFilters = reactive({
    type: props.filters?.type || 'all',
    unlinked: Boolean(props.filters?.unlinked),
});

/** documents = плоский реестр 1С; by_order = заказ → документы */
const viewMode = ref('by_order');

watch(
    () => props.filters,
    (value) => {
        localFilters.type = value?.type || 'all';
        localFilters.unlinked = Boolean(value?.unlinked);
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
        order_id: props.filters?.order_id || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function setTypeFilter(value) {
    localFilters.type = value;
    applyFilters();
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

function openOrder(row) {
    if (!row?.order_id) {
        return;
    }
    window.open(route('orders.edit', row.order_id), '_blank', 'noopener,noreferrer');
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
            lead="Реестр ЭПД из 1С: фильтры в заголовках, колонки и представления — как у Лидов/Заказов. Режим «По заказам» раскрывает документы внутри заказа."
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

        <p
            v-if="epdPilot?.enabled"
            class="rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
        >
            Пилот: только тестовая ИБ
            <span class="font-semibold">{{ epdPilot.publication_label || 'sandbox' }}</span>.
        </p>
        <p v-if="syncMessage" class="text-sm text-slate-600 dark:text-slate-300">{{ syncMessage }}</p>
        <p v-if="lastSyncedAt" class="text-xs text-slate-500">
            Последняя синхронизация: {{ lastSyncedAt }}
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                :class="viewMode === 'by_order' ? crmPillActive : crmPill"
                @click="viewMode = 'by_order'"
            >
                По заказам
            </button>
            <button
                type="button"
                :class="viewMode === 'documents' ? crmPillActive : crmPill"
                @click="viewMode = 'documents'"
            >
                Плоский реестр ({{ rows.length }})
            </button>
            <button
                type="button"
                :class="localFilters.unlinked ? crmPillActive : crmPill"
                @click="localFilters.unlinked = !localFilters.unlinked; applyFilters()"
            >
                Без заказа ({{ unlinkedCount }})
            </button>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="opt in typeOptions"
                :key="opt.value"
                type="button"
                :class="localFilters.type === opt.value ? crmPillActive : crmPill"
                @click="setTypeFilter(opt.value)"
            >
                {{ opt.label }}
            </button>
        </div>

        <div :class="crmGridPanel">
            <EpdGrid
                :rows="rows"
                :user-id="userId"
                :view-mode="viewMode"
                @link-request="openLinkModal"
                @unlink-request="unlinkEntry"
                @open-order="openOrder"
            />
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
                    <ul class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                        <li
                            v-for="order in linkModal.orders"
                            :key="order.id"
                            class="flex items-center justify-between gap-3 px-3 py-2"
                        >
                            <div>
                                <div class="font-medium">{{ order.order_number || `#${order.id}` }}</div>
                                <div class="text-xs text-zinc-500">{{ order.customer_name || '—' }} · {{ formatDate(order.order_date) }}</div>
                            </div>
                            <button type="button" :class="crmBtnCreate" class="!px-2 !py-1 text-xs" :disabled="linkModal.busy" @click="linkOrder(order)">
                                Выбрать
                            </button>
                        </li>
                        <li v-if="linkModal.orders.length === 0" class="px-3 py-4 text-center text-sm text-zinc-500">
                            Введите номер и нажмите «Найти».
                        </li>
                    </ul>
                </div>
            </section>
        </Modal>
    </div>
</template>

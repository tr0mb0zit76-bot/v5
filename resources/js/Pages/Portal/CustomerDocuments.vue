<script setup>
import PortalCarrierDocuments from '@/Components/Portal/PortalCarrierDocuments.vue';
import PortalOutgoingDocuments from '@/Components/Portal/PortalOutgoingDocuments.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({
    layout: (h, page) => h(GuestLayout, { wide: true }, () => page),
});

const props = defineProps({
    status: { type: String, required: true },
    link_validity_hint: { type: String, default: '' },
    unloading_actual: { type: String, default: null },
    can_upload_documents: { type: Boolean, default: false },
    trip_status: { type: Object, default: () => ({ code: 'new', label: 'Новый заказ' }) },
    route_milestones: { type: Array, default: () => [] },
    order: { type: Object, required: true },
    customer: { type: Object, required: true },
    portal_token: { type: String, required: true },
    document_slots: { type: Array, default: () => [] },
    outgoing_documents: { type: Array, default: () => [] },
    document_upload_limits: { type: Object, default: () => ({}) },
    traklo_apk_url: { type: String, default: '/downloads/traklo.apk' },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? null);
const documentUploadHint = computed(() => props.document_upload_limits?.hint_ru ?? '');
const isClosed = computed(() => props.status === 'closed');
const documentsReadonly = computed(() => !props.can_upload_documents);
const statusLabel = computed(() => props.trip_status?.label || props.order?.status_label || '—');

function formatPointDates(point) {
    const parts = [];
    if (point.planned_date) {
        parts.push(`план ${point.planned_date}`);
    }
    if (point.actual_date) {
        parts.push(`факт ${point.actual_date}`);
    }

    return parts.join(' · ');
}
</script>

<template>
    <Head :title="`Перевозка — заказ ${order.order_number}`" />

    <div class="space-y-6">
        <div>
            <h1 class="text-lg font-semibold text-zinc-900">Перевозка</h1>
            <p class="mt-1 text-sm text-zinc-600">
                Заказ <span class="font-medium">{{ order.order_number }}</span>
            </p>
            <p class="mt-1 text-sm text-zinc-500">
                {{ customer.name }}<span v-if="customer.inn"> · ИНН {{ customer.inn }}</span>
            </p>
            <p v-if="link_validity_hint" class="mt-2 text-xs text-zinc-500">{{ link_validity_hint }}</p>
        </div>

        <div
            v-if="flash?.message"
            class="rounded-xl border px-3 py-2 text-sm"
            :class="flash.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
        >
            {{ flash.message }}
        </div>

        <div
            v-if="isClosed"
            class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
        >
            <p class="font-medium">Ссылка закрыта</p>
            <p class="mt-1">
                Перевозка завершена<template v-if="unloading_actual"> (выгрузка {{ unloading_actual }})</template>.
                Обратитесь к менеджеру, если нужно что-то изменить.
            </p>
        </div>

        <section class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-800">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-medium text-zinc-900">Статус рейса</h2>
                <span class="font-semibold text-zinc-900">{{ statusLabel }}</span>
            </div>
            <p v-if="order.loading_date || order.unloading_date" class="mt-2 text-zinc-600">
                <span v-if="order.loading_date">Погрузка: {{ order.loading_date }}</span>
                <span v-if="order.loading_date && order.unloading_date"> · </span>
                <span v-if="order.unloading_date">Выгрузка: {{ order.unloading_date }}</span>
            </p>
        </section>

        <section v-if="route_milestones.length" class="space-y-2">
            <h2 class="text-sm font-medium text-zinc-800">Маршрут</h2>
            <ul class="space-y-2 text-sm text-zinc-600">
                <li
                    v-for="(point, index) in route_milestones"
                    :key="index"
                    class="rounded-lg border border-zinc-100 bg-white px-3 py-2"
                >
                    <div class="font-medium text-zinc-800">{{ point.title }}</div>
                    <div v-if="point.address">{{ point.address }}</div>
                    <div v-if="formatPointDates(point)" class="text-xs text-zinc-500">
                        {{ formatPointDates(point) }}
                    </div>
                </li>
            </ul>
        </section>

        <PortalOutgoingDocuments
            :documents="outgoing_documents"
            heading="Документы от нас"
            hint="Счета, заявки и закрывающие документы для скачивания."
            :show-empty="true"
            empty-text="Пока нет файлов от нас. Когда менеджер отправит документы, они появятся здесь."
        />

        <PortalCarrierDocuments
            :portal-token="portal_token"
            :document-slots="document_slots"
            :readonly="documentsReadonly"
            :document-upload-hint="documentUploadHint"
            heading="Ваши документы"
            hint="Загрузите сканы или фото по списку ниже. После выгрузки ссылка закроется."
            upload-route-name="portal.customer.documents.store"
        />

        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            <p class="font-medium">Мобильное приложение Traklo</p>
            <p class="mt-1 text-sky-800">
                Для постоянного доступа к заказам и чату с менеджером установите приложение.
            </p>
            <a
                :href="traklo_apk_url"
                class="mt-3 inline-flex rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800"
            >
                Скачать Traklo
            </a>
        </div>
    </div>
</template>

<script setup>
import PortalCarrierDocuments from '@/Components/Portal/PortalCarrierDocuments.vue';
import PortalCarrierFleetDocuments from '@/Components/Portal/PortalCarrierFleetDocuments.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({
    layout: (h, page) => h(GuestLayout, { wide: true }, () => page),
});

const props = defineProps({
    status: { type: String, required: true },
    link_validity_hint: { type: String, default: '' },
    unloading_actual: { type: String, default: null },
    can_upload_documents: { type: Boolean, default: false },
    can_submit_fleet_form: { type: Boolean, default: false },
    submitted_at: { type: String, default: null },
    submission: { type: Object, default: null },
    order: { type: Object, required: true },
    carrier: { type: Object, required: true },
    leg: { type: Object, required: true },
    route_summary: { type: Array, default: () => [] },
    form_defaults: { type: Object, default: () => ({}) },
    portal_token: { type: String, required: true },
    document_slots: { type: Array, default: () => [] },
    fleet_document_sections: { type: Array, default: () => [] },
    document_upload_limits: { type: Object, default: () => ({}) },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? null);
const documentUploadHint = computed(() => props.document_upload_limits?.hint_ru ?? '');

const isSubmitted = computed(() => props.status === 'submitted');
const isClosed = computed(() => props.status === 'closed');
const documentsReadonly = computed(() => !props.can_upload_documents);

const form = useForm({
    tractor_plate: props.form_defaults.tractor_plate ?? '',
    trailer_plate: props.form_defaults.trailer_plate ?? '',
    tractor_brand: props.form_defaults.tractor_brand ?? '',
    trailer_brand: props.form_defaults.trailer_brand ?? '',
    driver_full_name: props.form_defaults.driver_full_name ?? '',
    driver_phone: props.form_defaults.driver_phone ?? '',
    driver_license: props.form_defaults.driver_license ?? '',
    comment: props.form_defaults.comment ?? '',
});

const fleetIdentity = computed(() => ({
    tractor_plate: form.tractor_plate,
    trailer_plate: form.trailer_plate,
    tractor_brand: form.tractor_brand,
    trailer_brand: form.trailer_brand,
    driver_full_name: form.driver_full_name,
    driver_phone: form.driver_phone,
    driver_license: form.driver_license,
}));

function submit() {
    form.post(route('portal.carrier.store', { token: props.portal_token }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Данные перевозчика — заказ ${order.order_number}`" />

    <div class="space-y-6">
        <div>
            <h1 class="text-lg font-semibold text-zinc-900">Данные по перевозке</h1>
            <p class="mt-1 text-sm text-zinc-600">
                Заказ <span class="font-medium">{{ order.order_number }}</span>
                · {{ leg.label }}
                <span v-if="leg.carrier_slot > 1"> · исполнитель {{ leg.carrier_slot }}</span>
            </p>
            <p class="mt-1 text-sm text-zinc-500">{{ carrier.name }}<span v-if="carrier.inn"> · ИНН {{ carrier.inn }}</span></p>
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
                Фактическая дата выгрузки проставлена<template v-if="unloading_actual"> ({{ unloading_actual }})</template>.
                Обратитесь к менеджеру, если нужно что-то изменить.
            </p>
        </div>

        <div v-if="order.loading_date || order.unloading_date" class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-700">
            <span v-if="order.loading_date">Погрузка: {{ order.loading_date }}</span>
            <span v-if="order.loading_date && order.unloading_date"> · </span>
            <span v-if="order.unloading_date">Выгрузка: {{ order.unloading_date }}</span>
        </div>

        <div v-if="route_summary.length" class="space-y-2">
            <h2 class="text-sm font-medium text-zinc-800">Маршрут</h2>
            <ul class="space-y-2 text-sm text-zinc-600">
                <li
                    v-for="(point, index) in route_summary"
                    :key="index"
                    class="rounded-lg border border-zinc-100 px-3 py-2"
                >
                    <div class="font-medium text-zinc-800">{{ point.title }}</div>
                    <div v-if="point.address">{{ point.address }}</div>
                    <div v-if="point.planned_date" class="text-xs text-zinc-500">{{ point.planned_date }}</div>
                </li>
            </ul>
        </div>

        <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
            <PortalCarrierDocuments
                :portal-token="portal_token"
                :document-slots="document_slots"
                :readonly="documentsReadonly"
                :document-upload-hint="documentUploadHint"
            />

            <div class="space-y-4">
                <template v-if="isSubmitted">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        <p class="font-medium">Данные по ТС и водителю отправлены</p>
                        <p v-if="submitted_at" class="mt-1 text-emerald-800">{{ submitted_at }}</p>
                        <p v-if="can_upload_documents" class="mt-2 text-emerald-800">
                            Можно продолжить загружать закрывающие документы слева.
                        </p>
                    </div>
                    <dl v-if="submission" class="grid gap-3 text-sm">
                        <div v-if="submission.tractor_plate || submission.trailer_plate">
                            <dt class="text-zinc-500">ТС</dt>
                            <dd class="font-medium">{{ [submission.tractor_plate, submission.trailer_plate].filter(Boolean).join(' / ') }}</dd>
                        </div>
                        <div v-if="submission.driver_full_name">
                            <dt class="text-zinc-500">Водитель</dt>
                            <dd class="font-medium">{{ submission.driver_full_name }}</dd>
                        </div>
                        <div v-if="submission.driver_phone">
                            <dt class="text-zinc-500">Телефон</dt>
                            <dd>{{ submission.driver_phone }}</dd>
                        </div>
                        <div v-if="submission.comment">
                            <dt class="text-zinc-500">Комментарий</dt>
                            <dd class="whitespace-pre-wrap">{{ submission.comment }}</dd>
                        </div>
                    </dl>
                </template>

                <form v-else-if="can_submit_fleet_form" class="space-y-4 rounded-xl border border-zinc-200 bg-white p-4" @submit.prevent="submit">
                    <h2 class="text-sm font-semibold text-zinc-900">Транспорт и водитель</h2>

                    <p v-if="form.errors.documents" class="text-xs text-rose-600">{{ form.errors.documents }}</p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-600">Госномер тягача</label>
                            <input v-model="form.tractor_plate" type="text" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm" />
                            <p v-if="form.errors.tractor_plate" class="text-xs text-rose-600">{{ form.errors.tractor_plate }}</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-600">Госномер прицепа</label>
                            <input v-model="form.trailer_plate" type="text" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-600">Марка тягача</label>
                            <input v-model="form.tractor_brand" type="text" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-600">Марка прицепа</label>
                            <input v-model="form.trailer_brand" type="text" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm" />
                        </div>
                    </div>

                    <PortalCarrierFleetDocuments
                        :portal-token="portal_token"
                        :sections="fleet_document_sections"
                        :readonly="documentsReadonly"
                        :fleet-identity="fleetIdentity"
                    />

                    <div class="space-y-1">
                        <label class="text-xs font-medium text-zinc-600">ФИО водителя *</label>
                        <input v-model="form.driver_full_name" type="text" required class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm" />
                        <p v-if="form.errors.driver_full_name" class="text-xs text-rose-600">{{ form.errors.driver_full_name }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-600">Телефон водителя</label>
                            <input v-model="form.driver_phone" type="tel" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-600">Номер водительского удостоверения</label>
                            <input v-model="form.driver_license" type="text" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-medium text-zinc-600">Комментарий</label>
                        <textarea v-model="form.comment" rows="3" class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm" />
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Отправка…' : 'Отправить данные' }}
                    </button>
                </form>

                <div v-else-if="!isClosed && isSubmitted" class="space-y-4">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700">
                        Данные по ТС и водителю уже отправлены. Закрывающие документы можно загрузить слева.
                    </div>
                    <PortalCarrierFleetDocuments
                        v-if="can_upload_documents && fleet_document_sections.length"
                        :portal-token="portal_token"
                        :sections="fleet_document_sections"
                        :readonly="documentsReadonly"
                        :fleet-identity="submission ?? form_defaults"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

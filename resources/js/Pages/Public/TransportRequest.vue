<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    submitted: {
        type: Boolean,
        default: false,
    },
    traklo_apk_url: {
        type: String,
        default: '/downloads/traklo.apk',
    },
});

const form = useForm({
    company_name: '',
    contact_name: '',
    phone: '',
    email: '',
    loading_location: '',
    unloading_location: '',
    cargo: '',
    planned_shipping_date: '',
    comment: '',
    website: '',
});

function submit() {
    form.post(route('public.transport-request.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <div class="min-h-screen bg-slate-950 px-5 py-8 text-slate-50">
        <Head title="Заявка на перевозку — Traklo" />

        <main class="mx-auto w-full max-w-2xl">
            <div class="mb-6 rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                <div class="text-xs font-semibold uppercase tracking-[0.35em] text-sky-300">Traklo</div>
                <h1 class="mt-3 text-3xl font-semibold">Заявка на перевозку</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300">
                    Оставьте маршрут и параметры груза. Менеджер «Автоальянс-Смоленск» свяжется с вами и уточнит детали расчёта.
                </p>
            </div>

            <div v-if="props.submitted" class="mb-5 rounded-3xl border border-emerald-400/30 bg-emerald-400/10 p-4 text-sm leading-6 text-emerald-100">
                Заявка принята. Мы увидим её в CRM и вернёмся с уточнениями.
            </div>

            <form class="space-y-4 rounded-3xl border border-white/10 bg-white/[0.04] p-5" @submit.prevent="submit">
                <input v-model="form.website" type="text" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />

                <div>
                    <label class="text-sm font-medium text-slate-200">Компания</label>
                    <input
                        v-model="form.company_name"
                        type="text"
                        class="mt-1 w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-base outline-none focus:border-sky-500"
                        placeholder="Название организации"
                    />
                    <InputError class="mt-2" :message="form.errors.company_name" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-200">Контактное лицо *</label>
                        <input
                            v-model="form.contact_name"
                            type="text"
                            required
                            class="mt-1 w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-base outline-none focus:border-sky-500"
                            placeholder="Имя"
                        />
                        <InputError class="mt-2" :message="form.errors.contact_name" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-200">Телефон *</label>
                        <input
                            v-model="form.phone"
                            type="tel"
                            required
                            class="mt-1 w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-base outline-none focus:border-sky-500"
                            placeholder="+7..."
                        />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-200">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="mt-1 w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-base outline-none focus:border-sky-500"
                        placeholder="name@company.ru"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-200">Откуда *</label>
                        <input
                            v-model="form.loading_location"
                            type="text"
                            required
                            class="mt-1 w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-base outline-none focus:border-sky-500"
                            placeholder="Город, страна"
                        />
                        <InputError class="mt-2" :message="form.errors.loading_location" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-200">Куда *</label>
                        <input
                            v-model="form.unloading_location"
                            type="text"
                            required
                            class="mt-1 w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-base outline-none focus:border-sky-500"
                            placeholder="Город, страна"
                        />
                        <InputError class="mt-2" :message="form.errors.unloading_location" />
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-200">Груз</label>
                    <textarea
                        v-model="form.cargo"
                        rows="3"
                        class="mt-1 w-full resize-none rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-base outline-none focus:border-sky-500"
                        placeholder="Что везём, вес, объём, особенности"
                    />
                    <InputError class="mt-2" :message="form.errors.cargo" />
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-200">Желаемая дата погрузки</label>
                    <input
                        v-model="form.planned_shipping_date"
                        type="date"
                        class="mt-1 w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-base outline-none focus:border-sky-500"
                    />
                    <InputError class="mt-2" :message="form.errors.planned_shipping_date" />
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-200">Комментарий</label>
                    <textarea
                        v-model="form.comment"
                        rows="3"
                        class="mt-1 w-full resize-none rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-base outline-none focus:border-sky-500"
                        placeholder="Сроки, условия оплаты, требования к машине или документам"
                    />
                    <InputError class="mt-2" :message="form.errors.comment" />
                </div>

                <button
                    type="submit"
                    class="w-full rounded-2xl bg-sky-600 px-5 py-3 text-base font-semibold text-white transition active:bg-sky-500 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Отправляем...' : 'Отправить заявку' }}
                </button>
            </form>

            <div class="mt-6 rounded-3xl border border-white/10 bg-white/[0.04] p-5 text-sm text-slate-300">
                <p class="font-medium text-slate-100">Уже работаете с нами?</p>
                <p class="mt-2 leading-6">
                    Установите мобильное приложение Traklo — заказы, документы и чат с менеджером в одном месте.
                </p>
                <a
                    :href="traklo_apk_url"
                    class="mt-4 inline-flex rounded-2xl border border-sky-400/40 px-4 py-2 text-sm font-semibold text-sky-200 hover:bg-sky-400/10"
                >
                    Скачать Traklo
                </a>
            </div>
        </main>
    </div>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: {
        type: String,
        required: true,
    },
    contact_name: {
        type: String,
        default: null,
    },
    email: {
        type: String,
        default: null,
    },
    contractor_name: {
        type: String,
        default: null,
    },
    external_party_label: {
        type: String,
        default: null,
    },
    traklo_apk_url: {
        type: String,
        default: '/downloads/traklo.apk',
    },
    expires_at: {
        type: String,
        default: null,
    },
});

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('external.invite.store', { token: props.token }), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Доступ в Traklo" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-slate-950 px-4 py-10 text-slate-100">
        <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-xl">
            <h1 class="text-xl font-semibold">Доступ в Traklo</h1>
            <p v-if="contractor_name" class="mt-2 text-sm text-slate-400">{{ contractor_name }}</p>
            <p v-if="contact_name || email" class="mt-1 text-sm text-slate-300">
                {{ contact_name || email }}
                <span v-if="external_party_label" class="text-slate-500"> · {{ external_party_label }}</span>
            </p>

            <p class="mt-4 text-sm text-slate-400">
                Задайте пароль для входа в мобильное приложение Traklo.
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Пароль</label>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"
                        required
                    />
                    <InputError :message="form.errors.password" class="mt-1" />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Повтор пароля</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"
                        required
                    />
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Войти в Traklo
                </button>
            </form>

            <div class="mt-6 border-t border-slate-800 pt-4 text-sm text-slate-400">
                <p>Ещё нет приложения?</p>
                <a :href="traklo_apk_url" class="mt-1 inline-block font-medium text-emerald-400 hover:text-emerald-300">
                    Скачать Traklo (APK)
                </a>
            </div>
        </div>
    </div>
</template>

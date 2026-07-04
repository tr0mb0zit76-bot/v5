<script setup>
import InputError from '@/Components/InputError.vue';
import { getDeviceName } from '@/support/mobileDevice';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    deviceKey: {
        type: String,
        required: true,
    },
});

const form = useForm({
    device_key: props.deviceKey,
    device_name: getDeviceName(),
    pin: '',
    pin_confirmation: '',
});

const submit = () => {
    form.post(route('mobile.pin-setup.store'));
};
</script>

<template>
    <div class="flex min-h-screen bg-zinc-950 px-5 py-8 text-zinc-50">
        <Head title="PIN для быстрого входа" />

        <main class="mx-auto flex w-full max-w-md flex-col justify-center">
            <div class="mb-8">
                <div class="text-xs font-semibold uppercase tracking-[0.35em] text-sky-300">CRM</div>
                <h1 class="mt-3 text-3xl font-semibold">Быстрый PIN</h1>
                <p class="mt-2 text-sm leading-6 text-zinc-400">
                    Задайте 4–6 цифр для разблокировки на этом телефоне, если сессия CRM истечёт.
                    Полный вход email+пароль по-прежнему нужен при первом входе или смене аккаунта.
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="pin" class="text-sm font-medium text-zinc-200">PIN</label>
                    <input
                        id="pin"
                        v-model="form.pin"
                        type="password"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        required
                        autofocus
                        autocomplete="new-password"
                        class="mt-1 w-full border border-white/10 bg-white/5 px-3 py-3 text-center text-2xl tracking-[0.5em] text-zinc-50 outline-none focus:border-sky-500"
                    />
                    <InputError class="mt-2" :message="form.errors.pin" />
                </div>

                <div>
                    <label for="pin_confirmation" class="text-sm font-medium text-zinc-200">Повтор PIN</label>
                    <input
                        id="pin_confirmation"
                        v-model="form.pin_confirmation"
                        type="password"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        required
                        autocomplete="new-password"
                        class="mt-1 w-full border border-white/10 bg-white/5 px-3 py-3 text-center text-2xl tracking-[0.5em] text-zinc-50 outline-none focus:border-sky-500"
                    />
                </div>

                <button
                    type="submit"
                    class="w-full bg-sky-600 px-4 py-3 text-base font-semibold text-white transition hover:bg-sky-500 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Сохраняем...' : 'Сохранить PIN и открыть чаты' }}
                </button>
            </form>
        </main>
    </div>
</template>

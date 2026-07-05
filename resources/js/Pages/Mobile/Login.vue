<script setup>
import InputError from '@/Components/InputError.vue';
import MobileAppUpdateBanner from '@/Components/Mobile/MobileAppUpdateBanner.vue';
import { getDeviceName, getOrCreateDeviceKey } from '@/support/mobileDevice';
import axios from 'axios';
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

defineProps({
    status: {
        type: String,
        default: null,
    },
});

const deviceKey = getOrCreateDeviceKey();
const pinUnlockAvailable = ref(false);
const registeredUserName = ref('');
const mode = ref('checking');

const passwordForm = useForm({
    email: '',
    password: '',
    remember: true,
    device_key: deviceKey,
    device_name: getDeviceName(),
});

const pinForm = useForm({
    device_key: deviceKey,
    pin: '',
});

async function detectPinUnlock() {
    mode.value = 'checking';

    try {
        const { data } = await axios.post(route('mobile.device.check'), {
            device_key: deviceKey,
        });

        pinUnlockAvailable.value = data.registered === true;
        registeredUserName.value = data.user_name ?? '';

        mode.value = pinUnlockAvailable.value ? 'pin' : 'password';
    } catch {
        mode.value = 'password';
    }
}

const submitPassword = () => {
    passwordForm.post(route('mobile.login.store'), {
        onFinish: () => passwordForm.reset('password'),
    });
};

const submitPin = () => {
    pinForm.post(route('mobile.pin-unlock'), {
        onFinish: () => pinForm.reset('pin'),
    });
};

function usePasswordLogin() {
    mode.value = 'password';
    pinForm.clearErrors();
}

onMounted(() => {
    detectPinUnlock();
});
</script>

<template>
    <div class="flex min-h-screen bg-zinc-950 px-5 py-8 text-zinc-50">
        <Head title="Traklo" />
        <MobileAppUpdateBanner />

        <main class="mx-auto flex w-full max-w-md flex-col justify-center">
            <div class="mb-8">
                <div class="text-xs font-semibold uppercase tracking-[0.35em] text-sky-300">TRAKLO</div>
                <h1 class="mt-3 text-3xl font-semibold">Traklo</h1>
                <p v-if="mode === 'pin'" class="mt-2 text-sm leading-6 text-zinc-400">
                    Сессия истекла. Введите PIN для {{ registeredUserName || 'вашего аккаунта' }}.
                </p>
                <p v-else class="mt-2 text-sm leading-6 text-zinc-400">
                    Рабочая лента логиста: чаты, заказы, документы и задачи в одном мобильном приложении.
                </p>
            </div>

            <div v-if="status" class="mb-4 border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-sm text-sky-100">
                {{ status }}
            </div>

            <div v-if="mode === 'checking'" class="py-8 text-center text-sm text-zinc-500">
                Проверяем устройство…
            </div>

            <form v-else-if="mode === 'pin'" class="space-y-4" @submit.prevent="submitPin">
                <div>
                    <label for="pin" class="text-sm font-medium text-zinc-200">PIN-код</label>
                    <input
                        id="pin"
                        v-model="pinForm.pin"
                        type="password"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        class="mt-1 w-full border border-white/10 bg-white/5 px-3 py-3 text-center text-2xl tracking-[0.5em] text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                        placeholder="••••"
                    />
                    <InputError class="mt-2" :message="pinForm.errors.pin" />
                    <InputError class="mt-2" :message="pinForm.errors.device_key" />
                </div>

                <button
                    type="submit"
                    class="w-full bg-sky-600 px-4 py-3 text-base font-semibold text-white transition hover:bg-sky-500 disabled:opacity-60"
                    :disabled="pinForm.processing"
                >
                    {{ pinForm.processing ? 'Входим...' : 'Разблокировать' }}
                </button>

                <button
                    type="button"
                    class="w-full px-4 py-3 text-sm text-zinc-400 underline-offset-4 hover:text-zinc-200 hover:underline"
                    @click="usePasswordLogin"
                >
                    Войти email и паролем
                </button>
            </form>

            <form v-else class="space-y-4" @submit.prevent="submitPassword">
                <div>
                    <label for="email" class="text-sm font-medium text-zinc-200">Email</label>
                    <input
                        id="email"
                        v-model="passwordForm.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        class="mt-1 w-full border border-white/10 bg-white/5 px-3 py-3 text-base text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                        placeholder="name@company.ru"
                    />
                    <InputError class="mt-2" :message="passwordForm.errors.email" />
                </div>

                <div>
                    <label for="password" class="text-sm font-medium text-zinc-200">Пароль</label>
                    <input
                        id="password"
                        v-model="passwordForm.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="mt-1 w-full border border-white/10 bg-white/5 px-3 py-3 text-base text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                        placeholder="Пароль"
                    />
                    <InputError class="mt-2" :message="passwordForm.errors.password" />
                </div>

                <label class="flex items-center gap-3 text-sm text-zinc-300">
                    <input
                        v-model="passwordForm.remember"
                        type="checkbox"
                        class="border-white/20 bg-white/5 text-sky-600 focus:ring-sky-500"
                    />
                    Запомнить вход на телефоне
                </label>

                <button
                    type="submit"
                    class="w-full bg-sky-600 px-4 py-3 text-base font-semibold text-white transition hover:bg-sky-500 disabled:opacity-60"
                    :disabled="passwordForm.processing"
                >
                    {{ passwordForm.processing ? 'Входим...' : 'Войти' }}
                </button>
            </form>
        </main>
    </div>
</template>

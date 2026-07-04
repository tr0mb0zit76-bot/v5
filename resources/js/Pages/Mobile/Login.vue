<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post(route('mobile.login.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="flex min-h-screen bg-zinc-950 px-5 py-8 text-zinc-50">
        <Head title="Автоальянс Чат" />

        <main class="mx-auto flex w-full max-w-md flex-col justify-center">
            <div class="mb-8">
                <div class="text-xs font-semibold uppercase tracking-[0.35em] text-sky-300">CRM</div>
                <h1 class="mt-3 text-3xl font-semibold">Автоальянс Чат</h1>
                <p class="mt-2 text-sm leading-6 text-zinc-400">
                    Войдите рабочим email и паролем. После входа приложение откроет мобильный мессенджер.
                </p>
            </div>

            <div v-if="status" class="mb-4 border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-sm text-sky-100">
                {{ status }}
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="email" class="text-sm font-medium text-zinc-200">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        class="mt-1 w-full border border-white/10 bg-white/5 px-3 py-3 text-base text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                        placeholder="name@company.ru"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <label for="password" class="text-sm font-medium text-zinc-200">Пароль</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="mt-1 w-full border border-white/10 bg-white/5 px-3 py-3 text-base text-zinc-50 outline-none placeholder:text-zinc-500 focus:border-sky-500"
                        placeholder="Пароль"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <label class="flex items-center gap-3 text-sm text-zinc-300">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="border-white/20 bg-white/5 text-sky-600 focus:ring-sky-500"
                    />
                    Запомнить вход на телефоне
                </label>

                <button
                    type="submit"
                    class="w-full bg-sky-600 px-4 py-3 text-base font-semibold text-white transition hover:bg-sky-500 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Входим...' : 'Войти' }}
                </button>
            </form>
        </main>
    </div>
</template>

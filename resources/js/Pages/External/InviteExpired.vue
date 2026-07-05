<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Ссылка недействительна" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-slate-950 px-4 py-10 text-slate-100">
        <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 text-center shadow-xl">
            <h1 class="text-xl font-semibold">Ссылка недействительна</h1>
            <p class="mt-3 text-sm text-slate-400">
                Приглашение истекло или уже использовано. Попросите менеджера отправить новую ссылку.
            </p>
            <Link
                :href="route('mobile.login')"
                class="mt-6 inline-block rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-slate-100 hover:bg-slate-700"
            >
                Перейти ко входу
            </Link>
        </div>
    </div>
</template>

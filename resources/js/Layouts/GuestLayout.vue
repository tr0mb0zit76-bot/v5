<script setup>
import { Link } from '@inertiajs/vue3';

const lightLogoUrl = '/assets/logo_black.png';
const primaryLogoUrl = '/assets/logo.png';
</script>

<template>
    <div class="min-h-screen bg-zinc-100 text-zinc-900">
        <div class="flex min-h-screen flex-col lg:flex-row">
            <section class="flex flex-1 items-center justify-center px-6 py-10 sm:px-10 lg:px-14">
                <div class="w-full max-w-md">
                    <Link href="/" class="mb-8 inline-flex items-center">
                        <img
                            :src="lightLogoUrl"
                            alt="Логистические Решения"
                            class="h-12 w-auto sm:h-14"
                        />
                    </Link>

                    <div class="border border-zinc-200 bg-white px-6 py-6 shadow-sm sm:px-8 sm:py-8">
                        <slot />
                    </div>
                </div>
            </section>

            <aside class="flex min-h-[260px] w-full flex-col justify-between bg-[#ff8a1e] px-8 py-10 text-white lg:min-h-screen lg:w-[34%] lg:px-12 lg:py-14">
                <div class="space-y-8">
                    <img
                        :src="primaryLogoUrl"
                        alt="Логистические Решения"
                        class="h-14 w-auto"
                    />

                    <div class="space-y-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/75">
                            Кабинет клиента
                        </p>
                        <h1 class="max-w-sm text-3xl font-semibold leading-tight sm:text-4xl">
                            Войдите в рабочее пространство логистики
                        </h1>
                        <p class="max-w-sm text-sm leading-6 text-white/85 sm:text-base">
                            Управляйте заказами, документами и коммуникацией с командой из одного кабинета.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 text-sm text-white/88">
                    <div class="border-t border-white/20 pt-4">
                        Экспедиторские заявки, контроль маршрутов и документы в одном интерфейсе.
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>

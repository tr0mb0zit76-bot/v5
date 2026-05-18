<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Версия для сброса кэша браузера после замены файла в public/assets */
const logoUrl = '/assets/logo_black.png?v=2';

const page = usePage();
/** Полный URL витрины с бэкенда — Inertia Link на другой хост не срабатывает */
const showcaseHomeUrl = computed(() => page.props.showcase_home_url ?? '/');
</script>

<template>
    <div class="min-h-dvh bg-zinc-100 text-zinc-900">
        <div class="mx-auto flex min-h-dvh w-full max-w-6xl items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
            <section class="w-full max-w-md">
                <a
                    :href="showcaseHomeUrl"
                    class="mb-8 block w-full rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 focus-visible:ring-offset-2"
                >
                    <img
                        :src="logoUrl"
                        alt="Автоальянс Смоленск"
                        class="h-auto w-full object-contain"
                    />
                </a>

                <div class="border border-zinc-200 bg-white px-6 py-6 shadow-sm sm:px-8 sm:py-8">
                    <slot />
                </div>
            </section>
        </div>
    </div>
</template>

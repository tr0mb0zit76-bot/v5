<script setup>
import { Link } from '@inertiajs/vue3';

const lightLogoUrl = '/assets/logo_black.png';
</script>

<template>
    <div class="min-h-dvh bg-zinc-100 text-zinc-900">
        <div class="mx-auto flex min-h-dvh w-full max-w-6xl items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
            <section class="w-full max-w-md">
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
            </section>
        </div>
    </div>
</template>

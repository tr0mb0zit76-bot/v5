<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto lg:min-h-0">
        <div class="shrink-0 space-y-1">
            <h1 class="text-2xl font-semibold">Справочники</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Централизованное управление общими классификаторами системы.
            </p>
        </div>

        <div class="grid min-h-0 grid-cols-1 gap-4 xl:grid-cols-[minmax(0,340px)_minmax(0,1fr)]">
            <aside class="border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="space-y-2">
                    <button
                        v-for="dictionary in dictionaries"
                        :key="dictionary.key"
                        type="button"
                        class="flex w-full items-start justify-between gap-3 border px-3 py-3 text-left transition-colors"
                        :class="activeDictionary?.key === dictionary.key
                            ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-500 dark:bg-zinc-800'
                            : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/70'"
                        @click="activeKey = dictionary.key"
                    >
                        <div class="space-y-1">
                            <div class="text-sm font-medium">{{ dictionary.title }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ dictionary.description }}</div>
                        </div>
                        <div class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">{{ dictionary.items.length }}</div>
                    </button>
                </div>
            </aside>

            <section class="flex min-h-0 flex-col border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <template v-if="activeDictionary">
                    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div class="space-y-1">
                            <h2 class="text-lg font-semibold">{{ activeDictionary.title }}</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ activeDictionary.description }}</p>
                        </div>

                        <form
                            v-if="activeDictionary.key === 'contractor-activity-types'"
                            class="flex w-full gap-2 md:max-w-md"
                            @submit.prevent="submitActivityType"
                        >
                            <input
                                v-model="activityTypeForm.name"
                                type="text"
                                placeholder="Новый вид деятельности"
                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                            />
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center border border-zinc-200 px-3 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                :disabled="activityTypeForm.processing"
                            >
                                {{ activityTypeForm.processing ? 'Сохранение...' : 'Добавить' }}
                            </button>
                        </form>

                        <form
                            v-else-if="activeDictionary.key === 'currencies'"
                            class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end md:max-w-xl"
                            @submit.prevent="submitCurrency"
                        >
                            <div class="flex w-full flex-1 flex-col gap-1 sm:max-w-[7rem]">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Код ISO</label>
                                <input
                                    v-model="currencyForm.code"
                                    type="text"
                                    maxlength="3"
                                    placeholder="USD"
                                    class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm uppercase outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                    @input="currencyForm.code = String(currencyForm.code || '').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3)"
                                />
                            </div>
                            <div class="flex w-full flex-1 min-w-[12rem] flex-col gap-1">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Название</label>
                                <input
                                    v-model="currencyForm.name"
                                    type="text"
                                    placeholder="Доллар США"
                                    class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                />
                            </div>
                            <button
                                type="submit"
                                class="inline-flex h-[38px] shrink-0 items-center justify-center border border-zinc-200 px-3 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                :disabled="currencyForm.processing"
                            >
                                {{ currencyForm.processing ? 'Сохранение...' : 'Добавить' }}
                            </button>
                        </form>
                    </div>

                    <div v-if="activeDictionary.key === 'contractor-activity-types' && activityTypeForm.errors.name" class="text-sm text-rose-600">
                        {{ activityTypeForm.errors.name }}
                    </div>
                    <div v-if="activeDictionary.key === 'currencies'" class="space-y-1">
                        <div v-if="currencyForm.errors.code" class="text-sm text-rose-600">{{ currencyForm.errors.code }}</div>
                        <div v-if="currencyForm.errors.name" class="text-sm text-rose-600">{{ currencyForm.errors.name }}</div>
                    </div>

                    <div class="mt-4 min-h-0 flex-1 overflow-auto border border-zinc-200 dark:border-zinc-800">
                        <div v-if="activeDictionary.items.length === 0" class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">
                            Справочник пока пуст.
                        </div>

                        <div v-else class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <div
                                v-for="item in activeDictionary.items"
                                :key="item.id"
                                class="flex items-center justify-between gap-3 px-4 py-3"
                            >
                                <div class="text-sm text-zinc-900 dark:text-zinc-100">
                                    <template v-if="activeDictionary.key === 'currencies'">
                                        <span class="font-mono font-medium">{{ item.code }}</span>
                                        <span class="text-zinc-500 dark:text-zinc-400"> — {{ item.name }}</span>
                                    </template>
                                    <template v-else>
                                        {{ item.name }}
                                    </template>
                                </div>
                                <button
                                    type="button"
                                    class="text-sm text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300"
                                    @click="activeDictionary.key === 'currencies' ? removeCurrency(item) : removeActivityType(item)"
                                >
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'configuration', activeLeafKey: 'dictionaries' }, () => page),
});

const props = defineProps({
    dictionaries: {
        type: Array,
        default: () => [],
    },
});

const activeKey = ref(props.dictionaries[0]?.key ?? null);

const activeDictionary = computed(() => {
    return props.dictionaries.find((dictionary) => dictionary.key === activeKey.value) ?? null;
});

const activityTypeForm = useForm({
    name: '',
});

const currencyForm = useForm({
    code: '',
    name: '',
});

function submitActivityType() {
    activityTypeForm.post(route('settings.dictionaries.activity-types.store'), {
        preserveScroll: true,
        onSuccess: () => {
            activityTypeForm.reset();
        },
    });
}

function submitCurrency() {
    currencyForm.post(route('settings.dictionaries.currencies.store'), {
        preserveScroll: true,
        onSuccess: () => {
            currencyForm.reset();
        },
    });
}

function removeActivityType(item) {
    if (!window.confirm(`Удалить "${item.name}" из справочника?`)) {
        return;
    }

    router.delete(route('settings.dictionaries.activity-types.destroy', item.id), {
        preserveScroll: true,
    });
}

function removeCurrency(item) {
    if (!window.confirm(`Удалить валюту ${item.code} из справочника?`)) {
        return;
    }

    router.delete(route('settings.dictionaries.currencies.destroy', item.id), {
        preserveScroll: true,
    });
}
</script>

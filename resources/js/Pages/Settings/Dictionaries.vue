<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
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

                        <form class="flex w-full gap-2 md:max-w-md" @submit.prevent="submitActivityType">
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
                    </div>

                    <div v-if="activityTypeForm.errors.name" class="text-sm text-rose-600">
                        {{ activityTypeForm.errors.name }}
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
                                <div class="text-sm text-zinc-900 dark:text-zinc-100">{{ item.name }}</div>
                                <button
                                    type="button"
                                    class="text-sm text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300"
                                    @click="removeActivityType(item)"
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
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'dictionaries' }, () => page),
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

function submitActivityType() {
    activityTypeForm.post(route('settings.dictionaries.activity-types.store'), {
        preserveScroll: true,
        onSuccess: () => {
            activityTypeForm.reset();
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
</script>

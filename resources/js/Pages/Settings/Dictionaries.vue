<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            lead="Централизованное управление общими классификаторами системы."
            title="Справочники"
        />

        <div class="grid min-h-0 grid-cols-1 gap-4 xl:grid-cols-[minmax(0,340px)_minmax(0,1fr)]">
            <aside :class="`${crmPanel} p-4`">
                <div class="space-y-2">
                    <button
                        v-for="dictionary in dictionaries"
                        :key="dictionary.key"
                        type="button"
                        :class="[
                            activeDictionary?.key === dictionary.key ? crmListItemActive : crmListItemIdle,
                            'justify-between',
                        ]"
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

            <section :class="`${crmPanel} flex min-h-0 flex-col p-4`">
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
                            v-else-if="activeDictionary.key === 'vat-rates'"
                            class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end md:max-w-xl"
                            @submit.prevent="submitVatRate"
                        >
                            <div class="flex w-full flex-1 flex-col gap-1 sm:max-w-[8rem]">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Ставка, %</label>
                                <input
                                    v-model="vatRateForm.rate_percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    placeholder="22"
                                    class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                />
                            </div>
                            <div class="flex w-full flex-1 min-w-[12rem] flex-col gap-1">
                                <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Подпись (необязательно)</label>
                                <input
                                    v-model="vatRateForm.label"
                                    type="text"
                                    placeholder="Напр. С НДС 7%"
                                    class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                />
                            </div>
                            <button
                                type="submit"
                                class="inline-flex h-[38px] shrink-0 items-center justify-center border border-zinc-200 px-3 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                :disabled="vatRateForm.processing"
                            >
                                {{ vatRateForm.processing ? 'Сохранение...' : 'Добавить' }}
                            </button>
                        </form>

                        <form
                            v-else-if="activeDictionary.key === 'departments'"
                            class="flex w-full gap-2 md:max-w-md"
                            @submit.prevent="submitDepartment"
                        >
                            <input
                                v-model="departmentForm.name"
                                type="text"
                                placeholder="Название подразделения"
                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                            >
                            <button
                                type="submit"
                                class="inline-flex shrink-0 items-center justify-center border border-zinc-200 px-3 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                :disabled="departmentForm.processing"
                            >
                                {{ departmentForm.processing ? 'Сохранение...' : 'Добавить' }}
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
                    <div v-if="activeDictionary.key === 'vat-rates'" class="space-y-1">
                        <div v-if="vatRateForm.errors.rate_percent" class="text-sm text-rose-600">{{ vatRateForm.errors.rate_percent }}</div>
                        <div v-if="vatRateForm.errors.label" class="text-sm text-rose-600">{{ vatRateForm.errors.label }}</div>
                    </div>
                    <div v-if="activeDictionary.key === 'departments' && departmentForm.errors.name" class="text-sm text-rose-600">
                        {{ departmentForm.errors.name }}
                    </div>

                    <div class="mt-4 min-h-0 flex-1 overflow-auto border border-zinc-200 dark:border-zinc-800">
                        <div v-if="activeDictionary.items.length === 0" class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">
                            Справочник пока пуст.
                        </div>

                        <div v-else class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <div
                                v-for="item in activeDictionary.items"
                                :key="item.id"
                                class="px-4 py-3"
                            >
                                <template v-if="activeDictionary.key === 'departments'">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0 flex-1 space-y-2">
                                            <input
                                                v-model="departmentDraft(item).name"
                                                type="text"
                                                class="w-full border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-50"
                                            >
                                            <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                                <label class="inline-flex items-center gap-2">
                                                    <input
                                                        v-model="departmentDraft(item).is_active"
                                                        type="checkbox"
                                                        class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-zinc-400"
                                                    >
                                                    <span>Активно</span>
                                                </label>
                                                <span v-if="item.users_count > 0">
                                                    Пользователей: {{ item.users_count }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex shrink-0 gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center border border-zinc-200 px-3 py-1.5 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                                @click="saveDepartment(item)"
                                            >
                                                Сохранить
                                            </button>
                                            <button
                                                type="button"
                                                class="text-sm text-rose-600 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-40 dark:text-rose-400 dark:hover:text-rose-300"
                                                :disabled="item.users_count > 0"
                                                :title="item.users_count > 0 ? 'Сначала переназначьте пользователей' : 'Удалить подразделение'"
                                                @click="removeDepartment(item)"
                                            >
                                                Удалить
                                            </button>
                                        </div>
                                    </div>
                                </template>
                                <div
                                    v-else
                                    class="flex items-center justify-between gap-3"
                                >
                                    <div class="text-sm text-zinc-900 dark:text-zinc-100">
                                        <template v-if="activeDictionary.key === 'currencies'">
                                            <span class="font-mono font-medium">{{ item.code }}</span>
                                            <span class="text-zinc-500 dark:text-zinc-400"> — {{ item.name }}</span>
                                        </template>
                                        <template v-else-if="activeDictionary.key === 'vat-rates'">
                                            <span class="font-medium">{{ item.label }}</span>
                                            <span class="text-zinc-500 dark:text-zinc-400"> — {{ item.rate_percent }}%</span>
                                            <span class="ml-2 font-mono text-xs text-zinc-400 dark:text-zinc-500">{{ item.code }}</span>
                                        </template>
                                        <template v-else>
                                            {{ item.name }}
                                        </template>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-sm text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300"
                                        @click="removeDictionaryItem(item)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmListItemActive, crmListItemIdle, crmPanel } from '@/support/crmUi.js';

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

const vatRateForm = useForm({
    rate_percent: '',
    label: '',
});

const departmentForm = useForm({
    name: '',
});

const departmentDrafts = reactive({});

const page = usePage();

watch(
    () => props.dictionaries.find((dictionary) => dictionary.key === 'departments')?.items ?? [],
    (items) => {
        for (const key of Object.keys(departmentDrafts)) {
            if (!items.some((item) => String(item.id) === String(key))) {
                delete departmentDrafts[key];
            }
        }

        for (const item of items) {
            departmentDrafts[item.id] = {
                name: item.name,
                is_active: Boolean(item.is_active),
            };
        }
    },
    { immediate: true, deep: true },
);

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

function submitVatRate() {
    vatRateForm.post(route('settings.dictionaries.vat-rates.store'), {
        preserveScroll: true,
        onSuccess: () => {
            vatRateForm.reset();
        },
    });
}

function submitDepartment() {
    departmentForm.post(route('settings.dictionaries.departments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            departmentForm.reset();
        },
    });
}

function departmentDraft(item) {
    if (!departmentDrafts[item.id]) {
        departmentDrafts[item.id] = {
            name: item.name,
            is_active: Boolean(item.is_active),
        };
    }

    return departmentDrafts[item.id];
}

function saveDepartment(item) {
    const draft = departmentDraft(item);

    router.patch(route('settings.dictionaries.departments.update', item.id), {
        name: draft.name,
        is_active: draft.is_active,
    }, {
        preserveScroll: true,
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

function removeVatRate(item) {
    if (!window.confirm(`Удалить ставку «${item.label}» из справочника?`)) {
        return;
    }

    router.delete(route('settings.dictionaries.vat-rates.destroy', item.id), {
        preserveScroll: true,
    });
}

function removeDepartment(item) {
    if (item.users_count > 0) {
        return;
    }

    if (!window.confirm(`Удалить подразделение «${item.name}»?`)) {
        return;
    }

    router.delete(route('settings.dictionaries.departments.destroy', item.id), {
        preserveScroll: true,
    });
}

function removeDictionaryItem(item) {
    const key = activeDictionary.value?.key;
    if (key === 'currencies') {
        removeCurrency(item);
        return;
    }
    if (key === 'vat-rates') {
        removeVatRate(item);
        return;
    }
    if (key === 'departments') {
        removeDepartment(item);
        return;
    }
    removeActivityType(item);
}

watch(
    () => page.props.errors?.department,
    (message) => {
        if (message) {
            window.alert(String(message));
        }
    },
);
</script>

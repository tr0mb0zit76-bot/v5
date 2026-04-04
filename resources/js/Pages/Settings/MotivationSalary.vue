<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div class="shrink-0 space-y-1">
            <h1 class="text-2xl font-semibold">Условия сотрудников</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Индивидуальные условия расчета зарплаты: оклад, бонус и дата начала действия.
            </p>
        </div>

        <div class="grid min-h-0 grid-cols-1 gap-3 xl:grid-cols-[minmax(360px,0.42fr)_minmax(0,0.58fr)]">
            <section class="border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mb-4 space-y-1">
                    <h2 class="text-lg font-semibold">Новое условие</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Добавьте персональные параметры для сотрудника.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <label class="space-y-1">
                        <span class="text-sm font-medium">Сотрудник</span>
                        <select
                            v-model="createSalaryForm.manager_id"
                            class="w-full border border-zinc-300 px-3 py-2 text-sm outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                        >
                            <option value="">Выберите сотрудника</option>
                            <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                                {{ employee.name }}<span v-if="employee.role_name"> · {{ employee.role_name }}</span>
                            </option>
                        </select>
                    </label>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <label class="space-y-1">
                            <span class="text-sm font-medium">Оклад</span>
                            <input
                                v-model.number="createSalaryForm.base_salary"
                                type="number"
                                min="0"
                                class="w-full border border-zinc-300 px-3 py-2 text-sm outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                            >
                        </label>

                        <label class="space-y-1">
                            <span class="text-sm font-medium">Бонус, %</span>
                            <input
                                v-model.number="createSalaryForm.bonus_percent"
                                type="number"
                                min="0"
                                max="100"
                                class="w-full border border-zinc-300 px-3 py-2 text-sm outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                            >
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <label class="space-y-1">
                            <span class="text-sm font-medium">Действует с</span>
                            <input
                                v-model="createSalaryForm.effective_from"
                                type="date"
                                class="w-full border border-zinc-300 px-3 py-2 text-sm outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                            >
                        </label>

                        <label class="space-y-1">
                            <span class="text-sm font-medium">Действует до</span>
                            <input
                                v-model="createSalaryForm.effective_to"
                                type="date"
                                class="w-full border border-zinc-300 px-3 py-2 text-sm outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                            >
                        </label>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="createSalaryForm.is_active" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-zinc-400">
                        <span>Запись активна</span>
                    </label>

                    <div class="flex justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg bg-zinc-900 px-4 py-2 text-sm text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                            :disabled="createSalaryForm.processing"
                            @click="storeSalaryCoefficient"
                        >
                            Добавить условие
                        </button>
                    </div>
                </div>
            </section>

            <section class="min-h-0 overflow-auto border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-zinc-50 dark:bg-zinc-950/60">
                        <tr>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Сотрудник</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Оклад</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Бонус</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">С</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">По</th>
                            <th class="px-3 py-3 text-left font-medium text-zinc-600 dark:text-zinc-300">Статус</th>
                            <th class="px-3 py-3 text-right font-medium text-zinc-600 dark:text-zinc-300">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        <tr v-for="coefficient in salaryDrafts" :key="coefficient.id">
                            <td class="px-3 py-2">
                                <select
                                    v-model="coefficient.manager_id"
                                    class="w-full min-w-[180px] border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                >
                                    <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                                        {{ employee.name }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input
                                    v-model.number="coefficient.base_salary"
                                    type="number"
                                    min="0"
                                    class="w-28 border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model.number="coefficient.bonus_percent"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="w-20 border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                    >
                                    <span class="text-zinc-500 dark:text-zinc-400">%</span>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <input
                                    v-model="coefficient.effective_from"
                                    type="date"
                                    class="border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <input
                                    v-model="coefficient.effective_to"
                                    type="date"
                                    class="border border-zinc-300 px-2 py-1.5 outline-none transition focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-400"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <label class="inline-flex items-center gap-2 text-xs">
                                    <input v-model="coefficient.is_active" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-zinc-400">
                                    <span>{{ coefficient.is_active ? 'Активно' : 'Выключено' }}</span>
                                </label>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                        @click="saveSalaryCoefficient(coefficient)"
                                    >
                                        Сохранить
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-600 transition-colors hover:bg-red-50 dark:border-red-900/40 dark:text-red-400 dark:hover:bg-red-950/30"
                                        @click="deleteSalaryCoefficient(coefficient.id)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="salaryDrafts.length === 0">
                            <td colspan="7" class="px-3 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                Индивидуальные условия для сотрудников ещё не заданы.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'settings', activeSubKey: 'motivation', activeLeafKey: 'salary-settings' }, () => page),
});

const props = defineProps({
    employees: {
        type: Array,
        default: () => [],
    },
    salaryCoefficients: {
        type: Array,
        default: () => [],
    },
});

const createSalaryForm = useForm({
    manager_id: '',
    base_salary: 0,
    bonus_percent: 0,
    effective_from: '',
    effective_to: '',
    is_active: true,
});

const salaryDrafts = reactive(props.salaryCoefficients.map((row) => ({
    ...row,
})));

function storeSalaryCoefficient() {
    createSalaryForm.post(route('settings.motivation.salary.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createSalaryForm.reset();
            createSalaryForm.is_active = true;
        },
    });
}

function saveSalaryCoefficient(coefficient) {
    router.patch(route('settings.motivation.salary.update', coefficient.id), {
        manager_id: coefficient.manager_id,
        base_salary: coefficient.base_salary,
        bonus_percent: coefficient.bonus_percent,
        effective_from: coefficient.effective_from,
        effective_to: coefficient.effective_to,
        is_active: coefficient.is_active,
    }, {
        preserveScroll: true,
    });
}

function deleteSalaryCoefficient(id) {
    if (!window.confirm('Удалить это условие мотивации?')) {
        return;
    }

    router.delete(route('settings.motivation.salary.destroy', id), {
        preserveScroll: true,
    });
}
</script>

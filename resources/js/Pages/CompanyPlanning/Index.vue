<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto pb-6">
        <CrmPageHeader
            :lead="`Управленческие инициативы компании: цели, этапы, сроки и бюджет. Всего: ${initiatives.length}`"
            title="План компании"
        >
            <template #actions>
                <button type="button" :class="crmBtnCreate" @click="createOpen = !createOpen">
                    {{ createOpen ? 'Скрыть форму' : 'Новая инициатива' }}
                </button>
            </template>
        </CrmPageHeader>

        <section v-if="createOpen" :class="`${crmPanel} space-y-4 p-5`">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Новая инициатива</h2>
            <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submitCreate">
                <label :class="crmFilterField" class="md:col-span-2">
                    <span :class="crmLabelCompact">Название</span>
                    <input v-model="createForm.title" :class="crmFieldFluid" placeholder="Например: Запуск направления импорта" />
                    <InputError :message="createForm.errors.title" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Направление</span>
                    <select v-model="createForm.direction" :class="crmFieldFluid">
                        <option :value="null">Не указано</option>
                        <option v-for="(label, value) in directionLabels" :key="value" :value="value">{{ label }}</option>
                    </select>
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Владелец</span>
                    <select v-model="createForm.owner_id" :class="crmFieldFluid">
                        <option :value="null">Не назначен</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Старт</span>
                    <input v-model="createForm.starts_on" type="date" :class="crmFieldFluid" />
                </label>
                <label :class="crmFilterField">
                    <span :class="crmLabelCompact">Дедлайн</span>
                    <input v-model="createForm.ends_on" type="date" :class="crmFieldFluid" />
                </label>
                <label :class="crmFilterField" class="md:col-span-2">
                    <span :class="crmLabelCompact">Цель</span>
                    <textarea v-model="createForm.goal" rows="2" :class="crmFieldFluid" />
                </label>
                <div class="md:col-span-2 flex justify-end gap-2">
                    <button type="button" :class="crmBtnNeutral" @click="createOpen = false">Отмена</button>
                    <button type="submit" :class="crmBtnPrimary" :disabled="createForm.processing">Создать</button>
                </div>
            </form>
        </section>

        <section :class="`${crmPanel} p-4`">
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="option in statusFilterOptions"
                    :key="option.value"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                    :class="statusFilter === option.value
                        ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-50 dark:bg-zinc-50 dark:text-zinc-900'
                        : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300'"
                    @click="applyStatusFilter(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>

            <div v-if="initiatives.length === 0" class="rounded-2xl border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                Инициатив пока нет. Создайте первую — это верхний уровень плана, не задачи сотрудников.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="px-3 py-2">Инициатива</th>
                            <th class="px-3 py-2">Направление</th>
                            <th class="px-3 py-2">Владелец</th>
                            <th class="px-3 py-2">Сроки</th>
                            <th class="px-3 py-2">Прогресс</th>
                            <th class="px-3 py-2">Бюджет</th>
                            <th class="px-3 py-2">Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in initiatives"
                            :key="row.id"
                            class="cursor-pointer border-b border-zinc-100 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900/60"
                            @click="openInitiative(row)"
                        >
                            <td class="px-3 py-3 align-top">
                                <div class="font-medium text-zinc-900 dark:text-zinc-50">{{ row.title }}</div>
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ row.milestones_count }} этапов</div>
                            </td>
                            <td class="px-3 py-3 align-top text-zinc-700 dark:text-zinc-300">{{ row.direction_label || '—' }}</td>
                            <td class="px-3 py-3 align-top text-zinc-700 dark:text-zinc-300">{{ row.owner_name || '—' }}</td>
                            <td class="px-3 py-3 align-top text-zinc-700 dark:text-zinc-300">{{ formatPeriod(row.starts_on, row.ends_on) }}</td>
                            <td class="px-3 py-3 align-top">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-24 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${row.progress_percent}%` }" />
                                    </div>
                                    <span class="text-xs text-zinc-600 dark:text-zinc-400">{{ row.progress_percent }}%</span>
                                </div>
                            </td>
                            <td class="px-3 py-3 align-top text-zinc-700 dark:text-zinc-300">{{ formatBudget(row) }}</td>
                            <td class="px-3 py-3 align-top">
                                <span :class="statusBadgeClass(row.status)">{{ row.status_label }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmBtnCreate,
    crmBtnNeutral,
    crmBtnPrimary,
    crmFieldFluid,
    crmFilterField,
    crmLabelCompact,
    crmPanel,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'planning', activeSubKey: 'company-planning', mainFill: true }, () => page),
});

const props = defineProps({
    initiatives: { type: Array, default: () => [] },
    status_filter: { type: String, default: '' },
    status_labels: { type: Object, default: () => ({}) },
    direction_labels: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
});

const createOpen = ref(false);
const statusFilter = computed(() => props.status_filter ?? '');

const createForm = useForm({
    title: '',
    direction: null,
    owner_id: null,
    starts_on: null,
    ends_on: null,
    goal: null,
    status: 'draft',
});

const statusFilterOptions = computed(() => [
    { value: '', label: 'Все' },
    ...Object.entries(props.status_labels).map(([value, label]) => ({ value, label })),
]);

function applyStatusFilter(value) {
    router.get(route('company-planning.index'), value ? { status: value } : {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

function submitCreate() {
    createForm.post(route('company-planning.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createOpen.value = false;
        },
    });
}

function openInitiative(row) {
    if (!row?.id) {
        return;
    }

    router.get(route('company-planning.show', row.id));
}

function formatPeriod(start, end) {
    if (!start && !end) {
        return '—';
    }

    return `${formatDate(start)} → ${formatDate(end)}`;
}

function formatDate(value) {
    if (!value) {
        return '…';
    }

    const parts = String(value).split('-');
    if (parts.length !== 3) {
        return value;
    }

    return `${parts[2]}.${parts[1]}.${parts[0]}`;
}

function formatBudget(row) {
    if (row.planned_budget_amount === null || row.planned_budget_amount === undefined || row.planned_budget_amount === '') {
        return '—';
    }

    const amount = Number(row.planned_budget_amount);
    const currency = row.budget_currency || 'RUB';

    return `${amount.toLocaleString('ru-RU', { maximumFractionDigits: 0 })} ${currency}`;
}

function statusBadgeClass(status) {
    const base = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium';

    return {
        draft: `${base} bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300`,
        active: `${base} bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200`,
        on_hold: `${base} bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200`,
        completed: `${base} bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200`,
        cancelled: `${base} bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200`,
    }[status] ?? `${base} bg-zinc-100 text-zinc-700`;
}
</script>

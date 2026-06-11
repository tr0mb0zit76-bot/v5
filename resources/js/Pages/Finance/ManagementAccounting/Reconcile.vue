<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
        <div class="space-y-1">
            <Link
                href="/finance/management-accounting?tab=payments"
                class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            >
                <ArrowLeft class="h-4 w-4" />
                К управленческому учёту
            </Link>
            <h1 :class="crmPageTitle">Разнесение выписки</h1>
            <p :class="crmPageLead">
                {{ importData.file_name }} · {{ importData.bank_account?.bank_name }}
                · {{ formatDate(importData.period_from) }} — {{ formatDate(importData.period_to) }}
                · разнесено {{ importData.lines_allocated }} / {{ importData.lines_count }}
            </p>
        </div>

        <div class="space-y-3">
            <article
                v-for="line in lines"
                :key="line.id"
                :class="`${crmPanel} space-y-3 p-4 ${line.status === 'allocated' ? 'opacity-70' : ''}`"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <div class="text-sm text-zinc-500">{{ formatDate(line.operation_date) }}</div>
                        <div class="font-medium" :class="line.direction === 'in' ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'">
                            {{ line.direction === 'in' ? '+' : '−' }}{{ formatMoney(line.amount) }}
                        </div>
                        <p class="text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ line.description }}</p>
                    </div>
                    <div v-if="line.status === 'allocated'" class="rounded border border-emerald-200 px-2 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-800 dark:text-emerald-300">
                        Разнесено
                    </div>
                </div>

                <div v-if="line.match_notes" class="text-xs text-amber-700 dark:text-amber-300">
                    {{ line.match_notes }} ({{ line.match_confidence }}%)
                </div>

                <div v-if="line.suggested_order && !hasOperationalCandidates(line)" class="text-sm">
                    Заявка: <strong>{{ line.suggested_order.order_number }}</strong>
                    <span v-if="line.suggested_payment_schedule">
                        · график #{{ line.suggested_payment_schedule.id }}
                        · {{ formatMoney(line.suggested_payment_schedule.amount) }}
                    </span>
                </div>

                <div v-if="line.suggested_user" class="text-sm">
                    Сотрудник: {{ line.suggested_user.name }}
                </div>

                <form
                    v-if="line.status !== 'allocated'"
                    class="grid gap-2 md:grid-cols-4"
                    @submit.prevent="allocateLine(line)"
                >
                    <select
                        v-model="allocationForms[line.id].allocation_type"
                        class="rounded-lg border border-zinc-300 px-2 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                    >
                        <option value="operational">Операционный</option>
                        <option value="payroll">ФОТ</option>
                        <option value="category">Статья</option>
                    </select>
                    <select
                        v-if="allocationForms[line.id].allocation_type === 'category' || allocationForms[line.id].allocation_type === 'payroll'"
                        v-model="allocationForms[line.id].category_id"
                        class="rounded-lg border border-zinc-300 px-2 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                    >
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                    <select
                        v-if="allocationForms[line.id].allocation_type === 'operational' && hasOperationalCandidates(line)"
                        v-model="allocationForms[line.id].payment_schedule_id"
                        required
                        class="md:col-span-2 rounded-lg border border-zinc-300 px-2 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                    >
                        <option
                            v-if="line.operational_candidates.length > 1"
                            value=""
                            disabled
                        >
                            Выберите строку графика…
                        </option>
                        <option
                            v-for="candidate in line.operational_candidates"
                            :key="candidate.payment_schedule_id"
                            :value="candidate.payment_schedule_id"
                        >
                            {{ candidateOptionLabel(candidate) }}
                        </option>
                    </select>
                    <input
                        v-else-if="allocationForms[line.id].allocation_type === 'operational'"
                        v-model.number="allocationForms[line.id].payment_schedule_id"
                        type="number"
                        placeholder="ID строки графика"
                        class="rounded-lg border border-zinc-300 px-2 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                    >
                    <input
                        v-if="allocationForms[line.id].allocation_type === 'payroll'"
                        v-model.number="allocationForms[line.id].user_id"
                        type="number"
                        placeholder="ID сотрудника"
                        class="rounded-lg border border-zinc-300 px-2 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-950"
                    >
                    <button
                        type="submit"
                        :class="crmBtnPrimary"
                    >
                        Подтвердить
                    </button>
                </form>
            </article>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { ArrowLeft } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnPrimary, crmPageLead, crmPageTitle, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) =>
        h(CrmLayout, { activeKey: 'finance', activeSubKey: 'finance-management-accounting' }, () => page),
});

const props = defineProps({
    import: { type: Object, required: true },
    lines: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const importData = props.import;

const allocationForms = reactive({});

for (const line of props.lines) {
    const candidates = Array.isArray(line.operational_candidates) ? line.operational_candidates : [];
    const ambiguous = candidates.length > 1;
    const defaultScheduleId = ambiguous
        ? ''
        : (candidates[0]?.payment_schedule_id
            ?? line.suggested_payment_schedule?.id
            ?? '');

    allocationForms[line.id] = {
        allocation_type: line.match_type === 'operational'
            ? 'operational'
            : (line.match_type === 'payroll' ? 'payroll' : 'category'),
        category_id: line.suggested_category?.id ?? props.categories[0]?.id ?? null,
        payment_schedule_id: defaultScheduleId,
        user_id: line.suggested_user?.id ?? null,
    };
}

function hasOperationalCandidates(line) {
    return Array.isArray(line.operational_candidates) && line.operational_candidates.length > 0;
}

function candidateOptionLabel(candidate) {
    const order = candidate.order_number || `#${candidate.order_id}`;
    const plan = candidate.planned_date ? formatDate(candidate.planned_date) : 'без даты';
    const amount = formatMoney(candidate.amount);

    return `${order} · ${amount} · план ${plan} · график #${candidate.payment_schedule_id}`;
}

function allocateLine(line) {
    router.post(`/finance/management-accounting/lines/${line.id}/allocate`, allocationForms[line.id], {
        preserveScroll: true,
    });
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('ru-RU');
}
</script>

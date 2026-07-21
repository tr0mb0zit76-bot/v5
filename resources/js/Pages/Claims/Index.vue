<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3">
        <CrmPageHeader title="Претензии" lead="Инциденты по заказам: простой, порча, сроки, ставки.">
            <template #actions>
                <form class="flex flex-wrap items-center gap-2" @submit.prevent="applyFilters">
                    <input
                        v-model="localFilters.q"
                        type="search"
                        :class="crmFieldFluid"
                        class="!w-56"
                        placeholder="Номер / тема / заказ"
                    />
                    <select v-model="localFilters.status" :class="`${crmFieldFluid} !w-44`">
                        <option value="">Все статусы</option>
                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <button type="submit" :class="crmBtnSecondary">Найти</button>
                </form>
            </template>
        </CrmPageHeader>

        <div :class="crmGridPanel">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Номер</th>
                        <th class="px-3 py-2 font-semibold">Заказ</th>
                        <th class="px-3 py-2 font-semibold">Тема</th>
                        <th class="px-3 py-2 font-semibold">Тип</th>
                        <th class="px-3 py-2 font-semibold">Статус</th>
                        <th class="px-3 py-2 font-semibold">Риск</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <tr
                        v-for="claim in claims"
                        :key="claim.id"
                        class="hover:bg-zinc-50 dark:hover:bg-zinc-900/60"
                    >
                        <td class="px-3 py-2 font-medium">
                            <Link
                                v-if="claim.edit_url"
                                :href="claim.edit_url"
                                class="text-sky-700 underline dark:text-sky-300"
                            >
                                {{ claim.number }}
                            </Link>
                            <span v-else>{{ claim.number }}</span>
                        </td>
                        <td class="px-3 py-2">{{ claim.order_number || `№${claim.order_id}` }}</td>
                        <td class="px-3 py-2">{{ claim.title }}</td>
                        <td class="px-3 py-2">{{ claim.type_label }}</td>
                        <td class="px-3 py-2">{{ claim.status_label }}</td>
                        <td class="px-3 py-2">
                            <span v-if="claim.amount_risk != null">
                                {{ Number(claim.amount_risk).toLocaleString('ru-RU') }} {{ claim.currency }}
                            </span>
                            <span v-else class="text-zinc-400">—</span>
                        </td>
                    </tr>
                    <tr v-if="!claims.length">
                        <td colspan="6" class="px-3 py-8 text-center text-zinc-500">
                            Претензий пока нет.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnSecondary, crmFieldFluid, crmGridPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'claims', mainFill: true }, () => page),
});

const props = defineProps({
    claims: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    partyOptions: { type: Array, default: () => [] },
});

const localFilters = reactive({
    q: props.filters?.q ?? '',
    status: props.filters?.status ?? '',
});

function applyFilters() {
    router.get(route('claims.index'), {
        q: localFilters.q || undefined,
        status: localFilters.status || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}
</script>

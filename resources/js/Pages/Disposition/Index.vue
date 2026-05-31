<template>
    <div class="flex min-h-0 flex-1 flex-col gap-2">
        <CrmPageHeader
            lead="Заказы в статусе «Выполняется»: утренние и вечерние отметки местоположения и комментарии по дням. Левый край таблицы — сегодня."
            title="Диспозиция"
        />

        <div :class="crmGridPanel">
            <DispositionGrid
                :dates="dates"
                :rows="rows"
                :today="today"
                :user-id="userId"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import DispositionGrid from '@/Components/Disposition/DispositionGrid.vue';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmGridPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'planning', activeSubKey: 'disposition' }, () => page),
});

const props = defineProps({
    dates: { type: Array, default: () => [] },
    today: { type: String, default: '' },
    rows: { type: Array, default: () => [] },
    status_filter: { type: String, default: 'in_progress' },
});

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
</script>

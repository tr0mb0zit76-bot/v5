<template>
    <div :class="`${crmPanel} p-8`">
        <h1 :class="crmPageTitleSm">Контейнера</h1>
        <p :class="`${crmPageLead} mt-2`">Раздел в разработке.</p>
    </div>
</template>

<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmPageLead, crmPageTitleSm, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'fleet', activeSubKey: 'fleet-containers' }, () => page),
});
</script>

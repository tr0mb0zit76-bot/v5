const fs = require('fs');
const path = require('path');

const inner = fs.readFileSync(path.join(__dirname, '../resources/js/Components/Orders/_route_inner.vue.txt'), 'utf8');
const script = `<script setup>
import { inject } from 'vue';
import { Minus, Plus, X } from 'lucide-vue-next';
import CarrierPortalInviteButton from '@/Components/Orders/CarrierPortalInviteButton.vue';
import OrderTrakloChatButton from '@/Components/Orders/OrderTrakloChatButton.vue';
import { ORDER_WIZARD_ROUTE_TAB_KEY } from '@/support/orderWizardRouteTabKey.js';

const ctx = inject(ORDER_WIZARD_ROUTE_TAB_KEY);
const {
    form,
    order,
    ownFleetContractor,
    borderCrossingLegPicker,
    routeChainLabel,
    hasBorderCrossingPoint,
    stageLabel,
    CARRIER_MODE_SINGLE,
    CARRIER_MODE_SPLIT,
    OWN_FLEET_CONTRACTOR_NAME,
    crmFieldFluid,
    crmSegmented,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
    addPerformer,
    removePerformer,
    setPerformerCarrierMode,
    isPerformerSplit,
    removeSplitCarrier,
    addSplitCarrier,
    splitCarrierSlotLabel,
    openCounterpartyModal,
    carrierSearchValue,
    highlightRequiredField,
    setCarrierResultsVisible,
    onPerformerCarrierInput,
    restorePerformerCarrierSearch,
    clearPerformerContractor,
    normalizeNullableNumber,
    isCarrierResultsVisible,
    filteredCarrierResults,
    selectOwnFleetPerformer,
    selectPerformerContractor,
    onSplitPerformerCarrierInput,
    restoreSplitPerformerCarrierSearch,
    clearSplitPerformerContractor,
    selectOwnFleetSplitSlot,
    selectSplitPerformerContractor,
    fleetVehicleOptionsForLeg,
    fleetDriverOptionsForLeg,
    loadFleetOptionsForLeg,
    maxActualDate,
    onPerformerActualDateInput,
    onSplitActualDateInput,
    routePointsWithIndicesForLeg,
    routePointsDragEnabled,
    draggedRoutePointIndex,
    dragOverRoutePointIndex,
    handleRoutePointDragStart,
    handleRoutePointDragOver,
    handleRoutePointDrop,
    handleRoutePointDragEnd,
    routePointTitle,
    routePointInlineBtn,
    removeRoutePointAt,
    addRoutePointAfter,
    canRemoveRoutePoint,
    onRoutePointAddressInput,
    syncRoutePointCityFromAddress,
    addressSuggestions,
    selectAddress,
    routePointAddressHighlightValue,
    routePointCityValue,
    setRoutePointCity,
    routePointTimeBlockHeading,
    routePointCombinedContact,
    setRoutePointCombinedContact,
    onRoutePointLegChanged,
    onBorderCrossingLegPickerChange,
} = ctx;
</script>

<template>
    <div class="space-y-4">
${inner.split('\n').map((l) => `        ${l}`).join('\n')}
    </div>
</template>
`;

const out = script.replace(/props\.ownFleetContractor/g, 'ownFleetContractor');
fs.writeFileSync(path.join(__dirname, '../resources/js/Components/Orders/OrderWizardRouteTab.vue'), out, 'utf8');
console.log('Created OrderWizardRouteTab.vue');

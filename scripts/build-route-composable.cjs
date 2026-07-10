const fs = require('fs');
const path = require('path');

const wizardPath = path.join(__dirname, '../resources/js/Pages/Orders/Wizard.vue');
const outPath = path.join(__dirname, '../resources/js/composables/useOrderWizardRouteTab.js');
const lines = fs.readFileSync(wizardPath, 'utf8').split(/\r?\n/);

const ranges = [
    [4335, 4608],
    [4677, 4755],
    [4892, 5283],
    [5285, 5312],
    [5650, 5937],
    [6645, 6709],
    [3894, 3920],
];

let extracted = '';
for (const [start, end] of ranges) {
    extracted += `${lines.slice(start - 1, end).join('\n')}\n\n`;
}

const header = `import { computed, nextTick, ref, watch } from 'vue';
import {
    routePointCityValue,
    setRoutePointCity,
    syncRoutePointCityFromAddress,
} from '@/support/routePointNormalizedData.js';
import {
    blankPerformer,
    blankSplitCarrier,
    CARRIER_MODE_SINGLE,
    CARRIER_MODE_SPLIT,
    EXECUTION_MODE_OWN_FLEET,
    isOwnFleetExecutionMode,
    isPerformerSplit,
    performerFleetCacheKey,
    splitCarrierSlotLabel,
} from '@/support/orderPerformers.js';
import { todayIsoDate } from '@/support/orderActualDates.js';
import {
    isVirtualOwnFleetContractor,
    OWN_FLEET_CONTRACTOR_NAME,
} from '@/support/ownFleetCatalog.js';

export { CARRIER_MODE_SINGLE, CARRIER_MODE_SPLIT };

export function useOrderWizardRouteTab(deps) {
    const {
        form,
        props,
        contractors,
        carrierOptions,
        normalizeNullableNumber,
        blankRoutePoint,
        highlightRequiredField,
        openCounterpartyModal,
        onPerformerActualDateInput,
        onSplitActualDateInput,
        syncContractorCostsFromPerformers,
        syncCargoAllocationMatrixSlots,
        getContractorById,
        ensureContractorInLocalList,
        applyCarrierDefaultsByStage,
        removeCarrierDocumentsForStage,
        normalizeContractorCost,
        normalizePaymentSchedule,
        blankPaymentSchedule,
        costMatchesPerformerSlot,
        MIN_CONTRACTOR_QUERY_LENGTH,
    } = deps;

    const borderCrossingLegPicker = ref('');
    const carrierSearch = ref({});
    const showCarrierResults = ref({});
    const serverCarrierSearchResults = ref({});
    const isSearchingCarriers = ref({});
    const carrierSearchTimers = ref({});
    const carrierSearchAbortControllers = ref({});
    const carrierSearchFetchSeq = ref({});
    const fleetOptionsCache = ref({});
    const addressSuggestions = ref({});
    const addressTimers = {};
    const draggedRoutePointIndex = ref(null);
    const dragOverRoutePointIndex = ref(null);
    const maxActualDate = todayIsoDate();
    const routePointInlineBtn =
        'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-zinc-200 text-zinc-600 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';

`;

const footer = fs.readFileSync(path.join(__dirname, 'useOrderWizardRouteTab.footer.js'), 'utf8');

fs.mkdirSync(path.dirname(outPath), { recursive: true });
fs.writeFileSync(outPath, header + extracted + footer, 'utf8');
console.log('Wrote', outPath, (header + extracted + footer).split('\n').length, 'lines');

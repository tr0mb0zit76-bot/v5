const fs = require('fs');
const path = require('path');

const p = path.join(__dirname, '../resources/js/Pages/Orders/Wizard.vue');
let lines = fs.readFileSync(p, 'utf8').split(/\r?\n/);

function spliceLines(startLine, endLine) {
    lines.splice(startLine - 1, endLine - startLine + 1);
}

// Remove in reverse order (1-based line numbers from last grep/read pass)
const removals = [
    [5654, 5718], // onRoutePointAddressInput .. selectAddress
    [3344, 3495], // addPerformer .. before stageLabel
    [3520, 3617], // remapStageReferences .. onRoutePointLegChanged
    [3686, 4994], // carrierSearchKey .. contractorCostRowHasPaymentDetails (keep costRowTitle block 3665-3684)
];

// Fix removal 3: should start after contractorCostOrderDate
removals[3] = [3686, 4994];

// performerHasLoadingActual + wizardRouteLoadingHasActualDate
spliceLines(2903, 2930);

for (const [start, end] of removals.sort((a, b) => b[0] - a[0])) {
    spliceLines(start, end);
}

// Remove refs (find by content after edits - approximate)
const refPatterns = [
    /^const borderCrossingLegPicker = ref/,
    /^const carrierSearch = ref/,
    /^const showCarrierResults = ref/,
    /^const fleetOptionsCache = ref/,
    /^const addressSuggestions = ref/,
    /^const addressTimers = /,
    /^const draggedRoutePointIndex = ref/,
    /^const dragOverRoutePointIndex = ref/,
    /^const serverCarrierSearchResults = ref/,
    /^const isSearchingCarriers = ref/,
    /^const carrierSearchTimers = ref/,
    /^const carrierSearchAbortControllers = ref/,
    /^const carrierSearchFetchSeq = ref/,
];

lines = lines.filter((line) => !refPatterns.some((re) => re.test(line)));

// Remove carrier search watch block
const watchCarrierIdx = lines.findIndex((l) => l.includes('watch(carrierSearch,'));
if (watchCarrierIdx !== -1) {
    let depth = 0;
    let end = watchCarrierIdx;
    for (let i = watchCarrierIdx; i < lines.length; i++) {
        if (lines[i].includes('watch(carrierSearch,')) {
            depth = 1;
        }
        if (depth && lines[i].trim() === '});') {
            end = i;
            break;
        }
    }
    lines.splice(watchCarrierIdx, end - watchCarrierIdx + 1);
}

// Remove performers carrier watch + initial registration block before syncCarrierNorms
const perfWatchIdx = lines.findIndex((l) => l.includes('() => form.performers.map((performer) => ({'));
if (perfWatchIdx !== -1) {
    const start = lines.findIndex((l, i) => i < perfWatchIdx && l.includes('if (Array.isArray(props.order?.performers))'));
    const end = lines.findIndex((l, i) => i > perfWatchIdx && l.trim() === '{ deep: true, immediate: true },');
    if (start !== -1 && end !== -1) {
        lines.splice(start, end - start + 2);
    }
}

// Remove is_international_transport watch (composable handles)
const intlWatchIdx = lines.findIndex((l) => l.includes('() => form.is_international_transport,'));
if (intlWatchIdx !== -1) {
    let end = intlWatchIdx;
    for (let i = intlWatchIdx; i < lines.length; i++) {
        if (lines[i].trim() === ');') {
            end = i;
            break;
        }
    }
    lines.splice(intlWatchIdx - 1, end - intlWatchIdx + 2);
}

fs.writeFileSync(p, lines.join('\n'), 'utf8');
console.log('Patched Wizard.vue, lines now', lines.length);

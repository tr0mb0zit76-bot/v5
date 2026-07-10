const fs = require('fs');
const path = require('path');

const wizardPath = path.join(__dirname, '../resources/js/Pages/Orders/Wizard.vue');

function findFunctionEnd(lines, startIdx) {
    let depth = 0;
    let started = false;

    for (let i = startIdx; i < lines.length; i++) {
        for (const ch of lines[i]) {
            if (ch === '{') {
                depth += 1;
                started = true;
            } else if (ch === '}') {
                depth -= 1;
                if (started && depth === 0) {
                    return i;
                }
            }
        }
    }

    return startIdx;
}

function findConstEnd(lines, startIdx) {
    let i = startIdx;
    while (i < lines.length && !lines[i].includes(';')) {
        i += 1;
    }

    return i;
}

function removeFunctions(content, names) {
    let lines = content.split(/\r?\n/);
    const toRemove = new Set(names);

    for (let i = 0; i < lines.length; i++) {
        const fnMatch = lines[i].match(/^function (\w+)/);
        const constMatch = lines[i].match(/^const (\w+) = (computed|ref)\(/);

        if (fnMatch && toRemove.has(fnMatch[1])) {
            const end = findFunctionEnd(lines, i);
            lines.splice(i, end - i + 1);
            i -= 1;
            continue;
        }

        if (constMatch && toRemove.has(constMatch[1])) {
            const end = findConstEnd(lines, i);
            lines.splice(i, end - i + 1);
            i -= 1;
        }
    }

    return lines.join('\n');
}

function removeWatchBlock(content, needle) {
    const lines = content.split(/\r?\n/);
    const idx = lines.findIndex((l) => l.includes(needle));
    if (idx === -1) {
        return content;
    }

    let start = idx;
    while (start > 0 && !lines[start].trim().startsWith('watch(')) {
        start -= 1;
    }

    let end = start;
    for (let i = start; i < lines.length; i++) {
        if (lines[i].trim() === ');') {
            end = i;
            break;
        }
    }

    lines.splice(start, end - start + 1);

    return lines.join('\n');
}

// Only route-tab-specific symbols — shared helpers stay in Wizard.vue
const removeNames = [
    'addPerformer',
    'setPerformerCarrierMode',
    'addSplitCarrier',
    'removeSplitCarrier',
    'parsePerformerCarrierTarget',
    'splitCarrierAt',
    'removePerformer',
    'remapStageReferences',
    'reindexLegStagesAndRemap',
    'pruneEmptyLegPerformers',
    'onRoutePointLegChanged',
    'performerCarrierSearchLabel',
    'splitCarrierSearchLabel',
    'carrierSearchKey',
    'carrierSearchValue',
    'setCarrierSearchValue',
    'setCarrierResultsVisible',
    'isCarrierResultsVisible',
    'filteredCarrierResults',
    'selectOwnFleetPerformer',
    'selectOwnFleetSplitSlot',
    'selectSplitPerformerContractor',
    'clearSplitPerformerContractor',
    'onSplitPerformerCarrierInput',
    'restoreSplitPerformerCarrierSearch',
    'selectPerformerContractor',
    'clearPerformerContractor',
    'syncPerformerContractor',
    'onPerformerCarrierInput',
    'restorePerformerCarrierSearch',
    'loadFleetOptionsForLeg',
    'seedFleetOptionsFromPerformer',
    'preloadFleetOptionsForPerformers',
    'fleetVehicleOptionsForLeg',
    'fleetDriverOptionsForLeg',
    'addRoutePoint',
    'addRoutePointForLeg',
    'canRemoveRoutePoint',
    'addRoutePointAfter',
    'removeRoutePointAt',
    'onBorderCrossingLegPickerChange',
    'routePointTypeHeading',
    'routePointTimeBlockHeading',
    'routePointAddressHighlightValue',
    'normalizeRoutePointSequences',
    'syncRoutePointsFromPerformers',
    'routePointOrdinal',
    'routePointTitle',
    'routePointCombinedContact',
    'setRoutePointCombinedContact',
    'routePointsWithIndicesForLeg',
    'routePointsDragEnabled',
    'handleRoutePointDragStart',
    'handleRoutePointDragOver',
    'handleRoutePointDrop',
    'handleRoutePointDragEnd',
    'onRoutePointAddressInput',
    'queueAddressLookup',
    'fetchAddressSuggestions',
    'selectAddress',
    'performerHasLoadingActual',
    'wizardRouteLoadingHasActualDate',
    'stageLabel',
    'toStageKey',
    'stageMatches',
];

const removeConsts = [
    'borderCrossingLegPicker',
    'carrierSearch',
    'showCarrierResults',
    'serverCarrierSearchResults',
    'isSearchingCarriers',
    'carrierSearchTimers',
    'carrierSearchAbortControllers',
    'carrierSearchFetchSeq',
    'fleetOptionsCache',
    'addressSuggestions',
    'draggedRoutePointIndex',
    'dragOverRoutePointIndex',
    'routeChainLabel',
    'hasBorderCrossingPoint',
];

let content = fs.readFileSync(wizardPath, 'utf8');

for (const name of removeConsts) {
    content = content.replace(new RegExp(`^const ${name} = ref\\([^\\n]*\\);\\n`, 'gm'), '');
}

content = content.replace(/^const addressTimers = \{\};\n/gm, '');
content = content.replace(/^const routePointInlineBtn =[\s\S]*?dark:hover:bg-zinc-800';\n\n/gm, '');

content = removeFunctions(content, removeNames);
content = removeWatchBlock(content, 'watch(carrierSearch,');
content = removeWatchBlock(content, '() => form.is_international_transport,');
content = removeWatchBlock(content, '() => form.performers.map((performer) => ({');

content = content.replace(/\nif \(Array.isArray\(props\.order\?\.performers\)\) \{[\s\S]*?\n\}\n\nwatch\(\n    \(\) => form\.performers\.map/, '\n// route performers watch moved to useOrderWizardRouteTab\n');

fs.writeFileSync(wizardPath, content, 'utf8');
console.log('Cleaned route-only symbols, lines', content.split(/\r?\n/).length);

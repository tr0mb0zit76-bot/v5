import { computed, ref } from 'vue';
import {
    contractorCostRowsFromPerformers,
    isOwnFleetExecutionMode,
    splitCarrierSlotLabel,
} from '@/support/orderPerformers.js';
import { normalizePartyNormsPenalties } from '@/support/normsPenalties.js';

export function useOrderWizardFinanceTab(deps) {
    const {
        form,
        props,
        isEditing,
        contractors,
        getContractorById,
        ensureContractorInLocalList,
        normalizeContractorCost,
        normalizePaymentFormCode,
        contractorPaymentSchedule,
        applyCarrierNormsDefaultsByStage,
        isPerformerSplit: isPerformerSplitFn,
        isAdditionalContractorCost: isAdditionalContractorCostFn,
        costMatchesPerformerSlot: costMatchesPerformerSlotFn,
        blankAdditionalCostRow: blankAdditionalCostRowFn,
        MIN_CONTRACTOR_QUERY_LENGTH,
        additionalExpenseAmountFieldClass,
        stageMatches: stageMatchesFn,
        stageLabel: stageLabelFn,
        toStageKey,
        orderPs: orderPsModule,
    } = deps;

    const additionalCostSearch = ref({});
    const showAdditionalCostResults = ref({});
    const additionalCostSearchTimers = ref({});
    const additionalCostSearchAbortControllers = ref({});
    const additionalCostSearchFetchSeq = ref({});
    const serverAdditionalCostSearchResults = ref({});
    const isSearchingAdditionalCosts = ref({});

    const canEditFinancialFields = computed(() => {
        if (!isEditing.value) {
            return true;
        }

        return props.order?.can_edit_financial_fields !== false;
    });

    const additionalCostContractorOptions = computed(() => contractors.value.filter((contractor) => {
        const type = String(contractor.type ?? '');

        return type === 'contractor' || type === 'carrier' || type === 'both';
    }));

    const legContractorCosts = computed(() => (
        Array.isArray(form.financial_term.contractors_costs)
            ? form.financial_term.contractors_costs.filter((row) => !isAdditionalContractorCostFn(row))
            : []
    ));

    function normalizePartyNormsPenaltiesWithStage(raw) {
        const base = normalizePartyNormsPenalties(raw);

        return {
            ...base,
            stage: raw?.stage != null && String(raw.stage).trim() !== '' ? String(raw.stage).trim() : null,
        };
    }

    function contractorCostRowHasPaymentDetails(costRow) {
        if (!costRow || typeof costRow !== 'object') {
            return false;
        }

        if (String(costRow.payment_terms ?? '').trim() !== '') {
            return true;
        }

        const schedule = costRow.payment_schedule;
        if (!schedule || typeof schedule !== 'object') {
            return false;
        }

        if (orderPsModule.usesInstallments(schedule)) {
            return true;
        }

        return Boolean(schedule.has_prepayment)
            || Number(schedule.postpayment_days || 0) > 0
            || Number(schedule.prepayment_days || 0) > 0;
    }

    function syncCarrierNormsByLegFromPerformers() {
        const existingRows = Array.isArray(form.financial_term.carrier_norms_by_leg)
            ? form.financial_term.carrier_norms_by_leg
            : [];

        form.financial_term.carrier_norms_by_leg = form.performers.map((performer) => {
            const existingRow = existingRows.find((row) => stageMatchesFn(row.stage, performer.stage));

            return normalizePartyNormsPenaltiesWithStage({
                ...existingRow,
                stage: performer.stage,
            });
        });
    }

    function syncContractorCostsFromPerformers() {
        const existingRows = Array.isArray(form.financial_term.contractors_costs)
            ? form.financial_term.contractors_costs.filter((row) => !isAdditionalContractorCostFn(row))
            : [];

        const syncedRows = contractorCostRowsFromPerformers(form.performers).map((row) => {
            const existingRow = existingRows.find(
                (cost) => !isAdditionalContractorCostFn(cost) && costMatchesPerformerSlotFn(cost, row.performer, row.slot),
            );

            const nextRow = normalizeContractorCost({
                ...existingRow,
                stage: row.stage,
                carrier_slot: row.carrier_slot,
                contractor_id: row.contractor_id,
                contractor_name: existingRow?.contractor_name ?? null,
                execution_mode: row.execution_mode ?? existingRow?.execution_mode ?? null,
                is_additional: false,
            });

            const previousContractorId = deps.normalizeNullableNumber(existingRow?.contractor_id);
            const nextContractorId = deps.normalizeNullableNumber(row.contractor_id);
            const contractorChanged = previousContractorId !== nextContractorId;
            const shouldApplyCarrierDefaults = nextContractorId !== null
                && (contractorChanged || !contractorCostRowHasPaymentDetails(existingRow));

            if (shouldApplyCarrierDefaults) {
                const contractor = getContractorById(nextContractorId);

                if (contractor?.default_carrier_payment_form) {
                    nextRow.payment_form = normalizePaymentFormCode(contractor.default_carrier_payment_form, 'no_vat');
                }

                nextRow.payment_schedule = contractorPaymentSchedule(contractor, 'default_carrier_payment_schedule', 'default_carrier_payment_term');
            }

            return nextRow;
        });
        form.financial_term.contractors_costs = syncedRows;
        syncCarrierNormsByLegFromPerformers();

        form.performers.forEach((performer) => {
            if (isPerformerSplitFn(performer)) {
                performer.split_carriers.forEach((slot) => {
                    if (slot.contractor_id) {
                        applyCarrierNormsDefaultsByStage(performer.stage, slot.contractor_id);
                    }
                });

                return;
            }

            if (performer.contractor_id) {
                applyCarrierNormsDefaultsByStage(performer.stage, performer.contractor_id);
            }
        });
    }

    function costRowTitle(cost) {
        const contractor = getContractorById(cost?.contractor_id);
        const contractorName = contractor?.name ? String(contractor.name).trim() : String(cost?.contractor_name ?? '').trim();
        const stagePart = stageLabelFn(cost?.stage);
        const slotPart = cost?.carrier_slot ? ` · ${splitCarrierSlotLabel(cost.carrier_slot)}` : '';

        if (contractorName !== '') {
            return `${stagePart}${slotPart} · ${contractorName}`;
        }

        return `${stagePart}${slotPart}`;
    }

    function contractorCostAmountLabel(cost) {
        return isOwnFleetExecutionMode(cost?.execution_mode) ? 'Примерная стоимость' : 'Стоимость';
    }

    function contractorCostOrderDate() {
        return form.order_date;
    }

    function addAdditionalCostRow() {
        form.financial_term.additional_costs.push(blankAdditionalCostRowFn(form.order_date));
    }

    function removeAdditionalCostRow(index) {
        if (!Array.isArray(form.financial_term.additional_costs)) {
            return;
        }

        const row = form.financial_term.additional_costs[index];
        const key = row?.id != null ? additionalCostSearchKey(row.id) : null;

        if (key) {
            const { [key]: _search, ...restSearch } = additionalCostSearch.value;
            additionalCostSearch.value = restSearch;

            const { [key]: _visible, ...restVisible } = showAdditionalCostResults.value;
            showAdditionalCostResults.value = restVisible;

            const { [key]: _server, ...restServer } = serverAdditionalCostSearchResults.value;
            serverAdditionalCostSearchResults.value = restServer;
        }

        form.financial_term.additional_costs.splice(index, 1);
    }

    function additionalCostSearchKey(rowId) {
        return String(rowId ?? '');
    }

    function isAdditionalCostContractorType(type) {
        const normalized = String(type ?? '');

        return normalized === 'contractor' || normalized === 'carrier' || normalized === 'both';
    }

    function filterAdditionalCostSearchResults(list) {
        return (Array.isArray(list) ? list : []).filter((contractor) => isAdditionalCostContractorType(contractor?.type));
    }

    function additionalCostSearchValue(rowId) {
        return additionalCostSearch.value[additionalCostSearchKey(rowId)] ?? '';
    }

    function setAdditionalCostSearchValue(rowId, value) {
        const key = additionalCostSearchKey(rowId);
        additionalCostSearch.value = {
            ...additionalCostSearch.value,
            [key]: value,
        };
        queueAdditionalCostSearch(rowId, value);
    }

    function setAdditionalCostResultsVisible(rowId, visible) {
        const key = additionalCostSearchKey(rowId);
        showAdditionalCostResults.value = {
            ...showAdditionalCostResults.value,
            [key]: visible,
        };
    }

    function isAdditionalCostResultsVisible(rowId) {
        return Boolean(showAdditionalCostResults.value[additionalCostSearchKey(rowId)]);
    }

    function hideAdditionalCostResults(rowId) {
        window.setTimeout(() => setAdditionalCostResultsVisible(rowId, false), 150);
    }

    function queueAdditionalCostSearch(rowId, query) {
        const key = additionalCostSearchKey(rowId);

        if (additionalCostSearchTimers.value[key]) {
            clearTimeout(additionalCostSearchTimers.value[key]);
        }

        if (String(query ?? '').trim().length < MIN_CONTRACTOR_QUERY_LENGTH) {
            serverAdditionalCostSearchResults.value = {
                ...serverAdditionalCostSearchResults.value,
                [key]: [],
            };

            return;
        }

        additionalCostSearchTimers.value[key] = window.setTimeout(async () => {
            await searchAdditionalCostContractors(rowId, String(query).trim());
        }, 550);
    }

    async function searchAdditionalCostContractors(rowId, query) {
        const key = additionalCostSearchKey(rowId);

        additionalCostSearchAbortControllers.value[key]?.abort();
        const ac = new AbortController();
        additionalCostSearchAbortControllers.value = {
            ...additionalCostSearchAbortControllers.value,
            [key]: ac,
        };
        const seq = (additionalCostSearchFetchSeq.value[key] ?? 0) + 1;
        additionalCostSearchFetchSeq.value = {
            ...additionalCostSearchFetchSeq.value,
            [key]: seq,
        };
        isSearchingAdditionalCosts.value = {
            ...isSearchingAdditionalCosts.value,
            [key]: true,
        };

        try {
            const response = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(query)}&limit=100`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
                signal: ac.signal,
            });

            if (!response.ok) {
                throw new Error(`Contractor search failed with status ${response.status}`);
            }

            const data = await response.json();
            if (seq !== additionalCostSearchFetchSeq.value[key]) {
                return;
            }

            serverAdditionalCostSearchResults.value = {
                ...serverAdditionalCostSearchResults.value,
                [key]: filterAdditionalCostSearchResults(data.contractors || []),
            };
        } catch (error) {
            if (error?.name !== 'AbortError') {
                console.error('Additional cost contractor search error', error);
                serverAdditionalCostSearchResults.value = {
                    ...serverAdditionalCostSearchResults.value,
                    [key]: [],
                };
            }
        } finally {
            if (seq === additionalCostSearchFetchSeq.value[key]) {
                isSearchingAdditionalCosts.value = {
                    ...isSearchingAdditionalCosts.value,
                    [key]: false,
                };
            }
        }
    }

    function additionalCostCombinedResults(rowId) {
        const query = additionalCostSearchValue(rowId).trim().toLowerCase();
        const key = additionalCostSearchKey(rowId);
        const serverResults = serverAdditionalCostSearchResults.value[key] ?? [];
        const selectedRow = (form.financial_term.additional_costs ?? []).find(
            (row) => additionalCostSearchKey(row.id) === key,
        );
        const selectedContractor = getContractorById(selectedRow?.contractor_id);

        if (query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
            const visibleContractors = additionalCostContractorOptions.value
                .filter((contractor) => {
                    const name = String(contractor.name ?? '').toLowerCase();
                    const fullName = String(contractor.full_name ?? '').toLowerCase();
                    const inn = String(contractor.inn ?? '').toLowerCase();

                    if (!query) {
                        return true;
                    }

                    return name.includes(query) || fullName.includes(query) || inn.includes(query);
                })
                .slice(0, 50);

            if (!selectedContractor || visibleContractors.some((contractor) => contractor.id === selectedContractor.id)) {
                return visibleContractors;
            }

            return [selectedContractor, ...visibleContractors.slice(0, 49)];
        }

        const serverIds = new Set(serverResults.map((contractor) => contractor.id));
        const localResults = additionalCostContractorOptions.value
            .filter((contractor) => {
                const name = String(contractor.name ?? '').toLowerCase();
                const fullName = String(contractor.full_name ?? '').toLowerCase();
                const inn = String(contractor.inn ?? '').toLowerCase();

                return name.includes(query) || fullName.includes(query) || inn.includes(query);
            })
            .filter((contractor) => !serverIds.has(contractor.id));

        const merged = [...serverResults, ...localResults].slice(0, 50);

        if (selectedContractor && !merged.some((contractor) => contractor.id === selectedContractor.id)) {
            return [selectedContractor, ...merged.slice(0, 49)];
        }

        return merged;
    }

    function selectAdditionalCostContractor(index, contractor) {
        ensureContractorInLocalList(contractor);

        const row = form.financial_term.additional_costs[index];
        if (!row) {
            return;
        }

        row.contractor_id = deps.normalizeNullableNumber(contractor.id);
        row.contractor_name = contractor.name ?? null;

        if (contractor.default_carrier_payment_form) {
            row.payment_form = normalizePaymentFormCode(contractor.default_carrier_payment_form, 'no_vat');
        }

        row.payment_schedule = contractorPaymentSchedule(contractor, 'default_carrier_payment_schedule', 'default_carrier_payment_term');
        setAdditionalCostSearchValue(row.id, contractor.name ?? '');
        setAdditionalCostResultsVisible(row.id, false);
    }

    const tabContext = {
        form,
        order: props.order,
        canEditFinancialFields,
        highlightRequiredField: deps.highlightRequiredField,
        currencyOptions: props.currencyOptions,
        paymentFormOptions: deps.paymentFormOptions,
        legContractorCosts,
        costRowTitle,
        contractorCostAmountLabel,
        contractorCostOrderDate,
        syncContractorCostsFromPerformers,
        crmFieldFluid: deps.crmFieldFluid,
        addAdditionalCostRow,
        removeAdditionalCostRow,
        additionalCostSearchValue,
        setAdditionalCostSearchValue,
        setAdditionalCostResultsVisible,
        hideAdditionalCostResults,
        isAdditionalCostResultsVisible,
        additionalCostCombinedResults,
        selectAdditionalCostContractor,
        additionalExpenseAmountFieldClass,
        bonusMultiplier: props.bonusMultiplier,
    };

    return {
        tabContext,
        canEditFinancialFields,
        legContractorCosts,
        additionalCostContractorOptions,
        syncContractorCostsFromPerformers,
        syncCarrierNormsByLegFromPerformers,
        addAdditionalCostRow,
        removeAdditionalCostRow,
        selectAdditionalCostContractor,
        costRowTitle,
        contractorCostAmountLabel,
        contractorCostOrderDate,
        additionalCostSearchValue,
        setAdditionalCostSearchValue,
        setAdditionalCostResultsVisible,
        hideAdditionalCostResults,
        isAdditionalCostResultsVisible,
        additionalCostCombinedResults,
    };
}


    function removeItem(collection, index) {
        collection.splice(index, 1);
        nextTick(() => {
            pruneEmptyLegPerformers();
        });
    }

    watch(carrierSearch, (newSearchValues, oldSearchValues) => {
        for (const [key, value] of Object.entries(newSearchValues)) {
            const oldValue = oldSearchValues[key] || '';
            if (value !== oldValue) {
                const match = key.match(/^(\w+)-(\d+(?:-\d+)?)$/);
                if (match) {
                    const [, kind, indexStr] = match;
                    queueCarrierSearch(kind, indexStr, value);
                }
            }
        }
    }, { deep: true });

    function queueCarrierSearch(kind, index, query) {
        const key = carrierSearchKey(kind, index);

        if (carrierSearchTimers.value[key]) {
            clearTimeout(carrierSearchTimers.value[key]);
        }

        if (query.trim().length < MIN_CONTRACTOR_QUERY_LENGTH) {
            carrierSearchAbortControllers.value[key]?.abort();
            carrierSearchFetchSeq.value = {
                ...carrierSearchFetchSeq.value,
                [key]: (carrierSearchFetchSeq.value[key] ?? 0) + 1,
            };
            serverCarrierSearchResults.value = {
                ...serverCarrierSearchResults.value,
                [key]: [],
            };
            isSearchingCarriers.value = {
                ...isSearchingCarriers.value,
                [key]: false,
            };

            return;
        }

        carrierSearchTimers.value[key] = setTimeout(async () => {
            await searchCarriers(kind, index, query.trim());
        }, 550);
    }

    async function searchCarriers(kind, index, query) {
        if (query.length < MIN_CONTRACTOR_QUERY_LENGTH) {
            const keyEmpty = carrierSearchKey(kind, index);
            serverCarrierSearchResults.value = {
                ...serverCarrierSearchResults.value,
                [keyEmpty]: [],
            };

            return;
        }

        const key = carrierSearchKey(kind, index);
        carrierSearchAbortControllers.value[key]?.abort();
        const ac = new AbortController();
        carrierSearchAbortControllers.value = {
            ...carrierSearchAbortControllers.value,
            [key]: ac,
        };
        const seq = (carrierSearchFetchSeq.value[key] ?? 0) + 1;
        carrierSearchFetchSeq.value = {
            ...carrierSearchFetchSeq.value,
            [key]: seq,
        };

        isSearchingCarriers.value = {
            ...isSearchingCarriers.value,
            [key]: true,
        };

        try {
            const response = await fetch(`${route('contractors.search')}?q=${encodeURIComponent(query)}&type=carrier&limit=100`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
                signal: ac.signal,
            });

            if (!response.ok) {
                throw new Error(`Carrier search failed with status ${response.status}`);
            }

            const data = await response.json();
            if (seq !== carrierSearchFetchSeq.value[key]) {
                return;
            }

            serverCarrierSearchResults.value = {
                ...serverCarrierSearchResults.value,
                [key]: data.contractors || [],
            };
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }

            console.error('Carrier search error', error);
            if (seq === carrierSearchFetchSeq.value[key]) {
                serverCarrierSearchResults.value = {
                    ...serverCarrierSearchResults.value,
                    [key]: [],
                };
            }
        } finally {
            if (seq === carrierSearchFetchSeq.value[key]) {
                isSearchingCarriers.value = {
                    ...isSearchingCarriers.value,
                    [key]: false,
                };
            }
        }
    }

    function registerInitialPerformerCarriers() {
        if (!Array.isArray(props.order?.performers)) {
            return;
        }

        props.order.performers.forEach((p, legIndex) => {
            const registerCarrier = (id, name) => {
                const normalizedId = normalizeNullableNumber(id);
                const normalizedName = name ? String(name).trim() : '';

                if (normalizedId !== null && normalizedName !== '') {
                    ensureContractorInLocalList({
                        id: normalizedId,
                        name: normalizedName,
                        type: 'carrier',
                        inn: null,
                        phone: null,
                        email: null,
                        is_own_company: false,
                    });
                }
            };

            if (p.carrier_mode === CARRIER_MODE_SPLIT && Array.isArray(p.split_carriers)) {
                p.split_carriers.forEach((slot, slotIndex) => {
                    registerCarrier(slot.contractor_id, slot.contractor_name);
                    setCarrierSearchValue(
                        'performer-slot',
                        `${legIndex}-${slotIndex}`,
                        splitCarrierSearchLabel(legIndex, slotIndex, slot.contractor_id),
                    );
                });

                return;
            }

            registerCarrier(p.contractor_id, p.contractor_name);
        });
    }

    function setupPerformersCarrierWatch() {
        watch(
            () => form.performers.map((performer) => ({
                stage: performer.stage,
                mode: performer.carrier_mode,
                contractor_id: performer.contractor_id,
                contractor_name: performer.contractor_name,
                split_carriers: (performer.split_carriers ?? []).map((slot) => [
                    slot.slot,
                    slot.contractor_id,
                    slot.contractor_name,
                    slot.fleet_vehicle_id,
                    slot.fleet_driver_id,
                ]),
            })),
            (performers, prev) => {
                performers.forEach((row, index) => {
                    const performer = form.performers[index];
                    if (!performer) {
                        return;
                    }

                    if (isPerformerSplit(performer)) {
                        performer.split_carriers.forEach((slot, slotIndex) => {
                            setCarrierSearchValue(
                                'performer-slot',
                                `${index}-${slotIndex}`,
                                splitCarrierSearchLabel(index, slotIndex, slot.contractor_id),
                            );

                            const prevSlot = prev?.[index]?.split_carriers?.[slotIndex];
                            const contractorChanged = prevSlot != null && prevSlot[1] !== slot.contractor_id;
                            if (contractorChanged) {
                                slot.fleet_vehicle_id = null;
                                slot.fleet_driver_id = null;
                            }
                            if (prev != null && contractorChanged) {
                                loadFleetOptionsForLeg(index, slotIndex);
                            }
                        });

                        return;
                    }

                    setCarrierSearchValue('performer', index, performerCarrierSearchLabel(index, row.contractor_id));
                    const costIndex = form.financial_term.contractors_costs.findIndex((cost) => stageMatches(cost.stage, row.stage));

                    if (costIndex !== -1) {
                        setCarrierSearchValue('cost', costIndex, performerCarrierSearchLabel(index, row.contractor_id));
                    }

                    const prevRow = prev?.[index];
                    if (prevRow && prevRow.contractor_id !== row.contractor_id) {
                        performer.fleet_vehicle_id = null;
                        performer.fleet_driver_id = null;
                    }

                    if (prev != null && prevRow && prevRow.contractor_id !== row.contractor_id) {
                        loadFleetOptionsForLeg(index);
                    }
                });
            },
            { deep: true, immediate: true },
        );
    }

    function setupInternationalTransportWatch() {
        watch(
            () => form.is_international_transport,
            (international) => {
                if (!international) {
                    form.route_points = form.route_points.filter((p) => p.type !== 'border_crossing');
                    normalizeRoutePointSequences();
                }
                borderCrossingLegPicker.value = '';
            },
        );
    }

    function initRouteTabSideEffects() {
        registerInitialPerformerCarriers();
        setupPerformersCarrierWatch();
        setupInternationalTransportWatch();
    }

    return {
        CARRIER_MODE_SINGLE,
        CARRIER_MODE_SPLIT,
        OWN_FLEET_CONTRACTOR_NAME,
        borderCrossingLegPicker,
        carrierSearch,
        showCarrierResults,
        fleetOptionsCache,
        addressSuggestions,
        draggedRoutePointIndex,
        dragOverRoutePointIndex,
        maxActualDate,
        routePointInlineBtn,
        routeChainLabel,
        hasBorderCrossingPoint,
        addPerformer,
        removePerformer,
        setPerformerCarrierMode,
        addSplitCarrier,
        removeSplitCarrier,
        stageLabel,
        toStageKey,
        stageMatches,
        onRoutePointLegChanged,
        carrierSearchKey,
        carrierSearchValue,
        setCarrierSearchValue,
        setCarrierResultsVisible,
        isCarrierResultsVisible,
        filteredCarrierResults,
        selectOwnFleetPerformer,
        selectOwnFleetSplitSlot,
        selectSplitPerformerContractor,
        clearSplitPerformerContractor,
        onSplitPerformerCarrierInput,
        restoreSplitPerformerCarrierSearch,
        selectPerformerContractor,
        clearPerformerContractor,
        onPerformerCarrierInput,
        restorePerformerCarrierSearch,
        loadFleetOptionsForLeg,
        fleetVehicleOptionsForLeg,
        fleetDriverOptionsForLeg,
        preloadFleetOptionsForPerformers,
        addRoutePointAfter,
        removeRoutePointAt,
        canRemoveRoutePoint,
        onBorderCrossingLegPickerChange,
        routePointTimeBlockHeading,
        routePointAddressHighlightValue,
        normalizeRoutePointSequences,
        syncRoutePointsFromPerformers,
        routePointTitle,
        routePointCombinedContact,
        setRoutePointCombinedContact,
        routePointsWithIndicesForLeg,
        routePointsDragEnabled,
        handleRoutePointDragStart,
        handleRoutePointDragOver,
        handleRoutePointDrop,
        handleRoutePointDragEnd,
        onRoutePointAddressInput,
        selectAddress,
        routePointCityValue,
        setRoutePointCity,
        syncRoutePointCityFromAddress,
        parsePerformerCarrierTarget,
        splitCarrierSearchLabel,
        performerCarrierSearchLabel,
        splitCarrierSlotLabel,
        isPerformerSplit,
        normalizeNullableNumber,
        highlightRequiredField,
        openCounterpartyModal,
        onPerformerActualDateInput,
        onSplitActualDateInput,
        wizardRouteLoadingHasActualDate,
        initRouteTabSideEffects,
        form,
        props,
        order: props.order,
        ownFleetContractor: props.ownFleetContractor,
        crmFieldFluid: deps.crmFieldFluid,
        crmSegmented: deps.crmSegmented,
        crmSegmentedBtn: deps.crmSegmentedBtn,
        crmSegmentedBtnActive: deps.crmSegmentedBtnActive,
    };
}

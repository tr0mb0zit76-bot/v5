import { toRaw } from 'vue';
import { router } from '@inertiajs/vue3';
import { serializeAdditionalCostsForSubmit, sumAdditionalCostsAmount } from '@/support/orderAdditionalCosts.js';
import { normalizePartyNormsPenalties } from '@/support/normsPenalties.js';
import {
    CARRIER_MODE_SINGLE,
    CARRIER_MODE_SPLIT,
    EXECUTION_MODE_OWN_FLEET,
    isOwnFleetExecutionMode,
} from '@/support/orderPerformers.js';
import { validateCargoPerformerAllocations } from '@/support/orderCargoPerformerAllocations.js';

export function useOrderWizardSubmit(deps) {
    const {
        form,
        props,
        activeTab,
        isEditing,
        isOrderFormEditable,
        saveAttempted,
        coreRequiredFieldsValid,
        hasSelectedCarrier,
        hasClientPrice,
        canShowMarkDisruptionButton,
        syncContractorCostsFromPerformers,
        needsCargoPerformerAllocationUi,
        cargoPerformerAllocationColumns,
        serializeCargoItemsForSubmit,
        selectedLoadingTypeCodes,
        normalizeNullableNumber,
        normalizePaymentFormCode,
        defaultClientPaymentForm,
        toStageKey,
        stageLabel,
        printFormTemplateSelection,
        orderBasicTermsDraft,
        activeIntakeDraftId,
        intakeDraftCommitted,
    } = deps;

    function normsPenaltiesForSubmit(row) {
        const n = normalizePartyNormsPenalties(row && typeof row === 'object' ? row : {});

        return {
            ...(n.stage ? { stage: n.stage } : {}),
            miss_amount: n.miss_amount,
            miss_currency: n.miss_currency,
            downtime_amount: n.downtime_amount,
            downtime_currency: n.downtime_currency,
            fine_amount: n.fine_amount,
            fine_currency: n.fine_currency,
            penalty_terms: n.penalty_terms,
            norm_loading_hours: n.norm_loading_hours,
            norm_customs_hours: n.norm_customs_hours,
            norm_unloading_hours: n.norm_unloading_hours,
        };
    }

    function buildSubmitPayload() {
        const rawFinancial = JSON.parse(JSON.stringify(toRaw(form.financial_term)));

        return {
            status: form.status,
            own_company_id: form.own_company_id,
            own_company_bank_account_id: form.own_company_bank_account_id && String(form.own_company_bank_account_id).trim() !== ''
                ? String(form.own_company_bank_account_id).trim()
                : null,
            client_id: form.client_id,
            order_owner_id: form.order_owner_id,
            responsible_id: form.order_owner_id,
            dispatcher_id: form.dispatcher_id,
            compensation_owner_percent: form.dispatcher_id ? form.compensation_owner_percent : 100,
            compensation_dispatcher_percent: form.dispatcher_id ? form.compensation_dispatcher_percent : 0,
            order_date: form.order_date,
            order_number: form.order_number,
            payment_terms: form.payment_terms,
            special_notes: form.special_notes,
            svh_name: form.svh_name,
            svh_address: form.svh_address,
            customs_post_code: form.customs_post_code,
            cargo_declared_sum: form.cargo_declared_sum,
            is_international_transport: Boolean(form.is_international_transport),
            additional_expenses: sumAdditionalCostsAmount(form.financial_term.additional_costs),
            additional_expenses_payment_date: form.financial_term.additional_costs[0]?.service_date || form.order_date || null,
            insurance: form.insurance,
            bonus: form.bonus,

            print_form_template_selection: {
                ...(props.order?.print_form_template_selection ?? {}),
                ...printFormTemplateSelection,
            },

            ...(function exportOrderBasicTermsPayload() {
                if (!orderBasicTermsDraft.dirty) {
                    return {};
                }

                const payload = {};

                if (orderBasicTermsDraft.customer_basic_terms !== undefined) {
                    payload.customer_basic_terms = orderBasicTermsDraft.customer_basic_terms ?? null;
                }

                if (orderBasicTermsDraft.carrier_basic_terms !== undefined) {
                    payload.carrier_basic_terms = orderBasicTermsDraft.carrier_basic_terms ?? null;
                }

                return payload;
            }()),

            performers: form.performers.map((performer) => {
                const carrierMode = performer.carrier_mode === CARRIER_MODE_SPLIT ? CARRIER_MODE_SPLIT : CARRIER_MODE_SINGLE;

                if (carrierMode === CARRIER_MODE_SPLIT) {
                    return {
                        stage: toStageKey(performer.stage) || 'leg_1',
                        carrier_mode: CARRIER_MODE_SPLIT,
                        contractor_id: null,
                        contractor_name: null,
                        fleet_vehicle_id: null,
                        fleet_driver_id: null,
                        loading_actual: null,
                        unloading_actual: null,
                        split_carriers: (performer.split_carriers ?? []).map((slot, index) => ({
                            slot: Number(slot?.slot ?? index + 1),
                            contractor_id: normalizeNullableNumber(slot.contractor_id),
                            contractor_name: slot.contractor_name ? String(slot.contractor_name).trim() || null : null,
                            fleet_vehicle_id: normalizeNullableNumber(slot.fleet_vehicle_id),
                            fleet_driver_id: normalizeNullableNumber(slot.fleet_driver_id),
                            execution_mode: isOwnFleetExecutionMode(slot?.execution_mode) ? EXECUTION_MODE_OWN_FLEET : null,
                            fleet_trip_id: normalizeNullableNumber(slot?.fleet_trip_id),
                            loading_actual: slot.loading_actual || null,
                            unloading_actual: slot.unloading_actual || null,
                        })),
                        loading_special_conditions: String(performer.loading_special_conditions ?? '').trim() || null,
                        unloading_special_conditions: String(performer.unloading_special_conditions ?? '').trim() || null,
                    };
                }

                return {
                    stage: toStageKey(performer.stage) || 'leg_1',
                    carrier_mode: CARRIER_MODE_SINGLE,
                    contractor_id: normalizeNullableNumber(performer.contractor_id),
                    contractor_name: performer.contractor_name ? String(performer.contractor_name).trim() || null : null,
                    fleet_vehicle_id: normalizeNullableNumber(performer.fleet_vehicle_id),
                    fleet_driver_id: normalizeNullableNumber(performer.fleet_driver_id),
                    execution_mode: isOwnFleetExecutionMode(performer?.execution_mode) ? EXECUTION_MODE_OWN_FLEET : null,
                    fleet_trip_id: normalizeNullableNumber(performer?.fleet_trip_id),
                    loading_actual: performer.loading_actual || null,
                    unloading_actual: performer.unloading_actual || null,
                    loading_special_conditions: String(performer.loading_special_conditions ?? '').trim() || null,
                    unloading_special_conditions: String(performer.unloading_special_conditions ?? '').trim() || null,
                    split_carriers: [],
                };
            }),

            route_points: form.route_points.map((point) => ({
                stage: toStageKey(point.stage) || 'leg_1',
                type: point.type,
                sequence: point.sequence,
                address: point.address,
                normalized_data: point.normalized_data || {},
                planned_date: point.planned_date,
                planned_time_from: point.planned_time_from || null,
                planned_time_to: point.planned_time_to || null,
                actual_date: point.actual_date,
                actual_time: point.actual_time || null,
                contact_person: point.contact_person,
                contact_phone: point.contact_phone,
                sender_name: point.sender_name,
                sender_contact: point.sender_contact,
                sender_phone: point.sender_phone,
                recipient_name: point.recipient_name,
                recipient_contact: point.recipient_contact,
                recipient_phone: point.recipient_phone,
            })),
            loading_types: selectedLoadingTypeCodes(),

            cargo_items: serializeCargoItemsForSubmit(),

            financial_term: {
                client_price: rawFinancial.client_price,
                client_currency: rawFinancial.client_currency,
                client_payment_form: normalizePaymentFormCode(rawFinancial.client_payment_form, defaultClientPaymentForm()),
                client_request_mode: rawFinancial.client_request_mode,
                client_payment_schedule: rawFinancial.client_payment_schedule || {},
                client_payment_terms: rawFinancial.client_payment_terms ?? '',
                contractors_costs: (rawFinancial.contractors_costs || [])
                    .filter((cost) => !cost?.is_additional && !String(cost?.stage ?? '').startsWith('additional'))
                    .map((cost) => ({
                        stage: cost.stage,
                        carrier_slot: cost.carrier_slot != null && cost.carrier_slot !== '' ? Number(cost.carrier_slot) : null,
                        contractor_id: normalizeNullableNumber(cost.contractor_id),
                        amount: cost.amount,
                        currency: cost.currency || 'RUB',
                        payment_form: normalizePaymentFormCode(cost.payment_form, 'no_vat'),
                        payment_schedule: cost.payment_schedule || {},
                        payment_terms: cost.payment_terms ?? '',
                        execution_mode: isOwnFleetExecutionMode(cost.execution_mode) ? EXECUTION_MODE_OWN_FLEET : null,
                    })),
                additional_costs: serializeAdditionalCostsForSubmit(rawFinancial.additional_costs || []),
                kpi_percent: rawFinancial.kpi_percent,
                client_norms_penalties: normsPenaltiesForSubmit(rawFinancial.client_norms_penalties),
                carrier_norms_by_leg: Array.isArray(rawFinancial.carrier_norms_by_leg)
                    ? rawFinancial.carrier_norms_by_leg.map((row) => normsPenaltiesForSubmit(row))
                    : [],
            },

            documents: form.documents
                .filter((document) => !document.is_print_workflow && document.flow !== 'print_template_workflow')
                .map((document) => ({
                    id: document.id ?? null,
                    type: document.type,
                    flow: 'uploaded',
                    party: document.party,
                    stage: document.stage,
                    contractor_id: document.contractor_id ?? null,
                    carrier_contractor_id: document.carrier_contractor_id ?? null,
                    requirement_key: document.requirement_key,
                    number: document.number,
                    document_date: document.document_date && String(document.document_date).trim() !== ''
                        ? document.document_date
                        : null,
                    status: 'signed',
                    template_id: document.template_id,
                    file: document.file instanceof File ? document.file : null,
                })),
            ...(activeIntakeDraftId.value && !isEditing.value
                ? { intake_draft_id: activeIntakeDraftId.value }
                : {}),
        };
    }

    function markIntakeDraftCommitted() {
        intakeDraftCommitted.value = true;
        activeIntakeDraftId.value = null;
    }

    function buildWizardSubmitOptions(onError, extra = {}) {
        return {
            preserveScroll: true,
            preserveState: true,
            onError,
            ...extra,
        };
    }

    function postWizardPayload(url, payload, onError, extraOptions = {}) {
        form.processing = true;

        router.post(url, payload, {
            ...buildWizardSubmitOptions(onError),
            ...extraOptions,
            onFinish: () => {
                form.processing = false;
                extraOptions.onFinish?.();
            },
        });
    }

    function markOrderDisruption() {
        if (!canShowMarkDisruptionButton.value || !props.order?.id) {
            return;
        }

        if (!window.confirm('Установить статус «Срыв»? Убедитесь, что по плечам ещё не указана фактическая дата погрузки.')) {
            return;
        }

        const previousStatus = form.status;
        form.status = 'disruption';

        submit({
            skipCoreValidation: true,
            revertStatusOnError: previousStatus,
        });
    }

    function submit(options = {}) {
        const skipCoreValidation = options.skipCoreValidation === true;
        const revertStatusOnError = options.revertStatusOnError ?? null;

        saveAttempted.value = true;

        if (isEditing.value && !isOrderFormEditable.value) {
            return;
        }

        if (!skipCoreValidation && !coreRequiredFieldsValid.value) {
            const errors = {};

            if (!form.client_id) {
                errors.client_id = 'Выберите заказчика.';
            }

            if (!form.order_date) {
                errors.order_date = 'Укажите дату заказа.';
            }

            if (!hasSelectedCarrier.value) {
                errors.performers = 'Укажите хотя бы одного перевозчика.';
                errors['financial_term.contractors_costs'] = 'Для сохранения нужен выбранный перевозчик.';
            }

            if (!hasClientPrice.value) {
                errors['financial_term.client_price'] = 'Укажите цену клиента больше 0.';
            }

            if (!form.client_id || !form.order_date) {
                activeTab.value = 'main';
            } else if (!hasSelectedCarrier.value) {
                activeTab.value = 'route';
            } else if (!hasClientPrice.value) {
                activeTab.value = 'finance';
            }

            form.clearErrors().setError(errors);

            return;
        }

        syncContractorCostsFromPerformers();

        if (!skipCoreValidation && needsCargoPerformerAllocationUi.value) {
            const allocationErrors = validateCargoPerformerAllocations(
                form.cargo_items,
                cargoPerformerAllocationColumns.value,
                true,
                stageLabel,
            );
            if (allocationErrors.length > 0) {
                activeTab.value = 'cargo';
                window.alert(allocationErrors.join('\n'));

                return;
            }
        }

        const hasNewDocumentFiles = form.documents.some((document) => document.file instanceof File);

        const handleRequestError = (errors) => {
            if (revertStatusOnError !== null) {
                form.status = revertStatusOnError;
            }

            const fieldErrors = errors && typeof errors === 'object' ? errors : {};
            const hasFieldErrors = Object.keys(fieldErrors).length > 0;

            if (hasFieldErrors) {
                form.clearErrors().setError(fieldErrors);

                return;
            }

            form.clearErrors();
            window.alert('Не удалось сохранить заказ. Обновите страницу и попробуйте снова.');
        };

        if (hasNewDocumentFiles) {
            const payload = buildSubmitPayload();
            const jsonBody = {
                ...payload,
                documents: payload.documents.map(({ file: _file, ...meta }) => meta),
            };
            const formData = new FormData();
            formData.append('order_payload', JSON.stringify(jsonBody));
            payload.documents.forEach((doc, index) => {
                if (doc.file instanceof File) {
                    formData.append(`document_file_${index}`, doc.file);
                }
            });

            const url = isEditing.value ? route('orders.save', props.order.id) : route('orders.store');
            postWizardPayload(url, formData, handleRequestError, {
                forceFormData: true,
                onSuccess: () => {
                    if (!isEditing.value) {
                        markIntakeDraftCommitted();
                    }
                },
            });

            return;
        }

        const payload = buildSubmitPayload();

        if (isEditing.value) {
            if (!props.order?.id) {
                return;
            }

            postWizardPayload(route('orders.save', props.order.id), payload, handleRequestError);

            return;
        }

        postWizardPayload(route('orders.store'), payload, handleRequestError, {
            onSuccess: markIntakeDraftCommitted,
        });
    }

    return {
        buildSubmitPayload,
        buildWizardSubmitOptions,
        postWizardPayload,
        submit,
        markOrderDisruption,
        normsPenaltiesForSubmit,
        markIntakeDraftCommitted,
    };
}

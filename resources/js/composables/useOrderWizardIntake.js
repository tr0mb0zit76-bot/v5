import { ref } from 'vue';

export function useOrderWizardIntake(deps) {
    const {
        form,
        isEditing,
        activeTab,
        blankRoutePoint,
        blankOrder,
        normalizeRoutePointSequences,
        normalizeCargoItem,
        normalizeContractorCost,
        normalizePaymentSchedule,
        mergeFinancialTermFromIntake: mergeFinancialTermFromIntakeFn,
        syncContractorCostsFromPerformers,
        getContractorById,
        stageMatches,
        toStageKey,
        setCarrierSearchValue,
    } = deps;

    const intakeFileInput = ref(null);
    const intakeSelectedFile = ref(null);
    const intakeLoading = ref(false);
    const intakePreview = ref(null);
    const intakeError = ref('');
    const activeIntakeDraftId = ref(null);
    const intakeDraftCommitted = ref(false);

    function getCsrfToken() {
        if (typeof document === 'undefined') {
            return '';
        }

        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    }

    async function activateIntakeDraftLearning(draftId) {
        try {
            await fetch(route('orders.intake.learning.activate', { draft: draftId }), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
        } catch (error) {
            console.error('intake learning activate failed', error);
        }
    }

    function discardActiveIntakeLearning() {
        if (isEditing.value || intakeDraftCommitted.value || !activeIntakeDraftId.value) {
            return;
        }

        const id = activeIntakeDraftId.value;
        activeIntakeDraftId.value = null;

        try {
            fetch(route('orders.intake.learning.discard', { draft: id }), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                keepalive: true,
            });
        } catch (error) {
            console.error('intake learning discard failed', error);
        }
    }

    function mergeFinancialTermFromIntake(patchTerm) {
        if (mergeFinancialTermFromIntakeFn) {
            mergeFinancialTermFromIntakeFn(patchTerm);

            return;
        }

        if (!patchTerm || typeof patchTerm !== 'object') {
            return;
        }

        const current = form.financial_term;
        const merged = {
            ...current,
            ...patchTerm,
        };

        merged.client_payment_schedule = normalizePaymentSchedule(
            patchTerm.client_payment_schedule !== undefined
                ? patchTerm.client_payment_schedule
                : current.client_payment_schedule,
        );

        if (Array.isArray(patchTerm.contractors_costs)) {
            merged.contractors_costs = patchTerm.contractors_costs.map((row) => normalizeContractorCost(row));
        } else if (Array.isArray(merged.contractors_costs)) {
            merged.contractors_costs = merged.contractors_costs.map((row) => normalizeContractorCost(row));
        }

        form.financial_term = merged;
    }

    function onIntakeFileSelected(event) {
        intakeError.value = '';
        intakePreview.value = null;
        intakeSelectedFile.value = event.target.files?.[0] ?? null;
    }

    async function extractIntakeDraft() {
        if (!intakeSelectedFile.value) {
            return;
        }

        intakeLoading.value = true;
        intakeError.value = '';
        intakePreview.value = null;

        const body = new FormData();
        body.append('file', intakeSelectedFile.value);

        try {
            const response = await fetch(route('orders.intake.extract'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body,
            });

            const payload = await response.json();

            if (!response.ok) {
                intakeError.value = payload?.message
                    ?? payload?.errors?.file?.[0]
                    ?? 'Не удалось распознать заявку.';

                return;
            }

            intakePreview.value = payload;
        } catch (error) {
            console.error('order intake extract failed', error);
            intakeError.value = 'Ошибка сети при распознавании заявки.';
        } finally {
            intakeLoading.value = false;
        }
    }

    function applyIntakeDraft() {
        const patch = intakePreview.value?.wizard_patch;
        if (!patch || typeof patch !== 'object') {
            return;
        }

        Object.entries(patch).forEach(([key, value]) => {
            if (key === 'route_points' && Array.isArray(value)) {
                form.route_points = value.map((point, index) => ({
                    ...blankRoutePoint(point.type ?? 'loading', Number(point.sequence ?? (index + 1)), toStageKey(point.stage ?? 'leg_1') || 'leg_1'),
                    ...point,
                    stage: toStageKey(point.stage ?? 'leg_1') || 'leg_1',
                    sequence: Number(point.sequence ?? (index + 1)),
                    normalized_data: point.normalized_data || {},
                }));
                normalizeRoutePointSequences();

                return;
            }

            if (key === 'cargo_items' && Array.isArray(value) && value[0]) {
                const base = form.cargo_items[0] ?? blankOrder().cargo_items[0];
                form.cargo_items[0] = normalizeCargoItem({ ...base, ...value[0] });

                return;
            }

            if (key === 'financial_term' && value && typeof value === 'object') {
                mergeFinancialTermFromIntake(value);

                return;
            }

            if (key === 'carrier_contractor_id' && value != null && form.performers[0]) {
                const carrierId = Number(value);
                form.performers[0].contractor_id = carrierId;
                const carrierName = patch.carrier_contractor_name ?? getContractorById(carrierId)?.name ?? '';
                if (carrierName) {
                    form.performers[0].contractor_name = carrierName;
                    setCarrierSearchValue('performer', 0, carrierName);
                }
                const costIndex = form.financial_term.contractors_costs.findIndex((cost) => stageMatches(cost.stage, form.performers[0].stage));
                if (costIndex !== -1) {
                    form.financial_term.contractors_costs[costIndex].contractor_id = carrierId;
                }

                return;
            }

            if (Object.prototype.hasOwnProperty.call(form, key)) {
                form[key] = value;
            }
        });

        syncContractorCostsFromPerformers();

        const draftId = Number(intakePreview.value?.draft_id ?? 0);
        if (draftId > 0 && !isEditing.value) {
            activeIntakeDraftId.value = draftId;
            intakeDraftCommitted.value = false;
            void activateIntakeDraftLearning(draftId);
        }

        activeTab.value = 'main';
    }

    function applyIntakeDraftPayload(payload) {
        if (!payload?.wizard_patch || typeof payload.wizard_patch !== 'object') {
            return;
        }

        intakePreview.value = {
            draft_id: payload.draft_id,
            confidence: payload.confidence,
            preview: payload.preview ?? [],
            warnings: payload.warnings ?? [],
            wizard_patch: payload.wizard_patch,
            matched_contractors: payload.matched_contractors ?? [],
        };
        applyIntakeDraft();
    }

    async function loadAndApplyIntakeDraftById(draftId) {
        const id = Number(draftId);
        if (!Number.isFinite(id) || id <= 0) {
            return;
        }

        intakeError.value = '';

        try {
            const response = await fetch(route('orders.intake.draft', { draft: id }), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json();

            if (!response.ok) {
                intakeError.value = data?.message ?? 'Не удалось загрузить черновик заявки.';

                return;
            }

            applyIntakeDraftPayload(data);
            activeIntakeDraftId.value = id;
            intakeDraftCommitted.value = false;
            void activateIntakeDraftLearning(id);
        } catch (error) {
            console.error('order intake draft load failed', error);
            intakeError.value = 'Ошибка сети при загрузке черновика.';
        }
    }

    return {
        intakeFileInput,
        intakeSelectedFile,
        intakeLoading,
        intakePreview,
        intakeError,
        activeIntakeDraftId,
        intakeDraftCommitted,
        onIntakeFileSelected,
        extractIntakeDraft,
        applyIntakeDraft,
        loadAndApplyIntakeDraftById,
        discardActiveIntakeLearning,
    };
}

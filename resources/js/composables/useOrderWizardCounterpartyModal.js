import { nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    ensureContractorPartyAutofill,
    isCompleteContractorInn,
    normalizedContractorInn,
} from '@/support/contractorPartyAutofill.js';
import { isVirtualOwnFleetContractor } from '@/support/ownFleetCatalog.js';

export function useOrderWizardCounterpartyModal(deps) {
    const { contractors, ownCompanyOptions, applyContractor } = deps;

    const showCounterpartyModal = ref(false);
    const counterpartyNameInput = ref(null);
    const inlineContractorSaving = ref(false);
    const inlineContractorError = ref('');
    const counterpartyInnLookupTimer = ref(null);
    const counterpartyLastAutofilledInn = ref('');
    const counterpartyTarget = ref({ kind: 'client', index: null });
    const counterpartyForm = useForm({
        name: '',
        inn: '',
        kpp: '',
        address: '',
        phone: '',
        email: '',
        contact_person: '',
        type: 'customer',
    });

    async function lookupCounterpartyByInn() {
        const normalizedInn = normalizedContractorInn(counterpartyForm.inn);
        if (!isCompleteContractorInn(normalizedInn)) {
            return;
        }

        if (normalizedInn === counterpartyLastAutofilledInn.value && String(counterpartyForm.name ?? '').trim() !== '') {
            return;
        }

        counterpartyForm.inn = normalizedInn;
        const filled = await ensureContractorPartyAutofill(counterpartyForm, {
            force: normalizedInn !== counterpartyLastAutofilledInn.value,
        });

        if (filled) {
            counterpartyLastAutofilledInn.value = normalizedInn;
            inlineContractorError.value = '';
        }
    }

    watch(() => counterpartyForm.inn, (inn) => {
        if (!showCounterpartyModal.value) {
            return;
        }

        clearTimeout(counterpartyInnLookupTimer.value);

        const normalizedInn = normalizedContractorInn(inn);
        if (isCompleteContractorInn(normalizedInn) && counterpartyForm.inn !== normalizedInn) {
            counterpartyForm.inn = normalizedInn;
        }

        if (!isCompleteContractorInn(normalizedInn)) {
            counterpartyLastAutofilledInn.value = '';

            return;
        }

        if (normalizedInn === counterpartyLastAutofilledInn.value && String(counterpartyForm.name ?? '').trim() !== '') {
            return;
        }

        counterpartyInnLookupTimer.value = window.setTimeout(() => {
            void lookupCounterpartyByInn();
        }, 500);
    });

    async function openCounterpartyModal(options = {}) {
        inlineContractorError.value = '';
        counterpartyForm.clearErrors();
        counterpartyForm.reset();
        counterpartyLastAutofilledInn.value = '';
        counterpartyTarget.value = {
            kind: options.kind === 'performer-slot'
                ? 'performer-slot'
                : (options.kind === 'performer' ? 'performer' : 'client'),
            index: options.index ?? null,
        };
        counterpartyForm.type = options.type === 'carrier'
            ? 'carrier'
            : (options.type === 'contractor' ? 'contractor' : 'customer');
        showCounterpartyModal.value = true;

        await nextTick();
        counterpartyNameInput.value?.focus?.();
    }

    function closeCounterpartyModal() {
        clearTimeout(counterpartyInnLookupTimer.value);
        showCounterpartyModal.value = false;
        inlineContractorError.value = '';
        counterpartyForm.clearErrors();
        counterpartyLastAutofilledInn.value = '';
        counterpartyTarget.value = { kind: 'client', index: null };
    }

    async function createInlineCounterparty() {
        inlineContractorError.value = '';
        counterpartyForm.clearErrors();
        inlineContractorSaving.value = true;

        try {
            if (isCompleteContractorInn(counterpartyForm.inn) && !String(counterpartyForm.name ?? '').trim()) {
                const filled = await ensureContractorPartyAutofill(counterpartyForm);
                if (!filled) {
                    inlineContractorError.value = 'Не удалось получить данные по ИНН. Укажите название вручную.';

                    return;
                }
            }

            if (!String(counterpartyForm.name ?? '').trim()) {
                inlineContractorError.value = 'Укажите название контрагента или корректный ИНН.';

                return;
            }

            const response = await fetch(route('orders.contractors.store'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(counterpartyForm.data()),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (response.status === 422 && payload.errors) {
                    const first = Object.values(payload.errors).flat()[0];
                    inlineContractorError.value = first || 'Проверьте введённые данные.';
                } else {
                    inlineContractorError.value = payload.message || 'Не удалось создать контрагента.';
                }

                return;
            }

            const contractor = payload.contractor;

            contractors.value.unshift(contractor);
            if (contractor.is_own_company && !isVirtualOwnFleetContractor(contractor)) {
                ownCompanyOptions.value.unshift(contractor);
            }

            applyContractor(counterpartyTarget.value, contractor);

            counterpartyForm.reset();
            counterpartyForm.type = 'customer';
            showCounterpartyModal.value = false;
            counterpartyLastAutofilledInn.value = '';
            counterpartyTarget.value = { kind: 'client', index: null };
        } catch (error) {
            console.error(error);
            inlineContractorError.value = 'Не удалось создать контрагента.';
        } finally {
            inlineContractorSaving.value = false;
        }
    }

    return {
        showCounterpartyModal,
        counterpartyNameInput,
        inlineContractorSaving,
        inlineContractorError,
        counterpartyTarget,
        counterpartyForm,
        openCounterpartyModal,
        closeCounterpartyModal,
        createInlineCounterparty,
    };
}

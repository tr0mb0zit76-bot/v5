import { nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { isVirtualOwnFleetContractor } from '@/support/ownFleetCatalog.js';

export function useOrderWizardCounterpartyModal(deps) {
    const { contractors, ownCompanyOptions, applyContractor } = deps;

    const showCounterpartyModal = ref(false);
    const counterpartyNameInput = ref(null);
    const inlineContractorSaving = ref(false);
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

    async function openCounterpartyModal(options = {}) {
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
        showCounterpartyModal.value = false;
        counterpartyTarget.value = { kind: 'client', index: null };
    }

    async function createInlineCounterparty() {
        inlineContractorSaving.value = true;

        try {
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

            if (!response.ok) {
                throw new Error(`Inline contractor creation failed with status ${response.status}`);
            }

            const payload = await response.json();
            const contractor = payload.contractor;

            contractors.value.unshift(contractor);
            if (contractor.is_own_company && !isVirtualOwnFleetContractor(contractor)) {
                ownCompanyOptions.value.unshift(contractor);
            }

            applyContractor(counterpartyTarget.value, contractor);

            counterpartyForm.reset();
            counterpartyForm.type = 'customer';
            showCounterpartyModal.value = false;
            counterpartyTarget.value = { kind: 'client', index: null };
        } catch (error) {
            console.error(error);
        } finally {
            inlineContractorSaving.value = false;
        }
    }

    return {
        showCounterpartyModal,
        counterpartyNameInput,
        inlineContractorSaving,
        counterpartyTarget,
        counterpartyForm,
        openCounterpartyModal,
        closeCounterpartyModal,
        createInlineCounterparty,
    };
}

export function normalizedContractorInn(value) {
    return String(value ?? '').replace(/\D/g, '');
}

export function isCompleteContractorInn(value) {
    const normalized = normalizedContractorInn(value);

    return normalized.length === 10 || normalized.length === 12;
}

export async function fetchContractorPartySuggestion(query) {
    const trimmed = String(query ?? '').trim();
    if (trimmed.length < 2) {
        return null;
    }

    const response = await fetch(`${route('contractors.suggest-party')}?query=${encodeURIComponent(trimmed)}`, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        return null;
    }

    const data = await response.json();
    const suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];

    return suggestions[0] ?? null;
}

/**
 * @param {Record<string, unknown>} target
 */
export function applyContractorPartySuggestion(target, suggestion) {
    const party = suggestion?.data ?? {};

    target.name = suggestion?.value ?? target.name ?? '';
    target.inn = party.inn != null && party.inn !== '' ? String(party.inn) : (target.inn ?? '');
    target.kpp = party.kpp != null && party.kpp !== '' ? String(party.kpp) : (target.kpp ?? '');

    if ('address' in target) {
        target.address = party.address?.value ?? target.address ?? '';
    }

    if ('full_name' in target) {
        target.full_name = party.name?.full_with_opf ?? target.full_name ?? '';
    }

    if ('ogrn' in target) {
        target.ogrn = party.ogrn != null && party.ogrn !== '' ? String(party.ogrn) : (target.ogrn ?? '');
    }

    if ('okpo' in target) {
        target.okpo = party.okpo != null && party.okpo !== '' ? String(party.okpo) : (target.okpo ?? '');
    }

    if ('legal_address' in target) {
        const address = party.address?.value ?? '';
        target.legal_address = address || target.legal_address || '';
        if ('actual_address' in target && !target.actual_address) {
            target.actual_address = address;
        }
        if ('postal_address' in target && !target.postal_address) {
            target.postal_address = address;
        }
    }

    if (party.type === 'INDIVIDUAL' && 'legal_form' in target) {
        target.legal_form = 'ip';
    }
}

export async function ensureContractorPartyAutofill(target, options = {}) {
    const normalizedInn = normalizedContractorInn(target.inn);
    const hasName = String(target.name ?? '').trim() !== '';

    if (!isCompleteContractorInn(normalizedInn) || hasName) {
        return hasName;
    }

    const suggestion = await fetchContractorPartySuggestion(normalizedInn);
    if (suggestion) {
        applyContractorPartySuggestion(target, suggestion);
    }

    return String(target.name ?? '').trim() !== '';
}

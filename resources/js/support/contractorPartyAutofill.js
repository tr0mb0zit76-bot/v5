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
 * @param {Record<string, unknown>|null|undefined} suggestion
 */
export function resolveContractorNameFromSuggestion(suggestion) {
    const party = suggestion?.data ?? {};
    const partyName = party.name ?? {};

    const candidates = [
        suggestion?.value,
        partyName.short_with_opf,
        partyName.full_with_opf,
        partyName.short,
        partyName.full,
    ];

    for (const candidate of candidates) {
        const normalized = String(candidate ?? '').trim();
        if (normalized !== '') {
            return normalized;
        }
    }

    return '';
}

/**
 * @param {Record<string, unknown>} target
 */
export function applyContractorPartySuggestion(target, suggestion) {
    const party = suggestion?.data ?? {};

    const resolvedName = resolveContractorNameFromSuggestion(suggestion);
    if (resolvedName !== '') {
        target.name = resolvedName;
    }
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

/**
 * @param {{ inn?: string|null, name?: string|null, ignoreId?: number|null }} params
 * @returns {Promise<{
 *   duplicate: boolean,
 *   matched_by: string|null,
 *   message: string|null,
 *   contractor_id: number|null,
 *   contractor_name: string|null,
 *   can_open: boolean,
 *   open_url: string|null
 * }>}
 */
export async function fetchContractorDuplicateCheck(params = {}) {
    const searchParams = new URLSearchParams();

    if (params.inn) {
        searchParams.set('inn', String(params.inn));
    }

    if (params.name) {
        searchParams.set('name', String(params.name));
    }

    if (params.ignoreId) {
        searchParams.set('ignore_id', String(params.ignoreId));
    }

    const query = searchParams.toString();
    if (query === '') {
        return {
            duplicate: false,
            matched_by: null,
            message: null,
            contractor_id: null,
            contractor_name: null,
            can_open: false,
            open_url: null,
        };
    }

    const response = await fetch(`${route('contractors.duplicate-check')}?${query}`, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        return {
            duplicate: false,
            matched_by: null,
            message: null,
            contractor_id: null,
            contractor_name: null,
            can_open: false,
            open_url: null,
        };
    }

    return response.json();
}

export function contractorPartyProfileIncomplete(target) {
    if ('full_name' in target && String(target.full_name ?? '').trim() === '') {
        return true;
    }

    return String(target.name ?? '').trim() === '';
}

export async function ensureContractorPartyAutofill(target, options = {}) {
    const normalizedInn = normalizedContractorInn(target.inn);
    const hasName = String(target.name ?? '').trim() !== '';

    if (!isCompleteContractorInn(normalizedInn)) {
        return hasName;
    }

    if (!options.force && hasName && !contractorPartyProfileIncomplete(target)) {
        return true;
    }

    const suggestion = await fetchContractorPartySuggestion(normalizedInn);
    if (suggestion) {
        applyContractorPartySuggestion(target, suggestion);
    }

    return String(target.name ?? '').trim() !== '';
}

const TRANSPORT_SCOPE = {
    ANY: 'any',
    DOMESTIC: 'domestic',
    INTERNATIONAL: 'international',
};

function normalizeContractorIds(contractorIds) {
    if (!Array.isArray(contractorIds)) {
        return [];
    }

    return [...new Set(contractorIds.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0))];
}

function matchesTransportScope(scope, isInternationalTransport) {
    const normalized = String(scope ?? TRANSPORT_SCOPE.ANY);

    if (normalized === TRANSPORT_SCOPE.DOMESTIC) {
        return !isInternationalTransport;
    }

    if (normalized === TRANSPORT_SCOPE.INTERNATIONAL) {
        return Boolean(isInternationalTransport);
    }

    return true;
}

function matchesOwnCompany(templateOwnCompanyId, orderOwnCompanyId) {
    if (templateOwnCompanyId == null || templateOwnCompanyId === '') {
        return true;
    }

    if (orderOwnCompanyId == null || orderOwnCompanyId === '') {
        return false;
    }

    return Number(templateOwnCompanyId) === Number(orderOwnCompanyId);
}

function isDualOrientedTemplate(template) {
    const party = String(template?.party ?? 'internal');

    if (party === 'customer' || party === 'carrier') {
        return false;
    }

    const hasCarrier = template?.has_carrier_basic_terms;
    const hasCustomer = template?.has_customer_basic_terms;

    if (hasCarrier === undefined || hasCustomer === undefined) {
        return false;
    }

    return Boolean(hasCustomer) && Boolean(hasCarrier);
}

export function effectivePrintParty(template) {
    const party = String(template?.party ?? 'internal');

    if (party === 'customer' || party === 'carrier') {
        return party;
    }

    if (isDualOrientedTemplate(template)) {
        return 'dual';
    }

    if (isCustomerOrientedTemplate(template)) {
        return 'customer';
    }

    if (isCarrierOrientedTemplate(template)) {
        return 'carrier';
    }

    return 'internal';
}

export function matchesPrintSlotParty(template, slotParty) {
    if (!slotParty) {
        return true;
    }

    const effective = effectivePrintParty(template);

    if (effective === 'dual') {
        return false;
    }

    if (slotParty === 'customer') {
        return effective === 'customer' || effective === 'internal';
    }

    if (slotParty === 'carrier') {
        return effective === 'carrier' || effective === 'internal';
    }

    return true;
}

function matchesParty(templateParty, slotParty) {
    if (!slotParty) {
        return true;
    }

    const party = String(templateParty ?? 'internal');

    return party === slotParty || party === 'internal';
}

function isCarrierOrientedTemplate(template) {
    const party = String(template?.party ?? 'internal');

    if (party === 'carrier') {
        return true;
    }

    if (party === 'customer') {
        return false;
    }

    const hasCarrier = Boolean(template?.has_carrier_basic_terms);
    const hasCustomer = Boolean(template?.has_customer_basic_terms);

    return hasCarrier && !hasCustomer;
}

function isCustomerOrientedTemplate(template) {
    const party = String(template?.party ?? 'internal');

    if (party === 'customer') {
        return true;
    }

    if (party === 'carrier') {
        return false;
    }

    const hasCarrier = Boolean(template?.has_carrier_basic_terms);
    const hasCustomer = Boolean(template?.has_customer_basic_terms);

    return hasCustomer && !hasCarrier;
}

function matchesContractor(templateContractorId, contractorIds) {
    if (templateContractorId == null || templateContractorId === '') {
        return true;
    }

    return normalizeContractorIds(contractorIds).includes(Number(templateContractorId));
}

export function templateSpecificityScore(template, context) {
    let score = template?.is_default ? 1000 : 0;
    const ownCompanyId = context?.ownCompanyId ?? null;
    const isInternationalTransport = Boolean(context?.isInternationalTransport);

    const templateOwnCompanyId = template?.own_company_id ?? null;
    if (templateOwnCompanyId != null && ownCompanyId != null && Number(templateOwnCompanyId) === Number(ownCompanyId)) {
        score += 200;
    } else if (templateOwnCompanyId == null) {
        score += 20;
    }

    const scope = String(template?.transport_scope ?? TRANSPORT_SCOPE.ANY);
    if (scope === TRANSPORT_SCOPE.DOMESTIC && !isInternationalTransport) {
        score += 100;
    } else if (scope === TRANSPORT_SCOPE.INTERNATIONAL && isInternationalTransport) {
        score += 100;
    } else if (scope === TRANSPORT_SCOPE.ANY) {
        score += 10;
    }

    if (template?.contractor_id != null) {
        score += 50;
    }

    return score;
}

export function templateMatchesOrderContext(template, context) {
    if (!template || template.is_active === false || !template.file_path) {
        return false;
    }

    if ((template.entity_type ?? 'order') !== 'order') {
        return false;
    }

    const ownCompanyId = context?.ownCompanyId ?? null;
    const isInternationalTransport = Boolean(context?.isInternationalTransport);
    const party = context?.party ?? null;
    const contractorIds = context?.contractorIds ?? [];

    if (!matchesPrintSlotParty(template, party)) {
        return false;
    }

    if (!matchesOwnCompany(template.own_company_id, ownCompanyId)) {
        return false;
    }

    if (!matchesTransportScope(template.transport_scope, isInternationalTransport)) {
        return false;
    }

    return matchesContractor(template.contractor_id, contractorIds);
}

export function preferOwnCompanySpecificTemplates(templates, ownCompanyId) {
    const list = Array.isArray(templates) ? templates : [];

    if (ownCompanyId == null || ownCompanyId === '') {
        return list;
    }

    const companyId = Number(ownCompanyId);
    const specific = list.filter(
        (template) => template?.own_company_id != null
            && Number(template.own_company_id) === companyId,
    );

    if (specific.length === 0) {
        return list;
    }

    return specific;
}

export function filterPrintFormTemplates(catalog, context, party) {
    const list = Array.isArray(catalog) ? catalog : [];
    const ownCompanyId = context?.ownCompanyId ?? null;

    const filtered = list
        .filter((template) => templateMatchesOrderContext(template, { ...context, party }))
        .sort((left, right) => templateSpecificityScore(right, context) - templateSpecificityScore(left, context));

    return preferOwnCompanySpecificTemplates(filtered, ownCompanyId);
}

export function defaultTemplateForContext(catalog, context, party) {
    const filtered = filterPrintFormTemplates(catalog, context, party);

    if (filtered.length === 0) {
        return null;
    }

    const defaults = filtered.filter((template) => template.is_default);

    if (defaults.length > 0) {
        return defaults.sort(
            (left, right) => templateSpecificityScore(right, context) - templateSpecificityScore(left, context),
        )[0];
    }

    return filtered[0];
}

export function buildPrintFormTemplateContext({
    ownCompanyId = null,
    isInternationalTransport = false,
    performers = [],
    additionalCosts = [],
    customerId = null,
    carrierId = null,
}) {
    const contractorIds = [];

    if (customerId) {
        contractorIds.push(Number(customerId));
    }

    if (carrierId) {
        contractorIds.push(Number(carrierId));
    }

    (Array.isArray(performers) ? performers : []).forEach((performer) => {
        if (performer?.contractor_id) {
            contractorIds.push(Number(performer.contractor_id));
        }
    });

    (Array.isArray(additionalCosts) ? additionalCosts : []).forEach((row) => {
        if (row?.contractor_id) {
            contractorIds.push(Number(row.contractor_id));
        }
    });

    return {
        ownCompanyId: ownCompanyId != null && ownCompanyId !== '' ? Number(ownCompanyId) : null,
        isInternationalTransport: Boolean(isInternationalTransport),
        contractorIds: normalizeContractorIds(contractorIds),
    };
}

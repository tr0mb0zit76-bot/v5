import { documentMatchesRequirementRule } from '@/support/orderDocumentRequirementSlots.js';
import { documentTypeDisplayLabel } from '@/support/orderDocumentTypes.js';

/**
 * Строки таблицы учёта документов: обязательные слоты + доп. подписанные файлы.
 *
 * @param {Array<Record<string, unknown>>} signedDocuments
 * @param {Array<Record<string, unknown>>} requiredDocumentRules
 * @param {Array<Record<string, unknown>>} requiredDocumentChecklist
 * @param {Array<{value: string, label: string}>} documentTypeOptions
 */
export function buildRegistryTableRows(
    signedDocuments,
    requiredDocumentRules,
    requiredDocumentChecklist = [],
    documentTypeOptions = [],
) {
    const rules = Array.isArray(requiredDocumentRules) ? requiredDocumentRules : [];
    const signed = Array.isArray(signedDocuments) ? signedDocuments : [];
    const checklist = Array.isArray(requiredDocumentChecklist) ? requiredDocumentChecklist : [];
    const typeLabels = new Map(
        (Array.isArray(documentTypeOptions) ? documentTypeOptions : []).map((opt) => [opt.value, opt.label]),
    );

    const signedById = new Map(
        signed.filter((doc) => doc?.id).map((doc) => [Number(doc.id), doc]),
    );
    const usedSignedIds = new Set();

    const rows = rules.map((rule) => {
        const checklistItem = checklist.find((item) => item?.key === rule.key);
        const completed = Boolean(checklistItem?.completed);
        const matchedId = checklistItem?.matched_document_id
            ? Number(checklistItem.matched_document_id)
            : null;

        let matchedSigned = matchedId ? signedById.get(matchedId) : null;

        if (!matchedSigned) {
            matchedSigned = signed.find(
                (doc) => doc?.id
                    && !usedSignedIds.has(doc.id)
                    && documentMatchesRequirementRule(doc, rule),
            ) ?? null;
        }

        if (matchedSigned?.id) {
            if (!rule.allows_multiple) {
                usedSignedIds.add(matchedSigned.id);
            }

            return {
                ...matchedSigned,
                requirement_key: rule.key,
                requirement_label: rule.label,
                type_label: registryTypeLabel(matchedSigned, rule, typeLabels),
                checklist_completed: completed,
                is_placeholder: false,
            };
        }

        const defaultType = Array.isArray(rule.accepted_types) ? rule.accepted_types[0] : 'other';

        return {
            _localKey: `requirement-${rule.key}`,
            requirement_key: rule.key,
            requirement_label: rule.label,
            party: rule.party,
            type: defaultType,
            type_label: rule.label,
            number: null,
            document_date: null,
            original_name: null,
            uploaded_file_preview_url: null,
            checklist_completed: false,
            is_placeholder: true,
        };
    });

    signed
        .filter((doc) => doc?.id && !usedSignedIds.has(doc.id))
        .forEach((doc) => {
            rows.push({
                ...doc,
                requirement_key: doc.requirement_key ?? null,
                requirement_label: null,
                type_label: typeLabels.get(doc.type) ?? doc.type,
                checklist_completed: false,
                is_placeholder: false,
            });
        });

    return rows;
}

/**
 * @param {Record<string, unknown>} document
 * @param {Record<string, unknown>} rule
 * @param {Map<string, string>} typeLabels
 */
function registryTypeLabel(document, rule, typeLabels) {
    const transportLabel = documentTypeDisplayLabel(String(document?.type ?? ''), typeLabels);
    const base = transportLabel !== String(document?.type ?? '') ? transportLabel : (typeLabels.get(String(document?.type ?? '')) ?? rule.label);
    const counterparty = rule.counterparty_label ? String(rule.counterparty_label) : '';

    if (counterparty !== '' && String(rule.party) !== 'internal') {
        return `${base} · ${counterparty}`;
    }

    return base;
}

/** @deprecated use documentMatchesRequirementRule */
export function documentMatchesRule(document, rule) {
    return documentMatchesRequirementRule(document, rule);
}

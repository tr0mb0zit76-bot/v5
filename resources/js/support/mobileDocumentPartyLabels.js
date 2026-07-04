const PARTY_LABELS = {
    customer: 'Заказчик',
    carrier: 'Перевозчик',
    internal: 'Внутренний',
};

export function documentPartyLabel(party) {
    return PARTY_LABELS[String(party ?? '').toLowerCase()] ?? String(party ?? '—');
}

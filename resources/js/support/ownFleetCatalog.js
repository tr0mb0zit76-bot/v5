/** Имя виртуального перевозчика «Собственный парк» (не юрлицо в заказе). */
export const OWN_FLEET_CONTRACTOR_NAME = 'Собственный парк';

export const OWN_FLEET_EXECUTION_MODE = 'own_fleet';

export function isVirtualOwnFleetContractor(contractor) {
    if (!contractor?.name) {
        return false;
    }

    return String(contractor.name).trim() === OWN_FLEET_CONTRACTOR_NAME;
}

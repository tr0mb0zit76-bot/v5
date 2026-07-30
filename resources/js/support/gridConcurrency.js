/**
 * Optimistic concurrency helpers for grid inline edits.
 */

export function concurrencyPayload(row) {
    const updatedAt = row?.updated_at ?? null;

    return updatedAt ? { expected_updated_at: updatedAt } : {};
}

export function isConcurrencyConflict(error) {
    return error?.response?.status === 409
        || error?.response?.data?.code === 'concurrency_conflict';
}

export function concurrencyConflictMessage(error) {
    return error?.response?.data?.message
        ?? 'Запись изменена другим пользователем. Обновите данные и повторите.';
}

/**
 * @param {import('@inertiajs/vue3').VisitOptions} [extra]
 */
export function inertiaConcurrencyHandlers(onConflict) {
    return {
        onError: (errors) => {
            const message = errors?.concurrency
                ?? errors?.expected_updated_at
                ?? null;
            if (message) {
                window.alert(Array.isArray(message) ? message[0] : message);
                onConflict?.();
            }
        },
        onFinish: () => {},
    };
}

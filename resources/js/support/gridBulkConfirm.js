/** Порог: выше — спрашиваем подтверждение перед массовым действием. */
export const GRID_BULK_CONFIRM_THRESHOLD = 20;

/**
 * Confirm для опасных/широких bulk-операций (назначение, удаление и т.п.).
 *
 * @param {number} count
 * @param {string} actionDescription краткое описание, напр. «сменить ответственного у задач»
 * @returns {boolean}
 */
export function confirmLargeBulkGridAction(count, actionDescription) {
  const n = Number(count);

  if (!Number.isFinite(n) || n <= GRID_BULK_CONFIRM_THRESHOLD) {
    return true;
  }

  if (typeof window === 'undefined' || typeof window.confirm !== 'function') {
    return true;
  }

  return window.confirm(
    `Вы собираетесь ${actionDescription}: ${n} шт. Продолжить?`,
  );
}

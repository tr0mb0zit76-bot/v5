/**
 * Дополнительно к `preventDefaultOnContextMenu: true` в gridOptions AG Grid.
 * Повесьте на внешнюю панель грида (включая нижний скроллбар): @contextmenu.capture="..."
 */
export function suppressNativeContextMenuCapture(event) {
    /** Только предотвращаем системное меню; не вызываем stopPropagation на capture — иначе событие не дойдёт до ячеек AG Grid. */
    event.preventDefault();
}

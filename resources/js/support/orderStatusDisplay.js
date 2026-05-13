/** @typedef {{ label: string, colorClass: string, paths: string[], circles?: { cx: string, cy: string, r: string }[], filled?: boolean }} OrderStatusIconMeta */

const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * Подписи из мастера / старые текстовые значения в БД → код статуса для иконки.
 * @type {Record<string, string>}
 */
const ORDER_STATUS_RU_LABEL_TO_CODE = {
    'новый заказ': 'new',
    'выполняется': 'in_progress',
    документы: 'documents',
    оплата: 'payment',
    закрыта: 'closed',
    отменена: 'cancelled',
    черновик: 'draft',
    'черновик (legacy)': 'draft',
    'на согласовании (legacy)': 'pending',
    'подтвержден (legacy)': 'confirmed',
    'завершен (legacy)': 'completed',
    'на согласовании': 'pending',
    подтвержден: 'confirmed',
    завершен: 'completed',
    завершён: 'completed',
    'завершён (legacy)': 'completed',
    срыв: 'disruption',
    'статус срыв': 'disruption',
};

function normalizeRuStatusKey(raw) {
    if (raw === null || raw === undefined || raw === '') {
        return null;
    }

    const k = String(raw).trim().toLowerCase();

    return ORDER_STATUS_RU_LABEL_TO_CODE[k] ?? null;
}

/** Иконки (stroke), цвет через currentColor на родителе. @type {Record<string, OrderStatusIconMeta>} */
export const ORDER_STATUS_ICON_META = {
    new: {
        label: 'Новый заказ',
        colorClass: 'text-sky-600 dark:text-sky-400',
        paths: [
            'm12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z',
            'M5 3v4',
            'M3 5h4',
            'M19 17v4',
            'M17 19h4',
        ],
    },
    in_progress: {
        label: 'Выполняется',
        colorClass: 'text-amber-600 dark:text-amber-400',
        paths: [
            'M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2',
            'M15 18H9',
            'M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14',
        ],
        circles: [{ cx: '17', cy: '18', r: '2' }, { cx: '7', cy: '18', r: '2' }],
    },
    documents: {
        label: 'Документы',
        colorClass: 'text-violet-600 dark:text-violet-400',
        paths: [
            'M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z',
            'M14 2v6h6',
            'M16 13H8',
            'M16 17H8',
            'M10 9H8',
        ],
    },
    payment: {
        label: 'Оплата',
        colorClass: 'text-emerald-600 dark:text-emerald-400',
        paths: [
            'M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1',
            'M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4',
        ],
    },
    closed: {
        label: 'Завершено',
        colorClass: 'text-green-700 dark:text-green-400',
        paths: [
            'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z',
            'm9 12 2 2 4-4',
        ],
    },
    cancelled: {
        label: 'Отменена',
        colorClass: 'text-rose-600 dark:text-rose-400',
        paths: ['M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z', 'm15 9-6 6', 'm9 9 6 6'],
    },
    disruption: {
        label: 'Срыв',
        colorClass: 'text-red-600 dark:text-red-400',
        filled: true,
        paths: [
            'M12 2.2 19.8 6.1 19.8 17.9 12 21.8 4.2 17.9 4.2 6.1 12 2.2Z',
            'M9.2 9.2h5.6v5.6H9.2z',
        ],
    },
    draft: {
        label: 'Черновик',
        colorClass: 'text-zinc-500 dark:text-zinc-400',
        paths: ['M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z', 'm15 5 4 4'],
    },
    pending: {
        label: 'На согласовании (legacy)',
        colorClass: 'text-orange-600 dark:text-orange-400',
        paths: [
            'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z',
            'M12 6v6l4 2',
        ],
    },
    confirmed: {
        label: 'Подтвержден (legacy)',
        colorClass: 'text-blue-600 dark:text-blue-400',
        paths: [
            'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z',
            'm9 12 2 2 4-4',
        ],
    },
    completed: {
        label: 'Завершен (legacy)',
        colorClass: 'text-green-600 dark:text-green-400',
        paths: ['M20 6 L9 17 l-5 -5'],
    },
};

/**
 * @param {OrderStatusIconMeta} meta
 * @returns {SVGSVGElement}
 */
export function buildOrderStatusIconSvg(meta) {
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('width', '20');
    svg.setAttribute('height', '20');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', meta.filled ? 'currentColor' : 'none');
    svg.setAttribute('stroke', meta.filled ? 'none' : 'currentColor');
    svg.setAttribute('stroke-width', meta.filled ? '0' : '2');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('class', 'shrink-0');
    meta.paths.forEach((d, index) => {
        const p = document.createElementNS(SVG_NS, 'path');
        p.setAttribute('d', d);
        if (meta.filled) {
            p.setAttribute('fill', index === 0 ? 'currentColor' : '#ffffff');
        }
        svg.appendChild(p);
    });
    (meta.circles ?? []).forEach((c) => {
        const circle = document.createElementNS(SVG_NS, 'circle');
        circle.setAttribute('cx', c.cx);
        circle.setAttribute('cy', c.cy);
        circle.setAttribute('r', c.r);
        if (!meta.filled) {
            svg.appendChild(circle);
        }
    });

    return svg;
}

export function pickStatusString(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return String(value).trim();
}

/**
 * Код для иконки: учитывает status_text (COALESCE(manual, status)), а также сырые поля строки.
 * @param {{ manual_status?: string|null, status?: string|null }|null} row
 * @param {string|null|undefined} statusTextCell
 */
export function resolveOrderStatusIconKey(row, statusTextCell) {
    const text = pickStatusString(statusTextCell);
    const manual = row ? pickStatusString(row.manual_status) : null;
    const machine = row ? pickStatusString(row.status) : null;

    const hasMeta = (k) => Boolean(k && ORDER_STATUS_ICON_META[k]);

    if (hasMeta(manual)) {
        return manual;
    }

    if (manual && !hasMeta(manual) && hasMeta(machine)) {
        return machine;
    }

    if (hasMeta(machine)) {
        return machine;
    }

    if (hasMeta(text)) {
        return text;
    }

    const fromRu =
        normalizeRuStatusKey(text) ?? normalizeRuStatusKey(manual) ?? normalizeRuStatusKey(machine);

    if (fromRu && hasMeta(fromRu)) {
        return fromRu;
    }

    return text ?? manual ?? machine ?? '';
}

export function resolveOrderStatusLabel(cellValue, row) {
    if (cellValue === null || cellValue === undefined || cellValue === '') {
        return '—';
    }

    const key = resolveOrderStatusIconKey(row ?? null, cellValue);
    const meta = key && ORDER_STATUS_ICON_META[key];

    if (meta) {
        return meta.label;
    }

    return String(cellValue);
}

/**
 * @param {object} params AG Grid cell renderer params
 */
export function renderOrderStatusTextCell(params) {
    const displayText = pickStatusString(params.value);
    const wrap = document.createElement('div');
    wrap.className = 'flex h-full w-full items-center justify-center';
    wrap.setAttribute('role', 'presentation');

    if (!displayText) {
        wrap.textContent = '—';
        wrap.classList.add('text-zinc-400');
        wrap.title = '';

        return wrap;
    }

    const iconKey = resolveOrderStatusIconKey(params.data ?? null, params.value);
    const meta = iconKey && ORDER_STATUS_ICON_META[iconKey];

    if (!meta) {
        wrap.textContent = displayText;
        wrap.title = displayText;
        wrap.classList.add('max-w-full', 'truncate', 'px-1', 'text-xs', 'text-zinc-600', 'dark:text-zinc-300');
        wrap.setAttribute('role', 'img');
        wrap.setAttribute('aria-label', displayText);

        return wrap;
    }

    const manual = pickStatusString(params.data?.manual_status);
    const machine = pickStatusString(params.data?.status);
    const titleParts = [meta.label];
    if (manual && manual !== iconKey && manual !== machine) {
        titleParts.push(manual);
    }
    const title = titleParts.filter(Boolean).join(' · ');

    wrap.title = title;
    wrap.setAttribute('role', 'img');
    wrap.setAttribute('aria-label', title);
    const span = document.createElement('span');
    span.className = `inline-flex ${meta.colorClass}`;
    span.appendChild(buildOrderStatusIconSvg(meta));
    wrap.appendChild(span);

    return wrap;
}

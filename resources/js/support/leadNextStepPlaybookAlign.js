/**
 * Мягкая проверка: открытый шаг похож на цель этапа / next_move playbook.
 * Без морфологии — пересечение стеммленных токенов (≥4) после нормализации.
 *
 * @param {string[]} taskTitles
 * @param {string[]} playbookHints
 * @returns {boolean} true = шаг выглядит вне playbook
 */
export function isNextStepOffPlaybook(taskTitles, playbookHints) {
    const titles = (taskTitles ?? []).map((t) => String(t ?? '').trim()).filter(Boolean);
    const hints = (playbookHints ?? []).map((h) => String(h ?? '').trim()).filter(Boolean);

    if (titles.length === 0 || hints.length === 0) {
        return false;
    }

    const hintStems = new Set(uniqueTokens(hints.join(' ')).map(roughStem));
    if (hintStems.size === 0) {
        return false;
    }

    const taskStems = uniqueTokens(titles.join(' ')).map(roughStem);
    const overlap = taskStems.some((stem) => hintStems.has(stem) || [...hintStems].some((hint) => stemsClose(stem, hint)));

    return !overlap;
}

/**
 * @param {string} a
 * @param {string} b
 */
function stemsClose(a, b) {
    if (a === b) {
        return true;
    }
    if (a.length < 4 || b.length < 4) {
        return false;
    }

    return a.startsWith(b) || b.startsWith(a);
}

/**
 * Грубая стемма для RU: срезаем одно частое окончание.
 *
 * @param {string} token
 * @returns {string}
 */
function roughStem(token) {
    const endings = [
        'иями', 'ями', 'ами', 'иях', 'ях', 'ией', 'ыми', 'ими',
        'ого', 'его', 'ому', 'ему', 'ых', 'их', 'ые', 'ие', 'ая', 'яя', 'ое', 'ее',
        'ов', 'ев', 'ам', 'ям', 'ах', 'ом', 'ем', 'ой', 'ей',
        'ы', 'и', 'а', 'я', 'у', 'ю', 'е', 'о', 'ь',
    ];

    for (const ending of endings) {
        if (token.length - ending.length >= 4 && token.endsWith(ending)) {
            return token.slice(0, -ending.length);
        }
    }

    return token;
}

/**
 * @param {string} text
 * @returns {string[]}
 */
function uniqueTokens(text) {
    const seen = new Set();
    const out = [];

    for (const raw of normalize(text).split(' ')) {
        if (raw.length < 4 || seen.has(raw)) {
            continue;
        }
        seen.add(raw);
        out.push(raw);
    }

    return out;
}

/**
 * @param {string} text
 * @returns {string}
 */
function normalize(text) {
    return String(text ?? '')
        .toLowerCase()
        .replace(/ё/g, 'е')
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

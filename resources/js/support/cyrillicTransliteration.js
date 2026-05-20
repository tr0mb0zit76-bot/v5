/**
 * Транслитерация кириллицы в латиницу (ориентир — паспортная/международная схема для печатных форм).
 *
 * @param {string|null|undefined} input
 * @returns {string}
 */
export function transliterateCyrillic(input) {
    const source = String(input ?? '');

    if (source === '') {
        return '';
    }

    const pairs = [
        ['Щ', 'Shch'], ['щ', 'shch'],
        ['Ш', 'Sh'], ['ш', 'sh'],
        ['Ч', 'Ch'], ['ч', 'ch'],
        ['Ж', 'Zh'], ['ж', 'zh'],
        ['Ю', 'Yu'], ['ю', 'yu'],
        ['Я', 'Ya'], ['я', 'ya'],
        ['Ё', 'Yo'], ['ё', 'yo'],
        ['Х', 'Kh'], ['х', 'kh'],
        ['Ц', 'Ts'], ['ц', 'ts'],
        ['Ъ', ''], ['ъ', ''],
        ['Ь', ''], ['ь', ''],
        ['Э', 'E'], ['э', 'e'],
        ['А', 'A'], ['а', 'a'],
        ['Б', 'B'], ['б', 'b'],
        ['В', 'V'], ['в', 'v'],
        ['Г', 'G'], ['г', 'g'],
        ['Д', 'D'], ['д', 'd'],
        ['Е', 'E'], ['е', 'e'],
        ['З', 'Z'], ['з', 'z'],
        ['И', 'I'], ['и', 'i'],
        ['Й', 'Y'], ['й', 'y'],
        ['К', 'K'], ['к', 'k'],
        ['Л', 'L'], ['л', 'l'],
        ['М', 'M'], ['м', 'm'],
        ['Н', 'N'], ['н', 'n'],
        ['О', 'O'], ['о', 'o'],
        ['П', 'P'], ['п', 'p'],
        ['Р', 'R'], ['р', 'r'],
        ['С', 'S'], ['с', 's'],
        ['Т', 'T'], ['т', 't'],
        ['У', 'U'], ['у', 'u'],
        ['Ф', 'F'], ['ф', 'f'],
        ['Ы', 'Y'], ['ы', 'y'],
    ];

    let out = '';
    let index = 0;

    while (index < source.length) {
        let matched = false;

        for (const [from, to] of pairs) {
            if (source.startsWith(from, index)) {
                out += to;
                index += from.length;
                matched = true;

                break;
            }
        }

        if (!matched) {
            out += source[index];
            index += 1;
        }
    }

    return out.replace(/\s+/g, ' ').trim();
}

/**
 * @param {string|null|undefined} target
 * @param {string|null|undefined} source
 * @param {boolean} overwrite
 */
export function transliteratedFieldValue(target, source, overwrite = false) {
    const current = String(target ?? '').trim();
    const nextSource = String(source ?? '').trim();

    if (nextSource === '') {
        return current;
    }

    if (current !== '' && !overwrite) {
        return current;
    }

    return transliterateCyrillic(nextSource);
}

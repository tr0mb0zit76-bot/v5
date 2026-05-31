/**
 * GFM-таблицы должны идти подряд без пустых строк между строками.
 * Строки с отступом (tab / 4 пробела) marked воспринимает как code block, а не table.
 */
export function stripMarkdownBom(markdown) {
    if (!markdown) {
        return markdown;
    }

    return markdown.charCodeAt(0) === 0xfeff ? markdown.slice(1) : markdown;
}

export function isMarkdownTableRow(line) {
    const trimmed = line.trim();

    return trimmed.startsWith('|') && trimmed.includes('|', 1);
}

export function normalizeMarkdownTables(markdown) {
    if (!markdown || !markdown.includes('|')) {
        return stripMarkdownBom(markdown ?? '');
    }

    const lines = stripMarkdownBom(markdown).replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
    const result = [];
    let index = 0;

    while (index < lines.length) {
        const line = lines[index];

        if (!isMarkdownTableRow(line)) {
            result.push(line);
            index += 1;

            continue;
        }

        const tableLines = [];

        while (index < lines.length) {
            const current = lines[index];

            if (current.trim() === '') {
                let nextIndex = index + 1;

                while (nextIndex < lines.length && lines[nextIndex].trim() === '') {
                    nextIndex += 1;
                }

                if (nextIndex < lines.length && isMarkdownTableRow(lines[nextIndex])) {
                    index = nextIndex;

                    continue;
                }

                break;
            }

            if (!isMarkdownTableRow(current)) {
                break;
            }

            tableLines.push(current.trim());
            index += 1;
        }

        if (tableLines.length > 0) {
            result.push(...tableLines);
        }
    }

    return result.join('\n');
}

export function prepareSalesBookMarkdown(markdown) {
    return normalizeMarkdownTables(stripMarkdownBom(markdown ?? ''));
}

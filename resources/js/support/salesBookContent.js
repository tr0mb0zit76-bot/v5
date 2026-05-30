export const SALES_BOOK_CHILD_LINKS_START = '<!-- sales-book:child-links -->';

export const SALES_BOOK_CHILD_LINKS_END = '<!-- /sales-book:child-links -->';

/**
 * Убирает автоблок ссылок на дочерние страницы — в редакторе он только мешает и ломается при правках.
 */
export function stripSalesBookChildLinksBlock(content) {
    const pattern = new RegExp(
        `\\n?${escapeRegExp(SALES_BOOK_CHILD_LINKS_START)}.*?${escapeRegExp(SALES_BOOK_CHILD_LINKS_END)}`,
        's',
    );

    return (content || '').replace(pattern, '').replace(/\s+$/, '');
}

function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

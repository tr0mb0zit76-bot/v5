/**
 * Full EmailMaker / .eml document → body fragment + CSS for GrapesJS canvas.
 * Mirrors App\Support\ProposalHtmlEmailDocumentNormalizer.
 *
 * @param {string} html
 * @param {string|null|undefined} existingCss
 * @returns {{ body: string, css: string, fontUrls: string[] }}
 */
export function normalizeProposalEmailHtml(html, existingCss = null) {
    let source = String(html ?? '').trim();
    const cssChunks = [];
    const existing = String(existingCss ?? '').trim();
    if (existing !== '') {
        cssChunks.push(existing);
    }

    const fontUrls = extractFontStylesheetUrls(source);

    const styleRe = /<style\b[^>]*>([\s\S]*?)<\/style>/gi;
    let styleMatch = styleRe.exec(source);
    while (styleMatch) {
        const chunk = String(styleMatch[1] ?? '').trim();
        if (chunk !== '') {
            cssChunks.push(chunk);
        }
        styleMatch = styleRe.exec(source);
    }
    source = source.replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '');

    const body = extractBodyInnerHtml(source);

    for (const url of fontUrls) {
        if (!cssChunks.some((chunk) => chunk.includes(url))) {
            cssChunks.unshift(`@import url('${url.replace(/'/g, "\\'")}');`);
        }
    }

    return {
        body: body.trim(),
        css: uniqueCssChunks(cssChunks).join('\n'),
        fontUrls,
    };
}

/**
 * @param {string} html
 */
export function extractBodyInnerHtml(html) {
    const bodyMatch = String(html ?? '').match(/<body\b[^>]*>([\s\S]*)<\/body>/i);
    if (bodyMatch) {
        return String(bodyMatch[1] ?? '').trim();
    }

    return String(html ?? '')
        .replace(/<!DOCTYPE[^>]*>/gi, '')
        .replace(/<\/?html\b[^>]*>/gi, '')
        .replace(/<head\b[^>]*>[\s\S]*?<\/head>/gi, '')
        .trim();
}

/**
 * @param {string} html
 * @returns {string[]}
 */
export function extractFontStylesheetUrls(html) {
    const urls = [];
    const linkRe = /<link\b[^>]*>/gi;
    let match = linkRe.exec(html);
    while (match) {
        const tag = match[0];
        if (!/rel\s*=\s*["']stylesheet["']/i.test(tag)) {
            match = linkRe.exec(html);
            continue;
        }
        const hrefMatch = tag.match(/href\s*=\s*["']([^"']+)["']/i);
        const href = hrefMatch ? String(hrefMatch[1] ?? '').trim() : '';
        if (href === '') {
            match = linkRe.exec(html);
            continue;
        }
        const lower = href.toLowerCase();
        if (
            lower.includes('fonts.googleapis.com')
            || lower.includes('fonts.gstatic.com')
            || /font/i.test(tag)
        ) {
            urls.push(href);
        }
        match = linkRe.exec(html);
    }

    return [...new Set(urls)];
}

/**
 * @param {string} body
 * @param {string} css
 * @param {string[]} fontUrls
 */
export function buildProposalEmailPreviewDocument(body, css = '', fontUrls = []) {
    const fontLinks = (Array.isArray(fontUrls) ? fontUrls : [])
        .map((url) => `<link rel="stylesheet" href="${escapeAttr(url)}">`)
        .join('\n');
    const styleBlock = css ? `<style>${css}</style>` : '';

    return `<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
${fontLinks}
${styleBlock}
</head>
<body style="margin:0;padding:0;">
${body}
</body>
</html>`;
}

/**
 * @param {string[]} chunks
 */
function uniqueCssChunks(chunks) {
    const seen = new Set();
    const out = [];
    for (const chunk of chunks) {
        if (seen.has(chunk)) {
            continue;
        }
        seen.add(chunk);
        out.push(chunk);
    }

    return out;
}

/**
 * @param {string} value
 */
function escapeAttr(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;');
}

import { marked } from 'marked';

marked.setOptions({
    gfm: true,
    breaks: true,
});

/**
 * Markdown → HTML для ответов ИИ-ассистента (таблицы, списки, жирный текст).
 */
export function renderAgentMarkdown(text) {
    const source = String(text ?? '').trim();

    if (source === '') {
        return '';
    }

    const html = marked.parse(source);

    return typeof html === 'string' ? html : '';
}

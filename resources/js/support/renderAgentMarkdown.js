import DOMPurify from 'dompurify';
import { Marked } from 'marked';

/**
 * Отдельный экземпляр marked: @tiptap/markdown вызывает marked.use() на глобальном
 * singleton и регистрирует токены вроде taskList без HTML-renderer — ломает parse() в панели ИИ.
 */
const agentMarked = new Marked({
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

    const html = agentMarked.parse(source);

    if (typeof html !== 'string') {
        return '';
    }

    return DOMPurify.sanitize(html, { USE_PROFILES: { html: true } });
}

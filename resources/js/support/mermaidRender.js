import mermaid from 'mermaid';

let initialized = false;
let lastTheme = null;
let idCounter = 0;

function resolveTheme() {
    if (typeof document === 'undefined') {
        return 'default';
    }

    return document.documentElement.classList.contains('dark') ? 'dark' : 'default';
}

function ensureMermaid() {
    const theme = resolveTheme();

    if (initialized && lastTheme === theme) {
        return;
    }

    mermaid.initialize({
        startOnLoad: false,
        securityLevel: 'strict',
        theme,
    });

    initialized = true;
    lastTheme = theme;
}

export function isMermaidLanguage(language) {
    return String(language ?? '').trim().toLowerCase() === 'mermaid';
}

export async function renderMermaidDiagram(source) {
    const trimmed = String(source ?? '').trim();

    if (trimmed === '') {
        return '';
    }

    ensureMermaid();

    const id = `sales-book-mermaid-${++idCounter}`;

    try {
        const { svg } = await mermaid.render(id, trimmed);

        return svg;
    } catch (error) {
        if (typeof mermaid.parse === 'function') {
            await mermaid.parse(trimmed);
        }

        throw error;
    }
}

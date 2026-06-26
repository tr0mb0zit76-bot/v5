let initialized = false;
let lastTheme = null;
let idCounter = 0;
let mermaidApi = null;
let mermaidLoadPromise = null;

function resolveTheme() {
    if (typeof document === 'undefined') {
        return 'default';
    }

    return document.documentElement.classList.contains('dark') ? 'dark' : 'default';
}

async function loadMermaid() {
    if (mermaidApi) {
        return mermaidApi;
    }

    if (!mermaidLoadPromise) {
        mermaidLoadPromise = import('mermaid')
            .then((module) => {
                mermaidApi = module.default ?? module;

                return mermaidApi;
            })
            .catch((error) => {
                mermaidLoadPromise = null;

                throw error;
            });
    }

    return mermaidLoadPromise;
}

async function ensureMermaid() {
    const mermaid = await loadMermaid();
    const theme = resolveTheme();

    if (initialized && lastTheme === theme) {
        return mermaid;
    }

    mermaid.initialize({
        startOnLoad: false,
        securityLevel: 'strict',
        theme,
    });

    initialized = true;
    lastTheme = theme;

    return mermaid;
}

export function isMermaidLanguage(language) {
    return String(language ?? '').trim().toLowerCase() === 'mermaid';
}

export async function renderMermaidDiagram(source) {
    const trimmed = String(source ?? '').trim();

    if (trimmed === '') {
        return '';
    }

    const mermaid = await ensureMermaid();

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

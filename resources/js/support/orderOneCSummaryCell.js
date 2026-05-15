const el = (tag) => document.createElement(tag);

export class OrderOneCSummaryCellRenderer {
    init(params) {
        this.params = params;
        this.eGui = el('div');
        this.eGui.className = 'flex h-full min-h-[2.5rem] items-start gap-2 py-1 pr-1';

        const text = el('d' + 'iv');
        text.className = 'min-w-0 flex-1 whitespace-normal text-xs leading-snug text-zinc-700 dark:text-zinc-200';
        text.textContent = params.value ?? '—';
        this.eGui.appendChild(text);

        const copyButton = document.createElement('button');
        copyButton.type = 'button';
        copyButton.title = 'Скопировать';
        copyButton.className = 'shrink-0 rounded-md border border-zinc-200 px-2 py-1 text-[10px] font-medium text-zinc-600 transition hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-500 dark:hover:bg-zinc-800';
        copyButton.textContent = 'Копировать';
        copyButton.addEventListener('click', (event) => {
            event.stopPropagation();
            void copySummaryText(params.value);
        });
        this.eGui.appendChild(copyButton);
    }

    getGui() {
        return this.eGui;
    }

    refresh(params) {
        this.params = params;
        const textNode = this.eGui?.firstChild;
        if (textNode) {
            textNode.textContent = params.value ?? '—';
        }

        return true;
    }
}

async function copySummaryText(value) {
    const text = value == null ? '' : String(value).trim();
    if (text === '') {
        return;
    }

    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
}

export function renderOrderOneCSummaryCell(params) {
    return new OrderOneCSummaryCellRenderer();
}

<template>
    <div class="proposal-grapes-shell flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
        <div class="proposal-grapes-root flex min-h-0 flex-1 flex-col overflow-hidden">
            <div ref="containerRef" class="min-h-0 flex-1" />
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import grapesjs from 'grapesjs';
import grapesjsPresetNewsletter from 'grapesjs-preset-newsletter';
import ru from 'grapesjs/locale/ru';
import 'grapesjs/dist/css/grapes.min.css';

const props = defineProps({
    htmlBody: {
        type: String,
        default: '',
    },
    cssInline: {
        type: String,
        default: '',
    },
});

const containerRef = ref(null);
/** @type {import('grapesjs').Editor | null} */
let editor = null;
let canvasLoaded = false;

onMounted(() => {
    if (!containerRef.value) {
        return;
    }

    editor = grapesjs.init({
        container: containerRef.value,
        height: '100%',
        width: 'auto',
        fromElement: false,
        storageManager: false,
        noticeOnUnload: false,
        i18n: {
            locale: 'ru',
            detectLocale: false,
            messages: { ru },
        },
        plugins: [grapesjsPresetNewsletter],
        pluginsOpts: {
            [grapesjsPresetNewsletter]: {
                modalTitleImport: 'Импорт HTML',
                modalTitleExport: 'Экспорт HTML',
                modalBtnImport: 'Импорт',
                textCleanCanvas: 'Очистить холст? Все изменения будут потеряны.',
                showBlocksOnLoad: true,
            },
        },
        deviceManager: {
            devices: [
                { name: 'Desktop', width: '' },
                { name: 'Tablet', width: '768px', widthMedia: '992px' },
                { name: 'Mobile', width: '320px', widthMedia: '480px' },
            ],
        },
        canvas: {
            styles: [
                'https://fonts.googleapis.com/css2?family=Arial&display=swap',
            ],
        },
    });

    editor.on('load', () => {
        if (canvasLoaded) {
            return;
        }

        canvasLoaded = true;

        const html = props.htmlBody?.trim() ?? '';
        const css = props.cssInline?.trim() ?? '';

        if (html !== '') {
            editor.setComponents(html);
        }

        if (css !== '') {
            editor.setStyle(css);
        }
    });
});

onBeforeUnmount(() => {
    editor?.destroy();
    editor = null;
});

function syncFromEditor() {
    if (!editor) {
        return {
            html_body: props.htmlBody,
            css_inline: props.cssInline,
        };
    }

    return {
        html_body: editor.getHtml(),
        css_inline: editor.getCss({ avoidProtected: true }),
    };
}

function insertVariable(path) {
    if (!editor) {
        return;
    }

    const token = `{${path}}`;
    const selected = editor.getSelected();

    if (selected && (selected.is('text') || selected.get('type') === 'text')) {
        const current = selected.get('content') ?? '';
        selected.set('content', `${current}${token}`);
        editor.select(selected);
        return;
    }

    const parent = selected && selected.get('droppable') ? selected : editor.getWrapper();
    const added = parent.append({
        type: 'text',
        content: token,
        style: {
            padding: '10px',
            'font-family': 'Arial, sans-serif',
        },
    });

    const component = Array.isArray(added) ? added[0] : added;
    if (component) {
        editor.select(component);
    }
}

defineExpose({
    syncFromEditor,
    insertVariable,
});
</script>

<style>
.proposal-grapes-root {
    --proposal-gjs-border: #e4e4e7;
    --proposal-gjs-muted: #71717a;
    --proposal-gjs-panel: #ffffff;
    --proposal-gjs-panel-soft: #f8fafc;
    --proposal-gjs-text: #27272a;
    --proposal-gjs-accent: #059669;
    --proposal-gjs-accent-soft: #ecfdf5;
    /* Единая ширина правой колонки для всех панелей GrapesJS (commands / options / views) */
    --gjs-left-width: 14rem;
    --proposal-gjs-view-tabs-height: 2.75rem;
    min-height: 28rem;
    height: 100%;
}

.proposal-grapes-root .gjs-editor {
    height: 100% !important;
    min-height: 28rem;
    border: 0;
    background: var(--proposal-gjs-panel-soft);
    color: var(--proposal-gjs-text);
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

/* Кнопки тулбара не вылезают на вкладки блоков справа */
.proposal-grapes-root .gjs-pn-commands {
    overflow: hidden;
}

.proposal-grapes-root .gjs-pn-commands .gjs-pn-buttons {
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: 1px;
}

.proposal-grapes-root .gjs-pn-options {
    max-width: calc(100% - var(--gjs-left-width));
}

.proposal-grapes-root .gjs-pn-views {
    display: block;
    height: var(--proposal-gjs-view-tabs-height);
    z-index: 5;
    top: var(--gjs-canvas-top, 40px);
    border-bottom-color: var(--proposal-gjs-border);
    background: rgba(255, 255, 255, 0.98);
}

.proposal-grapes-root .gjs-pn-views-container {
    padding-top: calc(var(--gjs-canvas-top, 40px) + var(--proposal-gjs-view-tabs-height) + 0.5rem) !important;
    box-shadow: none;
    border-left: 1px solid var(--proposal-gjs-border);
}

.proposal-grapes-root .gjs-one-bg {
    background-color: var(--proposal-gjs-panel);
}

.proposal-grapes-root .gjs-two-color {
    color: #52525b;
}

.proposal-grapes-root .gjs-three-bg {
    background-color: var(--proposal-gjs-accent);
}

.proposal-grapes-root .gjs-four-color,
.proposal-grapes-root .gjs-four-color-h:hover {
    color: var(--proposal-gjs-accent);
}

.proposal-grapes-root .gjs-pn-panels {
    border-bottom: 1px solid var(--proposal-gjs-border);
    background: rgba(255, 255, 255, 0.96);
    box-shadow: none;
}

.proposal-grapes-root .gjs-pn-panel {
    background: transparent;
    box-shadow: none;
    padding: 3px 4px;
}

.proposal-grapes-root .gjs-pn-btn {
    margin: 1px;
    min-height: 26px;
    min-width: 26px;
    padding: 3px;
    border-radius: 0.5rem;
    font-size: 15px;
    color: #71717a;
}

.proposal-grapes-root .gjs-pn-btn:hover,
.proposal-grapes-root .gjs-pn-btn.gjs-pn-active {
    background: var(--proposal-gjs-accent-soft);
    color: #047857;
    box-shadow: inset 0 0 0 1px #a7f3d0;
}

.proposal-grapes-root .gjs-cv-canvas {
    background: #f4f4f5;
}

.proposal-grapes-root .gjs-cv-canvas__frames {
    background: #f8fafc;
}

.proposal-grapes-root .gjs-blocks-c {
    gap: 0.5rem;
    padding: 0.75rem;
    background: #f8fafc;
}

.proposal-grapes-root .gjs-block {
    width: calc(50% - 0.5rem);
    min-height: 4.25rem;
    margin: 0;
    border: 1px solid var(--proposal-gjs-border);
    border-radius: 1rem;
    background: #fff;
    color: #3f3f46;
    box-shadow: none;
    transition: border-color 120ms ease, color 120ms ease, transform 120ms ease;
}

.proposal-grapes-root .gjs-block:hover {
    transform: translateY(-1px);
    border-color: #34d399;
    color: #047857;
}

.proposal-grapes-root .gjs-block-label {
    font-size: 0.68rem;
    line-height: 1.15;
}

.proposal-grapes-root .gjs-category-title,
.proposal-grapes-root .gjs-sm-sector-title,
.proposal-grapes-root .gjs-trt-trait__wrp,
.proposal-grapes-root .gjs-clm-tags {
    border-color: var(--proposal-gjs-border);
    background: #f8fafc;
    color: #3f3f46;
}

.proposal-grapes-root .gjs-sm-sector,
.proposal-grapes-root .gjs-trt-traits {
    border-color: var(--proposal-gjs-border);
    background: #fff;
}

.proposal-grapes-root .gjs-sm-label,
.proposal-grapes-root .gjs-layer-title,
.proposal-grapes-root .gjs-trt-trait__label {
    color: #52525b;
}

.proposal-grapes-root .gjs-field,
.proposal-grapes-root .gjs-field input,
.proposal-grapes-root .gjs-field select,
.proposal-grapes-root .gjs-field textarea {
    border-color: var(--proposal-gjs-border);
    border-radius: 0.625rem;
    background: #f4f4f5;
    color: #27272a;
}

.proposal-grapes-root .gjs-rte-toolbar,
.proposal-grapes-root .gjs-toolbar {
    border: 1px solid rgba(39, 39, 42, 0.08);
    border-radius: 0.75rem;
    background: #18181b;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.16);
}

.proposal-grapes-root .gjs-resizer-h {
    border-color: var(--proposal-gjs-accent);
}

.proposal-grapes-root .gjs-selected {
    outline-color: var(--proposal-gjs-accent) !important;
}

.dark .proposal-grapes-root .gjs-cv-canvas {
    background: #27272a;
}

.dark .proposal-grapes-root {
    --proposal-gjs-border: #3f3f46;
    --proposal-gjs-muted: #a1a1aa;
    --proposal-gjs-panel: #18181b;
    --proposal-gjs-panel-soft: #09090b;
    --proposal-gjs-text: #e4e4e7;
    --proposal-gjs-accent: #34d399;
    --proposal-gjs-accent-soft: rgba(6, 78, 59, 0.42);
}

.dark .proposal-grapes-root .gjs-pn-panels {
    background: rgba(24, 24, 27, 0.96);
}

.dark .proposal-grapes-root .gjs-pn-views {
    background: rgba(24, 24, 27, 0.98);
}

.dark .proposal-grapes-root .gjs-two-color,
.dark .proposal-grapes-root .gjs-pn-btn,
.dark .proposal-grapes-root .gjs-sm-label,
.dark .proposal-grapes-root .gjs-layer-title,
.dark .proposal-grapes-root .gjs-trt-trait__label {
    color: #d4d4d8;
}

.dark .proposal-grapes-root .gjs-pn-btn:hover,
.dark .proposal-grapes-root .gjs-pn-btn.gjs-pn-active {
    color: #a7f3d0;
    box-shadow: inset 0 0 0 1px rgba(52, 211, 153, 0.35);
}

.dark .proposal-grapes-root .gjs-cv-canvas__frames,
.dark .proposal-grapes-root .gjs-blocks-c,
.dark .proposal-grapes-root .gjs-category-title,
.dark .proposal-grapes-root .gjs-sm-sector-title,
.dark .proposal-grapes-root .gjs-trt-trait__wrp,
.dark .proposal-grapes-root .gjs-clm-tags {
    background: #09090b;
    color: #e4e4e7;
}

.dark .proposal-grapes-root .gjs-block,
.dark .proposal-grapes-root .gjs-sm-sector,
.dark .proposal-grapes-root .gjs-trt-traits {
    background: #18181b;
    color: #e4e4e7;
}

.dark .proposal-grapes-root .gjs-field,
.dark .proposal-grapes-root .gjs-field input,
.dark .proposal-grapes-root .gjs-field select,
.dark .proposal-grapes-root .gjs-field textarea {
    background: #27272a;
    color: #f4f4f5;
}
</style>

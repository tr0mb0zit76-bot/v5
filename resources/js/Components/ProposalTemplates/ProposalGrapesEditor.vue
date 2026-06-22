<template>
    <div class="proposal-grapes-root flex min-h-[640px] flex-col overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800">
        <div ref="containerRef" class="min-h-0 flex-1" />
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
.proposal-grapes-root .gjs-editor {
    min-height: 640px;
}

.proposal-grapes-root .gjs-cv-canvas {
    background: #f4f4f5;
}

.dark .proposal-grapes-root .gjs-cv-canvas {
    background: #27272a;
}
</style>

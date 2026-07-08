<template>
    <node-view-wrapper
        class="sales-book-code-block"
        :class="{ 'is-mermaid': isMermaid }"
        data-type="code-block"
    >
        <template v-if="isMermaid">
            <div v-if="editor.isEditable" class="mermaid-block-label">Диаграмма Mermaid</div>
            <pre v-if="editor.isEditable" class="mermaid-source"><node-view-content as="code" /></pre>
            <div
                v-if="!editor.isEditable && diagramSvg"
                class="mermaid-diagram"
                :class="{ 'mermaid-diagram-readonly': !editor.isEditable }"
                v-html="diagramSvg"
            />
            <p v-else-if="renderError" class="mermaid-error">{{ renderError }}</p>
            <p v-else-if="!editor.isEditable && !source.trim()" class="mermaid-error">Пустая диаграмма Mermaid</p>
        </template>
        <pre v-else><node-view-content as="code" /></pre>
    </node-view-wrapper>
</template>

<script setup>
import { NodeViewContent, NodeViewWrapper, nodeViewProps } from '@tiptap/vue-3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { isMermaidLanguage, renderMermaidDiagram } from '@/support/mermaidRender.js';

const props = defineProps(nodeViewProps);

const diagramSvg = ref('');
const renderError = ref('');
let renderGeneration = 0;
let renderTimer = null;
let themeObserver = null;

const isMermaid = computed(() => isMermaidLanguage(props.node.attrs.language));
const source = computed(() => props.node.textContent);

function clearRenderTimer() {
    if (renderTimer !== null) {
        clearTimeout(renderTimer);
        renderTimer = null;
    }
}

async function renderDiagram() {
    if (!isMermaid.value || props.editor.isEditable) {
        diagramSvg.value = '';
        renderError.value = '';

        return;
    }

    const generation = ++renderGeneration;
    const currentSource = source.value;

    if (!currentSource.trim()) {
        diagramSvg.value = '';
        renderError.value = '';

        return;
    }

    try {
        const svg = await renderMermaidDiagram(currentSource);

        if (generation !== renderGeneration) {
            return;
        }

        diagramSvg.value = svg;
        renderError.value = '';
    } catch (error) {
        if (generation !== renderGeneration) {
            return;
        }

        diagramSvg.value = '';
        renderError.value = error instanceof Error ? error.message : 'Не удалось отрисовать диаграмму Mermaid';
    }
}

function scheduleRender() {
    clearRenderTimer();
    renderTimer = setTimeout(() => {
        renderTimer = null;
        void renderDiagram();
    }, props.editor.isEditable ? 350 : 0);
}

watch(
    () => props.editor.isEditable,
    () => {
        scheduleRender();
    },
);

watch(isMermaid, () => {
    scheduleRender();
}, { immediate: true });

watch(source, () => {
    scheduleRender();
});

onMounted(() => {
    themeObserver = new MutationObserver(() => {
        scheduleRender();
    });
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

onBeforeUnmount(() => {
    themeObserver?.disconnect();
    themeObserver = null;
    clearRenderTimer();
    renderGeneration += 1;
});
</script>

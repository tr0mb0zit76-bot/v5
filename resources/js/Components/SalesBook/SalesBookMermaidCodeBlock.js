import CodeBlock from '@tiptap/extension-code-block';
import { VueNodeViewRenderer } from '@tiptap/vue-3';
import MermaidCodeBlockView from './MermaidCodeBlockView.vue';

export const SalesBookMermaidCodeBlock = CodeBlock.extend({
    addNodeView() {
        return VueNodeViewRenderer(MermaidCodeBlockView);
    },
});

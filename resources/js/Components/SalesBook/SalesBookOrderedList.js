import OrderedList from '@tiptap/extension-ordered-list';

/**
 * Нумерованный список с типом маркера: 1, 2… или a, b… (HTML type="a").
 */
export const SalesBookOrderedList = OrderedList.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            type: {
                default: null,
                parseHTML: (element) => element.getAttribute('type'),
                renderHTML: (attributes) => {
                    if (!attributes.type) {
                        return {};
                    }

                    return { type: attributes.type };
                },
            },
        };
    },
});

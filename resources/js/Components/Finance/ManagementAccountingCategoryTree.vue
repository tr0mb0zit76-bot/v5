<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 :class="crmSectionTitle">Статьи учёта</h2>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Единый справочник для управленки и бюджетирования. Раскройте группу, чтобы увидеть вложенные статьи.
                </p>
            </div>
            <button
                type="button"
                :class="crmBtnSecondary"
                :disabled="syncForm.processing"
                @click="syncCategories"
            >
                Обновить справочник
            </button>
        </div>

        <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitNew">
            <label class="min-w-[12rem] flex-1 space-y-1 text-sm">
                <span :class="crmLabel">Название</span>
                <input v-model="createForm.name" type="text" :class="crmFieldFluid" placeholder="Например: Бухгалтерия" required>
            </label>
            <label class="min-w-[12rem] space-y-1 text-sm">
                <span :class="crmLabel">Родительская группа</span>
                <select v-model="createForm.parent_id" :class="crmFieldFluid">
                    <option :value="null">Новая группа верхнего уровня</option>
                    <option v-for="group in groupOptions" :key="group.id" :value="group.id">
                        {{ group.label }}
                    </option>
                </select>
            </label>
            <button type="submit" :class="crmBtnPrimary" :disabled="createForm.processing">
                Добавить
            </button>
        </form>
        <p v-if="createForm.errors.name" class="text-sm text-rose-600">{{ createForm.errors.name }}</p>

        <div :class="`${crmPanel} p-4`">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-800">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ tree.length }} групп верхнего уровня
                </span>
                <div class="flex flex-wrap gap-2">
                    <button type="button" :class="crmBtnNeutral" @click="expandAll">
                        Развернуть все
                    </button>
                    <button type="button" :class="crmBtnNeutral" @click="collapseAll">
                        Свернуть все
                    </button>
                </div>
            </div>

            <ul v-if="tree.length > 0" class="space-y-0.5">
                <CategoryTreeNode
                    v-for="node in tree"
                    :key="node.id"
                    :node="node"
                    :expanded-ids="expandedIds"
                    @toggle="toggleExpanded"
                    @rename="renameCategory"
                    @toggle-budget="toggleBudget"
                    @remove="removeCategory"
                />
            </ul>
            <p v-else class="py-8 text-center text-sm text-zinc-500">
                Справочник пуст. Запустите миграции или нажмите «Обновить справочник».
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import CategoryTreeNode from '@/Components/Finance/ManagementAccountingCategoryTreeNode.vue';
import {
    crmBtnNeutral,
    crmBtnPrimary,
    crmBtnSecondary,
    crmFieldFluid,
    crmLabel,
    crmPanel,
    crmSectionTitle,
} from '@/support/crmUi.js';

const props = defineProps({
    tree: { type: Array, default: () => [] },
});

const createForm = useForm({
    name: '',
    parent_id: null,
    flow: 'out',
});

const syncForm = useForm({});
const expandedIds = ref(new Set());

const groupOptions = computed(() => {
    const options = [];

    const walk = (nodes, prefix = '') => {
        nodes.forEach((node) => {
            if (node.kind === 'group') {
                options.push({
                    id: node.id,
                    label: `${prefix}${node.name}`,
                });
                walk(node.children ?? [], `${prefix}— `);
            }
        });
    };

    walk(props.tree);

    return options;
});

watch(
    () => props.tree,
    (tree) => {
        const next = new Set();

        tree.forEach((node) => {
            if ((node.children ?? []).length > 0) {
                next.add(node.id);
            }
        });

        expandedIds.value = next;
    },
    { immediate: true, deep: true },
);

function collectExpandableIds(nodes, ids = []) {
    nodes.forEach((node) => {
        if ((node.children ?? []).length > 0) {
            ids.push(node.id);
            collectExpandableIds(node.children ?? [], ids);
        }
    });

    return ids;
}

function expandAll() {
    expandedIds.value = new Set(collectExpandableIds(props.tree));
}

function collapseAll() {
    expandedIds.value = new Set();
}

function toggleExpanded(id) {
    const next = new Set(expandedIds.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    expandedIds.value = next;
}

function submitNew() {
    createForm.transform((data) => ({
        ...data,
        parent_id: data.parent_id === null || data.parent_id === '' ? null : Number(data.parent_id),
    })).post('/finance/management-accounting/categories?tab=categories', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset('name');
            createForm.parent_id = null;
        },
    });
}

function syncCategories() {
    syncForm.post('/finance/management-accounting/categories/sync?tab=categories', {
        preserveScroll: true,
    });
}

function renameCategory(category, name) {
    router.patch(`/finance/management-accounting/categories/${category.id}?tab=categories`, { name }, {
        preserveScroll: true,
    });
}

function toggleBudget(category, includeInBudget) {
    router.patch(`/finance/management-accounting/categories/${category.id}?tab=categories`, {
        include_in_budget: includeInBudget,
    }, {
        preserveScroll: true,
    });
}

function removeCategory(category) {
    if (!window.confirm(`Удалить статью «${category.name}»?`)) {
        return;
    }

    router.delete(`/finance/management-accounting/categories/${category.id}?tab=categories`, {
        preserveScroll: true,
    });
}
</script>

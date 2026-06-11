<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 :class="crmSectionTitle">Статьи учёта</h2>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Единый справочник для управленки и бюджетирования. Группы раскрываются в отчёте «Учёт».
                </p>
            </div>
            <button
                type="button"
                :class="crmBtnSecondary"
                :disabled="syncForm.processing"
                @click="syncCategories"
            >
                Синхронизировать с бюджетом
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

        <div class="space-y-1">
            <CategoryTreeNode
                v-for="node in tree"
                :key="node.id"
                :node="node"
                :depth="0"
                @rename="renameCategory"
                @remove="removeCategory"
            />
            <p v-if="tree.length === 0" class="py-6 text-center text-sm text-zinc-500">
                Справочник пуст. Запустите миграции или синхронизацию с бюджетом.
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import CategoryTreeNode from '@/Components/Finance/ManagementAccountingCategoryTreeNode.vue';
import {
    crmBtnPrimary,
    crmBtnSecondary,
    crmFieldFluid,
    crmLabel,
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

function removeCategory(category) {
    if (!window.confirm(`Удалить статью «${category.name}»?`)) {
        return;
    }

    router.delete(`/finance/management-accounting/categories/${category.id}?tab=categories`, {
        preserveScroll: true,
    });
}
</script>

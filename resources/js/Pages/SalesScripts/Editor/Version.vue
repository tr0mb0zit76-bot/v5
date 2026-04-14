<template>
    <div class="min-h-0 flex-1 space-y-8 overflow-y-auto lg:min-h-0">
        <section class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Версия {{ payload.version.version_number }}</div>
                    <h1 class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ payload.script.title }}</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <span v-if="payload.version.is_active && payload.version.published_at" class="font-medium text-emerald-700 dark:text-emerald-300">Опубликована и активна</span>
                        <span v-else>Черновик или неактивна — не отображается при старте сессии.</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="!payload.version.is_active || !payload.version.published_at"
                        type="button"
                        class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                        @click="publish"
                    >
                        Опубликовать
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                        @click="unpublish"
                    >
                        Снять с публикации
                    </button>
                </div>
            </div>
            <p
                v-if="page.props.flash?.message"
                class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ page.props.flash.message }}
            </p>
            <div v-if="page.props.errors && Object.keys(page.props.errors).length" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                <ul class="list-inside list-disc space-y-1">
                    <li v-for="(msg, key) in page.props.errors" :key="key">
                        {{ key }}: {{ Array.isArray(msg) ? msg[0] : msg }}
                    </li>
                </ul>
            </div>
            <div class="mt-4 flex flex-wrap gap-4 text-sm">
                <Link
                    :href="route('scripts.editor.index')"
                    class="font-medium text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-300"
                >
                    ← К списку сценариев
                </Link>
                <Link
                    :href="route('scripts.index')"
                    class="font-medium text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-300"
                >
                    Прохождение сценариев
                </Link>
            </div>
        </section>

        <section class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Стартовый шаг</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Ключ должен совпадать с полем «Ключ шага» одного из узлов ниже.</p>
            <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="saveEntry">
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">entry_node_key</label>
                    <input
                        v-model="entryKey"
                        type="text"
                        class="mt-1 w-56 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                    />
                </div>
                <button
                    type="submit"
                    class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                >
                    Сохранить
                </button>
            </form>
        </section>

        <section class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Шаги</h2>
            <form class="mt-4 grid gap-3 border-b border-zinc-100 pb-6 dark:border-zinc-800 md:grid-cols-2" @submit.prevent="addNode">
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Ключ шага</label>
                    <input v-model="newNode.client_key" type="text" required class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Тип</label>
                    <select v-model="newNode.kind" class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <option v-for="k in nodeKinds" :key="k.value" :value="k.value">{{ k.label }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Текст</label>
                    <textarea v-model="newNode.body" required rows="2" class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Подсказка</label>
                    <input v-model="newNode.hint" type="text" class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Порядок</label>
                    <input v-model.number="newNode.sort_order" type="number" min="0" class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white dark:bg-zinc-50 dark:text-zinc-900">Добавить шаг</button>
                </div>
            </form>

            <div class="mt-6 space-y-4">
                <div
                    v-for="node in payload.nodes"
                    :key="node.id"
                    class="rounded-xl border border-zinc-100 p-4 dark:border-zinc-800"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="font-mono text-sm text-zinc-800 dark:text-zinc-200">{{ node.client_key }}</div>
                        <button
                            type="button"
                            class="text-xs font-medium text-rose-700 hover:underline dark:text-rose-300"
                            @click="removeNode(node.id)"
                        >
                            Удалить
                        </button>
                    </div>
                    <form class="mt-3 grid gap-2 md:grid-cols-2" @submit.prevent="updateNode(node)">
                        <div>
                            <label class="text-xs text-zinc-500">Ключ</label>
                            <input v-model="nodeForms[node.id].client_key" type="text" required class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        </div>
                        <div>
                            <label class="text-xs text-zinc-500">Тип</label>
                            <select v-model="nodeForms[node.id].kind" class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <option v-for="k in nodeKinds" :key="k.value" :value="k.value">{{ k.label }}</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-zinc-500">Текст</label>
                            <textarea v-model="nodeForms[node.id].body" required rows="2" class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-zinc-500">Подсказка</label>
                            <input v-model="nodeForms[node.id].hint" type="text" class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        </div>
                        <div>
                            <label class="text-xs text-zinc-500">Порядок</label>
                            <input v-model.number="nodeForms[node.id].sort_order" type="number" min="0" class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium dark:border-zinc-600">Сохранить шаг</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Переходы</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Связывают шаги. Реакция опциональна — если не выбрана, в сессии будет кнопка «Дальше».</p>

            <form class="mt-4 grid gap-3 border-b border-zinc-100 pb-6 dark:border-zinc-800 md:grid-cols-2 lg:grid-cols-3" @submit.prevent="addTransition">
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Из шага</label>
                    <select v-model.number="newTransition.from_node_id" required class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <option v-for="n in payload.nodes" :key="n.id" :value="n.id">{{ n.client_key }} (#{{ n.id }})</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">В шаг</label>
                    <select v-model.number="newTransition.to_node_id" required class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <option v-for="n in payload.nodes" :key="n.id" :value="n.id">{{ n.client_key }} (#{{ n.id }})</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Реакция</label>
                    <select v-model="newTransition.sales_script_reaction_class_id" class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <option :value="null">—</option>
                        <option v-for="r in reactionClasses" :key="r.id" :value="r.id">{{ r.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Порядок</label>
                    <input v-model.number="newTransition.sort_order" type="number" min="0" class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                </div>
                <div class="flex items-end lg:col-span-2">
                    <button type="submit" class="w-full rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white dark:bg-zinc-50 dark:text-zinc-900">Добавить переход</button>
                </div>
            </form>

            <div class="mt-6 space-y-4">
                <div
                    v-for="t in payload.transitions"
                    :key="t.id"
                    class="rounded-xl border border-zinc-100 p-4 dark:border-zinc-800"
                >
                    <form class="grid gap-2 md:grid-cols-2 lg:grid-cols-4" @submit.prevent="updateTransition(t)">
                        <div>
                            <label class="text-xs text-zinc-500">Из</label>
                            <select v-model.number="transitionForms[t.id].from_node_id" required class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <option v-for="n in payload.nodes" :key="n.id" :value="n.id">{{ n.client_key }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-zinc-500">В</label>
                            <select v-model.number="transitionForms[t.id].to_node_id" required class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <option v-for="n in payload.nodes" :key="n.id" :value="n.id">{{ n.client_key }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-zinc-500">Реакция</label>
                            <select v-model="transitionForms[t.id].sales_script_reaction_class_id" class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <option :value="null">—</option>
                                <option v-for="r in reactionClasses" :key="r.id" :value="r.id">{{ r.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-zinc-500">Порядок</label>
                            <input v-model.number="transitionForms[t.id].sort_order" type="number" min="0" class="mt-1 w-full rounded-lg border border-zinc-200 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        </div>
                        <div class="flex flex-wrap gap-2 md:col-span-2 lg:col-span-4">
                            <button type="submit" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600">Сохранить</button>
                            <button type="button" class="text-sm text-rose-700 dark:text-rose-300" @click="removeTransition(t.id)">Удалить</button>
                        </div>
                    </form>
                </div>
                <p v-if="payload.transitions.length === 0" class="text-sm text-zinc-500">Переходов пока нет.</p>
            </div>
        </section>
    </div>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'scripts' }, () => page),
});

const props = defineProps({
    payload: {
        type: Object,
        required: true,
    },
    reactionClasses: {
        type: Array,
        default: () => [],
    },
    nodeKinds: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const entryKey = ref(props.payload.version.entry_node_key ?? '');
watch(
    () => props.payload.version.entry_node_key,
    (v) => {
        entryKey.value = v ?? '';
    },
);

const newNode = reactive({
    client_key: '',
    kind: props.nodeKinds[0]?.value ?? 'say',
    body: '',
    hint: '',
    sort_order: 0,
});

const newTransition = reactive({
    from_node_id: props.payload.nodes[0]?.id ?? null,
    to_node_id: props.payload.nodes[1]?.id ?? props.payload.nodes[0]?.id ?? null,
    sales_script_reaction_class_id: null,
    sort_order: 0,
});

const nodeForms = reactive({});
const transitionForms = reactive({});

watch(
    () => props.payload.nodes,
    (nodes) => {
        const ids = new Set(nodes.map((n) => n.id));
        Object.keys(nodeForms).forEach((id) => {
            if (! ids.has(Number(id))) {
                delete nodeForms[id];
            }
        });
        nodes.forEach((node) => {
            nodeForms[node.id] = {
                client_key: node.client_key,
                kind: node.kind,
                body: node.body,
                hint: node.hint ?? '',
                sort_order: node.sort_order,
            };
        });
    },
    { deep: true, immediate: true },
);

watch(
    () => props.payload.transitions,
    (rows) => {
        const ids = new Set(rows.map((t) => t.id));
        Object.keys(transitionForms).forEach((id) => {
            if (! ids.has(Number(id))) {
                delete transitionForms[id];
            }
        });
        rows.forEach((t) => {
            transitionForms[t.id] = {
                from_node_id: t.from_node_id,
                to_node_id: t.to_node_id,
                sales_script_reaction_class_id: t.sales_script_reaction_class_id,
                sort_order: t.sort_order,
            };
        });
    },
    { deep: true, immediate: true },
);

function saveEntry() {
    const trimmed = entryKey.value.trim();
    router.patch(route('scripts.editor.versions.update', props.payload.version.id), {
        entry_node_key: trimmed === '' ? null : trimmed,
    });
}

function addNode() {
    router.post(route('scripts.editor.versions.nodes.store', props.payload.version.id), { ...newNode });
}

function updateNode(node) {
    const data = nodeForms[node.id];
    router.patch(route('scripts.editor.nodes.update', node.id), { ...data });
}

function removeNode(id) {
    if (!window.confirm('Удалить шаг и связанные переходы?')) {
        return;
    }
    router.delete(route('scripts.editor.nodes.destroy', id));
}

function addTransition() {
    router.post(route('scripts.editor.versions.transitions.store', props.payload.version.id), {
        from_node_id: newTransition.from_node_id,
        to_node_id: newTransition.to_node_id,
        sales_script_reaction_class_id: newTransition.sales_script_reaction_class_id,
        sort_order: newTransition.sort_order,
    });
}

function updateTransition(t) {
    const data = transitionForms[t.id];
    router.patch(route('scripts.editor.transitions.update', t.id), {
        from_node_id: data.from_node_id,
        to_node_id: data.to_node_id,
        sales_script_reaction_class_id: data.sales_script_reaction_class_id,
        sort_order: data.sort_order,
    });
}

function removeTransition(id) {
    if (!window.confirm('Удалить переход?')) {
        return;
    }
    router.delete(route('scripts.editor.transitions.destroy', id));
}

function publish() {
    router.post(route('scripts.editor.versions.publish', props.payload.version.id));
}

function unpublish() {
    router.post(route('scripts.editor.versions.unpublish', props.payload.version.id));
}
</script>

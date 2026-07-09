<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0">
        <section :class="`${crmPanel} space-y-4 p-6`">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div :class="crmPageEyebrow">Версия {{ payload.version.version_number }}</div>
                    <h1 :class="crmPageTitle">Конструктор сценария</h1>
                    <p :class="`${crmPageLead} mt-2`">{{ payload.script.title }}</p>
                    <p class="mt-2 text-sm">
                        <span
                            v-if="payload.version.is_active && payload.version.published_at"
                            class="font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            Опубликована и активна
                        </span>
                        <span v-else class="text-zinc-500 dark:text-zinc-400">
                            Черновик — не показывается при старте сессии, пока не опубликуете.
                        </span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" :class="crmBtnSecondary" @click="addNode">
                        + Шаг
                    </button>
                    <button
                        v-if="!payload.version.is_active || !payload.version.published_at"
                        type="button"
                        :class="crmBtnPrimary"
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
                    <button type="button" :class="crmBtnCreate" :disabled="saving || autosaving" @click="saveGraph">
                        {{ saving ? 'Сохранение…' : 'Сохранить' }}
                    </button>
                </div>
                <p v-if="autosaveHint" class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ autosaveHint }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('scripts.editor.versions.analytics', payload.version.id)"
                    :class="crmBtnSecondaryOutline"
                >
                    Аналитика
                </Link>
                <Link
                    :href="route('scripts.editor.index')"
                    :class="crmBtnSecondaryOutline"
                >
                    К списку сценариев
                </Link>
                <Link
                    :href="route('scripts.index')"
                    :class="crmBtnSecondaryOutline"
                >
                    К прохождению
                </Link>
            </div>
            <p
                v-if="page.props.flash?.message"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ page.props.flash.message }}
            </p>
            <div
                v-if="page.props.errors && Object.keys(page.props.errors).length"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
            >
                <ul class="list-inside list-disc space-y-1">
                    <li v-for="(msg, key) in page.props.errors" :key="key">
                        {{ key }}: {{ Array.isArray(msg) ? msg[0] : msg }}
                    </li>
                </ul>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <ScriptGraphCanvas
                :nodes="graphNodes"
                :transitions="graphTransitions"
                :entry-node-key="entryNodeKey"
                :node-kinds="nodeKinds"
                :reaction-classes="reactionClasses"
                :selected-node-key="selectedNodeKey"
                :selected-transition-id="selectedTransitionId"
                :active-tag-filter="activeTagFilter"
                @update:selected-node-key="onSelectNode"
                @update:selected-transition-id="selectedTransitionId = $event"
                @update:active-tag-filter="activeTagFilter = $event"
                @update:node-position="onNodePosition"
                @create-transition="onCreateTransition"
                @add-answer="onAddAnswer"
            />

            <div class="space-y-4">
                <section :class="`${crmPanel} space-y-3 p-4`">
                    <h2 :class="crmSectionTitle">Стартовый шаг</h2>
                    <select v-model="entryNodeKey" :class="crmFieldFluid">
                        <option v-for="node in graphNodes" :key="`entry-${node.client_key}`" :value="node.client_key">
                            {{ node.client_key }}
                        </option>
                    </select>
                </section>

                <section :class="`${crmPanel} space-y-3 p-4`">
                    <h2 :class="crmSectionTitle">Шаблоны блоков</h2>
                    <p :class="crmPageLead">Сохранённые «образы» шагов — вставка копией в этот сценарий.</p>
                    <ul v-if="nodeTemplates.length" class="max-h-40 space-y-2 overflow-y-auto text-xs">
                        <li
                            v-for="template in nodeTemplates"
                            :key="template.id"
                            class="rounded-xl border border-zinc-200 p-2 dark:border-zinc-700"
                        >
                            <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ template.title }}</div>
                            <p class="mt-1 line-clamp-2 text-zinc-500 dark:text-zinc-400">{{ nodeExcerpt(template.body) }}</p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" :class="`${crmBtnSecondary} text-[11px]`" @click="insertFromTemplate(template)">
                                    Вставить
                                </button>
                                <button
                                    type="button"
                                    class="text-[11px] text-rose-700 hover:underline dark:text-rose-300"
                                    @click="deleteTemplate(template.id)"
                                >
                                    Удалить
                                </button>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-xs text-zinc-500 dark:text-zinc-400">Пока нет шаблонов.</p>
                </section>

                <section :class="`${crmPanel} space-y-3 p-4`">
                    <h2 :class="crmSectionTitle">Поля разговора</h2>
                    <p :class="crmPageLead">Справочник для плейсхолдеров в тексте: <code class="text-[10px]">{client_name}</code></p>
                    <ul v-if="captureFields.length" class="max-h-36 space-y-1.5 overflow-y-auto text-xs">
                        <li
                            v-for="field in captureFields"
                            :key="field.id"
                            class="flex items-start justify-between gap-2 rounded-lg border border-zinc-200 px-2 py-1.5 dark:border-zinc-700"
                        >
                            <div>
                                <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ field.label }}</div>
                                <div class="font-mono text-[10px] text-zinc-500">{ {{ field.code }} }</div>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 text-rose-700 hover:underline dark:text-rose-300"
                                @click="deleteCaptureField(field.id)"
                            >
                                ×
                            </button>
                        </li>
                    </ul>
                    <div class="grid gap-2">
                        <input v-model="newFieldCode" type="text" :class="crmFieldFluid" placeholder="Код: client_name" />
                        <input v-model="newFieldLabel" type="text" :class="crmFieldFluid" placeholder="Подпись: Имя собеседника" />
                        <button type="button" :class="`${crmBtnSecondary} text-xs`" @click="createCaptureField">
                            + Поле
                        </button>
                    </div>
                </section>

                <section :class="`${crmPanel} space-y-3 p-4`">
                    <h2 :class="crmSectionTitle">Шаги сценария</h2>
                    <p :class="crmPageLead">На схеме — превью текста и ответы; полное редактирование — справа.</p>
                    <ul class="max-h-48 space-y-1.5 overflow-y-auto">
                        <li
                            v-for="node in graphNodes"
                            :key="`list-${node.client_key}`"
                            class="cursor-pointer rounded-xl border px-3 py-2 text-left transition"
                            :class="selectedNodeKey === node.client_key
                                ? 'border-sky-400 bg-sky-50 dark:border-sky-700 dark:bg-sky-950/30'
                                : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/50'"
                            @click="onSelectNode(node.client_key)"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ node.client_key }}</span>
                                <span class="shrink-0 text-[10px] uppercase tracking-wide text-zinc-400">{{ kindShort(node.kind) }}</span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-zinc-700 dark:text-zinc-200">
                                {{ nodeExcerpt(node.body) }}
                            </p>
                        </li>
                    </ul>
                </section>

                <section v-if="selectedNode" :class="`${crmPanel} space-y-3 p-4`">
                    <div class="flex items-center justify-between gap-2">
                        <h2 :class="crmSectionTitle">Редактирование</h2>
                        <button
                            type="button"
                            class="text-xs font-medium text-rose-700 hover:underline dark:text-rose-300"
                            @click="removeNode(selectedNode.client_key)"
                        >
                            Удалить
                        </button>
                    </div>
                    <div>
                        <label :class="crmLabelCompact">Ключ</label>
                        <input v-model="selectedNode.client_key" type="text" :class="`${crmFieldFluid} mt-1`" />
                    </div>
                    <div>
                        <label :class="crmLabelCompact">Тип</label>
                        <select v-model="selectedNode.kind" :class="`${crmFieldFluid} mt-1`">
                            <option v-for="kind in nodeKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label :class="crmLabelCompact">Реплика / текст оператора</label>
                        <textarea
                            ref="bodyTextareaRef"
                            v-model="selectedNode.body"
                            rows="5"
                            :class="`${crmFieldFluid} mt-1`"
                        />
                        <div v-if="captureFields.length" class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="field in captureFields"
                                :key="`ins-${field.code}`"
                                type="button"
                                class="rounded-full border border-zinc-200 px-2 py-0.5 text-[10px] font-medium text-zinc-600 hover:border-sky-300 hover:bg-sky-50 dark:border-zinc-600 dark:text-zinc-300"
                                @click="insertFieldPlaceholder(field.code)"
                            >
                                + { {{ field.code }} }
                            </button>
                        </div>
                    </div>
                    <div class="space-y-2 rounded-xl border border-violet-200/80 bg-violet-50/40 p-3 dark:border-violet-900/50 dark:bg-violet-950/20">
                        <label class="inline-flex items-center gap-2 text-xs font-medium">
                            <input v-model="selectedNode.ab_enabled" type="checkbox" class="rounded border-zinc-300" />
                            A/B: вторая формулировка
                        </label>
                        <textarea
                            v-model="selectedNode.body_variant_b"
                            rows="3"
                            :class="`${crmFieldFluid} mt-1`"
                            placeholder="Вариант B (альтернативный текст)"
                            :disabled="!selectedNode.ab_enabled"
                        />
                        <div v-if="selectedNode.ab_enabled" class="flex items-center gap-2 text-xs">
                            <span>Доля B:</span>
                            <input
                                v-model.number="selectedNode.ab_variant_b_weight"
                                type="number"
                                min="0"
                                max="100"
                                class="w-16 rounded-lg border border-zinc-200 px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950"
                            />
                            <span>%</span>
                        </div>
                    </div>
                    <div v-if="bodyFieldCodes.length">
                        <label :class="crmLabelCompact">Захват на этом шаге</label>
                        <p class="mt-0.5 text-[11px] text-zinc-500 dark:text-zinc-400">
                            Отмеченные поля — ввод оператора; остальные в тексте — подстановка ранее записанного.
                        </p>
                        <div class="mt-2 space-y-1.5">
                            <label
                                v-for="code in bodyFieldCodes"
                                :key="`cap-${code}`"
                                class="flex items-center gap-2 text-xs text-zinc-700 dark:text-zinc-200"
                            >
                                <input
                                    type="checkbox"
                                    :checked="selectedNode.capture_field_codes.includes(code)"
                                    @change="toggleCaptureField(code)"
                                />
                                <span class="font-mono">{ {{ code }} }</span>
                                <span class="text-zinc-500">{{ captureFieldLabel(code) }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" :class="`${crmBtnSecondary} text-xs`" @click="saveNodeAsTemplate">
                            Сохранить как шаблон
                        </button>
                    </div>
                    <div>
                        <label :class="crmLabelCompact">Методология (свернуто у оператора)</label>
                        <input v-model="selectedNode.hint" type="text" :class="`${crmFieldFluid} mt-1`" placeholder="СПИН, тон, рамка времени…" />
                    </div>
                    <div>
                        <label :class="crmLabelCompact">Теги группы</label>
                        <p class="mt-0.5 text-[11px] text-zinc-500 dark:text-zinc-400">
                            Для фильтра на схеме: «квалификация», «возражения»… Введите и нажмите Enter.
                        </p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <span
                                v-for="tag in selectedNode.tags"
                                :key="tag"
                                class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                            >
                                {{ tag }}
                                <button
                                    type="button"
                                    class="text-amber-700 hover:text-rose-600 dark:text-amber-300"
                                    @click="removeNodeTag(tag)"
                                >
                                    ×
                                </button>
                            </span>
                        </div>
                        <input
                            v-model="tagDraft"
                            type="text"
                            :class="`${crmFieldFluid} mt-2`"
                            placeholder="Новый тег"
                            list="script-node-tag-suggestions"
                            @keydown.enter.prevent="addNodeTag"
                        />
                        <datalist id="script-node-tag-suggestions">
                            <option v-for="tag in tagSuggestions" :key="`sug-${tag}`" :value="tag" />
                        </datalist>
                    </div>
                </section>

                <section :class="`${crmPanel} space-y-3 p-4`">
                    <h2 :class="crmSectionTitle">Связи</h2>
                    <p :class="crmPageLead">
                        Потяните стрелку на схеме или отредактируйте выбранную связь. В поле «Фраза клиента» — дословная реплика собеседника: именно она появится на кнопке при прохождении скрипта. Тип реакции — для аналитики, не для экрана менеджера.
                    </p>

                    <div v-if="selectedTransition" class="space-y-3 rounded-xl border border-sky-200 bg-sky-50/60 p-3 dark:border-sky-900/50 dark:bg-sky-950/20">
                        <div class="text-xs font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-200">Связь</div>
                        <div class="grid gap-2">
                            <select v-model="selectedTransition.from_client_key" :class="crmFieldFluid">
                                <option v-for="node in graphNodes" :key="`sel-from-${node.client_key}`" :value="node.client_key">{{ node.client_key }}</option>
                            </select>
                            <select v-model="selectedTransition.to_client_key" :class="crmFieldFluid">
                                <option v-for="node in graphNodes" :key="`sel-to-${node.client_key}`" :value="node.client_key">{{ node.client_key }}</option>
                            </select>
                            <select v-model="selectedTransition.target_type" :class="crmFieldFluid" @change="onTransitionTargetTypeChange">
                                <option value="node">Обычный переход к шагу</option>
                                <option value="script">Перейти в другой сценарий, потом вернуться</option>
                                <option value="return">Вернуться в исходный сценарий</option>
                            </select>
                            <select
                                v-if="selectedTransition.target_type === 'script'"
                                v-model="selectedTransition.target_sales_script_version_id"
                                :class="crmFieldFluid"
                            >
                                <option :value="null">Выберите целевой сценарий</option>
                                <option
                                    v-for="version in targetVersions"
                                    :key="`target-version-${version.id}`"
                                    :value="version.id"
                                >
                                    {{ version.title }} · v{{ version.version_number }}
                                </option>
                            </select>
                            <p
                                v-if="selectedTransition.target_type === 'script'"
                                class="text-[11px] leading-relaxed text-zinc-500 dark:text-zinc-400"
                            >
                                Поле «куда» выше становится точкой возврата после успешной отработки подключённого сценария.
                            </p>
                            <p
                                v-else-if="selectedTransition.target_type === 'return'"
                                class="text-[11px] leading-relaxed text-zinc-500 dark:text-zinc-400"
                            >
                                Если сессия запущена как подключённый сценарий, переход вернёт оператора назад. Шаг «куда» используется как запасной вариант.
                            </p>
                            <input
                                v-model="selectedTransition.customer_label"
                                type="text"
                                :class="crmFieldFluid"
                                placeholder="Фраза клиента: «Да, задавайте вопросы»"
                            />
                            <select v-model="selectedTransition.sales_script_reaction_class_id" :class="crmFieldFluid">
                                <option :value="null">Линейный переход (без реакции)</option>
                                <option v-for="reaction in reactionClasses" :key="reaction.id" :value="reaction.id">{{ reaction.label }}</option>
                            </select>
                            <div class="grid grid-cols-[1fr_112px] gap-2">
                                <select v-model="selectedTransition.conversation_effect" :class="crmFieldFluid">
                                    <option :value="null">Направление — автоматически</option>
                                    <option value="positive">Интерес растёт</option>
                                    <option value="neutral">Нейтрально</option>
                                    <option value="risk">Есть риск</option>
                                    <option value="critical">Критичный поворот</option>
                                </select>
                                <select v-model="selectedTransition.momentum_delta" :class="crmFieldFluid">
                                    <option :value="null">Δ авто</option>
                                    <option :value="2">+2</option>
                                    <option :value="1">+1</option>
                                    <option :value="0">0</option>
                                    <option :value="-1">−1</option>
                                    <option :value="-2">−2</option>
                                </select>
                            </div>
                            <textarea
                                v-model="selectedTransition.next_move_preview"
                                rows="2"
                                :class="crmFieldFluid"
                                placeholder="Короткая фраза следующего хода. Если пусто — попробуем взять прямую речь из следующего шага."
                            />
                            <p class="text-[11px] leading-relaxed text-zinc-500 dark:text-zinc-400">
                                Направление и Δ двигают «температуру разговора». Предпросмотр показывается полупрозрачно под ответом клиента.
                            </p>
                            <div class="flex gap-2">
                                <button type="button" :class="`${crmBtnSecondary} flex-1 text-xs`" @click="moveTransition(-1)">↑</button>
                                <button type="button" :class="`${crmBtnSecondary} flex-1 text-xs`" @click="moveTransition(1)">↓</button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-xl border border-rose-200 px-2 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                    @click="removeTransition(selectedTransition.local_id)"
                                >
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </div>

                    <ul class="max-h-52 space-y-2 overflow-y-auto text-xs">
                        <li
                            v-for="transition in graphTransitions"
                            :key="transition.local_id"
                            class="cursor-pointer rounded-lg border p-2 transition"
                            :class="selectedTransitionId === transition.local_id
                                ? 'border-sky-400 bg-sky-50 dark:border-sky-700 dark:bg-sky-950/30'
                                : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/50'"
                            @click="selectedTransitionId = transition.local_id"
                        >
                            <div class="font-medium text-zinc-800 dark:text-zinc-100">
                                {{ transition.from_client_key }} → {{ transition.to_client_key }}
                            </div>
                            <div :class="`${crmPageLead} mt-1`">
                                {{ transition.customer_label || transitionLabel(transition.sales_script_reaction_class_id) }}
                                <span v-if="transition.target_type === 'script'">
                                    · сценарий: {{ targetVersionLabel(transition.target_sales_script_version_id) }}
                                </span>
                                <span v-else-if="transition.target_type === 'return'">
                                    · возврат
                                </span>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </section>
    </div>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import ScriptGraphCanvas from '@/Components/SalesScripts/ScriptGraphCanvas.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import {
    crmBtnCreate,
    crmBtnPrimary,
    crmBtnSecondary,
    crmBtnSecondaryOutline,
    crmFieldFluid,
    crmLabelCompact,
    crmPageEyebrow,
    crmPageLead,
    crmPageTitle,
    crmPanel,
    crmSectionTitle,
} from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-scripts' }, () => page),
});

const props = defineProps({
    payload: { type: Object, required: true },
    reactionClasses: { type: Array, default: () => [] },
    nodeKinds: { type: Array, default: () => [] },
    captureFields: { type: Array, default: () => [] },
    nodeTemplates: { type: Array, default: () => [] },
    targetVersions: { type: Array, default: () => [] },
});

const page = usePage();
const saving = ref(false);
const autosaving = ref(false);
const autosaveHint = ref('');
const autosaveTimer = ref(null);
const autosaveQueued = ref(false);
const edgeSeq = ref(1);
const selectedNodeKey = ref(props.payload.nodes[0]?.client_key ?? null);
const selectedTransitionId = ref(null);
const activeTagFilter = ref(null);
const tagDraft = ref('');
const newFieldCode = ref('');
const newFieldLabel = ref('');
const entryNodeKey = ref(props.payload.version.entry_node_key ?? props.payload.nodes[0]?.client_key ?? '');
const bodyTextareaRef = ref(null);

const graphNodes = reactive(
    props.payload.nodes.map((node, index) => ({
        client_key: node.client_key,
        kind: node.kind,
        body: node.body ?? '',
        body_variant_b: node.body_variant_b ?? '',
        ab_enabled: Boolean(node.ab_enabled),
        ab_variant_b_weight: Number.isFinite(Number(node.ab_variant_b_weight)) ? Number(node.ab_variant_b_weight) : 50,
        hint: node.hint ?? '',
        tags: Array.isArray(node.tags) ? [...node.tags] : [],
        capture_field_codes: Array.isArray(node.capture_field_codes) ? [...node.capture_field_codes] : [],
        sort_order: node.sort_order ?? index,
        canvas_x: Number.isInteger(node.canvas_x) ? node.canvas_x : 40 + (index % 4) * 260,
        canvas_y: Number.isInteger(node.canvas_y) ? node.canvas_y : 40 + Math.floor(index / 4) * 180,
    })),
);

const graphTransitions = reactive(
    props.payload.transitions.map((transition, index) => ({
        local_id: `t-${transition.id ?? index}-${index}`,
        from_client_key: resolveClientKeyByNodeId(transition.from_node_id),
        to_client_key: resolveClientKeyByNodeId(transition.to_node_id),
        target_type: transition.target_type ?? 'node',
        target_sales_script_version_id: transition.target_sales_script_version_id ?? null,
        sales_script_reaction_class_id: transition.sales_script_reaction_class_id ?? null,
        customer_label: transition.customer_label ?? '',
        conversation_effect: transition.conversation_effect ?? null,
        momentum_delta: transition.momentum_delta ?? null,
        next_move_preview: transition.next_move_preview ?? '',
        sort_order: transition.sort_order ?? index,
    })),
);

const selectedNode = computed(() => graphNodes.find((node) => node.client_key === selectedNodeKey.value) ?? null);
const selectedTransition = computed(() => graphTransitions.find((t) => t.local_id === selectedTransitionId.value) ?? null);

const bodyFieldCodes = computed(() => {
    if (!selectedNode.value?.body) {
        return [];
    }

    const matches = [...String(selectedNode.value.body).matchAll(/\{([a-z][a-z0-9_]*)\}/g)];

    return [...new Set(matches.map((match) => match[1]))];
});

const tagSuggestions = computed(() => {
    const tags = new Set();

    for (const node of graphNodes) {
        for (const tag of node.tags ?? []) {
            tags.add(tag);
        }
    }

    return [...tags].sort((a, b) => a.localeCompare(b, 'ru'));
});

function resolveClientKeyByNodeId(nodeId) {
    return props.payload.nodes.find((node) => node.id === nodeId)?.client_key ?? '';
}

function kindShort(kind) {
    return ({ say: 'сказать', ask: 'спросить', branch: 'ветка' })[kind] ?? kind;
}

function nodeExcerpt(text) {
    const normalized = String(text ?? '').replace(/\s+/g, ' ').trim();

    if (normalized === '') {
        return '— пустой текст —';
    }

    return normalized.length > 120 ? `${normalized.slice(0, 119)}…` : normalized;
}

function onSelectNode(clientKey) {
    selectedNodeKey.value = clientKey;
    selectedTransitionId.value = null;
}

function transitionLabel(reactionId) {
    if (reactionId === null || reactionId === undefined) {
        return 'Дальше';
    }

    return props.reactionClasses.find((item) => item.id === reactionId)?.label ?? 'Реакция';
}

function targetVersionLabel(versionId) {
    const version = props.targetVersions.find((item) => Number(item.id) === Number(versionId));

    return version ? `${version.title} · v${version.version_number}` : 'другой сценарий';
}

function onTransitionTargetTypeChange() {
    if (!selectedTransition.value) {
        return;
    }

    if (selectedTransition.value.target_type !== 'script') {
        selectedTransition.value.target_sales_script_version_id = null;
    }
}

function uniqueClientKey(base) {
    let candidate = base;
    let suffix = 1;

    while (graphNodes.some((node) => node.client_key === candidate)) {
        suffix += 1;
        candidate = `${base}_${suffix}`;
    }

    return candidate;
}

function onNodePosition({ client_key, canvas_x, canvas_y }) {
    const node = graphNodes.find((item) => item.client_key === client_key);
    if (node) {
        node.canvas_x = canvas_x;
        node.canvas_y = canvas_y;
    }
}

function captureFieldLabel(code) {
    return props.captureFields.find((field) => field.code === code)?.label ?? code;
}

function insertFieldPlaceholder(code) {
    if (!selectedNode.value) {
        return;
    }

    const token = `{${code}}`;
    const textarea = bodyTextareaRef.value;
    const body = String(selectedNode.value.body ?? '');
    const start = typeof textarea?.selectionStart === 'number' ? textarea.selectionStart : body.length;
    const end = typeof textarea?.selectionEnd === 'number' ? textarea.selectionEnd : start;
    const before = body.slice(0, start);
    const after = body.slice(end);
    const needsSpaceBefore = before !== '' && !/\s$/u.test(before);
    const needsSpaceAfter = after !== '' && !/^\s|[.,;:!?)]/u.test(after);
    const insert = `${needsSpaceBefore ? ' ' : ''}${token}${needsSpaceAfter ? ' ' : ''}`;

    selectedNode.value.body = `${before}${insert}${after}`;

    if (!selectedNode.value.capture_field_codes.includes(code)) {
        selectedNode.value.capture_field_codes.push(code);
    }

    void nextTick(() => {
        const cursor = before.length + insert.length;
        bodyTextareaRef.value?.focus();
        bodyTextareaRef.value?.setSelectionRange(cursor, cursor);
    });
}

function toggleCaptureField(code) {
    if (!selectedNode.value) {
        return;
    }

    const index = selectedNode.value.capture_field_codes.indexOf(code);
    if (index === -1) {
        selectedNode.value.capture_field_codes.push(code);
    } else {
        selectedNode.value.capture_field_codes.splice(index, 1);
    }
}

function createCaptureField() {
    const code = newFieldCode.value.trim().toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
    const label = newFieldLabel.value.trim();

    if (!code || !label) {
        return;
    }

    router.post(route('scripts.editor.capture-fields.store'), {
        code,
        label,
        value_type: 'text',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newFieldCode.value = '';
            newFieldLabel.value = '';
        },
    });
}

function deleteCaptureField(fieldId) {
    if (!window.confirm('Удалить поле из справочника?')) {
        return;
    }

    router.delete(route('scripts.editor.capture-fields.destroy', fieldId), {
        preserveScroll: true,
    });
}

function saveNodeAsTemplate() {
    if (!selectedNode.value) {
        return;
    }

    const title = window.prompt('Название шаблона', selectedNode.value.client_key);
    if (!title?.trim()) {
        return;
    }

    router.post(route('scripts.editor.node-templates.store'), {
        title: title.trim(),
        kind: selectedNode.value.kind,
        body: selectedNode.value.body,
        hint: selectedNode.value.hint || null,
        tags: selectedNode.value.tags,
        capture_field_codes: selectedNode.value.capture_field_codes,
        default_transitions: outgoingForSelectedNode().map((transition) => {
            const targetNode = graphNodes.find((node) => node.client_key === transition.to_client_key);

            return {
                customer_label: transition.customer_label || null,
                sales_script_reaction_class_id: transition.sales_script_reaction_class_id,
                conversation_effect: transition.conversation_effect,
                momentum_delta: transition.momentum_delta,
                next_move_preview: transition.next_move_preview || null,
                target_kind: targetNode?.kind ?? 'say',
                target_body: targetNode?.body ?? 'Новая реплика оператора',
                target_hint: targetNode?.hint || null,
                target_tags: targetNode?.tags ?? [],
            };
        }),
    }, { preserveScroll: true });
}

function outgoingForSelectedNode() {
    if (!selectedNode.value) {
        return [];
    }

    return graphTransitions.filter((transition) => transition.from_client_key === selectedNode.value.client_key);
}

function insertFromTemplate(template) {
    const key = uniqueClientKey(String(template.title).toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '') || 'block');
    const baseNode = selectedNode.value ?? graphNodes[graphNodes.length - 1];
    const canvasX = baseNode ? baseNode.canvas_x + 40 : 60;
    const canvasY = baseNode ? baseNode.canvas_y + 40 : 60;

    graphNodes.push({
        client_key: key,
        kind: template.kind,
        body: template.body,
        body_variant_b: '',
        ab_enabled: false,
        ab_variant_b_weight: 50,
        hint: template.hint ?? '',
        tags: Array.isArray(template.tags) ? [...template.tags] : [],
        capture_field_codes: Array.isArray(template.capture_field_codes) ? [...template.capture_field_codes] : [],
        sort_order: graphNodes.length,
        canvas_x: canvasX,
        canvas_y: canvasY,
    });

    const defaults = Array.isArray(template.default_transitions) ? template.default_transitions : [];
    defaults.forEach((transition, index) => {
        const targetKey = uniqueClientKey(`${key}_answer_${index + 1}`);
        graphNodes.push({
            client_key: targetKey,
            kind: transition.target_kind ?? 'say',
            body: transition.target_body ?? 'Новая реплика оператора',
            body_variant_b: '',
            ab_enabled: false,
            ab_variant_b_weight: 50,
            hint: transition.target_hint ?? '',
            tags: Array.isArray(transition.target_tags) ? [...transition.target_tags] : [],
            capture_field_codes: [],
            sort_order: graphNodes.length,
            canvas_x: canvasX + 300,
            canvas_y: canvasY + index * 190,
        });
        graphTransitions.push({
            local_id: `template-${edgeSeq.value}`,
            from_client_key: key,
            to_client_key: targetKey,
            target_type: 'node',
            target_sales_script_version_id: null,
            sales_script_reaction_class_id: transition.sales_script_reaction_class_id ?? null,
            customer_label: transition.customer_label ?? '',
            conversation_effect: transition.conversation_effect ?? null,
            momentum_delta: transition.momentum_delta ?? null,
            next_move_preview: transition.next_move_preview ?? '',
            sort_order: graphTransitions.length,
        });
        edgeSeq.value += 1;
    });
    onSelectNode(key);
}

function deleteTemplate(templateId) {
    if (!window.confirm('Удалить шаблон?')) {
        return;
    }

    router.delete(route('scripts.editor.node-templates.destroy', templateId), {
        preserveScroll: true,
    });
}

function normalizeTag(value) {
    return String(value ?? '').trim().toLowerCase();
}

function addNodeTag() {
    if (!selectedNode.value) {
        return;
    }

    const tag = normalizeTag(tagDraft.value);
    if (tag === '') {
        return;
    }

    if (!selectedNode.value.tags.includes(tag)) {
        selectedNode.value.tags.push(tag);
    }

    tagDraft.value = '';
}

function removeNodeTag(tag) {
    if (!selectedNode.value) {
        return;
    }

    selectedNode.value.tags = selectedNode.value.tags.filter((item) => item !== tag);

    if (activeTagFilter.value === tag) {
        activeTagFilter.value = null;
    }
}

function onAddAnswer(fromClientKey) {
    onSelectNode(fromClientKey);

    const targetKey = graphNodes.find((node) => node.client_key !== fromClientKey)?.client_key ?? fromClientKey;
    const localId = `new-${edgeSeq.value}`;
    edgeSeq.value += 1;

    graphTransitions.push({
        local_id: localId,
        from_client_key: fromClientKey,
        to_client_key: targetKey,
        target_type: 'node',
        target_sales_script_version_id: null,
        sales_script_reaction_class_id: props.reactionClasses[0]?.id ?? null,
        customer_label: '',
        conversation_effect: null,
        momentum_delta: null,
        next_move_preview: '',
        sort_order: graphTransitions.length,
    });
    selectedTransitionId.value = localId;
}

function onCreateTransition({ from_client_key, to_client_key }) {
    const exists = graphTransitions.some(
        (t) => t.from_client_key === from_client_key
            && t.to_client_key === to_client_key
            && t.sales_script_reaction_class_id === null
            && !t.customer_label,
    );

    if (exists) {
        return;
    }

    const localId = `new-${edgeSeq.value}`;
    edgeSeq.value += 1;

    graphTransitions.push({
        local_id: localId,
        from_client_key,
        to_client_key,
        target_type: 'node',
        target_sales_script_version_id: null,
        sales_script_reaction_class_id: null,
        customer_label: '',
        conversation_effect: null,
        momentum_delta: null,
        next_move_preview: '',
        sort_order: graphTransitions.length,
    });
    selectedTransitionId.value = localId;
}

function addNode() {
    const key = uniqueClientKey(`step_${graphNodes.length + 1}`);
    graphNodes.push({
        client_key: key,
        kind: props.nodeKinds[0]?.value ?? 'say',
        body: 'Новая реплика оператора',
        body_variant_b: '',
        ab_enabled: false,
        ab_variant_b_weight: 50,
        hint: '',
        tags: [],
        capture_field_codes: [],
        sort_order: graphNodes.length,
        canvas_x: 60 + (graphNodes.length % 4) * 260,
        canvas_y: 60 + Math.floor(graphNodes.length / 4) * 180,
    });
    onSelectNode(key);

    if (!entryNodeKey.value) {
        entryNodeKey.value = key;
    }
}

function removeNode(clientKey) {
    const index = graphNodes.findIndex((node) => node.client_key === clientKey);
    if (index === -1) {
        return;
    }

    graphNodes.splice(index, 1);

    for (let i = graphTransitions.length - 1; i >= 0; i -= 1) {
        const transition = graphTransitions[i];
        if (transition.from_client_key === clientKey || transition.to_client_key === clientKey) {
            graphTransitions.splice(i, 1);
        }
    }

    if (selectedNodeKey.value === clientKey) {
        onSelectNode(graphNodes[0]?.client_key ?? null);
    }

    if (entryNodeKey.value === clientKey) {
        entryNodeKey.value = graphNodes[0]?.client_key ?? '';
    }
}

function removeTransition(localId) {
    const index = graphTransitions.findIndex((transition) => transition.local_id === localId);
    if (index !== -1) {
        graphTransitions.splice(index, 1);
    }

    if (selectedTransitionId.value === localId) {
        selectedTransitionId.value = null;
    }
}

function moveTransition(direction) {
    if (!selectedTransition.value) {
        return;
    }

    const index = graphTransitions.findIndex((t) => t.local_id === selectedTransition.value.local_id);
    const target = index + direction;

    if (index < 0 || target < 0 || target >= graphTransitions.length) {
        return;
    }

    const [item] = graphTransitions.splice(index, 1);
    graphTransitions.splice(target, 0, item);
}

function publish() {
    router.post(route('scripts.editor.versions.publish', props.payload.version.id));
}

function unpublish() {
    router.post(route('scripts.editor.versions.unpublish', props.payload.version.id));
}

function buildGraphPayload() {
    const nodes = graphNodes.map((node, index) => ({
        client_key: node.client_key.trim(),
        kind: node.kind,
        body: node.body,
        body_variant_b: node.body_variant_b || null,
        ab_enabled: Boolean(node.ab_enabled),
        ab_variant_b_weight: Number(node.ab_variant_b_weight ?? 50),
        hint: node.hint || null,
        tags: node.tags ?? [],
        capture_field_codes: node.capture_field_codes ?? [],
        sort_order: index,
        canvas_x: node.canvas_x,
        canvas_y: node.canvas_y,
    }));

    const transitions = graphTransitions.map((transition, index) => ({
        from_client_key: transition.from_client_key,
        to_client_key: transition.to_client_key,
        target_type: transition.target_type ?? 'node',
        target_sales_script_version_id: transition.target_type === 'script'
            ? transition.target_sales_script_version_id
            : null,
        sales_script_reaction_class_id: transition.sales_script_reaction_class_id,
        customer_label: transition.customer_label?.trim() || null,
        conversation_effect: transition.conversation_effect || null,
        momentum_delta: transition.momentum_delta === null ? null : Number(transition.momentum_delta),
        next_move_preview: transition.next_move_preview?.trim() || null,
        sort_order: index,
    }));

    return {
        entry_node_key: entryNodeKey.value.trim() === '' ? null : entryNodeKey.value.trim(),
        nodes,
        transitions,
    };
}

function scheduleAutosave() {
    if (autosaveTimer.value) {
        clearTimeout(autosaveTimer.value);
    }

    autosaveHint.value = 'Есть несохранённые изменения…';

    autosaveTimer.value = setTimeout(() => {
        autosaveTimer.value = null;
        void runAutosave();
    }, 2000);
}

async function runAutosave() {
    if (saving.value || autosaving.value) {
        autosaveQueued.value = true;

        return;
    }

    autosaving.value = true;
    autosaveHint.value = 'Автосохранение…';

    try {
        await axios.put(
            route('scripts.editor.versions.graph.update', props.payload.version.id),
            {
                ...buildGraphPayload(),
                autosave: true,
            },
        );
        autosaveHint.value = 'Черновик сохранён автоматически';
    } catch {
        autosaveHint.value = 'Не удалось автосохранить — нажмите «Сохранить»';
    } finally {
        autosaving.value = false;

        if (autosaveQueued.value) {
            autosaveQueued.value = false;
            scheduleAutosave();
        }
    }
}

watch(
    [graphNodes, graphTransitions, entryNodeKey],
    () => {
        scheduleAutosave();
    },
    { deep: true },
);

onBeforeUnmount(() => {
    if (autosaveTimer.value) {
        clearTimeout(autosaveTimer.value);
        autosaveTimer.value = null;
    }

    if (!saving.value && !autosaving.value) {
        void runAutosave();
    }
});

function saveGraph() {
    if (autosaveTimer.value) {
        clearTimeout(autosaveTimer.value);
        autosaveTimer.value = null;
    }

    saving.value = true;
    autosaveHint.value = '';

    router.put(
        route('scripts.editor.versions.graph.update', props.payload.version.id),
        buildGraphPayload(),
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

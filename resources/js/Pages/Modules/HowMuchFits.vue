<template>
    <div class="flex min-h-0 flex-1 flex-col gap-3">
        <div class="flex shrink-0 flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600 dark:text-sky-300">Модуль</div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Сколько влезет?</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">3D-схема загрузки для аргументации ставки и выбора прицепа.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="primary-action" @click="createProject">Новый расчёт</button>
                <button type="button" class="secondary-action" :disabled="!projectForm?.id" @click="saveProject">Сохранить</button>
            </div>
        </div>

        <div
            class="grid min-h-0 flex-1 gap-3"
            :class="activeStep === 'calculation'
                ? 'xl:grid-cols-[290px,minmax(360px,0.65fr),minmax(720px,1.35fr)]'
                : 'xl:grid-cols-[290px,minmax(0,1fr)]'"
        >
            <aside class="panel min-h-0 overflow-hidden">
                <div class="border-b border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Список проектов {{ projects.length }} / 300</div>
                    <div class="relative mt-3">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input v-model="projectSearch" type="search" class="field pl-9" placeholder="Поиск" />
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-2">
                    <button
                        v-for="project in filteredProjects"
                        :key="project.id"
                        type="button"
                        class="flex w-full items-start gap-3 rounded-2xl px-3 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800/70"
                        :class="projectForm?.id === project.id ? 'bg-sky-100 text-sky-950 dark:bg-sky-950 dark:text-sky-50' : ''"
                        @click="selectProject(project.id)"
                    >
                        <FolderOpen class="mt-0.5 h-5 w-5 shrink-0" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold">{{ project.name }}</span>
                            <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">
                                добавлено {{ project.created_at }}, обновлено {{ project.updated_at }}
                            </span>
                            <span v-if="project.transport_name" class="mt-1 block truncate text-xs text-sky-700 dark:text-sky-300">{{ project.transport_name }}</span>
                        </span>
                    </button>
                </div>
            </aside>

            <section class="panel min-h-0 overflow-hidden">
                <div class="border-b border-zinc-200 p-2 dark:border-zinc-800">
                    <div class="grid grid-cols-4 gap-1">
                        <button v-for="step in steps" :key="step.key" type="button" class="step-button" :class="activeStep === step.key ? 'step-button-active' : ''" @click="activeStep = step.key">
                            <component :is="step.icon" class="h-4 w-4" />
                            <span>{{ step.label }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="projectForm" class="min-h-0 flex-1 overflow-y-auto p-4">
                    <div v-if="activeStep === 'projects'" class="space-y-4">
                        <div>
                            <label class="label">Название проекта</label>
                            <input v-model="projectForm.name" class="field" />
                        </div>
                        <div>
                            <label class="label">Комментарий менеджера</label>
                            <textarea v-model="projectForm.notes" rows="5" class="field" placeholder="Что важно объяснить клиенту: почему нужен тент, почему не проходит по высоте, что меняется при штабелировании..." />
                        </div>
                        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
                            <div class="font-semibold">Аргумент для клиента</div>
                            <div class="mt-1">{{ salesArgument }}</div>
                        </div>
                        <button type="button" class="danger-action" :disabled="projects.length <= 1" @click="deleteProject">Удалить проект</button>
                    </div>

                    <div v-else-if="activeStep === 'cargo'" class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold">Грузовые группы</div>
                                <div class="text-xs text-zinc-500">Группируйте груз по получателям или партиям.</div>
                            </div>
                            <button type="button" class="secondary-action" @click="addCargoGroup">Добавить группу</button>
                        </div>

                        <div v-for="(group, groupIndex) in projectForm.cargo_groups" :key="group.local_id" class="rounded-3xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="grid gap-2 md:grid-cols-[1fr,1fr,4rem]">
                                <input v-model="group.name" class="field" placeholder="Группа" />
                                <input v-model="group.recipient_name" class="field" placeholder="Получатель" />
                                <input v-model="group.color" type="color" class="h-10 w-full rounded-xl border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900" />
                            </div>

                            <div class="mt-4 space-y-3">
                                <div v-for="(item, itemIndex) in group.items" :key="item.local_id" class="rounded-2xl bg-zinc-50 p-3 dark:bg-zinc-950">
                                    <div class="grid gap-2 lg:grid-cols-12">
                                        <input v-model="item.name" class="field lg:col-span-3" placeholder="Название" />
                                        <select v-model="item.package_type" class="field lg:col-span-2">
                                            <option value="pallet">Паллета</option>
                                            <option value="box">Коробка</option>
                                            <option value="crate">Ящик</option>
                                            <option value="roll">Рулон</option>
                                            <option value="bag">Мешок</option>
                                            <option value="custom">Другое</option>
                                        </select>
                                        <input v-model.number="item.quantity" type="number" min="1" class="field lg:col-span-1" placeholder="шт" />
                                        <input v-model.number="item.length_mm" type="number" min="1" class="field lg:col-span-1" placeholder="Д, мм" />
                                        <input v-model.number="item.width_mm" type="number" min="1" class="field lg:col-span-1" placeholder="Ш, мм" />
                                        <input v-model.number="item.height_mm" type="number" min="1" class="field lg:col-span-1" placeholder="В, мм" />
                                        <input v-model.number="item.weight_kg" type="number" min="0" step="0.01" class="field lg:col-span-1" placeholder="кг" />
                                        <input v-model="item.color" type="color" class="h-10 rounded-xl border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900 lg:col-span-1" />
                                        <button type="button" class="rounded-xl border border-rose-200 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40" @click="removeCargoItem(groupIndex, itemIndex)">×</button>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-3 text-xs text-zinc-600 dark:text-zinc-300">
                                        <label class="inline-flex items-center gap-1.5"><input v-model="item.can_rotate" type="checkbox" class="rounded" /> поворот</label>
                                        <label class="inline-flex items-center gap-1.5"><input v-model="item.stackable" type="checkbox" class="rounded" /> ярусы</label>
                                        <label class="inline-flex items-center gap-1.5"><input v-model="item.can_tilt" type="checkbox" class="rounded" /> кантование</label>
                                        <label class="inline-flex items-center gap-1.5">макс. ярус <input v-model.number="item.max_stack" type="number" min="1" max="20" class="h-7 w-16 rounded border border-zinc-200 bg-white px-2 dark:border-zinc-700 dark:bg-zinc-900" /></label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 flex justify-between gap-2">
                                <button type="button" class="secondary-action" @click="addCargoItem(groupIndex)">Добавить груз</button>
                                <button v-if="projectForm.cargo_groups.length > 1" type="button" class="danger-action" @click="removeCargoGroup(groupIndex)">Удалить группу</button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeStep === 'transport'" class="space-y-4">
                        <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold">Выбранный транспорт</div>
                                    <div class="text-xs text-zinc-500">{{ selectedTransport ? transportLabel(selectedTransport) : 'Не выбран' }}</div>
                                </div>
                                <button type="button" class="secondary-action" @click="resetTemplateDraft">Добавить шаблон</button>
                            </div>
                        </div>

                        <div class="grid gap-2 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800 md:grid-cols-2">
                            <input v-model="templateDraft.name" class="field md:col-span-2" placeholder="Название транспорта" />
                            <select v-model="templateDraft.category" class="field">
                                <option value="truck">Автотранспорт</option>
                                <option value="container">Контейнер</option>
                                <option value="pallet">Паллет</option>
                                <option value="platform">Платформа</option>
                                <option value="custom">Другое</option>
                            </select>
                            <input v-model.number="templateDraft.max_payload_kg" type="number" min="0" class="field" placeholder="Грузоподъёмность, кг" />
                            <input v-model.number="templateDraft.length_mm" type="number" min="1" class="field" placeholder="Длина, мм" />
                            <input v-model.number="templateDraft.width_mm" type="number" min="1" class="field" placeholder="Ширина, мм" />
                            <input v-model.number="templateDraft.height_mm" type="number" min="1" class="field" placeholder="Высота, мм" />
                            <input v-model.number="templateDraft.axles_count" type="number" min="1" class="field" placeholder="Оси" />
                            <label class="inline-flex items-center gap-2 text-sm"><input v-model="templateDraft.is_active" type="checkbox" class="rounded" /> Активен</label>
                            <button type="button" class="primary-action" @click="saveTransportTemplate">{{ templateDraft.id ? 'Сохранить шаблон' : 'Добавить в справочник' }}</button>
                        </div>

                        <div class="space-y-2">
                            <div v-for="template in transportTemplates" :key="template.id" class="flex items-center justify-between gap-3 rounded-2xl border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <button type="button" class="min-w-0 flex-1 text-left" @click="selectTransport(template.id)">
                                    <div class="truncate text-sm font-semibold">{{ template.name }}</div>
                                    <div class="text-xs text-zinc-500">{{ transportLabel(template) }}</div>
                                </button>
                                <div class="flex shrink-0 gap-2">
                                    <button type="button" class="secondary-action" @click="editTransportTemplate(template)">Редактировать</button>
                                    <button type="button" class="danger-action" @click="deleteTransportTemplate(template)">Удалить</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="metric-card"><span>Итого</span><strong>{{ layoutResult.totalUnits }} шт / {{ formatKg(layoutResult.totalWeightKg) }}</strong></div>
                            <div class="metric-card"><span>Занято</span><strong>{{ layoutResult.ldm.toFixed(2) }} LDM / {{ layoutResult.usedVolumePercent.toFixed(1) }}%</strong></div>
                            <div class="metric-card"><span>Свободно</span><strong>{{ formatMm(layoutResult.freeLengthMm) }} / {{ formatM3(layoutResult.freeVolumeM3) }}</strong></div>
                            <div class="metric-card" :class="layoutResult.fits ? 'border-emerald-300 text-emerald-700 dark:text-emerald-300' : 'border-rose-300 text-rose-700 dark:text-rose-300'"><span>Статус</span><strong>{{ layoutResult.fits ? 'Влезает' : 'Не влезает' }}</strong></div>
                        </div>
                        <div class="rounded-2xl border border-zinc-200 p-4 text-sm dark:border-zinc-800">
                            <div class="font-semibold">Что сказать клиенту</div>
                            <p class="mt-2 leading-6 text-zinc-600 dark:text-zinc-300">{{ salesArgument }}</p>
                        </div>
                        <div v-if="layoutResult.warnings.length" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                            <div class="font-semibold">Предупреждения</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li v-for="warning in layoutResult.warnings" :key="warning">{{ warning }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="activeStep === 'calculation'"
                class="panel min-h-0 overflow-hidden"
            >
                <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <div>
                        <div class="text-sm font-semibold">Расчёт загрузки</div>
                        <div class="text-xs text-zinc-500">{{ selectedTransport ? selectedTransport.name : 'Выберите транспорт' }}</div>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <label class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs font-semibold dark:border-zinc-700 dark:bg-zinc-900">
                            <input v-model="manualMode" type="checkbox" class="rounded" />
                            Ручная раскладка
                        </label>
                        <button type="button" class="secondary-action" @click="rotateScene(-12, 0)">Сцена ←</button>
                        <button type="button" class="secondary-action" @click="rotateScene(12, 0)">Сцена →</button>
                        <button type="button" class="secondary-action" @click="rotateScene(0, 8)">Наклон ↑</button>
                        <button type="button" class="secondary-action" @click="rotateScene(0, -8)">Наклон ↓</button>
                        <button type="button" class="secondary-action" @click="resetSceneView">Вид по умолчанию</button>
                        <button type="button" class="secondary-action" @click="resetManualPlacements">Сбросить позиции</button>
                        <button type="button" class="secondary-action" @click="activeStep = 'calculation'">Сводка</button>
                    </div>
                </div>

                <div class="grid min-h-0 flex-1 grid-rows-[minmax(360px,1fr),auto] overflow-hidden">
                    <div
                        ref="sceneViewport"
                        class="scene-viewport relative overflow-hidden bg-gradient-to-br from-slate-50 to-sky-50 dark:from-zinc-950 dark:to-sky-950/30"
                        @pointerdown="startSceneRotate"
                    >
                        <div class="scene-hint">
                            Тяните фон мышью, чтобы вращать сцену. Груз тяните мышью по полу прицепа.
                        </div>
                        <div v-if="selectedTransport" class="scene-shell">
                            <div class="scene" :style="sceneTransformStyle">
                                <div class="truck-shadow" />
                                <div class="trailer" :style="trailerStyle">
                                    <div class="trailer-floor">
                                        <span class="floor-label">Пол прицепа / зона размещения</span>
                                    </div>
                                    <div class="trailer-grid" />
                                    <div
                                        v-for="block in layoutResult.blocks"
                                        :key="block.key"
                                        class="cargo-cube"
                                        :class="[
                                            manualMode ? 'cargo-cube-manual' : '',
                                            selectedBlockKey === block.key ? 'cargo-cube-selected' : '',
                                            block.manual ? 'cargo-cube-positioned' : '',
                                        ]"
                                        :style="cubeStyle(block)"
                                        :title="`${block.name}: ${block.count} шт`"
                                        @pointerdown.stop.prevent="startBlockDrag($event, block)"
                                        @click.stop="selectBlock(block)"
                                    >
                                        <span class="cargo-face cargo-face-bottom" />
                                        <span class="cargo-face cargo-face-top">
                                            <span class="cargo-direction">→</span>
                                            <span>{{ block.count > 1 ? block.count : '' }}</span>
                                        </span>
                                        <span class="cargo-face cargo-face-front" />
                                        <span class="cargo-face cargo-face-back" />
                                        <span class="cargo-face cargo-face-left" />
                                        <span class="cargo-face cargo-face-right" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex h-full items-center justify-center text-sm text-zinc-500">Выберите транспорт для расчёта.</div>
                    </div>

                    <div class="max-h-56 overflow-y-auto border-t border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div class="text-sm font-semibold">Груз в сцене</div>
                                <div class="text-xs text-zinc-500">{{ layoutResult.placedUnits }} / {{ layoutResult.totalUnits }} шт размещено</div>
                            </div>
                            <div v-if="manualMode" class="text-xs text-zinc-500">
                                Выберите блок на сцене и двигайте его по кузову.
                            </div>
                        </div>

                        <div v-if="manualMode" class="mb-4 rounded-2xl border border-sky-200 bg-sky-50 p-3 dark:border-sky-900 dark:bg-sky-950/40">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-sky-950 dark:text-sky-100">
                                        {{ selectedBlock ? selectedBlock.name : 'Блок не выбран' }}
                                    </div>
                                    <div v-if="selectedBlock" class="text-xs text-sky-700 dark:text-sky-300">
                                        X {{ formatMm(selectedBlock.x) }}, Y {{ formatMm(selectedBlock.y) }},
                                        {{ selectedBlock.rotated ? 'повёрнут' : 'без поворота' }},
                                        {{ selectedBlock.tilted ? 'наклонён' : 'без наклона' }}
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-1">
                                    <span />
                                    <button type="button" class="manual-button" :disabled="!selectedBlock" @click="nudgeSelectedBlock(0, -250)">↑</button>
                                    <span />
                                    <button type="button" class="manual-button" :disabled="!selectedBlock" @click="nudgeSelectedBlock(-250, 0)">←</button>
                                    <button type="button" class="manual-button" :disabled="!selectedBlock" @click="rotateSelectedBlock">↻</button>
                                    <button type="button" class="manual-button" :disabled="!selectedBlock" @click="nudgeSelectedBlock(250, 0)">→</button>
                                    <span />
                                    <button type="button" class="manual-button" :disabled="!selectedBlock" @click="nudgeSelectedBlock(0, 250)">↓</button>
                                    <span />
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="secondary-action" :disabled="!selectedBlock" @click="lockSelectedBlock">Зафиксировать</button>
                                <button type="button" class="secondary-action" :disabled="!selectedBlock" @click="releaseSelectedBlock">Вернуть в авто</button>
                                <span class="text-xs leading-8 text-sky-700 dark:text-sky-300">
                                    Клавиатура: ←/→ вокруг вертикали, ↑/↓ вокруг горизонтали.
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-2 md:grid-cols-2">
                            <div v-for="item in cargoFlat" :key="item.local_id" class="flex items-center gap-2 rounded-xl border border-zinc-200 px-3 py-2 text-xs dark:border-zinc-800">
                                <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: item.color }" />
                                <span class="min-w-0 flex-1 truncate">{{ item.name }}</span>
                                <span>{{ item.quantity }} шт</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Boxes, Calculator, FolderOpen, Package, Search, Truck } from 'lucide-vue-next';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'modules', activeSubKey: 'modules-how-much-fits' }, () => page),
});

const props = defineProps({
    projects: { type: Array, default: () => [] },
    selectedProject: { type: Object, default: null },
    transportTemplates: { type: Array, default: () => [] },
});

const steps = [
    { key: 'projects', label: 'Проект', icon: FolderOpen },
    { key: 'cargo', label: 'Груз', icon: Package },
    { key: 'transport', label: 'Транспорт', icon: Truck },
    { key: 'calculation', label: 'Расчёт', icon: Calculator },
];

const activeStep = ref('calculation');
const projectSearch = ref('');
const projectForm = ref(cloneProject(props.selectedProject));
const templateDraft = ref(blankTemplate());
const manualMode = ref(Boolean(props.selectedProject?.calculation?.manual_mode));
const selectedBlockKey = ref(props.selectedProject?.calculation?.selected_manual_key ?? null);
const sceneViewport = ref(null);
const sceneRotationX = ref(Number(props.selectedProject?.calculation?.scene_view?.rotation_x ?? 58));
const sceneRotationZ = ref(Number(props.selectedProject?.calculation?.scene_view?.rotation_z ?? -34));
const sceneDrag = ref(null);
const blockDrag = ref(null);

watch(() => props.selectedProject, (project) => {
    projectForm.value = cloneProject(project);
    manualMode.value = Boolean(project?.calculation?.manual_mode);
    selectedBlockKey.value = project?.calculation?.selected_manual_key ?? null;
    sceneRotationX.value = Number(project?.calculation?.scene_view?.rotation_x ?? 58);
    sceneRotationZ.value = Number(project?.calculation?.scene_view?.rotation_z ?? -34);
}, { deep: true });

const filteredProjects = computed(() => {
    const query = projectSearch.value.trim().toLowerCase();
    if (!query) {
        return props.projects;
    }
    return props.projects.filter((project) => [project.name, project.transport_name].filter(Boolean).join(' ').toLowerCase().includes(query));
});

const selectedTransport = computed(() => {
    const id = Number(projectForm.value?.selected_transport_template_id);
    return props.transportTemplates.find((template) => Number(template.id) === id) ?? props.transportTemplates[0] ?? null;
});

const cargoFlat = computed(() => {
    return (projectForm.value?.cargo_groups ?? []).flatMap((group) => {
        return (group.items ?? []).map((item) => ({
            ...item,
            source_key: cargoItemKey(item),
            group_name: group.name,
            recipient_name: group.recipient_name,
            color: item.color || group.color || '#60a5fa',
        }));
    });
});

const manualPlacements = computed(() => {
    const placements = projectForm.value?.calculation?.manual_placements ?? {};
    const validPrefixes = new Set(cargoFlat.value.map((item) => item.source_key));
    return Object.fromEntries(
        Object.entries(placements).filter(([key]) => {
            return [...validPrefixes].some((prefix) => key.startsWith(`${prefix}-`));
        }),
    );
});
const layoutResult = computed(() => calculateLayout(selectedTransport.value, cargoFlat.value, manualMode.value ? manualPlacements.value : {}));
const selectedBlock = computed(() => layoutResult.value.blocks.find((block) => block.key === selectedBlockKey.value) ?? null);

watch(layoutResult, (result) => {
    if (selectedBlockKey.value && !result.blocks.some((block) => block.key === selectedBlockKey.value)) {
        selectedBlockKey.value = null;
    }
});

const salesArgument = computed(() => {
    const transport = selectedTransport.value;
    if (!transport) {
        return 'Сначала выберите транспорт из справочника, чтобы рассчитать вместимость и подготовить аргумент для клиента.';
    }
    const result = layoutResult.value;
    if (result.fits) {
        return `Груз помещается в «${transport.name}»: занимает ${result.ldm.toFixed(2)} LDM, ${result.usedVolumePercent.toFixed(1)}% объёма и ${result.usedPayloadPercent.toFixed(1)}% грузоподъёмности. Можно приложить схему как обоснование выбранного прицепа.`;
    }
    return `Груз не помещается в «${transport.name}»: размещено ${result.placedUnits} из ${result.totalUnits} мест. Нужен больший прицеп, второй транспорт или изменение условий штабелирования/поворота.`;
});

const trailerStyle = computed(() => {
    const transport = selectedTransport.value;
    if (!transport) {
        return {};
    }
    const ratio = transport.width_mm / transport.length_mm;
    return {
        width: '760px',
        height: `${Math.max(150, 760 * ratio)}px`,
    };
});

const sceneTransformStyle = computed(() => ({
    transform: `rotateX(${sceneRotationX.value}deg) rotateZ(${sceneRotationZ.value}deg)`,
}));

onMounted(() => {
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', stopPointerInteractions);
    window.addEventListener('keydown', onSceneKeydown);
});

onUnmounted(() => {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', stopPointerInteractions);
    window.removeEventListener('keydown', onSceneKeydown);
});

function cloneProject(project) {
    if (!project) {
        return null;
    }
    return {
        ...project,
        calculation: {
            ...(project.calculation ?? {}),
            manual_placements: project.calculation?.manual_placements ?? {},
            scene_view: project.calculation?.scene_view ?? {},
        },
        cargo_groups: (project.cargo_groups ?? []).map((group) => ({
            ...group,
            local_id: makeLocalId(),
            items: (group.items ?? []).map((item) => ({ ...item, client_key: item.client_key || makeLocalId(), local_id: makeLocalId() })),
        })),
    };
}

function blankTemplate() {
    return {
        id: null,
        name: '',
        category: 'truck',
        length_mm: 13600,
        width_mm: 2450,
        height_mm: 2700,
        max_payload_kg: 22000,
        axles_count: 5,
        is_active: true,
        sort_order: 100,
        settings: {},
    };
}

function blankCargoItem(color = '#60a5fa') {
    return {
        local_id: makeLocalId(),
        client_key: makeLocalId(),
        name: 'Новый груз',
        package_type: 'box',
        quantity: 1,
        length_mm: 1200,
        width_mm: 800,
        height_mm: 1000,
        weight_kg: 100,
        can_rotate: true,
        stackable: false,
        max_stack: 1,
        can_tilt: false,
        color,
    };
}

function createProject() {
    router.post(route('modules.how-much-fits.projects.store'), { name: 'Новый расчёт' });
}

function selectProject(projectId) {
    router.get(route('modules.how-much-fits.index'), { project: projectId }, { preserveState: false, preserveScroll: true });
}

function saveProject() {
    if (!projectForm.value?.id) {
        return;
    }
    router.patch(route('modules.how-much-fits.projects.update', projectForm.value.id), projectPayload());
}

function deleteProject() {
    if (!projectForm.value?.id || !window.confirm(`Удалить проект «${projectForm.value.name}»?`)) {
        return;
    }
    router.delete(route('modules.how-much-fits.projects.destroy', projectForm.value.id));
}

function projectPayload() {
    return {
        name: projectForm.value.name,
        notes: projectForm.value.notes,
        selected_transport_template_id: projectForm.value.selected_transport_template_id,
        calculation: {
            fits: layoutResult.value.fits,
            ldm: layoutResult.value.ldm,
            placed_units: layoutResult.value.placedUnits,
            total_units: layoutResult.value.totalUnits,
            used_volume_percent: layoutResult.value.usedVolumePercent,
            used_payload_percent: layoutResult.value.usedPayloadPercent,
            warnings: layoutResult.value.warnings,
            manual_mode: manualMode.value,
            selected_manual_key: selectedBlockKey.value,
            manual_placements: manualPlacements.value,
            scene_view: {
                rotation_x: sceneRotationX.value,
                rotation_z: sceneRotationZ.value,
            },
        },
        cargo_groups: projectForm.value.cargo_groups.map((group) => ({
            name: group.name,
            recipient_name: group.recipient_name,
            color: group.color,
            items: group.items.map((item) => ({
                name: item.name,
                client_key: item.client_key,
                package_type: item.package_type,
                quantity: Number(item.quantity || 1),
                length_mm: Number(item.length_mm || 1),
                width_mm: Number(item.width_mm || 1),
                height_mm: Number(item.height_mm || 1),
                weight_kg: Number(item.weight_kg || 0),
                can_rotate: Boolean(item.can_rotate),
                stackable: Boolean(item.stackable),
                max_stack: Number(item.max_stack || 1),
                can_tilt: Boolean(item.can_tilt),
                color: item.color,
            })),
        })),
    };
}

function addCargoGroup() {
    const color = randomColor(projectForm.value.cargo_groups.length);
    projectForm.value.cargo_groups.push({
        local_id: makeLocalId(),
        name: `Грузовая группа #${projectForm.value.cargo_groups.length + 1}`,
        recipient_name: '',
        color,
        items: [blankCargoItem(color)],
    });
}

function removeCargoGroup(index) {
    projectForm.value.cargo_groups.splice(index, 1);
}

function addCargoItem(groupIndex) {
    const group = projectForm.value.cargo_groups[groupIndex];
    group.items.push(blankCargoItem(group.color));
}

function removeCargoItem(groupIndex, itemIndex) {
    projectForm.value.cargo_groups[groupIndex].items.splice(itemIndex, 1);
}

function selectTransport(templateId) {
    projectForm.value.selected_transport_template_id = templateId;
}

function resetTemplateDraft() {
    templateDraft.value = blankTemplate();
}

function editTransportTemplate(template) {
    templateDraft.value = { ...template };
}

function saveTransportTemplate() {
    const payload = { ...templateDraft.value };
    if (payload.id) {
        router.patch(route('modules.how-much-fits.transport-templates.update', payload.id), payload, { preserveScroll: true });
    } else {
        router.post(route('modules.how-much-fits.transport-templates.store'), payload, {
            preserveScroll: true,
            onSuccess: resetTemplateDraft,
        });
    }
}

function deleteTransportTemplate(template) {
    if (window.confirm(`Удалить шаблон «${template.name}»?`)) {
        router.delete(route('modules.how-much-fits.transport-templates.destroy', template.id), { preserveScroll: true });
    }
}

function calculateLayout(transport, items, placements = {}) {
    if (!transport) {
        return emptyLayout();
    }
    const blocks = [];
    const warnings = [];
    let cursorX = 0;
    let cursorY = 0;
    let rowDepth = 0;
    let placedUnits = 0;
    let totalUnits = 0;
    let totalWeightKg = 0;
    let totalVolumeM3 = 0;
    let usedLengthMm = 0;
    let overflow = false;
    const maxBlocks = 260;

    for (const item of items) {
        const quantity = Math.max(0, Number(item.quantity || 0));
        totalUnits += quantity;
        totalWeightKg += quantity * Number(item.weight_kg || 0);
        totalVolumeM3 += quantity * item.length_mm * item.width_mm * item.height_mm / 1_000_000_000;

        let remaining = quantity;
        let unitIndex = 0;
        while (remaining > 0) {
            const blockKey = `${item.source_key}-${unitIndex}`;
            const manual = placements[blockKey] ?? null;
            const orientation = manual
                ? {
                    length: manual.rotated ? Number(item.width_mm) : Number(item.length_mm),
                    width: manual.rotated ? Number(item.length_mm) : Number(item.width_mm),
                    newRow: false,
                    manual,
                }
                : chooseOrientation(transport, item, cursorX, cursorY, rowDepth);
            if (!orientation) {
                overflow = true;
                warnings.push(`${item.name}: не удалось разместить ${remaining} шт.`);
                break;
            }

            if (!manual && orientation.newRow) {
                cursorX = 0;
                cursorY += rowDepth;
                rowDepth = 0;
            }

            const stackLimit = item.stackable
                ? Math.max(1, Math.min(Number(item.max_stack || 1), Math.floor(transport.height_mm / item.height_mm)))
                : 1;
            const count = Math.min(remaining, stackLimit);
            const block = {
                key: blockKey,
                name: item.name,
                count,
                color: item.color,
                x: manual ? Number(manual.x || 0) : cursorX,
                y: manual ? Number(manual.y || 0) : cursorY,
                z: 0,
                length: orientation.length,
                width: orientation.width,
                height: item.height_mm * count,
                base_length: Number(item.length_mm),
                base_width: Number(item.width_mm),
                rotated: Boolean(manual?.rotated),
                tilted: Number(manual?.tilted ?? 0),
                locked: Boolean(manual?.locked),
                manual: Boolean(manual),
            };
            if (blocks.length < maxBlocks) {
                blocks.push(block);
            }
            placedUnits += count;
            remaining -= count;
            usedLengthMm = Math.max(usedLengthMm, block.x + orientation.length);
            if (!manual) {
                cursorX += orientation.length;
                rowDepth = Math.max(rowDepth, orientation.width);
            }
            unitIndex += count;
        }
    }

    if (totalWeightKg > transport.max_payload_kg) {
        warnings.push(`Перевес: ${formatKg(totalWeightKg)} при лимите ${formatKg(transport.max_payload_kg)}.`);
    }
    if (blocks.length >= maxBlocks && placedUnits < totalUnits) {
        warnings.push('В 3D-сцене показана часть мест, чтобы интерфейс не тормозил.');
    }
    const manualWarnings = manualPlacementWarnings(transport, blocks);
    warnings.push(...manualWarnings);

    const transportVolumeM3 = transport.length_mm * transport.width_mm * transport.height_mm / 1_000_000_000;
    const fits = !overflow && placedUnits === totalUnits && totalWeightKg <= transport.max_payload_kg && manualWarnings.length === 0;

    return {
        blocks,
        warnings: [...new Set(warnings)],
        fits,
        totalUnits,
        placedUnits,
        totalWeightKg,
        totalVolumeM3,
        ldm: usedLengthMm / 1000,
        freeLengthMm: Math.max(0, transport.length_mm - usedLengthMm),
        freeVolumeM3: Math.max(0, transportVolumeM3 - totalVolumeM3),
        usedVolumePercent: transportVolumeM3 > 0 ? Math.min(999, totalVolumeM3 / transportVolumeM3 * 100) : 0,
        usedPayloadPercent: transport.max_payload_kg > 0 ? Math.min(999, totalWeightKg / transport.max_payload_kg * 100) : 0,
    };
}

function manualPlacementWarnings(transport, blocks) {
    const warnings = [];
    for (const block of blocks) {
        if (block.x < 0 || block.y < 0 || block.x + block.length > transport.length_mm || block.y + block.width > transport.width_mm) {
            warnings.push(`${block.name}: ручная позиция выходит за габариты транспорта.`);
        }
    }
    for (let i = 0; i < blocks.length; i++) {
        for (let j = i + 1; j < blocks.length; j++) {
            if (blocksOverlap(blocks[i], blocks[j])) {
                warnings.push(`${blocks[i].name} пересекается с ${blocks[j].name}.`);
            }
        }
    }
    return warnings;
}

function blocksOverlap(a, b) {
    return a.x < b.x + b.length
        && a.x + a.length > b.x
        && a.y < b.y + b.width
        && a.y + a.width > b.y;
}

function chooseOrientation(transport, item, cursorX, cursorY, rowDepth) {
    const variants = [{ length: item.length_mm, width: item.width_mm }];
    if (item.can_rotate && item.length_mm !== item.width_mm) {
        variants.push({ length: item.width_mm, width: item.length_mm });
    }
    for (const variant of variants) {
        if (cursorX + variant.length <= transport.length_mm && cursorY + variant.width <= transport.width_mm && item.height_mm <= transport.height_mm) {
            return { ...variant, newRow: false };
        }
    }
    for (const variant of variants) {
        const nextY = cursorY + rowDepth;
        if (variant.length <= transport.length_mm && nextY + variant.width <= transport.width_mm && item.height_mm <= transport.height_mm) {
            return { ...variant, newRow: true };
        }
    }
    return null;
}

function emptyLayout() {
    return {
        blocks: [],
        warnings: [],
        fits: false,
        totalUnits: 0,
        placedUnits: 0,
        totalWeightKg: 0,
        totalVolumeM3: 0,
        ldm: 0,
        freeLengthMm: 0,
        freeVolumeM3: 0,
        usedVolumePercent: 0,
        usedPayloadPercent: 0,
    };
}

function cubeStyle(block) {
    const transport = selectedTransport.value;
    const zScale = 92 / transport.height_mm;
    return {
        left: `${block.x / transport.length_mm * 100}%`,
        top: `${block.y / transport.width_mm * 100}%`,
        width: `${block.length / transport.length_mm * 100}%`,
        height: `${block.width / transport.width_mm * 100}%`,
        '--cube-color': block.color || '#60a5fa',
        '--cube-height': `${Math.max(12, block.height * zScale)}px`,
        '--cube-rotation': block.rotated ? '90deg' : '0deg',
        '--cube-tilt': `${Number(block.tilted || 0) * 12}deg`,
    };
}

function cargoItemKey(item) {
    return item.client_key ? `cargo-${item.client_key}` : `local-${item.local_id}`;
}

function ensureManualPlacement(block) {
    if (!projectForm.value.calculation) {
        projectForm.value.calculation = {};
    }
    if (!projectForm.value.calculation.manual_placements) {
        projectForm.value.calculation.manual_placements = {};
    }
    const existing = projectForm.value.calculation.manual_placements[block.key] ?? {};
    projectForm.value.calculation.manual_placements = {
        ...projectForm.value.calculation.manual_placements,
        [block.key]: {
            x: Number(existing.x ?? block.x),
            y: Number(existing.y ?? block.y),
            rotated: Boolean(existing.rotated ?? block.rotated),
            tilted: Number(existing.tilted ?? block.tilted ?? 0),
            locked: Boolean(existing.locked ?? block.locked),
        },
    };
    return projectForm.value.calculation.manual_placements[block.key];
}

function selectBlock(block) {
    if (!manualMode.value) {
        return;
    }
    selectedBlockKey.value = block.key;
    ensureManualPlacement(block);
    projectForm.value.calculation.selected_manual_key = block.key;
}

function nudgeSelectedBlock(deltaX, deltaY) {
    if (!selectedBlock.value || !selectedTransport.value) {
        return;
    }
    const placement = ensureManualPlacement(selectedBlock.value);
    const maxX = Math.max(0, selectedTransport.value.length_mm - selectedBlock.value.length);
    const maxY = Math.max(0, selectedTransport.value.width_mm - selectedBlock.value.width);
    updateManualPlacement(selectedBlock.value.key, {
        ...placement,
        x: clamp(Number(placement.x) + deltaX, 0, maxX),
        y: clamp(Number(placement.y) + deltaY, 0, maxY),
    });
}

function rotateSelectedBlock() {
    if (!selectedBlock.value || !selectedTransport.value) {
        return;
    }
    const placement = ensureManualPlacement(selectedBlock.value);
    const nextRotated = !Boolean(placement.rotated);
    const nextLength = nextRotated ? selectedBlock.value.base_width : selectedBlock.value.base_length;
    const nextWidth = nextRotated ? selectedBlock.value.base_length : selectedBlock.value.base_width;
    updateManualPlacement(selectedBlock.value.key, {
        ...placement,
        rotated: nextRotated,
        x: clamp(Number(placement.x), 0, Math.max(0, selectedTransport.value.length_mm - nextLength)),
        y: clamp(Number(placement.y), 0, Math.max(0, selectedTransport.value.width_mm - nextWidth)),
    });
}

function tiltSelectedBlock(direction = 1) {
    if (!selectedBlock.value) {
        return;
    }
    const placement = ensureManualPlacement(selectedBlock.value);
    const current = Number(placement.tilted || 0);
    updateManualPlacement(selectedBlock.value.key, {
        ...placement,
        tilted: current === direction ? 0 : direction,
    });
}

function lockSelectedBlock() {
    if (!selectedBlock.value) {
        return;
    }
    updateManualPlacement(selectedBlock.value.key, {
        ...ensureManualPlacement(selectedBlock.value),
        locked: true,
    });
}

function releaseSelectedBlock() {
    if (!selectedBlock.value || !projectForm.value?.calculation?.manual_placements) {
        return;
    }
    const next = { ...projectForm.value.calculation.manual_placements };
    delete next[selectedBlock.value.key];
    projectForm.value.calculation.manual_placements = next;
    projectForm.value.calculation.selected_manual_key = null;
    selectedBlockKey.value = null;
}

function resetManualPlacements() {
    if (!projectForm.value) {
        return;
    }
    projectForm.value.calculation = {
        ...(projectForm.value.calculation ?? {}),
        manual_placements: {},
        selected_manual_key: null,
    };
    selectedBlockKey.value = null;
}

function updateManualPlacement(key, placement) {
    projectForm.value.calculation.manual_placements = {
        ...(projectForm.value.calculation.manual_placements ?? {}),
        [key]: placement,
    };
}

function startBlockDrag(event, block) {
    if (!manualMode.value || !selectedTransport.value) {
        return;
    }
    event.preventDefault();
    selectBlock(block);
    const placement = ensureManualPlacement(block);
    blockDrag.value = {
        key: block.key,
        startClientX: event.clientX,
        startClientY: event.clientY,
        startX: Number(placement.x || 0),
        startY: Number(placement.y || 0),
        blockLength: block.length,
        blockWidth: block.width,
    };
    event.currentTarget?.setPointerCapture?.(event.pointerId);
}

function startSceneRotate(event) {
    if (event.button !== 0 || blockDrag.value) {
        return;
    }
    if (event.target instanceof Element && event.target.closest('.cargo-cube')) {
        return;
    }
    event.preventDefault();
    sceneDrag.value = {
        startClientX: event.clientX,
        startClientY: event.clientY,
        startRotationX: sceneRotationX.value,
        startRotationZ: sceneRotationZ.value,
    };
}

function onPointerMove(event) {
    if (blockDrag.value && selectedBlock.value && selectedTransport.value && sceneViewport.value) {
        const viewport = sceneViewport.value.getBoundingClientRect();
        const deltaX = event.clientX - blockDrag.value.startClientX;
        const deltaY = event.clientY - blockDrag.value.startClientY;
        const mmPerPxX = selectedTransport.value.length_mm / Math.max(1, viewport.width * 0.72);
        const mmPerPxY = selectedTransport.value.width_mm / Math.max(1, viewport.height * 0.56);
        updateManualPlacement(blockDrag.value.key, {
            ...ensureManualPlacement(selectedBlock.value),
            x: Math.round(clamp(
                blockDrag.value.startX + deltaX * mmPerPxX,
                0,
                Math.max(0, selectedTransport.value.length_mm - blockDrag.value.blockLength),
            ) / 5) * 5,
            y: Math.round(clamp(
                blockDrag.value.startY + deltaY * mmPerPxY,
                0,
                Math.max(0, selectedTransport.value.width_mm - blockDrag.value.blockWidth),
            ) / 5) * 5,
        });

        return;
    }

    if (sceneDrag.value) {
        const deltaX = event.clientX - sceneDrag.value.startClientX;
        const deltaY = event.clientY - sceneDrag.value.startClientY;
        sceneRotationZ.value = sceneDrag.value.startRotationZ + deltaX * 0.25;
        sceneRotationX.value = clamp(sceneDrag.value.startRotationX - deltaY * 0.18, 20, 78);
    }
}

function stopPointerInteractions() {
    blockDrag.value = null;
    sceneDrag.value = null;
}

function onSceneKeydown(event) {
    if (!manualMode.value || activeStep.value !== 'calculation' || !selectedBlock.value) {
        return;
    }
    const target = event.target;
    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement) {
        return;
    }
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        rotateSelectedBlock();
    }
    if (event.key === 'ArrowUp') {
        event.preventDefault();
        tiltSelectedBlock(1);
    }
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        tiltSelectedBlock(-1);
    }
}

function rotateScene(deltaZ, deltaX) {
    sceneRotationZ.value += deltaZ;
    sceneRotationX.value = clamp(sceneRotationX.value + deltaX, 20, 78);
}

function resetSceneView() {
    sceneRotationX.value = 58;
    sceneRotationZ.value = -34;
}

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function makeLocalId() {
    if (globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }
    return `local-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

function randomColor(index) {
    return ['#8b5cf6', '#22c55e', '#f97316', '#06b6d4', '#f43f5e', '#eab308'][index % 6];
}

function transportLabel(template) {
    return `${categoryLabel(template.category)}, ${template.length_mm} × ${template.width_mm} × ${template.height_mm} мм, ${formatKg(template.max_payload_kg)}, ${formatM3(template.length_mm * template.width_mm * template.height_mm / 1_000_000_000)}`;
}

function categoryLabel(category) {
    return {
        truck: 'Автотранспорт',
        container: 'Контейнер',
        pallet: 'Паллет',
        platform: 'Платформа',
        custom: 'Другое',
    }[category] ?? category;
}

function formatKg(value) {
    return `${Number(value || 0).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} кг`;
}

function formatM3(value) {
    return `${Number(value || 0).toLocaleString('ru-RU', { maximumFractionDigits: 1 })} м³`;
}

function formatMm(value) {
    return `${Number(value || 0).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} мм`;
}
</script>

<style scoped>
.panel {
    display: flex;
    flex-direction: column;
    border: 1px solid rgb(228 228 231);
    border-radius: 1.5rem;
    background: white;
    box-shadow: 0 10px 30px rgb(15 23 42 / 0.06);
}

:global(.dark) .panel {
    border-color: rgb(39 39 42);
    background: rgb(24 24 27);
}

.field {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgb(228 228 231);
    background: white;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    outline: none;
}

.field:focus {
    border-color: rgb(14 165 233);
}

:global(.dark) .field {
    border-color: rgb(63 63 70);
    background: rgb(9 9 11);
    color: rgb(244 244 245);
}

.label {
    margin-bottom: 0.375rem;
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(82 82 91);
}

.primary-action,
.secondary-action,
.danger-action {
    border-radius: 0.875rem;
    padding: 0.5rem 0.875rem;
    font-size: 0.875rem;
    font-weight: 600;
    transition: 0.15s ease;
}

.primary-action {
    background: rgb(2 132 199);
    color: white;
}

.secondary-action {
    border: 1px solid rgb(228 228 231);
    background: white;
    color: rgb(39 39 42);
}

.danger-action {
    border: 1px solid rgb(254 202 202);
    color: rgb(220 38 38);
}

.manual-button {
    display: flex;
    height: 2rem;
    width: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    border: 1px solid rgb(186 230 253);
    background: white;
    font-size: 0.875rem;
    font-weight: 800;
    color: rgb(2 132 199);
}

.manual-button:disabled {
    cursor: not-allowed;
    opacity: 0.4;
}

:global(.dark) .manual-button {
    border-color: rgb(12 74 110);
    background: rgb(8 47 73);
    color: rgb(125 211 252);
}

.primary-action:disabled,
.secondary-action:disabled,
.danger-action:disabled {
    cursor: not-allowed;
    opacity: 0.45;
}

:global(.dark) .secondary-action {
    border-color: rgb(63 63 70);
    background: rgb(24 24 27);
    color: rgb(244 244 245);
}

.step-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    border-radius: 1rem;
    padding: 0.625rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgb(82 82 91);
}

.step-button-active {
    background: rgb(224 242 254);
    color: rgb(2 132 199);
}

:global(.dark) .step-button-active {
    background: rgb(12 74 110 / 0.45);
    color: rgb(125 211 252);
}

.metric-card {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    border: 1px solid rgb(228 228 231);
    border-radius: 1rem;
    padding: 0.875rem;
    font-size: 0.75rem;
    color: rgb(82 82 91);
}

.metric-card strong {
    font-size: 1rem;
    color: inherit;
}

.scene-viewport {
    cursor: grab;
}

.scene-viewport:active {
    cursor: grabbing;
}

.scene-hint {
    position: absolute;
    left: 1rem;
    top: 1rem;
    z-index: 5;
    max-width: 22rem;
    border-radius: 1rem;
    background: rgb(255 255 255 / 0.82);
    padding: 0.625rem 0.875rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(3 105 161);
    box-shadow: 0 8px 24px rgb(15 23 42 / 0.08);
}

:global(.dark) .scene-hint {
    background: rgb(12 74 110 / 0.82);
    color: rgb(224 242 254);
}

.scene-shell {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    perspective: 1200px;
}

.scene {
    position: relative;
    transform: rotateX(58deg) rotateZ(-34deg);
    transform-style: preserve-3d;
}

.truck-shadow {
    position: absolute;
    left: -95px;
    top: 28%;
    width: 120px;
    height: 70px;
    border-radius: 1.5rem 0.5rem 0.5rem 1.5rem;
    background: rgb(15 23 42 / 0.16);
    filter: blur(1px);
}

.trailer {
    position: relative;
    transform-style: preserve-3d;
    border: 3px solid rgb(51 65 85 / 0.85);
    background: rgb(224 242 254 / 0.26);
    box-shadow: 0 18px 45px rgb(15 23 42 / 0.18);
}

.trailer::before,
.trailer::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.trailer::before {
    transform: translateZ(92px);
    border: 2px solid rgb(51 65 85 / 0.28);
    background: rgb(255 255 255 / 0.10);
}

.trailer::after {
    left: -3px;
    right: -3px;
    bottom: -18px;
    height: 14px;
    transform: rotateX(90deg);
    transform-origin: top;
    background: rgb(148 163 184 / 0.35);
}

.trailer-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgb(148 163 184 / 0.26) 1px, transparent 1px),
        linear-gradient(90deg, rgb(148 163 184 / 0.26) 1px, transparent 1px);
    background-size: 40px 40px;
}

.trailer-floor {
    position: absolute;
    inset: 0;
    border: 2px solid rgb(14 165 233 / 0.55);
    background:
        linear-gradient(135deg, rgb(14 165 233 / 0.10), rgb(255 255 255 / 0.22)),
        repeating-linear-gradient(0deg, rgb(14 165 233 / 0.12) 0 2px, transparent 2px 46px);
}

.floor-label {
    position: absolute;
    left: 0.75rem;
    top: 0.75rem;
    z-index: 4;
    transform: translateZ(4px);
    border-radius: 999px;
    background: rgb(14 165 233 / 0.92);
    padding: 0.25rem 0.625rem;
    color: white;
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.trailer-floor::after {
    content: 'ПОЛ ПРИЦЕПА';
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transform: translateZ(2px);
    color: rgb(2 132 199 / 0.18);
    font-size: clamp(1.5rem, 4vw, 4rem);
    font-weight: 900;
    letter-spacing: 0.18em;
    pointer-events: none;
}

.cargo-cube {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 8px;
    min-height: 8px;
    transform: translateZ(var(--cube-height)) rotateZ(var(--cube-rotation)) rotateX(var(--cube-tilt));
    transform-style: preserve-3d;
    border: 1px solid rgb(15 23 42 / 0.18);
    background: transparent;
    color: rgb(15 23 42);
    font-size: 0.65rem;
    font-weight: 800;
    box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.35);
}

.cargo-cube-manual {
    cursor: pointer;
    touch-action: none;
}

.cargo-cube-positioned {
    outline: 2px dashed rgb(2 132 199 / 0.5);
}

.cargo-cube-selected {
    outline: 3px solid rgb(14 165 233);
    filter: brightness(1.05) saturate(1.1);
    z-index: 20;
}

.cargo-face {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgb(15 23 42 / 0.26);
    background: color-mix(in srgb, var(--cube-color) 76%, white);
    box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.22);
}

.cargo-face-top {
    inset: 0;
    gap: 0.25rem;
    background:
        linear-gradient(135deg, rgb(255 255 255 / 0.22), transparent),
        color-mix(in srgb, var(--cube-color) 78%, white);
}

.cargo-face-bottom {
    inset: 0;
    transform: translateZ(calc(-1 * var(--cube-height)));
    background: color-mix(in srgb, var(--cube-color) 58%, black);
    opacity: 0.5;
}

.cargo-face-front,
.cargo-face-back {
    left: 0;
    right: 0;
    height: var(--cube-height);
    opacity: 0.78;
}

.cargo-face-front {
    bottom: calc(-1 * var(--cube-height));
    transform: rotateX(-90deg);
    transform-origin: top;
    background: color-mix(in srgb, var(--cube-color) 68%, black);
}

.cargo-face-back {
    top: calc(-1 * var(--cube-height));
    transform: rotateX(90deg);
    transform-origin: bottom;
    background: color-mix(in srgb, var(--cube-color) 86%, white);
}

.cargo-face-left,
.cargo-face-right {
    bottom: 0;
    top: 0;
    width: var(--cube-height);
    opacity: 0.7;
}

.cargo-face-left {
    left: calc(-1 * var(--cube-height));
    transform: rotateY(-90deg);
    transform-origin: right;
    background: color-mix(in srgb, var(--cube-color) 54%, black);
}

.cargo-face-right {
    right: calc(-1 * var(--cube-height));
    transform: rotateY(90deg);
    transform-origin: left;
    background: color-mix(in srgb, var(--cube-color) 72%, black);
}

.cargo-direction {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 1rem;
    width: 1rem;
    border-radius: 999px;
    background: rgb(255 255 255 / 0.7);
    color: rgb(15 23 42);
    font-size: 0.7rem;
}
</style>

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

        <div class="grid min-h-0 flex-1 gap-3 xl:grid-cols-[290px,minmax(420px,0.9fr),minmax(520px,1.1fr)]">
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

            <section class="panel min-h-0 overflow-hidden">
                <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <div>
                        <div class="text-sm font-semibold">Расчёт загрузки</div>
                        <div class="text-xs text-zinc-500">{{ selectedTransport ? selectedTransport.name : 'Выберите транспорт' }}</div>
                    </div>
                    <button type="button" class="secondary-action" @click="activeStep = 'calculation'">Сводка</button>
                </div>

                <div class="grid min-h-0 flex-1 grid-rows-[minmax(360px,1fr),auto] overflow-hidden">
                    <div class="relative overflow-hidden bg-gradient-to-br from-slate-50 to-sky-50 dark:from-zinc-950 dark:to-sky-950/30">
                        <div v-if="selectedTransport" class="scene-shell">
                            <div class="scene">
                                <div class="truck-shadow" />
                                <div class="trailer" :style="trailerStyle">
                                    <div class="trailer-grid" />
                                    <div
                                        v-for="block in layoutResult.blocks"
                                        :key="block.key"
                                        class="cargo-cube"
                                        :style="cubeStyle(block)"
                                        :title="`${block.name}: ${block.count} шт`"
                                    >
                                        <span>{{ block.count > 1 ? block.count : '' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex h-full items-center justify-center text-sm text-zinc-500">Выберите транспорт для расчёта.</div>
                    </div>

                    <div class="max-h-56 overflow-y-auto border-t border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="text-sm font-semibold">Груз в сцене</div>
                            <div class="text-xs text-zinc-500">{{ layoutResult.placedUnits }} / {{ layoutResult.totalUnits }} шт размещено</div>
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
import { computed, ref, watch } from 'vue';
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

watch(() => props.selectedProject, (project) => {
    projectForm.value = cloneProject(project);
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
            group_name: group.name,
            recipient_name: group.recipient_name,
            color: item.color || group.color || '#60a5fa',
        }));
    });
});

const layoutResult = computed(() => calculateLayout(selectedTransport.value, cargoFlat.value));

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

function cloneProject(project) {
    if (!project) {
        return null;
    }
    return {
        ...project,
        cargo_groups: (project.cargo_groups ?? []).map((group) => ({
            ...group,
            local_id: crypto.randomUUID(),
            items: (group.items ?? []).map((item) => ({ ...item, local_id: crypto.randomUUID() })),
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
        local_id: crypto.randomUUID(),
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
        },
        cargo_groups: projectForm.value.cargo_groups.map((group) => ({
            name: group.name,
            recipient_name: group.recipient_name,
            color: group.color,
            items: group.items.map((item) => ({
                name: item.name,
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
        local_id: crypto.randomUUID(),
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

function calculateLayout(transport, items) {
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
        while (remaining > 0) {
            const orientation = chooseOrientation(transport, item, cursorX, cursorY, rowDepth);
            if (!orientation) {
                overflow = true;
                warnings.push(`${item.name}: не удалось разместить ${remaining} шт.`);
                break;
            }

            if (orientation.newRow) {
                cursorX = 0;
                cursorY += rowDepth;
                rowDepth = 0;
            }

            const stackLimit = item.stackable
                ? Math.max(1, Math.min(Number(item.max_stack || 1), Math.floor(transport.height_mm / item.height_mm)))
                : 1;
            const count = Math.min(remaining, stackLimit);
            const block = {
                key: `${item.local_id}-${blocks.length}`,
                name: item.name,
                count,
                color: item.color,
                x: cursorX,
                y: cursorY,
                z: 0,
                length: orientation.length,
                width: orientation.width,
                height: item.height_mm * count,
            };
            if (blocks.length < maxBlocks) {
                blocks.push(block);
            }
            placedUnits += count;
            remaining -= count;
            usedLengthMm = Math.max(usedLengthMm, cursorX + orientation.length);
            cursorX += orientation.length;
            rowDepth = Math.max(rowDepth, orientation.width);
        }
    }

    if (totalWeightKg > transport.max_payload_kg) {
        warnings.push(`Перевес: ${formatKg(totalWeightKg)} при лимите ${formatKg(transport.max_payload_kg)}.`);
    }
    if (blocks.length >= maxBlocks && placedUnits < totalUnits) {
        warnings.push('В 3D-сцене показана часть мест, чтобы интерфейс не тормозил.');
    }

    const transportVolumeM3 = transport.length_mm * transport.width_mm * transport.height_mm / 1_000_000_000;
    const fits = !overflow && placedUnits === totalUnits && totalWeightKg <= transport.max_payload_kg;

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
    };
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
    background: rgb(255 255 255 / 0.48);
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

.cargo-cube {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 8px;
    min-height: 8px;
    transform: translateZ(var(--cube-height));
    transform-style: preserve-3d;
    border: 1px solid rgb(15 23 42 / 0.35);
    background: color-mix(in srgb, var(--cube-color) 78%, white);
    color: rgb(15 23 42);
    font-size: 0.65rem;
    font-weight: 800;
    box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.35);
}

.cargo-cube::before,
.cargo-cube::after {
    content: '';
    position: absolute;
    background: var(--cube-color);
    opacity: 0.58;
}

.cargo-cube::before {
    left: 0;
    right: 0;
    bottom: calc(-1 * var(--cube-height));
    height: var(--cube-height);
    transform: rotateX(90deg);
    transform-origin: top;
}

.cargo-cube::after {
    top: 0;
    right: calc(-1 * var(--cube-height));
    width: var(--cube-height);
    height: 100%;
    transform: rotateY(90deg);
    transform-origin: left;
}
</style>

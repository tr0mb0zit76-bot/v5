<template>
    <div class="howmuchfits-module flex min-h-0 flex-1 flex-col gap-3">
        <div class="flex shrink-0 flex-wrap items-start justify-between gap-3 print:hidden">
            <CrmPageHeader
                lead="3D-схема загрузки для аргументации ставки и выбора прицепа."
                title="Сколько влезет?"
            />
            <div class="flex flex-wrap gap-2 sm:ml-auto">
                <Link v-if="linkContext?.url" :href="linkContext.url" :class="crmBtnSecondary">← {{ linkContext.label }}</Link>
                <button type="button" :class="crmBtnPrimary" @click="createProject">{{ linkContext ? 'Новый расчёт по сделке' : 'Новый расчёт' }}</button>
                <button type="button" :class="crmBtnSecondary" :disabled="!projectForm?.id" @click="saveProject">Сохранить</button>
            </div>
        </div>

        <div v-if="viewerCanSeeAllProjects && !linkContext" class="rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-950 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-100 print:hidden">
            Показаны <strong>все проекты</strong> сотрудников с доступом к модулю.
        </div>

        <div v-if="linkContext" class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100 print:hidden">
            Расчёты привязаны к <strong>{{ linkContext.label }}</strong>. Их видят все, у кого есть доступ к этой сделке.
        </div>

        <div :class="`${crmPanel} workspace flex min-h-0 flex-1 flex-col overflow-hidden`">
            <div v-if="!projectForm" class="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-sm text-zinc-500">
                <p v-if="linkContext">Для {{ linkContext.label }} ещё нет расчёта загрузки.</p>
                <p v-else>Выберите или создайте проект на вкладке «Проекты».</p>
                <button v-if="linkContext" type="button" :class="crmBtnPrimary" @click="createProject">Создать расчёт</button>
            </div>

            <template v-else>
                <div class="min-h-0 flex-1 overflow-hidden">
                    <div v-if="activeStep === 'projects'" class="flex h-full flex-col overflow-y-auto p-4 md:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">
                                Список проектов {{ projects.length }} / 300
                            </div>
                            <HelpCircle class="h-5 w-5 text-zinc-400" />
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <div class="relative w-full min-w-[10rem] sm:w-auto">
                                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                                <input v-model="projectSearch" type="search" :class="crmGridSearchField" placeholder="Поиск" />
                            </div>
                            <button type="button" class="sort-pill" :class="projectSortBy === 'name' ? 'sort-pill-active' : ''" @click="setProjectSort('name')">по названию</button>
                            <button type="button" class="sort-pill" :class="projectSortBy === 'created' ? 'sort-pill-active' : ''" @click="setProjectSort('created')">по дате добавления</button>
                            <button type="button" class="sort-pill" :class="projectSortBy === 'updated' ? 'sort-pill-active' : ''" @click="setProjectSort('updated')">по дате изменения</button>
                            <select v-model="projectSortDir" class="field w-auto min-w-[9rem] shrink-0">
                                <option value="desc">по убыванию</option>
                                <option value="asc">по возрастанию</option>
                            </select>
                        </div>
                        <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
                            <div
                                v-for="project in sortedProjects"
                                :key="project.id"
                                class="flex items-start gap-3 py-4"
                                :class="projectForm.id === project.id ? 'rounded-2xl bg-sky-50 px-2 dark:bg-sky-950/40' : ''"
                            >
                                <FolderOpen class="mt-1 h-5 w-5 shrink-0 text-sky-600" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold">{{ project.name }}</div>
                                    <div class="mt-1 text-xs text-zinc-500">добавлено {{ project.created_at }}, обновлено {{ project.updated_at }}</div>
                                    <div v-if="project.owner_name" class="mt-1 text-xs text-zinc-500">автор: {{ project.owner_name }}</div>
                                    <div v-if="project.link_label" class="mt-1 truncate text-xs text-sky-700 dark:text-sky-300">{{ project.link_label }}</div>
                                    <div v-else-if="project.transport_name" class="mt-1 truncate text-xs text-sky-700 dark:text-sky-300">{{ project.transport_name }}</div>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <button type="button" class="text-link" @click="openProject(project.id)">открыть</button>
                                    <button type="button" class="icon-button" title="Выбрать" @click="selectProject(project.id)"><Copy class="h-4 w-4" /></button>
                                    <button type="button" class="icon-button text-rose-600" title="Удалить" :disabled="projects.length <= 1" @click="deleteProjectById(project.id)"><Trash2 class="h-4 w-4" /></button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-center">
                            <button type="button" :class="`${crmBtnPrimary} inline-flex items-center gap-2 px-6`" @click="createProject"><Plus class="h-4 w-4" /> Добавить новый проект</button>
                        </div>
                        <div class="mt-8 space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-800">
                            <div>
                                <label class="label">Название проекта</label>
                                <input v-model="projectForm.name" class="field" />
                            </div>
                            <div>
                                <label class="label">Комментарий менеджера</label>
                                <textarea v-model="projectForm.notes" rows="4" class="field" placeholder="Заметки по проекту..." />
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeStep === 'cargo'" class="flex h-full flex-col overflow-hidden">
                        <div class="border-b border-zinc-200 px-4 py-4 dark:border-zinc-800 md:px-6">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">
                                    Список грузовых мест {{ cargoListTotals.units }} / 7000
                                </div>
                                <div class="text-xs text-zinc-500">
                                    {{ cargoListTotals.units }} шт, {{ formatKg(cargoListTotals.weight) }}, {{ formatM3(cargoListTotals.volume) }}
                                </div>
                            </div>
                            <div class="relative mt-3">
                                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                                <input v-model="cargoSearch" type="search" :class="crmGridSearchField" placeholder="Поиск" />
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    v-for="(group, groupIndex) in projectForm.cargo_groups"
                                    :key="group.local_id"
                                    type="button"
                                    class="group-tab"
                                    :class="activeCargoGroupIndex === groupIndex ? 'group-tab-active' : ''"
                                    @click="activeCargoGroupIndex = groupIndex"
                                >
                                    {{ group.name }}
                                </button>
                                <button type="button" :class="crmBtnSecondary" @click="addCargoGroup">+ группа</button>
                            </div>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3 md:px-6">
                            <div v-if="activeCargoGroup" class="mb-3 grid gap-2 md:grid-cols-[1fr,1fr,4rem]">
                                <input v-model="activeCargoGroup.name" class="field" placeholder="Группа" />
                                <input v-model="activeCargoGroup.recipient_name" class="field" placeholder="Получатель" />
                                <input v-model="activeCargoGroup.color" type="color" class="h-10 w-full rounded-xl border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900" />
                            </div>
                            <div class="cargo-table-head hidden text-xs font-semibold uppercase tracking-wide text-zinc-500 md:grid">
                                <span>Груз</span>
                                <span>Параметры</span>
                                <span class="text-right">Действия</span>
                            </div>
                            <button
                                v-for="{ item, itemIndex, groupIndex } in cargoListItems"
                                :key="item.local_id"
                                type="button"
                                class="cargo-row"
                                @click="openCargoItemEditor(groupIndex, itemIndex)"
                            >
                                <span class="cargo-swatch" :style="{ backgroundColor: item.color || activeCargoGroup?.color }" />
                                <span class="min-w-0 flex-1 text-left">
                                    <span class="block truncate text-sm font-semibold">{{ item.name }}</span>
                                    <span class="mt-1 block text-xs text-zinc-500">
                                        {{ packageTypeLabel(item.package_type) }},
                                        {{ item.length_mm }} × {{ item.width_mm }} × {{ item.height_mm }} мм,
                                        {{ formatKg(item.weight_kg) }}, {{ item.quantity }} шт
                                    </span>
                                    <span class="mt-1 flex flex-wrap gap-2 text-[11px] text-zinc-400">
                                        <span>ярусы: {{ cargoConstraintLabel(item, 'stackable') }}</span>
                                        <span>поворот: {{ cargoConstraintLabel(item, 'can_rotate') }}</span>
                                        <span>кантование: {{ cargoConstraintLabel(item, 'can_tilt') }}</span>
                                    </span>
                                </span>
                                <span class="flex shrink-0 gap-1" @click.stop>
                                    <button type="button" class="icon-button" @click="openCargoItemEditor(groupIndex, itemIndex)"><Pencil class="h-4 w-4" /></button>
                                    <button type="button" class="icon-button text-rose-600" @click="removeCargoItem(groupIndex, itemIndex)"><Trash2 class="h-4 w-4" /></button>
                                </span>
                            </button>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" :class="`${crmBtnPrimary} inline-flex items-center gap-2`" @click="addCargoItem(activeCargoGroupIndex)"><Plus class="h-4 w-4" /> Добавить груз</button>
                                <button v-if="projectForm.cargo_groups.length > 1" type="button" :class="crmBtnDangerMuted" @click="removeCargoGroup(activeCargoGroupIndex)">Удалить группу</button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeStep === 'transport'" class="h-full overflow-y-auto p-4 md:p-6">
                        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
                            Сравните размещение груза в разных видах транспорта. Добавьте транспорт из шаблона или вручную, чтобы выбрать оптимальный вариант.
                        </div>
                        <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800">
                            <div class="bg-sky-600 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white">
                                Список транспорта {{ selectedTransport ? 1 : 0 }} / 10
                            </div>
                            <div v-if="selectedTransport" class="flex items-start gap-3 bg-white px-4 py-4 dark:bg-zinc-900">
                                <Truck class="mt-1 h-5 w-5 shrink-0 text-sky-600" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold">{{ selectedTransport.name }}</div>
                                    <div class="mt-1 text-xs text-zinc-500">{{ transportLabel(selectedTransport) }}</div>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <button type="button" class="text-link" @click="openManualTransportModal">редактировать</button>
                                    <button type="button" class="icon-button" @click="editTransportTemplate(selectedTransport)"><Pencil class="h-4 w-4" /></button>
                                </div>
                            </div>
                            <div v-else class="px-4 py-6 text-sm text-zinc-500">Транспорт не выбран.</div>
                        </div>
                        <div class="mt-4 flex justify-center">
                            <button type="button" :class="`${crmBtnPrimary} inline-flex items-center gap-2 px-6`" @click="openManualTransportModal"><Plus class="h-4 w-4" /> Добавить транспорт вручную</button>
                        </div>
                        <div class="mt-8">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Выбрать шаблон транспорта</div>
                            <div class="relative mt-3">
                                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                                <input v-model="transportSearch" type="search" :class="crmGridSearchField" placeholder="Поиск" />
                            </div>
                            <div class="mt-3 flex flex-wrap gap-4 border-b border-zinc-200 text-sm font-semibold dark:border-zinc-800">
                                <button type="button" class="category-tab" :class="transportCategoryFilter === 'all' ? 'category-tab-active' : ''" @click="transportCategoryFilter = 'all'">Все</button>
                                <button type="button" class="category-tab" :class="transportCategoryFilter === 'truck' ? 'category-tab-active' : ''" @click="transportCategoryFilter = 'truck'">Автотранспорт</button>
                                <button type="button" class="category-tab" :class="transportCategoryFilter === 'container' ? 'category-tab-active' : ''" @click="transportCategoryFilter = 'container'">Контейнер</button>
                                <button type="button" class="category-tab" :class="transportCategoryFilter === 'pallet' ? 'category-tab-active' : ''" @click="transportCategoryFilter = 'pallet'">Паллет</button>
                            </div>
                            <div class="mt-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                                <div v-for="template in filteredTransportTemplates" :key="template.id" class="flex items-center gap-3 py-3">
                                    <Truck class="h-5 w-5 shrink-0 text-zinc-400" />
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold">{{ template.name }}</div>
                                        <div class="text-xs text-zinc-500">{{ transportLabel(template) }}</div>
                                    </div>
                                    <button type="button" class="text-link" @click="addTransportFromTemplate(template)">добавить</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="print-area grid h-full min-h-0 xl:grid-cols-[minmax(0,1fr),300px]">
                        <div class="print-area-scene flex min-h-0 flex-col overflow-hidden border-b border-zinc-200 xl:border-b-0 xl:border-r dark:border-zinc-800">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-800 print:hidden">
                                <div class="flex min-w-0 flex-col gap-2">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Расчёт загрузки</div>
                                    <div v-if="multiVehicleSummary && multiVehicleSummary.truckCount > 1" class="flex flex-wrap gap-1">
                                        <button
                                            v-for="(truck, truckIndex) in multiVehicleSummary.trucks"
                                            :key="`truck-${truck.truckIndex}`"
                                            type="button"
                                            class="group-tab"
                                            :class="activeTruckIndex === truckIndex ? 'group-tab-active' : ''"
                                            @click="activeTruckIndex = truckIndex"
                                        >
                                            {{ truck.truckLabel }} · {{ truck.placedInTrailer }} шт
                                        </button>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-1">
                                    <label class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 px-2 py-1 text-[11px] font-semibold dark:border-zinc-700">
                                        <input v-model="manualMode" type="checkbox" class="rounded" /> Ручная раскладка
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 px-2 py-1 text-[11px] font-semibold dark:border-zinc-700">
                                        <input v-model="tightPacking" type="checkbox" class="rounded" /> Без зазоров
                                    </label>
                                    <button type="button" class="scene-tool" @click="rotateScene(-12, 0)">←</button>
                                    <button type="button" class="scene-tool" @click="rotateScene(12, 0)">→</button>
                                    <button type="button" class="scene-tool" @click="rotateScene(0, 8)">↑</button>
                                    <button type="button" class="scene-tool" @click="rotateScene(0, -8)">↓</button>
                                    <button type="button" class="scene-tool" @click="resetSceneView">Сброс вида</button>
                                    <button type="button" class="scene-tool" @click="resetManualPlacements">Сброс позиций</button>
                                    <button type="button" class="scene-tool" :disabled="!selectedBlock" @click="lockSelectedBlock">Зафиксировать</button>
                                </div>
                            </div>
                            <div
                                ref="sceneViewport"
                                tabindex="0"
                                class="scene-viewport relative min-h-[420px] flex-1 overflow-hidden outline-none"
                                @pointerdown="startSceneRotate"
                                @contextmenu.prevent
                                @wheel.prevent="onSceneWheel"
                            >
                                <div class="scene-hint print:hidden">
                                    Колесо — зум. ЛКМ по фону — вращение. ПКМ — сдвиг. При перетаскивании совпадение с той же серией подсвечивает грани. Отпускание — фиксация.
                                </div>
                                <div v-if="selectedTransport" class="scene-shell" :style="sceneShellStyle">
                                    <div class="scene" :style="sceneTransformStyle">
                                        <div class="truck-shadow" :style="truckShadowStyle" />
                                        <div ref="deckEl" class="scene-deck" :style="deckStyle">
                                            <div class="staging-pad" />
                                            <div class="trailer-zone" :style="trailerZoneStyle">
                                                <div class="trailer-cage" :style="trailerCageStyle">
                                                    <span class="trailer-cage-face trailer-cage-face-bottom" />
                                                    <span class="trailer-cage-face trailer-cage-face-top" />
                                                    <span class="trailer-cage-face trailer-cage-face-front" />
                                                    <span class="trailer-cage-face trailer-cage-face-back" />
                                                    <span class="trailer-cage-face trailer-cage-face-left" />
                                                    <span class="trailer-cage-face trailer-cage-face-right" />
                                                </div>
                                                <div class="trailer-floor" />
                                                <div class="trailer-grid" :style="trailerGridStyle" />
                                                <div class="trailer-rulers" aria-hidden="true">
                                                    <div class="trailer-ruler trailer-ruler-length">
                                                        <span
                                                            v-for="tick in lengthRulerTicks"
                                                            :key="`len-${tick.mm}`"
                                                            class="trailer-ruler-tick"
                                                            :style="{ left: `${(tick.mm / selectedTransport.length_mm) * 100}%` }"
                                                        >
                                                            <span class="trailer-ruler-mark" />
                                                            <span class="trailer-ruler-label">{{ tick.label }}</span>
                                                        </span>
                                                    </div>
                                                    <div class="trailer-ruler trailer-ruler-width">
                                                        <span
                                                            v-for="tick in widthRulerTicks"
                                                            :key="`wid-${tick.mm}`"
                                                            class="trailer-ruler-tick"
                                                            :style="{ top: `${(tick.mm / selectedTransport.width_mm) * 100}%` }"
                                                        >
                                                            <span class="trailer-ruler-mark" />
                                                            <span class="trailer-ruler-label">{{ tick.label }}</span>
                                                        </span>
                                                    </div>
                                                    <div class="trailer-ruler trailer-ruler-height">
                                                        <span
                                                            v-for="tick in heightRulerTicks"
                                                            :key="`h-${tick.mm}`"
                                                            class="trailer-ruler-tick"
                                                            :style="{ bottom: `${(tick.mm / selectedTransport.height_mm) * 100}%` }"
                                                        >
                                                            <span class="trailer-ruler-mark" />
                                                            <span class="trailer-ruler-label">{{ tick.label }}</span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                v-for="block in sceneBlocks"
                                                :key="block.key"
                                                class="cargo-cube"
                                                :class="[
                                                    Number(block.z) > 0 ? 'cargo-cube-elevated' : '',
                                                    blockUnderStack(block) ? 'cargo-cube-under-stack' : '',
                                                    manualMode && canLiftBlock(block) ? 'cargo-cube-manual' : '',
                                                    manualMode && !canLiftBlock(block) ? 'cargo-cube-covered' : '',
                                                    selectedBlockKey === block.key ? 'cargo-cube-selected' : '',
                                                    block.locked ? 'cargo-cube-locked' : '',
                                                    block.in_trailer ? '' : 'cargo-cube-staging',
                                                    blockDrag?.key === block.key ? 'cargo-cube-dragging' : '',
                                                    blockDrag?.key === block.key && blockDrag?.overlapping ? 'cargo-cube-overlap' : '',
                                                    ...cubeAlignGuideClasses(block),
                                                ]"
                                                :style="cubePositionStyle(block)"
                                                :title="`${block.name}${block.stack_count > 1 ? `, ярус ${block.stack_count}` : ''}${block.in_trailer ? '' : ' (зона сборки)'}`"
                                                @pointerdown.stop.prevent="startBlockDrag($event, block)"
                                                @click.stop="selectBlock(block)"
                                            >
                                                <div class="cargo-cube-lift" :style="cubeLiftStyle(block)">
                                                    <div
                                                        class="cargo-cube-body"
                                                        :class="{ 'cargo-cube-body-selected': selectedBlockKey === block.key }"
                                                        :style="cubeBodyStyle(block)"
                                                    >
                                                        <span class="cargo-face cargo-face-bottom" />
                                                        <span class="cargo-face cargo-face-top">
                                                            <span class="cargo-direction" :style="cubeDirectionStyle(block)">→</span>
                                                            <span v-if="block.stack_count > 1">{{ block.stack_count }}</span>
                                                        </span>
                                                        <span class="cargo-face cargo-face-front" />
                                                        <span class="cargo-face cargo-face-back" />
                                                        <span class="cargo-face cargo-face-left" />
                                                        <span class="cargo-face cargo-face-right" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex h-full items-center justify-center text-sm text-zinc-500">Выберите транспорт.</div>
                            </div>
                            <div v-if="manualMode && selectedBlock" class="border-t border-zinc-200 px-3 py-2 text-xs text-zinc-600 dark:border-zinc-800 dark:text-zinc-300 print:hidden">
                                <span class="font-semibold">{{ selectedBlock.name }}</span>
                                <span v-if="selectedBlock.locked" class="ml-2 text-emerald-600 dark:text-emerald-400">зафиксирован</span>
                                <span v-else-if="!canLiftBlock(selectedBlock)" class="ml-2 text-amber-700 dark:text-amber-300">— сверху есть груз, снять нельзя</span>
                                <span v-else class="ml-2">— отпустите для фиксации, Enter — вручную</span>
                            </div>
                        </div>

                        <aside class="print-area-summary calc-sidebar flex min-h-0 flex-col overflow-y-auto bg-zinc-50/80 p-4 dark:bg-zinc-950/50">
                            <div v-if="selectedTransport" class="text-xs font-semibold uppercase leading-5 tracking-wide text-zinc-700 dark:text-zinc-200">
                                {{ selectedTransport.name }}
                            </div>
                            <div v-if="selectedTransport" class="mt-1 text-[11px] leading-5 text-zinc-500">{{ transportLabel(selectedTransport) }}</div>
                            <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                                <div class="summary-chip"><span>Итого</span><strong>{{ layoutResult.totalUnits }} шт</strong><strong>{{ formatKg(layoutResult.totalWeightKg) }}</strong></div>
                                <div class="summary-chip"><span>Занято</span><strong>{{ layoutResult.ldm.toFixed(2) }} LDM</strong><strong>{{ layoutResult.usedVolumePercent.toFixed(1) }}%</strong></div>
                                <div class="summary-chip"><span>Свободно</span><strong>{{ formatMm(layoutResult.freeLengthMm) }}</strong><strong>{{ formatM3(layoutResult.freeVolumeM3) }}</strong></div>
                                <div class="summary-chip" :class="layoutResult.fits ? 'summary-chip-ok' : 'summary-chip-bad'">
                                    <span>Статус</span><strong>{{ layoutStatusLabel }}</strong>
                                </div>
                            </div>
                            <div v-if="multiVehicleSummary && multiVehicleSummary.truckCount > 1" class="mt-3 rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs text-sky-950 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
                                <div class="font-semibold">Несколько машин</div>
                                <div class="mt-1">Всего {{ multiVehicleSummary.truckCount }} маш · размещено {{ multiVehicleSummary.placedUnits }} / {{ multiVehicleSummary.totalUnits }} мест</div>
                                <div v-if="multiVehicleSummary.unplacedUnits > 0" class="mt-1 text-amber-800 dark:text-amber-200">
                                    Не размещено: {{ multiVehicleSummary.unplacedUnits }} мест
                                </div>
                            </div>
                            <div v-if="layoutResult.warnings.length" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                                <div class="font-semibold">Предупреждения</div>
                                <ul class="mt-1 space-y-1">
                                    <li v-for="warning in layoutResult.warnings" :key="warning">{{ warning }}</li>
                                </ul>
                            </div>
                            <button type="button" class="mt-4 flex w-full items-center justify-between text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 print:hidden" @click="showPlacementDetails = !showPlacementDetails">
                                <span>Расчёты размещения</span><span>{{ showPlacementDetails ? '−' : '+' }}</span>
                            </button>
                            <div v-if="showPlacementDetails" class="mt-2 space-y-1 text-xs text-zinc-600 dark:text-zinc-300 print:hidden">
                                <div>Размещено: {{ layoutResult.placedUnits }} / {{ layoutResult.totalUnits }} шт (в кузове {{ layoutResult.placedInTrailer }})</div>
                                <div>Объём груза: {{ formatM3(layoutResult.totalVolumeM3) }}</div>
                                <div>Грузоподъёмность: {{ layoutResult.usedPayloadPercent.toFixed(1) }}%</div>
                            </div>
                            <button type="button" class="mt-3 flex w-full items-center justify-between text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 print:hidden" @click="showAxleLoad = !showAxleLoad">
                                <span>Нагрузка на оси</span><span>{{ showAxleLoad ? '−' : '+' }}</span>
                            </button>
                            <div v-if="showAxleLoad" class="mt-2 text-xs text-zinc-500 print:hidden">Автоматический расчёт осевой нагрузки появится в следующей версии.</div>
                            <div class="mt-4 min-h-0 flex-1 space-y-3 overflow-y-auto print:hidden">
                                <div v-for="group in projectForm.cargo_groups" :key="group.local_id">
                                    <div class="truncate text-[11px] font-semibold uppercase text-zinc-500">{{ group.recipient_name || group.name }}</div>
                                    <div v-for="(item, index) in group.items" :key="item.local_id" class="mt-2 flex items-start gap-2 text-xs">
                                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: item.color || group.color }" />
                                        <span class="min-w-0 flex-1">
                                            <span class="font-semibold">{{ index + 1 }}. {{ item.name }}</span>
                                            <span class="mt-0.5 block text-zinc-500">{{ item.length_mm }} × {{ item.width_mm }} × {{ item.height_mm }} мм, {{ formatKg(item.weight_kg) }}, {{ item.quantity }} шт</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white print:hidden" @click="exportCalculationPdf">
                                <FileDown class="h-4 w-4" /> Сохранить в PDF
                            </button>
                        </aside>
                    </div>
                </div>

                <nav class="bottom-steps grid shrink-0 grid-cols-4 border-t border-zinc-200 dark:border-zinc-800 print:hidden">
                    <button v-for="step in steps" :key="step.key" type="button" class="bottom-step" :class="activeStep === step.key ? 'bottom-step-active' : ''" @click="activeStep = step.key">
                        <component :is="step.icon" class="h-5 w-5" />
                        <span>{{ stepBottomLabel(step.key) }}</span>
                    </button>
                </nav>
            </template>
        </div>

        <Modal :show="cargoItemModalOpen" max-width="3xl" @close="closeCargoItemModal">
            <div v-if="cargoItemDraft" class="p-5 md:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Параметры</div>
                    <HelpCircle class="h-5 w-5 text-zinc-400" />
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-[1fr,7rem]">
                    <div>
                        <label class="label">Название или маркировка места</label>
                        <input v-model="cargoItemDraft.name" class="field" maxlength="110" />
                    </div>
                    <div>
                        <label class="label">Количество</label>
                        <div class="flex items-center gap-2">
                            <button type="button" class="qty-button" @click="adjustCargoQuantity(-1)">−</button>
                            <input v-model.number="cargoItemDraft.quantity" type="number" min="1" class="field text-center" />
                            <button type="button" class="qty-button" @click="adjustCargoQuantity(1)">+</button>
                        </div>
                    </div>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-4">
                    <div><label class="label">Длина, мм</label><input v-model.number="cargoItemDraft.length_mm" type="number" min="1" class="field" /></div>
                    <div><label class="label">Ширина, мм</label><input v-model.number="cargoItemDraft.width_mm" type="number" min="1" class="field" /></div>
                    <div><label class="label">Высота, мм</label><input v-model.number="cargoItemDraft.height_mm" type="number" min="1" class="field" /></div>
                    <div><label class="label">Масса брутто, кг</label><input v-model.number="cargoItemDraft.weight_kg" type="number" min="0" step="0.01" class="field" /></div>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="label">Тип упаковки</label>
                        <select v-model="cargoItemDraft.package_type" class="field">
                            <option value="pallet">Паллета</option>
                            <option value="box">Коробка</option>
                            <option value="crate">Ящик</option>
                            <option value="roll">Рулон</option>
                            <option value="bag">Мешок</option>
                            <option value="custom">Другое</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Цвет в сцене</label>
                        <input v-model="cargoItemDraft.color" type="color" class="h-10 w-full rounded-xl border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900" />
                    </div>
                    <div>
                        <label class="label inline-flex items-center gap-2"><Layers class="h-4 w-4" /> Ярусы</label>
                        <select v-model="cargoItemDraft.stackable" class="field">
                            <option :value="true">Да</option>
                            <option :value="false">Нет</option>
                        </select>
                    </div>
                    <div>
                        <label class="label inline-flex items-center gap-2"><RotateCw class="h-4 w-4" /> Поворот</label>
                        <select v-model="cargoItemDraft.can_rotate" class="field">
                            <option :value="true">Да</option>
                            <option :value="false">Нет</option>
                        </select>
                    </div>
                    <div>
                        <label class="label inline-flex items-center gap-2"><ArrowLeftRight class="h-4 w-4" /> Кантование</label>
                        <select v-model="cargoItemDraft.can_tilt" class="field">
                            <option :value="true">Да</option>
                            <option :value="false">Нет</option>
                        </select>
                    </div>
                    <div v-if="cargoItemDraft.stackable">
                        <label class="label">Макс. ярусов</label>
                        <input v-model.number="cargoItemDraft.max_stack" type="number" min="1" max="20" class="field" :placeholder="String(DEFAULT_MAX_STACK)" />
                    </div>
                </div>
                <div class="mt-6 flex justify-between gap-3">
                    <button type="button" :class="crmBtnSecondary" @click="closeCargoItemModal">Отменить</button>
                    <button type="button" :class="crmBtnPrimary" @click="saveCargoItemFromModal">Сохранить</button>
                </div>
            </div>
        </Modal>

        <Modal :show="transportModalOpen" max-width="3xl" @close="closeTransportModal">
            <div class="p-5 md:p-6">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Параметры транспорта</div>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <input v-model="templateDraft.name" class="field md:col-span-2" placeholder="Название или маркировка" />
                    <select v-model="templateDraft.category" class="field">
                        <option value="truck">Автотранспорт</option>
                        <option value="container">Контейнер</option>
                        <option value="pallet">Паллет</option>
                        <option value="platform">Платформа</option>
                        <option value="custom">Другое</option>
                    </select>
                    <input v-model.number="templateDraft.max_payload_kg" type="number" min="0" class="field" placeholder="Тоннаж, кг" />
                    <input v-model.number="templateDraft.length_mm" type="number" min="1" class="field" placeholder="Длина, мм" />
                    <input v-model.number="templateDraft.width_mm" type="number" min="1" class="field" placeholder="Ширина, мм" />
                    <input v-model.number="templateDraft.height_mm" type="number" min="1" class="field" placeholder="Высота, мм" />
                    <input v-model.number="templateDraft.axles_count" type="number" min="1" class="field" placeholder="Оси" />
                    <label class="inline-flex items-center gap-2 text-sm"><input v-model="templateDraft.is_active" type="checkbox" class="rounded" /> Активен в справочнике</label>
                </div>
                <div class="mt-6 flex justify-between gap-3">
                    <button type="button" :class="crmBtnSecondary" @click="closeTransportModal">Отменить</button>
                    <button type="button" :class="crmBtnPrimary" @click="saveManualTransport">Сохранить</button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Boxes,
    Calculator,
    Copy,
    FileDown,
    FolderOpen,
    HelpCircle,
    Layers,
    Package,
    Pencil,
    Plus,
    RotateCw,
    Search,
    Trash2,
    Truck,
} from 'lucide-vue-next';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import Modal from '@/Components/Modal.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnDangerMuted, crmBtnPrimary, crmBtnSecondary, crmFieldFluid, crmGridSearchField, crmPanel } from '@/support/crmUi.js';
import {
    blockInTrailer,
    blocksOverlap3D,
    blocksOverlapXY,
    buildSceneBounds,
    calculateLayout,
    calculateMultiVehicleLayout,
    clientPointToSceneMm,
    blockCanBeLifted,
    buildHeightRulerTicks,
    buildLengthRulerTicks,
    buildWidthRulerTicks,
    footprintPositionAfterRotation,
    computeSeriesAlignHints,
    findSupportedZForBlock,
    findTopSupportedZForBlock,
    placementRotationY,
    placementRotationZ,
    snapshotPlacementsFromBlocks,
    sortBlocksForScenePaint,
} from '@/support/loadingPlannerLayout.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'modules', activeSubKey: 'modules-how-much-fits' }, () => page),
});

const DEFAULT_MAX_STACK = 5;

const props = defineProps({
    projects: { type: Array, default: () => [] },
    selectedProject: { type: Object, default: null },
    transportTemplates: { type: Array, default: () => [] },
    linkContext: { type: Object, default: null },
    initialStep: { type: String, default: null },
    viewerCanSeeAllProjects: { type: Boolean, default: false },
});

const steps = [
    { key: 'projects', label: 'Проект', icon: FolderOpen },
    { key: 'cargo', label: 'Груз', icon: Package },
    { key: 'transport', label: 'Транспорт', icon: Truck },
    { key: 'calculation', label: 'Расчёт', icon: Calculator },
];

const activeStep = ref(props.initialStep && steps.some((step) => step.key === props.initialStep) ? props.initialStep : 'projects');
const activeTruckIndex = ref(0);
const projectSearch = ref('');
const projectSortBy = ref('updated');
const projectSortDir = ref('desc');
const projectForm = ref(cloneProject(props.selectedProject));
const templateDraft = ref(blankTemplate());
const activeCargoGroupIndex = ref(0);
const cargoSearch = ref('');
const cargoItemModalOpen = ref(false);
const cargoItemDraft = ref(null);
const cargoItemEdit = ref({ groupIndex: 0, itemIndex: 0 });
const transportModalOpen = ref(false);
const transportSearch = ref('');
const transportCategoryFilter = ref('all');
const showPlacementDetails = ref(false);
const showAxleLoad = ref(false);
const manualMode = ref(Boolean(props.selectedProject?.calculation?.manual_mode));
const tightPacking = ref(Boolean(props.selectedProject?.calculation?.tight_packing));
const selectedBlockKey = ref(props.selectedProject?.calculation?.selected_manual_key ?? null);
const sceneViewport = ref(null);
const deckEl = ref(null);
const sceneRotationX = ref(Number(props.selectedProject?.calculation?.scene_view?.rotation_x ?? 58));
const sceneRotationZ = ref(Number(props.selectedProject?.calculation?.scene_view?.rotation_z ?? -34));
const sceneZoom = ref(Number(props.selectedProject?.calculation?.scene_view?.zoom ?? 1));
const scenePanX = ref(Number(props.selectedProject?.calculation?.scene_view?.pan_x ?? 0));
const scenePanY = ref(Number(props.selectedProject?.calculation?.scene_view?.pan_y ?? 0));
const basePlacementsCache = ref(props.selectedProject?.calculation?.base_placements ?? {});
const sceneDrag = ref(null);
const blockDrag = ref(null);

watch(() => props.selectedProject, (project) => {
    projectForm.value = cloneProject(project);
    manualMode.value = Boolean(project?.calculation?.manual_mode);
    tightPacking.value = Boolean(project?.calculation?.tight_packing);
    selectedBlockKey.value = project?.calculation?.selected_manual_key ?? null;
    sceneRotationX.value = Number(project?.calculation?.scene_view?.rotation_x ?? 58);
    sceneRotationZ.value = Number(project?.calculation?.scene_view?.rotation_z ?? -34);
    sceneZoom.value = Number(project?.calculation?.scene_view?.zoom ?? 1);
    scenePanX.value = Number(project?.calculation?.scene_view?.pan_x ?? 0);
    scenePanY.value = Number(project?.calculation?.scene_view?.pan_y ?? 0);
    basePlacementsCache.value = project?.calculation?.base_placements ?? {};
    activeCargoGroupIndex.value = 0;

    if (!project) {
        activeStep.value = 'projects';
    }
}, { deep: true });

const filteredProjects = computed(() => {
    const query = projectSearch.value.trim().toLowerCase();
    if (!query) {
        return props.projects;
    }
    return props.projects.filter((project) => [project.name, project.transport_name, project.owner_name, project.link_label].filter(Boolean).join(' ').toLowerCase().includes(query));
});

const sortedProjects = computed(() => {
    const list = [...filteredProjects.value];
    const dir = projectSortDir.value === 'asc' ? 1 : -1;
    list.sort((left, right) => {
        if (projectSortBy.value === 'name') {
            return left.name.localeCompare(right.name, 'ru') * dir;
        }
        const leftDate = projectSortBy.value === 'created' ? left.created_at : left.updated_at;
        const rightDate = projectSortBy.value === 'created' ? right.created_at : right.updated_at;
        return String(leftDate).localeCompare(String(rightDate), 'ru') * dir;
    });
    return list;
});

const activeCargoGroup = computed(() => projectForm.value?.cargo_groups?.[activeCargoGroupIndex.value] ?? null);

const cargoListItems = computed(() => {
    const group = activeCargoGroup.value;
    if (!group) {
        return [];
    }
    const query = cargoSearch.value.trim().toLowerCase();
    return (group.items ?? [])
        .map((item, itemIndex) => ({ item, itemIndex, groupIndex: activeCargoGroupIndex.value }))
        .filter(({ item }) => {
            if (!query) {
                return true;
            }
            const haystack = [item.name, packageTypeLabel(item.package_type)].join(' ').toLowerCase();
            return haystack.includes(query);
        });
});

const cargoListTotals = computed(() => {
    const items = (projectForm.value?.cargo_groups ?? []).flatMap((group) => group.items ?? []);
    const units = items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
    const weight = items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.weight_kg || 0), 0);
    const volume = items.reduce((sum, item) => sum + Number(item.quantity || 0) * item.length_mm * item.width_mm * item.height_mm / 1_000_000_000, 0);
    return { units, weight, volume };
});

const filteredTransportTemplates = computed(() => {
    const query = transportSearch.value.trim().toLowerCase();
    return props.transportTemplates.filter((template) => {
        if (transportCategoryFilter.value !== 'all' && template.category !== transportCategoryFilter.value) {
            return false;
        }
        if (!query) {
            return true;
        }
        return [template.name, categoryLabel(template.category), transportLabel(template)].join(' ').toLowerCase().includes(query);
    });
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
const layoutOptions = computed(() => ({
    placementGapMm: tightPacking.value ? 0 : null,
}));

const autoLayoutResult = computed(() => calculateLayout(
    selectedTransport.value,
    cargoFlat.value,
    {},
    layoutOptions.value,
));

const multiLayoutResult = computed(() => {
    if (!selectedTransport.value || manualMode.value) {
        return null;
    }

    return calculateMultiVehicleLayout(selectedTransport.value, cargoFlat.value, layoutOptions.value);
});

const layoutResult = computed(() => {
    if (!selectedTransport.value) {
        return autoLayoutResult.value;
    }

    if (!manualMode.value) {
        const multi = multiLayoutResult.value;
        if (multi) {
            if (multi.truckCount > 0) {
                const truck = multi.trucks[activeTruckIndex.value] ?? multi.trucks[0];

                return {
                    ...truck,
                    fits: multi.fits,
                    multiSummary: multi,
                };
            }

            return {
                ...autoLayoutResult.value,
                fits: false,
                warnings: multi.warnings,
                multiSummary: multi,
            };
        }

        return autoLayoutResult.value;
    }

    const base = Object.keys(basePlacementsCache.value).length > 0
        ? basePlacementsCache.value
        : snapshotPlacementsFromBlocks(autoLayoutResult.value.blocks);

    const excludeSettleKeys = blockDrag.value?.key ? new Set([blockDrag.value.key]) : new Set();
    const freezeSettleKeys = new Set(blockDrag.value?.freezeSettleKeys ?? []);

    return calculateLayout(selectedTransport.value, cargoFlat.value, manualPlacements.value, {
        basePlacements: base,
        freezeBase: true,
        excludeSettleKeys,
        freezeSettleKeys,
        placementGapMm: layoutOptions.value.placementGapMm,
    });
});

const multiVehicleSummary = computed(() => layoutResult.value?.multiSummary ?? multiLayoutResult.value ?? null);

const layoutStatusLabel = computed(() => {
    const multi = multiVehicleSummary.value;
    if (!multi || multi.truckCount <= 1) {
        return layoutResult.value?.fits ? 'Влезает' : 'Не влезает';
    }

    if (multi.fits) {
        return `${multi.truckCount} маш · влезает`;
    }

    return `${multi.truckCount} маш · осталось ${multi.unplacedUnits} мест`;
});
const selectedBlock = computed(() => layoutResult.value.blocks.find((block) => block.key === selectedBlockKey.value) ?? null);

const sceneBlocks = computed(() => {
    let blocks = sortBlocksForScenePaint(layoutResult.value.blocks);
    const topKey = blockDrag.value?.key ?? selectedBlockKey.value;
    if (!topKey) {
        return blocks;
    }

    return [...blocks.filter((block) => block.key !== topKey), ...blocks.filter((block) => block.key === topKey)];
});

watch(layoutResult, (result) => {
    if (selectedBlockKey.value && !result.blocks.some((block) => block.key === selectedBlockKey.value)) {
        selectedBlockKey.value = null;
    }
});

watch([multiLayoutResult, selectedTransport, cargoFlat], () => {
    activeTruckIndex.value = 0;
});

const sceneBounds = computed(() => (selectedTransport.value ? buildSceneBounds(selectedTransport.value) : null));

const deckStyle = computed(() => {
    const transport = selectedTransport.value;
    const bounds = sceneBounds.value;
    if (!transport || !bounds) {
        return {};
    }
    const trailerRatio = transport.width_mm / transport.length_mm;
    const baseWidth = 760;
    return {
        width: `${Math.round(baseWidth * (bounds.total_length_mm / bounds.trailer_length_mm))}px`,
        height: `${Math.max(150, Math.round(baseWidth * trailerRatio * (bounds.total_width_mm / bounds.trailer_width_mm)))}px`,
    };
});

const trailerZoneStyle = computed(() => {
    const bounds = sceneBounds.value;
    if (!bounds) {
        return {};
    }

    return {
        left: `${(-bounds.min_x / bounds.total_length_mm) * 100}%`,
        top: `${(-bounds.min_y / bounds.total_width_mm) * 100}%`,
        width: `${(bounds.trailer_length_mm / bounds.total_length_mm) * 100}%`,
        height: `${(bounds.trailer_width_mm / bounds.total_width_mm) * 100}%`,
    };
});

const truckShadowStyle = computed(() => {
    const bounds = sceneBounds.value;
    if (!bounds) {
        return {};
    }

    return {
        left: `${((-bounds.min_x - 120) / bounds.total_length_mm) * 100}%`,
        top: '28%',
    };
});

const trailerCageStyle = computed(() => {
    const transport = selectedTransport.value;
    if (!transport) {
        return {};
    }

    const cageHeightPx = Math.max(8, transport.height_mm * sceneZScalePxPerMm(transport));

    return {
        '--cage-height': `${cageHeightPx}px`,
    };
});

const trailerGridStyle = computed(() => {
    const transport = selectedTransport.value;
    if (!transport) {
        return {};
    }

    const minorX = (1000 / transport.length_mm) * 100;
    const minorY = (1000 / transport.width_mm) * 100;
    const majorX = (3000 / transport.length_mm) * 100;
    const majorY = (3000 / transport.width_mm) * 100;

    return {
        backgroundSize: `${minorX}% ${minorY}%, ${majorX}% ${majorY}%`,
    };
});

const lengthRulerTicks = computed(() => {
    const transport = selectedTransport.value;
    if (!transport) {
        return [];
    }

    return buildLengthRulerTicks(transport.length_mm);
});

const widthRulerTicks = computed(() => {
    const transport = selectedTransport.value;
    if (!transport) {
        return [];
    }

    return buildWidthRulerTicks(transport.width_mm);
});

const heightRulerTicks = computed(() => {
    const transport = selectedTransport.value;
    if (!transport) {
        return [];
    }

    return buildHeightRulerTicks(transport.height_mm);
});

const sceneShellStyle = computed(() => ({
    transform: `translate(${scenePanX.value}px, ${scenePanY.value}px) scale(${sceneZoom.value})`,
}));

const sceneTransformStyle = computed(() => ({
    transform: `rotateX(${sceneRotationX.value}deg) rotateZ(${sceneRotationZ.value}deg)`,
}));

function focusSceneViewport() {
    sceneViewport.value?.focus({ preventScroll: true });
}

function syncBasePlacementsFromAuto() {
    const snapshot = snapshotPlacementsFromBlocks(autoLayoutResult.value.blocks);
    basePlacementsCache.value = snapshot;
    if (projectForm.value?.calculation) {
        projectForm.value.calculation.base_placements = snapshot;
    }
}

watch(manualMode, (enabled) => {
    if (enabled) {
        syncBasePlacementsFromAuto();
        if (activeStep.value === 'calculation') {
            focusSceneViewport();
        }
    }
});

watch(
    () => cargoItemDraft.value?.stackable,
    (stackable, previous) => {
        if (!cargoItemDraft.value || !stackable || previous === stackable) {
            return;
        }

        const current = Number(cargoItemDraft.value.max_stack || 0);
        if (current < DEFAULT_MAX_STACK) {
            cargoItemDraft.value.max_stack = DEFAULT_MAX_STACK;
        }
    },
);

watch(tightPacking, () => {
    basePlacementsCache.value = {};
    if (manualMode.value) {
        syncBasePlacementsFromAuto();
    }
});

const cargoLayoutSignature = computed(() => {
    return (projectForm.value?.cargo_groups ?? [])
        .flatMap((group) => (group.items ?? []).map((item) => [
            cargoItemKey(item),
            item.quantity,
            item.length_mm,
            item.width_mm,
            item.height_mm,
            item.stackable,
            item.max_stack,
        ].join(':')))
        .join('|');
});

watch(
    () => [projectForm.value?.selected_transport_template_id, cargoLayoutSignature.value],
    () => {
        basePlacementsCache.value = {};
        if (manualMode.value) {
            syncBasePlacementsFromAuto();
        }
    },
);

watch(activeStep, (step) => {
    if (step === 'calculation' && manualMode.value) {
        focusSceneViewport();
    }
});

onMounted(() => {
    const step = new URLSearchParams(window.location.search).get('step');
    if (step && steps.some((entry) => entry.key === step)) {
        activeStep.value = step;
    }
    if (manualMode.value && Object.keys(basePlacementsCache.value).length === 0) {
        syncBasePlacementsFromAuto();
    }
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
            base_placements: project.calculation?.base_placements ?? {},
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
        max_stack: DEFAULT_MAX_STACK,
        can_tilt: false,
        color,
    };
}

function indexQuery(extra = {}) {
    const query = { ...extra };

    if (props.linkContext?.order_id) {
        query.order = props.linkContext.order_id;
    } else if (props.linkContext?.lead_id) {
        query.lead = props.linkContext.lead_id;
    }

    return query;
}

function createProject() {
    const payload = { name: props.linkContext ? undefined : 'Новый расчёт' };

    if (props.linkContext?.order_id) {
        payload.order_id = props.linkContext.order_id;
    } else if (props.linkContext?.lead_id) {
        payload.lead_id = props.linkContext.lead_id;
    }

    router.post(route('modules.how-much-fits.projects.store'), payload);
}

function selectProject(projectId) {
    router.get(route('modules.how-much-fits.index'), indexQuery({ project: projectId }), { preserveState: false, preserveScroll: true });
}

function openProject(projectId) {
    if (projectForm.value?.id === projectId) {
        activeStep.value = 'cargo';
        return;
    }
    router.get(route('modules.how-much-fits.index'), indexQuery({ project: projectId, step: 'cargo' }), { preserveState: false, preserveScroll: true });
}

function setProjectSort(field) {
    if (projectSortBy.value === field) {
        projectSortDir.value = projectSortDir.value === 'asc' ? 'desc' : 'asc';
        return;
    }
    projectSortBy.value = field;
    projectSortDir.value = field === 'name' ? 'asc' : 'desc';
}

function openCargoItemEditor(groupIndex, itemIndex) {
    const item = projectForm.value?.cargo_groups?.[groupIndex]?.items?.[itemIndex];
    if (!item) {
        return;
    }
    cargoItemEdit.value = { groupIndex, itemIndex };
    cargoItemDraft.value = { ...item };
    cargoItemModalOpen.value = true;
}

function closeCargoItemModal() {
    cargoItemModalOpen.value = false;
    cargoItemDraft.value = null;
}

function saveCargoItemFromModal() {
    const { groupIndex, itemIndex } = cargoItemEdit.value;
    const group = projectForm.value?.cargo_groups?.[groupIndex];
    if (!group || !cargoItemDraft.value) {
        return;
    }
    group.items[itemIndex] = { ...group.items[itemIndex], ...cargoItemDraft.value };
    closeCargoItemModal();
}

function adjustCargoQuantity(delta) {
    if (!cargoItemDraft.value) {
        return;
    }
    cargoItemDraft.value.quantity = Math.max(1, Number(cargoItemDraft.value.quantity || 1) + delta);
}

function openManualTransportModal() {
    templateDraft.value = selectedTransport.value ? { ...selectedTransport.value } : blankTemplate();
    transportModalOpen.value = true;
}

function closeTransportModal() {
    transportModalOpen.value = false;
}

function saveManualTransport() {
    const payload = { ...templateDraft.value };
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeTransportModal();
            if (payload.id) {
                selectTransport(payload.id);
            }
        },
    };
    if (payload.id) {
        router.patch(route('modules.how-much-fits.transport-templates.update', payload.id), payload, options);
        return;
    }
    router.post(route('modules.how-much-fits.transport-templates.store'), payload, options);
}

function addTransportFromTemplate(template) {
    selectTransport(template.id);
}

function packageTypeLabel(type) {
    return {
        pallet: 'Паллета',
        box: 'Коробка',
        crate: 'Ящик',
        roll: 'Рулон',
        bag: 'Мешок',
        custom: 'Другое',
    }[type] ?? type;
}

function cargoConstraintLabel(item, key) {
    if (key === 'stackable') {
        return item.stackable ? 'да' : 'нет';
    }
    if (key === 'can_rotate') {
        return item.can_rotate ? 'да' : 'нет';
    }
    if (key === 'can_tilt') {
        return item.can_tilt ? 'да' : 'нет';
    }
    return 'нет';
}

function captureSceneView() {
    return {
        rotationX: sceneRotationX.value,
        rotationZ: sceneRotationZ.value,
        zoom: sceneZoom.value,
        panX: scenePanX.value,
        panY: scenePanY.value,
    };
}

function restoreSceneView(snapshot) {
    sceneRotationX.value = snapshot.rotationX;
    sceneRotationZ.value = snapshot.rotationZ;
    sceneZoom.value = snapshot.zoom;
    scenePanX.value = snapshot.panX;
    scenePanY.value = snapshot.panY;
}

function resolvePrintSceneScale() {
    const deck = deckEl.value;

    if (!deck) {
        return 0.68;
    }

    // A4 portrait: полезная ширина/высота под 3D-сцену (с учётом перспективы и поворота).
    const printableWidthPx = 680;
    const printableHeightPx = 520;
    const deckRect = deck.getBoundingClientRect();
    const projectedWidth = deckRect.width * 1.35;
    const projectedHeight = deckRect.height * 1.55;
    const scaleX = (printableWidthPx * 0.96) / Math.max(projectedWidth, 1);
    const scaleY = (printableHeightPx * 0.96) / Math.max(projectedHeight, 1);

    return clamp(Math.min(scaleX, scaleY, 1), 0.38, 0.92);
}

function exportCalculationPdf() {
    if (!selectedTransport.value) {
        window.alert('Сначала выберите транспорт для расчёта.');
        return;
    }

    const previousView = captureSceneView();
    resetSceneView();

    nextTick(() => {
        requestAnimationFrame(() => {
            const printScale = resolvePrintSceneScale();
            const root = document.documentElement;

            root.classList.add('howmuchfits-printing');
            root.style.setProperty('--howmuchfits-print-scale', String(printScale));

            const cleanup = () => {
                root.classList.remove('howmuchfits-printing');
                root.style.removeProperty('--howmuchfits-print-scale');
                restoreSceneView(previousView);
            };

            window.addEventListener('afterprint', cleanup, { once: true });
            window.print();
        });
    });
}

function saveProject() {
    if (!projectForm.value?.id) {
        return;
    }
    router.patch(route('modules.how-much-fits.projects.update', projectForm.value.id), projectPayload());
}

function deleteProject() {
    if (!projectForm.value?.id) {
        return;
    }
    deleteProjectById(projectForm.value.id);
}

function deleteProjectById(projectId) {
    const project = props.projects.find((entry) => Number(entry.id) === Number(projectId));
    if (!project || !window.confirm(`Удалить проект «${project.name}»?`)) {
        return;
    }
    router.delete(route('modules.how-much-fits.projects.destroy', projectId), {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
            activeStep.value = 'projects';
        },
    });
}

function stepBottomLabel(stepKey) {
    return {
        projects: 'Выбор проекта',
        cargo: 'Выбор груза',
        transport: 'Выбор транспорта',
        calculation: 'Расчет загрузки',
    }[stepKey] ?? stepKey;
}

function projectPayload() {
    return {
        name: projectForm.value.name,
        notes: projectForm.value.notes,
        selected_transport_template_id: projectForm.value.selected_transport_template_id,
        calculation: {
            fits: layoutResult.value.fits,
            ldm: layoutResult.value.ldm,
            placed_units: multiVehicleSummary.value?.placedUnits ?? layoutResult.value.placedUnits,
            total_units: multiVehicleSummary.value?.totalUnits ?? layoutResult.value.totalUnits,
            truck_count: multiVehicleSummary.value?.truckCount ?? 1,
            unplaced_units: multiVehicleSummary.value?.unplacedUnits ?? 0,
            used_volume_percent: multiVehicleSummary.value?.usedVolumePercent ?? layoutResult.value.usedVolumePercent,
            used_payload_percent: multiVehicleSummary.value?.usedPayloadPercent ?? layoutResult.value.usedPayloadPercent,
            warnings: layoutResult.value.warnings,
            manual_mode: manualMode.value,
            tight_packing: tightPacking.value,
            selected_manual_key: selectedBlockKey.value,
            manual_placements: manualPlacements.value,
            base_placements: manualMode.value
                ? snapshotPlacementsFromBlocks(layoutResult.value.blocks)
                : basePlacementsCache.value,
            scene_view: {
                rotation_x: sceneRotationX.value,
                rotation_z: sceneRotationZ.value,
                zoom: sceneZoom.value,
                pan_x: scenePanX.value,
                pan_y: scenePanY.value,
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
                max_stack: Number(item.stackable ? (item.max_stack || DEFAULT_MAX_STACK) : (item.max_stack || 1)),
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
    transportModalOpen.value = true;
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

function sceneZScalePxPerMm(transport) {
    return 92 / transport.height_mm;
}

function layoutZOptions() {
    return { placementGapMm: layoutOptions.value.placementGapMm };
}

function cubePositionStyle(block) {
    const bounds = sceneBounds.value;
    if (!bounds) {
        return {};
    }

    return {
        left: `${(block.x - bounds.min_x) / bounds.total_length_mm * 100}%`,
        top: `${(block.y - bounds.min_y) / bounds.total_width_mm * 100}%`,
        width: `${block.length / bounds.total_length_mm * 100}%`,
        height: `${block.width / bounds.total_width_mm * 100}%`,
        '--cube-color': block.color || '#60a5fa',
    };
}

function cubeLiftStyle(block) {
    const transport = selectedTransport.value;
    if (!transport) {
        return {};
    }

    const zScale = sceneZScalePxPerMm(transport);
    const cubeHeightPx = Math.max(4, block.unit_height * zScale);
    const zOffsetPx = Number(block.z || 0) * zScale;

    return {
        transform: `translateZ(${zOffsetPx}px)`,
        '--cube-height': `${cubeHeightPx}px`,
    };
}

function cubeBodyStyle(block) {
    const rotationY = block.rotation_y ?? 0;

    return {
        '--cube-rot-y': `${rotationY}deg`,
    };
}

function cubeDirectionStyle(block) {
    const rotationZ = block.rotation_z ?? (block.rotated ? 90 : 0);

    return {
        transform: `rotate(${rotationZ}deg)`,
    };
}

function cubeAlignGuideClasses(block) {
    if (!manualMode.value || !blockDrag.value?.alignHints) {
        return [];
    }

    const hints = blockDrag.value.alignHints;

    if (block.key === blockDrag.value.key) {
        const dragged = hints.dragged;

        return [
            dragged.stack ? 'cargo-cube-align-self-stack' : '',
            dragged.left ? 'cargo-cube-align-self-edge-left' : '',
            dragged.right ? 'cargo-cube-align-self-edge-right' : '',
            dragged.front ? 'cargo-cube-align-self-edge-front' : '',
            dragged.back ? 'cargo-cube-align-self-edge-back' : '',
        ].filter(Boolean);
    }

    const match = hints.blocks[block.key];
    if (!match) {
        return [];
    }

    return [
        match.stack && match.below ? 'cargo-cube-align-stack-below' : '',
        match.stack ? 'cargo-cube-align-stack' : '',
        !match.stack && (match.left || match.right || match.front || match.back) ? 'cargo-cube-align-edge' : '',
        match.left ? 'cargo-cube-align-edge-left' : '',
        match.right ? 'cargo-cube-align-edge-right' : '',
        match.front ? 'cargo-cube-align-edge-front' : '',
        match.back ? 'cargo-cube-align-edge-back' : '',
    ].filter(Boolean);
}

function canLiftBlock(block) {
    if (!selectedTransport.value) {
        return true;
    }

    return blockCanBeLifted(block, layoutResult.value.blocks, selectedTransport.value);
}

function blockUnderStack(block) {
    if (!selectedTransport.value || !block.in_trailer) {
        return false;
    }

    return !blockCanBeLifted(block, layoutResult.value.blocks, selectedTransport.value);
}

function refreshDragAlignHints(block, x, y, z) {
    if (!blockDrag.value) {
        return;
    }

    blockDrag.value.alignHints = computeSeriesAlignHints(
        block,
        x,
        y,
        block.length,
        block.width,
        layoutResult.value.blocks,
        { draggedZ: z },
    );
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
    const rotationZ = placementRotationZ(existing);
    const rotationY = placementRotationY(existing);
    projectForm.value.calculation.manual_placements = {
        ...projectForm.value.calculation.manual_placements,
        [block.key]: {
            x: Number(existing.x ?? block.x),
            y: Number(existing.y ?? block.y),
            z: Number(existing.z ?? block.z ?? 0),
            rotation_z: rotationZ,
            rotation_y: rotationY,
            rotated: rotationZ % 180 === 90,
            tilted: rotationY,
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
    focusSceneViewport();
}

function collectFreezeSettleKeys(key, x, y, length, width, blocks) {
    const probe = { x, y, length, width };

    return blocks
        .filter((block) => block.key !== key && blocksOverlapXY(probe, block))
        .map((block) => block.key);
}

function resolvePlacementZ(key, x, y, length, width, unitHeight, { preferTop = true } = {}) {
    const transport = selectedTransport.value;
    if (!transport) {
        return 0;
    }

    const probe = { x, y, length, width, unit_height: unitHeight };
    if (!blockInTrailer(probe, transport)) {
        return 0;
    }

    const others = layoutResult.value.blocks.filter((block) => block.key !== key);

    if (preferTop) {
        return findTopSupportedZForBlock(probe, others, transport, layoutZOptions());
    }

    return findSupportedZForBlock(probe, others, transport, layoutZOptions());
}

function placementWouldOverlap(key, x, y, length, width, unitHeight) {
    const z = resolvePlacementZ(key, x, y, length, width, unitHeight);
    const candidate = { x, y, length, width, height: unitHeight, unit_height: unitHeight, z };
    return layoutResult.value.blocks.some((block) => {
        if (block.key === key) {
            return false;
        }

        return blocksOverlap3D(candidate, block);
    });
}

function clampPositionToBounds(x, y, length, width) {
    const bounds = sceneBounds.value;
    if (!bounds) {
        return { x, y };
    }

    return {
        x: clamp(x, bounds.min_x, bounds.max_x - length),
        y: clamp(y, bounds.min_y, bounds.max_y - width),
    };
}

function applyManualPosition(key, placement, length, width, unitHeight, { allowOverlap = false } = {}) {
    const bounds = sceneBounds.value;
    if (!bounds) {
        return false;
    }

    const clamped = clampPositionToBounds(Number(placement.x), Number(placement.y), length, width);
    const z = resolvePlacementZ(key, clamped.x, clamped.y, length, width, unitHeight);

    if (!allowOverlap && placementWouldOverlap(key, clamped.x, clamped.y, length, width, unitHeight)) {
        return false;
    }

    updateManualPlacement(key, { ...placement, x: clamped.x, y: clamped.y, z });
    return true;
}

function rotateSelectedBlockZ(step) {
    if (!selectedBlock.value || !selectedTransport.value) {
        return;
    }
    const placement = ensureManualPlacement(selectedBlock.value);
    const nextRotationZ = (placementRotationZ(placement) + step * 90 + 360) % 360;
    const footprintSwapped = nextRotationZ % 180 === 90;
    const nextLength = footprintSwapped ? selectedBlock.value.base_width : selectedBlock.value.base_length;
    const nextWidth = footprintSwapped ? selectedBlock.value.base_length : selectedBlock.value.base_width;
    const centered = footprintPositionAfterRotation(
        placement.x,
        placement.y,
        selectedBlock.value.length,
        selectedBlock.value.width,
        nextLength,
        nextWidth,
    );
    const clamped = clampPositionToBounds(centered.x, centered.y, nextLength, nextWidth);
    const nextZ = resolvePlacementZ(
        selectedBlock.value.key,
        clamped.x,
        clamped.y,
        nextLength,
        nextWidth,
        selectedBlock.value.unit_height,
    );

    updateManualPlacement(selectedBlock.value.key, {
        ...placement,
        rotation_z: nextRotationZ,
        rotation_y: placementRotationY(placement),
        rotated: footprintSwapped,
        x: clamped.x,
        y: clamped.y,
        z: nextZ,
        locked: false,
    });
}

function rotateSelectedBlockY(step) {
    if (!selectedBlock.value) {
        return;
    }
    const placement = ensureManualPlacement(selectedBlock.value);
    const nextRotationY = (placementRotationY(placement) + step * 90 + 360) % 360;
    updateManualPlacement(selectedBlock.value.key, {
        ...placement,
        rotation_y: nextRotationY,
        tilted: nextRotationY,
        locked: false,
    });
}

function lockSelectedBlock() {
    if (!selectedBlock.value) {
        return false;
    }
    const block = selectedBlock.value;
    const placement = ensureManualPlacement(block);
    if (placementWouldOverlap(block.key, placement.x, placement.y, block.length, block.width, block.unit_height)) {
        return false;
    }
    updateManualPlacement(block.key, { ...placement, locked: true });
    return true;
}

function finalizeBlockDrag() {
    if (!blockDrag.value || !selectedBlock.value) {
        return;
    }
    const block = selectedBlock.value;
    const placement = ensureManualPlacement(block);
    const x = Number(placement.x);
    const y = Number(placement.y);
    const z = resolvePlacementZ(block.key, x, y, block.length, block.width, block.unit_height);
    const overlaps = placementWouldOverlap(block.key, x, y, block.length, block.width, block.unit_height);

    if (overlaps) {
        const validZ = resolvePlacementZ(
            block.key,
            blockDrag.value.lastValidX,
            blockDrag.value.lastValidY,
            block.length,
            block.width,
            block.unit_height,
        );
        updateManualPlacement(block.key, {
            ...placement,
            x: blockDrag.value.lastValidX,
            y: blockDrag.value.lastValidY,
            z: validZ,
            locked: false,
        });
    } else {
        updateManualPlacement(block.key, { ...placement, x, y, z, locked: true });
    }

    blockDrag.value = null;
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
    basePlacementsCache.value = {};
    projectForm.value.calculation = {
        ...(projectForm.value.calculation ?? {}),
        manual_placements: {},
        base_placements: {},
        selected_manual_key: null,
    };
    selectedBlockKey.value = null;
    if (manualMode.value) {
        syncBasePlacementsFromAuto();
    }
}

function updateManualPlacement(key, placement) {
    projectForm.value.calculation.manual_placements = {
        ...(projectForm.value.calculation.manual_placements ?? {}),
        [key]: placement,
    };
}

function startBlockDrag(event, block) {
    if (!manualMode.value || !selectedTransport.value || !deckEl.value) {
        return;
    }

    if (!canLiftBlock(block)) {
        selectBlock(block);
        return;
    }

    event.preventDefault();
    selectBlock(block);
    const placement = ensureManualPlacement(block);
    updateManualPlacement(block.key, { ...placement, locked: false });
    const deckRect = deckEl.value.getBoundingClientRect();
    const pointer = clientPointToSceneMm(event.clientX, event.clientY, deckRect, sceneBounds.value, sceneRotationZ.value);
    blockDrag.value = {
        key: block.key,
        grabOffsetX: Number(placement.x || 0) - pointer.x,
        grabOffsetY: Number(placement.y || 0) - pointer.y,
        lastValidX: Number(placement.x || 0),
        lastValidY: Number(placement.y || 0),
        freezeSettleKeys: collectFreezeSettleKeys(
            block.key,
            Number(placement.x || 0),
            Number(placement.y || 0),
            block.length,
            block.width,
            layoutResult.value.blocks,
        ),
        overlapping: false,
        alignHints: computeSeriesAlignHints(
            block,
            Number(placement.x || 0),
            Number(placement.y || 0),
            block.length,
            block.width,
            layoutResult.value.blocks,
            { draggedZ: Number(placement.z ?? block.z ?? 0) },
        ),
    };
    const cubeElement = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;
    cubeElement?.setPointerCapture?.(event.pointerId);
}

function startSceneRotate(event) {
    if (blockDrag.value) {
        return;
    }
    if (event.target instanceof Element && event.target.closest('.cargo-cube')) {
        return;
    }

    if (event.button === 2) {
        event.preventDefault();
        sceneDrag.value = {
            mode: 'pan',
            startClientX: event.clientX,
            startClientY: event.clientY,
            startPanX: scenePanX.value,
            startPanY: scenePanY.value,
        };
        return;
    }

    if (event.button !== 0) {
        return;
    }

    event.preventDefault();
    sceneDrag.value = {
        mode: 'rotate',
        startClientX: event.clientX,
        startClientY: event.clientY,
        startRotationX: sceneRotationX.value,
        startRotationZ: sceneRotationZ.value,
    };
}

function onPointerMove(event) {
    if (blockDrag.value && selectedBlock.value && selectedTransport.value && deckEl.value) {
        const deckRect = deckEl.value.getBoundingClientRect();
        const pointer = clientPointToSceneMm(event.clientX, event.clientY, deckRect, sceneBounds.value, sceneRotationZ.value);
        const block = selectedBlock.value;
        const placement = ensureManualPlacement(block);
        const snapStep = tightPacking.value ? 1 : 5;
        const nextX = Math.round((pointer.x + blockDrag.value.grabOffsetX) / snapStep) * snapStep;
        const nextY = Math.round((pointer.y + blockDrag.value.grabOffsetY) / snapStep) * snapStep;
        const overlaps = placementWouldOverlap(
            blockDrag.value.key,
            nextX,
            nextY,
            block.length,
            block.width,
            block.unit_height,
        );
        const nextZ = resolvePlacementZ(
            blockDrag.value.key,
            nextX,
            nextY,
            block.length,
            block.width,
            block.unit_height,
        );

        blockDrag.value.overlapping = overlaps;
        blockDrag.value.freezeSettleKeys = collectFreezeSettleKeys(
            blockDrag.value.key,
            nextX,
            nextY,
            block.length,
            block.width,
            layoutResult.value.blocks,
        );
        updateManualPlacement(blockDrag.value.key, { ...placement, x: nextX, y: nextY, z: nextZ, locked: false });
        refreshDragAlignHints(block, nextX, nextY, nextZ);

        if (!overlaps) {
            blockDrag.value.lastValidX = nextX;
            blockDrag.value.lastValidY = nextY;
        }

        return;
    }

    if (sceneDrag.value?.mode === 'pan') {
        scenePanX.value = sceneDrag.value.startPanX + (event.clientX - sceneDrag.value.startClientX);
        scenePanY.value = sceneDrag.value.startPanY + (event.clientY - sceneDrag.value.startClientY);
        return;
    }

    if (sceneDrag.value?.mode === 'rotate') {
        const deltaX = event.clientX - sceneDrag.value.startClientX;
        const deltaY = event.clientY - sceneDrag.value.startClientY;
        sceneRotationZ.value = sceneDrag.value.startRotationZ - deltaX * 0.25;
        sceneRotationX.value = clamp(sceneDrag.value.startRotationX - deltaY * 0.28, -12, 112);
    }
}

function stopPointerInteractions() {
    if (blockDrag.value) {
        finalizeBlockDrag();
    }
    sceneDrag.value = null;
}

function onSceneWheel(event) {
    const delta = event.deltaY > 0 ? -0.08 : 0.08;
    sceneZoom.value = clamp(sceneZoom.value + delta, 0.45, 2.4);
}

function onSceneKeydown(event) {
    if (!manualMode.value || activeStep.value !== 'calculation' || !selectedBlock.value) {
        return;
    }
    const target = event.target;
    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement) {
        return;
    }
    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        rotateSelectedBlockZ(-1);
    }
    if (event.key === 'ArrowRight') {
        event.preventDefault();
        rotateSelectedBlockZ(1);
    }
    if (event.key === 'ArrowUp') {
        event.preventDefault();
        rotateSelectedBlockY(-1);
    }
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        rotateSelectedBlockY(1);
    }
    if (event.key === 'Enter') {
        event.preventDefault();
        lockSelectedBlock();
    }
}

function rotateScene(deltaZ, deltaX) {
    sceneRotationZ.value -= deltaZ;
    sceneRotationX.value = clamp(sceneRotationX.value + deltaX, -12, 112);
}

function resetSceneView() {
    sceneRotationX.value = 58;
    sceneRotationZ.value = -34;
    sceneZoom.value = 1;
    scenePanX.value = 0;
    scenePanY.value = 0;
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
    border: 1px solid rgb(var(--crm-border));
    border-radius: var(--crm-radius-xl);
    background: rgb(var(--crm-surface));
    box-shadow: 0 10px 30px rgb(var(--crm-shadow) / 0.06);
}

.field {
    width: 100%;
    border-radius: var(--crm-radius-md);
    border: 1px solid rgb(var(--crm-border));
    background: rgb(var(--crm-surface));
    color: rgb(var(--crm-text));
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    outline: none;
}

.field:focus {
    border-color: rgb(var(--crm-accent));
}

.label {
    margin-bottom: 0.375rem;
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(var(--crm-text-muted));
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
    border-radius: var(--crm-radius-md);
    border: 1px solid rgb(var(--crm-accent) / 0.35);
    background: rgb(var(--crm-surface));
    font-size: 0.875rem;
    font-weight: 800;
    color: rgb(var(--crm-accent));
}

.manual-button:disabled {
    cursor: not-allowed;
    opacity: 0.4;
}

.danger-action:disabled {
    cursor: not-allowed;
    opacity: 0.45;
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
    background: rgb(var(--crm-accent-soft) / 1);
    color: rgb(var(--crm-accent));
}

.metric-card {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    border: 1px solid rgb(var(--crm-border));
    border-radius: var(--crm-radius-lg);
    background: rgb(var(--crm-surface));
    padding: 0.875rem;
    font-size: 0.75rem;
    color: rgb(var(--crm-text-muted));
}

.metric-card strong {
    font-size: 1rem;
    color: inherit;
}

.scene-viewport {
    cursor: grab;
    background: rgb(var(--crm-surface-muted));
    overflow: visible;
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
    border-radius: var(--crm-radius-lg);
    border: 1px solid rgb(var(--crm-border));
    background: rgb(var(--crm-surface) / 0.92);
    padding: 0.625rem 0.875rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(var(--crm-accent));
    box-shadow: 0 8px 24px rgb(var(--crm-shadow) / 0.12);
}

.scene-shell {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: visible;
    perspective: 1200px;
}

.scene {
    position: relative;
    transform: rotateX(58deg) rotateZ(-34deg);
    transform-style: preserve-3d;
}

.truck-shadow {
    position: absolute;
    width: 120px;
    height: 70px;
    border-radius: 1.5rem 0.5rem 0.5rem 1.5rem;
    background: rgb(15 23 42 / 0.16);
    filter: blur(1px);
    pointer-events: none;
}

.scene-deck {
    position: relative;
    transform-style: preserve-3d;
    overflow: visible;
}

.staging-pad {
    position: absolute;
    inset: 0;
    border-radius: var(--crm-radius-lg);
    background:
        repeating-linear-gradient(
            -45deg,
            rgb(var(--crm-surface-muted) / 0.95),
            rgb(var(--crm-surface-muted) / 0.95) 12px,
            rgb(var(--crm-surface) / 0.95) 12px,
            rgb(var(--crm-surface) / 0.95) 24px
        );
    box-shadow: inset 0 0 0 1px rgb(var(--crm-border) / 0.65);
    pointer-events: none;
}

.trailer-zone {
    position: absolute;
    transform-style: preserve-3d;
    border: 1px dashed rgb(15 23 42 / 0.28);
    background: rgb(var(--crm-accent-soft) / 0.22);
    box-shadow: 0 14px 36px rgb(var(--crm-shadow) / 0.16);
    pointer-events: none;
}

.trailer-cage {
    position: absolute;
    inset: 0;
    transform-style: preserve-3d;
    transform-origin: 50% 50% 0;
    pointer-events: none;
}

.trailer-cage-face {
    position: absolute;
    border: 1px solid rgb(15 23 42 / 0.82);
    background: transparent;
    box-shadow: none;
    backface-visibility: visible;
}

.trailer-cage-face-bottom {
    inset: 0;
    transform: translate3d(0, 0, 0);
}

.trailer-cage-face-top {
    inset: 0;
    transform: translate3d(0, 0, var(--cage-height));
}

.trailer-cage-face-front {
    left: 0;
    right: 0;
    bottom: 0;
    height: var(--cage-height);
    transform-origin: bottom center;
    transform: rotateX(-90deg);
}

.trailer-cage-face-back {
    left: 0;
    right: 0;
    top: 0;
    height: var(--cage-height);
    transform-origin: top center;
    transform: rotateX(90deg);
}

.trailer-cage-face-left {
    left: 0;
    top: 0;
    bottom: 0;
    width: var(--cage-height);
    transform-origin: left center;
    transform: rotateY(-90deg);
}

.trailer-cage-face-right {
    right: 0;
    top: 0;
    bottom: 0;
    width: var(--cage-height);
    transform-origin: right center;
    transform: rotateY(90deg);
}

.trailer-grid {
    position: absolute;
    inset: 0;
    transform: translateZ(1px);
    background-color: rgb(var(--crm-surface) / 0.08);
    background-image:
        linear-gradient(rgb(15 23 42 / 0.14) 1px, transparent 1px),
        linear-gradient(90deg, rgb(15 23 42 / 0.14) 1px, transparent 1px),
        linear-gradient(rgb(15 23 42 / 0.32) 1px, transparent 1px),
        linear-gradient(90deg, rgb(15 23 42 / 0.32) 1px, transparent 1px);
    pointer-events: none;
}

.trailer-floor {
    position: absolute;
    inset: 0;
    transform: translateZ(0.5px);
    background: rgb(var(--crm-surface) / 0.12);
    pointer-events: none;
}

.trailer-rulers {
    position: absolute;
    inset: 0;
    transform-style: preserve-3d;
    pointer-events: none;
}

.trailer-ruler {
    position: absolute;
    color: rgb(15 23 42 / 0.88);
    font-size: 0.6rem;
    font-weight: 700;
    line-height: 1;
}

.trailer-ruler-length {
    left: 0;
    right: 0;
    bottom: -1.35rem;
    height: 1.1rem;
    transform: translateZ(3px);
    border-bottom: 1px solid rgb(15 23 42 / 0.55);
}

.trailer-ruler-width {
    top: 0;
    bottom: 0;
    left: -1.65rem;
    width: 1.35rem;
    transform: translateZ(3px);
    border-left: 1px solid rgb(15 23 42 / 0.55);
}

.trailer-ruler-height {
    top: 0;
    bottom: 0;
    right: -1.65rem;
    width: 1.35rem;
    transform: translateZ(3px);
    border-right: 1px solid rgb(15 23 42 / 0.55);
}

.trailer-ruler-tick {
    position: absolute;
    display: flex;
    flex-direction: column;
    align-items: center;
    transform: translate(-50%, 0);
}

.trailer-ruler-width .trailer-ruler-tick {
    flex-direction: row;
    align-items: center;
    transform: translate(0, -50%);
}

.trailer-ruler-height .trailer-ruler-tick {
    flex-direction: row-reverse;
    align-items: center;
    transform: translate(50%, 0);
}

.trailer-ruler-mark {
    display: block;
    background: rgb(15 23 42 / 0.82);
}

.trailer-ruler-length .trailer-ruler-mark {
    width: 1px;
    height: 0.45rem;
}

.trailer-ruler-width .trailer-ruler-mark {
    width: 0.45rem;
    height: 1px;
}

.trailer-ruler-label {
    margin-top: 0.12rem;
    white-space: nowrap;
}

.trailer-ruler-width .trailer-ruler-label {
    margin-top: 0;
    margin-right: 0.2rem;
    text-align: right;
}

.trailer-ruler-height .trailer-ruler-mark {
    width: 0.45rem;
    height: 1px;
}

.trailer-ruler-height .trailer-ruler-label {
    margin-top: 0;
    margin-left: 0.2rem;
    white-space: nowrap;
}

:global(.dark) .trailer-zone {
    border-color: rgb(226 232 240 / 0.22);
}

:global(.dark) .trailer-cage-face {
    border-color: rgb(226 232 240 / 0.78);
}

:global(.dark) .trailer-ruler {
    color: rgb(226 232 240 / 0.92);
    border-color: rgb(226 232 240 / 0.55);
}

:global(.dark) .trailer-ruler-mark {
    background: rgb(226 232 240 / 0.82);
}

:global(.dark) .trailer-grid {
    background-image:
        linear-gradient(rgb(226 232 240 / 0.16) 1px, transparent 1px),
        linear-gradient(90deg, rgb(226 232 240 / 0.16) 1px, transparent 1px),
        linear-gradient(rgb(226 232 240 / 0.38) 1px, transparent 1px),
        linear-gradient(90deg, rgb(226 232 240 / 0.38) 1px, transparent 1px);
}

.cargo-cube {
    position: absolute;
    min-width: 8px;
    min-height: 8px;
    transform-style: preserve-3d;
    color: rgb(15 23 42);
    font-size: 0.65rem;
    font-weight: 800;
}

.cargo-cube-lift {
    position: absolute;
    inset: 0;
    transform-style: preserve-3d;
    transform-origin: 50% 50% 0;
}

.cargo-cube-body {
    position: absolute;
    inset: 0;
    transform-style: preserve-3d;
    transform-origin: 50% 50% calc(var(--cube-height) / 2);
    transform: rotateY(var(--cube-rot-y));
}

.cargo-cube-body-selected .cargo-face-top {
    outline: 2px solid rgb(14 165 233);
    outline-offset: -1px;
}

.cargo-cube-elevated .cargo-face-bottom {
    display: none;
}

.cargo-cube-manual {
    cursor: grab;
    touch-action: none;
}

.cargo-cube-manual:active,
.cargo-cube-dragging {
    cursor: grabbing;
}

.cargo-cube-covered {
    cursor: not-allowed;
}

.cargo-cube-covered .cargo-face-top {
    box-shadow: inset 0 0 0 2px rgb(245 158 11 / 0.85);
}

.cargo-cube-under-stack .cargo-face-front,
.cargo-cube-under-stack .cargo-face-left,
.cargo-cube-under-stack .cargo-face-right {
    outline: 2px solid rgb(2 132 199 / 0.55);
    outline-offset: -1px;
}

.cargo-cube-selected {
    z-index: 20;
}

.cargo-cube-dragging {
    z-index: 30;
}

.cargo-cube-staging {
    outline: 1px dashed rgb(161 161 170 / 0.8);
    outline-offset: 1px;
}

.cargo-cube-locked {
    outline: 3px solid rgb(16 185 129 / 0.9);
    outline-offset: 1px;
}

.cargo-cube-overlap {
    outline: 3px solid rgb(244 63 94 / 0.95);
    outline-offset: 1px;
}

.cargo-cube-align-stack-below .cargo-face,
.cargo-cube-align-stack .cargo-face {
    filter: brightness(0.7);
    border-color: rgb(2 132 199 / 0.9);
}

.cargo-cube-align-stack-below .cargo-face-top {
    box-shadow: inset 0 0 0 2px rgb(2 132 199 / 0.65);
}

.cargo-cube-align-edge .cargo-face {
    filter: brightness(0.82);
    border-color: rgb(15 23 42 / 0.55);
}

.cargo-cube-align-edge-left .cargo-face-left,
.cargo-cube-align-edge-left .cargo-face-top {
    box-shadow: inset 3px 0 0 rgb(2 132 199 / 0.9);
}

.cargo-cube-align-edge-right .cargo-face-right,
.cargo-cube-align-edge-right .cargo-face-top {
    box-shadow: inset -3px 0 0 rgb(2 132 199 / 0.9);
}

.cargo-cube-align-edge-front .cargo-face-front,
.cargo-cube-align-edge-front .cargo-face-top {
    box-shadow: inset 0 3px 0 rgb(2 132 199 / 0.9);
}

.cargo-cube-align-edge-back .cargo-face-back,
.cargo-cube-align-edge-back .cargo-face-top {
    box-shadow: inset 0 -3px 0 rgb(2 132 199 / 0.9);
}

.cargo-cube-align-self-stack .cargo-face-top {
    border-color: rgb(2 132 199);
    box-shadow: inset 0 0 0 2px rgb(2 132 199 / 0.75);
}

.cargo-cube-align-self-edge-left .cargo-face-left,
.cargo-cube-align-self-edge-left .cargo-face-top {
    box-shadow: inset 3px 0 0 rgb(2 132 199);
}

.cargo-cube-align-self-edge-right .cargo-face-right,
.cargo-cube-align-self-edge-right .cargo-face-top {
    box-shadow: inset -3px 0 0 rgb(2 132 199);
}

.cargo-cube-align-self-edge-front .cargo-face-front,
.cargo-cube-align-self-edge-front .cargo-face-top {
    box-shadow: inset 0 3px 0 rgb(2 132 199);
}

.cargo-cube-align-self-edge-back .cargo-face-back,
.cargo-cube-align-self-edge-back .cargo-face-top {
    box-shadow: inset 0 -3px 0 rgb(2 132 199);
}

.cargo-face {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    border: 1px solid rgb(var(--crm-border) / 0.55);
    background: color-mix(in srgb, var(--cube-color) 72%, rgb(var(--crm-surface)));
    box-shadow: inset 0 0 0 1px rgb(var(--crm-surface) / 0.22);
    backface-visibility: hidden;
    transform-style: preserve-3d;
}

.cargo-face-bottom {
    inset: 0;
    transform: translate3d(0, 0, 0);
    background: color-mix(in srgb, var(--cube-color) 62%, black);
    opacity: 0.9;
}

.cargo-face-top {
    inset: 0;
    gap: 0.25rem;
    transform: translate3d(0, 0, var(--cube-height));
    background:
        linear-gradient(135deg, rgb(255 255 255 / 0.22), transparent),
        color-mix(in srgb, var(--cube-color) 78%, white);
}

.cargo-face-front {
    left: 0;
    right: 0;
    bottom: 0;
    height: var(--cube-height);
    transform-origin: bottom center;
    transform: rotateX(-90deg);
    background: color-mix(in srgb, var(--cube-color) 68%, black);
    opacity: 0.8;
}

.cargo-face-back {
    left: 0;
    right: 0;
    top: 0;
    height: var(--cube-height);
    transform-origin: top center;
    transform: rotateX(90deg);
    background: color-mix(in srgb, var(--cube-color) 86%, white);
    opacity: 0.78;
}

.cargo-face-left {
    left: 0;
    top: 0;
    bottom: 0;
    width: var(--cube-height);
    transform-origin: left center;
    transform: rotateY(-90deg);
    background: color-mix(in srgb, var(--cube-color) 54%, black);
    opacity: 0.76;
}

.cargo-face-right {
    right: 0;
    top: 0;
    bottom: 0;
    width: var(--cube-height);
    transform-origin: right center;
    transform: rotateY(90deg);
    background: color-mix(in srgb, var(--cube-color) 72%, black);
    opacity: 0.8;
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

.workspace {
    min-height: 0;
}

.bottom-steps {
    background: rgb(var(--crm-surface));
    border-color: rgb(var(--crm-border));
}

.bottom-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 0.75rem 0.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgb(var(--crm-text-muted));
}

.bottom-step-active {
    color: rgb(var(--crm-accent));
    background: rgb(var(--crm-accent-soft) / 0.65);
}

.sort-pill,
.group-tab,
.category-tab {
    border-radius: 999px;
    border: 1px solid rgb(var(--crm-border));
    background: rgb(var(--crm-surface));
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(var(--crm-text-muted));
}

.sort-pill-active,
.group-tab-active {
    border-color: rgb(var(--crm-accent) / 0.35);
    background: rgb(var(--crm-accent-soft));
    color: rgb(var(--crm-accent));
}

.category-tab {
    border: none;
    border-radius: 0;
    border-bottom: 2px solid transparent;
    padding: 0.5rem 0;
}

.category-tab-active {
    color: rgb(var(--crm-accent));
    border-bottom-color: rgb(var(--crm-accent));
    background: transparent;
}

.text-link {
    font-size: 0.75rem;
    font-weight: 700;
    color: rgb(var(--crm-accent));
}

.icon-button {
    display: inline-flex;
    height: 2rem;
    width: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: var(--crm-radius-md);
    border: 1px solid rgb(var(--crm-border));
    background: rgb(var(--crm-surface));
    color: rgb(var(--crm-text-muted));
}

.cargo-row {
    display: grid;
    width: 100%;
    grid-template-columns: auto 1fr auto;
    gap: 0.75rem;
    border-radius: var(--crm-radius-lg);
    padding: 0.875rem 0.5rem;
    text-align: left;
    transition: background 0.15s ease;
}

.cargo-row:hover {
    background: rgb(var(--crm-surface-muted));
}

.cargo-swatch {
    margin-top: 0.25rem;
    height: 1rem;
    width: 1rem;
    border-radius: 0.25rem;
}

.cargo-table-head {
    grid-template-columns: 1fr 1.4fr auto;
    gap: 0.75rem;
    padding: 0 0.5rem 0.5rem;
}

.summary-chip {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    border-radius: var(--crm-radius-md);
    border: 1px solid rgb(var(--crm-border));
    background: rgb(var(--crm-surface));
    padding: 0.5rem 0.625rem;
    color: rgb(var(--crm-text-muted));
}

.summary-chip strong {
    font-size: 0.75rem;
    color: rgb(var(--crm-text));
}

.summary-chip-ok {
    border-color: rgb(167 243 208);
    color: rgb(4 120 87);
}

.summary-chip-bad {
    border-color: rgb(254 202 202);
    color: rgb(220 38 38);
}

.scene-tool {
    border-radius: 0.5rem;
    border: 1px solid rgb(var(--crm-border));
    background: rgb(var(--crm-surface));
    color: rgb(var(--crm-text));
    padding: 0.25rem 0.5rem;
    font-size: 0.65rem;
    font-weight: 700;
}

.scene-tool:disabled {
    opacity: 0.45;
}

.qty-button {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    align-items: center;
    justify-content: center;
    border-radius: var(--crm-radius-md);
    border: 1px solid rgb(var(--crm-border));
    background: rgb(var(--crm-surface));
    color: rgb(var(--crm-text));
    font-size: 1.125rem;
    font-weight: 700;
}

.calc-sidebar {
    max-height: 100%;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }

    :global(body *) {
        visibility: hidden;
    }

    :global(.print-area),
    :global(.print-area *) {
        visibility: visible;
    }

    :global(html.howmuchfits-printing .print-area) {
        position: absolute;
        inset: 0;
        display: flex !important;
        flex-direction: column !important;
        gap: 0.75rem;
        width: 100%;
        height: auto;
        min-height: auto;
        background: white;
    }

    :global(html.howmuchfits-printing .print-area-scene) {
        order: 1;
        width: 100%;
        flex: none;
        overflow: visible !important;
        border: none !important;
    }

    :global(html.howmuchfits-printing .print-area-summary) {
        order: 2;
        width: 100%;
        max-height: none !important;
        overflow: visible !important;
        flex: none;
        background: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    :global(html.howmuchfits-printing .scene-viewport) {
        min-height: 62vh !important;
        height: 62vh !important;
        max-height: 680px;
        overflow: visible !important;
        flex: none !important;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    :global(html.howmuchfits-printing .scene-shell) {
        position: relative !important;
        inset: auto !important;
        width: 100%;
        height: 100%;
        transform: translate(0, 0) scale(var(--howmuchfits-print-scale, 0.68)) !important;
        transform-origin: center center;
    }

    :global(html.howmuchfits-printing .print-area-summary .grid) {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .howmuchfits-module {
        padding: 0;
    }
}
</style>

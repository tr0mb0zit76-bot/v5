<script setup>
import { inject } from 'vue';
import { ORDER_WIZARD_CARGO_TAB_KEY } from '@/support/orderWizardCargoTabKey.js';

const {
    form,
    highlightRequiredField,
    cargoTypeOptions,
    packageTypeOptions,
    loadingTypeOptions,
    truckBodyTypeOptions,
    trailerTypeOptions,
    cargoTitleSuggestions,
    crmFieldFluid: fieldFluid,
    removeItem,
    addCargoItem,
    applyCargoTypeOption,
    applyPackageTypeOption,
    applyLoadingTypeOption,
    applyTruckBodyTypeOption,
    applyTrailerTypeOption,
    dictionarySelectionLabel,
    onCargoDecimalInput,
    cargoComputedVolumeM3,
    cargoLineTotalWeightKg,
    cargoPackageCountFactor,
    cargoWeightInKg,
    cargoLineTotalVolumeM3,
    cargoHasDimensions,
    cargoDimensionsLabel,
    cargoSummary,
    needsCargoPerformerAllocationUi,
    cargoPerformerAllocationColumns,
    cargoPerformerAllocationColumnSummaries,
    cargoAllocationRowStatuses,
    cargoAllocationFieldClass,
    findCargoAllocation,
    onCargoAllocationPackagesInput,
    onCargoAllocationWeightInput,
    allocationWeightFieldPlaceholder,
} = inject(ORDER_WIZARD_CARGO_TAB_KEY);
</script>

<template>
    <div class="space-y-4">
        <h2 class="text-base font-semibold">Грузовые позиции</h2>

        <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="space-y-2">
                <label class="text-sm font-medium">Объявленная стоимость груза</label>
                <input
                    v-model="form.cargo_declared_sum"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full max-w-xs rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                    placeholder="Сумма для таможни / страхования"
                />
                <p v-if="form.errors.cargo_declared_sum" class="text-xs text-rose-500">{{ form.errors.cargo_declared_sum }}</p>
            </div>
        </div>

        <div class="space-y-4">
            <div v-for="(item, index) in form.cargo_items" :key="`cargo-${index}`" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium">Груз {{ index + 1 }}</div>
                    <button type="button" class="rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40" @click="removeItem(form.cargo_items, index)">
                        Удалить
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-12 lg:gap-x-2 lg:gap-y-2">
                    <div class="space-y-1 lg:col-span-4">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Наименование</label>
                        <input v-model="item.name" list="cargo-title-suggestions" type="text" :class="['w-full rounded-lg border px-2 py-1.5 text-sm dark:bg-zinc-950', highlightRequiredField('cargo_name_' + index, item.name)]" />
                    </div>
                    <div class="space-y-1 lg:col-span-2">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Тип груза</label>
                        <select
                            v-model.number="item.cargo_type_id"
                            :class="['w-full rounded-lg border px-2 py-1.5 text-sm dark:bg-zinc-950', highlightRequiredField('cargo_type_' + index, item.cargo_type_id)]"
                            @change="applyCargoTypeOption(item)"
                        >
                            <option v-for="option in cargoTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-1 lg:col-span-2">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Вес</label>
                        <div class="flex gap-1.5">
                            <input
                                :value="item.weight_value ?? ''"
                                type="text"
                                inputmode="decimal"
                                class="min-w-0 flex-1 rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                @input="onCargoDecimalInput(item, 'weight_value', $event)"
                            />
                            <select v-model="item.weight_unit" class="w-[4.25rem] shrink-0 rounded-lg border border-zinc-200 bg-white px-1.5 py-1.5 text-xs dark:border-zinc-700 dark:bg-zinc-950">
                                <option value="kg">кг</option>
                                <option value="t">т</option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-1 lg:col-span-1">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Мест</label>
                        <input v-model="item.package_count" type="number" min="0" step="1" :class="fieldFluid" />
                    </div>
                    <div class="space-y-1 lg:col-span-1">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Упаковка</label>
                        <select v-model="item.pack_type_id" :class="fieldFluid" @change="applyPackageTypeOption(item)">
                            <option :value="null">—</option>
                            <option v-for="option in packageTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-1 lg:col-span-1">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">ТН ВЭД</label>
                        <input v-model="item.hs_code" type="text" :class="fieldFluid" />
                    </div>
                    <div class="space-y-1 lg:col-span-1">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Класс опасн.</label>
                        <input v-model="item.dangerous_class" type="text" :class="fieldFluid" />
                    </div>
                </div>

                <div class="flex flex-wrap items-end gap-x-2 gap-y-2">
                    <div class="grid min-w-[17rem] flex-1 gap-2 sm:grid-cols-3">
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Погрузка</label>
                            <details class="relative">
                                <summary class="flex h-8 cursor-pointer list-none items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-2 text-xs dark:border-zinc-700 dark:bg-zinc-950">
                                    <span class="truncate">{{ dictionarySelectionLabel(item.loading_type_items) }}</span>
                                    <span class="text-zinc-400">▾</span>
                                </summary>
                                <div class="absolute z-30 mt-1 max-h-44 w-full space-y-1 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2 text-xs shadow-lg dark:border-zinc-700 dark:bg-zinc-950">
                                    <label v-for="option in loadingTypeOptions" :key="option.value" class="flex cursor-pointer items-center gap-1.5">
                                        <input v-model="item.loading_type_ids" :value="option.value" type="checkbox" class="h-3.5 w-3.5 rounded border-zinc-300" @change="applyLoadingTypeOption(item)" />
                                        <span class="leading-tight">{{ option.label }}</span>
                                    </label>
                                </div>
                            </details>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Кузов</label>
                            <details class="relative">
                                <summary class="flex h-8 cursor-pointer list-none items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-2 text-xs dark:border-zinc-700 dark:bg-zinc-950">
                                    <span class="truncate">{{ dictionarySelectionLabel(item.truck_body_type_items) }}</span>
                                    <span class="text-zinc-400">▾</span>
                                </summary>
                                <div class="absolute z-30 mt-1 max-h-44 w-full space-y-1 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2 text-xs shadow-lg dark:border-zinc-700 dark:bg-zinc-950">
                                    <label v-for="option in truckBodyTypeOptions" :key="option.value" class="flex cursor-pointer items-center gap-1.5">
                                        <input v-model="item.truck_body_type_ids" :value="option.value" type="checkbox" class="h-3.5 w-3.5 rounded border-zinc-300" @change="applyTruckBodyTypeOption(item)" />
                                        <span class="leading-tight">{{ option.label }}</span>
                                    </label>
                                </div>
                            </details>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">Прицеп</label>
                            <details class="relative">
                                <summary class="flex h-8 cursor-pointer list-none items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-2 text-xs dark:border-zinc-700 dark:bg-zinc-950">
                                    <span class="truncate">{{ dictionarySelectionLabel(item.trailer_type_items) }}</span>
                                    <span class="text-zinc-400">▾</span>
                                </summary>
                                <div class="absolute z-30 mt-1 max-h-44 w-full space-y-1 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2 text-xs shadow-lg dark:border-zinc-700 dark:bg-zinc-950">
                                    <label v-for="option in trailerTypeOptions" :key="option.value" class="flex cursor-pointer items-center gap-1.5">
                                        <input v-model="item.trailer_type_ids" :value="option.value" type="checkbox" class="h-3.5 w-3.5 rounded border-zinc-300" @change="applyTrailerTypeOption(item)" />
                                        <span class="leading-tight">{{ option.label }}</span>
                                    </label>
                                </div>
                            </details>
                        </div>
                    </div>
                    <div class="flex min-w-0 flex-1 flex-wrap items-end gap-x-1.5 gap-y-1">
                        <div class="flex w-[5.5rem] shrink-0 items-center gap-1">
                            <label class="w-5 shrink-0 text-xs font-medium text-zinc-600 dark:text-zinc-400">Д</label>
                            <input
                                :value="item.length_m ?? ''"
                                type="text"
                                inputmode="decimal"
                                class="h-8 min-w-0 flex-1 rounded border border-zinc-200 bg-white px-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                @input="onCargoDecimalInput(item, 'length_m', $event)"
                            />
                        </div>
                        <div class="flex w-[5.5rem] shrink-0 items-center gap-1">
                            <label class="w-5 shrink-0 text-xs font-medium text-zinc-600 dark:text-zinc-400">Ш</label>
                            <input
                                :value="item.width_m ?? ''"
                                type="text"
                                inputmode="decimal"
                                class="h-8 min-w-0 flex-1 rounded border border-zinc-200 bg-white px-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                @input="onCargoDecimalInput(item, 'width_m', $event)"
                            />
                        </div>
                        <div class="flex w-[5.5rem] shrink-0 items-center gap-1">
                            <label class="w-5 shrink-0 text-xs font-medium text-zinc-600 dark:text-zinc-400">В</label>
                            <input
                                :value="item.height_m ?? ''"
                                type="text"
                                inputmode="decimal"
                                class="h-8 min-w-0 flex-1 rounded border border-zinc-200 bg-white px-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950"
                                @input="onCargoDecimalInput(item, 'height_m', $event)"
                            />
                        </div>
                        <div class="flex w-[8.25rem] shrink-0 items-center gap-1">
                            <label class="w-12 shrink-0 text-xs font-medium text-zinc-600 dark:text-zinc-400">Объём</label>
                            <input
                                :value="item.volume_m3 ?? ''"
                                type="text"
                                inputmode="decimal"
                                :readonly="cargoComputedVolumeM3(item) !== null"
                                placeholder="—"
                                :class="[
                                    'h-8 min-w-0 flex-1 rounded px-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950',
                                    cargoComputedVolumeM3(item) !== null
                                        ? 'cursor-default border border-dashed border-zinc-200 bg-zinc-50 text-zinc-800 dark:border-zinc-600 dark:bg-zinc-900/60 dark:text-zinc-100'
                                        : 'border border-zinc-200 bg-white dark:border-zinc-700',
                                ]"
                                @input="onCargoDecimalInput(item, 'volume_m3', $event)"
                            />
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-12">
                    <div class="space-y-2 md:col-span-8">
                        <label class="text-sm font-medium">Описание</label>
                        <textarea v-model="item.description" rows="2" :class="fieldFluid" />
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/80 px-3 py-2 text-xs dark:border-zinc-700 dark:bg-zinc-900/40 md:col-span-4">
                        <div class="font-medium text-zinc-700 dark:text-zinc-200">Сводка позиции</div>
                        <div class="mt-1">
                            Вес: {{ cargoLineTotalWeightKg(item).toFixed(2) }} кг
                            <span v-if="cargoPackageCountFactor(item) > 1" class="text-zinc-500">({{ cargoWeightInKg(item).toFixed(2) }} кг × {{ cargoPackageCountFactor(item) }})</span>
                        </div>
                        <div>
                            Объём:
                            <template v-if="cargoLineTotalVolumeM3(item) > 0">{{ cargoLineTotalVolumeM3(item).toFixed(3) }} м³</template>
                            <template v-else>—</template>
                            <span v-if="cargoPackageCountFactor(item) > 1 && cargoLineTotalVolumeM3(item) > 0" class="text-zinc-500">({{ (cargoLineTotalVolumeM3(item) / cargoPackageCountFactor(item)).toFixed(3) }} м³ × {{ cargoPackageCountFactor(item) }})</span>
                        </div>
                        <div v-if="cargoHasDimensions(item)">Габариты (Д×Ш×В): {{ cargoDimensionsLabel(item) }}</div>
                        <div>Мест: {{ Number(item.package_count || 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <datalist id="cargo-title-suggestions">
            <option v-for="title in cargoTitleSuggestions" :key="title" :value="title" />
        </datalist>

        <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
            <div class="grid flex-1 gap-3 text-sm md:grid-cols-3">
                <div>Общий вес: <span class="font-medium">{{ cargoSummary.totalWeight.toFixed(2) }} кг</span></div>
                <div>Общий объём: <span class="font-medium">{{ cargoSummary.totalVolume.toFixed(2) }} м³</span></div>
                <div>Всего мест: <span class="font-medium">{{ cargoSummary.totalPackages }}</span></div>
            </div>
            <button
                type="button"
                class="shrink-0 rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                @click="addCargoItem"
            >
                Добавить груз
            </button>
        </div>

        <div v-if="needsCargoPerformerAllocationUi" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Распределение по исполнителям</h3>
                <p class="mt-0.5 text-xs text-zinc-500">
                    Места и вес по каждой машине. Вес подставляется из позиции груза (вес места × кол-во), если не указан вручную.
                    На плече с несколькими исполнителями сумма мест и веса должна совпадать с позицией.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr class="text-left text-xs text-zinc-500">
                            <th class="sticky left-0 z-10 min-w-[10rem] border-b border-zinc-200 bg-white py-2 pr-3 dark:border-zinc-700 dark:bg-zinc-950">Груз</th>
                            <th
                                v-for="column in cargoPerformerAllocationColumns"
                                :key="`alloc-head-${column.key}`"
                                class="min-w-[8.5rem] border-b border-zinc-200 px-2 py-2 dark:border-zinc-700"
                            >
                                {{ column.label }}
                            </th>
                            <th class="min-w-[8rem] border-b border-zinc-200 px-2 py-2 dark:border-zinc-700">Проверка по плечам</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(item, cargoIndex) in form.cargo_items"
                            :key="`alloc-row-${cargoIndex}`"
                            :class="cargoAllocationRowStatuses[cargoIndex]?.isMismatch ? 'bg-rose-50/60 dark:bg-rose-950/20' : ''"
                        >
                            <td class="sticky left-0 z-10 border-b border-zinc-100 bg-white py-2 pr-3 align-top dark:border-zinc-800 dark:bg-zinc-950">
                                <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ item.name || `Груз ${cargoIndex + 1}` }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ Number(item.package_count || 0) }} мест · {{ cargoLineTotalWeightKg(item).toFixed(0) }} кг
                                </div>
                            </td>
                            <td
                                v-for="column in cargoPerformerAllocationColumns"
                                :key="`alloc-cell-${cargoIndex}-${column.key}`"
                                class="border-b border-zinc-100 px-2 py-2 align-top dark:border-zinc-800"
                            >
                                <div class="flex flex-col gap-1">
                                    <input
                                        :value="findCargoAllocation(item, column.stage, column.carrier_slot)?.package_count ?? ''"
                                        type="number"
                                        min="0"
                                        step="1"
                                        :class="cargoAllocationFieldClass"
                                        placeholder="Мест"
                                        @input="onCargoAllocationPackagesInput(item, column, $event.target.value)"
                                    />
                                    <input
                                        :value="findCargoAllocation(item, column.stage, column.carrier_slot)?.weight_value ?? ''"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :class="cargoAllocationFieldClass"
                                        :placeholder="allocationWeightFieldPlaceholder(item, column)"
                                        @input="onCargoAllocationWeightInput(item, column, $event.target.value)"
                                    />
                                </div>
                            </td>
                            <td class="border-b border-zinc-100 px-2 py-2 align-top text-xs dark:border-zinc-800">
                                <div
                                    v-for="leg in cargoAllocationRowStatuses[cargoIndex]?.legStatuses ?? []"
                                    :key="`alloc-status-${cargoIndex}-${leg.stage}`"
                                    class="leading-relaxed"
                                    :class="leg.packagesMismatch || leg.weightMismatch ? 'text-rose-600' : 'text-zinc-600 dark:text-zinc-400'"
                                >
                                    <template v-if="leg.isSplitLeg">
                                        {{ leg.stageLabel }}: {{ leg.stagePackages }}/{{ cargoAllocationRowStatuses[cargoIndex]?.expectedPackages }} мест,
                                        {{ leg.stageWeightKg.toFixed(0) }}/{{ cargoAllocationRowStatuses[cargoIndex]?.expectedWeightKg.toFixed(0) }} кг
                                    </template>
                                    <template v-else>
                                        {{ leg.stageLabel }}: {{ leg.stagePackages > 0 ? `${leg.stagePackages} мест` : '—' }}
                                        <span v-if="leg.stageWeightKg > 0"> · {{ leg.stageWeightKg.toFixed(0) }} кг</span>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            <td class="sticky left-0 z-10 bg-zinc-50 py-2 pr-3 dark:bg-zinc-900/80">Сводка по машине</td>
                            <td
                                v-for="column in cargoPerformerAllocationColumnSummaries"
                                :key="`alloc-foot-${column.key}`"
                                class="px-2 py-2"
                            >
                                <template v-if="column.hasAny">
                                    {{ column.totalPackages }} мест<br>
                                    {{ column.totalWeightKg.toFixed(0) }} кг
                                </template>
                                <span v-else class="text-zinc-400">—</span>
                            </td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>

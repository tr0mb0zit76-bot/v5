<script setup>
import { computed, watch } from 'vue';
import { Plus } from 'lucide-vue-next';
import {
    applyCargoTypeOption,
    applyLoadingTypeOption,
    applyPackageTypeOption,
    applyTrailerTypeOption,
    applyTruckBodyTypeOption,
    blankLeadCargoItem,
    cargoComputedVolumeM3,
    cargoDimensionFieldsEmpty,
    cargoDimensionsLabel,
    cargoHasDimensions,
    cargoLineTotalVolumeM3,
    cargoLineTotalWeightKg,
    cargoPackageCountFactor,
    cargoWeightInKg,
    leadCargoSummary,
} from '@/support/leadWizardCargo.js';
import { dictionarySelectionLabel, sanitizeDecimalInput } from '@/support/wizardDictionaryHelpers.js';
import { crmBtnSecondary, crmFieldFluid } from '@/support/crmUi.js';

const cargoItems = defineModel('cargoItems', { type: Array, required: true });

const props = defineProps({
    cargoTypeOptions: { type: Array, default: () => [] },
    packageTypeOptions: { type: Array, default: () => [] },
    loadingTypeOptions: { type: Array, default: () => [] },
    truckBodyTypeOptions: { type: Array, default: () => [] },
    trailerTypeOptions: { type: Array, default: () => [] },
    cargoTitleSuggestions: { type: Array, default: () => [] },
});

const cargoSummary = computed(() => leadCargoSummary(cargoItems.value));

function addCargoItem() {
    cargoItems.value = [...cargoItems.value, blankLeadCargoItem()];
}

function removeCargoItem(index) {
    const next = [...cargoItems.value];
    next.splice(index, 1);
    cargoItems.value = next.length > 0 ? next : [blankLeadCargoItem()];
}

function onCargoTypeChange(item) {
    applyCargoTypeOption(item, props.cargoTypeOptions);
}

function onPackageTypeChange(item) {
    applyPackageTypeOption(item, props.packageTypeOptions);
}

function onLoadingTypeChange(item) {
    applyLoadingTypeOption(item, props.loadingTypeOptions);
}

function onTruckBodyTypeChange(item) {
    applyTruckBodyTypeOption(item, props.truckBodyTypeOptions);
}

function onTrailerTypeChange(item) {
    applyTrailerTypeOption(item, props.trailerTypeOptions);
}

function onCargoDecimalInput(item, field, event) {
    item[field] = sanitizeDecimalInput(event.target.value);

    if (field === 'weight_value') {
        item.weight_kg = item.weight_value;
    }
}

watch(
    () => cargoItems.value,
    (items) => {
        items.forEach((item) => {
            const volume = cargoComputedVolumeM3(item);
            if (volume !== null) {
                item.volume_m3 = Math.round(volume * 1000) / 1000;
            } else if (!cargoDimensionFieldsEmpty(item)) {
                item.volume_m3 = null;
            }
        });
    },
    { deep: true, immediate: true },
);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold">Грузовые позиции</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Несколько грузов в одном лиде.</p>
            </div>
            <button type="button" :class="crmBtnSecondary" @click="addCargoItem">
                <Plus class="h-4 w-4" />
                Добавить груз
            </button>
        </div>

        <div class="space-y-4">
            <div
                v-for="(item, index) in cargoItems"
                :key="`lead-cargo-${index}`"
                class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800"
            >
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium">Груз {{ index + 1 }}</div>
                    <button
                        type="button"
                        class="rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40"
                        @click="removeCargoItem(index)"
                    >
                        Удалить
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-12 lg:gap-x-2 lg:gap-y-2">
                    <div class="space-y-1 lg:col-span-4">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Наименование</label>
                        <input v-model="item.name" list="lead-cargo-title-suggestions" type="text" :class="crmFieldFluid" />
                    </div>
                    <div class="space-y-1 lg:col-span-2">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Тип груза</label>
                        <select
                            v-model.number="item.cargo_type_id"
                            :class="crmFieldFluid"
                            @change="onCargoTypeChange(item)"
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
                        <input v-model="item.package_count" type="number" min="0" step="1" :class="crmFieldFluid" />
                    </div>
                    <div class="space-y-1 lg:col-span-1">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Упаковка</label>
                        <select v-model="item.pack_type_id" :class="crmFieldFluid" @change="onPackageTypeChange(item)">
                            <option :value="null">—</option>
                            <option v-for="option in packageTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-1 lg:col-span-1">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">ТН ВЭД</label>
                        <input v-model="item.hs_code" type="text" :class="crmFieldFluid" />
                    </div>
                    <div class="space-y-1 lg:col-span-1">
                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Класс опасн.</label>
                        <input v-model="item.dangerous_class" type="text" :class="crmFieldFluid" />
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
                                        <input v-model="item.loading_type_ids" :value="option.value" type="checkbox" class="h-3.5 w-3.5 rounded border-zinc-300" @change="onLoadingTypeChange(item)" />
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
                                        <input v-model="item.truck_body_type_ids" :value="option.value" type="checkbox" class="h-3.5 w-3.5 rounded border-zinc-300" @change="onTruckBodyTypeChange(item)" />
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
                                        <input v-model="item.trailer_type_ids" :value="option.value" type="checkbox" class="h-3.5 w-3.5 rounded border-zinc-300" @change="onTrailerTypeChange(item)" />
                                        <span class="leading-tight">{{ option.label }}</span>
                                    </label>
                                </div>
                            </details>
                        </div>
                    </div>
                    <div class="flex min-w-0 flex-1 flex-wrap items-end gap-x-1.5 gap-y-1">
                        <div class="flex w-[5.5rem] shrink-0 items-center gap-1">
                            <label class="w-5 shrink-0 text-xs font-medium text-zinc-600 dark:text-zinc-400">Д</label>
                            <input :value="item.length_m ?? ''" type="text" inputmode="decimal" class="h-8 min-w-0 flex-1 rounded border border-zinc-200 bg-white px-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950" @input="onCargoDecimalInput(item, 'length_m', $event)" />
                        </div>
                        <div class="flex w-[5.5rem] shrink-0 items-center gap-1">
                            <label class="w-5 shrink-0 text-xs font-medium text-zinc-600 dark:text-zinc-400">Ш</label>
                            <input :value="item.width_m ?? ''" type="text" inputmode="decimal" class="h-8 min-w-0 flex-1 rounded border border-zinc-200 bg-white px-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950" @input="onCargoDecimalInput(item, 'width_m', $event)" />
                        </div>
                        <div class="flex w-[5.5rem] shrink-0 items-center gap-1">
                            <label class="w-5 shrink-0 text-xs font-medium text-zinc-600 dark:text-zinc-400">В</label>
                            <input :value="item.height_m ?? ''" type="text" inputmode="decimal" class="h-8 min-w-0 flex-1 rounded border border-zinc-200 bg-white px-1 text-xs tabular-nums dark:border-zinc-700 dark:bg-zinc-950" @input="onCargoDecimalInput(item, 'height_m', $event)" />
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
                        <textarea v-model="item.description" rows="2" :class="crmFieldFluid" />
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
                        </div>
                        <div v-if="cargoHasDimensions(item)">Габариты (Д×Ш×В): {{ cargoDimensionsLabel(item) }}</div>
                        <div>Мест: {{ Number(item.package_count || 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <datalist id="lead-cargo-title-suggestions">
            <option v-for="title in cargoTitleSuggestions" :key="title" :value="title" />
        </datalist>

        <div class="grid gap-3 rounded-2xl border border-zinc-200 p-4 text-sm dark:border-zinc-800 md:grid-cols-3">
            <div>Общий вес: <span class="font-medium">{{ cargoSummary.totalWeight.toFixed(2) }} кг</span></div>
            <div>Общий объём: <span class="font-medium">{{ cargoSummary.totalVolume.toFixed(2) }} м³</span></div>
            <div>Всего мест: <span class="font-medium">{{ cargoSummary.totalPackages }}</span></div>
        </div>
    </div>
</template>

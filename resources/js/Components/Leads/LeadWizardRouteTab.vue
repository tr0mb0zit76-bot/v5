<script setup>
import { computed, ref } from 'vue';
import { Minus, Plus } from 'lucide-vue-next';
import {
    addLeadRoutePointAfter,
    canRemoveLeadRoutePoint,
    leadRouteChainLabel,
    removeLeadRoutePoint,
    routePointCombinedContact,
    routePointTitle,
    routePointTimeBlockHeading,
    setRoutePointCity,
    setRoutePointCombinedContact,
    syncRoutePointCityFromAddress,
    routePointCityValue,
} from '@/support/leadWizardRoute.js';
import {
    addLeadPerformer,
    removeLeadPerformerAt,
    routePointsWithIndicesForLeg,
    stageLabel,
} from '@/support/leadWizardPerformers.js';
import { crmBtnSecondary, crmFieldFluid } from '@/support/crmUi.js';

const routePointInlineBtn =
    'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-zinc-200 text-zinc-600 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800';

const performers = defineModel('performers', { type: Array, required: true });
const routePoints = defineModel('routePoints', { type: Array, required: true });

const addressSuggestions = ref({});
const addressTimers = {};

const routeChainLabel = computed(() => leadRouteChainLabel(routePoints.value));

function addPerformer() {
    const next = addLeadPerformer(performers.value, routePoints.value);
    performers.value = next.performers;
    routePoints.value = next.routePoints;
}

function removePerformer(legIndex) {
    const next = removeLeadPerformerAt(performers.value, routePoints.value, legIndex);
    performers.value = next.performers;
    routePoints.value = next.routePoints;
}

function addRoutePointAfter(index) {
    routePoints.value = addLeadRoutePointAfter(routePoints.value, index);
}

function removeRoutePoint(index) {
    routePoints.value = removeLeadRoutePoint(routePoints.value, index);
}

function onRoutePointAddressInput(index) {
    const point = routePoints.value[index];
    if (point) {
        syncRoutePointCityFromAddress(point);
    }
    queueAddressLookup(index);
}

function queueAddressLookup(index) {
    clearTimeout(addressTimers[index]);

    if (String(routePoints.value[index]?.address ?? '').trim().length < 3) {
        addressSuggestions.value[index] = [];
        return;
    }

    addressTimers[index] = window.setTimeout(() => {
        fetchAddressSuggestions(index);
    }, 300);
}

async function fetchAddressSuggestions(index) {
    const query = routePoints.value[index]?.address ?? '';

    try {
        const response = await fetch(`${route('orders.suggest-address')}?query=${encodeURIComponent(query)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        addressSuggestions.value[index] = Array.isArray(data.suggestions) ? data.suggestions : [];
    } catch (error) {
        console.error('Address suggestions error', error);
        addressSuggestions.value[index] = [];
    }
}

function selectAddress(index, suggestion) {
    const point = routePoints.value[index];
    const existing = point.normalized_data || {};
    point.address = suggestion.value ?? '';
    const suggestedCity = suggestion.data?.city
        ?? suggestion.data?.settlement
        ?? suggestion.data?.city_with_type
        ?? existing.city
        ?? null;
    point.normalized_data = {
        ...existing,
        city: suggestedCity,
        region: suggestion.data?.region_with_type ?? suggestion.data?.region ?? existing.region ?? null,
        street: suggestion.data?.street_with_type ?? suggestion.data?.street ?? existing.street ?? null,
        house: suggestion.data?.house ?? existing.house ?? null,
        coordinates: {
            lat: suggestion.data?.geo_lat ?? existing.coordinates?.lat ?? null,
            lng: suggestion.data?.geo_lon ?? existing.coordinates?.lng ?? null,
        },
        kladr_id: suggestion.data?.kladr_id ?? existing.kladr_id ?? null,
        fias_id: suggestion.data?.fias_id ?? existing.fias_id ?? null,
    };
    syncRoutePointCityFromAddress(point);
    addressSuggestions.value[index] = [];
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold">Маршрут</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Плечи и точки погрузки / выгрузки до конверсии в заказ.</p>
            </div>
            <button type="button" :class="crmBtnSecondary" @click="addPerformer">
                <Plus class="h-4 w-4" />
                Добавить плечо
            </button>
        </div>

        <div class="rounded-2xl border border-dashed border-zinc-200 p-4 text-sm leading-relaxed text-zinc-500 dark:border-zinc-700">
            {{ routeChainLabel }}
        </div>

        <div v-if="performers.length > 1" class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Маршрут разбит на {{ performers.length }} плеча. Точки привязаны к своему плечу и перенесутся в заказ при конвертации.
            </p>
        </div>

        <div
            v-for="(performer, legIndex) in performers"
            :key="`lead-leg-${performer.stage}-${legIndex}`"
            class="space-y-4 rounded-2xl border border-zinc-200 bg-white/40 p-4 dark:border-zinc-700 dark:bg-zinc-950/30"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                <div>
                    <div class="text-base font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ stageLabel(performer.stage) }}
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Черновик перевозки на этом участке маршрута.</p>
                </div>
                <button
                    v-if="performers.length > 1"
                    type="button"
                    class="rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40"
                    @click="removePerformer(legIndex)"
                >
                    Удалить плечо
                </button>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Ориентир ставки перевозчика, ₽</label>
                    <input
                        v-model.number="performer.estimated_cost"
                        type="number"
                        min="0"
                        step="1"
                        :class="crmFieldFluid"
                        placeholder="Необязательно"
                    />
                </div>
            </div>

            <div
                v-for="item in routePointsWithIndicesForLeg(routePoints, performer.stage)"
                :key="`lead-route-point-${item.globalIndex}`"
                class="space-y-3 rounded-2xl border border-zinc-200 bg-white/60 p-4 dark:border-zinc-700 dark:bg-zinc-950/40"
            >
                <div class="text-base font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ routePointTitle(routePoints, item.point, item.globalIndex) }}
                </div>

                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_9rem_9.5rem_14rem] lg:items-end">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Адрес</label>
                        <div class="flex items-start gap-1.5">
                            <div class="relative min-w-0 flex-1">
                                <input
                                    v-model="item.point.address"
                                    type="text"
                                    :class="crmFieldFluid"
                                    placeholder="Начни вводить адрес"
                                    @input="onRoutePointAddressInput(item.globalIndex)"
                                    @blur="syncRoutePointCityFromAddress(item.point)"
                                />
                                <div
                                    v-if="addressSuggestions[item.globalIndex]?.length"
                                    class="absolute z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                                >
                                    <button
                                        v-for="suggestion in addressSuggestions[item.globalIndex]"
                                        :key="suggestion.value"
                                        type="button"
                                        class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                        @click="selectAddress(item.globalIndex, suggestion)"
                                    >
                                        <span class="text-sm font-medium">{{ suggestion.value }}</span>
                                        <span class="text-xs text-zinc-500">{{ suggestion.data?.region_with_type || suggestion.data?.region || '' }}</span>
                                    </button>
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-1 pt-0.5">
                                <button
                                    type="button"
                                    :class="routePointInlineBtn"
                                    title="Добавить ещё одну точку этого типа"
                                    @click="addRoutePointAfter(item.globalIndex)"
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    :class="routePointInlineBtn"
                                    :disabled="!canRemoveLeadRoutePoint(routePoints, item.globalIndex)"
                                    title="Удалить точку"
                                    @click="removeRoutePoint(item.globalIndex)"
                                >
                                    <Minus class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Город</label>
                        <input
                            :value="routePointCityValue(item.point)"
                            type="text"
                            :class="crmFieldFluid"
                            placeholder="Нормализованное название"
                            @input="setRoutePointCity(item.point, $event.target.value)"
                        />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Плановая дата</label>
                        <input v-model="item.point.planned_date" type="date" :class="crmFieldFluid" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">{{ routePointTimeBlockHeading(item.point.type) }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input v-model="item.point.planned_time_from" type="time" :class="crmFieldFluid" aria-label="Время с" />
                            <input v-model="item.point.planned_time_to" type="time" :class="crmFieldFluid" aria-label="Время до" />
                        </div>
                    </div>
                </div>

                <div v-if="item.point.type === 'loading'" class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Отправитель</label>
                        <input v-model="item.point.sender_name" type="text" :class="crmFieldFluid" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Контакт на загрузке</label>
                        <input
                            :value="routePointCombinedContact(item.point)"
                            type="text"
                            :class="crmFieldFluid"
                            placeholder="Имя и телефон"
                            @input="setRoutePointCombinedContact(item.point, $event.target.value)"
                        />
                    </div>
                </div>

                <div v-if="item.point.type === 'unloading'" class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Получатель</label>
                        <input v-model="item.point.recipient_name" type="text" :class="crmFieldFluid" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Контакт на выгрузке</label>
                        <input
                            :value="routePointCombinedContact(item.point)"
                            type="text"
                            :class="crmFieldFluid"
                            placeholder="Имя и телефон"
                            @input="setRoutePointCombinedContact(item.point, $event.target.value)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

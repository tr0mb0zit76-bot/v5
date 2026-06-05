<script setup>
import { computed, ref } from 'vue';
import { Plus } from 'lucide-vue-next';
import {
    addLeadRoutePoint,
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
import { crmBtnSecondary, crmFieldFluid } from '@/support/crmUi.js';

const routePoints = defineModel('routePoints', { type: Array, required: true });

const addressSuggestions = ref({});
const addressTimers = {};

const routeChainLabel = computed(() => leadRouteChainLabel(routePoints.value));

function addRoutePoint(type) {
    routePoints.value = addLeadRoutePoint(routePoints.value, type);
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
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Точки погрузки и выгрузки до конверсии в заказ.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" :class="crmBtnSecondary" @click="addRoutePoint('loading')">
                    <Plus class="h-4 w-4" />
                    Погрузка
                </button>
                <button type="button" :class="crmBtnSecondary" @click="addRoutePoint('unloading')">
                    <Plus class="h-4 w-4" />
                    Выгрузка
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-dashed border-zinc-200 p-4 text-sm leading-relaxed text-zinc-500 dark:border-zinc-700">
            {{ routeChainLabel }}
        </div>

        <div class="space-y-4">
            <div
                v-for="(point, index) in routePoints"
                :key="`lead-route-point-${index}`"
                class="space-y-3 rounded-2xl border border-zinc-200 bg-white/40 p-4 dark:border-zinc-700 dark:bg-zinc-950/30"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="text-base font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ routePointTitle(routePoints, point, index) }}
                    </div>
                    <button
                        v-if="routePoints.length > 1"
                        type="button"
                        class="rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40"
                        @click="removeRoutePoint(index)"
                    >
                        Удалить
                    </button>
                </div>

                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_9rem_9.5rem_14rem] lg:items-end">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Адрес</label>
                        <div class="relative">
                            <input
                                v-model="point.address"
                                type="text"
                                :class="crmFieldFluid"
                                placeholder="Начни вводить адрес"
                                @input="onRoutePointAddressInput(index)"
                                @blur="syncRoutePointCityFromAddress(point)"
                            />
                            <div
                                v-if="addressSuggestions[index]?.length"
                                class="absolute z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                            >
                                <button
                                    v-for="suggestion in addressSuggestions[index]"
                                    :key="suggestion.value"
                                    type="button"
                                    class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                    @click="selectAddress(index, suggestion)"
                                >
                                    <span class="text-sm font-medium">{{ suggestion.value }}</span>
                                    <span class="text-xs text-zinc-500">{{ suggestion.data?.region_with_type || suggestion.data?.region || '' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Город</label>
                        <input
                            :value="routePointCityValue(point)"
                            type="text"
                            :class="crmFieldFluid"
                            placeholder="Нормализованное название"
                            @input="setRoutePointCity(point, $event.target.value)"
                        />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Плановая дата</label>
                        <input v-model="point.planned_date" type="date" :class="crmFieldFluid" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">{{ routePointTimeBlockHeading(point.type) }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input v-model="point.planned_time_from" type="time" :class="crmFieldFluid" aria-label="Время с" />
                            <input v-model="point.planned_time_to" type="time" :class="crmFieldFluid" aria-label="Время до" />
                        </div>
                    </div>
                </div>

                <div v-if="point.type === 'loading'" class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Отправитель</label>
                        <input v-model="point.sender_name" type="text" :class="crmFieldFluid" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Контакт на загрузке</label>
                        <input
                            :value="routePointCombinedContact(point)"
                            type="text"
                            :class="crmFieldFluid"
                            placeholder="Имя и телефон"
                            @input="setRoutePointCombinedContact(point, $event.target.value)"
                        />
                    </div>
                </div>

                <div v-if="point.type === 'unloading'" class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Получатель</label>
                        <input v-model="point.recipient_name" type="text" :class="crmFieldFluid" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Контакт на выгрузке</label>
                        <input
                            :value="routePointCombinedContact(point)"
                            type="text"
                            :class="crmFieldFluid"
                            placeholder="Имя и телефон"
                            @input="setRoutePointCombinedContact(point, $event.target.value)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

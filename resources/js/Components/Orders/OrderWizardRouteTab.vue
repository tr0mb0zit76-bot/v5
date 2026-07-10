<script setup>
import { inject } from 'vue';
import { Minus, Plus, X } from 'lucide-vue-next';
import CarrierPortalInviteButton from '@/Components/Orders/CarrierPortalInviteButton.vue';
import OrderTrakloChatButton from '@/Components/Orders/OrderTrakloChatButton.vue';
import { ORDER_WIZARD_ROUTE_TAB_KEY } from '@/support/orderWizardRouteTabKey.js';

const ctx = inject(ORDER_WIZARD_ROUTE_TAB_KEY);
const {
    form,
    order,
    ownFleetContractor,
    borderCrossingLegPicker,
    routeChainLabel,
    hasBorderCrossingPoint,
    stageLabel,
    CARRIER_MODE_SINGLE,
    CARRIER_MODE_SPLIT,
    OWN_FLEET_CONTRACTOR_NAME,
    crmFieldFluid,
    crmSegmented,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
    addPerformer,
    removePerformer,
    setPerformerCarrierMode,
    isPerformerSplit,
    removeSplitCarrier,
    addSplitCarrier,
    splitCarrierSlotLabel,
    openCounterpartyModal,
    carrierSearchValue,
    highlightRequiredField,
    setCarrierResultsVisible,
    onPerformerCarrierInput,
    restorePerformerCarrierSearch,
    clearPerformerContractor,
    normalizeNullableNumber,
    isCarrierResultsVisible,
    filteredCarrierResults,
    selectOwnFleetPerformer,
    selectPerformerContractor,
    onSplitPerformerCarrierInput,
    restoreSplitPerformerCarrierSearch,
    clearSplitPerformerContractor,
    selectOwnFleetSplitSlot,
    selectSplitPerformerContractor,
    fleetVehicleOptionsForLeg,
    fleetDriverOptionsForLeg,
    loadFleetOptionsForLeg,
    maxActualDate,
    onPerformerActualDateInput,
    onSplitActualDateInput,
    routePointsWithIndicesForLeg,
    routePointsDragEnabled,
    draggedRoutePointIndex,
    dragOverRoutePointIndex,
    handleRoutePointDragStart,
    handleRoutePointDragOver,
    handleRoutePointDrop,
    handleRoutePointDragEnd,
    routePointTitle,
    routePointInlineBtn,
    removeRoutePointAt,
    addRoutePointAfter,
    canRemoveRoutePoint,
    onRoutePointAddressInput,
    syncRoutePointCityFromAddress,
    addressSuggestions,
    selectAddress,
    routePointAddressHighlightValue,
    routePointCityValue,
    setRoutePointCity,
    routePointTimeBlockHeading,
    routePointCombinedContact,
    setRoutePointCombinedContact,
    onRoutePointLegChanged,
    onBorderCrossingLegPickerChange,
} = ctx;
</script>

<template>
    <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold">Маршрут</h2>
                    <p v-if="form.errors.performers" class="mt-1 text-xs text-rose-500">{{ form.errors.performers }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="addPerformer">
                        Добавить плечо
                    </button>
                </div>
            </div>
        
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 flex-1 rounded-xl border border-dashed border-zinc-200 px-3 py-2 text-sm text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">
                    {{ routeChainLabel }}
                </div>
                <div
                    v-if="form.is_international_transport"
                    class="w-full shrink-0 sm:max-w-xs"
                >
                    <label class="sr-only" for="wizard-border-crossing-leg">Добавить прохождение границы на плечо</label>
                    <select
                        id="wizard-border-crossing-leg"
                        v-model="borderCrossingLegPicker"
                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-55 dark:border-zinc-700 dark:bg-zinc-950"
                        :disabled="hasBorderCrossingPoint"
                        @change="onBorderCrossingLegPickerChange"
                    >
                        <option value="">Добавить прохождение границы…</option>
                        <option v-for="(p, idx) in form.performers" :key="p.stage" :value="String(idx)">
                            {{ stageLabel(p.stage) }}
                        </option>
                    </select>
                </div>
            </div>
        
            <div class="space-y-6">
                <div
                    v-for="(performer, legIndex) in form.performers"
                    :key="`leg-route-${legIndex}`"
                    class="space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800"
                >
                    <div class="space-y-3 border-b border-zinc-100 pb-4 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ stageLabel(performer.stage) }}</span>
                            <div class="flex flex-wrap items-center gap-2">
                                <div :class="crmSegmented">
                                    <button
                                        type="button"
                                        :class="[!isPerformerSplit(performer) ? crmSegmentedBtnActive : crmSegmentedBtn, 'px-2.5 py-1 text-[11px]']"
                                        @click="setPerformerCarrierMode(legIndex, CARRIER_MODE_SINGLE)"
                                    >
                                        Один исполнитель
                                    </button>
                                    <button
                                        type="button"
                                        :class="[isPerformerSplit(performer) ? crmSegmentedBtnActive : crmSegmentedBtn, 'px-2.5 py-1 text-[11px]']"
                                        @click="setPerformerCarrierMode(legIndex, CARRIER_MODE_SPLIT)"
                                    >
                                        Несколько исполнителей
                                    </button>
                                </div>
                                <button
                                    v-if="form.performers.length > 1"
                                    type="button"
                                    class="shrink-0 rounded-xl border border-rose-200 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40"
                                    @click="removePerformer(legIndex)"
                                >
                                    Удалить плечо
                                </button>
                            </div>
                        </div>
        
                        <template v-if="!isPerformerSplit(performer)">
                            <div class="grid min-w-0 w-full grid-cols-1 gap-2 sm:grid-cols-12 sm:gap-3">
                                <div class="space-y-1 sm:col-span-5">
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Перевозчик</label>
                                        <div class="flex items-center gap-2">
                                            <CarrierPortalInviteButton
                                                v-if="order?.id && order?.can_edit_order"
                                                :order-id="order.id"
                                                :stage="performer.stage"
                                                :contractor-id="performer.contractor_id"
                                                :carrier-slot="1"
                                            />
                                            <OrderTrakloChatButton
                                                v-if="order?.id && performer.contractor_id"
                                                :order-id="order.id"
                                                :contractor-id="performer.contractor_id"
                                                external-party="carrier"
                                            />
                                            <button
                                                type="button"
                                                class="rounded-lg border border-zinc-200 px-2 py-1 text-[11px] hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                                @click.stop="openCounterpartyModal({ kind: 'performer', index: legIndex, type: 'carrier' })"
                                            >
                                                + Новый
                                            </button>
                                        </div>
                                    </div>
                                    <div class="relative">
                                        <input
                                            :value="carrierSearchValue('performer', legIndex)"
                                            type="text"
                                            :class="['w-full rounded-xl border bg-white px-3 py-2 pr-10 text-sm dark:bg-zinc-950', highlightRequiredField('performer_carrier', performer.contractor_id)]"
                                            placeholder="Поиск перевозчика"
                                            @focus="setCarrierResultsVisible('performer', legIndex, true)"
                                            @input="onPerformerCarrierInput(legIndex, $event.target.value)"
                                            @blur="restorePerformerCarrierSearch(legIndex)"
                                        />
                                        <button
                                            v-if="normalizeNullableNumber(form.performers[legIndex]?.contractor_id) !== null"
                                            type="button"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                            title="Очистить перевозчика"
                                            @click="clearPerformerContractor(legIndex)"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                        <div
                                            v-if="isCarrierResultsVisible('performer', legIndex)"
                                            class="absolute left-0 top-full z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                                        >
                                            <button
                                                v-if="ownFleetContractor?.id"
                                                type="button"
                                                class="flex w-full flex-col items-start border-b border-zinc-100 px-4 py-3 text-left hover:bg-sky-50 dark:border-zinc-800 dark:hover:bg-sky-950/30"
                                                @mousedown.prevent
                                                @click="selectOwnFleetPerformer(legIndex)"
                                            >
                                                <span class="text-sm font-medium text-sky-700 dark:text-sky-300">{{ OWN_FLEET_CONTRACTOR_NAME }}</span>
                                            </button>
                                            <button
                                                v-for="contractor in filteredCarrierResults('performer', legIndex)"
                                                :key="contractor.id"
                                                type="button"
                                                class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                                @mousedown.prevent
                                                @click="selectPerformerContractor(legIndex, contractor)"
                                            >
                                                <span class="text-sm font-medium">{{ contractor.name }}</span>
                                                <span class="text-xs text-zinc-500">{{ contractor.inn || 'Без ИНН' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-1 sm:col-span-3">
                                    <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Авто</label>
                                    <select
                                        v-model="performer.fleet_vehicle_id"
                                        :class="crmFieldFluid"
                                        :disabled="normalizeNullableNumber(performer.contractor_id) === null"
                                        @focus="loadFleetOptionsForLeg(legIndex)"
                                    >
                                        <option :value="null">—</option>
                                        <option v-for="v in fleetVehicleOptionsForLeg(legIndex)" :key="v.id" :value="v.id">{{ v.label }}</option>
                                    </select>
                                </div>
                                <div class="space-y-1 sm:col-span-4">
                                    <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Водитель</label>
                                    <select
                                        v-model="performer.fleet_driver_id"
                                        :class="crmFieldFluid"
                                        :disabled="normalizeNullableNumber(performer.contractor_id) === null"
                                        @focus="loadFleetOptionsForLeg(legIndex)"
                                    >
                                        <option :value="null">—</option>
                                        <option v-for="d in fleetDriverOptionsForLeg(legIndex)" :key="d.id" :value="d.id">{{ d.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <p
                                v-if="performer.carrier_portal_submission?.driver_full_name"
                                class="text-xs text-emerald-600 dark:text-emerald-400"
                            >
                                Заполнено перевозчиком: {{ performer.carrier_portal_submission.driver_full_name }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <div class="w-[8.75rem] space-y-0.5">
                                    <label class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Факт. погрузка</label>
                                    <input v-model="performer.loading_actual" type="date" :max="maxActualDate" class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" @change="onPerformerActualDateInput(performer, 'loading_actual')" />
                                </div>
                                <div class="w-[8.75rem] space-y-0.5">
                                    <label class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Факт. выгрузка</label>
                                    <input v-model="performer.unloading_actual" type="date" :max="maxActualDate" class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" @change="onPerformerActualDateInput(performer, 'unloading_actual')" />
                                </div>
                            </div>
                        </template>
        
                        <template v-else>
                            <div
                                v-for="(slot, slotIndex) in performer.split_carriers"
                                :key="`leg-${legIndex}-slot-${slot.slot}`"
                                class="space-y-3 rounded-xl border border-zinc-100 bg-zinc-50/60 p-3 dark:border-zinc-800 dark:bg-zinc-900/40"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ splitCarrierSlotLabel(slot.slot) }}</span>
                                    </div>
                                    <button
                                        v-if="performer.split_carriers.length > 2"
                                        type="button"
                                        class="rounded-lg border border-rose-200 px-2 py-1 text-[11px] text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40"
                                        @click="removeSplitCarrier(legIndex, slotIndex)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                                <div class="grid min-w-0 w-full grid-cols-1 gap-2 sm:grid-cols-12 sm:gap-3">
                                    <div class="space-y-1 sm:col-span-5">
                                        <div class="flex items-center justify-between gap-2">
                                            <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Перевозчик</label>
                                            <div class="flex items-center gap-2">
                                                <CarrierPortalInviteButton
                                                    v-if="order?.id && order?.can_edit_order"
                                                    :order-id="order.id"
                                                    :stage="performer.stage"
                                                    :contractor-id="slot.contractor_id"
                                                    :carrier-slot="slot.slot ?? slotIndex + 1"
                                                />
                                                <OrderTrakloChatButton
                                                    v-if="order?.id && slot.contractor_id"
                                                    :order-id="order.id"
                                                    :contractor-id="slot.contractor_id"
                                                    external-party="carrier"
                                                />
                                                <button
                                                    type="button"
                                                    class="rounded-lg border border-zinc-200 px-2 py-1 text-[11px] hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                                    @click.stop="openCounterpartyModal({ kind: 'performer-slot', index: `${legIndex}-${slotIndex}`, type: 'carrier' })"
                                                >
                                                    + Новый
                                                </button>
                                            </div>
                                        </div>
                                        <div class="relative">
                                            <input
                                                :value="carrierSearchValue('performer-slot', `${legIndex}-${slotIndex}`)"
                                                type="text"
                                                :class="['w-full rounded-xl border bg-white px-3 py-2 pr-10 text-sm dark:bg-zinc-950', highlightRequiredField(`performer_carrier_${legIndex}_${slotIndex}`, slot.contractor_id)]"
                                                placeholder="Поиск перевозчика"
                                                @focus="setCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`, true)"
                                                @input="onSplitPerformerCarrierInput(legIndex, slotIndex, $event.target.value)"
                                                @blur="restoreSplitPerformerCarrierSearch(legIndex, slotIndex)"
                                            />
                                            <button
                                                v-if="normalizeNullableNumber(slot.contractor_id) !== null"
                                                type="button"
                                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                                title="Очистить перевозчика"
                                                @click="clearSplitPerformerContractor(legIndex, slotIndex)"
                                            >
                                                <X class="h-4 w-4" />
                                            </button>
                                            <div
                                                v-if="isCarrierResultsVisible('performer-slot', `${legIndex}-${slotIndex}`)"
                                                class="absolute left-0 top-full z-20 mt-2 max-h-64 w-full overflow-auto rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900"
                                            >
                                                <button
                                                    v-if="ownFleetContractor?.id"
                                                    type="button"
                                                    class="flex w-full flex-col items-start border-b border-zinc-100 px-4 py-3 text-left hover:bg-sky-50 dark:border-zinc-800 dark:hover:bg-sky-950/30"
                                                    @mousedown.prevent
                                                    @click="selectOwnFleetSplitSlot(legIndex, slotIndex)"
                                                >
                                                    <span class="text-sm font-medium text-sky-700 dark:text-sky-300">{{ OWN_FLEET_CONTRACTOR_NAME }}</span>
                                                </button>
                                                <button
                                                    v-for="contractor in filteredCarrierResults('performer-slot', `${legIndex}-${slotIndex}`)"
                                                    :key="contractor.id"
                                                    type="button"
                                                    class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                                    @mousedown.prevent
                                                    @click="selectSplitPerformerContractor(legIndex, slotIndex, contractor)"
                                                >
                                                    <span class="text-sm font-medium">{{ contractor.name }}</span>
                                                    <span class="text-xs text-zinc-500">{{ contractor.inn || 'Без ИНН' }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-1 sm:col-span-3">
                                        <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Авто</label>
                                        <select
                                            v-model="slot.fleet_vehicle_id"
                                            :class="crmFieldFluid"
                                            :disabled="normalizeNullableNumber(slot.contractor_id) === null"
                                            @focus="loadFleetOptionsForLeg(legIndex, slotIndex)"
                                        >
                                            <option :value="null">—</option>
                                            <option v-for="v in fleetVehicleOptionsForLeg(legIndex, slotIndex)" :key="v.id" :value="v.id">{{ v.label }}</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1 sm:col-span-4">
                                        <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Водитель</label>
                                        <select
                                            v-model="slot.fleet_driver_id"
                                            :class="crmFieldFluid"
                                            :disabled="normalizeNullableNumber(slot.contractor_id) === null"
                                            @focus="loadFleetOptionsForLeg(legIndex, slotIndex)"
                                        >
                                            <option :value="null">—</option>
                                            <option v-for="d in fleetDriverOptionsForLeg(legIndex, slotIndex)" :key="d.id" :value="d.id">{{ d.label }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2 border-t border-zinc-200/80 pt-2 dark:border-zinc-700">
                                    <div class="w-[8.75rem] space-y-0.5">
                                        <label class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Факт. погрузка</label>
                                        <input v-model="slot.loading_actual" type="date" :max="maxActualDate" class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" @change="onSplitActualDateInput(slot, 'loading_actual')" />
                                    </div>
                                    <div class="w-[8.75rem] space-y-0.5">
                                        <label class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">Факт. выгрузка</label>
                                        <input v-model="slot.unloading_actual" type="date" :max="maxActualDate" class="w-full rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-950" @change="onSplitActualDateInput(slot, 'unloading_actual')" />
                                    </div>
                                </div>
                            </div>
                            <button
                                v-if="performer.split_carriers.length < 4"
                                type="button"
                                class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                @click="addSplitCarrier(legIndex)"
                            >
                                Добавить исполнителя
                            </button>
                        </template>
        
                        <div class="grid gap-3 border-t border-zinc-100 pt-3 md:grid-cols-2 dark:border-zinc-800">
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Особые условия на загрузке</label>
                                <textarea
                                    v-model="performer.loading_special_conditions"
                                    rows="2"
                                    class="w-full resize-y rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                    placeholder="Ограничения по времени, пропуска, техника на погрузке…"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Особые условия на выгрузке</label>
                                <textarea
                                    v-model="performer.unloading_special_conditions"
                                    rows="2"
                                    class="w-full resize-y rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                    placeholder="Окна выгрузки, документы на месте, контакт на воротах…"
                                />
                            </div>
                        </div>
                    </div>
        
                    <div
                        v-for="item in routePointsWithIndicesForLeg(performer.stage)"
                        :key="`point-${item.globalIndex}`"
                        :draggable="routePointsDragEnabled()"
                        class="space-y-3 rounded-2xl border border-zinc-200 bg-white/40 p-4 dark:border-zinc-700 dark:bg-zinc-950/30"
                        :class="[
                            draggedRoutePointIndex === item.globalIndex ? 'opacity-60 ring-2 ring-zinc-300 dark:ring-zinc-700' : '',
                            dragOverRoutePointIndex === item.globalIndex ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-200 dark:bg-zinc-800/60' : '',
                        ]"
                        @dragstart="handleRoutePointDragStart(item.globalIndex, $event)"
                        @dragover.prevent="handleRoutePointDragOver(item.globalIndex)"
                        @drop.prevent="handleRoutePointDrop(item.globalIndex)"
                        @dragend="handleRoutePointDragEnd"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 text-base font-semibold text-zinc-900 dark:text-zinc-50">
                                {{ routePointTitle(item.point, item.globalIndex) }}
                            </div>
                            <span
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-zinc-200 text-zinc-400 dark:border-zinc-700 dark:text-zinc-500"
                                :class="routePointsDragEnabled() ? 'cursor-grab' : 'cursor-not-allowed opacity-50'"
                                :title="routePointsDragEnabled() ? 'Перетащить этап' : 'Порядок этапов фиксирован по плечам — перетаскивание отключено'"
                            >
                                ⋮⋮
                            </span>
                        </div>
        
                        <template v-if="item.point.type === 'border_crossing'">
                            <div class="flex items-start justify-end">
                                <button
                                    type="button"
                                    :class="routePointInlineBtn"
                                    title="Удалить прохождение границы"
                                    @click="removeRoutePointAt(item.globalIndex)"
                                >
                                    <Minus class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div class="w-full space-y-3">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Код поста и наименование СВХ</label>
                                    <div class="grid gap-2 sm:grid-cols-2 sm:gap-3">
                                        <input
                                            v-model="form.customs_post_code"
                                            type="text"
                                            :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm dark:bg-zinc-950', form.errors.customs_post_code ? 'border-rose-500 dark:border-rose-500' : 'border-zinc-200 dark:border-zinc-700']"
                                            placeholder="Код поста"
                                        />
                                        <input
                                            v-model="form.svh_name"
                                            type="text"
                                            :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm dark:bg-zinc-950', form.errors.svh_name ? 'border-rose-500 dark:border-rose-500' : 'border-zinc-200 dark:border-zinc-700']"
                                            placeholder="Наименование СВХ / таможенного склада"
                                        />
                                    </div>
                                    <p v-if="form.errors.customs_post_code || form.errors.svh_name" class="text-xs text-rose-500">
                                        {{ form.errors.customs_post_code || form.errors.svh_name }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Адрес СВХ</label>
                                    <input
                                        v-model="form.svh_address"
                                        type="text"
                                        :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm dark:bg-zinc-950', form.errors.svh_address ? 'border-rose-500 dark:border-rose-500' : 'border-zinc-200 dark:border-zinc-700']"
                                        placeholder="Почтовый или производственный адрес"
                                    />
                                    <p v-if="form.errors.svh_address" class="text-xs text-rose-500">{{ form.errors.svh_address }}</p>
                                </div>
                            </div>
                        </template>
                        <div v-else class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_9rem_9.5rem_14rem] lg:items-end">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Адрес</label>
                                <div class="flex items-start gap-1.5">
                                    <div class="relative min-w-0 flex-1">
                                        <input
                                            v-model="item.point.address"
                                            type="text"
                                            :class="['w-full rounded-xl border px-3 py-2 text-sm dark:bg-zinc-950', highlightRequiredField('route_point_address_' + item.globalIndex, routePointAddressHighlightValue(item.point))]"
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
                                            :disabled="!canRemoveRoutePoint(item.globalIndex)"
                                            title="Удалить точку"
                                            @click="removeRoutePointAt(item.globalIndex)"
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
        
                        <div v-if="form.performers.length > 1" class="border-t border-zinc-100 pt-3 dark:border-zinc-800">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-zinc-500">Отнести этап к плечу</label>
                                <select v-model="item.point.stage" class="w-full max-w-md rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" @change="onRoutePointLegChanged">
                                    <option v-for="p in form.performers" :key="p.stage" :value="p.stage">
                                        {{ stageLabel(p.stage) }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </div>
</template>

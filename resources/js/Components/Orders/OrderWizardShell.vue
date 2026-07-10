<script setup>
import { OctagonAlert, Package, Save, X } from 'lucide-vue-next';
import OrderStatusIcon from '@/Components/Orders/OrderStatusIcon.vue';
import { crmTabButtonClasses } from '@/support/crmAppearance.js';
import {
    crmBtnCreate,
    crmBtnSecondary,
    crmFieldFluid,
    crmPanel,
    crmSegmented,
    crmSegmentedBtn,
    crmSegmentedBtnActive,
    crmWizardBack,
    crmWizardBody,
    crmWizardHeader,
    crmWizardShell,
} from '@/support/crmUi.js';

const props = defineProps({
    isMobileStandalone: { type: Boolean, default: false },
    isEditing: { type: Boolean, default: false },
    pageTitle: { type: String, required: true },
    mobilePageTitle: { type: String, default: '' },
    desktopSubtitle: { type: String, default: 'Новый заказ' },
    formProcessing: { type: Boolean, default: false },
    customerDebtBlocked: { type: Boolean, default: false },
    isOrderFormEditable: { type: Boolean, default: true },
    hasUnsavedDocumentFiles: { type: Boolean, default: false },
    coreValidationIssues: { type: Array, default: () => [] },
    tabs: { type: Array, default: () => [] },
    activeTab: { type: String, required: true },
    isInternationalTransport: { type: Boolean, default: false },
    orderStatusBadgeLabel: { type: String, default: '' },
    orderStatusIconMeta: { type: Object, default: null },
    orderStatusIconKey: { type: String, default: null },
    canShowMarkDisruptionButton: { type: Boolean, default: false },
    canUseHowMuchFits: { type: Boolean, default: false },
    wizardBodyInert: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:activeTab',
    'update:isInternationalTransport',
    'go-back',
    'submit',
    'mark-disruption',
    'open-how-much-fits',
]);

function setActiveTab(key) {
    emit('update:activeTab', key);
}

function setInternationalTransport(value) {
    emit('update:isInternationalTransport', value);
}
</script>

<template>
    <div :class="crmWizardShell">
        <div
            v-if="isMobileStandalone"
            :class="`${crmPanel} space-y-3 px-4 py-4`"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        :class="crmWizardBack"
                        title="К реестру"
                        @click="emit('go-back')"
                    >
                        <X class="h-5 w-5" />
                        <span class="sr-only">К реестру</span>
                    </button>

                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Мобильный мастер</div>
                        <h1 class="truncate text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ mobilePageTitle || pageTitle }}
                        </h1>
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Статус</span>
                                <span
                                    class="inline-flex max-w-full items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-xs font-medium text-zinc-800 dark:border-zinc-600 dark:bg-zinc-800/80 dark:text-zinc-100"
                                    :title="'Рассчитывается автоматически по фактическим датам плеч, документам и оплатам. Текущий: ' + orderStatusBadgeLabel"
                                >
                                    <OrderStatusIcon v-if="orderStatusIconMeta" :icon-key="orderStatusIconKey" :size="18" />
                                    <span class="min-w-0 truncate">{{ orderStatusBadgeLabel }}</span>
                                </span>
                                <button
                                    v-if="canShowMarkDisruptionButton"
                                    type="button"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700 transition-colors hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/60"
                                    title="Перевозка не началась, заказ уже не «Новый». Доступно руководителю и администратору."
                                    @click="emit('mark-disruption')"
                                >
                                    <OctagonAlert class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                    Срыв
                                </button>
                            </div>
                            <span class="h-4 w-px shrink-0 bg-zinc-200 dark:bg-zinc-600" aria-hidden="true" />
                            <div class="flex items-center gap-1.5">
                                <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Перевозка</span>
                                <div :class="crmSegmented">
                                    <button
                                        type="button"
                                        :class="[!isInternationalTransport ? crmSegmentedBtnActive : crmSegmentedBtn, 'px-2.5 py-1 text-[11px]']"
                                        @click="setInternationalTransport(false)"
                                    >
                                        Внутренняя
                                    </button>
                                    <button
                                        type="button"
                                        :class="[isInternationalTransport ? crmSegmentedBtnActive : crmSegmentedBtn, 'px-2.5 py-1 text-[11px]']"
                                        @click="setInternationalTransport(true)"
                                    >
                                        Международная
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    :class="crmBtnCreate"
                    class="h-11 shrink-0"
                    :disabled="formProcessing || customerDebtBlocked || !isOrderFormEditable"
                    @click="emit('submit')"
                >
                    <Save class="h-4 w-4" />
                    {{ formProcessing ? '...' : 'Сохранить' }}
                </button>
            </div>

            <p v-if="hasUnsavedDocumentFiles" class="text-xs text-amber-800 dark:text-amber-200">
                В документах выбран новый файл — нажмите «Сохранить» выше, иначе вложение не попадёт в заказ.
            </p>
            <p v-if="coreValidationIssues.length > 0" class="text-xs text-rose-700 dark:text-rose-300">
                Для сохранения заполните: {{ coreValidationIssues.join(', ') }}.
            </p>

            <div class="space-y-2">
                <label class="text-xs font-medium uppercase tracking-wide text-zinc-500">Шаг</label>
                <select
                    :value="activeTab"
                    :class="crmFieldFluid"
                    @change="setActiveTab($event.target.value)"
                >
                    <option v-for="tab in tabs" :key="tab.key" :value="tab.key">{{ tab.label }}</option>
                </select>
            </div>
        </div>

        <template v-else>
            <div :class="crmWizardHeader">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        :class="crmWizardBack"
                        title="К реестру"
                        @click="emit('go-back')"
                    >
                        <X class="h-5 w-5" />
                        <span class="sr-only">К реестру</span>
                    </button>

                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                            {{ desktopSubtitle }}
                        </div>
                        <h1 class="mt-1 truncate text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                            {{ pageTitle }}
                        </h1>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <span
                        v-if="hasUnsavedDocumentFiles"
                        class="max-w-md text-right text-xs text-amber-800 dark:text-amber-200"
                    >
                        В документах есть новый файл — сохраните заказ.
                    </span>
                    <button
                        v-if="canUseHowMuchFits && isEditing"
                        type="button"
                        :class="`${crmBtnSecondary} inline-flex items-center gap-2 !px-4 !py-2`"
                        @click="emit('open-how-much-fits')"
                    >
                        <Package class="h-4 w-4" />
                        Сколько влезет?
                    </button>
                    <button
                        type="button"
                        :class="crmBtnCreate"
                        :disabled="formProcessing || customerDebtBlocked || !isOrderFormEditable"
                        @click="emit('submit')"
                    >
                        <Save class="h-4 w-4" />
                        {{ formProcessing ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-2 border-b border-zinc-200 bg-white px-5 py-2 dark:border-zinc-800 dark:bg-zinc-900 sm:flex-row sm:flex-nowrap sm:items-center sm:justify-between sm:gap-x-3 sm:gap-y-2">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="inline-flex items-center gap-2 text-sm transition-colors"
                        :class="crmTabButtonClasses(activeTab === tab.key)"
                        @click="setActiveTab(tab.key)"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        {{ tab.label }}
                    </button>
                </div>
                <div class="flex w-full min-w-0 flex-wrap items-center gap-x-4 gap-y-2 border-t border-zinc-200 pt-2.5 sm:w-auto sm:min-w-0 sm:flex-nowrap sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Перевозка</span>
                        <div :class="crmSegmented">
                            <button
                                type="button"
                                :class="!isInternationalTransport ? crmSegmentedBtnActive : crmSegmentedBtn"
                                @click="setInternationalTransport(false)"
                            >
                                Внутренняя
                            </button>
                            <button
                                type="button"
                                :class="isInternationalTransport ? crmSegmentedBtnActive : crmSegmentedBtn"
                                @click="setInternationalTransport(true)"
                            >
                                Международная
                            </button>
                        </div>
                    </div>
                    <span class="hidden h-6 w-px shrink-0 bg-zinc-200 sm:block dark:bg-zinc-600" aria-hidden="true" />
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Статус заказа</span>
                        <span
                            class="inline-flex max-w-full items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-medium leading-none text-zinc-800 dark:border-zinc-600 dark:bg-zinc-800/80 dark:text-zinc-100"
                            :title="'Рассчитывается автоматически по фактическим датам плеч, документам и оплатам. Текущий: ' + orderStatusBadgeLabel"
                        >
                            <OrderStatusIcon v-if="orderStatusIconMeta" :icon-key="orderStatusIconKey" />
                            <span class="min-w-0 truncate">{{ orderStatusBadgeLabel }}</span>
                        </span>
                        <button
                            v-if="canShowMarkDisruptionButton"
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold leading-none text-red-700 transition-colors hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/60"
                            title="Перевозка не началась, заказ уже не «Новый». Доступно руководителю и администратору."
                            @click="emit('mark-disruption')"
                        >
                            <OctagonAlert class="h-4 w-4 shrink-0" aria-hidden="true" />
                            Срыв
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div
            :class="crmWizardBody"
            :inert="wizardBodyInert"
        >
            <slot />
        </div>
    </div>
</template>

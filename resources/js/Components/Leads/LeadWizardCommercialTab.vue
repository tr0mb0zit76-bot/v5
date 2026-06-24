<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import PrintWorkflowDocList from '@/Components/Orders/PrintWorkflowDocList.vue';
import { crmFieldFluid, crmPageEyebrow } from '@/support/crmUi.js';

const selectedTemplateId = defineModel('selectedTemplateId', { type: String, default: '' });
const selectedHtmlTemplateId = defineModel('selectedHtmlTemplateId', { type: String, default: '' });

const props = defineProps({
    leadId: { type: [Number, null], default: null },
    offers: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    printFormTemplateOptions: { type: Array, default: () => [] },
    proposalHtmlTemplateOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['send-offer']);

const commercialPrintDocs = computed(() => (
    (props.offers ?? [])
        .filter((offer) => offer.generated_file_path)
        .map((offer) => ({
            id: offer.id,
            print_template_name: offer.print_template_name || offer.number || offer.title || 'Коммерческое предложение',
            draft_preview_url: route('leads.offers.draft', {
                lead: props.leadId,
                offer: offer.id,
                preview: 1,
                preview_mode: 'browser',
            }),
            draft_download_url: route('leads.offers.draft', {
                lead: props.leadId,
                offer: offer.id,
            }),
        }))
));

function templateOptionLabel(template) {
    if (template.contractor_name) {
        return `${template.name} • ${template.contractor_name}`;
    }

    if (template.is_default) {
        return `${template.name} • по умолчанию`;
    }

    return template.name;
}

function createCommercialInCard() {
    if (!props.leadId || !selectedTemplateId.value) {
        return;
    }

    router.post(route('leads.commercial.from-template', props.leadId), {
        print_form_template_id: Number(selectedTemplateId.value),
    }, { preserveScroll: true });
}

function previewHtmlProposal() {
    if (!props.leadId || !selectedHtmlTemplateId.value) {
        return;
    }

    const url = route('leads.proposal.html-preview', {
        lead: props.leadId,
        proposal_html_template_id: Number(selectedHtmlTemplateId.value),
    });

    window.open(url, '_blank', 'noopener,noreferrer');
}

function createHtmlCommercialInCard() {
    if (!props.leadId || !selectedHtmlTemplateId.value) {
        return;
    }

    router.post(route('leads.proposal.from-html-template', props.leadId), {
        proposal_html_template_id: Number(selectedHtmlTemplateId.value),
    }, { preserveScroll: true });
}

function formatMoney(value, currency = 'RUB') {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(Number(value));
}
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-[1.4fr,0.9fr]">
        <div class="space-y-4">
            <div>
                <h3 class="text-base font-semibold">Печатные формы</h3>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Каталог шаблонов — в разделе «Настройки → Шаблоны печати».
                    Выберите шаблон и нажмите «Создать в карточке» — черновик появится в списке ниже.
                </p>
            </div>

            <div class="space-y-3 rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/25">
                <div>
                    <div class="text-sm font-semibold text-emerald-950 dark:text-emerald-100">Коммерческое предложение</div>
                    <p class="mt-1 text-xs text-emerald-900/80 dark:text-emerald-200/80">
                        Шаблоны для лида. Черновик DOCX сохраняется в карточке и доступен для предпросмотра и скачивания.
                    </p>
                </div>

                <template v-if="!leadId">
                    <p class="text-xs text-emerald-900/80">Сохраните лид, чтобы создать печатную форму.</p>
                </template>
                <template v-else>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[200px] flex-1 space-y-1">
                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Шаблон</label>
                            <select v-model="selectedTemplateId" :class="crmFieldFluid">
                                <option value="">Выберите шаблон</option>
                                <option
                                    v-for="template in printFormTemplateOptions"
                                    :key="template.id"
                                    :value="String(template.id)"
                                >
                                    {{ templateOptionLabel(template) }}
                                </option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50 dark:bg-emerald-600"
                            :disabled="!selectedTemplateId"
                            @click="createCommercialInCard"
                        >
                            Создать в карточке
                        </button>
                    </div>

                    <PrintWorkflowDocList
                        :docs="commercialPrintDocs"
                        :is-editable="true"
                        :document-storage="{}"
                    />
                </template>
            </div>

            <div
                v-if="proposalHtmlTemplateOptions.length"
                class="space-y-3 rounded-2xl border border-sky-200/80 bg-sky-50/40 p-4 dark:border-sky-900/60 dark:bg-sky-950/25"
            >
                <div>
                    <div class="text-sm font-semibold text-sky-950 dark:text-sky-100">HTML-шаблон КП</div>
                    <p class="mt-1 text-xs text-sky-900/80 dark:text-sky-200/80">
                        Конструктор шаблонов — в «Модули → Шаблоны КП». Здесь: preview → PDF в карточке → отправка по e-mail.
                    </p>
                </div>

                <template v-if="!leadId">
                    <p class="text-xs text-sky-900/80">Сохраните лид, чтобы сформировать HTML-КП.</p>
                </template>
                <template v-else>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[200px] flex-1 space-y-1">
                            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">HTML-шаблон</label>
                            <select v-model="selectedHtmlTemplateId" :class="crmFieldFluid">
                                <option value="">Выберите шаблон</option>
                                <option
                                    v-for="template in proposalHtmlTemplateOptions"
                                    :key="template.id"
                                    :value="String(template.id)"
                                >
                                    {{ template.name }}
                                </option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl border border-sky-300 bg-white px-4 py-2 text-sm font-medium text-sky-900 hover:bg-sky-100 disabled:opacity-50 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100 dark:hover:bg-sky-900"
                            :disabled="!selectedHtmlTemplateId"
                            @click="previewHtmlProposal"
                        >
                            Preview
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-800 disabled:opacity-50 dark:bg-sky-600"
                            :disabled="!selectedHtmlTemplateId"
                            @click="createHtmlCommercialInCard"
                        >
                            Сохранить PDF в карточке
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <div class="space-y-3 border border-zinc-200 p-4 dark:border-zinc-800">
            <h3 class="text-base font-semibold">История КП и конверсии</h3>
            <div
                v-for="offer in offers"
                :key="offer.id"
                class="border border-zinc-200 p-3 text-sm dark:border-zinc-800"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="font-medium">{{ offer.number || 'Черновик КП' }}</div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ offer.sent_at ? 'отправлено' : offer.offer_date || '—' }}</span>
                </div>
                <div class="mt-2 text-zinc-500 dark:text-zinc-400">
                    {{ offer.price ? formatMoney(offer.price, offer.currency) : 'Без цены' }}
                </div>
                <button
                    v-if="!offer.sent_at && offer.generated_file_path"
                    type="button"
                    class="mt-3 inline-flex items-center rounded-xl border border-zinc-200 px-3 py-1.5 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    @click="emit('send-offer', offer)"
                >
                    Отправить по e-mail
                </button>
            </div>
            <div v-if="offers.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
                Коммерческие предложения ещё не формировались.
            </div>
            <div v-if="orders.length" class="border border-zinc-200 p-3 text-sm dark:border-zinc-800">
                <div :class="crmPageEyebrow">Создан заказ</div>
                <div class="mt-2 font-medium">{{ orders[0].order_number }}</div>
            </div>
        </div>
    </div>
</template>

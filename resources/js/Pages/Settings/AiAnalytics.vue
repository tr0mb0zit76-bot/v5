<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto lg:min-h-0">
        <CrmPageHeader
            lead="Обезличенные обращения к ассистенту в command bar, вызовы tools и распознавание заявок. Данные в MySQL CRM — отдельная БД не нужна."
            title="Аналитика AI"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <select
                        :value="days"
                        class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:border-zinc-50"
                        @change="changePeriod($event.target.value)"
                    >
                        <option v-for="option in periodOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <Link :href="salesBookUrl" :class="crmBtnSecondary">
                        Книга продаж
                    </Link>
                </div>
            </template>
        </CrmPageHeader>

        <div v-if="! insights.available" :class="`${crmPanel} border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200`">
            {{ insights.message ?? 'Аналитика недоступна.' }}
        </div>

        <template v-else>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div :class="`${crmPanel} p-4`">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Диалоги (command bar)</div>
                    <div class="mt-1 text-2xl font-semibold">{{ insights.conversation_turns_total }}</div>
                </div>
                <div :class="`${crmPanel} p-4`">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Слабые / неудачные</div>
                    <div class="mt-1 text-2xl font-semibold text-rose-600 dark:text-rose-400">{{ insights.weak_or_failed_turns }}</div>
                </div>
                <div :class="`${crmPanel} p-4`">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Intake заявок</div>
                    <div class="mt-1 text-2xl font-semibold">{{ insights.order_intake?.total ?? 0 }}</div>
                    <div class="mt-1 text-xs text-zinc-500">ошибок: {{ insights.order_intake?.failed ?? 0 }}</div>
                </div>
                <div :class="`${crmPanel} p-4`">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Период</div>
                    <div class="mt-1 text-lg font-semibold">{{ days }} дн.</div>
                </div>
            </div>

            <section v-if="insights.knowledge_gap_hints?.length" :class="`${crmPanel} space-y-2 p-4`">
                <h2 class="text-sm font-semibold">Рекомендации</h2>
                <ul class="list-disc space-y-1 pl-5 text-sm text-zinc-600 dark:text-zinc-400">
                    <li v-for="(hint, index) in insights.knowledge_gap_hints" :key="index">{{ hint }}</li>
                </ul>
            </section>

            <section v-if="insights.sales_book_knowledge_gaps?.length" :class="`${crmPanel} space-y-2 p-4`">
                <h2 class="text-sm font-semibold">Пробелы Книги продаж</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Вопросы, где ассистент не нашёл ответ в Книге (или не прочитал статью).
                </p>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="gap in insights.sales_book_knowledge_gaps"
                        :key="`gap-${gap.id}`"
                        class="flex items-start justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700"
                    >
                        <div class="min-w-0 flex-1">
                            <p>{{ gap.user_prompt || '—' }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ gap.gap_reason || gap.outcome }}</p>
                        </div>
                        <button
                            type="button"
                            :class="`${crmBtnSecondaryOutline} shrink-0 p-2 text-zinc-500 hover:text-rose-600 dark:hover:text-rose-400`"
                            title="Убрать из списка"
                            @click="dismissSalesBookGap(gap.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </li>
                </ul>
            </section>

            <section v-if="insights.command_bar_feedback" :class="`${crmPanel} space-y-2 p-4`">
                <h2 class="text-sm font-semibold">Оценки ответов ассистента</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Полезно: {{ insights.command_bar_feedback.helpful ?? 0 }} ·
                    Не помогло: {{ insights.command_bar_feedback.not_helpful ?? 0 }}
                </p>
            </section>

            <div class="grid min-h-0 grid-cols-1 gap-4 xl:grid-cols-2">
                <section :class="`${crmPanel} flex min-h-0 flex-col p-4`">
                    <h2 class="mb-3 text-sm font-semibold">Частые вопросы</h2>
                    <div class="min-h-0 flex-1 overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase text-zinc-500">
                                <tr>
                                    <th class="pb-2 pr-3">Вопрос (образец)</th>
                                    <th class="pb-2 pr-3">Раз</th>
                                    <th class="pb-2">Слабых</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                <tr v-for="(row, index) in insights.top_user_questions" :key="index">
                                    <td class="py-2 pr-3 align-top">{{ row.sample_prompt || '—' }}</td>
                                    <td class="py-2 pr-3 align-top whitespace-nowrap">{{ row.ask_count }}</td>
                                    <td class="py-2 align-top whitespace-nowrap">{{ row.weak_or_failed_count }}</td>
                                </tr>
                                <tr v-if="insights.top_user_questions.length === 0">
                                    <td colspan="3" class="py-4 text-zinc-500">Пока нет данных за период.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section :class="`${crmPanel} flex min-h-0 flex-col p-4`">
                    <h2 class="mb-3 text-sm font-semibold">Недавние слабые ответы</h2>
                    <div class="min-h-0 flex-1 space-y-3 overflow-auto">
                        <article
                            v-for="(row, index) in insights.recent_weak_or_failed"
                            :key="index"
                            class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-800"
                        >
                            <div class="mb-1 flex flex-wrap gap-2 text-xs text-zinc-500">
                                <span>{{ formatAt(row.at) }}</span>
                                <span>{{ row.outcome }}</span>
                            </div>
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ row.user_prompt || '—' }}</p>
                            <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ row.assistant_reply || '—' }}</p>
                        </article>
                        <p v-if="insights.recent_weak_or_failed.length === 0" class="text-sm text-zinc-500">
                            Нет проблемных диалогов за период.
                        </p>
                    </div>
                </section>
            </div>

            <section :class="`${crmPanel} p-4`">
                <h2 class="mb-3 text-sm font-semibold">Использование tools</h2>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-zinc-500">
                        <tr>
                            <th class="pb-2 pr-3">Tool</th>
                            <th class="pb-2 pr-3">Вызовов</th>
                            <th class="pb-2">Ошибок</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        <tr v-for="row in insights.tool_usage" :key="row.tool">
                            <td class="py-2 pr-3 font-mono text-xs">{{ row.tool }}</td>
                            <td class="py-2 pr-3">{{ row.total }}</td>
                            <td class="py-2">{{ row.error_count }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </template>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmBtnSecondary, crmBtnSecondaryOutline, crmPanel } from '@/support/crmUi.js';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'reports', activeSubKey: 'ai-analytics' }, () => page),
});

const props = defineProps({
    days: { type: Number, default: 30 },
    insights: { type: Object, required: true },
    periodOptions: { type: Array, default: () => [] },
    salesBookUrl: { type: String, required: true },
});

function changePeriod(value) {
    router.get(route('settings.ai-analytics'), { days: Number(value) }, { preserveState: true, preserveScroll: true });
}

function dismissSalesBookGap(eventId) {
    if (! window.confirm('Убрать этот запрос из списка пробелов Книги продаж?')) {
        return;
    }

    router.delete(route('settings.ai-analytics.sales-book-gaps.dismiss', eventId), {
        data: { days: props.days },
        preserveScroll: true,
    });
}

function formatAt(value) {
    if (! value) {
        return '';
    }

    try {
        return new Date(value).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return value;
    }
}
</script>

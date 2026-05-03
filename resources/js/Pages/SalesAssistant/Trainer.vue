<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto lg:min-h-0">
        <section class="border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500 dark:text-zinc-400">Помощник продаж</div>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Тренажер переговоров</h1>
            <p class="mt-2 max-w-3xl text-sm text-zinc-500 dark:text-zinc-400">
                Сначала выберите карточку клиента. Контекст карточки будет передан в сценарий как роль покупателя и условия диалога.
            </p>
            <p class="mt-2 text-sm">
                <Link
                    :href="route('sales-assistant.trainer.analytics')"
                    class="font-medium text-zinc-800 underline-offset-4 hover:underline dark:text-zinc-200"
                >
                    Аналитика тренажёра
                </Link>
            </p>
            <p
                v-if="page.props.flash?.message"
                class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ page.props.flash.message }}
            </p>
            <p v-if="page.props.can_manage_sales_scripts" class="mt-4">
                <Link
                    :href="route('scripts.editor.index')"
                    class="text-sm font-medium text-zinc-800 underline-offset-4 hover:underline dark:text-zinc-200"
                >
                    Редактор сценариев
                </Link>
                <span class="mx-2 text-zinc-300 dark:text-zinc-600">·</span>
                <Link
                    :href="route('scripts.index')"
                    class="text-sm font-medium text-zinc-800 underline-offset-4 hover:underline dark:text-zinc-200"
                >
                    Скрипты (живая сессия)
                </Link>
            </p>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Сессии (30д)</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ trainerSummary.total_sessions }}</div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Завершено</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ trainerSummary.completed_sessions }}</div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Средний score</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ trainerSummary.avg_score }}</div>
            </article>
            <article class="border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Успех / КП</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ trainerSummary.won_sessions }} / {{ trainerSummary.quote_sessions }}
                </div>
            </article>
        </section>

        <section>
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">1. Выберите профиль покупателя</h2>
            <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <button
                    v-for="profile in customerProfiles"
                    :key="profile.key"
                    type="button"
                    class="rounded-2xl border p-5 text-left shadow-sm transition"
                    :class="selectedProfile?.key === profile.key
                        ? 'border-emerald-400 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/40'
                        : 'border-zinc-200 bg-white hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-950 dark:hover:border-zinc-700'"
                    @click="selectedProfile = profile"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ profile.title }}</h3>
                        <span class="rounded-full border border-zinc-200 px-2 py-0.5 text-[11px] text-zinc-500 dark:border-zinc-700 dark:text-zinc-300">
                            {{ profile.segment }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ profile.summary }}</p>
                    <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="font-medium">Цель:</span> {{ profile.goal }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="font-medium">Возражение:</span> {{ profile.objection }}
                    </p>
                </button>
            </div>
        </section>

        <section>
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">2. Выберите роли</h2>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <label
                    v-for="mode in trainingRoleModes"
                    :key="mode.value"
                    class="cursor-pointer rounded-2xl border p-4 transition"
                    :class="selectedTrainingRoleMode === mode.value
                        ? 'border-sky-500 bg-sky-50 text-sky-950 dark:border-sky-400 dark:bg-sky-950/30 dark:text-sky-100'
                        : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-900'"
                >
                    <span class="flex items-start gap-3">
                        <input
                            v-model="selectedTrainingRoleMode"
                            :value="mode.value"
                            type="radio"
                            class="mt-1 shrink-0 border-zinc-300"
                        />
                        <span>
                            <span class="block text-sm font-semibold">{{ mode.label }}</span>
                            <span class="mt-1 block text-xs leading-5 opacity-80">{{ mode.description }}</span>
                        </span>
                    </span>
                </label>
            </div>
        </section>

        <section>
            <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">3. Запустите сценарий</h2>
            <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="script in scripts"
                    :key="script.id"
                    class="flex flex-col border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
                >
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ script.title }}</h2>
                    <p v-if="script.description" class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ script.description }}</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <span v-if="script.channel" class="rounded-full border border-zinc-200 px-2 py-0.5 dark:border-zinc-700">{{ script.channel }}</span>
                        <span
                            v-for="tag in script.tags || []"
                            :key="tag"
                            class="rounded-full border border-zinc-200 px-2 py-0.5 dark:border-zinc-700"
                        >
                            {{ tag }}
                        </span>
                    </div>
                    <div class="mt-4 flex flex-1 flex-col justify-end">
                        <button
                            v-if="script.active_version"
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-sky-800 bg-sky-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50 dark:border-sky-500 dark:bg-sky-600 dark:hover:bg-sky-500"
                            :disabled="selectedProfile === null"
                            @click="startTraining(script.active_version.id)"
                        >
                            {{ selectedProfile ? 'Начать тренировку' : 'Сначала выберите клиента' }}
                        </button>
                        <p v-else class="text-sm text-amber-700 dark:text-amber-300">Нет опубликованной версии сценария.</p>
                    </div>
                </article>
            </div>
        </section>

        <p v-if="scripts.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
            Сценарии пока не добавлены или не опубликованы. Обратитесь к администратору или откройте
            <Link :href="route('scripts.index')" class="font-medium underline-offset-4 hover:underline">«Скрипты»</Link>
            для проверки.
        </p>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineOptions({
    layout: (h, page) => h(CrmLayout, { activeKey: 'sales-assistant', activeSubKey: 'sales-assistant-trainer' }, () => page),
});

const props = defineProps({
    scripts: {
        type: Array,
        default: () => [],
    },
    trainerSummary: {
        type: Object,
        default: () => ({
            total_sessions: 0,
            completed_sessions: 0,
            avg_score: 0,
            won_sessions: 0,
            quote_sessions: 0,
        }),
    },
});

const page = usePage();
const selectedProfile = ref(null);
const selectedTrainingRoleMode = ref('manager_seller');
const trainerSummary = props.trainerSummary;

const trainingRoleModes = [
    {
        value: 'manager_seller',
        label: 'Я продавец, ассистент — покупатель',
        description: 'Классический режим: вы ведёте продажу, DeepSeek отвечает в роли выбранного покупателя.',
    },
    {
        value: 'manager_buyer',
        label: 'Я покупатель, ассистент — продавец',
        description: 'Режим наоборот: вы отвечаете как выбранный покупатель, DeepSeek тренирует вас примером продавца.',
    },
];

const customerProfiles = [
    {
        key: 'price-sensitive-owner',
        title: 'ИП, чувствителен к цене',
        segment: 'Малый бизнес',
        summary: 'Ищет перевозки точечно, сравнивает каждую ставку с конкурентами.',
        goal: 'Выбрать надежного партнера без переплат.',
        objection: 'У вас дороже, чем у текущего подрядчика.',
        context:
            'Роль: покупатель, владелец малого бизнеса. Фокус на цене и рисках срыва. Согласится только при аргументах про выгоду и надежность.',
    },
    {
        key: 'operations-manager-urgent',
        title: 'Операционный менеджер, срочная отгрузка',
        segment: 'Средний бизнес',
        summary: 'Нужен быстрый запуск перевозки, мало времени на обсуждения.',
        goal: 'Снять риск срыва отгрузки в ближайшие 24 часа.',
        objection: 'Сейчас нет времени на долгие согласования.',
        context:
            'Роль: менеджер по логистике. Критичны сроки, прозрачность статусов и наличие резервного плана. Эмоционально напряжен.',
    },
    {
        key: 'procurement-formal',
        title: 'Закупщик с формальными требованиями',
        segment: 'Крупный бизнес',
        summary: 'Просит документы, KPI, SLA и четкое соблюдение регламентов.',
        goal: 'Провести квалификацию поставщика и снизить операционные риски.',
        objection: 'Сначала докажите соответствие нашим требованиям.',
        context:
            'Роль: специалист по закупкам. Требует факты, кейсы, KPI, договорные гарантии. Не принимает эмоциональные аргументы.',
    },
];

function startTraining(versionId) {
    if (!selectedProfile.value) {
        return;
    }

    router.post(route('scripts.sessions.store'), {
        sales_script_version_id: versionId,
        return_to: 'trainer',
        trainer_profile_key: selectedProfile.value.key,
        trainer_profile_title: selectedProfile.value.title,
        trainer_profile_context: selectedProfile.value.context,
        training_role_mode: selectedTrainingRoleMode.value,
    });
}
</script>

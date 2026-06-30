<template>
    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto pb-28 md:pb-8 lg:min-h-0">
        <CrmPageHeader
            lead="Три шага: ваша роль → профиль покупателя → сценарий. Справа на каждом шаге — контекст и подсказки."
            title="Тренажер переговоров"
        />
        <section :class="`${crmPanel} space-y-3 p-6`">
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
            <article :class="`${crmStatCard} p-4`">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Сессии (30д)</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ trainerSummary.total_sessions }}</div>
            </article>
            <article :class="`${crmStatCard} p-4`">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Завершено</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ trainerSummary.completed_sessions }}</div>
            </article>
            <article :class="`${crmStatCard} p-4`">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Средний score</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ trainerSummary.avg_score }}</div>
            </article>
            <article :class="`${crmStatCard} p-4`">
                <div class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Успех / КП</div>
                <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ trainerSummary.won_sessions }} / {{ trainerSummary.quote_sessions }}
                </div>
            </article>
        </section>

        <section class="space-y-3">
            <div :class="`${crmPanel} p-4`">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                        Прогресс настройки
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ currentStep }}/3</div>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-sky-600 transition-all duration-500" :style="{ width: `${(currentStep / 3) * 100}%` }" />
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
            <article :class="`${crmPanel} p-4 transition-all duration-300`">
                <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">1. Роль пользователя</h2>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Сначала выберите, кого играете вы, а кого — ассистент.</p>
                <div class="mt-3 space-y-2">
                    <button
                        v-for="mode in trainingRoleModes"
                        :key="mode.value"
                        type="button"
                        class="w-full rounded-xl border px-3 py-2 text-left text-sm transition"
                        :class="selectedTrainingRoleMode === mode.value
                            ? 'border-sky-500 bg-sky-50 text-sky-950 dark:border-sky-400 dark:bg-sky-950/30 dark:text-sky-100'
                            : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-900'"
                        @click="selectedTrainingRoleMode = mode.value"
                    >
                        {{ mode.label }}
                    </button>
                </div>
                <div class="mt-4 rounded-xl border border-zinc-200 p-3 text-xs leading-5 text-zinc-600 dark:border-zinc-800 dark:text-zinc-300">
                    {{ selectedRoleMode?.description ?? 'Выберите роль слева.' }}
                </div>
            </article>

            <article ref="profileStepRef" :class="[`${crmPanel} p-4 transition-all duration-300`, { 'opacity-60': !selectedTrainingRoleMode, 'translate-y-1': !selectedTrainingRoleMode }]" >
                <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">2. Профиль покупателя</h2>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">После роли выберите, с каким типом собеседника тренируемся.</p>
                <div class="mt-3 max-h-64 space-y-2 overflow-auto pr-1">
                    <button
                        v-for="profile in customerProfiles"
                        :key="profile.key"
                        type="button"
                        class="w-full rounded-xl border px-3 py-2 text-left text-sm transition"
                        :disabled="!selectedTrainingRoleMode"
                        :class="selectedProfile?.key === profile.key
                            ? 'border-emerald-400 bg-emerald-50 text-emerald-950 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100'
                            : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-900'"
                        @click="selectedProfile = profile"
                    >
                        <div class="font-medium">{{ profile.title }}</div>
                        <div class="text-xs opacity-80">{{ profile.segment }}</div>
                    </button>
                </div>
                <div class="mt-4 rounded-xl border border-zinc-200 p-3 text-xs leading-5 text-zinc-600 dark:border-zinc-800 dark:text-zinc-300">
                    <template v-if="selectedProfile">
                        <p><span class="font-semibold">Суть:</span> {{ selectedProfile.summary }}</p>
                        <p class="mt-1"><span class="font-semibold">Цель:</span> {{ selectedProfile.goal }}</p>
                        <p class="mt-1"><span class="font-semibold">Возражение:</span> {{ selectedProfile.objection }}</p>
                    </template>
                    <template v-else>
                        Выберите профиль слева.
                    </template>
                </div>
            </article>

            <article ref="scenarioStepRef" :class="[`${crmPanel} p-4 transition-all duration-300`, { 'opacity-60': !selectedProfile, 'translate-y-1': !selectedProfile }]" >
                <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:text-zinc-400">3. Сценарий</h2>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Финальный шаг — выбрать сценарий и запустить тренировку.</p>
                <div class="mt-3 max-h-64 space-y-2 overflow-auto pr-1">
                    <button
                        v-for="script in scripts"
                        :key="script.id"
                        type="button"
                        class="w-full rounded-xl border px-3 py-2 text-left text-sm transition"
                        :disabled="!selectedProfile"
                        :class="selectedScriptId === script.id
                            ? 'border-sky-500 bg-sky-50 text-sky-950 dark:border-sky-400 dark:bg-sky-950/30 dark:text-sky-100'
                            : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-900'"
                        @click="selectedScriptId = script.id"
                    >
                        {{ script.title }}
                    </button>
                </div>
                <div class="mt-4 rounded-xl border border-zinc-200 p-3 text-xs leading-5 text-zinc-600 dark:border-zinc-800 dark:text-zinc-300">
                    <template v-if="selectedScript">
                        <p class="font-semibold text-zinc-800 dark:text-zinc-100">{{ selectedScript.title }}</p>
                        <p v-if="selectedScript.description" class="mt-1">{{ selectedScript.description }}</p>
                        <p class="mt-2"><span class="font-semibold">Канал:</span> {{ selectedScript.channel || '—' }}</p>
                        <p class="mt-1">
                            <span class="font-semibold">Теги:</span>
                            {{ (selectedScript.tags || []).length ? selectedScript.tags.join(', ') : '—' }}
                        </p>
                    </template>
                    <template v-else>
                        Выберите сценарий слева.
                    </template>
                </div>
                <button
                    type="button"
                    class="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-sky-800 bg-sky-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50 dark:border-sky-500 dark:bg-sky-600 dark:hover:bg-sky-500"
                    :disabled="!selectedScript?.active_version || !selectedProfile || !selectedTrainingRoleMode"
                    @click="startTraining(selectedScript?.active_version?.id)"
                >
                    {{ !selectedTrainingRoleMode ? 'Выберите роль'
                        : !selectedProfile ? 'Выберите профиль'
                            : !selectedScript ? 'Выберите сценарий'
                                : !selectedScript.active_version ? 'Нет активной версии'
                                    : 'Начать тренировку' }}
                </button>
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
import { computed, nextTick, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CrmPageHeader from '@/Components/Crm/CrmPageHeader.vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { crmPanel, crmStatCard } from '@/support/crmUi.js';

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
const selectedTrainingRoleMode = ref(null);
const selectedScriptId = ref(null);
const profileStepRef = ref(null);
const scenarioStepRef = ref(null);
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
        summary: 'Ищет перевозки точечно, сравнивает каждую ставку с конкурентами и быстро устает от общих обещаний.',
        goal: 'Понять, за что платит, и не переплатить за лишний сервис.',
        objection: 'У вас дороже, чем у текущего подрядчика.',
        context:
            'Ты играешь покупателя: владелец малого бизнеса, груз до 3 т, маршрут внутри страны. Говоришь коротко, иногда едко. Главный триггер — цена и «у конкурента дешевле». Если менеджер презентует компанию общими словами, возвращай его к ставке. Согласишься продолжать только если он объяснит состав ставки (страхование, срок подачи, ответственность за срыв), спросит критерии сравнения и предложит понятный следующий шаг. Можешь спросить про документы и отсрочку.',
    },
    {
        key: 'operations-manager-urgent',
        title: 'Операционный менеджер, срочная отгрузка',
        segment: 'Средний бизнес',
        summary: 'Нужен быстрый запуск перевозки, мало времени на обсуждения и высокая цена ошибки.',
        goal: 'Понять, кто отвечает за подачу машины, статусы и запасной вариант в ближайшие 24 часа.',
        objection: 'Сейчас нет времени на долгие согласования.',
        context:
            'Ты — менеджер по логистике на складе: пик сезона, слот на погрузку завтра. Нужны конкретика по машине, водителю, статусам в пути и запасной вариант если сорвётся. Не любишь общие слова; требуешь SLA и контакт диспетчера 24/7. Если менеджер уходит в презентацию, перебивай: «мне нужен план на завтра». Согласишься только на конкретный порядок действий и время следующего апдейта.',
    },
    {
        key: 'procurement-formal',
        title: 'Закупщик с формальными требованиями',
        segment: 'Крупный бизнес',
        summary: 'Просит документы, KPI, SLA и соблюдение регламента вместо устных обещаний.',
        goal: 'Проверить поставщика по чек-листу и не пропустить операционные риски.',
        objection: 'Сначала докажите соответствие нашим требованиям.',
        context:
            'Ты — специалист по закупкам: говоришь сухо, по пунктам. Интересуют лицензии, опыт на аналогичных грузах, процедура претензий, штрафы за простой, интеграция статусов в ERP. На «мы надёжные» реагируешь запросом фактов и кейсов. Согласишься двигаться дальше только если менеджер признаёт регламент, собирает чек-лист и фиксирует дедлайн/канал отправки документов.',
    },
    {
        key: 'lpr-skeptic',
        title: 'ЛПР: скептик к смене перевозчика',
        segment: 'Средний / крупный бизнес',
        summary: 'Давно работает с текущим подрядчиком, не видит смысла менять и не хочет ломать рабочий процесс.',
        goal: 'Понять, есть ли измеримый выигрыш без риска для текущей схемы.',
        objection: 'Нас всё устраивает, зачем нам риск?',
        context:
            'Ты — директор по операциям или коммерческий директор. Спокойный тон, но жёсткая рамка: «что изменится для моих метрик через месяц?». Если менеджер предлагает заменить текущего перевозчика сразу, сопротивляйся. Просишь сравнение рисков, а не только цену. Готов выслушать пилот на одном маршруте, если менеджер не обещает «волшебство», фиксирует KPI и не атакует действующего подрядчика.',
    },
    {
        key: 'switching-carrier-angry',
        title: 'Клиент после срыва у другого перевозчика',
        segment: 'Эмоциональный вход',
        summary: 'Недавно обожглись на срыве, ищут исполнителя без новых сюрпризов и пустых обещаний.',
        goal: 'Получить фактический план Б, регламент статусов и ответственность за срыв.',
        objection: 'Мы уже не верим обещаниям, только письменно и со штрафами.',
        context:
            'Ты — руководитель логистики, настроен настороженно и устало. Ссылаешься на срыв срока, простой, штрафы от грузополучателя. Требуешь чёткие гарантии, резервный транспорт, понятный регламент претензий. Если менеджер говорит шаблонами, перебивай и требуй факты. Становишься конструктивнее только после признания риска, плана Б, ответственного и времени следующего апдейта.',
    },
    {
        key: 'new-direction-greenfield',
        title: 'Новое направление / первый опыт маршрута',
        segment: 'Развитие',
        summary: 'Открывают новый коридор, мало опыта, много вопросов и страх ошибиться в процессе.',
        goal: 'Получить дорожную карту, список рисков и понятный расчёт без давления.',
        objection: 'Мы первый раз везём туда — расскажите по шагам.',
        context:
            'Ты — менеджер, который честно не знает деталей таможни / режима точек / типа крепления. Задаёшь много уточняющих вопросов, иногда наивных. Ценишь спокойное объяснение без снобизма. Если менеджер давит на сделку, проси сначала карту шагов. Готов двигаться дальше, если получил ясную дорожную карту, список данных для расчёта и сроки.',
    },
    {
        key: 'hard-price-negotiator',
        title: 'Жёсткий переговорщик по цене',
        segment: 'Закупка / собственник',
        summary: 'Сравнивает ставки, требует скидку и проверяет, отдаст ли менеджер маржу без встречных условий.',
        goal: 'Получить лучшую ставку, но сохранить контроль по срокам и документам.',
        objection: 'Дайте дешевле, иначе пойдём к конкуренту.',
        context:
            'Ты — опытный закупщик или собственник: давишь на цену, ссылаешься на конкретное КП конкурента и не раскрываешь весь бюджет сразу. Если менеджер просто даёт скидку, продолжай давить. Согласишься двигаться дальше только если он сравнит одинаковые условия, спросит встречное обязательство (объём, предоплата, регулярность, SLA) и предложит 2 варианта, а не одну уступку.',
    },
    {
        key: 'service-recovery-angry',
        title: 'Клиент с претензией по рейсу',
        segment: 'Удержание',
        summary: 'Раздражён задержкой, документами или простоем; ждёт факты, план и время следующего апдейта.',
        goal: 'Понять, кто отвечает за решение проблемы и что изменится на следующем рейсе.',
        objection: 'Вы уже подвели, почему мы должны продолжать работать?',
        context:
            'Ты — клиент после проблемного рейса: говоришь эмоционально, требуешь конкретики, можешь перебивать и просить компенсацию. Не принимаешь общие извинения и фразу «разберёмся». Становишься спокойнее только если менеджер признаёт проблему, собирает факты, называет ответственного, время следующего апдейта и план предотвращения повтора.',
    },
    {
        key: 'existing-client-growth',
        title: 'Действующий клиент для расширения',
        segment: 'Повторная продажа',
        summary: 'Уже работает с компанией, но не отдаёт больше объёма без понятной выгоды, процесса и критериев пилота.',
        goal: 'Проверить, стоит ли расширять объём или отдавать критичные рейсы без риска для текущей схемы.',
        objection: 'Текущего объёма достаточно, зачем расширять?',
        context:
            'Ты — действующий клиент: в целом доволен, но осторожен с расширением. Если менеджер просит больше объёма без причины, отвечай: «текущего достаточно». Готов обсуждать новый маршрут, регулярный объём или собственный парк, если менеджер говорит не общими преимуществами, а про конкретную точку боли, KPI пилота, ответственных, условия оплаты и дату ревью.',
    },
];

const selectedRoleMode = computed(() => (
    trainingRoleModes.find((mode) => mode.value === selectedTrainingRoleMode.value) ?? null
));

const selectedScript = computed(() => (
    props.scripts.find((script) => script.id === selectedScriptId.value) ?? null
));

const currentStep = computed(() => {
    if (!selectedTrainingRoleMode.value) {
        return 0;
    }
    if (!selectedProfile.value) {
        return 1;
    }
    if (!selectedScript.value) {
        return 2;
    }

    return 3;
});

watch(selectedTrainingRoleMode, async (value, previous) => {
    if (value && !previous) {
        await nextTick();
        profileStepRef.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

watch(selectedProfile, async (value, previous) => {
    if (value && !previous) {
        await nextTick();
        scenarioStepRef.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

function startTraining(versionId) {
    if (!selectedProfile.value || !selectedTrainingRoleMode.value || !versionId) {
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

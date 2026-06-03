<template>
    <section class="rounded-xl border border-zinc-200 bg-zinc-50/80 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Тест</h3>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Выберите по одному варианту на каждый вопрос, затем нажмите «Проверить».
                </p>
            </div>
            <div
                v-if="submitted"
                class="rounded-lg px-3 py-2 text-sm font-semibold"
                :class="scoreBadgeClass"
            >
                {{ displayedScore }} из {{ totalQuestions }}
            </div>
        </div>

        <div class="mt-4 space-y-4">
            <article
                v-for="(question, index) in quiz.questions"
                :key="question.id"
                class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950"
            >
                <h4 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    {{ index + 1 }}. {{ question.text }}
                </h4>

                <div class="mt-3 space-y-2">
                    <label
                        v-for="option in question.options"
                        :key="`${question.id}-${option.id}`"
                        class="flex cursor-pointer items-start gap-3 rounded-lg border px-3 py-2 text-sm transition-colors"
                        :class="optionClass(question, option.id)"
                    >
                        <input
                            v-model="answers[question.id]"
                            type="radio"
                            :name="`sales-book-quiz-${question.id}`"
                            :value="option.id"
                            class="mt-0.5 shrink-0"
                            :disabled="submitted || recording"
                        />
                        <span class="text-zinc-700 dark:text-zinc-200">{{ option.text }}</span>
                    </label>
                </div>

                <p
                    v-if="submitted && question.explanation"
                    class="mt-3 rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300"
                >
                    {{ question.explanation }}
                </p>
            </article>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button
                type="button"
                :class="crmBtnPrimary"
                :disabled="recording"
                @click="submitted ? resetQuiz() : submitQuiz()"
            >
                {{ recording ? 'Сохранение…' : (submitted ? 'Пройти заново' : 'Проверить') }}
            </button>
            <p v-if="validationMessage" class="text-xs text-amber-700 dark:text-amber-200">
                {{ validationMessage }}
            </p>
            <p v-else-if="recordError" class="text-xs text-rose-700 dark:text-rose-200">
                {{ recordError }}
            </p>
            <p v-else-if="submitted && resultMessage" class="text-xs text-zinc-600 dark:text-zinc-300">
                {{ resultMessage }}
            </p>
        </div>
    </section>
</template>

<script setup>
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { crmBtnPrimary } from '@/support/crmUi.js';

const props = defineProps({
    quiz: {
        type: Object,
        required: true,
    },
    articleId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['attempt-recorded']);

const answers = reactive({});
const submitted = ref(false);
const recording = ref(false);
const validationMessage = ref('');
const recordError = ref('');
const serverAttempt = ref(null);

const totalQuestions = computed(() => props.quiz?.questions?.length ?? 0);

const clientScore = computed(() => {
    if (!submitted.value) {
        return 0;
    }

    return (props.quiz?.questions ?? []).reduce((total, question) => (
        answers[question.id] === question.correct ? total + 1 : total
    ), 0);
});

const displayedScore = computed(() => {
    if (serverAttempt.value?.score !== undefined && serverAttempt.value?.score !== null) {
        return serverAttempt.value.score;
    }

    return clientScore.value;
});

const unansweredCount = computed(() => (
    (props.quiz?.questions ?? []).filter((question) => !answers[question.id]).length
));

const scoreBadgeClass = computed(() => {
    const ratio = totalQuestions.value > 0 ? displayedScore.value / totalQuestions.value : 0;

    if (ratio >= 0.83) {
        return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-100';
    }

    if (ratio >= 0.58) {
        return 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100';
    }

    return 'bg-rose-100 text-rose-900 dark:bg-rose-950/50 dark:text-rose-100';
});

const resultMessage = computed(() => {
    if (!submitted.value) {
        return '';
    }

    const currentScore = displayedScore.value;

    if (currentScore >= 10) {
        return 'Уверенный результат: можно опираться на эти подходы в реальных разговорах.';
    }

    if (currentScore >= 7) {
        return 'База есть, но стоит повторить темы, где были ошибки.';
    }

    return 'Лучше заново пройти материалы по возражениям, закрытию, сложным клиентам и конкурентному анализу.';
});

watch(
    () => props.articleId,
    () => {
        resetQuiz();
    },
);

function optionClass(question, optionId) {
    if (!submitted.value) {
        return answers[question.id] === optionId
            ? 'border-sky-300 bg-sky-50 dark:border-sky-800 dark:bg-sky-950/30'
            : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600';
    }

    if (optionId === question.correct) {
        return 'border-emerald-300 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30';
    }

    if (answers[question.id] === optionId) {
        return 'border-rose-300 bg-rose-50 dark:border-rose-900 dark:bg-rose-950/30';
    }

    return 'border-zinc-200 opacity-70 dark:border-zinc-700';
}

async function submitQuiz() {
    validationMessage.value = '';
    recordError.value = '';

    if (unansweredCount.value > 0) {
        validationMessage.value = `Ответьте на все вопросы. Осталось: ${unansweredCount.value}.`;

        return;
    }

    recording.value = true;

    try {
        const response = await axios.post(
            route('sales-assistant.book.articles.quiz-attempt', props.articleId),
            { answers: { ...answers } },
        );

        serverAttempt.value = response.data?.attempt ?? null;
        submitted.value = true;
        emit('attempt-recorded');
    } catch (error) {
        const errors = error.response?.data?.errors ?? {};
        recordError.value = errors.answers?.[0]
            ?? error.response?.data?.message
            ?? 'Не удалось сохранить результат теста.';
    } finally {
        recording.value = false;
    }
}

function resetQuiz() {
    Object.keys(answers).forEach((key) => {
        delete answers[key];
    });
    submitted.value = false;
    validationMessage.value = '';
    recordError.value = '';
    serverAttempt.value = null;
}
</script>

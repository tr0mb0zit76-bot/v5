<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="lead-voice-intake-title"
            @click.self="close"
        >
            <div class="w-full max-w-lg rounded-xl border border-zinc-200 bg-white p-4 shadow-xl dark:border-zinc-700 dark:bg-zinc-950">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2
                            id="lead-voice-intake-title"
                            class="text-base font-semibold text-zinc-900 dark:text-zinc-50"
                        >
                            Лид голосом
                        </h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Надиктуйте после звонка: маршрут, груз, контакты. Текст можно поправить перед созданием.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        aria-label="Закрыть"
                        @click="close"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        :class="listening ? crmBtnPrimary : crmBtnSecondaryOutline"
                        :disabled="!speechSupported || submitting"
                        @click="toggleListening"
                    >
                        <Mic class="h-4 w-4" />
                        {{ listening ? 'Стоп' : 'Говорить' }}
                    </button>
                    <span
                        v-if="!speechSupported"
                        class="text-xs text-amber-700 dark:text-amber-300"
                    >
                        Распознавание речи недоступно в этом браузере — вставьте текст вручную (Chrome/Edge).
                    </span>
                    <span
                        v-else-if="listening"
                        class="text-xs font-medium text-sky-700 dark:text-sky-300"
                    >
                        Слушаю…
                    </span>
                </div>

                <textarea
                    v-model="text"
                    rows="8"
                    class="mt-3 w-full resize-y rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-sky-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                    placeholder="Например: клиент из Казани, нужно тентованная фура в Москву на пятницу, груз паллеты 8 тонн, контакт Иван плюс семь девятьсот…"
                    @input="onManualEdit"
                />

                <p
                    v-if="error"
                    class="mt-2 text-xs text-rose-600 dark:text-rose-400"
                >
                    {{ error }}
                </p>

                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        :class="crmBtnSecondaryOutline"
                        :disabled="submitting"
                        @click="close"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        :class="crmBtnPrimary"
                        :disabled="submitting || text.trim().length < 10"
                        @click="submit"
                    >
                        {{ submitting ? 'Создаём…' : 'Создать лид' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { Mic, X } from 'lucide-vue-next';
import {
    createSpeechToTextSession,
    isSpeechToTextSupported,
    mergeSpeechTranscript,
} from '@/support/speechToTextSession.js';
import { crmBtnPrimary, crmBtnSecondaryOutline } from '@/support/crmUi.js';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'created']);

const text = ref('');
const baseText = ref('');
const error = ref('');
const submitting = ref(false);
const listening = ref(false);
const speechSupported = isSpeechToTextSupported();

let session = null;

watch(() => props.show, (open) => {
    if (!open) {
        stopListening();
        text.value = '';
        baseText.value = '';
        error.value = '';
        submitting.value = false;
    }
});

onBeforeUnmount(() => {
    stopListening();
});

function onManualEdit() {
    baseText.value = text.value;
}

function toggleListening() {
    if (listening.value) {
        stopListening();

        return;
    }

    startListening();
}

function startListening() {
    error.value = '';
    stopListening();

    session = createSpeechToTextSession({
        lang: 'ru-RU',
        onResult({ transcript, isFinal }) {
            const merged = mergeSpeechTranscript(baseText.value, transcript, isFinal);
            text.value = merged.displayText;
            baseText.value = merged.nextBaseText;
        },
        onError(message) {
            error.value = message;
            listening.value = false;
        },
        onEnd() {
            listening.value = false;
        },
    });

    if (!session) {
        error.value = 'Распознавание речи недоступно в этом браузере.';

        return;
    }

    try {
        session.start();
        listening.value = true;
        baseText.value = text.value;
    } catch {
        error.value = 'Не удалось запустить микрофон.';
        listening.value = false;
    }
}

function stopListening() {
    session?.stop();
    session = null;
    listening.value = false;
    baseText.value = text.value;
}

function close() {
    stopListening();
    emit('close');
}

async function submit() {
    const message = text.value.trim();

    if (message.length < 10) {
        error.value = 'Нужно хотя бы короткое описание (от 10 символов).';

        return;
    }

    stopListening();
    submitting.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(route('mobile.shell.leads.from-text'), {
            message,
        }, {
            headers: { Accept: 'application/json' },
        });

        emit('created', data);
        close();
    } catch (exception) {
        error.value = exception.response?.data?.message
            ?? exception.response?.data?.errors?.message?.[0]
            ?? 'Не удалось создать лид из текста.';
    } finally {
        submitting.value = false;
    }
}
</script>

/**
 * Browser Web Speech API — temporary STT provider.
 * Consumers must use speechToTextSession.js (façade), not this file directly,
 * so local Whisper (GPU) can replace the provider without UI rewrites.
 */

/**
 * @returns {typeof window.SpeechRecognition | null}
 */
export function getSpeechRecognitionConstructor() {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.SpeechRecognition || window.webkitSpeechRecognition || null;
}

export function isBrowserSpeechRecognitionSupported() {
    return getSpeechRecognitionConstructor() !== null;
}

/**
 * Merge interim/final transcript into the textarea value.
 *
 * @param {string} baseText Text-only text before the current utterance
 * @param {string} transcript  current recognition chunk
 * @param {boolean} isFinal
 * @returns {{ displayText: string, nextBaseText: string }}
 */
export function mergeSpeechTranscript(baseText, transcript, isFinal) {
    const base = String(baseText ?? '').trimEnd();
    const chunk = String(transcript ?? '').trim();

    if (chunk === '') {
        return { displayText: base, nextBaseText: base };
    }

    const joined = base === '' ? chunk : `${base} ${chunk}`;

    if (isFinal) {
        return { displayText: joined, nextBaseText: joined };
    }

    return { displayText: joined, nextBaseText: base };
}

/**
 * @param {{
 *   lang?: string,
 *   onResult?: (payload: { transcript: string, isFinal: boolean }) => void,
 *   onError?: (message: string) => void,
 *   onEnd?: () => void,
 * }} [options]
 * @returns {{ supported: boolean, start: () => void, stop: () => void } | null}
 */
export function createBrowserSpeechSession(options = {}) {
    const Recognition = getSpeechRecognitionConstructor();

    if (!Recognition) {
        return null;
    }

    const recognition = new Recognition();
    recognition.lang = options.lang ?? 'ru-RU';
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.maxAlternatives = 1;

    recognition.onresult = (event) => {
        let interim = '';
        let finalChunk = '';

        for (let i = event.resultIndex; i < event.results.length; i += 1) {
            const result = event.results[i];
            const text = result?.[0]?.transcript ?? '';

            if (result.isFinal) {
                finalChunk += text;
            } else {
                interim += text;
            }
        }

        if (finalChunk !== '') {
            options.onResult?.({ transcript: finalChunk, isFinal: true });
        }

        if (interim !== '') {
            options.onResult?.({ transcript: interim, isFinal: false });
        }
    };

    recognition.onerror = (event) => {
        const code = event?.error ?? 'speech_error';
        const messages = {
            'not-allowed': 'Нет доступа к микрофону — разрешите в браузере.',
            'no-speech': 'Речь не распознана — попробуйте ещё раз.',
            'audio-capture': 'Микрофон недоступен.',
            network: 'Ошибка сети распознавания речи.',
            aborted: '',
        };
        const message = messages[code] ?? `Ошибка распознавания (${code}).`;

        if (message !== '') {
            options.onError?.(message);
        }
    };

    recognition.onend = () => {
        options.onEnd?.();
    };

    return {
        supported: true,
        start() {
            recognition.start();
        },
        stop() {
            try {
                recognition.stop();
            } catch {
                // already stopped
            }
        },
    };
}

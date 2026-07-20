/**
 * Thin STT façade for CRM UI (lead voice, later command bar).
 *
 * Current provider: browser Web Speech (Chrome/Edge) — see browserSpeechRecognition.js.
 * Upgrade path (paused until GPU host): swap createSpeechToTextSession / isSpeechToTextSupported
 * to call SpeechTranscriptionService (local Whisper) without rewriting consumers.
 *
 * Do not scatter Web Speech APIs in Vue pages — only this module + the provider file.
 */

export {
    mergeSpeechTranscript,
} from './browserSpeechRecognition.js';

import {
    createBrowserSpeechSession,
    isBrowserSpeechRecognitionSupported,
} from './browserSpeechRecognition.js';

export function isSpeechToTextSupported() {
    return isBrowserSpeechRecognitionSupported();
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
export function createSpeechToTextSession(options = {}) {
    // ponytail: browser STT only until local Whisper on GPU; replace body, keep signature.
    return createBrowserSpeechSession(options);
}

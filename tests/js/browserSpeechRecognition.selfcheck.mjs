/**
 * Self-check: mergeSpeechTranscript for voice→lead spike.
 * Запуск: node tests/js/browserSpeechRecognition.selfcheck.mjs
 */
import { mergeSpeechTranscript } from '../../resources/js/support/browserSpeechRecognition.js';

function assert(cond, message) {
    if (!cond) {
        throw new Error(message);
    }
}

let step = mergeSpeechTranscript('', 'Казань Москва', false);
assert(step.displayText === 'Казань Москва', 'interim from empty');
assert(step.nextBaseText === '', 'interim must not commit base');

step = mergeSpeechTranscript('', 'Казань Москва', true);
assert(step.displayText === 'Казань Москва', 'final from empty');
assert(step.nextBaseText === 'Казань Москва', 'final commits base');

step = mergeSpeechTranscript('Казань Москва', 'паллеты', true);
assert(step.displayText === 'Казань Москва паллеты', 'append final');
assert(step.nextBaseText === 'Казань Москва паллеты', 'base advances');

step = mergeSpeechTranscript('Казань Москва', 'паллеты восемь тонн', false);
assert(step.displayText === 'Казань Москва паллеты восемь тонн', 'interim append');
assert(step.nextBaseText === 'Казань Москва', 'interim keeps prior base');

step = mergeSpeechTranscript('готово', '  ', true);
assert(step.displayText === 'готово', 'empty chunk ignored');

console.log('browserSpeechRecognition.selfcheck: ok');

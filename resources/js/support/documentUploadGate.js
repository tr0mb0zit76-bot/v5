import { inject, provide, ref, shallowRef } from 'vue';
import { assessDocumentUploadBudget } from '@/support/documentUploadClientCheck.js';

const GATE_KEY = Symbol('documentUploadGate');

/**
 * @typedef {{
 *   file: File,
 *   limits: Record<string, number|string>,
 *   budget: { pages: number, maxBytes: number, exceeds: boolean, overAbsolute: boolean, canOptimize: boolean },
 * }} DocumentUploadGateState
 */

/**
 * @returns {{
 *   modalOpen: import('vue').Ref<boolean>,
 *   modalState: import('vue').ShallowRef<DocumentUploadGateState|null>,
 *   ensureDocumentWithinBudget: (file: File, limits: Record<string, number|string>) => Promise<File|null>,
 *   complete: (file: File|null) => void,
 * }}
 */
export function provideDocumentUploadGate() {
    const modalOpen = ref(false);
    /** @type {import('vue').ShallowRef<DocumentUploadGateState|null>} */
    const modalState = shallowRef(null);
    /** @type {((file: File|null) => void)|null} */
    let pendingResolve = null;

    /**
     * @param {File} file
     * @param {Record<string, number|string>} limits
     * @returns {Promise<File|null>}
     */
    async function ensureDocumentWithinBudget(file, limits) {
        if (!file) {
            return null;
        }

        const budget = await assessDocumentUploadBudget(file, limits);

        if (!budget.exceeds) {
            return file;
        }

        if (budget.canOptimize) {
            return new Promise((resolve) => {
                pendingResolve = resolve;
                modalState.value = { file, limits, budget };
                modalOpen.value = true;
            });
        }

        showManualPrepareAlert(file, limits, budget);

        return null;
    }

    /**
     * @param {File|null} file
     */
    function complete(file) {
        modalOpen.value = false;
        modalState.value = null;
        const resolve = pendingResolve;
        pendingResolve = null;
        resolve?.(file ?? null);
    }

    const gate = {
        modalOpen,
        modalState,
        ensureDocumentWithinBudget,
        complete,
    };

    provide(GATE_KEY, gate);

    return gate;
}

/**
 * @returns {{
 *   ensureDocumentWithinBudget: (file: File, limits?: Record<string, number|string>) => Promise<File|null>,
 *   modalOpen?: import('vue').Ref<boolean>,
 *   modalState?: import('vue').ShallowRef<DocumentUploadGateState|null>,
 *   complete?: (file: File|null) => void,
 * }}
 */
export function useDocumentUploadGate() {
    const gate = inject(GATE_KEY, null);

    if (!gate) {
        return {
            ensureDocumentWithinBudget: async (file) => file,
        };
    }

    return gate;
}

/**
 * @param {File} file
 * @param {Record<string, number|string>} limits
 * @param {{ pages: number, maxBytes: number, exceeds: boolean, overAbsolute: boolean, canOptimize: boolean }} budget
 */
function showManualPrepareAlert(file, limits, budget) {
    const curMb = (file.size / 1024 / 1024).toFixed(2);
    const limMb = (budget.maxBytes / 1024 / 1024).toFixed(2);
    const abs =
        Number(limits.server_upload_max_bytes) || Number(limits.absolute_max_bytes) || 0;

    if (budget.overAbsolute && abs > 0) {
        const maxMb = (abs / 1024 / 1024).toFixed(1);
        window.alert(
            `Файл слишком большой (${curMb} МиБ). Абсолютный предел около ${maxMb} МиБ. Уменьшите размер или разбейте документ.`,
        );

        return;
    }

    const kb = Math.round(Number(limits.bytes_per_page) / 1024) || 600;
    window.alert(
        `Размер файла (${curMb} МиБ) превышает лимит (до ${limMb} МиБ: ~${kb} КиБ × ${budget.pages} стр.). Подготовьте PDF вручную по регламенту.`,
    );
}

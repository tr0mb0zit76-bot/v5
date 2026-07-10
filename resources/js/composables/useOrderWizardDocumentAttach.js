import { computed, ref, watch } from 'vue';
import { warnIfDocumentExceedsBudget } from '@/support/documentUploadClientCheck.js';

export const ORDER_DOCUMENT_UPLOAD_EXTENSIONS = new Set(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp']);

export function useOrderWizardDocumentAttach(deps) {
    const {
        form,
        props,
        page,
        isOrderFormEditable,
        stageLabel,
        stageMatches,
        addDocumentFor,
    } = deps;

    const showOrderDocumentAttachModal = ref(false);
    const orderDocumentAttachPendingFile = ref(null);
    const orderDocumentAttachPresetIndex = ref(null);
    const orderDocumentAttachNewDocType = ref('request');
    const orderDocumentAttachTargetKind = ref('customer');
    const orderDocumentAttachStage = ref(null);
    const orderDocumentGlobalDropActive = ref(false);
    let orderDocumentGlobalDropDepth = 0;
    const orderDocumentGlobalFileInputRef = ref(null);
    const orderDocumentAttachModalFileInputRef = ref(null);

    const orderDocumentAttachModalTitle = computed(() => (
        orderDocumentAttachPresetIndex.value !== null ? 'Заменить файл' : 'Прикрепить файл'
    ));

    watch(orderDocumentAttachTargetKind, (kind) => {
        if (kind === 'carrier' && form.performers.length === 0) {
            orderDocumentAttachTargetKind.value = 'customer';

            return;
        }
        if (kind === 'carrier' && form.performers.length > 0) {
            const stages = form.performers.map((p) => p.stage);
            if (orderDocumentAttachStage.value === null || orderDocumentAttachStage.value === '' || !stages.some((s) => stageMatches(s, orderDocumentAttachStage.value))) {
                orderDocumentAttachStage.value = form.performers[0].stage;
            }
        }
    });

    function documentStatusLabel(status) {
        return props.documentStatusOptions.find((o) => o.value === status)?.label ?? status;
    }

    function orderDocumentUploadExtension(file) {
        return (file.name.split('.').pop() || '').toLowerCase();
    }

    function onOrderDocumentGlobalDragEnter() {
        if (!isOrderFormEditable.value) {
            return;
        }
        orderDocumentGlobalDropDepth += 1;
        orderDocumentGlobalDropActive.value = true;
    }

    function onOrderDocumentGlobalDragLeave() {
        orderDocumentGlobalDropDepth = Math.max(0, orderDocumentGlobalDropDepth - 1);
        if (orderDocumentGlobalDropDepth === 0) {
            orderDocumentGlobalDropActive.value = false;
        }
    }

    function onOrderDocumentGlobalDragOver(event) {
        if (!isOrderFormEditable.value) {
            return;
        }
        const dt = event.dataTransfer;
        if (dt) {
            dt.dropEffect = 'copy';
        }
    }

    async function onOrderDocumentGlobalDrop(event) {
        orderDocumentGlobalDropDepth = 0;
        orderDocumentGlobalDropActive.value = false;
        if (!isOrderFormEditable.value) {
            return;
        }
        const file = event.dataTransfer?.files?.[0] ?? null;
        if (!file) {
            return;
        }
        await openOrderDocumentAttachModal({ file });
    }

    function triggerOrderDocumentGlobalFilePick() {
        if (!isOrderFormEditable.value) {
            return;
        }
        orderDocumentGlobalFileInputRef.value?.click();
    }

    async function onOrderDocumentGlobalFileInputChange(event) {
        const file = event.target.files?.[0] ?? null;
        const input = event.target;
        if (input && 'value' in input) {
            input.value = '';
        }
        if (!file) {
            return;
        }
        await openOrderDocumentAttachModal({ file });
    }

    async function setOrderDocumentAttachPendingFile(file) {
        if (!file) {
            orderDocumentAttachPendingFile.value = null;

            return;
        }
        const ext = orderDocumentUploadExtension(file);
        if (!ORDER_DOCUMENT_UPLOAD_EXTENSIONS.has(ext)) {
            window.alert(
                'Недопустимый тип файла. Разрешены: PDF, Word, Excel, изображения (JPG, PNG, WebP).',
            );

            return;
        }
        await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});
        orderDocumentAttachPendingFile.value = file;
    }

    async function openOrderDocumentAttachModal(options = {}) {
        const file = options.file ?? null;
        const rawPreset = options.presetIndex;
        const presetIndex = rawPreset !== undefined && rawPreset !== null && form.documents[rawPreset]
            ? rawPreset
            : null;

        orderDocumentAttachPresetIndex.value = presetIndex;
        orderDocumentAttachPendingFile.value = null;

        if (presetIndex !== null) {
            const doc = form.documents[presetIndex];
            orderDocumentAttachTargetKind.value = doc.party === 'carrier' ? 'carrier' : 'customer';
            orderDocumentAttachStage.value = doc.party === 'carrier' ? doc.stage : null;
        } else {
            orderDocumentAttachTargetKind.value = 'customer';
            orderDocumentAttachStage.value = form.performers[0]?.stage ?? null;
            orderDocumentAttachNewDocType.value = props.documentTypeOptions[0]?.value ?? 'request';
        }

        if (file) {
            await setOrderDocumentAttachPendingFile(file);
        }

        showOrderDocumentAttachModal.value = true;
    }

    function closeOrderDocumentAttachModal() {
        showOrderDocumentAttachModal.value = false;
        orderDocumentAttachPendingFile.value = null;
        orderDocumentAttachPresetIndex.value = null;
        if (orderDocumentAttachModalFileInputRef.value) {
            orderDocumentAttachModalFileInputRef.value.value = '';
        }
        if (orderDocumentGlobalFileInputRef.value) {
            orderDocumentGlobalFileInputRef.value.value = '';
        }
    }

    async function onOrderDocumentAttachModalFileChange(event) {
        const file = event.target.files?.[0] ?? null;
        await setOrderDocumentAttachPendingFile(file);
        const input = event.target;
        if (input && 'value' in input) {
            input.value = '';
        }
    }

    async function confirmOrderDocumentAttach() {
        const file = orderDocumentAttachPendingFile.value;
        if (!file) {
            window.alert('Выберите файл.');

            return;
        }
        const presetIdx = orderDocumentAttachPresetIndex.value;
        let index;
        if (presetIdx !== null) {
            if (!form.documents[presetIdx]) {
                window.alert('Документ не найден.');

                return;
            }
            index = presetIdx;
        } else {
            const party = orderDocumentAttachTargetKind.value === 'carrier' ? 'carrier' : 'customer';
            const stage = party === 'carrier' ? orderDocumentAttachStage.value : null;
            if (party === 'carrier' && (stage === null || stage === '')) {
                window.alert('Выберите плечо маршрута.');

                return;
            }
            const docType = orderDocumentAttachNewDocType.value;
            addDocumentFor(party, stage, { type: docType, flow: 'uploaded' });
            index = form.documents.length - 1;
        }
        await assignDocumentFileAtIndex(index, file);
        closeOrderDocumentAttachModal();
    }

    async function assignDocumentFileAtIndex(index, file) {
        if (!file) {
            return;
        }
        const ext = orderDocumentUploadExtension(file);
        if (!ORDER_DOCUMENT_UPLOAD_EXTENSIONS.has(ext)) {
            window.alert(
                'Недопустимый тип файла. Разрешены: PDF, Word, Excel, изображения (JPG, PNG, WebP).',
            );

            return;
        }
        await warnIfDocumentExceedsBudget(file, page.props.document_upload_limits ?? {});
        form.documents[index].file = file;
        form.documents[index].original_name = file.name;
    }

    function documentTypeLabel(type) {
        return props.documentTypeOptions.find((option) => option.value === type)?.label ?? type;
    }

    const orderDocumentAttachPresetSummary = computed(() => {
        const idx = orderDocumentAttachPresetIndex.value;
        if (idx === null || !form.documents[idx]) {
            return '';
        }
        const d = form.documents[idx];
        const partyLabel = d.party === 'carrier' ? 'Перевозчик' : 'Заказчик';
        const leg = d.party === 'carrier' && d.stage !== null && d.stage !== undefined && String(d.stage).length > 0
            ? ` · ${stageLabel(d.stage)}`
            : '';

        return `${partyLabel}${leg} · ${documentTypeLabel(d.type)} · ${documentStatusLabel(d.status)}${d.number ? ` · № ${d.number}` : ''}`;
    });

    return {
        ORDER_DOCUMENT_UPLOAD_EXTENSIONS,
        showOrderDocumentAttachModal,
        orderDocumentAttachPendingFile,
        orderDocumentAttachPresetIndex,
        orderDocumentAttachNewDocType,
        orderDocumentAttachTargetKind,
        orderDocumentAttachStage,
        orderDocumentGlobalDropActive,
        orderDocumentGlobalFileInputRef,
        orderDocumentAttachModalFileInputRef,
        orderDocumentAttachModalTitle,
        orderDocumentAttachPresetSummary,
        documentTypeLabel,
        documentStatusLabel,
        openOrderDocumentAttachModal,
        closeOrderDocumentAttachModal,
        confirmOrderDocumentAttach,
        setOrderDocumentAttachPendingFile,
        onOrderDocumentAttachModalFileChange,
        onOrderDocumentGlobalDragEnter,
        onOrderDocumentGlobalDragLeave,
        onOrderDocumentGlobalDragOver,
        onOrderDocumentGlobalDrop,
        triggerOrderDocumentGlobalFilePick,
        onOrderDocumentGlobalFileInputChange,
    };
}

import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/** Ширина страницы предпросмотра (мм), по умолчанию A4. */
export const OVERLAY_PREVIEW_PAGE_WIDTH_MM = 210;

/** Высота страницы предпросмотра (мм), по умолчанию A4. */
export const OVERLAY_PREVIEW_PAGE_HEIGHT_MM = 297;

/** 96 DPI: пикселей на мм при «логической» ширине страницы, на которой заданы устаревшие якоря. */
const LEGACY_PX_PER_MM = 3.78;

const LEGACY_PAGE_WIDTH_PX = OVERLAY_PREVIEW_PAGE_WIDTH_MM * LEGACY_PX_PER_MM;
const LEGACY_PAGE_HEIGHT_PX = OVERLAY_PREVIEW_PAGE_HEIGHT_MM * LEGACY_PX_PER_MM;

/** Устаревшие якоря (px) при ширине страницы ~794px — см. историю TemplateOverlayPreview. */
export const OVERLAY_PREVIEW_SIGNATURE_ANCHOR_LEGACY = { x: 620, y: 1160 };
export const OVERLAY_PREVIEW_STAMP_ANCHOR_LEGACY = { x: 460, y: 1120 };

/**
 * Масштаб наложений подписи/печати под фактическую ширину контейнера предпросмотра,
 * чтобы мм на экране совпадали с мм в PDF (fit-to-width в iframe).
 *
 * @param {import('vue').Ref<HTMLElement | null>} canvasRef — обёртка с max-w и iframe (та же ширина, что у «страницы»).
 */
export function useOverlayPreviewGeometry(canvasRef) {
    const containerWidth = ref(LEGACY_PAGE_WIDTH_PX);

    let resizeObserver = null;

    onMounted(() => {
        const el = canvasRef.value;
        if (!el) {
            return;
        }

        const applyWidth = () => {
            const w = el.getBoundingClientRect().width;
            if (w > 0) {
                containerWidth.value = w;
            }
        };

        applyWidth();

        if (typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver((entries) => {
                const w = entries[0]?.contentRect?.width;
                if (w > 0) {
                    containerWidth.value = w;
                }
            });
            resizeObserver.observe(el);
        }
    });

    onBeforeUnmount(() => {
        resizeObserver?.disconnect();
    });

    const pxPerMm = computed(() => containerWidth.value / OVERLAY_PREVIEW_PAGE_WIDTH_MM);

    const pageHeightPx = computed(
        () => containerWidth.value * (OVERLAY_PREVIEW_PAGE_HEIGHT_MM / OVERLAY_PREVIEW_PAGE_WIDTH_MM),
    );

    /**
     * @param {{ x: number, y: number }} anchorLegacy
     * @param {number} widthMm
     * @param {number} heightMm
     * @param {number} offsetXmm
     * @param {number} offsetYmm
     */
    function buildOverlayStyle(anchorLegacy, widthMm, heightMm, offsetXmm, offsetYmm) {
        const w = containerWidth.value;
        const px = pxPerMm.value;
        const ph = pageHeightPx.value;

        return {
            width: `${Math.max(20, Math.round(Number(widthMm) * px))}px`,
            height: `${Math.max(20, Math.round(Number(heightMm) * px))}px`,
            left: `${Math.round((anchorLegacy.x / LEGACY_PAGE_WIDTH_PX) * w + Number(offsetXmm) * px)}px`,
            top: `${Math.round((anchorLegacy.y / LEGACY_PAGE_HEIGHT_PX) * ph + Number(offsetYmm) * px)}px`,
        };
    }

    return {
        containerWidth,
        pxPerMm,
        pageHeightPx,
        buildOverlayStyle,
    };
}

/**
 * Печать HTML из текущей вкладки (без window.open).
 *
 * С флагом noopener window.open возвращает null, хотя пустая вкладка уже создана —
 * из-за этого показывалось «Разрешите всплывающие окна», а контент не попадал в окно.
 */
/** Базовые стили печати: поля страницы без служебных колонтитулов в разметке. */
export const PRINT_DOCUMENT_BASE_STYLES = `
    @page { size: auto; margin: 14mm; }
    @media print {
        body { margin: 0; padding: 0; }
    }
`;

export function printHtmlDocument(html, title = 'Печать') {
    const iframe = document.createElement('iframe');
    iframe.setAttribute('title', title);
    iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';

    document.body.appendChild(iframe);

    const printWindow = iframe.contentWindow;

    if (!printWindow) {
        iframe.remove();
        window.alert('Не удалось подготовить печать.');

        return;
    }

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();

    const cleanup = () => {
        iframe.remove();
    };

    printWindow.addEventListener('afterprint', cleanup, { once: true });
    setTimeout(cleanup, 120_000);

    printWindow.focus();
    printWindow.print();
}

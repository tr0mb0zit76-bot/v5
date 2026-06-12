export async function copyTextToClipboard(value) {
    const text = value == null ? '' : String(value).trim();

    if (text === '') {
        return false;
    }

    try {
        await navigator.clipboard.writeText(text);

        return true;
    } catch {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        const copied = document.execCommand('copy');
        document.body.removeChild(textarea);

        return copied;
    }
}

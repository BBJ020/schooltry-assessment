export function safeFilename(value) {
    if (!value) return 'Attachment';

    return String(value)
        .split(/[\\/]/)
        .pop()
        .replace(/[\u0000-\u001f\u007f]/g, '')
        .slice(0, 120) || 'Attachment';
}

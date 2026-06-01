/**
 * Versão estável dos PDFs gravados na aula (URLs R2/storage no banco).
 * Usada em ?v= no proxy /aula/{id}/pdf/{i} para invalidar cache ao trocar o arquivo.
 */
export function fingerprintPdfSources(files) {
    const parts = (files || []).map((f) => {
        const url = (f?.url ?? '').toString().trim();
        const name = (f?.name ?? '').toString().trim();
        return `${url}\0${name}`;
    });
    const raw = parts.join('\n');
    if (!raw) {
        return '0';
    }
    let h = 2166136261;
    for (let i = 0; i < raw.length; i++) {
        h ^= raw.charCodeAt(i);
        h = Math.imul(h, 16777619);
    }
    return (h >>> 0).toString(36);
}

export function lessonPdfProxyUrl(base, lessonId, fileIndex, version) {
    const p = String(base || '').replace(/\/$/, '');
    const v = encodeURIComponent(version || '0');
    return `${p}/aula/${lessonId}/pdf/${fileIndex}?v=${v}`;
}

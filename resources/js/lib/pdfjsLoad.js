import * as pdfjsLib from 'pdfjs-dist';

/**
 * PDF.js precisa enviar cookies de sessão nas rotas /m/{slug}/aula/.../pdf/{i} (proxy same-origin).
 */
export function isSameOriginPdfUrl(url) {
    try {
        const u = new URL(url, window.location.origin);
        return u.origin === window.location.origin;
    } catch {
        return false;
    }
}

export function getPdfDocumentOptions(url, extra = {}) {
    return {
        url,
        /** Rotas /aula/{id}/pdf/{i} no mesmo domínio: cookies + Range (206) do proxy Laravel */
        withCredentials: isSameOriginPdfUrl(url),
        disableWorker: extra.disableWorker ?? false,
        ...extra,
    };
}

export async function loadPdfDocument(url, extra = {}) {
    const task = pdfjsLib.getDocument(getPdfDocumentOptions(url, extra));
    return task.promise;
}

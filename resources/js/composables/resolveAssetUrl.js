/**
 * Resolve URL de asset (favicon, logo) para uso no documento.
 */
export function resolveAssetUrl(url) {
    if (!url || typeof url !== 'string') {
        return null;
    }
    const trimmed = url.trim();
    if (!trimmed) {
        return null;
    }
    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
        return trimmed;
    }
    if (typeof window === 'undefined') {
        return trimmed.startsWith('/') ? trimmed : `/${trimmed.replace(/^\//, '')}`;
    }
    return trimmed.startsWith('/')
        ? `${window.location.origin}${trimmed}`
        : `${window.location.origin}/${trimmed.replace(/^\//, '')}`;
}

/** Primeira URL válida entre os candidatos. */
export function pickFaviconUrl(...candidates) {
    for (const candidate of candidates) {
        const resolved = resolveAssetUrl(candidate);
        if (resolved) {
            return resolved;
        }
    }

    return null;
}

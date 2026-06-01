<script setup>
import { ref, shallowRef, watch, onMounted, onUnmounted, computed, nextTick, defineExpose } from 'vue';
import * as pdfjsLib from 'pdfjs-dist';
import PdfJsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?worker';
import PdfJsWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { loadPdfDocument } from '@/lib/pdfjsLoad';

let pdfWorkerAvailable = !import.meta.env.DEV;
pdfjsLib.GlobalWorkerOptions.workerSrc = PdfJsWorkerUrl;
if (!pdfWorkerAvailable) {
    // In local dev, app and Vite frequently use different origins (.test vs :5173).
    // Force main-thread rendering to avoid worker bootstrap/CORS failures.
    pdfjsLib.GlobalWorkerOptions.workerPort = null;
} else {
    try {
        pdfjsLib.GlobalWorkerOptions.workerPort = new PdfJsWorker();
    } catch (error) {
        console.warn('[pdfjs] Worker could not be initialized; using fallback.', error);
        pdfjsLib.GlobalWorkerOptions.workerPort = null;
        pdfWorkerAvailable = false;
    }
}

function isWorkerBootstrapError(error) {
    const msg = (error?.message || error || '').toString();
    return msg.includes('GlobalWorkerOptions.workerSrc') || msg.includes('Failed to construct \'Worker\'');
}

async function loadPdfDoc(url, forceMainThread = false) {
    return loadPdfDocument(url, {
        disableWorker: forceMainThread || !pdfWorkerAvailable,
    });
}

const props = defineProps({
    /** Lista `{ url, name }` com URLs absolutas acessiveis ao navegador. */
    files: { type: Array, required: true },
});

const emit = defineEmits(['last-page-reached']);

const canvasRef = ref(null);
const nextPeekCanvasRef = ref(null);
const canvasHostRef = ref(null);
const fullscreenRootRef = ref(null);
const fsNavScrollRef = ref(null);

const loading = ref(true);
const error = ref('');
const globalPage = ref(1);
const totalPages = ref(0);
const pdfDocs = shallowRef([]);
const pageIsLandscape = ref(false);
const isFullscreen = ref(false);

let renderTask = null;
let resizeObserver = null;
let resizeObservedEl = null;

const WHEEL_LISTENER_OPTS = { passive: false };
let wheelHostEl = null;
let pageFlipCooldown = 0;
const PAGE_FLIP_COOLDOWN_MS = 350;
const isDesktopViewport = ref(true);
const fullscreenNavVisible = ref(false);
let fullscreenNavTimer = null;
const fsThumbCanvases = ref({});
const hasNextPage = computed(() => globalPage.value < totalPages.value);

function globalToLocal(globalOneBased) {
    const g = globalOneBased - 1;
    let offset = 0;
    for (let fi = 0; fi < pdfDocs.value.length; fi++) {
        const doc = pdfDocs.value[fi];
        if (g < offset + doc.numPages) {
            return { fileIndex: fi, doc, pageNum: g - offset + 1 };
        }
        offset += doc.numPages;
    }
    const first = pdfDocs.value[0];
    return first ? { fileIndex: 0, doc: first, pageNum: 1 } : { fileIndex: 0, doc: null, pageNum: 1 };
}

function updateViewportFlags() {
    isDesktopViewport.value = typeof window !== 'undefined' ? window.innerWidth >= 768 : true;
}

function revealFullscreenNav() {
    if (!isFullscreen.value || !isDesktopViewport.value) return;
    fullscreenNavVisible.value = true;
    if (fullscreenNavTimer) clearTimeout(fullscreenNavTimer);
    fullscreenNavTimer = setTimeout(() => {
        fullscreenNavVisible.value = false;
        fullscreenNavTimer = null;
    }, 3000);
}

function setFsThumbRef(pg, el) {
    if (el) {
        fsThumbCanvases.value[pg] = el;
    } else {
        delete fsThumbCanvases.value[pg];
    }
}

async function renderCurrentPage() {
    const canvas = canvasRef.value;
    const host = canvasHostRef.value;
    if (!canvas || !host || !pdfDocs.value.length) return;

    const { doc, pageNum } = globalToLocal(globalPage.value);
    if (!doc) return;

    const page = await doc.getPage(pageNum);
    const outputScale = window.devicePixelRatio || 1;
    const baseViewport = page.getViewport({ scale: 1 });
    pageIsLandscape.value = baseViewport.width > baseViewport.height * 1.05;

    const cw = Math.max(80, host.clientWidth - 16);
    const ch = Math.max(80, host.clientHeight - 16);
    const fitWhole = Math.min(cw / baseViewport.width, ch / baseViewport.height, 8);
    const fitWidth = Math.min(cw / baseViewport.width, 8);
    const fit = isFullscreen.value ? fitWhole : fitWidth;
    const viewport = page.getViewport({ scale: fit * outputScale });

    const ctx = canvas.getContext('2d');
    if (renderTask) {
        try {
            renderTask.cancel();
        } catch (_) {}
        renderTask = null;
    }

    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    canvas.style.width = `${viewport.width / outputScale}px`;
    canvas.style.height = `${viewport.height / outputScale}px`;

    renderTask = page.render({
        canvasContext: ctx,
        viewport,
    });
    try {
        await renderTask.promise;
    } catch (e) {
        if (e?.name !== 'RenderingCancelledException') {
            console.warn(e);
        }
    }
    renderTask = null;
    void renderNextPeek();
}

async function renderNextPeek() {
    const canvas = nextPeekCanvasRef.value;
    if (!canvas) return;
    if (!hasNextPage.value || !pdfDocs.value.length) {
        canvas.width = 1;
        canvas.height = 1;
        return;
    }
    const { doc, pageNum } = globalToLocal(globalPage.value + 1);
    if (!doc) return;
    const page = await doc.getPage(pageNum);
    const vp = page.getViewport({ scale: 0.2 });
    const ctx = canvas.getContext('2d');
    canvas.width = vp.width;
    canvas.height = vp.height;
    await page.render({ canvasContext: ctx, viewport: vp }).promise;
}

async function destroyDocs() {
    for (const d of pdfDocs.value) {
        try {
            await d.destroy();
        } catch (_) {}
    }
    pdfDocs.value = [];
}

async function loadDocuments() {
    loading.value = true;
    error.value = '';
    await destroyDocs();
    totalPages.value = 0;
    globalPage.value = 1;

    const list = (props.files || [])
        .map((f) => ({ url: (f?.url ?? '').toString().trim() }))
        .filter((f) => f.url);
    if (!list.length) {
        loading.value = false;
        return;
    }

    try {
        const loadOne = async (url) => {
            try {
                return await loadPdfDoc(url);
            } catch (firstError) {
                if (!isWorkerBootstrapError(firstError)) {
                    throw firstError;
                }
                pdfWorkerAvailable = false;
                pdfjsLib.GlobalWorkerOptions.workerPort = null;
                return loadPdfDoc(url, true);
            }
        };

        const docs = await Promise.all(list.map(({ url }) => loadOne(url)));
        let pages = 0;
        for (const pdf of docs) {
            pages += pdf.numPages;
        }
        pdfDocs.value = docs;
        totalPages.value = pages;
        await nextTick();
        await renderCurrentPage();
        await nextTick();
        void renderVisibleThumbs();
    } catch (e) {
        console.error(e);
        error.value =
            'Não foi possível carregar o PDF. Tente atualizar a página ou contacte o suporte se o problema continuar.';
        await destroyDocs();
    } finally {
        loading.value = false;
    }
}

async function renderVisibleThumbs() {
    await nextTick();
    for (let pg = 1; pg <= totalPages.value; pg++) {
        const { doc, pageNum } = globalToLocal(pg);
        if (!doc) continue;
        const canvas = fsThumbCanvases.value[pg];
        if (!canvas) continue;
        const page = await doc.getPage(pageNum);
        const vp = page.getViewport({ scale: 0.18 });
        const ctx = canvas.getContext('2d');
        canvas.width = vp.width;
        canvas.height = vp.height;
        await page.render({ canvasContext: ctx, viewport: vp }).promise;
    }
}

function prevPage() {
    if (globalPage.value <= 1) return;
    globalPage.value -= 1;
    if (isFullscreen.value && canvasHostRef.value) {
        canvasHostRef.value.scrollTop = 0;
    }
    void renderCurrentPage();
}

function nextPage() {
    if (globalPage.value >= totalPages.value) return;
    globalPage.value += 1;
    if (isFullscreen.value && canvasHostRef.value) {
        canvasHostRef.value.scrollTop = 0;
    }
    void renderCurrentPage();
}

function goToGlobalPage(g) {
    if (g < 1 || g > totalPages.value) return;
    globalPage.value = g;
    if (isFullscreen.value && canvasHostRef.value) {
        canvasHostRef.value.scrollTop = 0;
    }
    void renderCurrentPage();
}

function syncThumbsScroll() {
    const container = fsNavScrollRef.value;
    if (!container) return;
    const target = container.querySelector(`[data-page="${globalPage.value}"]`);
    if (target) target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

async function toggleFullscreen() {
    const el = fullscreenRootRef.value;
    if (!el) return;
    try {
        if (!document.fullscreenElement) {
            await el.requestFullscreen();
            try {
                await screen.orientation?.lock?.('landscape-primary');
            } catch (_) {}
        } else {
            await document.exitFullscreen();
            try {
                screen.orientation?.unlock?.();
            } catch (_) {}
        }
    } catch (_) {}
}

function onWheelPageFlip(e) {
    const el = canvasHostRef.value;
    if (!el) return;
    if (Math.abs(e.deltaY) < 6) return;
    const atTop = el.scrollTop <= 0;
    const atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 1;
    const now = Date.now();
    if (now < pageFlipCooldown) {
        if ((e.deltaY > 0 && atBottom) || (e.deltaY < 0 && atTop)) e.preventDefault();
        return;
    }
    if (e.deltaY > 0 && atBottom && globalPage.value < totalPages.value) {
        e.preventDefault();
        pageFlipCooldown = now + PAGE_FLIP_COOLDOWN_MS;
        nextPage();
    } else if (e.deltaY < 0 && atTop && globalPage.value > 1) {
        e.preventDefault();
        pageFlipCooldown = now + PAGE_FLIP_COOLDOWN_MS;
        prevPage();
    }
}

function bindWheelHost() {
    if (wheelHostEl) {
        wheelHostEl.removeEventListener('wheel', onWheelPageFlip, WHEEL_LISTENER_OPTS);
        wheelHostEl = null;
    }
    const host = canvasHostRef.value;
    if (host) {
        host.addEventListener('wheel', onWheelPageFlip, WHEEL_LISTENER_OPTS);
        wheelHostEl = host;
    }
}

function unbindWheelHost() {
    if (wheelHostEl) {
        wheelHostEl.removeEventListener('wheel', onWheelPageFlip, WHEEL_LISTENER_OPTS);
        wheelHostEl = null;
    }
}

function onFullscreenChange() {
    const nowFs = !!document.fullscreenElement;
    const entering = nowFs && !isFullscreen.value;
    isFullscreen.value = nowFs;
    if (entering && canvasHostRef.value) {
        canvasHostRef.value.scrollTop = 0;
        revealFullscreenNav();
    }
    if (!nowFs) {
        fullscreenNavVisible.value = false;
        if (fullscreenNavTimer) {
            clearTimeout(fullscreenNavTimer);
            fullscreenNavTimer = null;
        }
    }
    void nextTick().then(() => renderCurrentPage());
}

function onResize() {
    updateViewportFlags();
    void renderCurrentPage();
}

function onOrientationChange() {
    setTimeout(() => void renderCurrentPage(), 200);
}

function onKeyDown(e) {
    const t = e.target;
    if (t && (t.closest?.('input, textarea, [contenteditable="true"]') || t.isContentEditable)) return;
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        prevPage();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        nextPage();
    }
}

function downloadPdf() {
    const files = props.files || [];
    const idx = globalToLocal(globalPage.value).fileIndex ?? 0;
    const item = files[idx];
    if (!item?.url) return;
    const a = document.createElement('a');
    a.href = item.url;
    a.download = (item.name || 'apresentacao.pdf').replace(/[^\w.\-\u00C0-\u024F]+/g, '_');
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

const hasPdf = computed(() => pdfDocs.value.length > 0 && totalPages.value > 0 && !error.value);

defineExpose({
    downloadPdf,
    hasPdf,
});

const showLandscapeHint = computed(() => {
    if (typeof window === 'undefined') return false;
    if (window.innerWidth > window.innerHeight) return false;
    return pageIsLandscape.value && totalPages.value > 0;
});

watch(
    () => props.files,
    () => {
        void loadDocuments();
    },
    { deep: true }
);

watch(globalPage, (p, prev) => {
    if (canvasHostRef.value) {
        canvasHostRef.value.scrollTop = 0;
    }
    if (isFullscreen.value) {
        revealFullscreenNav();
    }
    syncThumbsScroll();
    if (totalPages.value > 0 && p === totalPages.value && p !== prev) {
        emit('last-page-reached');
    }
});

watch(totalPages, (n) => {
    if (n > 0) {
        nextTick(() => void renderVisibleThumbs());
    }
});

onMounted(() => {
    document.addEventListener('fullscreenchange', onFullscreenChange);
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('resize', onResize);
    window.addEventListener('orientationchange', onOrientationChange);
    updateViewportFlags();
    void loadDocuments();
    nextTick(() => {
        const host = canvasHostRef.value;
        if (host && typeof ResizeObserver !== 'undefined') {
            resizeObservedEl = host;
            resizeObserver = new ResizeObserver(() => void renderCurrentPage());
            resizeObserver.observe(host);
        }
        bindWheelHost();
    });
});

onUnmounted(() => {
    unbindWheelHost();
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    window.removeEventListener('keydown', onKeyDown);
    window.removeEventListener('resize', onResize);
    window.removeEventListener('orientationchange', onOrientationChange);
    if (resizeObserver && resizeObservedEl) {
        try {
            resizeObserver.unobserve(resizeObservedEl);
        } catch (_) {}
    }
    resizeObserver = null;
    resizeObservedEl = null;
    if (renderTask) {
        try {
            renderTask.cancel();
        } catch (_) {}
        renderTask = null;
    }
    if (fullscreenNavTimer) clearTimeout(fullscreenNavTimer);
    void destroyDocs();
});

const showFullscreenPageNav = computed(
    () =>
        isFullscreen.value &&
        isDesktopViewport.value &&
        fullscreenNavVisible.value &&
        !loading.value &&
        !error.value &&
        totalPages.value > 0
);
</script>

<template>
    <div class="member-pdf-presentation flex flex-col gap-2">
        <p
            v-if="showLandscapeHint"
            class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-center text-xs text-amber-100"
        >
            Para melhor leitura, use o aparelho em modo paisagem ou a tela cheia.
        </p>

        <div
            ref="fullscreenRootRef"
            class="flex min-h-0 flex-col overflow-hidden rounded-lg border border-zinc-600 bg-zinc-950/80"
        >
            <div
                ref="canvasHostRef"
                :class="[
                    'relative w-full overflow-auto bg-zinc-950/80',
                    isFullscreen
                        ? 'flex min-h-0 flex-1 items-center justify-center p-3'
                        : 'flex aspect-video min-h-0 items-start justify-center p-3',
                ]"
            >
                <div
                    v-if="isFullscreen && !loading && !error && totalPages > 0"
                    class="pointer-events-none absolute left-4 top-4 z-[6] rounded-full border border-zinc-600 bg-zinc-900/85 px-3 py-1 text-sm font-semibold text-white shadow-lg"
                >
                    Página {{ globalPage }} / {{ totalPages }}
                </div>

                <canvas ref="canvasRef" class="mx-auto block max-w-full shadow-lg" />
                <!-- Metade esquerda: página anterior; metade direita: próxima (só quando o PDF está visível). -->
                <div
                    v-if="!loading && !error && totalPages > 0"
                    class="pointer-events-none absolute inset-3 z-[1] flex"
                >
                    <button
                        type="button"
                        class="pointer-events-auto h-full w-1/2 cursor-w-resize border-0 bg-transparent transition-colors hover:bg-white/[0.06] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ma-primary,#0ea5e9)] focus-visible:ring-offset-2 focus-visible:ring-offset-zinc-950 disabled:pointer-events-none disabled:opacity-0"
                        :disabled="globalPage <= 1"
                        aria-label="Página anterior - clique na metade esquerda da área da apresentação"
                        @click.stop="prevPage"
                    />
                    <button
                        type="button"
                        class="pointer-events-auto h-full w-1/2 cursor-e-resize border-0 bg-transparent transition-colors hover:bg-white/[0.06] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ma-primary,#0ea5e9)] focus-visible:ring-offset-2 focus-visible:ring-offset-zinc-950 disabled:pointer-events-none disabled:opacity-0"
                        :disabled="globalPage >= totalPages"
                        aria-label="Próxima página - clique na metade direita da área da apresentação"
                        @click.stop="nextPage"
                    />
                </div>
                <div
                    v-if="loading"
                    class="absolute inset-0 z-[2] flex items-center justify-center bg-zinc-950/60 text-sm text-zinc-300"
                >
                    Carregando...
                </div>
                <div
                    v-else-if="error"
                    class="absolute inset-0 z-[2] flex items-center justify-center bg-zinc-950/80 p-4 text-center text-sm text-red-200"
                >
                    {{ error }}
                </div>

                <aside
                    v-if="showFullscreenPageNav"
                    class="absolute right-4 top-4 z-[6] w-36 rounded-lg border border-zinc-600 bg-zinc-900/90 p-2 shadow-2xl"
                >
                    <p class="mb-2 text-center text-[11px] font-medium text-zinc-300">Páginas</p>
                    <div ref="fsNavScrollRef" class="max-h-[40vh] overflow-y-auto pr-1">
                        <button
                            v-for="pg in totalPages"
                            :key="`fs-nav-${pg}`"
                            :data-page="pg"
                            type="button"
                            class="mb-2 w-full rounded border p-1 text-xs text-zinc-100 transition"
                            :class="
                                pg === globalPage
                                    ? 'border-[var(--ma-primary,#0ea5e9)] bg-[var(--ma-primary,#0ea5e9)]/20 ring-1 ring-[var(--ma-primary,#0ea5e9)]'
                                    : 'border-zinc-700 hover:border-zinc-500 hover:bg-zinc-800'
                            "
                            @click="goToGlobalPage(pg)"
                        >
                            <canvas :ref="(el) => setFsThumbRef(pg, el)" class="mx-auto block h-auto w-full rounded bg-white" />
                            <span class="mt-1 block text-center text-[10px] text-zinc-400">{{ pg }}</span>
                        </button>
                    </div>
                </aside>

                <button
                    v-if="isFullscreen && isDesktopViewport && hasNextPage && !loading && !error"
                    type="button"
                    class="absolute bottom-4 right-4 z-[6] w-32 rounded-lg border border-zinc-600 bg-zinc-900/90 p-1.5 text-left shadow-2xl transition hover:border-[var(--ma-primary,#0ea5e9)]"
                    @click="nextPage"
                >
                    <p class="mb-1 text-center text-[10px] font-medium text-zinc-300">Próxima</p>
                    <div class="overflow-hidden rounded border border-zinc-700 bg-white/90">
                        <canvas ref="nextPeekCanvasRef" class="block h-auto w-full" />
                    </div>
                </button>
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-700 bg-zinc-900/90 px-3 py-2"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-zinc-600 px-2 py-1 text-xs font-medium text-zinc-100 hover:bg-zinc-800 disabled:opacity-40"
                        :disabled="globalPage <= 1 || loading || !!error"
                        @click="prevPage"
                    >
                        Anterior
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-zinc-600 px-2 py-1 text-xs font-medium text-zinc-100 hover:bg-zinc-800 disabled:opacity-40"
                        :disabled="globalPage >= totalPages || loading || !!error || totalPages === 0"
                        @click="nextPage"
                    >
                        Próxima
                    </button>
                    <span v-if="totalPages > 0" class="text-xs text-zinc-400">
                        Página {{ globalPage }} de {{ totalPages }}
                    </span>
                </div>
                <button
                    type="button"
                    class="rounded-md border border-zinc-600 px-2 py-1 text-xs font-medium text-zinc-100 hover:bg-zinc-800"
                    @click="toggleFullscreen"
                >
                    {{ isFullscreen ? 'Sair da tela cheia' : 'Tela cheia' }}
                </button>
            </div>
        </div>
    </div>
</template>

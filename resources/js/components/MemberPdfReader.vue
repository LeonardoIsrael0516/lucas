<script setup>
import { ref, shallowRef, watch, onMounted, onUnmounted, computed, nextTick, defineExpose } from 'vue';
import * as pdfjsLib from 'pdfjs-dist';
import PdfJsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?worker';
import PdfJsWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import axios from 'axios';
import { Heart, ZoomIn, ZoomOut, Highlighter, Maximize2, Minimize2 } from 'lucide-vue-next';

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
    const task = pdfjsLib.getDocument({
        url,
        withCredentials: false,
        disableWorker: forceMainThread || !pdfWorkerAvailable,
    });
    return task.promise;
}

const props = defineProps({
    files: { type: Array, required: true },
    baseUrl: { type: String, required: true },
    lessonId: { type: [Number, String], required: true },
    likesCount: { type: Number, default: 0 },
    userLiked: { type: Boolean, default: false },
});

const emit = defineEmits(['last-page-reached']);

const canvasRef = ref(null);
const nextPeekCanvasRef = ref(null);
const canvasHostRef = ref(null);
const fullscreenRootRef = ref(null);
const thumbsScrollRef = ref(null);
const fsNavScrollRef = ref(null);

const loading = ref(true);
const error = ref('');
const globalPage = ref(1);
const totalPages = ref(0);
const pdfDocs = shallowRef([]);
const zoomMul = ref(1);
const isFullscreen = ref(false);
const highlightColor = ref(null);
const toastMessage = ref('');
let toastTimer = null;

const likesCountLocal = ref(props.likesCount);
const userLikedLocal = ref(props.userLiked);

watch(
    () => [props.likesCount, props.userLiked],
    ([c, l]) => {
        likesCountLocal.value = c;
        userLikedLocal.value = l;
    }
);

/** highlightsByFile[fileIndex] = array */
const highlightsByFile = ref({});
let annotationsDirty = false;
let saveTimer = null;

const selecting = ref(false);
const selectStart = ref(null);
const selectCurrent = ref(null);

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

function showToast(msg) {
    toastMessage.value = msg;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toastMessage.value = '';
        toastTimer = null;
    }, 3200);
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

function apiPrefix() {
    return props.baseUrl.replace(/\/$/, '');
}

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

const currentLocal = computed(() => globalToLocal(globalPage.value));
const hasNextPage = computed(() => globalPage.value < totalPages.value);

const currentPageHighlights = computed(() => {
    const { fileIndex, pageNum } = currentLocal.value;
    const list = highlightsByFile.value[fileIndex] || [];
    return list.filter((h) => h.page === pageNum);
});

async function loadAnnotations() {
    try {
        const { data } = await axios.get(`${apiPrefix()}/aula/${props.lessonId}/pdf-annotations`, {
            headers: { Accept: 'application/json' },
        });
        const raw = data?.annotations_by_file || {};
        const next = {};
        Object.keys(raw).forEach((k) => {
            next[Number(k)] = Array.isArray(raw[k]) ? raw[k] : [];
        });
        highlightsByFile.value = next;
    } catch (_) {
        highlightsByFile.value = {};
    }
}

function scheduleSave() {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(() => void saveCurrentFileAnnotations(), 450);
}

async function saveCurrentFileAnnotations() {
    const fi = currentLocal.value.fileIndex;
    const list = highlightsByFile.value[fi] || [];
    try {
        await axios.put(
            `${apiPrefix()}/aula/${props.lessonId}/pdf-annotations`,
            { file_index: fi, highlights: list },
            { headers: { Accept: 'application/json', 'Content-Type': 'application/json' } }
        );
        annotationsDirty = false;
    } catch (e) {
        console.warn('pdf annotations save failed', e);
    }
}

async function saveFileIndexAnnotations(fi) {
    const list = highlightsByFile.value[fi] || [];
    try {
        await axios.put(
            `${apiPrefix()}/aula/${props.lessonId}/pdf-annotations`,
            { file_index: fi, highlights: list },
            { headers: { Accept: 'application/json', 'Content-Type': 'application/json' } }
        );
    } catch (e) {
        console.warn('pdf annotations save failed', e);
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
    const cw = Math.max(80, host.clientWidth - 16);
    const ch = Math.max(80, host.clientHeight - 16);
    const fitWhole = Math.min(cw / baseViewport.width, ch / baseViewport.height, 8);
    const fitWidth = Math.min(cw / baseViewport.width, 8);
    const fit = isFullscreen.value ? fitWhole : fitWidth * zoomMul.value;
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

    renderTask = page.render({ canvasContext: ctx, viewport });
    try {
        await renderTask.promise;
    } catch (e) {
        if (e?.name !== 'RenderingCancelledException') console.warn(e);
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
        const docs = [];
        let pages = 0;
        for (const { url } of list) {
            let pdf;
            try {
                pdf = await loadPdfDoc(url);
            } catch (firstError) {
                if (!isWorkerBootstrapError(firstError)) throw firstError;
                // Dev fallback: force load without worker when browser blocks cross-origin worker bootstrap.
                pdfWorkerAvailable = false;
                pdfjsLib.GlobalWorkerOptions.workerPort = null;
                pdf = await loadPdfDoc(url, true);
            }
            docs.push(pdf);
            pages += pdf.numPages;
        }
        pdfDocs.value = docs;
        totalPages.value = pages;
        await loadAnnotations();
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

const thumbCanvases = ref({});
const fsThumbCanvases = ref({});

function setThumbRef(pg, el) {
    if (el) {
        thumbCanvases.value[pg] = el;
    } else {
        delete thumbCanvases.value[pg];
    }
}

function setFsThumbRef(pg, el) {
    if (el) {
        fsThumbCanvases.value[pg] = el;
    } else {
        delete fsThumbCanvases.value[pg];
    }
}

async function renderVisibleThumbs() {
    await nextTick();
    for (let pg = 1; pg <= totalPages.value; pg++) {
        const { doc, pageNum } = globalToLocal(pg);
        if (!doc) continue;
        const canvases = [thumbCanvases.value[pg], fsThumbCanvases.value[pg]].filter(Boolean);
        if (!canvases.length) continue;
        const page = await doc.getPage(pageNum);
        const vp = page.getViewport({ scale: 0.18 });
        for (const canvas of canvases) {
            const ctx = canvas.getContext('2d');
            canvas.width = vp.width;
            canvas.height = vp.height;
            await page.render({ canvasContext: ctx, viewport: vp }).promise;
        }
    }
}

function prevPage() {
    if (globalPage.value <= 1) return;
    globalPage.value -= 1;
    if (canvasHostRef.value) canvasHostRef.value.scrollTop = 0;
}

function nextPage() {
    if (globalPage.value >= totalPages.value) return;
    globalPage.value += 1;
    if (canvasHostRef.value) canvasHostRef.value.scrollTop = 0;
}

function zoomIn() {
    zoomMul.value = Math.min(3, Math.round((zoomMul.value + 0.25) * 100) / 100);
    void renderCurrentPage();
}

function zoomOut() {
    zoomMul.value = Math.max(0.5, Math.round((zoomMul.value - 0.25) * 100) / 100);
    void renderCurrentPage();
}

async function toggleFullscreen() {
    const el = fullscreenRootRef.value;
    if (!el) return;
    try {
        if (!document.fullscreenElement) {
            await el.requestFullscreen();
        } else {
            await document.exitFullscreen();
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

function onKeyDown(e) {
    const t = e.target;
    if (t && (t.closest?.('input, textarea, button') || t.isContentEditable)) return;
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        prevPage();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        nextPage();
    }
}

function goToGlobalPage(g) {
    if (g < 1 || g > totalPages.value) return;
    globalPage.value = g;
    if (canvasHostRef.value) canvasHostRef.value.scrollTop = 0;
}

function syncThumbsScroll() {
    const selectors = [`[data-page="${globalPage.value}"]`];
    for (const container of [thumbsScrollRef.value, fsNavScrollRef.value]) {
        if (!container) continue;
        const target = container.querySelector(selectors[0]);
        if (target) target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
}

function overlayPointerDown(e) {
    if (!highlightColor.value) return;
    const overlay = e.currentTarget;
    const rect = overlay.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;
    selecting.value = true;
    selectStart.value = { x, y };
    selectCurrent.value = { x, y };
}

function overlayPointerMove(e) {
    if (!selecting.value || !highlightColor.value) return;
    const overlay = e.currentTarget;
    const rect = overlay.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;
    selectCurrent.value = { x, y };
}

function overlayPointerUp(e) {
    if (!selecting.value || !highlightColor.value || !selectStart.value) {
        selecting.value = false;
        return;
    }
    const overlay = e.currentTarget;
    const rect = overlay.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;
    const x0 = selectStart.value.x;
    const y0 = selectStart.value.y;
    const left = Math.min(x0, x);
    const top = Math.min(y0, y);
    const w = Math.abs(x - x0);
    const h = Math.abs(y - y0);
    selecting.value = false;
    selectStart.value = null;
    selectCurrent.value = null;
    if (w < 0.008 || h < 0.008) return;

    const { fileIndex, pageNum } = currentLocal.value;
    const id =
        typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : `h-${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const hl = {
        id,
        page: pageNum,
        color: highlightColor.value,
        x: left,
        y: top,
        width: w,
        height: h,
    };
    if (!highlightsByFile.value[fileIndex]) highlightsByFile.value[fileIndex] = [];
    highlightsByFile.value[fileIndex] = [...highlightsByFile.value[fileIndex], hl];
    annotationsDirty = true;
    scheduleSave();
}

function removeHighlight(id) {
    const fi = currentLocal.value.fileIndex;
    const list = highlightsByFile.value[fi] || [];
    highlightsByFile.value[fi] = list.filter((h) => h.id !== id);
    annotationsDirty = true;
    scheduleSave();
}

async function toggleLike() {
    try {
        const { data } = await axios.post(
            `${apiPrefix()}/aula/${props.lessonId}/like`,
            {},
            { headers: { Accept: 'application/json' } }
        );
        userLikedLocal.value = !!data.liked;
        likesCountLocal.value = Number(data.likes_count) || 0;
    } catch (_) {
        showToast('Não foi possível atualizar curtida.');
    }
}

function downloadPdf() {
    const files = props.files || [];
    const idx = currentLocal.value.fileIndex;
    const item = files[idx];
    if (!item?.url) return;
    const a = document.createElement('a');
    a.href = item.url;
    a.download = (item.name || 'documento.pdf').replace(/[^\w.\-\u00C0-\u024F]+/g, '_');
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    showToast('Download iniciado. Verifique sua pasta de downloads.');
}

const hasPdf = computed(() => pdfDocs.value.length > 0 && totalPages.value > 0 && !error.value);

defineExpose({
    downloadPdf,
    hasPdf,
});

watch(
    () => props.files,
    () => void loadDocuments(),
    { deep: true }
);

watch(globalPage, async (newP, oldP) => {
    if (canvasHostRef.value) {
        canvasHostRef.value.scrollTop = 0;
    }
    if (isFullscreen.value) {
        revealFullscreenNav();
    }
    if (oldP !== undefined && oldP !== null && newP !== oldP) {
        const prevFi = globalToLocal(oldP).fileIndex;
        await saveFileIndexAnnotations(prevFi);
    }
    await renderCurrentPage();
    await nextTick();
    void renderVisibleThumbs();
    syncThumbsScroll();
    if (totalPages.value > 0 && newP === totalPages.value && oldP !== undefined && newP !== oldP) {
        emit('last-page-reached');
    }
});

watch(zoomMul, () => void renderCurrentPage());

watch(totalPages, (n) => {
    if (n > 0) {
        nextTick(() => void renderVisibleThumbs());
    }
});

onMounted(() => {
    document.addEventListener('fullscreenchange', onFullscreenChange);
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('resize', onResize);
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
    if (resizeObserver && resizeObservedEl) {
        try {
            resizeObserver.unobserve(resizeObservedEl);
        } catch (_) {}
    }
    resizeObserver = null;
    resizeObservedEl = null;
    if (saveTimer) clearTimeout(saveTimer);
    if (toastTimer) clearTimeout(toastTimer);
    if (fullscreenNavTimer) clearTimeout(fullscreenNavTimer);
    if (renderTask) {
        try {
            renderTask.cancel();
        } catch (_) {}
        renderTask = null;
    }
    void destroyDocs();
});

const selectionRectCss = computed(() => {
    if (!selecting.value || !selectStart.value || !selectCurrent.value) return null;
    const x0 = selectStart.value.x;
    const y0 = selectStart.value.y;
    const x1 = selectCurrent.value.x;
    const y1 = selectCurrent.value.y;
    const left = Math.min(x0, x1);
    const top = Math.min(y0, y1);
    const w = Math.abs(x1 - x0);
    const h = Math.abs(y1 - y0);
    return {
        left: `${left * 100}%`,
        top: `${top * 100}%`,
        width: `${w * 100}%`,
        height: `${h * 100}%`,
    };
});

const colorBtn = (c) =>
    highlightColor.value === c ? 'ring-2 ring-white ring-offset-2 ring-offset-zinc-900' : 'opacity-80 hover:opacity-100';
const showFullscreenPageNav = computed(
    () =>
        isFullscreen.value &&
        isDesktopViewport.value &&
        fullscreenNavVisible.value &&
        !loading.value &&
        !error.value &&
        totalPages.value > 0
);

watch(isDesktopViewport, (isDesktop) => {
    if (!isDesktop) {
        fullscreenNavVisible.value = false;
    }
});
</script>

<template>
    <div class="member-pdf-reader flex flex-col gap-3">
        <div
            v-if="toastMessage"
            class="rounded-lg border border-emerald-500/40 bg-emerald-500/15 px-4 py-2 text-center text-sm text-emerald-100"
        >
            {{ toastMessage }}
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-stretch">
            <div
                ref="fullscreenRootRef"
                class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-lg border border-zinc-600 bg-zinc-950/80"
            >
                <div class="flex flex-wrap items-center gap-2 border-b border-zinc-700 bg-zinc-900/90 px-3 py-2">
                    <button
                        type="button"
                        class="rounded-md border border-zinc-600 px-2 py-1 text-xs text-zinc-100 hover:bg-zinc-800 disabled:opacity-40"
                        :disabled="globalPage <= 1 || loading || !!error"
                        @click="prevPage"
                    >
                        Anterior
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-zinc-600 px-2 py-1 text-xs text-zinc-100 hover:bg-zinc-800 disabled:opacity-40"
                        :disabled="globalPage >= totalPages || loading || !!error || totalPages === 0"
                        @click="nextPage"
                    >
                        Próxima
                    </button>
                    <span v-if="totalPages > 0" class="text-xs text-zinc-400">
                        Página {{ globalPage }} / {{ totalPages }}
                    </span>
                    <div class="mx-1 h-4 w-px bg-zinc-600" />
                    <button
                        type="button"
                        class="rounded-md border border-zinc-600 p-1.5 text-zinc-100 hover:bg-zinc-800"
                        title="Diminuir zoom"
                        @click="zoomOut"
                    >
                        <ZoomOut class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-zinc-600 p-1.5 text-zinc-100 hover:bg-zinc-800"
                        title="Aumentar zoom"
                        @click="zoomIn"
                    >
                        <ZoomIn class="h-4 w-4" />
                    </button>
                    <span class="text-xs text-zinc-500">{{ Math.round(zoomMul * 100) }}%</span>
                    <div class="mx-1 h-4 w-px bg-zinc-600" />
                    <button
                        type="button"
                        class="rounded-md border border-zinc-600 px-2 py-1 text-xs text-zinc-100 hover:bg-zinc-800"
                        @click="toggleFullscreen"
                    >
                        <span class="inline-flex items-center gap-1">
                            <component :is="isFullscreen ? Minimize2 : Maximize2" class="h-3.5 w-3.5" />
                            {{ isFullscreen ? 'Sair' : 'Tela cheia' }}
                        </span>
                    </button>
                    <button
                        type="button"
                        class="ml-auto inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs font-medium transition"
                        :class="
                            userLikedLocal
                                ? 'border-rose-400 bg-rose-500/20 text-rose-100'
                                : 'border-zinc-600 text-zinc-100 hover:bg-zinc-800'
                        "
                        @click="toggleLike"
                    >
                        <Heart class="h-3.5 w-3.5" :class="userLikedLocal ? 'fill-current' : ''" />
                        Gostei
                        <span class="tabular-nums text-zinc-300">({{ likesCountLocal }})</span>
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2 border-b border-zinc-700 bg-zinc-900/60 px-3 py-2">
                    <Highlighter class="h-4 w-4 shrink-0 text-zinc-400" />
                    <span class="text-xs text-zinc-500">Marcações:</span>
                    <button
                        type="button"
                        class="h-7 w-10 rounded border border-zinc-600 bg-yellow-400/90"
                        :class="colorBtn('yellow')"
                        title="Amarelo"
                        @click="highlightColor = highlightColor === 'yellow' ? null : 'yellow'"
                    />
                    <button
                        type="button"
                        class="h-7 w-10 rounded border border-zinc-600 bg-green-400/90"
                        :class="colorBtn('green')"
                        title="Verde"
                        @click="highlightColor = highlightColor === 'green' ? null : 'green'"
                    />
                    <button
                        type="button"
                        class="h-7 w-10 rounded border border-zinc-600 bg-pink-400/90"
                        :class="colorBtn('pink')"
                        title="Rosa"
                        @click="highlightColor = highlightColor === 'pink' ? null : 'pink'"
                    />
                    <span v-if="highlightColor" class="text-xs text-amber-200/90">
                        Arraste sobre a página para marcar. Clique na cor de novo para cancelar.
                    </span>
                </div>

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

                    <div class="relative mx-auto inline-block">
                        <canvas ref="canvasRef" class="max-w-full shadow-lg" />
                        <div
                            v-if="!loading && !error && totalPages > 0"
                            class="absolute inset-0 z-[3]"
                            :class="highlightColor ? 'pointer-events-auto' : 'pointer-events-none'"
                            @pointerdown.prevent="overlayPointerDown"
                            @pointermove.prevent="overlayPointerMove"
                            @pointerup.prevent="overlayPointerUp"
                            @pointerleave="overlayPointerUp"
                        >
                            <div
                                v-for="h in currentPageHighlights"
                                :key="h.id"
                                class="pointer-events-auto absolute cursor-pointer mix-blend-multiply"
                                :class="{
                                    'bg-yellow-400/45': h.color === 'yellow',
                                    'bg-green-400/45': h.color === 'green',
                                    'bg-pink-400/45': h.color === 'pink',
                                }"
                                :style="{
                                    left: `${h.x * 100}%`,
                                    top: `${h.y * 100}%`,
                                    width: `${h.width * 100}%`,
                                    height: `${h.height * 100}%`,
                                }"
                                title="Duplo clique para remover"
                                @dblclick.prevent="removeHighlight(h.id)"
                            />
                            <div
                                v-if="selectionRectCss && selecting"
                                class="pointer-events-none absolute border-2 border-dashed border-amber-300 bg-amber-400/20"
                                :style="selectionRectCss"
                            />
                        </div>
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

                    <div
                        v-if="loading"
                        class="absolute inset-0 z-[4] flex items-center justify-center bg-zinc-950/60 text-sm text-zinc-300"
                    >
                        Carregando...
                    </div>
                    <div
                        v-else-if="error"
                        class="absolute inset-0 z-[4] flex items-center justify-center bg-zinc-950/80 p-4 text-center text-sm text-red-200"
                    >
                        {{ error }}
                    </div>
                </div>
            </div>

            <!-- Miniaturas -->
            <aside
                class="flex w-full shrink-0 flex-col rounded-lg border border-zinc-600 bg-zinc-900/50 lg:w-44"
            >
                <p class="border-b border-zinc-700 px-2 py-2 text-center text-xs font-medium text-zinc-400">
                    Páginas
                </p>
                <div ref="thumbsScrollRef" class="max-h-[min(70vh,520px)] overflow-y-auto p-2">
                    <button
                        v-for="pg in totalPages"
                        :key="pg"
                        :data-page="pg"
                        type="button"
                        class="mb-2 w-full rounded-md border p-1 transition"
                        :class="
                            pg === globalPage
                                ? 'border-[var(--ma-primary,#0ea5e9)] ring-1 ring-[var(--ma-primary,#0ea5e9)]'
                                : 'border-zinc-700 hover:border-zinc-500'
                        "
                        @click="goToGlobalPage(pg)"
                    >
                        <canvas :ref="(el) => setThumbRef(pg, el)" class="mx-auto block h-auto w-full rounded bg-white" />
                        <span class="mt-1 block text-center text-[10px] text-zinc-500">{{ pg }}</span>
                    </button>
                </div>
            </aside>
        </div>
    </div>
</template>

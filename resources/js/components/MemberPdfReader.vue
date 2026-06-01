<script setup>
import { ref, shallowRef, watch, onMounted, onUnmounted, computed, nextTick, defineExpose } from 'vue';
import * as pdfjsLib from 'pdfjs-dist';
import PdfJsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?worker';
import PdfJsWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { loadPdfDocument } from '@/lib/pdfjsLoad';
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
    return loadPdfDocument(url, {
        disableWorker: forceMainThread || !pdfWorkerAvailable,
    });
}

const props = defineProps({
    files: { type: Array, required: true },
    baseUrl: { type: String, required: true },
    lessonId: { type: [Number, String], required: true },
    likesCount: { type: Number, default: 0 },
    userLiked: { type: Boolean, default: false },
    variant: { type: String, default: '' },
    hideLikeButton: { type: Boolean, default: false },
});

const isLessonVariant = computed(() => props.variant === 'lesson');

const emit = defineEmits(['last-page-reached']);

const canvasRef = ref(null);
const nextPeekCanvasRef = ref(null);
const canvasHostRef = ref(null);
const fullscreenRootRef = ref(null);
const thumbsScrollDesktopRef = ref(null);
const thumbsScrollMobileRef = ref(null);
/** Miniaturas coluna direita no modo não-aula */
const thumbsScrollLegacyRef = ref(null);
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

/** Aula PDF em viewport estreita (< 768px): faixa horizontal, pinça e scroll */
const isLessonMobile = computed(() => isLessonVariant.value && !isDesktopViewport.value);

/** Aula no desktop, fora de tela cheia (layout de scroll próprio) */
const isLessonDesktopViewer = computed(
    () => isLessonVariant.value && isDesktopViewport.value && !isFullscreen.value
);

/** Segmentos tipo Stories no topo; acima disso usamos barra única + texto */
const PDF_STORY_STYLE_MAX_PAGES = 30;

/** Páginas 1..N para segmentos estilo Stories (mobile, N ≤ 30) */
const lessonStorySegments = computed(() => {
    if (totalPages.value < 2 || totalPages.value > PDF_STORY_STYLE_MAX_PAGES) return [];
    return Array.from({ length: totalPages.value }, (_, i) => i + 1);
});

const lessonPdfProgressPct = computed(() => {
    if (totalPages.value <= 0) return 0;
    return Math.min(100, Math.max(0, (globalPage.value / totalPages.value) * 100));
});

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

function hostContentBox(host) {
    const s = getComputedStyle(host);
    const padX = parseFloat(s.paddingLeft) + parseFloat(s.paddingRight);
    const padY = parseFloat(s.paddingTop) + parseFloat(s.paddingBottom);
    return {
        w: Math.max(80, host.clientWidth - padX),
        h: Math.max(80, host.clientHeight - padY),
    };
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
    const { w: cw, h: ch } = hostContentBox(host);
    const fitWhole = Math.min(cw / baseViewport.width, ch / baseViewport.height, 8);
    const fitWidth = Math.min(cw / baseViewport.width, 8);
    let fit;
    if (isLessonVariant.value) {
        fit = fitWhole * zoomMul.value;
    } else if (isFullscreen.value) {
        fit = fitWhole;
    } else {
        fit = fitWidth * zoomMul.value;
    }
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
    await nextTick();
    if (isLessonDesktopViewer.value) {
        ensureCanvasVisibleAtTop();
    }
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

function resetCanvasHostScroll() {
    ensureCanvasVisibleAtTop();
}

/** Scroll do host: topo sempre visível; horizontal centrado quando há overflow */
function ensureCanvasVisibleAtTop() {
    const host = canvasHostRef.value;
    if (!host) return;
    host.scrollTop = 0;
    const overflowX = host.scrollWidth - host.clientWidth;
    host.scrollLeft = overflowX > 2 ? Math.floor(overflowX / 2) : 0;
}

function prevPage() {
    if (globalPage.value <= 1) return;
    globalPage.value -= 1;
    resetCanvasHostScroll();
}

function nextPage() {
    if (globalPage.value >= totalPages.value) return;
    globalPage.value += 1;
    resetCanvasHostScroll();
}

function zoomIn() {
    zoomMul.value = Math.min(3, Math.round((zoomMul.value + 0.25) * 100) / 100);
    nextTick(() => resetCanvasHostScrollAfterZoom());
}

function zoomOut() {
    zoomMul.value = Math.max(0.5, Math.round((zoomMul.value - 0.25) * 100) / 100);
    nextTick(() => resetCanvasHostScrollAfterZoom());
}

/** Após zoom por botões no desktop (aula, fora de tela cheia), ancora no topo da página */
function resetCanvasHostScrollAfterZoom() {
    if (!isLessonVariant.value || isLessonMobile.value || isFullscreen.value) return;
    resetCanvasHostScroll();
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

function hostHasVerticalOverflow() {
    const el = canvasHostRef.value;
    if (!el) return false;
    return el.scrollHeight > el.clientHeight + 6;
}

function hostHasHorizontalOverflow() {
    const el = canvasHostRef.value;
    if (!el) return false;
    return el.scrollWidth > el.clientWidth + 6;
}

function onWheelPageFlip(e) {
    const el = canvasHostRef.value;
    if (!el) return;

    /** Modo aula (página inteira): sem scroll vertical, rodinha troca página. Com zoom/pan, extremos igual ao legado. */
    if (isLessonVariant.value) {
        const hasScroll = hostHasVerticalOverflow();
        const atTop = el.scrollTop <= 0;
        const atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 1;
        if (Math.abs(e.deltaY) < 8) return;
        const now = Date.now();

        const canFlipFwd = globalPage.value < totalPages.value;
        const canFlipBack = globalPage.value > 1;

        if (!hasScroll && canFlipFwd && e.deltaY > 0) {
            if (now < pageFlipCooldown) {
                e.preventDefault();
                return;
            }
            e.preventDefault();
            pageFlipCooldown = now + PAGE_FLIP_COOLDOWN_MS;
            nextPage();
            return;
        }
        if (!hasScroll && canFlipBack && e.deltaY < 0) {
            if (now < pageFlipCooldown) {
                e.preventDefault();
                return;
            }
            e.preventDefault();
            pageFlipCooldown = now + PAGE_FLIP_COOLDOWN_MS;
            prevPage();
            return;
        }

        if (hasScroll) {
            if (now < pageFlipCooldown) {
                if ((e.deltaY > 0 && atBottom) || (e.deltaY < 0 && atTop)) e.preventDefault();
                return;
            }
            if (e.deltaY > 0 && atBottom && canFlipFwd) {
                e.preventDefault();
                pageFlipCooldown = now + PAGE_FLIP_COOLDOWN_MS;
                nextPage();
            } else if (e.deltaY < 0 && atTop && canFlipBack) {
                e.preventDefault();
                pageFlipCooldown = now + PAGE_FLIP_COOLDOWN_MS;
                prevPage();
            }
        }
        return;
    }

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

let touchGesturesBoundHost = null;

/** Ignora watcher de zoom durante pinça para não disparar dois renders por frame */
let pinchZoomBypassWatch = false;
/** Estado da pinça (dois dedos): distância inicial e zoom no início */
let pinchSession = null;
let pinchRenderRaf = null;
/** Arraste com 1 dedo para pan quando há zoom (scroll programático — mais fiável que flex+overflow no iOS) */
let panSession = null;
let suppressPanAfterPinch = false;

function lessonTouchGesturesEnabled() {
    if (!isLessonVariant.value || loading.value || !!error.value) return false;
    return isLessonMobile.value || isFullscreen.value;
}

function canLessonTouchPan() {
    return lessonTouchGesturesEnabled() && zoomMul.value > 1.01 && !highlightColor.value;
}

function pinchTouchDistance(ts) {
    if (ts.length < 2) return 0;
    const [a, b] = ts;
    return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
}

function cancelPinchRenderRaf() {
    if (pinchRenderRaf != null) {
        cancelAnimationFrame(pinchRenderRaf);
        pinchRenderRaf = null;
    }
}

function flushPinchRender() {
    cancelPinchRenderRaf();
    void renderCurrentPage();
}

function bindLessonTouchGestures() {
    unbindLessonTouchGestures();
    const host = canvasHostRef.value;
    if (!host || !lessonTouchGesturesEnabled()) return;
    host.addEventListener('touchstart', onLessonTouchStart, { passive: true });
    host.addEventListener('touchmove', onLessonTouchMove, { passive: false });
    host.addEventListener('touchend', onLessonTouchEnd, { passive: true });
    host.addEventListener('touchcancel', onLessonTouchCancel, { passive: true });
    touchGesturesBoundHost = host;
}

function unbindLessonTouchGestures() {
    if (!touchGesturesBoundHost) return;
    touchGesturesBoundHost.removeEventListener('touchstart', onLessonTouchStart);
    touchGesturesBoundHost.removeEventListener('touchmove', onLessonTouchMove);
    touchGesturesBoundHost.removeEventListener('touchend', onLessonTouchEnd);
    touchGesturesBoundHost.removeEventListener('touchcancel', onLessonTouchCancel);
    touchGesturesBoundHost = null;
    pinchSession = null;
    panSession = null;
    suppressPanAfterPinch = false;
    pinchZoomBypassWatch = false;
    cancelPinchRenderRaf();
}

function onLessonTouchStart(e) {
    if (!lessonTouchGesturesEnabled()) return;

    if (e.touches.length >= 2) {
        panSession = null;
        const d = pinchTouchDistance(e.touches);
        if (d > 8) {
            pinchZoomBypassWatch = true;
            pinchSession = { startDist: d, startZoom: zoomMul.value };
        }
        return;
    }

    if (e.touches.length !== 1 || suppressPanAfterPinch || !canLessonTouchPan()) return;

    const host = canvasHostRef.value;
    if (!host) return;
    panSession = {
        startX: e.touches[0].clientX,
        startY: e.touches[0].clientY,
        scrollLeft: host.scrollLeft,
        scrollTop: host.scrollTop,
    };
}

function onLessonTouchMove(e) {
    if (!lessonTouchGesturesEnabled()) return;

    if (e.touches.length >= 2) {
        if (!pinchSession) {
            const d0 = pinchTouchDistance(e.touches);
            if (d0 > 8) {
                pinchZoomBypassWatch = true;
                pinchSession = { startDist: d0, startZoom: zoomMul.value };
                panSession = null;
            }
        }
        if (!pinchSession) return;

        e.preventDefault();
        const d = pinchTouchDistance(e.touches);
        if (pinchSession.startDist > 8 && d > 8) {
            const scale = d / pinchSession.startDist;
            let next = pinchSession.startZoom * scale;
            next = Math.min(3, Math.max(0.5, Math.round(next * 100) / 100));
            zoomMul.value = next;
            if (!pinchRenderRaf) {
                pinchRenderRaf = requestAnimationFrame(() => {
                    pinchRenderRaf = null;
                    void renderCurrentPage();
                });
            }
        }
        return;
    }

    if (!panSession || e.touches.length !== 1 || pinchSession) return;

    e.preventDefault();
    const host = canvasHostRef.value;
    if (!host) return;
    const dx = panSession.startX - e.touches[0].clientX;
    const dy = panSession.startY - e.touches[0].clientY;
    host.scrollLeft = panSession.scrollLeft + dx;
    host.scrollTop = panSession.scrollTop + dy;
}

function onLessonTouchEnd(e) {
    if (!lessonTouchGesturesEnabled()) return;

    if (pinchSession && e.touches.length < 2) {
        pinchSession = null;
        pinchZoomBypassWatch = false;
        flushPinchRender();
        if (e.touches.length >= 1) {
            suppressPanAfterPinch = true;
        }
    }

    if (!e.touches.length) {
        panSession = null;
        suppressPanAfterPinch = false;
    } else if (e.touches.length === 1 && !pinchSession && canLessonTouchPan() && !suppressPanAfterPinch) {
        const host = canvasHostRef.value;
        if (host) {
            panSession = {
                startX: e.touches[0].clientX,
                startY: e.touches[0].clientY,
                scrollLeft: host.scrollLeft,
                scrollTop: host.scrollTop,
            };
        }
    }
}

function onLessonTouchCancel() {
    if (pinchSession) {
        pinchSession = null;
        pinchZoomBypassWatch = false;
        flushPinchRender();
    }
    panSession = null;
    suppressPanAfterPinch = false;
    cancelPinchRenderRaf();
}

function onFullscreenChange() {
    const nowFs = !!document.fullscreenElement;
    const entering = nowFs && !isFullscreen.value;
    isFullscreen.value = nowFs;
    if (entering && canvasHostRef.value) {
        resetCanvasHostScroll();
        revealFullscreenNav();
    }
    if (!nowFs) {
        fullscreenNavVisible.value = false;
        if (fullscreenNavTimer) {
            clearTimeout(fullscreenNavTimer);
            fullscreenNavTimer = null;
        }
    }
    void nextTick().then(() => {
        renderCurrentPage();
        bindLessonTouchGestures();
    });
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
    resetCanvasHostScroll();
}

function syncThumbsScroll() {
    const sel = `[data-page="${globalPage.value}"]`;
    const containers = [
        thumbsScrollDesktopRef.value,
        thumbsScrollMobileRef.value,
        thumbsScrollLegacyRef.value,
        fsNavScrollRef.value,
    ];
    for (const container of containers) {
        if (!container) continue;
        const target = container.querySelector(sel);
        if (target) target.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
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

async function downloadPdf() {
    const files = props.files || [];
    const idx = currentLocal.value.fileIndex;
    const item = files[idx];
    if (!item?.url) return;
    const filename = (item.name || 'documento.pdf').replace(/[^\w.\-\u00C0-\u024F]+/g, '_');
    let downloadUrl;
    try {
        downloadUrl = new URL(item.url, window.location.origin);
        downloadUrl.searchParams.set('download', '1');
    } catch {
        showToast('Não foi possível iniciar o download.');
        return;
    }
    try {
        const res = await fetch(downloadUrl.toString(), { credentials: 'same-origin' });
        if (!res.ok) throw new Error('download failed');
        const blob = await res.blob();
        const objectUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = objectUrl;
        a.download = filename;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(objectUrl);
        showToast('Download iniciado. Verifique sua pasta de downloads.');
    } catch {
        window.location.assign(downloadUrl.toString());
    }
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
    resetCanvasHostScroll();
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

watch(zoomMul, async () => {
    if (pinchZoomBypassWatch) return;
    await renderCurrentPage();
    resetCanvasHostScrollAfterZoom();
    void nextTick(() => bindLessonTouchGestures());
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
        bindLessonTouchGestures();
    });
});

onUnmounted(() => {
    unbindWheelHost();
    unbindLessonTouchGestures();
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

/** Host do PDF no modo aula: no mobile/tela cheia, scroll + gestos touch */
const pdfCanvasHostClasses = computed(() => {
    /** h-0 + flex-1: força altura limitada pelo pai para overflow-auto funcionar */
    const shell = 'relative h-0 min-h-0 min-w-0 flex-1 overflow-auto';
    if (!isLessonVariant.value) {
        return `${shell} flex aspect-video items-start justify-center bg-zinc-950/80 p-3`;
    }
    const mobileOrFs = isLessonMobile.value || isFullscreen.value;
    if (mobileOrFs) {
        const classes = [
            shell,
            'pdf-lesson-mobile-scroll-host bg-[var(--lesson-pdf-bg)]',
            isFullscreen.value ? 'p-3' : 'px-3 py-3 sm:px-6 sm:py-6',
        ];
        if (zoomMul.value > 1.01) {
            classes.push('pdf-lesson-touch-pan-active');
        }
        return classes;
    }
    return [
        shell,
        'pdf-lesson-desktop-scroll-host bg-[var(--lesson-pdf-bg)]',
        isFullscreen.value ? 'p-3' : 'p-4 sm:p-6',
    ];
});

const lessonMobileScrollInner = computed(
    () => isLessonVariant.value && (isLessonMobile.value || isFullscreen.value)
);

const lessonScrollShellClass = computed(() => {
    if (isLessonDesktopViewer.value) {
        return zoomMul.value <= 1.01
            ? 'pdf-lesson-scroll-content pdf-lesson-scroll-content--fit'
            : 'pdf-lesson-scroll-content pdf-lesson-scroll-content--zoomed';
    }
    if (lessonMobileScrollInner.value) return '';
    return 'relative mx-auto';
});

const lessonCanvasWrapClass = computed(() => {
    if (!isLessonVariant.value) return 'relative inline-block';
    if (isLessonDesktopViewer.value) return 'relative pdf-lesson-canvas-wrap';
    if (lessonMobileScrollInner.value) return 'relative pdf-lesson-mobile-inner';
    return 'relative inline-block';
});

watch(isDesktopViewport, (isDesktop) => {
    if (!isDesktop) {
        fullscreenNavVisible.value = false;
    }
});

watch(isLessonMobile, () => {
    void nextTick(() => bindLessonTouchGestures());
});

watch(isFullscreen, () => {
    void nextTick(() => {
        bindLessonTouchGestures();
        void renderCurrentPage();
    });
});

watch([isLessonVariant, loading, error], () => {
    void nextTick(() => bindLessonTouchGestures());
});
</script>

<template>
    <div
        class="member-pdf-reader flex h-full min-h-0 flex-col"
        :class="isLessonVariant ? '' : 'gap-3'"
    >
        <div
            v-if="toastMessage"
            class="shrink-0 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-center text-sm text-emerald-800"
            :class="isLessonVariant ? 'mx-2 mt-2' : 'border-emerald-500/40 bg-emerald-500/15 text-emerald-100'"
        >
            {{ toastMessage }}
        </div>

        <div
            ref="fullscreenRootRef"
            class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
            :class="isLessonVariant ? 'bg-[var(--lesson-surface)]' : 'rounded-lg border border-zinc-600 bg-zinc-950/80'"
        >
            <!-- Toolbar -->
            <div
                class="flex h-[42px] shrink-0 flex-wrap items-center gap-0.5 border-b px-2 sm:px-3.5"
                :class="isLessonVariant ? 'border-[var(--lesson-border)] bg-[var(--lesson-surface)]' : 'border-zinc-700 bg-zinc-900/90'"
            >
                <div
                    class="flex items-center gap-0.5 border-r pr-2 mr-1"
                    :class="isLessonVariant ? 'border-[var(--lesson-border)]' : 'border-zinc-600 pr-2.5'"
                >
                    <button
                        type="button"
                        class="pdf-tb-btn"
                        :class="isLessonVariant ? 'lesson-tb' : 'dark-tb'"
                        :disabled="globalPage <= 1 || loading || !!error"
                        @click="prevPage"
                    >
                        ‹
                    </button>
                    <span v-if="totalPages > 0" class="flex items-center gap-1 text-xs font-semibold" :class="isLessonVariant ? 'text-[var(--lesson-text-2)]' : 'text-zinc-400'">
                        <input
                            :value="globalPage"
                            type="number"
                            min="1"
                            :max="totalPages"
                            class="w-9 rounded-md border px-1 py-0.5 text-center text-xs"
                            :class="isLessonVariant ? 'border-[var(--lesson-border)] bg-[var(--lesson-bg)] text-[var(--lesson-text)]' : 'border-zinc-600 bg-zinc-800 text-zinc-100'"
                            @change="(e) => goToGlobalPage(Number(e.target.value))"
                        />
                        <span>/ {{ totalPages }}</span>
                    </span>
                    <button
                        type="button"
                        class="pdf-tb-btn"
                        :class="isLessonVariant ? 'lesson-tb' : 'dark-tb'"
                        :disabled="globalPage >= totalPages || loading || !!error || totalPages === 0"
                        @click="nextPage"
                    >
                        ›
                    </button>
                </div>
                <div
                    class="flex items-center gap-0.5 border-r pr-2 mr-1"
                    :class="isLessonVariant ? 'border-[var(--lesson-border)]' : 'border-zinc-600'"
                >
                    <button type="button" class="pdf-tb-btn" :class="isLessonVariant ? 'lesson-tb' : 'dark-tb'" title="Diminuir zoom" @click="zoomOut">
                        <ZoomOut class="h-3.5 w-3.5" />
                    </button>
                    <span class="min-w-[2rem] text-center text-xs" :class="isLessonVariant ? 'text-[var(--lesson-text-3)]' : 'text-zinc-500'">{{ Math.round(zoomMul * 100) }}%</span>
                    <button type="button" class="pdf-tb-btn" :class="isLessonVariant ? 'lesson-tb' : 'dark-tb'" title="Aumentar zoom" @click="zoomIn">
                        <ZoomIn class="h-3.5 w-3.5" />
                    </button>
                </div>
                <button type="button" class="pdf-tb-btn hidden sm:inline-flex" :class="isLessonVariant ? 'lesson-tb' : 'dark-tb'" @click="toggleFullscreen">
                    <component :is="isFullscreen ? Minimize2 : Maximize2" class="h-3.5 w-3.5" />
                    <span class="hidden md:inline">{{ isFullscreen ? 'Sair' : 'Ampliar' }}</span>
                </button>
                <div
                    class="flex items-center gap-1.5 border-l pl-2 ml-1"
                    :class="isLessonVariant ? 'border-[var(--lesson-border)]' : 'border-zinc-600'"
                >
                    <Highlighter v-if="!isLessonVariant" class="h-4 w-4 shrink-0 text-zinc-400" />
                    <span v-if="!isLessonVariant" class="text-xs text-zinc-500">Marcações:</span>
                    <button
                        type="button"
                        class="h-4 w-4 rounded-full border-2 border-transparent bg-yellow-400 transition hover:scale-110"
                        :class="highlightColor === 'yellow' ? 'border-[var(--lesson-text)]' : ''"
                        title="Amarelo"
                        @click="highlightColor = highlightColor === 'yellow' ? null : 'yellow'"
                    />
                    <button
                        type="button"
                        class="h-4 w-4 rounded-full border-2 border-transparent bg-green-400 transition hover:scale-110"
                        :class="highlightColor === 'green' ? 'border-[var(--lesson-text)]' : ''"
                        title="Verde"
                        @click="highlightColor = highlightColor === 'green' ? null : 'green'"
                    />
                    <button
                        type="button"
                        class="h-4 w-4 rounded-full border-2 border-transparent bg-pink-400 transition hover:scale-110"
                        :class="highlightColor === 'pink' ? 'border-[var(--lesson-text)]' : ''"
                        title="Rosa"
                        @click="highlightColor = highlightColor === 'pink' ? null : 'pink'"
                    />
                </div>
                <div class="flex-1" />
                <button
                    v-if="!hideLikeButton"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-semibold transition"
                    :class="
                        userLikedLocal
                            ? isLessonVariant
                                ? 'border-rose-300 bg-rose-50 text-rose-600'
                                : 'border-rose-400 bg-rose-500/20 text-rose-100'
                            : isLessonVariant
                              ? 'border-[var(--lesson-border)] text-[var(--lesson-text-2)] hover:bg-[var(--lesson-bg)]'
                              : 'border-zinc-600 text-zinc-100 hover:bg-zinc-800'
                    "
                    @click="toggleLike"
                >
                    <Heart class="h-3.5 w-3.5" :class="userLikedLocal ? 'fill-current' : ''" />
                    <span class="tabular-nums">{{ likesCountLocal }}</span>
                </button>
            </div>

            <div
                class="flex min-h-0 flex-1 overflow-hidden"
                :class="isLessonVariant ? (isDesktopViewport ? 'flex-row' : 'flex-col') : 'flex-row'"
            >
                <!-- Miniaturas verticais à esquerda (aula, desktop md+) -->
                <aside
                    v-if="isLessonVariant && isDesktopViewport"
                    class="flex w-[72px] shrink-0 flex-col overflow-x-hidden border-r border-[var(--lesson-border)] bg-[var(--lesson-bg2)]"
                >
                    <div
                        ref="thumbsScrollDesktopRef"
                        class="pdf-lesson-thumbs-desktop flex min-h-0 flex-1 flex-col gap-1.5 overflow-x-hidden overflow-y-auto px-1.5 py-2 scroll-smooth"
                    >
                        <button
                            v-for="pg in totalPages"
                            :key="`lesson-desk-${pg}`"
                            :data-page="pg"
                            type="button"
                            class="relative mx-auto h-[72px] w-[52px] shrink-0 overflow-hidden rounded-md border-2 bg-white shadow-sm transition"
                            :class="pg === globalPage ? 'border-[var(--student-primary,#0047b3)]' : 'border-transparent hover:border-[var(--lesson-border2)]'"
                            @click="goToGlobalPage(pg)"
                        >
                            <canvas
                                :ref="(el) => setThumbRef(pg, el)"
                                class="mx-auto block h-auto max-h-[64px] w-full max-w-full"
                            />
                            <span class="absolute bottom-0.5 right-1 text-[9px] font-bold text-[var(--lesson-text-3)]">{{
                                pg
                            }}</span>
                        </button>
                    </div>
                </aside>

                <!-- Coluna principal: PDF (+ progresso só em mobile) -->
                <div
                    class="flex min-h-0 min-w-0 flex-1 overflow-hidden"
                    :class="isLessonVariant && isLessonMobile ? 'flex-col' : ''"
                >
                    <!-- Progresso estilo Stories (mobile, ≤30 páginas) ou barra + texto (>30) -->
                    <div
                        v-if="isLessonVariant && isLessonMobile && totalPages > 1 && !loading && !error"
                        class="shrink-0 border-b border-[var(--lesson-border)] bg-[var(--lesson-surface)] px-3 pb-2 pt-2"
                        role="progressbar"
                        aria-label="Progresso de leitura do PDF"
                        :aria-valuenow="globalPage"
                        aria-valuemin="1"
                        :aria-valuemax="totalPages"
                    >
                        <template v-if="totalPages <= PDF_STORY_STYLE_MAX_PAGES">
                            <div class="flex gap-1">
                                <div
                                    v-for="seg in lessonStorySegments"
                                    :key="seg"
                                    class="pdf-story-segment h-[3px] flex-1 min-w-[3px] rounded-full transition-colors"
                                    :class="{
                                        'pdf-story-segment--done': seg < globalPage,
                                        'pdf-story-segment--current': seg === globalPage,
                                        'pdf-story-segment--future': seg > globalPage,
                                    }"
                                />
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-3">
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-[var(--lesson-border)]"
                                    role="presentation"
                                >
                                    <div
                                        class="h-full rounded-full transition-[width] duration-200 ease-out"
                                        :style="{
                                            width: `${lessonPdfProgressPct}%`,
                                            backgroundColor: 'var(--student-primary, #0047b3)',
                                        }"
                                    />
                                </div>
                                <p
                                    class="shrink-0 text-center text-[11px] font-semibold tabular-nums text-[var(--lesson-text-2)] sm:text-right"
                                >
                                    Página {{ globalPage }} de {{ totalPages }}
                                </p>
                            </div>
                        </template>
                    </div>

                    <!-- Área scroll do PDF -->
                    <div ref="canvasHostRef" :class="pdfCanvasHostClasses">
                        <div
                            v-if="isFullscreen && !loading && !error && totalPages > 0"
                            class="pointer-events-none absolute left-4 top-4 z-[6] rounded-full border border-zinc-600 bg-zinc-900/85 px-3 py-1 text-sm font-semibold text-white shadow-lg"
                        >
                            Página {{ globalPage }} / {{ totalPages }}
                        </div>

                        <div :class="lessonScrollShellClass">
                            <div :class="lessonCanvasWrapClass">
                            <canvas
                                ref="canvasRef"
                                :class="isLessonVariant ? 'pdf-lesson-main-canvas block shadow-lg' : 'max-w-full shadow-lg'"
                            />
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
                            class="absolute inset-0 z-[4] flex items-center justify-center text-sm"
                            :class="isLessonVariant ? 'bg-white/70 text-[var(--lesson-text-2)]' : 'bg-zinc-950/60 text-zinc-300'"
                        >
                            Carregando...
                        </div>
                        <div
                            v-else-if="error"
                            class="absolute inset-0 z-[4] flex items-center justify-center p-4 text-center text-sm text-red-600"
                            :class="isLessonVariant ? 'bg-white/90' : 'bg-zinc-950/80 text-red-200'"
                        >
                            {{ error }}
                        </div>
                    </div>
                </div>

                <!-- Miniaturas em faixa horizontal (aula só em mobile / < md) -->
                <aside
                    v-if="isLessonVariant && isLessonMobile"
                    class="shrink-0 border-t border-[var(--lesson-border)] bg-[var(--lesson-bg2)]"
                >
                    <div
                        ref="thumbsScrollMobileRef"
                        class="flex max-h-[92px] flex-row flex-nowrap gap-2 overflow-x-auto overflow-y-hidden px-2 py-2 scroll-smooth"
                    >
                        <button
                            v-for="pg in totalPages"
                            :key="`lesson-mob-${pg}`"
                            :data-page="pg"
                            type="button"
                            class="relative h-[72px] w-[52px] shrink-0 overflow-hidden rounded-md border-2 bg-white shadow-sm transition"
                            :class="pg === globalPage ? 'border-[var(--student-primary,#0047b3)]' : 'border-transparent hover:border-[var(--lesson-border2)]'"
                            @click="goToGlobalPage(pg)"
                        >
                            <canvas :ref="(el) => setThumbRef(pg, el)" class="mx-auto block h-auto max-h-[64px] w-full" />
                            <span class="absolute bottom-0.5 right-1 text-[9px] font-bold text-[var(--lesson-text-3)]">{{ pg }}</span>
                        </button>
                    </div>
                </aside>

                <!-- Miniaturas coluna (modo legado não-aula) -->
                <aside
                    v-if="!isLessonVariant"
                    class="flex w-full shrink-0 flex-col rounded-lg border border-zinc-600 bg-zinc-900/50 lg:w-44"
                >
                    <p class="border-b border-zinc-700 px-2 py-2 text-center text-xs font-medium text-zinc-400">Páginas</p>
                    <div ref="thumbsScrollLegacyRef" class="max-h-[min(70vh,520px)] overflow-x-hidden overflow-y-auto p-2">
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
    </div>
</template>

<style scoped>
.pdf-tb-btn.lesson-tb {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    border-radius: 7px;
    border: 1px solid transparent;
    padding: 4px 9px;
    font-size: 13px;
    font-weight: 600;
    color: var(--lesson-text-2);
    transition: background 0.15s, border-color 0.15s;
}
.pdf-tb-btn.lesson-tb:hover:not(:disabled) {
    background: var(--lesson-bg);
    border-color: var(--lesson-border);
    color: var(--lesson-text);
}
.pdf-tb-btn.lesson-tb:disabled {
    opacity: 0.4;
}
.pdf-tb-btn.dark-tb {
    border-radius: 6px;
    border: 1px solid rgb(82 82 91);
    padding: 4px 8px;
    font-size: 12px;
    color: rgb(244 244 245);
}
.pdf-tb-btn.dark-tb:hover:not(:disabled) {
    background: rgb(39 39 42);
}

/* Miniaturas verticais (desktop): só scroll vertical */
.pdf-lesson-thumbs-desktop {
    overflow-x: hidden !important;
    scrollbar-gutter: stable;
}

.pdf-lesson-thumbs-desktop button {
    max-width: 100%;
}

.pdf-lesson-thumbs-desktop canvas {
    max-width: 100%;
    height: auto;
}

/* Aula desktop (fora de tela cheia): scroll em bloco — flex no host cortava o topo com zoom */
.pdf-lesson-desktop-scroll-host {
    display: block;
    text-align: center;
    -webkit-overflow-scrolling: touch;
}

.pdf-lesson-scroll-content--fit {
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    width: 100%;
}

.pdf-lesson-scroll-content--zoomed {
    box-sizing: border-box;
    display: block;
    width: 100%;
    text-align: center;
}

.pdf-lesson-canvas-wrap {
    display: inline-block;
    max-width: none;
    text-align: left;
    vertical-align: top;
}

/* Aula mobile / tela cheia: pinça no JS; pan com 1 dedo quando zoom > 100% */
.pdf-lesson-mobile-scroll-host {
    touch-action: pan-x pan-y;
    -webkit-overflow-scrolling: touch;
    text-align: center;
}

.pdf-lesson-mobile-scroll-host.pdf-lesson-touch-pan-active {
    touch-action: none;
}

.pdf-lesson-mobile-inner {
    display: inline-block;
    min-width: 100%;
    min-height: 100%;
    vertical-align: top;
    text-align: left;
    box-sizing: border-box;
}

/* max-w-full no canvas fazia só a largura encolher dentro do host e mantinha altura CSS → PDF “esticado”. */
.pdf-lesson-main-canvas {
    max-width: none;
    width: auto;
    height: auto;
}

.pdf-story-segment {
    background-color: var(--lesson-border2, rgb(229 231 235));
}
.pdf-story-segment--future {
    opacity: 0.4;
}
.pdf-story-segment--done {
    background-color: var(--student-primary, #0047b3);
    opacity: 1;
}
.pdf-story-segment--current {
    background-color: var(--student-primary, #0047b3);
    opacity: 0.85;
}

</style>

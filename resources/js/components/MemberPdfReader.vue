<script setup>
import { ref, shallowRef, watch, onMounted, onUnmounted, computed, nextTick, defineExpose } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { resolveStudentAreaLogoUrl } from '@/composables/useStudentAreaLogo';
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

/** Logo do white label (Settings → White Label), igual sidebar / hub do aluno */
const loadingLogoUrl = computed(() => {
    const branding = page.props.student_branding ?? {};
    const fromWhiteLabel = resolveStudentAreaLogoUrl(branding, false, false);
    if (fromWhiteLabel) {
        return fromWhiteLabel;
    }

    const app = page.props.appSettings ?? {};
    const fromApp =
        app.app_logo || app.app_logo_dark || app.app_logo_icon || app.app_logo_icon_dark || '';
    if (String(fromApp).trim()) {
        return String(fromApp).trim();
    }

    const cfg = page.props.config ?? {};
    const fromCourse =
        cfg.logos?.logo_light || cfg.logos?.logo_dark || cfg.header?.logo_url || '';
    return String(fromCourse).trim() || null;
});

const loadingStatusText = computed(() => {
    if (loadRetryAttempt.value > 1) {
        return `Tentando novamente (${loadRetryAttempt.value}/${PDF_LOAD_MAX_ATTEMPTS})…`;
    }
    return 'Carregando documento…';
});

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

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
/** Zoom padrão: desktop 250%, mobile 100% (página inteira na área visível) */
const DEFAULT_ZOOM_DESKTOP = 2.5;
const DEFAULT_ZOOM_MOBILE = 1;

function defaultZoomMulForViewport() {
    return isDesktopViewport.value ? DEFAULT_ZOOM_DESKTOP : DEFAULT_ZOOM_MOBILE;
}

const zoomMul = ref(
    typeof window !== 'undefined' && window.innerWidth < 768 ? DEFAULT_ZOOM_MOBILE : DEFAULT_ZOOM_DESKTOP
);
const isFullscreen = ref(false);
/** Modo aula: documento contínuo (todas as páginas empilhadas, scroll nativo) */
const useContinuousScroll = computed(() => isLessonVariant.value);
const continuousWrapRef = ref(null);

/** Lista estável 1..N para v-for (evita ambiguidade do ref numérico) */
const pageNumberList = computed(() =>
    totalPages.value > 0 ? Array.from({ length: totalPages.value }, (_, i) => i + 1) : []
);
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
let resizeDebounceTimer = null;

const HOST_MIN_LAYOUT_PX = 80;
const THUMB_CHUNK_SIZE = 4;
const RESIZE_DEBOUNCE_MS = 50;
const HOST_LAYOUT_WAIT_MS = 4000;
const RENDER_RETRY_MAX = 12;
const RENDER_RETRY_DELAY_MS = 120;
const PDF_LOAD_MAX_ATTEMPTS = 3;
const PDF_LOAD_RETRY_BASE_MS = 1200;

const page = usePage();
const loadRetryAttempt = ref(0);

let renderRetryTimer = null;
const mainCanvasReady = ref(false);
/** PDF contínuo totalmente renderizado (evita overlay em cima de páginas vazias) */
const pdfDocumentReady = ref(false);

/** Invalida carregamentos / miniaturas ao trocar de aula */
let loadGeneration = 0;
let thumbRenderToken = 0;
let thumbIdleId = null;
const thumbsRenderedPages = new Set();

const WHEEL_LISTENER_OPTS = { passive: false };
let wheelHostEl = null;
let continuousScrollHostEl = null;
let pageScrollSyncSuppress = false;
let pageScrollRaf = null;
let documentRenderToken = 0;
const PDF_PAGE_GAP_PX = 12;
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

function syncDefaultZoomForViewport() {
    const target = defaultZoomMulForViewport();
    if (Math.abs(zoomMul.value - target) > 0.01) {
        zoomMul.value = target;
    }
}

function updateViewportFlags() {
    const prev = isDesktopViewport.value;
    isDesktopViewport.value = typeof window !== 'undefined' ? window.innerWidth >= 768 : true;
    if (prev !== isDesktopViewport.value) {
        syncDefaultZoomForViewport();
    }
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

function highlightsForPage(pg) {
    const { fileIndex, pageNum } = globalToLocal(pg);
    const list = highlightsByFile.value[fileIndex] || [];
    return list.filter((h) => h.page === pageNum);
}

function getContinuousPageBlock(pg) {
    const wrap = continuousWrapRef.value;
    if (!wrap) return null;
    return wrap.querySelector(`.pdf-lesson-page-block[data-pdf-page="${pg}"]`);
}

function getContinuousPageCanvas(pg) {
    const block = getContinuousPageBlock(pg);
    return block?.querySelector('canvas.pdf-lesson-page-canvas') ?? null;
}

/**
 * zoomMul 1 = página inteira cabe na área visível; 2.5 = 250% dessa base.
 * Mesma lógica no modo contínuo e na página única da aula.
 */
function computePageFitScale(baseViewport, hostOrBox) {
    const box =
        hostOrBox && typeof hostOrBox.w === 'number'
            ? hostOrBox
            : hostContentBox(hostOrBox);
    const { w: cw, h: ch } = box;
    const fitWidth = Math.min(cw / baseViewport.width, 8);
    const fitWhole = Math.min(cw / baseViewport.width, ch / baseViewport.height, 8);
    if (isLessonVariant.value || useContinuousScroll.value) {
        return fitWhole * zoomMul.value;
    }
    if (isFullscreen.value) {
        return fitWhole;
    }
    return fitWidth * zoomMul.value;
}

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

function hostHasValidLayout(host) {
    if (!host) return false;
    const s = getComputedStyle(host);
    const padX = parseFloat(s.paddingLeft) + parseFloat(s.paddingRight);
    const padY = parseFloat(s.paddingTop) + parseFloat(s.paddingBottom);
    return (
        host.clientWidth - padX >= HOST_MIN_LAYOUT_PX && host.clientHeight - padY >= HOST_MIN_LAYOUT_PX
    );
}

function waitForHostLayout(host, timeoutMs = HOST_LAYOUT_WAIT_MS) {
    return new Promise((resolve) => {
        if (!host) {
            resolve(false);
            return;
        }
        if (hostHasValidLayout(host)) {
            resolve(true);
            return;
        }
        let settled = false;
        const finish = (ok) => {
            if (settled) return;
            settled = true;
            try {
                ro.disconnect();
            } catch (_) {}
            clearTimeout(timer);
            resolve(ok);
        };
        const ro = new ResizeObserver(() => {
            if (hostHasValidLayout(host)) finish(true);
        });
        ro.observe(host);
        const timer = setTimeout(() => finish(hostHasValidLayout(host)), timeoutMs);
        requestAnimationFrame(() => {
            if (hostHasValidLayout(host)) finish(true);
        });
    });
}

/** Dimensões úteis para fit; na aula usa só o viewport visível do host de scroll */
function hostContentBox(host) {
    if (!host) {
        return { w: 640, h: 480 };
    }
    const s = getComputedStyle(host);
    const padX = parseFloat(s.paddingLeft) + parseFloat(s.paddingRight);
    const padY = parseFloat(s.paddingTop) + parseFloat(s.paddingBottom);

    if (isLessonVariant.value || useContinuousScroll.value) {
        let w = Math.max(80, host.clientWidth - padX);
        let h = Math.max(80, host.clientHeight - padY);
        if (h < HOST_MIN_LAYOUT_PX) {
            let el = host.parentElement;
            for (let i = 0; i < 3 && el; i++) {
                h = Math.max(h, el.clientHeight - padY);
                if (h >= HOST_MIN_LAYOUT_PX) break;
                el = el.parentElement;
            }
        }
        if (h < HOST_MIN_LAYOUT_PX) {
            h = Math.max(h, Math.min(700, Math.floor(window.innerHeight * 0.45)));
        }
        return { w, h };
    }

    let w = host.clientWidth - padX;
    let h = host.clientHeight - padY;
    if (w < HOST_MIN_LAYOUT_PX || h < HOST_MIN_LAYOUT_PX) {
        let el = host.parentElement;
        for (let i = 0; i < 4 && el; i++) {
            w = Math.max(w, el.clientWidth - padX);
            h = Math.max(h, el.clientHeight - padY);
            if (w >= HOST_MIN_LAYOUT_PX && h >= HOST_MIN_LAYOUT_PX) break;
            el = el.parentElement;
        }
    }
    if (w < HOST_MIN_LAYOUT_PX || h < HOST_MIN_LAYOUT_PX) {
        w = Math.max(w, Math.min(900, Math.floor(window.innerWidth * 0.55)));
        h = Math.max(h, Math.min(700, Math.floor(window.innerHeight * 0.45)));
    }

    return {
        w: Math.max(80, w),
        h: Math.max(80, h),
    };
}

function clearRenderRetry() {
    if (renderRetryTimer) {
        clearTimeout(renderRetryTimer);
        renderRetryTimer = null;
    }
}

function isMainCanvasPainted() {
    if (useContinuousScroll.value) {
        const first = getContinuousPageCanvas(1);
        const second = getContinuousPageCanvas(2);
        if (!first || first.width <= 16) return false;
        if (!second || second.width <= 16) return true;
        const w1 = first.getBoundingClientRect().width;
        const w2 = second.getBoundingClientRect().width;
        if (w1 < 8 || w2 < 8) return false;
        return Math.abs(w1 - w2) / Math.max(w1, w2) < 0.06;
    }
    const canvas = canvasRef.value;
    return !!canvas && canvas.width > 16 && canvas.height > 16;
}

function scheduleRenderRetry(gen = loadGeneration) {
    clearRenderRetry();
    const attempt = async (left) => {
        if (gen !== loadGeneration || !pdfDocs.value.length) return;
        if (isMainCanvasPainted()) {
            mainCanvasReady.value = true;
            return;
        }
        await nextTick();
        await renderCurrentPage();
        if (gen !== loadGeneration) return;
        if (isMainCanvasPainted()) {
            mainCanvasReady.value = true;
            return;
        }
        if (left > 0) {
            renderRetryTimer = setTimeout(() => void attempt(left - 1), RENDER_RETRY_DELAY_MS);
        }
    };
    void attempt(RENDER_RETRY_MAX);
}

/** Ordem de render: demais páginas primeiro, página 1 por último (layout estável + mesmo zoom) */
function continuousPagePaintOrder() {
    const order = [];
    for (let pg = 2; pg <= totalPages.value; pg++) order.push(pg);
    if (totalPages.value >= 1) order.push(1);
    return order;
}

async function renderPageToCanvas(pg, canvas, layoutBox = null) {
    const host = canvasHostRef.value;
    if (!canvas || !host || !pdfDocs.value.length) return false;

    const { doc, pageNum } = globalToLocal(pg);
    if (!doc) return false;

    const page = await doc.getPage(pageNum);
    const outputScale = window.devicePixelRatio || 1;
    const baseViewport = page.getViewport({ scale: 1 });
    const fit = computePageFitScale(baseViewport, layoutBox ?? host);
    const viewport = page.getViewport({ scale: fit * outputScale });

    const ctx = canvas.getContext('2d');
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    canvas.style.width = `${viewport.width / outputScale}px`;
    canvas.style.height = `${viewport.height / outputScale}px`;

    const task = page.render({ canvasContext: ctx, viewport });
    try {
        await task.promise;
    } catch (e) {
        if (e?.name !== 'RenderingCancelledException') console.warn(e);
        return false;
    }
    return canvas.width > 16 && canvas.height > 16;
}

let renderDocumentInFlight = null;

async function renderDocumentPages() {
    if (renderDocumentInFlight) {
        await renderDocumentInFlight;
    }

    const run = async () => {
        const token = ++documentRenderToken;
        const host = canvasHostRef.value;
        if (!host || !pdfDocs.value.length || !useContinuousScroll.value) return;

        pdfDocumentReady.value = false;
        await nextTick();
        await waitForHostLayout(host);
        if (token !== documentRenderToken) return;

        const layoutBox = hostContentBox(host);
        let anyPainted = false;

        for (const pg of continuousPagePaintOrder()) {
            const canvas = getContinuousPageCanvas(pg);
            if (!canvas) continue;
            const painted = await renderPageToCanvas(pg, canvas, layoutBox);
            if (painted) anyPainted = true;
        }

        if (token !== documentRenderToken) return;

        const canvas1 = getContinuousPageCanvas(1);
        if (canvas1) {
            await renderPageToCanvas(1, canvas1, layoutBox);
            anyPainted = anyPainted || canvas1.width > 16;
        }

        if (token !== documentRenderToken) return;

        mainCanvasReady.value = anyPainted;
        pdfDocumentReady.value = anyPainted;
        void renderNextPeek();

        if (anyPainted) {
            pageScrollSyncSuppress = true;
            globalPage.value = 1;
            host.scrollTop = 0;
            const overflowX = host.scrollWidth - host.clientWidth;
            host.scrollLeft = overflowX > 2 ? Math.floor(overflowX / 2) : 0;
            requestAnimationFrame(() => {
                pageScrollSyncSuppress = false;
            });
        }
    };

    renderDocumentInFlight = run();
    try {
        await renderDocumentInFlight;
    } finally {
        renderDocumentInFlight = null;
    }
}

async function renderCurrentPage() {
    if (useContinuousScroll.value) {
        return renderDocumentPages();
    }

    const canvas = canvasRef.value;
    const host = canvasHostRef.value;
    if (!canvas || !host || !pdfDocs.value.length) return;

    const { doc, pageNum } = globalToLocal(globalPage.value);
    if (!doc) return;

    const page = await doc.getPage(pageNum);
    const outputScale = window.devicePixelRatio || 1;
    const baseViewport = page.getViewport({ scale: 1 });
    const fit = computePageFitScale(baseViewport, host);
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
    mainCanvasReady.value = canvas.width > 16 && canvas.height > 16;
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
    documentRenderToken += 1;
    pdfDocumentReady.value = false;
    thumbsRenderedPages.clear();
    for (const d of pdfDocs.value) {
        try {
            await d.destroy();
        } catch (_) {}
    }
    pdfDocs.value = [];
}

async function loadOnePdfDocument(url) {
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
}

function countTotalPages(docs) {
    let pages = 0;
    for (const pdf of docs) {
        pages += pdf.numPages;
    }
    return pages;
}

async function loadRemainingPdfFiles(rest, gen) {
    try {
        const docs = await Promise.all(rest.map(({ url }) => loadOnePdfDocument(url)));
        if (gen !== loadGeneration) {
            for (const d of docs) {
                try {
                    await d.destroy();
                } catch (_) {}
            }
            return;
        }
        pdfDocs.value = [...pdfDocs.value, ...docs];
        totalPages.value = countTotalPages(pdfDocs.value);
        scheduleThumbRender();
    } catch (e) {
        console.warn('[pdf] Falha ao carregar PDFs adicionais', e);
    }
}

async function loadDocumentsCore(gen, list) {
    const firstDoc = await loadOnePdfDocument(list[0].url);
    if (gen !== loadGeneration) {
        try {
            await firstDoc.destroy();
        } catch (_) {}
        return false;
    }

    pdfDocs.value = [firstDoc];
    totalPages.value = firstDoc.numPages;

    void loadAnnotations();

    if (list.length > 1) {
        void loadRemainingPdfFiles(list.slice(1), gen);
    }

    return true;
}

async function loadDocuments() {
    const gen = ++loadGeneration;
    cancelThumbRender();
    clearRenderRetry();
    thumbsRenderedPages.clear();
    mainCanvasReady.value = false;
    pdfDocumentReady.value = false;
    loading.value = true;
    error.value = '';
    loadRetryAttempt.value = 0;
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

    let lastError = null;
    let loaded = false;

    for (let attempt = 1; attempt <= PDF_LOAD_MAX_ATTEMPTS; attempt++) {
        if (gen !== loadGeneration) return;

        loadRetryAttempt.value = attempt;
        error.value = '';

        if (attempt > 1) {
            await destroyDocs();
            totalPages.value = 0;
            globalPage.value = 1;
            mainCanvasReady.value = false;
            pdfDocumentReady.value = false;
            await sleep(PDF_LOAD_RETRY_BASE_MS * (attempt - 1));
        }

        try {
            const ok = await loadDocumentsCore(gen, list);
            if (ok && gen === loadGeneration) {
                loaded = true;
                break;
            }
            if (!ok) return;
        } catch (e) {
            lastError = e;
            console.warn(`[pdf] tentativa ${attempt}/${PDF_LOAD_MAX_ATTEMPTS} falhou`, e);
            if (gen !== loadGeneration) return;
        }
    }

    if (gen !== loadGeneration) return;

    if (!loaded) {
        console.error(lastError);
        error.value =
            'Não foi possível carregar o PDF. Verifique sua conexão ou tente novamente.';
        await destroyDocs();
        loading.value = false;
        return;
    }

    loading.value = false;
    syncDefaultZoomForViewport();
    await nextTick();
    await waitForHostLayout(canvasHostRef.value);
    if (gen !== loadGeneration) return;
    await renderCurrentPage();
    if (gen === loadGeneration) {
        scheduleThumbRender();
    }
    bindContinuousScroll();
    scheduleRenderRetry(gen);
}

function cancelThumbRender() {
    thumbRenderToken += 1;
    if (thumbIdleId !== null) {
        if (typeof cancelIdleCallback !== 'undefined') {
            cancelIdleCallback(thumbIdleId);
        } else {
            clearTimeout(thumbIdleId);
        }
        thumbIdleId = null;
    }
}

function scheduleThumbRender() {
    cancelThumbRender();
    const token = thumbRenderToken;
    const run = () => {
        thumbIdleId = null;
        if (token !== thumbRenderToken) return;
        void renderThumbsStaged(token);
    };
    if (typeof requestIdleCallback !== 'undefined') {
        thumbIdleId = requestIdleCallback(run, { timeout: 800 });
    } else {
        thumbIdleId = setTimeout(run, 50);
    }
}

async function yieldToMain() {
    await new Promise((resolve) => {
        if (typeof requestIdleCallback !== 'undefined') {
            requestIdleCallback(() => resolve(), { timeout: 120 });
        } else {
            setTimeout(resolve, 16);
        }
    });
}

async function renderThumbPage(pg) {
    const { doc, pageNum } = globalToLocal(pg);
    if (!doc) return;
    const canvases = [thumbCanvases.value[pg], fsThumbCanvases.value[pg]].filter(Boolean);
    if (!canvases.length) return;
    const page = await doc.getPage(pageNum);
    const vp = page.getViewport({ scale: 0.18 });
    for (const canvas of canvases) {
        const ctx = canvas.getContext('2d');
        canvas.width = vp.width;
        canvas.height = vp.height;
        await page.render({ canvasContext: ctx, viewport: vp }).promise;
    }
    thumbsRenderedPages.add(pg);
}

async function renderThumbPages(pages, token) {
    for (const pg of pages) {
        if (token !== thumbRenderToken) return;
        if (thumbsRenderedPages.has(pg)) continue;
        await renderThumbPage(pg);
    }
}

async function renderThumbsStaged(token) {
    await nextTick();
    if (token !== thumbRenderToken || totalPages.value <= 0) return;

    const center = globalPage.value;
    const priority = [];
    for (let pg = Math.max(1, center - 2); pg <= Math.min(totalPages.value, center + 2); pg++) {
        priority.push(pg);
    }
    await renderThumbPages(priority, token);
    if (token !== thumbRenderToken) return;

    const rest = [];
    for (let pg = 1; pg <= totalPages.value; pg++) {
        if (!priority.includes(pg)) rest.push(pg);
    }
    for (let i = 0; i < rest.length; i += THUMB_CHUNK_SIZE) {
        if (token !== thumbRenderToken) return;
        await renderThumbPages(rest.slice(i, i + THUMB_CHUNK_SIZE), token);
        if (i + THUMB_CHUNK_SIZE < rest.length) {
            await yieldToMain();
        }
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

function renderVisibleThumbs() {
    scheduleThumbRender();
}

function resetCanvasHostScroll() {
    if (useContinuousScroll.value) return;
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

function scrollToGlobalPage(g, behavior = 'smooth') {
    const block = useContinuousScroll.value ? getContinuousPageBlock(g) : null;
    const host = canvasHostRef.value;
    if (!block || !host) return;
    pageScrollSyncSuppress = true;
    const hostRect = host.getBoundingClientRect();
    const blockRect = block.getBoundingClientRect();
    const nextTop = host.scrollTop + (blockRect.top - hostRect.top);
    host.scrollTo({ top: Math.max(0, nextTop), behavior });
    requestAnimationFrame(() => {
        pageScrollSyncSuppress = false;
    });
}

function syncGlobalPageFromScroll() {
    if (pageScrollSyncSuppress || !useContinuousScroll.value) return;
    const host = canvasHostRef.value;
    const wrap = continuousWrapRef.value;
    if (!host || !wrap || totalPages.value <= 0) return;

    const hostRect = host.getBoundingClientRect();
    const anchor = hostRect.top + host.clientHeight * 0.28;
    let bestPg = globalPage.value;
    let bestDist = Infinity;

    const blocks = wrap.querySelectorAll('.pdf-lesson-page-block[data-pdf-page]');
    for (const block of blocks) {
        const pg = Number(block.getAttribute('data-pdf-page'));
        if (!Number.isFinite(pg) || pg < 1) continue;
        const blockRect = block.getBoundingClientRect();
        if (blockRect.bottom < hostRect.top || blockRect.top > hostRect.bottom) continue;
        const dist = Math.abs(blockRect.top - anchor);
        if (dist < bestDist) {
            bestDist = dist;
            bestPg = pg;
        }
    }

    if (bestPg !== globalPage.value) {
        pageScrollSyncSuppress = true;
        globalPage.value = bestPg;
        requestAnimationFrame(() => {
            pageScrollSyncSuppress = false;
        });
    }
}

function onContinuousHostScroll() {
    if (pageScrollRaf != null) return;
    pageScrollRaf = requestAnimationFrame(() => {
        pageScrollRaf = null;
        syncGlobalPageFromScroll();
    });
}

function bindContinuousScroll() {
    unbindContinuousScroll();
    const host = canvasHostRef.value;
    if (!host || !useContinuousScroll.value) return;
    host.addEventListener('scroll', onContinuousHostScroll, { passive: true });
    continuousScrollHostEl = host;
}

function unbindContinuousScroll() {
    if (continuousScrollHostEl) {
        continuousScrollHostEl.removeEventListener('scroll', onContinuousHostScroll);
        continuousScrollHostEl = null;
    }
    if (pageScrollRaf != null) {
        cancelAnimationFrame(pageScrollRaf);
        pageScrollRaf = null;
    }
}

function prevPage() {
    if (globalPage.value <= 1) return;
    globalPage.value -= 1;
    if (!useContinuousScroll.value) {
        resetCanvasHostScroll();
    }
}

function nextPage() {
    if (globalPage.value >= totalPages.value) return;
    globalPage.value += 1;
    if (!useContinuousScroll.value) {
        resetCanvasHostScroll();
    }
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
    if (useContinuousScroll.value) return;
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
    if (useContinuousScroll.value) return;

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
    if (useContinuousScroll.value) return false;
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
    if (useContinuousScroll.value) {
        pageScrollSyncSuppress = true;
        globalPage.value = g;
        nextTick(() => scrollToGlobalPage(g));
        return;
    }
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

function overlayPointerDown(pg, e) {
    if (!highlightColor.value) return;
    if (useContinuousScroll.value && pg !== globalPage.value) {
        globalPage.value = pg;
    }
    const overlay = e.currentTarget;
    const rect = overlay.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;
    selecting.value = true;
    selectStart.value = { x, y };
    selectCurrent.value = { x, y };
}

function overlayPointerMove(pg, e) {
    if (!selecting.value || !highlightColor.value) return;
    const overlay = e.currentTarget;
    const rect = overlay.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;
    selectCurrent.value = { x, y };
}

function overlayPointerUp(pg, e) {
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

    const { fileIndex, pageNum } = globalToLocal(pg);
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

/** Muda quando troca o PDF na aula (?v= no proxy ou URLs no storage) */
const filesVersionKey = computed(() =>
    (props.files || []).map((f) => `${(f?.url ?? '').toString()}|${(f?.name ?? '').toString()}`).join(';;')
);

watch(filesVersionKey, (next, prev) => {
    if (prev !== undefined && next === prev) return;
    void loadDocuments();
});

watch(globalPage, async (newP, oldP) => {
    if (useContinuousScroll.value) {
        if (!pageScrollSyncSuppress) {
            await nextTick();
            scrollToGlobalPage(newP, 'auto');
        }
        if (isFullscreen.value) {
            revealFullscreenNav();
        }
        if (oldP !== undefined && oldP !== null && newP !== oldP) {
            const prevFi = globalToLocal(oldP).fileIndex;
            await saveFileIndexAnnotations(prevFi);
        }
        scheduleThumbRender();
        syncThumbsScroll();
        if (totalPages.value > 0 && newP === totalPages.value && oldP !== undefined && newP !== oldP) {
            emit('last-page-reached');
        }
        return;
    }

    resetCanvasHostScroll();
    if (isFullscreen.value) {
        revealFullscreenNav();
    }
    if (oldP !== undefined && oldP !== null && newP !== oldP) {
        const prevFi = globalToLocal(oldP).fileIndex;
        await saveFileIndexAnnotations(prevFi);
    }
    mainCanvasReady.value = false;
    await renderCurrentPage();
    await nextTick();
    scheduleThumbRender();
    syncThumbsScroll();
    if (totalPages.value > 0 && newP === totalPages.value && oldP !== undefined && newP !== oldP) {
        emit('last-page-reached');
    }
});

watch(zoomMul, async () => {
    if (pinchZoomBypassWatch) return;
    pdfDocumentReady.value = false;
    documentRenderToken += 1;
    await renderCurrentPage();
    resetCanvasHostScrollAfterZoom();
    void nextTick(() => bindLessonTouchGestures());
});

watch(totalPages, (n) => {
    if (n > 0 && !loading.value) {
        nextTick(() => {
            scheduleThumbRender();
            if (useContinuousScroll.value) {
                bindContinuousScroll();
                void renderDocumentPages();
            }
        });
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
            resizeObserver = new ResizeObserver(() => {
                if (resizeDebounceTimer) clearTimeout(resizeDebounceTimer);
                resizeDebounceTimer = setTimeout(() => {
                    resizeDebounceTimer = null;
                    if (!pdfDocs.value.length) return;
                    void renderCurrentPage();
                }, RESIZE_DEBOUNCE_MS);
            });
            resizeObserver.observe(host);
        }
        bindWheelHost();
        bindContinuousScroll();
        bindLessonTouchGestures();
    });
});

onUnmounted(() => {
    unbindWheelHost();
    unbindContinuousScroll();
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
    if (resizeDebounceTimer) clearTimeout(resizeDebounceTimer);
    resizeDebounceTimer = null;
    clearRenderRetry();
    cancelThumbRender();
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
    const shell = [
        'relative min-h-0 min-w-0 flex-1 overflow-auto',
        loading.value && isLessonVariant.value ? 'min-h-[280px]' : '',
    ]
        .filter(Boolean)
        .join(' ');
    if (!isLessonVariant.value) {
        return `${shell} flex aspect-video items-start justify-center bg-zinc-950/80 p-3`;
    }
    const mobileOrFs = isLessonMobile.value || isFullscreen.value;
    if (mobileOrFs) {
        const classes = [
            shell,
            'pdf-lesson-mobile-scroll-host bg-[var(--lesson-pdf-bg)]',
            isFullscreen.value ? 'p-3' : 'px-2 py-2 sm:px-6 sm:py-6',
        ];
        if (isLessonMobile.value && !isFullscreen.value) {
            classes.push('min-h-[min(52vh,480px)]');
        }
        if (!useContinuousScroll.value && zoomMul.value > 1.01) {
            classes.push('pdf-lesson-touch-pan-active');
        }
        return classes;
    }
    const classes = [
        shell,
        'pdf-lesson-desktop-scroll-host bg-[var(--lesson-pdf-bg)]',
        isFullscreen.value ? 'p-3' : 'p-4 sm:p-6',
        'pdf-lesson-desktop-scroll-host--zoomed',
    ];
    return classes;
});

const lessonMobileScrollInner = computed(
    () => isLessonVariant.value && (isLessonMobile.value || isFullscreen.value)
);

const lessonScrollShellClass = computed(() => {
    if (useContinuousScroll.value) {
        return 'pdf-lesson-scroll-content pdf-lesson-continuous-doc';
    }
    if (isLessonDesktopViewer.value) {
        return 'pdf-lesson-scroll-content';
    }
    if (lessonMobileScrollInner.value) return '';
    return 'relative mx-auto';
});

const lessonCanvasWrapClass = computed(() => {
    const base = [];
    if (!isLessonVariant.value) {
        base.push('relative', 'inline-block');
    } else if (useContinuousScroll.value) {
        base.push('relative', 'w-full', 'min-h-0');
    } else if (isLessonDesktopViewer.value) {
        base.push('relative', 'pdf-lesson-canvas-wrap');
    } else if (lessonMobileScrollInner.value) {
        base.push('relative', 'pdf-lesson-mobile-inner');
    } else {
        base.push('relative', 'inline-block');
    }
    if (loading.value) {
        base.push('pdf-lesson-canvas-wrap--loading');
    }
    return base;
});

watch(isDesktopViewport, (isDesktop) => {
    if (!isDesktop) {
        fullscreenNavVisible.value = false;
    }
    if (pdfDocs.value.length && !loading.value) {
        void renderCurrentPage();
    }
});

watch(isLessonMobile, () => {
    void nextTick(() => bindLessonTouchGestures());
});

watch(isFullscreen, () => {
    void nextTick(() => {
        bindLessonTouchGestures();
        bindContinuousScroll();
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
            <!-- Toolbar mobile (aula): uma linha -->
            <div
                v-if="isLessonVariant && isLessonMobile"
                class="pdf-lesson-toolbar-mobile shrink-0 border-b border-[var(--lesson-border)] bg-[var(--lesson-surface)] px-2 py-1.5"
            >
                <div class="flex flex-nowrap items-center gap-1">
                    <div class="flex shrink-0 items-center gap-0.5">
                        <button
                            type="button"
                            class="pdf-tb-btn lesson-tb !px-1.5 !py-1"
                            :disabled="globalPage <= 1 || loading || !!error"
                            @click="prevPage"
                        >
                            ‹
                        </button>
                        <span
                            v-if="totalPages > 0"
                            class="min-w-[2.25rem] text-center text-[11px] font-semibold tabular-nums text-[var(--lesson-text-2)]"
                        >
                            {{ globalPage }}/{{ totalPages }}
                        </span>
                        <button
                            type="button"
                            class="pdf-tb-btn lesson-tb !px-1.5 !py-1"
                            :disabled="globalPage >= totalPages || loading || !!error || totalPages === 0"
                            @click="nextPage"
                        >
                            ›
                        </button>
                    </div>

                    <div
                        class="h-4 w-px shrink-0 bg-[var(--lesson-border)]"
                        aria-hidden="true"
                    />

                    <div class="flex shrink-0 items-center gap-0.5">
                        <button
                            type="button"
                            class="pdf-tb-btn lesson-tb !px-1 !py-1"
                            title="Diminuir zoom"
                            @click="zoomOut"
                        >
                            <ZoomOut class="h-3.5 w-3.5" />
                        </button>
                        <span class="min-w-[2.25rem] text-center text-[11px] tabular-nums text-[var(--lesson-text-3)]">
                            {{ Math.round(zoomMul * 100) }}%
                        </span>
                        <button
                            type="button"
                            class="pdf-tb-btn lesson-tb !px-1 !py-1"
                            title="Aumentar zoom"
                            @click="zoomIn"
                        >
                            <ZoomIn class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <div
                        v-if="totalPages > 1 && totalPages <= PDF_STORY_STYLE_MAX_PAGES && !loading && !error"
                        class="flex min-w-0 flex-1 items-center gap-px px-0.5"
                        role="progressbar"
                        :aria-valuenow="globalPage"
                        aria-valuemin="1"
                        :aria-valuemax="totalPages"
                        aria-label="Progresso de leitura"
                    >
                        <div
                            v-for="seg in lessonStorySegments"
                            :key="`tb-seg-${seg}`"
                            class="pdf-story-segment h-[3px] min-w-[2px] flex-1 rounded-full"
                            :class="{
                                'pdf-story-segment--done': seg < globalPage,
                                'pdf-story-segment--current': seg === globalPage,
                                'pdf-story-segment--future': seg > globalPage,
                            }"
                        />
                    </div>
                    <div
                        v-else-if="totalPages > 1 && !loading && !error"
                        class="mx-0.5 min-w-0 flex-1"
                        role="progressbar"
                        :aria-valuenow="globalPage"
                        aria-valuemin="1"
                        :aria-valuemax="totalPages"
                        aria-label="Progresso de leitura"
                    >
                        <div class="h-1 w-full overflow-hidden rounded-full bg-[var(--lesson-border)]">
                            <div
                                class="h-full rounded-full transition-[width] duration-200"
                                :style="{
                                    width: `${lessonPdfProgressPct}%`,
                                    backgroundColor: 'var(--student-primary, #0047b3)',
                                }"
                            />
                        </div>
                    </div>

                    <div
                        class="h-4 w-px shrink-0 bg-[var(--lesson-border)]"
                        aria-hidden="true"
                    />

                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            class="h-3.5 w-3.5 shrink-0 rounded-full border border-transparent bg-yellow-400"
                            :class="highlightColor === 'yellow' ? 'ring-1 ring-[var(--lesson-text)]' : ''"
                            title="Amarelo"
                            @click="highlightColor = highlightColor === 'yellow' ? null : 'yellow'"
                        />
                        <button
                            type="button"
                            class="h-3.5 w-3.5 shrink-0 rounded-full border border-transparent bg-green-400"
                            :class="highlightColor === 'green' ? 'ring-1 ring-[var(--lesson-text)]' : ''"
                            title="Verde"
                            @click="highlightColor = highlightColor === 'green' ? null : 'green'"
                        />
                        <button
                            type="button"
                            class="h-3.5 w-3.5 shrink-0 rounded-full border border-transparent bg-pink-400"
                            :class="highlightColor === 'pink' ? 'ring-1 ring-[var(--lesson-text)]' : ''"
                            title="Rosa"
                            @click="highlightColor = highlightColor === 'pink' ? null : 'pink'"
                        />
                    </div>

                    <button
                        type="button"
                        class="pdf-tb-btn lesson-tb ml-auto shrink-0 !px-1.5 !py-1"
                        title="Tela cheia"
                        @click="toggleFullscreen"
                    >
                        <component :is="isFullscreen ? Minimize2 : Maximize2" class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <!-- Toolbar desktop / legado -->
            <div
                v-else
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
                                <div
                                    v-if="!loading && !error && useContinuousScroll && totalPages > 0"
                                    ref="continuousWrapRef"
                                    class="pdf-lesson-continuous-wrap flex w-full flex-col items-center"
                                >
                                    <div
                                        v-for="pg in pageNumberList"
                                        :key="`pdf-page-${pg}`"
                                        class="pdf-lesson-page-block"
                                        :data-pdf-page="pg"
                                    >
                                        <canvas
                                            class="pdf-lesson-main-canvas pdf-lesson-page-canvas block shadow-lg"
                                        />
                                        <div
                                            v-if="pdfDocumentReady"
                                            class="absolute inset-0 z-[3]"
                                            :class="
                                                highlightColor && pg === globalPage
                                                    ? 'pointer-events-auto'
                                                    : 'pointer-events-none'
                                            "
                                            @pointerdown.prevent="overlayPointerDown(pg, $event)"
                                            @pointermove.prevent="overlayPointerMove(pg, $event)"
                                            @pointerup.prevent="overlayPointerUp(pg, $event)"
                                            @pointerleave="overlayPointerUp(pg, $event)"
                                        >
                                            <div
                                                v-for="h in highlightsForPage(pg)"
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
                                                v-if="selectionRectCss && selecting && pg === globalPage"
                                                class="pointer-events-none absolute border-2 border-dashed border-amber-300 bg-amber-400/20"
                                                :style="selectionRectCss"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <template v-else>
                                    <canvas
                                        ref="canvasRef"
                                        :class="isLessonVariant ? 'pdf-lesson-main-canvas block shadow-lg' : 'max-w-full shadow-lg'"
                                    />
                                    <div
                                        v-if="mainCanvasReady && !loading && !error && totalPages > 0"
                                        class="absolute inset-0 z-[3]"
                                        :class="highlightColor ? 'pointer-events-auto' : 'pointer-events-none'"
                                        @pointerdown.prevent="overlayPointerDown(globalPage, $event)"
                                        @pointermove.prevent="overlayPointerMove(globalPage, $event)"
                                        @pointerup.prevent="overlayPointerUp(globalPage, $event)"
                                        @pointerleave="overlayPointerUp(globalPage, $event)"
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
                                </template>
                                <div
                                    v-if="loading"
                                    class="absolute inset-0 z-30 flex min-h-[200px] min-w-[160px] flex-col items-center justify-center gap-3 px-4 text-center"
                                    :class="isLessonVariant ? 'bg-white text-[var(--lesson-text-2)]' : 'bg-zinc-950/60 text-zinc-300'"
                                >
                                    <div
                                        class="pdf-loader-brand"
                                        :class="{ 'pdf-loader-brand--has-logo': !!loadingLogoUrl }"
                                    >
                                        <img
                                            v-if="loadingLogoUrl"
                                            :src="loadingLogoUrl"
                                            alt=""
                                            class="pdf-loader-logo"
                                        />
                                        <div v-else class="pdf-loader-spinner" aria-hidden="true" />
                                    </div>
                                    <p class="text-sm font-medium">{{ loadingStatusText }}</p>
                                </div>
                                <div
                                    v-else-if="error"
                                    class="absolute inset-0 z-[4] flex flex-col items-center justify-center gap-3 p-4 text-center text-sm"
                                    :class="isLessonVariant ? 'bg-white/90 text-red-600' : 'bg-zinc-950/80 text-red-200'"
                                >
                                    <p>{{ error }}</p>
                                    <button
                                        type="button"
                                        class="rounded-lg border px-4 py-2 text-xs font-semibold transition hover:opacity-90"
                                        :class="
                                            isLessonVariant
                                                ? 'border-[var(--lesson-border)] bg-[var(--lesson-bg)] text-[var(--lesson-text)]'
                                                : 'border-zinc-600 bg-zinc-800 text-zinc-100'
                                        "
                                        @click="loadDocuments"
                                    >
                                        Tentar novamente
                                    </button>
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
                    </div>
                </div>

                <!-- Miniaturas em faixa horizontal (aula só em mobile / < md) -->
                <aside
                    v-if="isLessonVariant && isLessonMobile"
                    class="shrink-0 border-t border-[var(--lesson-border)] bg-[var(--lesson-bg2)]"
                >
                    <div
                        ref="thumbsScrollMobileRef"
                        class="flex max-h-[72px] flex-row flex-nowrap gap-1.5 overflow-x-auto overflow-y-hidden px-2 py-1.5 scroll-smooth"
                    >
                        <button
                            v-for="pg in totalPages"
                            :key="`lesson-mob-${pg}`"
                            :data-page="pg"
                            type="button"
                            class="relative h-[56px] w-[40px] shrink-0 overflow-hidden rounded-md border-2 bg-white shadow-sm transition"
                            :class="pg === globalPage ? 'border-[var(--student-primary,#0047b3)]' : 'border-transparent hover:border-[var(--lesson-border2)]'"
                            @click="goToGlobalPage(pg)"
                        >
                            <canvas :ref="(el) => setThumbRef(pg, el)" class="mx-auto block h-auto max-h-[48px] w-full" />
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

.pdf-lesson-desktop-scroll-host--fit {
    display: flex;
    align-items: center;
    justify-content: center;
}

.pdf-lesson-desktop-scroll-host--zoomed {
    display: block;
    text-align: center;
}

.pdf-lesson-scroll-content {
    box-sizing: border-box;
    width: 100%;
    flex-shrink: 0;
}

.pdf-lesson-continuous-doc {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
}

.pdf-lesson-continuous-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    max-width: none;
}

.pdf-lesson-page-block {
    position: relative;
    display: inline-block;
    flex: 0 0 auto;
    max-width: none;
    margin: 0 auto 12px;
    line-height: 0;
    vertical-align: top;
}

.pdf-lesson-page-block:last-child {
    margin-bottom: 0;
}

.pdf-lesson-canvas-wrap--loading {
    min-height: 200px;
    min-width: 160px;
}

.pdf-loader-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 56px;
}

.pdf-loader-brand--has-logo {
    animation: pdf-loader-float 1.6s ease-in-out infinite;
}

.pdf-loader-logo {
    max-height: 56px;
    max-width: min(180px, 70vw);
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgb(0 0 0 / 0.08));
}

.pdf-loader-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgb(0 0 0 / 0.08);
    border-top-color: var(--student-primary, #0047b3);
    border-radius: 50%;
    animation: pdf-loader-spin 0.85s linear infinite;
}

@keyframes pdf-loader-float {
    0%,
    100% {
        opacity: 0.72;
        transform: translateY(2px) scale(0.97);
    }
    50% {
        opacity: 1;
        transform: translateY(-4px) scale(1);
    }
}

@keyframes pdf-loader-spin {
    to {
        transform: rotate(360deg);
    }
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

.pdf-lesson-toolbar-mobile {
    -webkit-overflow-scrolling: touch;
}

.pdf-lesson-toolbar-mobile .pdf-tb-btn.lesson-tb {
    font-size: 12px;
}

</style>

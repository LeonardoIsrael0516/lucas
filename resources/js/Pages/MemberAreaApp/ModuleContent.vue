<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import CourseLessonSidebar from '@/components/member-area/CourseLessonSidebar.vue';
import MemberAreaVideoPlayer from '@/components/MemberAreaVideoPlayer.vue';
import MemberPdfPresentationViewer from '@/components/MemberPdfPresentationViewer.vue';
import MemberPdfReader from '@/components/MemberPdfReader.vue';
import LessonTabs from '@/components/LessonTabs.vue';
import LessonPdfIntro from '@/components/LessonPdfIntro.vue';
import LessonPdfActionBar from '@/components/LessonPdfActionBar.vue';
import axios from 'axios';
import { formatLessonDescription } from '@/lib/utils';
import { fingerprintPdfSources, lessonPdfProxyUrl } from '@/lib/pdfLessonFiles';
import { Link as LinkIcon, ChevronLeft, List, Menu, FileText } from 'lucide-vue-next';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    slug: { type: String, required: true },
    module: { type: Object, required: true },
    lessons: { type: Array, default: () => [] },
    current_lesson: { type: Object, default: null },
    progress_percent: { type: Number, default: 0 },
    modules: { type: Array, default: () => [] },
    comments_enabled: { type: Boolean, default: false },
    comments_require_approval: { type: Boolean, default: true },
    lesson_comments: { type: Array, default: () => [] },
    base_url: { type: String, default: '' },
    course_lesson_progress: {
        type: Object,
        default: () => ({ completed: 0, total: 0 }),
    },
});

const courseSidebarOpen = ref(false);
const isMobileViewport = ref(false);

function updateMobileViewport() {
    isMobileViewport.value = typeof window !== 'undefined' && window.innerWidth < 768;
}

const moduleId = computed(() => props.module?.id);

const allModules = computed(() => {
    const list = Array.isArray(props.modules) ? props.modules : [];
    return list.map((m) => ({
        id: m.id,
        title: m.title,
        thumbnail: m.thumbnail || null,
        cover_mode: m.cover_mode === 'horizontal' ? 'horizontal' : 'vertical',
        lessons: Array.isArray(m.lessons) ? m.lessons : [],
        is_locked: !!m.is_locked,
        lock_message: m.lock_message || null,
    }));
});

const flatLessons = computed(() => {
    const items = [];
    for (const m of allModules.value) {
        if (m.is_locked) continue;
        for (const l of m.lessons || []) {
            if (!l.is_locked) {
                items.push({ moduleId: m.id, lesson: l, href: `/m/${props.slug}/modulo/${m.id}?aula=${l.id}` });
            }
        }
    }
    return items;
});

const currentFlatIndex = computed(() => {
    if (!props.current_lesson) return -1;
    return flatLessons.value.findIndex(
        (it) => it.lesson.id === props.current_lesson.id && it.moduleId === moduleId.value
    );
});

const prevLessonHref = computed(() => {
    const i = currentFlatIndex.value;
    if (i <= 0) return null;
    return flatLessons.value[i - 1]?.href ?? null;
});

const nextLessonHref = computed(() => {
    const i = currentFlatIndex.value;
    if (i < 0 || i >= flatLessons.value.length - 1) return null;
    return flatLessons.value[i + 1]?.href ?? null;
});

const modulosHref = computed(() => `/m/${props.slug}/modulos`);

function normalizePdfFiles(lesson, defaultName = 'Material') {
    const list = Array.isArray(lesson?.content_files) ? lesson.content_files : [];
    const normalized = list
        .map((it) => {
            if (typeof it === 'string') return { url: it, name: defaultName };
            const url = (it?.url ?? '').toString().trim();
            if (!url) return null;
            return { url, name: (it?.name ?? defaultName).toString().trim() || defaultName };
        })
        .filter(Boolean);
    if (normalized.length === 0 && lesson?.content_url) {
        normalized.push({ url: lesson.content_url, name: defaultName });
    }
    return normalized;
}

function pdfPresentationViewerFiles(slug, lesson, defaultName = 'Apresentação') {
    const norm = normalizePdfFiles(lesson, defaultName);
    const version = fingerprintPdfSources(norm);
    const base = `/m/${slug}`;
    return norm.map((f, i) => ({
        ...f,
        url: lessonPdfProxyUrl(base, lesson.id, i, version),
    }));
}

const currentPdfFiles = computed(() => normalizePdfFiles(props.current_lesson));
const currentPresentationFiles = computed(() =>
    props.current_lesson?.type === 'pdf_presentation'
        ? pdfPresentationViewerFiles(props.slug, props.current_lesson)
        : []
);

const memberAreaBaseUrl = computed(() => {
    const u = (props.base_url || '').trim();
    if (u) return u.replace(/\/$/, '');
    return `/m/${props.slug}`;
});

function pdfReaderViewerFiles(lesson, defaultName = 'Documento') {
    const norm = normalizePdfFiles(lesson, defaultName);
    const version = fingerprintPdfSources(norm);
    const p = memberAreaBaseUrl.value;
    return norm.map((f, i) => ({
        ...f,
        url: lessonPdfProxyUrl(p, lesson.id, i, version),
    }));
}

const currentPdfReaderFiles = computed(() =>
    props.current_lesson?.type === 'pdf_reader'
        ? pdfReaderViewerFiles(props.current_lesson)
        : []
);

const pdfReaderComponentKey = computed(() => {
    if (props.current_lesson?.type !== 'pdf_reader') return '';
    const norm = normalizePdfFiles(props.current_lesson);
    return `${props.current_lesson.id}-${fingerprintPdfSources(norm)}`;
});

const pdfPresentationComponentKey = computed(() => {
    if (props.current_lesson?.type !== 'pdf_presentation') return '';
    const norm = normalizePdfFiles(props.current_lesson);
    return `${props.current_lesson.id}-${fingerprintPdfSources(norm)}`;
});

const lessonTabsPrimary = computed(
    () => props.config?.theme?.primary || 'var(--student-primary, #0047b3)'
);

const formattedOverviewHtml = computed(() =>
    props.current_lesson?.content_text ? formatLessonDescription(props.current_lesson.content_text) : ''
);

const downloadableLessonFiles = computed(() => {
    const cl = props.current_lesson;
    if (!cl) return [];
    const mapNamed = (list) =>
        (Array.isArray(list) ? list : []).map((f) => ({
            url: f.url,
            name: f.name || 'PDF',
            hint: null,
        }));
    if (cl.type === 'pdf_reader') return mapNamed(currentPdfReaderFiles.value);
    if (cl.type === 'pdf_presentation') return mapNamed(currentPresentationFiles.value);
    if (cl.type === 'pdf') return mapNamed(currentPdfFiles.value);
    return [];
});

const introAttachmentDownloadFiles = computed(() => {
    const cl = props.current_lesson;
    if (!cl?.id) return [];
    const base = memberAreaBaseUrl.value;
    return (Array.isArray(cl.attachment_files) ? cl.attachment_files : []).map((f, i) => ({
        url: `${base}/aula/${cl.id}/attachment/${i}?download=1`,
        name: f.name || 'Anexo',
    }));
});

const allowLessonDownloads = computed(() => true);
const isPdfLessonType = (t) => t === 'pdf' || t === 'pdf_presentation' || t === 'pdf_reader';

const lessonResourceLinks = computed(() => {
    const links = props.current_lesson?.resource_links;
    return Array.isArray(links) ? links.filter((l) => l?.url && l?.title) : [];
});

/** Conteúdo da introdução (visão geral, links, comentários, download). */
const hasPdfIntroContent = computed(() => {
    const cl = props.current_lesson;
    if (!cl || !isPdfLessonType(cl.type)) return false;
    const hasOverview = !!(formattedOverviewHtml.value && formattedOverviewHtml.value.trim());
    const hasLinks = lessonResourceLinks.value.length > 0;
    const hasComments = props.comments_enabled;
    const hasDownloads = downloadableLessonFiles.value.length > 0;
    const hasAttachments = introAttachmentDownloadFiles.value.length > 0;
    if (cl.type === 'pdf') {
        return hasOverview || hasLinks || hasComments || hasDownloads || hasAttachments;
    }
    return hasOverview || hasLinks || hasComments || hasDownloads || hasAttachments;
});

/** Tela cheia de introdução só para material tipo `pdf` (sem leitor embutido). */
const showPdfIntro = computed(() => {
    const cl = props.current_lesson;
    return !!cl && cl.type === 'pdf' && hasPdfIntroContent.value;
});

const showPdfReaderViewer = computed(
    () =>
        props.current_lesson?.type === 'pdf_reader' && currentPdfReaderFiles.value.length > 0
);

const showPdfPresentationViewer = computed(
    () =>
        props.current_lesson?.type === 'pdf_presentation' && currentPresentationFiles.value.length > 0
);

/** Painel inferior com abas da introdução (embaixo do leitor / conteúdo). */
const hasBottomPanel = computed(() => {
    if (!props.current_lesson) return false;
    const cl = props.current_lesson;
    if (cl.type === 'pdf_reader' || cl.type === 'pdf_presentation') {
        return true;
    }
    if (isPdfLessonType(cl.type)) return false;
    return (
        !!formattedOverviewHtml.value ||
        downloadableLessonFiles.value.length > 0 ||
        introAttachmentDownloadFiles.value.length > 0 ||
        lessonResourceLinks.value.length > 0 ||
        props.comments_enabled
    );
});

const showVisualizingBar = computed(() => {
    const cl = props.current_lesson;
    if (!cl || !isPdfLessonType(cl.type)) return false;
    if (isMobileViewport.value && (showPdfReaderViewer.value || showPdfPresentationViewer.value)) {
        return false;
    }
    return (
        showPdfIntro.value ||
        showPdfReaderViewer.value ||
        showPdfPresentationViewer.value ||
        (cl.type === 'pdf' && currentPdfFiles.value.length > 0)
    );
});

/** Abas unificadas fora do leitor (não na tela cheia LessonPdfIntro). */
const showLessonTabsPanel = computed(() => {
    if (!props.current_lesson || showPdfIntro.value) return false;
    const t = props.current_lesson.type;
    if (t === 'pdf_reader' || t === 'pdf_presentation') return true;
    if (t === 'pdf') return false;
    return hasBottomPanel.value;
});

const showPdfActionBar = computed(
    () => !!props.current_lesson && isPdfLessonType(props.current_lesson.type)
);

const showPdfViewerCard = computed(
    () => showPdfReaderViewer.value || showPdfPresentationViewer.value
);

const userRating = ref(null);
const userBookmarked = ref(false);
const ratingSubmitting = ref(false);
const bookmarkSubmitting = ref(false);

function syncEngagementFromLesson(lesson) {
    if (!lesson) {
        userRating.value = null;
        userBookmarked.value = false;
        return;
    }
    userRating.value = lesson.user_rating ?? null;
    userBookmarked.value = !!lesson.user_bookmarked;
}

async function submitLessonRating(rating) {
    const cl = props.current_lesson;
    if (!cl?.id || ratingSubmitting.value) return;
    ratingSubmitting.value = true;
    try {
        const { data } = await axios.put(`${memberAreaBaseUrl.value}/aula/${cl.id}/rating`, { rating });
        userRating.value = data.user_rating ?? rating;
        if (typeof data.user_bookmarked === 'boolean') {
            userBookmarked.value = data.user_bookmarked;
        }
    } finally {
        ratingSubmitting.value = false;
    }
}

async function toggleLessonBookmark() {
    const cl = props.current_lesson;
    if (!cl?.id || bookmarkSubmitting.value) return;
    bookmarkSubmitting.value = true;
    try {
        const { data } = await axios.post(`${memberAreaBaseUrl.value}/aula/${cl.id}/bookmark`);
        userBookmarked.value = !!data.user_bookmarked;
        router.reload({ only: ['has_saved_lessons'] });
    } finally {
        bookmarkSubmitting.value = false;
    }
}

async function handleLessonDownload({ file }) {
    if (!file?.url) return;
    const filename = (file.name || 'documento.pdf').replace(/[^\w.\-\u00C0-\u024F]+/g, '_');
    let downloadUrl;
    try {
        downloadUrl = new URL(file.url, window.location.origin);
        downloadUrl.searchParams.set('download', '1');
    } catch {
        return;
    }
    try {
        const res = await fetch(downloadUrl.toString(), { credentials: 'same-origin' });
        if (!res.ok) {
            throw new Error('download failed');
        }
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
    } catch {
        window.location.assign(downloadUrl.toString());
    }
}

const completedLessonIds = ref(new Set());
let autoCompleteTimer = null;

function coerceLessonCompleted(value) {
    return value === true || value === 1 || value === '1';
}

const completed = ref(coerceLessonCompleted(props.current_lesson?.is_completed));

function markComplete() {
    if (!props.current_lesson || completed.value) return;
    router.post(`/m/${props.slug}/aula/${props.current_lesson.id}/complete`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            completed.value = true;
            completedLessonIds.value.add(props.current_lesson.id);
        },
    });
}

function scheduleAutoComplete() {
    if (!props.current_lesson || completed.value) return;
    if (props.current_lesson.type !== 'video' || !props.current_lesson.content_url) return;
    const durationSeconds = Math.max(30, Math.floor((props.current_lesson.duration_seconds || 60) * 0.8));
    autoCompleteTimer = setTimeout(() => markComplete(), durationSeconds * 1000);
}

function shouldAutoCompleteNonVideo() {
    if (!props.current_lesson || completed.value) return false;
    const t = props.current_lesson.type;
    if (t === 'pdf' || t === 'pdf_presentation' || t === 'pdf_reader') return false;
    return (
        t === 'link' ||
        t === 'text' ||
        (t !== 'video' && (props.current_lesson.content_url || props.current_lesson.content_text))
    );
}

watch(
    () => props.current_lesson?.id,
    () => {
        syncEngagementFromLesson(props.current_lesson);
        completed.value = coerceLessonCompleted(props.current_lesson?.is_completed);
        if (autoCompleteTimer) clearTimeout(autoCompleteTimer);
        if (coerceLessonCompleted(props.current_lesson?.is_completed)) return;
        if (props.current_lesson?.type === 'video') scheduleAutoComplete();
        else if (shouldAutoCompleteNonVideo()) setTimeout(() => markComplete(), 500);
    }
);

onMounted(() => {
    updateMobileViewport();
    window.addEventListener('resize', updateMobileViewport);
    syncEngagementFromLesson(props.current_lesson);
    if (coerceLessonCompleted(props.current_lesson?.is_completed)) completed.value = true;
    else if (props.current_lesson?.type === 'video') scheduleAutoComplete();
    else if (shouldAutoCompleteNonVideo()) setTimeout(() => markComplete(), 500);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateMobileViewport);
    if (autoCompleteTimer) clearTimeout(autoCompleteTimer);
});

const commentContent = ref('');
const commentSubmitting = ref(false);
function submitComment() {
    if (!props.current_lesson || !props.comments_enabled || !commentContent.value?.trim()) return;
    commentSubmitting.value = true;
    router.post(`/m/${props.slug}/aula/${props.current_lesson.id}/comments`, { content: commentContent.value.trim() }, {
        preserveScroll: true,
        onFinish: () => {
            commentSubmitting.value = false;
            commentContent.value = '';
        },
    });
}
function formatCommentDate(iso) {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        return d.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch (_) {
        return iso;
    }
}

function onPdfReaderLastPage() {
    markComplete();
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <!-- Topbar da aula -->
        <div
            class="flex h-14 shrink-0 items-center gap-2 border-b border-[var(--lesson-border)] bg-[var(--lesson-surface)] px-3 sm:px-4"
        >
            <Link
                :href="modulosHref"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[var(--lesson-border)] bg-[var(--lesson-bg)] px-2.5 py-1.5 text-xs font-semibold text-[var(--lesson-text-2)] transition hover:bg-[var(--lesson-bg2)] sm:text-sm"
            >
                <ChevronLeft class="h-4 w-4" />
                <span class="hidden sm:inline">Voltar</span>
            </Link>
            <nav class="hidden min-w-0 flex-1 items-center gap-1.5 truncate text-xs text-[var(--lesson-text-3)] sm:flex sm:text-sm">
                <span class="truncate font-medium text-[var(--lesson-text-2)]">{{ product.name }}</span>
                <span>›</span>
                <span class="truncate">{{ module.title }}</span>
                <template v-if="current_lesson">
                    <span>›</span>
                    <span class="truncate font-bold text-[var(--lesson-text)]">{{ current_lesson.title }}</span>
                </template>
            </nav>
            <p v-if="current_lesson" class="min-w-0 flex-1 truncate text-sm font-bold text-[var(--lesson-text)] sm:hidden">
                {{ current_lesson.title }}
            </p>
            <div class="ml-auto flex shrink-0 items-center gap-1.5">
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--lesson-border)] text-[var(--lesson-text-2)] hover:bg-[var(--lesson-bg)] lg:hidden"
                    aria-label="Lista de aulas"
                    @click="courseSidebarOpen = true"
                >
                    <List class="h-5 w-5" />
                </button>
            </div>
        </div>

        <!-- Corpo: conteúdo + sidebar curso -->
        <div class="flex min-h-0 flex-1 overflow-hidden">
            <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                <template v-if="current_lesson">
                    <div
                        class="flex min-h-0 flex-1 flex-col overflow-hidden"
                        :class="showPdfReaderViewer && isMobileViewport ? 'max-md:min-h-0' : ''"
                    >
                        <!-- Intro PDF / Viewer -->
                        <div
                            class="flex min-h-0 flex-col overflow-hidden"
                            :class="
                                showPdfViewerCard
                                    ? [
                                          'flex-1 min-h-0',
                                          'mx-3 mt-3 rounded-xl border border-[var(--lesson-border)] bg-white shadow-sm max-md:mx-2 max-md:mt-2',
                                          showPdfReaderViewer && isMobileViewport
                                              ? 'max-md:min-h-[min(58vh,560px)]'
                                              : '',
                                      ]
                                    : 'flex-1 min-h-0'
                            "
                        >
                            <div
                                v-if="showVisualizingBar"
                                class="flex shrink-0 items-center gap-2 border-b border-[var(--lesson-border)] bg-white px-4 py-2.5 text-sm"
                            >
                                <FileText class="h-4 w-4 shrink-0 text-emerald-600" />
                                <span class="shrink-0 text-[var(--lesson-text-3)]">Visualizando:</span>
                                <span class="min-w-0 truncate font-semibold text-[var(--lesson-text)]">{{
                                    current_lesson.title
                                }}</span>
                            </div>
                            <div class="min-h-0 flex-1 overflow-hidden">
                            <LessonPdfIntro
                                v-if="showPdfIntro"
                                :lesson-type="current_lesson.type"
                                :lesson-title="current_lesson.title"
                                :overview-html="formattedOverviewHtml"
                                :downloadable-files="downloadableLessonFiles"
                                :attachment-files="introAttachmentDownloadFiles"
                                :allow-download="allowLessonDownloads"
                                :resource-links="lessonResourceLinks"
                                :primary-color="lessonTabsPrimary"
                                :comments-enabled="comments_enabled"
                                :comments-require-approval="comments_require_approval"
                                :lesson-comments="lesson_comments"
                                :comment-content="commentContent"
                                :comment-submitting="commentSubmitting"
                                @update:comment-content="commentContent = $event"
                                @submit-comment="submitComment"
                                @download="handleLessonDownload"
                            />
                            <template v-else-if="current_lesson.type === 'video'">
                                <div class="flex h-full flex-col bg-black">
                                    <MemberAreaVideoPlayer
                                        v-if="current_lesson.content_url"
                                        class="h-full w-full"
                                        :src="current_lesson.content_url"
                                        :watermark-enabled="!!current_lesson.watermark_enabled"
                                        :watermark-data="current_lesson.student ?? null"
                                        @ended="markComplete"
                                    />
                                    <div v-else class="flex flex-1 items-center justify-center text-zinc-400">
                                        Conteúdo não disponível.
                                    </div>
                                </div>
                            </template>
                            <template v-else-if="current_lesson.type === 'link' && current_lesson.content_url">
                                <div class="flex h-full items-center justify-center bg-[var(--lesson-bg)] p-8">
                                    <a
                                        :href="current_lesson.content_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-2 rounded-lg px-6 py-3 text-lg font-semibold text-white"
                                        :style="{ backgroundColor: 'var(--student-primary)' }"
                                    >
                                        {{ current_lesson.link_title?.trim() || 'Abrir link externo' }}
                                        <LinkIcon class="h-5 w-5" />
                                    </a>
                                </div>
                            </template>
                            <template v-else-if="showPdfPresentationViewer">
                                <div class="h-full min-h-0 overflow-auto bg-[var(--lesson-pdf-bg)] p-4">
                                    <MemberPdfPresentationViewer
                                        :key="pdfPresentationComponentKey"
                                        :files="currentPresentationFiles"
                                    />
                                </div>
                            </template>
                            <template v-else-if="showPdfReaderViewer">
                                <MemberPdfReader
                                    :key="pdfReaderComponentKey"
                                    variant="lesson"
                                    class="h-full"
                                    hide-like-button
                                    :files="currentPdfReaderFiles"
                                    :base-url="memberAreaBaseUrl"
                                    :lesson-id="current_lesson.id"
                                    :likes-count="current_lesson.likes_count ?? 0"
                                    :user-liked="!!current_lesson.user_liked"
                                    @last-page-reached="onPdfReaderLastPage"
                                />
                            </template>
                            <template v-else-if="current_lesson.type === 'text' && current_lesson.content_text">
                                <div
                                    class="prose prose-zinc h-full max-w-none overflow-auto p-8"
                                    v-html="current_lesson.content_text"
                                />
                            </template>
                            <template
                                v-else-if="
                                    current_lesson.type === 'pdf' &&
                                    !hasPdfIntroContent &&
                                    currentPdfFiles.length
                                "
                            >
                                <div class="flex h-full flex-col items-center justify-center gap-3 bg-[var(--lesson-bg)] p-8">
                                    <a
                                        v-for="(f, i) in currentPdfFiles"
                                        :key="`${f.url}-${i}`"
                                        :href="f.url"
                                        download
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex w-full max-w-md items-center justify-center gap-2 rounded-lg px-4 py-3 font-semibold text-white"
                                        :style="{ backgroundColor: 'var(--student-primary)' }"
                                    >
                                        {{ f.name || 'Baixar material' }}
                                    </a>
                                </div>
                            </template>
                            <div
                                v-else-if="!showPdfIntro && !showPdfReaderViewer && !showPdfPresentationViewer"
                                class="flex h-full items-center justify-center text-[var(--lesson-text-3)]"
                            >
                                Conteúdo não disponível.
                            </div>
                            </div>
                        </div>

                        <LessonPdfActionBar
                            v-if="showPdfActionBar"
                            :prev-href="prevLessonHref"
                            :next-href="nextLessonHref"
                            :completed="completed"
                            :user-rating="userRating"
                            :user-bookmarked="userBookmarked"
                            :rating-submitting="ratingSubmitting"
                            :bookmark-submitting="bookmarkSubmitting"
                            @complete="markComplete"
                            @rate="submitLessonRating"
                            @bookmark="toggleLessonBookmark"
                        />

                        <LessonTabs
                            v-if="showLessonTabsPanel"
                            :tall="!isMobileViewport"
                            class="shrink-0 max-md:max-h-[30vh]"
                            show-empty-overview
                            :overview-html="formattedOverviewHtml"
                            :downloadable-files="downloadableLessonFiles"
                            :attachment-files="introAttachmentDownloadFiles"
                            :allow-download="allowLessonDownloads"
                            :resource-links="lessonResourceLinks"
                            :primary-color="lessonTabsPrimary"
                            :comments-enabled="comments_enabled"
                            :comments-require-approval="comments_require_approval"
                            :lesson-comments="lesson_comments"
                            :comment-content="commentContent"
                            :comment-submitting="commentSubmitting"
                            @update:comment-content="commentContent = $event"
                            @submit-comment="submitComment"
                            @download="handleLessonDownload"
                        />
                    </div>
                </template>
                <template v-else>
                    <div class="flex flex-1 flex-col items-center justify-center gap-4 p-12 text-center">
                        <Menu class="h-10 w-10 text-[var(--lesson-text-3)]" />
                        <p class="text-[var(--lesson-text-2)]">Selecione uma aula na lista ao lado.</p>
                        <button
                            type="button"
                            class="rounded-lg border border-[var(--lesson-border)] px-4 py-2 text-sm font-semibold lg:hidden"
                            @click="courseSidebarOpen = true"
                        >
                            Ver aulas
                        </button>
                    </div>
                </template>
            </div>

            <!-- Sidebar curso — desktop -->
            <CourseLessonSidebar
                class="hidden lg:flex"
                :product="product"
                :module="module"
                :slug="slug"
                :modules="allModules"
                :current_lesson="current_lesson"
                :course_lesson_progress="course_lesson_progress"
                :module-id="moduleId"
            />
        </div>

        <!-- Sidebar curso — mobile drawer -->
        <Teleport to="body">
            <div v-if="courseSidebarOpen" class="fixed inset-0 z-[60] lg:hidden">
                <div class="absolute inset-0 bg-black/50" @click="courseSidebarOpen = false" />
                <div
                    class="member-lesson-drawer absolute right-0 top-0 bottom-0 flex w-[min(100%,320px)] overflow-hidden bg-white shadow-2xl"
                >
                    <CourseLessonSidebar
                        class="h-full min-h-0 w-full bg-white"
                        :product="product"
                        :module="module"
                        :slug="slug"
                        :modules="allModules"
                        :current_lesson="current_lesson"
                        :course_lesson_progress="course_lesson_progress"
                        :module-id="moduleId"
                        @close="courseSidebarOpen = false"
                    />
                </div>
            </div>
        </Teleport>
    </div>
</template>

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
import { formatLessonDescription } from '@/lib/utils';
import { Link as LinkIcon, Check, ChevronLeft, ChevronRight, List, Menu } from 'lucide-vue-next';

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
    return norm.map((f, i) => ({
        ...f,
        url: `/m/${slug}/aula/${lesson.id}/pdf/${i}`,
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
    const p = memberAreaBaseUrl.value;
    return norm.map((f, i) => ({
        ...f,
        url: `${p}/aula/${lesson.id}/pdf/${i}`,
    }));
}

const currentPdfReaderFiles = computed(() =>
    props.current_lesson?.type === 'pdf_reader'
        ? pdfReaderViewerFiles(props.current_lesson)
        : []
);

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

const allowLessonDownloads = computed(() => true);
const isPdfLessonType = (t) => t === 'pdf' || t === 'pdf_presentation' || t === 'pdf_reader';

const lessonResourceLinks = computed(() => {
    const links = props.current_lesson?.resource_links;
    return Array.isArray(links) ? links.filter((l) => l?.url && l?.title) : [];
});

const pdfContentUnlocked = ref(false);

/** Conteúdo configurado na página de introdução (não conta só ter PDF anexado). */
const hasPdfIntroContent = computed(() => {
    const cl = props.current_lesson;
    if (!cl || !isPdfLessonType(cl.type)) return false;
    const hasOverview = !!(formattedOverviewHtml.value && formattedOverviewHtml.value.trim());
    const hasLinks = lessonResourceLinks.value.length > 0;
    const hasComments = props.comments_enabled;
    if (cl.type === 'pdf') {
        return hasOverview || hasLinks || hasComments || downloadableLessonFiles.value.length > 0;
    }
    return hasOverview || hasLinks || hasComments;
});

const showPdfIntro = computed(() => {
    const cl = props.current_lesson;
    if (!cl || !isPdfLessonType(cl.type)) return false;
    if (!hasPdfIntroContent.value) return false;
    if (cl.type === 'pdf') return true;
    return !pdfContentUnlocked.value;
});

const showPdfViewer = computed(() => {
    const cl = props.current_lesson;
    if (!cl || !isPdfLessonType(cl.type)) return false;
    if (cl.type === 'pdf') return false;
    if (!hasPdfIntroContent.value) return true;
    return pdfContentUnlocked.value;
});

const hasBottomPanel = computed(
    () =>
        !!props.current_lesson &&
        !isPdfLessonType(props.current_lesson.type) &&
        (!!formattedOverviewHtml.value ||
            downloadableLessonFiles.value.length > 0 ||
            props.comments_enabled)
);

function openPdfContent() {
    pdfContentUnlocked.value = true;
    const t = props.current_lesson?.type;
    if (t === 'pdf_presentation' && !completed.value) {
        markComplete();
    }
}

function backToPdfIntro() {
    pdfContentUnlocked.value = false;
}

function handleLessonDownload({ file }) {
    if (!file?.url) return;
    const a = document.createElement('a');
    a.href = file.url;
    a.download = (file.name || 'documento.pdf').replace(/[^\w.\-\u00C0-\u024F]+/g, '_');
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

const completedLessonIds = ref(new Set());
const completed = ref(props.current_lesson?.is_completed ?? false);
let autoCompleteTimer = null;

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
        pdfContentUnlocked.value = false;
        completed.value = props.current_lesson?.is_completed ?? false;
        if (autoCompleteTimer) clearTimeout(autoCompleteTimer);
        if (props.current_lesson?.is_completed) return;
        if (props.current_lesson?.type === 'video') scheduleAutoComplete();
        else if (shouldAutoCompleteNonVideo()) setTimeout(() => markComplete(), 500);
    }
);

onMounted(() => {
    if (props.current_lesson?.is_completed) completed.value = true;
    else if (props.current_lesson?.type === 'video') scheduleAutoComplete();
    else if (shouldAutoCompleteNonVideo()) setTimeout(() => markComplete(), 500);
});

onUnmounted(() => {
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
                <Link
                    v-if="prevLessonHref"
                    :href="prevLessonHref"
                    class="hidden items-center gap-1 rounded-lg border border-[var(--lesson-border)] px-2.5 py-1.5 text-xs font-semibold text-[var(--lesson-text-2)] hover:bg-[var(--lesson-bg)] sm:inline-flex"
                >
                    Anterior
                </Link>
                <span
                    v-else
                    class="hidden rounded-lg border border-transparent px-2.5 py-1.5 text-xs text-[var(--lesson-text-3)] opacity-50 sm:inline"
                >
                    Anterior
                </span>
                <Link
                    v-if="nextLessonHref"
                    :href="nextLessonHref"
                    class="hidden items-center gap-1 rounded-lg border border-[var(--lesson-border)] px-2.5 py-1.5 text-xs font-semibold text-[var(--lesson-text-2)] hover:bg-[var(--lesson-bg)] sm:inline-flex"
                >
                    Próxima
                </Link>
                <span
                    v-else
                    class="hidden rounded-lg border border-transparent px-2.5 py-1.5 text-xs text-[var(--lesson-text-3)] opacity-50 sm:inline"
                >
                    Próxima
                </span>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--lesson-border)] text-[var(--lesson-text-2)] hover:bg-[var(--lesson-bg)] lg:hidden"
                    aria-label="Lista de aulas"
                    @click="courseSidebarOpen = true"
                >
                    <List class="h-5 w-5" />
                </button>
                <button
                    v-if="current_lesson"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold shadow-sm transition sm:text-sm"
                    :class="
                        completed
                            ? 'border-2 border-emerald-600 bg-emerald-50 text-emerald-800 hover:bg-emerald-50'
                            : 'border border-transparent text-white hover:opacity-95'
                    "
                    :style="completed ? undefined : { backgroundColor: 'var(--student-primary, #0047b3)' }"
                    :disabled="completed"
                    @click="markComplete"
                >
                    <Check v-if="completed" class="h-4 w-4" />
                    {{ completed ? (isPdfLessonType(current_lesson?.type) ? 'Concluído' : 'Concluído') : 'Marcar como concluído' }}
                </button>
            </div>
        </div>

        <!-- Corpo: conteúdo + sidebar curso -->
        <div class="flex min-h-0 flex-1 overflow-hidden">
            <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                <template v-if="current_lesson">
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                        <!-- Intro PDF / Viewer -->
                        <div class="min-h-0 flex-1 overflow-hidden">
                            <LessonPdfIntro
                                v-if="showPdfIntro"
                                :lesson-type="current_lesson.type"
                                :lesson-title="current_lesson.title"
                                :overview-html="formattedOverviewHtml"
                                :downloadable-files="downloadableLessonFiles"
                                :allow-download="allowLessonDownloads"
                                :resource-links="lessonResourceLinks"
                                :primary-color="lessonTabsPrimary"
                                :comments-enabled="comments_enabled"
                                :comments-require-approval="comments_require_approval"
                                :lesson-comments="lesson_comments"
                                :comment-content="commentContent"
                                :comment-submitting="commentSubmitting"
                                @open-content="openPdfContent"
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
                            <template
                                v-else-if="
                                    showPdfViewer &&
                                    current_lesson.type === 'pdf_presentation' &&
                                    currentPresentationFiles.length
                                "
                            >
                                <div class="flex h-full min-h-0 flex-col">
                                    <div
                                        v-if="hasPdfIntroContent"
                                        class="flex shrink-0 items-center border-b border-[var(--lesson-border)] bg-[var(--lesson-surface)] px-3 py-2"
                                    >
                                        <button
                                            type="button"
                                            class="text-xs font-semibold text-[var(--lesson-text-2)] underline-offset-2 hover:text-[var(--lesson-text)] hover:underline"
                                            @click="backToPdfIntro"
                                        >
                                            Voltar à visão geral
                                        </button>
                                    </div>
                                    <div class="min-h-0 flex-1 overflow-auto bg-[var(--lesson-pdf-bg)] p-4">
                                        <MemberPdfPresentationViewer :files="currentPresentationFiles" />
                                    </div>
                                </div>
                            </template>
                            <template
                                v-else-if="showPdfViewer && current_lesson.type === 'pdf_reader' && currentPdfReaderFiles.length"
                            >
                                <div class="flex h-full min-h-0 flex-col">
                                    <div
                                        v-if="hasPdfIntroContent"
                                        class="flex shrink-0 items-center border-b border-[var(--lesson-border)] bg-[var(--lesson-surface)] px-3 py-2"
                                    >
                                        <button
                                            type="button"
                                            class="text-xs font-semibold text-[var(--lesson-text-2)] underline-offset-2 hover:text-[var(--lesson-text)] hover:underline"
                                            @click="backToPdfIntro"
                                        >
                                            Voltar à visão geral
                                        </button>
                                    </div>
                                    <div class="min-h-0 flex-1 overflow-hidden">
                                        <MemberPdfReader
                                            :key="`${current_lesson.id}-open`"
                                            variant="lesson"
                                            class="h-full"
                                            :files="currentPdfReaderFiles"
                                            :base-url="memberAreaBaseUrl"
                                            :lesson-id="current_lesson.id"
                                            :likes-count="current_lesson.likes_count ?? 0"
                                            :user-liked="!!current_lesson.user_liked"
                                            @last-page-reached="onPdfReaderLastPage"
                                        />
                                    </div>
                                </div>
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
                                v-else-if="!showPdfIntro && !showPdfViewer"
                                class="flex h-full items-center justify-center text-[var(--lesson-text-3)]"
                            >
                                Conteúdo não disponível.
                            </div>
                        </div>

                        <LessonTabs
                            v-if="hasBottomPanel"
                            :overview-html="formattedOverviewHtml"
                            :downloadable-files="downloadableLessonFiles"
                            :allow-download="allowLessonDownloads"
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
                <div class="absolute inset-0 bg-black/40" @click="courseSidebarOpen = false" />
                <div class="absolute right-0 top-0 bottom-0 flex w-[min(100%,320px)] shadow-2xl">
                    <CourseLessonSidebar
                        class="h-full w-full"
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

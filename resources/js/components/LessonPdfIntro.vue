<script setup>
import { computed, ref, watch } from 'vue';
import { Download, MessageCircle, Link2, BookOpen } from 'lucide-vue-next';
import LessonResourceTabsContent from '@/components/LessonResourceTabsContent.vue';

const props = defineProps({
    lessonType: { type: String, required: true },
    lessonTitle: { type: String, default: '' },
    overviewHtml: { type: String, default: '' },
    downloadableFiles: { type: Array, default: () => [] },
    allowDownload: { type: Boolean, default: true },
    resourceLinks: { type: Array, default: () => [] },
    primaryColor: { type: String, default: '' },
    commentsEnabled: { type: Boolean, default: false },
    commentsRequireApproval: { type: Boolean, default: true },
    lessonComments: { type: Array, default: () => [] },
    commentContent: { type: String, default: '' },
    commentSubmitting: { type: Boolean, default: false },
});

const emit = defineEmits(['open-content', 'download', 'update:commentContent', 'submit-comment']);

const hasOverview = computed(() => !!(props.overviewHtml && props.overviewHtml.trim()));
const hasDownloadTab = computed(
    () => props.allowDownload && Array.isArray(props.downloadableFiles) && props.downloadableFiles.length > 0
);
const hasLinksTab = computed(
    () => Array.isArray(props.resourceLinks) && props.resourceLinks.some((l) => l?.url && l?.title)
);
const hasCommentsTab = computed(() => props.commentsEnabled);

const tabIds = computed(() => {
    const ids = [];
    if (hasOverview.value) ids.push('overview');
    if (hasDownloadTab.value) ids.push('download');
    if (hasLinksTab.value) ids.push('links');
    if (hasCommentsTab.value) ids.push('comments');
    return ids;
});

const activeTab = ref('overview');

watch(
    tabIds,
    (ids) => {
        if (!ids.includes(activeTab.value)) {
            activeTab.value = ids[0] || 'overview';
        }
    },
    { immediate: true }
);

const activeStyle = computed(() => {
    const c = (props.primaryColor || '').trim();
    if (!c) return {};
    return { borderBottomColor: c, color: c };
});

const showViewerCta = computed(() => props.lessonType === 'pdf_reader' || props.lessonType === 'pdf_presentation');

const ctaLabel = computed(() => {
    if (props.lessonType === 'pdf_reader') return 'Abrir leitor de PDF';
    if (props.lessonType === 'pdf_presentation') return 'Iniciar apresentação';
    return '';
});

const hasAnyTab = computed(() => tabIds.value.length > 0);

function tabButtonClass(id) {
    return activeTab.value === id
        ? 'border-[var(--student-primary,#0047b3)] text-[#001e45]'
        : 'border-transparent text-[var(--lesson-text-3)] hover:text-[var(--lesson-text-2)]';
}

function goToDownloadTab() {
    if (hasDownloadTab.value) {
        activeTab.value = 'download';
    }
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col bg-[var(--lesson-bg)]">
        <header class="shrink-0 border-b border-[var(--lesson-border)] bg-[var(--lesson-surface)] px-4 py-4 sm:px-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--lesson-text-3)]">Introdução</p>
            <h1 class="mt-1 text-lg font-bold text-[var(--lesson-text)] sm:text-xl">{{ lessonTitle || 'Aula' }}</h1>
        </header>

        <div
            v-if="hasAnyTab"
            class="flex shrink-0 items-center gap-0 overflow-x-auto border-b border-[var(--lesson-border)] bg-[var(--lesson-surface)] px-2"
        >
            <button
                v-if="tabIds.includes('overview')"
                type="button"
                class="flex h-11 shrink-0 items-center gap-1.5 border-b-2.5 px-3.5 text-[13px] font-semibold transition"
                :class="tabButtonClass('overview')"
                :style="activeTab === 'overview' && primaryColor ? activeStyle : undefined"
                @click="activeTab = 'overview'"
            >
                Visão geral
            </button>
            <button
                v-if="hasDownloadTab"
                type="button"
                class="flex h-11 shrink-0 items-center gap-1.5 border-b-2.5 px-3.5 text-[13px] font-semibold transition"
                :class="tabButtonClass('download')"
                :style="activeTab === 'download' && primaryColor ? activeStyle : undefined"
                @click="activeTab = 'download'"
            >
                <Download class="h-3.5 w-3.5" />
                Baixar PDF
            </button>
            <button
                v-if="hasLinksTab"
                type="button"
                class="flex h-11 shrink-0 items-center gap-1.5 border-b-2.5 px-3.5 text-[13px] font-semibold transition"
                :class="tabButtonClass('links')"
                :style="activeTab === 'links' && primaryColor ? activeStyle : undefined"
                @click="activeTab = 'links'"
            >
                <Link2 class="h-3.5 w-3.5" />
                Links
            </button>
            <button
                v-if="hasCommentsTab"
                type="button"
                class="flex h-11 shrink-0 items-center gap-1.5 border-b-2.5 px-3.5 text-[13px] font-semibold transition"
                :class="tabButtonClass('comments')"
                :style="activeTab === 'comments' && primaryColor ? activeStyle : undefined"
                @click="activeTab = 'comments'"
            >
                <MessageCircle class="h-3.5 w-3.5" />
                Comentar
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6">
            <LessonResourceTabsContent
                v-if="hasAnyTab"
                :active-tab="activeTab"
                :overview-html="overviewHtml"
                :downloadable-files="downloadableFiles"
                :allow-download="allowDownload"
                :resource-links="resourceLinks"
                :comments-enabled="commentsEnabled"
                :comments-require-approval="commentsRequireApproval"
                :lesson-comments="lessonComments"
                :comment-content="commentContent"
                :comment-submitting="commentSubmitting"
                @download="emit('download', $event)"
                @update:comment-content="emit('update:commentContent', $event)"
                @submit-comment="emit('submit-comment')"
            />
            <div v-else class="flex flex-col items-center justify-center gap-3 py-12 text-center">
                <BookOpen class="h-10 w-10 text-[var(--lesson-text-3)]" />
                <p class="text-sm text-[var(--lesson-text-2)]">Nenhum conteúdo de introdução foi configurado para esta aula.</p>
                <button
                    v-if="showViewerCta"
                    type="button"
                    class="mt-2 rounded-lg px-6 py-3 text-sm font-bold text-white"
                    :style="{ backgroundColor: 'var(--student-primary, #0047b3)' }"
                    @click="emit('open-content')"
                >
                    {{ ctaLabel }}
                </button>
            </div>
        </div>

        <footer
            v-if="showViewerCta || (lessonType === 'pdf' && hasDownloadTab)"
            class="shrink-0 border-t border-[var(--lesson-border)] bg-[var(--lesson-surface)] p-4 sm:px-6"
        >
            <button
                v-if="showViewerCta"
                type="button"
                class="w-full rounded-lg px-6 py-3.5 text-sm font-bold text-white shadow-sm"
                :style="{ backgroundColor: 'var(--student-primary, #0047b3)' }"
                @click="emit('open-content')"
            >
                {{ ctaLabel }}
            </button>
            <button
                v-else-if="lessonType === 'pdf' && hasDownloadTab"
                type="button"
                class="w-full rounded-lg border border-[var(--lesson-border)] bg-[var(--lesson-bg)] px-6 py-3.5 text-sm font-bold text-[var(--lesson-text)]"
                @click="goToDownloadTab"
            >
                Ver materiais para download
            </button>
        </footer>
    </div>
</template>

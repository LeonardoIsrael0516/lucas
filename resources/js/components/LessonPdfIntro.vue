<script setup>
import { computed, ref, watch } from 'vue';
import { AlignLeft, Paperclip, MessageCircle, Link2, BookOpen } from 'lucide-vue-next';
import LessonResourceTabsContent from '@/components/LessonResourceTabsContent.vue';

const props = defineProps({
    lessonType: { type: String, required: true },
    lessonTitle: { type: String, default: '' },
    overviewHtml: { type: String, default: '' },
    downloadableFiles: { type: Array, default: () => [] },
    attachmentFiles: { type: Array, default: () => [] },
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
const hasMaterialsTab = computed(
    () =>
        props.allowDownload &&
        ((Array.isArray(props.downloadableFiles) && props.downloadableFiles.length > 0) ||
            (Array.isArray(props.attachmentFiles) && props.attachmentFiles.length > 0))
);
const hasLinksTab = computed(
    () => Array.isArray(props.resourceLinks) && props.resourceLinks.some((l) => l?.url && l?.title)
);
const hasCommentsTab = computed(() => props.commentsEnabled);

const tabIds = computed(() => {
    const ids = [];
    ids.push('overview');
    if (hasMaterialsTab.value) ids.push('materials');
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

const accentColor = computed(() => {
    const c = (props.primaryColor || '').trim();
    return c || '#2563eb';
});

const showViewerCta = computed(() => props.lessonType === 'pdf_reader' || props.lessonType === 'pdf_presentation');

const ctaLabel = computed(() => {
    if (props.lessonType === 'pdf_reader') return 'Abrir leitor de PDF';
    if (props.lessonType === 'pdf_presentation') return 'Iniciar apresentação';
    return '';
});

const hasAnyTab = computed(() => tabIds.value.length > 0);

function tabActiveClass(id) {
    return activeTab.value === id
        ? 'border-current text-current'
        : 'border-transparent text-[var(--lesson-text-3)] hover:text-[var(--lesson-text-2)]';
}

function goToMaterialsTab() {
    if (hasMaterialsTab.value) {
        activeTab.value = 'materials';
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
                class="flex h-11 shrink-0 items-center gap-1.5 border-b-2 px-4 text-[13px] font-semibold transition"
                :class="tabActiveClass('overview')"
                :style="activeTab === 'overview' ? { color: accentColor, borderColor: accentColor } : undefined"
                @click="activeTab = 'overview'"
            >
                <AlignLeft class="h-4 w-4" />
                Descrição
            </button>
            <button
                v-if="tabIds.includes('materials')"
                type="button"
                class="flex h-11 shrink-0 items-center gap-1.5 border-b-2 px-4 text-[13px] font-semibold transition"
                :class="tabActiveClass('materials')"
                :style="activeTab === 'materials' ? { color: accentColor, borderColor: accentColor } : undefined"
                @click="activeTab = 'materials'"
            >
                <Paperclip class="h-4 w-4" />
                Materiais
            </button>
            <button
                v-if="tabIds.includes('links')"
                type="button"
                class="flex h-11 shrink-0 items-center gap-1.5 border-b-2 px-4 text-[13px] font-semibold transition"
                :class="tabActiveClass('links')"
                :style="activeTab === 'links' ? { color: accentColor, borderColor: accentColor } : undefined"
                @click="activeTab = 'links'"
            >
                <Link2 class="h-4 w-4" />
                Links
            </button>
            <button
                v-if="tabIds.includes('comments')"
                type="button"
                class="flex h-11 shrink-0 items-center gap-1.5 border-b-2 px-4 text-[13px] font-semibold transition"
                :class="tabActiveClass('comments')"
                :style="activeTab === 'comments' ? { color: accentColor, borderColor: accentColor } : undefined"
                @click="activeTab = 'comments'"
            >
                <MessageCircle class="h-4 w-4" />
                Comentar
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6">
            <LessonResourceTabsContent
                v-if="hasAnyTab"
                :active-tab="activeTab"
                :overview-html="overviewHtml"
                :downloadable-files="downloadableFiles"
                :attachment-files="attachmentFiles"
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
                    class="mt-2 rounded-lg bg-emerald-600 px-6 py-3 text-sm font-bold text-white hover:bg-emerald-700"
                    @click="emit('open-content')"
                >
                    {{ ctaLabel }}
                </button>
            </div>
        </div>

        <footer
            v-if="showViewerCta || (lessonType === 'pdf' && hasMaterialsTab)"
            class="shrink-0 border-t border-[var(--lesson-border)] bg-[var(--lesson-surface)] p-4 sm:px-6"
        >
            <button
                v-if="showViewerCta"
                type="button"
                class="w-full rounded-xl bg-emerald-600 px-6 py-3.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700"
                @click="emit('open-content')"
            >
                {{ ctaLabel }}
            </button>
            <button
                v-else-if="lessonType === 'pdf' && hasMaterialsTab"
                type="button"
                class="w-full rounded-xl border border-[var(--lesson-border)] bg-[var(--lesson-bg)] px-6 py-3.5 text-sm font-bold text-[var(--lesson-text)]"
                @click="goToMaterialsTab"
            >
                Ver materiais para download
            </button>
        </footer>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { AlignLeft, Paperclip, MessageCircle, Link2 } from 'lucide-vue-next';
import LessonResourceTabsContent from '@/components/LessonResourceTabsContent.vue';

const props = defineProps({
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
    tall: { type: Boolean, default: false },
    /** Exibe aba Descrição mesmo sem texto (placeholder estilo mockup). */
    showEmptyOverview: { type: Boolean, default: false },
});

const emit = defineEmits(['download', 'update:commentContent', 'submit-comment']);

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
    if (hasOverview.value || props.showEmptyOverview) ids.push('overview');
    if (hasMaterialsTab.value) ids.push('materials');
    if (hasLinksTab.value) ids.push('links');
    if (hasCommentsTab.value) ids.push('comments');
    return ids;
});

const panelContentMaxHeight = computed(() => (props.tall ? 'min(38vh, 320px)' : '220px'));

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

function tabActiveClass(id) {
    return activeTab.value === id
        ? 'border-current text-current'
        : 'border-transparent text-[var(--lesson-text-3)] hover:text-[var(--lesson-text-2)]';
}

const hasAnyTab = computed(() => tabIds.value.length > 0);
</script>

<template>
    <div
        v-if="hasAnyTab"
        class="shrink-0 overflow-hidden border-t border-[var(--lesson-border)] bg-white"
    >
        <div class="flex h-11 items-center gap-0 overflow-x-auto border-b border-[var(--lesson-border)] px-2">
            <button
                v-if="tabIds.includes('overview')"
                type="button"
                class="flex h-full shrink-0 items-center gap-1.5 border-b-2 px-4 text-[13px] font-semibold transition"
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
                class="flex h-full shrink-0 items-center gap-1.5 border-b-2 px-4 text-[13px] font-semibold transition"
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
                class="flex h-full shrink-0 items-center gap-1.5 border-b-2 px-4 text-[13px] font-semibold transition"
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
                class="flex h-full shrink-0 items-center gap-1.5 border-b-2 px-4 text-[13px] font-semibold transition"
                :class="tabActiveClass('comments')"
                :style="activeTab === 'comments' ? { color: accentColor, borderColor: accentColor } : undefined"
                @click="activeTab = 'comments'"
            >
                <MessageCircle class="h-4 w-4" />
                Comentar
            </button>
        </div>

        <div class="overflow-y-auto px-4 py-4" :style="{ maxHeight: panelContentMaxHeight }">
            <LessonResourceTabsContent
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
        </div>
    </div>
</template>

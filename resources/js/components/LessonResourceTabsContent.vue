<script setup>
import { computed } from 'vue';
import { ExternalLink } from 'lucide-vue-next';
import LessonDescriptionExpandable from '@/components/LessonDescriptionExpandable.vue';

const props = defineProps({
    activeTab: { type: String, required: true },
    overviewHtml: { type: String, default: '' },
    downloadableFiles: { type: Array, default: () => [] },
    attachmentFiles: { type: Array, default: () => [] },
    allowDownload: { type: Boolean, default: true },
    resourceLinks: { type: Array, default: () => [] },
    commentsEnabled: { type: Boolean, default: false },
    commentsRequireApproval: { type: Boolean, default: true },
    lessonComments: { type: Array, default: () => [] },
    commentContent: { type: String, default: '' },
    commentSubmitting: { type: Boolean, default: false },
    contentClass: { type: String, default: '' },
});

const emit = defineEmits(['download', 'update:commentContent', 'submit-comment']);

const hasMaterialsTab = computed(
    () =>
        props.allowDownload &&
        ((Array.isArray(props.downloadableFiles) && props.downloadableFiles.length > 0) ||
            (Array.isArray(props.attachmentFiles) && props.attachmentFiles.length > 0))
);
const hasDownloadables = computed(
    () => props.allowDownload && Array.isArray(props.downloadableFiles) && props.downloadableFiles.length > 0
);
const hasAttachments = computed(
    () => props.allowDownload && Array.isArray(props.attachmentFiles) && props.attachmentFiles.length > 0
);
const hasLinksTab = computed(
    () => Array.isArray(props.resourceLinks) && props.resourceLinks.some((l) => l?.url && l?.title)
);

function onDownloadClick(file, index) {
    emit('download', { file, index });
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
</script>

<template>
    <div :class="contentClass">
        <div v-show="activeTab === 'overview'">
            <LessonDescriptionExpandable :html="overviewHtml" />
        </div>

        <div v-show="activeTab === 'materials' && hasMaterialsTab" class="space-y-4">
            <div v-if="hasDownloadables" class="space-y-2">
                <p class="text-xs font-semibold text-[var(--lesson-text-3)]">PDF da aula</p>
                <div class="flex flex-col gap-2">
                    <div
                        v-for="(file, index) in downloadableFiles"
                        :key="`${file.url}-${index}`"
                        class="flex items-center justify-between gap-2 rounded-lg border border-[var(--lesson-border)] bg-[var(--lesson-bg)] px-3 py-2"
                    >
                        <p class="min-w-0 text-sm font-bold text-[var(--lesson-text)]">{{ file.name || 'PDF' }}</p>
                        <button
                            type="button"
                            class="shrink-0 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700"
                            @click="onDownloadClick(file, index)"
                        >
                            Baixar
                        </button>
                    </div>
                </div>
            </div>
            <div v-if="hasAttachments" class="space-y-2">
                <p class="text-xs font-semibold text-[var(--lesson-text-3)]">Outros arquivos</p>
                <div class="flex flex-col gap-2">
                    <div
                        v-for="(file, index) in attachmentFiles"
                        :key="`att-${file.url}-${index}`"
                        class="flex items-center justify-between gap-2 rounded-lg border border-[var(--lesson-border)] bg-[var(--lesson-bg)] px-3 py-2"
                    >
                        <p class="min-w-0 text-sm font-bold text-[var(--lesson-text)]">{{ file.name || 'Anexo' }}</p>
                        <button
                            type="button"
                            class="shrink-0 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700"
                            @click="onDownloadClick(file, index)"
                        >
                            Baixar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-show="activeTab === 'links' && hasLinksTab" class="space-y-2">
            <ul class="space-y-2">
                <li v-for="(link, index) in resourceLinks" :key="`${link.url}-${index}`">
                    <a
                        v-if="link.url && link.title"
                        :href="link.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-2 rounded-lg border border-[var(--lesson-border)] bg-[var(--lesson-bg)] px-3 py-2.5 text-sm font-semibold text-[var(--lesson-text)] transition hover:border-[var(--student-primary)] hover:text-[var(--student-primary)]"
                    >
                        <ExternalLink class="h-4 w-4 shrink-0 text-[var(--lesson-text-3)]" />
                        <span class="min-w-0 flex-1">{{ link.title }}</span>
                    </a>
                </li>
            </ul>
        </div>

        <div v-show="activeTab === 'comments' && commentsEnabled" class="space-y-3">
            <ul v-if="lessonComments?.length" class="max-h-48 space-y-2 overflow-y-auto sm:max-h-64">
                <li
                    v-for="c in lessonComments"
                    :key="c.id"
                    class="flex gap-2 border-b border-[var(--lesson-border)] pb-2 text-sm last:border-0"
                >
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs font-semibold text-white"
                        :style="{ backgroundColor: 'var(--student-primary)' }"
                    >
                        <img v-if="c.user?.avatar_url" :src="c.user.avatar_url" alt="" class="h-full w-full object-cover" />
                        <span v-else>{{ (c.user?.name ?? 'A').slice(0, 2).toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-[var(--lesson-text)]">{{ c.user?.name ?? 'Aluno' }}</p>
                        <p class="text-[var(--lesson-text-2)]">{{ c.content }}</p>
                        <p class="mt-0.5 text-[10px] text-[var(--lesson-text-3)]">{{ formatCommentDate(c.created_at) }}</p>
                    </div>
                </li>
            </ul>
            <textarea
                :value="commentContent"
                rows="3"
                class="w-full resize-none rounded-lg border border-[var(--lesson-border)] bg-[var(--lesson-bg)] px-3 py-2 text-sm text-[var(--lesson-text)] placeholder:text-[var(--lesson-text-3)] focus:border-[var(--student-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--student-primary)]"
                placeholder="Deixe uma dúvida ou comentário sobre esta aula..."
                maxlength="2000"
                @input="emit('update:commentContent', $event.target.value)"
            />
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p v-if="commentsRequireApproval" class="text-xs text-[var(--lesson-text-3)]">
                    Comentários publicados após aprovação.
                </p>
                <button
                    type="button"
                    class="ml-auto rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                    :disabled="commentSubmitting || !commentContent?.trim()"
                    @click="emit('submit-comment')"
                >
                    {{ commentSubmitting ? 'Enviando…' : 'Enviar' }}
                </button>
            </div>
        </div>
    </div>
</template>

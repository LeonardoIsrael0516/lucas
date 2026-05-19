<script setup>
import { computed, ref, watch } from 'vue';
import { Download } from 'lucide-vue-next';

const props = defineProps({
    /** Pre-formatted HTML from parent (sanitized server-side). */
    overviewHtml: { type: String, default: '' },
    /** List of { url, name } for download tab. */
    downloadableFiles: { type: Array, default: () => [] },
    allowDownload: { type: Boolean, default: true },
    /** Optional hex for active tab underline (e.g. from student branding). */
    primaryColor: { type: String, default: '' },
});

const emit = defineEmits(['download']);

const hasOverview = computed(() => !!(props.overviewHtml && props.overviewHtml.trim()));
const hasDownloadTab = computed(
    () => props.allowDownload && Array.isArray(props.downloadableFiles) && props.downloadableFiles.length > 0
);

const tabIds = computed(() => {
    const ids = [];
    ids.push('overview');
    if (hasDownloadTab.value) ids.push('download');
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

function onDownloadClick(file, index) {
    emit('download', { file, index });
}
</script>

<template>
    <div class="border-t border-zinc-700 bg-zinc-900/40">
        <div class="flex border-b border-zinc-700/80 px-2 pt-1">
            <button
                type="button"
                class="relative px-4 py-2.5 text-sm font-medium transition"
                :class="
                    activeTab === 'overview'
                        ? 'border-b-2 border-[var(--ma-primary,#22d3ee)] text-white'
                        : 'border-b-2 border-transparent text-zinc-400 hover:text-zinc-200'
                "
                :style="activeTab === 'overview' && primaryColor ? activeStyle : undefined"
                @click="activeTab = 'overview'"
            >
                Visão geral
            </button>
            <button
                v-if="hasDownloadTab"
                type="button"
                class="relative px-4 py-2.5 text-sm font-medium transition"
                :class="
                    activeTab === 'download'
                        ? 'border-b-2 border-[var(--ma-primary,#22d3ee)] text-white'
                        : 'border-b-2 border-transparent text-zinc-400 hover:text-zinc-200'
                "
                :style="activeTab === 'download' && primaryColor ? activeStyle : undefined"
                @click="activeTab = 'download'"
            >
                Baixar PDF
            </button>
        </div>

        <div v-show="activeTab === 'overview'" class="p-6">
            <div
                v-if="hasOverview"
                class="prose prose-invert max-w-none"
                v-html="overviewHtml"
            />
            <p v-else class="text-sm text-zinc-500">Nenhuma descrição foi adicionada para esta aula.</p>
        </div>

        <div v-show="activeTab === 'download' && hasDownloadTab" class="divide-y divide-zinc-700/80">
            <div
                v-for="(file, index) in downloadableFiles"
                :key="`${file.url}-${index}`"
                class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-zinc-200">{{ file.name || 'Arquivo PDF' }}</p>
                    <p v-if="file.hint" class="mt-0.5 text-xs text-zinc-500">{{ file.hint }}</p>
                </div>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-zinc-600 bg-zinc-800 px-3 py-2 text-xs font-medium text-zinc-100 transition hover:bg-zinc-700"
                    @click="onDownloadClick(file, index)"
                >
                    <Download class="h-4 w-4" />
                    Baixar
                </button>
            </div>
        </div>
    </div>
</template>

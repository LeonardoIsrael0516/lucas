<script setup>
import { Upload, FolderOpen } from 'lucide-vue-next';

defineProps({
    uploadLabel: { type: String, default: 'Escolher imagem' },
    libraryLabel: { type: String, default: 'Da biblioteca' },
    size: { type: String, default: 'sm' },
    fullWidth: { type: Boolean, default: false },
});

const emit = defineEmits(['upload', 'library']);
</script>

<template>
    <div class="flex flex-wrap gap-2" :class="fullWidth ? 'w-full' : ''">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
            :class="fullWidth ? 'flex-1' : ''"
            @click="emit('upload')"
        >
            <Upload class="h-3.5 w-3.5" />
            {{ uploadLabel }}
        </button>
        <button
            type="button"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-sky-700 transition hover:border-sky-400 hover:bg-sky-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-sky-300"
            :class="fullWidth ? 'flex-1' : ''"
            @click="emit('library')"
        >
            <FolderOpen class="h-3.5 w-3.5" />
            {{ libraryLabel }}
        </button>
    </div>
</template>

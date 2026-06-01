<script setup>
import { ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import { Plus, Trash2, Paperclip, ChevronDown, ChevronRight, MessageSquare } from 'lucide-vue-next';

const props = defineProps({
    lessonForm: { type: Object, required: true },
    lessonType: { type: String, default: 'video' },
    commentsEnabled: { type: Boolean, default: false },
    attachmentUploading: { type: Boolean, default: false },
    uploadLimits: {
        type: Object,
        default: () => ({ attachment_max_mb: 50 }),
    },
    inputClass: { type: String, default: '' },
});

const emit = defineEmits([
    'add-resource-link',
    'remove-resource-link-at',
    'upload-attachment',
    'remove-attachment-at',
    'go-comentarios',
]);

const open = ref(true);
const fileInputRef = ref(null);

function isPdfType(type) {
    return type === 'pdf' || type === 'pdf_presentation' || type === 'pdf_reader';
}

function onFileChange(event) {
    const file = event.target?.files?.[0];
    if (file) {
        emit('upload-attachment', file);
    }
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function overviewHint(type) {
    if (isPdfType(type)) {
        return 'O aluno vê este texto na introdução, antes de abrir o PDF ou o leitor.';
    }
    if (type === 'video') {
        return 'Descrição complementar exibida junto ao vídeo (quando houver painel de recursos).';
    }
    return 'Texto de apoio exibido na área do aluno.';
}
</script>

<template>
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-sm font-semibold text-zinc-800 dark:text-zinc-200"
            @click="open = !open"
        >
            <span>Introdução da aula</span>
            <ChevronDown v-if="open" class="h-4 w-4 shrink-0 text-zinc-500" />
            <ChevronRight v-else class="h-4 w-4 shrink-0 text-zinc-500" />
        </button>

        <div v-show="open" class="space-y-4 border-t border-zinc-200 px-3 py-3 dark:border-zinc-700">
            <div v-if="lessonType !== 'text'">
                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Visão geral</label>
                <textarea
                    v-model="lessonForm.content_text"
                    :class="inputClass"
                    class="w-full"
                    rows="5"
                    placeholder="Resumo da aula: o que o aluno vai aprender, referências, dicas..."
                />
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ overviewHint(lessonType) }}
                </p>
            </div>
            <p v-else class="text-xs text-zinc-500 dark:text-zinc-400">
                O conteúdo principal desta aula é o campo <strong>Texto</strong> acima. Use links e anexos abaixo para complementar.
            </p>

            <div>
                <div class="mb-1 flex items-center justify-between gap-2">
                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Links úteis</label>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="(lessonForm.resource_links?.length ?? 0) >= 20"
                        @click="emit('add-resource-link')"
                    >
                        <Plus class="mr-1 h-3.5 w-3.5" />
                        Adicionar
                    </Button>
                </div>
                <div
                    v-if="!(lessonForm.resource_links?.length)"
                    class="rounded-md border border-dashed border-zinc-300 px-3 py-3 text-center text-xs text-zinc-500 dark:border-zinc-600"
                >
                    Nenhum link. Ex.: legislação, YouTube, material externo.
                </div>
                <div
                    v-for="(link, linkIdx) in lessonForm.resource_links ?? []"
                    :key="linkIdx"
                    class="mb-2 flex flex-col gap-2 rounded-lg border border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-700 dark:bg-zinc-800/50"
                >
                    <input v-model="link.title" type="text" :class="inputClass" placeholder="Título do link" />
                    <div class="flex gap-2">
                        <input v-model="link.url" type="url" :class="inputClass" class="min-w-0 flex-1" placeholder="https://..." />
                        <button
                            type="button"
                            class="shrink-0 rounded p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30"
                            @click="emit('remove-resource-link-at', linkIdx)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Anexos para download</label>
                <p class="mb-2 text-xs text-zinc-500 dark:text-zinc-400">
                    Arquivos extras (PDF, imagens, ZIP, Office, TXT). Máx.
                    {{ uploadLimits.attachment_max_mb ?? 50 }} MB cada. Até 20 anexos.
                </p>
                <input ref="fileInputRef" type="file" class="hidden" @change="onFileChange" />
                <Button type="button" size="sm" variant="outline" :disabled="attachmentUploading" @click="fileInputRef?.click()">
                    <Paperclip class="mr-1.5 h-3.5 w-3.5" />
                    {{ attachmentUploading ? 'Enviando…' : 'Enviar anexo' }}
                </Button>
                <ul v-if="(lessonForm.attachment_files?.length ?? 0) > 0" class="mt-2 space-y-1">
                    <li
                        v-for="(f, i) in lessonForm.attachment_files"
                        :key="`${f.url}-${i}`"
                        class="flex items-center justify-between gap-2 rounded-md border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-800/50"
                    >
                        <span class="min-w-0 flex-1 truncate text-zinc-600 dark:text-zinc-300">{{ f.name || 'Anexo' }}</span>
                        <button type="button" class="shrink-0 text-red-600 hover:underline" @click="emit('remove-attachment-at', i)">
                            Remover
                        </button>
                    </li>
                </ul>
            </div>

            <div
                class="rounded-lg border px-3 py-2 text-xs"
                :class="
                    commentsEnabled
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30'
                        : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30'
                "
            >
                <div class="flex items-start gap-2">
                    <MessageSquare class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    <div>
                        <template v-if="commentsEnabled">
                            Comentários <strong>ativados</strong> neste produto (aba Comentários).
                        </template>
                        <template v-else>
                            Comentários <strong>desativados</strong>.
                            <button type="button" class="font-semibold underline" @click="emit('go-comentarios')">
                                Ativar na aba Comentários
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

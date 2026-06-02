<script setup>
import { computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';
import LessonIntroSection from '@/components/member-builder/LessonIntroSection.vue';
import { useDragReorderList } from '@/composables/useDragReorderList.js';
import {
    Plus,
    Trash2,
    X,
    FileVideo,
    Link,
    FileText,
    Pencil,
    FolderOpen,
    BookOpen,
    Presentation,
    Copy,
    ChevronLeft,
    LayoutTemplate,
    GripVertical,
} from 'lucide-vue-next';

const props = defineProps({
    modules: { type: Array, default: () => [] },
    selectedModuleId: { type: [Number, String], default: null },
    selectedModule: { type: Object, default: null },
    lessonForm: { type: Object, default: null },
    lessonFormSaving: { type: Boolean, default: false },
    commentsEnabled: { type: Boolean, default: false },
    attachmentUploading: { type: Boolean, default: false },
    modulesReordering: { type: Boolean, default: false },
    lessonsReordering: { type: Boolean, default: false },
    uploadLimits: {
        type: Object,
        default: () => ({ pdf_max_mb: 50, attachment_max_mb: 50 }),
    },
    inputClass: {
        type: String,
        default:
            'block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200',
    },
});

const emit = defineEmits([
    'select-module',
    'clear-selection',
    'open-module-modal',
    'open-module-edit',
    'duplicate-module',
    'delete-module',
    'open-lesson-form',
    'close-lesson-form',
    'duplicate-lesson',
    'delete-lesson',
    'save-lesson',
    'open-pdf-library',
    'open-attachment-library',
    'clear-lesson-pdf',
    'remove-lesson-pdf-at',
    'add-resource-link',
    'remove-resource-link-at',
    'upload-attachment',
    'remove-attachment-at',
    'go-comentarios',
    'open-apply-template',
    'reorder-modules',
    'reorder-lessons',
]);

const sortedLessons = computed(() =>
    [...(props.selectedModule?.lessons ?? [])].sort(
        (a, b) => (a.position ?? 0) - (b.position ?? 0)
    )
);

const canReorderModules = computed(() => props.modules.length > 1);
const canReorderLessons = computed(() => sortedLessons.value.length > 1);

const moduleDrag = useDragReorderList((order) => emit('reorder-modules', order));
const lessonDrag = useDragReorderList((order) => {
    if (props.selectedModuleId != null) {
        emit('reorder-lessons', { moduleId: props.selectedModuleId, order });
    }
});

function isLessonPdfContentType(type) {
    return type === 'pdf' || type === 'pdf_presentation' || type === 'pdf_reader';
}

function pdfLessonFileLabel(type) {
    if (type === 'pdf_presentation') return 'Apresentação';
    if (type === 'pdf_reader') return 'Documento';
    return 'Material';
}

function lessonCountLabel(count) {
    const n = count ?? 0;
    return `${n} ${n === 1 ? 'aula' : 'aulas'}`;
}

const isNewLesson = computed(() => props.lessonForm && !props.lessonForm.id);

function isLessonSelected(lesson) {
    if (!props.lessonForm) return false;
    if (lesson?.id && props.lessonForm.id) return lesson.id == props.lessonForm.id;
    return false;
}

function onMobileClearModule() {
    emit('clear-selection');
}

function onMobileBackToLessons() {
    emit('close-lesson-form');
}
</script>

<template>
    <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    Estrutura do curso
                </h2>
                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                    Módulos à esquerda, aulas no centro e detalhes à direita. Arraste pelo ícone
                    <GripVertical class="inline h-3 w-3 align-text-bottom" />
                    para reordenar.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-700 underline-offset-2 hover:underline dark:text-sky-300"
                    @click="emit('open-pdf-library', 'manage')"
                >
                    <FolderOpen class="h-3.5 w-3.5" />
                    Biblioteca de mídia
                </button>
            </div>
        </div>

        <div class="relative flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden lg:flex-row lg:gap-4">
            <!-- Backdrop mobile: módulo selecionado (painel aulas) ou editor -->
            <div
                v-if="selectedModuleId && !lessonForm"
                class="fixed inset-0 z-30 bg-black/50 lg:hidden"
                aria-hidden="true"
                @click="onMobileClearModule"
            />
            <div
                v-if="lessonForm"
                class="fixed inset-0 z-40 bg-black/50 lg:hidden"
                aria-hidden="true"
                @click="onMobileBackToLessons"
            />

            <!-- Coluna 1: Módulos -->
            <aside
                class="flex min-h-0 w-full shrink-0 flex-col lg:w-72 xl:w-80"
                :class="selectedModuleId ? 'max-lg:hidden' : 'max-lg:flex-1'"
            >
                <Button size="sm" class="mb-3 w-full shrink-0" @click="emit('open-module-modal')">
                    <Plus class="mr-1.5 h-4 w-4" />
                    Novo módulo
                </Button>

                <div
                    v-if="!modules.length"
                    class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400"
                >
                    Nenhum módulo. Clique em &quot;Novo módulo&quot; para começar.
                </div>

                <ul
                    v-else
                    class="min-h-0 flex-1 space-y-1 overflow-y-auto rounded-xl border border-zinc-200 bg-zinc-50/50 p-2 dark:border-zinc-700 dark:bg-zinc-800/30"
                >
                    <li
                        v-for="mod in modules"
                        :key="mod.id"
                        @dragover="moduleDrag.onDragOver($event, mod.id)"
                        @dragleave="moduleDrag.onDragLeave(mod.id)"
                        @drop="moduleDrag.onDrop($event, modules, mod.id)"
                    >
                        <div
                            class="group flex w-full items-center gap-1 rounded-lg transition"
                            :class="[
                                selectedModuleId == mod.id
                                    ? 'bg-sky-600 text-white shadow-sm dark:bg-sky-500'
                                    : 'hover:bg-white dark:hover:bg-zinc-700/60',
                                moduleDrag.isDragOver(mod.id)
                                    ? 'ring-2 ring-sky-400 ring-offset-1 dark:ring-sky-300'
                                    : '',
                                moduleDrag.isDragging(mod.id) ? 'opacity-40' : '',
                            ]"
                        >
                            <button
                                v-if="canReorderModules"
                                type="button"
                                class="ml-1 shrink-0 cursor-grab touch-none rounded p-1 active:cursor-grabbing"
                                :class="
                                    selectedModuleId == mod.id
                                        ? 'text-sky-100/80 hover:bg-sky-500/50'
                                        : 'text-zinc-400 hover:bg-zinc-200/80 dark:hover:bg-zinc-600'
                                "
                                title="Arrastar para reordenar"
                                draggable="true"
                                :disabled="modulesReordering"
                                @dragstart="moduleDrag.onDragStart($event, mod.id)"
                                @dragend="moduleDrag.onDragEnd"
                                @click.stop
                            >
                                <GripVertical class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 items-center gap-2.5 px-2 py-2.5 text-left"
                                @click="emit('select-module', mod.id)"
                            >
                                <div
                                    class="h-10 w-10 shrink-0 overflow-hidden rounded-md bg-zinc-200 dark:bg-zinc-700"
                                    :class="
                                        selectedModuleId == mod.id
                                            ? 'ring-1 ring-white/30'
                                            : ''
                                    "
                                >
                                    <img
                                        v-if="mod.thumbnail"
                                        :src="mod.thumbnail"
                                        :alt="mod.title"
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center"
                                        :class="
                                            selectedModuleId == mod.id
                                                ? 'text-sky-100'
                                                : 'text-zinc-400 dark:text-zinc-500'
                                        "
                                    >
                                        <BookOpen class="h-4 w-4" />
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ mod.title }}</p>
                                    <p
                                        class="text-[11px]"
                                        :class="
                                            selectedModuleId == mod.id
                                                ? 'text-sky-100'
                                                : 'text-zinc-500 dark:text-zinc-400'
                                        "
                                    >
                                        {{ lessonCountLabel(mod.lessons?.length) }}
                                    </p>
                                </div>
                            </button>
                            <div
                                class="flex shrink-0 items-center gap-0.5 pr-1.5 opacity-100 lg:opacity-0 lg:group-hover:opacity-100"
                                :class="selectedModuleId == mod.id ? '!opacity-100' : ''"
                            >
                                <button
                                    type="button"
                                    class="rounded p-1.5 transition"
                                    :class="
                                        selectedModuleId == mod.id
                                            ? 'text-sky-100 hover:bg-sky-500/80'
                                            : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-600'
                                    "
                                    title="Duplicar"
                                    @click.stop="emit('duplicate-module', mod.id)"
                                >
                                    <Copy class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1.5 transition"
                                    :class="
                                        selectedModuleId == mod.id
                                            ? 'text-sky-100 hover:bg-sky-500/80'
                                            : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-600'
                                    "
                                    title="Editar"
                                    @click.stop="emit('open-module-edit', mod)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1.5 text-red-500 hover:bg-red-500/10"
                                    :class="selectedModuleId == mod.id ? 'text-red-200 hover:bg-red-500/30' : ''"
                                    title="Remover"
                                    @click.stop="emit('delete-module', mod.id)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </aside>

            <!-- Placeholder desktop: nenhum módulo selecionado -->
            <div
                v-if="!selectedModuleId"
                class="hidden min-h-0 flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 bg-white/50 p-8 text-center dark:border-zinc-600 dark:bg-zinc-900/20 lg:flex"
            >
                <BookOpen class="mx-auto h-12 w-12 text-zinc-300 dark:text-zinc-600" />
                <p class="mt-3 text-sm font-medium text-zinc-600 dark:text-zinc-300">Selecione um módulo</p>
                <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">
                    Escolha um módulo na lista para ver e editar as aulas.
                </p>
            </div>

            <!-- Coluna 2: Aulas -->
            <section
                v-if="selectedModuleId"
                class="flex min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900/40 max-lg:fixed max-lg:inset-y-0 max-lg:right-0 max-lg:z-40 max-lg:w-full max-lg:max-w-md max-lg:shadow-xl lg:w-72 lg:shrink-0 xl:w-84"
                :class="lessonForm ? 'max-lg:hidden' : ''"
            >
                <div class="flex shrink-0 items-center gap-2 border-b border-zinc-200 px-3 py-2.5 dark:border-zinc-700">
                    <button
                        type="button"
                        class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 lg:hidden dark:hover:bg-zinc-700"
                        title="Voltar aos módulos"
                        @click="onMobileClearModule"
                    >
                        <ChevronLeft class="h-5 w-5" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            Aulas
                        </p>
                        <h3 class="truncate text-sm font-bold text-zinc-900 dark:text-zinc-100">
                            {{ selectedModule?.title }}
                        </h3>
                    </div>
                    <Button size="sm" variant="outline" class="shrink-0 !py-1" @click="emit('open-module-edit', selectedModule)">
                        <Pencil class="mr-1 h-3.5 w-3.5" />
                        Módulo
                    </Button>
                    <Button size="sm" class="shrink-0" @click="emit('open-lesson-form', null)">
                        <Plus class="mr-1 h-3.5 w-3.5" />
                        Nova aula
                    </Button>
                </div>

                <ul class="min-h-0 flex-1 space-y-0.5 overflow-y-auto p-2">
                    <li
                        v-for="lesson in sortedLessons"
                        :key="lesson.id"
                        @dragover="lessonDrag.onDragOver($event, lesson.id)"
                        @dragleave="lessonDrag.onDragLeave(lesson.id)"
                        @drop="lessonDrag.onDrop($event, sortedLessons, lesson.id)"
                    >
                        <div
                            class="group flex cursor-pointer items-center gap-1 rounded-lg px-1 py-2 text-sm transition"
                            :class="[
                                isLessonSelected(lesson)
                                    ? 'bg-sky-600 text-white shadow-sm dark:bg-sky-500'
                                    : 'hover:bg-zinc-100 dark:hover:bg-zinc-800/80',
                                lessonDrag.isDragOver(lesson.id)
                                    ? 'ring-2 ring-sky-400 ring-offset-1 dark:ring-sky-300'
                                    : '',
                                lessonDrag.isDragging(lesson.id) ? 'opacity-40' : '',
                            ]"
                            @click="emit('open-lesson-form', lesson)"
                        >
                            <button
                                v-if="canReorderLessons"
                                type="button"
                                class="shrink-0 cursor-grab touch-none rounded p-1 active:cursor-grabbing"
                                :class="
                                    isLessonSelected(lesson)
                                        ? 'text-sky-100/80 hover:bg-sky-500/50'
                                        : 'text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700'
                                "
                                title="Arrastar para reordenar"
                                draggable="true"
                                :disabled="lessonsReordering"
                                @dragstart="lessonDrag.onDragStart($event, lesson.id)"
                                @dragend="lessonDrag.onDragEnd"
                                @click.stop
                            >
                                <GripVertical class="h-3.5 w-3.5" />
                            </button>
                            <span class="flex min-w-0 flex-1 items-center gap-2 truncate">
                                <FileVideo
                                    v-if="lesson.type === 'video'"
                                    class="h-4 w-4 shrink-0"
                                    :class="isLessonSelected(lesson) ? 'text-sky-100' : 'text-zinc-500'"
                                />
                                <Link
                                    v-else-if="lesson.type === 'link'"
                                    class="h-4 w-4 shrink-0"
                                    :class="isLessonSelected(lesson) ? 'text-sky-100' : 'text-zinc-500'"
                                />
                                <Presentation
                                    v-else-if="lesson.type === 'pdf_presentation'"
                                    class="h-4 w-4 shrink-0"
                                    :class="isLessonSelected(lesson) ? 'text-sky-100' : 'text-zinc-500'"
                                />
                                <BookOpen
                                    v-else-if="lesson.type === 'pdf_reader'"
                                    class="h-4 w-4 shrink-0"
                                    :class="isLessonSelected(lesson) ? 'text-sky-100' : 'text-zinc-500'"
                                />
                                <FileText
                                    v-else
                                    class="h-4 w-4 shrink-0"
                                    :class="isLessonSelected(lesson) ? 'text-sky-100' : 'text-zinc-500'"
                                />
                                <span class="truncate font-medium">{{ lesson.title || 'Sem título' }}</span>
                            </span>
                            <div class="flex shrink-0 items-center gap-0.5">
                                <button
                                    type="button"
                                    class="rounded p-1 opacity-100 lg:opacity-0 lg:group-hover:opacity-100"
                                    :class="
                                        isLessonSelected(lesson)
                                            ? 'text-sky-100 hover:bg-sky-500/80'
                                            : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-700'
                                    "
                                    title="Duplicar aula"
                                    @click.stop="emit('duplicate-lesson', lesson.id)"
                                >
                                    <Copy class="h-3 w-3" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 text-red-600 opacity-100 hover:bg-red-50 lg:opacity-0 lg:group-hover:opacity-100 dark:hover:bg-red-950/30"
                                    :class="isLessonSelected(lesson) ? '!text-red-200 hover:!bg-red-500/30' : ''"
                                    title="Remover aula"
                                    @click.stop="emit('delete-lesson', lesson.id)"
                                >
                                    <Trash2 class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>

                <p
                    v-if="!selectedModule?.lessons?.length"
                    class="px-4 py-6 text-center text-xs text-zinc-500 dark:text-zinc-400"
                >
                    Nenhuma aula neste módulo. Use &quot;Nova aula&quot; para adicionar.
                </p>
            </section>

            <!-- Coluna 3: Editor -->
            <aside
                v-if="selectedModuleId"
                class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50/80 dark:border-zinc-700 dark:bg-zinc-800/40 lg:min-w-[22rem] xl:min-w-[28rem]"
                :class="
                    lessonForm
                        ? 'max-lg:fixed max-lg:inset-0 max-lg:z-50 max-lg:max-w-none max-lg:rounded-none max-lg:border-0'
                        : 'max-lg:hidden'
                "
            >
                <div
                    v-if="!lessonForm"
                    class="flex min-h-[12rem] flex-1 flex-col items-center justify-center p-6 text-center lg:min-h-0"
                >
                    <FileText class="h-10 w-10 text-zinc-300 dark:text-zinc-600" />
                    <p class="mt-3 text-sm font-medium text-zinc-600 dark:text-zinc-300">
                        Selecione uma aula
                    </p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        Clique em uma aula na lista ou crie uma nova.
                    </p>
                    <Button size="sm" class="mt-4" variant="outline" @click="emit('open-lesson-form', null)">
                        <Plus class="mr-1.5 h-4 w-4" />
                        Nova aula
                    </Button>
                </div>

                <template v-else>
                    <div class="flex shrink-0 items-center justify-between gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                {{ isNewLesson ? 'Nova aula' : 'Editar aula' }}
                            </p>
                            <p class="truncate text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                {{ selectedModule?.title }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded p-1.5 text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-600 lg:hidden"
                            title="Voltar às aulas"
                            @click="onMobileBackToLessons"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-4">
                        <div class="space-y-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Título</label>
                                <input
                                    v-model="lessonForm.title"
                                    type="text"
                                    :class="inputClass"
                                    class="w-full"
                                    placeholder="Título da aula"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Tipo</label>
                                <select v-model="lessonForm.type" :class="inputClass" class="w-full">
                                    <option value="video">Vídeo</option>
                                    <option value="link">Link</option>
                                    <option value="pdf">Material</option>
                                    <option value="pdf_presentation">Apresentação (PDF)</option>
                                    <option value="pdf_reader">Leitor de PDF</option>
                                    <option value="text">Texto</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Liberação</label>
                                <div class="grid gap-2">
                                    <select v-model="lessonForm.release_mode" :class="inputClass" class="w-full">
                                        <option value="none">Imediata</option>
                                        <option value="days">Após X dias</option>
                                        <option value="date">Na data</option>
                                    </select>
                                    <input
                                        v-if="lessonForm.release_mode === 'days'"
                                        v-model="lessonForm.release_after_days"
                                        type="number"
                                        min="1"
                                        step="1"
                                        :class="inputClass"
                                        class="w-full"
                                        placeholder="Ex.: 7"
                                    />
                                    <input
                                        v-else-if="lessonForm.release_mode === 'date'"
                                        v-model="lessonForm.release_at_date"
                                        type="date"
                                        :class="inputClass"
                                        class="w-full"
                                    />
                                </div>
                            </div>
                            <div v-if="lessonForm.type === 'link'">
                                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Título do link</label>
                                <input
                                    v-model="lessonForm.link_title"
                                    type="text"
                                    :class="inputClass"
                                    class="w-full"
                                    placeholder="Ex: Abrir material complementar"
                                />
                            </div>
                            <div
                                v-if="
                                    lessonForm.type === 'video' ||
                                    lessonForm.type === 'link' ||
                                    isLessonPdfContentType(lessonForm.type)
                                "
                            >
                                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">URL do conteúdo</label>
                                <input
                                    v-model="lessonForm.content_url"
                                    type="url"
                                    :class="inputClass"
                                    class="w-full"
                                    placeholder="https://..."
                                />
                                <p v-if="lessonForm.type === 'video'" class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    Aceita links do YouTube, Vimeo, Wistia, Loom e outras plataformas de vídeo compatíveis.
                                </p>
                            </div>
                            <div v-if="lessonForm.type === 'text'">
                                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Texto</label>
                                <textarea
                                    v-model="lessonForm.content_text"
                                    :class="inputClass"
                                    class="w-full"
                                    rows="3"
                                    placeholder="Conteúdo da aula..."
                                />
                            </div>
                            <div v-if="isLessonPdfContentType(lessonForm.type)" class="space-y-2">
                                <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                    {{
                                        lessonForm.type === 'pdf_presentation'
                                            ? 'Arquivos da apresentação'
                                            : lessonForm.type === 'pdf_reader'
                                              ? 'Arquivos do leitor'
                                              : 'Materiais PDF'
                                    }}
                                </label>
                                <div class="flex flex-wrap items-center gap-2">
                                    <Button type="button" size="sm" variant="outline" @click="emit('open-pdf-library', 'pick')">
                                        <FolderOpen class="mr-1.5 h-3.5 w-3.5" />
                                        {{
                                            lessonForm.type === 'pdf'
                                                ? 'Biblioteca de materiais'
                                                : 'Biblioteca de mídia'
                                        }}
                                    </Button>
                                    <span
                                        v-if="(lessonForm.content_files?.length ?? 0) > 0"
                                        class="text-xs text-zinc-500 dark:text-zinc-400"
                                    >
                                        {{ lessonForm.content_files.length }} arquivo(s)
                                    </span>
                                    <button
                                        v-if="(lessonForm.content_files?.length ?? 0) > 0"
                                        type="button"
                                        class="text-xs text-red-600 hover:underline"
                                        @click="emit('clear-lesson-pdf')"
                                    >
                                        Remover todos
                                    </button>
                                </div>
                                <div v-if="(lessonForm.content_files?.length ?? 0) > 0" class="space-y-1">
                                    <div
                                        v-for="(f, i) in lessonForm.content_files"
                                        :key="`${f.url}-${i}`"
                                        class="flex items-center justify-between gap-2 rounded-md border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-800/50"
                                    >
                                        <span class="min-w-0 flex-1 truncate text-zinc-600 dark:text-zinc-300">{{
                                            f.name || pdfLessonFileLabel(lessonForm.type)
                                        }}</span>
                                        <button
                                            type="button"
                                            class="shrink-0 text-red-600 hover:underline"
                                            @click="emit('remove-lesson-pdf-at', i)"
                                        >
                                            Remover
                                        </button>
                                    </div>
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Máx. {{ uploadLimits.pdf_max_mb }} MB por arquivo.
                                </p>
                            </div>
                            <div
                                v-if="lessonForm.type === 'video'"
                                class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800/50"
                            >
                                <Toggle v-model="lessonForm.watermark_enabled" label="Marca d'água (proteção DRM)" />
                            </div>

                            <LessonIntroSection
                                v-if="lessonForm"
                                :lesson-form="lessonForm"
                                :lesson-type="lessonForm.type"
                                :comments-enabled="commentsEnabled"
                                :attachment-uploading="attachmentUploading"
                                :upload-limits="uploadLimits"
                                :input-class="inputClass"
                                @add-resource-link="emit('add-resource-link')"
                                @remove-resource-link-at="emit('remove-resource-link-at', $event)"
                                @upload-attachment="emit('upload-attachment', $event)"
                                @remove-attachment-at="emit('remove-attachment-at', $event)"
                                @open-attachment-library="emit('open-attachment-library')"
                                @go-comentarios="emit('go-comentarios')"
                            />

                            <Button
                                v-if="lessonForm.id"
                                type="button"
                                size="sm"
                                variant="outline"
                                class="w-full"
                                @click="emit('open-apply-template')"
                            >
                                <LayoutTemplate class="mr-1.5 h-4 w-4" />
                                Replicar esta aula em outras…
                            </Button>
                        </div>
                    </div>

                    <div
                        class="flex shrink-0 flex-col gap-2 border-t border-zinc-200 bg-white/90 p-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/90 sm:flex-row"
                    >
                        <Button variant="outline" size="sm" class="flex-1" @click="emit('close-lesson-form')">
                            Cancelar
                        </Button>
                        <Button
                            size="sm"
                            class="flex-1"
                            :disabled="lessonFormSaving"
                            @click="emit('save-lesson')"
                        >
                            {{ lessonFormSaving ? 'Salvando…' : 'Salvar' }}
                        </Button>
                    </div>
                </template>
            </aside>
        </div>
    </div>
</template>

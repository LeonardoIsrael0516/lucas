<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { CheckCircle, ChevronDown, Lock, Play } from 'lucide-vue-next';

const props = defineProps({
    product: { type: Object, required: true },
    module: { type: Object, required: true },
    slug: { type: String, required: true },
    modules: { type: Array, default: () => [] },
    current_lesson: { type: Object, default: null },
    course_lesson_progress: { type: Object, default: () => ({ completed: 0, total: 0 }) },
    moduleId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close']);

const lessonSidebarQuery = ref('');
const expandedModuleIds = ref(new Set());

function initExpanded() {
    const next = new Set(expandedModuleIds.value);
    if (props.moduleId != null) {
        next.add(props.moduleId);
    }
    expandedModuleIds.value = next;
}
initExpanded();

const progressPercent = computed(() => {
    const { completed, total } = props.course_lesson_progress || {};
    if (!total) return 0;
    return Math.min(100, Math.round((completed / total) * 100));
});

function filteredLessonsForModule(m) {
    const q = lessonSidebarQuery.value.trim().toLowerCase();
    const list = Array.isArray(m?.lessons) ? m.lessons : [];
    if (!q) return list;
    return list.filter((l) => (l.title || '').toLowerCase().includes(q));
}

function lessonUrl(moduleId, lessonId) {
    return `/m/${props.slug}/modulo/${moduleId}?aula=${lessonId}`;
}

function isModuleExpanded(mid) {
    return expandedModuleIds.value.has(mid);
}

function toggleModuleExpansion(m) {
    if (m.is_locked) return;
    const mid = m.id;
    const next = new Set(expandedModuleIds.value);
    if (next.has(mid)) next.delete(mid);
    else next.add(mid);
    expandedModuleIds.value = next;
}

function isLessonCompleted(lesson) {
    return !!lesson.is_completed;
}

function lessonStatus(lesson, m) {
    if (lesson.is_locked || m.is_locked) return 'locked';
    if (props.current_lesson?.id === lesson.id && m.id === props.moduleId) return 'current';
    if (isLessonCompleted(lesson)) return 'done';
    return 'pending';
}
</script>

<template>
    <aside
        class="flex h-full w-full shrink-0 flex-col border-l border-[var(--lesson-border,#d5dded)] bg-white bg-[var(--lesson-surface,#ffffff)] lg:w-[var(--lesson-rs-w,308px)]"
    >
        <div class="shrink-0 border-b border-[var(--lesson-border)] p-4">
            <div class="mb-3 flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <Link href="/area-membros" class="text-xs font-semibold text-[var(--lesson-text-3)] hover:text-[var(--student-primary)]">
                        ← Meus cursos
                    </Link>
                    <h2 class="mt-1 font-[family-name:var(--font-sans)] text-sm font-bold leading-snug text-[#001e45]">
                        {{ product?.name }}
                    </h2>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-[var(--lesson-text-3)] hover:bg-[var(--lesson-bg)] lg:hidden"
                    aria-label="Fechar lista"
                    @click="emit('close')"
                >
                    ✕
                </button>
            </div>
            <div v-if="course_lesson_progress.total > 0" class="mb-3">
                <div class="mb-1 flex justify-between text-xs text-[var(--lesson-text-3)]">
                    <span>Progresso</span>
                    <span class="font-bold text-emerald-600">{{ course_lesson_progress.completed }} / {{ course_lesson_progress.total }}</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-[var(--lesson-bg2)]">
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-emerald-600 to-emerald-400 transition-[width]"
                        :style="{ width: `${progressPercent}%` }"
                    />
                </div>
            </div>
            <input
                v-model="lessonSidebarQuery"
                type="search"
                placeholder="Buscar aula…"
                class="w-full rounded-lg border border-[var(--lesson-border)] bg-[var(--lesson-bg)] px-3 py-2 text-sm text-[var(--lesson-text)] placeholder:text-[var(--lesson-text-3)] focus:border-[var(--student-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--student-primary)]"
                autocomplete="off"
            />
        </div>

        <nav class="min-h-0 flex-1 overflow-y-auto p-2">
            <template v-for="m in modules" :key="m.id">
                <div class="mb-1 rounded-lg" :class="m.id === moduleId ? 'bg-[var(--lesson-bg)]' : ''">
                    <button
                        v-if="!m.is_locked"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg px-2 py-2.5 text-left text-sm font-semibold text-[var(--lesson-text)] hover:bg-[var(--lesson-bg)]"
                        @click="toggleModuleExpansion(m)"
                    >
                        <ChevronDown
                            class="h-4 w-4 shrink-0 text-[var(--lesson-text-3)] transition-transform"
                            :class="isModuleExpanded(m.id) ? 'rotate-180' : ''"
                        />
                        <span class="min-w-0 flex-1 truncate">{{ m.title || 'Módulo' }}</span>
                    </button>
                    <div v-else class="flex items-center gap-2 px-2 py-2.5 text-sm opacity-70">
                        <Lock class="h-4 w-4 shrink-0 text-amber-600" />
                        <span class="truncate text-[var(--lesson-text-2)]">{{ m.title }}</span>
                    </div>

                    <div v-show="isModuleExpanded(m.id) && !m.is_locked" class="space-y-0.5 pb-2 pl-1">
                        <template v-for="(lesson, idx) in filteredLessonsForModule(m)" :key="lesson.id">
                            <Link
                                v-if="!lesson.is_locked"
                                :href="lessonUrl(m.id, lesson.id)"
                                class="group flex items-start gap-2 rounded-lg px-2 py-2 text-sm transition"
                                :class="
                                    lessonStatus(lesson, m) === 'current'
                                        ? 'bg-emerald-50 ring-1 ring-emerald-200'
                                        : 'hover:bg-[var(--lesson-bg)]'
                                "
                                @click="emit('close')"
                            >
                                <CheckCircle
                                    v-if="lessonStatus(lesson, m) === 'done'"
                                    class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"
                                />
                                <Play
                                    v-else-if="lessonStatus(lesson, m) === 'current'"
                                    class="mt-0.5 h-4 w-4 shrink-0 fill-emerald-600 text-emerald-600"
                                />
                                <span
                                    v-else
                                    class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-[var(--lesson-border2)] text-[10px] text-[var(--lesson-text-3)]"
                                >
                                    {{ idx + 1 }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate font-medium"
                                        :class="lessonStatus(lesson, m) === 'current' ? 'text-emerald-900' : 'text-[var(--lesson-text)]'"
                                    >
                                        {{ lesson.title || 'Sem título' }}
                                    </span>
                                    <span
                                        v-if="lessonStatus(lesson, m) === 'done'"
                                        class="mt-0.5 inline-block text-[10px] font-semibold uppercase text-emerald-600"
                                    >
                                        Concluída
                                    </span>
                                    <span v-if="lesson.pages_count" class="mt-0.5 block text-[11px] text-[var(--lesson-text-3)]">
                                        {{ lesson.pages_count }} pág.
                                    </span>
                                </span>
                            </Link>
                            <div v-else class="flex cursor-not-allowed items-start gap-2 rounded-lg px-2 py-2 text-sm opacity-60">
                                <Lock class="mt-0.5 h-4 w-4 shrink-0" />
                                <span class="truncate text-[var(--lesson-text-2)]">{{ lesson.title }}</span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </nav>
    </aside>
</template>

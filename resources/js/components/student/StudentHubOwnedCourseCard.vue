<script setup>
import StudentCourseCrest from '@/components/student/StudentCourseCrest.vue';
import { Calendar, ChevronRight, MoreVertical, RotateCcw } from 'lucide-vue-next';

const props = defineProps({
    course: { type: Object, required: true },
    showActionsMenu: { type: Boolean, default: false },
    refundSettingsEnabled: { type: Boolean, default: false },
    menuOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-menu', 'refund']);

function progressLabel() {
    const c = props.course;
    const pct = c.completion_percent ?? 0;
    const done = c.lessons_completed ?? 0;
    const total = c.lessons_total ?? 0;
    if (total > 0) {
        return `${pct}% concluído • ${done} de ${total} aulas`;
    }
    return `${pct}% concluído`;
}
</script>

<template>
    <div class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
        <div class="flex items-start justify-between gap-2 border-b border-zinc-100 px-4 py-2.5">
            <span
                v-if="course.has_new_content"
                class="rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800"
            >
                Novo conteúdo
            </span>
            <span v-else class="flex-1" />
            <span
                v-if="course.access_until_label"
                class="inline-flex items-center gap-1 text-xs text-zinc-500"
            >
                <Calendar class="h-3.5 w-3.5 shrink-0" />
                {{ course.access_until_label }}
            </span>
        </div>

        <div class="flex gap-4 p-4 sm:p-5">
            <StudentCourseCrest :src="course.crest_url" :name="course.name" size="lg" plain shape="circle" />
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="line-clamp-2 text-base font-bold leading-snug text-zinc-900">{{ course.name }}</h3>
                        <p v-if="course.subtitle" class="mt-1 line-clamp-2 text-sm text-zinc-600">{{ course.subtitle }}</p>
                    </div>
                    <div
                        v-if="showActionsMenu"
                        class="relative shrink-0"
                        :data-course-menu="course.id"
                    >
                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-700"
                            aria-label="Ações do curso"
                            :aria-expanded="menuOpen"
                            @click.stop="emit('toggle-menu')"
                        >
                            <MoreVertical class="h-4 w-4" />
                        </button>
                        <div
                            v-show="menuOpen"
                            class="absolute right-0 top-full z-30 mt-1 w-52 rounded-xl border border-zinc-200 bg-white py-1 shadow-lg"
                            role="menu"
                        >
                            <button
                                v-if="course.refund?.can_request"
                                type="button"
                                role="menuitem"
                                class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-red-700 hover:bg-red-50"
                                @click="emit('refund')"
                            >
                                <RotateCcw class="h-4 w-4 shrink-0" />
                                Solicitar reembolso
                            </button>
                            <p
                                v-else-if="course.refund?.pending"
                                role="menuitem"
                                class="px-3 py-2.5 text-sm text-amber-700"
                            >
                                Reembolso em análise
                            </p>
                            <p
                                v-else-if="course.refund?.status === 'denied' && !course.refund?.can_request"
                                role="menuitem"
                                class="px-3 py-2.5 text-sm text-zinc-500"
                            >
                                Reembolso negado
                            </p>
                            <p v-else role="menuitem" class="px-3 py-2.5 text-sm text-zinc-500">
                                Reembolso indisponível
                            </p>
                        </div>
                    </div>
                </div>

                <p v-if="course.apostilas_label" class="mt-2 text-sm text-zinc-600">{{ course.apostilas_label }}</p>
                <div v-if="course.download_progress" class="mt-2">
                    <div class="flex justify-between text-xs text-zinc-500">
                        <span>{{ course.download_progress.done }} / {{ course.download_progress.total }} baixados</span>
                    </div>
                    <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-zinc-200">
                        <div
                            class="h-full rounded-full bg-sky-500"
                            :style="{
                                width:
                                    course.download_progress.total > 0
                                        ? Math.round((course.download_progress.done / course.download_progress.total) * 100) + '%'
                                        : '0%',
                            }"
                        />
                    </div>
                </div>

                <p
                    v-if="refundSettingsEnabled && course.refund?.pending"
                    class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800"
                >
                    Reembolso em análise
                </p>

                <p class="mt-3 text-xs text-zinc-500">{{ progressLabel() }}</p>
                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-zinc-200">
                    <div
                        class="h-full rounded-full bg-emerald-500 transition-[width]"
                        :style="{ width: `${Math.min(100, course.completion_percent ?? 0)}%` }"
                    />
                </div>
            </div>
        </div>

        <div class="flex border-t border-zinc-100">
            <a
                :href="course.continue_href"
                class="inline-flex flex-1 items-center justify-center gap-1 bg-[#002147] px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-[#001a38]"
                :style="{ backgroundColor: 'var(--student-primary, #002147)' }"
            >
                Continuar
                <ChevronRight class="h-4 w-4" />
            </a>
            <a
                :href="course.member_area_href"
                class="inline-flex flex-1 items-center justify-center border-l border-zinc-200 px-4 py-3.5 text-sm font-medium text-zinc-800 transition hover:bg-zinc-50"
            >
                Acessar conteúdo
            </a>
        </div>
    </div>
</template>

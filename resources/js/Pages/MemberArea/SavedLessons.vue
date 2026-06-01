<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import StudentAreaDocumentHead from '@/components/student/StudentAreaDocumentHead.vue';
import StudentHubSidebar from '@/components/student/StudentHubSidebar.vue';
import StudentAreaBackToPanelLink from '@/components/student/StudentAreaBackToPanelLink.vue';
import { Bookmark, ChevronRight, ExternalLink } from 'lucide-vue-next';

defineProps({
    student_branding: { type: Object, default: () => ({}) },
    hub_nav: { type: Object, default: () => ({}) },
    profile_href: { type: String, default: '/meu-perfil' },
    suporte_href: { type: String, default: null },
    saved_lessons: { type: Array, default: () => [] },
});

const sidebarCollapsed = ref(false);
const page = usePage();
const coursesHref = computed(() => {
    const base = String(page.props.app_url || '').replace(/\/$/, '');
    return base ? `${base}/area-membros` : '/area-membros';
});
</script>

<template>
    <div
        class="flex min-h-[100dvh] bg-white text-zinc-900"
        :style="{ '--student-primary': student_branding?.primary || '#0ea5e9' }"
    >
        <StudentAreaDocumentHead title="Aulas salvas" :student_branding="student_branding" />

        <StudentHubSidebar
            class="hidden min-h-0 md:flex"
            :student_branding="student_branding"
            :hub_nav="hub_nav"
            :profile_href="profile_href"
            :suporte_href="suporte_href"
            active="saved"
            :courses_href="coursesHref"
            variant="navy"
            :collapsed="sidebarCollapsed"
            @update:collapsed="sidebarCollapsed = $event"
        />

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="shrink-0 border-b border-zinc-200 bg-white px-4 py-3 md:px-6">
                <div class="flex items-center justify-between gap-3">
                    <h1 class="text-lg font-semibold md:text-xl">Aulas salvas</h1>
                    <StudentAreaBackToPanelLink variant="header" />
                </div>
            </header>

            <main class="flex-1 overflow-y-auto bg-white p-4 md:p-6">
                <p class="mb-6 text-sm text-zinc-600">
                    Aulas que você marcou com «Salvar aula» no leitor de PDF. Toque para continuar de onde parou.
                </p>

                <ul v-if="saved_lessons.length" class="space-y-3">
                    <li
                        v-for="item in saved_lessons"
                        :key="`${item.product_id}-${item.lesson_id}`"
                        class="rounded-xl border border-zinc-200 bg-white shadow-sm"
                    >
                        <a
                            :href="item.lesson_href"
                            class="flex items-center gap-4 p-4 transition hover:bg-zinc-50"
                        >
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[color-mix(in_srgb,var(--student-primary)_15%,transparent)]"
                            >
                                <Bookmark class="h-5 w-5 text-[var(--student-primary)]" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-zinc-900">{{ item.lesson_title }}</p>
                                <p class="mt-0.5 truncate text-sm text-zinc-500">
                                    {{ item.product_name }}
                                    <span v-if="item.module_title"> · {{ item.module_title }}</span>
                                </p>
                                <p v-if="item.saved_at" class="mt-1 text-xs text-zinc-400">Salva em {{ item.saved_at }}</p>
                            </div>
                            <ExternalLink class="h-4 w-4 shrink-0 text-zinc-400" />
                        </a>
                    </li>
                </ul>

                <div
                    v-else
                    class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50/50 py-16 text-center"
                >
                    <Bookmark class="mx-auto h-12 w-12 text-zinc-300" />
                    <p class="mt-4 text-sm font-medium text-zinc-600">Nenhuma aula salva ainda</p>
                    <p class="mt-1 text-xs text-zinc-500">
                        No leitor de PDF, use o botão «Salvar aula» abaixo do documento.
                    </p>
                    <Link
                        :href="coursesHref"
                        class="mt-6 inline-flex items-center gap-1 text-sm font-medium text-[var(--student-primary)] hover:underline"
                    >
                        Ir para meus cursos
                        <ChevronRight class="h-4 w-4" />
                    </Link>
                </div>
            </main>
        </div>
    </div>
</template>

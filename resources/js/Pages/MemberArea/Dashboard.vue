<script setup>
import { computed, onMounted, onUnmounted, ref, toRef } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import StudentAreaDocumentHead from '@/components/student/StudentAreaDocumentHead.vue';
import ThemeToggler from '@/components/layout/ThemeToggler.vue';
import StudentCourseCover from '@/components/student/StudentCourseCover.vue';
import StudentAreaBackToPanelLink from '@/components/student/StudentAreaBackToPanelLink.vue';
import RefundRequestModal from '@/components/member-area/RefundRequestModal.vue';
import { useStudentAreaSidebarLogo } from '@/composables/useStudentAreaLogo';
import { Award, Bell, BookOpen, ChevronRight, LayoutGrid, LifeBuoy, Lock, LogOut, MessageCircle, MoreVertical, RotateCcw, Search, Trophy, UserRound } from 'lucide-vue-next';

const props = defineProps({
    search_query: { type: String, default: '' },
    auth_user: { type: Object, default: () => ({}) },
    notifications_unread_count: { type: Number, default: 0 },
    continue_items: { type: Array, default: () => [] },
    my_courses: { type: Array, default: () => [] },
    recommended_courses: { type: Array, default: () => [] },
    other_courses: { type: Array, default: () => [] },
    hub_nav: {
        type: Object,
        default: () => ({ community_enabled: false, certificate_enabled: false, gamification_enabled: false }),
    },
    suporte_href: { type: String, default: null },
    profile_href: { type: String, default: '/meu-perfil' },
    student_branding: {
        type: Object,
        default: () => ({
            primary: '#0ea5e9',
            logo_url: null,
            logo_light_url: null,
            logo_light_collapsed_url: null,
            logo_dark_url: null,
            logo_dark_collapsed_url: null,
        }),
    },
    support_whatsapp: { type: Object, default: () => ({ enabled: false, url: '' }) },
    refund_settings: { type: Object, default: () => ({ enabled: false, days: 7 }) },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const refundModalOpen = ref(false);
const refundModalCourse = ref(null);
const refundModalRef = ref(null);

function openRefundModal(course) {
    refundModalCourse.value = course;
    refundModalOpen.value = true;
    refundModalRef.value?.openFor(course);
}

function closeRefundModal() {
    refundModalOpen.value = false;
    refundModalCourse.value = null;
}

const openCourseMenuId = ref(null);

function showCourseActionsMenu() {
    return !!props.refund_settings?.enabled;
}

function toggleCourseMenu(courseId) {
    openCourseMenuId.value = openCourseMenuId.value === courseId ? null : courseId;
}

function closeCourseMenu() {
    openCourseMenuId.value = null;
}

function openRefundFromMenu(course) {
    closeCourseMenu();
    openRefundModal(course);
}

function handleCourseMenuClickOutside(event) {
    if (openCourseMenuId.value == null) {
        return;
    }
    const menuEl = document.querySelector(`[data-course-menu="${openCourseMenuId.value}"]`);
    if (menuEl && !menuEl.contains(event.target)) {
        closeCourseMenu();
    }
}

onMounted(() => {
    document.addEventListener('click', handleCourseMenuClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleCourseMenuClickOutside);
});

const searchLocal = ref(props.search_query || '');
const hasUnread = computed(() => (props.notifications_unread_count || 0) > 0);
const sidebarCollapsed = ref(false);
const { sidebarLogoUrl } = useStudentAreaSidebarLogo(toRef(props, 'student_branding'), sidebarCollapsed);

function submitSearch(e) {
    e.preventDefault();
    router.get('/area-membros', { q: searchLocal.value.trim() || undefined }, { preserveState: true, preserveScroll: true, replace: true });
}

function lessonProgressBar(item) {
    if (!item.lesson_total || !item.lesson_index) return 0;
    return Math.min(100, Math.round((item.lesson_index / item.lesson_total) * 100));
}
</script>

<template>
    <div
        class="flex min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100"
        :style="{ '--student-primary': student_branding?.primary || '#0ea5e9' }"
    >
        <StudentAreaDocumentHead title="Meus cursos" :student_branding="student_branding" />

        <aside
            class="hidden shrink-0 flex-col border-r border-zinc-200 bg-white py-6 transition-all dark:border-zinc-800 dark:bg-zinc-900 md:flex"
            :class="sidebarCollapsed ? 'w-20' : 'w-64'"
        >
            <div class="mb-8 px-4">
                <div class="mb-3 flex items-center justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-zinc-200 p-1.5 text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        :title="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'"
                        @click="sidebarCollapsed = !sidebarCollapsed"
                    >
                        <ChevronRight class="h-4 w-4 transition-transform" :class="sidebarCollapsed ? '' : 'rotate-180'" />
                    </button>
                </div>
                <div class="flex items-center justify-center">
                    <img
                        v-if="sidebarLogoUrl"
                        :src="sidebarLogoUrl"
                        alt="Logo"
                        class="h-auto w-auto max-h-14 object-contain"
                    />
                    <div v-else class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-white" :class="sidebarCollapsed ? 'hidden' : ''">Minha Plataforma</div>
                </div>
            </div>
            <nav class="flex flex-1 flex-col gap-1 px-2">
                <a
                    href="#sec-meus-cursos"
                    class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium"
                    :class="sidebarCollapsed ? 'justify-center' : 'gap-3'"
                    :style="{ backgroundColor: 'color-mix(in srgb, var(--student-primary) 18%, transparent)', color: 'var(--student-primary)' }"
                >
                    <LayoutGrid class="h-5 w-5 shrink-0" />
                    <span v-if="!sidebarCollapsed">Meus cursos</span>
                </a>
                <StudentAreaBackToPanelLink :collapsed="sidebarCollapsed" />
                <Link
                    v-if="profile_href"
                    :href="profile_href"
                    class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                    :class="sidebarCollapsed ? 'justify-center' : 'gap-3'"
                >
                    <UserRound class="h-5 w-5 shrink-0" />
                    <span v-if="!sidebarCollapsed">Meu perfil</span>
                </Link>
                <Link
                    v-if="hub_nav?.community_enabled"
                    href="/area-membros/comunidade"
                    class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                    :class="sidebarCollapsed ? 'justify-center' : 'gap-3'"
                >
                    <MessageCircle class="h-5 w-5 shrink-0" />
                    <span v-if="!sidebarCollapsed">Comunidade</span>
                </Link>
                <Link
                    v-if="hub_nav?.certificate_enabled"
                    href="/area-membros/certificados"
                    class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                    :class="sidebarCollapsed ? 'justify-center' : 'gap-3'"
                >
                    <Award class="h-5 w-5 shrink-0" />
                    <span v-if="!sidebarCollapsed">Certificados</span>
                </Link>
                <Link
                    v-if="hub_nav?.gamification_enabled"
                    href="/area-membros/conquistas"
                    class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                    :class="sidebarCollapsed ? 'justify-center' : 'gap-3'"
                >
                    <Trophy class="h-5 w-5 shrink-0" />
                    <span v-if="!sidebarCollapsed">Conquistas</span>
                </Link>
                <Link
                    v-if="suporte_href"
                    :href="suporte_href"
                    class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                    :class="sidebarCollapsed ? 'justify-center' : 'gap-3'"
                >
                    <LifeBuoy class="h-5 w-5 shrink-0" />
                    <span v-if="!sidebarCollapsed">Suporte</span>
                </Link>
                <span
                    v-else
                    class="flex items-center rounded-lg px-3 py-2.5 text-sm text-zinc-400 dark:text-zinc-500"
                    :class="sidebarCollapsed ? 'justify-center' : 'gap-3'"
                    title="Suporte não disponível"
                >
                    <LifeBuoy class="h-5 w-5 shrink-0" />
                    <span v-if="!sidebarCollapsed">Suporte</span>
                </span>
            </nav>
            <div class="mt-auto border-t border-zinc-200 px-2 pt-4 dark:border-zinc-800">
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="flex w-full items-center rounded-lg px-3 py-2.5 text-left text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                    :class="sidebarCollapsed ? 'justify-center' : 'gap-3'"
                >
                    <LogOut class="h-5 w-5 shrink-0" />
                    <span v-if="!sidebarCollapsed">Sair</span>
                </Link>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
                <div class="flex h-14 items-center gap-3 px-4 md:px-6">
                    <form class="mx-auto flex max-w-xl flex-1" @submit="submitSearch">
                        <div class="relative w-full">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                            <input
                                v-model="searchLocal"
                                type="search"
                                name="q"
                                placeholder="O que você está procurando?"
                                class="w-full rounded-full border border-zinc-200 bg-zinc-50 py-2 pl-10 pr-4 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500"
                            />
                        </div>
                    </form>
                    <div class="flex shrink-0 items-center gap-2">
                        <StudentAreaBackToPanelLink variant="header" />
                        <span class="relative inline-flex rounded-lg p-2 text-zinc-600 dark:text-zinc-400" title="Notificações" role="status">
                            <Bell class="h-5 w-5" />
                            <span v-if="hasUnread" class="absolute right-1 top-1 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-zinc-900" />
                        </span>
                        <ThemeToggler />
                        <div class="hidden items-center gap-2 sm:flex">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-semibold text-white" :style="{ backgroundColor: 'var(--student-primary)' }">
                        {{ auth_user.initials }}
                    </div>
                            <span class="max-w-[10rem] truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ auth_user.name }}</span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 space-y-10 px-4 py-8 md:px-8">
                <section v-if="continue_items.length">
                    <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Continue de onde você parou</h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <a
                            v-for="item in continue_items"
                            :key="`${item.product_id}-${item.lesson_id}`"
                            :href="item.lesson_href"
                            class="group flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                        >
                            <StudentCourseCover :src="item.thumbnail_url" :alt="item.product_name" img-class="opacity-80 group-hover:opacity-100">
                                <div v-if="!item.thumbnail_url" class="absolute inset-0 flex items-center justify-center">
                                    <BookOpen class="h-12 w-12 text-white/40" />
                                </div>
                                <div class="absolute left-2 right-2 top-2 flex justify-between text-xs font-medium text-white drop-shadow">
                                    <span v-if="item.lesson_index && item.lesson_total">Aula {{ item.lesson_index }} de {{ item.lesson_total }}</span>
                                    <span v-else class="text-white/80">—</span>
                                    <span class="text-white/70">pág. —/—</span>
                                </div>
                            </StudentCourseCover>
                            <div class="flex flex-1 flex-col p-4">
                                <p class="text-xs font-medium text-sky-600 dark:text-sky-400">
                                    <template v-if="item.module_title">{{ item.module_title }}</template>
                                    <template v-else>{{ item.product_name }}</template>
                                </p>
                                <p class="mt-1 line-clamp-2 font-semibold text-zinc-900 dark:text-white">{{ item.lesson_title }}</p>
                                <div class="mt-3 h-1 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div class="h-full rounded-full transition-all" :style="{ width: lessonProgressBar(item) + '%', backgroundColor: 'var(--student-primary)' }" />
                                </div>
                            </div>
                        </a>
                    </div>
                </section>

                <div
                    v-if="flashSuccess"
                    class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
                >
                    {{ flashSuccess }}
                </div>

                <section id="sec-meus-cursos">
                    <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Meus cursos</h2>
                    <div v-if="my_courses.length" class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        <div v-for="c in my_courses" :key="c.id" class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <StudentCourseCover :src="c.cover_url" :alt="c.name">
                                <span v-if="c.has_new_content" class="absolute left-2 top-2 rounded-full bg-emerald-500 px-2 py-0.5 text-xs font-semibold text-white">Novo conteúdo</span>
                                <span v-if="c.access_until_label" class="absolute right-2 top-2 rounded bg-black/50 px-2 py-0.5 text-xs text-white">{{ c.access_until_label }}</span>
                                <div v-if="!c.cover_url" class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-2xl font-bold tracking-tight text-white/90 drop-shadow">{{ c.name?.slice(0, 8) }}</span>
                                </div>
                            </StudentCourseCover>
                            <div class="flex flex-1 flex-col p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="min-w-0 flex-1 font-semibold text-zinc-900 dark:text-white">{{ c.name }}</h3>
                                    <div
                                        v-if="showCourseActionsMenu()"
                                        class="relative shrink-0"
                                        :data-course-menu="c.id"
                                    >
                                        <button
                                            type="button"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                            aria-label="Ações do curso"
                                            :aria-expanded="openCourseMenuId === c.id"
                                            @click.stop="toggleCourseMenu(c.id)"
                                        >
                                            <MoreVertical class="h-4 w-4" />
                                        </button>
                                        <div
                                            v-show="openCourseMenuId === c.id"
                                            class="absolute right-0 top-full z-30 mt-1 w-52 rounded-xl border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                                            role="menu"
                                        >
                                            <button
                                                v-if="c.refund?.can_request"
                                                type="button"
                                                role="menuitem"
                                                class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40"
                                                @click="openRefundFromMenu(c)"
                                            >
                                                <RotateCcw class="h-4 w-4 shrink-0" />
                                                Solicitar reembolso
                                            </button>
                                            <p
                                                v-else-if="c.refund?.pending"
                                                role="menuitem"
                                                class="px-3 py-2.5 text-sm text-amber-700 dark:text-amber-300"
                                            >
                                                Reembolso em análise
                                            </p>
                                            <p
                                                v-else-if="c.refund?.status === 'denied' && !c.refund?.can_request"
                                                role="menuitem"
                                                class="px-3 py-2.5 text-sm text-zinc-500 dark:text-zinc-400"
                                            >
                                                Reembolso negado
                                            </p>
                                            <p
                                                v-else
                                                role="menuitem"
                                                class="px-3 py-2.5 text-sm text-zinc-500 dark:text-zinc-400"
                                            >
                                                Reembolso indisponível
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="c.apostilas_label" class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ c.apostilas_label }}</p>
                                <div v-if="c.download_progress" class="mt-2">
                                    <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                                        <span>{{ c.download_progress.done }} / {{ c.download_progress.total }} baixados</span>
                                    </div>
                                    <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                        <div
                                            class="h-full rounded-full bg-sky-500"
                                            :style="{
                                                width:
                                                    c.download_progress.total > 0
                                                        ? Math.round((c.download_progress.done / c.download_progress.total) * 100) + '%'
                                                        : '0%',
                                            }"
                                        />
                                    </div>
                                </div>
                                <p
                                    v-if="refund_settings?.enabled && c.refund?.pending"
                                    class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                                >
                                    Reembolso em análise
                                </p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <a
                                        :href="c.continue_href"
                                        class="inline-flex flex-1 min-w-[7rem] items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium text-white transition"
                                        :style="{ backgroundColor: 'var(--student-primary)' }"
                                    >
                                        Continuar
                                        <ChevronRight class="ml-1 h-4 w-4" />
                                    </a>
                                    <a
                                        :href="c.member_area_href"
                                        class="inline-flex items-center justify-center rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                    >
                                        Acessar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">Nenhum curso encontrado{{ search_query ? ' para esta busca' : '' }}.</p>
                </section>

                <section v-if="recommended_courses.length" class="mb-10">
                    <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Recomendados para você</h2>
                    <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        <div v-for="c in recommended_courses" :key="'rec-' + c.id" class="flex flex-col overflow-hidden rounded-xl border border-sky-200 bg-white shadow-sm dark:border-sky-900/50 dark:bg-zinc-900">
                            <StudentCourseCover :src="c.cover_url" :alt="c.name" img-class="opacity-70">
                                <span class="absolute left-2 top-2 rounded-full bg-sky-600 px-2 py-0.5 text-xs font-semibold text-white">Recomendado</span>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <Lock class="h-14 w-14 text-white/50" />
                                </div>
                            </StudentCourseCover>
                            <div class="flex flex-1 flex-col p-4">
                                <h3 class="font-semibold text-zinc-900 dark:text-white">{{ c.name }}</h3>
                                <p class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">{{ c.price_label }}</p>
                                <a
                                    :href="c.checkout_url"
                                    class="mt-4 inline-flex items-center justify-center rounded-lg border-2 border-amber-600 px-4 py-2.5 text-sm font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-500 dark:text-amber-400 dark:hover:bg-amber-950/40"
                                >
                                    Adquirir acesso
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 v-if="other_courses.length || !recommended_courses.length" class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Outros cursos disponíveis</h2>
                    <div v-if="other_courses.length" class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        <div v-for="c in other_courses" :key="c.id" class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <StudentCourseCover :src="c.cover_url" :alt="c.name" img-class="opacity-70">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <Lock class="h-14 w-14 text-white/50" />
                                </div>
                            </StudentCourseCover>
                            <div class="flex flex-1 flex-col p-4">
                                <h3 class="font-semibold text-zinc-900 dark:text-white">{{ c.name }}</h3>
                                <p class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">{{ c.price_label }}</p>
                                <a
                                    :href="c.checkout_url"
                                    class="mt-4 inline-flex items-center justify-center rounded-lg border-2 border-amber-600 px-4 py-2.5 text-sm font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-500 dark:text-amber-400 dark:hover:bg-amber-950/40"
                                >
                                    Adquirir acesso
                                </a>
                            </div>
                        </div>
                    </div>
                    <p v-else-if="!recommended_courses.length" class="text-sm text-zinc-500 dark:text-zinc-400">Não há outros cursos disponíveis no momento.</p>
                </section>
            </main>

            <a
                v-if="support_whatsapp?.enabled && support_whatsapp?.url"
                :href="support_whatsapp.url"
                target="_blank"
                rel="noopener noreferrer"
                class="fixed bottom-6 right-6 z-30 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg transition hover:scale-105"
                title="WhatsApp"
                aria-label="Abrir WhatsApp"
            >
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path
                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"
                    />
                </svg>
            </a>

            <RefundRequestModal
                ref="refundModalRef"
                :open="refundModalOpen"
                :course="refundModalCourse"
                @close="closeRefundModal"
            />
        </div>
    </div>
</template>


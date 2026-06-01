<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import StudentAreaDocumentHead from '@/components/student/StudentAreaDocumentHead.vue';
import StudentHubSidebar from '@/components/student/StudentHubSidebar.vue';
import StudentHubContinueCard from '@/components/student/StudentHubContinueCard.vue';
import StudentHubOwnedCourseCard from '@/components/student/StudentHubOwnedCourseCard.vue';
import StudentHubUpsellCourseCard from '@/components/student/StudentHubUpsellCourseCard.vue';
import StudentAreaBackToPanelLink from '@/components/student/StudentAreaBackToPanelLink.vue';
import StudentHubAccountMenu from '@/components/student/StudentHubAccountMenu.vue';
import RefundRequestModal from '@/components/member-area/RefundRequestModal.vue';
import { Bell, Search } from 'lucide-vue-next';

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

const searchLocal = ref(props.search_query || '');
const searchDebounceMs = 400;
let searchDebounceTimer = null;
let searchReady = false;

watch(
    () => props.search_query,
    (q) => {
        const next = q || '';
        if (searchLocal.value !== next) {
            searchLocal.value = next;
        }
    },
);

const hasUnread = computed(() => (props.notifications_unread_count || 0) > 0);
const sidebarCollapsed = ref(false);

const searchOnly = [
    'search_query',
    'my_courses',
    'continue_items',
    'recommended_courses',
    'other_courses',
];

/** Área hub: sempre tema claro; restaura classe `dark` do html ao sair se o aluno tinha escuro antes. */
const hadDarkHtmlOnMount = ref(false);

onMounted(() => {
    hadDarkHtmlOnMount.value = document.documentElement.classList.contains('dark');
    document.documentElement.classList.remove('dark');
    document.addEventListener('click', handleCourseMenuClickOutside);
    searchReady = true;
});

onUnmounted(() => {
    document.removeEventListener('click', handleCourseMenuClickOutside);
    if (hadDarkHtmlOnMount.value) {
        document.documentElement.classList.add('dark');
    }
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
});

function runSearch() {
    const term = searchLocal.value.trim();
    const current = String(props.search_query || '').trim();
    if (term === current) {
        return;
    }
    router.get(
        '/area-membros',
        term ? { q: term } : {},
        {
            preserveScroll: true,
            replace: true,
            only: searchOnly,
        },
    );
}

function scheduleSearch() {
    if (!searchReady) {
        return;
    }
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    searchDebounceTimer = setTimeout(runSearch, searchDebounceMs);
}

function submitSearch(e) {
    e.preventDefault();
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    runSearch();
}

watch(searchLocal, () => {
    scheduleSearch();
});

</script>

<template>
    <div
        class="flex h-[100dvh] max-h-[100dvh] items-stretch overflow-hidden bg-zinc-50 text-zinc-900"
        :style="{ '--student-primary': student_branding?.primary || '#0ea5e9' }"
    >
        <StudentAreaDocumentHead title="Meus cursos" :student_branding="student_branding" />

        <StudentHubSidebar
            class="hidden min-h-0 md:flex"
            :student_branding="student_branding"
            :hub_nav="hub_nav"
            :profile_href="profile_href"
            :suporte_href="suporte_href"
            active="courses"
            courses-href="#sec-meus-cursos"
            variant="navy"
            :collapsed="sidebarCollapsed"
            @update:collapsed="sidebarCollapsed = $event"
        />

        <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
            <header class="z-20 shrink-0 border-b border-zinc-200 bg-white/95 backdrop-blur">
                <div class="flex h-14 items-center gap-3 px-4 md:px-6">
                    <form class="mx-auto flex max-w-xl flex-1" @submit="submitSearch">
                        <div class="relative w-full">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                            <input
                                v-model="searchLocal"
                                type="search"
                                name="q"
                                autocomplete="off"
                                placeholder="O que você está procurando?"
                                class="w-full rounded-full border border-zinc-200 bg-white py-2 pl-10 pr-4 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                            />
                        </div>
                    </form>
                    <div class="flex shrink-0 items-center gap-2">
                        <StudentAreaBackToPanelLink variant="header" />
                        <span class="relative inline-flex rounded-lg p-2 text-zinc-600" title="Notificações" role="status">
                            <Bell class="h-5 w-5" />
                            <span v-if="hasUnread" class="absolute right-1 top-1 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white" />
                        </span>
                        <StudentHubAccountMenu :user="auth_user" :profile-href="profile_href" />
                    </div>
                </div>
            </header>

            <main class="min-h-0 flex-1 space-y-10 overflow-y-auto overscroll-y-contain px-4 py-8 pb-24 md:px-8">
                <section v-if="continue_items.length">
                    <h2 class="mb-4 text-lg font-semibold text-zinc-900">Continue de onde você parou</h2>
                    <div class="flex flex-col gap-4">
                        <StudentHubContinueCard
                            v-for="item in continue_items"
                            :key="`${item.product_id}-${item.lesson_id}`"
                            :item="item"
                        />
                    </div>
                </section>

                <div
                    v-if="flashSuccess"
                    class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ flashSuccess }}
                </div>

                <section id="sec-meus-cursos">
                    <h2 class="mb-4 text-lg font-semibold text-zinc-900">Meus cursos</h2>
                    <div v-if="my_courses.length" class="grid gap-4 lg:grid-cols-2">
                        <StudentHubOwnedCourseCard
                            v-for="c in my_courses"
                            :key="c.id"
                            :course="c"
                            :show-actions-menu="showCourseActionsMenu()"
                            :refund-settings-enabled="!!refund_settings?.enabled"
                            :menu-open="openCourseMenuId === c.id"
                            @toggle-menu="toggleCourseMenu(c.id)"
                            @refund="openRefundFromMenu(c)"
                        />
                    </div>
                    <p v-else class="text-sm text-zinc-500">Nenhum curso encontrado{{ search_query ? ' para esta busca' : '' }}.</p>
                </section>

                <section v-if="recommended_courses.length" class="mb-10">
                    <h2 class="mb-4 text-lg font-semibold text-zinc-900">Recomendados para você</h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <StudentHubUpsellCourseCard
                            v-for="c in recommended_courses"
                            :key="'rec-' + c.id"
                            :course="c"
                            recommended
                        />
                    </div>
                </section>

                <section>
                    <h2 v-if="other_courses.length || !recommended_courses.length" class="mb-4 text-lg font-semibold text-zinc-900">Outros cursos disponíveis</h2>
                    <div v-if="other_courses.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <StudentHubUpsellCourseCard v-for="c in other_courses" :key="c.id" :course="c" />
                    </div>
                    <p v-else-if="!recommended_courses.length" class="text-sm text-zinc-500">Não há outros cursos disponíveis no momento.</p>
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


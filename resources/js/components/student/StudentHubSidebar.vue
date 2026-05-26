<script setup>
import { ref, toRef } from 'vue';
import { Link } from '@inertiajs/vue3';
import StudentAreaBackToPanelLink from '@/components/student/StudentAreaBackToPanelLink.vue';
import { useStudentAreaSidebarLogo } from '@/composables/useStudentAreaLogo';
import {
    Award,
    ChevronRight,
    LayoutGrid,
    LifeBuoy,
    LogOut,
    MessageCircle,
    Trophy,
    UserRound,
} from 'lucide-vue-next';

const props = defineProps({
    student_branding: {
        type: Object,
        default: () => ({ primary: '#0ea5e9' }),
    },
    hub_nav: {
        type: Object,
        default: () => ({}),
    },
    profile_href: { type: String, default: '/meu-perfil' },
    suporte_href: { type: String, default: null },
    /** courses | community | certificate | gamification | support | profile */
    active: { type: String, default: 'courses' },
    /** When true, "Meus cursos" links to /area-membros instead of anchor */
    courses_href: { type: String, default: '/area-membros' },
    collapsed: { type: Boolean, default: false },
    show_collapse: { type: Boolean, default: true },
    /** Navy sidebar (course app) vs light (hub dashboard) */
    variant: { type: String, default: 'light' },
    /** Logout no rodapé da sidebar; no layout da aula fica só no menu do avatar */
    showSidebarLogout: { type: Boolean, default: true },
});

const emit = defineEmits(['update:collapsed']);

const collapsedLocal = ref(props.collapsed);
const { sidebarLogoUrl } = useStudentAreaSidebarLogo(toRef(props, 'student_branding'), collapsedLocal);

function toggleCollapsed() {
    collapsedLocal.value = !collapsedLocal.value;
    emit('update:collapsed', collapsedLocal.value);
}

function navClass(id) {
    const isActive = props.active === id;
    if (props.variant === 'navy') {
        return [
            'relative flex items-center rounded-lg px-2.5 py-2 text-sm font-medium transition',
            collapsedLocal.value ? 'justify-center' : 'gap-2.5',
            isActive
                ? 'bg-white/11 text-white before:absolute before:left-0 before:top-[18%] before:bottom-[18%] before:w-0.5 before:rounded-r before:bg-emerald-400 before:content-[\'\']'
                : 'text-white/55 hover:bg-white/[0.07] hover:text-white/90',
        ];
    }
    return [
        'flex items-center rounded-lg px-3 py-2.5 text-sm font-medium transition',
        collapsedLocal.value ? 'justify-center' : 'gap-3',
        isActive
            ? ''
            : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800',
    ];
}

function coursesActiveStyle() {
    if (props.active !== 'courses' || props.variant === 'navy') {
        return props.active === 'courses' && props.variant === 'navy' ? {} : undefined;
    }
    return {
        backgroundColor: 'color-mix(in srgb, var(--student-primary) 18%, transparent)',
        color: 'var(--student-primary)',
    };
}
</script>

<template>
    <aside
        class="flex min-h-0 shrink-0 flex-col self-stretch overflow-hidden transition-all"
        :class="[
            collapsedLocal ? 'w-[72px]' : 'w-[var(--lesson-hub-w,252px)]',
            variant === 'navy'
                ? 'border-r border-white/10 bg-[#001e45]'
                : 'border-r border-zinc-200 bg-white py-6 dark:border-zinc-800 dark:bg-zinc-900',
        ]"
        :style="{ '--student-primary': student_branding?.primary || '#0ea5e9' }"
    >
        <div class="flex flex-col px-3 pt-4" :class="variant === 'light' ? 'mb-8' : 'mb-4 border-b border-white/10 pb-4'">
            <div v-if="show_collapse" class="mb-3 flex items-center justify-end">
                <button
                    type="button"
                    class="rounded-lg p-1.5 transition"
                    :class="
                        variant === 'navy'
                            ? 'text-white/60 hover:bg-white/10 hover:text-white'
                            : 'border border-zinc-200 text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800'
                    "
                    :title="collapsedLocal ? 'Expandir menu' : 'Recolher menu'"
                    @click="toggleCollapsed"
                >
                    <ChevronRight class="h-4 w-4 transition-transform" :class="collapsedLocal ? '' : 'rotate-180'" />
                </button>
            </div>
            <div class="flex items-center justify-center gap-2 px-1">
                <img
                    v-if="sidebarLogoUrl"
                    :src="sidebarLogoUrl"
                    alt="Logo"
                    class="h-auto max-h-12 w-auto object-contain"
                />
                <div
                    v-else
                    class="text-base font-bold tracking-tight"
                    :class="[
                        collapsedLocal ? 'hidden' : '',
                        variant === 'navy' ? 'text-white' : 'text-zinc-900 dark:text-white',
                    ]"
                >
                    Minha Plataforma
                </div>
            </div>
        </div>

        <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto px-2 pb-2">
            <p
                v-if="!collapsedLocal && variant === 'navy'"
                class="mb-1 px-2 text-[10px] font-bold uppercase tracking-widest text-white/30"
            >
                Menu
            </p>

            <Link
                v-if="courses_href.startsWith('/')"
                :href="courses_href"
                :class="navClass('courses')"
                :style="coursesActiveStyle()"
            >
                <LayoutGrid class="h-5 w-5 shrink-0" />
                <span v-if="!collapsedLocal">Meus cursos</span>
            </Link>
            <a
                v-else
                :href="courses_href"
                :class="navClass('courses')"
                :style="coursesActiveStyle()"
            >
                <LayoutGrid class="h-5 w-5 shrink-0" />
                <span v-if="!collapsedLocal">Meus cursos</span>
            </a>

            <StudentAreaBackToPanelLink :collapsed="collapsedLocal" :dark-sidebar="variant === 'navy'" />

            <p
                v-if="!collapsedLocal && variant === 'navy'"
                class="mb-1 mt-4 px-2 text-[10px] font-bold uppercase tracking-widest text-white/30"
            >
                Conta
            </p>

            <Link v-if="profile_href" :href="profile_href" :class="navClass('profile')">
                <UserRound class="h-5 w-5 shrink-0" />
                <span v-if="!collapsedLocal">Meu perfil</span>
            </Link>
            <Link
                v-if="hub_nav?.community_enabled"
                href="/area-membros/comunidade"
                :class="navClass('community')"
            >
                <MessageCircle class="h-5 w-5 shrink-0" />
                <span v-if="!collapsedLocal">Comunidade</span>
            </Link>
            <Link
                v-if="hub_nav?.certificate_enabled"
                href="/area-membros/certificados"
                :class="navClass('certificate')"
            >
                <Award class="h-5 w-5 shrink-0" />
                <span v-if="!collapsedLocal">Certificados</span>
            </Link>
            <Link
                v-if="hub_nav?.gamification_enabled"
                href="/area-membros/conquistas"
                :class="navClass('gamification')"
            >
                <Trophy class="h-5 w-5 shrink-0" />
                <span v-if="!collapsedLocal">Conquistas</span>
            </Link>
            <Link v-if="suporte_href" :href="suporte_href" :class="navClass('support')">
                <LifeBuoy class="h-5 w-5 shrink-0" />
                <span v-if="!collapsedLocal">Suporte</span>
            </Link>
            <span
                v-else
                class="flex items-center rounded-lg px-3 py-2.5 text-sm opacity-60"
                :class="[
                    collapsedLocal ? 'justify-center' : 'gap-3',
                    variant === 'navy' ? 'text-white/40' : 'text-zinc-400',
                ]"
                title="Suporte não disponível"
            >
                <LifeBuoy class="h-5 w-5 shrink-0" />
                <span v-if="!collapsedLocal">Suporte</span>
            </span>
        </nav>

        <div
            v-if="showSidebarLogout"
            class="mt-auto border-t px-2 py-3"
            :class="variant === 'navy' ? 'border-white/10' : 'border-zinc-200 dark:border-zinc-800'"
        >
            <Link
                href="/logout"
                method="post"
                as="button"
                class="flex w-full items-center rounded-lg px-2.5 py-2.5 text-left text-sm font-medium transition"
                :class="[
                    collapsedLocal ? 'justify-center' : 'gap-2.5',
                    variant === 'navy'
                        ? 'text-white/35 hover:bg-white/[0.07] hover:text-white/60'
                        : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800',
                ]"
            >
                <LogOut class="h-5 w-5 shrink-0" />
                <span v-if="!collapsedLocal">Sair</span>
            </Link>
        </div>
    </aside>
</template>

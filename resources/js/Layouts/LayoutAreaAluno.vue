<script setup>
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { GraduationCap, Palette, LogIn, MessageCircle, Globe, Award, Trophy } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps({
    activeSection: { type: String, default: '' },
});

const page = usePage();
const active = computed(() => {
    const fromProps = page.props.active_section;
    if (fromProps) return fromProps;
    const url = page.url.split('?')[0];
    if (url.includes('/login')) return 'login';
    if (url.includes('/suporte')) return 'suporte';
    if (url.includes('/comunidade')) return 'comunidade';
    if (url.includes('/certificado')) return 'certificado';
    if (url.includes('/gamificacao')) return 'gamificacao';
    return 'personalizacao';
});

const sections = [
    { id: 'personalizacao', label: 'Personalização', href: '/area-aluno/personalizacao', icon: Palette },
    { id: 'login', label: 'Tela de login', href: '/area-aluno/login', icon: LogIn },
    { id: 'suporte', label: 'Suporte', href: '/area-aluno/suporte', icon: MessageCircle },
    { id: 'comunidade', label: 'Comunidade', href: '/area-aluno/comunidade', icon: Globe },
    { id: 'certificado', label: 'Certificado', href: '/area-aluno/certificado', icon: Award },
    { id: 'gamificacao', label: 'Gamificação', href: '/area-aluno/gamificacao', icon: Trophy },
];
</script>

<template>
    <LayoutInfoprodutor>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex items-center gap-3">
                <GraduationCap class="h-8 w-8 text-[var(--color-primary)]" />
                <div>
                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Área do aluno</h1>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Hub, login, suporte, comunidade e certificado em /area-membros</p>
                </div>
            </div>
            <nav class="flex flex-wrap gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-700">
                <Link
                    v-for="s in sections"
                    :key="s.id"
                    :href="s.href"
                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="active === s.id
                        ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                        : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800'"
                >
                    <component :is="s.icon" class="h-4 w-4" />
                    {{ s.label }}
                </Link>
            </nav>
            <slot />
        </div>
    </LayoutInfoprodutor>
</template>

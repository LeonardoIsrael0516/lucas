<script setup>
import { Link } from '@inertiajs/vue3';
import StudentAreaDocumentHead from '@/components/student/StudentAreaDocumentHead.vue';
import { Award, LayoutGrid } from 'lucide-vue-next';

defineProps({
    student_branding: { type: Object, default: () => ({}) },
    hub_nav: { type: Object, default: () => ({}) },
    certificates: { type: Array, default: () => [] },
    profile_href: { type: String, default: '/meu-perfil' },
    suporte_href: { type: String, default: null },
});
</script>

<template>
    <div class="min-h-screen bg-zinc-50 p-6 dark:bg-zinc-950" :style="{ '--student-primary': student_branding?.primary || '#0ea5e9' }">
        <StudentAreaDocumentHead title="Certificados" :student_branding="student_branding" />
        <Link href="/area-membros" class="mb-6 inline-flex items-center gap-2 text-sm text-zinc-600 hover:text-[var(--student-primary)]">
            <LayoutGrid class="h-4 w-4" /> Voltar aos cursos
        </Link>
        <h1 class="mb-6 text-2xl font-bold">Meus certificados</h1>
        <ul class="space-y-4">
            <li v-for="c in certificates" :key="c.product_id" class="flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center gap-3">
                    <Award class="h-8 w-8 text-[var(--student-primary)]" />
                    <div>
                        <p class="font-medium">{{ c.product_name }}</p>
                        <p class="text-sm text-zinc-500">{{ c.progress_percent }}% concluído (mín. {{ c.required_percent }}%)</p>
                    </div>
                </div>
                <Link
                    v-if="c.view_url"
                    :href="c.view_url"
                    class="rounded-lg bg-[var(--student-primary)] px-4 py-2 text-sm font-medium text-white"
                >
                    Ver certificado
                </Link>
                <span v-else class="text-sm text-zinc-500">Em progresso</span>
            </li>
        </ul>
        <p v-if="!certificates.length" class="text-zinc-500">Nenhum curso com certificado disponível.</p>
    </div>
</template>

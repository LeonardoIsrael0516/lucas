<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { LayoutGrid, Trophy } from 'lucide-vue-next';

defineProps({
    student_branding: { type: Object, default: () => ({}) },
    achievements: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="min-h-screen bg-zinc-50 p-6 dark:bg-zinc-950" :style="{ '--student-primary': student_branding?.primary || '#0ea5e9' }">
        <Head title="Conquistas" />
        <Link href="/area-membros" class="mb-6 inline-flex items-center gap-2 text-sm text-zinc-600 hover:text-[var(--student-primary)]">
            <LayoutGrid class="h-4 w-4" /> Voltar aos cursos
        </Link>
        <h1 class="mb-6 flex items-center gap-2 text-2xl font-bold"><Trophy class="h-7 w-7 text-[var(--student-primary)]" /> Conquistas</h1>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="a in achievements"
                :key="a.id"
                class="rounded-xl border p-4 dark:border-zinc-700"
                :class="a.unlocked ? 'border-emerald-300 bg-emerald-50 dark:bg-emerald-950/30' : 'border-zinc-200 bg-white opacity-70 dark:bg-zinc-900'"
            >
                <img v-if="a.image_url" :src="a.image_url" alt="" class="mb-2 h-16 w-16 object-contain" />
                <p class="font-semibold">{{ a.title }}</p>
                <p class="text-xs text-zinc-500">{{ a.requirement_text }}</p>
                <p v-if="a.product_name" class="mt-1 text-xs text-zinc-400">{{ a.product_name }}</p>
            </div>
        </div>
        <p v-if="!achievements.length" class="text-zinc-500">Nenhuma conquista configurada.</p>
    </div>
</template>

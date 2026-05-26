<script setup>
import { Link } from '@inertiajs/vue3';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { Lock } from 'lucide-vue-next';

defineOptions({ layout: MemberAreaAppLayout });

defineProps({
    product: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    modules: { type: Array, default: () => [] },
    slug: { type: String, required: true },
});
</script>

<template>
    <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-5xl">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link href="/area-membros" class="text-xs font-semibold text-[var(--lesson-text-3)] hover:text-[var(--student-primary)]">
                        ← Meus cursos
                    </Link>
                    <h1 class="mt-1 text-2xl font-bold text-[var(--lesson-text)]">Módulos</h1>
                    <p class="text-sm text-[var(--lesson-text-2)]">{{ product.name }}</p>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="mod in modules"
                    :key="mod.id"
                    class="overflow-hidden rounded-xl border border-[var(--lesson-border)] bg-[var(--lesson-surface)] shadow-sm"
                >
                    <Link v-if="!mod.is_locked" :href="`/m/${slug}/modulo/${mod.id}`" class="block">
                        <div class="flex aspect-video w-full items-center justify-center bg-[var(--lesson-bg2)]">
                            <img v-if="mod.thumbnail" :src="mod.thumbnail" alt="" class="h-full w-full object-cover" />
                            <svg v-else class="h-12 w-12 text-[var(--lesson-text-3)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </Link>
                    <div v-else class="relative opacity-70">
                        <div class="flex aspect-video w-full items-center justify-center bg-[var(--lesson-bg2)]">
                            <Lock class="h-8 w-8 text-amber-600" />
                        </div>
                    </div>
                    <div class="p-4">
                        <p class="font-semibold text-[var(--lesson-text)]">{{ mod.title }}</p>
                        <p v-if="mod.is_locked && mod.lock_message" class="mt-1 text-xs text-[var(--lesson-text-3)]">{{ mod.lock_message }}</p>
                        <ul class="mt-3 space-y-1">
                            <li v-for="lesson in mod.lessons" :key="lesson.id" class="flex items-center gap-2 text-sm">
                                <span v-if="lesson.is_completed" class="text-emerald-600">✓</span>
                                <Link
                                    v-if="!mod.is_locked && !lesson.is_locked"
                                    :href="`/m/${slug}/modulo/${mod.id}?aula=${lesson.id}`"
                                    class="truncate text-[var(--lesson-text-2)] hover:text-[var(--student-primary)]"
                                >
                                    {{ lesson.title }}
                                </Link>
                                <span v-else class="truncate text-[var(--lesson-text-3)]">{{ lesson.title }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

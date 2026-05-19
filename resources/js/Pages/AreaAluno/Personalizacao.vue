<script setup>
import { useForm } from '@inertiajs/vue3';
import LayoutAreaAluno from '@/Layouts/LayoutAreaAluno.vue';
import Button from '@/components/ui/Button.vue';

const props = defineProps({
    student_area_primary: { type: String, default: '#0ea5e9' },
});

const form = useForm({
    student_area_primary: props.student_area_primary,
});

function submit() {
    form.post('/area-aluno/personalizacao', { preserveScroll: true });
}
</script>

<template>
    <LayoutAreaAluno>
        <form class="space-y-6" @submit.prevent="submit">
            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-5 dark:border-zinc-700 dark:bg-zinc-800">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Personalização da área do aluno</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        Define a cor principal da tela <code class="rounded bg-zinc-100 px-1 py-0.5 font-mono text-xs dark:bg-zinc-900/40">/area-membros</code> e das áreas de curso.
                    </p>
                </div>
                <div class="space-y-6 p-6">
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                        As logos da sidebar do aluno seguem o <strong>White Label</strong> do painel (Configurações → White Label).
                    </p>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Cor principal</label>
                        <div class="flex flex-wrap items-center gap-3">
                            <input v-model="form.student_area_primary" type="color" class="h-10 w-14 rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-800" />
                            <input
                                v-model="form.student_area_primary"
                                type="text"
                                class="h-10 min-w-[220px] rounded-lg border border-zinc-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-800"
                                placeholder="#0ea5e9"
                            />
                        </div>
                        <p v-if="form.errors.student_area_primary" class="mt-1 text-sm text-red-600">{{ form.errors.student_area_primary }}</p>
                    </div>
                </div>
            </section>

            <Button type="submit" :disabled="form.processing">Salvar</Button>
        </form>
    </LayoutAreaAluno>
</template>

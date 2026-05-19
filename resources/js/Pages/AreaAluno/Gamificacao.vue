<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LayoutAreaAluno from '@/Layouts/LayoutAreaAluno.vue';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';
import { Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    gamification: { type: Object, default: () => ({ enabled: false, achievements: [] }) },
});

const form = useForm({
    gamification: {
        enabled: props.gamification.enabled ?? false,
        achievements: [...(props.gamification.achievements ?? [])],
    },
});

function addAchievement() {
    form.gamification.achievements.push({
        id: crypto.randomUUID(),
        title: 'Nova conquista',
        trigger: 'first_lesson',
        trigger_config: {},
        description: '',
        image: '',
    });
}

function removeAchievement(index) {
    form.gamification.achievements.splice(index, 1);
}

function submit() {
    form.put('/area-aluno/gamificacao', { preserveScroll: true });
}
</script>

<template>
    <LayoutAreaAluno>
        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800/50">
                <Toggle v-model="form.gamification.enabled" label="Habilitar gamificação em /area-membros/conquistas" />
            </section>
            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold">Conquistas</h2>
                    <Button type="button" size="sm" variant="secondary" @click="addAchievement"><Plus class="h-4 w-4" /> Adicionar</Button>
                </div>
                <div v-for="(ach, idx) in form.gamification.achievements" :key="ach.id" class="mb-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <input v-model="ach.title" type="text" placeholder="Título" class="mb-2 w-full rounded border px-2 py-1 text-sm dark:bg-zinc-800" />
                    <select v-model="ach.trigger" class="mb-2 w-full rounded border px-2 py-1 text-sm dark:bg-zinc-800">
                        <option value="first_lesson">Primeira aula</option>
                        <option value="lessons_count">Número de aulas</option>
                        <option value="completion_percent">% do curso</option>
                        <option value="course_complete">Curso completo</option>
                        <option value="first_comment">Primeiro comentário</option>
                        <option value="certificate_earned">Certificado</option>
                    </select>
                    <input v-if="ach.trigger === 'lessons_count'" v-model.number="ach.trigger_config.count" type="number" min="1" placeholder="Qtd aulas" class="mb-2 w-24 rounded border px-2 py-1 text-sm" />
                    <input v-if="ach.trigger === 'completion_percent'" v-model.number="ach.trigger_config.percent" type="number" min="1" max="100" placeholder="%" class="mb-2 w-24 rounded border px-2 py-1 text-sm" />
                    <textarea v-model="ach.description" rows="2" placeholder="Descrição" class="mb-2 w-full rounded border px-2 py-1 text-sm dark:bg-zinc-800" />
                    <button type="button" class="text-sm text-red-600" @click="removeAchievement(idx)"><Trash2 class="inline h-4 w-4" /> Remover</button>
                </div>
            </section>
            <Button type="submit" :disabled="form.processing">Salvar</Button>
        </form>
    </LayoutAreaAluno>
</template>

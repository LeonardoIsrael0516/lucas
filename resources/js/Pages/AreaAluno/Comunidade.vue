<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import LayoutAreaAluno from '@/Layouts/LayoutAreaAluno.vue';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';
import { Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    community_enabled: { type: Boolean, default: false },
    community_users_can_delete_own_posts: { type: Boolean, default: true },
    community_pages: { type: Array, default: () => [] },
});

const pages = ref([...props.community_pages]);
const settingsForm = useForm({
    community_enabled: props.community_enabled,
    community_users_can_delete_own_posts: props.community_users_can_delete_own_posts,
});

function saveSettings() {
    settingsForm.put('/area-aluno/comunidade/settings', { preserveScroll: true });
}

const newTitle = ref('');
async function addPage() {
    const title = newTitle.value.trim();
    if (!title) return;
    const { data } = await axios.post('/area-aluno/comunidade/pages', { title, is_public_posting: true }, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, Accept: 'application/json' },
    });
    if (data.community_pages) pages.value = data.community_pages;
    newTitle.value = '';
}

async function removePage(id) {
    if (!confirm('Remover página e posts?')) return;
    const { data } = await axios.delete(`/area-aluno/comunidade/pages/${id}`, {
        headers: { Accept: 'application/json' },
    });
    pages.value = data.community_pages ?? pages.value.filter((p) => p.id !== id);
}
</script>

<template>
    <LayoutAreaAluno>
        <form class="mb-8 space-y-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800/50" @submit.prevent="saveSettings">
            <h2 class="text-base font-semibold">Comunidade global</h2>
            <Toggle v-model="settingsForm.community_enabled" label="Habilitar comunidade em /area-membros" />
            <Toggle v-model="settingsForm.community_users_can_delete_own_posts" label="Alunos podem excluir próprios posts" />
            <Button type="submit" size="sm" :disabled="settingsForm.processing">Salvar opções</Button>
        </form>

        <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="mb-4 text-base font-semibold">Páginas</h2>
            <div class="mb-4 flex gap-2">
                <input v-model="newTitle" type="text" placeholder="Nova página" class="flex-1 rounded-lg border px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800" />
                <Button type="button" size="sm" @click="addPage"><Plus class="h-4 w-4" /></Button>
            </div>
            <ul class="space-y-2">
                <li v-for="p in pages" :key="p.id" class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-900/40">
                    <span>{{ p.title }} <span class="text-zinc-500">/{{ p.slug }}</span></span>
                    <button type="button" class="text-red-600" @click="removePage(p.id)"><Trash2 class="h-4 w-4" /></button>
                </li>
            </ul>
            <p v-if="!pages.length" class="text-sm text-zinc-500">Nenhuma página criada.</p>
        </section>
    </LayoutAreaAluno>
</template>

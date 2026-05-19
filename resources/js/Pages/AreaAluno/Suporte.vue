<script setup>
import { useForm } from '@inertiajs/vue3';
import LayoutAreaAluno from '@/Layouts/LayoutAreaAluno.vue';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';

const props = defineProps({
    student_support_enabled: { type: Boolean, default: false },
    student_support_whatsapp_enabled: { type: Boolean, default: false },
    student_support_whatsapp_url: { type: String, default: '' },
});

const form = useForm({
    student_support_enabled: props.student_support_enabled,
    student_support_whatsapp_enabled: props.student_support_whatsapp_enabled,
    student_support_whatsapp_url: props.student_support_whatsapp_url,
});

function submit() {
    form.post('/area-aluno/suporte', { preserveScroll: true });
}
</script>

<template>
    <LayoutAreaAluno>
        <form class="space-y-6" @submit.prevent="submit">
            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-5 dark:border-zinc-700 dark:bg-zinc-800">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Suporte aos alunos</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        Central de tickets e WhatsApp na área do aluno (<code class="rounded bg-zinc-100 px-1 py-0.5 font-mono text-xs dark:bg-zinc-900/40">/suporte</code>).
                        Para responder pedidos, use o menu <strong>Suporte alunos</strong> no painel (equipe com permissão).
                    </p>
                </div>
                <div class="space-y-6 p-6">
                    <div>
                        <Toggle v-model="form.student_support_enabled" label="Ativar central de tickets" />
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Exibe o item Suporte na área do aluno e permite abrir tickets.</p>
                    </div>
                    <div>
                        <Toggle v-model="form.student_support_whatsapp_enabled" label="Mostrar botão WhatsApp" />
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Botão flutuante na área do aluno (com suporte habilitado).</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Link do WhatsApp</label>
                        <input
                            v-model="form.student_support_whatsapp_url"
                            type="url"
                            placeholder="https://wa.me/5511999999999"
                            class="w-full max-w-xl rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800"
                        />
                        <p v-if="form.errors.student_support_whatsapp_url" class="mt-1 text-sm text-red-600">{{ form.errors.student_support_whatsapp_url }}</p>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Use o link gerado pelo WhatsApp (wa.me) ou api.whatsapp.com.</p>
                    </div>
                </div>
            </section>

            <Button type="submit" :disabled="form.processing">Salvar</Button>
        </form>
    </LayoutAreaAluno>
</template>

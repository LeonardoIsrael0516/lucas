<script setup>
import { useForm } from '@inertiajs/vue3';
import LayoutAreaAluno from '@/Layouts/LayoutAreaAluno.vue';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';

const props = defineProps({
    certificate: { type: Object, default: () => ({ enabled: false }) },
});

const form = useForm({
    certificate: {
        enabled: props.certificate.enabled ?? false,
        title: props.certificate.title ?? '',
        signature_text: props.certificate.signature_text ?? '',
        platform_name: props.certificate.platform_name ?? '',
        primary_color: props.certificate.primary_color ?? '#0ea5e9',
        completion_percent: props.certificate.completion_percent ?? 100,
        print_format: props.certificate.print_format ?? 'A4',
    },
});

function submit() {
    form.put('/area-aluno/certificado', { preserveScroll: true });
}
</script>

<template>
    <LayoutAreaAluno>
        <form class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800/50" @submit.prevent="submit">
            <h2 class="text-base font-semibold">Certificado global</h2>
            <p class="text-xs text-zinc-500">Emitido por curso quando o aluno atinge o percentual configurado no produto (padrão 100%).</p>
            <Toggle v-model="form.certificate.enabled" label="Habilitar certificados" />
            <input v-model="form.certificate.title" type="text" placeholder="Título padrão" class="w-full max-w-md rounded-lg border px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800" />
            <input v-model="form.certificate.signature_text" type="text" placeholder="Texto da assinatura" class="w-full max-w-md rounded-lg border px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800" />
            <input v-model="form.certificate.platform_name" type="text" placeholder="Nome da plataforma" class="w-full max-w-md rounded-lg border px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800" />
            <Button type="submit" :disabled="form.processing">Salvar</Button>
        </form>
    </LayoutAreaAluno>
</template>

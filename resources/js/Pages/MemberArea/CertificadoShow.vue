<script setup>
import { Link } from '@inertiajs/vue3';
import StudentAreaDocumentHead from '@/components/student/StudentAreaDocumentHead.vue';
import { LayoutGrid } from 'lucide-vue-next';

defineProps({
    student_branding: { type: Object, default: () => ({}) },
    product: { type: Object, required: true },
    certificate: { type: Object, required: true },
});
</script>

<template>
    <div class="min-h-screen bg-zinc-50 p-6 dark:bg-zinc-950" :style="{ '--student-primary': student_branding?.primary || '#0ea5e9' }">
        <StudentAreaDocumentHead :title="certificate.title" :student_branding="student_branding" />
        <Link href="/area-membros/certificados" class="mb-4 inline-flex items-center gap-2 text-sm text-zinc-600">
            <LayoutGrid class="h-4 w-4" /> Voltar
        </Link>
        <div class="mx-auto max-w-2xl rounded-xl border border-zinc-200 bg-white p-8 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h1 class="text-2xl font-bold">{{ certificate.title }}</h1>
            <p class="mt-4 text-zinc-600">{{ certificate.student_name }}</p>
            <p class="mt-2 text-sm text-zinc-500">Concluiu {{ certificate.completion_percent }}% · {{ product.name }}</p>
            <p class="mt-6 text-sm text-zinc-500">Emitido em {{ certificate.issued_at }}</p>
            <p v-if="certificate.config?.signature_text" class="mt-8 font-serif text-lg">{{ certificate.config.signature_text }}</p>
        </div>
    </div>
</template>

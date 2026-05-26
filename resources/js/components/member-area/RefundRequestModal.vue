<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
    course: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const form = useForm({
    product_id: '',
    reason: '',
});

watch(
    () => [props.open, props.course?.id],
    ([isOpen, productId]) => {
        if (isOpen && productId) {
            form.clearErrors();
            form.product_id = productId;
            form.reason = '';
        }
    }
);

function openFor(course) {
    form.clearErrors();
    form.product_id = course?.id ?? '';
    form.reason = '';
}

function submit() {
    form.post('/area-membros/reembolsos', {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            form.reset();
        },
    });
}

function close() {
    emit('close');
}

defineExpose({ openFor });
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/50" @click="close" />
            <div class="relative z-10 w-full max-w-md rounded-xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Solicitar reembolso</h2>
                        <p v-if="course?.name" class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ course.name }}</p>
                    </div>
                    <button type="button" class="rounded-lg p-1 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="Fechar" @click="close">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Motivo da solicitação</label>
                        <textarea
                            v-model="form.reason"
                            rows="5"
                            required
                            minlength="10"
                            maxlength="2000"
                            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                            placeholder="Descreva o motivo do reembolso (mínimo 10 caracteres)..."
                        />
                        <p v-if="form.errors.reason" class="mt-1 text-sm text-red-600">{{ form.errors.reason }}</p>
                        <p v-if="form.errors.product_id" class="mt-1 text-sm text-red-600">{{ form.errors.product_id }}</p>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Sua solicitação será analisada pela equipe. O prazo de resposta pode variar conforme a política da plataforma.
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="close">Cancelar</Button>
                        <Button type="submit" variant="primary" :disabled="form.processing">Enviar solicitação</Button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

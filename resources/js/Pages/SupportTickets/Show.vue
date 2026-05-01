<script setup>
import { computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: LayoutInfoprodutor });

const page = usePage();
const props = defineProps({
    ticket: { type: Object, required: true },
});

const flashSuccess = computed(() => page.props.flash?.success ?? page.props.flash?.message ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const isOpen = computed(() => props.ticket?.status === 'open');

const replyForm = useForm({
    message: '',
});

function sendReply() {
    replyForm.post(`/suporte-alunos/${props.ticket.id}/reply`, {
        preserveScroll: true,
        onSuccess: () => replyForm.reset('message'),
    });
}

function closeTicket() {
    if (!window.confirm('Encerrar este ticket? O aluno não poderá enviar novas mensagens.')) return;
    router.post(`/suporte-alunos/${props.ticket.id}/close`, {}, { preserveScroll: true });
}

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return '—';
    }
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center gap-3">
            <Link href="/suporte-alunos" class="text-sm font-medium text-[var(--color-primary)] hover:underline">← Voltar à lista</Link>
        </div>

        <div v-if="flashSuccess" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ flashSuccess }}
        </div>
        <div v-if="flashError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            {{ flashError }}
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ ticket.subject }}</h1>
            <div class="mt-2 flex flex-wrap gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                <span
                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="
                        ticket.status === 'open'
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                            : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-400'
                    "
                >
                    {{ ticket.status === 'open' ? 'Aberto' : 'Encerrado' }}
                </span>
                <span>{{ ticket.student?.name }} · {{ ticket.student?.email }}</span>
                <span v-if="ticket.closed_at">Encerrado em {{ fmtDate(ticket.closed_at) }}</span>
                <span v-if="ticket.closed_by_name">por {{ ticket.closed_by_name }}</span>
            </div>
        </div>

        <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Conversa</h2>
            <div class="space-y-4">
                <div v-for="m in ticket.messages" :key="m.id" class="border-b border-zinc-100 pb-4 last:border-0 dark:border-zinc-800">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ m.author_name }}</span>
                        <span class="text-xs text-zinc-500">{{ fmtDate(m.created_at) }}</span>
                        <span
                            v-if="m.is_staff"
                            class="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-sky-800 dark:bg-sky-900/40 dark:text-sky-200"
                            >Equipe</span
                        >
                        <span
                            v-else
                            class="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-zinc-600 dark:bg-zinc-400"
                            >Aluno</span
                        >
                    </div>
                    <p class="mt-2 whitespace-pre-wrap text-sm text-zinc-700 dark:text-zinc-300">{{ m.body }}</p>
                </div>
            </div>
        </div>

        <div v-if="isOpen" class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white">Responder</h2>
            <form class="space-y-3" @submit.prevent="sendReply">
                <textarea
                    v-model="replyForm.message"
                    rows="4"
                    required
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                    placeholder="Digite a resposta..."
                />
                <p v-if="replyForm.errors.message" class="text-sm text-red-600">{{ replyForm.errors.message }}</p>
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" variant="primary" :disabled="replyForm.processing">Enviar resposta</Button>
                    <Button type="button" variant="outline" class="border-red-200 text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300" @click="closeTicket">
                        Encerrar ticket
                    </Button>
                </div>
            </form>
        </div>
        <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">Este ticket está encerrado.</p>
    </div>
</template>

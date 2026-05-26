<script setup>
import { computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: LayoutInfoprodutor });

const page = usePage();
const props = defineProps({
    refund_request: { type: Object, required: true },
});

const flashSuccess = computed(() => page.props.flash?.success ?? page.props.flash?.message ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const isPending = computed(() => props.refund_request?.status === 'pending');

const denyForm = useForm({
    admin_note: '',
});

function approve() {
    if (!window.confirm('Aprovar este reembolso? O acesso ao curso será revogado e o pedido marcado como reembolsado. Faça o estorno no gateway manualmente.')) {
        return;
    }
    router.post(`/reembolsos/${props.refund_request.id}/aprovar`);
}

function deny() {
    if (!window.confirm('Negar esta solicitação de reembolso?')) return;
    denyForm.post(`/reembolsos/${props.refund_request.id}/negar`, { preserveScroll: true });
}

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return '—';
    }
}

function fmtMoney(amount) {
    if (amount == null) return '—';
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(amount);
}

function statusLabel(status) {
    if (status === 'pending') return 'Pendente';
    if (status === 'approved') return 'Aprovado';
    if (status === 'denied') return 'Negado';
    return status;
}
</script>

<template>
    <div class="space-y-6">
        <Link href="/reembolsos" class="text-sm font-medium text-[var(--color-primary)] hover:underline">← Voltar à lista</Link>

        <div v-if="flashSuccess" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ flashSuccess }}
        </div>
        <div v-if="flashError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            {{ flashError }}
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Solicitação #{{ refund_request.id }}</h1>
            <div class="mt-2 flex flex-wrap gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                    {{ statusLabel(refund_request.status) }}
                </span>
                <span>{{ refund_request.student?.name }} · {{ refund_request.student?.email }}</span>
                <span v-if="refund_request.reviewed_at">Analisado em {{ fmtDate(refund_request.reviewed_at) }}</span>
                <span v-if="refund_request.reviewed_by_name">por {{ refund_request.reviewed_by_name }}</span>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Curso</h2>
                <p class="mt-2 font-medium text-zinc-900 dark:text-white">{{ refund_request.product?.name }}</p>
            </section>
            <section v-if="refund_request.order" class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Pedido</h2>
                <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                    #{{ refund_request.order.id }} · {{ fmtMoney(refund_request.order.amount) }} · {{ fmtDate(refund_request.order.created_at) }}
                </p>
                <Link
                    v-if="refund_request.order.vendas_url"
                    :href="refund_request.order.vendas_url"
                    class="mt-2 inline-block text-sm font-medium text-[var(--color-primary)] hover:underline"
                >
                    Ver em Vendas
                </Link>
            </section>
        </div>

        <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Motivo do aluno</h2>
            <p class="mt-3 whitespace-pre-wrap text-sm text-zinc-700 dark:text-zinc-300">{{ refund_request.reason }}</p>
            <p class="mt-2 text-xs text-zinc-500">Enviado em {{ fmtDate(refund_request.created_at) }}</p>
        </section>

        <section v-if="refund_request.admin_note" class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Observação da equipe</h2>
            <p class="mt-3 whitespace-pre-wrap text-sm text-zinc-700 dark:text-zinc-300">{{ refund_request.admin_note }}</p>
        </section>

        <div v-if="isPending" class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-white">Análise</h2>
            <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                Ao aprovar, o acesso ao curso é revogado e o pedido fica como reembolsado. O estorno financeiro deve ser feito manualmente no gateway de pagamento.
            </p>
            <div class="mb-4">
                <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Observação (opcional, ao negar)</label>
                <textarea
                    v-model="denyForm.admin_note"
                    rows="3"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                    placeholder="Motivo da negativa para registro interno..."
                />
                <p v-if="denyForm.errors.admin_note" class="mt-1 text-sm text-red-600">{{ denyForm.errors.admin_note }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button type="button" variant="primary" @click="approve">Aprovar reembolso</Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-red-200 text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300"
                    :disabled="denyForm.processing"
                    @click="deny"
                >
                    Negar solicitação
                </Button>
            </div>
        </div>
    </div>
</template>

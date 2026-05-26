<script setup>
import { Link, router as inertiaRouter } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import { Eye } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    requests: { type: Object, required: true },
    filters: { type: Object, default: () => ({ status: 'pending' }) },
});

function setFilter(status) {
    inertiaRouter.get('/reembolsos', { status }, { preserveState: true, preserveScroll: true, replace: true });
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

function statusClass(status) {
    if (status === 'pending') return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200';
    if (status === 'approved') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200';
    if (status === 'denied') return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200';
    return 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300';
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Reembolsos</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Solicitações enviadas pelos alunos na área de membros.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="f in [
                        { id: 'pending', label: 'Pendentes' },
                        { id: 'approved', label: 'Aprovados' },
                        { id: 'denied', label: 'Negados' },
                        { id: 'all', label: 'Todos' },
                    ]"
                    :key="f.id"
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-medium"
                    :class="
                        filters.status === f.id
                            ? 'bg-[var(--color-primary)] text-white'
                            : 'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'
                    "
                    @click="setFilter(f.id)"
                >
                    {{ f.label }}
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Aluno</th>
                        <th class="hidden px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 md:table-cell">Curso</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Valor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                        <th class="hidden px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 lg:table-cell">Solicitado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    <tr v-for="r in requests.data" :key="r.id" class="hover:bg-zinc-50 dark:hover:bg-zinc-900/40">
                        <td class="px-4 py-3">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ r.student_name }}</div>
                            <div class="text-xs text-zinc-500">{{ r.student_email }}</div>
                        </td>
                        <td class="hidden px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 md:table-cell">{{ r.product_name }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ fmtMoney(r.order_amount) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(r.status)">
                                {{ statusLabel(r.status) }}
                            </span>
                        </td>
                        <td class="hidden px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400 lg:table-cell">{{ fmtDate(r.created_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="`/reembolsos/${r.id}`"
                                class="inline-flex items-center justify-center rounded-lg p-2 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-700 dark:hover:text-white"
                                aria-label="Ver solicitação"
                                title="Ver solicitação"
                            >
                                <Eye class="h-5 w-5" />
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!requests.data?.length">
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-zinc-500">Nenhuma solicitação neste filtro.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="requests?.links?.length > 3" class="flex flex-wrap items-center justify-center gap-2" aria-label="Paginação">
            <a
                v-for="link in requests.links"
                :key="link.label"
                :href="link.url"
                :aria-current="link.active ? 'page' : undefined"
                :aria-disabled="!link.url"
                :class="[
                    'relative inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition',
                    link.active
                        ? 'z-10 bg-[var(--color-primary)] text-white'
                        : link.url
                          ? 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700'
                          : 'cursor-not-allowed text-zinc-400 dark:text-zinc-500',
                ]"
                v-html="link.label"
                @click.prevent="link.url && inertiaRouter.visit(link.url, { preserveState: true })"
            />
        </nav>
    </div>
</template>

<script setup>
import { Link, router as inertiaRouter } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import { Eye } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    tickets: { type: Object, required: true },
    filters: { type: Object, default: () => ({ status: 'open' }) },
});

function setFilter(status) {
    inertiaRouter.get('/suporte-alunos', { status }, { preserveState: true, preserveScroll: true, replace: true });
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
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Suporte alunos</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Tickets abertos pela área do aluno (<code class="rounded bg-zinc-100 px-1 text-xs dark:bg-zinc-900">/suporte</code>).</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-medium"
                    :class="
                        filters.status === 'open'
                            ? 'bg-[var(--color-primary)] text-white'
                            : 'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'
                    "
                    @click="setFilter('open')"
                >
                    Abertos
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-medium"
                    :class="
                        filters.status === 'closed'
                            ? 'bg-[var(--color-primary)] text-white'
                            : 'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'
                    "
                    @click="setFilter('closed')"
                >
                    Encerrados
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-medium"
                    :class="
                        filters.status === 'all'
                            ? 'bg-[var(--color-primary)] text-white'
                            : 'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'
                    "
                    @click="setFilter('all')"
                >
                    Todos
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Assunto</th>
                        <th class="hidden px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 md:table-cell">Aluno</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                        <th class="hidden px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 lg:table-cell">Atualizado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-zinc-50 dark:hover:bg-zinc-900/40">
                        <td class="px-4 py-3">
                            <Link :href="`/suporte-alunos/${t.id}`" class="font-medium text-[var(--color-primary)] hover:underline">
                                {{ t.subject }}
                            </Link>
                        </td>
                        <td class="hidden px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 md:table-cell">
                            <div>{{ t.student_name }}</div>
                            <div class="text-xs text-zinc-500">{{ t.student_email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    t.status === 'open'
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                                        : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-400'
                                "
                            >
                                {{ t.status === 'open' ? 'Aberto' : 'Encerrado' }}
                            </span>
                        </td>
                        <td class="hidden px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400 lg:table-cell">{{ fmtDate(t.updated_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="`/suporte-alunos/${t.id}`"
                                class="inline-flex items-center justify-center rounded-lg p-2 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-700 dark:hover:text-white"
                                aria-label="Abrir ticket"
                                title="Abrir ticket"
                            >
                                <Eye class="h-5 w-5" />
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!tickets.data?.length">
                        <td colspan="5" class="px-4 py-12 text-center text-sm text-zinc-500">Nenhum ticket neste filtro.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="tickets?.links?.length > 3"
            class="flex flex-wrap items-center justify-center gap-2"
            aria-label="Paginação"
        >
            <a
                v-for="link in tickets.links"
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

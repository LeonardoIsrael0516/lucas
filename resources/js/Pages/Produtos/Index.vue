<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import ProdutoCreateSidebar from '@/components/produtos/ProdutoCreateSidebar.vue';
import {
    MoreVertical,
    Pencil,
    Copy,
    Trash2,
    Package,
    ExternalLink,
    Search,
    TicketPercent,
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    produtos: { type: [Array, Object], default: () => [] },
    productTypes: { type: Array, default: () => [] },
    billingTypes: { type: Array, default: () => [] },
    exchange_rates: { type: Object, default: () => ({ brl_eur: 0.16, brl_usd: 0.18 }) },
    plugin_card_actions: { type: Object, default: () => ({}) },
    plugin_form_sections: { type: Array, default: () => [] },
    q: { type: String, default: '' },
});

const produtosList = computed(() => props.produtos?.data ?? (Array.isArray(props.produtos) ? props.produtos : []));

const sidebarOpen = ref(false);
const openMenuId = ref(null);
const productToDelete = ref(null);
const search = ref(props.q ?? '');
let searchTimer = null;

function openSidebar() {
    sidebarOpen.value = true;
}

function closeSidebar() {
    sidebarOpen.value = false;
}

function toggleMenu(id) {
    openMenuId.value = openMenuId.value === id ? null : id;
}

function closeMenu() {
    openMenuId.value = null;
}

function handleClickOutside(event) {
    if (openMenuId.value == null) return;
    const menuEl = document.querySelector(`[data-product-menu="${openMenuId.value}"]`);
    if (menuEl && !menuEl.contains(event.target)) {
        closeMenu();
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

function onSearchInput() {
    const q = (search.value ?? '').trim();
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/produtos', q ? { q } : {}, { preserveState: true, preserveScroll: true, replace: true });
        searchTimer = null;
    }, 600);
}

function formatBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value ?? 0);
}

function duplicate(p) {
    router.post(`/produtos/${p.id}/duplicate`, {}, { preserveScroll: true });
    closeMenu();
}

function openDeleteModal(p) {
    closeMenu();
    productToDelete.value = p;
}

function closeDeleteModal() {
    productToDelete.value = null;
}

function confirmDestroy() {
    const p = productToDelete.value;
    if (!p) return;
    router.delete(`/produtos/${p.id}`, { preserveScroll: true });
    closeDeleteModal();
}

function pluginActions(productId) {
    return props.plugin_card_actions?.[productId] ?? [];
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-md">
                <Search class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-zinc-400" />
                <input
                    v-model="search"
                    type="text"
                    class="w-full rounded-xl border border-zinc-200 bg-white py-2.5 pl-9 pr-3 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    placeholder="Buscar produto por nome..."
                    @input="onSearchInput"
                />
            </div>
            <div class="flex items-center justify-end gap-2">
                <Link
                    href="/produtos/cupons"
                    class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"
                >
                    <TicketPercent class="h-4 w-4" />
                    Cupons
                </Link>
                <Button @click="openSidebar">Novo produto</Button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
            <div
                v-for="p in produtosList"
                :key="p.id"
                class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800"
            >
                <Link
                    :href="`/produtos/${p.id}/edit`"
                    class="relative block aspect-[16/10] w-full bg-zinc-50 dark:bg-zinc-800/60"
                >
                    <img
                        v-if="p.image_url"
                        :src="p.image_url"
                        :alt="p.name"
                        class="absolute inset-0 h-full w-full object-cover object-center"
                    />
                    <div
                        v-else
                        class="absolute inset-0 flex items-center justify-center text-zinc-400 dark:text-zinc-500"
                    >
                        <Package class="h-10 w-10" aria-hidden="true" />
                    </div>

                    <span
                        class="absolute left-3 top-3 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="p.is_active ? 'bg-emerald-600 text-white' : 'bg-zinc-500 text-white'"
                    >
                        {{ p.is_active ? 'Ativo' : 'Inativo' }}
                    </span>
                </Link>
                <div class="p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <Link :href="`/produtos/${p.id}/edit`" class="block min-w-0">
                                <p class="line-clamp-2 text-[13px] font-semibold leading-snug text-zinc-900 dark:text-white">
                                    {{ p.name }}
                                </p>
                            </Link>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span class="rounded-lg bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                                    {{ p.type_label }}
                                </span>
                                <span class="rounded-lg bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                                    {{ p.billing_type_label ?? 'Pagamento único' }}
                                </span>
                            </div>
                        </div>

                        <div class="relative shrink-0" :data-product-menu="p.id">
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                aria-label="Abrir menu"
                                aria-expanded="openMenuId === p.id"
                                @click="toggleMenu(p.id)"
                            >
                                <MoreVertical class="h-3.5 w-3.5" />
                            </button>
                            <div
                                v-show="openMenuId === p.id"
                                class="absolute right-0 top-full z-50 mt-1 w-48 rounded-xl border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <Link
                                    :href="`/produtos/${p.id}/edit`"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                    @click="closeMenu"
                                >
                                    <Pencil class="h-4 w-4 shrink-0" />
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                    @click="duplicate(p)"
                                >
                                    <Copy class="h-4 w-4 shrink-0" />
                                    Duplicar
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                    @click="openDeleteModal(p)"
                                >
                                    <Trash2 class="h-4 w-4 shrink-0" />
                                    Excluir
                                </button>
                                <template v-for="(action, actIdx) in pluginActions(p.id)" :key="`plugin-${p.id}-${actIdx}`">
                                    <a
                                        v-if="action.href"
                                        :href="action.href"
                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                        @click="closeMenu"
                                    >
                                        <ExternalLink v-if="!action.icon" class="h-4 w-4 shrink-0" />
                                        <component v-else :is="action.icon" class="h-4 w-4 shrink-0" />
                                        {{ action.label }}
                                    </a>
                                    <span v-else class="block border-t border-zinc-100 px-3 py-1 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                                        {{ action.label }}
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <p class="mt-2 text-sm font-bold text-[var(--color-primary)]">
                        {{ formatBRL(p.price_brl ?? p.price) }}
                    </p>

                    <div class="mt-2 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>Alunos: {{ p.alunos_count ?? 0 }}</span>
                        <span>Vendas: {{ p.vendas_count ?? 0 }}</span>
                    </div>

                    <a
                        v-if="p.checkout_slug"
                        :href="`/c/${p.checkout_slug}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-2 inline-flex text-xs font-semibold text-[var(--color-primary)] hover:underline"
                    >
                        Ver checkout →
                    </a>
                </div>
            </div>
        </div>

        <nav
            v-if="produtos?.links?.length > 3"
            class="flex items-center justify-center gap-2"
            aria-label="Paginação"
        >
            <a
                v-for="link in produtos.links"
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
                @click.prevent="link.url && router.visit(link.url, { preserveState: true })"
            />
        </nav>

        <div
            v-if="!produtosList.length"
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-700"
        >
            <Package class="h-14 w-14 text-zinc-400 dark:text-zinc-500" />
            <p class="mt-3 text-zinc-600 dark:text-zinc-400">Nenhum produto ainda.</p>
            <Button class="mt-4" @click="openSidebar">
                Criar primeiro produto
            </Button>
        </div>
    </div>

    <!-- Modal de confirmação de exclusão -->
    <Teleport to="body">
        <div
            v-if="productToDelete"
            class="fixed inset-0 z-[100002] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-modal-title"
        >
            <div
                class="fixed inset-0 bg-zinc-900/60 dark:bg-zinc-950/70"
                aria-hidden="true"
                @click="closeDeleteModal"
            />
            <div
                class="relative w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
            >
                <h2 id="delete-modal-title" class="text-lg font-semibold text-zinc-900 dark:text-white">
                    Excluir produto?
                </h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    Tem certeza que deseja excluir
                    <strong class="text-zinc-900 dark:text-white">"{{ productToDelete?.name }}"</strong>?
                    Esta ação não pode ser desfeita.
                </p>
                <div class="mt-5 flex gap-3 justify-end">
                    <Button variant="outline" @click="closeDeleteModal">
                        Cancelar
                    </Button>
                    <Button variant="destructive" @click="confirmDestroy">
                        Excluir
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <ProdutoCreateSidebar
        :open="sidebarOpen"
        :product-types="productTypes"
        :billing-types="billingTypes"
        :exchange-rates="exchange_rates"
        :plugin-form-sections="plugin_form_sections"
        @close="closeSidebar"
        @success="closeSidebar"
    />
</template>

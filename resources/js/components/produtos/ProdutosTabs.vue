<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Package } from 'lucide-vue-next';

const page = usePage();
const isProdutos = computed(() => {
    const url = page.url;
    return url === '/produtos' || url.startsWith('/produtos?') || url.match(/^\/produtos\/\d+/);
});
</script>

<template>
    <nav class="inline-flex rounded-xl bg-zinc-100/80 p-1 dark:bg-zinc-800/80" aria-label="Produtos">
        <Link
            href="/produtos"
            :class="[
                'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                isProdutos
                    ? 'bg-white text-[var(--color-primary)] shadow-sm dark:bg-zinc-700 dark:text-[var(--color-primary)]'
                    : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white',
            ]"
        >
            <Package class="h-4 w-4 shrink-0" aria-hidden="true" />
            Produtos
        </Link>
    </nav>
</template>

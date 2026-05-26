<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { useBackToPanelLink } from '@/composables/useBackToPanelLink';

defineProps({
    collapsed: { type: Boolean, default: false },
    /** sidebar | header */
    variant: { type: String, default: 'sidebar' },
    /** Sidebar escura (área do curso) */
    darkSidebar: { type: Boolean, default: false },
});

const { showBackToPanel, backToPanelHref, backToPanelLabel } = useBackToPanelLink();
</script>

<template>
    <Link
        v-if="showBackToPanel"
        :href="backToPanelHref"
        class="flex items-center rounded-lg text-sm font-medium transition"
        :class="[
            variant === 'header'
                ? 'gap-1.5 px-2.5 py-1.5 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'
                : darkSidebar
                  ? 'px-2.5 py-2 text-white/55 hover:bg-white/[0.07] hover:text-white/90'
                  : 'px-3 py-2.5 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800',
            collapsed ? 'justify-center' : 'gap-3',
        ]"
        :title="backToPanelLabel"
    >
        <ArrowLeft class="h-5 w-5 shrink-0" />
        <span v-if="!collapsed">{{ backToPanelLabel }}</span>
    </Link>
</template>

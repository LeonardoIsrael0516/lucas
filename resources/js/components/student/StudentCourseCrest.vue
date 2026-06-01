<script setup>
import { computed } from 'vue';

const props = defineProps({
    src: { type: String, default: null },
    name: { type: String, default: '' },
    /** sm | md | lg */
    size: { type: String, default: 'md' },
    /** Sem caixa/borda/sombra — só a imagem ou iniciais (hub upsell / cards horizontais) */
    plain: { type: Boolean, default: false },
    /** circle | rounded */
    shape: { type: String, default: 'circle' },
});

const sizeClass = computed(() => {
    if (props.size === 'sm') return 'h-14 w-14 text-xs';
    if (props.size === 'lg') return 'h-[4.5rem] w-[4.5rem] text-lg';
    return 'h-[4.25rem] w-[4.25rem] text-sm';
});

const shapeClass = computed(() => (props.shape === 'rounded' ? 'rounded-xl' : 'rounded-full'));

const shellClass = computed(() => {
    const base = ['relative shrink-0 overflow-hidden', sizeClass.value, shapeClass.value];
    if (props.plain) {
        return base;
    }
    return [...base, 'border border-zinc-200 bg-white shadow-sm'];
});

const initials = computed(() => {
    const parts = String(props.name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }
    return '?';
});

const fallbackStyle = computed(() => {
    if (props.plain) {
        return {};
    }
    const name = props.name || 'curso';
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const hue = Math.abs(hash) % 360;
    return {
        backgroundColor: `hsl(${hue} 42% 38%)`,
        color: '#fff',
    };
});
</script>

<template>
    <div class="student-course-crest" :class="shellClass">
        <img
            v-if="src"
            :src="src"
            :alt="name"
            class="h-full w-full object-contain"
            :class="plain ? '' : 'p-1'"
        />
        <div
            v-else
            class="flex h-full w-full items-center justify-center font-bold tracking-tight"
            :class="plain ? 'text-zinc-500 ring-1 ring-zinc-200' : ''"
            :style="fallbackStyle"
        >
            {{ initials }}
        </div>
    </div>
</template>

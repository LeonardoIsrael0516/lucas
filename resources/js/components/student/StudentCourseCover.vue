<script setup>
/** Proporção da capa do produto (checkout + hub). */
defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    imgClass: { type: String, default: '' },
});
</script>

<template>
    <div
        class="student-course-cover relative isolate w-full shrink-0 overflow-hidden bg-gradient-to-br from-zinc-800 to-zinc-950"
        style="aspect-ratio: 1060 / 663; max-height: 10.75rem;"
    >
        <img
            v-if="src"
            :src="src"
            :alt="alt"
            class="absolute inset-0 h-full w-full object-cover object-center"
            :class="imgClass"
        />
        <slot />
    </div>
</template>

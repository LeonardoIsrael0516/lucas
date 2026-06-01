<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';

const props = defineProps({
    html: { type: String, default: '' },
    emptyText: {
        type: String,
        default: 'Dê as boas-vindas e orientações gerais de como seus alunos podem aproveitar o conteúdo desta aula.',
    },
});

const expanded = ref(false);
const contentRef = ref(null);
const isTruncatable = ref(false);

const hasContent = computed(() => !!(props.html && props.html.trim()));

async function measureTruncation() {
    await nextTick();
    const el = contentRef.value;
    if (!el || !hasContent.value) {
        isTruncatable.value = false;
        return;
    }
    isTruncatable.value = el.scrollHeight > el.clientHeight + 2;
}

onMounted(measureTruncation);
watch(() => props.html, measureTruncation);
</script>

<template>
    <div>
        <div
            v-if="hasContent"
            ref="contentRef"
            class="prose prose-sm max-w-none text-[var(--lesson-text)]"
            :class="expanded ? '' : 'line-clamp-4 max-h-[6.5rem] overflow-hidden'"
            v-html="html"
        />
        <p v-else class="text-sm leading-relaxed text-[var(--lesson-text-2)]">
            {{ emptyText }}
        </p>
        <button
            v-if="hasContent && (isTruncatable || expanded)"
            type="button"
            class="mt-2 text-sm font-semibold text-[var(--student-primary,#059669)] hover:underline"
            @click="expanded = !expanded"
        >
            {{ expanded ? 'Ver menos' : 'Ver mais' }}
        </button>
    </div>
</template>

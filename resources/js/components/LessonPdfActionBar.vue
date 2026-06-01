<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Star, Bookmark, Check, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    prevHref: { type: String, default: null },
    nextHref: { type: String, default: null },
    completed: { type: Boolean, default: false },
    userRating: { type: Number, default: null },
    userBookmarked: { type: Boolean, default: false },
    ratingSubmitting: { type: Boolean, default: false },
    bookmarkSubmitting: { type: Boolean, default: false },
    completeSubmitting: { type: Boolean, default: false },
});

const emit = defineEmits(['complete', 'rate', 'bookmark']);

const stars = [1, 2, 3, 4, 5];

const canComplete = computed(() => !props.completed && !props.completeSubmitting);

const outlineBtnClass =
    'inline-flex items-center justify-center gap-2 rounded-lg border border-[var(--lesson-border)] bg-white px-3 py-2 text-sm font-semibold text-[var(--lesson-text)] shadow-sm transition hover:bg-[var(--lesson-bg)] disabled:cursor-not-allowed disabled:opacity-50';

const navBtnClass =
    'inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[var(--lesson-border)] bg-white text-[var(--lesson-text)] shadow-sm transition hover:bg-[var(--lesson-bg)] disabled:cursor-not-allowed disabled:opacity-40';

function starClass(n) {
    const filled = props.userRating != null && n <= props.userRating;
    return [
        'rounded p-0.5 transition',
        filled ? 'text-amber-400' : 'text-[var(--lesson-text-3)] hover:text-amber-300',
        props.ratingSubmitting ? 'cursor-wait opacity-60' : 'cursor-pointer',
    ];
}

function onStarClick(n) {
    if (props.ratingSubmitting) return;
    emit('rate', n);
}
</script>

<template>
    <div
        class="flex shrink-0 flex-col gap-3 border-b border-[var(--lesson-border)] bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-[var(--lesson-text-2)]">Avaliar aula</span>
            <div class="flex items-center gap-0.5" role="group" aria-label="Avaliar aula de 1 a 5 estrelas">
                <button
                    v-for="n in stars"
                    :key="n"
                    type="button"
                    :class="starClass(n)"
                    :aria-label="`${n} estrela${n > 1 ? 's' : ''}`"
                    :aria-pressed="userRating != null && n <= userRating"
                    @click="onStarClick(n)"
                >
                    <Star
                        class="h-5 w-5"
                        :class="userRating != null && n <= userRating ? 'fill-current' : ''"
                    />
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <button
                type="button"
                :class="[
                    outlineBtnClass,
                    userBookmarked ? 'border-amber-300 bg-amber-50 text-amber-900' : '',
                ]"
                :disabled="bookmarkSubmitting"
                @click="emit('bookmark')"
            >
                <Bookmark class="h-4 w-4" :class="userBookmarked ? 'fill-current' : ''" />
                Salvar aula
            </button>

            <button
                v-if="canComplete"
                type="button"
                :class="outlineBtnClass"
                :disabled="completeSubmitting"
                @click="emit('complete')"
            >
                <Check class="h-4 w-4" />
                Concluir aula
            </button>
            <button
                v-else
                type="button"
                :class="outlineBtnClass"
                disabled
                aria-disabled="true"
            >
                <Check class="h-4 w-4" />
                Concluída
            </button>

            <Link v-if="prevHref" :href="prevHref" :class="navBtnClass" aria-label="Aula anterior">
                <ChevronLeft class="h-5 w-5" />
            </Link>
            <button v-else type="button" :class="navBtnClass" disabled aria-label="Aula anterior">
                <ChevronLeft class="h-5 w-5" />
            </button>

            <Link v-if="nextHref" :href="nextHref" :class="navBtnClass" aria-label="Próxima aula">
                <ChevronRight class="h-5 w-5" />
            </Link>
            <button v-else type="button" :class="navBtnClass" disabled aria-label="Próxima aula">
                <ChevronRight class="h-5 w-5" />
            </button>
        </div>
    </div>
</template>

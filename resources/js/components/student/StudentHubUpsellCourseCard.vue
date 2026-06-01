<script setup>
import StudentCourseCrest from '@/components/student/StudentCourseCrest.vue';
import { Lock } from 'lucide-vue-next';

defineProps({
    course: { type: Object, required: true },
    recommended: { type: Boolean, default: false },
});
</script>

<template>
    <div
        class="flex flex-col overflow-hidden rounded-xl border bg-white p-4 shadow-sm sm:p-5"
        :class="recommended ? 'border-sky-200' : 'border-zinc-200'"
    >
        <div class="mb-4 flex items-start justify-between gap-2">
            <Lock class="h-5 w-5 shrink-0 text-zinc-400" aria-hidden="true" />
            <span
                v-if="recommended"
                class="rounded-md bg-orange-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-orange-700"
            >
                Recomendado
            </span>
        </div>

        <div class="flex items-center gap-3 sm:gap-4">
            <StudentCourseCrest
                :src="course.crest_url"
                :name="course.name"
                size="lg"
                plain
                shape="circle"
            />
            <div class="min-w-0 flex-1">
                <h3 class="line-clamp-2 text-base font-bold leading-snug text-zinc-900">{{ course.name }}</h3>
                <p v-if="course.subtitle" class="mt-1 line-clamp-2 text-sm text-zinc-500">{{ course.subtitle }}</p>
            </div>
        </div>

        <p class="mt-5 text-center text-sm font-semibold text-orange-600">{{ course.price_label }}</p>
        <a
            :href="course.checkout_url"
            class="mt-3 inline-flex w-full items-center justify-center rounded-lg border-2 border-orange-500 bg-white px-4 py-2.5 text-sm font-semibold text-orange-600 transition hover:bg-orange-50"
        >
            Adquirir acesso
        </a>
    </div>
</template>

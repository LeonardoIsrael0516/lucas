<script setup>
import StudentCourseCrest from '@/components/student/StudentCourseCrest.vue';
import { ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    item: { type: Object, required: true },
});

function progressPercent() {
    const total = props.item.lesson_total;
    const index = props.item.lesson_index;
    if (!total || !index) return 0;
    return Math.min(100, Math.round((index / total) * 100));
}
</script>

<template>
    <a
        :href="item.lesson_href"
        class="group flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-zinc-300 hover:shadow-md sm:gap-5 sm:p-5"
    >
        <StudentCourseCrest :src="item.crest_url" :name="item.product_name" size="lg" plain shape="circle" />
        <div class="min-w-0 flex-1">
            <div class="mb-1 flex flex-wrap items-center gap-2">
                <span
                    v-if="item.has_new_content"
                    class="rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800"
                >
                    Novo conteúdo
                </span>
            </div>
            <p class="line-clamp-1 text-base font-bold text-zinc-900 sm:text-lg">{{ item.lesson_title }}</p>
            <p v-if="item.subtitle" class="mt-0.5 line-clamp-1 text-sm text-zinc-600">{{ item.subtitle }}</p>
            <p v-else class="mt-0.5 line-clamp-1 text-sm font-medium text-zinc-700">{{ item.product_name }}</p>
            <p v-if="item.module_title" class="mt-1 line-clamp-1 text-xs text-zinc-500">
                {{ item.module_title }}
            </p>
            <div class="mt-3 h-1.5 w-full max-w-md overflow-hidden rounded-full bg-zinc-200">
                <div
                    class="h-full rounded-full bg-emerald-500 transition-[width]"
                    :style="{ width: `${progressPercent()}%` }"
                />
            </div>
            <p v-if="item.lesson_index && item.lesson_total" class="mt-1.5 text-xs text-zinc-500">
                Aula {{ item.lesson_index }} de {{ item.lesson_total }}
            </p>
        </div>
        <ChevronRight class="h-6 w-6 shrink-0 text-zinc-400 transition group-hover:text-zinc-600" />
    </a>
</template>

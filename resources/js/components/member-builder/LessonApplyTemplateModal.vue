<script setup>
import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/Button.vue';
import { X, Search } from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
    sourceLesson: { type: Object, default: null },
    sourceModuleTitle: { type: String, default: '' },
    modules: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'apply']);

const search = ref('');
const selectedIds = ref(new Set());
const keepTargetTitles = ref(true);
const copyStorage = ref(true);

const allLessons = computed(() => {
    const out = [];
    for (const mod of props.modules ?? []) {
        if (mod.related_product_id || mod.external_url) continue;
        for (const lesson of mod.lessons ?? []) {
            if (lesson.id == props.sourceLesson?.id) continue;
            out.push({
                lesson,
                moduleId: mod.id,
                moduleTitle: mod.title ?? 'Módulo',
            });
        }
    }
    return out;
});

const filteredLessons = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return allLessons.value;
    return allLessons.value.filter((e) => {
        const t = (e.lesson.title ?? '').toLowerCase();
        const m = (e.moduleTitle ?? '').toLowerCase();
        return t.includes(q) || m.includes(q);
    });
});

const grouped = computed(() => {
    const map = new Map();
    for (const e of filteredLessons.value) {
        const key = e.moduleId;
        if (!map.has(key)) {
            map.set(key, { moduleTitle: e.moduleTitle, items: [] });
        }
        map.get(key).items.push(e);
    }
    return [...map.values()];
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            search.value = '';
            selectedIds.value = new Set();
            keepTargetTitles.value = true;
            copyStorage.value = true;
        }
    }
);

function toggle(id) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
}

function selectAllVisible() {
    selectedIds.value = new Set(filteredLessons.value.map((e) => e.lesson.id));
}

function clearSelection() {
    selectedIds.value = new Set();
}

function submit() {
    if (!selectedIds.value.size) return;
    emit('apply', {
        target_lesson_ids: [...selectedIds.value],
        keep_target_titles: keepTargetTitles.value,
        copy_storage: copyStorage.value,
    });
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
            @click.self="emit('close')"
        >
            <div
                class="flex max-h-[min(92vh,720px)] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                role="dialog"
                aria-labelledby="apply-template-title"
            >
                <div class="flex shrink-0 items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <div class="min-w-0 pr-2">
                        <h2 id="apply-template-title" class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                            Replicar aula em outras
                        </h2>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                            Modelo: {{ sourceLesson?.title || 'Aula' }}
                            <span v-if="sourceModuleTitle"> · {{ sourceModuleTitle }}</span>
                        </p>
                    </div>
                    <button type="button" class="rounded p-1 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="emit('close')">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="shrink-0 space-y-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">
                        Copia tipo, liberação, visão geral, links, PDFs, anexos e demais campos da aula modelo para as aulas selecionadas.
                    </p>
                    <label class="flex items-center gap-2 text-xs text-zinc-700 dark:text-zinc-300">
                        <input v-model="keepTargetTitles" type="checkbox" class="rounded border-zinc-300" />
                        Manter o título de cada aula destino
                    </label>
                    <label class="flex items-center gap-2 text-xs text-zinc-700 dark:text-zinc-300">
                        <input v-model="copyStorage" type="checkbox" class="rounded border-zinc-300" />
                        Copiar arquivos no storage (cópias independentes)
                    </label>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            v-model="search"
                            type="search"
                            class="w-full rounded-lg border border-zinc-300 bg-white py-2 pl-9 pr-3 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                            placeholder="Buscar aula ou módulo..."
                        />
                    </div>
                    <div class="flex gap-2">
                        <Button type="button" size="sm" variant="outline" @click="selectAllVisible">Selecionar visíveis</Button>
                        <Button type="button" size="sm" variant="ghost" @click="clearSelection">Limpar</Button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4">
                    <p v-if="!allLessons.length" class="text-center text-sm text-zinc-500">
                        Não há outras aulas neste produto para aplicar o modelo.
                    </p>
                    <div v-else-if="!grouped.length" class="text-center text-sm text-zinc-500">Nenhuma aula encontrada na busca.</div>
                    <div v-else class="space-y-4">
                        <div v-for="(group, gi) in grouped" :key="gi">
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">{{ group.moduleTitle }}</p>
                            <ul class="space-y-1">
                                <li v-for="entry in group.items" :key="entry.lesson.id">
                                    <label
                                        class="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/60"
                                        :class="selectedIds.has(entry.lesson.id) ? 'border-sky-500 bg-sky-50/80 dark:bg-sky-950/30' : ''"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="selectedIds.has(entry.lesson.id)"
                                            class="rounded border-zinc-300"
                                            @change="toggle(entry.lesson.id)"
                                        />
                                        <span class="min-w-0 flex-1 truncate">{{ entry.lesson.title || 'Sem título' }}</span>
                                    </label>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 gap-2 border-t border-zinc-200 p-4 dark:border-zinc-700">
                    <Button variant="outline" class="flex-1" @click="emit('close')">Cancelar</Button>
                    <Button class="flex-1" :disabled="saving || !selectedIds.size" @click="submit">
                        {{ saving ? 'Aplicando…' : `Aplicar em ${selectedIds.size} aula(s)` }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

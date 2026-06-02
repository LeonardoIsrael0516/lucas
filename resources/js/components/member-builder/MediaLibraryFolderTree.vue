<script setup>
import { computed } from 'vue';
import { ChevronRight, ChevronDown, FolderOpen, Pencil, Trash2 } from 'lucide-vue-next';

defineOptions({ name: 'MediaLibraryFolderTree' });

const props = defineProps({
    parentId: { type: [Number, null], default: null },
    depth: { type: Number, default: 0 },
    /** @type {Record<string, Array>} */
    foldersByParent: { type: Object, required: true },
    expandedIds: { type: Array, default: () => [] },
    currentFolderId: { type: [Number, null], default: null },
});

const emit = defineEmits(['select', 'toggle-expand', 'rename', 'delete']);

const children = computed(() => {
    const key = props.parentId === null || props.parentId === undefined ? 'null' : String(props.parentId);
    return props.foldersByParent[key] ?? [];
});

function hasChildren(folder) {
    const list = props.foldersByParent[String(folder.id)] ?? [];
    return list.length > 0;
}

function isExpanded(folderId) {
    return props.expandedIds.includes(folderId);
}

function isActive(folderId) {
    return props.currentFolderId === folderId;
}
</script>

<template>
    <ul v-if="children.length" class="flex flex-col gap-0.5" role="tree">
        <li v-for="folder in children" :key="folder.id" role="treeitem" :aria-expanded="hasChildren(folder) ? isExpanded(folder.id) : undefined">
            <div
                class="group flex min-w-0 items-center gap-0.5 rounded-lg pr-1 transition"
                :style="{ paddingLeft: `${depth * 12 + 4}px` }"
            >
                <button
                    v-if="hasChildren(folder)"
                    type="button"
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-zinc-500 hover:bg-zinc-200/80 dark:hover:bg-zinc-700"
                    :aria-label="isExpanded(folder.id) ? 'Recolher subpastas' : 'Expandir subpastas'"
                    @click.stop="emit('toggle-expand', folder.id)"
                >
                    <ChevronDown v-if="isExpanded(folder.id)" class="h-3.5 w-3.5" />
                    <ChevronRight v-else class="h-3.5 w-3.5" />
                </button>
                <span v-else class="inline-block w-6 shrink-0" aria-hidden="true" />

                <button
                    type="button"
                    class="flex min-w-0 flex-1 items-center gap-1.5 rounded-md px-1.5 py-1.5 text-left text-xs font-medium transition"
                    :class="
                        isActive(folder.id)
                            ? 'bg-sky-600 text-white shadow-sm'
                            : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700/80'
                    "
                    :title="folder.path_label || folder.name"
                    @click="emit('select', folder.id)"
                >
                    <FolderOpen class="h-3.5 w-3.5 shrink-0 opacity-80" />
                    <span class="truncate">{{ folder.name }}</span>
                    <span
                        class="shrink-0 rounded px-1 py-0.5 text-[10px] tabular-nums"
                        :class="
                            isActive(folder.id)
                                ? 'bg-white/20 text-white'
                                : 'bg-zinc-200/90 text-zinc-600 dark:bg-zinc-600 dark:text-zinc-300'
                        "
                    >
                        {{ folder.items_count }}
                    </span>
                </button>

                <span
                    class="hidden shrink-0 items-center gap-0.5 group-hover:flex"
                    :class="isActive(folder.id) ? '!flex' : ''"
                >
                    <button
                        type="button"
                        class="rounded p-0.5 hover:bg-black/10"
                        title="Renomear"
                        @click.stop="emit('rename', folder)"
                    >
                        <Pencil class="h-3 w-3" />
                    </button>
                    <button
                        type="button"
                        class="rounded p-0.5 hover:bg-black/10"
                        title="Excluir"
                        @click.stop="emit('delete', folder)"
                    >
                        <Trash2 class="h-3 w-3" />
                    </button>
                </span>
            </div>

            <MediaLibraryFolderTree
                v-if="hasChildren(folder) && isExpanded(folder.id)"
                :parent-id="folder.id"
                :depth="depth + 1"
                :folders-by-parent="foldersByParent"
                :expanded-ids="expandedIds"
                :current-folder-id="currentFolderId"
                @select="emit('select', $event)"
                @toggle-expand="emit('toggle-expand', $event)"
                @rename="emit('rename', $event)"
                @delete="emit('delete', $event)"
            />
        </li>
    </ul>
</template>

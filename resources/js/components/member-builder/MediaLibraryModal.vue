<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import Button from '@/components/ui/Button.vue';
import MediaLibraryFolderTree from '@/components/member-builder/MediaLibraryFolderTree.vue';
import {
    X,
    Search,
    Upload,
    Trash2,
    FileText,
    Check,
    Loader2,
    FolderOpen,
    Plus,
    Image as ImageIcon,
    FileArchive,
    File,
} from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
    embedded: { type: Boolean, default: false },
    mode: { type: String, default: 'pick' },
    indexUrl: { type: String, required: true },
    uploadUrl: { type: String, required: true },
    foldersUrl: { type: String, required: true },
    deleteUrlBase: { type: String, required: true },
    pdfMaxMb: { type: Number, default: 50 },
    csrfToken: { type: String, default: '' },
    currentProductId: { type: String, default: '' },
    tenantProducts: { type: Array, default: () => [] },
    /** 0 = sem limite; 1 = apenas uma imagem/arquivo na seleção */
    maxPick: { type: Number, default: 0 },
    /** Ex.: image — restringe filtros e listagem no modo pick */
    lockedMediaType: { type: String, default: '' },
});

const emit = defineEmits(['close', 'select']);

defineExpose({
    openUploadPanel: () => {
        activePanel.value = 'upload';
    },
    openLibraryPanel: () => {
        activePanel.value = 'library';
    },
    refresh: () => fetchItems(1),
});

const MEDIA_FILTERS = [
    { id: '', label: 'Todos' },
    { id: 'image', label: 'Imagens' },
    { id: 'pdf', label: 'PDFs' },
    { id: 'document', label: 'Documentos' },
    { id: 'archive', label: 'Arquivos' },
];

const UPLOAD_ACCEPT =
    '.pdf,application/pdf,image/jpeg,image/png,image/gif,image/webp,.zip,application/zip,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain';

const activePanel = ref('library');
const items = ref([]);
const allFolders = ref([]);
const currentFolder = ref(null);
const currentFolderId = ref(null);
const expandedFolderIds = ref([]);
const filterMediaType = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(false);
const uploading = ref(false);
const searchQuery = ref('');
const filterProductId = ref('');
const selectedIds = ref(new Set());
const uploadInput = ref(null);
const errorMessage = ref('');
const movingItemId = ref(null);
const moveTargetFolderId = ref('');
let searchDebounce = null;

const headers = () => ({
    'X-CSRF-TOKEN': props.csrfToken,
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
});

const uploadHeaders = () => ({
    ...headers(),
    'Content-Type': 'multipart/form-data',
});

const selectedCount = computed(() => selectedIds.value.size);
const canConfirmPick = computed(() => props.mode === 'pick' && selectedCount.value > 0);
const isAtRoot = computed(() => currentFolderId.value == null);
const newFolderLabel = computed(() => (currentFolderId.value != null ? 'Nova subpasta' : 'Nova pasta'));
const isVisible = computed(() => props.embedded || props.open);
const hasGridContent = computed(() => items.value.length > 0);

const foldersByParent = computed(() => {
    const map = {};
    for (const folder of allFolders.value) {
        const key = folder.parent_id == null ? 'null' : String(folder.parent_id);
        if (!map[key]) map[key] = [];
        map[key].push(folder);
    }
    for (const key of Object.keys(map)) {
        map[key].sort((a, b) => String(a.name).localeCompare(String(b.name), 'pt-BR'));
    }
    return map;
});

const currentFolderLabel = computed(() => {
    if (currentFolderId.value == null) return 'Raiz';
    return currentFolder.value?.name ?? allFolders.value.find((f) => f.id === currentFolderId.value)?.name ?? 'Pasta';
});

function expandAncestors(folderId) {
    const next = new Set(expandedFolderIds.value);
    let current = allFolders.value.find((f) => f.id === folderId);
    while (current?.parent_id != null) {
        next.add(current.parent_id);
        current = allFolders.value.find((f) => f.id === current.parent_id);
    }
    expandedFolderIds.value = [...next];
}

function toggleFolderExpand(folderId) {
    const next = new Set(expandedFolderIds.value);
    if (next.has(folderId)) {
        next.delete(folderId);
    } else {
        next.add(folderId);
    }
    expandedFolderIds.value = [...next];
}

function selectFolderById(folderId) {
    if (folderId == null) {
        goToRoot();
        return;
    }
    const folder = allFolders.value.find((f) => f.id === folderId);
    if (!folder) return;
    expandAncestors(folderId);
    const hasSubfolders = (foldersByParent.value[String(folder.id)] ?? []).length > 0;
    if (hasSubfolders) {
        expandedFolderIds.value = [...new Set([...expandedFolderIds.value, folder.id])];
    }
    currentFolderId.value = folder.id;
    currentFolder.value = folder;
    searchQuery.value = '';
    fetchItems(1);
}

function deleteUrlFor(id) {
    return `${props.deleteUrlBase.replace(/\/$/, '')}/${id}`;
}

function moveUrlFor(id) {
    return `${deleteUrlFor(id)}/move`;
}

function folderUpdateUrl(id) {
    return `${props.foldersUrl.replace(/\/$/, '')}/${id}`;
}

function itemPreviewIcon(item) {
    if (item.media_type === 'image' || item.is_image) return 'image';
    if (item.media_type === 'pdf') return 'pdf';
    if (item.media_type === 'archive') return 'archive';
    if (item.media_type === 'document') return 'document';
    return 'file';
}

async function fetchItems(page = 1) {
    loading.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.get(props.indexUrl, {
            headers: headers(),
            params: {
                page,
                per_page: 36,
                q: searchQuery.value.trim() || undefined,
                product_id: filterProductId.value || undefined,
                folder_id: currentFolderId.value ?? undefined,
                media_type: filterMediaType.value || undefined,
            },
        });
        items.value = Array.isArray(data?.items) ? data.items : [];
        allFolders.value = Array.isArray(data?.all_folders) ? data.all_folders : [];
        currentFolder.value = data?.current_folder ?? currentFolder.value;
        if (currentFolderId.value != null && !currentFolder.value) {
            currentFolder.value = allFolders.value.find((f) => f.id === currentFolderId.value) ?? null;
        }
        meta.value = data?.meta ?? { current_page: 1, last_page: 1, total: 0 };
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Não foi possível carregar a biblioteca.';
        items.value = [];
    } finally {
        loading.value = false;
    }
}

function scheduleSearch() {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => fetchItems(1), 350);
}

function setMediaFilter(type) {
    filterMediaType.value = type;
    fetchItems(1);
}

function goToRoot() {
    currentFolderId.value = null;
    currentFolder.value = null;
    fetchItems(1);
}

function toggleSelect(id) {
    if (props.mode !== 'pick') return;
    if (props.maxPick === 1) {
        selectedIds.value = selectedIds.value.has(id) ? new Set() : new Set([id]);
        return;
    }
    const next = new Set(selectedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        if (props.maxPick > 0 && next.size >= props.maxPick) {
            return;
        }
        next.add(id);
    }
    selectedIds.value = next;
}

function onCardClick(item) {
    if (props.mode !== 'pick') return;
    if (props.lockedMediaType && item.media_type !== props.lockedMediaType) {
        return;
    }
    toggleSelect(item.id);
}

function isSelected(id) {
    return selectedIds.value.has(id);
}

async function onUploadFiles(fileList) {
    const files = Array.from(fileList ?? []);
    if (!files.length) return;

    uploading.value = true;
    errorMessage.value = '';
    try {
        for (const file of files) {
            const formData = new FormData();
            formData.append('file', file);
            if (currentFolderId.value != null) {
                formData.append('folder_id', String(currentFolderId.value));
            }
            const { data } = await axios.post(props.uploadUrl, formData, { headers: uploadHeaders() });
            if (data?.item?.id) {
                selectedIds.value = new Set([...selectedIds.value, data.item.id]);
            }
        }
        activePanel.value = 'library';
        await fetchItems(1);
    } catch (e) {
        errorMessage.value =
            e.response?.data?.message ??
            e.response?.data?.errors?.file?.[0] ??
            e.message ??
            `Erro ao enviar arquivo. Tamanho máx. ${props.pdfMaxMb} MB (PDF).`;
    } finally {
        uploading.value = false;
        if (uploadInput.value) uploadInput.value.value = '';
    }
}

function onUploadInputChange(e) {
    onUploadFiles(e.target?.files);
}

async function removeItem(item) {
    if (item.usage_count > 0) {
        alert('Este arquivo está em uso em uma ou mais aulas. Remova-o das aulas antes de excluir.');
        return;
    }
    if (!confirm(`Remover "${item.name}" da biblioteca?`)) return;
    try {
        await axios.delete(deleteUrlFor(item.id), { headers: headers() });
        selectedIds.value.delete(item.id);
        selectedIds.value = new Set(selectedIds.value);
        await fetchItems(meta.value.current_page);
    } catch (e) {
        alert(e.response?.data?.message ?? 'Não foi possível remover o arquivo.');
    }
}

async function createFolder() {
    const name = window.prompt(
        currentFolderId.value != null ? 'Nome da nova subpasta:' : 'Nome da nova pasta:'
    );
    if (!name?.trim()) return;
    try {
        const body = { name: name.trim() };
        if (currentFolderId.value != null) {
            body.parent_id = currentFolderId.value;
        }
        await axios.post(props.foldersUrl, body, {
            headers: { ...headers(), 'Content-Type': 'application/json' },
        });
        if (currentFolderId.value != null) {
            expandedFolderIds.value = [...new Set([...expandedFolderIds.value, currentFolderId.value])];
        }
        await fetchItems(meta.value.current_page);
    } catch (e) {
        alert(e.response?.data?.message ?? e.response?.data?.errors?.name?.[0] ?? 'Não foi possível criar a pasta.');
    }
}

async function renameFolder(folder, e) {
    e?.stopPropagation();
    const name = window.prompt('Renomear pasta:', folder.name);
    if (!name?.trim() || name.trim() === folder.name) return;
    try {
        await axios.patch(
            folderUpdateUrl(folder.id),
            { name: name.trim() },
            { headers: { ...headers(), 'Content-Type': 'application/json' } }
        );
        await fetchItems(meta.value.current_page);
    } catch (err) {
        alert(err.response?.data?.message ?? err.response?.data?.errors?.name?.[0] ?? 'Não foi possível renomear.');
    }
}

async function deleteFolder(folder, e) {
    e?.stopPropagation();
    if (folder.items_count > 0) {
        alert('A pasta não está vazia. Mova ou remova os arquivos antes de excluir.');
        return;
    }
    if (folder.children_count > 0) {
        alert('A pasta contém subpastas. Remova as subpastas antes de excluir.');
        return;
    }
    if (!confirm(`Excluir a pasta "${folder.name}"?`)) return;
    try {
        await axios.delete(folderUpdateUrl(folder.id), { headers: headers() });
        expandedFolderIds.value = expandedFolderIds.value.filter((id) => id !== folder.id);
        if (currentFolderId.value === folder.id) {
            goToRoot();
        } else {
            await fetchItems(meta.value.current_page);
        }
    } catch (err) {
        alert(err.response?.data?.message ?? 'Não foi possível excluir a pasta.');
    }
}

function startMove(item, e) {
    e?.stopPropagation();
    movingItemId.value = item.id;
    moveTargetFolderId.value = item.folder_id != null ? String(item.folder_id) : '';
}

function cancelMove() {
    movingItemId.value = null;
    moveTargetFolderId.value = '';
}

async function confirmMove(item) {
    const folderId = moveTargetFolderId.value === '' ? null : Number(moveTargetFolderId.value);
    try {
        await axios.patch(
            moveUrlFor(item.id),
            { folder_id: folderId },
            { headers: { ...headers(), 'Content-Type': 'application/json' } }
        );
        cancelMove();
        await fetchItems(meta.value.current_page);
    } catch (e) {
        alert(e.response?.data?.message ?? 'Não foi possível mover o arquivo.');
    }
}

function confirmPick() {
    const picked = items.value.filter((it) => selectedIds.value.has(it.id));
    if (!picked.length) return;
    emit(
        'select',
        picked.map((it) => ({
            url: it.url,
            name: it.name,
            library_item_id: it.id,
            media_type: it.media_type,
        }))
    );
    emit('close');
}

function closeModal() {
    emit('close');
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            selectedIds.value = new Set();
            searchQuery.value = '';
            filterProductId.value = '';
            filterMediaType.value = props.lockedMediaType || '';
            activePanel.value = 'library';
            currentFolderId.value = null;
            currentFolder.value = null;
            expandedFolderIds.value = [];
            movingItemId.value = null;
            fetchItems(1);
        }
    }
);

watch(filterProductId, () => {
    if (isVisible.value) fetchItems(1);
});

onMounted(() => {
    if (props.embedded) {
        fetchItems(1);
    }
});
</script>

<template>
    <Teleport to="body" :disabled="embedded">
        <div
            v-if="isVisible"
            :class="
                embedded
                    ? 'flex h-full min-h-[min(70vh,800px)] flex-col'
                    : 'fixed inset-0 z-[200] flex items-center justify-center p-3 sm:p-6'
            "
            :role="embedded ? undefined : 'dialog'"
            :aria-modal="embedded ? undefined : 'true'"
            aria-labelledby="media-library-title"
        >
            <div v-if="!embedded" class="absolute inset-0 bg-black/50" @click="closeModal" />
            <div
                :class="
                    embedded
                        ? 'flex h-full min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900'
                        : 'relative flex h-[min(92vh,900px)] w-full max-w-6xl flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900'
                "
            >
                <header
                    v-if="!embedded"
                    class="flex shrink-0 items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700"
                >
                    <div class="flex items-center gap-2">
                        <FolderOpen class="h-5 w-5 text-sky-600" />
                        <h2 id="media-library-title" class="text-lg font-bold text-zinc-900 dark:text-white">
                            Biblioteca de mídia
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        aria-label="Fechar"
                        @click="closeModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </header>

                <div class="flex shrink-0 gap-1 border-b border-zinc-200 px-3 dark:border-zinc-700">
                    <button
                        type="button"
                        class="px-3 py-2.5 text-sm font-semibold transition"
                        :class="
                            activePanel === 'library'
                                ? 'border-b-2 border-sky-600 text-sky-700 dark:text-sky-300'
                                : 'text-zinc-500 hover:text-zinc-700'
                        "
                        @click="activePanel = 'library'"
                    >
                        Biblioteca
                    </button>
                    <button
                        type="button"
                        class="px-3 py-2.5 text-sm fontibold transition"
                        :class="
                            activePanel === 'upload'
                                ? 'border-b-2 border-sky-600 text-sky-700 dark:text-sky-300'
                                : 'text-zinc-500 hover:text-zinc-700'
                        "
                        @click="activePanel = 'upload'"
                    >
                        Enviar novo
                    </button>
                </div>

                <div v-if="activePanel === 'library'" class="flex min-h-0 flex-1 flex-col md:flex-row">
                    <aside
                        class="flex shrink-0 flex-col border-b border-zinc-200 bg-zinc-50/80 dark:border-zinc-700 dark:bg-zinc-800/40 md:w-60 md:border-b-0 md:border-r lg:w-64"
                    >
                        <div class="px-2 pt-2">
                            <p class="px-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-zinc-400">Pastas</p>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto px-1.5 py-1 md:max-h-none">
                            <button
                                type="button"
                                class="mb-1 flex w-full items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition"
                                :class="
                                    isAtRoot
                                        ? 'bg-sky-600 text-white shadow-sm'
                                        : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-700/80'
                                "
                                @click="goToRoot"
                            >
                                <FolderOpen class="h-3.5 w-3.5 shrink-0" />
                                <span class="truncate">Raiz</span>
                            </button>

                            <MediaLibraryFolderTree
                                :parent-id="null"
                                :depth="0"
                                :folders-by-parent="foldersByParent"
                                :expanded-ids="expandedFolderIds"
                                :current-folder-id="currentFolderId"
                                @select="selectFolderById"
                                @toggle-expand="toggleFolderExpand"
                                @rename="renameFolder"
                                @delete="deleteFolder"
                            />
                        </div>
                        <div class="border-t border-zinc-200 p-2 dark:border-zinc-700">
                            <button
                                type="button"
                                class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed border-zinc-300 px-2 py-1.5 text-xs font-medium text-zinc-600 transition hover:border-sky-400 hover:text-sky-700 dark:border-zinc-600 dark:text-zinc-400"
                                @click="createFolder"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                {{ newFolderLabel }}
                            </button>
                        </div>
                    </aside>

                    <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                        <div class="flex shrink-0 flex-wrap items-center gap-2 border-b border-zinc-100 px-3 py-2 dark:border-zinc-800">
                            <p class="w-full text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                Exibindo:
                                <span class="text-zinc-900 dark:text-zinc-100">{{ currentFolderLabel }}</span>
                            </p>
                            <div v-if="!lockedMediaType" class="flex w-full flex-wrap gap-1">
                                <button
                                    v-for="f in MEDIA_FILTERS"
                                    :key="f.id"
                                    type="button"
                                    class="rounded-full px-2.5 py-1 text-xs font-medium transition"
                                    :class="
                                        filterMediaType === f.id
                                            ? 'bg-sky-600 text-white'
                                            : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300'
                                    "
                                    @click="setMediaFilter(f.id)"
                                >
                                    {{ f.label }}
                                </button>
                            </div>
                            <div class="relative min-w-[140px] flex-1">
                                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    :placeholder="isAtRoot ? 'Buscar na raiz…' : `Buscar em ${currentFolder?.name ?? 'pasta'}…`"
                                    class="w-full rounded-lg border border-zinc-200 py-2 pl-8 pr-3 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                                    @input="scheduleSearch"
                                />
                            </div>
                            <select
                                v-if="tenantProducts.length"
                                v-model="filterProductId"
                                class="rounded-lg border border-zinc-200 px-2 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                            >
                                <option value="">Todos os cursos</option>
                                <option v-for="p in tenantProducts" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                            <p
                                v-if="errorMessage"
                                class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300"
                            >
                                {{ errorMessage }}
                            </p>
                            <div v-if="loading" class="flex min-h-[240px] items-center justify-center text-zinc-500">
                                <Loader2 class="h-8 w-8 animate-spin" />
                            </div>
                            <p
                                v-else-if="!hasGridContent"
                                class="flex min-h-[240px] items-center justify-center text-center text-sm text-zinc-500"
                            >
                                <span v-if="isAtRoot">Nenhum arquivo na raiz. Envie mídia ou selecione uma pasta.</span>
                                <span v-else>Nenhum arquivo nesta pasta. Expanda subpastas na árvore ou envie arquivos aqui.</span>
                            </p>
                            <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                                <article
                                    v-for="item in items"
                                    :key="item.id"
                                    class="group relative flex flex-col overflow-hidden rounded-xl border-2 bg-white shadow-sm transition dark:bg-zinc-800/50"
                                    :class="[
                                        isSelected(item.id)
                                            ? 'border-sky-500 ring-2 ring-sky-500/30 dark:border-sky-500'
                                            : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600',
                                        mode === 'pick' ? 'cursor-pointer' : '',
                                    ]"
                                    :role="mode === 'pick' ? 'button' : undefined"
                                    :tabindex="mode === 'pick' ? 0 : undefined"
                                    @click="onCardClick(item)"
                                    @keydown.enter.prevent="onCardClick(item)"
                                    @keydown.space.prevent="onCardClick(item)"
                                >
                                    <div
                                        class="relative flex aspect-[4/3] items-center justify-center overflow-hidden bg-gradient-to-br from-zinc-50 to-zinc-100 dark:from-zinc-800 dark:to-zinc-900"
                                    >
                                        <img
                                            v-if="itemPreviewIcon(item) === 'image'"
                                            :src="item.url"
                                            :alt="item.name"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        />
                                        <FileText
                                            v-else-if="itemPreviewIcon(item) === 'pdf'"
                                            class="h-12 w-12 text-red-500/80"
                                            stroke-width="1.25"
                                        />
                                        <FileArchive
                                            v-else-if="itemPreviewIcon(item) === 'archive'"
                                            class="h-12 w-12 text-amber-600/80"
                                            stroke-width="1.25"
                                        />
                                        <File
                                            v-else-if="itemPreviewIcon(item) === 'document'"
                                            class="h-12 w-12 text-blue-500/80"
                                            stroke-width="1.25"
                                        />
                                        <ImageIcon v-else class="h-12 w-12 text-zinc-400" stroke-width="1.25" />
                                        <div
                                            v-if="mode === 'pick'"
                                            class="absolute left-2 top-2 flex h-6 w-6 items-center justify-center rounded-md border-2 shadow-sm transition"
                                            :class="
                                                isSelected(item.id)
                                                    ? 'border-sky-600 bg-sky-600 text-white'
                                                    : 'border-white/90 bg-white/90 text-transparent group-hover:border-sky-400 dark:border-zinc-700 dark:bg-zinc-900/90'
                                            "
                                        >
                                            <Check v-if="isSelected(item.id)" class="h-4 w-4" />
                                        </div>
                                        <div
                                            class="absolute bottom-1.5 left-1.5 right-1.5 flex flex-wrap gap-1 opacity-0 transition group-hover:opacity-100"
                                        >
                                            <button
                                                type="button"
                                                class="rounded-md bg-white/95 px-1.5 py-1 text-[10px] font-medium text-zinc-600 shadow-sm hover:text-sky-700 dark:bg-zinc-900/95"
                                                title="Mover para pasta"
                                                @click.stop="startMove(item, $event)"
                                            >
                                                Mover
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md bg-white/95 p-1.5 text-zinc-400 shadow-sm hover:bg-red-50 hover:text-red-600 disabled:opacity-40 dark:bg-zinc-900/95"
                                                :disabled="item.usage_count > 0"
                                                :title="item.usage_count > 0 ? 'Em uso' : 'Remover'"
                                                @click.stop="removeItem(item)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex min-h-0 flex-1 flex-col gap-1 border-t border-zinc-100 p-2.5 dark:border-zinc-700">
                                        <p
                                            class="line-clamp-2 text-left text-xs font-semibold leading-snug text-zinc-800 dark:text-zinc-100"
                                            :title="item.name"
                                        >
                                            {{ item.name }}
                                        </p>
                                        <p class="text-left text-[10px] leading-tight text-zinc-500">
                                            {{ item.human_size }}
                                            <span v-if="item.created_at_formatted"> · {{ item.created_at_formatted }}</span>
                                        </p>
                                        <p
                                            v-if="item.usage_count > 0"
                                            class="text-left text-[10px] font-medium text-sky-600 dark:text-sky-400"
                                        >
                                            {{ item.usage_count }} aula(s)
                                        </p>
                                        <div
                                            v-if="movingItemId === item.id"
                                            class="mt-2 space-y-1.5 border-t border-zinc-100 pt-2 dark:border-zinc-700"
                                            @click.stop
                                        >
                                            <label class="block text-[10px] font-medium text-zinc-500">Mover para</label>
                                            <select
                                                v-model="moveTargetFolderId"
                                                class="w-full rounded border border-zinc-200 px-1.5 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-800"
                                            >
                                                <option value="">Raiz (sem pasta)</option>
                                                <option v-for="f in allFolders" :key="f.id" :value="String(f.id)">
                                                    {{ f.path_label || f.name }}
                                                </option>
                                            </select>
                                            <div class="flex gap-1">
                                                <Button type="button" size="sm" class="flex-1 text-xs" @click="confirmMove(item)">
                                                    OK
                                                </Button>
                                                <Button type="button" size="sm" variant="outline" class="text-xs" @click="cancelMove">
                                                    Cancelar
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </div>

                        <div
                            v-if="meta.last_page > 1"
                            class="flex shrink-0 items-center justify-between border-t border-zinc-200 px-3 py-2 text-xs dark:border-zinc-700"
                        >
                            <span class="text-zinc-500">{{ meta.total }} arquivo(s)</span>
                            <div class="flex gap-1">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    :disabled="meta.current_page <= 1 || loading"
                                    @click="fetchItems(meta.current_page - 1)"
                                >
                                    Anterior
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    :disabled="meta.current_page >= meta.last_page || loading"
                                    @click="fetchItems(meta.current_page + 1)"
                                >
                                    Próxima
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="flex min-h-0 flex-1 flex-col items-center justify-center gap-4 p-8 sm:p-12">
                    <p v-if="currentFolder" class="text-center text-sm text-zinc-600 dark:text-zinc-400">
                        Os arquivos serão salvos em: <strong>{{ currentFolder.name }}</strong>
                    </p>
                    <p v-else class="text-center text-sm text-zinc-500">Salvar na raiz da biblioteca</p>
                    <input
                        ref="uploadInput"
                        type="file"
                        :accept="UPLOAD_ACCEPT"
                        multiple
                        class="hidden"
                        @change="onUploadInputChange"
                    />
                    <div
                        class="flex w-full max-w-xl flex-col items-center rounded-xl border-2 border-dashed border-zinc-300 px-8 py-14 dark:border-zinc-600"
                    >
                        <Upload class="mb-3 h-10 w-10 text-zinc-400" />
                        <p class="mb-1 text-center text-sm font-medium text-zinc-700 dark:text-zinc-200">
                            Imagens, PDFs, documentos ou ZIP
                        </p>
                        <p class="mb-4 text-center text-xs text-zinc-500">
                            Limites variam por tipo (PDF até {{ pdfMaxMb }} MB)
                        </p>
                        <Button type="button" :disabled="uploading" @click="uploadInput?.click()">
                            <Loader2 v-if="uploading" class="mr-2 h-4 w-4 animate-spin" />
                            {{ uploading ? 'Enviando…' : 'Selecionar arquivos' }}
                        </Button>
                    </div>
                    <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>
                </div>

                <footer
                    v-if="!embedded && mode === 'pick'"
                    class="flex shrink-0 items-center justify-end gap-2 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700"
                >
                    <Button type="button" variant="outline" @click="closeModal">Cancelar</Button>
                    <Button type="button" :disabled="!canConfirmPick" @click="confirmPick">
                        {{ maxPick === 1 ? 'Usar selecionado' : `Usar selecionados (${selectedCount})` }}
                    </Button>
                </footer>
                <footer
                    v-else-if="!embedded"
                    class="flex shrink-0 justify-end border-t border-zinc-200 px-4 py-3 dark:border-zinc-700"
                >
                    <Button type="button" variant="outline" @click="closeModal">Fechar</Button>
                </footer>
            </div>
        </div>
    </Teleport>
</template>

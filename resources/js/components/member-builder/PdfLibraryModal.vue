<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import Button from '@/components/ui/Button.vue';
import {
    X,
    Search,
    Upload,
    Trash2,
    FileText,
    Check,
    Loader2,
    FolderOpen,
    Pencil,
    FolderInput,
    Plus,
} from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
    mode: { type: String, default: 'pick' },
    indexUrl: { type: String, required: true },
    uploadUrl: { type: String, required: true },
    foldersUrl: { type: String, required: true },
    deleteUrlBase: { type: String, required: true },
    pdfMaxMb: { type: Number, default: 50 },
    csrfToken: { type: String, default: '' },
    currentProductId: { type: String, default: '' },
    tenantProducts: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'select']);

const activePanel = ref('library');
const items = ref([]);
const folders = ref([]);
const currentFolder = ref(null);
const currentFolderId = ref(null);
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

function selectFolder(folderId) {
    if (folderId == null) {
        goToRoot();
        return;
    }
    const folder = folders.value.find((f) => f.id === folderId);
    if (folder) {
        openFolder(folder);
    }
}

function isFolderActive(folderId) {
    return currentFolderId.value === folderId;
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
            },
        });
        items.value = Array.isArray(data?.items) ? data.items : [];
        folders.value = Array.isArray(data?.folders) ? data.folders : [];
        currentFolder.value = data?.current_folder ?? null;
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

function openFolder(folder) {
    currentFolderId.value = folder.id;
    currentFolder.value = folder;
    searchQuery.value = '';
    fetchItems(1);
}

function goToRoot() {
    currentFolderId.value = null;
    currentFolder.value = null;
    fetchItems(1);
}

function toggleSelect(id) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selectedIds.value = next;
}

function onCardClick(item) {
    if (props.mode === 'pick') {
        toggleSelect(item.id);
    }
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
            if (file.type !== 'application/pdf') {
                alert('Selecione apenas arquivos PDF.');
                continue;
            }
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
            `Erro ao enviar PDF. Tamanho máx. ${props.pdfMaxMb} MB.`;
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
        alert('Este PDF está em uso em uma ou mais aulas. Remova-o das aulas antes de excluir.');
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
    const name = window.prompt('Nome da nova pasta:');
    if (!name?.trim()) return;
    try {
        await axios.post(
            props.foldersUrl,
            { name: name.trim() },
            { headers: { ...headers(), 'Content-Type': 'application/json' } }
        );
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
        alert('A pasta não está vazia. Mova ou remova os PDFs antes de excluir.');
        return;
    }
    if (!confirm(`Excluir a pasta "${folder.name}"?`)) return;
    try {
        await axios.delete(folderUpdateUrl(folder.id), { headers: headers() });
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
        alert(e.response?.data?.message ?? 'Não foi possível mover o PDF.');
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
            activePanel.value = 'library';
            currentFolderId.value = null;
            currentFolder.value = null;
            movingItemId.value = null;
            fetchItems(1);
        }
    }
);

watch(filterProductId, () => {
    if (props.open) fetchItems(1);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[200] flex items-center justify-center p-3 sm:p-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="pdf-library-title"
        >
            <div class="absolute inset-0 bg-black/50" @click="closeModal" />
            <div
                class="relative flex h-[min(92vh,900px)] w-full max-w-6xl flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900"
            >
                <header class="flex shrink-0 items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <div class="flex items-center gap-2">
                        <FolderOpen class="h-5 w-5 text-sky-600" />
                        <h2 id="pdf-library-title" class="text-lg font-bold text-zinc-900 dark:text-white">
                            Biblioteca de PDFs
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
                        class="px-3 py-2.5 text-sm font-semibold transition"
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
                    <!-- Sidebar / abas de pastas -->
                    <aside
                        class="flex shrink-0 flex-col border-b border-zinc-200 bg-zinc-50/80 dark:border-zinc-700 dark:bg-zinc-800/40 md:w-52 md:border-b-0 md:border-r"
                    >
                        <div class="hidden px-2 pt-2 md:block">
                            <p class="px-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-zinc-400">Pastas</p>
                        </div>
                        <div
                            class="flex gap-1 overflow-x-auto px-2 py-2 md:flex-col md:overflow-x-visible md:overflow-y-auto md:px-1.5 md:py-1"
                        >
                            <button
                                type="button"
                                class="flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition md:w-full"
                                :class="
                                    isAtRoot
                                        ? 'bg-sky-600 text-white shadow-sm'
                                        : 'bg-white text-zinc-700 hover:bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700'
                                "
                                @click="goToRoot"
                            >
                                <FolderOpen class="h-3.5 w-3.5 shrink-0" />
                                <span class="truncate">Raiz</span>
                            </button>
                            <button
                                v-for="folder in folders"
                                :key="folder.id"
                                type="button"
                                class="group flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition md:w-full"
                                :class="
                                    isFolderActive(folder.id)
                                        ? 'bg-sky-600 text-white shadow-sm'
                                        : 'bg-white text-zinc-700 hover:bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700'
                                "
                                :title="folder.name"
                                @click="selectFolder(folder.id)"
                            >
                                <span class="min-w-0 flex-1 truncate">{{ folder.name }}</span>
                                <span
                                    class="shrink-0 rounded px-1 py-0.5 text-[10px] tabular-nums"
                                    :class="
                                        isFolderActive(folder.id)
                                            ? 'bg-white/20 text-white'
                                            : 'bg-zinc-200 text-zinc-600 dark:bg-zinc-600 dark:text-zinc-300'
                                    "
                                >
                                    {{ folder.items_count }}
                                </span>
                                <span
                                    class="hidden shrink-0 items-center gap-0.5 md:group-hover:flex"
                                    :class="isFolderActive(folder.id) ? '!flex' : ''"
                                >
                                    <span
                                        role="button"
                                        tabindex="0"
                                        class="rounded p-0.5 hover:bg-black/10"
                                        title="Renomear"
                                        @click.stop="renameFolder(folder, $event)"
                                        @keydown.enter.stop.prevent="renameFolder(folder, $event)"
                                    >
                                        <Pencil class="h-3 w-3" />
                                    </span>
                                    <span
                                        role="button"
                                        tabindex="0"
                                        class="rounded p-0.5 hover:bg-black/10 disabled:opacity-30"
                                        :class="{ 'pointer-events-none': folder.items_count > 0 }"
                                        title="Excluir"
                                        @click.stop="deleteFolder(folder, $event)"
                                        @keydown.enter.stop.prevent="deleteFolder(folder, $event)"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </span>
                                </span>
                            </button>
                        </div>
                        <div class="hidden border-t border-zinc-200 p-2 dark:border-zinc-700 md:block">
                            <button
                                type="button"
                                class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed border-zinc-300 px-2 py-1.5 text-xs font-medium text-zinc-600 transition hover:border-sky-400 hover:text-sky-700 dark:border-zinc-600 dark:text-zinc-400"
                                @click="createFolder"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                Nova pasta
                            </button>
                        </div>
                        <button
                            type="button"
                            class="mx-2 mb-2 flex items-center justify-center gap-1 rounded-lg border border-dashed border-zinc-300 px-2 py-1.5 text-xs font-medium text-zinc-600 md:hidden"
                            @click="createFolder"
                        >
                            <FolderInput class="h-3.5 w-3.5" />
                            Nova pasta
                        </button>
                    </aside>

                    <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                        <div class="flex shrink-0 flex-wrap gap-2 border-b border-zinc-100 px-3 py-2 dark:border-zinc-800">
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
                                v-else-if="!items.length"
                                class="flex min-h-[240px] items-center justify-center text-center text-sm text-zinc-500"
                            >
                                <span v-if="isAtRoot">Nenhum PDF na raiz. Envie arquivos ou selecione uma pasta.</span>
                                <span v-else>Esta pasta está vazia.</span>
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
                                    class="relative flex aspect-[4/3] items-center justify-center bg-gradient-to-br from-red-50 to-zinc-100 dark:from-red-950/30 dark:to-zinc-800"
                                >
                                    <FileText class="h-12 w-12 text-red-500/80" stroke-width="1.25" />
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
                                    <div class="absolute bottom-1.5 left-1.5 right-1.5 flex flex-wrap gap-1 opacity-0 transition group-hover:opacity-100">
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
                                            <option v-for="f in folders" :key="f.id" :value="String(f.id)">{{ f.name }}</option>
                                        </select>
                                        <div class="flex gap-1">
                                            <Button type="button" size="sm" class="flex-1 text-xs" @click="confirmMove(item)">OK</Button>
                                            <Button type="button" size="sm" variant="outline" class="text-xs" @click="cancelMove">Cancelar</Button>
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
                        Os PDFs serão salvos em: <strong>{{ currentFolder.name }}</strong>
                    </p>
                    <input
                        ref="uploadInput"
                        type="file"
                        accept=".pdf,application/pdf"
                        multiple
                        class="hidden"
                        @change="onUploadInputChange"
                    />
                    <div
                        class="flex w-full max-w-xl flex-col items-center rounded-xl border-2 border-dashed border-zinc-300 px-8 py-14 dark:border-zinc-600"
                    >
                        <Upload class="mb-3 h-10 w-10 text-zinc-400" />
                        <p class="mb-1 text-center text-sm font-medium text-zinc-700 dark:text-zinc-200">
                            Arraste PDFs ou clique para enviar
                        </p>
                        <p class="mb-4 text-center text-xs text-zinc-500">Máx. {{ pdfMaxMb }} MB por arquivo</p>
                        <Button type="button" :disabled="uploading" @click="uploadInput?.click()">
                            <Loader2 v-if="uploading" class="mr-2 h-4 w-4 animate-spin" />
                            {{ uploading ? 'Enviando…' : 'Selecionar arquivos' }}
                        </Button>
                    </div>
                    <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>
                </div>

                <footer
                    v-if="mode === 'pick'"
                    class="flex shrink-0 items-center justify-end gap-2 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700"
                >
                    <Button type="button" variant="outline" @click="closeModal">Cancelar</Button>
                    <Button type="button" :disabled="!canConfirmPick" @click="confirmPick">
                        Usar selecionados ({{ selectedCount }})
                    </Button>
                </footer>
                <footer
                    v-else
                    class="flex shrink-0 justify-end border-t border-zinc-200 px-4 py-3 dark:border-zinc-700"
                >
                    <Button type="button" variant="outline" @click="closeModal">Fechar</Button>
                </footer>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import MediaLibraryModal from '@/components/member-builder/MediaLibraryModal.vue';
import Button from '@/components/ui/Button.vue';
import { Upload, FolderPlus } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    tenant_products: { type: Array, default: () => [] },
    upload_limits: {
        type: Object,
        default: () => ({ pdf_max_mb: 50, image_max_mb: 10 }),
    },
});

const libraryRef = ref(null);

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function openUpload() {
    libraryRef.value?.openUploadPanel?.();
}

function openNewFolder() {
    libraryRef.value?.openLibraryPanel?.();
    const name = window.prompt('Nome da nova pasta na raiz:');
    if (!name?.trim()) return;
    window.axios
        .post(
            '/biblioteca/folders',
            { name: name.trim() },
            {
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
            }
        )
        .then(() => libraryRef.value?.refresh?.())
        .catch((e) => {
            alert(e.response?.data?.message ?? e.response?.data?.errors?.name?.[0] ?? 'Não foi possível criar a pasta.');
        });
}
</script>

<template>
    <div class="flex min-h-[min(72vh,900px)] flex-col gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="max-w-xl text-sm text-zinc-600 dark:text-zinc-400">
                    Todos os arquivos enviados na plataforma (imagens, PDFs, documentos). Organize em pastas e
                    subpastas.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button type="button" variant="outline" size="sm" @click="openNewFolder">
                    <FolderPlus class="mr-1.5 h-4 w-4" />
                    Nova pasta
                </Button>
                <Button type="button" size="sm" @click="openUpload">
                    <Upload class="mr-1.5 h-4 w-4" />
                    Enviar arquivos
                </Button>
            </div>
        </div>

        <div class="min-h-0 flex-1">
        <MediaLibraryModal
            ref="libraryRef"
            :embedded="true"
            :open="true"
            mode="manage"
            index-url="/biblioteca/items"
            upload-url="/biblioteca/items"
            folders-url="/biblioteca/folders"
            delete-url-base="/biblioteca/items"
            :pdf-max-mb="upload_limits.pdf_max_mb ?? 50"
            :csrf-token="csrfToken()"
            :tenant-products="tenant_products"
        />
        </div>
    </div>
</template>

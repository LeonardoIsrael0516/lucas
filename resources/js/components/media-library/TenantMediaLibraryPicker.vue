<script setup>
import MediaLibraryModal from '@/components/member-builder/MediaLibraryModal.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    maxPick: { type: Number, default: 1 },
    lockedMediaType: { type: String, default: 'image' },
    pdfMaxMb: { type: Number, default: 50 },
});

const emit = defineEmits(['close', 'select']);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
</script>

<template>
    <MediaLibraryModal
        :open="open"
        mode="pick"
        :max-pick="maxPick"
        :locked-media-type="lockedMediaType"
        index-url="/biblioteca/items"
        upload-url="/biblioteca/items"
        folders-url="/biblioteca/folders"
        delete-url-base="/biblioteca/items"
        :pdf-max-mb="pdfMaxMb"
        :csrf-token="csrfToken()"
        :tenant-products="[]"
        @close="emit('close')"
        @select="emit('select', $event)"
    />
</template>

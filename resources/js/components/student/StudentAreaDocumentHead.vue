<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { pickFaviconUrl } from '@/composables/resolveAssetUrl';

const props = defineProps({
    title: { type: String, default: '' },
    student_branding: { type: Object, default: () => ({}) },
});

const page = usePage();
const appSettings = computed(() => page.props.appSettings ?? {});

const faviconHref = computed(() => pickFaviconUrl(
    props.student_branding?.favicon_url,
    appSettings.value?.favicon_url,
    appSettings.value?.pwa_icon_192,
    appSettings.value?.app_logo_icon_dark,
    appSettings.value?.app_logo_icon,
));
</script>

<template>
    <Head>
        <title v-if="title">{{ title }}</title>
        <link v-if="faviconHref" rel="icon" :href="faviconHref" type="image/png" />
        <link v-if="faviconHref" rel="apple-touch-icon" :href="faviconHref" />
    </Head>
</template>

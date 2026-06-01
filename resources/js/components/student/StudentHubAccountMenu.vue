<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, LogOut, UserRound } from 'lucide-vue-next';

const props = defineProps({
    user: { type: Object, default: null },
    profileHref: { type: String, default: '/meu-perfil' },
    showName: { type: Boolean, default: true },
});

const page = usePage();
const menuOpen = ref(false);
const menuRef = ref(null);

function initialsFromName(name) {
    const parts = String(name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return (parts[0]?.[0] || '?').toUpperCase();
}

const displayUser = computed(() => {
    const auth = page.props.auth?.user;
    const u = props.user;
    if (!u && !auth) {
        return null;
    }
    const name = u?.name ?? auth?.name ?? '';

    return {
        name,
        initials: u?.initials ?? initialsFromName(name),
        avatar_url: u?.avatar_url ?? auth?.avatar_url ?? null,
    };
});

function handleClickOutside(event) {
    if (menuRef.value && !menuRef.value.contains(event.target)) {
        menuOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
    <div v-if="displayUser" ref="menuRef" class="relative">
        <button
            type="button"
            class="flex items-center gap-2 rounded-lg px-1 py-1 transition hover:bg-zinc-100"
            :aria-expanded="menuOpen"
            @click.stop="menuOpen = !menuOpen"
        >
            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs font-semibold text-white"
                :style="{ backgroundColor: 'var(--student-primary, #0ea5e9)' }"
            >
                <img
                    v-if="displayUser.avatar_url"
                    :src="displayUser.avatar_url"
                    :alt="displayUser.name"
                    class="h-full w-full object-cover"
                />
                <span v-else>{{ displayUser.initials }}</span>
            </span>
            <span
                v-if="showName"
                class="hidden max-w-[10rem] truncate text-sm font-medium text-zinc-800 sm:inline"
            >
                {{ displayUser.name }}
            </span>
            <ChevronDown class="hidden h-4 w-4 text-zinc-500 sm:block" />
        </button>
        <div
            v-show="menuOpen"
            class="absolute right-0 z-50 mt-2 w-52 rounded-xl border border-zinc-200 bg-white py-1 shadow-lg"
        >
            <Link
                :href="profileHref"
                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-zinc-700 hover:bg-zinc-50"
                @click="menuOpen = false"
            >
                <UserRound class="h-4 w-4 shrink-0" />
                Meu perfil
            </Link>
            <Link
                href="/logout"
                method="post"
                as="button"
                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-zinc-700 hover:bg-zinc-50"
                @click="menuOpen = false"
            >
                <LogOut class="h-4 w-4 shrink-0" />
                Sair
            </Link>
        </div>
    </div>
</template>

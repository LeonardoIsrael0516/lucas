<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import ThemeToggler from '@/components/layout/ThemeToggler.vue';
import { Bell, LayoutGrid, LifeBuoy, LogOut, MessageCircle, Plus, UserRound, X } from 'lucide-vue-next';

const page = usePage();
const props = defineProps({
    auth_user: { type: Object, default: () => ({}) },
    tickets: { type: Array, default: () => [] },
    notifications_unread_count: { type: Number, default: 0 },
    community_href: { type: String, default: null },
    profile_href: { type: String, default: '/meu-perfil' },
    suporte_href: { type: String, default: '/suporte' },
    student_branding: { type: Object, default: () => ({ primary: '#0ea5e9', logo_url: null }) },
    support_whatsapp: { type: Object, default: () => ({ enabled: false, url: '' }) },
});

const hasUnread = computed(() => (props.notifications_unread_count || 0) > 0);
const flashSuccess = computed(() => page.props.flash?.success ?? page.props.flash?.message ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const modalOpen = ref(false);
const createForm = useForm({
    subject: '',
    message: '',
});

function openModal() {
    createForm.reset();
    createForm.clearErrors();
    modalOpen.value = true;
}

function submitTicket() {
    createForm.post('/suporte', {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false;
        },
    });
}

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return '—';
    }
}
</script>

<template>
    <div
        class="flex min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100"
        :style="{ '--student-primary': student_branding?.primary || '#0ea5e9' }"
    >
        <Head title="Suporte" />

        <aside class="hidden w-56 shrink-0 flex-col border-r border-zinc-200 bg-white py-6 dark:border-zinc-800 dark:bg-zinc-900 md:flex">
            <div class="mb-8 px-4">
                <Link href="/area-membros" class="flex items-center gap-2">
                    <img v-if="student_branding?.logo_url" :src="student_branding.logo_url" alt="Logo" class="h-7 w-auto max-w-[160px] object-contain" />
                    <div v-else class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-white">Minha Plataforma</div>
                </Link>
            </div>
            <nav class="flex flex-1 flex-col gap-1 px-2">
                <Link href="/area-membros" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                    <LayoutGrid class="h-5 w-5 shrink-0" />
                    Meus cursos
                </Link>
                <Link v-if="profile_href" :href="profile_href" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                    <UserRound class="h-5 w-5 shrink-0" />
                    Meu perfil
                </Link>
                <a v-if="community_href" :href="community_href" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                    <MessageCircle class="h-5 w-5 shrink-0" />
                    Comunidade
                </a>
                <span v-else class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-zinc-400 opacity-60 dark:text-zinc-500">
                    <MessageCircle class="h-5 w-5 shrink-0" />
                    Comunidade
                </span>
                <span
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium"
                    :style="{ backgroundColor: 'color-mix(in srgb, var(--student-primary) 18%, transparent)', color: 'var(--student-primary)' }"
                >
                    <LifeBuoy class="h-5 w-5 shrink-0" />
                    Suporte
                </span>
            </nav>
            <div class="mt-auto border-t border-zinc-200 px-2 pt-4 dark:border-zinc-800">
                <Link href="/logout" method="post" as="button" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                    <LogOut class="h-5 w-5 shrink-0" />
                    Sair
                </Link>
            </div>
        </aside>

        <div class="relative flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
                <div class="flex h-14 items-center justify-between gap-3 px-4 md:px-6">
                    <h1 class="text-lg font-semibold text-zinc-900 dark:text-white">Suporte</h1>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="relative inline-flex rounded-lg p-2 text-zinc-600 dark:text-zinc-400" title="Notificações">
                            <Bell class="h-5 w-5" />
                            <span v-if="hasUnread" class="absolute right-1 top-1 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-zinc-900" />
                        </span>
                        <ThemeToggler />
                        <div class="hidden items-center gap-2 sm:flex">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-semibold text-white" :style="{ backgroundColor: 'var(--student-primary)' }">
                                {{ auth_user.initials }}
                            </div>
                            <span class="max-w-[10rem] truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ auth_user.name }}</span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-8 md:px-8">
                <div v-if="flashSuccess" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                    {{ flashSuccess }}
                </div>
                <div v-if="flashError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                    {{ flashError }}
                </div>

                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Abra um chamado para falar com o suporte do seu curso.</p>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white shadow-sm"
                        :style="{ backgroundColor: 'var(--student-primary)' }"
                        @click="openModal"
                    >
                        <Plus class="h-4 w-4" />
                        Novo ticket
                    </button>
                </div>

                <div v-if="tickets.length" class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        <li v-for="t in tickets" :key="t.id">
                            <Link :href="`/suporte/${t.id}`" class="flex flex-col gap-1 px-4 py-4 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/80 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-medium text-zinc-900 dark:text-white">{{ t.subject }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ fmtDate(t.updated_at) }}</p>
                                </div>
                                <span
                                    class="inline-flex w-fit shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="
                                        t.status === 'open'
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                                            : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-400'
                                    "
                                >
                                    {{ t.status === 'open' ? 'Aberto' : 'Encerrado' }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </div>
                <p v-else class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">
                    Nenhum ticket ainda. Clique em <strong>Novo ticket</strong> para começar.
                </p>
            </main>

            <a
                v-if="support_whatsapp?.enabled && support_whatsapp?.url"
                :href="support_whatsapp.url"
                target="_blank"
                rel="noopener noreferrer"
                class="fixed bottom-6 right-6 z-30 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg transition hover:scale-105 hover:shadow-xl"
                title="WhatsApp"
                aria-label="Abrir WhatsApp"
            >
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path
                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"
                    />
                </svg>
            </a>

            <div
                v-if="modalOpen"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                @click.self="modalOpen = false"
            >
                <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Novo ticket</h2>
                        <button type="button" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="Fechar" @click="modalOpen = false">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <form class="space-y-4" @submit.prevent="submitTicket">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Assunto</label>
                            <input
                                v-model="createForm.subject"
                                type="text"
                                required
                                maxlength="255"
                                class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800"
                            />
                            <p v-if="createForm.errors.subject" class="mt-1 text-sm text-red-600">{{ createForm.errors.subject }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Mensagem</label>
                            <textarea
                                v-model="createForm.message"
                                required
                                rows="5"
                                class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800"
                            />
                            <p v-if="createForm.errors.message" class="mt-1 text-sm text-red-600">{{ createForm.errors.message }}</p>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" @click="modalOpen = false">
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                                :style="{ backgroundColor: 'var(--student-primary)' }"
                            >
                                Enviar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

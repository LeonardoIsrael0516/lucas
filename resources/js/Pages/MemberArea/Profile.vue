<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import ThemeToggler from '@/components/layout/ThemeToggler.vue';
import { Camera, Lock, Loader2, LayoutGrid, MessageCircle, LifeBuoy, LogOut, UserRound, Bell } from 'lucide-vue-next';

const props = defineProps({
    user: { type: Object, required: true },
    community_href: { type: String, default: null },
    notifications_unread_count: { type: Number, default: 0 },
    student_branding: { type: Object, default: () => ({ primary: '#0ea5e9', logo_url: null }) },
    suporte_href: { type: String, default: null },
    support_whatsapp: { type: Object, default: () => ({ enabled: false, url: '' }) },
});

const avatarInputRef = ref(null);
const avatarPreview = ref(null);

const profileForm = useForm({
    name: props.user.name,
    email: props.user.email,
    username: props.user.username ?? '',
    avatar: null,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const avatarUrl = computed(() => {
    if (avatarPreview.value) return avatarPreview.value;
    return props.user.avatar_url || null;
});

function triggerAvatarClick() {
    avatarInputRef.value?.click();
}

function onAvatarChange(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    profileForm.avatar = file;
    const reader = new FileReader();
    reader.onload = (e) => {
        avatarPreview.value = e.target?.result;
    };
    reader.readAsDataURL(file);
}

function submitProfile() {
    profileForm.post('/meu-perfil', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            profileForm.avatar = null;
            avatarPreview.value = null;
        },
    });
}

function submitPassword() {
    passwordForm.put('/meu-perfil/senha', { preserveScroll: true });
}
</script>

<template>
    <div
        class="flex min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100"
        :style="{ '--student-primary': student_branding?.primary || '#0ea5e9' }"
    >
        <Head title="Meu perfil" />

        <aside class="hidden w-56 shrink-0 flex-col border-r border-zinc-200 bg-white py-6 dark:border-zinc-800 dark:bg-zinc-900 md:flex">
            <div class="mb-8 px-4">
                <div class="flex items-center gap-2">
                    <img v-if="student_branding?.logo_url" :src="student_branding.logo_url" alt="Logo" class="h-7 w-auto max-w-[160px] object-contain" />
                    <div v-else class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-white">Minha Plataforma</div>
                </div>
            </div>
            <nav class="flex flex-1 flex-col gap-1 px-2">
                <Link
                    href="/area-membros"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium"
                    :style="{ backgroundColor: 'color-mix(in srgb, var(--student-primary) 18%, transparent)', color: 'var(--student-primary)' }"
                >
                    <LayoutGrid class="h-5 w-5 shrink-0" />
                    Meus cursos
                </Link>
                <span class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                    <UserRound class="h-5 w-5 shrink-0" />
                    Meu perfil
                </span>
                <a
                    v-if="community_href"
                    :href="community_href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                >
                    <MessageCircle class="h-5 w-5 shrink-0" />
                    Comunidade
                </a>
                <span
                    v-else
                    class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-zinc-400 opacity-60 dark:text-zinc-500"
                    title="Entre em um curso com comunidade ativa"
                >
                    <MessageCircle class="h-5 w-5 shrink-0" />
                    Comunidade
                </span>
                <Link
                    v-if="suporte_href"
                    :href="suporte_href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                >
                    <LifeBuoy class="h-5 w-5 shrink-0" />
                    Suporte
                </Link>
                <span v-else class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-zinc-400 dark:text-zinc-500" title="Suporte não disponível">
                    <LifeBuoy class="h-5 w-5 shrink-0" />
                    Suporte
                </span>
            </nav>
            <div class="mt-auto border-t border-zinc-200 px-2 pt-4 dark:border-zinc-800">
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                >
                    <LogOut class="h-5 w-5 shrink-0" />
                    Sair
                </Link>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
                <div class="flex h-14 items-center justify-between gap-3 px-4 md:px-6">
                    <h1 class="text-base font-semibold text-zinc-900 dark:text-white">Meu perfil</h1>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="relative inline-flex rounded-lg p-2 text-zinc-600 dark:text-zinc-400" title="Notificações" role="status">
                            <Bell class="h-5 w-5" />
                            <span
                                v-if="(notifications_unread_count || 0) > 0"
                                class="absolute right-1 top-1 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-zinc-900"
                            />
                        </span>
                        <ThemeToggler />
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-8 md:px-8">
                <div class="grid gap-6 lg:grid-cols-2">
            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-5 dark:border-zinc-800 dark:bg-zinc-900/40">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Dados</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Atualize seu nome e foto.</p>
                </div>
                <div class="space-y-6 p-6">
                    <div class="flex items-center gap-4">
                        <div class="relative h-16 w-16 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                            <img v-if="avatarUrl" :src="avatarUrl" alt="Avatar" class="h-full w-full object-cover" />
                        </div>
                        <div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"
                                @click="triggerAvatarClick"
                            >
                                <Camera class="h-4 w-4" />
                                Trocar foto
                            </button>
                            <input ref="avatarInputRef" type="file" class="hidden" accept="image/*" @change="onAvatarChange" />
                            <p v-if="profileForm.errors.avatar" class="mt-2 text-sm text-red-600">{{ profileForm.errors.avatar }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Nome</label>
                        <input v-model="profileForm.name" type="text" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        <p v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">E-mail</label>
                        <input v-model="profileForm.email" type="email" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">{{ profileForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Usuário (opcional)</label>
                        <input v-model="profileForm.username" type="text" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        <p v-if="profileForm.errors.username" class="mt-1 text-sm text-red-600">{{ profileForm.errors.username }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button type="button" :disabled="profileForm.processing" @click="submitProfile">
                            <Loader2 v-if="profileForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                            Salvar
                        </Button>
                        <p v-if="profileForm.recentlySuccessful" class="text-sm text-emerald-600">Salvo.</p>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-5 dark:border-zinc-800 dark:bg-zinc-900/40">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Senha</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Altere sua senha de acesso.</p>
                </div>
                <div class="space-y-4 p-6">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Senha atual</label>
                        <input v-model="passwordForm.current_password" type="password" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.current_password }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Nova senha</label>
                        <input v-model="passwordForm.password" type="password" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Confirmar nova senha</label>
                        <input v-model="passwordForm.password_confirmation" type="password" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Button type="button" :disabled="passwordForm.processing" @click="submitPassword">
                            <Lock class="mr-2 h-4 w-4" />
                            Alterar senha
                        </Button>
                        <p v-if="passwordForm.recentlySuccessful" class="text-sm text-emerald-600">Salvo.</p>
                    </div>
                </div>
            </section>
                </div>
            </main>
        </div>

        <a
            v-if="support_whatsapp?.enabled && support_whatsapp?.url"
            :href="support_whatsapp.url"
            target="_blank"
            rel="noopener noreferrer"
            class="fixed bottom-6 right-6 z-30 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg transition hover:scale-105"
            title="WhatsApp"
            aria-label="Abrir WhatsApp"
        >
            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path
                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"
                />
            </svg>
        </a>
    </div>
</template>


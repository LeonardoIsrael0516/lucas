<script setup>
import { ref, computed } from 'vue';
import { useForm, Link, usePage, Head } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';

const showPassword = ref(false);
const page = usePage();
const flashError = computed(() => page.props.flash?.error ?? null);

const lb = computed(() => {
    const b = page.props.login_branding;
    if (b && typeof b === 'object') {
        return b;
    }
    return {
        title: 'Entrar',
        subtitle: 'Entre com seu e-mail e senha',
        background_color: '#18181b',
        primary_color: '#0ea5e9',
        logo_url: null,
        background_image_url: null,
        login_without_password: false,
    };
});

const primary = computed(() => lb.value.primary_color || '#0ea5e9');
const title = computed(() => lb.value.title || 'Área de Membros');
const subtitle = computed(() => {
    if (lb.value.subtitle) {
        return lb.value.subtitle;
    }
    return lb.value.login_without_password ? 'Entre com seu e-mail' : 'Entre com seu e-mail e senha';
});

const backgroundStyle = computed(() => {
    if (lb.value.background_image_url) {
        return { backgroundImage: `url(${lb.value.background_image_url})` };
    }
    return { backgroundColor: lb.value.background_color || '#18181b' };
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    if (lb.value.login_without_password) {
        form.post('/login-without-password', {
            onFinish: () => form.reset('password'),
        });
        return;
    }
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head>
        <title>{{ title }}</title>
        <meta name="theme-color" :content="primary" />
    </Head>
    <div
        class="relative flex min-h-screen flex-col items-center justify-center bg-cover bg-center px-4 py-12 transition-colors"
        :style="{
            '--ma-primary': primary,
            ...backgroundStyle,
        }"
    >
        <div v-if="lb.background_image_url" class="absolute inset-0 bg-black/50" aria-hidden="true" />
        <div
            class="relative z-10 w-full max-w-md rounded-2xl border border-white/10 bg-zinc-900/90 p-8 shadow-2xl backdrop-blur-sm"
        >
            <div class="flex flex-col items-center text-center">
                <img
                    v-if="lb.logo_url"
                    :src="lb.logo_url"
                    :alt="title"
                    class="mb-6 h-12 w-auto max-w-[200px] object-contain object-center"
                />
                <h1 class="text-2xl font-bold tracking-tight text-white">
                    {{ title }}
                </h1>
                <p class="mt-1.5 text-sm text-zinc-400">
                    {{ subtitle }}
                </p>
            </div>
            <p
                v-if="flashError"
                class="mt-4 rounded-xl border border-amber-500/40 bg-amber-500/15 px-4 py-3 text-center text-sm text-amber-100"
            >
                {{ flashError }}
            </p>
            <form class="mt-8 space-y-5" @submit.prevent="submit">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-zinc-300">E-mail</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        class="w-full rounded-xl border border-zinc-600 bg-zinc-800/80 px-4 py-3 text-white placeholder-zinc-500 transition focus:border-[var(--ma-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/30"
                        placeholder="seu@email.com"
                    />
                    <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-400">{{ form.errors.email }}</p>
                </div>
                <div v-if="!lb.login_without_password">
                    <div class="mb-1.5 flex items-center justify-between">
                        <label for="password" class="text-sm font-medium text-zinc-300">Senha</label>
                        <Link
                            href="/esqueci-senha"
                            class="text-sm font-medium transition hover:underline"
                            :style="{ color: primary }"
                        >
                            Esqueci minha senha
                        </Link>
                    </div>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            class="w-full rounded-xl border border-zinc-600 bg-zinc-800/80 py-3 pl-4 pr-12 text-white placeholder-zinc-500 transition focus:border-[var(--ma-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/30"
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-1.5 text-zinc-400 transition hover:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/30"
                            :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                            @click="showPassword = !showPassword"
                        >
                            <Eye v-if="showPassword" class="h-5 w-5" />
                            <EyeOff v-else class="h-5 w-5" />
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="mt-1.5 text-sm text-red-400">{{ form.errors.password }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <input
                        id="remember"
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-zinc-600 bg-zinc-800/80 text-[var(--ma-primary)] focus:ring-[var(--ma-primary)]/50"
                    />
                    <label for="remember" class="text-sm text-zinc-400">Lembrar de mim</label>
                </div>
                <button
                    type="submit"
                    class="w-full rounded-xl px-4 py-3.5 font-semibold text-white shadow-lg transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-zinc-900 disabled:opacity-50"
                    :style="{ backgroundColor: primary }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Entrando…' : 'Entrar' }}
                </button>
            </form>
        </div>
    </div>
</template>

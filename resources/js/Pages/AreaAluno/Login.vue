<script setup>
import { useForm } from '@inertiajs/vue3';
import LayoutAreaAluno from '@/Layouts/LayoutAreaAluno.vue';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';

const props = defineProps({
    login: { type: Object, default: () => ({}) },
});

const form = useForm({
    login_title: props.login.title ?? 'Área de Membros',
    login_subtitle: props.login.subtitle ?? 'Entre com seu e-mail e senha',
    login_background_color: props.login.background_color ?? '#18181b',
    login_primary_color: props.login.primary_color ?? '#0ea5e9',
    login_password_mode: props.login.login_password_mode ?? 'auto',
    login_default_password: props.login.login_default_password ?? '',
    login_without_password: props.login.login_without_password ?? false,
    login_logo: null,
    login_background_image: null,
});

function onLoginLogoChange(e) {
    form.login_logo = e.target.files?.[0] ?? null;
}

function onLoginBackgroundChange(e) {
    form.login_background_image = e.target.files?.[0] ?? null;
}

function submit() {
    form.post('/area-aluno/login', { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <LayoutAreaAluno>
        <form class="space-y-6" @submit.prevent="submit">
            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="border-b border-zinc-200 bg-zinc-50 px-6 py-5 dark:border-zinc-700 dark:bg-zinc-800">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Tela de login da escola</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        Página única em <code class="rounded bg-zinc-100 px-1 py-0.5 font-mono text-xs dark:bg-zinc-900/40">/login</code> para todos os alunos. Após entrar, redireciona para
                        <code class="rounded bg-zinc-100 px-1 py-0.5 font-mono text-xs dark:bg-zinc-900/40">/area-membros</code>.
                    </p>
                </div>
                <div class="space-y-6 p-6">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Título</label>
                        <input v-model="form.login_title" type="text" class="w-full max-w-xl rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        <p v-if="form.errors.login_title" class="mt-1 text-sm text-red-600">{{ form.errors.login_title }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Subtítulo</label>
                        <input v-model="form.login_subtitle" type="text" class="w-full max-w-xl rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        <p v-if="form.errors.login_subtitle" class="mt-1 text-sm text-red-600">{{ form.errors.login_subtitle }}</p>
                    </div>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Cor de fundo (sem imagem)</label>
                            <div class="flex flex-wrap items-center gap-3">
                                <input v-model="form.login_background_color" type="color" class="h-10 w-14 rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-800" />
                                <input v-model="form.login_background_color" type="text" class="h-10 min-w-[140px] flex-1 rounded-lg border border-zinc-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Cor principal (botões e links)</label>
                            <div class="flex flex-wrap items-center gap-3">
                                <input v-model="form.login_primary_color" type="color" class="h-10 w-14 rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-800" />
                                <input v-model="form.login_primary_color" type="text" class="h-10 min-w-[140px] flex-1 rounded-lg border border-zinc-200 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Logo (opcional)</label>
                        <div v-if="login.logo_url" class="mb-3 flex items-center gap-3">
                            <img :src="login.logo_url" alt="Logo login" class="h-10 w-auto rounded bg-white p-2 shadow-sm dark:bg-zinc-900" />
                            <span class="text-xs text-zinc-600 dark:text-zinc-400">Logo atual.</span>
                        </div>
                        <input type="file" accept="image/*" class="block w-full text-sm text-zinc-600 dark:text-zinc-300" @change="onLoginLogoChange" />
                        <p v-if="form.errors.login_logo" class="mt-1 text-sm text-red-600">{{ form.errors.login_logo }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Imagem de fundo (opcional)</label>
                        <div v-if="login.background_image_url" class="mb-3">
                            <img :src="login.background_image_url" alt="Fundo" class="max-h-32 rounded border border-zinc-200 object-cover dark:border-zinc-700" />
                        </div>
                        <input type="file" accept="image/*" class="block w-full text-sm text-zinc-600 dark:text-zinc-300" @change="onLoginBackgroundChange" />
                        <p v-if="form.errors.login_background_image" class="mt-1 text-sm text-red-600">{{ form.errors.login_background_image }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <p class="text-sm font-medium text-zinc-900 dark:text-white">Senha no checkout (novos alunos)</p>
                        <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                            Define como a senha é criada quando alguém compra um curso com área de membros.
                        </p>
                        <div class="mt-4 space-y-3">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input v-model="form.login_password_mode" type="radio" class="mt-1" value="auto" />
                                <span>
                                    <span class="block text-sm font-medium text-zinc-900 dark:text-white">Senha automática</span>
                                    <span class="mt-0.5 block text-xs text-zinc-500">Uma senha aleatória é gerada e enviada por e-mail.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3">
                                <input v-model="form.login_password_mode" type="radio" class="mt-1" value="default" />
                                <span>
                                    <span class="block text-sm font-medium text-zinc-900 dark:text-white">Senha padrão para todos</span>
                                    <span class="mt-0.5 block text-xs text-zinc-500">Todos os novos alunos recebem a mesma senha (menos seguro).</span>
                                </span>
                            </label>
                        </div>
                        <div v-if="form.login_password_mode === 'default'" class="mt-4">
                            <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Senha padrão</label>
                            <input
                                v-model="form.login_default_password"
                                type="text"
                                autocomplete="new-password"
                                class="w-full max-w-md rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800"
                                placeholder="Ex.: Curso2025!"
                            />
                            <p v-if="form.errors.login_default_password" class="mt-1 text-sm text-red-600">{{ form.errors.login_default_password }}</p>
                        </div>
                    </div>
                    <div class="pt-1">
                        <Toggle v-model="form.login_without_password" label="Permitir login só com e-mail" />
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            Na tela de login não será pedida senha (apenas para alunos que já existem no sistema).
                        </p>
                    </div>
                </div>
            </section>

            <Button type="submit" :disabled="form.processing">Salvar</Button>
        </form>
    </LayoutAreaAluno>
</template>

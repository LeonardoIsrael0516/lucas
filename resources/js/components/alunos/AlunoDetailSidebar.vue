<script setup>
import { computed, ref, watch } from 'vue';
import { X, Trash2, Loader2, Search, Link2, Send, Pencil } from 'lucide-vue-next';
import axios from 'axios';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    aluno: { type: Object, default: null },
    produtos: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'updated', 'deleted']);

const loading = ref(false);
const detail = ref(null);
const search = ref('');
const editing = ref(false);
const saving = ref(false);
const deleting = ref(false);
const sending = ref(false);
const toast = ref({ message: null, type: null });

const form = ref({
    name: '',
    email: '',
    phone: '',
    password: '',
    product_ids: [],
});

const initials = computed(() => {
    const name = (detail.value?.name ?? props.aluno?.name ?? '').trim();
    if (!name) return 'A';
    const parts = name.split(/\s+/).filter(Boolean);
    const a = parts[0]?.[0] ?? 'A';
    const b = parts.length > 1 ? parts[parts.length - 1]?.[0] : '';
    return (a + b).toUpperCase();
});

const products = computed(() => detail.value?.products ?? []);
const productsWithAccess = computed(() => products.value.filter((p) => !!p.has_access));
const productsWithoutAccess = computed(() => products.value.filter((p) => !p.has_access));

const filteredProducts = computed(() => {
    const q = (search.value ?? '').trim().toLowerCase();
    if (!q) return products.value;
    return products.value.filter((p) => (p.name ?? '').toLowerCase().includes(q));
});

watch(
    () => [props.open, props.aluno?.id],
    async ([open, id]) => {
        if (!open || !id) return;
        loading.value = true;
        editing.value = false;
        search.value = '';
        try {
            const { data } = await axios.get(`/produtos/alunos/${id}`);
            detail.value = data;
            form.value = {
                name: data?.name ?? '',
                email: data?.email ?? '',
                phone: data?.phone ?? '',
                password: '',
                product_ids: (data?.products ?? []).filter((p) => p.has_access).map((p) => p.id),
            };
        } catch (err) {
            showToast(err.response?.data?.message ?? 'Erro ao carregar detalhes do aluno.', 'error');
            detail.value = null;
        } finally {
            loading.value = false;
        }
    },
    { immediate: true }
);

function close() {
    emit('close');
}

function startEdit() {
    editing.value = true;
}

function cancelEdit() {
    editing.value = false;
    if (!detail.value) return;
    form.value = {
        name: detail.value?.name ?? '',
        email: detail.value?.email ?? '',
        phone: detail.value?.phone ?? '',
        password: '',
        product_ids: (detail.value?.products ?? []).filter((p) => p.has_access).map((p) => p.id),
    };
}

async function save() {
    if (!props.aluno) return;
    saving.value = true;
    try {
        const { data } = await axios.put(`/produtos/alunos/${props.aluno.id}`, {
            name: form.value.name,
            email: form.value.email,
            phone: form.value.phone || null,
            password: form.value.password || undefined,
            product_ids: form.value.product_ids,
        });
        showToast(data.message ?? 'Aluno atualizado.', 'success');
        editing.value = false;
        emit('updated', data.aluno);
        // Refresh details (progress/access badges)
        const refreshed = await axios.get(`/produtos/alunos/${props.aluno.id}`);
        detail.value = refreshed.data;
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Erro ao atualizar. Tente novamente.', 'error');
    } finally {
        saving.value = false;
    }
}

async function deleteAluno() {
    if (!props.aluno) return;
    if (!window.confirm('Tem certeza que deseja excluir este aluno? Esta ação não pode ser desfeita.')) return;
    deleting.value = true;
    try {
        await axios.delete(`/produtos/alunos/${props.aluno.id}`);
        showToast('Aluno excluído com sucesso.', 'success');
        close();
        emit('deleted', props.aluno.id);
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Erro ao excluir.', 'error');
    } finally {
        deleting.value = false;
    }
}

async function sendAccess(productId, channel) {
    if (!props.aluno) return;
    sending.value = true;
    try {
        const { data } = await axios.post(`/produtos/alunos/${props.aluno.id}/send-access`, {
            product_id: productId,
            channel,
        });
        showToast(data.message ?? 'Acesso enviado.', 'success');
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Erro ao enviar acesso.', 'error');
    } finally {
        sending.value = false;
    }
}

async function copyMagicLink(productId) {
    const link = detail.value?.magic_links?.[productId];
    if (!link) {
        showToast('Link mágico indisponível para este curso.', 'error');
        return;
    }
    try {
        await navigator.clipboard.writeText(link);
        showToast('Link mágico copiado.', 'success');
    } catch {
        showToast('Não foi possível copiar o link.', 'error');
    }
}

function showToast(message, type) {
    toast.value = { message, type };
    setTimeout(() => {
        toast.value = { message: null, type: null };
    }, 4000);
}
</script>

<template>
    <Teleport to="body">
        <div
            v-show="open"
            class="fixed inset-0 z-[100000] flex justify-end"
            aria-modal="true"
            role="dialog"
        >
            <div
                class="fixed inset-0 bg-zinc-900/50 dark:bg-zinc-950/60"
                aria-hidden="true"
                @click="close"
            />
            <aside
                class="relative flex h-full w-full max-w-4xl flex-col rounded-l-2xl bg-white shadow-2xl dark:bg-zinc-900"
            >
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-full bg-zinc-100 text-sm font-semibold text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100">
                            {{ initials }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">
                                {{ detail?.name ?? aluno?.name ?? 'Aluno' }}
                            </p>
                            <p class="truncate text-xs text-zinc-600 dark:text-zinc-400">
                                {{ detail?.email ?? aluno?.email ?? '' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" :disabled="!detail" @click="startEdit">
                            <Pencil class="h-4 w-4" />
                            Editar
                        </Button>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                            aria-label="Fechar"
                            @click="close"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <div v-if="!aluno" class="flex flex-1 items-center justify-center p-8">
                    <p class="text-sm text-zinc-500">Nenhum aluno selecionado.</p>
                </div>

                <div v-else class="flex flex-1 flex-col overflow-hidden">
                    <div v-if="loading" class="flex flex-1 items-center justify-center p-8">
                        <Loader2 class="h-5 w-5 animate-spin text-zinc-500" />
                    </div>

                    <div v-else class="grid flex-1 grid-cols-1 gap-0 overflow-hidden lg:grid-cols-[1fr_320px]">
                        <!-- Main -->
                        <div class="flex flex-col overflow-hidden">
                            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="space-y-1.5">
                                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Nome</p>
                                        <input
                                            v-model="form.name"
                                            :disabled="!editing"
                                            type="text"
                                            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 disabled:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:disabled:bg-zinc-800/50"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">E-mail</p>
                                        <input
                                            v-model="form.email"
                                            :disabled="!editing"
                                            type="email"
                                            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 disabled:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:disabled:bg-zinc-800/50"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Telefone</p>
                                        <input
                                            v-model="form.phone"
                                            :disabled="!editing"
                                            type="tel"
                                            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 disabled:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:disabled:bg-zinc-800/50"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Nova senha (opcional)</p>
                                        <input
                                            v-model="form.password"
                                            :disabled="!editing"
                                            type="password"
                                            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 disabled:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:disabled:bg-zinc-800/50"
                                            placeholder="Deixe em branco para manter"
                                        />
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <Button v-if="editing" variant="primary" :disabled="saving" @click="save">
                                        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                                        Salvar
                                    </Button>
                                    <Button v-if="editing" variant="outline" :disabled="saving" @click="cancelEdit">Cancelar</Button>
                                    <Button v-if="!editing" variant="outline" :disabled="deleting" @click="deleteAluno">
                                        <Loader2 v-if="deleting" class="h-4 w-4 animate-spin" />
                                        <Trash2 v-else class="h-4 w-4" />
                                        Apagar aluno
                                    </Button>
                                </div>
                            </div>

                            <div class="flex-1 overflow-y-auto px-5 py-4">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="relative flex-1">
                                        <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-zinc-400" />
                                        <input
                                            v-model="search"
                                            type="text"
                                            class="w-full rounded-lg border border-zinc-200 bg-white pl-9 pr-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                            placeholder="Buscar cursos..."
                                        />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <div
                                        v-for="p in filteredProducts"
                                        :key="p.id"
                                        class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800"
                                    >
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <label class="flex cursor-pointer items-center gap-3">
                                                <Checkbox
                                                    :disabled="!editing"
                                                    :model-value="form.product_ids.includes(p.id)"
                                                    @update:model-value="(v) => { if (!editing) return; if (v) form.product_ids = [...new Set([...form.product_ids, p.id])]; else form.product_ids = form.product_ids.filter(x => x !== p.id); }"
                                                />
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ p.name }}</p>
                                                    <p v-if="p.progress_percent !== null && p.progress_percent !== undefined" class="text-xs text-zinc-600 dark:text-zinc-400">
                                                        Progresso: {{ p.progress_percent }}%
                                                    </p>
                                                </div>
                                            </label>

                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                                    :class="p.has_access ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-200' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'"
                                                >
                                                    {{ p.has_access ? 'Com acesso' : 'Sem acesso' }}
                                                </span>
                                                <button
                                                    v-if="detail?.magic_links?.[p.id]"
                                                    type="button"
                                                    class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                                    title="Copiar link mágico"
                                                    @click="copyMagicLink(p.id)"
                                                >
                                                    <Link2 class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                :disabled="sending"
                                                @click="sendAccess(p.id, 'email')"
                                            >
                                                <Send class="h-4 w-4" />
                                                Enviar acesso (Email)
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                :disabled="sending"
                                                @click="sendAccess(p.id, 'whatsapp')"
                                            >
                                                <Send class="h-4 w-4" />
                                                Enviar acesso (WhatsApp)
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="border-l border-zinc-200 p-5 dark:border-zinc-800">
                            <div class="space-y-4">
                                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Resumo</p>
                                    <div class="mt-3 space-y-2 text-sm text-zinc-900 dark:text-white">
                                        <div class="flex items-center justify-between">
                                            <span>Cursos com acesso</span>
                                            <span class="font-semibold">{{ productsWithAccess.length }}</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span>Outros cursos</span>
                                            <span class="font-semibold">{{ productsWithoutAccess.length }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Informações</p>
                                    <div class="mt-3 space-y-2 text-sm text-zinc-900 dark:text-white">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-zinc-600 dark:text-zinc-400">ID</span>
                                            <span class="font-mono text-xs">{{ detail?.id ?? aluno?.id }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-zinc-600 dark:text-zinc-400">Cadastro</span>
                                            <span class="text-xs">{{ (detail?.created_at ?? '').slice(0, 10) || '-' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-zinc-600 dark:text-zinc-400">Último acesso</span>
                                            <span class="text-xs">{{ (detail?.last_access_at ?? '').slice(0, 19).replace('T', ' ') || '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Toast -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="translate-y-2 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-2 opacity-0"
                    >
                        <div
                            v-if="toast.message"
                            role="alert"
                            :class="[
                                'mx-5 mb-5 rounded-xl border px-4 py-3 text-sm',
                                toast.type === 'error'
                                    ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200',
                            ]"
                        >
                            {{ toast.message }}
                        </div>
                    </Transition>
                </div>
            </aside>
        </div>
    </Teleport>
</template>

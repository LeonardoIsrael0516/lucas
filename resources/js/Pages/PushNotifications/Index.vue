<script setup>
import { ref, computed } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import { Bell, ExternalLink } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    products: { type: Array, default: () => [] },
    total_subscribers: { type: Number, default: 0 },
});

const form = ref({
    product_id: '',
    all_products: false,
    title: '',
    body: '',
});

const sending = ref(false);
const result = ref(null);

const readyProducts = computed(() => props.products.filter((p) => p.ready));
const selectedProduct = computed(() => props.products.find((p) => p.id === form.value.product_id));

async function send() {
    result.value = null;
    if (!form.value.all_products && !form.value.product_id) {
        result.value = { success: false, message: 'Selecione um curso ou marque envio para todos.' };
        return;
    }
    if (!form.value.title.trim() || !form.value.body.trim()) {
        result.value = { success: false, message: 'Preencha título e mensagem.' };
        return;
    }
    sending.value = true;
    try {
        const { data } = await window.axios.post('/notificacoes-push/enviar', {
            product_id: form.value.all_products ? null : form.value.product_id,
            all_products: form.value.all_products,
            title: form.value.title.trim(),
            body: form.value.body.trim(),
        });
        result.value = {
            success: !!data.success,
            message: data.message || 'Envio concluído.',
            sent: data.sent ?? 0,
            products: data.products ?? [],
        };
    } catch (e) {
        const msg = e?.response?.data?.message || e?.message || 'Erro ao enviar.';
        result.value = { success: false, message: msg };
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Notificações push</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Envie notificações para alunos inscritos nas áreas de membros com push ativo.
                Total de inscritos no tenant: <strong>{{ total_subscribers }}</strong>.
            </p>
        </div>

        <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                <Bell class="h-4 w-4 text-violet-500" />
                Nova notificação
            </h2>

            <div class="space-y-4">
                <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <input v-model="form.all_products" type="checkbox" class="rounded border-zinc-300" />
                    Enviar para todos os cursos com push ativo ({{ readyProducts.length }})
                </label>

                <div v-if="!form.all_products">
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Curso</label>
                    <select
                        v-model="form.product_id"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                    >
                        <option value="">Selecione…</option>
                        <option v-for="p in products" :key="p.id" :value="p.id" :disabled="!p.ready">
                            {{ p.name }}
                            <template v-if="p.ready"> ({{ p.subscribers_count }} inscrito(s))</template>
                            <template v-else> — push não configurado</template>
                        </option>
                    </select>
                    <p v-if="selectedProduct && !selectedProduct.ready" class="mt-1 text-xs text-amber-600">
                        Ative push e salve no
                        <a :href="selectedProduct.member_builder_url" class="underline" target="_blank" rel="noopener">Member Builder</a>.
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Título</label>
                    <input
                        v-model="form.title"
                        type="text"
                        maxlength="100"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                        placeholder="Ex: Nova aula disponível"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Mensagem</label>
                    <input
                        v-model="form.body"
                        type="text"
                        maxlength="200"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                        placeholder="Texto curto da notificação"
                    />
                </div>

                <Button type="button" :disabled="sending" @click="send">
                    {{ sending ? 'Enviando…' : 'Enviar notificação' }}
                </Button>

                <p
                    v-if="result"
                    class="text-sm"
                    :class="result.success ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                >
                    {{ result.message }}
                </p>
                <ul v-if="result?.products?.length > 1" class="mt-2 space-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                    <li v-for="row in result.products" :key="row.product_id">
                        {{ row.name }}: {{ row.sent }} enviado(s)
                    </li>
                </ul>
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="mb-3 text-sm font-semibold text-zinc-800 dark:text-zinc-200">Cursos (área de membros)</h2>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-700">
                <li v-for="p in products" :key="p.id" class="flex items-center justify-between gap-3 py-3 text-sm">
                    <div>
                        <p class="font-medium text-zinc-900 dark:text-white">{{ p.name }}</p>
                        <p class="text-xs text-zinc-500">
                            <span v-if="p.ready">{{ p.subscribers_count }} inscrito(s) · push ativo</span>
                            <span v-else class="text-amber-600">Push desativado ou sem chaves VAPID</span>
                        </p>
                    </div>
                    <a
                        :href="p.member_builder_url"
                        class="inline-flex items-center gap-1 text-xs font-medium text-[var(--color-primary)] hover:underline"
                        target="_blank"
                        rel="noopener"
                    >
                        Configurar
                        <ExternalLink class="h-3 w-3" />
                    </a>
                </li>
            </ul>
        </section>
    </div>
</template>

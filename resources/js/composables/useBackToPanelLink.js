import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Link para o painel quando um administrador visualiza a área do aluno.
 */
export function useBackToPanelLink() {
    const page = usePage();
    const showBackToPanel = computed(() => page.props.auth?.user?.role === 'admin');
    const backToPanelHref = '/dashboard';
    const backToPanelLabel = 'Voltar para admin';

    return { showBackToPanel, backToPanelHref, backToPanelLabel };
}

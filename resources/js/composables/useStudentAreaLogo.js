import { ref, computed, onMounted, onUnmounted } from 'vue';

/**
 * Acompanha tema dark/light via classe no <html> (mesmo padrão do ThemeToggler).
 */
export function useStudentAreaTheme() {
    const isDark = ref(
        typeof document !== 'undefined' && document.documentElement.classList.contains('dark'),
    );
    let observer = null;
    onMounted(() => {
        observer = new MutationObserver(() => {
            isDark.value = document.documentElement.classList.contains('dark');
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    });
    onUnmounted(() => {
        observer?.disconnect();
    });
    return { isDark };
}

/**
 * Seleção de logo: variante exata ou fallback para logo_url legado (sem cascata entre variantes).
 *
 * @param {Record<string, unknown>|null|undefined} branding
 * @param {boolean} isDark
 * @param {boolean} collapsed
 * @returns {string|null}
 */
export function resolveStudentAreaLogoUrl(branding, isDark, collapsed) {
    const b = branding || {};
    if (isDark) {
        if (collapsed && b.logo_dark_collapsed_url) {
            return b.logo_dark_collapsed_url;
        }
        if (b.logo_dark_url) {
            return b.logo_dark_url;
        }
    } else {
        if (collapsed && b.logo_light_collapsed_url) {
            return b.logo_light_collapsed_url;
        }
        if (b.logo_light_url) {
            return b.logo_light_url;
        }
    }
    return b.logo_url || null;
}

/**
 * @param {import('vue').Ref<Record<string, unknown>|null|undefined>} brandingRef
 * @param {import('vue').Ref<boolean>} collapsedRef
 */
export function useStudentAreaSidebarLogo(brandingRef, collapsedRef) {
    const { isDark } = useStudentAreaTheme();
    const sidebarLogoUrl = computed(() =>
        resolveStudentAreaLogoUrl(brandingRef.value, isDark.value, collapsedRef.value),
    );
    return { isDark, sidebarLogoUrl };
}

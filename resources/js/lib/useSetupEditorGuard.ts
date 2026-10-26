import { onBeforeUnmount, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { tr } from '@/i18n';

export function useSetupEditorGuard(enabled: () => boolean, dirty: () => boolean) {
    const stop = router.on('before', (event) => {
        const visit = event.detail.visit;
        const destination = visit.url.pathname + visit.url.search;
        if (
            enabled() &&
            dirty() &&
            visit.method === 'get' &&
            destination !== window.location.pathname + window.location.search &&
            !window.confirm(tr('setup_unsaved'))
        )
            event.preventDefault();
    });
    const unload = (event: BeforeUnloadEvent) => {
        if (enabled() && dirty()) {
            event.preventDefault();
            event.returnValue = '';
        }
    };
    onMounted(() => window.addEventListener('beforeunload', unload));
    onBeforeUnmount(() => {
        stop();
        window.removeEventListener('beforeunload', unload);
    });
}

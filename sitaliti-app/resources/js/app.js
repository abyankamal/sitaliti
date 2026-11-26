import '../css/app.css';
import '@fontsource/plus-jakarta-sans/400.css';
import '@fontsource/plus-jakarta-sans/500.css';
import '@fontsource/plus-jakarta-sans/600.css';
import '@fontsource/plus-jakarta-sans/700.css';
import '@fontsource/jetbrains-mono/400.css';
import '@fontsource/jetbrains-mono/500.css';
import '@fontsource/jetbrains-mono/600.css';
import '@fontsource/jetbrains-mono/700.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import GovToastContainer from '@/Components/UI/GovToastContainer.vue';
import GovConfirmDialog from '@/Components/UI/GovConfirmDialog.vue';
import { useToast } from '@/Composables/useToast';
import { useConfirm } from '@/Composables/useConfirm';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const { consumeFlash } = useToast();
const { forceCancel } = useConfirm();

router.on('success', (event) => {
    consumeFlash(event.detail?.page?.props?.flash);
});

router.on('navigate', () => {
    forceCancel();
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        consumeFlash(props?.initialPage?.props?.flash);

        return createApp({
            render: () => [
                h(App, props),
                h(GovToastContainer),
                h(GovConfirmDialog),
            ],
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

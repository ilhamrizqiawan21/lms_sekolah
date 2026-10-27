import 'bootstrap/dist/js/bootstrap.bundle.min.js';
// app.css supplies Bootstrap, base tokens, and legacy component styles; these layers follow in cascade order.
import '../css/ruang-kelas.css';
import '../css/ui-refresh.css';
import '../css/agenda-sekolah.css';
import '../css/calendar.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import { initColorMode } from './theme';

// Resolve the page map once at startup instead of recreating the glob on every navigation.
const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue');

let lastPageUrl = `${window.location.pathname}${window.location.search}`;
let pageTransitionTimer: ReturnType<typeof setTimeout> | undefined;

router.on('navigate', (event) => {
    const nextUrl = new URL(event.detail.page.url, window.location.origin);
    const nextPageUrl = `${nextUrl.pathname}${nextUrl.search}`;
    if (nextPageUrl === lastPageUrl) return;

    lastPageUrl = nextPageUrl;
    const root = document.documentElement;
    root.classList.remove('page-route-entering');
    if (pageTransitionTimer) clearTimeout(pageTransitionTimer);

    requestAnimationFrame(() => {
        root.classList.add('page-route-entering');
        pageTransitionTimer = setTimeout(() => root.classList.remove('page-route-entering'), 240);
    });
});

createInertiaApp({
    title: (title) => title ? `${title} - LMS Sekolah` : 'LMS Sekolah',
    resolve: async (name) => {
        const resolvePage = pages[`./Pages/${name}.vue`];
        if (!resolvePage) {
            throw new Error(`Halaman Inertia tidak ditemukan: ${name}`);
        }

        return (await resolvePage()).default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
        initColorMode();
    },
    progress: {
        delay: 150,
        color: 'var(--app-primary)',
        showSpinner: false,
    },
});

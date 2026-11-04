import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    title: (title) => title ? `${title} - Pollos Misme` : 'Pollos Misme - POS',
    resolve: (name) => {
        const pages = import.meta.glob('./Paginas/**/*.vue', { eager: true });
        const page = pages[`./Paginas/${name}.vue`];
        if (!page) {
            throw new Error(`Inertia page not found: ./Paginas/${name}.vue`);
        }
        return page.default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});

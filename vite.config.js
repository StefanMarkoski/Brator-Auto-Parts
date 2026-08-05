import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/*
 | Vite builds the ADMIN PANEL ONLY.
 |
 | The storefront serves the purchased theme's CSS and JS straight off disk with no
 | build step at all — that is what keeps it byte-identical to what was bought. If a
 | storefront entry ever appears in this list, that guarantee is gone.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/admin.css', 'resources/js/admin.js'],
            refresh: ['resources/views/admin/**'],
        }),
        tailwindcss(),
    ],
});

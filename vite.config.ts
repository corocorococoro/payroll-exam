import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

const wayfinderCommand =
    process.env.WAYFINDER_COMMAND ??
    (process.env.CI
        ? 'php artisan wayfinder:generate'
        : 'docker compose exec -T app php artisan wayfinder:generate');

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        ...(process.env.SKIP_WAYFINDER_GENERATION === '1'
            ? []
            : [
                  wayfinder({
                      formVariants: true,
                      // ローカルはDocker、CIはsetup-phpで用意したPHPを使う。
                      command: wayfinderCommand,
                  }),
              ]),
    ],
});

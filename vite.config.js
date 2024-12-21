import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { nodePolyfills } from 'vite-plugin-node-polyfills';

export default defineConfig({
    // server: {
    //     https: true, // Enable HTTPS
    //     hmr: {
    //         host: 'smartexecutive.test'
    //         // host: 'factually-clean-pig.ngrok-free.app',
               // detectTls: 'smartexecutive.test'
    //     },
    // },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            ssr: 'resources/js/ssr.js',
            refresh: true,
            // detectTls: 'https://factually-clean-pig.ngrok-free.app',
            // detectTls: 'https://smartexecutive.test'
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        nodePolyfills(),
    ],
});

import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
export default defineConfig({
    plugins: [vue()],
    resolve: { alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) } },
    build: { ssr: 'resources/js/seo-render.ts', outDir: '.bootstrap/seo-renderer', emptyOutDir: true },
});

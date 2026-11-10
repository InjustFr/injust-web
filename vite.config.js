import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import symfonyPlugin from 'vite-plugin-symfony';

const assetsDir = process.env.ASSETS_DIR ?? 'build';
const devPort = Number(process.env.VITE_DEV_PORT ?? 5178);

export default defineConfig({
    plugins: [vue(), symfonyPlugin()],
    base: `/${assetsDir}/`,
    server: {
        host: '0.0.0.0',
        port: 5178,
        strictPort: true,
        origin: `http://localhost:${devPort}`,
        cors: true,
    },
    build: {
        outDir: `public/${assetsDir}`,
        emptyOutDir: true,
        rollupOptions: {
            input: {
                site: './assets/site.js',
                admin: './assets/admin.js',
                auth: './assets/auth.js',
            },
        },
    },
    test: {
        include: ['assets/**/*.test.js', 'bin/**/*.test.mjs'],
        environment: 'node',
    },
});

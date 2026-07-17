import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
    server: {
        host: '127.0.0.1',
        port: 5174,
        strictPort: true,
        // Важно: разрешаем CORS для MCP
        cors: true,
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            'ag-grid-community/styles': path.resolve(__dirname, 'node_modules/ag-grid-community/styles'),
            mermaid: path.resolve(__dirname, 'node_modules/mermaid/dist/mermaid.core.mjs'),
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('ag-grid-community') || id.includes('ag-grid-vue3')) {
                        return 'vendor-ag-grid'
                    }

                    if (id.includes('grapesjs')) {
                        return 'vendor-grapesjs'
                    }

                    if (id.includes('node_modules/mermaid')) {
                        return 'vendor-mermaid'
                    }

                    if (id.includes('@tiptap/')) {
                        return 'vendor-tiptap'
                    }
                },
            },
        },
        chunkSizeWarningLimit: 1000,
    },
    optimizeDeps: {
        include: [
            'ag-grid-community',
            'ag-grid-vue3',
            'grapesjs',
            'grapesjs-preset-newsletter',
            'mermaid',
            '@tiptap/vue-3',
            '@tiptap/starter-kit',
        ],
    },
})

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
        port: 5173,
        strictPort: true,
        // Важно: разрешаем CORS для MCP
        cors: true,
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            'ag-grid-community/styles': path.resolve(__dirname, 'node_modules/ag-grid-community/styles'),
        },
    },
    optimizeDeps: {
        include: [
            'ag-grid-community',
            'ag-grid-vue3',
            'ag-grid-community/styles/ag-grid.css',
        ],
    },
})
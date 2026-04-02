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
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            // Добавляем алиас для AG Grid (на случай проблем с путями)
            'ag-grid-community/styles': path.resolve(__dirname, 'node_modules/ag-grid-community/styles'),
        },
    },
    // Добавляем оптимизацию для AG Grid
    optimizeDeps: {
        include: [
            'ag-grid-community',
            'ag-grid-vue3',
            'ag-grid-community/styles/ag-grid.css',
        ],
    },
})
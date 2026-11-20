
import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import tailwindcss from '@tailwindcss/vite'

// `--mode native` (apps de escritorio y celular) fija VITE_TARGET sin depender de un archivo .env,
// porque el .gitignore de la raíz ignora los .env.* y el archivo nunca llegaría al repositorio.
export default defineConfig(({ mode }) => ({
  define: mode === 'native' ? { 'import.meta.env.VITE_TARGET': JSON.stringify('native') } : {},
  plugins: [
    vue(),
    vueDevTools(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    // En desarrollo, las llamadas a /api van al backend de Laravel (php artisan serve).
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
}))

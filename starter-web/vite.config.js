import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    setupFiles: './src/test/setup.js',
    css: true,
    globals: true,
    // Playwright E2E vive en `e2e/` y no debe ejecutarse con Vitest.
    exclude: ['**/node_modules/**', '**/dist/**', 'e2e/**'],
  },
})

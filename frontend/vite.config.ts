/// <reference types="vitest/config" />
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
// Base path is configurable via APP_BASE so the same codebase can be served at
// a subfolder (local XAMPP: /irlvat/) or at a domain root (cPanel: /).
export default defineConfig({
  base: process.env.APP_BASE ?? '/irlvat/',
  plugins: [react(), tailwindcss()],
  server: {
    port: 5173,
    proxy: {
      // Proxy API calls to the Laravel backend during local dev (strip the subfolder).
      '/irlvat/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/irlvat/, ''),
      },
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    css: true,
  },
})

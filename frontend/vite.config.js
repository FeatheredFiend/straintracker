import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// The build lands in the Symfony app's public/app/, served by nginx/Apache
// as static files, with SpaController handing out index.html for every
// client-side route - one origin for app and API, like the warhammer app.
// `npm run dev` serves from / and proxies /api to the Docker stack.
export default defineConfig(({ command }) => ({
  plugins: [react()],
  base: command === 'build' ? '/app/' : '/',
  build: {
    outDir: '../public/app',
    emptyOutDir: true,
  },
  server: {
    proxy: {
      '/api': 'http://localhost:8083',
    },
  },
}))

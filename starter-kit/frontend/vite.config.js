import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import path from 'path'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig, loadEnv } from 'vite'
import { sentryVitePlugin } from '@sentry/vite-plugin'
const env = loadEnv('all', process.cwd())
// import { run } from 'vite-plugin-run'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.ts'],
      ssr: 'resources/js/ssr.ts',
      refresh: true
    }),
    tailwindcss(),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false
        }
      }
    }),
    sentryVitePlugin({
      org: env.VITE_SENTRY_ORG,
      project: env.VITE_SENTRY_PROJECT,
      release: {
        name: new Date().getTime().toString()
      },
      authToken: env.VITE_SENTRY_AUTH_TOKEN
    })
    // run([
    //   {
    //     name: 'wayfinder',
    //     run: ['php', 'artisan', 'wayfinder:generate'],
    //     pattern: ['routes/**/*.php', 'app/**/Http/**/*.php']
    //   }
    // ])
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './resources/js')
    }
  },
  build: {
    sourcemap: true
  }
})

// import {sentryVitePlugin} from '@sentry/vite-plugin'
import {defineConfig} from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: true
    }),
    tailwindcss(),
    // sentryVitePlugin({
    //   org: 'norman-huth',
    //   project: env.VITE_SENTRY_PROJECT,
    //   telemetry: false,
    //   release: {
    //     name: new Date().toISOString()
    //   },
    //   authToken: env.VITE_SENTRY_AUTH_TOKEN.trim()
    // })
  ],
  server: {
    watch: {
      ignored: ['**/storage/framework/views/**']
    }
  }
})

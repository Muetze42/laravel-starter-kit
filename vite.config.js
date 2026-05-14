// import {sentryVitePlugin} from '@sentry/vite-plugin'
import {defineConfig} from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import { bunny } from 'laravel-vite-plugin/fonts'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      fonts: [
        bunny('Inter Sans', {
          weights: [100, 200, 300, 400, 500, 600, 700, 800, 900],
        }),
        bunny('JetBrains Mono', {
          weights: [100, 200, 300, 400, 500, 600, 700, 800],
        }),
      ],
      refresh: true
    }),
    // inertia(),
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
    // vue({
    //   template: {
    //     transformAssetUrls: {
    //       base: null,
    //       includeAbsolute: false,
    //     },
    //   },
    // }),
    // wayfinder({
    //   formVariants: true,
    // }),
  ],
  // server: {
  //   watch: {
  //     ignored: ['**/storage/framework/views/**']
  //   }
  // }
})

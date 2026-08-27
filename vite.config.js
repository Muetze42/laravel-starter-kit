// import {realpathSync} from 'node:fs'
// import {basename} from 'node:path'

// import {sentryVitePlugin} from '@sentry/vite-plugin'
// import {loadEnv} from 'vite'
import {defineConfig} from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
// import { bunny } from 'laravel-vite-plugin/fonts'

// To enable Sentry, switch defineConfig to a callback and uncomment these lines.
//   const env = loadEnv(mode, process.cwd(), '')
//   const sentryRelease = basename(realpathSync(process.cwd()))

export default defineConfig({
  // build: {
  //   sourcemap: 'hidden',
  // },
  // define: {
  //   'import.meta.env.VITE_SENTRY_RELEASE': JSON.stringify(sentryRelease),
  // },
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      fonts: [
        // bunny('Inter', {
        //   weights: [100, 200, 300, 400, 500, 600, 700, 800, 900],
        // }),
        // bunny('JetBrains Mono', {
        //   weights: [100, 200, 300, 400, 500, 600, 700, 800],
        // }),
      ],
      refresh: true
    }),
    // inertia(),
    tailwindcss(),
    // sentryVitePlugin({
    //   authToken: env.SENTRY_AUTH_TOKEN?.trim(),
    //   org: env.SENTRY_ORG?.trim(),
    //   project: env.SENTRY_PROJECT?.trim(),
    //   release: {
    //     name: sentryRelease,
    //   },
    //   sourcemaps: {
    //     filesToDeleteAfterUpload: ['public/build/**/*.map', 'bootstrap/ssr/**/*.map'],
    //   },
    //   telemetry: false,
    // }),
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

import assert from 'node:assert/strict'
import { registerHooks } from 'node:module'
import { pathToFileURL } from 'node:url'

const [configPath, framework] = process.argv.slice(2)
const modules = {
    vite: `export const defineConfig = value => value; export const loadEnv = () => globalThis.frontendTestEnv`,
    'laravel-vite-plugin': `export default options => ({ name: 'laravel', options })`,
    '@tailwindcss/vite': `export default () => ({ name: 'tailwind' })`,
    '@vitejs/plugin-vue': `export default options => ({ name: 'vue', options })`,
    '@vitejs/plugin-react': `export default options => ({ name: 'react', options })`,
    '@sveltejs/vite-plugin-svelte': `export const svelte = options => ({ name: 'svelte', options })`,
    '@inertiajs/vite': `export default options => ({ name: 'inertia', options })`,
    '@sentry/vite-plugin': `export const sentryVitePlugin = options => ({ name: 'sentry', options })`
}
registerHooks({
    resolve(specifier, context, nextResolve) {
        if (Object.hasOwn(modules, specifier)) {
            return { url: `data:text/javascript,${encodeURIComponent(modules[specifier])}`, shortCircuit: true }
        }
        return nextResolve(specifier, context)
    }
})
const { default: configure } = await import(pathToFileURL(configPath).href)
for (const credentials of [false, true, 'partial']) {
    for (const isSsrBuild of [false, true]) {
        globalThis.frontendTestEnv = credentials === true
            ? { SENTRY_AUTH_TOKEN: ' test-token ', SENTRY_ORG: ' test-org ', SENTRY_PROJECT: ' test-project ', SENTRY_RELEASE: ' release-123 ' }
            : credentials === 'partial' ? { SENTRY_AUTH_TOKEN: ' test-token ' } : {}
        const config = configure({ mode: 'production', isSsrBuild })
        const plugins = config.plugins.flat(Infinity)
        assert.deepEqual(plugins.map(plugin => plugin.name).sort(), [framework, 'inertia', 'laravel', 'tailwind', 'existing-plugin', 'sentry'].sort())
        const plugin = name => plugins.find(item => item.name === name).options
        if (framework === 'vue') {
            assert.deepEqual(plugin('vue').template.transformAssetUrls, { base: null, includeAbsolute: false })
        }
        assert.deepEqual(plugin('laravel'), {
            input: ['resources/css/app.css', `resources/js/app.${framework === 'react' ? 'tsx' : 'ts'}`], refresh: true
        })
        assert.equal(plugin('inertia'), undefined)
        assert.deepEqual(config.build, { cssCodeSplit: false, sourcemap: 'hidden' })
        assert.deepEqual(config.server.watch, { ignored: ['**/storage/**'] })
        assert.equal(config.resolve.alias.existing, '/existing')
        assert.deepEqual(config.resolve.alias, { existing: '/existing' })
        assert.equal(config.server.host, undefined)
        assert.equal(config.define.EXISTING_FLAG, 'true')
        const sentry = plugin('sentry')
        assert.equal(plugins.at(-1).name, 'sentry')
        assert.equal(sentry.telemetry, false)
        assert.equal(sentry.release.create, credentials === true)
        assert.equal(sentry.release.finalize, credentials === true)
        assert.equal(JSON.parse(config.define['import.meta.env.VITE_SENTRY_RELEASE']), sentry.release.name)
        assert.ok(sentry.release.name)
        assert.equal(sentry.sourcemaps.disable, credentials === true ? false : 'disable-upload')
        const output = isSsrBuild ? 'bootstrap/ssr' : 'public/build'
        assert.deepEqual(sentry.sourcemaps.assets, [`${output}/**`])
        assert.deepEqual(sentry.sourcemaps.filesToDeleteAfterUpload, credentials === true ? [`${output}/**/*.map`] : [])
        if (credentials === true) {
            assert.equal(sentry.authToken, 'test-token')
            assert.equal(sentry.org, 'test-org')
            assert.equal(sentry.project, 'test-project')
            assert.equal(sentry.release.name, 'release-123')
        }
    }
}

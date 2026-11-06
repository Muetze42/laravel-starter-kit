import assert from 'node:assert/strict'
import { mkdtempSync, mkdirSync, symlinkSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { registerHooks } from 'node:module'
import { pathToFileURL } from 'node:url'

const [configPath, framework] = process.argv.slice(2)
const modules = {
    vite: `export const defineConfig = value => value; export const loadEnv = (mode, root, prefix) => Object.fromEntries(Object.entries(globalThis.frontendTestEnv).filter(([name]) => name.startsWith(prefix)))`,
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
const originalDirectory = process.cwd()
const releaseRoot = mkdtempSync(join(tmpdir(), 'sentry-release-'))
const originalNow = Date.now
Date.now = () => 1793980800123
for (const directory of ['20261106123456', '00123', 'app', 'release-123', '12.3']) mkdirSync(join(releaseRoot, directory))
symlinkSync(join(releaseRoot, '20261106123456'), join(releaseRoot, 'current'))
try {
    for (const [directory, expectedRelease] of [
        ['20261106123456', '20261106123456'],
        ['00123', '00123'],
        ['current', '20261106123456'],
        ['app', '1793980800123'],
        ['release-123', '1793980800123'],
        ['12.3', '1793980800123']
    ]) {
        process.chdir(join(releaseRoot, directory))
        for (const credentials of [false, true, 'partial']) {
            for (const isSsrBuild of [false, true]) {
                globalThis.frontendTestEnv =
                    credentials === true
                        ? { SENTRY_AUTH_TOKEN: 'test-token', SENTRY_ORG: 'test-org', SENTRY_PROJECT: 'test-project', SENTRY_RELEASE: 'release-123' }
                        : credentials === 'partial'
                          ? { SENTRY_AUTH_TOKEN: 'test-token' }
                          : {}
                for (const name of ['SENTRY_AUTH_TOKEN', 'SENTRY_ORG', 'SENTRY_PROJECT', 'SENTRY_RELEASE']) delete process.env[name]
                const config = configure({ mode: 'production', isSsrBuild })
                const plugins = config.plugins.flat(Infinity)
                assert.deepEqual(plugins.map((plugin) => plugin.name).sort(), [framework, 'inertia', 'laravel', 'tailwind', 'existing-plugin', 'sentry'].sort())
                const plugin = (name) => plugins.find((item) => item.name === name).options
                if (framework === 'vue') {
                    assert.deepEqual(plugin('vue').template.transformAssetUrls, { base: null, includeAbsolute: false })
                }
                assert.deepEqual(plugin('laravel'), {
                    input: ['resources/css/app.css', `resources/js/app.${framework === 'react' ? 'tsx' : 'ts'}`],
                    refresh: true
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
                assert.deepEqual(sentry, {
                    release: { name: expectedRelease },
                    sourcemaps: { filesToDeleteAfterUpload: ['public/build/**/*.map', 'bootstrap/ssr/**/*.map'] },
                    telemetry: false
                })
                assert.deepEqual(config.define, { EXISTING_FLAG: 'true' })
                for (const name of ['SENTRY_AUTH_TOKEN', 'SENTRY_ORG', 'SENTRY_PROJECT', 'SENTRY_RELEASE']) {
                    assert.equal(process.env[name], globalThis.frontendTestEnv[name])
                }
            }
        }
    }
} finally {
    process.chdir(originalDirectory)
    Date.now = originalNow
    rmSync(releaseRoot, { recursive: true, force: true })
}

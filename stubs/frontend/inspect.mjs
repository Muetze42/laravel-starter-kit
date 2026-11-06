import { createRequire } from 'node:module'
import { readFileSync, readdirSync } from 'node:fs'
import { resolve, relative, dirname } from 'node:path'

// Use the parser shipped with the project's Vite 8 installation, without evaluating configuration.
const root = process.cwd()
const require = createRequire(resolve(root, 'package.json'))
const viteRequire = createRequire(require.resolve('vite/package.json'))
const { parseSync } = await import(viteRequire.resolve('rolldown/utils'))
const options = JSON.parse(process.argv[2])
const changes = {}
const fail = (file, reason) => {
    throw new Error(`${file}: ${reason}`)
}
const parse = (file, source) => {
    const parsed = parseSync(file, source)
    if (parsed.errors.length) fail(file, 'Invalid syntax; fix it before installing.')
    return parsed.program
}
const key = (property) => property.key?.name ?? property.key?.value
const property = (object, name, file) => {
    if (object?.type !== 'ObjectExpression') fail(file, 'Use a literal configuration object.')
    if (object.properties.some((p) => p.type !== 'Property' || p.computed)) fail(file, 'Spread/computed configuration is unsupported; use explicit properties.')
    const found = object.properties.filter((p) => key(p) === name)
    if (found.length > 1) fail(file, `Duplicate ${name} properties are ambiguous.`)
    return found[0]?.value
}
const apply = (source, edits) => {
    for (const [start, end, text] of edits.sort((a, b) => b[0] - a[0])) source = source.slice(0, start) + text + source.slice(end)
    return source
}
const walk = (node, visit) => {
    if (!node || typeof node !== 'object') return
    if (typeof node.type === 'string') visit(node)
    for (const value of Object.values(node)) {
        if (Array.isArray(value)) value.forEach((item) => walk(item, visit))
        else if (value && typeof value === 'object') walk(value, visit)
    }
}
const viteFile = 'vite.config.js'
// Remove the starter's obsolete Sentry examples before adding working configuration.
const source = readFileSync(viteFile, 'utf8')
    .replace(/^  \/\/ server: \{\n  \/\/   watch: \{\n  \/\/     ignored: \['\*\*\/storage\/framework\/views\/\*\*'\]\n  \/\/   \}\n  \/\/ \}\n/m, '')
    .replace(/^\/\/ import \{(?:realpathSync|basename|sentryVitePlugin|loadEnv)\}.*\n/gm, '')
    .replace(/^\/\/ To enable Sentry.*\n(?:\/\/.*\n)*/m, '')
    .replace(/^  \/\/ build: \{\n  \/\/   sourcemap: 'hidden',\n  \/\/ \},\n/m, '')
    .replace(/^  \/\/ define: \{\n  \/\/   'import\.meta\.env\.VITE_SENTRY_RELEASE': JSON\.stringify\(sentryRelease\),\n  \/\/ \},\n/m, '')
    .replace(/^    \/\/ sentryVitePlugin\(\{\n(?:    \/\/.*\n)*?    \/\/ \}\),\n/m, '')
const ast = parse(viteFile, source)
const exported = ast.body.filter((n) => n.type === 'ExportDefaultDeclaration')
const call = exported[0]?.declaration
if (exported.length !== 1 || call?.type !== 'CallExpression' || call.callee.name !== 'defineConfig' || call.arguments.length !== 1)
    fail(viteFile, 'Use export default defineConfig({ ... }); callbacks are unsupported.')
const object = call.arguments[0]
const plugins = property(object, 'plugins', viteFile)
if (plugins?.type !== 'ArrayExpression' || plugins.elements.some((n) => !n || n.type === 'SpreadElement'))
    fail(viteFile, 'Use a literal plugins array without spreads.')
const imports = ast.body.filter((n) => n.type === 'ImportDeclaration')
for (const entry of imports) {
    if (/inertia|plugin-vue|plugin-react|plugin-svelte|@sentry\/vite-plugin/.test(entry.source.value))
        fail(viteFile, 'A framework or Inertia plugin already exists; integrate it manually.')
    if (entry.source.value.startsWith('.')) fail(viteFile, 'Local configuration modules need manual TypeScript migration.')
}
const laravelImport = imports.find((n) => n.source.value === 'laravel-vite-plugin')?.specifiers.find((n) => n.type === 'ImportDefaultSpecifier')?.local.name
const laravelCalls = plugins.elements.filter((n) => n.type === 'CallExpression' && n.callee.name === laravelImport)
if (laravelCalls.length !== 1) fail(viteFile, 'Expected exactly one Laravel plugin call.')
const buildDirectory = property(laravelCalls[0].arguments[0], 'buildDirectory', viteFile)
if (buildDirectory && buildDirectory.value !== 'build') fail(viteFile, 'Custom Laravel build directories require manual integration.')
const build = property(object, 'build', viteFile)
const outDir = build && property(build, 'outDir', viteFile)
if (outDir && outDir.value !== 'public/build') fail(viteFile, 'Custom build.outDir requires manual integration so rollback can protect existing artifacts.')
const input = property(laravelCalls[0].arguments[0], 'input', viteFile)
const inputs = input?.type === 'ArrayExpression' ? input.elements : [input]
if (inputs.some((n) => n?.type !== 'Literal' || typeof n.value !== 'string')) fail(viteFile, 'Laravel input must be a string or an array of string literals.')
if (inputs.filter((n) => n.value === 'resources/js/app.js').length !== 1) fail(viteFile, 'Expected resources/js/app.js exactly once in Laravel inputs.')
let edits = inputs.filter((n) => n.value === 'resources/js/app.js').map((n) => [n.start, n.end, JSON.stringify(options.entry)])

const additions = new Map()
const setProperty = (target, name, value) => {
    const existing = property(target, name, viteFile)
    if (existing) {
        edits.push([existing.start, existing.end, value])
        return
    }
    if (!additions.has(target)) additions.set(target, [])
    additions.get(target).push(`${JSON.stringify(name)}: ${value}`)
}
if (build) setProperty(build, 'sourcemap', "'hidden'")
else setProperty(object, 'build', "{ sourcemap: 'hidden' }")
const define = property(object, 'define', viteFile)
const releaseDefine = 'JSON.stringify(sentryRelease)'
if (define) setProperty(define, 'import.meta.env.VITE_SENTRY_RELEASE', releaseDefine)
else setProperty(object, 'define', `{ 'import.meta.env.VITE_SENTRY_RELEASE': ${releaseDefine} }`)
edits.push([laravelCalls[0].end, laravelCalls[0].end, `,\n    inertia()`])
// Append last so Sentry sees the output from all existing build plugins.
edits.push([
    plugins.end - 1,
    plugins.end - 1,
    `${
        plugins.elements.length &&
        !source
            .slice(plugins.elements.at(-1).end, plugins.end - 1)
            .trimStart()
            .startsWith(',')
            ? ','
            : ''
    }
    ${options.pluginExpression},
    sentryVitePlugin({
      authToken: env.SENTRY_AUTH_TOKEN?.trim() ?? '',
      org: env.SENTRY_ORG?.trim() ?? '',
      project: env.SENTRY_PROJECT?.trim() ?? '',
      release: { name: sentryRelease, create: uploadSourcemaps, finalize: uploadSourcemaps },
      sourcemaps: {
        disable: uploadSourcemaps ? false : 'disable-upload',
        assets: [outputDirectory + '/**'],
        filesToDeleteAfterUpload: uploadSourcemaps
          ? [outputDirectory + '/**/*.map'] : []
      },
      telemetry: false
    })
`
])
edits.push([
    object.start,
    object.start,
    `({ mode, isSsrBuild }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const outputDirectory = isSsrBuild ? 'bootstrap/ssr' : 'public/build'
  const sentryRelease = env.SENTRY_RELEASE?.trim() || basename(realpathSync(process.cwd()))
  const uploadSourcemaps = Boolean(env.SENTRY_AUTH_TOKEN?.trim() && env.SENTRY_ORG?.trim() && env.SENTRY_PROJECT?.trim())

  return `
])
edits.push([object.end, object.end, '\n}'])
walk(ast, (n) => {
    if (
        n.type === 'Identifier' &&
        [
            ...parse('framework.js', options.pluginImport).body[0].specifiers.map((specifier) => specifier.local.name),
            'inertia',
            'env',
            'outputDirectory',
            'sentryVitePlugin',
            'sentryRelease',
            'uploadSourcemaps',
            'loadEnv',
            'basename',
            'realpathSync'
        ].includes(n.name)
    )
        fail(viteFile, `Reserved installer binding ${n.name} already exists.`)
})
const viteImport = imports.find((entry) => entry.source.value === 'vite')
if (!viteImport || !viteImport.specifiers.some((specifier) => specifier.imported?.name === 'defineConfig')) {
    fail(viteFile, 'Expected a named defineConfig import from Vite.')
}
const viteImports = viteImport.specifiers.map((specifier) => {
    if (specifier.type === 'ImportDefaultSpecifier') return specifier.local.name
    if (specifier.type !== 'ImportSpecifier') fail(viteFile, 'Namespace Vite imports require manual integration.')
    return specifier.imported.name === specifier.local.name
        ? specifier.local.name
        : `${specifier.imported.name} as ${specifier.local.name}`
})
if (viteImport.specifiers.some((specifier) => specifier.type !== 'ImportSpecifier')) {
    fail(viteFile, 'Use named Vite imports before installing.')
}
edits.push([viteImport.start, viteImport.end, `import { ${[...viteImports, 'loadEnv'].join(', ')} } from 'vite'`])
for (const [target, properties] of additions) {
    const last = target.properties.at(-1)
    if (last && !source.slice(last.end, target.end - 1).trimStart().startsWith(',')) {
        edits.push([last.end, last.end, ','])
    }
    edits.push([target.end - 1, target.end - 1, `\n${properties.join(',\n')},\n`])
}
changes['vite.config.ts'] =
    "import { realpathSync } from 'node:fs'\n" +
    "import { basename } from 'node:path'\n" +
    "import { sentryVitePlugin } from '@sentry/vite-plugin'\n" +
    `${options.pluginImport}\nimport inertia from '@inertiajs/vite'\n` +
    apply(source, edits)
const generated = changes['vite.config.ts']
const objectEdits = []
const generatedAst = parse('vite.config.ts', generated)
const generatedImports = generatedAst.body.filter((node) => node.type === 'ImportDeclaration' && node.specifiers.length > 0)
const sortedImports = [...generatedImports].sort((left, right) => left.source.value.localeCompare(right.source.value))
for (const [index, node] of generatedImports.entries()) {
    const sorted = sortedImports[index]
    objectEdits.push([node.start, node.end, generated.slice(sorted.start, sorted.end)])
}
walk(generatedAst, (node) => {
    if (node.type === 'ObjectExpression' && node.properties.length > 0) {
        objectEdits.push([node.start + 1, node.start + 1, '\n'])
        objectEdits.push([node.end - 1, node.end - 1, '\n'])
    }
})
changes['vite.config.ts'] = apply(generated, objectEdits)
changes[viteFile] = null
const entryFile = 'resources/js/app.js'
const entrySource = readFileSync(entryFile, 'utf8')
if (parse(entryFile, entrySource).body.length > 0) fail(entryFile, 'Existing application code requires manual integration before installing.')
changes[entryFile] = null
const migrating = new Set([entryFile])
// Reject unhandled consumers rather than leaving references to removed modules.
const scan = (directory) => {
    for (const item of readdirSync(directory, { withFileTypes: true })) {
        const file = `${directory}/${item.name}`
        if (item.isSymbolicLink()) fail(file, 'Symlink source trees are unsupported.')
        if (item.isDirectory()) {
            scan(file)
            continue
        }
        if (!/\.(js|ts|tsx|jsx|vue|svelte)$/.test(file) || migrating.has(file)) continue
        const contents = readFileSync(file, 'utf8')
        walk(parse(file, contents), (n) => {
            if (n.source?.type !== 'Literal' || !n.source.value.startsWith('.')) return
            const consumer = relative(root, resolve(dirname(resolve(root, file)), n.source.value))
            if (migrating.has(consumer) || migrating.has(consumer + '.js')) fail(file, 'Imports a migrated module; migrate this consumer manually first.')
        })
    }
}
scan('resources/js')
process.stdout.write(JSON.stringify(changes))

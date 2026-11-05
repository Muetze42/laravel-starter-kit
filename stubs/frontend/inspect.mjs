import { createRequire } from 'node:module'
import { readFileSync, existsSync, readdirSync } from 'node:fs'
import { resolve, relative, dirname, extname } from 'node:path'

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
  if (object.properties.some((p) => p.type !== 'Property' || p.computed))
    fail(file, 'Spread/computed configuration is unsupported; use explicit properties.')
  const found = object.properties.filter((p) => key(p) === name)
  if (found.length > 1) fail(file, `Duplicate ${name} properties are ambiguous.`)
  return found[0]?.value
}
const apply = (source, edits) => {
  for (const [start, end, text] of edits.sort((a, b) => b[0] - a[0]))
    source = source.slice(0, start) + text + source.slice(end)
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
  .replace(/^\/\/ import \{(?:realpathSync|basename|sentryVitePlugin|loadEnv)\}.*\n/gm, '')
  .replace(/^\/\/ To enable Sentry.*\n(?:\/\/.*\n)*/m, '')
  .replace(/^  \/\/ build: \{\n  \/\/   sourcemap: 'hidden',\n  \/\/ \},\n/m, '')
  .replace(
    /^  \/\/ define: \{\n  \/\/   'import\.meta\.env\.VITE_SENTRY_RELEASE': JSON\.stringify\(sentryRelease\),\n  \/\/ \},\n/m,
    ''
  )
  .replace(/^    \/\/ sentryVitePlugin\(\{\n(?:    \/\/.*\n)*?    \/\/ \}\),\n/m, '')
const ast = parse(viteFile, source)
const exported = ast.body.filter((n) => n.type === 'ExportDefaultDeclaration')
const call = exported[0]?.declaration
if (
  exported.length !== 1 ||
  call?.type !== 'CallExpression' ||
  call.callee.name !== 'defineConfig' ||
  call.arguments.length !== 1
)
  fail(viteFile, 'Use export default defineConfig({ ... }); callbacks are unsupported.')
const object = call.arguments[0]
const plugins = property(object, 'plugins', viteFile)
if (
  plugins?.type !== 'ArrayExpression' ||
  plugins.elements.some((n) => !n || n.type === 'SpreadElement')
)
  fail(viteFile, 'Use a literal plugins array without spreads.')
const imports = ast.body.filter((n) => n.type === 'ImportDeclaration')
for (const entry of imports) {
  if (/inertia|plugin-vue|plugin-react|plugin-svelte|@sentry\/vite-plugin/.test(entry.source.value))
    fail(viteFile, 'A framework or Inertia plugin already exists; integrate it manually.')
  if (entry.source.value.startsWith('.'))
    fail(viteFile, 'Local configuration modules need manual TypeScript migration.')
}
const laravelImport = imports
  .find((n) => n.source.value === 'laravel-vite-plugin')
  ?.specifiers.find((n) => n.type === 'ImportDefaultSpecifier')?.local.name
const laravelCalls = plugins.elements.filter(
  (n) => n.type === 'CallExpression' && n.callee.name === laravelImport
)
if (laravelCalls.length !== 1) fail(viteFile, 'Expected exactly one Laravel plugin call.')
const buildDirectory = property(laravelCalls[0].arguments[0], 'buildDirectory', viteFile)
if (buildDirectory && buildDirectory.value !== 'build')
  fail(viteFile, 'Custom Laravel build directories require manual integration.')
const build = property(object, 'build', viteFile)
const outDir = build && property(build, 'outDir', viteFile)
if (outDir && outDir.value !== 'public/build')
  fail(
    viteFile,
    'Custom build.outDir requires manual integration so rollback can protect existing artifacts.'
  )
const input = property(laravelCalls[0].arguments[0], 'input', viteFile)
const inputs = input?.type === 'ArrayExpression' ? input.elements : [input]
if (inputs.some((n) => n?.type !== 'Literal' || typeof n.value !== 'string'))
  fail(viteFile, 'Laravel input must be a string or an array of string literals.')
if (inputs.filter((n) => n.value === 'resources/js/app.js').length !== 1)
  fail(viteFile, 'Expected resources/js/app.js exactly once in Laravel inputs.')
let edits = inputs
  .filter((n) => n.value === 'resources/js/app.js')
  .map((n) => [n.start, n.end, JSON.stringify(options.entry)])
edits.push([
  plugins.start + 1,
  plugins.start + 1,
  `\n    ${options.pluginExpression},\n    frontendInertia({ ssr: { host: '127.0.0.1' } }),`
])
const setProperty = (target, name, value) => {
  const existing = property(target, name, viteFile)
  edits.push(
    existing
      ? [existing.start, existing.end, value]
      : [target.start + 1, target.start + 1, `\n${JSON.stringify(name)}: ${value},`]
  )
}
if (build) setProperty(build, 'sourcemap', "'hidden'")
else setProperty(object, 'build', "{ sourcemap: 'hidden' }")
const server = property(object, 'server', viteFile)
if (server) setProperty(server, 'host', "'127.0.0.1'")
else setProperty(object, 'server', "{ host: '127.0.0.1' }")
const define = property(object, 'define', viteFile)
const releaseDefine = 'JSON.stringify(frontendSentryRelease)'
if (define) setProperty(define, 'import.meta.env.VITE_SENTRY_RELEASE', releaseDefine)
else setProperty(object, 'define', `{ 'import.meta.env.VITE_SENTRY_RELEASE': ${releaseDefine} }`)
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
    frontendSentry({
      authToken: frontendEnv.SENTRY_AUTH_TOKEN?.trim() ?? '',
      org: frontendEnv.SENTRY_ORG?.trim() ?? '',
      project: frontendEnv.SENTRY_PROJECT?.trim() ?? '',
      release: { name: frontendSentryRelease, create: frontendSentryUpload, finalize: frontendSentryUpload },
      sourcemaps: {
        disable: frontendSentryUpload ? false : 'disable-upload',
        assets: [isSsrBuild ? 'bootstrap/ssr/**' : 'public/build/**'],
        filesToDeleteAfterUpload: frontendSentryUpload
          ? [isSsrBuild ? 'bootstrap/ssr/**/*.map' : 'public/build/**/*.map'] : []
      },
      telemetry: false
    })
`
])
edits.push([
  object.start,
  object.start,
  `({ mode, isSsrBuild }) => {
  const frontendEnv = frontendLoadEnv(mode, process.cwd(), '')
  const frontendSentryRelease = frontendEnv.SENTRY_RELEASE?.trim() || frontendBasename(frontendRealpath(process.cwd()))
  const frontendSentryUpload = Boolean(frontendEnv.SENTRY_AUTH_TOKEN?.trim() && frontendEnv.SENTRY_ORG?.trim() && frontendEnv.SENTRY_PROJECT?.trim())
  return `
])
edits.push([object.end, object.end, '\n}'])
const resolveConfig = property(object, 'resolve', viteFile)
const alias = resolveConfig && property(resolveConfig, 'alias', viteFile)
const aliasValue = "frontendFilePath(new URL('./resources/js', import.meta.url))"
if (!resolveConfig)
  edits.push([
    object.start + 1,
    object.start + 1,
    `\n  resolve: { alias: { '@': ${aliasValue} } },`
  ])
else if (!alias)
  edits.push([resolveConfig.start + 1, resolveConfig.start + 1, `alias: { '@': ${aliasValue} },`])
else {
  const existing = property(alias, '@', viteFile)
  if (existing) {
    const text = source.slice(existing.start, existing.end)
    if (![aliasValue, "new URL('./resources/js', import.meta.url).pathname"].includes(text))
      fail(viteFile, 'The @ alias must point to resources/js; reconcile it before installing.')
  } else edits.push([alias.start + 1, alias.start + 1, `'@': ${aliasValue},`])
}
walk(ast, (n) => {
  if (
    n.type === 'Identifier' &&
    [
      'frontendFramework',
      'frontendInertia',
      'frontendFilePath',
      'frontendEnv',
      'frontendSentry',
      'frontendSentryRelease',
      'frontendSentryUpload',
      'frontendLoadEnv',
      'frontendBasename',
      'frontendRealpath'
    ].includes(n.name)
  )
    fail(viteFile, `Reserved installer binding ${n.name} already exists.`)
})
const pathImport =
  alias && property(alias, '@', viteFile)
    ? ''
    : "import { fileURLToPath as frontendFilePath } from 'node:url'\n"
changes['vite.config.ts'] =
  "import { realpathSync as frontendRealpath } from 'node:fs'\n" +
  "import { basename as frontendBasename } from 'node:path'\n" +
  "import { loadEnv as frontendLoadEnv } from 'vite'\n" +
  "import { sentryVitePlugin as frontendSentry } from '@sentry/vite-plugin'\n" +
  `${pathImport}${options.pluginImport}\nimport frontendInertia from '@inertiajs/vite'\n` +
  apply(source, edits)
parse('vite.config.ts', changes['vite.config.ts'])
changes[viteFile] = null
const migrating = new Set()
const migrate = (file, target) => {
  if (migrating.has(file)) return
  migrating.add(file)
  if (!file.startsWith('resources/js/'))
    fail(file, 'Local modules outside resources/js require manual migration.')
  if (existsSync(target)) fail(target, 'Destination already exists.')
  const original = readFileSync(file, 'utf8')
  const tree = parse(file, original)
  const modifications = []
  walk(tree, (n) => {
    if (
      ['FunctionDeclaration', 'FunctionExpression', 'ArrowFunctionExpression'].includes(n.type) &&
      n.params.length
    )
      fail(
        file,
        'Function parameters need explicit TypeScript types; migrate this module manually.'
      )
    if (['ThisExpression', 'JSXElement', 'WithStatement'].includes(n.type))
      fail(file, 'This JavaScript shape requires manual TypeScript migration.')
    if (
      n.type === 'ImportExpression' ||
      (n.type === 'CallExpression' && ['require', 'eval'].includes(n.callee.name))
    )
      fail(file, 'Dynamic module loading requires manual migration.')
    if (
      n.type === 'ImportDeclaration' ||
      n.type === 'ExportNamedDeclaration' ||
      n.type === 'ExportAllDeclaration'
    ) {
      if (!n.source) return
      const spec = n.source.value
      if (spec.includes('inertia') || ['vue', 'react', 'react-dom', 'svelte'].includes(spec))
        fail(file, 'Existing framework initialization requires manual integration.')
      if (!spec.startsWith('.')) return
      const absolute = resolve(dirname(resolve(root, file)), spec)
      const resolved = existsSync(absolute) && extname(absolute) ? absolute : `${absolute}.js`
      const child = relative(root, resolved)
      if (extname(resolved) !== '.js') {
        if (!['.css', '.json', '.ts'].includes(extname(resolved)))
          fail(file, `Unsupported import ${spec}; migrate it manually.`)
        return
      }
      if (!existsSync(resolved)) fail(file, `Cannot resolve ${spec}.`)
      migrate(child, child.replace(/\.js$/, '.ts'))
      if (spec.endsWith('.js'))
        modifications.push([
          n.source.start,
          n.source.end,
          JSON.stringify(spec.slice(0, -3) + '.ts')
        ])
    }
  })
  changes[target] = apply(original, modifications)
  parse(target, changes[target])
  changes[file] = null
}
migrate('resources/js/app.js', 'resources/js/browser.ts')
changes['resources/js/browser.ts'] += '\nexport {}\n'
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
      if (migrating.has(consumer) || migrating.has(consumer + '.js'))
        fail(file, 'Imports a migrated module; migrate this consumer manually first.')
    })
  }
}
scan('resources/js')
process.stdout.write(JSON.stringify(changes))

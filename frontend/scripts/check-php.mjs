import fs from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'

const require = createRequire(import.meta.url)
const parser = require('php-parser')

const engine = new parser({
  parser: { extractDoc: false, suppressErrors: false, version: 803 },
  ast: { withPositions: true },
})

const root = path.resolve(process.cwd(), '..')
const targets = [
  'src/Api',
  'src/Core/Http/ApiRequest.php',
  'src/Core/Http/ApiResponse.php',
  'src/Core/Routing',
  'src/Core/Middleware',
  'src/Core/Support',
  'src/Domain/Auth',
  'src/Domain/Module',
  'src/Domain/User/PanelUser.php',
  'src/Public/Controllers/Api',
  'src/Modules',
  'src/routes/api.php',
  'console.php',
]

const files = []

function walk(target) {
  const full = path.join(root, target)

  if (!fs.existsSync(full)) {
    return
  }

  if (fs.statSync(full).isFile()) {
    if (full.endsWith('.php')) files.push(full)
    return
  }

  for (const entry of fs.readdirSync(full)) {
    walk(path.join(target, entry))
  }
}

targets.forEach(walk)

let failures = 0

for (const file of files) {
  const code = fs.readFileSync(file, 'utf8')

  try {
    engine.parseCode(code, file)
  } catch (error) {
    failures++
    console.log(`FAIL ${path.relative(root, file)}: ${error.message}`)
  }
}

console.log(`Parsed ${files.length} PHP files, ${failures} failure(s).`)
process.exit(failures === 0 ? 0 : 1)

import { chromium } from 'playwright'

const base = 'http://localhost:3000'
const user = process.env.SHOT_USER || 'admin'
const password = process.env.SHOT_PASS

if (!password) {
  console.error('Set SHOT_PASS to the admin password before running this script.')
  process.exit(2)
}

const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } })
const errors = []

page.on('console', (message) => {
  if (message.type() === 'error') errors.push(message.text())
})
page.on('pageerror', (error) => errors.push(String(error)))

await page.goto(`${base}/login`, { waitUntil: 'networkidle' })
await page.fill('#username', user)
await page.fill('#password', password)
await page.click('button[type="submit"]')
await page.waitForURL(`${base}/`, { timeout: 20000 })

await page.goto(`${base}/movies/create`, { waitUntil: 'networkidle' })
await page.fill('input[placeholder="Buscar película…"]', 'matrix')
await page.click('button:has-text("Buscar")')
await page.waitForSelector('text=Matrix', { timeout: 20000 })
await page.waitForTimeout(1500)
await page.screenshot({ path: '../docs/_media/tmdb-picker.png', fullPage: true })

console.log('saved tmdb-picker screenshot')
console.log(errors.length ? `console errors:\n${errors.join('\n')}` : 'no console errors')
await browser.close()
process.exit(errors.length ? 1 : 0)

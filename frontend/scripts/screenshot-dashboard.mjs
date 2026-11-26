import { chromium } from 'playwright'

const base = process.env.SHOT_BASE || 'http://localhost:3000'
const user = process.env.SHOT_USER || 'admin'
const password = process.env.SHOT_PASS || 'admin-test-pass'

const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } })
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
await page.waitForTimeout(2500)
await page.screenshot({ path: '../docs/_media/fork-panel-dashboard.png', fullPage: true })

console.log('saved dashboard screenshot')
console.log(errors.length ? `console errors:\n${errors.join('\n')}` : 'no console errors')

await browser.close()
process.exit(errors.length ? 1 : 0)

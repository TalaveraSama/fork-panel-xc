import { chromium } from 'playwright'

const base = 'http://81.181.198.198'
const password = process.env.SHOT_PASS

if (!password) {
  console.error('Set SHOT_PASS')
  process.exit(2)
}

const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1557, height: 500 } })
const errors = []

page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()) })
page.on('pageerror', (e) => errors.push(String(e)))

await page.goto(`${base}/login`, { waitUntil: 'networkidle' })
await page.fill('#username', 'admin')
await page.fill('#password', password)
await page.click('button[type="submit"]')
await page.waitForURL(`${base}/`, { timeout: 20000 })

await page.goto(`${base}/streams`, { waitUntil: 'networkidle' })
await page.waitForSelector('text=Galaxya TV', { timeout: 20000 })
await page.waitForTimeout(600)
await page.screenshot({ path: '../docs/_media/streams-status.png', fullPage: true })

const badge = page.locator('tbody tr').first().locator('.badge').filter({ hasText: /Online|Offline|Disabled/ }).first()
const text = (await badge.innerText()).trim()
const color = await badge.evaluate((el) => getComputedStyle(el).color)
console.log(`status=${text} color=${color}`)
console.log(errors.length ? `console errors:\n${errors.join('\n')}` : 'no console errors')
await browser.close()

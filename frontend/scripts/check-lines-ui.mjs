import { chromium } from 'playwright'

const base = 'http://localhost:3000'
const password = process.env.SHOT_PASS
const username = `uiline${Date.now().toString().slice(-6)}`

if (!password) {
  console.error('Set SHOT_PASS')
  process.exit(2)
}

const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } })
const errors = []

page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()) })
page.on('pageerror', (e) => errors.push(String(e)))

await page.goto(`${base}/login`, { waitUntil: 'networkidle' })
await page.fill('#username', 'admin')
await page.fill('#password', password)
await page.click('button[type="submit"]')
await page.waitForURL(`${base}/`, { timeout: 20000 })

await page.goto(`${base}/lines`, { waitUntil: 'networkidle' })
await page.click('a:has-text("Create Line")')
await page.waitForSelector('text=Create Line', { timeout: 10000 })

await page.fill('#username', username)
await page.fill('#password', 'Secret12345')
await page.fill('#exp_date', '2027-12-31')
await page.fill('#max_connections', '3')
await page.click('button[type="submit"]')

await page.waitForSelector('text=created', { timeout: 20000 })
await page.waitForTimeout(600)
const credentials = await page.locator('code').allInnerTexts()
console.log('created credentials:', credentials.slice(0, 2).join(' / '))
await page.screenshot({ path: '../docs/_media/line-created.png', fullPage: true })

await page.click('a:has-text("Open line")')
await page.waitForSelector(`text=${username}`, { timeout: 10000 })
await page.waitForTimeout(800)
await page.screenshot({ path: '../docs/_media/line-detail.png', fullPage: true })

console.log(`line detail loaded for ${username}`)
console.log(errors.length ? `console errors:\n${errors.join('\n')}` : 'no console errors')
await browser.close()
process.exit(errors.length ? 1 : 0)

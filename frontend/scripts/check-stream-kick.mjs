import { chromium } from 'playwright'

const base = 'http://81.181.198.198'
const password = process.env.SHOT_PASS

if (!password) {
  console.error('Set SHOT_PASS')
  process.exit(2)
}

const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } })
const errors = []

page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()) })
page.on('pageerror', (e) => errors.push(String(e)))
page.on('dialog', (dialog) => dialog.accept())

await page.goto(`${base}/login`, { waitUntil: 'networkidle' })
await page.fill('#username', 'admin')
await page.fill('#password', password)
await page.click('button[type="submit"]')
await page.waitForURL(`${base}/`, { timeout: 20000 })

await page.goto(`${base}/streams/1`, { waitUntil: 'networkidle' })
await page.click('button:has-text("Stats")')
await page.waitForSelector('text=Active connections', { timeout: 10000 })
await page.waitForTimeout(800)
await page.screenshot({ path: '../docs/_media/stream-kick.png', fullPage: true })

const bodyBefore = await page.locator('body').innerText()
const hasSession = bodyBefore.includes('uitest')
console.log('session visible:', hasSession)

if (hasSession) {
  await page.click('button:has-text("Kick")')
  await page.waitForTimeout(1200)
  const bodyAfter = await page.locator('body').innerText()
  console.log('no active viewers after kick:', bodyAfter.includes('No active viewers'))
}

console.log(errors.length ? `console errors:\n${errors.join('\n')}` : 'no console errors')
await browser.close()

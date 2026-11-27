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

await page.goto(`${base}/login`, { waitUntil: 'networkidle' })
await page.fill('#username', 'admin')
await page.fill('#password', password)
await page.click('button[type="submit"]')
await page.waitForURL(`${base}/`, { timeout: 20000 })

await page.goto(`${base}/streams/1`, { waitUntil: 'networkidle' })
await page.waitForSelector('text=Galaxya TV', { timeout: 20000 })

for (const [tab, file] of [['Stats', 'stream-stats.png'], ['Settings', 'stream-settings.png'], ['Logs', 'stream-logs.png']]) {
  await page.click(`button:has-text("${tab}")`)
  await page.waitForTimeout(900)
  await page.screenshot({ path: `../docs/_media/${file}`, fullPage: true })
  console.log(`captured ${tab}`)
}

const bodyText = await page.locator('body').innerText()
console.log('coming soon present:', /coming soon/i.test(bodyText))
console.log(errors.length ? `console errors:\n${errors.join('\n')}` : 'no console errors')
await browser.close()
process.exit(errors.length ? 1 : 0)

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
const streamRequests = []

page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()) })
page.on('pageerror', (e) => errors.push(String(e)))
page.on('response', (response) => {
  if (response.url().includes('/stream/')) {
    streamRequests.push(`${response.status()} ${response.url().slice(0, 80)}`)
  }
})

await page.goto(`${base}/login`, { waitUntil: 'networkidle' })
await page.fill('#username', 'admin')
await page.fill('#password', password)
await page.click('button[type="submit"]')
await page.waitForURL(`${base}/`, { timeout: 20000 })

await page.goto(`${base}/streams/1/player`, { waitUntil: 'networkidle' })
await page.waitForSelector('video', { timeout: 15000 })
await page.waitForTimeout(8000)

const video = await page.locator('video').evaluate((el) => ({
  readyState: el.readyState,
  networkState: el.networkState,
  error: el.error ? el.error.message : null,
  currentTime: el.currentTime,
}))

await page.screenshot({ path: '../docs/_media/stream-player.png', fullPage: true })
console.log('video:', JSON.stringify(video))
console.log('stream requests:', streamRequests.length)
console.log(streamRequests.slice(0, 5).join('\n'))
console.log(errors.length ? `console errors:\n${errors.join('\n')}` : 'no console errors')
await browser.close()

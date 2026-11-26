import { chromium } from 'playwright'

const base = 'http://localhost:4173'
const browser = await chromium.launch()
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } })

await context.addInitScript(() => {
  localStorage.setItem('fp_token', 'test-token')
  localStorage.removeItem('fp_dark_mode')
  localStorage.removeItem('fp_sidebar_collapsed')
})

const page = await context.newPage()

await page.route('**/api/auth/me', (route) => route.fulfill({
  status: 200, contentType: 'application/json',
  body: JSON.stringify({ user: { id: 1, username: 'admin' }, permissions: [], roles: ['admin'] }),
}))
await page.route('**/api/dashboard/stats', (route) => route.fulfill({
  status: 200, contentType: 'application/json',
  body: JSON.stringify({
    totalStreams: 2, onlineStreams: 1, totalUsers: 1, activeUsers: 1, totalConnections: 0,
    bandwidth: 0, cpu: 5, memory: 20, disk: 30,
    streamsByType: { live: 1, vod: 1, series: 0, radio: 0 },
    history: { labels: [], inbound: [], outbound: [], connections: [] },
  }),
}))
await page.route('**/api/dashboard/activity*', (route) => route.fulfill({
  status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }),
}))

await page.goto(`${base}/`, { waitUntil: 'networkidle' })
await page.waitForSelector('text=Dashboard')
await page.waitForTimeout(300)

const read = () => page.evaluate(() => ({
  darkClass: document.documentElement.classList.contains('dark'),
  stored: localStorage.getItem('fp_dark_mode'),
  bodyBg: getComputedStyle(document.body).backgroundColor,
  cardBg: getComputedStyle(document.querySelector('.card')).backgroundColor,
}))

const light = await read()
await page.locator('[aria-label="Switch to dark mode"]').click()
await page.waitForTimeout(400)
const dark = await read()
await page.screenshot({ path: '../docs/_media/dashboard-dark.png' })

await page.locator('[aria-label="Switch to light mode"]').click()
await page.waitForTimeout(400)
const back = await read()

console.log('light:', JSON.stringify(light))
console.log('dark :', JSON.stringify(dark))
console.log('back :', JSON.stringify(back))

const ok = light.darkClass === false
  && dark.darkClass === true
  && dark.stored === 'true'
  && dark.bodyBg !== light.bodyBg
  && dark.cardBg !== light.cardBg
  && back.darkClass === false
  && back.bodyBg === light.bodyBg

console.log(ok ? 'THEME_TOGGLE_OK' : 'THEME_TOGGLE_FAILED')
await browser.close()
process.exit(ok ? 0 : 1)

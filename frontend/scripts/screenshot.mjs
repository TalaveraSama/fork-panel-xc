import { chromium } from 'playwright'

const url = process.env.SHOT_URL || 'http://127.0.0.1:4173/login'
const out = process.env.SHOT_OUT || '../docs/_media/fork-panel-login.png'

const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } })
const errors = []

page.on('console', (message) => {
  if (message.type() === 'error') errors.push(message.text())
})
page.on('pageerror', (error) => errors.push(String(error)))

await page.goto(url, { waitUntil: 'networkidle' })
await page.waitForTimeout(600)
await page.screenshot({ path: out, fullPage: true })

console.log(`saved ${out}`)
console.log(errors.length ? `console errors:\n${errors.join('\n')}` : 'no console errors')

await browser.close()
process.exit(errors.length ? 1 : 0)

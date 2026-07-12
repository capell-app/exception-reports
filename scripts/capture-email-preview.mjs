import { chromium } from 'playwright'
import { pathToFileURL } from 'node:url'

const [, , htmlPath, screenshotPath] = process.argv

if (!htmlPath || !screenshotPath) {
  throw new Error('Usage: node capture-email-preview.mjs <html-path> <screenshot-path>')
}

const browser = await chromium.launch()
const page = await browser.newPage({
  viewport: {
    width: 760,
    height: 1100,
  },
  deviceScaleFactor: 1,
})

await page.goto(pathToFileURL(htmlPath).toString(), {
  waitUntil: 'load',
})

await page.screenshot({
  path: screenshotPath,
  fullPage: true,
})

await browser.close()
